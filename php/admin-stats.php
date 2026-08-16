<?php
/**
 * Manor Cares — admin: dashboard summary statistics
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', 1);

require __DIR__ . '/security-headers.php';
require __DIR__ . '/middleware.php';

$admin = mc_require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    mc_json(false, 'Invalid request method.', [], 405);
}

try {
    $pdo = mc_db();

    $totals = $pdo->query(
        "SELECT
            COUNT(*) FILTER (WHERE status <> 'deleted') AS total_users,
            COUNT(*) FILTER (WHERE status = 'active') AS active_users,
            COUNT(*) FILTER (WHERE status = 'suspended') AS suspended_users,
            COUNT(*) FILTER (WHERE role = 'admin') AS admin_users
         FROM users"
    )->fetch();

    $bookingTotals = $pdo->query(
        "SELECT
            COUNT(*) FILTER (WHERE status = 'pending') AS pending,
            COUNT(*) FILTER (WHERE status = 'confirmed') AS confirmed,
            COUNT(*) FILTER (WHERE status = 'completed') AS completed,
            COUNT(*) FILTER (WHERE status = 'cancelled') AS cancelled
         FROM bookings"
    )->fetch();

    $growth = $pdo->query(
        "SELECT to_char(date_trunc('month', created_at), 'Mon') AS month, COUNT(*) AS count
         FROM users
         WHERE created_at > NOW() - INTERVAL '6 months'
         GROUP BY date_trunc('month', created_at)
         ORDER BY date_trunc('month', created_at)"
    )->fetchAll();

    $planBreakdown = $pdo->query(
        "SELECT plan, COUNT(*) AS count FROM users WHERE status <> 'deleted' GROUP BY plan ORDER BY count DESC"
    )->fetchAll();

    mc_json(true, 'OK', [
        'users' => $totals,
        'bookings' => $bookingTotals,
        'growth' => $growth,
        'plans' => $planBreakdown,
    ]);
} catch (Throwable $e) {
    error_log('Manor Cares admin stats error: ' . $e->getMessage());
    mc_json(false, 'Could not load dashboard stats right now.', [], 500);
}
