# Audit pre-implementation — Feature Catalogue IA

> **⚠️ ARCHIVE — instantané d'avril 2026.** Audit réalisé *avant* l'implémentation
> du Milestone 16, aujourd'hui livrée. Ne décrit pas l'état courant du projet :
> le code fait foi, les invariants sont dans `ARCHITECTURE.md`.

> **Document de référence** pour l'implémentation de la feature Catalogue IA.
> Produit le 2026-04-17 après lecture des specs (`catalogue-ia-specs.md`)
> et audit complet du projet existant.

---

## 1. État du projet existant

### 1.1 Entités Doctrine (19 entités)

| Entité | Table | Relations clés |
|---|---|---|
| `Product` | `product` | ManyToOne → ProductCategory, ManyToMany → Stone, ManyToMany → Product (related) |
| `ProductCategory` | `product_category` | Self-referencing (parent/children), OneToMany → Product |
| `Stone` | `stone` | ManyToMany → Product |
| `Order` | `order` | ManyToOne → Customer, OneToMany → OrderItem |
| `OrderItem` | `order_item` | ManyToOne → Order, ManyToOne → Product |
| `Customer` | `customer` | OneToMany → CustomerAddress, Order, WishlistItem |
| `CustomerAddress` | `customer_address` | ManyToOne → Customer |
| `Cart` | `cart` | OneToOne → Customer, OneToMany → CartItem |
| `CartItem` | `cart_item` | ManyToOne → Cart, ManyToOne → Product |
| `Reservation` | `reservation` | OneToOne → Product |
| `Promotion` | `promotion` | ManyToMany → Product, ProductCategory; OneToMany → PromotionUsage |
| `PromotionUsage` | `promotion_usage` | ManyToOne → Promotion, Order |
| `ShippingSettings` | `shipping_settings` | — |
| `SiteSettings` | `site_settings` | — |
| `Admin` | `admin` | — |
| `ContactMessage` | `contact_message` | — |
| `Testimonial` | `testimonial` | ManyToOne → Order |
| `WishlistItem` | `wishlist_item` | ManyToOne → Customer, Product |
| `ResetPasswordRequest` | `reset_password_request` | ManyToOne → Customer |

### 1.2 Enums existants (7)

| Enum | Cases |
|---|---|
| `AdminRole` | SuperAdmin, Admin |
| `ContactSubject` | General, Order, Return, Collaboration, Other |
| `DiscountType` | Percentage, FixedAmount |
| `OrderStatus` | Pending, Processing, Shipped, Delivered, Cancelled |
| `PromotionType` | ProductAutomatic, CartAutomatic, CartCode |
| `ShippingTier` | Standard, Heavy, Set |
| `TestimonialStatus` | Pending, Approved, Rejected |

### 1.3 Services existants (16)

| Service | Rôle |
|---|---|
| `CartManager` | Gestion panier guest/customer |
| `PromotionEngine` | Évaluation et application des promotions |
| `ReservationManager` | Réservation session produits (anti-double-vente) |
| `StripeService` | Création PaymentIntent Stripe |
| `CurrencyConverter` | Conversion EUR → USD/CAD/GBP/MXN (cache 6h) |
| `ShippingCostProvider` | Coûts livraison par ShippingTier |
| `OrderMailer` | Emails transactionnels commande |
| `InvoiceGenerator` | Factures PDF (Dompdf) |
| `WishlistManager` | Wishlist clients |
| `TurnstileVerifier` | CAPTCHA Cloudflare Turnstile |
| `ContactMailer` | Notifications formulaire contact |
| `TestimonialMailer` | Emails demande d'avis J+14 |
| `PendingOrderVerifier` | Vérification commandes pending vs Stripe |
| `AbandonedOrderCleaner` | Nettoyage commandes abandonnées >24h |
| `ImageProcessor` | Conversion/resize images en WebP post-upload |
| `InstagramFeedService` | Feed Instagram via Behold.so |

### 1.4 EasyAdmin — CRUDs existants (11)

| CRUD Controller | Entité | Points notables |
|---|---|---|
| `ProductCrudController` | Product | Layout 2 colonnes, 3 uploads VichUploader (thumbnail, wornPhoto, contextPhoto) avec crop 4:5, filtres pays, bilingue |
| `ProductCategoryCrudController` | ProductCategory | Arbre hiérarchique drag-and-drop, bilingue |
| `StoneCrudController` | Stone | Bilingue complet, upload image 1:1, caractéristiques |
| `OrderCrudController` | Order | Workflow statut, tracking, notes internes |
| `CustomerCrudController` | Customer | Lecture seule, lien vers commandes |
| `PromotionCrudController` | Promotion | 3 onglets, ciblage produit/catégorie |
| `TestimonialCrudController` | Testimonial | Actions approve/reject, modération |
| `ContactMessageCrudController` | ContactMessage | Reply par mailto, mark as read |
| `ShippingSettingsCrudController` | ShippingSettings | Edit only |
| `SiteSettingsCrudController` | SiteSettings | Maintenance mode, collection active |
| `AdminCrudController` | Admin | Gestion admins, rôles |

