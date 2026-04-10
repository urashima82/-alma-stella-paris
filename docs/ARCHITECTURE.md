# ARCHITECTURE.md — Alma Stella Paris

> All decisions in this file are **validated**. Do not re-discuss unless
> the developer explicitly opens the question.

---

## Project structure

```
alma-stella/
├── assets/
│   ├── styles/
│   │   └── app.css          # Tailwind entry point
│   └── controllers/          # Stimulus controllers
│       ├── currency_selector_controller.js  # Dropdown toggle for currency selector
│       ├── cart_drawer_controller.js        # Cart drawer slide-in (add/remove/display)
│       └── stripe_payment_controller.js     # Stripe Payment Element mount & confirm
├── config/
├── docs/
│   └── design/
│       └── screenshots/      # base44 prototype screenshots (visual reference)
│           ├── homepage-hero.png
│           ├── catalog-grid.png
│           ├── product-detail.png
│           ├── cart-drawer.png
│           ├── checkout.png
│           └── about.png
├── src/
│   ├── Controller/
│   │   ├── Admin/            # EasyAdmin CRUD controllers
│   │   │   ├── AdminLoginController.php  # Magic link login flow
│   │   │   ├── OrderCrudController.php   # Order management with status workflow
│   │   │   └── OrderItemCrudController.php # OrderItem sub-form (read-only)
│   │   ├── LocaleRedirectController.php  # Root / → /{locale}/ redirect
│   │   └── Shop/             # Public-facing controllers (locale-prefixed)
│   │       ├── HomeController.php
│   │       ├── CatalogController.php
│   │       ├── ProductController.php
│   │       ├── CurrencyController.php  # POST /currency/switch — changes active currency
│   │       ├── CartController.php      # Cart API: add/remove/content (JSON responses)
│   │       └── AboutController.php
│   ├── Entity/
│   ├── Enum/
│   ├── Repository/
│   ├── Service/
│   │   ├── CurrencyConverter.php
│   │   ├── CartManager.php          # Session-based cart (add/remove/get products)
│   │   ├── SocialPublisher.php
│   │   ├── OrderMailer.php              # Order confirmation email via Symfony Mailer
│   │   ├── PendingOrderVerifier.php    # Verifies pending orders against Stripe API
│   │   └── ShippingCalculator.php
│   ├── Twig/
│   │   ├── CurrencyExtension.php
│   │   ├── LocaleProductExtension.php    # |localized_name, |localized_description, |localized_slug
│   │   └── TrackingExtension.php         # tracking_url() — generates 17track URL from tracking number
│   ├── Security/
│   │   └── AdminAuthenticationEntryPoint.php  # Redirects unauthenticated to /admin/login
│   ├── EventSubscriber/
│   │   ├── LocaleSubscriber.php          # Persists locale in session + cookie (30 days)
│   │   ├── CurrencySubscriber.php        # Persists currency in session + cookie (30 days)
│   │   ├── AdminLoginSubscriber.php      # Updates lastLoggedInAt on login (invalidates link)
│   │   ├── EasyAdminFlashSubscriber.php  # Adds flash messages on CRUD persist/update/delete
│   │   └── OrderStatusSubscriber.php     # Sends shipped email (in customer's locale) when status → shipped, blocks without tracking number
│   ├── Message/
│   │   └── VerifyPendingOrdersMessage.php
│   ├── MessageHandler/
│   │   └── VerifyPendingOrdersHandler.php
│   ├── Command/
│   │   └── VerifyPendingOrdersCommand.php  # CLI: app:verify-pending-orders
│   └── Schedule.php                        # Symfony Scheduler provider (default)
├── templates/
│   ├── admin/
│   │   ├── dashboard.html.twig
│   │   └── login.html.twig              # Magic link login page (brand-styled)
│   ├── email/
│   │   ├── order_confirmation.html.twig  # Bilingual order confirmation email
│   │   ├── order_shipped.html.twig       # Bilingual shipped notification with tracking
│   │   └── admin_login_link.html.twig    # Magic link email template
│   └── shop/
│       ├── base.html.twig
│       ├── home/
│       ├── catalog/
│       ├── product/
│       ├── about/
│       ├── cart/
│       └── account/
│   └── bundles/
│       └── EasyAdminBundle/
│           └── flash_messages.html.twig  # Override: toast markup instead of default alerts
├── public/
│   ├── css/
│   │   └── admin.css             # EasyAdmin dark-mode fix + toast notification styles
│   └── js/
│       └── admin-toast.js        # Toast system: intercepts AJAX, auto-dismiss, progress bar
├── bundles/
│   └── TwigBundle/
│       └── Exception/         # Custom 404/500 error pages
└── tests/
```

