<?php
/**
 * Manor Cares — mbstring polyfills
 *
 * Some hosts (and this local dev machine) ship PHP without the optional
 * `mbstring` extension enabled. Rather than making it a hard requirement,
 * fall back to the `iconv` extension (bundled with PHP core) so user-facing
 * forms (signup, contact, profile, bookings, etc.) never hard-crash with an
 * "undefined function" fatal error just because mbstring isn't installed.
 */

declare(strict_types=1);

if (!function_exists('mb_strlen')) {
    function mb_strlen(string $string, ?string $encoding = null): int
    {
        return iconv_strlen($string, $encoding ?? 'UTF-8');
    }
}

if (!function_exists('mb_substr')) {
    function mb_substr(string $string, int $start, ?int $length = null, ?string $encoding = null): string
    {
        return iconv_substr($string, $start, $length, $encoding ?? 'UTF-8') ?: '';
    }
}

if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $string, ?string $encoding = null): string
    {
        return strtolower($string);
    }
}

if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper(string $string, ?string $encoding = null): string
    {
        return strtoupper($string);
    }
}
