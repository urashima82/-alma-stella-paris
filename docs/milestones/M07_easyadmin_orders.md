# Milestone 7 — EasyAdmin order management — terminé

> **Archive** — checklist de milestone terminé, extraite de `ROADMAP.md`
> le 2026-08-18. L'état courant du projet est décrit par le code et
> `docs/ARCHITECTURE.md`.

*Estimated effort: 3-4h*

### Tasks
- [x] EasyAdmin CRUD for `Order`:
  - Status workflow: `pending → processing → shipped → delivered → cancelled`
  - Tracking number field
  - Origin country field (France / Mexico) — affects shipping display only
  - Internal notes field (admin-only)
  - Customer details visible (with link to customer if account exists)
  - Order items list with product snapshots
  - Billing address displayed when different from shipping
- [x] Order status change triggers email via Symfony Mailer:
  - Shipped → sends tracking number to customer
  - Delivered → sends care instructions + Instagram CTA
  - Cancelled → sends cancellation notification to customer
- [x] Dashboard stats widget: orders today, revenue this week, low stock alert
- [x] `ShippingSettingsCrudController` — admin-editable shipping tier costs
- [x] `SiteSettingsCrudController` — active collection filter (all / france / mexico)

### Definition of Done
- Change order status to "shipped" + add tracking number → customer receives email
- Change order status to "cancelled" → customer receives cancellation email
- Dashboard stats display correctly
- Origin country (FR/MX) saved without affecting customer-facing prices
- Shipping costs editable from EasyAdmin

---
