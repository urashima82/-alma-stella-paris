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
│       ├── account_dropdown_controller.js   # Account menu dropdown toggle (header)
│       ├── address_selector_controller.js   # Fills checkout form from saved customer address
│       ├── billing_toggle_controller.js     # Shows/hides billing address fields (checkbox toggle)
│       ├── cart_drawer_controller.js        # Cart drawer slide-in (add/remove/display)
│       ├── catalog_load_more_controller.js  # Catalog "load more" (auto ×3 via IntersectionObserver then manual button, AJAX append, back/forward restore via sessionStorage, no-JS pagination fallback)
│       ├── category_drawer_controller.js   # Mobile filter drawer (slide-in from left, holds categories + stones)
│       ├── category_panel_controller.js    # Desktop toolbar Category drawer (full-width inline panel, tile cards w/ subcategory lists, ESC + sibling-close)
│       ├── checkout_identify_controller.js  # Email detection: existing account → login, new → guest
│       ├── collapsible_controller.js        # Collapsible/accordion sections (sidebar, mobile nav)
│       ├── coupon_code_controller.js        # Async coupon code validation at checkout
│       ├── csrf_protection_controller.js    # CSRF token handling on forms
│       ├── currency_selector_controller.js  # Dropdown toggle for currency selector
│       ├── email_check_controller.js        # Async email existence check on registration form
│       ├── lightbox_controller.js           # Image lightbox / gallery view on product detail
│       ├── mobile_menu_controller.js        # Mobile hamburger menu toggle
│       ├── reservation_timer_controller.js  # Checkout countdown timer (mm:ss, auto-reload on expiry)
│       ├── star_rating_controller.js        # Star rating input on testimonial submission form
│       ├── stone_drawer_controller.js       # Immersive stones drawer (toolbar trigger, multi-select tiles, search, navigates to ?stones=slug1,slug2)
│       ├── stripe_payment_controller.js     # Stripe Payment Element mount & confirm
│       ├── turnstile_controller.js          # Cloudflare Turnstile CAPTCHA integration
│       └── wishlist_toggle_controller.js    # Heart toggle (AJAX POST, guest redirect to login)
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
│   │   │   ├── AdminLoginController.php          # Magic link login flow
│   │   │   ├── AdminCrudController.php           # Admin user management (Super Admin only)
│   │   │   ├── ContactMessageCrudController.php  # Contact messages (read/delete)
│   │   │   ├── CustomerCrudController.php        # Customer list (read-only for admin)
│   │   │   ├── DashboardController.php           # EasyAdmin dashboard with stats widgets
│   │   │   ├── OrderCrudController.php           # Order management with status workflow
│   │   │   ├── OrderItemCrudController.php       # OrderItem sub-form (read-only)
│   │   │   ├── ProductCategoryCrudController.php # Category management
│   │   │   ├── ProductCrudController.php         # Product mgmt + inline AI workspace (sources upload, gallery, actions)
│   │   │   ├── ProductWizardController.php       # AI-assisted product creation wizard (form → wait → review)
│   │   │   ├── SourcePhotoCrudController.php     # Standalone source photo CRUD (also embedded in product page)
│   │   │   ├── PromotionCrudController.php      # Promotion management with targeting + stats
│   │   │   ├── StoneCrudController.php          # Stone CRUD — bilingual, image upload, product links
│   │   │   ├── TestimonialCrudController.php    # Testimonial moderation (pending/approved/rejected)
│   │   │   ├── CategoryReorderController.php    # Drag & drop category reordering API
│   │   │   ├── ShippingSettingsCrudController.php # Shipping tier cost overrides
│   │   │   ├── SiteSettingsCrudController.php    # Site-wide settings (active collection, maintenance mode)
│   │   │   ├── CategoryVisualPromptCrudController.php # AI prompt management per category × visual type
│   │   │   └── GeneratedVisualCrudController.php      # AI visual validation (approve/reject/regenerate)
│   │   ├── LocaleRedirectController.php  # Root / → /{locale}/ redirect
│   │   ├── SitemapController.php         # /sitemap.xml — auto-generated XML sitemap (no locale prefix)
│   │   └── Shop/             # Public-facing controllers (locale-prefixed)
│   │       ├── AboutController.php
│   │       ├── AccountController.php       # Dashboard, orders, addresses, profile
│   │       ├── CartController.php          # Cart API: add/remove/content (JSON responses)
│   │       ├── WishlistController.php     # Wishlist: toggle (AJAX) + account page
│   │       ├── CatalogController.php        # Shop listing: ?stones=slug1,slug2 (CSV) or ?stones=none for "without stone"
│   │       ├── CheckoutController.php      # 3-step tunnel: identify → checkout → payment + tracking
│   │       ├── ContactController.php       # Contact form with honeypot + rate limiter
│   │       ├── CurrencyController.php      # POST /currency/switch — changes active currency
│   │       ├── HomeController.php
│   │       ├── InvoiceController.php       # PDF invoice download (token-verified, locale-aware)
│   │       ├── LegalController.php         # Legal notice + terms of sale
│   │       ├── ProductController.php
│   │       ├── StoneGuideController.php    # Stone guide: index + detail pages
│   │       ├── ResetPasswordController.php # Forgot password + reset flow
│   │       ├── SecurityController.php      # Customer login, register (OTP), logout
│   │       └── TestimonialController.php   # Testimonial listing + customer submission
│   ├── Entity/
│   ├── Enum/
│   │   ├── AdminRole.php         # SuperAdmin / Admin
│   │   ├── ContactSubject.php    # General / Order / Return / Collaboration / Other
│   │   ├── DiscountType.php      # Percentage / FixedAmount
│   │   ├── OrderStatus.php       # Pending / Processing / Shipped / Delivered / Cancelled
│   │   ├── PromotionType.php     # ProductAutomatic / CartAutomatic / CartCode
│   │   ├── ShippingTier.php      # Standard / Heavy / Set
│   │   └── TestimonialStatus.php # Pending / Approved / Rejected
│   ├── Repository/
│   ├── Service/
│   │   ├── CartManager.php          # Hybrid cart: session+cookie (guests) / DB (customers)
│   │   ├── WishlistManager.php      # Wishlist: toggle, visibility filter, sold-item cleanup
│   │   ├── ContactMailer.php        # Contact form notification (plain text to admins, reply-to sender)
│   │   ├── CurrencyConverter.php    # Exchange rates from open.er-api.com, cached 6h
│   │   ├── ImageProcessor.php       # Resizes and converts images to WebP (GD driver)
│   │   ├── InstagramFeedService.php # Instagram feed via Behold.so API, cached 6h
│   │   ├── InvoiceGenerator.php      # PDF invoice generation (dompdf, bilingual, logo + legal footer)
│   │   ├── OrderMailer.php          # Order emails (confirmation, shipped, delivered, cancelled, admin)
│   │   ├── PendingOrderVerifier.php # Verifies pending orders against Stripe API
│   │   ├── PromotionEngine.php      # Promotion calculation: product/cart promos, coupon validation, usage tracking
│   │   ├── ReservationManager.php   # Product reservation: reserve, release, expiry check (15 min)
│   │   ├── ShippingCostProvider.php # Shipping cost resolution (DB settings → enum fallback)
│   │   ├── StripeService.php        # PaymentIntent creation & retrieval
│   │   ├── TestimonialMailer.php    # J+14 testimonial request emails (scheduler, deduplication)
│   │   ├── TurnstileVerifier.php    # Cloudflare Turnstile CAPTCHA verification
│   │   └── AbandonedOrderCleaner.php # Cleans up stale pending orders (scheduler)
│   ├── Twig/
│   │   ├── CurrencyExtension.php         # |price filter — formats amount in selected currency
│   │   ├── LocaleProductExtension.php    # |localized_name, |localized_description, |localized_slug
│   │   ├── PromotionExtension.php        # product_promo(), product_promo_price(), product_compare_at_price()
│   │   ├── ShippingExtension.php         # Shipping-related Twig helpers (promo-aware display_price)
│   │   ├── WishlistExtension.php        # wishlist_product_ids(), wishlist_count()
│   │   ├── TrackingExtension.php         # tracking_url() — generates 17track URL from tracking number
│   │   ├── TurnstileExtension.php        # Turnstile CAPTCHA Twig helpers (site key, enabled flag)
│   │   ├── AdminExtension.php            # Admin-specific Twig globals (pending counts, etc.)
│   │   └── CategoryNavExtension.php      # Category navigation tree for catalog sidebar
│   ├── Security/
│   │   ├── AdminAuthenticationEntryPoint.php     # Redirects unauthenticated to /admin/login
│   │   └── CustomerAuthenticationEntryPoint.php  # Redirects unauthenticated to /{locale}/login
│   ├── EventSubscriber/
│   │   ├── AdminLoginSubscriber.php      # Updates lastLoggedInAt on login (invalidates link)
│   │   ├── CartCookieSubscriber.php      # Applies pending cart cookies to HTTP response (guest persistence)
│   │   ├── CartMergeSubscriber.php       # Merges guest cart into customer DB cart on login
│   │   ├── CurrencySubscriber.php        # Persists currency in session + cookie (30 days)
│   │   ├── EasyAdminFlashSubscriber.php  # Adds flash messages on CRUD persist/update/delete
│   │   ├── ImageUploadSubscriber.php     # Processes product images after EasyAdmin persist/update
│   │   ├── LocaleSubscriber.php          # Persists locale in session + cookie (30 days)
│   │   ├── MaintenanceModeSubscriber.php # Blocks public requests when maintenance mode is enabled (IP whitelist bypass)
│   │   ├── OrderStatusSubscriber.php     # Handles status changes: admin email, shipped/delivered/cancelled to customer
│   │   └── SecurityHeadersSubscriber.php # Adds security headers (CSP, X-Frame-Options, HSTS, etc.) to all responses
│   ├── Message/
│   │   ├── CleanExpiredReservationsMessage.php
│   │   ├── CleanAbandonedOrdersMessage.php
│   │   ├── SendTestimonialRequestsMessage.php
│   │   └── VerifyPendingOrdersMessage.php
│   ├── MessageHandler/
│   │   ├── CleanExpiredReservationsHandler.php
│   │   ├── CleanAbandonedOrdersHandler.php
│   │   ├── SendTestimonialRequestsHandler.php
│   │   └── VerifyPendingOrdersHandler.php
│   ├── Command/
│   │   ├── CleanExpiredReservationsCommand.php  # CLI: app:clean-expired-reservations
│   │   ├── CleanAbandonedOrdersCommand.php      # CLI: app:clean-abandoned-orders
│   │   ├── ImportCatalogueImagesCommand.php     # CLI: app:import-catalogue-images (SourcePhoto via Flysystem)
│   │   ├── ImportStoneImagesCommand.php         # CLI: app:import-stone-images
│   │   ├── SendTestimonialRequestsCommand.php   # CLI: app:send-testimonial-requests
│   │   └── VerifyPendingOrdersCommand.php       # CLI: app:verify-pending-orders
│   └── Schedule.php                        # Symfony Scheduler provider (default)
├── templates/
│   ├── admin/
│   │   ├── dashboard.html.twig
│   │   ├── login.html.twig              # Magic link login page (brand-styled)
│   │   └── order/
│   │       └── edit.html.twig           # Custom order edit: info panels + payment/invoice/dates
│   ├── email/
│   │   ├── admin_login_link.html.twig    # Magic link email template
│   │   ├── admin_new_order.html.twig     # New order notification (FR only, to admin)
│   │   ├── order_cancelled.html.twig     # Bilingual cancellation notification
│   │   ├── order_confirmation.html.twig  # Bilingual order confirmation email
│   │   ├── order_delivered.html.twig     # Bilingual delivery notification + care instructions
│   │   ├── order_shipped.html.twig       # Bilingual shipped notification with tracking
│   │   ├── registration_otp.html.twig    # OTP verification code for registration
│   │   ├── reset_password.html.twig      # Bilingual password reset email
│   │   ├── testimonial_request.html.twig # J+14 testimonial request (bilingual, unique token link)
│   │   └── admin_new_testimonial.html.twig # Admin notification when testimonial submitted
│   ├── pdf/
│   │   └── invoice.html.twig           # Invoice PDF template (bilingual, logo, legal footer)
│   └── shop/
│       ├── base.html.twig
│       ├── home/
│       ├── catalog/             # index + _grid_items.html.twig partial (also served alone for AJAX "load more")
│       ├── product/
│       ├── about/
│       ├── contact/
│       ├── cart/
│       ├── checkout/            # Identify, checkout form, payment, confirmation, tracking
│       ├── legal/               # Legal notice, terms of sale
│       ├── stone/               # Stone guide: index grid + detail page
│       ├── testimonial/         # Testimonial listing, submission form, thank you
│       ├── security/            # Login, register, OTP verification, forgot/reset password
│       └── account/             # Dashboard, orders, addresses, profile (authenticated)
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
    private string $slugFr;                  // French URL slug
    private string $description;             // English
    private string $descriptionFr;           // French (with accents)
    private float $basePrice;               // EUR — internal, never displayed raw
    private ?float $compareAtPrice;         // EUR — original price for discount display (nullable)
    private ShippingTier $shippingTier;     // Determines shipping cost baked in
    private ProductCategory $category;
    private bool $isPublished;
    private bool $isFeatured;
    private bool $isSoldOut;                // Pièce unique — replaces integer stock
    private ?array $availableIn;            // JSON — countries where piece is available ['france','mexico']
    private ?\DateTimeImmutable $soldAt;    // Set when isSoldOut toggled to true
    private ?string $thumbnail;               // VichUploader — card/catalog image (4:5, 600×750)
    private ?string $wornPhoto;                // VichUploader — worn photo, hero on detail page (4:5, 800×1000)
    private ?string $contextPhoto;             // VichUploader — lifestyle/context photo (4:5, 800×1000)
    private Collection $relatedProducts;     // "Wear it with" — ManyToMany self-ref
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    // Constants
    const COUNTRY_FRANCE = 'france';
    const COUNTRY_MEXICO = 'mexico';

    // Computed — never stored
    public function getDisplayPrice(): float
    {
        return $this->basePrice + $this->shippingTier->shippingCostEur();
    }

    public function getDiscountPercent(): ?int  // null if no compare-at price
    public function isNew(): bool               // true if created < 14 days ago
    public function isVisibleInCatalog(): bool  // published + (not sold or sold < 14 days)
}
```

### ShippingTier enum

```php
// src/Enum/ShippingTier.php
enum ShippingTier: string
{
    case Standard = 'standard'; // Small pieces <100g  — rings, studs, fine chains   → +10€
    case Heavy    = 'heavy';    // Statement pieces 100-350g — large necklaces, cuffs → +15€
    case Set      = 'set';      // Full sets / gift boxes 350g-1kg                    → +20€

