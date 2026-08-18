# Milestone 14 — Two-level category hierarchy — terminé

> **Archive** — checklist de milestone terminé, extraite de `ROADMAP.md`
> le 2026-08-18. L'état courant du projet est décrit par le code et
> `docs/ARCHITECTURE.md`.

*Estimated effort: 6-8h*

> **Categories become hierarchical (parent → subcategory) with mixed mode.**
> A product is attached to a **leaf category** — either a subcategory (level 2)
> or a parent that has no children (e.g. Coffrets, Chaînes de cheville).
> Products cannot be attached to a parent that has children.
> URLs: `/shop/{parentSlug}/{childSlug}` for hierarchical categories,
> `/shop/{slug}` for single-level categories.

### Category structure

```
Colliers (Necklaces)
  ├─ Pendentifs (Pendants)
  ├─ Ras-de-cou (Chokers)
  └─ Sautoirs & Chaînes (Long & Chain)

Boucles d'oreilles (Earrings)
  ├─ Créoles (Hoops)
  ├─ Puces (Studs)
  └─ Pendantes (Drops)

Bracelets (Bracelets)
  ├─ Chaînes (Chain)
  ├─ Manchettes (Cuffs)
  └─ Perles & Pierres (Beaded)

Bagues (Rings)
  ├─ Pierres (Stone)
  └─ Simples & Empilables (Plain)

Chaînes de cheville (Anklets)      ← no subcategories (leaf parent)

Coffrets (Sets)                    ← no subcategories (leaf parent)
```

### 14a — Entity & migration
- [x] Add self-referencing `parent` (ManyToOne, nullable) and `children` (OneToMany) to `ProductCategory`
- [x] Add validation: max 2 levels (a parent cannot have a parent)
- [x] Add validation: products can only be attached to leaf categories (subcategory OR childless parent)
- [x] Modify initial migration to add `parent_id` column with foreign key
- [x] Recreate DDEV environment and verify schema

### 14b — EasyAdmin category management
- [x] Category list: indented tree view (subcategories shown with `↳` prefix under parent)
- [x] Drag & drop reordering (handle `☰`) to change position and parent assignment
- [x] Category form: parent selector (dropdown showing only root categories)
- [x] Category form: prevent selecting a parent that already has a parent (enforce 2 levels max)
- [x] Position ordering works within each level (siblings sorted by position)
- [x] Product count column aggregates subcategories for parent rows

### 14c — EasyAdmin product form
- [x] Product category field: show leaf categories only (subcategories + childless parents), grouped by parent
- [x] Category filter in product list: adapted for hierarchy

### 14d — Catalog controller & routing
- [x] Route: `/shop/{parentSlug}/{childSlug}` (EN) / `/boutique/{parentSlug}/{childSlug}` (FR) — subcategory filter
- [x] Route: `/shop/{parentSlug}` (EN) / `/boutique/{parentSlug}` (FR) — all products of a parent (or direct for childless parents like Coffrets, Chaînes de cheville)
- [x] Keep `/shop` / `/boutique` showing all products (no filter)
- [x] 404 if parent slug or child slug not found
- [x] Breadcrumb structured data: 2-level for hierarchical, 1-level for childless parents

### 14e — Shop sidebar (category filters)
- [x] Replace horizontal category bar with vertical sidebar (desktop: left column)
- [x] Sidebar: collapsible sections per parent category (with children)
- [x] Childless parents (Coffrets, Chaînes de cheville) shown as simple links, no collapse
- [x] Active parent auto-expanded, others collapsed by default
- [x] Active subcategory highlighted
- [x] Product count per subcategory displayed (and per childless parent)
- [x] Responsive mobile: "Filtrer" button opens a drawer (slide-in panel from left)
- [x] Drawer contains same collapsible category tree + "Fermer" button
- [x] Background overlay (grisé) when drawer is open

### 14f — Navbar
- [x] Desktop + mobile: "Boutique" is a simple link to `/shop` (mega-menu removed)
- [x] `nav_dropdown_controller.js` removed (no longer needed)

### 14g — Fixtures & data update
- [x] Restructure `AppFixtures` with parent + subcategory hierarchy
- [x] Reassign 12 existing products to appropriate leaf categories (subcategories or childless parents)
- [x] Reload fixtures and verify all products display correctly

### 14h — Repository & Twig filters
- [x] `findAllOrdered()` → returns tree structure (parents with children)
- [x] `findRootCategories()` — returns only parent categories
- [x] `findChildrenByParent()` — returns subcategories of a given parent
- [x] `localized_name` / `localized_slug` filters still work on both levels
- [x] Update `ARCHITECTURE.md` with new category structure

### Definition of Done
- Create parent + subcategory in EasyAdmin → hierarchy displays correctly
- Cannot assign a product to a parent that has children (forced to pick leaf category)
- Can assign a product to a childless parent (e.g. Coffrets) → works correctly
- `/shop/bracelets` shows all bracelet products (from all subcategories)
- `/shop/bracelets/manchettes` shows only cuff bracelets
- `/shop/coffrets` shows coffret products directly (no subcategory level)
- Sidebar: collapsible tree for parents with children, simple links for childless parents
- Navbar "Boutique" links directly to catalog (no dropdown)
- All 12 fixture products correctly assigned to leaf categories
- No broken links, 404 on invalid slugs

---
