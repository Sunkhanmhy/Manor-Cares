<?php
/**
 * Manor Cares — "who am I" endpoint
 *
 * Used by the dashboard front-ends on load to fetch the current user's
 * profile and a fresh CSRF token in one call.
 */

declare(strict_types=1);

require __DIR__ . '/security-headers.php';
require __DIR__ . '/middleware.php';
require __DIR__ . '/csrf.php';

$user = mc_require_auth();

mc_json(true, 'OK', [
    'user' => [
        'id'            => (int) $user['id'],
        'name'          => $user['name'],
        'email'         => $user['email'],
        'plan'          => $user['plan'],
        'role'          => $user['role'],
        'status'        => $user['status'],
        'phone'         => $user['phone'],
        'address'       => $user['address'],
        'avatar_url'    => $user['avatar_url'],
        'created_at'    => $user['created_at'],
        'last_login_at' => $user['last_login_at'],
    ],
    'csrf_token' => mc_csrf_token(),
]);
