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
