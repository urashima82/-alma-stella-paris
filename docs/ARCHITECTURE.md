# Architecture — Alma Stella Paris

> **Index, not narrative.** Only what a competent developer could not derive from the
> code: deliberate absences, cross-file invariants, constraints that look like mistakes
> until you know why. Single-file "why" belongs in that file's own docblock, not here.
> Anything a `grep` answers (entity fields, service method lists, routes, templates)
> does not belong here — it rots the day someone edits the code, and a wrong doc costs
> more than a missing one.
>
> Validated decisions live in `CLAUDE.md` ("Architecture decisions"). Feature design
> narratives live in `docs/milestones/`. The AI pipelines have their own design docs:
> `AI_GENERATION_PIPELINE.md` (visuals, M16) and `AI_CONTENT_FILL.md` (copy, M17).

## Orientation

### Directory map

```
src/
  Controller/Shop/     public storefront (locale-prefixed /en | /fr)
  Controller/Admin/    EasyAdmin CRUDs + magic-link login + AI workspace
  Entity/ Repository/ Enum/ Form/      Doctrine model + Symfony forms
  Service/             commerce services; Content/ Gemini/ Generator/ Prompt/ Visual/
                       are the two AI pipelines' service trees
  Analytics/           privacy-first page-view counting (collector + subscriber)
  Message*/ Command/ Schedule.php      scheduler jobs, each also exposed as app:* CLI
  Twig/ Security/ EventSubscriber/ DataFixtures/
templates/shop/        storefront · templates/admin/ + templates/email/ + templates/pdf/
assets/                Stimulus controllers + Tailwind entry (AssetMapper, no npm)
public/css/ public/js/ admin-only assets, deliberately OUTSIDE AssetMapper (see below)
docs/design/screenshots/   base44 prototype — the visual reference for the storefront
```

### Asset pipeline

- **No npm, no node.** Tailwind runs through the Symfony TailwindCSS bundle, JS through
  AssetMapper. After any template or CSS change **both** commands are required:
  `tailwind:build` then `asset-map:compile` — the CSS rebuild alone is not visible.
- **Admin assets are plain files in `public/css/` and `public/js/`**, loaded by
  `DashboardController` outside AssetMapper — EasyAdmin has its own asset system and
  these files (dark-mode fix, toast system, method override) must load inside it.

### Bilingual storefront — the route-pair contract

- Public routes are prefixed `/en` | `/fr` (`config/routes.yaml`). A page whose French
  path differs from the English one declares **two routes**: `name` (requirement
  `_locale: en`) and `name_fr` (requirement `_locale: fr`).
- **`base.html.twig` builds hreflang links and the language switcher by regenerating
  the *current* route with the other `_locale`.** For a route pair that generation
  violates the `_locale` requirement — so every route-pair page MUST override the
  `hreflang` and `language_switch_href` blocks with its twin route (12 templates do
  today). A new paired page that skips the override breaks the language switcher and
  ships wrong hreflang on that page only, and nothing else will tell you.
- Entity content is bilingual through field pairs (`name`/`nameFr`, `slug`/`slugFr`…)
  rendered via `LocaleProductExtension` filters — there is no translation catalogue
  for entity content, only for UI strings.

### Money

- Price columns are DECIMAL, mapped as **strings** (`'0.00'`); setters normalize via
  `number_format`, getters cast to float. Do not retype an entity price field as
  `float` — the string mapping is what keeps Doctrine change-tracking exact.

## Commerce model

- **Every piece is unique.** `Product.isSoldOut` is a boolean — there is no stock
  integer anywhere, and no quantity concept in cart, order or reservation. Sold pieces
  stay visible in the catalog for 14 days (`isVisibleInCatalog()`), by design.
