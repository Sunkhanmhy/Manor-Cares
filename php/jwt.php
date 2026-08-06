<?php
/**
 * Manor Cares — JWT helper (wraps firebase/php-jwt)
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function mc_jwt_issue(array $claims, array $authConfig): string
{
    $now = time();
    $payload = array_merge($claims, [
        'iat' => $now,
        'exp' => $now + $authConfig['jwt_ttl'],
    ]);

    return JWT::encode($payload, $authConfig['jwt_secret'], $authConfig['jwt_algo']);
}

/**
 * @return array<string,mixed>|null Decoded claims, or null if invalid/expired.
 */
function mc_jwt_verify(string $token, array $authConfig): ?array
{
    try {
        $decoded = JWT::decode($token, new Key($authConfig['jwt_secret'], $authConfig['jwt_algo']));
        return (array) $decoded;
    } catch (Throwable $e) {
        return null;
    }
}

function mc_jwt_set_cookie(string $token, array $authConfig): void
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    setcookie($authConfig['cookie_name'], $token, [
        'expires'  => time() + $authConfig['jwt_ttl'],
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}
