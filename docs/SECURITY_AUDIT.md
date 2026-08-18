# Security Audit Report — Alma Stella Paris

> **⚠️ ARCHIVE — snapshot of April 2026.** Findings and fixes reflect the codebase
> at that date; everything shipped since (checkout changes, AI pipelines, worldwide
> shipping…) is not covered. Do not treat this as the current security posture.

> **Date:** 2026-04-14
> **Last updated:** 2026-04-15
> **Scope:** Full application security review (OWASP Top 10, Stripe, auth, headers, uploads, dependencies)
> **Status:** Audit complete — 10/14 issues fixed, 4 accepted or deferred
>
> **Fixed:**
> - ~~C2 (CVE-2025-64500): Upgraded to Symfony 7.4 LTS (v7.4.8)~~
> - ~~H1+H2 (CSRF): Stateless tokens on all forms and AJAX endpoints~~
> - ~~H3 (Open redirect): Referer host validation in CurrencyController~~
> - ~~H4 (Security headers): SecurityHeadersSubscriber (CSP, X-Frame-Options, HSTS, etc.)~~
> - ~~M1 (Rate limiting): Login 5/15min, admin 3/15min, registration 5/30min, checkout~~
> - ~~M2 (Password in session): Hashed before storage during OTP flow~~
> - ~~M3+M5 (Upload validation): Assert\File constraints + MIME check in ImageProcessor~~
>
> **Remaining:**
> - C1 (Stripe webhook): Deferred — existing cron-based PendingOrderVerifier is acceptable
> - M4 (Email enumeration): Accepted as UX trade-off
> - B1-B3 (Low severity): Documented, no code change needed

---

## Summary by severity

| Severity | Count |
|----------|-------|
| **CRITICAL** | 2 |
| **HIGH** | 4 |
| **MEDIUM** | 5 |
| **LOW** | 3 |

---

## CRITICAL

### C1 — No Stripe webhook endpoint (no signature verification) — ⏸️ DEFERRED

The application has **no webhook endpoint** for Stripe. No `Webhook::constructEvent()`, no `STRIPE_WEBHOOK_SECRET`. Payment confirmation relies entirely on the client-side return flow (`/payment/confirm`). An attacker could potentially confirm an order without actual payment by manipulating the client-side flow.

The `PendingOrderVerifier` (scheduler) checks pending orders after 1 hour, but this window is wide.

| Detail | Value |
|--------|-------|
| **Files** | `src/Service/StripeService.php`, `src/Controller/Shop/CheckoutController.php` |
| **Impact** | Order fraud, unverified payments |
| **Fix** | Implement a webhook controller (`/stripe/webhook`) with `Webhook::constructEvent()` signature verification for `payment_intent.succeeded` and `payment_intent.payment_failed` events |
| **Status** | **Deferred** — existing `PendingOrderVerifier` cron is acceptable for current volume |

---

### C2 — CVE-2025-64500: `symfony/http-foundation` v7.2.9 — ✅ FIXED

`composer audit` reports a **high** severity vulnerability: incorrect parsing of `PATH_INFO` can lead to limited authorization bypass.

| Detail | Value |
|--------|-------|
| **Package** | `symfony/http-foundation` v7.2.9 |
| **CVE** | CVE-2025-64500 |
| **Impact** | Limited authorization bypass |
| **Fix** | `composer update symfony/http-foundation` to patched version (>=7.3.7 or next 7.2.x patch) |
| **Status** | **Fixed** — upgraded to Symfony 7.4 LTS (v7.4.8) on 2026-04-15 |

---

## HIGH

### H1 — Missing CSRF on manual forms (non-Symfony Form) — ✅ FIXED

Forms that do **not** use the Symfony Form component have **no CSRF protection**:

- **Checkout** — `templates/shop/checkout/index.html.twig:27`
- **Profile** — `templates/shop/account/profile.html.twig:30,79` (update_info + change_password)
- **Addresses** — `templates/shop/account/address_form.html.twig:27` (add/edit)

