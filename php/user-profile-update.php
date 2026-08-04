<?php
/**
 * Manor Cares — update the signed-in user's profile
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

$name    = trim((string) ($_POST['name'] ?? ''));
$phone   = trim((string) ($_POST['phone'] ?? ''));
$address = trim((string) ($_POST['address'] ?? ''));

$errors = [];
if ($name === '' || mb_strlen($name) > 120) {
    $errors[] = 'Please provide your full name.';
}
if (mb_strlen($phone) > 30) {
    $errors[] = 'Phone number is too long.';
}
if (mb_strlen($address) > 255) {
    $errors[] = 'Address is too long.';
}
if (!empty($errors)) {
    mc_json(false, implode(' ', $errors), [], 422);
}

try {
    $pdo = mc_db();
    $stmt = $pdo->prepare('UPDATE users SET name = :name, phone = :phone, address = :address, updated_at = NOW() WHERE id = :id');
    $stmt->execute([
        'name'    => $name,
        'phone'   => $phone !== '' ? $phone : null,
        'address' => $address !== '' ? $address : null,
        'id'      => $user['id'],
    ]);

    mc_json(true, 'Profile updated.', [
        'user' => ['id' => (int) $user['id'], 'name' => $name, 'phone' => $phone, 'address' => $address],
    ]);
} catch (Throwable $e) {
    error_log('Manor Cares profile update error: ' . $e->getMessage());
    mc_json(false, 'Could not update your profile right now.', [], 500);
}