---

## Entities

### Product

```php
// src/Entity/Product.php
class Product
{
    private int $id;
    private string $name;                    // English name for SEO
    private string $nameFr;                  // French name (with accents)
    private string $slug;                    // URL-friendly, auto-generated
    private string $description;             // English
    private string $descriptionFr;           // French (with accents)
    private float $basePrice;               // USD — internal, never displayed raw
    private ShippingTier $shippingTier;     // Determines shipping cost baked in
    private ProductCategory $category;
    private bool $isPublished;
    private bool $isFeatured;
    private int $stock;
    private ?string $thumbnail;               // VichUploader — card/catalog image (4:5, 600×750)
    private ?string $wornPhoto;                // VichUploader — worn photo, hero on detail page (4:5, 800×1000)
    private ?string $contextPhoto;             // VichUploader — lifestyle/context photo (4:5, 800×1000)
    private Collection $relatedProducts;     // "Wear it with" — ManyToMany self-ref
    private Collection $wishlistItems;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    // Computed — never stored
    public function getDisplayPrice(): float
    {
        return $this->basePrice + $this->shippingTier->shippingCostUsd();
    }
}
```

### ShippingTier enum

```php
// src/Enum/ShippingTier.php
enum ShippingTier: string
{
    case Standard = 'standard'; // Small pieces <100g  — rings, studs, fine chains   → +$10
    case Heavy    = 'heavy';    // Statement pieces 100-350g — large necklaces, cuffs → +$14
    case Set      = 'set';      // Full sets / gift boxes 350g-1kg                    → +$22

    public function label(): string
    {
        return match($this) {
            self::Standard => 'Pièce légère',   // accent required
            self::Heavy    => 'Pièce forte',    // accent required
            self::Set      => 'Set / Coffret',
        };
    }

    public function shippingCostUsd(): float
    {
        return match($this) {
            self::Standard => 10.00,
            self::Heavy    => 14.00,
            self::Set      => 22.00,
        };
    }

    public function maxWeightGrams(): int
    {
        return match($this) {
            self::Standard => 100,
            self::Heavy    => 350,
            self::Set      => 1000,
        };
    }
}
```

> **Note on shipping costs:** When La Poste or Estafeta update their rates,
> update `shippingCostUsd()` only — all display prices recalculate automatically.
> No database migration needed.

### Order

```php
class Order
{
    private int $id;
    private string $reference;           // e.g. ASP-2024-00042
    private OrderStatus $status;
    private string $customerEmail;
    private string $customerName;
    private Address $shippingAddress;
    private string $shippingCountry;     // ISO 3166-1 alpha-2
    private string $originCountry;       // 'FR' or 'MX' — set by Estelle at dispatch
    private float $totalUsd;
    private string $stripePiId;          // Stripe PaymentIntent ID
    private string $trackingNumber;
    private Collection $items;           // OrderItem
    private \DateTimeImmutable $createdAt;
}
```

### Admin

```php
// src/Entity/Admin.php
class Admin implements UserInterface
{
    private int $id;
    private string $email;              // Unique, used as user identifier
    private array $roles;               // ROLE_ADMIN always included
    private ?\DateTimeImmutable $lastLoggedInAt;  // Updated on login, invalidates old links
    private \DateTimeImmutable $createdAt;
}
```

> **Authentication:** Passwordless via Symfony Login Link. No password stored.
> Link is signed (HMAC) using `email` + `lastLoggedInAt`, expires in 10 minutes,
> single-use (lastLoggedInAt changes on login, invalidating old links).

### Other entities (summary)