The corresponding controllers (`CheckoutController`, `AccountController`) do not validate any CSRF token.

| Detail | Value |
|--------|-------|
| **Impact** | CSRF attacks on checkout, profile updates, password changes, address management |
| **Fix** | Add `<input type="hidden" name="_token" value="{{ csrf_token('submit') }}">` in each form + `isCsrfTokenValid('submit', ...)` validation in controllers |
| **Status** | **Fixed** — CSRF tokens added to all manual forms + controller validation (commit 899da05) |

---

### H2 — Missing CSRF on AJAX calls (fetch) — ✅ FIXED

Stimulus controllers send POST requests without CSRF tokens:

- `assets/controllers/cart_drawer_controller.js` — add/remove cart items
- `assets/controllers/wishlist_toggle_controller.js` — toggle wishlist
- `assets/controllers/coupon_code_controller.js` — validate/remove coupon

Only the `X-Requested-With: XMLHttpRequest` header is sent, which is not reliable CSRF protection.

| Detail | Value |
|--------|-------|
| **Impact** | CSRF attacks on cart, wishlist, and coupon operations |
| **Fix** | Add a `X-CSRF-Token` header in each fetch call + server-side validation, or use Symfony stateless CSRF via a meta tag |
| **Status** | **Fixed** — stateless CSRF tokens via `<meta>` tag + `X-CSRF-Token` header on all Stimulus fetch calls (commit 899da05) |

---

### H3 — Open redirect in `CurrencyController` — ✅ FIXED

`src/Controller/Shop/CurrencyController.php:28-29`: the `Referer` header is used directly in `$this->redirect($referer)` without validation. An attacker can craft a request with a malicious Referer to redirect users to external phishing sites.

| Detail | Value |
|--------|-------|
| **Impact** | Phishing, credential theft |
| **Fix** | Validate that the referer is an internal URL (same host) before redirecting |
| **Status** | **Fixed** — `parse_url()` host validation against `$request->getHost()` (commit 899da05) |

---

### H4 — No HTTP security headers — ✅ FIXED

No security headers are configured anywhere (no event subscriber, no NelmioSecurityBundle, nothing in `.htaccess`):

- `X-Content-Type-Options: nosniff` — missing
- `X-Frame-Options: DENY` — missing
- `Content-Security-Policy` — missing
- `Strict-Transport-Security` — missing
- `Referrer-Policy` — missing
- `Permissions-Policy` — missing

| Detail | Value |
|--------|-------|
| **Impact** | Clickjacking, MIME sniffing, lack of HTTPS enforcement |
| **Fix** | Create a `SecurityHeadersSubscriber` on `ResponseEvent` that adds all headers |
| **Status** | **Fixed** — `SecurityHeadersSubscriber` adds CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, HSTS in prod (commit 899da05) |

---

## MEDIUM

### M1 — Missing rate limiting on sensitive endpoints — ✅ FIXED

Only the contact form is rate-limited (3 req / 15min). Missing:

- **Customer login** (`/login`, `/connexion`) — brute force possible
- **Admin login** (`/admin/login`) — brute force on magic link generation
- **Registration** — flood possible
- **Checkout/payment** — no throttle
- **OTP submission** — 5 attempts max but no IP-based rate limit

| Detail | Value |
|--------|-------|
| **Files** | `config/packages/rate_limiter.yaml`, various controllers |
| **Fix** | Add rate limiters in `rate_limiter.yaml` for `login`, `admin_login`, `registration`, `checkout` |
| **Status** | **Fixed** — login 5/15min, admin_login 3/15min, registration 5/30min, checkout limiter (commit 899da05) |

---

### M2 — Plaintext password stored in session (registration OTP flow) — ✅ FIXED

`src/Controller/Shop/SecurityController.php:84`: the plaintext password is stored in `$session->set('_registration_data', ['password' => $password])` while awaiting OTP verification.

