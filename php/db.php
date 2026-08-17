<?php
/**
 * Manor Cares — shared environment loader
 *
 * Loads `.env` into getenv() for local development (contact-handler.php
 * uses this for MAIL_ and RESEND_ vars). No database connection lives here
 * anymore — the app has no DB-backed features (auth/dashboards removed).
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', 1);

require_once __DIR__ . '/polyfills.php';

/**
 * Minimal .env loader for local development — no Composer dependency.
 * Production (Railway, Vercel, etc.) should keep using real environment
 * variables and never ship a .env file (see .gitignore).
 */
if (!function_exists('mc_load_dotenv')) {
    function mc_load_dotenv(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $path = __DIR__ . '/../.env';
        if (!is_file($path)) {
            return;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $value = trim($value, "\"'");
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
            }
        }
    }
}
mc_load_dotenv();

