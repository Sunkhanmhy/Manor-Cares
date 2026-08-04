<?php
/**
 * Manor Cares — admin: delete a user account
 *
 * Soft-deletes by default (status = 'deleted', email anonymised) to
 * preserve referential integrity of historical bookings/audit entries.
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
if ($targetId <= 0) {
    mc_json(false, 'Invalid user.', [], 422);
}
if ($targetId === (int) $admin['id']) {
    mc_json(false, 'You cannot delete your own admin account.', [], 422);
}

try {
    $pdo = mc_db();
    $stmt = $pdo->prepare(
        "UPDATE users SET status = 'deleted', email = 'deleted-' || id || '@manor-cares.invalid', updated_at = NOW() WHERE id = :id RETURNING id"
    );
    $stmt->execute(['id' => $targetId]);

    if ($stmt->fetch() === false) {
        mc_json(false, 'User not found.', [], 404);
    }

    $audit = $pdo->prepare("INSERT INTO audit_log (actor_user_id, action, target_user_id) VALUES (:actor, 'user.delete', :target)");
    $audit->execute(['actor' => $admin['id'], 'target' => $targetId]);

    mc_json(true, 'User account deleted.');
} catch (Throwable $e) {
    error_log('Manor Cares admin user delete error: ' . $e->getMessage());
    mc_json(false, 'Could not delete this user right now.', [], 500);
}