| Detail | Value |
|--------|-------|
| **Impact** | Password exposure if session storage is compromised |
| **Fix** | Hash the password before storing in session, or create the Customer in database with a `verified=false` flag |
| **Status** | **Fixed** — password hashed via `UserPasswordHasherInterface` before session storage, stored as `password_hash` (commit 899da05) |

---

### M3 — No MIME type / file size validation on image uploads — ✅ FIXED

`config/packages/vich_uploader.yaml` has no MIME type or size restriction. The `Product` entity has no `Assert\File` constraint. An admin could upload any file type.

| Detail | Value |
|--------|-------|
| **Files** | `config/packages/vich_uploader.yaml`, `src/Entity/Product.php` |
| **Fix** | Add `#[Assert\File(mimeTypes: ['image/jpeg', 'image/png', 'image/webp'], maxSize: '5M')]` constraints on Product upload fields |
| **Status** | **Fixed** — `#[Assert\File(maxSize: '5M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'])]` on thumbnail, wornPhoto, contextPhoto (commit 899da05) |

---

### M4 — Email enumeration on registration — ℹ️ ACCEPTED

`src/Controller/Shop/SecurityController.php:298`: the error message `register.error.email_already_used` reveals whether an email exists in the database. This is a common UX trade-off but a privacy risk.

| Detail | Value |
|--------|-------|
| **Impact** | User enumeration |
| **Fix** | Optional — keep for UX or unify error messages to prevent enumeration |
| **Status** | **Accepted** — kept for UX, standard e-commerce practice |

---

### M5 — `ImageProcessor` processes files without format validation — ✅ FIXED

`src/Service/ImageProcessor.php:26`: `decodePath()` is called without checking the file's MIME type. A malicious file disguised as an image could exploit a GD library vulnerability.

| Detail | Value |
|--------|-------|
| **Fix** | Validate MIME type with `mime_content_type()` before processing |
| **Status** | **Fixed** — `mime_content_type()` check before processing (commit 899da05) |

---

## LOW

### B1 — `|raw` filter in admin templates

`templates/admin/order/edit.html.twig:32,147`: `{{ order.statusLabel|raw }}` and `{{ order.itemsSummary|raw }}`. Currently safe because the Order entity methods use `htmlspecialchars()`, but fragile if the code evolves.

---

### B2 — Remember-me cookie lifetime: 30 days

`config/packages/security.yaml:50`: `lifetime: 2592000` (30 days). Acceptable for e-commerce but long. 7-14 days would be more prudent.

---

### B3 — APP_SECRET placeholder in `.env`

`.env:19`: `APP_SECRET=change_me_in_env_local`. Correct (overridden by `.env.local`) but fragile if someone deploys without `.env.local`.

---

## Positive findings

- Parameterized Doctrine queries everywhere (no SQL injection)
- CSRF on native Symfony form_login
- Admin magic link well implemented (single-use, 10min, signature)
- IDOR protection on customer addresses
- Password hashing with `algorithm: auto`
- `.gitignore` covers `.env.local` and `.env.*.local`
- `access_control` firewall correct (`^/admin` = `ROLE_ADMIN`)
- No hardcoded secrets in source code

---

## Remediation summary

| Issue | Status | Commit |
|-------|--------|--------|
| C2 — CVE-2025-64500 | ✅ Fixed | `4562de0` (Symfony 7.4 LTS) |
| H4 — Security headers | ✅ Fixed | `899da05` |
| H1+H2 — CSRF (forms + AJAX) | ✅ Fixed | `899da05` |
| H3 — Open redirect | ✅ Fixed | `899da05` |
| M1 — Rate limiting | ✅ Fixed | `899da05` |
| M2 — Password in session | ✅ Fixed | `899da05` |
| M3+M5 — Upload validation | ✅ Fixed | `899da05` |
| C1 — Stripe webhook | ⏸️ Deferred | — |
| M4 — Email enumeration | ℹ️ Accepted | — |
| B1-B3 — Low severity | ℹ️ Documented | — |
