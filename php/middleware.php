<?php
/**
 * Manor Cares — request authentication & authorization middleware
 *
 * Verifies the JWT stored in the httpOnly auth cookie, re-reads the user
 * from Postgres (so role/status changes take effect immediately even for
 * tokens issued before a suspension), and — critically for defense in
 * depth — tells Postgres who is asking via `set_config()` so Row Level
 * Security policies in schema.sql are enforced even if application code
 * has a bug in its own WHERE clause.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';

if (!function_exists('mc_json')) {
    function mc_json(bool $success, string $message, array $extra = [], int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_SLASHES);
        exit;
    }
}

/**
 * Reads + verifies the auth cookie, loads the current DB row, and binds
 * app.current_user_id / app.is_admin on the active PDO connection so RLS
 * policies scope every subsequent query automatically.
 *
 * @return array<string,mixed>|null
 */
function mc_current_user(): ?array
{
    static $cached = false;
    static $user = null;
    if ($cached) {
        return $user;
    }
    $cached = true;

    $authConfig = require __DIR__ . '/auth-config.php';
    $cookieName = $authConfig['cookie_name'];
    $token = $_COOKIE[$cookieName] ?? '';
    if ($token === '') {
        return null;
    }

    $claims = mc_jwt_verify($token, $authConfig);
    if ($claims === null || empty($claims['sub'])) {
        return null;
    }

    $pdo = mc_db();
    $stmt = $pdo->prepare('SELECT id, name, email, plan, role, status, phone, address, avatar_url, created_at, last_login_at FROM users WHERE id = :id');
    $stmt->execute(['id' => (int) $claims['sub']]);
    $row = $stmt->fetch();

    if ($row === false || $row['status'] !== 'active') {
        return null;
    }

    // Bind the verified identity for Postgres RLS (see php/create-app-role.sql
    // for why this only matters when connected as a non-owner role).
    $pdo->prepare("SELECT set_config('app.current_user_id', :uid, false)")->execute(['uid' => (string) $row['id']]);
    $pdo->prepare("SELECT set_config('app.is_admin', :is_admin, false)")->execute(['is_admin' => $row['role'] === 'admin' ? 'true' : 'false']);

    $user = $row;
    return $user;
}

function mc_require_auth(): array
{
    $user = mc_current_user();
    if ($user === null) {
        mc_json(false, 'Please sign in to continue.', [], 401);
    }
    return $user;
}

function mc_require_admin(): array
{
    $user = mc_require_auth();
    if ($user['role'] !== 'admin') {
        mc_json(false, 'You do not have permission to access this resource.', [], 403);
    }
    return $user;
}
