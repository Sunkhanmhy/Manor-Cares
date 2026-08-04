<?php
/**
 * Manor Cares — admin: update a user's role / status
 */

declare(strict_types=1);

require __DIR__ . '/security-headers.php';
require __DIR__ . '/middleware.php';
require __DIR__ . '/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mc_json(false, 'Invalid request method.', [], 405);
}

$admin = mc_require_admin();
mc_csrf_require();

$targetId = (int) ($_POST['id'] ?? 0);
$role     = trim((string) ($_POST['role'] ?? ''));
$status   = trim((string) ($_POST['status'] ?? ''));

if ($targetId <= 0) {
    mc_json(false, 'Invalid user.', [], 422);
}
if ($targetId === (int) $admin['id']) {
    mc_json(false, 'You cannot modify your own admin account from here.', [], 422);
}

$allowedRoles = ['user', 'admin'];
$allowedStatuses = ['active', 'suspended'];
if (!in_array($role, $allowedRoles, true) || !in_array($status, $allowedStatuses, true)) {
    mc_json(false, 'Invalid role or status value.', [], 422);
}

try {
    $pdo = mc_db();
    $stmt = $pdo->prepare('UPDATE users SET role = :role, status = :status, updated_at = NOW() WHERE id = :id RETURNING id, name, email, role, status');
    $stmt->execute(['role' => $role, 'status' => $status, 'id' => $targetId]);
    $row = $stmt->fetch();

    if ($row === false) {
        mc_json(false, 'User not found.', [], 404);
    }

    $audit = $pdo->prepare("INSERT INTO audit_log (actor_user_id, action, target_user_id, meta) VALUES (:actor, 'user.update', :target, :meta)");
    $audit->execute([
        'actor'  => $admin['id'],
        'target' => $targetId,
        'meta'   => json_encode(['role' => $role, 'status' => $status]),
    ]);

    mc_json(true, 'User updated.', ['user' => $row]);
} catch (Throwable $e) {
    error_log('Manor Cares admin user update error: ' . $e->getMessage());
    mc_json(false, 'Could not update this user right now.', [], 500);
}