- **One price computation, and it is not on the entity.**
  `ShippingCostProvider::getDisplayPrice()` (DB `ShippingSettings` override → enum
  fallback) is the single source of truth. `Product::getDisplayPrice()` **existed and
  was deliberately removed** (commit e4a26e0): it read the enum defaults directly, so
  checkout, promotions and the admin disagreed with product pages once real rates were
  configured. Never reintroduce a price computation that bypasses the provider.
- **Shipping is baked into the display price** (`ShippingTier` enum), not a checkout
  line. Included zone: EU-27 + UK + Switzerland, **metropolitan France only** — the
  zone list is `CheckoutController::INCLUDED_ZONE_COUNTRY_CODES`. Any other destination
  (DOM-TOM included: postal codes 97x/98x under country FR) pays each item's tier **a
  second time** as a visible "international shipping" line —
  `Order.shippingSurchargeEur`, included in `totalEur` so Stripe charges it without
  special-casing. The Stimulus live preview is display only: the surcharge is always
  recomputed server-side on submit.
- **Currency display is cosmetic.** Rates from open.er-api.com cached 6 h, silent EUR
  fallback when the API is down. Stripe charges EUR, always.
- **No product reviews, by consequence:** a unique piece sold once can never
  accumulate reviews, so social proof is brand-level `Testimonial`s (J+14 email,
  one per customer email, moderated) — do not add a per-product rating model.
- **Dormant on purpose:** `Product.availableIn` and `SiteSettings.activeCollection`
  belong to the France/Mexico dual-collection sales model retired in August 2026
  (Mexico stays as sourcing narrative only). The fields remain in schema and fixtures;
  do not build new features on them and do not delete them without reopening the
  decision.

## Orders & payment

- **No Stripe webhooks — validated decision.** Payment truth is pull-based, three
  layers, each covering the previous one's failure mode: (1) the Stimulus controller
  POSTs `/payment/confirm` right after payment, (2) the payment page auto-confirms on
  3DS redirect return, (3) `VerifyPendingOrdersMessage` re-checks pending orders
  against Stripe every 5 minutes. Removing any layer silently loses the orders the
  other two miss; adding a webhook means reopening the decision.
