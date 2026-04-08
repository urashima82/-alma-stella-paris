# DESIGN.md — Alma Stella Paris

> Visual identity, content guidelines, and UI component rules.
> Claude Code must refer to `docs/design/screenshots/` for the base44 prototype
> visual reference alongside this file.

---

## Brand identity

**Alma Stella Paris** means "soul star" — a bridge between French elegance
and Mexican vibrancy. The brand voice is warm, intimate, and confident.
Never corporate, never aggressive.

### Brand taglines
- Primary: *"Wear the world."*
- Secondary: *"Curated jewelry. Sourced globally. Worn daily."*
- French variant: *"Porter le monde."*

---

## Color system

Define these as Tailwind CSS custom tokens in `tailwind.config.js`:

```javascript
// tailwind.config.js
module.exports = {
  theme: {
    extend: {
      colors: {
        'alma': {
          'bg':        '#FAF8F4',  // warm white — page background
          'surface':   '#F0EBE1',  // linen — cards, sections
          'hover':     '#E8DDD0',  // soft taupe — hover states, borders
          'gold':      '#C9A84C',  // warm gold — CTAs, accents, borders
          'gold-muted':'#C9A84C40',// gold at 25% — dividers (use CSS alpha)
          'text':      '#2C2418',  // warm near-black — all body text
          'text-muted':'#7A6A55',  // warm gray — secondary text, labels
        }
      },
      fontFamily: {
        'serif': ['"Cormorant Garamond"', 'Georgia', 'serif'],
        'sans':  ['Inter', 'system-ui', 'sans-serif'],
      }
    }
  }
}
```

### Usage rules
- Gold (`#C9A84C`) is used **only** for primary CTAs, active states, and
  thin decorative borders — never as background fill for large areas
- Never use pure black (`#000000`) — always use `alma-text` (`#2C2418`)
- Hover backgrounds use `alma-hover`, not gold

---

## Typography

| Role | Font | Weight | Size |
|---|---|---|---|
| Hero headline | Cormorant Garamond | 300 (light) | 56-72px |
| Page title | Cormorant Garamond | 400 | 36-42px |
| Section heading | Cormorant Garamond | 400 | 24-28px |
| Product name | Cormorant Garamond | 500 | 20-22px |
| Body text | Inter | 400 | 16px |
| Labels / badges | Inter | 500 | 12-13px |
| Price | Inter | 600 | 18-20px |
| Navigation | Inter | 400 | 14px |

Google Fonts import (in `base.html.twig`):
```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
```

---

## UI components

### Buttons

```html
<!-- Primary CTA — solid gold -->
<button class="bg-alma-gold text-white px-8 py-3 font-sans text-sm font-500
               hover:bg-alma-gold/90 transition-colors">
  Shop the collection
</button>

<!-- Secondary — gold outline -->
<button class="border border-alma-gold text-alma-gold px-8 py-3 font-sans text-sm
               hover:bg-alma-gold hover:text-white transition-colors">
  Discover more
</button>

<!-- Ghost — for less prominent actions -->
<button class="text-alma-text-muted underline underline-offset-4 text-sm
               hover:text-alma-text transition-colors">
  View all
</button>
```

### Product card

```html
<article class="group cursor-pointer">
  <div class="aspect-[4/5] bg-alma-surface overflow-hidden
              border border-transparent group-hover:border-alma-gold
              transition-colors duration-300">
    <img class="w-full h-full object-cover
                group-hover:scale-105 transition-transform duration-500" ...>
  </div>
  <div class="mt-3 flex justify-between items-start">
    <div>
      <h3 class="font-serif text-base text-alma-text">{{ product.name }}</h3>
      <p class="text-alma-text-muted text-xs mt-0.5">{{ product.category.name }}</p>
    </div>
    <p class="font-sans font-semibold text-alma-text">
      {{ product.displayPrice | price }}
    </p>
  </div>
</article>
```

### Material badges (product detail)

```html
<!-- French content — accents mandatory -->
<span class="inline-flex items-center gap-1.5 px-3 py-1
             bg-alma-surface text-alma-text-muted text-xs font-sans font-500
             border border-alma-hover">
  Acier inoxydable
</span>
<span ...>Pierre naturelle</span>
<span ...>Résistant à l'eau</span>
```

### Currency disclaimer (non-USD)

```html
<!-- Shown only when current_currency != 'USD' -->
<p class="text-xs text-alma-text-muted italic mt-1">
  Prices shown in {{ current_currency }} are indicative.
  You will be charged in USD at checkout.
</p>
```

---

## Content guidelines

### French copy rules
- Always use correct UTF-8 accented characters: `é è ê ë à â ù û ü î ï ô ç œ æ`
- Never substitute with unaccented versions in templates, fixtures, or seed data
- French and English copy coexist — do not mix within a single sentence

### English copy tone
- Warm, confident, editorial — never salesy
- Short sentences, active voice
- Product names in English (SEO-friendly for US/CA market)

### Bilingual pattern
```twig
{# Page headings — French main, English sub #}
<h1 class="font-serif">Porter le monde.</h1>
<p class="font-sans">Curated jewelry. Sourced globally. Worn daily.</p>

{# Product badges — French only (brand personality) #}
<span>Pièce unique</span>
<span>Livraison soignée</span>
<span>Résistant à l'eau</span>

{# UI labels — English (international usability) #}
<button>Add to cart</button>
<label>Select quantity</label>
```

---

## Sample product catalog

Reference data for fixtures and development. All prices are `basePrice` in USD
(display price = base + ShippingTier cost).

| Name | Base price | Category | Tier | Display price |
|---|---|---|---|---|
| Gold Star Pendant Necklace | $38 | Necklaces | Standard | $48 |
| Turquoise Stone Ring | $28 | Rings | Standard | $38 |
| Black Onyx Drop Earrings | $32 | Earrings | Standard | $42 |
| Layered Gold Chain Bracelet | $26 | Bracelets | Standard | $36 |
| Mother of Pearl Choker | $40 | Necklaces | Heavy | $54 |
| Lapis Lazuli Stud Earrings | $24 | Earrings | Standard | $34 |
| Hammered Gold Cuff | $52 | Bracelets | Heavy | $66 |
| Shell & Gold Anklet | $18 | Anklets | Standard | $28 |
| Moonstone Solitaire Ring | $48 | Rings | Standard | $58 |
| Coin Charm Necklace | $34 | Necklaces | Standard | $44 |
| Beaded Stone Bracelet | $22 | Bracelets | Standard | $32 |
| Pearl & Gold Hoops | $36 | Earrings | Standard | $46 |

---

## Design reference screenshots

All base44 prototype screenshots are in `docs/design/screenshots/`.
Use them as the visual target for every frontend milestone.

| File | Content |
|---|---|
| `homepage-hero.png` | Hero section, tagline, CTA |
| `catalog-grid.png` | Product grid with filter bar |
| `product-detail.png` | Full product detail layout |
| `cart-drawer.png` | Slide-in cart drawer |
| `checkout.png` | Checkout form |
| `about.png` | About page layout |

> If a screenshot is missing, ask the developer to provide it before
> implementing that section.

---

## Decorative elements

- **Moon & star motif** from the Alma Stella logo appears as:
  - Section dividers (thin gold SVG, centered)
  - Favicon
  - Order confirmation page decorative element (`✦`)
- **Dried flowers** are a recurring motif in product photography — the UI
  should leave breathing room for lifestyle images (generous padding, no busy backgrounds)
- **Thin gold dividers** (1px, `#C9A84C` at 25% opacity) between sections
  instead of heavy separators
