<?php
/**
 * Manor Cares — admin: recent audit log entries
 */

declare(strict_types=1);

require __DIR__ . '/security-headers.php';
require __DIR__ . '/middleware.php';

$admin = mc_require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    mc_json(false, 'Invalid request method.', [], 405);
}

try {
    $pdo = mc_db();
    $stmt = $pdo->query(
        "SELECT a.id, a.action, a.meta, a.created_at,
                actor.name AS actor_name, target.name AS target_name
         FROM audit_log a
         LEFT JOIN users actor ON actor.id = a.actor_user_id
         LEFT JOIN users target ON target.id = a.target_user_id
         ORDER BY a.created_at DESC LIMIT 50"
    );
    mc_json(true, 'OK', ['entries' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    error_log('Manor Cares admin audit log error: ' . $e->getMessage());
    mc_json(false, 'Could not load the audit log right now.', [], 500);
}
