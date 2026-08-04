<?php
/**
 * Manor Cares — cancel one of the signed-in user's bookings
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

$bookingId = (int) ($_POST['id'] ?? 0);
if ($bookingId <= 0) {
    mc_json(false, 'Invalid booking.', [], 422);
}

try {
    $pdo = mc_db();
    // RLS also enforces `user_id = current_user_id` — the explicit clause
    // keeps intent clear and works even without a non-owner DB role.
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = :id AND user_id = :uid AND status IN ('pending','confirmed') RETURNING id");
    $stmt->execute(['id' => $bookingId, 'uid' => $user['id']]);

    if ($stmt->fetch() === false) {
        mc_json(false, 'Booking not found or already resolved.', [], 404);
    }

    mc_json(true, 'Booking cancelled.');
} catch (Throwable $e) {
    error_log('Manor Cares booking cancel error: ' . $e->getMessage());
    mc_json(false, 'Could not cancel this booking right now.', [], 500);
}
