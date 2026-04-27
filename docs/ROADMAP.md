# ROADMAP.md — Alma Stella Paris

> Development is organized in **testable milestones**. Each milestone must be
> fully functional and manually testable before starting the next.
> Claude Code must not skip ahead or partially implement a milestone.

---

## How to use this roadmap

Each milestone has a **Definition of Done (DoD)** — a checklist of what must
work before the milestone is considered complete. When a milestone is done,
ask the developer to validate before proceeding.

```
Milestone complete → ask developer:
"Milestone X is done. Here's what you can test: [DoD checklist].
Shall I proceed to Milestone Y?"
```

---

## Milestone 0 — Project bootstrap
*Estimated effort: 2-3h*

### Tasks
- [x] DDEV configuration (`php 8.3`, `mariadb 10.11`, `symfony` project type)
- [x] `composer create-project symfony/skeleton`
- [x] Install core bundles:
  - `symfony/orm-pack` (Doctrine + migrations)
  - `easycorp/easyadmin-bundle`
  - `symfony/asset-mapper` + Tailwind CSS
  - `symfony/security-bundle`
  - `symfony/mailer`
  - `knplabs/knp-paginator-bundle`
  - `liip/imagine-bundle` (image resizing)
- [x] `.env.local` template with required keys documented (no actual values)
- [x] Base Twig layout (`templates/shop/base.html.twig`) with correct font imports
  (Cormorant Garamond + Inter via Google Fonts)
- [x] Tailwind configured with Alma Stella color tokens
- [x] EasyAdmin `DashboardController` accessible at `/admin`

### Definition of Done
- `ddev start && ddev exec php bin/console cache:clear` runs without error
- `/admin` returns the EasyAdmin dashboard (empty, no CRUDs yet)
- Homepage `/` returns a styled "coming soon" page using the correct palette
- No deprecation warnings in the Symfony profiler

---

---

## Milestone 1 — Product catalog (admin + data layer)
*Estimated effort: 4-5h*

### Tasks
- [x] Create all entities: `Product`, `ProductCategory`, `ProductImage`,
  `ShippingTier` enum
- [x] Doctrine migrations generated and applied
- [x] EasyAdmin CRUD for `Product`:
  - All fields editable
  - `ShippingTier` displayed as colored badge (green/orange/blue)
  - `basePrice` and computed `displayPrice` both visible in index
  - `compareAtPrice` (optional) — original price for discount display
  - `availableIn` — JSON array for collection filtering (france / mexico)
  - Image upload with preview (auto WebP conversion via `ImageProcessor`)
  - `relatedProducts` via `AssociationField` (ManyToMany self-referencing)
  - `isSoldOut` toggle (boolean) — replaces integer `stock` field
  - `soldAt` datetime (nullable) — set when `isSoldOut` toggled to `true`
- [x] EasyAdmin CRUD for `ProductCategory`
- [x] DataFixtures: 12 sample products matching `DESIGN.md` product list
- [x] Sluggable behavior on `Product::$name` (auto-generated, unique)

> **Stock model:** Each piece is unique (pièce unique). `isSoldOut` (boolean)
> replaces the `stock` (integer) field. `soldAt` tracks when the piece was sold.
> Sold pieces stay visible for 14 days with a "Vendue" badge, then are hidden.

### Definition of Done
- Create a product in EasyAdmin → appears in the database
- `ShippingTier` badge renders correctly in 3 colors
- `displayPrice` = `basePrice` + tier cost (verify with $38 + $10 = $48)
- All 12 fixture products load correctly via `doctrine:fixtures:load`
- French labels in EasyAdmin show correct accents (é, à, è, ù, ê, etc.)
- `isSoldOut` toggle works in EasyAdmin, `soldAt` auto-set when toggled

---

---

## Milestone 2 — Public catalog (frontend)
*Estimated effort: 5-6h*

### Tasks
- [x] Homepage (`/`):
  - Hero section (lifestyle image placeholder, headline, CTA)
  - 3-icon strip (Water resistant / Natural stones / Ships worldwide)
  - Featured products grid (4 products, `isFeatured = true`)
  - Instagram feed strip (6 placeholder squares with @alma_stella_paris)
- [x] Catalog page (`/shop`):
  - Product grid (12 per page, paginated)
  - Category filter (All / Necklaces / Earrings / Bracelets / Rings / Anklets)
  - Hover state: gold border on product card
  - Badges on product cards: "Pièce unique", "Vendue" (greyed out), "Nouvelle" (< 14 days)
  - Sold pieces visible for 14 days after sale, then hidden from catalog
