<?php
/**
 * Manor Cares — list / create bookings for the signed-in user
 *
 * GET  -> list the current user's bookings (newest first)
 * POST -> create a new booking request
 */

declare(strict_types=1);

require __DIR__ . '/security-headers.php';
require __DIR__ . '/middleware.php';
require __DIR__ . '/csrf.php';

$user = mc_require_auth();
$pdo = mc_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT id, service_type, property_type, address, preferred_date, notes, status, created_at FROM bookings WHERE user_id = :uid ORDER BY created_at DESC');
    $stmt->execute(['uid' => $user['id']]);
    mc_json(true, 'OK', ['bookings' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mc_csrf_require();

    $serviceType   = trim((string) ($_POST['service_type'] ?? ''));
    $propertyType  = trim((string) ($_POST['property_type'] ?? ''));
    $address       = trim((string) ($_POST['address'] ?? ''));
    $preferredDate = trim((string) ($_POST['preferred_date'] ?? ''));
    $notes         = trim((string) ($_POST['notes'] ?? ''));

    if ($serviceType === '' || mb_strlen($serviceType) > 60) {
        mc_json(false, 'Please choose a service type.', [], 422);
    }
    if ($address === '' || mb_strlen($address) > 255) {
        mc_json(false, 'Please provide a service address.', [], 422);
    }
    $dateValue = null;
    if ($preferredDate !== '') {
        $ts = strtotime($preferredDate);
        if ($ts === false) {
            mc_json(false, 'Please provide a valid date.', [], 422);
        }
        $dateValue = date('Y-m-d', $ts);
    }

    try {
        $insert = $pdo->prepare(
            'INSERT INTO bookings (user_id, service_type, property_type, address, preferred_date, notes) VALUES (:uid, :service_type, :property_type, :address, :preferred_date, :notes) RETURNING id, status, created_at'
        );
        $insert->execute([
            'uid'            => $user['id'],
            'service_type'   => $serviceType,
            'property_type'  => $propertyType !== '' ? $propertyType : null,
            'address'        => $address,
            'preferred_date' => $dateValue,
            'notes'          => $notes !== '' ? mb_substr($notes, 0, 2000) : null,
        ]);
        $row = $insert->fetch();

        mc_json(true, 'Booking request submitted.', ['booking' => $row], 201);
    } catch (Throwable $e) {
        error_log('Manor Cares booking create error: ' . $e->getMessage());
        mc_json(false, 'Could not submit your booking right now.', [], 500);
    }
}

mc_json(false, 'Invalid request method.', [], 405);
