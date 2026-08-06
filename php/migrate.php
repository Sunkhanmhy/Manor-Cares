<?php
/**
 * Manor Cares — one-time database migration runner
 *
 * Usage (after setting SUPABASE_DB_URL in .env, or as a real env var in prod):
 *   php php/migrate.php
 *
 * Equivalent to pasting php/schema.sql into the Supabase Dashboard's SQL
 * Editor and clicking "Run".
 */

declare(strict_types=1);

require __DIR__ . '/db.php';

try {
    $pdo = mc_db();
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    if ($sql === false) {
        throw new RuntimeException('Could not read schema.sql');
    }
    $pdo->exec($sql);
    echo "Migration complete: users table is ready." . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
