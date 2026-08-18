# Milestone 0 — Project bootstrap — terminé

> **Archive** — checklist de milestone terminé, extraite de `ROADMAP.md`
> le 2026-08-18. L'état courant du projet est décrit par le code et
> `docs/ARCHITECTURE.md`.

*Estimated effort: 2-3h*

### Tasks
- [x] DDEV configuration (`php 8.3`, `mariadb 10.11`, `symfony` project type)
- [x] `composer create-project symfony/skeleton`
- [x] Install core bundles:
  - `symfony/orm-pack` (Doctrine + migrations)
  - `easycorp/easyadmin-bundle`
  - `symfony/asset-mapper` + Tailwind CSS
  - `symfony/security-bundle`
  - `symfony/mailer`
  - `knplabs/knp-paginator-bundle`
  - `liip/imagine-bundle` (image resizing)
- [x] `.env.local` template with required keys documented (no actual values)
- [x] Base Twig layout (`templates/shop/base.html.twig`) with correct font imports
  (Cormorant Garamond + Inter via Google Fonts)
- [x] Tailwind configured with Alma Stella color tokens
- [x] EasyAdmin `DashboardController` accessible at `/admin`

### Definition of Done
- `ddev start && ddev exec php bin/console cache:clear` runs without error
- `/admin` returns the EasyAdmin dashboard (empty, no CRUDs yet)
- Homepage `/` returns a styled "coming soon" page using the correct palette
- No deprecation warnings in the Symfony profiler

---
