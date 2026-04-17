# Plan d'adaptation — Feature Catalogue IA

> Croisement entre `CATALOGUE-IA-SPECS.md` et `CATALOGUE_IA_AUDIT.md`.
> Checklist structurée pour l'implémentation.
> Produit le 2026-04-17.

---

## 1. Ce qui existe et peut être réutilisé tel quel

### 1.1 Entités

- [ ] **Product** — Structure de base réutilisable (id, name/nameFr, slug/slugFr, description/descriptionFr, basePrice, compareAtPrice, shippingTier, isPublished, isFeatured, isSoldOut, availableIn, soldAt, createdAt, updatedAt). Toutes les relations existantes (ManyToOne → ProductCategory, ManyToMany → Stone, ManyToMany → self) restent en place.
- [ ] **ProductCategory** — Hiérarchie parent/enfant, bilingue, drag-and-drop (Gedmo Sortable), slugs, `position`. Les enfants remplissent le rôle de sous-catégories prévu par la spec.
- [ ] **Stone** — Entité riche (bilingue, vertus, chakra, traditions, soins, lustre, origine, image). Aucune modification requise puisque Stone est hors prompts IA.

### 1.2 Services

- [ ] **ImageProcessor** (`src/Service/ImageProcessor.php`) — Conversion WebP (qualité 85%), resize, crop. Réutilisé tel quel pour traiter les visuels approuvés copiés vers VichUploader.
- [ ] **ImageUploadSubscriber** (`src/EventSubscriber/ImageUploadSubscriber.php`) — Traitement auto post-upload (Product thumbnail: 600×750, wornPhoto/contextPhoto: 800×1000). Continue de fonctionner pour les images finales VichUploader.
- [ ] **CartManager, PromotionEngine, ReservationManager, StripeService, CurrencyConverter, ShippingCostProvider, OrderMailer, InvoiceGenerator, WishlistManager, TurnstileVerifier, ContactMailer, TestimonialMailer, PendingOrderVerifier, AbandonedOrderCleaner, InstagramFeedService** — Aucun impact, aucune modification.

### 1.3 EasyAdmin

- [ ] **DashboardController** — Structure du menu existante (6 sections). Les nouveaux CRUDs s'ajoutent dans une nouvelle section.
- [ ] **Tous les CRUDs non impactés** — OrderCrudController, CustomerCrudController, PromotionCrudController, TestimonialCrudController, ContactMessageCrudController, ShippingSettingsCrudController, SiteSettingsCrudController, AdminCrudController. Aucune modification.
- [ ] **Templates admin custom** — dashboard.html.twig, login.html.twig, order/edit.html.twig, customer/detail.html.twig, category/index.html.twig, tous les field templates. Aucun impact.

### 1.4 Commandes CLI

- [ ] **app:send-testimonial-requests, app:verify-pending-orders, app:clean-expired-reservations, app:clean-abandoned-orders** — Aucun impact.
- [ ] **ImportStoneImagesCommand** — Aucun impact (images de pierres, pas de produits).

### 1.5 Infrastructure

- [ ] **VichUploaderBundle** — Reste en place pour Product (thumbnail, wornPhoto, contextPhoto) et Stone (imageName). Mappings existants `product_images` et `stone_images` inchangés.
- [ ] **Rate limiter** — Infrastructure existante (4 politiques configurées). Pattern à suivre pour ajouter `gemini_api`.
- [ ] **Messenger** — Framework installé. Pattern Message/MessageHandler existant (4 paires). À configurer en async.
- [ ] **Intervention Image (GD)** — Déjà installé, utilisé par ImageProcessor.
- [ ] **symfony/http-client** — Déjà installé, prêt pour GeminiImageClient.

### 1.6 Fixtures existantes

- [ ] **AppFixtures** — Crée déjà les admins, shipping settings, site settings, customers, 5 catégories racine avec enfants, 14+ pierres (bilingue complet), 222 produits liés, commandes, promotions, témoignages.

---

## 2. Ce qui existe mais doit être adapté

### 2.1 Entité `ProductCategory` — ajouter 2 champs

**Fichier** : `src/Entity/ProductCategory.php`

