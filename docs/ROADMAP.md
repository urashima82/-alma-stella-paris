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

## Milestone 4 — Currency selector
*Estimated effort: 2-3h*

### Tasks
- [x] `CurrencyConverter` service (open.er-api.com, cached 6h)
- [x] `CurrencyExtension` Twig extension with `|price` filter
- [x] Currency selector in header (USD / EUR / CAD / GBP / MXN)
- [x] Selection stored in session + cookie (30-day expiry)
- [x] Disclaimer displayed when non-USD currency selected:
  *"Prices shown in [EUR] are indicative. You will be charged in USD at checkout."*
- [x] Fallback to USD if exchange rate API is unavailable

### Definition of Done
- Select EUR → all product prices update across all pages
- Refresh the page → currency selection is remembered
- Close and reopen browser → currency still remembered (cookie)
- Force the exchange rate API to fail (mock) → site displays USD without error
- Disclaimer visible only when non-USD currency selected

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

## Milestone 9 — Automated emails & customer testimonials
*Estimated effort: 2-3h*

### Tasks
- [ ] `Testimonial` entity (email, rating 1-5, text, firstName, lastNameInitial, city, status)
- [ ] Post-purchase testimonial request email (J+14 via Symfony Scheduler)
  - Deduplicate by email: if a `Testimonial` already exists for this email → skip
  - Email sourced from the `Order` (works for guests and logged-in customers)
- [ ] Public testimonial submission form (accessible via unique token in email)
- [ ] Testimonial moderation in EasyAdmin (pending → approved / rejected)
- [ ] Display latest approved testimonials on homepage (section with "Voir tous les témoignages" link)
- [ ] Dedicated `/testimonials` page (all approved testimonials)
- [ ] Footer link to `/testimonials`
- [ ] DataFixtures: sample testimonials (approved) for development display
- [ ] Schema.org `AggregateRating` from approved testimonials

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

## Milestone 10 — Social publishing
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

## Milestone 11 — Security audit & hardening
*Estimated effort: 3-4h*

> **Every fix requires developer approval before implementation.**
> Claude audits and proposes — developer validates before any change is applied.

### Tasks
- [ ] OWASP Top 10 audit (XSS, CSRF, SQL injection, mass assignment, etc.)
- [ ] Stripe webhook signature verification audit
- [ ] Authentication & session security review (magic link, customer auth)
- [ ] Rate limiting on sensitive endpoints (login, testimonial submission, checkout)
- [ ] Input validation & sanitization audit (forms, query parameters)
- [ ] CORS & security headers review (`X-Content-Type-Options`, `X-Frame-Options`, CSP, etc.)
- [ ] Dependency vulnerability scan (`composer audit`)
- [ ] File upload security review (image uploads, WebP conversion)
- [ ] Environment secrets audit (`.env` not exposed, no hardcoded keys)
- [ ] EasyAdmin access control review (admin routes properly protected)

### Definition of Done
- Full audit report delivered with findings classified by severity (critical / high / medium / low)
- Each proposed fix reviewed and approved by developer before implementation
- All critical and high severity issues resolved
- `composer audit` returns no known vulnerabilities
- Security headers verified via browser dev tools or online scanner

---

## Milestone 12 — SEO & performance
*Estimated effort: 3h*

### Tasks
- [ ] Dynamic `<title>` and `<meta description>` on all public pages
- [ ] Schema.org `Product` structured data on product detail pages
- [ ] Schema.org `BreadcrumbList` on catalog and product pages
- [ ] Auto-generated XML sitemap (`/sitemap.xml`)
- [ ] Open Graph tags for social sharing
- [ ] Image optimization (WebP conversion via Liip Imagine)
- [ ] Lighthouse score ≥ 90 on mobile for homepage

### Definition of Done
- Google Rich Results Test validates product structured data
- `/sitemap.xml` lists all published products and static pages
- Sharing a product URL on social media shows correct OG image and title
- Lighthouse mobile score ≥ 90 for homepage

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

## Milestone 10 — Automated emails & customer testimonials
*Estimated effort: 2-3h*

### Tasks
- [ ] `Testimonial` entity (email, rating 1-5, text, firstName, lastNameInitial, city, status)
- [ ] Post-purchase testimonial request email (J+14 via Symfony Scheduler)
  - Deduplicate by email: if a `Testimonial` already exists for this email → skip
  - Email sourced from the `Order` (works for guests and logged-in customers)
- [ ] Public testimonial submission form (accessible via unique token in email)
- [ ] Testimonial moderation in EasyAdmin (pending → approved / rejected)
- [ ] Display latest approved testimonials on homepage (section with "Voir tous les témoignages" link)
- [ ] Dedicated `/testimonials` page (all approved testimonials)
- [ ] Footer link to `/testimonials`
- [ ] DataFixtures: sample testimonials (approved) for development display
- [ ] Schema.org `AggregateRating` from approved testimonials

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

## Milestone 11 — Social publishing
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

## Milestone 12 — Security audit & hardening
*Estimated effort: 3-4h*

> **Every fix requires developer approval before implementation.**
> Claude audits and proposes — developer validates before any change is applied.

### Tasks
- [ ] OWASP Top 10 audit (XSS, CSRF, SQL injection, mass assignment, etc.)
- [ ] Stripe webhook signature verification audit
- [ ] Authentication & session security review (magic link, customer auth)
- [ ] Rate limiting on sensitive endpoints (login, testimonial submission, checkout)
- [ ] Input validation & sanitization audit (forms, query parameters)
- [ ] CORS & security headers review (`X-Content-Type-Options`, `X-Frame-Options`, CSP, etc.)
- [ ] Dependency vulnerability scan (`composer audit`)
- [ ] File upload security review (image uploads, WebP conversion)
- [ ] Environment secrets audit (`.env` not exposed, no hardcoded keys)
- [ ] EasyAdmin access control review (admin routes properly protected)

### Definition of Done
- Full audit report delivered with findings classified by severity (critical / high / medium / low)
- Each proposed fix reviewed and approved by developer before implementation
- All critical and high severity issues resolved
- `composer audit` returns no known vulnerabilities
- Security headers verified via browser dev tools or online scanner

---

## Milestone 13 — SEO & performance
*Estimated effort: 3h*

### Tasks
- [ ] Dynamic `<title>` and `<meta description>` on all public pages
- [ ] Schema.org `Product` structured data on product detail pages
- [ ] Schema.org `BreadcrumbList` on catalog and product pages
- [ ] Auto-generated XML sitemap (`/sitemap.xml`)
- [ ] Open Graph tags for social sharing
- [ ] Image optimization (WebP conversion via Liip Imagine)
- [ ] Lighthouse score ≥ 90 on mobile for homepage

### Definition of Done
- Google Rich Results Test validates product structured data
- `/sitemap.xml` lists all published products and static pages
- Sharing a product URL on social media shows correct OG image and title
- Lighthouse mobile score ≥ 90 for homepage

---

## V2 backlog (post-launch, not in current scope)

- ~~Wishlist persistence (tied to customer account)~~ ✅ Implemented
- Multi-currency Stripe charges (vs current cosmetic conversion)
- Lookbook / editorial seasonal pages
- Referral program ("Give $10, Get $10")
- Loyalty program (3 orders → automatic discount)
- Faire/Ankorstore B2B wholesale channel
