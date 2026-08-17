# Manor Cares

Luxury cleaning services website with account authentication, a user
dashboard and an admin dashboard, backed by **Supabase Postgres**, **PHP**,
and **JWT** sessions — with Google/GitHub OAuth (via Supabase Auth), email
via **Resend** (or SMTP/PHPMailer), and signed payment webhooks.

## Stack

- **Frontend:** static HTML/CSS + vanilla JS (native ES modules for the
  dashboards, bundled/minified by [Vite](https://vitejs.dev) only when you
  choose to run a production build — no build step is required to develop).
- **Backend:** PHP 8+ (no framework), PDO/PostgreSQL (Supabase), `firebase/php-jwt`.
- **Database:** [Supabase](https://supabase.com) Postgres — used directly
  over a standard `postgres://` connection (not the REST API), so the
  existing schema/RLS policies work unchanged.
- **Auth:** email+password (JWT in an httpOnly `SameSite=Strict` cookie,
  bcrypt hashing, brute-force lockout) **or** Google/GitHub OAuth handled by
  Supabase Auth — either path issues the same first-party JWT session, so
  the rest of the app doesn't need to know which one was used.
- **Mail:** [Resend](https://resend.com) HTTPS API (preferred) with an
  automatic fallback to SMTP via PHPMailer — no `curl` extension required,
  outbound HTTP uses PHP's built-in stream wrapper.
- **Payments:** a single webhook endpoint (`php/webhook.php`) that verifies
  either Stripe's signature scheme or a generic HMAC scheme, so you can
  point Stripe, Paystack, or a custom integration at it.
- **Security:** CSRF double-submit tokens, security headers (CSP, HSTS,
  etc.), Postgres Row Level Security, signed webhooks, audit log.

---

## 1. Quick start (local development)

1. **PHP extensions** — make sure `pdo_pgsql` is installed
   (`sudo apt install php8.3-pgsql` on Debian/Ubuntu, or enable it in
   `php.ini` on other systems). `mbstring` and `curl` are **not** required —
   the app has built-in fallbacks (`php/polyfills.php`, `php/http-client.php`)
   if they're missing.
2. **Environment**:
   ```bash
   cp .env.example .env
   php -r "echo bin2hex(random_bytes(32));"   # paste into JWT_SECRET



   ```
3. Follow **§2 (Database)** and **§3 (OAuth)** below to fill in your
   Supabase values, then run the migration and start the app:
   ```bash
   php php/migrate.php
   php php/seed-admin.php admin@manor-cares.com "Admin Name" "StrongPassw0rd!"
   php -S localhost:8000
   ```
   Visit `http://localhost:8000` — regular accounts land on
   `user-dashboard.html`, admins on `admin-dashboard.html`.

### Optional: Vite dev server / production bundle

The dashboards (`src/dashboard/*.js`) are plain ES modules and run as-is —
Vite is only needed if you want HMR or a minified bundle:

```bash
npm install
npm run dev      # http://localhost:5173, proxies /php to `php -S localhost:8000`
npm run build    # outputs assets/dist/{dashboard,admin}.js
```

---






## 2. Connect the database (Supabase)

1. Create a project at [supabase.com](https://supabase.com/dashboard) (free
   tier is enough to start).
2. **Get the connection string**: Project → **Settings → Database →
   Connection string**. Choose:
   - **Transaction pooler** (port `6543`) — recommended for most PHP hosts
     (short-lived connections per request, works well with `php -S` /
     PHP-FPM / serverless).
   - **Direct connection** (port `5432`) — fine for long-running processes
     or if the pooler misbehaves with prepared statements.
3. Paste it into `.env` as `SUPABASE_DB_URL` (keep `PGSSLMODE=require`):
   ```
   SUPABASE_DB_URL=postgresql://postgres.<project-ref>:<db-password>@aws-0-<region>.pooler.supabase.com:6543/postgres
   PGSSLMODE=require
   ```

4. **Run the schema** — either:
   ```bash
   php php/migrate.php
   ```
   or paste the contents of `php/schema.sql` into the Supabase Dashboard →
   **SQL Editor** and click "Run". Both are idempotent/safe to re-run.
5. **(Recommended for production) Enforce Row Level Security properly.**
   Supabase's default `postgres` connection role is the table owner and
   therefore **bypasses RLS**, same as any Postgres superuser. To have the
   policies in `schema.sql` actually enforced:
   - Open the Supabase **SQL Editor**.
   - Run `php/create-app-role.sql` (edit the `-v app_password=...` value
     inline, or just hard-code a strong password directly in the script
     when pasting it into the editor — the SQL Editor doesn't support
     `psql` variables).
   - Update `SUPABASE_DB_URL` to authenticate as `manor_app` instead of
     `postgres`, keeping the same host/port/database.
6. **Get the project API keys** for OAuth (see §3): Project → **Settings →
   API** → copy the `URL`, `anon public` key, and `service_role` key into
   `.env` as `SUPABASE_URL`, `SUPABASE_ANON_KEY`, `SUPABASE_SERVICE_ROLE_KEY`.                                                                                                                                        
   The service role key is optional today (reserved for future
   Storage/Admin API use) — **never** expose it to the browser.

---

## 3. Connect OAuth (Google & GitHub sign-in)

Manor Cares doesn't talk to Google/GitHub directly — it delegates the OAuth
dance to **Supabase Auth**, which already knows how to do PKCE, token
exchange, and provider quirks. The app only needs `SUPABASE_URL` +
`SUPABASE_ANON_KEY` (already set in §2.6).

1. In the Supabase Dashboard, go to **Authentication → Sign In / Providers**.
2. **Enable Google**:
   - In [Google Cloud Console](https://console.cloud.google.com/apis/credentials),
     create an OAuth 2.0 Client ID (type "Web application").
   - Authorized redirect URI: `https://<project-ref>.supabase.co/auth/v1/callback`
     (Supabase shows you the exact value to copy).
   - Copy the Client ID/Secret into Supabase's Google provider settings and
     toggle it on.
3. **Enable GitHub**:
   - In GitHub → Settings → Developer settings → OAuth Apps, create a new
     OAuth App.
   - Authorization callback URL: `https://<project-ref>.supabase.co/auth/v1/callback`
   - Copy the Client ID/Secret into Supabase's GitHub provider settings and
     toggle it on.
4. **Whitelist your app's own callback URL** in Supabase: Authentication →
   URL Configuration → **Redirect URLs**, add:
   - `http://localhost:8000/php/oauth-callback.php` (local dev)
   - `https://yourdomain.com/php/oauth-callback.php` (production)
5. That's it — `create-account.html` already has "Continue with Google" /
   "Continue with GitHub" buttons that call `php/oauth-start.php`, which
   redirects to Supabase, which redirects to the provider, which redirects
   back to `php/oauth-callback.php`. That file verifies the PKCE code,
   fetches the verified email/name/avatar from Supabase, creates or links a
   row in the local `users` table, and issues the same `manor_auth` JWT
   cookie a password sign-in would — dashboards, CSRF, and RLS binding all
   work identically regardless of which sign-in method was used.
   - Accounts created via OAuth have `password_hash = NULL`; if someone
     later tries to sign in with a password on that email, `auth-login.php`
     shows a clear message pointing them back to the OAuth button instead.

---

## 4. Contact form email (Resend or SMTP)

`php/contact-handler.php` (used by both `contact.html` and the
`services.html` "Request Clean-up Quote" form) sends through
`php/mailer.php`, which picks a backend automatically
**Option A — Resend (recommended)**
1. Create an account at [resend.com](https://resend.com) and verify a
   sending domain (Domains → Add Domain → add the DNS records it gives
   you).
2. Create an API key: **API Keys → Create API Key**.
3. Set in `.env`:
   ```
   RESEND_API_KEY=re_your_key_here
   RESEND_FROM_EMAIL=no-reply@yourdomain.com   # must be on the verified domain
   ```
   Done — `mailer.php` uses Resend automatically whenever `RESEND_API_KEY`
   is set, over plain HTTPS (no `curl` extension needed).

**Option B — SMTP (fallback)**
Leave `RESEND_API_KEY` blank and fill in the `MAIL_SMTP_*` variables with
any provider (Gmail app password, SES SMTP, Mailtrap for testing,
Postmark, etc.):
```
MAIL_SMTP_HOST=smtp.yourprovider.com
MAIL_SMTP_USER=...
MAIL_SMTP_PASS=...
MAIL_SMTP_PORT=587
MAIL_SMTP_ENCRYPTION=tls
```

Also set `MAIL_TO_ADDRESS`/`MAIL_TO_NAME` to the inbox that should receive
contact form + quote request submissions.

---

## 5. Payment webhooks

`php/webhook.php` is a single endpoint that accepts **either** of two
signature schemes, so it works with a real payment provider or a custom
integration without code changes:

### Stripe
1. Stripe Dashboard → **Developers → Webhooks → Add endpoint**.
2. Endpoint URL: `https://yourdomain.com/php/webhook.php`
3. Select the events you care about (e.g. `checkout.session.completed`,
   `invoice.paid`, `charge.refunded`).
4. Copy the endpoint's **Signing secret** (`whsec_...`) into `.env` as
   `STRIPE_WEBHOOK_SECRET`.
5. Stripe sends a `Stripe-Signature` header — `webhook.php` detects it
   automatically and verifies the `t=...,v1=...` HMAC-SHA256 scheme with a
   5-minute replay tolerance, exactly per
   [Stripe's documented algorithm](https://docs.stripe.com/webhooks#verify-manually).
6. Test locally with the [Stripe CLI](https://docs.stripe.com/stripe-cli):
   ```bash
   stripe listen --forward-to localhost:8000/php/webhook.php
   stripe trigger checkout.session.completed
   ```

### Paystack / any other provider (generic HMAC)
1. Set a strong random value in `.env` as `WEBHOOK_SECRET`.
2. Configure the provider to sign its payload with HMAC-SHA256 using that
   secret and send it in an `X-Manor-Signature` header (for providers that
   support custom webhook signing, e.g. via a middleware/relay you control —
   most SaaS payment providers use their own header/scheme like Stripe's, in
   which case add a branch to `webhook.php` following the same pattern used
   for Stripe).
3. Every request (valid or not) is logged to the `webhooks_log` table for
   auditing; invalid signatures are rejected with `401` **before** the
   payload is trusted or acted on.
4. Add your business logic where the file says
   `// Handle known, trusted event types here...` — e.g. mark a booking as
   paid, upgrade a subscription plan, etc.

---

## Security notes

- **Row Level Security**: see §2.5 — policies in `php/schema.sql` are
  ignored for the table owner/superuser; use `php/create-app-role.sql` for
  real enforcement.
- **Secrets**: `.env`, `JWT_SECRET`, `WEBHOOK_SECRET`, `STRIPE_WEBHOOK_SECRET`,
  `SUPABASE_SERVICE_ROLE_KEY`, and DB credentials must never be committed —
  see `.gitignore`. In production, set them as real environment variables.
- **OAuth**: PKCE state + code_verifier are bound to a short-lived,
  httpOnly `manor_oauth` session cookie (`SameSite=Lax`, required so it
  survives the cross-site redirect back from the provider).
- **Webhooks**: signature-verified before the payload is ever trusted (see
  §5); every attempt is logged regardless of validity.
- **Brute force**: `php/auth-login.php` locks an account for 15 minutes
  after 5 failed password attempts (OAuth-only accounts are unaffected).

## Deploying to production

1. Point `SUPABASE_DB_URL`, `SUPABASE_URL`, `SUPABASE_ANON_KEY` at your
   Supabase project (§2), and add your production domain's OAuth callback
   URL in Supabase (§3.4).
2. Set `JWT_SECRET`, `WEBHOOK_SECRET`, `STRIPE_WEBHOOK_SECRET`,
   `BCRYPT_COST`, `RESEND_API_KEY`/`RESEND_FROM_EMAIL` (or `MAIL_*`) as real
   environment variables on your host (Railway, Render, Vercel, etc.) —
   never ship a `.env` file to production.
3. Run `php php/migrate.php` once against production (e.g.
   `railway run php php/migrate.php`), then
   `php php/seed-admin.php ...` to create your first admin.
4. Point your payment provider's webhook at
   `https://yourdomain.com/php/webhook.php` (§5).
5. (Optional) run `npm run build` as part of your build step if you want
   the minified dashboard bundle instead of the raw ES modules.

---

## Troubleshooting: signup/login failing in production

### Symptom

`create-account.html` / the sign-in form show a generic
**"Sorry, we could not create your account right now. Please try again
later."** or **"Sorry, sign-in is unavailable right now."**, even though
`SUPABASE_DB_URL` looks correct in Railway's Variables tab.

### Why the message is so generic

`php/auth-signup.php` and `php/auth-login.php` deliberately catch **every**
internal error and show a friendly message instead of leaking database
internals to visitors (`catch (Throwable $e) { error_log(...); mc_respond
(false, 'Sorry, ...'); }`). The real reason is always written to the
server's error log, never sent to the browser — so step 1 is always to go
find that real reason.

### Step 1 — read the real error in Railway's logs

Open your Railway service → **Deployments** → the active deployment →
**View Logs** (or run `railway logs` with the Railway CLI), then search for
`Manor Cares signup error` or `Manor Cares login error`. Whatever follows
that prefix is the actual PDO/Postgres message. Match it against the table
below — this repo's [php/db.php](php/db.php) now logs an extra, categorized
hint line right before it (`Manor Cares DB connection failed (...)`) to
save you the guesswork.

| What the log says | Root cause | Fix |
| --- | --- | --- |
| `Authentication credentials are invalid. Please reconnect with fresh credentials to restore pool functionality` | Supabase's **pooler (Supavisor)** is rejecting the password. This happens when the DB password was changed **outside the Supabase dashboard** (e.g. via a raw `ALTER ROLE ... PASSWORD`, or the pooler simply hasn't picked up a very recent change) — the direct connection can keep working with the new password while the pooler still rejects it. | In Supabase: **Project Settings → Database → Reset Database Password** (this is the one action guaranteed to sync to *both* the direct and pooler endpoints). Copy the freshly-generated connection string immediately after, and paste the whole thing into Railway's `SUPABASE_DB_URL`. |
| `password authentication failed for user "postgres"` (or `postgres.<ref>`) | The password in `SUPABASE_DB_URL` is simply wrong — most often the literal `[YOUR-PASSWORD]` placeholder brackets from Supabase's dashboard were left around the real password. `db.php` now auto-strips a *matching* pair of brackets and logs a warning when it does this, but the value should still be corrected at the source. | Re-copy the connection string from Supabase and replace `[YOUR-PASSWORD]` (**brackets included**) with the real password. |
| `Tenant or user not found` / `SASL` errors | Host/user mismatch — the **pooler** connection needs a username like `postgres.<project-ref>` (with a dot), the **direct** connection just needs `postgres`. Mixing a pooler host with a direct-style username (or vice versa) fails auth. | Copy the *entire* connection string from a single tab in Supabase (Transaction pooler recommended for Railway) — don't hand-assemble host/user from different tabs. |
| Request just hangs for ~30-60s then fails, no specific PDO message | `SUPABASE_DB_URL` uses Supabase's **direct connection** host (`db.<project-ref>.supabase.co`), which is **IPv6-only** unless you've bought Supabase's IPv4 add-on. Railway's network is IPv4-only, so it can never reach that host — it just times out. `db.php` now caps the connection attempt at 10 seconds so this fails fast instead of hanging the request. | Switch to the **Transaction pooler** connection string (Project Settings → Database → Connection string → *Transaction pooler*, host looks like `aws-0-<region>.pooler.supabase.com`, port `6543`) — it's IPv4-compatible. |
| `could not translate host name "..." to address` | Typo in the host, or the Supabase project is paused (free-tier projects auto-pause after a week of inactivity). | Re-copy the connection string; if the Supabase dashboard shows the project as "Paused", click **Restore/Resume** first. |
| No new log line appears at all after you changed the variable | The change hasn't reached the running container yet. | See Step 2. |

### Step 2 — confirm Railway actually redeployed with the new value

Environment variables are baked into a container at process start — a
running PHP process never sees a variable change until it restarts.
Changing a variable in Railway's **Variables** tab normally triggers an
automatic redeploy, but confirm it: open **Deployments** and check that the
newest deployment's timestamp is *after* the moment you saved the variable.
If not, click **Deploy** manually.

### Step 3 — self-test the exact variable Railway has, without deploying

Use the new [php/db-check.php](php/db-check.php) script together with the
[Railway CLI](https://docs.railway.app/guides/cli), which can run a command
locally using your **real, currently-deployed** Railway variables:

```bash
railway login
railway link                       # select this project/service
railway run php php/db-check.php
```

This prints the host/port/database/user Manor Cares actually resolved from
`SUPABASE_DB_URL` (password never shown — only its length, plus a warning
if it's still bracket-wrapped), flags a direct-connection (IPv6-only) host,
then attempts a real connection so you see the exact PDO error immediately
in your own terminal instead of digging through Railway's log viewer.

### Step 4 — remember `.env` is local-only

`php/db.php`'s dotenv loader only reads `.env` when the file exists on
disk, and Railway never receives it — it's git-ignored on purpose (see
`.gitignore`). Fixing your local `.env` has **zero effect on production**;
the value must be corrected in Railway's **Variables** tab for the actual
deployed service.