- [ ] Ajouter propriété `preservationInstructions` (text, nullable) — Instructions EN de préservation pour les prompts IA (ex: "PRESERVE EXACTLY: the band thickness, the exact setting…")
- [ ] Ajouter propriété `specificFocus` (text, nullable) — Focus spécifique optionnel pour les sous-catégories (ex: "emphasize the flat signet face and engraving")
- [ ] Ajouter relation `OneToMany` vers `CategoryVisualPrompt` (mappedBy: 'category', cascade: persist)
- [ ] Ajouter méthode `getVisualPromptFor(VisualType $type): ?CategoryVisualPrompt`
- [ ] Ajouter méthode `hasVisualPromptFor(VisualType $type): bool`

### 2.2 Entité `Product` — ajouter 1 champ + 2 relations

**Fichier** : `src/Entity/Product.php`

- [ ] Ajouter propriété `visualStatus` (enum `VisualWorkflowStatus`, default `DRAFT`) — Workflow IA indépendant des booléens existants
- [ ] Ajouter relation `OneToMany` vers `SourcePhoto` (mappedBy: 'product', cascade: persist/remove, orphanRemoval)
- [ ] Ajouter relation `OneToMany` vers `GeneratedVisual` (mappedBy: 'product', cascade: persist/remove)
- [ ] Ajouter méthode `getApprovedVisualFor(VisualType $type): ?GeneratedVisual`
- [ ] Ajouter méthode `getSourcePhotos(): Collection`
- [ ] Ajouter méthode `getGeneratedVisuals(): Collection`

### 2.3 Migration Doctrine — modifier la migration existante

**Fichier** : `migrations/Version20260408140632.php`

- [ ] Ajouter colonnes `preservation_instructions` et `specific_focus` à la table `product_category`
- [ ] Ajouter colonne `visual_status` à la table `product`
- [ ] Ajouter les tables `category_visual_prompt`, `source_photo`, `generated_visual`, `gemini_usage_log`
- [ ] Ajouter les index et FK correspondants
- [ ] Recréer l'environnement DDEV après modification (`ddev delete --omit-snapshot && ddev start`)

### 2.4 `ProductCrudController` — enrichir le CRUD existant

**Fichier** : `src/Controller/Admin/ProductCrudController.php` (219 lignes actuellement)

**Page index** :
- [ ] Ajouter colonne `visualStatus` avec badge coloré (après `isSoldOut`)
- [ ] Ajouter filtre par `visualStatus`
- [ ] Ajouter compteur visuels (ex: "3/9 approuvés") comme colonne formatée

**Page edit/new** :
- [ ] Ajouter un fieldset "Photos sources" dans la colonne gauche, après le fieldset "Photos" existant
- [ ] Implémenter un champ upload multiple pour `SourcePhoto` (CollectionField ou custom)
- [ ] Ajouter le champ `visualStatus` dans la colonne droite (fieldset "Publication")
- [ ] Ajouter un bouton/action "Générer les visuels IA" qui dispatche 9 messages Messenger (3 types × 3 variantes)
- [ ] Ajouter un fieldset "Visuels générés" affichant les `GeneratedVisual` groupés par type avec preview et statut

**Actions custom** :
- [ ] Action `generateVisuals` — dispatche les messages, passe `visualStatus` à `PENDING_VISUALS`
- [ ] Sécuriser : empêcher la génération si aucune `SourcePhoto` uploadée

### 2.5 `ProductCategoryCrudController` — enrichir le CRUD existant

**Fichier** : `src/Controller/Admin/ProductCategoryCrudController.php`

