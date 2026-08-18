# Milestone 4 — Currency selector — terminé

> **Archive** — checklist de milestone terminé, extraite de `ROADMAP.md`
> le 2026-08-18. L'état courant du projet est décrit par le code et
> `docs/ARCHITECTURE.md`.

*Estimated effort: 2-3h*

### Tasks
- [x] `CurrencyConverter` service (open.er-api.com, cached 6h)
- [x] `CurrencyExtension` Twig extension with `|price` filter
- [x] Currency selector in header (EUR / USD / CAD / GBP / MXN)
- [x] Selection stored in session + cookie (30-day expiry)
- [x] Disclaimer displayed when non-EUR currency selected:
  *"Prices shown in [USD] are indicative. You will be charged in EUR at checkout."*
- [x] Fallback to EUR if exchange rate API is unavailable

### Definition of Done
- Select USD → all product prices update across all pages
- Refresh the page → currency selection is remembered
- Close and reopen browser → currency still remembered (cookie)
- Force the exchange rate API to fail (mock) → site displays EUR without error
- Disclaimer visible only when non-EUR currency selected

---