### 1.5 Commandes CLI existantes (6)

| Commande | Rôle |
|---|---|
| `app:send-testimonial-requests` | Emails avis J+14 |
| `app:verify-pending-orders` | Vérification Stripe |
| `app:clean-expired-reservations` | Libération réservations expirées |
| `app:clean-abandoned-orders` | Nettoyage commandes abandonnées |
| `ImportCatalogueImagesCommand` | Import bulk images produits |
| `ImportStoneImagesCommand` | Import bulk images pierres |

### 1.6 Gestion d'images actuelle

- **VichUploaderBundle** avec `SmartUniqueNamer`
- **Mappings** : `product_images` → `public/uploads/products/`, `stone_images` → `public/uploads/stones/`
- **ImageUploadSubscriber** : conversion WebP automatique post-upload (600×750 thumbnails, 800×1000 worn/context)
- **ImageProcessor** : resize + conversion via Intervention Image (GD)
- **540 fichiers WebP** actuellement dans `public/uploads/products/`

### 1.7 Configuration Messenger actuelle

```yaml
# Transport synchrone — pas d'async configuré
framework:
    messenger:
        transports: sync
```

→ Nécessite reconfiguration pour transport async (Doctrine).

---

## 2. Packages Composer — état vs besoins

### Déjà installés

| Package | Requis par la spec | Statut |
|---|---|---|
| `symfony/http-client` | ✅ Client HTTP Gemini | Installé |
| `symfony/messenger` | ✅ Queue async | Installé (sync, à reconfigurer) |
| `symfony/rate-limiter` | ✅ Rate limiting Gemini | Installé |
| `symfony/cache` | ✅ Cache budget | Installé (via framework-bundle) |
| `easycorp/easyadmin-bundle` | ✅ Back-office | Installé (v5.0.4) |
| `intervention/image` | Utile pour post-traitement | Installé (v4.x) |
| `vich/uploader-bundle` | Images existantes | Installé (v2.9.2) |

### À installer

| Package | Usage |
|---|---|
| `league/flysystem-bundle` | Stockage images IA (sources + générées) |
| `league/csv` | ~~Import CSV~~ → **hors scope** (catalogue déjà importé) |

→ **Seul `league/flysystem-bundle` est à installer.**

---

## 3. Décisions d'architecture validées

### 3.1 Catégories → Enrichir `ProductCategory`

**Décision** : ne pas créer d'entités `Category`/`Subcategory` séparées.

Ajouter à `ProductCategory` existant :
- `preservationInstructions` (text, nullable) — instructions EN de préservation pour les prompts IA
- `specificFocus` (text, nullable) — focus spécifique pour les sous-catégories

La relation `CategoryVisualPrompt` pointe vers `ProductCategory`.
Les enfants de `ProductCategory` (hiérarchie existante) jouent le rôle de sous-catégories.

**Justification** : évite un modèle de données dual (deux systèmes de catégories), conserve l'arbre drag-and-drop et le bilingue existants.

### 3.2 Statut produit → Champ `visualStatus` dédié

**Décision** : ajouter un champ `visualStatus` (enum `VisualWorkflowStatus`) à `Product`, indépendant des booléens existants.

```
Booléens existants (inchangés)    Nouveau champ IA
─────────────────────────────     ──────────────────
isPublished                       visualStatus: DRAFT
isSoldOut                         visualStatus: PENDING_VISUALS
isFeatured                        visualStatus: READY_FOR_REVIEW
                                  visualStatus: VISUALS_APPROVED
```

Un produit peut être `VISUALS_APPROVED` côté IA sans être `isPublished` côté boutique.
Les deux axes sont indépendants.

**Justification** : zéro impact sur la logique boutique existante (contrôleurs, templates, requêtes, filtres). Le workflow IA vit à côté.

### 3.3 Stockage images IA → Flysystem

**Décision** : `league/flysystem-bundle` pour les images IA uniquement.

