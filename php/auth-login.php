<?php
/**
 * Manor Cares — Sign in endpoint
 *
 * Verifies credentials against the Railway Postgres `users` table and
 * issues a JWT (stored as an httpOnly cookie) on success.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/db.php';
require __DIR__ . '/jwt.php';

header('Content-Type: application/json; charset=utf-8');

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

    $stmt = $pdo->prepare('SELECT id, name, email, password_hash, plan FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    // Generic error message for both "no such user" and "wrong password"
    // to avoid leaking which emails are registered.
    if ($user === false || !password_verify($password, $user['password_hash'])) {
        mc_respond(false, 'Invalid email or password.', [], 401);
    }

    $token = mc_jwt_issue([
        'sub'   => (int) $user['id'],
        'email' => $user['email'],
        'name'  => $user['name'],
        'plan'  => $user['plan'],
    ], $authConfig);
    mc_jwt_set_cookie($token, $authConfig);

    mc_respond(true, "Welcome back, {$user['name']}!", [
        'user' => ['id' => (int) $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'plan' => $user['plan']],
    ]);
} catch (Throwable $e) {
    error_log('Manor Cares login error: ' . $e->getMessage());
    mc_respond(false, 'Sorry, sign-in is unavailable right now. Please try again later.', [], 500);
}
