<?php
/**
 * Manor Cares — database connectivity self-test (CLI only)
 *
 * Prints the connection details Manor Cares actually resolved from
 * SUPABASE_DB_URL (password redacted to its length only) and attempts a
 * real connection, so you can verify production credentials without
 * guessing or exposing secrets. Refuses to run outside the CLI SAPI so it
 * can never be reached over HTTP.
 *
 * Usage (local, using your .env):
 *   php php/db-check.php
 *
 * Usage (against Railway's real deployed variables, without deploying):
 *   railway login && railway link
 *   railway run php php/db-check.php
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', 1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden.');
}

require __DIR__ . '/db.php';

$raw = (string) (getenv('SUPABASE_DB_URL') ?: getenv('DATABASE_URL') ?: '');
if (trim($raw) === '') {
    fwrite(STDERR, "SUPABASE_DB_URL / DATABASE_URL is not set in this environment.\n");
    exit(1);
}

// Same sanitizing + parsing mc_db() uses, so this diagnostic never drifts
// out of sync with what the app actually connects with.
$parts = mc_db_parse_url($raw);
if ($parts === false) {
    fwrite(STDERR, "SUPABASE_DB_URL / DATABASE_URL could not be parsed at all — check it's a valid postgresql://user:pass@host:port/db URL.\n");
    exit(1);
}
$host = $parts['host'] ?? null;
$pass = $parts['pass'] ?? '';

echo "Resolved connection details:\n";
echo '  host:     ' . ($host ?? '(missing — SUPABASE_DB_URL could not be parsed)') . "\n";
echo '  port:     ' . ($parts['port'] ?? '5432 (default)') . "\n";
echo '  database: ' . (isset($parts['path']) ? ltrim($parts['path'], '/') : 'postgres (default)') . "\n";
echo '  user:     ' . ($parts['user'] ?? '(missing)') . "\n";
echo '  password: ' . ($pass === '' ? '(missing)' : 'set (' . strlen($pass) . " chars, not shown)") . "\n";
echo '  sslmode:  ' . (getenv('PGSSLMODE') ?: 'require (default)') . "\n";

if ($pass !== '' && $pass[0] === '[' && str_ends_with($pass, ']')) {
    echo "  WARNING: password is wrapped in [brackets] — looks like the unedited Supabase\n";
    echo "           placeholder. db.php auto-strips this, but fix it at the source too.\n";
}
if ($host !== null && str_starts_with($host, 'db.') && str_ends_with($host, '.supabase.co')) {
    echo "  NOTE: this is Supabase's *direct* connection host (IPv6-only unless you bought\n";
    echo "        the IPv4 add-on). If the connection below hangs or times out, switch to\n";
    echo "        the \"Transaction pooler\" connection string instead.\n";
}

echo "\nAttempting to connect...\n";

try {
    $pdo = mc_db();
    $version = $pdo->query('SELECT version()')->fetchColumn();
    echo "SUCCESS — connected. Server says:\n  {$version}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAILED — ' . get_class($e) . ': ' . $e->getMessage() . "\n");
    exit(1);
}
