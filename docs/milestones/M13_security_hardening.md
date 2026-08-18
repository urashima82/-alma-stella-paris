# Milestone 13 — Security audit & hardening — terminé

> **Archive** — checklist de milestone terminé, extraite de `ROADMAP.md`
> le 2026-08-18. L'état courant du projet est décrit par le code et
> `docs/ARCHITECTURE.md`.

*Estimated effort: 3-4h*

> **Every fix requires developer approval before implementation.**
> Claude audits and proposes — developer validates before any change is applied.

### Tasks
- [x] OWASP Top 10 audit (XSS, CSRF, SQL injection, mass assignment, etc.)
- [x] Stripe webhook signature verification audit
- [x] Authentication & session security review (magic link, customer auth)
- [x] Rate limiting on sensitive endpoints (login, testimonial submission, checkout)
- [x] Input validation & sanitization audit (forms, query parameters)
- [x] CORS & security headers review (`X-Content-Type-Options`, `X-Frame-Options`, CSP, etc.)
- [x] Dependency vulnerability scan (`composer audit`)
- [x] File upload security review (image uploads, WebP conversion)
- [x] Environment secrets audit (`.env` not exposed, no hardcoded keys)
- [x] EasyAdmin access control review (admin routes properly protected)

### Definition of Done
- Full audit report delivered with findings classified by severity (critical / high / medium / low)
- Each proposed fix reviewed and approved by developer before implementation
- All critical and high severity issues resolved
- `composer audit` returns no known vulnerabilities
- Security headers verified via browser dev tools or online scanner

---
