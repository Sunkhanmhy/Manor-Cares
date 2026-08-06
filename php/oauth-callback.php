<?php
/**
 * Manor Cares — OAuth sign-in: step 2 (Supabase Auth PKCE callback)
 *
 * Supabase redirects the browser back here with ?code=...&state=... after
 * the user approves access with Google/GitHub. We exchange that code (plus
 * our stored PKCE code_verifier) for a Supabase session, read the verified
 * email/name/avatar from it, upsert a local `users` row, and issue our own
 * `manor_auth` JWT cookie exactly like auth-login.php/auth-signup.php do —
 * so the rest of the app (dashboards, CSRF, RLS binding) doesn't need to
 * know or care that this particular sign-in started with an OAuth provider.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/security-headers.php';
require __DIR__ . '/oauth-session.php';
require __DIR__ . '/polyfills.php';
require __DIR__ . '/http-client.php';
require __DIR__ . '/db.php';
require __DIR__ . '/jwt.php';

function mc_oauth_fail(string $reason): void
{
    header('Location: ../create-account.html?oauth_error=' . rawurlencode($reason));
    exit;
}

mc_oauth_start_session();

$code  = (string) ($_GET['code'] ?? '');
$state = (string) ($_GET['state'] ?? '');

$expectedState    = $_SESSION['oauth_state'] ?? '';
$codeVerifier     = $_SESSION['oauth_code_verifier'] ?? '';
$provider         = $_SESSION['oauth_provider'] ?? '';
unset($_SESSION['oauth_state'], $_SESSION['oauth_code_verifier'], $_SESSION['oauth_provider']);

if ($code === '' || $state === '' || $expectedState === '' || !hash_equals($expectedState, $state) || $codeVerifier === '') {
    mc_oauth_fail('Sign-in could not be verified. Please try again.');
}

$supabaseConfig = require __DIR__ . '/supabase-config.php';
if ($supabaseConfig['url'] === '' || $supabaseConfig['anon_key'] === '') {
    mc_oauth_fail('OAuth is not configured.');
}

// Exchange the authorization code + PKCE verifier for a Supabase session.
try {
    $response = mc_http_post_json($supabaseConfig['url'] . '/auth/v1/token?grant_type=pkce', [
        'auth_code'     => $code,
        'code_verifier' => $codeVerifier,
    ], [
        'apikey' => $supabaseConfig['anon_key'],
    ]);
} catch (Throwable $e) {
    error_log('Manor Cares OAuth token exchange failed: ' . $e->getMessage());
    mc_oauth_fail('Sign-in failed. Please try again.');
}

$session = json_decode($response['body'], true);
if ($response['status'] < 200 || $response['status'] >= 300 || !is_array($session) || empty($session['access_token']) || empty($session['user'])) {
    error_log('Manor Cares OAuth token exchange error (HTTP ' . $response['status'] . '): ' . $response['body']);
    mc_oauth_fail('Sign-in failed. Please try again.');
}

$supabaseUser = $session['user'];
$email        = trim((string) ($supabaseUser['email'] ?? ''));
$metadata     = (array) ($supabaseUser['user_metadata'] ?? []);
$name         = trim((string) ($metadata['full_name'] ?? $metadata['name'] ?? explode('@', $email)[0]));
$avatarUrl    = trim((string) ($metadata['avatar_url'] ?? $metadata['picture'] ?? ''));
$oauthId      = (string) ($supabaseUser['id'] ?? '');

if ($email === '' || $oauthId === '') {
    mc_oauth_fail('Your provider account has no verified email address to sign in with.');
}

try {
    $pdo = mc_db();

    // Find by oauth identity first, then by email (to link an existing
    // password-based account the first time someone uses OAuth with it).
    $stmt = $pdo->prepare('SELECT id, name, email, plan, role, status FROM users WHERE (oauth_provider = :provider AND oauth_id = :oauth_id) OR email = :email LIMIT 1');
    $stmt->execute(['provider' => $provider, 'oauth_id' => $oauthId, 'email' => $email]);
    $user = $stmt->fetch();

    if ($user === false) {
        $insert = $pdo->prepare(
            'INSERT INTO users (name, email, plan, role, oauth_provider, oauth_id, avatar_url)
             VALUES (:name, :email, :plan, :role, :provider, :oauth_id, :avatar_url)
             RETURNING id, name, email, plan, role, status'
        );
        $insert->execute([
            'name'       => $name !== '' ? $name : $email,
            'email'      => $email,
            'plan'       => 'essential',
            'role'       => 'user',
            'provider'   => $provider,
            'oauth_id'   => $oauthId,
            'avatar_url' => $avatarUrl !== '' ? $avatarUrl : null,
        ]);
        $user = $insert->fetch();
    } else {
        // Link the OAuth identity to the existing account if not linked yet.
        $update = $pdo->prepare('UPDATE users SET oauth_provider = :provider, oauth_id = :oauth_id, last_login_at = NOW() WHERE id = :id');
        $update->execute(['provider' => $provider, 'oauth_id' => $oauthId, 'id' => $user['id']]);
    }

    if ($user['status'] !== 'active') {
        mc_oauth_fail('This account has been suspended. Please contact support.');
    }

    $authConfig = require __DIR__ . '/auth-config.php';
    $token = mc_jwt_issue([
        'sub'   => (int) $user['id'],
        'email' => $user['email'],
        'name'  => $user['name'],
        'plan'  => $user['plan'],
        'role'  => $user['role'],
    ], $authConfig);
    mc_jwt_set_cookie($token, $authConfig);

    header('Location: ../' . ($user['role'] === 'admin' ? 'admin-dashboard.html' : 'user-dashboard.html'));
    exit;
} catch (Throwable $e) {
    error_log('Manor Cares OAuth callback error: ' . $e->getMessage());
    mc_oauth_fail('Sign-in failed. Please try again.');
}
