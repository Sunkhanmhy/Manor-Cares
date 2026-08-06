-- Manor Cares — Auth + Dashboard schema (Supabase / PostgreSQL)
-- Run once against your Supabase database, e.g.:
--   psql "$SUPABASE_DB_URL" -f php/schema.sql
-- or via php/migrate.php (php php/migrate.php), or paste it into the
-- Supabase Dashboard → SQL Editor and click "Run".
-- Safe to re-run: every statement is idempotent.

-- ---------------------------------------------------------------------------
-- users
-- ---------------------------------------------------------------------------
-- password_hash is nullable because OAuth-only accounts (Google/GitHub via
-- Supabase Auth, see php/oauth-start.php + php/oauth-callback.php) never set
-- a local password.
CREATE TABLE IF NOT EXISTS users (
    id                    BIGSERIAL PRIMARY KEY,
    name                  VARCHAR(120)  NOT NULL,
    email                 VARCHAR(180)  NOT NULL UNIQUE,
    password_hash         TEXT,
    plan                  VARCHAR(40)   NOT NULL DEFAULT 'essential',
    role                  VARCHAR(20)   NOT NULL DEFAULT 'user' CHECK (role IN ('user','admin')),
    status                VARCHAR(20)   NOT NULL DEFAULT 'active' CHECK (status IN ('active','suspended','deleted')),
    phone                 VARCHAR(30),
    address               VARCHAR(255),
    avatar_url            VARCHAR(255),
    oauth_provider        VARCHAR(20),
    oauth_id              VARCHAR(255),
    failed_login_attempts SMALLINT      NOT NULL DEFAULT 0,
    locked_until          TIMESTAMPTZ,
    last_login_at         TIMESTAMPTZ,
    created_at            TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    updated_at            TIMESTAMPTZ   NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_users_email ON users (email);
CREATE INDEX IF NOT EXISTS idx_users_role ON users (role);

-- Add columns for installs that already have an older `users` table.
ALTER TABLE users ALTER COLUMN password_hash DROP NOT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(20) NOT NULL DEFAULT 'user';
ALTER TABLE users ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'active';
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(30);
ALTER TABLE users ADD COLUMN IF NOT EXISTS address VARCHAR(255);
ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(255);
ALTER TABLE users ADD COLUMN IF NOT EXISTS oauth_provider VARCHAR(20);
ALTER TABLE users ADD COLUMN IF NOT EXISTS oauth_id VARCHAR(255);
ALTER TABLE users ADD COLUMN IF NOT EXISTS failed_login_attempts SMALLINT NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS locked_until TIMESTAMPTZ;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMPTZ;
ALTER TABLE users ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW();

CREATE UNIQUE INDEX IF NOT EXISTS idx_users_oauth ON users (oauth_provider, oauth_id) WHERE oauth_provider IS NOT NULL;

-- ---------------------------------------------------------------------------
-- bookings (service requests raised from the user dashboard)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bookings (
    id             BIGSERIAL PRIMARY KEY,
    user_id        BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    service_type   VARCHAR(60)  NOT NULL,
    property_type  VARCHAR(60),
    address        VARCHAR(255),
    preferred_date DATE,
    notes          TEXT,
    status         VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','confirmed','completed','cancelled')),
    created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_bookings_user ON bookings (user_id);
CREATE INDEX IF NOT EXISTS idx_bookings_status ON bookings (status);

-- ---------------------------------------------------------------------------
-- audit_log (admin actions on user accounts, for accountability)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
    id             BIGSERIAL PRIMARY KEY,
    actor_user_id  BIGINT REFERENCES users(id) ON DELETE SET NULL,
    action         VARCHAR(80) NOT NULL,
    target_user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    meta           JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_log (created_at DESC);

-- ---------------------------------------------------------------------------
-- webhooks_log (inbound webhook events, signature-verified before insert)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS webhooks_log (
    id               BIGSERIAL PRIMARY KEY,
    event_type       VARCHAR(80) NOT NULL,
    payload          JSONB NOT NULL,
    signature_valid  BOOLEAN NOT NULL,
    received_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- ---------------------------------------------------------------------------
-- Row Level Security
--
-- NOTE: RLS policies are ignored for the table owner / superuser connection
-- role — on Supabase that's the default `postgres` role used by the
-- connection string in Settings → Database. For these policies to actually
-- be enforced, connect the application with a non-owner role (see
-- php/create-app-role.sql, run it from the Supabase SQL Editor) and set
-- SUPABASE_DB_URL to use that role instead.
-- ---------------------------------------------------------------------------
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE users FORCE ROW LEVEL SECURITY;
ALTER TABLE bookings ENABLE ROW LEVEL SECURITY;
ALTER TABLE bookings FORCE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS users_self_or_admin_select ON users;
CREATE POLICY users_self_or_admin_select ON users
    FOR SELECT
    USING (
        id = NULLIF(current_setting('app.current_user_id', true), '')::bigint
        OR current_setting('app.is_admin', true) = 'true'
    );

DROP POLICY IF EXISTS users_self_or_admin_update ON users;
CREATE POLICY users_self_or_admin_update ON users
    FOR UPDATE
    USING (
        id = NULLIF(current_setting('app.current_user_id', true), '')::bigint
        OR current_setting('app.is_admin', true) = 'true'
    );

DROP POLICY IF EXISTS users_admin_delete ON users;
CREATE POLICY users_admin_delete ON users
    FOR DELETE
    USING (current_setting('app.is_admin', true) = 'true');

DROP POLICY IF EXISTS users_signup_insert ON users;
CREATE POLICY users_signup_insert ON users
    FOR INSERT
    WITH CHECK (true);

DROP POLICY IF EXISTS bookings_self_or_admin_select ON bookings;
CREATE POLICY bookings_self_or_admin_select ON bookings
    FOR SELECT
    USING (
        user_id = NULLIF(current_setting('app.current_user_id', true), '')::bigint
        OR current_setting('app.is_admin', true) = 'true'
    );

DROP POLICY IF EXISTS bookings_self_insert ON bookings;
CREATE POLICY bookings_self_insert ON bookings
    FOR INSERT
    WITH CHECK (user_id = NULLIF(current_setting('app.current_user_id', true), '')::bigint);

DROP POLICY IF EXISTS bookings_self_or_admin_update ON bookings;
CREATE POLICY bookings_self_or_admin_update ON bookings
    FOR UPDATE
    USING (
        user_id = NULLIF(current_setting('app.current_user_id', true), '')::bigint
        OR current_setting('app.is_admin', true) = 'true'
    );
