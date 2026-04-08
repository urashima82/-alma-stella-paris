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
│   │   ├── LocaleRedirectController.php  # Root / → /{locale}/ redirect
│   │   └── Shop/             # Public-facing controllers (locale-prefixed)
│   │       ├── HomeController.php
│   │       ├── CatalogController.php
│   │       ├── ProductController.php
│   │       └── AboutController.php
│   ├── Entity/
│   ├── Enum/
│   ├── Repository/
│   ├── Service/
│   │   ├── CurrencyConverter.php
│   │   ├── SocialPublisher.php
│   │   ├── BrevoMailer.php
│   │   └── ShippingCalculator.php
│   ├── Twig/
│   │   ├── CurrencyExtension.php
│   │   └── LocaleProductExtension.php    # |localized_name, |localized_description, |localized_slug
│   └── EventSubscriber/
│       └── LocaleSubscriber.php          # Persists locale in session + cookie (30 days)
├── templates/
│   ├── admin/
│   └── shop/
│       ├── base.html.twig
│       ├── home/
│       ├── catalog/
│       ├── product/
│       ├── about/
│       ├── cart/
│       └── account/
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
    private array $images;                   // Ordered list of image paths
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

### Other entities (summary)

| Entity | Purpose |
|---|---|
| `ProductCategory` | Necklaces, Earrings, Bracelets, Rings, Anklets, Sets |
| `ProductImage` | Ordered images per product |
| `OrderItem` | Snapshot of product + price at order time |
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

### BrevoMailer

Automated email sequences via Brevo API v3:

| Trigger | Delay | Template |
|---|---|---|
| Cart abandoned | +1 hour | Product image + CTA |
| Order confirmed | Immediate | Order summary |
| Post-purchase | +14 days | Review request + related products |
| Back in stock | On stock update | Wishlist notification |
| New subscriber | Immediate | Welcome + 10% off code |

---

## Security notes

- Stripe webhook signature verified on every webhook call
- All admin routes protected by `ROLE_ADMIN`
- CSRF protection on all forms (Symfony default)
- Product images stored outside `public/` and served via a controller
  (prevents direct URL guessing of unpublished products)
- API keys (Stripe, Brevo, TikTok, Pinterest) stored in `.env.local` only —
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
