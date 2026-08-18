# Milestone 3 — Internationalization (FR/EN) — terminé

> **Archive** — checklist de milestone terminé, extraite de `ROADMAP.md`
> le 2026-08-18. L'état courant du projet est décrit par le code et
> `docs/ARCHITECTURE.md`.

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
