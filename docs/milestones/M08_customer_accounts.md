# Milestone 8 — Customer accounts — terminé

> **Archive** — checklist de milestone terminé, extraite de `ROADMAP.md`
> le 2026-08-18. L'état courant du projet est décrit par le code et
> `docs/ARCHITECTURE.md`.

*Estimated effort: 6-8h*

> **Strategy:** Guest checkout remains available (no account required to buy).
> Customer accounts are optional and provide convenience features (order history,
> saved addresses, pre-filled checkout). Account creation is encouraged
> post-purchase on the confirmation page. Authentication uses classic
> email + password (not Magic Link — different UX needs than admin).

### Tasks

#### Entity & security
- [x] Create `Customer` entity implementing `UserInterface`:
  - `email` (unique, identifier)
  - `password` (hashed)
  - `firstName`, `lastName`
  - `createdAt`, `updatedAt`
- [x] Create `CustomerAddress` entity:
  - FK to `Customer`
  - `label` (e.g. "Home", "Office")
  - `recipientName` (optional — allows "ship to different name")
  - `addressLine1`, `addressLine2`, `city`, `state`, `postalCode`, `country`
  - `isDefault` (boolean)
- [x] Add optional `customer` FK on `Order` entity (nullable — guest orders have no customer)
- [x] Configure second Symfony firewall (`shop`) for customer authentication:
  - Separate from `admin` firewall
  - `form_login` authenticator with email + password
  - Remember me cookie (30 days)
  - Custom entry point (redirect to login page)
- [x] Install `symfonycasts/reset-password-bundle` for password reset flow
- [x] Update initial migration with all new tables/columns

#### Registration & authentication
- [x] Registration page (`/{_locale}/register` / `/{_locale}/inscription`):
  - Form: email, password, password confirmation, first name, last name
  - Email validation (unique check + async `email_check_controller`)
  - Password strength requirement (min 8 chars)
  - **OTP email verification** before account creation:
    - 6-digit code sent to email (`registration_otp.html.twig`)
    - Verification page (`/{_locale}/verify-email` / `/{_locale}/verification-email`)
    - 10-minute expiry, max 5 attempts
    - Resend option at `/{_locale}/verify-email/resend`
  - Auto-login after successful OTP verification
  - Guest orders linked automatically by email on account creation
- [x] Login page (`/{_locale}/login` / `/{_locale}/connexion`):
  - Email + password form
  - "Forgot password?" link
  - Link to registration page
  - Brand-styled (consistent with site design)
- [x] Logout route (`/{_locale}/logout` / `/{_locale}/deconnexion`)
- [x] Password reset flow:
  - Request form (enter email)
  - Email with reset link (Symfony Mailer, branded template)
  - Reset form (new password + confirmation)
  - Link expires after 1 hour, single-use

#### Account pages (authenticated)
- [x] Account dashboard (`/{_locale}/account` / `/{_locale}/mon-compte`):
  - Welcome message with first name
  - Quick links: orders, addresses, profile
  - Summary: number of orders, member since
- [x] Order history page (`/{_locale}/account/orders` / `/{_locale}/mon-compte/commandes`):
  - List of past orders (paginated) with reference, date, status, total
  - Order detail view with items, tracking info, shipping address
- [x] Address book page (`/{_locale}/account/addresses` / `/{_locale}/mon-compte/adresses`):
  - List saved addresses
  - Add / edit / delete addresses
  - Set default address
- [x] Profile page (`/{_locale}/account/profile` / `/{_locale}/mon-compte/profil`):
  - Edit first name, last name, email
  - Change password (current password + new password)

#### Checkout integration
- [x] If logged in at checkout: pre-fill shipping form from default address
- [x] Address selector dropdown if customer has multiple saved addresses (`address_selector_controller`)
- [x] Readonly email field at checkout (from account or identify step)
- [x] After payment: link `Order` to `Customer` if authenticated
- [x] Post-purchase account creation prompt on confirmation page:
  *"Create your account to track your order and enjoy exclusive offers"*
  — only email pre-filled (from order), customer sets password
- [x] Guest order linking: when creating an account, automatically link past
  guest orders matching the same email address

#### Header & navigation
- [x] Add account icon in header (user silhouette):
  - Not logged in → links to login page
  - Logged in → dropdown with: My account, My orders, Logout
- [x] Mobile: account link in hamburger menu

#### EasyAdmin
- [x] EasyAdmin CRUD for `Customer` (read-only for admin):
  - List: email, name, number of orders, member since
  - Detail: profile info + linked orders
- [x] Update `Order` CRUD to show linked customer (if any) with link to customer detail

#### Invoice download
- [x] Add `invoiceToken` (UUID v4) field on `Order` entity, auto-generated at creation
- [x] Install `dompdf/dompdf` for PDF generation
- [x] `InvoiceGenerator` service: renders Twig template (`pdf/invoice.html.twig`) to PDF
- [x] `InvoiceController` with route `GET /invoice/{reference}/{token}` — token-verified access
- [x] Invoice download button on order detail page (for Processing/Shipped/Delivered orders)
- [x] Invoice download link in delivered email (`order_delivered.html.twig`)

#### Invoice improvements
- [x] Invoice PDF localized FR/EN (labels, legal mentions, product names)
- [x] Brand logo embedded in invoice header (base64-encoded)
- [x] Legal footer fixed at bottom of every page (SIRET, address, TVA art. 293 B)
- [x] `paidAt` field on `Order` — invoice shows payment date instead of creation date
- [x] Shipping hidden from invoice (free shipping included in product price)
- [x] `productNameFr` on `OrderItem` — bilingual product name snapshots
- [x] Localized product names in all email templates and account order detail
- [x] Localized invoice URL in delivery notification email
- [x] Free shipping line in customer order detail recap

#### Sequential numbering (French legislation compliant)
- [x] Invoice numbering: `FA-YYYY-XXXXX` — sequential, gap-free, assigned on payment confirmation
- [x] Order references: `ASP-YYYY-XXXXX` — sequential via `OrderRepository::nextOrderReference()`
- [x] `invoiceNumber` field on `Order` (nullable, unique) — separate from order reference
- [x] Invoice PDF and filename use `invoiceNumber` instead of order reference

#### Admin invoice integration
- [x] Admin order edit: payment date and invoice number in "Paiement" panel
- [x] Admin order edit: invoice download link (opens PDF in customer's locale)
- [x] Admin order edit: payment date in "Dates" panel

#### DataFixtures
- [x] Sample customer accounts (2-3) with addresses and linked orders

### Definition of Done
- Register with email + password → account created, auto-logged in
- Login → redirected to account dashboard
- Forgot password → reset email received → password changed successfully
- Account dashboard shows order history with correct data
- Add/edit/delete addresses in address book, set default
- Checkout as logged-in user → shipping form pre-filled from default address
- Checkout as guest → still works exactly as before (no regression)
- Post-purchase: account creation prompt on confirmation page
- Create account after guest purchase → past orders linked automatically
- Header shows account icon with dropdown when logged in
- Customer visible in EasyAdmin (read-only)
- All pages bilingual (FR/EN), French accents correct
- Pages mobile-responsive (375px, 768px)

---
