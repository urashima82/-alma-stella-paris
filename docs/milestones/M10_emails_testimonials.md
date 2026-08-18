# Milestone 10 — Automated emails & customer testimonials — terminé

> **Archive** — checklist de milestone terminé, extraite de `ROADMAP.md`
> le 2026-08-18. L'état courant du projet est décrit par le code et
> `docs/ARCHITECTURE.md`.

*Estimated effort: 2-3h*

### Tasks
- [x] `Testimonial` entity (email, rating 1-5, text, firstName, lastNameInitial, city, status)
- [x] Post-purchase testimonial request email (J+14 via Symfony Scheduler)
  - Deduplicate by email: if a `Testimonial` already exists for this email → skip
  - Email sourced from the `Order` (works for guests and logged-in customers)
- [x] Public testimonial submission form (accessible via unique token in email)
- [x] Testimonial moderation in EasyAdmin (pending → approved / rejected)
- [x] Display latest approved testimonials on homepage (section with "Voir tous les témoignages" link)
- [x] Dedicated `/testimonials` page (all approved testimonials)
- [x] Footer link to `/testimonials`
- [x] DataFixtures: sample testimonials (approved) for development display
- [x] Schema.org `AggregateRating` from approved testimonials

> **Testimonials are brand-level, not product-level.** Since every piece is unique
> and sold once, product reviews make no sense. Testimonials capture the overall
> Alma Stella experience (quality, packaging, delivery, etc.).
>
> **No newsletter, no Brevo.** Communication strategy relies on social media
> (Instagram, Pinterest, TikTok Shop). Transactional emails only, sent via
> Symfony Mailer + SMTP.
>
> **No abandoned cart emails.** The reservation system already handles cart
> retention and item release — abandoned cart reminders would conflict with this logic.

### Definition of Done
- J+14 after order: customer receives testimonial request email (only if no existing testimonial for that email)
- Submit a testimonial via email link → testimonial saved as pending
- Admin approves → testimonial appears on homepage and `/testimonials` page
- Homepage shows latest testimonials with link to full listing
- Footer contains link to `/testimonials`
- Fixtures load sample testimonials visible on homepage after `doctrine:fixtures:load`
- Returning customer who already left a testimonial is never re-solicited
- Scheduler cron commands run correctly via `php bin/console messenger:consume`

---
