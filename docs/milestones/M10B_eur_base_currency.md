# Milestone 10b — EUR base currency migration — terminé

> **Archive** — checklist de milestone terminé, extraite de `ROADMAP.md`
> le 2026-08-18. L'état courant du projet est décrit par le code et
> `docs/ARCHITECTURE.md`.

*Estimated effort: 6-8h*

> **Architectural change:** Switch the reference currency from USD to EUR.
> Estelle's Stripe settlement currency is EUR (French bank account), so charging
> in EUR eliminates the ~2% Stripe conversion fee on all EUR transactions.
> Non-EUR currencies (USD, CAD, GBP, MXN) remain as cosmetic display with
> a disclaimer: "You will be charged in EUR at checkout."
>
> **Since we are still in creation phase** (nothing in production), all changes
> are made in place — modify existing migration, no backwards compatibility needed.

### Tasks

#### 1. Entity & database schema (rename USD → EUR)
- [x] `Order` entity: rename `$totalUsd` → `$totalEur`, `$discountAmountUsd` → `$discountAmountEur`
  - Rename all getters/setters: `getTotalUsd()` → `getTotalEur()`, `setTotalUsd()` → `setTotalEur()`,
    `getDiscountAmountUsd()` → `getDiscountAmountEur()`, `setDiscountAmountUsd()` → `setDiscountAmountEur()`
  - Update `getItemsSummary()`: change `$` symbols to `€` and `getTotalUsd()` → `getTotalEur()`
- [x] `OrderItem` entity: rename `$discountAmountUsd` → `$discountAmountEur`
  - Rename getter/setter: `getDiscountAmountUsd()` → `getDiscountAmountEur()`
  - Update `getLineTotal()` docblock: "in EUR" instead of "in USD"
- [x] `ShippingSettings` entity: rename `$shippingCostUsd` → `$shippingCostEur`
  - Rename getter/setter accordingly
- [x] `Promotion` entity: update any field labels/docblocks referencing USD
  - `fixedAmountValue` and `minimumOrderAmount` are now in EUR (docblocks)
- [x] `PromotionUsage` entity: update any USD references in docblocks
- [x] Modify initial migration (`Version20260408140632`):
  - Rename columns: `total_usd` → `total_eur`, `discount_amount_usd` → `discount_amount_eur` (on `order` table)
  - Rename column: `discount_amount_usd` → `discount_amount_eur` (on `order_item` table)
  - Rename column: `shipping_cost_usd` → `shipping_cost_eur` (on `shipping_settings` table)

#### 2. ShippingTier enum (EUR values)
- [x] Rename method `shippingCostUsd()` → `shippingCostEur()`
- [x] Convert hardcoded shipping costs to EUR:
  - Standard: 10 €
  - Heavy: 15 €
  - Set: 20 €

#### 3. CurrencyConverter service
- [x] Change `BASE_CURRENCY` constant: `'USD'` → `'EUR'`
- [x] Change `API_URL`: `open.er-api.com/v6/latest/USD` → `open.er-api.com/v6/latest/EUR`
- [x] Rename `convert()` parameter: `$amountUsd` → `$amountEur`
- [x] Update `SUPPORTED_CURRENCIES` array order: `['EUR', 'USD', 'CAD', 'GBP', 'MXN']`
- [x] Update default symbol fallback: `'$'` → `'€'`

#### 4. StripeService
- [x] Change PaymentIntent currency: `'usd'` → `'eur'`
- [x] Update amount calculation: `getTotalUsd()` → `getTotalEur()`

#### 5. ShippingCostProvider service
- [x] Rename method returning cost: update any `Usd` references to `Eur`
- [x] Ensure `ShippingSettings` column reference updated

#### 6. PromotionEngine service
- [x] Update all `discountAmountUsd` / `totalUsd` references to EUR equivalents
- [x] Verify fixed-amount promotions are treated as EUR

#### 7. CartManager & CartController
- [x] Update any `Usd` / `USD` references in price calculations

#### 8. CheckoutController
- [x] Replace all `totalUsd` / `discountAmountUsd` calls with EUR equivalents
- [x] Replace all `setTotalUsd()` / `setDiscountAmountUsd()` calls
- [x] Update inline comments referencing USD

#### 9. CurrencyExtension (Twig)
- [x] Default currency: `EUR` instead of `USD`
- [x] Rename `is_non_usd_currency()` → `is_non_eur_currency()`
- [x] Update `|price` filter: parameter name `$amountUsd` → `$amountEur`
- [x] Update fallback behavior for base currency

