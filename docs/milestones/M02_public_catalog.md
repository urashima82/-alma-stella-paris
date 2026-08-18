# Milestone 2 — Public catalog (frontend) — terminé

> **Archive** — checklist de milestone terminé, extraite de `ROADMAP.md`
> le 2026-08-18. L'état courant du projet est décrit par le code et
> `docs/ARCHITECTURE.md`.

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
