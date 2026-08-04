<?php
/**
 * Manor Cares — Postgres (Railway) database connection
 *
 * Railway automatically injects a `DATABASE_URL` environment variable
 * (format: postgres://user:password@host:port/database) into any service
 * you attach a Postgres plugin to. This helper parses that URL, falling
 * back to discrete PG* variables for local development.
 *
 * Required on Railway: link a Postgres database to this service (or add
 * the "PostgreSQL" plugin from the Railway dashboard) — Railway then sets
 * DATABASE_URL for you automatically. No further configuration needed.
 */

declare(strict_types=1);

/**
 * Minimal .env loader for local development — no Composer dependency.
 * Railway/production should keep using real environment variables and
 * never ship a .env file (see .gitignore).
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

    $databaseUrl = getenv('DATABASE_URL') ?: '';

    if ($databaseUrl !== '') {
        $parts = parse_url($databaseUrl);
        if ($parts === false || !isset($parts['host'])) {
            throw new RuntimeException('DATABASE_URL is malformed.');
        }
        $host   = $parts['host'];
        $port   = $parts['port'] ?? 5432;
        $dbname = isset($parts['path']) ? ltrim($parts['path'], '/') : '';
        $user   = isset($parts['user']) ? rawurldecode($parts['user']) : '';
        $pass   = isset($parts['pass']) ? rawurldecode($parts['pass']) : '';
    } else {
        $host   = getenv('PGHOST') ?: 'localhost';
        $port   = getenv('PGPORT') ?: '5432';
        $dbname = getenv('PGDATABASE') ?: 'manor_cares';
        $user   = getenv('PGUSER') ?: 'postgres';
        $pass   = getenv('PGPASSWORD') ?: '';
    }

    $sslMode = getenv('PGSSLMODE') ?: 'require';
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode={$sslMode}";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
