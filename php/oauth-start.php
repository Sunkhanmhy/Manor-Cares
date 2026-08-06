<?php
/**
 * Manor Cares — OAuth sign-in: step 1 (redirect to provider via Supabase Auth)
 *
 * Google/GitHub OAuth apps are configured once in the Supabase Dashboard
 * (Authentication → Providers) — this app never needs the provider's own
 * client id/secret, only SUPABASE_URL + SUPABASE_ANON_KEY. Uses the OAuth
 * PKCE flow so the callback is a plain server-side GET with a `code` query
 * parameter (no JavaScript required to read a URL fragment).
 *
 * Usage: <a href="php/oauth-start.php?provider=google">Continue with Google</a>
 */

declare(strict_types=1);

require __DIR__ . '/security-headers.php';
require __DIR__ . '/oauth-session.php';

$allowedProviders = ['google', 'github'];
$provider = strtolower((string) ($_GET['provider'] ?? ''));

if (!in_array($provider, $allowedProviders, true)) {
    http_response_code(400);
    echo 'Unsupported OAuth provider.';
    exit;
}

$supabaseConfig = require __DIR__ . '/supabase-config.php';
if ($supabaseConfig['url'] === '' || $supabaseConfig['anon_key'] === '') {
    http_response_code(503);
    echo 'OAuth is not configured yet. Set SUPABASE_URL and SUPABASE_ANON_KEY in .env.';
    exit;
}

mc_oauth_start_session();

// PKCE: generate a code_verifier + S256 code_challenge, and a random state
// value for CSRF protection, all bound to this browser session.
$codeVerifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
$codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
$state = bin2hex(random_bytes(16));

$_SESSION['oauth_code_verifier'] = $codeVerifier;
$_SESSION['oauth_state'] = $state;
$_SESSION['oauth_provider'] = $provider;

$scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) ? 'https' : 'http';
$redirectTo = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/php/oauth-callback.php';

$authorizeUrl = $supabaseConfig['url'] . '/auth/v1/authorize?' . http_build_query([
    'provider'               => $provider,
    'redirect_to'            => $redirectTo,
    'code_challenge'         => $codeChallenge,
    'code_challenge_method'  => 's256',
    'state'                  => $state,
]);

header('Location: ' . $authorizeUrl, true, 302);
exit;