```
VichUploader (existant, inchangé) :
  public/uploads/products/   → thumbnail, wornPhoto, contextPhoto
  public/uploads/stones/     → imageName

Flysystem (nouveau, IA uniquement) :
  var/storage/products/{id}/sources/    → SourcePhoto
  var/storage/products/{id}/generated/  → GeneratedVisual
```

**Justification** : séparation claire des responsabilités. VichUploader reste pour les images publiques, Flysystem gère les images IA (privées, volumineuses, potentiellement migrables vers S3).

### 3.4 Stone → Hors prompts IA

**Décision** : aucun changement à l'entité `Stone`. Pas de champs `visualDescription`, `mood`, `complementaryPalette`.

Le `PromptBuilder` n'utilise pas `Stone`. Gemini se base uniquement sur les photos sources pour reproduire fidèlement la pierre.

**Justification** : l'aspect d'une pierre change selon le retravail. Une description textuelle théorique risque de contredire ce que Gemini voit sur les photos sources. La spec demande elle-même `"PRESERVE EXACTLY"` — mieux vaut laisser Gemini travailler à partir de ce qu'il voit.

### 3.5 Relation Product ↔ Stone → Garder ManyToMany

**Décision** : conserver la relation `ManyToMany` existante. La spec proposait `ManyToOne` (une seule pierre), mais certains bijoux ont plusieurs pierres et Stone est hors scope IA.

### 3.6 Images finales → Copie auto vers VichUploader

**Décision** : quand un `GeneratedVisual` est approuvé, copie automatique vers le champ VichUploader correspondant du `Product`.

```
Approbation vignette-v2 → Product.thumbnail (VichUploader)
Approbation worn-v1     → Product.wornPhoto (VichUploader)
Approbation lifestyle-v3 → Product.contextPhoto (VichUploader)
```

L'`ImageProcessor` existant traite le fichier (WebP, resize). La boutique affiche la nouvelle image sans aucun changement côté templates.

**Justification** : zéro changement côté boutique. Le `GeneratedVisual` reste archivé dans Flysystem pour traçabilité.

### 3.7 Import images → Adapter le script existant

**Décision** : adapter `ImportCatalogueImagesCommand` pour créer des `SourcePhoto` (Flysystem) au lieu des 3 champs VichUploader séparés.

Le script existant importe les images brutes. Après adaptation, il :
1. Copie les images vers Flysystem (`var/storage/products/{id}/sources/`)
2. Crée les entrées `SourcePhoto` en BDD
3. Les images sont prêtes pour la génération IA

Usage unique pour les tests et le lancement, puis le workflow normal passe par le ProductCrud (upload multi).

### 3.8 Prompts → BDD + EasyAdmin + fallback code

**Décision** : suivre la spec. `CategoryVisualPrompt` éditable dans EasyAdmin avec versioning. Fallback générique en dur dans `PromptFallbackProvider`.

---

## 4. Collisions identifiées et résolutions

### 4.1 Noms d'entités

| Spec originale | Résolution |
|---|---|
| `Category` | → Réutiliser `ProductCategory` (enrichi) |
| `Subcategory` | → Enfants de `ProductCategory` (existant) |
| `Stone` (avec champs IA) | → `Stone` existant, inchangé |
| `Product.status` (enum) | → Nouveau champ `visualStatus` (pas de conflit) |

### 4.2 Services

| Collision potentielle | Résolution |
|---|---|
| `ImageProcessor` vs `ImageStorage` | Coexistence : `ImageProcessor` pour VichUploader, `ImageStorage` pour Flysystem IA |
| `ImportCatalogueImagesCommand` vs `ImportCatalogueCommand` | Adapter l'existant au lieu de créer une nouvelle commande |

### 4.3 Messenger

Le transport sync actuel doit être reconfiguré. Les tâches planifiées existantes (scheduler) ne sont pas impactées — elles utilisent `scheduler_default`, le transport IA sera `gemini_async` (séparé).

### 4.4 Routes

Aucune collision. La feature est entièrement back-office (EasyAdmin, routes auto-générées).

---

## 5. Scope validé pour l'implémentation

### À implémenter

