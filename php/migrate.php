<?php
/**
 * Manor Cares — one-time database migration runner
 *
 * Usage (run once after linking the Railway Postgres plugin):
 *   railway run php php/migrate.php
 * or, if you have DATABASE_URL exported locally:
 *   php php/migrate.php
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
