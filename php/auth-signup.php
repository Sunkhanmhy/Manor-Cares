<?php
/**
 * Manor Cares — Sign up endpoint
 *
 * Creates a new user in the Railway Postgres database, hashes the password
 * with bcrypt, issues a JWT (stored as an httpOnly cookie) and returns JSON.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', 1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/security-headers.php';
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

// Honeypot anti-bot field
if (!empty($_POST['website'])) {
    mc_respond(true, 'Account created.');
}

$name     = trim((string) ($_POST['name'] ?? ''));
$email    = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$confirm  = (string) ($_POST['confirm_password'] ?? '');
$plan     = trim((string) ($_POST['plan'] ?? 'essential'));

$allowedPlans = ['essential', 'signature', 'estate', 'custom'];
if (!in_array($plan, $allowedPlans, true)) {
    $plan = 'essential';
}

$errors = [];
if ($name === '' || mb_strlen($name) > 120) {
    $errors[] = 'Please provide your full name.';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
    $errors[] = 'Please provide a valid email address.';
}
if (mb_strlen($password) < 8 || mb_strlen($password) > 200) {
    $errors[] = 'Password must be at least 8 characters long.';
}
if ($password !== $confirm) {
    $errors[] = 'Passwords do not match.';
}

if (!empty($errors)) {
    mc_respond(false, implode(' ', $errors), [], 422);
}

try {
    $pdo = mc_db();

    $check = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $check->execute(['email' => $email]);
    if ($check->fetch() !== false) {
        mc_respond(false, 'An account with that email already exists. Please sign in instead.', [], 409);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => $authConfig['bcrypt_cost']]);

    $insert = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, plan) VALUES (:name, :email, :password_hash, :plan) RETURNING id'
    );
    $insert->execute([
        'name'          => $name,
        'email'         => $email,
        'password_hash' => $hash,
        'plan'          => $plan,
    ]);
    $userId = (int) $insert->fetchColumn();

    $token = mc_jwt_issue(['sub' => $userId, 'email' => $email, 'name' => $name, 'plan' => $plan, 'role' => 'user'], $authConfig);
    mc_jwt_set_cookie($token, $authConfig);

    mc_respond(true, "Welcome, {$name}! Your account has been created.", [
        'user' => ['id' => $userId, 'name' => $name, 'email' => $email, 'plan' => $plan, 'role' => 'user'],
        'redirect' => 'user-dashboard.html',
    ]);
} catch (Throwable $e) {
    error_log('Manor Cares signup error: ' . $e->getMessage());
    mc_respond(false, 'Sorry, we could not create your account right now. Please try again later.', [], 500);
}
