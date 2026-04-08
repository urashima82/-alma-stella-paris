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
- [ ] DDEV configuration (`php 8.3`, `mariadb 10.11`, `symfony` project type)
- [ ] `composer create-project symfony/skeleton`
- [ ] Install core bundles:
  - `symfony/orm-pack` (Doctrine + migrations)
  - `easycorp/easyadmin-bundle`
  - `symfony/asset-mapper` + Tailwind CSS
  - `symfony/security-bundle`
  - `symfony/mailer`
  - `knplabs/knp-paginator-bundle`
  - `liip/imagine-bundle` (image resizing)
- [ ] `.env.local` template with required keys documented (no actual values)
- [ ] Base Twig layout (`templates/shop/base.html.twig`) with correct font imports
  (Cormorant Garamond + Inter via Google Fonts)
- [ ] Tailwind configured with Alma Stella color tokens
- [ ] EasyAdmin `DashboardController` accessible at `/admin`

### Definition of Done
- `ddev start && ddev exec php bin/console cache:clear` runs without error
- `/admin` returns the EasyAdmin dashboard (empty, no CRUDs yet)
- Homepage `/` returns a styled "coming soon" page using the correct palette
- No deprecation warnings in the Symfony profiler

---

## Milestone 1 — Product catalog (admin + data layer)
*Estimated effort: 4-5h*

### Tasks
- [ ] Create all entities: `Product`, `ProductCategory`, `ProductImage`,
  `ShippingTier` enum
- [ ] Doctrine migrations generated and applied
- [ ] EasyAdmin CRUD for `Product`:
  - All fields editable
  - `ShippingTier` displayed as colored badge (green/orange/blue)
  - `basePrice` and computed `displayPrice` both visible in index
  - Image upload with preview
  - `relatedProducts` via `AssociationField` (ManyToMany self-referencing)
- [ ] EasyAdmin CRUD for `ProductCategory`
- [ ] DataFixtures: 12 sample products matching `DESIGN.md` product list
- [ ] Sluggable behavior on `Product::$name` (auto-generated, unique)

### Definition of Done
- Create a product in EasyAdmin → appears in the database
- `ShippingTier` badge renders correctly in 3 colors
- `displayPrice` = `basePrice` + tier cost (verify with $38 + $10 = $48)
- All 12 fixture products load correctly via `doctrine:fixtures:load`
- French labels in EasyAdmin show correct accents (é, à, è, ù, ê, etc.)

---

## Milestone 2 — Public catalog (frontend)
*Estimated effort: 5-6h*

### Tasks
- [ ] Homepage (`/`):
  - Hero section (lifestyle image placeholder, headline, CTA)
  - 3-icon strip (Water resistant / Natural stones / Ships worldwide)
  - Featured products grid (4 products, `isFeatured = true`)
  - Instagram feed strip (6 placeholder squares with @alma_stella_paris)
- [ ] Catalog page (`/shop`):
  - Product grid (12 per page, paginated)
  - Category filter (All / Necklaces / Earrings / Bracelets / Rings / Anklets)
  - Hover state: gold border on product card
- [ ] Product detail page (`/shop/{slug}`):
  - Large image + thumbnail strip
  - Name, display price in selected currency
  - Material badges (Acier inoxydable / Pierre naturelle / Résistant à l'eau)
  - Shipping info accordion
  - "Wear it with" — 3 related products
- [ ] About page (`/about`):
  - Brand story with correct French accents
  - 3 values cards
- [ ] 404 and 500 error pages styled with brand identity

### Definition of Done
- All pages load without Symfony profiler errors
- Category filter works without page reload (Stimulus or simple Turbo)
- Product detail shows correct `displayPrice` including shipping tier
- "Wear it with" section shows linked related products set in EasyAdmin
- All French copy uses correct UTF-8 accented characters
- Pages are mobile-responsive (test at 375px and 768px viewport)
- Visual result matches `docs/design/screenshots/` reference images

---

## Milestone 3 — Currency selector
*Estimated effort: 2-3h*

### Tasks
- [ ] `CurrencyConverter` service (open.er-api.com, cached 6h)
- [ ] `CurrencyExtension` Twig extension with `|price` filter
- [ ] Currency selector in header (USD / EUR / CAD / GBP / MXN)
- [ ] Selection stored in session + cookie (30-day expiry)
- [ ] Disclaimer displayed when non-USD currency selected:
  *"Prices shown in [EUR] are indicative. You will be charged in USD at checkout."*
- [ ] Fallback to USD if exchange rate API is unavailable

### Definition of Done
- Select EUR → all product prices update across all pages
- Refresh the page → currency selection is remembered
- Close and reopen browser → currency still remembered (cookie)
- Force the exchange rate API to fail (mock) → site displays USD without error
- Disclaimer visible only when non-USD currency selected

---

## Milestone 4 — Cart & Stripe checkout
*Estimated effort: 6-8h*

### Tasks
- [ ] Cart stored in session (no login required at launch)
- [ ] Cart drawer (slide-in, Stimulus controller):
  - Item list with thumbnails
  - Quantity update
  - Item removal
  - Subtotal in selected currency
- [ ] Checkout page (`/checkout`):
  - Customer info form (name, email)
  - Shipping address form with country selector
  - Order summary
  - Stripe Elements payment form (card + Apple Pay + Google Pay)
- [ ] Stripe PaymentIntent creation (server-side)
- [ ] Order entity created on successful payment
- [ ] Confirmation page (`/order/{reference}/confirmation`):
  - "Merci ! ✦ Your order is confirmed." message
  - Order summary
  - Estimated delivery note
- [ ] Brevo order confirmation email sent automatically
- [ ] Stock decrement on successful order

### Definition of Done
- Add product to cart → drawer opens and shows item
- Update quantity → subtotal updates
- Complete Stripe test payment (use test card `4242 4242 4242 4242`)
- Order appears in EasyAdmin with correct status `pending`
- Confirmation email received in Brevo test inbox
- Stock decrements correctly in the database
- Cart clears after successful payment

---

## Milestone 5 — EasyAdmin order management
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

## Milestone 6 — Social publishing
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

## Milestone 7 — Email marketing & wishlist
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

## Milestone 8 — SEO & performance
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
