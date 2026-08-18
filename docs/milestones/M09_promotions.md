# Milestone 9 — Promotions & discount codes — terminé

> **Archive** — checklist de milestone terminé, extraite de `ROADMAP.md`
> le 2026-08-18. L'état courant du projet est décrit par le code et
> `docs/ARCHITECTURE.md`.

*Estimated effort: 8-10h*

> **Full-featured promotion system**: automatic product discounts, automatic cart
> discounts, manual coupon codes,.
> Highly configurable from EasyAdmin with usage tracking and analytics.

### Tasks

#### Entities & enums
- [x] `PromotionType` enum: `ProductAutomatic` / `CartAutomatic` / `CartCode`
- [x] `DiscountType` enum: `Percentage` / `FixedAmount`
- [x] `Promotion` entity with full configuration fields
- [x] `PromotionUsage` entity for usage tracking
- [x] Add `discountAmountUsd` + `promotionCode` on `Order`
- [x] Add `discountAmountUsd` on `OrderItem`
- [x] `PromotionRepository` with active promo queries
- [x] Update initial migration with all new tables/columns

#### PromotionEngine service
- [x] `PromotionEngine` service — central discount calculation logic
- [x] Product-level auto promotions: find applicable promos, calculate discounted price
- [x] Cart-level auto promotions: evaluate conditions, apply best discount
- [x] Code validation: check code validity, usage limits, email limits, minimum amount
- [x] Cumul logic: `isCumulable` flag determines stacking behavior
- [x] `compareAtPrice` interaction: `overridesCompareAtPrice` flag per promotion

#### EasyAdmin
- [x] `PromotionCrudController` with full form (all configurable fields)
- [x] Product/category restriction via `AssociationField`
- [x] Stats display on promotion detail: usage count, revenue, last used
- [x] Menu entry "Promotions" in admin sidebar

#### Checkout integration
- [x] Coupon code input field at checkout step 2 (order summary)
- [x] `coupon_code_controller` Stimulus controller for async validation
- [x] Apply discount to order total before Stripe PaymentIntent creation
- [x] Track promotion usage on successful payment (`PromotionUsage` created)
- [x] Update `PromotionEngine` stats (usage count, revenue, lastUsedAt)

#### Catalog & product display (prix barrés)
- [x] Auto product promotions generate dynamic strikethrough prices on catalog
- [x] Product detail page shows original price barré + discounted price + badge
- [x] `compareAtPrice` coexists: promo takes priority if `overridesCompareAtPrice = true`
- [x] Cart drawer shows per-item discount when applicable

#### DataFixtures
- [x] Sample promotions (1 product auto, 1 cart auto, 1 code)

### Definition of Done
- Create promotion in EasyAdmin with all configuration options
- Product auto promo → prix barré visible on catalog + product detail
- Cart auto promo → discount line visible in checkout recap
- Enter valid code at checkout → discount applied, total updated
- Enter expired/maxed-out code → clear error message
- `isCumulable = false` → best single offer retained
- `overridesCompareAtPrice = false` → promo skips products with existing compareAtPrice
- Promotion usage tracked per order, stats visible in EasyAdmin
- All pages bilingual (FR/EN), French accents correct

---