    public function label(): string
    {
        return match($this) {
            self::Standard => 'Pièce légère',   // accent required
            self::Heavy    => 'Pièce forte',    // accent required
            self::Set      => 'Set / Coffret',
        };
    }

    public function shippingCostEur(): float
    {
        return match($this) {
            self::Standard => 10.00,
            self::Heavy    => 15.00,
            self::Set      => 20.00,
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
> update `shippingCostEur()` only — all display prices recalculate automatically.
> No database migration needed.

### Order

```php
class Order
{
    private int $id;
    private string $reference;                  // e.g. ASP-2026-00042 — sequential per year
    private OrderStatus $status;                // Pending / Processing / Shipped / Delivered / Cancelled
    private ?Customer $customer;                // Nullable — guest orders have no customer
    private string $customerEmail;
    private string $customerName;
    private ?string $customerLocale;            // 'en' or 'fr' — for bilingual emails

    // Shipping address
    private ?string $shippingRecipientName;     // Can differ from customerName
    private string $shippingAddressLine1;
    private ?string $shippingAddressLine2;
    private string $shippingCity;
    private ?string $shippingState;
    private string $shippingPostalCode;
    private string $shippingCountry;            // ISO 3166-1 alpha-2

    // Billing address (optional — only if different from shipping)
    private ?string $billingRecipientName;
    private ?string $billingAddressLine1;
    private ?string $billingAddressLine2;
    private ?string $billingCity;
    private ?string $billingState;
    private ?string $billingPostalCode;
    private ?string $billingCountry;

    private string $originCountry;              // 'FR' or 'MX' — set by Estelle at dispatch
    private float $totalEur;
    private string $stripePaymentIntentId;      // Stripe PaymentIntent ID
    private ?string $stripePaymentStatus;       // Tracks Stripe PI status
    private ?string $trackingNumber;
    private ?string $internalNotes;             // Admin-only notes
    private ?string $invoiceNumber;             // e.g. FA-2026-00001 — sequential, assigned on payment confirmation
    private string $invoiceToken;               // UUID v4 — generated at order creation, used for invoice download link
    private ?\DateTimeImmutable $paidAt;        // Set when Stripe payment confirmed (used as invoice date)
    private Collection $items;                  // OrderItem
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public function hasSeparateBillingAddress(): bool  // true if billing fields populated
    public function getFullBillingAddress(): string    // falls back to shipping if no billing
    public function getFullShippingAddress(): string
}
```

> **Numbering:** Order references (`ASP-YYYY-XXXXX`) and invoice numbers
> (`FA-YYYY-XXXXX`) are both sequential, gap-free, generated per year via
> `OrderRepository`. Invoice numbers are assigned only on payment confirmation
> (French legislation: art. 242 nonies A du CGI). Reference is assigned at
> order creation, invoice number at payment.
```

### Admin

```php
// src/Entity/Admin.php
class Admin implements UserInterface
{
    private int $id;
    private string $email;              // Unique, used as user identifier
    private array $roles;               // ROLE_ADMIN always included, ROLE_SUPER_ADMIN for super admins
    private AdminRole $role;            // SuperAdmin or Admin (enum)
    private bool $receivesAdminEmails;  // Whether to receive admin notifications (new orders, etc.)
    private ?\DateTimeImmutable $lastLoggedInAt;  // Updated on login, invalidates old links
    private \DateTimeImmutable $createdAt;
}
```

> **Authentication:** Passwordless via Symfony Login Link. No password stored.
> Link is signed (HMAC) using `email` + `lastLoggedInAt`, expires in 10 minutes,
> single-use (lastLoggedInAt changes on login, invalidating old links).
>
> **Roles:** `AdminRole` enum — `SuperAdmin` (full management access) vs `Admin`
> (read-only access to admin list). Super Admin accounts cannot be deleted.
> `receivesAdminEmails` controls who gets notified on new orders.

### Customer

```php
// src/Entity/Customer.php
class Customer implements UserInterface, PasswordAuthenticatedUserInterface
{
    private int $id;
    private string $email;              // Unique, user identifier
    private string $password;           // Hashed (bcrypt/argon2)
    private string $firstName;
    private string $lastName;
    private array $roles;               // ROLE_CUSTOMER always included
    private Collection $addresses;      // OneToMany → CustomerAddress
    private Collection $orders;         // OneToMany → Order
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;
}
```

> **Authentication:** Classic email + password via Symfony `form_login` on the
> `main` firewall. Separate from admin's Magic Link auth. Remember-me cookie
> (30 days). Password reset via `symfonycasts/reset-password-bundle` (1h expiry).
>
> **Guest checkout preserved:** `Order.customer` FK is nullable. Guest orders
> have no linked customer. When a customer creates an account, past guest orders
> matching the same email are automatically linked.

### CustomerAddress

```php
// src/Entity/CustomerAddress.php
class CustomerAddress
{
    private int $id;
    private Customer $customer;         // ManyToOne, CASCADE delete
    private string $label;              // "Home", "Office", etc.
    private ?string $recipientName;     // Allows "ship to different name"
    private string $addressLine1;
    private ?string $addressLine2;
    private string $city;
    private ?string $state;
    private string $postalCode;
    private string $country;            // ISO 3166-1 alpha-2
    private bool $isDefault;
}
```

### Cart

```php
// src/Entity/Cart.php
class Cart
{
    private int $id;
    private Customer $customer;            // OneToOne — each customer has one persistent cart
    private Collection $items;             // OneToMany → CartItem
    private \DateTimeImmutable $updatedAt;

    public function addProduct(Product $product): void
    public function removeProduct(Product $product): void
    public function containsProduct(Product $product): bool
    public function getProductIds(): array
    public function clear(): void
}
```

### CartItem

```php
// src/Entity/CartItem.php
class CartItem
{
    private int $id;
    private Cart $cart;                    // ManyToOne
    private Product $product;              // ManyToOne
    private \DateTimeImmutable $addedAt;
}
```

> **Hybrid cart system:** Guests use session + cookie (`alma_cart`, 30-day expiry,
> JSON-encoded product IDs). Logged-in customers use a database-backed `Cart` entity.
> On login, `CartMergeSubscriber` merges the guest session cart into the customer's
> DB cart automatically.

### WishlistItem

```php
// src/Entity/WishlistItem.php
class WishlistItem
{
    private int $id;
    private Customer $customer;              // ManyToOne
    private Product $product;                // ManyToOne
    private \DateTimeImmutable $addedAt;
}
```

> **Wishlist:** Only available to logged-in customers. Items are filtered by
> `isSoldOut = false AND isPublished = true` — sold or unpublished products
> disappear automatically. A flash message notifies the customer when items
> have been sold between visits.

### ShippingSettings

```php
// src/Entity/ShippingSettings.php
class ShippingSettings
{
    private int $id;
    private ShippingTier $tier;            // Standard / Heavy / Set
    private string $label;
    private float $shippingCostEur;
    private int $maxWeightGrams;

    public static function createFromTier(ShippingTier $tier): self
}
```

> **Purpose:** Allows admin to override shipping costs from EasyAdmin instead of
> editing the `ShippingTier` enum directly. `ShippingCostProvider` checks DB settings
> first, falls back to the enum defaults.

### SiteSettings

```php
// src/Entity/SiteSettings.php
class SiteSettings
{
    private int $id;
    private string $activeCollection = 'all';  // 'all' | 'france' | 'mexico'
    private bool $isMaintenanceMode = false;
    private ?string $maintenanceMessage = null;
    private array $maintenanceAllowedIps = [];  // JSON list of IP addresses

    const COLLECTION_ALL = 'all';
    const COLLECTION_FRANCE = 'france';
    const COLLECTION_MEXICO = 'mexico';
}
```

> **Purpose:** Singleton entity — controls which products are visible on the storefront
> based on Estelle's current location (France or Mexico collection, or all).
> Also controls maintenance mode: toggle, custom message, and IP whitelist.

### ContactMessage

```php
// src/Entity/ContactMessage.php
class ContactMessage
{
    private int $id;
    private string $name;
    private string $email;
    private ContactSubject $subject;  // Enum: General, Order, Return, Collaboration, Other
    private string $message;
    private bool $isRead;
    private \DateTimeImmutable $createdAt;
}
```

> **Anti-spam:** Honeypot field (hidden `website` input, rejected if filled)
> + Symfony RateLimiter (3 submissions / 15 min / IP).
> **Admin notification:** Plain text email with Reply-To set to sender's email
> for easy direct response.

### Reservation

```php
// src/Entity/Reservation.php
class Reservation
{
    private int $id;
    private Product $product;              // OneToOne — one reservation per product
    private string $sessionId;             // PHP session ID of the reserving visitor
    private \DateTimeImmutable $expiresAt; // now + 15 minutes
    private \DateTimeImmutable $createdAt;

    public static function create(Product $product, string $sessionId, int $durationMinutes = 15): self
    public function isExpired(): bool
    public function isOwnedBy(string $sessionId): bool
    public function getRemainingSeconds(): int
}
```

> **Purpose:** Prevents double-selling of unique pieces. A product is reserved
> when the customer enters checkout (identification step). Reservation lasts 15
> minutes. Other visitors see "Réservé" / "Reserved" on the product and cannot
> add it to their cart. Expired reservations are cleaned up lazily (on each
> product access) and in batch (scheduler every 5 minutes).

### Other entities (summary)

| Entity | Purpose |
|---|---|
| `ProductCategory` | Two-level hierarchy: 5 root categories (Rings, Earrings, Bracelets, Necklaces, Sets) with 32 subcategories from the official catalogue. Self-referencing `parent` ManyToOne. Products attach to leaf categories only. |
| ~~`ProductImage`~~ | **Removed** — replaced by 3 VichUploader fields on `Product`: `thumbnail`, `wornPhoto`, `contextPhoto` |
| `OrderItem` | Snapshot of product + price + bilingual name at order time |
| `Admin` | Admin users — passwordless auth via magic link |
| `Customer` | Customer accounts — email + password auth, order history, saved addresses |
| `CustomerAddress` | Customer shipping addresses with default flag + optional recipient name |
| `Cart` | Persistent cart for logged-in customers (OneToOne with Customer) |
| `CartItem` | Individual cart item (ManyToOne to Cart + Product) |
| `WishlistItem` | Customer wishlist item (ManyToOne to Customer + Product, unique constraint) |
| `Reservation` | Temporary product lock during checkout (15 min, OneToOne with Product) |
| `ContactMessage` | Contact form submissions — name, email, subject, message, read flag |
| `ResetPasswordRequest` | Token storage for password reset flow (symfonycasts bundle) |
| `ShippingSettings` | Admin-editable shipping tier costs (overrides enum defaults) |
| `SiteSettings` | Singleton — active collection filter + maintenance mode (toggle, message, IP whitelist) |
| `Promotion` | Promotions & coupons — product auto, cart auto, code, private link |
| `PromotionUsage` | Tracks promotion usage per order (discount amount, email, timestamp) |
| `Stone` | Natural stone guide — bilingual content, virtues, origin, ManyToMany with Product |
| `CategoryVisualPrompt` | AI visual generation prompts per category × visual type (vignette/worn/lifestyle), versioned, editable in EasyAdmin |
| `SourcePhoto` | Raw smartphone photos uploaded for AI generation, stored in Flysystem (ManyToOne → Product) |
| `GeneratedVisual` | AI-generated visuals with approval workflow (generating → pending_review → approved/rejected/failed) |
| `GeminiUsageLog` | Tracks Gemini API costs per call (with `operation` enum: `visual` for M16 / `text_fill` for M17) for monthly budget enforcement |
| `ProductContentSuggestion` | AI-generated product copy (FR + EN, name + description) with review workflow (generating → pending → applied/rejected). Captures `contextSnapshot` (category, stones at call time) for traceability — Milestone 17 |

---

### Stone

```php
// src/Entity/Stone.php
class Stone
{
    private int $id;
    private string $name;                    // EN name
    private string $nameFr;                  // FR name
    private string $slug;                    // Auto-generated from name (unique)
    private string $slugFr;                  // Auto-generated from nameFr (unique)
    private string $shortDescription;        // Short hook EN (badges, tooltips)
    private string $shortDescriptionFr;      // Short hook FR
    private string $description;             // Full description EN
    private string $descriptionFr;           // Full description FR
    private ?string $funFact;                // "Did you know?" EN
    private ?string $funFactFr;              // "Le saviez-vous ?" FR
    private ?string $traditions;             // Cultural traditions EN
    private ?string $traditionsFr;           // Cultural traditions FR
    private string $virtues;                 // Emotional/spiritual virtues EN
    private string $virtuesFr;               // Emotional/spiritual virtues FR
    private ?string $chakra;                 // Associated chakra(s)
    private string $color;                   // Dominant color
    private ?string $lustre;                 // Stone lustre
    private ?string $origin;                 // Geographic origin
    private ?string $care;                   // Care instructions EN
    private ?string $careFr;                 // Care instructions FR
    private ?string $imageName;              // VichUploader (stone_images mapping)
    private int $position;                   // Display order
    private Collection<Product> $products;   // ManyToMany (inversedBy, owning side = Product)
}
```

> **Purpose:** Each stone has a dedicated page with origin, traditions, virtues,
> and links to products. Products can have multiple stones (e.g. Duo Émeraude
> & Malachite). The stone filter in the catalog allows filtering by stone type,
> with a "Sans pierre" option for plain steel jewelry.

---

### Promotion

```php
// src/Entity/Promotion.php
class Promotion
{
    private int $id;
    private string $name;                       // Admin label
    private ?string $code;                      // Null for auto promos, e.g. "BIENVENUE10" for codes
    private PromotionType $type;                // ProductAutomatic / CartAutomatic / CartCode
    private DiscountType $discountType;         // Percentage / FixedAmount
    private float $discountValue;               // 10 = 10% or 10€
    private bool $isActive;
    private bool $isCumulable;                  // Can stack with other promos
    private bool $overridesCompareAtPrice;      // If false, skip products with existing compareAtPrice
    private ?\DateTimeImmutable $startsAt;
    private ?\DateTimeImmutable $endsAt;
    private ?int $maxUsages;                    // Total usage limit
    private ?int $maxUsagesPerEmail;            // Per-customer limit
    private ?float $minimumAmountEur;           // Minimum cart amount
    private Collection $products;               // ManyToMany → Product (targeted)
    private Collection $categories;             // ManyToMany → ProductCategory (targeted)
    private Collection $usages;                 // OneToMany → PromotionUsage
    private int $usageCount;                    // Auto-incremented counter
    private float $revenueGeneratedEur;         // Revenue tracked
    private ?\DateTimeImmutable $lastUsedAt;

    public function isCurrentlyValid(): bool    // Active + within date range
    public function hasReachedMaxUsages(): bool
    public function appliesToProduct(Product $product): bool  // Empty lists = applies to all
    public function canApplyToProductWithCompareAtPrice(Product $product): bool
    public function calculateDiscount(float $price): float
    public function getDiscountLabel(): string  // e.g. "-10%" or "-5,00 €"
}
```

> **Promotion types:**
> - `ProductAutomatic`: applies to individual products, generates dynamic strikethrough prices
> - `CartAutomatic`: applies to cart subtotal when conditions are met, no code required
> - `CartCode`: requires customer to enter a code at checkout step 2
>
> **Cumul logic:** `isCumulable = false` → best single offer wins. `isCumulable = true` → stacks.
>
> **CompareAtPrice interaction:** if `overridesCompareAtPrice = false`, the promo does not apply
> to products that already have a manual `compareAtPrice` set.

### Testimonial

```php
// src/Entity/Testimonial.php
class Testimonial
{
    private int $id;
    private string $email;                    // Customer email (from order)
    private string $token;                    // UUID v4 for submission form URL
    private ?int $rating;                     // 1-5 (filled on submission)
    private ?string $text;                    // Testimonial body (filled on submission)
    private ?string $firstName;               // e.g. "Sarah"
    private ?string $lastNameInitial;         // e.g. "J"
    private ?string $city;                    // e.g. "Portland, OR"
    private TestimonialStatus $status;        // Pending / Approved / Rejected
    private ?Order $relatedOrder;             // ManyToOne (nullable, ON DELETE SET NULL)
    private \DateTimeImmutable $createdAt;    // When email was sent
    private ?\DateTimeImmutable $submittedAt; // When customer submitted (null = not yet)
}
```

> **Brand-level testimonials**, not product reviews. Since every piece is unique
> and sold once, product reviews make no sense. Testimonials capture the overall
> Alma Stella experience (quality, packaging, delivery, etc.).
>
> **Flow:** J+14 after payment → shell Testimonial created (email + token) →
> email sent with unique link → customer fills form → status=Pending →
> admin moderates in EasyAdmin → Approved testimonials appear on homepage + `/testimonials`.
>
> **Deduplication:** one testimonial per email address. If a Testimonial already
> exists for that email, the scheduler skips sending.

---

## Services

### InstagramFeedService

- Fetches Instagram feed from Behold.so API (`https://feeds.behold.so/{feedId}`)
- Cached 6 hours via Symfony Cache (same pattern as `CurrencyConverter`)
- Returns up to 6 latest posts: image URL (medium size via Behold CDN), permalink, caption
- Graceful fallback: returns empty array if API unavailable or Feed ID not configured
- No external JavaScript — server-side fetch only, rendered in Twig
- Images served via Behold CDN (`behold.pictures`) with lazy loading
- Config: `BEHOLD_FEED_ID` env var (injected via `services.yaml` bind)

### CurrencyConverter

- Fetches rates from `https://open.er-api.com/v6/latest/EUR` (free, no key)
- Cached 6 hours via Symfony Cache (Redis in production, filesystem in dev)
- Supported currencies: `EUR`, `USD`, `CAD`, `GBP`, `MXN`
- Falls back to EUR silently if the external API is unavailable
- Formats output using PHP `NumberFormatter` with correct locale per currency

### CartManager

- **Hybrid cart system:** guests use session + cookie, customers use DB (`Cart` entity)
- Guest: products stored in session (`_cart` key) + cookie (`alma_cart`, 30-day expiry)
- Customer: products stored in `Cart` + `CartItem` entities (database)
- Automatically detects auth state and routes to correct storage
- `mergeGuestCartIntoCustomer()` — called by `CartMergeSubscriber` on login
- **Reservation-aware:** `add()` rejects products reserved by other sessions;
  `getProducts()` filters them out and syncs the cart

### ReservationManager

- Manages temporary product holds during checkout (prevents double-selling)
- `reserve(Product)` — creates a 15-minute reservation for the current session
- `release(Product)` — removes reservation (called after successful payment)
- `isReservedByOther(Product)` — lazy check with auto-expiry cleanup
- `getReservedProductIdsByOthers()` — used by catalog/product pages for badge display
- `getRemainingSeconds()` — feeds the countdown timer in checkout templates
- `releaseExpired()` — batch cleanup called by `CleanExpiredReservationsHandler` (scheduler)
- Reservations are created at checkout entry (identification step) and released
  on successful payment or automatic expiry

### ShippingCostProvider

- Resolves shipping cost for a `ShippingTier`: checks `ShippingSettings` in DB first
- Falls back to `ShippingTier::shippingCostEur()` enum default if no DB override
- Used by `Product::getDisplayPrice()` and checkout total calculation

### ImageProcessor

- Resizes and converts images to WebP (quality 85%)
- Uses Intervention Image v4 with GD driver
- Called by `ImageUploadSubscriber` after EasyAdmin persist/update events

### ImageUploadSubscriber

- Listens to `AfterEntityPersistedEvent` and `AfterEntityUpdatedEvent`
- Processes Product images: thumbnail (600×750), wornPhoto (800×1000), contextPhoto (800×1000)
- Converts non-WebP uploads to WebP, resizes to max dimensions

### StripeService

- Creates Stripe PaymentIntents server-side (amount in cents, EUR)
- Retrieves existing PaymentIntents for verification
- Uses `StripeClient` (SDK v20) with secret key from env
- Automatic payment methods enabled (card, Apple Pay, Google Pay)
- Order reference stored in PaymentIntent metadata
- **No webhooks** — payment verification via 3 layers:
  1. Immediate: Stimulus controller calls `POST /payment/confirm` after payment
  2. On-return: payment page detects 3DS redirect return and auto-confirms
  3. Scheduler: `VerifyPendingOrdersMessage` runs every 5 min via Symfony Scheduler
  4. Scheduler: `CleanExpiredReservationsMessage` runs every 5 min (releases expired holds)
  5. Scheduler: `SendTestimonialRequestsMessage` runs every 6 hours (J+14 testimonial emails)
  6. Scheduler: `CleanAbandonedOrdersMessage` runs periodically (cleans stale pending orders)

### SocialPublisher *(planned — Milestone 12, not yet implemented)*

Three channels, three integration levels:

| Channel | Integration | Trigger |
|---|---|---|
| Pinterest | Full API — creates Pin automatically | EasyAdmin action button |
| TikTok Shop | Full API — creates/updates product in catalog | EasyAdmin action button |
| Instagram | Deep link — opens mobile app with pre-filled caption | EasyAdmin generates link |

### PromptBuilder (AI Visual Generation)

- Composes full Gemini prompts from multiple fragments: product metadata, brand style, preservation instructions, category framing/staging, technical specs
- Uses `CategoryVisualPromptRepository` to find the active prompt for a category × visual type
- Falls back to `PromptFallbackProvider` (in-memory `CategoryVisualPrompt`, version 0) if no prompt configured
- Returns `PromptResult` DTO: content, categoryPromptVersion, usedFallback flag
- Supporting providers: `BrandStyleProvider` (brand identity), `TechnicalSpecsProvider` (4:5 ratio, 819×1024)

### GeminiImageClient

- HTTP client for Gemini 2.5 Flash Image API (`generateContent` endpoint)
- Sends prompt text + source photos (base64) → receives generated image (base64)
- Retry with exponential backoff on HTTP 429 (2s, 4s, 8s — max 3 retries)
- Returns `GeminiResponse` DTO (imageData, mimeType, requestId)
- Throws `GeminiApiException` on definitive errors

### BudgetGuard

- Monthly spending control for Gemini API calls
- `ensureBudgetAvailable()` — throws `BudgetExceededException` if monthly limit reached
- `recordCall(float $costUsd)` — persists a `GeminiUsageLog` entry
- Budget threshold from env: `GEMINI_MONTHLY_BUDGET_USD` (default: 30)

### ImageStorage (Flysystem)

- Abstraction for source photo and generated visual storage via Flysystem
- Store structure: `{productId}/sources/` and `{productId}/generated/`
- Methods: `storeSourcePhoto`, `storeGeneratedVisual`, `read`, `delete`, `getPublicUrl`
- Local adapter: `var/storage/products/` (configurable via `IMAGE_STORAGE_PATH`)

### VisualApprovalHandler

- Copies approved AI-generated visuals from Flysystem to VichUploader (`public/uploads/products/`)
- Processes images via `ImageProcessor` (WebP conversion, crop to 4:5 ratio)
- Maps `VisualType` to Product fields: Vignette → `thumbnail`, Worn → `wornPhoto`, Lifestyle → `contextPhoto`
- Called from `GeneratedVisualCrudController::approveVisual()` action

### GenerateVisualHandler (Messenger)

- Async message handler for `GenerateVisualMessage` (dispatched via `gemini_async` transport)
- Pipeline: rate limit → budget check → load product + sources → build prompt → call Gemini → store image → persist `GeneratedVisual` (status: `PendingReview`)
- On failure: creates `GeneratedVisual` with status `Failed` + error message
- Checks if all 9 variants generated → transitions product to `ReadyForReview`
- Rate limited: 15 req/min via Symfony RateLimiter (`gemini_api` policy)
- Retry strategy: 2 retries, 5s initial delay, 2× multiplier, 30s max

### AI Content Filling (M17 — independent from visual pipeline)

A second Gemini-powered pipeline generates product copy (FR + EN names + descriptions) from the same source photos. It runs on a different model (`gemini-2.5-flash`), through a separate Messenger message and handler, with its own EasyAdmin tab. The two pipelines never share state beyond the budget cap and rate limiter — see [`AI_CONTENT_FILL.md`](AI_CONTENT_FILL.md) for the full design.

Key services (`src/Service/Content/`):

- `GeminiTextClient` — multimodal vision → strict JSON via `responseSchema`.
- `ContentBrandVoiceProvider` / `ContentFewShotProvider` — editorial voice + 4 textual few-shot pairs.
- `ContentPromptBuilder` — composes brand voice + few-shot + dynamic taxonomy (category, stones) + fallback strategy + optional regeneration steering, plus the JSON schema.
- `ProductContentFiller` — orchestrator: load sources, snapshot context, build prompt, call Gemini, parse, return `ContentSuggestionResult`.
- `FillProductContentHandler` — async worker. Pre-creates a `ProductContentSuggestion` (status `Generating`), calls the filler, transitions to `Pending` for human review. Reuses the shared rate limiter and `BudgetGuard` (`GeminiOperation::TextFill`).

### AbandonedOrderCleaner

- Cleans up stale pending orders (status `Pending`, older than configurable threshold)
- Triggered via Symfony Scheduler (`CleanAbandonedOrdersMessage`)
- Releases associated product reservations when cleaning

### TurnstileVerifier

- Verifies Cloudflare Turnstile CAPTCHA tokens server-side
- Used on public forms (contact, testimonial submission) for bot protection
- Disabled gracefully when `TURNSTILE_SECRET_KEY` is empty (dev mode)

### OrderMailer

Transactional emails via Symfony Mailer (Mailpit in dev, SMTP in production):

| Trigger | Template |
|---|---|
| Order confirmed | `email/order_confirmation.html.twig` — bilingual (FR/EN), order summary with items |
| Order shipped | `email/order_shipped.html.twig` — bilingual, clickable tracking link (La Poste/17track), link to tracking page |
| Order delivered | `email/order_delivered.html.twig` — bilingual, care instructions + Instagram CTA + invoice download link |
| Order cancelled | `email/order_cancelled.html.twig` — bilingual cancellation notification |
| Order → Processing (admin) | `email/admin_new_order.html.twig` — FR only, full summary + link to EasyAdmin order |
| J+14 testimonial request | `email/testimonial_request.html.twig` — bilingual, CTA to testimonial form (unique token) |
| Admin login link | `email/admin_login_link.html.twig` — magic link with 10min expiry |
| Registration OTP | `email/registration_otp.html.twig` — 6-digit verification code (10min expiry) |
| Password reset | `email/reset_password.html.twig` — bilingual reset link (1h expiry) |

- Sender: `hello@almastellaparis.com`
- Email failure does not block the payment flow (caught and logged)

### TestimonialMailer

- Scheduled via Symfony Scheduler (`SendTestimonialRequestsMessage`, every 6 hours)
- Queries delivered orders with `paidAt` between 14 and 45 days ago
- Deduplicates by email: skips if `Testimonial` already exists for that address
- Creates a shell `Testimonial` entity (email + token + order link) and sends request email
- Email template: `testimonial_request.html.twig` — bilingual, CTA button to submission form
- Submission form at `/{_locale}/testimonial/{token}` / `/{_locale}/temoignage/{token}`

### InvoiceGenerator

PDF invoice generation via dompdf (`dompdf/dompdf`):

- **Service:** `App\Service\InvoiceGenerator` — renders Twig template to PDF on the fly
- **Template:** `templates/pdf/invoice.html.twig` — A4 portrait, brand-styled, fully bilingual FR/EN
- **Route:** `GET /{_locale}/invoice/{reference}/{token}` (`shop_invoice_download`)
- **Access control:** token-based (UUID v4 stored as `invoiceToken` on Order entity, generated at order creation)
- **Available for:** orders with status `Processing`, `Shipped`, or `Delivered`
- **Access points:**
  - Delivered email: download button with localized URL
  - Customer account: download button on order detail page for eligible orders
  - Admin: download link in order edit "Paiement" panel (in customer's locale)
- **Content:**
  - Brand logo (base64-encoded PNG in header)
  - Invoice number (`FA-YYYY-XXXXX`), payment date, billing address
  - Items with localized product names and prices (shipping included in unit price)
  - Shipping: "Offerte" / "Free", TVA 0,00 €, Total (EUR)
  - Fixed page footer on every page: legal mentions (Estelle Bédé, EI, SIRET 917 539 751, address, TVA art. 293 B CGI)

---

## Security notes

- All admin routes protected by `ROLE_ADMIN`
- CSRF protection on all forms (Symfony default)
- Product images stored in `public/uploads/products/` with hashed filenames
  (SmartUniqueNamer) — unpublished products not linked in HTML
- API keys (Stripe, TikTok, Pinterest) stored in `.env.local` only —
  never committed, never hardcoded
- Rate limiting on checkout and login endpoints via Symfony RateLimiter

### Two firewalls

| Firewall | Pattern | Provider | Auth method | Purpose |
|---|---|---|---|---|
| `admin` | `^/admin` | `Admin` entity | Magic Link (login_link) | EasyAdmin access |
| `main` | everything else | `Customer` entity | Email + password (form_login) | Customer accounts |

- `ROLE_ADMIN` / `ROLE_SUPER_ADMIN` — admin panel access
- `ROLE_CUSTOMER` — customer account pages (`/account`, `/mon-compte`)
- Guest checkout remains available — no role required for cart/checkout
- Remember-me cookie: 30 days on `main` firewall
- Password reset via `symfonycasts/reset-password-bundle` (1h token, single-use)
- Registration requires OTP email verification before account creation

---

## Checkout tunnel (3 steps)

The checkout follows a 3-step funnel: **Identify → Checkout → Payment**.

### Step 1 — Identify (`/identify` | `/identification`)
- **Logged-in customer:** automatically redirected to step 2
- **Guest:** enters email address
  - `checkout_identify_controller` checks if email matches an existing account
  - If account found → prompts to log in (preserves cart)
  - If new email → proceeds as guest, email stored in session (`_checkout_email`)
- Email is **readonly** in subsequent steps (cannot be changed)
- **Reservation activated:** all cart products are reserved for 15 minutes on entry
  - `reservation_timer_controller` displays a countdown timer on all checkout pages
  - Other visitors see "Reserved" badge and cannot add reserved products to their cart

### Step 2 — Checkout (`/checkout` | `/livraison`)
- Shipping address form (pre-filled from default `CustomerAddress` if logged in)
- `address_selector_controller` — dropdown to pick from saved addresses
- Optional billing address toggle (`billing_toggle_controller`)
  - If checked: shows separate billing address fields
  - If unchecked: billing = shipping
- Order summary with items and totals

### Step 3 — Payment (Stripe Elements)
- Stripe Payment Element (card + Apple Pay + Google Pay)
- `stripe_payment_controller` handles mount and confirm
- 3D Secure redirect handling on return

### Order deduplication
- Pending orders are reused: if `_pending_order` exists in session and status is
  still `Pending`, the existing order is **updated** rather than creating a duplicate.
- Prevents orphaned orders from abandoned checkout attempts.

### Post-payment
- Order status → `Processing`, confirmation email sent
- Products marked `isSoldOut`, reservations released, cart cleared
- Confirmation page + branded tracking page (`/order/{reference}/tracking`)
- Post-purchase account creation prompt (guest only)

### Reservation system (anti-double-sell)

Since every piece is unique (`isSoldOut` boolean), a reservation mechanism
prevents two customers from purchasing the same item simultaneously.

| Aspect | Implementation |
|---|---|
| **Trigger** | Checkout entry (identification step) |
| **Duration** | 15 minutes (configurable via `Reservation::DEFAULT_DURATION_MINUTES`) |
| **Storage** | `Reservation` entity (OneToOne with `Product`, unique constraint) |
| **Cleanup** | Lazy check on each product access + scheduler batch every 5 minutes |
| **Client UX** | `reservation_timer_controller` — visible countdown (mm:ss), turns red under 2 min |
| **Expiry** | Auto-remove from cart + notification, page reloads after 3s |
| **Catalog** | "Reserved" badge on product card + product detail page |
| **Cart** | `CartManager.add()` rejects reserved products; `getProducts()` filters them |

Flow:
1. Client A enters checkout → products reserved for 15 min
2. Client B sees "Reserved" badge → cannot add to cart
3. If A pays → products marked `isSoldOut`, reservations deleted
4. If 15 min expire → reservations deleted, products available again, A's cart cleared

---

## Registration flow (OTP verification)

Registration uses a **2-step email verification** flow:

1. User fills registration form (email, password, first name, last name)
2. Server generates a **6-digit OTP code**, stores registration data in session:
   - `_registration_otp` — the code
   - `_registration_otp_expires` — `time() + 600` (10 minutes)
   - `_registration_otp_attempts` — max 5 attempts
   - `_registration_data` — form data (not yet persisted)
3. OTP email sent via `registration_otp.html.twig`
4. User enters code on `/verify-email` (`/verification-email`)
5. On valid code: `Customer` entity created, auto-login, guest orders linked by email
6. Resend available at `/verify-email/resend` (`/verification-email/renvoyer`)

> **Why OTP instead of link?** Keeps the user in the same browser tab/flow,
> especially important when registration is triggered mid-checkout.

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
