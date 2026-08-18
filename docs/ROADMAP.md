# ROADMAP.md — Alma Stella Paris

> Development is organized in **testable milestones**. Each milestone must be
> fully functional and manually testable before starting the next.
> Claude Code must not skip ahead or partially implement a milestone.

---

## How to use this roadmap

Each milestone has a **Definition of Done (DoD)** — a checklist of what must
work before the milestone is considered complete. When a milestone is done,
ask the developer to validate before proceeding.

When every box of a milestone is checked and validated, move its full
checklist to `docs/milestones/` (archive file per milestone) and add it to
the table below — this file only carries the milestones still open.

---

## Completed milestones (archived)

Full checklists live in [`docs/milestones/`](milestones/), listed here in
order of completion:

| # | Milestone | Archive |
|---|---|---|
| 0 | Project bootstrap | [M00_project_bootstrap.md](milestones/M00_project_bootstrap.md) |
| 1 | Product catalog (admin + data layer) | [M01_product_catalog_admin.md](milestones/M01_product_catalog_admin.md) |
| 2 | Public catalog (frontend) | [M02_public_catalog.md](milestones/M02_public_catalog.md) |
| 3 | Internationalization (FR/EN) | [M03_internationalization.md](milestones/M03_internationalization.md) |
| 4 | Currency selector | [M04_currency_selector.md](milestones/M04_currency_selector.md) |
| 5 | Cart & Stripe checkout | [M05_cart_stripe_checkout.md](milestones/M05_cart_stripe_checkout.md) |
| 6 | Admin authentication (Magic Link) | [M06_admin_magic_link.md](milestones/M06_admin_magic_link.md) |
| 7 | EasyAdmin order management | [M07_easyadmin_orders.md](milestones/M07_easyadmin_orders.md) |
| 8 | Customer accounts | [M08_customer_accounts.md](milestones/M08_customer_accounts.md) |
| 12 | SEO & performance | [M12_seo_performance.md](milestones/M12_seo_performance.md) |
| 9 | Promotions & discount codes | [M09_promotions.md](milestones/M09_promotions.md) |
| 10 | Automated emails & customer testimonials | [M10_emails_testimonials.md](milestones/M10_emails_testimonials.md) |
| 13 | Security audit & hardening | [M13_security_hardening.md](milestones/M13_security_hardening.md) |
| 14 | Two-level category hierarchy | [M14_category_hierarchy.md](milestones/M14_category_hierarchy.md) |
| 10b | EUR base currency migration | [M10B_eur_base_currency.md](milestones/M10B_eur_base_currency.md) |
| 11 | Instagram feed (Behold.so) | [M11_instagram_feed.md](milestones/M11_instagram_feed.md) |
| 15 | Guide des pierres & filtre boutique | [M15_stone_guide.md](milestones/M15_stone_guide.md) |
| 18 | Wizard de création produit assistée IA | [M18_product_wizard.md](milestones/M18_product_wizard.md) |
| 16 | Catalogue IA (génération visuels) | [M16_ai_visual_catalog.md](milestones/M16_ai_visual_catalog.md) |
| 17 | Remplissage IA des contenus produit | [M17_ai_content_fill.md](milestones/M17_ai_content_fill.md) |

---

## Open milestones

**None** — every milestone of the initial scope is completed and archived
(arbitration of 2026-08-18). New work starts as a new milestone here, or is
picked from the V2 backlog below.

---

## V2 backlog (post-launch, not in current scope)

- ~~Wishlist persistence (tied to customer account)~~ ✅ Implemented
- ~~Multi-currency Stripe charges (vs current cosmetic conversion)~~ ✅ Replaced by EUR base currency (Milestone 10b)
- **Social publishing (ex-M12, deferred 2026-08-18)** — Pinterest & TikTok Shop
  API clients, Instagram deep link, `SocialPublisher` orchestrator, EasyAdmin
  action + per-channel modal, `social_publish_log` history table. Until then,
  publishing stays manual on the networks.
- **AI admin comfort (ex-M16 groups B/C/D, deferred 2026-08-18)** — detail in
  [M16_ai_visual_catalog.md](milestones/M16_ai_visual_catalog.md):
  - B — AI consumption dashboard (monthly cost, trend, top products, budget alert)
  - C — advanced UX (side-by-side comparisons, prompt preview, cross-product pending view, per-product prompt override)
  - D — robustness (real-time polling during generation, full history, HD source download)
- **Withdrawal webform (deferred 2026-08-18)** — "Rétractation" motive on the
  contact form + mandatory automatic acknowledgement email (art. L221-21).
  Pure UX, no legal obligation.
- **Estelle's personal data decoupling (deferred 2026-08-18)** — home address
  and personal phone are published in the legal notice by assumed choice.
  If revisited: commercial domiciliation and/or virtual line (translation keys
  `legal.publisher_address`, `legal.publisher_phone`,
  `terms.withdrawal_form_recipient`), partial-diffusion Sirene status (free).
- Lookbook / editorial seasonal pages
- Referral program ("Give $10, Get $10")
- Loyalty program (3 orders → automatic discount)
- Faire/Ankorstore B2B wholesale channel
