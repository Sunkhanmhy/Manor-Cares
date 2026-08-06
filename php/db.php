<?php
/**
 * Manor Cares — Supabase Postgres database connection
 *
 * Supabase gives you a standard Postgres connection string under
 * Project Settings → Database → Connection string (use the "Transaction
 * pooler" URI for serverless/many-short-lived-connections hosting like
 * Railway/Vercel, or the direct connection URI otherwise). Set it as
 * SUPABASE_DB_URL (DATABASE_URL is also accepted as an alias so existing
 * deployments keep working).
 *
 * Format: postgres://user:password@host:port/database
 */

declare(strict_types=1);

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

function mc_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // SUPABASE_DB_URL is the canonical name; DATABASE_URL is accepted as an
    // alias for compatibility with generic hosting providers (e.g. Railway)
    // that inject that variable name automatically.
    $databaseUrl = getenv('SUPABASE_DB_URL') ?: (getenv('DATABASE_URL') ?: '');

    if ($databaseUrl === '') {
        throw new RuntimeException(
            'SUPABASE_DB_URL is not set. Copy your Supabase project\'s connection string ' .
            '(Project Settings → Database → Connection string) into .env as SUPABASE_DB_URL.'
        );
    }

    $parts = parse_url($databaseUrl);
    if ($parts === false || !isset($parts['host'])) {
        throw new RuntimeException('SUPABASE_DB_URL is malformed.');
    }
    $host   = $parts['host'];
    $port   = $parts['port'] ?? 5432;
    $dbname = isset($parts['path']) ? ltrim($parts['path'], '/') : 'postgres';
    $user   = isset($parts['user']) ? rawurldecode($parts['user']) : '';
    $pass   = isset($parts['pass']) ? rawurldecode($parts['pass']) : '';

    $sslMode = getenv('PGSSLMODE') ?: 'require';
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode={$sslMode}";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
