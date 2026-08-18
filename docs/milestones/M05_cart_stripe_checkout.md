# Milestone 5 — Cart & Stripe checkout — terminé

> **Archive** — checklist de milestone terminé, extraite de `ROADMAP.md`
> le 2026-08-18. L'état courant du projet est décrit par le code et
> `docs/ARCHITECTURE.md`.

*Estimated effort: 6-8h*

### Tasks
- [x] Cart stored in session (guests) + database (logged-in customers):
  - Guest: session + cookie (`alma_cart`, 30-day expiry)
  - Customer: `Cart` + `CartItem` entities in database
  - `CartMergeSubscriber` merges guest cart into customer on login
- [x] Cart drawer (slide-in, Stimulus controller):
  - Item list with thumbnails
  - No quantity selector (pièce unique = always 1)
  - Item removal
  - Subtotal in selected currency
- [x] Checkout tunnel (3-step funnel):
  - **Step 1 — Identify** (`/identify` | `/identification`):
    - Logged-in customer: auto-redirect to step 2
    - Guest: email entry with account detection (`checkout_identify_controller`)
    - Existing account found → prompt to log in
    - New email → proceed as guest, email stored in session
    - Email readonly in subsequent steps
  - **Step 2 — Checkout** (`/checkout` | `/livraison`):
    - Shipping address form with country selector
    - Address selector for logged-in customers (`address_selector_controller`)
    - Optional separate billing address (`billing_toggle_controller`)
    - Order summary
  - **Step 3 — Payment**:
    - Stripe Elements payment form (card + Apple Pay + Google Pay)
- [x] Stripe PaymentIntent creation (server-side)
- [x] Order deduplication: reuse pending order in session instead of creating duplicates
- [x] Order entity created on successful payment
- [x] Confirmation page (`/order/{reference}/confirmation`):
  - "Merci ! ✦ Your order is confirmed." message
  - Order summary
  - Estimated delivery note
- [x] Branded tracking page (`/order/{reference}/tracking`):
  - Order status progress indicator (Confirmed → Shipped → Delivered)
  - Tracking number with 17track link
  - Special handling for Cancelled status (red badge)
- [x] Order confirmation email sent automatically (Symfony Mailer + Twig template, Mailpit in dev)
- [x] On successful payment: set `isSoldOut = true` + `soldAt = now()` on purchased products
- [x] Prevent adding sold-out product to cart (server-side check)
- [x] Payment verification on return (3D Secure redirect handling)
- [x] `app:verify-pending-orders` console command + Symfony Scheduler (every 5 min) —
  verifies Stripe status for pending orders, confirms or cancels them automatically
- [x] **Product reservation system** (anti-double-sell for pièce unique):
  - [x] `Reservation` entity (OneToOne with Product, sessionId, expiresAt)
  - [x] `ReservationManager` service (reserve, release, lazy expiry check)
  - [x] Reservation triggered at checkout entry (identification step), 15-minute hold
  - [x] "Reserved" badge on catalog + product detail for other visitors
  - [x] `reservation_timer_controller` — visible countdown on all checkout pages
  - [x] `CartManager` blocks adding reserved products + filters them from cart
  - [x] `CleanExpiredReservationsMessage` — scheduler batch cleanup every 5 min
  - [x] Auto-release on successful payment or expiry

> **No Stripe webhooks.** Payment verification uses 3 layers: immediate
> (Stimulus controller), on-return (3DS redirect), and Symfony Scheduler.
> Single cron in prod: `* * * * * php bin/console messenger:consume scheduler_default`

### Definition of Done
- Add product to cart → drawer opens and shows item
- Cannot add sold-out product to cart (button disabled + server-side rejection)
- Complete Stripe test payment (use test card `4242 4242 4242 4242`)
- Order appears in EasyAdmin with correct status `pending`
- Purchased product automatically marked `isSoldOut` with `soldAt` timestamp
- Confirmation email received in Mailpit (dev) or real inbox (prod)
- Cart clears after successful payment

---
