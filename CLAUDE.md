# CLAUDE.md — Alma Stella Paris

> **Primary reference file for Claude Code.**
> Read this file at the start of every session before touching any code.
> All architectural, business, and UX decisions documented here are **validated and final**
> unless explicitly overridden by the developer in a new session.

---

## Project overview

E-commerce platform for **Alma Stella Paris**, a French jewelry brand selling
curated stainless steel jewelry with natural stones.

- **Owner:** Estelle (French resident, travels between France and Mexico)
- **Target audience:** US and Canadian customers (high purchasing power)
- **Brand positioning:** Accessible-premium — beautiful everyday jewelry,
  water-resistant, personally curated between Paris and Mexico
- **Instagram:** [@alma_stella_paris](https://www.instagram.com/alma_stella_paris/)

---

## Code quality rules — non-negotiable

### Language
- **All code must be written in English** — variable names, function names,
  class names, method names, comments, docblocks, commit messages, Twig template
  variables, EasyAdmin labels in PHP, enum cases, service names, route names.
- **Exception:** user-facing French content (product descriptions, UI copy,
  email templates) stays in French with correct accents — `é à ù ê î ô û ç œ æ`.
- **Never** write `e` instead of `é`, `a` instead of `à`, etc. in French content.
  Use proper UTF-8 characters systematically.

```php
// ✅ Correct
private ShippingTier $shippingTier = ShippingTier::Standard;
// label in EasyAdmin form: 'Pièce légère' ← accent required

// ❌ Wrong
private $tier_expedition;   // French variable name
// label: 'Piece legere'    // Missing accents
```

### Code style
- PSR-12 strictly enforced — run `ddev exec vendor/bin/php-cs-fixer fix` before any commit
- Strict types on every PHP file: `declare(strict_types=1);`
- Type hints on all method signatures, including return types
- No magic strings — use constants, enums, or configuration
- Services are injected via constructor, never via `$container->get()`

### Code quality tools

All tools run inside DDEV. **GrumPHP** enforces PHPStan + CS Fixer on every commit
via pre-commit hooks.

| Tool | Config file | Command |
|---|---|---|
| PHP CS Fixer | `.php-cs-fixer.dist.php` | `ddev exec vendor/bin/php-cs-fixer fix` |
| PHPStan (level 6) | `phpstan.neon` | `ddev exec vendor/bin/phpstan analyse` |
| PHPUnit | `phpunit.dist.xml` | `ddev exec vendor/bin/phpunit` |
| GrumPHP | `grumphp.yml` | Automatic on `git commit` |

- **PHPStan level 6** with Symfony + Doctrine extensions
- **PHP CS Fixer** — `@Symfony` + `@Symfony:risky` + `declare_strict_types` +
  `native_function_invocation`
- **Tailwind rebuild:** run `ddev exec php bin/console tailwind:build` after
  every template or CSS change
- **Architecture updates:** update `docs/ARCHITECTURE.md` when adding entities,
  controllers, routes, or Stimulus controllers

---

## Tech stack

| Layer | Technology | Version |
|---|---|---|
| Framework | Symfony | 7.4.x (LTS) |
| PHP | PHP | 8.3 |
| Database | MariaDB | 10.11+ |
| ORM | Doctrine | 3.x |
| CSS | Tailwind CSS | 4.x |
| Admin | EasyAdmin | 5.x |
| Dev environment | DDEV | latest |
| Payment | Stripe PHP SDK | latest |
| Email | Symfony Mailer | (built-in) |
| Scheduler | Symfony Scheduler + Messenger | 7.2.x |
| PDF invoices | dompdf/dompdf | latest |
| Image processing | Intervention Image (GD) | 4.x |
| Password reset | symfonycasts/reset-password-bundle | latest |
| Bot protection | Cloudflare Turnstile | (API) |
| Instagram feed | Behold.so | (API, cached 6h) |
| Exchange rates | open.er-api.com | (API, cached 6h) |

**Drupal is not used in this project.** It is referenced only as the developer's
background context.

---

## Working method

- **Incremental:** Work step by step, one sub-step at a time. Don't batch
  multiple sub-steps without user confirmation.
- **Current milestone:** Check `docs/ROADMAP.md` for the next unchecked sub-step.
- **Roadmap tracking:** After completing each task or sub-task, immediately
  check it off in `docs/ROADMAP.md` (`- [ ]` → `- [x]`). This keeps the
  roadmap as the single source of truth for project progress.
- **Tailwind rebuild + asset compile:** After every template or CSS change, run
  both commands: `ddev exec php bin/console tailwind:build` then
  `ddev exec php bin/console asset-map:compile`. The CSS rebuild alone is not
  enough — assets must be recompiled for changes to be visible.
- **Architecture updates:** Update `docs/ARCHITECTURE.md` when adding entities,
  controllers, routes, or Stimulus controllers.
- **TEMPORARY (remove when in production):** Do NOT create new migration files.
  Always modify the existing initial migration and recreate the DDEV environment
  (`ddev delete --omit-snapshot && ddev start`) instead of running new migrations.

---

## Essential commands

```bash
# DDEV
ddev start / ddev stop / ddev restart
ddev ssh                    # SSH into container
ddev exec <command>         # Run command in container

# Symfony
ddev exec php bin/console cache:clear
ddev exec php bin/console doctrine:migrations:migrate
ddev exec php bin/console doctrine:fixtures:load

# Assets (NO npm — uses Symfony TailwindCSS Bundle)
ddev exec php bin/console tailwind:build   # Rebuild CSS
ddev exec php bin/console asset-map:compile # Compile assets (always run after tailwind:build)

# Scheduler (Symfony Scheduler + Messenger)
ddev exec php bin/console messenger:consume scheduler_default  # Run scheduled tasks
ddev exec php bin/console debug:scheduler                      # List scheduled tasks

# Manual CLI commands (also run automatically by scheduler)
ddev exec php bin/console app:verify-pending-orders            # Verify pending orders against Stripe
ddev exec php bin/console app:clean-expired-reservations       # Release expired product reservations
ddev exec php bin/console app:send-testimonial-requests        # Send J+14 testimonial emails
ddev exec php bin/console app:clean-abandoned-orders           # Clean up stale pending orders

# Code quality
ddev exec vendor/bin/php-cs-fixer fix      # Fix code style
ddev exec vendor/bin/phpstan analyse       # Static analysis
ddev exec vendor/bin/phpunit               # Tests
```

---

## Developer interaction protocol

> **This is mandatory — Claude Code must follow this for every key decision.**

Before implementing any feature that involves a significant architectural choice,
a third-party service selection, or a UX pattern, **stop and ask the developer**
using an interactive question with checkbox suggestions.

### When to ask
- Choice between two valid implementation approaches
- Third-party API or bundle selection
- Database schema decisions that are hard to reverse
- Any feature not explicitly specced in `ROADMAP.md`
- Security-sensitive implementations (auth, payment, API keys)

### How to ask
Present options as a numbered or checkbox list with a brief trade-off note.
Wait for explicit confirmation before proceeding.

```
Example:
"Before implementing the currency selector, I need your input:

How should we store the visitor's currency preference?
[ ] A — Session (simplest, lost on browser close)
[ ] B — Cookie with 30-day expiry (persists across visits)
[ ] C — User account preference (requires login)

Recommended: B — matches e-commerce standard behavior."
```

---

## Architecture decisions (validated)

See `ARCHITECTURE.md` for full technical details.

### Key decisions summary
- **Reference currency:** EUR — all `base_price` stored in EUR in the database
- **Currency display:** cosmetic conversion via live exchange rates (open.er-api.com,
  cached 6h) — Stripe always charges in EUR
- **Shipping model:** `ShippingTier` enum with costs baked into display price —
  no dynamic shipping calculator at checkout
- **Social publishing:** Pinterest and TikTok Shop via API (direct); Instagram
  via deep link (mobile app) — no direct Meta API publishing
- **No geo-pricing:** identical prices for all countries — discrimination tarifaire
  géographique explicitly rejected

---

## Localisation rules

> Read `LOCALISATION.md` for full implementation details.

### Quick reference
- Prices stored in **EUR**, displayed in visitor's chosen currency
- Supported currencies: `EUR`, `USD`, `CAD`, `GBP`, `MXN`
- Default currency: `EUR`
- Currency preference stored in **session + cookie (30 days)**
- Non-EUR prices carry a disclaimer: *"Prices shown in [currency] are indicative.
  You will be charged in EUR at checkout."*
- French content must use correct UTF-8 accented characters at all times
- Product descriptions are bilingual FR/EN (see content guidelines in `DESIGN.md`)
- **EasyAdmin is in French** — locale `fr`, all menu items, labels, and
  dashboard text must be in French. Field labels use format `Nom (EN)` / `Nom (FR)`
  to distinguish bilingual fields.

---

## Design reference

> See `DESIGN.md` for full design system and `docs/design/screenshots/` for
> base44 prototype screenshots.

### Quick palette reference
```
Background:  #FAF8F4  (warm white)
Text:        #2C2418  (warm near-black)
Accent/CTA:  #C9A84C  (warm gold)
Surface:     #F0EBE1  (linen)
Hover:       #E8DDD0  (soft taupe)
```

---

## Out of scope — never implement without explicit discussion

- Geo-pricing (different prices per country)
- Direct Instagram API publishing (use deep link instead)
- Multi-currency Stripe charges (always charge EUR)
- Complex shipping weight calculator (ShippingTier enum is sufficient)
- Any Drupal dependency