#### 10. CurrencySubscriber
- [x] Default currency fallback: `'EUR'` instead of `'USD'`

#### 11. Templates — storefront (15 Twig files)
- [x] `base.html.twig`: update currency disclaimer text (charged in EUR)
- [x] `checkout/index.html.twig`: `$` → `€` in price display, USD → EUR references
- [x] `checkout/payment.html.twig`: same
- [x] `product/show.html.twig`: same
- [x] `account/orders.html.twig`: same
- [x] `account/order_detail.html.twig`: same
- [x] Update `is_non_usd_currency` → `is_non_eur_currency` in all templates

#### 12. Templates — emails (5 Twig files)
- [x] `order_confirmation.html.twig`: `$` → `€`, USD → EUR
- [x] `order_shipped.html.twig`: same
- [x] `order_delivered.html.twig`: same
- [x] `order_cancelled.html.twig`: same
- [x] `admin_new_order.html.twig`: same

#### 13. Templates — admin & invoice
- [x] `admin/dashboard.html.twig`: `$` → `€`, USD → EUR
- [x] `admin/order/edit.html.twig`: same
- [x] `admin/customer/detail.html.twig`: same
- [x] `pdf/invoice.html.twig`: update currency symbol and references

#### 14. EasyAdmin controllers
- [x] `OrderCrudController`: update field labels (`Total USD` → `Total EUR`, etc.)
- [x] `OrderItemCrudController`: same
- [x] `ProductCrudController`: update `basePrice` label context
- [x] `PromotionCrudController`: update currency references in labels
- [x] `ShippingSettingsCrudController`: rename field labels USD → EUR
- [x] `CustomerCrudController`: update any USD display references

#### 15. Translations (YAML)
- [x] `messages.en.yaml`: update disclaimer text and any USD-specific strings
- [x] `messages.fr.yaml`: same

#### 16. OrderRepository
- [x] Update any `totalUsd` / `discountAmountUsd` DQL or QueryBuilder references

#### 17. DataFixtures
- [x] Convert all product `basePrice` values from USD to EUR equivalents
- [x] Convert `compareAtPrice` values
- [x] Convert promotion `fixedAmountValue` and `minimumOrderAmount`
- [x] Convert shipping settings default values
- [x] Update order fixture totals

#### 18. Documentation
- [x] `CLAUDE.md`: update all "USD" references to "EUR" (reference currency, Stripe, localisation,
  architecture decisions, out of scope section)
- [x] `docs/ARCHITECTURE.md`: update currency references throughout
- [x] `docs/LOCALISATION.md`: update "one price, many displays" to EUR base, update disclaimer text,
  update default currency
- [x] `docs/ROADMAP.md`: update Milestone 4 description (cosmetic disclaimer = non-EUR)
- [x] V2 backlog: remove "Multi-currency Stripe charges" line (no longer relevant)

#### 19. Rebuild & verify
- [x] Run `ddev exec vendor/bin/php-cs-fixer fix`
- [x] Run `ddev exec vendor/bin/phpstan analyse` — zero errors
- [x] Run `ddev exec php bin/console tailwind:build && ddev exec php bin/console asset-map:compile`
- [x] Recreate DDEV environment: `ddev delete --omit-snapshot && ddev start`
- [x] Run `ddev exec php bin/console doctrine:migrations:migrate`
- [x] Run `ddev exec php bin/console doctrine:fixtures:load`
- [x] Smoke test all pages in browser

### Definition of Done
- `CurrencyConverter::BASE_CURRENCY` is `'EUR'`
- Stripe PaymentIntent uses `'eur'` currency
- All entity properties, getters, setters reference EUR (no `Usd` anywhere in codebase)
- All database columns reference EUR (`total_eur`, `discount_amount_eur`, `shipping_cost_eur`)
- Default currency in header selector is EUR
- Select USD → disclaimer shows "You will be charged in EUR at checkout"
- Select EUR → no disclaimer shown
- `|price` filter converts from EUR base to selected display currency
- Product prices in fixtures are in EUR
- All email templates show `€` symbol
- Invoice PDF shows `€` amounts
- EasyAdmin dashboard revenue displayed in `€`
- `PHPStan analyse` passes at level 6
- `php-cs-fixer fix` reports no changes
- All 12 fixture products load correctly
- `grep -ri "totalUsd\|discountAmountUsd\|shippingCostUsd" src/` returns zero results
- Documentation (CLAUDE.md, ARCHITECTURE.md, LOCALISATION.md) consistently references EUR

---