- [ ] Ajouter champ `preservationInstructions` (TextareaField, nullable, avec help text expliquant l'usage IA, visible uniquement en edit)
- [ ] Ajouter champ `specificFocus` (TextareaField, nullable, avec help text, visible uniquement en edit)
- [ ] Ajouter lien ou sous-onglet vers les `CategoryVisualPrompt` associés

### 2.6 `ImportCatalogueImagesCommand` — adapter pour SourcePhoto

**Fichier** : `src/Command/ImportCatalogueImagesCommand.php` (241 lignes actuellement)

**Comportement actuel** : parse un CSV, mappe les photos de `docs/Catalogue/photos/` vers les 3 champs VichUploader (wornPhoto, thumbnail, contextPhoto), convertit en WebP.

**Adaptation requise** :
- [ ] Ajouter une option `--as-source-photos` (ou remplacer le comportement par défaut)
- [ ] Quand activé : copier les images vers Flysystem (`var/storage/products/{id}/sources/`) au lieu de VichUploader
- [ ] Créer les entrées `SourcePhoto` en BDD (angle déduit : 1ère photo → FRONT, suivantes → OTHER)
- [ ] Conserver le mode VichUploader existant en fallback (ou vice versa selon le besoin)
- [ ] Mettre à jour le `visualStatus` du produit si au moins une source est importée

### 2.7 Configuration Messenger — passer en async

**Fichier** : `config/packages/messenger.yaml`

**État actuel** : transport `sync://` uniquement, pas de routing.

- [ ] Ajouter transport `gemini_async` (DSN Doctrine, queue `gemini_generation`, retry 2×, backoff 5s→30s)
- [ ] Ajouter transport `failed` (Doctrine, queue `failed`)
- [ ] Ajouter routing : `App\Message\GenerateVisualMessage` → `gemini_async`
- [ ] Conserver `sync://` comme default pour les messages existants (scheduler) qui fonctionnent déjà en sync
- [ ] Vérifier que `MESSENGER_TRANSPORT_DSN` est dans `.env`

### 2.8 Configuration Rate Limiter — ajouter Gemini

**Fichier** : `config/packages/rate_limiter.yaml`

**État actuel** : 4 politiques (contact_form, admin_login, registration, checkout).

- [ ] Ajouter politique `gemini_api` (token_bucket, 15 req/min)

### 2.9 Configuration services.yaml — bindings et paramètres

**Fichier** : `config/services.yaml`

- [ ] Ajouter paramètre `gemini.api_key` (`%env(GEMINI_API_KEY)%`)
- [ ] Ajouter paramètre `gemini.monthly_budget_usd` (`%env(float:GEMINI_MONTHLY_BUDGET_USD)%`)
- [ ] Ajouter paramètre `image_storage.path` (`%env(IMAGE_STORAGE_PATH)%`)
- [ ] Ajouter bindings pour les nouveaux services si nécessaire

### 2.10 Variables d'environnement — `.env`

- [ ] Ajouter `GEMINI_API_KEY=` (vide en dev, rempli en `.env.local`)
- [ ] Ajouter `GEMINI_MONTHLY_BUDGET_USD=30`
- [ ] Ajouter `IMAGE_STORAGE_PATH=%kernel.project_dir%/var/storage/products`
- [ ] Ajouter `MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0` (si pas déjà présent)

### 2.11 DashboardController — ajouter la section IA

**Fichier** : `src/Controller/Admin/DashboardController.php`

- [ ] Ajouter une section "Génération IA" dans le menu (après "Catalogue")
- [ ] Ajouter lien vers `CategoryVisualPromptCrudController` ("Prompts visuels")
- [ ] Ajouter lien vers `GeneratedVisualCrudController` ("Visuels générés")
- [ ] Optionnel : ajouter un compteur de visuels en attente de review sur l'item de menu

### 2.12 AppFixtures — enrichir pour les nouvelles entités

**Fichier** : `src/DataFixtures/AppFixtures.php` (1437 lignes)

- [ ] Ajouter les `preservationInstructions` aux ProductCategory racines existantes (Bague, Collier, Bracelet, Boucles d'oreilles, Coffrets)
- [ ] Ajouter les `CategoryVisualPrompt` (3 par catégorie racine × 4 catégories = 12 prompts) après le chargement des catégories
- [ ] Initialiser `visualStatus: DRAFT` pour tous les produits existants (valeur par défaut)

---

## 3. Ce qui n'existe pas et doit être créé from scratch

### 3.1 Enums (4 fichiers)

- [ ] **`src/Enum/VisualType.php`** — Cases : `Vignette`, `Worn`, `Lifestyle`. Méthodes : `label()`, `badgeColor()`. String-backed (`'vignette'`, `'worn'`, `'lifestyle'`).
- [ ] **`src/Enum/VisualStatus.php`** — Cases : `Generating`, `PendingReview`, `Approved`, `Rejected`, `Failed`. Méthodes : `label()`, `badgeColor()`.
- [ ] **`src/Enum/VisualWorkflowStatus.php`** — Cases : `Draft`, `PendingVisuals`, `ReadyForReview`, `VisualsApproved`. Méthodes : `label()`, `badgeColor()`.
- [ ] **`src/Enum/PhotoAngle.php`** — Cases : `Front`, `ThreeQuarter`, `Detail`, `Back`, `Other`. Méthodes : `label()`.

### 3.2 Entités (3 fichiers + repositories)

- [ ] **`src/Entity/CategoryVisualPrompt.php`**
  - Champs : id, visualType (enum), framing (text), staging (text), props (json), active (bool), version (int)
  - Relations : ManyToOne → ProductCategory (inversedBy: 'visualPrompts')
  - Contrainte unique : (category_id, visualType, version)
  - Repository : `src/Repository/CategoryVisualPromptRepository.php`

- [ ] **`src/Entity/SourcePhoto.php`**
  - Champs : id, path (string 500), position (int), angle (enum PhotoAngle)
  - Relations : ManyToOne → Product (inversedBy: 'sourcePhotos')
  - Repository : `src/Repository/SourcePhotoRepository.php`

- [ ] **`src/Entity/GeneratedVisual.php`**
  - Champs : id, type (enum VisualType), path (string 500), promptUsed (text), categoryPromptVersion (int), status (enum VisualStatus), variant (int), geminiRequestId (string 100, nullable), errorMessage (text, nullable), createdAt (datetime_immutable)
  - Relations : ManyToOne → Product (inversedBy: 'generatedVisuals')
  - Repository : `src/Repository/GeneratedVisualRepository.php` (avec méthodes : findByProductGroupedByType, countByStatus, findPendingReviewForProduct)

- [ ] **`src/Entity/GeminiUsageLog.php`**
  - Champs : id, costUsd (decimal 8,4), createdAt (datetime_immutable)
  - Pas de relations
  - Repository : `src/Repository/GeminiUsageLogRepository.php` (avec méthode : getCurrentMonthTotal)

### 3.3 Services — Client Gemini (4 fichiers)

- [ ] **`src/Service/Gemini/GeminiImageClient.php`**
  - Injection : HttpClientInterface, string $geminiApiKey
  - Méthode : `generate(string $prompt, array $imageBase64Array): GeminiResponse`
  - Retry sur HTTP 429 (backoff exponentiel : 2s, 4s, 8s, max 3 tentatives)
  - Construit le payload JSON (contents.parts avec text + inlineData)
  - Headers : x-goog-api-key, Content-Type: application/json

- [ ] **`src/Service/Gemini/GeminiResponse.php`**
  - DTO readonly : imageData (string base64), mimeType (string), requestId (?string)

- [ ] **`src/Service/Gemini/GeminiApiException.php`**
  - Exception custom avec code HTTP, message API, requestId

- [ ] **`src/Service/Gemini/BudgetGuard.php`**
  - Injection : GeminiUsageLogRepository, float $monthlyBudgetUsd
  - Méthodes : `ensureBudgetAvailable(): void` (throw BudgetExceededException), `recordCall(float $costUsd): void`, `getCurrentMonthSpending(): float`

### 3.4 Services — Prompt Builder (5 fichiers)

- [ ] **`src/Service/Prompt/PromptBuilder.php`**
  - Injection : BrandStyleProvider, TechnicalSpecsProvider, PromptFallbackProvider, CategoryVisualPromptRepository
  - Méthode : `buildForVisual(Product $product, VisualType $type): PromptResult`
  - Composition du prompt : metadata → brand style → preservation → framing → staging → technical specs

- [ ] **`src/Service/Prompt/PromptResult.php`**
  - DTO readonly : content (string), categoryPromptVersion (int), usedFallback (bool)

- [ ] **`src/Service/Prompt/BrandStyleProvider.php`**
  - Méthode : `getBrandStyle(): string`
  - Contenu en dur initialement (identité Alma Stella : "French jewelry maison aesthetic…")

- [ ] **`src/Service/Prompt/TechnicalSpecsProvider.php`**
  - Méthode : `getSpecsFor(VisualType $type): string`
  - Retourne format, résolution, style par type de visuel

- [ ] **`src/Service/Prompt/PromptFallbackProvider.php`**
  - Méthode : `getGenericPromptFor(VisualType $type): CategoryVisualPrompt`
  - Prompts génériques en dur (filet de sécurité). Version = 0 (identifie le fallback).

### 3.5 Services — Stockage images (1 fichier)

- [ ] **`src/Service/Visual/ImageStorage.php`**
  - Injection : FilesystemOperator (Flysystem)
  - Méthodes : `storeSourcePhoto(UploadedFile $file, Product $product): string`, `storeGeneratedVisual(string $base64Data, Product $product, VisualType $type, int $variant): string`, `delete(string $path): void`, `getPublicUrl(string $path): string`
  - Structure : `{product_id}/sources/` et `{product_id}/generated/`

### 3.6 Message / Handler (2 fichiers)

- [ ] **`src/Message/GenerateVisualMessage.php`**
  - Propriétés : productId (int), type (VisualType), variantNumber (int)
  - Pattern identique aux 4 messages existants

- [ ] **`src/MessageHandler/GenerateVisualHandler.php`**
  - Injection : RateLimiterFactory, BudgetGuard, ProductRepository, PromptBuilder, GeminiImageClient, ImageStorage, EntityManagerInterface
  - Séquence : rate limit → budget check → load product + sources → build prompt → call Gemini → store image → create GeneratedVisual (PENDING_REVIEW) → record cost → check if product ready for review

### 3.7 Logique d'approbation — copie vers VichUploader (1 fichier)

- [ ] **`src/Service/Visual/VisualApprovalHandler.php`**
  - Injection : ImageStorage (Flysystem), string $uploadDir (VichUploader path), EntityManagerInterface, ImageProcessor
  - Méthode : `approveVisual(GeneratedVisual $visual): void`
  - Séquence : lire image depuis Flysystem → copier dans `public/uploads/products/` → mettre à jour le champ VichUploader correspondant (thumbnail/wornPhoto/contextPhoto selon VisualType) → déclencher ImageProcessor (resize WebP) → flush → vérifier si tous les types ont un visuel approuvé → mettre à jour `visualStatus`

### 3.8 EasyAdmin — nouveaux CRUDs (2 fichiers)

- [ ] **`src/Controller/Admin/CategoryVisualPromptCrudController.php`**
  - Index : colonnes category, visualType (badge), version, active (toggle)
  - Filtres : par catégorie, par type, par active
  - Form : category (dropdown), visualType (choice), framing (textarea), staging (textarea), props (json/textarea), active (toggle), version (number)
  - Help texts pédagogiques expliquant l'impact de chaque champ sur le prompt
  - Action custom "Tester" (optionnelle, Phase 6+)

- [ ] **`src/Controller/Admin/GeneratedVisualCrudController.php`**
  - Index : vue en grille avec preview image, product name, type (badge), status (badge), variant, createdAt
  - Filtres : par produit, par type, par status
  - Detail/Edit : affichage côte à côte (photos sources | visuel généré), promptUsed (lecture seule), categoryPromptVersion (lecture seule)
  - Actions custom : `approve` (appelle VisualApprovalHandler), `reject` (status → REJECTED), `regenerate` (dispatche un nouveau GenerateVisualMessage)

### 3.9 Configuration Flysystem (1 fichier)

- [ ] **`config/packages/flysystem.yaml`** (nouveau)
  - Adapter : local, root `%env(IMAGE_STORAGE_PATH)%`
  - Filesystem : `default.storage` injectable via FilesystemOperator

### 3.10 Package Composer (1 installation)

- [ ] **`league/flysystem-bundle`** — `ddev exec composer require league/flysystem-bundle`

---

## 4. Décisions tranchées

> Toutes les ambiguïtés ont été résolues le 2026-04-17.

### 4.1 Upload multiple SourcePhoto → CollectionField EasyAdmin

**Décision** : FormType Symfony custom (`SourcePhotoType`) embarqué dans un `CollectionField`. Ajout/suppression dynamique de lignes, chaque ligne = upload fichier + sélecteur d'angle + position auto-incrémentée. Natif EasyAdmin, pas de JS custom.

### 4.2 Copie vers VichUploader à l'approbation → Pipeline VichUploader

**Décision** : créer un `UploadedFile` temporaire depuis le contenu Flysystem et le passer au setter VichUploader (`setThumbnailFile()`, `setWornPhotoFile()`, `setContextPhotoFile()`). Déclenche automatiquement `ImageUploadSubscriber` (conversion WebP + resize). Le nommage `SmartUniqueNamer` s'applique. Le pipeline existant est réutilisé intégralement.

### 4.3 Menu EasyAdmin → Nouvelle section "Génération IA"

**Décision** : section dédiée entre "Catalogue" et "Ventes" avec 2 items : "Prompts visuels" et "Visuels générés". Séparation claire des responsabilités.

### 4.4 Fallback prompt → Objet mémoire, jamais persisté

**Décision** : `PromptFallbackProvider` crée un `CategoryVisualPrompt` en mémoire (version = 0, jamais en BDD). Le `PromptBuilder` l'utilise si aucun prompt n'est trouvé pour la catégorie/type. `PromptResult.usedFallback = true` pour traçabilité. Pas de données fantômes en BDD.

### 4.5 Fixtures → Fichier séparé

**Décision** : créer `CategoryVisualPromptFixtures.php` avec `DependentFixtureInterface` (dépend de `AppFixtures`). Récupère les catégories via références, ajoute `preservationInstructions`, crée les 12 `CategoryVisualPrompt`. `AppFixtures.php` reste inchangé.

### 4.6 Ratio images Gemini → 4:5 directement

**Décision** : demander "portrait 4:5, 819×1024" dans les `TechnicalSpecs` du prompt. Gemini génère directement au bon ratio. Le pipeline VichUploader resize sans crop (zéro perte de contenu). Les 3 types utilisent le même ratio.

### 4.7 Images existantes → Import adapté, pas de migration

**Décision** : le site n'est pas en ligne. Pas de migration des images existantes. L'`ImportCatalogueImagesCommand` est adapté pour créer des `SourcePhoto` (Flysystem) au lieu des 3 champs VichUploader. Les champs VichUploader (thumbnail, wornPhoto, contextPhoto) restent vides après import. Ils ne sont remplis qu'à l'approbation d'un visuel IA. Chaque regénération d'environnement (`ddev delete + start + fixtures + import`) repart propre.

---

## 5. Phases d'implémentation

> **Terminologie** : la ROADMAP utilise "Milestone" (0-15+). Ici on utilise
> **"Phase"** pour les étapes d'implémentation de cette feature.
> La feature entière correspond au **Milestone 16** de la ROADMAP.
>
> **Gestion du contexte** : chaque phase se termine par un commit, un résumé
> compact et un `/clear`. Au redémarrage, relire ce plan + l'audit + le résumé
> de la phase précédente (écrit dans `docs/milestones/`).

---

### Phase 1 — Modèle de données

**Périmètre** : tout ce qui touche au schéma BDD, aux types, et au chargement des données initiales.

**Créations** :
- [ ] Enum `VisualType` (`src/Enum/VisualType.php`)
- [ ] Enum `VisualStatus` (`src/Enum/VisualStatus.php`)
- [ ] Enum `VisualWorkflowStatus` (`src/Enum/VisualWorkflowStatus.php`)
- [ ] Enum `PhotoAngle` (`src/Enum/PhotoAngle.php`)
- [ ] Entité `CategoryVisualPrompt` + repository
- [ ] Entité `SourcePhoto` + repository
- [ ] Entité `GeneratedVisual` + repository
- [ ] Entité `GeminiUsageLog` + repository

**Modifications** :
- [ ] `ProductCategory` : ajouter `preservationInstructions`, `specificFocus`, relation `OneToMany` → `CategoryVisualPrompt`
- [ ] `Product` : ajouter `visualStatus`, relations `OneToMany` → `SourcePhoto` et `GeneratedVisual`
- [ ] Migration existante (`Version20260408140632.php`) : ajouter colonnes + tables
- [ ] `AppFixtures` : ajouter les références aux catégories pour les fixtures dépendantes

**Installations** :
- [ ] `composer require league/flysystem-bundle`
- [ ] Config `config/packages/flysystem.yaml`

**Fixtures** :
- [ ] `CategoryVisualPromptFixtures.php` (12 prompts : 4 catégories × 3 types)

**Vérification de sortie** :
- [ ] `ddev delete --omit-snapshot && ddev start`
- [ ] `ddev exec php bin/console doctrine:migrations:migrate` — pas d'erreur
- [ ] `ddev exec php bin/console doctrine:fixtures:load` — fixtures OK
- [ ] `ddev exec vendor/bin/phpstan analyse` — niveau 6, zéro erreur
- [ ] `ddev exec vendor/bin/php-cs-fixer fix --dry-run` — pas de diff
- [ ] Vérifier en BDD : tables créées, 12 CategoryVisualPrompt, catégories avec preservationInstructions

**⏸ STOP — commit + résumé dans `docs/milestones/M16_PHASE1_DONE.md` + `/clear`**

---

### Phase 2 — Cerveau IA + Client Gemini + Queue

**Périmètre** : toute la logique métier IA (prompt building, appel API, gestion budget, queue async).

**Prérequis** : Phase 1 terminée. Relire `docs/milestones/M16_PHASE1_DONE.md` au redémarrage.

**Créations — Services Prompt** :
- [ ] `src/Service/Prompt/BrandStyleProvider.php`
- [ ] `src/Service/Prompt/TechnicalSpecsProvider.php` (ratio 4:5, 819×1024)
- [ ] `src/Service/Prompt/PromptFallbackProvider.php` (objet mémoire, version 0)
- [ ] `src/Service/Prompt/PromptResult.php` (DTO readonly)
- [ ] `src/Service/Prompt/PromptBuilder.php` (composition : metadata → brand → preservation → framing → staging → specs)

**Créations — Client Gemini** :
- [ ] `src/Service/Gemini/GeminiImageClient.php` (HTTP client, retry 429, payload JSON)
- [ ] `src/Service/Gemini/GeminiResponse.php` (DTO readonly)
- [ ] `src/Service/Gemini/GeminiApiException.php`
- [ ] `src/Service/Gemini/BudgetGuard.php` (vérifie budget mensuel via GeminiUsageLog)

**Créations — Queue async** :
- [ ] `src/Message/GenerateVisualMessage.php`
- [ ] `src/MessageHandler/GenerateVisualHandler.php` (rate limit → budget → prompt → Gemini → store → persist)
- [ ] `src/Service/Visual/ImageStorage.php` (Flysystem : store sources, store generated, delete, getUrl)

**Modifications config** :
- [ ] `config/packages/messenger.yaml` : transport `gemini_async` (Doctrine), transport `failed`, routing
- [ ] `config/packages/rate_limiter.yaml` : politique `gemini_api` (token_bucket, 15/min)
- [ ] `config/services.yaml` : bindings `$geminiApiKey`, `$monthlyBudgetUsd`
- [ ] `.env` : `GEMINI_API_KEY`, `GEMINI_MONTHLY_BUDGET_USD`, `IMAGE_STORAGE_PATH`, `MESSENGER_TRANSPORT_DSN`

**Vérification de sortie** :
- [ ] `ddev exec vendor/bin/phpstan analyse` — zéro erreur
- [ ] `ddev exec vendor/bin/php-cs-fixer fix --dry-run` — pas de diff
- [ ] Test manuel : instancier un `PromptBuilder`, vérifier le prompt généré pour un produit test (via tinker ou commande temporaire)
- [ ] Vérifier que le worker Messenger démarre : `ddev exec php bin/console messenger:consume gemini_async --limit=1 -vv`

**⏸ STOP — commit + résumé dans `docs/milestones/M16_PHASE2_DONE.md` + `/clear`**

---

### Phase 3 — Back-office EasyAdmin

**Périmètre** : tous les CRUDs, le formulaire SourcePhoto, le bouton de génération, la validation des visuels, le menu admin.

**Prérequis** : Phase 2 terminée. Relire `docs/milestones/M16_PHASE2_DONE.md` au redémarrage.

**Créations** :
- [ ] `src/Form/SourcePhotoType.php` (FormType : FileType + EnumType angle + HiddenType position)
- [ ] `src/Controller/Admin/CategoryVisualPromptCrudController.php` (CRUD standard, help texts, filtres par catégorie/type)
- [ ] `src/Controller/Admin/GeneratedVisualCrudController.php` (vue grille, preview, actions approve/reject/regenerate)
- [ ] `src/Service/Visual/VisualApprovalHandler.php` (copie Flysystem → VichUploader via UploadedFile temporaire)

**Modifications** :
- [ ] `src/Controller/Admin/ProductCrudController.php` :
  - Ajouter fieldset "Photos sources" avec `CollectionField` → `SourcePhotoType`
  - Ajouter champ `visualStatus` (badge) en index et en edit
  - Ajouter compteur visuels en index
  - Ajouter filtre `visualStatus`
  - Ajouter action custom `generateVisuals` (dispatche 9 messages)
  - Ajouter fieldset "Visuels générés" (affichage groupé par type)
- [ ] `src/Controller/Admin/ProductCategoryCrudController.php` :
  - Ajouter champs `preservationInstructions` et `specificFocus` (textarea, edit only)
  - Ajouter lien vers les prompts visuels associés
- [ ] `src/Controller/Admin/DashboardController.php` :
  - Ajouter section "Génération IA" au menu (entre Catalogue et Ventes)
  - Items : "Prompts visuels" + "Visuels générés"

**Vérification de sortie** :
- [ ] `ddev exec vendor/bin/phpstan analyse` — zéro erreur
- [ ] `ddev exec vendor/bin/php-cs-fixer fix --dry-run` — pas de diff
- [ ] `ddev exec php bin/console tailwind:build && ddev exec php bin/console asset-map:compile`
- [ ] Navigation admin : nouvelle section visible, CRUDs accessibles
- [ ] Créer un produit test avec SourcePhotos via le CollectionField
- [ ] Vérifier l'affichage des CategoryVisualPrompt (12 entrées via fixtures)
- [ ] Tester le bouton "Générer les visuels" (dispatche les messages, sans clé API ça échoue proprement)

**⏸ STOP — commit + résumé dans `docs/milestones/M16_PHASE3_DONE.md` + `/clear`**

---

### Phase 4 — Import adapté + finitions

**Périmètre** : adapter la commande d'import images pour SourcePhoto, variables d'environnement, vérification end-to-end.

**Prérequis** : Phase 3 terminée. Relire `docs/milestones/M16_PHASE3_DONE.md` au redémarrage.

**Modifications** :
- [ ] `src/Command/ImportCatalogueImagesCommand.php` :
  - Adapter pour créer des `SourcePhoto` via Flysystem au lieu des 3 champs VichUploader
  - Copier les images dans `var/storage/products/{id}/sources/`
  - Créer les entrées `SourcePhoto` en BDD (angle déduit, position auto)
  - Mettre à jour `visualStatus` du produit
  - Conserver `--dry-run`

**Vérifications** :
- [ ] `ddev delete --omit-snapshot && ddev start` (environnement propre)
- [ ] `ddev exec php bin/console doctrine:migrations:migrate`
- [ ] `ddev exec php bin/console doctrine:fixtures:load`
- [ ] Lancer l'import : `ddev exec php bin/console app:import:catalogue-images`
- [ ] Vérifier en BDD : SourcePhoto créées pour chaque produit
- [ ] Vérifier dans Flysystem : fichiers présents dans `var/storage/products/`
- [ ] `ddev exec vendor/bin/phpstan analyse` — zéro erreur
- [ ] `ddev exec vendor/bin/php-cs-fixer fix --dry-run` — pas de diff
- [ ] Test end-to-end complet :
  1. Fixtures chargées ✓
  2. Import images ✓ (SourcePhoto en BDD + Flysystem)
  3. Admin : produit visible avec ses sources ✓
  4. Admin : CategoryVisualPrompt visibles ✓
  5. Admin : bouton "Générer" dispatche les messages ✓
  6. Worker Messenger consomme un message ✓ (avec ou sans clé API)

**⏸ STOP — commit + résumé dans `docs/milestones/M16_PHASE4_DONE.md` + `/clear` — MILESTONE 16 TERMINÉ**

---

### Vue d'ensemble

```
ROADMAP Milestone 16 — Catalogue IA (génération visuels)
│
├── Phase 1  Modèle de données     enums, entités, migration, fixtures, Flysystem
│   ⏸ commit + résumé M16_PHASE1_DONE.md + /clear
│
├── Phase 2  Cerveau IA + Queue    services prompt, client Gemini, budget, messenger async
│   ⏸ commit + résumé M16_PHASE2_DONE.md + /clear
│
├── Phase 3  Back-office EasyAdmin CRUDs, formulaires, actions, menu, approval handler
│   ⏸ commit + résumé M16_PHASE3_DONE.md + /clear
│
└── Phase 4  Import + finitions    commande adaptée, vérification end-to-end
    ⏸ commit + résumé M16_PHASE4_DONE.md + /clear — DONE
```

### Format du résumé de phase (`docs/milestones/M16_PHASEX_DONE.md`)

Chaque fichier de résumé doit contenir :

```markdown
# Milestone 16 — Phase X — [Titre] — TERMINÉ

## Fichiers créés
- chemin/fichier.php — description courte

## Fichiers modifiés
- chemin/fichier.php — ce qui a changé

## État de la BDD
- Tables ajoutées : ...
- Colonnes ajoutées : ...

## Ce qui fonctionne
- point vérifié 1
- point vérifié 2

## Point d'attention pour la phase suivante
- détail important à ne pas oublier
```