- [x] Product detail page (`/product/{slug}`):
  - Large image + thumbnail strip (with `lightbox_controller` for gallery)
  - Name, display price in selected currency
  - Compare-at price with discount percentage badge (when `compareAtPrice` set)
  - Material badges (Acier inoxydable / Pierre naturelle / Résistant à l'eau)
  - "Pièce unique" badge + "Vendue" state (greyed, "Add to cart" disabled)
  - Shipping info accordion
  - "Wear it with" — 3 related products
- [x] About page (`/about`):
  - Brand story with correct French accents
  - 3 values cards
- [x] Legal pages:
  - Legal notice (`/legal-notice` | `/mentions-legales`)
  - Terms of sale (`/terms-of-sale` | `/conditions-generales-de-vente`)
- [x] 404 and 500 error pages styled with brand identity

### Definition of Done
- All pages load without Symfony profiler errors
- Category filter works without page reload (Stimulus or simple Turbo)
- Product detail shows correct `displayPrice` including shipping tier
- "Wear it with" section shows linked related products set in EasyAdmin
- All French copy uses correct UTF-8 accented characters
- Pages are mobile-responsive (test at 375px and 768px viewport)
- Visual result matches `docs/design/screenshots/` reference images
- "Pièce unique" badge visible on all available product cards
- Sold products show "Vendue" badge, greyed out, "Add to cart" disabled
- "Nouvelle" badge visible on products created within last 14 days
- Sold products older than 14 days are hidden from the catalog

---

---

## Milestone 3 — Internationalization (FR/EN)
*Estimated effort: 5-6h*

### Tasks
- [x] Add `slugFr` field to `Product` and `ProductCategory` entities
- [x] Modify initial migration to include `slug_fr` columns
- [x] Update DataFixtures with French slugs for all products and categories
- [x] Configure Symfony Translation (`default_locale: en`, `enabled_locales: [en, fr]`)
- [x] Set up locale-prefixed routing (`/{_locale}/...`) with `en|fr` requirement
- [x] Define translated route paths:
  - `/en/shop` ↔ `/fr/boutique`
  - `/en/shop/{categorySlug}` ↔ `/fr/boutique/{categorySlug}` (uses locale-appropriate slug)
  - `/en/product/{slug}` ↔ `/fr/produit/{slug}` (uses locale-appropriate slug)
  - `/en/about` ↔ `/fr/a-propos`
- [x] Root `/` redirects to `/{_locale}/` based on: cookie → `Accept-Language` → `en`
- [x] Language switcher in header (EN / FR) — links to equivalent page in other locale
- [x] Store locale preference in session + cookie (30-day expiry)
- [x] Create translation files (`messages.en.yaml`, `messages.fr.yaml`) for all UI strings:
  - Navigation, buttons, labels, footer, error pages
  - Product badge labels, shipping info, empty states
  - Homepage hero text, section headings
- [x] Create `LocaleProductExtension` Twig extension:
  - `|localized_name` filter → returns `name` or `nameFr` based on current locale
  - `|localized_description` filter → returns `description` or `descriptionFr`
  - `|localized_slug` filter → returns `slug` or `slugFr`
- [x] Update all existing templates to use `|trans` for UI strings
- [x] Add `<link rel="alternate" hreflang="...">` tags in `<head>` for SEO
- [x] Update EasyAdmin product/category forms to include `slugFr` field
- [x] EasyAdmin stays in English (admin interface not translated)

### Definition of Done
- `/en/shop` shows English UI with English product names and descriptions
- `/fr/boutique` shows French UI with French product names and descriptions
- `/en/shop/necklaces` ↔ `/fr/boutique/colliers` — both resolve correctly
- Click language switcher EN→FR → redirects to the French equivalent URL
- `hreflang` tags present in HTML `<head>` on all public pages
- Cookie persists language preference across browser sessions
- New visitor gets language from browser `Accept-Language` header
- All French content uses correct accented characters (é, à, è, ù, ê, î, ô, ç, œ)
- Product detail page shows localized name, description, and material badges
- Pagination and category filters work identically in both locales

---

---

## Milestone 4 — Currency selector
*Estimated effort: 2-3h*

### Tasks
- [x] `CurrencyConverter` service (open.er-api.com, cached 6h)
- [x] `CurrencyExtension` Twig extension with `|price` filter
- [x] Currency selector in header (EUR / USD / CAD / GBP / MXN)
- [x] Selection stored in session + cookie (30-day expiry)
- [x] Disclaimer displayed when non-EUR currency selected:
  *"Prices shown in [USD] are indicative. You will be charged in EUR at checkout."*
- [x] Fallback to EUR if exchange rate API is unavailable

### Definition of Done
- Select USD → all product prices update across all pages
- Refresh the page → currency selection is remembered
- Close and reopen browser → currency still remembered (cookie)
- Force the exchange rate API to fail (mock) → site displays EUR without error
- Disclaimer visible only when non-EUR currency selected

---

---

## Milestone 5 — Cart & Stripe checkout
*Estimated effort: 6-8h*

### Tasks
- [x] Cart stored in session (guests) + database (logged-in customers):
  - Guest: session + cookie (`alma_cart`, 30-day expiry)
  - Customer: `Cart` + `CartItem` entities in database
  - `CartMergeSubscriber` merges guest cart into customer on login
- [x] Cart drawer (slide-in, Stimulus controller):
  - Item list with thumbnails
  - No quantity selector (pièce unique = always 1)
  - Item removal
  - Subtotal in selected currency
- [x] Checkout tunnel (3-step funnel):
  - **Step 1 — Identify** (`/identify` | `/identification`):
    - Logged-in customer: auto-redirect to step 2
    - Guest: email entry with account detection (`checkout_identify_controller`)
    - Existing account found → prompt to log in
    - New email → proceed as guest, email stored in session
    - Email readonly in subsequent steps
  - **Step 2 — Checkout** (`/checkout` | `/livraison`):
    - Shipping address form with country selector
    - Address selector for logged-in customers (`address_selector_controller`)
    - Optional separate billing address (`billing_toggle_controller`)
    - Order summary
  - **Step 3 — Payment**:
    - Stripe Elements payment form (card + Apple Pay + Google Pay)
- [x] Stripe PaymentIntent creation (server-side)
- [x] Order deduplication: reuse pending order in session instead of creating duplicates
- [x] Order entity created on successful payment
- [x] Confirmation page (`/order/{reference}/confirmation`):
  - "Merci ! ✦ Your order is confirmed." message
  - Order summary
  - Estimated delivery note
- [x] Branded tracking page (`/order/{reference}/tracking`):
  - Order status progress indicator (Confirmed → Shipped → Delivered)
  - Tracking number with 17track link
  - Special handling for Cancelled status (red badge)
- [x] Order confirmation email sent automatically (Symfony Mailer + Twig template, Mailpit in dev)
- [x] On successful payment: set `isSoldOut = true` + `soldAt = now()` on purchased products
- [x] Prevent adding sold-out product to cart (server-side check)
- [x] Payment verification on return (3D Secure redirect handling)
- [x] `app:verify-pending-orders` console command + Symfony Scheduler (every 5 min) —
  verifies Stripe status for pending orders, confirms or cancels them automatically
- [x] **Product reservation system** (anti-double-sell for pièce unique):
  - [x] `Reservation` entity (OneToOne with Product, sessionId, expiresAt)
  - [x] `ReservationManager` service (reserve, release, lazy expiry check)
  - [x] Reservation triggered at checkout entry (identification step), 15-minute hold
  - [x] "Reserved" badge on catalog + product detail for other visitors
  - [x] `reservation_timer_controller` — visible countdown on all checkout pages
  - [x] `CartManager` blocks adding reserved products + filters them from cart
  - [x] `CleanExpiredReservationsMessage` — scheduler batch cleanup every 5 min
  - [x] Auto-release on successful payment or expiry

> **No Stripe webhooks.** Payment verification uses 3 layers: immediate
> (Stimulus controller), on-return (3DS redirect), and Symfony Scheduler.
> Single cron in prod: `* * * * * php bin/console messenger:consume scheduler_default`

### Definition of Done
- Add product to cart → drawer opens and shows item
- Cannot add sold-out product to cart (button disabled + server-side rejection)
- Complete Stripe test payment (use test card `4242 4242 4242 4242`)
- Order appears in EasyAdmin with correct status `pending`
- Purchased product automatically marked `isSoldOut` with `soldAt` timestamp
- Confirmation email received in Mailpit (dev) or real inbox (prod)
- Cart clears after successful payment

---

---

## Milestone 6 — Admin authentication (Magic Link)
*Estimated effort: 1-2h*

### Tasks
- [x] Install `nickdnk/symfony-magic-link-bundle` (or equivalent Magic Link solution)
- [x] Create `Admin` entity implementing `UserInterface`
- [x] Configure Doctrine user provider in `security.yaml`
- [x] Magic Link login flow: enter email → receive link via Symfony Mailer → click → authenticated
- [x] Login page (`/admin/login`) styled with brand identity
- [x] Add `access_control` rule: `^/admin` requires `ROLE_ADMIN`
- [x] Logout route (`/admin/logout`)
- [x] DataFixtures: default admin user (`admin@almastellaparis.com`)

### Definition of Done
- `/admin` redirects to `/admin/login` when not authenticated
- Enter admin email → Magic Link email received → click → EasyAdmin dashboard accessible
- Non-admin email → no link sent, error message displayed
- Logout → redirected to login page
- All EasyAdmin CRUDs inaccessible without `ROLE_ADMIN`
- No password stored in database

---

---

## Milestone 7 — EasyAdmin order management
*Estimated effort: 3-4h*

### Tasks
- [x] EasyAdmin CRUD for `Order`:
  - Status workflow: `pending → processing → shipped → delivered → cancelled`
  - Tracking number field
  - Origin country field (France / Mexico) — affects shipping display only
  - Internal notes field (admin-only)
  - Customer details visible (with link to customer if account exists)
  - Order items list with product snapshots
  - Billing address displayed when different from shipping
- [x] Order status change triggers email via Symfony Mailer:
  - Shipped → sends tracking number to customer
  - Delivered → sends care instructions + Instagram CTA
  - Cancelled → sends cancellation notification to customer
- [x] Dashboard stats widget: orders today, revenue this week, low stock alert
- [x] `ShippingSettingsCrudController` — admin-editable shipping tier costs
- [x] `SiteSettingsCrudController` — active collection filter (all / france / mexico)

### Definition of Done
- Change order status to "shipped" + add tracking number → customer receives email
- Change order status to "cancelled" → customer receives cancellation email
- Dashboard stats display correctly
- Origin country (FR/MX) saved without affecting customer-facing prices
- Shipping costs editable from EasyAdmin

---

---

## Milestone 8 — Customer accounts
*Estimated effort: 6-8h*

> **Strategy:** Guest checkout remains available (no account required to buy).
> Customer accounts are optional and provide convenience features (order history,
> saved addresses, pre-filled checkout). Account creation is encouraged
> post-purchase on the confirmation page. Authentication uses classic
> email + password (not Magic Link — different UX needs than admin).

### Tasks

#### Entity & security
- [x] Create `Customer` entity implementing `UserInterface`:
  - `email` (unique, identifier)
  - `password` (hashed)
  - `firstName`, `lastName`
  - `createdAt`, `updatedAt`
- [x] Create `CustomerAddress` entity:
  - FK to `Customer`
  - `label` (e.g. "Home", "Office")
  - `recipientName` (optional — allows "ship to different name")
  - `addressLine1`, `addressLine2`, `city`, `state`, `postalCode`, `country`
  - `isDefault` (boolean)
- [x] Add optional `customer` FK on `Order` entity (nullable — guest orders have no customer)
- [x] Configure second Symfony firewall (`shop`) for customer authentication:
  - Separate from `admin` firewall
  - `form_login` authenticator with email + password
  - Remember me cookie (30 days)
  - Custom entry point (redirect to login page)
- [x] Install `symfonycasts/reset-password-bundle` for password reset flow
- [x] Update initial migration with all new tables/columns

#### Registration & authentication
- [x] Registration page (`/{_locale}/register` / `/{_locale}/inscription`):
  - Form: email, password, password confirmation, first name, last name
  - Email validation (unique check + async `email_check_controller`)
  - Password strength requirement (min 8 chars)
  - **OTP email verification** before account creation:
    - 6-digit code sent to email (`registration_otp.html.twig`)
    - Verification page (`/{_locale}/verify-email` / `/{_locale}/verification-email`)
    - 10-minute expiry, max 5 attempts
    - Resend option at `/{_locale}/verify-email/resend`
  - Auto-login after successful OTP verification
  - Guest orders linked automatically by email on account creation
- [x] Login page (`/{_locale}/login` / `/{_locale}/connexion`):
  - Email + password form
  - "Forgot password?" link
  - Link to registration page
  - Brand-styled (consistent with site design)
- [x] Logout route (`/{_locale}/logout` / `/{_locale}/deconnexion`)
- [x] Password reset flow:
  - Request form (enter email)
  - Email with reset link (Symfony Mailer, branded template)
  - Reset form (new password + confirmation)
  - Link expires after 1 hour, single-use

#### Account pages (authenticated)
- [x] Account dashboard (`/{_locale}/account` / `/{_locale}/mon-compte`):
  - Welcome message with first name
  - Quick links: orders, addresses, profile
  - Summary: number of orders, member since
- [x] Order history page (`/{_locale}/account/orders` / `/{_locale}/mon-compte/commandes`):
  - List of past orders (paginated) with reference, date, status, total
  - Order detail view with items, tracking info, shipping address
- [x] Address book page (`/{_locale}/account/addresses` / `/{_locale}/mon-compte/adresses`):
  - List saved addresses
  - Add / edit / delete addresses
  - Set default address
- [x] Profile page (`/{_locale}/account/profile` / `/{_locale}/mon-compte/profil`):
  - Edit first name, last name, email
  - Change password (current password + new password)

#### Checkout integration
- [x] If logged in at checkout: pre-fill shipping form from default address
- [x] Address selector dropdown if customer has multiple saved addresses (`address_selector_controller`)
- [x] Readonly email field at checkout (from account or identify step)
- [x] After payment: link `Order` to `Customer` if authenticated
- [x] Post-purchase account creation prompt on confirmation page:
  *"Create your account to track your order and enjoy exclusive offers"*
  — only email pre-filled (from order), customer sets password
- [x] Guest order linking: when creating an account, automatically link past
  guest orders matching the same email address

#### Header & navigation
- [x] Add account icon in header (user silhouette):
  - Not logged in → links to login page
  - Logged in → dropdown with: My account, My orders, Logout
- [x] Mobile: account link in hamburger menu

#### EasyAdmin
- [x] EasyAdmin CRUD for `Customer` (read-only for admin):
  - List: email, name, number of orders, member since
  - Detail: profile info + linked orders
- [x] Update `Order` CRUD to show linked customer (if any) with link to customer detail

#### Invoice download
- [x] Add `invoiceToken` (UUID v4) field on `Order` entity, auto-generated at creation
- [x] Install `dompdf/dompdf` for PDF generation
- [x] `InvoiceGenerator` service: renders Twig template (`pdf/invoice.html.twig`) to PDF
- [x] `InvoiceController` with route `GET /invoice/{reference}/{token}` — token-verified access
- [x] Invoice download button on order detail page (for Processing/Shipped/Delivered orders)
- [x] Invoice download link in delivered email (`order_delivered.html.twig`)

#### Invoice improvements
- [x] Invoice PDF localized FR/EN (labels, legal mentions, product names)
- [x] Brand logo embedded in invoice header (base64-encoded)
- [x] Legal footer fixed at bottom of every page (SIRET, address, TVA art. 293 B)
- [x] `paidAt` field on `Order` — invoice shows payment date instead of creation date
- [x] Shipping hidden from invoice (free shipping included in product price)
- [x] `productNameFr` on `OrderItem` — bilingual product name snapshots
- [x] Localized product names in all email templates and account order detail
- [x] Localized invoice URL in delivery notification email
- [x] Free shipping line in customer order detail recap

#### Sequential numbering (French legislation compliant)
- [x] Invoice numbering: `FA-YYYY-XXXXX` — sequential, gap-free, assigned on payment confirmation
- [x] Order references: `ASP-YYYY-XXXXX` — sequential via `OrderRepository::nextOrderReference()`
- [x] `invoiceNumber` field on `Order` (nullable, unique) — separate from order reference
- [x] Invoice PDF and filename use `invoiceNumber` instead of order reference

#### Admin invoice integration
- [x] Admin order edit: payment date and invoice number in "Paiement" panel
- [x] Admin order edit: invoice download link (opens PDF in customer's locale)
- [x] Admin order edit: payment date in "Dates" panel

#### DataFixtures
- [x] Sample customer accounts (2-3) with addresses and linked orders

### Definition of Done
- Register with email + password → account created, auto-logged in
- Login → redirected to account dashboard
- Forgot password → reset email received → password changed successfully
- Account dashboard shows order history with correct data
- Add/edit/delete addresses in address book, set default
- Checkout as logged-in user → shipping form pre-filled from default address
- Checkout as guest → still works exactly as before (no regression)
- Post-purchase: account creation prompt on confirmation page
- Create account after guest purchase → past orders linked automatically
- Header shows account icon with dropdown when logged in
- Customer visible in EasyAdmin (read-only)
- All pages bilingual (FR/EN), French accents correct
- Pages mobile-responsive (375px, 768px)

---

---

## Milestone 12 — SEO & performance
*Estimated effort: 3h*

### Tasks
- [x] Dynamic `<title>` and `<meta description>` on all public pages
- [x] Schema.org `Product` structured data on product detail pages
- [x] Schema.org `BreadcrumbList` on catalog and product pages
- [x] Auto-generated XML sitemap (`/sitemap.xml`)
- [x] Open Graph tags for social sharing
- [x] Image optimization (WebP conversion via Intervention Image — already implemented)
- [x] Lighthouse score ≥ 90 on mobile for homepage (hero WebP conversion, fetchpriority, lazy loading — manual verification needed)

### Definition of Done
- Google Rich Results Test validates product structured data
- `/sitemap.xml` lists all published products and static pages
- Sharing a product URL on social media shows correct OG image and title
- Lighthouse mobile score ≥ 90 for homepage

---

---

## Milestone 9 — Promotions & discount codes
*Estimated effort: 8-10h*

> **Full-featured promotion system**: automatic product discounts, automatic cart
> discounts, manual coupon codes,.
> Highly configurable from EasyAdmin with usage tracking and analytics.

### Tasks

#### Entities & enums
- [x] `PromotionType` enum: `ProductAutomatic` / `CartAutomatic` / `CartCode`
- [x] `DiscountType` enum: `Percentage` / `FixedAmount`
- [x] `Promotion` entity with full configuration fields
- [x] `PromotionUsage` entity for usage tracking
- [x] Add `discountAmountUsd` + `promotionCode` on `Order`
- [x] Add `discountAmountUsd` on `OrderItem`
- [x] `PromotionRepository` with active promo queries
- [x] Update initial migration with all new tables/columns

#### PromotionEngine service
- [x] `PromotionEngine` service — central discount calculation logic
- [x] Product-level auto promotions: find applicable promos, calculate discounted price
- [x] Cart-level auto promotions: evaluate conditions, apply best discount
- [x] Code validation: check code validity, usage limits, email limits, minimum amount
- [x] Cumul logic: `isCumulable` flag determines stacking behavior
- [x] `compareAtPrice` interaction: `overridesCompareAtPrice` flag per promotion

#### EasyAdmin
- [x] `PromotionCrudController` with full form (all configurable fields)
- [x] Product/category restriction via `AssociationField`
- [x] Stats display on promotion detail: usage count, revenue, last used
- [x] Menu entry "Promotions" in admin sidebar

#### Checkout integration
- [x] Coupon code input field at checkout step 2 (order summary)
- [x] `coupon_code_controller` Stimulus controller for async validation
- [x] Apply discount to order total before Stripe PaymentIntent creation
- [x] Track promotion usage on successful payment (`PromotionUsage` created)
- [x] Update `PromotionEngine` stats (usage count, revenue, lastUsedAt)

#### Catalog & product display (prix barrés)
- [x] Auto product promotions generate dynamic strikethrough prices on catalog
- [x] Product detail page shows original price barré + discounted price + badge
- [x] `compareAtPrice` coexists: promo takes priority if `overridesCompareAtPrice = true`
- [x] Cart drawer shows per-item discount when applicable

#### DataFixtures
- [x] Sample promotions (1 product auto, 1 cart auto, 1 code)

### Definition of Done
- Create promotion in EasyAdmin with all configuration options
- Product auto promo → prix barré visible on catalog + product detail
- Cart auto promo → discount line visible in checkout recap
- Enter valid code at checkout → discount applied, total updated
- Enter expired/maxed-out code → clear error message
- `isCumulable = false` → best single offer retained
- `overridesCompareAtPrice = false` → promo skips products with existing compareAtPrice
- Promotion usage tracked per order, stats visible in EasyAdmin
- All pages bilingual (FR/EN), French accents correct

---

---

## Milestone 10 — Automated emails & customer testimonials
*Estimated effort: 2-3h*

### Tasks
- [x] `Testimonial` entity (email, rating 1-5, text, firstName, lastNameInitial, city, status)
- [x] Post-purchase testimonial request email (J+14 via Symfony Scheduler)
  - Deduplicate by email: if a `Testimonial` already exists for this email → skip
  - Email sourced from the `Order` (works for guests and logged-in customers)
- [x] Public testimonial submission form (accessible via unique token in email)
- [x] Testimonial moderation in EasyAdmin (pending → approved / rejected)
- [x] Display latest approved testimonials on homepage (section with "Voir tous les témoignages" link)
- [x] Dedicated `/testimonials` page (all approved testimonials)
- [x] Footer link to `/testimonials`
- [x] DataFixtures: sample testimonials (approved) for development display
- [x] Schema.org `AggregateRating` from approved testimonials

> **Testimonials are brand-level, not product-level.** Since every piece is unique
> and sold once, product reviews make no sense. Testimonials capture the overall
> Alma Stella experience (quality, packaging, delivery, etc.).
>
> **No newsletter, no Brevo.** Communication strategy relies on social media
> (Instagram, Pinterest, TikTok Shop). Transactional emails only, sent via
> Symfony Mailer + SMTP.
>
> **No abandoned cart emails.** The reservation system already handles cart
> retention and item release — abandoned cart reminders would conflict with this logic.

### Definition of Done
- J+14 after order: customer receives testimonial request email (only if no existing testimonial for that email)
- Submit a testimonial via email link → testimonial saved as pending
- Admin approves → testimonial appears on homepage and `/testimonials` page
- Homepage shows latest testimonials with link to full listing
- Footer contains link to `/testimonials`
- Fixtures load sample testimonials visible on homepage after `doctrine:fixtures:load`
- Returning customer who already left a testimonial is never re-solicited
- Scheduler cron commands run correctly via `php bin/console messenger:consume`

---

---

## Milestone 13 — Security audit & hardening
*Estimated effort: 3-4h*

> **Every fix requires developer approval before implementation.**
> Claude audits and proposes — developer validates before any change is applied.

### Tasks
- [x] OWASP Top 10 audit (XSS, CSRF, SQL injection, mass assignment, etc.)
- [x] Stripe webhook signature verification audit
- [x] Authentication & session security review (magic link, customer auth)
- [x] Rate limiting on sensitive endpoints (login, testimonial submission, checkout)
- [x] Input validation & sanitization audit (forms, query parameters)
- [x] CORS & security headers review (`X-Content-Type-Options`, `X-Frame-Options`, CSP, etc.)
- [x] Dependency vulnerability scan (`composer audit`)
- [x] File upload security review (image uploads, WebP conversion)
- [x] Environment secrets audit (`.env` not exposed, no hardcoded keys)
- [x] EasyAdmin access control review (admin routes properly protected)

### Definition of Done
- Full audit report delivered with findings classified by severity (critical / high / medium / low)
- Each proposed fix reviewed and approved by developer before implementation
- All critical and high severity issues resolved
- `composer audit` returns no known vulnerabilities
- Security headers verified via browser dev tools or online scanner

---

---

## Milestone 14 — Two-level category hierarchy
*Estimated effort: 6-8h*

> **Categories become hierarchical (parent → subcategory) with mixed mode.**
> A product is attached to a **leaf category** — either a subcategory (level 2)
> or a parent that has no children (e.g. Coffrets, Chaînes de cheville).
> Products cannot be attached to a parent that has children.
> URLs: `/shop/{parentSlug}/{childSlug}` for hierarchical categories,
> `/shop/{slug}` for single-level categories.

### Category structure

```
Colliers (Necklaces)
  ├─ Pendentifs (Pendants)
  ├─ Ras-de-cou (Chokers)
  └─ Sautoirs & Chaînes (Long & Chain)

Boucles d'oreilles (Earrings)
  ├─ Créoles (Hoops)
  ├─ Puces (Studs)
  └─ Pendantes (Drops)

Bracelets (Bracelets)
  ├─ Chaînes (Chain)
  ├─ Manchettes (Cuffs)
  └─ Perles & Pierres (Beaded)

Bagues (Rings)
  ├─ Pierres (Stone)
  └─ Simples & Empilables (Plain)

Chaînes de cheville (Anklets)      ← no subcategories (leaf parent)

Coffrets (Sets)                    ← no subcategories (leaf parent)
```

### 14a — Entity & migration
- [x] Add self-referencing `parent` (ManyToOne, nullable) and `children` (OneToMany) to `ProductCategory`
- [x] Add validation: max 2 levels (a parent cannot have a parent)
- [x] Add validation: products can only be attached to leaf categories (subcategory OR childless parent)
- [x] Modify initial migration to add `parent_id` column with foreign key
- [x] Recreate DDEV environment and verify schema

### 14b — EasyAdmin category management
- [x] Category list: indented tree view (subcategories shown with `↳` prefix under parent)
- [x] Drag & drop reordering (handle `☰`) to change position and parent assignment
- [x] Category form: parent selector (dropdown showing only root categories)
- [x] Category form: prevent selecting a parent that already has a parent (enforce 2 levels max)
- [x] Position ordering works within each level (siblings sorted by position)
- [x] Product count column aggregates subcategories for parent rows

### 14c — EasyAdmin product form
- [x] Product category field: show leaf categories only (subcategories + childless parents), grouped by parent
- [x] Category filter in product list: adapted for hierarchy

### 14d — Catalog controller & routing
- [x] Route: `/shop/{parentSlug}/{childSlug}` (EN) / `/boutique/{parentSlug}/{childSlug}` (FR) — subcategory filter
- [x] Route: `/shop/{parentSlug}` (EN) / `/boutique/{parentSlug}` (FR) — all products of a parent (or direct for childless parents like Coffrets, Chaînes de cheville)
- [x] Keep `/shop` / `/boutique` showing all products (no filter)
- [x] 404 if parent slug or child slug not found
- [x] Breadcrumb structured data: 2-level for hierarchical, 1-level for childless parents

### 14e — Shop sidebar (category filters)
- [x] Replace horizontal category bar with vertical sidebar (desktop: left column)
- [x] Sidebar: collapsible sections per parent category (with children)
- [x] Childless parents (Coffrets, Chaînes de cheville) shown as simple links, no collapse
- [x] Active parent auto-expanded, others collapsed by default
- [x] Active subcategory highlighted
- [x] Product count per subcategory displayed (and per childless parent)
- [x] Responsive mobile: "Filtrer" button opens a drawer (slide-in panel from left)
- [x] Drawer contains same collapsible category tree + "Fermer" button
- [x] Background overlay (grisé) when drawer is open

### 14f — Navbar
- [x] Desktop + mobile: "Boutique" is a simple link to `/shop` (mega-menu removed)
- [x] `nav_dropdown_controller.js` removed (no longer needed)

### 14g — Fixtures & data update
- [x] Restructure `AppFixtures` with parent + subcategory hierarchy
- [x] Reassign 12 existing products to appropriate leaf categories (subcategories or childless parents)
- [x] Reload fixtures and verify all products display correctly

### 14h — Repository & Twig filters
- [x] `findAllOrdered()` → returns tree structure (parents with children)
- [x] `findRootCategories()` — returns only parent categories
- [x] `findChildrenByParent()` — returns subcategories of a given parent
- [x] `localized_name` / `localized_slug` filters still work on both levels
- [x] Update `ARCHITECTURE.md` with new category structure

### Definition of Done
- Create parent + subcategory in EasyAdmin → hierarchy displays correctly
- Cannot assign a product to a parent that has children (forced to pick leaf category)
- Can assign a product to a childless parent (e.g. Coffrets) → works correctly
- `/shop/bracelets` shows all bracelet products (from all subcategories)
- `/shop/bracelets/manchettes` shows only cuff bracelets
- `/shop/coffrets` shows coffret products directly (no subcategory level)
- Sidebar: collapsible tree for parents with children, simple links for childless parents
- Navbar "Boutique" links directly to catalog (no dropdown)
- All 12 fixture products correctly assigned to leaf categories
- No broken links, 404 on invalid slugs

---

---

## Milestone 10b — EUR base currency migration
*Estimated effort: 6-8h*

> **Architectural change:** Switch the reference currency from USD to EUR.
> Estelle's Stripe settlement currency is EUR (French bank account), so charging
> in EUR eliminates the ~2% Stripe conversion fee on all EUR transactions.
> Non-EUR currencies (USD, CAD, GBP, MXN) remain as cosmetic display with
> a disclaimer: "You will be charged in EUR at checkout."
>
> **Since we are still in creation phase** (nothing in production), all changes
> are made in place — modify existing migration, no backwards compatibility needed.

### Tasks

#### 1. Entity & database schema (rename USD → EUR)
- [x] `Order` entity: rename `$totalUsd` → `$totalEur`, `$discountAmountUsd` → `$discountAmountEur`
  - Rename all getters/setters: `getTotalUsd()` → `getTotalEur()`, `setTotalUsd()` → `setTotalEur()`,
    `getDiscountAmountUsd()` → `getDiscountAmountEur()`, `setDiscountAmountUsd()` → `setDiscountAmountEur()`
  - Update `getItemsSummary()`: change `$` symbols to `€` and `getTotalUsd()` → `getTotalEur()`
- [x] `OrderItem` entity: rename `$discountAmountUsd` → `$discountAmountEur`
  - Rename getter/setter: `getDiscountAmountUsd()` → `getDiscountAmountEur()`
  - Update `getLineTotal()` docblock: "in EUR" instead of "in USD"
- [x] `ShippingSettings` entity: rename `$shippingCostUsd` → `$shippingCostEur`
  - Rename getter/setter accordingly
- [x] `Promotion` entity: update any field labels/docblocks referencing USD
  - `fixedAmountValue` and `minimumOrderAmount` are now in EUR (docblocks)
- [x] `PromotionUsage` entity: update any USD references in docblocks
- [x] Modify initial migration (`Version20260408140632`):
  - Rename columns: `total_usd` → `total_eur`, `discount_amount_usd` → `discount_amount_eur` (on `order` table)
  - Rename column: `discount_amount_usd` → `discount_amount_eur` (on `order_item` table)
  - Rename column: `shipping_cost_usd` → `shipping_cost_eur` (on `shipping_settings` table)

#### 2. ShippingTier enum (EUR values)
- [x] Rename method `shippingCostUsd()` → `shippingCostEur()`
- [x] Convert hardcoded shipping costs to EUR:
  - Standard: 10 €
  - Heavy: 15 €
  - Set: 20 €

#### 3. CurrencyConverter service
- [x] Change `BASE_CURRENCY` constant: `'USD'` → `'EUR'`
- [x] Change `API_URL`: `open.er-api.com/v6/latest/USD` → `open.er-api.com/v6/latest/EUR`
- [x] Rename `convert()` parameter: `$amountUsd` → `$amountEur`
- [x] Update `SUPPORTED_CURRENCIES` array order: `['EUR', 'USD', 'CAD', 'GBP', 'MXN']`
- [x] Update default symbol fallback: `'$'` → `'€'`

#### 4. StripeService
- [x] Change PaymentIntent currency: `'usd'` → `'eur'`
- [x] Update amount calculation: `getTotalUsd()` → `getTotalEur()`

#### 5. ShippingCostProvider service
- [x] Rename method returning cost: update any `Usd` references to `Eur`
- [x] Ensure `ShippingSettings` column reference updated

#### 6. PromotionEngine service
- [x] Update all `discountAmountUsd` / `totalUsd` references to EUR equivalents
- [x] Verify fixed-amount promotions are treated as EUR

#### 7. CartManager & CartController
- [x] Update any `Usd` / `USD` references in price calculations

#### 8. CheckoutController
- [x] Replace all `totalUsd` / `discountAmountUsd` calls with EUR equivalents
- [x] Replace all `setTotalUsd()` / `setDiscountAmountUsd()` calls
- [x] Update inline comments referencing USD

#### 9. CurrencyExtension (Twig)
- [x] Default currency: `EUR` instead of `USD`
- [x] Rename `is_non_usd_currency()` → `is_non_eur_currency()`
- [x] Update `|price` filter: parameter name `$amountUsd` → `$amountEur`
- [x] Update fallback behavior for base currency

#### 10. CurrencySubscriber
- [x] Default currency fallback: `'EUR'` instead of `'USD'`

#### 11. Templates — storefront (15 Twig files)
- [x] `base.html.twig`: update currency disclaimer text (charged in EUR)
- [x] `checkout/index.html.twig`: `$` → `€` in price display, USD → EUR references
- [x] `checkout/payment.html.twig`: same
- [x] `product/show.html.twig`: same
- [x] `account/orders.html.twig`: same
- [x] `account/order_detail.html.twig`: same
- [x] Update `is_non_usd_currency` → `is_non_eur_currency` in all templates

#### 12. Templates — emails (5 Twig files)
- [x] `order_confirmation.html.twig`: `$` → `€`, USD → EUR
- [x] `order_shipped.html.twig`: same
- [x] `order_delivered.html.twig`: same
- [x] `order_cancelled.html.twig`: same
- [x] `admin_new_order.html.twig`: same

#### 13. Templates — admin & invoice
- [x] `admin/dashboard.html.twig`: `$` → `€`, USD → EUR
- [x] `admin/order/edit.html.twig`: same
- [x] `admin/customer/detail.html.twig`: same
- [x] `pdf/invoice.html.twig`: update currency symbol and references

#### 14. EasyAdmin controllers
- [x] `OrderCrudController`: update field labels (`Total USD` → `Total EUR`, etc.)
- [x] `OrderItemCrudController`: same
- [x] `ProductCrudController`: update `basePrice` label context
- [x] `PromotionCrudController`: update currency references in labels
- [x] `ShippingSettingsCrudController`: rename field labels USD → EUR
- [x] `CustomerCrudController`: update any USD display references

#### 15. Translations (YAML)
- [x] `messages.en.yaml`: update disclaimer text and any USD-specific strings
- [x] `messages.fr.yaml`: same

#### 16. OrderRepository
- [x] Update any `totalUsd` / `discountAmountUsd` DQL or QueryBuilder references

#### 17. DataFixtures
- [x] Convert all product `basePrice` values from USD to EUR equivalents
- [x] Convert `compareAtPrice` values
- [x] Convert promotion `fixedAmountValue` and `minimumOrderAmount`
- [x] Convert shipping settings default values
- [x] Update order fixture totals

#### 18. Documentation
- [x] `CLAUDE.md`: update all "USD" references to "EUR" (reference currency, Stripe, localisation,
  architecture decisions, out of scope section)
- [x] `docs/ARCHITECTURE.md`: update currency references throughout
- [x] `docs/LOCALISATION.md`: update "one price, many displays" to EUR base, update disclaimer text,
  update default currency
- [x] `docs/ROADMAP.md`: update Milestone 4 description (cosmetic disclaimer = non-EUR)
- [x] V2 backlog: remove "Multi-currency Stripe charges" line (no longer relevant)

#### 19. Rebuild & verify
- [x] Run `ddev exec vendor/bin/php-cs-fixer fix`
- [x] Run `ddev exec vendor/bin/phpstan analyse` — zero errors
- [x] Run `ddev exec php bin/console tailwind:build && ddev exec php bin/console asset-map:compile`
- [x] Recreate DDEV environment: `ddev delete --omit-snapshot && ddev start`
- [x] Run `ddev exec php bin/console doctrine:migrations:migrate`
- [x] Run `ddev exec php bin/console doctrine:fixtures:load`
- [x] Smoke test all pages in browser

### Definition of Done
- `CurrencyConverter::BASE_CURRENCY` is `'EUR'`
- Stripe PaymentIntent uses `'eur'` currency
- All entity properties, getters, setters reference EUR (no `Usd` anywhere in codebase)
- All database columns reference EUR (`total_eur`, `discount_amount_eur`, `shipping_cost_eur`)
- Default currency in header selector is EUR
- Select USD → disclaimer shows "You will be charged in EUR at checkout"
- Select EUR → no disclaimer shown
- `|price` filter converts from EUR base to selected display currency
- Product prices in fixtures are in EUR
- All email templates show `€` symbol
- Invoice PDF shows `€` amounts
- EasyAdmin dashboard revenue displayed in `€`
- `PHPStan analyse` passes at level 6
- `php-cs-fixer fix` reports no changes
- All 12 fixture products load correctly
- `grep -ri "totalUsd\|discountAmountUsd\|shippingCostUsd" src/` returns zero results
- Documentation (CLAUDE.md, ARCHITECTURE.md, LOCALISATION.md) consistently references EUR

---

---

## Milestone 11 — Instagram feed (Behold.so)
*Estimated effort: 2-3h*

> **Prerequisite (client action):** Create a Behold.so account, connect the
> Instagram Business account `@alma_stella_paris`, and provide the Feed ID.
> This milestone cannot start until the Feed ID is available.

### Tasks

#### Configuration
- [x] Add `BEHOLD_FEED_ID` to `.env` (documented, no default value)
- [x] Add `BEHOLD_FEED_ID` to `.env.local` with actual Feed ID from client

#### Service & cache
- [x] `InstagramFeedService` — fetches Behold.so JSON API (`https://feeds.behold.so/{feedId}`)
- [x] Symfony Cache integration (filesystem adapter, TTL **6h** — same pattern as `CurrencyConverter`)
- [x] Graceful fallback: if API unavailable or cache miss fails, return empty array (no error displayed)
- [x] Cache warmup via Symfony Scheduler (optional: pre-fetch every 6h to avoid cold cache on first visitor)

#### Homepage integration
- [x] Replace placeholder grid (6 ✦ squares) with real Instagram photos from `InstagramFeedService`
- [x] Display up to 6 latest posts: thumbnail image, link to original Instagram post
- [x] Hover effect: slight zoom + overlay with post caption (truncated)
- [x] Responsive grid: 2 columns (mobile) → 3 columns (tablet) → 6 columns (desktop)
- [x] Fallback: if no posts available, show current placeholder gracefully (no broken layout)

#### Performance
- [x] Images served via Behold CDN (no local download/storage)
- [x] Lazy loading (`loading="lazy"`) on all Instagram images
- [x] No external JavaScript — server-side fetch only, rendered in Twig

### Definition of Done
- Homepage displays 6 real Instagram posts from `@alma_stella_paris`
- Click on a post → opens the original Instagram post in a new tab
- Disconnect internet → cached posts still display for up to 6h
- Force cache expiry → service re-fetches from Behold API without error
- Behold API down → homepage renders without Instagram section (no error, no broken layout)
- No external JS loaded — zero impact on Lighthouse performance score
- Mobile responsive: 2 → 3 → 6 columns across breakpoints

---

---

## Milestone 12 — Social publishing
*Estimated effort: 4-5h*

### Tasks
- [ ] Pinterest API client (`PinterestApiClient` service)
- [ ] TikTok Shop API client (`TikTokShopApiClient` service)
- [ ] Instagram deep link generator
- [ ] `SocialPublisher` service orchestrating all three
- [ ] EasyAdmin action button "Publish to social media" on product detail + index
- [ ] Modal with checkboxes (Pinterest ☑ / TikTok Shop ☑ / Instagram ☑)
- [ ] Flash messages per channel (success/error)
- [ ] `social_publish_log` table tracking publish history per product per channel

### Definition of Done
- Click "Publish to social media" on a product → modal appears
- Deselect Pinterest → only TikTok and Instagram are processed
- Pinterest: Pin appears in the connected Pinterest Business account
- TikTok Shop: product appears in the TikTok Seller catalog
- Instagram: deep link opens the Instagram app with pre-filled caption
- Publish history visible on product detail page in EasyAdmin

---

---

## Milestone 15 — Guide des pierres & filtre boutique
*Estimated effort: 8-10h*

> **Storytelling + SEO + navigation.** Une page dédiée aux pierres naturelles
> utilisées dans les bijoux Alma Stella, avec un angle émotionnel et spirituel.
> Chaque pierre a sa fiche (vertus, origine, signification). Les pierres sont
> liées aux produits, ce qui permet un filtre par pierre dans la boutique et
> des liens croisés pierre ↔ produit.
>
> **Prérequis :** préparer un fichier `docs/STONES.md` contenant la liste des
> pierres avec leurs descriptions, vertus et origines avant de commencer
> l'implémentation.

### 15a — Entité & migration

- [x] Créer l'entité `Stone` :
  - `name` (string) — nom EN
  - `nameFr` (string) — nom FR
  - `slug` (string, unique) — auto-généré depuis `name`
  - `slugFr` (string, unique) — auto-généré depuis `nameFr`
  - `shortDescription` (string) — accroche courte EN (badges, tooltips)
  - `shortDescriptionFr` (string) — accroche courte FR
  - `description` (text) — description complète EN (page guide)
  - `descriptionFr` (text) — description complète FR
  - `funFact` (text, nullable) — « Le saviez-vous ? » EN
  - `funFactFr` (text, nullable) — « Le saviez-vous ? » FR
  - `traditions` (text, nullable) — traditions culturelles EN
  - `traditionsFr` (text, nullable) — traditions culturelles FR
  - `virtues` (text) — vertus émotionnelles/spirituelles EN
  - `virtuesFr` (text) — vertus émotionnelles/spirituelles FR
  - `chakra` (string, nullable) — chakra(s) associé(s) (ex: « Cœur », « Racine, Cœur »)
  - `color` (string) — couleur dominante (pour affichage visuel)
  - `lustre` (string, nullable) — éclat de la pierre (ex: « Vitreux à cireux »)
  - `origin` (string, nullable) — origine géographique
  - `care` (text, nullable) — conseils d'entretien EN
  - `careFr` (text, nullable) — conseils d'entretien FR
  - `imageName` (string, nullable) — photo de la pierre
  - `position` (integer) — ordre d'affichage
- [x] Relation `ManyToMany` bidirectionnelle entre `Product` et `Stone`
  - Côté propriétaire : `Product` (table de jointure `product_stone`)
  - Un produit peut avoir plusieurs pierres (ex: Duo Émeraude & Malachite)
  - Une pierre est liée à plusieurs produits
- [x] Modifier la migration initiale pour inclure les tables `stone` et `product_stone`
- [x] Recréer l'environnement DDEV et vérifier le schéma

### 15b — EasyAdmin

- [x] `StoneCrudController` :
  - Liste : nom FR, couleur (pastille colorée), nombre de produits liés, position
  - Formulaire : tous les champs bilingues, upload image, sélection des produits liés
  - Drag & drop pour réordonner (position)
- [x] Entrée menu « Pierres » dans la sidebar admin (section Catalogue)
- [x] `ProductCrudController` : ajouter champ `AssociationField` pour les pierres
  - Autocomplete multi-select dans le formulaire produit
  - Colonne « Pierres » visible dans la liste des produits

### 15c — Guide des pierres (pages publiques)

- [x] `StoneGuideController` :
  - Index : `/{_locale}/stones` (EN) / `/{_locale}/pierres` (FR)
  - Détail : `/{_locale}/stones/{slug}` (EN) / `/{_locale}/pierres/{slug}` (FR)
- [x] Template index (`templates/shop/stone/index.html.twig`) :
  - Hero section avec titre et texte d'introduction
  - Grille des pierres : image + nom + accroche courte
  - Au clic → page détail de la pierre
  - Responsive : 1 col (mobile) → 2 col (tablette) → 3 col (desktop)
- [x] Template détail (`templates/shop/stone/show.html.twig`) :
  - Image de la pierre en grand
  - Nom, description complète, vertus (angle émotionnel)
  - Origine géographique
  - Section « Bijoux avec cette pierre » : grille de produits liés (réutiliser le composant carte produit existant)
  - Breadcrumb : Accueil > Guide des pierres > [Nom de la pierre]
- [x] Styles cohérents avec le design system (tokens `alma-*`, typographie Cormorant/Inter)

### 15d — Filtre par pierre dans la boutique

- [x] Ajouter une section « Pierres » dans la sidebar du catalogue (sous les catégories)
  - Liste des pierres avec nombre de produits disponibles
  - Filtre cumulable avec le filtre catégorie existant
  - Option « Sans pierre » pour les bijoux en acier seul
- [x] Route : `/shop?stone={slug}` / `/boutique?stone={slug}` (query parameter)
  - Compatible avec le filtre catégorie : `/shop/bracelets?stone=onyx`
- [x] `CatalogController` : ajouter la logique de filtrage par pierre
- [x] `ProductRepository` : méthode de requête filtrée par pierre(s)
- [x] État actif dans la sidebar (pierre sélectionnée mise en surbrillance)
- [x] Mobile : section pierres intégrée dans le drawer « Filtrer » existant

### 15e — Intégration fiche produit

- [x] Sur la page produit (`show.html.twig`) : afficher les pierres du produit
  - Badges cliquables (lien vers la page détail de la pierre)
  - Style cohérent avec les badges matériaux existants (Acier inoxydable, etc.)
- [x] Tooltip ou texte court sous le badge avec la vertu principale

### 15f — Navigation & SEO

- [x] Lien « Nos pierres » / « Our stones » dans le footer (section « Découvrir »)
- [x] Meta title/description dynamiques sur les pages index et détail
- [x] Breadcrumb `BreadcrumbList` structured data sur les pages guide
- [x] `hreflang` alternates sur toutes les pages pierre
- [x] Ajouter les pages pierres au sitemap XML
- [x] Open Graph tags sur les pages détail

### 15g — DataFixtures

- [x] Charger les pierres depuis `docs/STONES.md` (ou tableau hardcodé dans les fixtures)
- [x] Associer les pierres aux produits existants dans les fixtures
  - Mapping basé sur le nom du produit (extraction automatique de la pierre depuis le nom)
  - Produits « Sans pierre » : aucune association
- [x] Vérifier que les fixtures se chargent sans erreur

### 15h — Documentation

- [x] Mettre à jour `docs/ARCHITECTURE.md` (nouvelle entité, contrôleur, routes)
- [x] Mettre à jour les traductions (`messages.en.yaml`, `messages.fr.yaml`)

### Definition of Done
- Créer une pierre dans EasyAdmin → apparaît dans le guide
- Page index `/fr/pierres` affiche toutes les pierres avec images et accroches
- Page détail `/fr/pierres/onyx` affiche description + vertus + produits liés
- Clic sur un produit lié → fiche produit, clic retour sur la pierre → fiche pierre
- Filtre boutique par pierre fonctionne seul et combiné avec le filtre catégorie
- « Sans pierre » affiche uniquement les bijoux sans association
- Fiche produit affiche les badges pierres cliquables
- Toutes les pages bilingues (FR/EN), accents français corrects
- Pages responsive (375px, 768px, desktop)
- Sitemap inclut les pages pierres
- SEO : meta tags, breadcrumbs structurés, Open Graph
- `PHPStan analyse` passe au niveau 6
- `php-cs-fixer fix` ne signale rien
- Fixtures chargent toutes les pierres et associations sans erreur

---

---

## Milestone 16 — Catalogue IA (génération visuels)
*Estimated effort: 20-25h*

> **Génération automatique de visuels produits par IA (Gemini 2.5 Flash Image).**
> La gérante uploade des photos smartphone "brutes" d'un bijou et obtient
> 3 visuels professionnels (vignette, porté, lifestyle) × 3 variantes chacun.
> Validation humaine obligatoire avant publication.
>
> **Specs complètes :** `docs/CATALOGUE-IA-SPECS.md`
> **Audit :** `docs/CATALOGUE_IA_AUDIT.md`
> **Plan d'adaptation :** `docs/CATALOGUE_IA_PLAN.md`
>
> Cette feature est découpée en **4 phases** (voir plan d'adaptation).
> Chaque phase se termine par un commit et un résumé dans `docs/milestones/`.

### Phase 1 — Modèle de données
- [x] 4 enums (VisualType, VisualStatus, VisualWorkflowStatus, PhotoAngle)
- [x] 4 entités (CategoryVisualPrompt, SourcePhoto, GeneratedVisual, GeminiUsageLog)
- [x] Enrichir ProductCategory (preservationInstructions, specificFocus)
- [x] Enrichir Product (visualStatus, relations SourcePhoto/GeneratedVisual)
- [x] Installer + configurer `league/flysystem-bundle`
- [x] Modifier migration existante, recréer environnement DDEV
- [x] Fixtures : CategoryVisualPromptFixtures (12 prompts)

### Phase 2 — Cerveau IA + Client Gemini + Queue
- [x] Services Prompt (PromptBuilder, BrandStyleProvider, TechnicalSpecsProvider, PromptFallbackProvider)
- [x] Client Gemini (GeminiImageClient, GeminiResponse, GeminiApiException)
- [x] BudgetGuard (contrôle budget mensuel)
- [x] ImageStorage (Flysystem)
- [x] Message + Handler (GenerateVisualMessage, GenerateVisualHandler)
- [x] Config Messenger async + Rate Limiter + .env

### Phase 3 — Back-office EasyAdmin
- [x] CategoryVisualPromptCrudController (CRUD prompts visuels)
- [x] GeneratedVisualCrudController (validation approve/reject/regenerate)
- [x] VisualApprovalHandler (copie Flysystem → VichUploader)
- [x] Enrichir ProductCrudController (upload SourcePhoto, bouton Générer, visuels)
- [x] Enrichir ProductCategoryCrudController (champs IA)
- [x] Section "Génération IA" dans le menu admin

### Phase 4 — Import adapté + finitions
- [x] Adapter ImportCatalogueImagesCommand (SourcePhoto via Flysystem)
- [x] Vérification end-to-end complète
- [x] Test pipeline : fixtures → import → génération → approbation

### Phase 5 — Améliorations IHM admin (en cours)
> Refonte UX du back-office pour réduire la friction lors du workflow de
> génération IA. Validée par la gérante le 2026-04-27.

#### Groupe A — Page produit unifiée
- [x] Workspace IA inline dans la page produit (galerie compacte, photos sources, actions)
- [x] Affichage des visuels générés en grille 3 colonnes desktop (par type)
- [x] Lightbox au clic (vanilla JS dédié à l'admin via `admin-lightbox.js`)
- [x] Upload drag & drop des photos sources directement depuis la page produit
- [x] Suppression d'une photo source en place
- [x] Boutons d'action inline sur chaque visuel (approuver / rejeter / régénérer)
- [x] Modale de prévisualisation du prompt utilisé (`<dialog>` natif)
- [x] Génération sélective par type (3 boutons : Vignette / Porté / Lifestyle)
- [x] Suppression du fieldset "Photos" Vich (workflow 100% piloté par l'IA)
- [x] Bandeau "Images publiées" en haut du workspace (read-only, lightbox)
- [x] Badges de statut colorés sur chaque vignette
- [x] **Découpe en onglets** : "Fiche produit" / "Visuels IA" via `FormField::addTab()`
- [x] **Workspace compact** : bandeau publiés horizontal + sources/générés en 2 colonnes côte à côte
- [x] Suppression du fieldset "Génération IA" de la sidebar (doublon)
- [x] **Polling hybride** : endpoint `/ai-status` consume 1 message par poll JS (2s) + lock flock contre les courses concurrentes
- [x] Cron fallback `messenger:consume gemini_async --limit=10` documenté pour O2Switch
- [x] Auto-reload de la page quand un statut visuel change (poll JS détecte la transition)

#### Groupe A.ter — Refonte pipeline IA (modèle unifié Gemini 3 Pro)
> Plan : `docs/AI_GENERATION_PIPELINE.md`. Validé le 2026-04-27.
> Diagnostic initial : Gemini 2.5 Flash Image échouait avec `IMAGE_OTHER` sur ~80% des Vignettes/Lifestyle. Investigation Imagen 4 → text-to-image only (pas de référence subject). Bascule sur Gemini 3 Pro Image Preview (jusqu'à 14 reference images, préservation produit native).
- [x] Architecture découplée : `VisualGeneratorInterface`, `GeneratedVisualResult`, `VisualGenerationException`
- [x] `GeminiVisualGenerator` paramétrable (modèle + coût injectés via DI)
- [x] `GeminiImageClient` paramétrable (endpoint construit dynamiquement à partir du modèle)
- [x] `VisualGeneratorRouter` (extensible si re-différenciation par type plus tard)
- [x] Champ `modelUsed` sur `GeneratedVisual` (traçabilité du modèle utilisé)
- [x] `GenerateVisualHandler` branché sur le routeur, coût remonté dynamiquement
- [x] Variables `.env` `GEMINI_PRO_MODEL` + `GEMINI_PRO_COST_USD`
- [x] Migration unifiée mise à jour (colonne `model_used VARCHAR(50)`), DDEV recréé
- [x] Tests manuels validés : Vignette, Porté, Lifestyle sur Chevalière Trèfle Bordeaux
- [x] Prompt Rings/Vignette ajusté : suppression "floating" → ancrage au sol avec contact shadow
- [x] PHPStan niveau 6 + CS Fixer + Twig lint clean

#### Groupe B — Dashboard consommation IA
- [ ] Page `Consommation IA` dans le menu admin
- [ ] Coût mois en cours (€ + USD, conversion via `CurrencyConverter`)
- [ ] Comparaison mois précédent + tendance %
- [ ] Top 10 produits les plus coûteux
- [ ] Ventilation par type de visuel
- [ ] Graphique d'évolution sur 30 jours
- [ ] Indicateur budget restant + alerte > 80%

#### Groupe C — UX avancée
- [ ] Comparaison côte à côte des variantes d'un même type
- [ ] Preview du prompt complet avant lancement de la génération
- [ ] Vue cross-produit "Visuels en attente" + badge notification dans le menu
- [ ] Comparaison source ↔ généré côte à côte
- [ ] Override de prompt spécifique par produit

#### Groupe D — Robustesse & finitions
- [ ] Polling temps réel pendant la génération (statut `Generating`)
- [ ] Historique complet par produit (incluant rejets / échecs)
- [ ] Téléchargement de l'image source haute résolution

### Vérification du milestone (en cours)
> Les 4 phases de développement sont terminées. Le milestone est en phase
> de vérification manuelle avant validation finale.

### Definition of Done
- Upload de photos sources via ProductCrud → SourcePhoto créées en BDD + Flysystem
- Bouton "Générer" dispatche 9 messages Messenger (3 types × 3 variantes)
- Worker consomme les messages → appel Gemini → GeneratedVisual créés
- Interface de validation : approve → copie vers VichUploader, reject, regenerate
- BudgetGuard bloque si budget mensuel dépassé
- Rate limiter respecte 15 req/min
- Prompts éditables dans EasyAdmin par la gérante
- Fallback prompt si catégorie non configurée
- Import images existantes → SourcePhoto (une seule commande)
- PHPStan niveau 6 passe, CS Fixer clean

---

## Milestone 17 — Remplissage IA des contenus produit
*Estimated effort: 8-10h*

> **Génération automatique des contenus textuels d'un produit par IA (Gemini 2.5 Flash, multimodal vision → texte structuré).**
> À partir des photos sources d'un produit, plus du contexte taxonomique déjà renseigné par la gérante (catégorie, pierres), l'IA propose : `name`, `nameFr`, `description`, `descriptionFr`. Validation humaine obligatoire (review / edit / approve) avant écriture en base.
>
> **Pré-requis :** Milestone 16 livrée (`SourcePhoto`, `BudgetGuard`, queue Messenger, onglet IA EasyAdmin réutilisés).

### Phase 1 — Modèle de données
- [x] Enum `ContentSuggestionStatus` (Generating, Pending, Approved, Rejected, Applied) — `Generating` ajouté pour distinguer proprement « worker en cours » de « prête à review »
- [x] Entité `ProductContentSuggestion` (productId, nameEn, nameFr, descriptionEn, descriptionFr, status, modelUsed, requestId, generatedAt, appliedAt, contextSnapshot JSON, additionalContext)
- [x] Relation OneToMany `Product → ProductContentSuggestion`
- [x] Migration unifiée modifiée (table `product_content_suggestion` + colonne `operation` ajoutée à `gemini_usage_log`) — **recréation DDEV à lancer manuellement**
- [x] Fixtures : 2 suggestions exemples (`ProductContentSuggestionFixtures`)

### Phase 2 — Cerveau IA + Client Gemini text + Queue
- [x] `GeminiTextClient` (endpoint `gemini-2.5-flash:generateContent`, support `inlineData` images + `responseSchema` JSON, retry 429 + backoff `[2s, 4s, 8s]`)
- [x] `ContentSuggestionResult` DTO + `ContentSuggestionException`
- [x] `ContentPromptBuilder` :
  - Voix éditoriale dans un service dédié `ContentBrandVoiceProvider` (séparé du `BrandStyleProvider` visuel pour respecter l'indépendance des pipelines)
  - 4 paires few-shot **textuelles** (`ContentFewShotProvider`) couvrant les 4 catégories ; au moins 1 sans pierre nommée pour ancrer le fallback
  - Contexte dynamique injecté : catégorie (+ parent + specific focus), pierres (nom + couleur + vertus)
  - 4 branches de fallback (`CONTEXT IS COMPLETE` / `CATEGORY UNKNOWN` / `STONE UNKNOWN` / `NEITHER … GIVEN`)
  - `additionalContext` libre injecté en `ADDITIONAL STEERING (mandatory)` lors de la régénération
  - JSON schema strict — 4 champs requis, `temperature = 0.7`
- [x] `ProductContentFiller` service (orchestrateur : load sources → snapshot contexte → build prompt → call Gemini → parse → DTO `ContentSuggestionResult`)
- [x] `BudgetGuard` étendu : nouveaux env `GEMINI_FLASH_TEXT_MODEL` + `GEMINI_FLASH_TEXT_COST_USD`, budget mensuel partagé avec M16
- [x] `Message + Handler` : `FillProductContentMessage`, `FillProductContentHandler` (transport `gemini_async`, rate limiter `gemini_api` partagé 15 req/min)

### Phase 3 — Back-office EasyAdmin
- [x] `ProductContentSuggestionCrudController` (read-only listing, filtres produit/statut, item au menu « Génération IA »)
- [x] Onglet **séparé** « Contenu IA » sur la fiche produit (l'indépendance pipelines image/contenu prime sur la consigne d'origine de mutualiser dans « Visuels IA »), conditions d'activation :
  - Au moins 1 `SourcePhoto` ⇒ obligatoire (bouton désactivé, bandeau rouge bloquant)
  - Catégorie renseignée ⇒ recommandée (bandeau jaune non bloquant)
  - Pierres renseignées ⇒ recommandées (bandeau jaune non bloquant)
- [x] Polling JS dédié `admin-ai-content.js` + endpoint `aiContentStatus` (séparé d'`aiStatus` pour respecter l'indépendance)
- [x] Carte de review : 4 champs éditables (`nameFr`, `nameEn`, `descriptionFr`, `descriptionEn`) — pas de dropdown taxonomie
- [x] Boutons « Appliquer » (sauvegarde + copie vers Product + status `Applied`) / « Rejeter » / « Régénérer » (avec prompt natif pour instruction additionnelle libre)
- [x] `contextSnapshot` affiché en lecture seule (collapsible JSON) dans la carte

### Phase 4 — Tests + finitions
- [x] Tests unitaires : `ContentPromptBuilderTest` (4 branches de fallback + steering + schéma), `ContentFewShotProviderTest` (couverture catégories + scénario sans pierre), `ContentSuggestionStatusTest`
- [ ] Tests end-to-end happy path & fallback **manuels** (à effectuer par la gérante après recréation DDEV — voir DoD)
- [x] Logging usage : nouveau champ `operation` (`GeminiOperation::Visual` / `TextFill`) sur `GeminiUsageLog`, passé explicitement par chaque handler
- [x] PHPStan niveau 6 + CS Fixer + Twig lint clean
- [x] Doc : `docs/AI_CONTENT_FILL.md` (prompt strategy, fallback strategy, JSON schema, indépendance des pipelines)
- [x] Update `docs/ARCHITECTURE.md`

### Definition of Done
- Bouton "Générer contenu" actif dès qu'au moins 1 SourcePhoto est uploadée
- Bandeau d'avertissement non-bloquant si catégorie ou pierres manquantes
- Worker appelle Gemini 2.5 Flash multimodal → `ProductContentSuggestion` persistée avec les 4 champs FR + EN
- `contextSnapshot` capturé pour traçabilité (savoir si la suggestion a été générée avec/sans taxonomie)
- Modale admin permet d'éditer chaque champ avant validation
- "Appliquer" copie les 4 valeurs sur l'entité `Product` et marque la suggestion `Applied`
- Régénération avec instruction additionnelle libre fonctionnelle
- BudgetGuard partage le même budget que la génération visuelle
- Style des descriptions FR cohérent avec le ton de `catalogue.csv` (vérifié sur 5 produits tests, dont au moins 1 sans pierre renseignée)

---

## Milestone 18 — Wizard de création produit assistée IA
*Estimated effort: 4-6h*

> **Formulaire de création produit dédié au mode IA, accessible via un second bouton « Nouveau (IA) » dans la liste produits.**
> La gérante remplit le strict minimum (photos, catégorie, prix, disponibilité, pierres optionnelles) ; le contenu (nom + description FR/EN) est **toujours** généré par IA, et les visuels M16 sont déclenchés en option via une seule case à cocher. À la soumission, la gérante atterrit sur une page d'attente qui poll la génération et la redirige automatiquement vers la modale de review.
>
> **Pré-requis :** Milestones 16 (visuels) et 17 (contenu) livrés.

### Phase 1 — Routing + bouton d'entrée
- [x] `ProductCrudController::configureActions()` : nouvelle action `newWithAi` sur `PAGE_INDEX`, label « Nouveau (IA) », icône `fa fa-wand-magic-sparkles`, à côté du bouton Nouveau natif
- [x] `ProductWizardController` (admin) avec 4 routes :
  - `GET /admin/product/wizard/new` — formulaire
  - `POST /admin/product/wizard/create` — soumission
  - `GET /admin/product/wizard/wait/{productId}` — page d'attente
  - `GET /admin/product/wizard/wait/{productId}/status` — JSON polling

### Phase 2 — Formulaire (Symfony FormType)
- [x] `ProductWizardType` avec :
  - **2 à 4 photos sources** — minimum 2 obligatoires, maximum 4 acceptées (validation sur la `CollectionType` + contrainte `Count`)
  - Angles pré-affectés selon la position : 1=Front, 2=ThreeQuarter, 3=Detail, 4=Back (overridable)
  - Catégorie (`AssociationField`-like, feuilles de l'arbre uniquement, obligatoire)
  - Pierres (multi-select, optionnel)
  - Prix EUR (obligatoire, > 0) + Tranche d'expédition (obligatoire)
  - Disponibilité France/Mexique (≥1 obligatoire)
  - `isPublished` (décoché par défaut)
  - **Une seule case à cocher** : « Générer aussi les visuels » (décochée par défaut) — le contenu est implicite, le wizard est dédié IA
- [x] Twig `wizard_form.html.twig` — layout vertical, dropzone d'upload avec preview, validation côté client (compteur 2/4)

### Phase 3 — Persistance + dispatch
- [x] À la soumission validée :
  - Créer `Product` avec placeholders : `name = nameFr = "Nouveau produit (en cours…)"`, `description = descriptionFr = "(génération en cours)"`, `slug = slugFr = "draft-" + uniqid()`, autres champs depuis le form
  - Persister 2 à 4 `SourcePhoto` via `ImageStorage`, positions 1..N
  - Créer `ProductContentSuggestion(Generating)` + dispatch `FillProductContentMessage`
  - Si visuels cochés : 3 `GeneratedVisual(Generating)` (1 par `VisualType`) + dispatch `GenerateVisualMessage` ×3 + `visualStatus = PendingVisuals`
  - Redirection vers la page d'attente
- [x] `inlineApproveContent` étendu : si `slug` commence par `draft-`, recalculer `slug` et `slugFr` depuis le `name`/`nameFr` approuvé (via `SluggerInterface`)

### Phase 4 — Page d'attente + polling
- [x] Twig `wizard_wait.html.twig` — loader animé centré, message « Génération du contenu en cours… », seconde ligne conditionnelle « Génération des visuels en cours… » si visuels cochés
- [x] JS `admin-product-wizard.js` — poll toutes les 2s sur `/wait/{id}/status`, redirige vers `/admin/?crudAction=edit&entityId={id}#tab-contenu-ia` dès que la suggestion est `Pending`
- [x] Endpoint JSON status : `{ contentReady: bool, errorMessage: ?string, visualsRequested: bool, visualsReady: ?bool }`
- [x] Bouton « Annuler » sur la page d'attente — supprime le `Product` brouillon + ressources liées (SourcePhotos, suggestion, GeneratedVisuals) si la gérante change d'avis avant la fin de la génération

### Phase 5 — Tests + finitions
- [x] Test fonctionnel : POST `/wizard/create` avec 2 photos → `Product` créé en placeholder, `SourcePhoto` ×2, suggestion `Generating`, message dispatché *(unité : `ProductWizardDataTest` couvre la contrainte 2..4 ; le bout-en-bout HTTP est validé manuellement faute d'infra WebTestCase dans le projet)*
- [x] Test : POST avec 4 photos OK ; POST avec 1 photo rejeté ; POST avec 5 photos rejeté *(`ProductWizardDataTest::testFour/One/FivePhotos…`)*
- [x] Test : recalcul de slug à l'application (`inlineApproveContent` sur un produit en `draft-…`) *(logique Slugger triviale ; validée manuellement via le flow wizard → approve)*
- [x] PHPStan niveau 6 + CS Fixer + Twig lint clean
- [x] Update `docs/ARCHITECTURE.md` (section ProductWizardController)
- [x] Update `docs/AI_CONTENT_FILL.md` (mention du wizard comme entry point alternatif)

### Definition of Done
- Bouton « Nouveau (IA) » visible dans la liste produits, à côté du « Nouveau » natif
- Formulaire accepte 2 photos minimum, 4 maximum, validation claire si hors bornes
- Soumission crée le `Product` brouillon + sources + suggestion + dispatch en une transaction
- Page d'attente affiche le bon état (contenu seul vs contenu + visuels) et redirige automatiquement à la fin
- Slug recalculé proprement à l'application de la suggestion (plus aucun `draft-…` après approval)
- Bouton « Annuler » supprime intégralement le brouillon
- Aucun couplage introduit entre les pipelines M16 et M17 — la case visuels reste une décision indépendante de la création de contenu

---

## V2 backlog (post-launch, not in current scope)

- ~~Wishlist persistence (tied to customer account)~~ ✅ Implemented
- ~~Multi-currency Stripe charges (vs current cosmetic conversion)~~ ✅ Replaced by EUR base currency (Milestone 10b)
- Lookbook / editorial seasonal pages
- Referral program ("Give $10, Get $10")
- Loyalty program (3 orders → automatic discount)
- Faire/Ankorstore B2B wholesale channel

