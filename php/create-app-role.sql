-- Manor Cares — dedicated, least-privilege application role
--
-- Row Level Security policies in schema.sql are IGNORED by the table owner
-- and by superusers — on Supabase that's the default `postgres` role. To
-- have RLS actually enforced, the application must connect as a separate,
-- non-owner role. Run this once against your Supabase database (via the
-- SQL Editor in the dashboard, or psql/the Supabase CLI), then set
-- SUPABASE_DB_URL to use this role for the PHP application — keep the
-- default `postgres` role only for running php/migrate.php.
--
-- Usage:
--   psql "$SUPABASE_DB_URL" -v app_password="'change-me-to-a-strong-secret'" -f php/create-app-role.sql
--
-- Supabase's database is named `postgres` by default — adjust the GRANT
-- CONNECT line below if yours differs.

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'manor_app') THEN
        CREATE ROLE manor_app LOGIN PASSWORD: NDBKBKLFGMPHMPMPFH;
    END IF;
END
$$;

GRANT CONNECT ON DATABASE postgres TO manor_app;
GRANT USAGE ON SCHEMA public TO manor_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON users, bookings, audit_log, webhooks_log TO manor_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO manor_app;

-- manor_app does NOT own these tables, so RLS policies defined in
-- schema.sql are enforced for every query it runs.
