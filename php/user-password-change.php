<?php
/**
 * Manor Cares — change the signed-in user's password
 */

declare(strict_types=1);

require __DIR__ . '/security-headers.php';
require __DIR__ . '/middleware.php';
require __DIR__ . '/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mc_json(false, 'Invalid request method.', [], 405);
}

$user = mc_require_auth();
mc_csrf_require();
$authConfig = require __DIR__ . '/auth-config.php';

$current = (string) ($_POST['current_password'] ?? '');
$new     = (string) ($_POST['new_password'] ?? '');
$confirm = (string) ($_POST['confirm_password'] ?? '');

if (mb_strlen($new) < 8 || mb_strlen($new) > 200) {
    mc_json(false, 'New password must be at least 8 characters long.', [], 422);
}
if ($new !== $confirm) {
    mc_json(false, 'New passwords do not match.', [], 422);
}

try {
    $pdo = mc_db();
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
    $stmt->execute(['id' => $user['id']]);
    $row = $stmt->fetch();

    if ($row === false || !password_verify($current, $row['password_hash'])) {
        mc_json(false, 'Current password is incorrect.', [], 401);
    }

    $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => $authConfig['bcrypt_cost']]);
    $update = $pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id');
    $update->execute(['hash' => $hash, 'id' => $user['id']]);

    mc_json(true, 'Password updated successfully.');
} catch (Throwable $e) {
    error_log('Manor Cares password change error: ' . $e->getMessage());
    mc_json(false, 'Could not update your password right now.', [], 500);
}