- **Two number sequences with different legal clocks.** Order references
  (`ASP-YYYY-XXXXX`) are assigned at order creation; invoice numbers (`FA-YYYY-XXXXX`)
  only at payment confirmation — French law requires invoice numbers sequential and
  gap-free (art. 242 nonies A du CGI), and abandoned orders would create gaps. Both are
  generated per-year in `OrderRepository`; a failed checkout must never consume an
  invoice number. Both generators are max+1 reads guarded twice: a DB `lock: true`
  read inside `wrapInTransaction` AND a process-level `LockFactory` flock — the DB
  gap locks alone do not conflict on an empty prefix (the year's first number), so
  dropping the flock reintroduces the duplicate race at every January rollover.
- **`OrderConfirmer` is the only way an order becomes paid.** Both callers (the
  `/payment/confirm` endpoint and the scheduler) delegate to it; inside ONE
  transaction under pessimistic locks it re-reads the order status (idempotence — an
  unlocked check-then-act let scheduler and controller both finalize: burned invoice
  number, duplicate emails, even a wrongful full refund), re-reads the product rows
  (double-sale detection), assigns the invoice number and flips the sold flags. A
  unique-piece conflict is persisted BEFORE the Stripe refund call, which carries an
  idempotency key: a crash between commit and refund can strand a `refund_pending`
  order for the admin, never refund twice. Conflict refunds are proportional to what
  was actually paid (order-level coupon included) — raw line totals over-refund.
- **A cancelled order always gets its PaymentIntent cancelled at Stripe too**
  (scheduler and manual admin cancellation, which asks Stripe for the intent's real
  status — `paidAt` only proves a completed confirmation flow). An open client_secret
  on a cancelled or superseded order is money captured that nothing will ever fulfil;
  the same reasoning cancels the old intent when checkout resets it on a total change.
- **Free orders are not supported — validated decision.** Stripe rejects intents under
  0,50 €, so the checkout clamps promotion discounts to keep the total chargeable, and
  entity validation refuses >100 % promotions and published 0 € products. Supporting a
  100 %-coupon flow means reopening the decision, not relaxing the clamp.
- **A pending order is reused, not duplicated**: the session's `_pending_order` is
  updated as long as its status is `Pending`. `AbandonedOrderCleaner` (scheduler) is
  what makes this safe — stale pending orders are cleaned and their reservations
  released.
- `OrderItem` snapshots price and bilingual name at order time — order history must
  survive product edits and deletion.
- **Email failure never blocks the payment flow** (caught and logged). An order whose
  confirmation mail failed is still a paid order.
- **Invoices are token-gated, not auth-gated** (`invoiceToken` UUID on Order): guests
  have no account, yet must reach their invoice from the delivered email. The same
  token gates the confirmation and tracking pages — order references are sequential,
  hence enumerable, and those pages show the customer's email and address.

## Reservation system (anti-double-sell)

The cross-file invariant that makes "pièce unique" safe under concurrency:

- One `Reservation` per product (OneToOne, unique constraint), keyed by PHP session id,
  15 minutes, created for the whole cart when a visitor **enters checkout**
  (identification step). Creation goes through `ReservationRepository::tryInsert()`
  (raw `INSERT IGNORE`) so the unique constraint arbitrates concurrent checkouts —
  a persist+flush path would 500 on the loser instead of telling it "reserved".
- `CartManager` is reservation-aware on **both** paths: `add()` rejects products
  reserved by another session, and `getProducts()` filters them out and syncs the cart
  — a stale cart must not resurrect a reserved product at checkout.
- Cleanup is dual: lazy (each product access checks expiry) **and** batch (scheduler
  every 5 minutes). Neither alone suffices — lazy only fires on visited products,
  batch alone leaves up to 5 minutes of ghost "Reserved" badges.
- On payment: products flip `isSoldOut`, reservations are deleted, cart cleared. On
  expiry: reservation deleted, the reserving visitor's cart loses the item.

## Cart & customers

- **Hybrid cart:** guests use session + cookie (`alma_cart`, 30 days), logged-in
  customers a DB `Cart`. Three collaborators keep it coherent — `CartManager` routes
  by auth state, `CartCookieSubscriber` writes pending cookies onto the response,
  `CartMergeSubscriber` merges guest cart into the DB cart on login. Touching one
  usually means touching the others.
- **Guest checkout is a preserved capability, not a fallback**: `Order.customer` is
  nullable, no role is required for cart or checkout. When an account is created,
  past guest orders with the same email are linked automatically.
- **Registration is OTP-verified, and nothing persists before the code is right**: the
  form data waits in session, the `Customer` row is only created after a valid 6-digit
  code. OTP instead of a verification link is deliberate — a link switches tab/context,
  which kills a registration triggered mid-checkout.
- Wishlist is customer-only and self-cleaning: sold or unpublished products are
  filtered out at read time, with a flash telling the customer what disappeared.

## Auth & security

Two firewalls, two user entities, two auth models — never mix them:

| Firewall | Pattern | Users | Auth |
|---|---|---|---|
| `admin` | `^/admin` | `Admin` | Magic link (login_link), **no password stored** |
| `main` | everything else | `Customer` | email + password, remember-me 30 days |

- The magic link is signed with `email` + `lastLoggedInAt` and
  `AdminLoginSubscriber` updates `lastLoggedInAt` on login — that update is what makes
  every previously issued link single-use. `app:admin-login-link` prints one from the
  CLI when mail is unavailable.
- Turnstile degrades gracefully: empty `TURNSTILE_SECRET_KEY` disables verification
  (dev). The contact form additionally has a honeypot + rate limiter of its own.
- Secret-bearing URLs (reset-password, invoice, testimonial tokens) are masked out of
  stored analytics paths — see Analytics below.

## Hosting constraints (o2switch / LiteSpeed)

Production runs on shared hosting; these things exist only because of it:

- **Never build assets on production.** Compiled assets (`public/assets/`) are
  tracked in git on purpose (see the `.gitignore` note): build in dev
  (`tailwind:build` + `asset-map:compile`), commit, and production only pulls.
  The Tailwind v4 binary cannot even run there (`/tmp` is mounted noexec —
  Bun fails to map its native libraries), and `asset-map:compile` on the server
  regenerates the Stimulus loader with a different debug flag, diverging from
  the committed manifests. The host also drops `error_log` files at the webroot;
  they are gitignored, not Symfony logs — read them on the server.

- **The host kills PATCH requests** (HTTP/2 protocol error before the app), which broke
  every EasyAdmin index toggle. The fix has two halves that only work together:
  `public/js/admin-method-override.js` rewrites same-origin PATCH fetches to
  POST + `_method=PATCH`, and `framework.http_method_override: true` restores the verb
  server-side. Removing either half re-breaks all toggles, in production only.
- **Production seeding is a dance**: the fixtures bundle is a dev dependency, so seed
  with dev dependencies installed, then reinstall `--no-dev`. `--append` is mandatory
  — without it `doctrine:fixtures:load` **purges the whole database**:

  ```bash
  composer install
  APP_ENV=dev php bin/console doctrine:fixtures:load --group=core --append
  composer install --no-dev --optimize-autoloader
  php bin/console cache:clear
  ```

## AI pipelines (M16 visuals · M17 content)

- **The two pipelines are independent by design** — different Gemini models, separate
  Messenger messages/handlers, separate status enums and workflows, separate service
  trees (`Service/Visual/` + `Service/Prompt/` vs `Service/Content/`). They share
  exactly two things: `BudgetGuard` (one monthly cap, `GEMINI_MONTHLY_BUDGET_USD`,
  split by `GeminiOperation`) and the `gemini_api` rate limiter. Do not factor their
  "duplication" together — the independence is the design (see the two design docs).
- Every Gemini call is budget-checked before and cost-logged after
  (`GeminiUsageLog`); a pipeline path that skips `BudgetGuard` is a bug.
- Generated assets live in Flysystem (`var/storage/products/`), **not** in
  `public/uploads/` — only approval copies a visual into VichUploader territory.
  `ProductImage` as an entity was removed on purpose: a product has exactly three
  image slots (`thumbnail`, `wornPhoto`, `contextPhoto`), not a gallery.

## Analytics (privacy-first)

- **No IP, no cookie, no visitor identifier is ever stored** — one `views` counter per
  `(day, dimension, value)` triple (`PageViewStat`). This is what makes the site
  consent-banner-free; adding any per-visitor field to the model reopens the GDPR
  question, so don't.
- Countability is decided by `App\Analytics\PageViewCollector` (unit-tested): GET +
  200 + `text/html` only; excludes bots, the back-office, admins browsing the shop,
  and AJAX partial fetches; masks secret route parameters out of stored paths.
- `PageViewSubscriber` decides on `kernel.response` (priority −900, before the session
  closes — it needs to know whether an admin is logged in) but **writes on
  `kernel.terminate`** so the visitor never pays the write, and swallows every failure:
  analytics must not be able to break a page.

## Fixtures

| Group | Classes | Content |
|---|---|---|
| `core` | `CoreFixtures`, `CategoryVisualPromptFixtures` | Admins, shipping & site settings, category tree, stones, AI prompts — production-safe |
| `demo` | `DemoFixtures`, `ProductContentSuggestionFixtures` | Fake customers, catalogue products, orders, promotions, testimonials — never in production |

Demo may depend on core, never the reverse. Dev loads both (plain
`doctrine:fixtures:load`); production loads `--group=core --append` only (see the
hosting section above).
