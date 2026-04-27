# Deployment Guide — Alma Stella Paris

> **Last updated:** 2026-04-15
> **Status:** Pre-production — infrastructure preparation done in code,
> awaiting domain purchase and hosting setup.

---

## Table of contents

1. [Infrastructure overview](#infrastructure-overview)
2. [Cloudflare setup](#cloudflare-setup)
3. [Cloudflare Turnstile (bot protection)](#cloudflare-turnstile-bot-protection)
4. [Environment configuration](#environment-configuration)
5. [Server requirements](#server-requirements)
6. [Deployment steps](#deployment-steps)
7. [Post-deployment checklist](#post-deployment-checklist)

---

## Infrastructure overview

```
┌─────────────────┐     ┌───────────────────┐     ┌─────────────────────┐
│   Visitor        │────▶│   Cloudflare       │────▶│   Hosting server     │
│   (browser)      │◀────│   (DNS + CDN +     │◀────│   (PHP 8.3 + Apache  │
│                  │     │    DDoS + Turnstile)│     │    + MariaDB 10.11)  │
└─────────────────┘     └───────────────────┘     └─────────────────────┘
```

- **DNS & CDN:** Cloudflare (free plan sufficient)
- **Bot protection:** Cloudflare Turnstile on public forms
- **SSL:** Managed by Cloudflare (Full Strict mode recommended)
- **Payment:** Stripe (always charges in USD)
- **Email:** SMTP provider (Postmark, Mailgun, or Amazon SES)

---

## Cloudflare setup

### 1. Add the domain

1. Create a Cloudflare account at [dash.cloudflare.com](https://dash.cloudflare.com)
2. Add the domain (e.g. `almastellaparis.com`)
3. Cloudflare provides two nameservers — update them at the domain registrar
4. Wait for DNS propagation (usually < 24h)

### 2. DNS records

| Type  | Name              | Content              | Proxy |
|-------|-------------------|----------------------|-------|
| A     | `@`               | Server IP            | ✅ Proxied |
| CNAME | `www`             | `almastellaparis.com`| ✅ Proxied |
| MX    | `@`               | Mail provider        | ❌ DNS only |
| TXT   | `@`               | SPF record           | ❌ DNS only |
| TXT   | `_dmarc`          | DMARC record         | ❌ DNS only |

### 3. SSL/TLS settings

- **Encryption mode:** Full (Strict)
- **Always Use HTTPS:** On
- **Minimum TLS Version:** 1.2
- **Automatic HTTPS Rewrites:** On

### 4. Caching

Cloudflare automatically caches static assets (CSS, JS, images, fonts) when
the proxy is active. The `.htaccess` file is configured with proper cache
headers:

- **CSS/JS (fingerprinted):** 1 year + `Cache-Control: immutable`
- **Images:** 1 month
- **Fonts:** 1 year

### 5. Trusted proxies (already configured)

The application is configured to trust Cloudflare's proxy headers in
production (`config/packages/framework.yaml`):

```yaml
when@prod:
    framework:
        trusted_proxies: '%env(TRUSTED_PROXIES)%'
        trusted_headers: ['x-forwarded-for', 'x-forwarded-host', 'x-forwarded-proto', 'x-forwarded-port']
```

Set `TRUSTED_PROXIES=REMOTE_ADDR` in the production environment. This tells
Symfony to read the real client IP from the `X-Forwarded-For` header sent by
Cloudflare. Without this, rate limiting and logging would see Cloudflare's IP
instead of the visitor's.

---

## Cloudflare Turnstile (bot protection)

Turnstile is Cloudflare's invisible CAPTCHA alternative. It protects public
forms against bots without adding friction for legitimate users.

### Protected forms

| Form | Controller | Existing protections |
|------|-----------|---------------------|
| Contact | `ContactController` | Honeypot + Rate limit (3/15min) + Turnstile |
| Testimonial | `TestimonialController` | Token-gated URL + Turnstile |

### How it works

1. **Frontend:** A Stimulus controller (`turnstile_controller.js`) loads the
   Turnstile script from Cloudflare and renders an invisible widget
2. **On submit:** The widget adds a hidden `cf-turnstile-response` field
3. **Backend:** `TurnstileVerifier` service sends the token to Cloudflare's
   API for validation
4. **Fail-open:** If Cloudflare's API is unreachable, the submission goes
   through (avoids blocking legitimate users)

### Setup

1. Go to [Cloudflare Dashboard → Turnstile](https://dash.cloudflare.com) → **Add site**
2. Choose **Managed** challenge type (recommended)
3. Add the production domain
4. Copy the **Site Key** and **Secret Key** to `.env.prod.local`:

```env
TURNSTILE_SITE_KEY=0x4AAAAAAA...
TURNSTILE_SECRET_KEY=0x4AAAAAAA...
```

### Development mode

When `TURNSTILE_SITE_KEY` and `TURNSTILE_SECRET_KEY` are empty (default in
`.env`), Turnstile is completely disabled:
- The widget does not render in templates
- `TurnstileVerifier::verify()` returns `true` without calling the API
- Forms work exactly as before

### Files involved

```
src/Service/TurnstileVerifier.php           # Server-side token verification
src/Twig/TurnstileExtension.php             # Twig functions: turnstile_site_key(), turnstile_enabled()
assets/controllers/turnstile_controller.js  # Stimulus controller (loads script, renders widget)
templates/shop/_turnstile.html.twig         # Reusable partial (include in any form)
```

### Adding Turnstile to a new form

1. Include the partial in the template, before the submit button:
   ```twig
   {% include 'shop/_turnstile.html.twig' %}
   ```

2. Add verification in the controller:
   ```php
   $turnstileToken = (string) $request->request->get('cf-turnstile-response', '');
   if (!$turnstileVerifier->verify($turnstileToken, $request->getClientIp())) {
       $this->addFlash('error', 'form.error.bot_detected');
       return $this->redirectToRoute('...');
   }
   ```

### Content Security Policy

The CSP in `SecurityHeadersSubscriber` already allows Turnstile:
- `script-src: https://challenges.cloudflare.com`
- `frame-src: https://challenges.cloudflare.com`

---

## Environment configuration

### Template

A documented production template is available at **`.env.prod.dist`**.
To configure production:

```bash
cp .env.prod.dist .env.prod.local
# Edit .env.prod.local with real values
```

### Required variables

| Variable | Example | Notes |
|----------|---------|-------|
| `APP_SECRET` | `a1b2c3d4...` | `php -r "echo bin2hex(random_bytes(16));"` |
| `DATABASE_URL` | `mysql://user:pass@host/db` | MariaDB 10.11+ |
| `MAILER_DSN` | `smtp://key@smtp.postmarkapp.com:587` | Transactional email provider |
| `STRIPE_PUBLIC_KEY` | `pk_live_...` | Stripe Dashboard → API keys |
| `STRIPE_SECRET_KEY` | `sk_live_...` | Stripe Dashboard → API keys |
| `STRIPE_WEBHOOK_SECRET` | `whsec_...` | Stripe Dashboard → Webhooks |
| `TRUSTED_PROXIES` | `REMOTE_ADDR` | Required behind Cloudflare |
| `TURNSTILE_SITE_KEY` | `0x4AAA...` | Cloudflare Dashboard → Turnstile |
| `TURNSTILE_SECRET_KEY` | `0x4AAA...` | Cloudflare Dashboard → Turnstile |
| `DEFAULT_URI` | `https://www.almastellaparis.com` | For CLI URL generation |

### Compiling env for production

```bash
composer dump-env prod
```

This creates `.env.local.php` with all variables compiled — no file parsing
at runtime.

---

## Server requirements

| Requirement | Minimum |
|------------|---------|
| PHP | 8.3 |
| MariaDB | 10.11 |
| Web server | Apache 2.4+ with `mod_rewrite`, `mod_expires`, `mod_headers` |
| PHP extensions | `intl`, `mbstring`, `pdo_mysql`, `gd` or `imagick`, `curl`, `openssl` |
| Composer | 2.x |
| Disk | ~500 MB (app + vendor + uploads) |

### Apache modules required

```bash
a2enmod rewrite expires headers
```

### Cron jobs

#### Symfony Scheduler (background tasks)

The application uses Symfony Scheduler for background tasks (pending order
cleanup, OTP expiry). Add this cron entry:

```cron
* * * * * cd /path/to/project && php bin/console messenger:consume scheduler_default --time-limit=55 --memory-limit=128M
```

#### AI image generation queue (`gemini_async`)

Visuels IA generation messages are dispatched to the `gemini_async` Doctrine
transport and consumed exclusively by the cron worker — the `/ai-status`
polling endpoint is read-only (it does not consume messages, to avoid blocking
PHP-FPM slots during 30-90s Gemini calls).

```cron
* * * * * cd /path/to/project && php bin/console messenger:consume gemini_async --limit=10 --time-limit=55 --memory-limit=256M --no-debug --quiet
```

Notes for shared hosting (e.g., **O2Switch**):
- `messenger:consume` is a one-shot CLI invocation, no daemon required.
- `--limit=10` caps the number of messages processed per cron run; the next
  cron picks up the rest one minute later.
- `--memory-limit=256M` accommodates Gemini base64 payloads.
- `--no-debug` avoids the Symfony TraceableEventDispatcher bug on
  WorkerStoppedEvent (only triggers in dev mode but keeping the flag is
  harmless in prod).
- Use the absolute path of the project's PHP binary in the cPanel cron UI
  (e.g., `/usr/local/bin/ea-php83`), not the system `php` shim.

#### Local development (DDEV)

DDEV mirrors this setup via the [`ddev-cron` add-on](https://github.com/ddev/ddev-cron).
The crontab is versioned at `.ddev/web-build/messenger.cron` and is loaded
automatically on `ddev start` / `ddev restart`. Logs are written to
`/tmp/messenger-gemini.log` and `/tmp/messenger-scheduler.log` inside the
web container. Inspect with: `ddev exec tail -f /tmp/messenger-gemini.log`.

---

## Deployment steps

### First deployment

```bash
# 1. Clone the repository
git clone <repo-url> /var/www/alma-stella
cd /var/www/alma-stella

# 2. Install dependencies (no dev)
composer install --no-dev --optimize-autoloader

# 3. Configure environment
cp .env.prod.dist .env.prod.local
# Edit .env.prod.local with real values
composer dump-env prod

# 4. Run database migration
php bin/console doctrine:migrations:migrate --no-interaction

# 5. Set up Messenger transports (creates messenger_messages table for the
#    Doctrine transport so the AI generation queue can accept messages)
php bin/console messenger:setup-transports

# 6. Build assets
php bin/console tailwind:build --minify
php bin/console asset-map:compile

# 7. Warm up cache
php bin/console cache:warmup

# 8. Set permissions
chown -R www-data:www-data var/ public/uploads/
```

### Subsequent deployments

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console tailwind:build --minify
php bin/console asset-map:compile
php bin/console cache:clear
```

---

## Post-deployment checklist

- [ ] DNS points to Cloudflare nameservers
- [ ] Cloudflare proxy enabled (orange cloud) on A and CNAME records
- [ ] SSL mode set to Full (Strict) in Cloudflare
- [ ] `TRUSTED_PROXIES=REMOTE_ADDR` in production env
- [ ] Turnstile site created and keys configured
- [ ] Stripe webhook endpoint configured: `https://domain.com/en/checkout/webhook`
- [ ] Stripe webhook signing secret set in `STRIPE_WEBHOOK_SECRET`
- [ ] Transactional email provider configured and DNS records added (SPF, DKIM, DMARC)
- [ ] Cron job for `messenger:consume scheduler_default` running
- [ ] Cron job for `messenger:consume gemini_async` running (AI visuals fallback)
- [ ] Test contact form (Turnstile widget visible, email received)
- [ ] Test checkout flow end-to-end (Stripe live mode)
- [ ] Test admin login (magic link email received)
- [ ] Verify security headers: `curl -I https://domain.com`
- [ ] Verify HSTS header present in production
- [ ] Run Lighthouse audit on homepage
