<?php
/**
 * Manor Cares — admin: paginated / searchable user list
 */

declare(strict_types=1);

require __DIR__ . '/security-headers.php';
require __DIR__ . '/middleware.php';

$admin = mc_require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    mc_json(false, 'Invalid request method.', [], 405);
}

$search = trim((string) ($_GET['q'] ?? ''));
$page   = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

try {
    $pdo = mc_db();

    $where = '';
    $params = [];
    if ($search !== '') {
        $where = 'WHERE name ILIKE :q OR email ILIKE :q';
        $params['q'] = '%' . $search . '%';
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users {$where}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT id, name, email, plan, role, status, last_login_at, created_at FROM users {$where} ORDER BY created_at DESC LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    mc_json(true, 'OK', [
        'users' => $stmt->fetchAll(),
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
    ]);
} catch (Throwable $e) {
    error_log('Manor Cares admin users list error: ' . $e->getMessage());
    mc_json(false, 'Could not load users right now.', [], 500);
}
