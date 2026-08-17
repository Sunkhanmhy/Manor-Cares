# Manor Cares

Luxury cleaning services marketing website: static HTML/CSS/JS pages, a
subscription/pricing page that hands off to an embedded one-time payment
page, and a small PHP backend that only sends contact/quote-request emails.

## Stack

- **Frontend:** static HTML/CSS + vanilla JS (`js/script.js`). No build
  step is required — [Vite](https://vitejs.dev) is wired up only for teams
  that want a local dev server with HMR (`npm run dev`).
- **Backend:** a single PHP endpoint, `php/contact-handler.php`, used by
  the "Send Us A Message" form (`contact.html`) and the "Request Clean-up
  Quote" form (`services.html`/`subscription.html`). It sends mail via
  `php/mailer.php`, which picks **Resend** (HTTPS API) or **SMTP/PHPMailer**
  automatically based on which env vars are set.
- **Payments:** `subscription.html`'s pricing plans each link to
  `one-time-payment.html?plan=<id>`, which loads that plan's payment page
  inside a full-viewport iframe (see "One-time payment page" below).
- **Security:** input sanitization + a honeypot field + optional
  `ALLOWED_ORIGIN` check on the contact/quote form endpoint.

---

## 1. Quick start (local development)

```bash
cp .env.example .env
php -S localhost:8000
```

Visit `http://localhost:8000`. No database, migration, or seeding step is
needed — this app has no accounts/login.

### Optional: Vite dev server

```bash
npm install
npm run dev      # http://localhost:5173, proxies /php to `php -S localhost:8000`
```

---

## 2. Contact form email (Resend or SMTP)

`php/contact-handler.php` sends through `php/mailer.php`, which picks a
backend automatically.

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

## 3. One-time payment page

`one-time-payment.html` has a single content section: a full-viewport
(`100%` width/height, auto-fits desktop/tablet/mobile) iframe. Each pricing
plan button on `subscription.html` links to
`one-time-payment.html?plan=<id>` with a unique `id` per plan (`starter`,
`essential`, `signature`, `deluxe`, `estate`, `platinum`).

To wire up real payment links, open `one-time-payment.html` and fill in the
`PAYMENT_LINKS` map at the bottom of the file:

```js
const PAYMENT_LINKS = {
  starter:   'https://your-payment-provider.com/starter-link',
  essential: 'https://your-payment-provider.com/essential-link',
  signature: 'https://your-payment-provider.com/signature-link',
  deluxe:    'https://your-payment-provider.com/deluxe-link',
  estate:    'https://your-payment-provider.com/estate-link',
  platinum:  'https://your-payment-provider.com/platinum-link',
};
```

If a plan has no link configured yet (or the `plan` query param doesn't
match), the page shows a fallback message with a link back to
`subscription.html` instead of an empty iframe.

---

## Security notes

- **Secrets**: `.env` (`RESEND_API_KEY`, `MAIL_SMTP_PASS`, etc.) must never
  be committed — see `.gitignore`. In production, set them as real
  environment variables.
- **Contact/quote forms**: honeypot field + optional `ALLOWED_ORIGIN`
  origin check in `php/contact-handler.php`; all output is escaped before
  being sent.

## Deploying to production

1. Set `RESEND_API_KEY`/`RESEND_FROM_EMAIL` (or `MAIL_SMTP_*`) and
   `MAIL_TO_ADDRESS`/`MAIL_TO_NAME` as real environment variables on your
   host (Railway, Render, Vercel, etc.) — never ship a `.env` file to
   production.
2. Fill in `one-time-payment.html`'s `PAYMENT_LINKS` map with your real
   payment page URLs (see §3).
3. Deploy the static files + `php/` folder to any PHP 8.1+ host.