| Phase | Contenu |
|---|---|
| **Phase 1 — Fondations** | Enums (`VisualType`, `VisualStatus`, `VisualWorkflowStatus`, `PhotoAngle`). Entités (`CategoryVisualPrompt`, `SourcePhoto`, `GeneratedVisual`). Enrichir `ProductCategory` et `Product`. Modifier la migration existante. |
| **Phase 2 — Import sources** | Adapter `ImportCatalogueImagesCommand` pour créer des `SourcePhoto` via Flysystem |
| **Phase 3 — Cerveau IA** | `PromptBuilder`, `PromptResult`, `BrandStyleProvider`, `TechnicalSpecsProvider`, `PromptFallbackProvider`. Tests unitaires. |
| **Phase 4 — Client Gemini** | `GeminiImageClient`, `GeminiResponse`, `GeminiApiException`, `BudgetGuard` (table `gemini_usage_log`) |
| **Phase 5 — Queue async** | `GenerateVisualMessage`, `GenerateVisualHandler`, config Messenger async, RateLimiter, `ImageStorage` Flysystem |
| **Phase 6 — Back-office** | `CategoryVisualPromptCrudController`, enrichir `ProductCrudController` (upload multi SourcePhoto, bouton Générer), `GeneratedVisualCrudController` (validation approve/reject/regenerate, copie auto vers VichUploader) |
| **Phase 7 — Fixtures** | `CategoryFixtures` (preservationInstructions), `CategoryVisualPromptFixtures` (prompts initiaux pour 4 catégories × 3 types) |

### Hors scope immédiat

| Élément | Raison |
|---|---|
| `ImportCatalogueCommand` (CSV) | Catalogue déjà importé (222 produits) |
| `MigrateAllVisualsCommand` (batch 222) | Reporter après validation du pipeline sur quelques produits tests |
| `StoneFixtures` (champs IA) | Stone hors prompts IA |
| Dashboard monitoring coût | `BudgetGuard` suffit comme garde-fou initial |
| Champs IA sur `Stone` | Décision : Stone hors prompts IA |

---

## 6. Entités à créer / modifier — synthèse

### Nouvelles entités

```
CategoryVisualPrompt
  ├── id (int, PK)
  ├── category → ProductCategory (ManyToOne)
  ├── visualType (VisualType enum)
  ├── framing (text)
  ├── staging (text)
  ├── props (json)
  ├── active (bool)
  └── version (int)
  Contrainte unique : (category_id, visualType, version)

SourcePhoto
  ├── id (int, PK)
  ├── product → Product (ManyToOne)
  ├── path (string)
  ├── position (int)
  └── angle (PhotoAngle enum)

GeneratedVisual
  ├── id (int, PK)
  ├── product → Product (ManyToOne)
  ├── type (VisualType enum)
  ├── path (string)
  ├── promptUsed (text)
  ├── categoryPromptVersion (int)
  ├── status (VisualStatus enum)
  ├── variant (int)
  ├── geminiRequestId (string, nullable)
  ├── errorMessage (text, nullable)
  └── createdAt (datetime_immutable)

GeminiUsageLog (table simple, pas d'entité Doctrine si overkill)
  ├── id (int, PK)
  ├── costUsd (decimal)
  └── createdAt (datetime_immutable)
```

### Entités modifiées

```
ProductCategory (existant)
  + preservationInstructions (text, nullable)
  + specificFocus (text, nullable)
  + OneToMany → CategoryVisualPrompt

Product (existant)
  + visualStatus (VisualWorkflowStatus enum, default DRAFT)
  + OneToMany → SourcePhoto
  + OneToMany → GeneratedVisual
```

### Nouveaux enums

```
VisualType:           VIGNETTE, WORN, LIFESTYLE
VisualStatus:         GENERATING, PENDING_REVIEW, APPROVED, REJECTED, FAILED
VisualWorkflowStatus: DRAFT, PENDING_VISUALS, READY_FOR_REVIEW, VISUALS_APPROVED
PhotoAngle:           FRONT, THREE_QUARTER, DETAIL, BACK, OTHER
```

---

## 7. Conventions à respecter

Rappel des conventions observées dans le code existant (à suivre strictement) :

- **Attributs PHP 8** pour Doctrine (`#[ORM\Entity]`), pas d'annotations
- **`declare(strict_types=1)`** sur chaque fichier PHP
- **Enums string-backed** avec méthodes `label()` et `badgeColor()`
- **EasyAdmin en français** : labels, menus, messages flash
- **Bilingue** : champs dupliqués `name`/`nameFr` (pas de bundle i18n)
- **Services** : injection constructeur, typage strict
- **Code en anglais** : noms de variables, classes, méthodes, commentaires
- **Contenu utilisateur en français** : labels EasyAdmin, descriptions, UI copy
- **PSR-12** + PHP CS Fixer (`@Symfony` + `@Symfony:risky`)
- **PHPStan level 6**
- **Migration unique** : modifier la migration initiale existante, `ddev delete --omit-snapshot && ddev start`

---

*Fin du rapport d'audit. Prêt pour l'implémentation après validation.*
