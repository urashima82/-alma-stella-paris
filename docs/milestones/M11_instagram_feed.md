# Milestone 11 — Instagram feed (Behold.so) — terminé

> **Archive** — checklist de milestone terminé, extraite de `ROADMAP.md`
> le 2026-08-18. L'état courant du projet est décrit par le code et
> `docs/ARCHITECTURE.md`.

*Estimated effort: 2-3h*

> **Prerequisite (client action):** Create a Behold.so account, connect the
> Instagram Business account `@alma_stella_paris`, and provide the Feed ID.
> This milestone cannot start until the Feed ID is available.

### Tasks

#### Configuration
- [x] Add `BEHOLD_FEED_ID` to `.env` (documented, no default value)
- [x] Add `BEHOLD_FEED_ID` to `.env.local` with actual Feed ID from client

#### Service & cache
- [x] `InstagramFeedService` — fetches Behold.so JSON API (`https://feeds.behold.so/{feedId}`)
- [x] Symfony Cache integration (filesystem adapter, TTL **6h** — same pattern as `CurrencyConverter`)
- [x] Graceful fallback: if API unavailable or cache miss fails, return empty array (no error displayed)
- [x] Cache warmup via Symfony Scheduler (optional: pre-fetch every 6h to avoid cold cache on first visitor)

#### Homepage integration
- [x] Replace placeholder grid (6 ✦ squares) with real Instagram photos from `InstagramFeedService`
- [x] Display up to 6 latest posts: thumbnail image, link to original Instagram post
- [x] Hover effect: slight zoom + overlay with post caption (truncated)
- [x] Responsive grid: 2 columns (mobile) → 3 columns (tablet) → 6 columns (desktop)
- [x] Fallback: if no posts available, show current placeholder gracefully (no broken layout)

#### Performance
- [x] Images served via Behold CDN (no local download/storage)
- [x] Lazy loading (`loading="lazy"`) on all Instagram images
- [x] No external JavaScript — server-side fetch only, rendered in Twig

### Definition of Done
- Homepage displays 6 real Instagram posts from `@alma_stella_paris`
- Click on a post → opens the original Instagram post in a new tab
- Disconnect internet → cached posts still display for up to 6h
- Force cache expiry → service re-fetches from Behold API without error
- Behold API down → homepage renders without Instagram section (no error, no broken layout)
- No external JS loaded — zero impact on Lighthouse performance score
- Mobile responsive: 2 → 3 → 6 columns across breakpoints

---