| Entity | Purpose |
|---|---|
| `ProductCategory` | Necklaces, Earrings, Bracelets, Rings, Anklets, Sets |
| ~~`ProductImage`~~ | **Removed** — replaced by 3 VichUploader fields on `Product`: `thumbnail`, `wornPhoto`, `contextPhoto` |
| `OrderItem` | Snapshot of product + price at order time |
| `Admin` | Admin users — passwordless auth via magic link |
| `WishlistItem` | Guest (email) or user + product + notification flag |
| `ProductReview` | Rating 1-5, text, country, verified purchase flag |
| `NewsletterSubscriber` | Email + consent + source (popup/footer/checkout) |

---

## Services

### CurrencyConverter

- Fetches rates from `https://open.er-api.com/v6/latest/USD` (free, no key)
- Cached 6 hours via Symfony Cache (Redis in production, filesystem in dev)
- Supported currencies: `USD`, `EUR`, `CAD`, `GBP`, `MXN`
- Falls back to USD silently if the external API is unavailable
- Formats output using PHP `NumberFormatter` with correct locale per currency

### ImageProcessor

- Resizes and converts images to WebP (quality 85%)
- Uses Intervention Image v4 with GD driver
- Called by `ImageUploadSubscriber` after EasyAdmin persist/update events

### ImageUploadSubscriber

- Listens to `AfterEntityPersistedEvent` and `AfterEntityUpdatedEvent`
- Processes Product images: thumbnail (600×750), wornPhoto (800×1000), contextPhoto (800×1000)
- Converts non-WebP uploads to WebP, resizes to max dimensions

### StripeService

- Creates Stripe PaymentIntents server-side (amount in cents, USD)
- Retrieves existing PaymentIntents for verification
- Uses `StripeClient` (SDK v20) with secret key from env
- Automatic payment methods enabled (card, Apple Pay, Google Pay)
- Order reference stored in PaymentIntent metadata
- **No webhooks** — payment verification via 3 layers:
  1. Immediate: Stimulus controller calls `POST /payment/confirm` after payment
  2. On-return: payment page detects 3DS redirect return and auto-confirms
  3. Scheduler: `VerifyPendingOrdersMessage` runs every 5 min via Symfony Scheduler

### SocialPublisher

Three channels, three integration levels:

| Channel | Integration | Trigger |
|---|---|---|
| Pinterest | Full API — creates Pin automatically | EasyAdmin action button |
| TikTok Shop | Full API — creates/updates product in catalog | EasyAdmin action button |
| Instagram | Deep link — opens mobile app with pre-filled caption | EasyAdmin generates link |

Auto-generated content per product:
- **Title:** `$product->getName()` (English)
- **Description:** `$product->getDescription()` + auto hashtags
- **Hashtags:** `#jewelry #bijoux #bohemian #frenchjewelry #almastellaparis`
- **Link:** canonical product URL on the site

### OrderMailer

Transactional emails via Symfony Mailer (Mailpit in dev, SMTP in production):

| Trigger | Template |
|---|---|
| Order confirmed | `email/order_confirmation.html.twig` — bilingual (FR/EN), order summary with items |
| Order shipped | `email/order_shipped.html.twig` — bilingual, clickable tracking link (La Poste/17track), link to tracking page |
| Admin login link | `email/admin_login_link.html.twig` — magic link with 10min expiry |

- Sender: `hello@almastellaparis.com`
- Email failure does not block the payment flow (caught and logged)

---

## Security notes

- Stripe webhook signature verified on every webhook call
- All admin routes protected by `ROLE_ADMIN`
- CSRF protection on all forms (Symfony default)
- Product images stored in `public/uploads/products/` with hashed filenames
  (SmartUniqueNamer) — unpublished products not linked in HTML
- API keys (Stripe, TikTok, Pinterest) stored in `.env.local` only —
  never committed, never hardcoded
- Rate limiting on checkout and login endpoints via Symfony RateLimiter

---

## Database

MariaDB 10.11+ via DDEV in development.

```bash
# Start dev environment
ddev start

# Run migrations
ddev exec php bin/console doctrine:migrations:migrate

# Load fixtures (dev only)
ddev exec php bin/console doctrine:fixtures:load
```

Migrations are generated, never hand-written:
```bash
ddev exec php bin/console doctrine:migrations:diff
```
