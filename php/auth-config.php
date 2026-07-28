<?php
/**
 * Manor Cares — Auth configuration
 *
 * JWT_SECRET must be set as an environment variable on Railway (Project →
 * Variables). Generate a strong secret, e.g.:
 *   php -r "echo bin2hex(random_bytes(32));"
 *
 * A fallback is provided only so the codebase doesn't fatally error in a
 * fresh local checkout — it is NOT safe for production and a warning is
 * logged if it's ever used.
 */

declare(strict_types=1);

if (!function_exists('mc_env')) {
    function mc_env(string $key, string $default = ''): string
    {
        $value = getenv($key);
        return $value !== false && $value !== '' ? $value : $default;
    }
}

$jwtSecret = mc_env('JWT_SECRET', '');
if ($jwtSecret === '') {
    error_log('WARNING: JWT_SECRET is not set. Using an insecure development fallback — set JWT_SECRET on Railway before going live.');
    $jwtSecret = 'insecure-dev-secret-change-me';
}

return [
    'jwt_secret'    => $jwtSecret,
    'jwt_algo'      => 'HS256',
    'jwt_ttl'       => (int) mc_env('JWT_TTL_SECONDS', '86400'), // 24h
    'cookie_name'   => 'manor_auth',
    'bcrypt_cost'   => (int) mc_env('BCRYPT_COST', '12'),
];
