# Deployment Guide — Alma Stella Paris

> **Last updated:** 2026-09-20
> **Status:** Deployed on o2switch, shop not yet open to the public.
>
> This file was first written in April 2026 as a plan, around a Cloudflare
> proxy that was never put in place. It now describes the infrastructure as it
> actually is.

---

## Table of contents

1. [Infrastructure overview](#infrastructure-overview)
2. [Caching & client IP](#caching--client-ip)
3. [Cloudflare Turnstile (bot protection)](#cloudflare-turnstile-bot-protection)
4. [Environment configuration](#environment-configuration)
5. [Server requirements](#server-requirements)
6. [Deployment steps](#deployment-steps)
7. [Post-deployment checklist](#post-deployment-checklist)

---

## Infrastructure overview

```
┌─────────────────┐              ┌──────────────────────────────┐
│   Visitor        │─────────────▶│  o2switch (shared hosting)    │
│   (browser)      │◀─────────────│  LiteSpeed + PHP 8.3          │
└─────────────────┘   no proxy    │  + MariaDB 10.11              │
                      no CDN      └───────────────┬──────────────┘
                                                  │ server to server
                                                  ▼
                                   Stripe · Turnstile · Gemini · SMTP
```

- **Request path:** visitors reach the host directly. Nothing sits in front —
  no CDN, no reverse proxy. This matters for caching and for client IPs, both
  covered in the next section.
- **Bot protection:** Cloudflare Turnstile on public forms. It is an API the
  server calls, not a proxy in front of the site, and it is the **only** use of
  Cloudflare here.
- **Domain, DNS and TLS:** all at o2switch. The domain is registered there, its
  zone is served by o2switch's nameservers, and the certificate is issued and
  renewed by the host. Nothing to do at a third party.
- **Payment:** Stripe, always charged in **EUR** (see "Money" in
  `ARCHITECTURE.md` — display currencies are cosmetic).
- **Email:** SMTP provider.

---

## Caching & client IP

Two consequences of having nothing in front of the host.

### Static asset caching

`public/.htaccess` gives every CSS and JS a year of `Cache-Control: immutable`,
images a month, fonts a year. There is no edge cache — these headers act on
visitors' browsers alone, and there is nothing to purge on a deploy.

That year is only safe because every CSS and JS carries a version in its URL.
AssetMapper does it with a content hash in the filename; the back-office files
under `public/css/` and `public/js/` are plain paths, so
`DashboardController::configureAssets()` appends `?v=<filemtime>` to each. Add
an unversioned stylesheet or script under `public/` and a deploy will ship new
markup to browsers still holding the old file — `immutable` means they will not
even ask the server whether it changed, so no `git pull` or `cache:clear` can
reach them and each visitor has to force-reload by hand.

### Trusted proxies — must stay empty

```yaml
# config/packages/framework.yaml
when@prod:
    framework:
        trusted_proxies: '%env(TRUSTED_PROXIES)%'
        trusted_headers: ['x-forwarded-for', 'x-forwarded-host', 'x-forwarded-proto', 'x-forwarded-port']
```

**`TRUSTED_PROXIES` must be empty in production.** It exists for a reverse
proxy that is not there.

Setting it to `REMOTE_ADDR` — as this guide wrongly instructed until
2026-09-20 — tells Symfony to trust the machine the request came from. With a
proxy in front that is the proxy; without one it is the **visitor**, who can
then set `X-Forwarded-For` to anything and have `Request::getClientIp()` return
it. Every IP-based control in the app reads that value:

| Where | What a spoofed IP buys |
|---|---|
| `MaintenanceModeSubscriber` | walks past maintenance mode using an allowlisted IP |
| `AdminLoginController` | unlimited magic-link emails to the admin address |
| `SecurityController`, `ContactController`, `CheckoutController` | rate limiters reset on every request |
| `security.yaml` `login_throttling` | customer login brute-force unthrottled |

If a proxy or CDN is ever put in front of the site, set `TRUSTED_PROXIES` to
that proxy's ranges — never to `REMOTE_ADDR` on a shared host, where the value
is only as trustworthy as the immediate peer.

---

## Cloudflare Turnstile (bot protection)

Turnstile is Cloudflare's invisible CAPTCHA alternative. It protects public
forms against bots without adding friction for legitimate users.

This is the only part of Cloudflare in use: the domain is not on Cloudflare's
nameservers and no traffic is proxied through it. Setting Turnstile up needs
nothing but an account and a site registered in the dashboard.

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

The server keeps its real values in **`.env.local`**, which Dotenv loads for
every environment. `.env.prod.local` would work too — it is read later and wins
— but only one of the two should carry a given variable, or the winner is
decided by a load order nobody remembers:

```
.env  →  .env.local  →  .env.prod  →  .env.prod.local
```

```bash
cp .env.prod.dist .env.local
# Edit .env.local with real values, on the server only (it is gitignored)
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
| `TRUSTED_PROXIES` | *(empty)* | Nothing proxies this site — see "Trusted proxies" |
| `TURNSTILE_SITE_KEY` | `0x4AAA...` | Cloudflare Dashboard → Turnstile |
| `TURNSTILE_SECRET_KEY` | `0x4AAA...` | Cloudflare Dashboard → Turnstile |
| `DEFAULT_URI` | `https://www.almastellaparis.com` | For CLI URL generation |

### Compiling env for production — optional, and sticky

```bash
composer dump-env prod
```

This creates `.env.local.php` with all variables compiled, so nothing is parsed
at runtime. It is **not** currently used on this deployment, and it is worth
knowing why that matters before running it: once `.env.local.php` exists,
`Dotenv::bootEnv()` loads it and stops — every `.env*` file is ignored. Editing
`.env.local` then changes nothing until `composer dump-env prod` is run again,
with no warning and no error.

If it is ever adopted, add that command to the deployment steps above, right
after the `git pull`.

---

## Server requirements

| Requirement | Minimum |
|------------|---------|
| PHP | 8.3 |
| MariaDB | 10.11 |
| Web server | LiteSpeed (o2switch) — Apache-compatible, reads the same `.htaccess` |
| PHP extensions | `intl`, `mbstring`, `pdo_mysql`, `gd` or `imagick`, `curl`, `openssl` |
| Composer | 2.x |
| Disk | ~500 MB (app + vendor + uploads) |

### Web server modules

o2switch runs LiteSpeed with `mod_rewrite`, `mod_expires` and `mod_headers`
equivalents already active, so `public/.htaccess` works as written and there is
nothing to enable. On a stock Apache host the equivalent would be:

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

Nothing has to be purged and no one has to force-reload: asset URLs change
whenever their contents do, so a browser fetches the new file simply because it
has never seen that URL.
Migrations are the only step that can be skipped when a release touches no
mapping — `doctrine:migrations:migrate` is a no-op then, so it stays in the
list.

---

## Post-deployment checklist

- [ ] HTTPS issued and forced by o2switch (domain, DNS and certificate all live there)
- [ ] `TRUSTED_PROXIES` empty in the production env (nothing proxies the site —
      a non-empty value makes every IP-based rate limit and the maintenance
      allowlist spoofable)
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
