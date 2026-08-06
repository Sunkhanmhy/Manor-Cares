# Manor Cares

Luxury cleaning services website with account authentication, a user
dashboard and an admin dashboard, backed by PHP + PostgreSQL + JWT.

## Stack

- **Frontend:** static HTML/CSS + vanilla JS (native ES modules for the
  dashboards, bundled/minified by [Vite](https://vitejs.dev) only when you
  choose to run a production build — no build step is required to develop).
- **Backend:** PHP 8+ (no framework), PDO/PostgreSQL, `firebase/php-jwt`,
  PHPMailer.
- **Auth:** JWT stored in an httpOnly, `SameSite=Strict` cookie; bcrypt
  password hashing; CSRF double-submit token for state-changing requests;
  Postgres Row Level Security as defense-in-depth.

## Local setup

1. **PHP extensions** — make sure `pdo_pgsql` is installed
   (`sudo apt install php8.3-pgsql` on Debian/Ubuntu, or enable it in
   `php.ini` on other systems).
2. **Database** — start a local Postgres with Docker:
   ```bash
   docker compose up -d
   ```
   (or point `PGHOST`/`PGUSER`/etc. at any Postgres instance you already have).
3. **Environment** — copy the example env file and adjust secrets:
   ```bash
   cp .env.example .env
   php -r "echo bin2hex(random_bytes(32));"   # paste into JWT_SECRET
   ```
4. **Schema** — run the migration (idempotent, safe to re-run):
   ```bash
   php php/migrate.php
   ```
5. **Create an admin** (CLI only, never exposed over HTTP):
   ```bash
   php php/seed-admin.php admin@manor-cares.com "Admin Name" "StrongPassw0rd!"
   ```
6. **Run the app**:
   ```bash
   php -S localhost:8000
   ```
   Visit `http://localhost:8000`, create an account or sign in — regular
   accounts land on `user-dashboard.html`, admins on `admin-dashboard.html`.

### Optional: Vite dev server / production bundle

The dashboards (`src/dashboard/*.js`) are plain ES modules and run as-is.
Vite is wired up if you want HMR or a minified bundle:

```bash
npm install
npm run dev      # http://localhost:5173, proxies /php to `php -S localhost:8000`
npm run build    # outputs assets/dist/{dashboard,admin}.js
```

## Security notes

- **Row Level Security**: policies in `php/schema.sql` are ignored for the
  table owner/superuser. To have Postgres actually enforce them, create a
  least-privilege role with `php/create-app-role.sql` and connect the app
  (not the migration step) as that role.
- **Secrets**: `.env`, `JWT_SECRET`, `WEBHOOK_SECRET` and DB credentials must
  never be committed — see `.gitignore`. On Railway, set them as real
  environment variables instead.
- **Webhooks**: `php/webhook.php` verifies an HMAC-SHA256 signature
  (`X-Manor-Signature` header, keyed with `WEBHOOK_SECRET`) before trusting
  any payload.
- **Brute force**: `php/auth-login.php` locks an account for 15 minutes
  after 5 failed attempts.

## Deploying to Railway

1. Attach a PostgreSQL plugin — Railway sets `DATABASE_URL` automatically,
   which `php/db.php` already reads.
2. Set `JWT_SECRET`, `WEBHOOK_SECRET`, `BCRYPT_COST`, and the `MAIL_*`
   variables in the service's Variables tab.
3. Run `php php/migrate.php` once (`railway run php php/migrate.php`) and
   `php php/seed-admin.php ...` to create your first admin.
4. (Optional) run `npm run build` as part of your build step if you want
   the minified dashboard bundle instead of the raw ES modules.
   




















