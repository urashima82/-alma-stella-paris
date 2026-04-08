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
  - Image upload with preview
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
  - Large image + thumbnail strip
  - Name, display price in selected currency
  - Material badges (Acier inoxydable / Pierre naturelle / Résistant à l'eau)
  - "Pièce unique" badge + "Vendue" state (greyed, "Add to cart" disabled)
  - Shipping info accordion
  - "Wear it with" — 3 related products
- [x] About page (`/about`):
  - Brand story with correct French accents
  - 3 values cards
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
- [x] Cart stored in session (no login required at launch)
- [x] Cart drawer (slide-in, Stimulus controller):
  - Item list with thumbnails
  - No quantity selector (pièce unique = always 1)
  - Item removal
  - Subtotal in selected currency
- [x] Checkout page (`/checkout`):
  - Customer info form (name, email)
  - Shipping address form with country selector
  - Order summary
  - Stripe Elements payment form (card + Apple Pay + Google Pay)
- [ ] Stripe PaymentIntent creation (server-side)
- [x] Order entity created on successful payment
- [x] Confirmation page (`/order/{reference}/confirmation`):
  - "Merci ! ✦ Your order is confirmed." message
  - Order summary
  - Estimated delivery note
- [ ] Brevo order confirmation email sent automatically
- [ ] On successful payment: set `isSoldOut = true` + `soldAt = now()` on purchased products
- [x] Prevent adding sold-out product to cart (server-side check)

### Definition of Done
- Add product to cart → drawer opens and shows item
- Cannot add sold-out product to cart (button disabled + server-side rejection)
- Complete Stripe test payment (use test card `4242 4242 4242 4242`)
- Order appears in EasyAdmin with correct status `pending`
- Purchased product automatically marked `isSoldOut` with `soldAt` timestamp
- Confirmation email received in Brevo test inbox
- Cart clears after successful payment

---

## Milestone 6 — EasyAdmin order management
*Estimated effort: 3-4h*

### Tasks
- [ ] EasyAdmin CRUD for `Order`:
  - Status workflow: `pending → processing → shipped → delivered`
  - Tracking number field
  - Origin country field (France / Mexico) — affects shipping display only
  - Customer details visible
  - Order items list with product snapshots
- [ ] Order status change triggers Brevo email (shipped → sends tracking number)
- [ ] Dashboard stats widget: orders today, revenue this week, low stock alert

### Definition of Done
- Change order status to "shipped" + add tracking number → customer receives email
- Dashboard stats display correctly
- Origin country (FR/MX) saved without affecting customer-facing prices

---

## Milestone 7 — Social publishing
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

## Milestone 8 — Email marketing & wishlist
*Estimated effort: 4-5h*

### Tasks
- [ ] Newsletter subscriber entity + GDPR-compliant signup form (footer + exit popup)
- [ ] Welcome email sequence via Brevo (immediate + 10% off code)
- [ ] Abandoned cart detection (session-based, 1h delay via Symfony Messenger)
- [ ] Abandoned cart email with product images
- [ ] Post-purchase review request email (J+14 via Messenger scheduler)
- [ ] Wishlist (guest via email, no account required):
  - Add/remove from product cards and detail pages
  - Back-in-stock notification email when stock > 0
- [ ] `ProductReview` entity + submission form (post-purchase email link only)
- [ ] Reviews displayed on product detail page with country flag

### Definition of Done
- Subscribe to newsletter → welcome email received within 1 minute
- Add to cart, wait for simulated 1h timeout → abandoned cart email received
- Submit a review via post-purchase email link → review appears on product page
- Add out-of-stock product to wishlist → email received when stock restored
- GDPR consent checkbox present and required on all email capture forms

---

## Milestone 9 — SEO & performance
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

- Customer accounts (order history, saved addresses, wishlist persistence)
- Multi-currency Stripe charges (vs current cosmetic conversion)
- Lookbook / editorial seasonal pages
- Referral program ("Give $10, Get $10")
- Loyalty program (3 orders → automatic discount)
- Branded order tracking page
- Faire/Ankorstore B2B wholesale channel
