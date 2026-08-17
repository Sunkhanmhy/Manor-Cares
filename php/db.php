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

/**
 * Trim + strip an accidental wrapping "..."/'...' from a raw connection
 * string (some dashboards preserve quotes literally as part of the value),
 * then parse_url() it. Shared by mc_db() and php/db-check.php so the two
 * can never drift out of sync on how the raw env var is sanitized.
 */
if (!function_exists('mc_db_parse_url')) {
    function mc_db_parse_url(string $raw): array|false
    {
        $raw = trim($raw);
        if (strlen($raw) >= 2) {
            $first = $raw[0];
            $last  = $raw[strlen($raw) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $raw = substr($raw, 1, -1);
            }
        }
        if ($raw === '') {
            return false;
        }
        return parse_url($raw);
    }
}

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

    if (trim($databaseUrl) === '') {
        throw new RuntimeException(
            'SUPABASE_DB_URL is not set. Copy your Supabase project\'s connection string ' .
            '(Project Settings → Database → Connection string) into .env (or your host\'s ' .
            'environment variables in production) as SUPABASE_DB_URL.'
        );
    }

    $parts = mc_db_parse_url($databaseUrl);
    if ($parts === false || !isset($parts['host'])) {
        throw new RuntimeException(
            'SUPABASE_DB_URL is malformed — could not parse a host from it. ' .
            'Expected format: postgresql://user:password@host:port/database'
        );
    }
    $host    = $parts['host'];
    $port    = $parts['port'] ?? 5432;
    $dbname  = isset($parts['path']) ? ltrim($parts['path'], '/') : 'postgres';
    $user    = isset($parts['user']) ? rawurldecode($parts['user']) : '';
    $rawPass = $parts['pass'] ?? '';

    // Defensive: Supabase's dashboard shows the password as a bracketed
    // placeholder — postgresql://postgres:[YOUR-PASSWORD]@host:port/db — and
    // the single most common copy/paste mistake is replacing only the text
    // inside the brackets while leaving the brackets themselves in place.
    // Auto-repair it (loudly, in the log) instead of failing every request.
    if (strlen($rawPass) > 1 && $rawPass[0] === '[' && str_ends_with($rawPass, ']')) {
        error_log(
            'Manor Cares: SUPABASE_DB_URL password was wrapped in literal [brackets] ' .
            '(the Supabase placeholder was not fully replaced) — auto-stripping them so ' .
            'the app keeps working. Please fix the env var at the source too.'
        );
        $rawPass = substr($rawPass, 1, -1);
    }
    $pass = rawurldecode($rawPass);

    if ($user === '' || $pass === '') {
        throw new RuntimeException('SUPABASE_DB_URL is missing a username or password.');
    }

    // Non-fatal hint: Supabase's *direct* connection host (db.<ref>.supabase.co)
    // is IPv6-only unless the project has the paid IPv4 add-on. Most PaaS hosts
    // (Railway, Vercel, Render) only have IPv4 egress, so connections to it just
    // hang until they time out. The pooler host (*.pooler.supabase.com) is
    // IPv4-compatible and is what external hosts should use instead.
    if (str_starts_with($host, 'db.') && str_ends_with($host, '.supabase.co')) {
        error_log(
            "Manor Cares: SUPABASE_DB_URL points at Supabase's direct connection host " .
            "({$host}), which is IPv6-only unless you bought the IPv4 add-on. If the DB " .
            'connection hangs or times out on a host like Railway, switch to the ' .
            '"Transaction pooler" connection string instead (Project Settings → Database → ' .
            'Connection string).'
        );
    }

    $sslMode = getenv('PGSSLMODE') ?: 'require';
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode={$sslMode}";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 10,
        ]);
    } catch (PDOException $e) {
        // Categorize the failure in the log so whoever reads error_log /
        // Railway logs next doesn't have to decode a raw libpq message.
        $hint = match (true) {
            str_contains($e->getMessage(), 'password authentication failed') =>
                'wrong username/password — re-copy SUPABASE_DB_URL from Supabase and watch for the [brackets] placeholder',
            str_contains($e->getMessage(), 'Tenant or user not found'),
            str_contains($e->getMessage(), 'SASL') =>
                'pooler/direct mismatch — the pooler needs a user like "postgres.<project-ref>", the direct connection just "postgres"; copy the whole string from one tab, don\'t mix hosts/users',
            str_contains($e->getMessage(), 'ENOIDENTIFIER'),
            str_contains($e->getMessage(), 'no tenant identifier provided') =>
                'pooler username is missing the "." + project-ref suffix (e.g. use "' . $user . '.<project-ref>" instead of just "' . $user . '") — every username on the Supavisor pooler must end in .<project-ref>, even custom roles like manor_app',
            str_contains($e->getMessage(), 'could not translate host name') =>
                'DNS lookup failed — check for a typo in the host, or the Supabase project may be paused',
            str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'timeout') =>
                'connection timed out — likely the IPv6-only direct host reached from a host with no IPv6 egress; use the pooler connection string',
            str_contains($e->getMessage(), 'SSL') =>
                'TLS/SSL negotiation failed — check the PGSSLMODE value',
            default => 'see the raw PDO message above',
        };
        error_log("Manor Cares DB connection failed ({$hint}): " . $e->getMessage());
        throw $e;
    }

    return $pdo;
}
