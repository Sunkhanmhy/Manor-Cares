<?php
/**
 * Manor Cares — admin: list / update all bookings
 */

declare(strict_types=1);

require __DIR__ . '/security-headers.php';
require __DIR__ . '/middleware.php';
require __DIR__ . '/csrf.php';

$admin = mc_require_admin();
$pdo = mc_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query(
        "SELECT b.id, b.service_type, b.property_type, b.address, b.preferred_date, b.status, b.created_at,
                u.id AS user_id, u.name AS user_name, u.email AS user_email
         FROM bookings b JOIN users u ON u.id = b.user_id
         ORDER BY b.created_at DESC LIMIT 100"
    );
    mc_json(true, 'OK', ['bookings' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mc_csrf_require();

    $id = (int) ($_POST['id'] ?? 0);
    $status = trim((string) ($_POST['status'] ?? ''));
    $allowed = ['pending', 'confirmed', 'completed', 'cancelled'];

    if ($id <= 0 || !in_array($status, $allowed, true)) {
        mc_json(false, 'Invalid booking or status.', [], 422);
    }

    $stmt = $pdo->prepare('UPDATE bookings SET status = :status, updated_at = NOW() WHERE id = :id RETURNING id, status');
    $stmt->execute(['status' => $status, 'id' => $id]);
    $row = $stmt->fetch();

    if ($row === false) {
        mc_json(false, 'Booking not found.', [], 404);
    }

    $audit = $pdo->prepare("INSERT INTO audit_log (actor_user_id, action, meta) VALUES (:actor, 'booking.status_update', :meta)");
    $audit->execute(['actor' => $admin['id'], 'meta' => json_encode(['booking_id' => $id, 'status' => $status])]);

    mc_json(true, 'Booking updated.', ['booking' => $row]);
}

mc_json(false, 'Invalid request method.', [], 405);
