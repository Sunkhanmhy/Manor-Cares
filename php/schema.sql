-- Manor Cares — Auth schema (PostgreSQL / Railway)
-- Run once against your Railway Postgres database, e.g.:
--   railway run psql "$DATABASE_URL" -f php/schema.sql
-- or via php/migrate.php (php php/migrate.php)

CREATE TABLE IF NOT EXISTS users (
    id            BIGSERIAL PRIMARY KEY,
    name          VARCHAR(120)  NOT NULL,
    email         VARCHAR(180)  NOT NULL UNIQUE,
    password_hash TEXT          NOT NULL,
    plan          VARCHAR(40)   NOT NULL DEFAULT 'essential',
    created_at    TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_users_email ON users (email);
