# LOCALISATION.md — Alma Stella Paris

> All internationalisation and localisation decisions are documented here.
> These rules apply across every layer: PHP services, Twig templates,
> EasyAdmin, email templates, and database content.

---

## Currency system

### Core principle
**One price, many displays.** All prices are stored in EUR in the database.
Conversion is cosmetic — Stripe always charges in EUR.

```
Database:     basePrice = 35.00 (EUR, always)
              displayPrice = basePrice + shippingTier.cost = 45.00 (EUR)

Twig filter:  {{ product.displayPrice | price }}
              → EUR visitor sees: 45,00 €
              → USD visitor sees: ~$49.50 (live rate)
              → CAD visitor sees: ~CA$67.00 (live rate)
```

### Supported currencies

| Code | Label | Locale | Flag |
|---|---|---|---|
| EUR | Euro | fr_FR | 🇪🇺 |
| USD | US Dollar | en_US | 🇺🇸 |
| CAD | Canadian Dollar | fr_CA | 🇨🇦 |
| GBP | British Pound | en_GB | 🇬🇧 |
| MXN | Mexican Peso | es_MX | 🇲🇽 |

### Exchange rate source
- API: `https://open.er-api.com/v6/latest/EUR`
- Free tier, no API key required for these currencies
- Cache TTL: **6 hours** (Symfony Cache, Redis in production)
- Fallback: display EUR silently if API is unreachable

### Currency preference storage
1. Session (current visit)
2. Cookie `alma_currency`, 30-day expiry, `SameSite=Lax`
3. Default if neither: `EUR`

```php
// src/Controller/Shop/CurrencyController.php
#[Route('/currency/{code}', name: 'shop_set_currency', methods: ['POST'])]
public function set(string $code, Request $request, SessionInterface $session): Response
{
    if (in_array($code, CurrencyConverter::SUPPORTED, true)) {
        $session->set('currency', $code);

        $response = $this->redirect($request->headers->get('referer', '/'));
        $cookie = Cookie::create('alma_currency', $code, strtotime('+30 days'))
            ->withSameSite('lax')
            ->withHttpOnly(false); // Accessible to JS for display purposes

        $response->headers->setCookie($cookie);
        return $response;
    }

    return $this->redirect('/');
}
```

### Disclaimer (mandatory for non-EUR)

This message **must** appear near every price when the selected currency is not EUR:

> *"Prices shown in [USD] are indicative. You will be charged in EUR at checkout."*

Placement: below the price on product detail, in the cart drawer, and at the
top of the checkout page.

---

## Language rules

### Code language: English (mandatory)

Every identifier in PHP, Twig, JavaScript, and CSS must be in English:

```php
// ✅
private string $shippingAddress;
public function getDisplayPrice(): float
class CurrencyConverter

// ❌
private string $adresseExpedition;
public function getPrixAffiche(): float
class ConvertisseurDevise
```

### Content language: bilingual FR/EN

#### French content rules

French is used for:
- Product descriptions (`$descriptionFr`)
- Brand story (About page)
- Material badges and product labels
- Email subject lines (to French-speaking customers)
- EasyAdmin field labels (Estelle's working language)

**Accents are mandatory in all French content.** This includes:
- Twig templates
- PHP string literals
- DataFixtures
- EasyAdmin labels
- Email templates
- Database seed data

```php
// ✅ Correct accents in fixtures
$product->setDescriptionFr(
    'Pendentif étoile en acier doré, pierre naturelle sertie à la main. '
    . 'Pièce légère, idéale au quotidien. Résistant à l\'eau.'
);

// ❌ Missing accents
$product->setDescriptionFr(
    'Pendentif etoile en acier dore, pierre naturelle sertie a la main.'
);
```

#### English content rules

English is used for:
- Product names (`$name`) — optimised for US/CA SEO
- Product descriptions (`$description`)
- UI labels and navigation
- Error messages
- Log messages
- Email body content (default)

#### Bilingual entity fields

Products have parallel FR/EN fields:

| PHP field | Language | Example |
|---|---|---|
| `$name` | English | `"Gold Star Pendant Necklace"` |
| `$nameFr` | French | `"Pendentif Étoile Doré"` |
| `$description` | English | `"Delicate star pendant..."` |
| `$descriptionFr` | French | `"Délicat pendentif étoile..."` |

Display logic in Twig:
```twig
{# Show French if available and visitor locale is FR, else English #}
{% set productName = (app.request.locale starts with 'fr' and product.nameFr)
    ? product.nameFr
    : product.name %}
```

---

## Shipping origin

Estelle ships from either France or Mexico depending on her location.
This is managed in EasyAdmin per order at dispatch time.

```php
enum ShippingOrigin: string
{
    case France = 'FR';
    case Mexico = 'MX';
}
```

**This does not affect product prices or shipping tier costs** — those are
fixed regardless of origin. It affects only:
- The tracking carrier displayed to the customer (La Poste vs Estafeta)
- The estimated delivery time shown in the shipped email
- Internal reporting

### Carrier mapping

| Origin | Carrier | US delivery estimate | CA delivery estimate |
|---|---|---|---|
| France | La Poste Colissimo International | 7-14 business days | 10-16 business days |
| Mexico | Estafeta / FedEx Mexico | 4-8 business days | 6-10 business days |

---

## SEO localisation

### URL structure
All public URLs are in English:
```
/shop                          → catalog
/shop/gold-star-pendant-necklace → product detail
/about                         → about page
```

### Meta tags
```twig
{# Product detail — English for SEO, targeting US/CA market #}
<title>{{ product.name }} — Alma Stella Paris</title>
<meta name="description" content="
    {{ product.description | slice(0, 155) }} — Free worldwide shipping.
">

{# Open Graph — English #}
<meta property="og:title" content="{{ product.name }} — Alma Stella Paris">
<meta property="og:description" content="{{ product.description | slice(0, 200) }}">
<meta property="og:image" content="{{ product.mainImageUrl }}">
```

### Schema.org Product markup
```twig
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Product",
    "name": "{{ product.name }}",
    "description": "{{ product.description }}",
    "image": "{{ product.mainImageUrl }}",
    "offers": {
        "@type": "Offer",
        "price": "{{ product.displayPrice }}",
        "priceCurrency": "EUR",
        "availability": "{{ product.stock > 0
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock' }}"
    }
}
</script>
```

---

## Email localisation

All transactional emails default to English (international customer base).
The brand signature always includes the French tagline for personality:

```
---
Alma Stella Paris
"Porter le monde." ✦
https://almastellaparis.com
@alma_stella_paris
```

Subject line examples (English, warm tone):
- Order confirmed: `Your Alma Stella order is confirmed ✦`
- Shipped: `Your order is on its way! 🌍`
- Abandoned cart: `You left something beautiful behind...`
- Review request: `How are you wearing your Alma Stella piece?`
- Back in stock: `Great news — it's back ✦`
