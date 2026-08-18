# Milestone 1 — Product catalog (admin + data layer) — terminé

> **Archive** — checklist de milestone terminé, extraite de `ROADMAP.md`
> le 2026-08-18. L'état courant du projet est décrit par le code et
> `docs/ARCHITECTURE.md`.

*Estimated effort: 4-5h*

### Tasks
- [x] Create all entities: `Product`, `ProductCategory`, `ProductImage`,
  `ShippingTier` enum
- [x] Doctrine migrations generated and applied
- [x] EasyAdmin CRUD for `Product`:
  - All fields editable
  - `ShippingTier` displayed as colored badge (green/orange/blue)
  - `basePrice` and computed `displayPrice` both visible in index
  - `compareAtPrice` (optional) — original price for discount display
  - `availableIn` — JSON array for collection filtering (france / mexico)
  - Image upload with preview (auto WebP conversion via `ImageProcessor`)
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
