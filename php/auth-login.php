<?php
/**
 * Manor Cares — Sign in endpoint
 *
 * Verifies credentials against the Postgres `users` table and issues a
 * JWT (stored as an httpOnly cookie) on success. Includes brute-force
 * lockout: 5 failed attempts locks the account for 15 minutes.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/security-headers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/jwt.php';

header('Content-Type: application/json; charset=utf-8');

const MC_MAX_LOGIN_ATTEMPTS = 5;
const MC_LOCKOUT_MINUTES = 15;

function mc_respond(bool $success, string $message, array $extra = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mc_respond(false, 'Invalid request method.', [], 405);
}

$authConfig = require __DIR__ . '/auth-config.php';

$email    = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    mc_respond(false, 'Please provide a valid email and password.', [], 422);
}

try {
    $pdo = mc_db();

    $stmt = $pdo->prepare('SELECT id, name, email, password_hash, plan, role, status, failed_login_attempts, locked_until FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user !== false && $user['locked_until'] !== null && strtotime((string) $user['locked_until']) > time()) {
        mc_respond(false, 'Too many failed attempts. Please try again in a few minutes.', [], 429);
    }

    // Generic error message for both "no such user" and "wrong password"
    // to avoid leaking which emails are registered.
    if ($user === false || !password_verify($password, $user['password_hash'])) {
        if ($user !== false) {
            $attempts = (int) $user['failed_login_attempts'] + 1;
            $lockUntil = $attempts >= MC_MAX_LOGIN_ATTEMPTS ? 'NOW() + INTERVAL \'' . MC_LOCKOUT_MINUTES . ' minutes\'' : 'NULL';
            $update = $pdo->prepare("UPDATE users SET failed_login_attempts = :attempts, locked_until = {$lockUntil} WHERE id = :id");
            $update->execute(['attempts' => $attempts, 'id' => $user['id']]);
        }
        mc_respond(false, 'Invalid email or password.', [], 401);
    }

    if ($user['status'] !== 'active') {
        mc_respond(false, 'This account has been suspended. Please contact support.', [], 403);
    }

    $reset = $pdo->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = :id');
    $reset->execute(['id' => $user['id']]);

    $token = mc_jwt_issue([
        'sub'   => (int) $user['id'],
        'email' => $user['email'],
        'name'  => $user['name'],
        'plan'  => $user['plan'],
        'role'  => $user['role'],
    ], $authConfig);
    mc_jwt_set_cookie($token, $authConfig);

    mc_respond(true, "Welcome back, {$user['name']}!", [
        'user' => [
            'id' => (int) $user['id'], 'name' => $user['name'], 'email' => $user['email'],
            'plan' => $user['plan'], 'role' => $user['role'],
        ],
        'redirect' => $user['role'] === 'admin' ? 'admin-dashboard.html' : 'user-dashboard.html',
    ]);
} catch (Throwable $e) {
    error_log('Manor Cares login error: ' . $e->getMessage());
    mc_respond(false, 'Sorry, sign-in is unavailable right now. Please try again later.', [], 500);
}
