# Milestone 6 — Admin authentication (Magic Link) — terminé

> **Archive** — checklist de milestone terminé, extraite de `ROADMAP.md`
> le 2026-08-18. L'état courant du projet est décrit par le code et
> `docs/ARCHITECTURE.md`.

*Estimated effort: 1-2h*

### Tasks
- [x] Install `nickdnk/symfony-magic-link-bundle` (or equivalent Magic Link solution)
- [x] Create `Admin` entity implementing `UserInterface`
- [x] Configure Doctrine user provider in `security.yaml`
- [x] Magic Link login flow: enter email → receive link via Symfony Mailer → click → authenticated
- [x] Login page (`/admin/login`) styled with brand identity
- [x] Add `access_control` rule: `^/admin` requires `ROLE_ADMIN`
- [x] Logout route (`/admin/logout`)
- [x] DataFixtures: default admin user (`admin@almastellaparis.com`)

### Definition of Done
- `/admin` redirects to `/admin/login` when not authenticated
- Enter admin email → Magic Link email received → click → EasyAdmin dashboard accessible
- Non-admin email → no link sent, error message displayed
- Logout → redirected to login page
- All EasyAdmin CRUDs inaccessible without `ROLE_ADMIN`
- No password stored in database

---
