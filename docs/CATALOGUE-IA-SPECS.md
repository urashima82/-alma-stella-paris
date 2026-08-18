# Spécifications — Feature Catalogue IA

> **⚠️ ARCHIVE — instantané d'avril 2026.** Spécifications d'origine du
> Milestone 16, aujourd'hui livré ; l'implémentation a divergé sur certains
> points pendant le développement. Le code fait foi, les invariants sont dans
> `ARCHITECTURE.md`.

> Document de spécifications techniques pour l'intégration de la génération de visuels produits par IA (Gemini 2.5 Flash Image) dans la boutique e-commerce.
>
> **Destinataire** : Claude Code
> **Stack cible** : Symfony 7.x, PHP 8.3+, Doctrine, EasyAdmin, Messenger

---

## 1. Contexte projet

### Stack technique

- **Framework** : Symfony 7.x
- **PHP** : 8.3+
- **ORM** : Doctrine
- **Back-office** : EasyAdmin
- **Queue** : Symfony Messenger
- **Base de données** : (à préciser — MySQL/PostgreSQL)

### État actuel du projet

- Boutique e-commerce fonctionnelle (catalogue, panier, paiement)
- Site pas encore en ligne, en phase de finalisation
- La **refonte de la logique de stockage des images** a été effectuée en amont de cette feature (prérequis validé)

### Objectif de cette feature

Permettre à la gérante (sœur du dev) d'uploader plusieurs photos "brutes" d'un bijou (prises au smartphone, qualité amateur) et d'obtenir automatiquement **3 visuels professionnels** générés par IA :

1. **Vignette produit** : packshot professionnel avec ambiance studio (fidélité maximale au bijou)
2. **Visuel porté** : le bijou porté par un modèle
3. **Visuel lifestyle** : mise en contexte ambiance

Chaque type de visuel est généré en **3 variantes** pour permettre la sélection manuelle.

### Modèle IA utilisé

- **Modèle** : Gemini 2.5 Flash Image (alias "Nano Banana")
- **Identifiant API** : `gemini-2.5-flash-image`
- **Endpoint** : `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image:generateContent`
- **Tarification** : ~0,039 $ par image générée (Tier 1 payant)
- **Doc officielle** : https://ai.google.dev/gemini-api/docs/image-generation

---

## 2. ⚠️ Décisions d'architecture non-négociables

Ces choix ont été validés lors de la phase de conception et ne doivent pas être remis en cause :

- **Prompts stockés en base de données** (pas en YAML) pour permettre à la gérante d'éditer les prompts via le back-office sans déploiement, et d'ajouter de nouvelles catégories qui créent leurs propres prompts.
- **Génération asynchrone obligatoire via Messenger** (rate limits Gemini + UX : l'utilisateur ne doit pas attendre 30 secondes sur un formulaire).
- **Validation humaine obligatoire** avant publication d'un visuel sur la boutique. Aucune publication auto, aucune exception.
- **Photos sources conservées** en base : elles peuvent servir à regénérer plus tard avec de meilleurs prompts.
- **Versioning des prompts** : chaque `CategoryVisualPrompt` a un champ `version`, et chaque `GeneratedVisual` stocke le prompt complet utilisé (traçabilité + debug).
- **Fallback obligatoire** si une catégorie n'a pas encore de prompt configuré : prompt générique en dur dans le code (pas d'erreur, pas de crash).

---

## 3. Prérequis à valider avant implémentation

### Côté infrastructure

- [ ] Compte Google Cloud créé
- [ ] Facturation activée sur le compte (Tier 1 API)
- [ ] Clé API Gemini générée sur https://aistudio.google.com/apikey
- [ ] Budget alert configuré (recommandé : 30 $/mois initialement)
- [ ] Storage pour images (local ou S3/Scaleway/Bunny) décidé et configuré

### Variables d'environnement (.env.local)

```dotenv
GEMINI_API_KEY=xxxxxxxxxxxxxxxxxxxx
GEMINI_MONTHLY_BUDGET_USD=30
IMAGE_STORAGE_PATH=%kernel.project_dir%/var/storage/products
# ou pour S3/Scaleway/etc. : configurer Flysystem adapter
```

### Packages Composer à installer

```bash
composer require symfony/http-client
composer require symfony/messenger
composer require symfony/rate-limiter
composer require symfony/cache
composer require league/flysystem-bundle  # pour abstraction stockage
composer require easycorp/easyadmin-bundle  # si pas déjà installé
composer require league/csv  # pour import catalogue initial
```

---

## 4. Modèle de données

### Diagramme relationnel (synthèse)

```
Category ──┬── Subcategory (n)
           └── CategoryVisualPrompt (n) — une par VisualType
           
Product ───┬── Category (n-1)
           ├── Subcategory (n-1)
           ├── Stone (n-1, nullable)
           ├── SourcePhoto (1-n)
           └── GeneratedVisual (1-n)

Stone (référentiel partagé)
```

### Entité `Category`

| Champ | Type | Notes |
|-------|------|-------|
| id | int | PK auto |
| name | string(100) | unique, ex: "Bague" |
| slug | string(100) | unique |
| preservationInstructions | text | Instructions EN de préservation spécifiques à cette catégorie |

Relations :
- `OneToMany` vers `Subcategory` (mappedBy: 'category')
- `OneToMany` vers `CategoryVisualPrompt` (mappedBy: 'category', cascade: persist)

Méthodes utiles :
- `getVisualPromptFor(VisualType $type): ?CategoryVisualPrompt`
- `hasVisualPromptFor(VisualType $type): bool`

### Entité `Subcategory`

| Champ | Type | Notes |
|-------|------|-------|
| id | int | PK auto |
| category_id | int | FK vers Category |
| name | string(150) | ex: "Bague chevalière" |
| specificFocus | text, nullable | Instructions EN spécifiques (optionnel) |

### Entité `Stone`

| Champ | Type | Notes |
|-------|------|-------|
| id | int | PK auto |
| name | string(100) | unique, ex: "Onyx noir" |
| visualDescription | text | Description EN optimisée pour l'IA |
| mood | string(255), nullable | ex: "dramatic, mysterious" |
| complementaryPalette | json | tableau de couleurs suggérées |

**Cas spécial** : créer une entité `Stone` nommée `"INCONNU"` avec :
- `visualDescription` : `"stone as shown in reference images, do not alter or infer type"`
- `mood` : null
- `complementaryPalette` : `[]`

### Entité `CategoryVisualPrompt`

| Champ | Type | Notes |
|-------|------|-------|
| id | int | PK auto |
| category_id | int | FK vers Category |
| visualType | string (enum) | VIGNETTE, WORN, LIFESTYLE |
| framing | text | Instructions EN de cadrage |
| staging | text | Instructions EN de mise en scène |
| props | json | Tableau d'éléments d'ambiance |
| active | bool | Permet de désactiver sans supprimer |
| version | int | Default 1, incrémenté manuellement |

**Contrainte unique** : (category_id, visualType, version)

### Entité `Product`

| Champ | Type | Notes |
|-------|------|-------|
| id | int | PK auto |
| name | string(255) | Nom commercial |
| category_id | int | FK |
| subcategory_id | int | FK |
| stone_id | int, nullable | FK vers Stone |
| collection | string(100) | Default "Collection France" |
| description | text | Description marketing (NON utilisée dans prompts IA) |
| priceEur | decimal(10,2) | |
| status | string (enum) | DRAFT, PENDING_VISUALS, READY_FOR_REVIEW, PUBLISHED |

Relations :
- `OneToMany` vers `SourcePhoto`
- `OneToMany` vers `GeneratedVisual`

### Entité `SourcePhoto`

| Champ | Type | Notes |
|-------|------|-------|
| id | int | PK auto |
| product_id | int | FK |
| path | string(500) | Chemin dans le storage |
| position | int | Ordre d'upload |
| angle | string (enum) | FRONT, THREE_QUARTER, DETAIL, BACK, OTHER |

### Entité `GeneratedVisual`

| Champ | Type | Notes |
|-------|------|-------|
| id | int | PK auto |
| product_id | int | FK |
| type | string (enum) | VisualType |
| path | string(500) | Chemin de l'image générée |
| promptUsed | text | Prompt complet utilisé (traçabilité) |
| categoryPromptVersion | int | Version du prompt catégorie au moment de la gen |
| status | string (enum) | GENERATING, PENDING_REVIEW, APPROVED, REJECTED, FAILED |
| variant | int | 1, 2, 3 |
| geminiRequestId | string(100), nullable | Pour debug |
| errorMessage | text, nullable | Si status FAILED |
| createdAt | datetime_immutable | |

### Enums PHP

```php
// src/Enum/VisualType.php
enum VisualType: string
{
    case VIGNETTE = 'vignette';
    case WORN = 'worn';
    case LIFESTYLE = 'lifestyle';
}

// src/Enum/VisualStatus.php
enum VisualStatus: string
{
    case GENERATING = 'generating';
    case PENDING_REVIEW = 'pending_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case FAILED = 'failed';
}

// src/Enum/ProductStatus.php
enum ProductStatus: string
{
    case DRAFT = 'draft';
    case PENDING_VISUALS = 'pending_visuals';
    case READY_FOR_REVIEW = 'ready_for_review';
    case PUBLISHED = 'published';
}

// src/Enum/PhotoAngle.php
enum PhotoAngle: string
{
    case FRONT = 'front';
    case THREE_QUARTER = 'three_quarter';
    case DETAIL = 'detail';
    case BACK = 'back';
    case OTHER = 'other';
}
```

---

## 5. Architecture applicative

### Arborescence cible

```
src/
├── Entity/
│   ├── Category.php
│   ├── Subcategory.php
│   ├── Stone.php
│   ├── CategoryVisualPrompt.php
│   ├── Product.php
│   ├── SourcePhoto.php
│   └── GeneratedVisual.php
│
├── Enum/
│   ├── VisualType.php
│   ├── VisualStatus.php
│   ├── ProductStatus.php
│   └── PhotoAngle.php
│
├── Repository/
│   ├── CategoryRepository.php
│   ├── ProductRepository.php
│   └── GeneratedVisualRepository.php
│
├── Service/
│   ├── Gemini/
│   │   ├── GeminiImageClient.php
│   │   ├── GeminiResponse.php
│   │   ├── GeminiApiException.php
│   │   └── BudgetGuard.php
│   │
│   ├── Prompt/
│   │   ├── PromptBuilder.php
│   │   ├── PromptResult.php
│   │   ├── BrandStyleProvider.php
│   │   ├── TechnicalSpecsProvider.php
│   │   └── PromptFallbackProvider.php
│   │
│   └── Visual/
│       ├── ImageStorage.php
│       └── VisualGenerationOrchestrator.php
│
├── Message/
│   └── GenerateVisualMessage.php
│
├── MessageHandler/
│   └── GenerateVisualHandler.php
│
├── Controller/
│   └── Admin/
│       └── (CRUDs EasyAdmin)
│
├── Command/
│   ├── ImportCatalogueCommand.php
│   └── MigrateAllVisualsCommand.php
│
└── DataFixtures/
    ├── CategoryFixtures.php
    ├── StoneFixtures.php
    └── CategoryVisualPromptFixtures.php
```

### Flux de données

```
1. UPLOAD
   Back-office EasyAdmin → ProductCrudController
   → Upload de N SourcePhoto
   → Création du Product avec status=PENDING_VISUALS
   → Dispatch 9 messages (3 types × 3 variantes)

2. QUEUE
   Messenger transport 'gemini_async'
   → GenerateVisualHandler (1 message à la fois)
   → RateLimiter Symfony (token_bucket: 15/min)
   → BudgetGuard (vérifie budget mensuel)

3. GÉNÉRATION
   PromptBuilder.buildForVisual(Product, VisualType)
   → GeminiImageClient.generate(prompt, sourcePhotos)
   → Retry automatique sur HTTP 429 (backoff exponentiel)
   → Stockage image générée via ImageStorage
   → Création GeneratedVisual status=PENDING_REVIEW

4. VALIDATION (humaine)
   Back-office EasyAdmin → GeneratedVisualCrudController
   → Vue comparative : photo originale | variantes 1/2/3
   → Action approve/reject/regenerate
   → Si 3 variantes FAILED : alerte email admin

5. PUBLICATION
   Quand chaque type a au moins 1 visuel APPROVED
   → Product.status = READY_FOR_REVIEW
   → Action manuelle "Publier" → status=PUBLISHED
```

---

## 6. Spécifications détaillées des services

### `GeminiImageClient`

**Responsabilité** : encapsuler les appels HTTP à l'API Gemini avec retry, gestion d'erreurs, parsing de réponse.

**Méthode principale** :
```php
public function generate(string $prompt, array $imageBase64Array): GeminiResponse
```

**Comportement** :
- Construit le payload JSON attendu par l'API Gemini
- Gère le retry sur HTTP 429 (backoff exponentiel : 2s, 4s, 8s ; max 3 tentatives)
- Retourne une `GeminiResponse` contenant l'image générée en base64 + métadonnées
- Lève `GeminiApiException` sur erreur définitive

**Format du payload** :
```json
{
  "contents": [{
    "parts": [
      {"text": "<prompt complet>"},
      {"inlineData": {"mimeType": "image/jpeg", "data": "<base64>"}},
      {"inlineData": {"mimeType": "image/jpeg", "data": "<base64>"}}
    ]
  }],
  "generationConfig": {
    "responseModalities": ["IMAGE"]
  }
}
```

**Headers** :
```
x-goog-api-key: <GEMINI_API_KEY>
Content-Type: application/json
```

### `BudgetGuard`

**Responsabilité** : empêcher un dépassement de budget mensuel en cas de boucle buggée ou d'abus.

**Méthodes** :
```php
public function ensureBudgetAvailable(): void  // throw BudgetExceededException
public function recordCall(float $estimatedCostUsd): void
public function getCurrentMonthSpending(): float
```

**Implémentation** :
- Table dédiée `gemini_usage_log` (date, cost_usd)
- Ou compteur Redis si disponible
- Seuil configuré via env `GEMINI_MONTHLY_BUDGET_USD`

### `PromptBuilder`

**Responsabilité** : composer le prompt final à envoyer à Gemini en combinant les fragments de BDD + éléments statiques.

**Méthode principale** :
```php
public function buildForVisual(Product $product, VisualType $type): PromptResult
```

**Retourne** :
```php
final readonly class PromptResult
{
    public function __construct(
        public string $content,         // le prompt complet
        public int $categoryPromptVersion,
        public bool $usedFallback,
    ) {}
}
```

**Ordre de composition du prompt** :
1. Metadata block (type, pierre si connue)
2. Brand style (depuis `BrandStyleProvider`)
3. Preservation block (depuis `Category` + `Subcategory`)
4. Framing block (depuis `CategoryVisualPrompt`)
5. Staging block (depuis `CategoryVisualPrompt` + `Stone`)
6. Technical specs (depuis `TechnicalSpecsProvider`)

**Exemple de prompt final généré** :
```
PRODUCT METADATA:
- Type: Bague > Bague chevalière
- Stone: deep glossy jet-black stone, polished mirror finish
- Mood: dramatic, mysterious

BRAND IDENTITY: French jewelry maison aesthetic. Timeless elegance, natural warm lighting, neutral sophisticated palette (ivory, warm beige, soft gold undertones), editorial catalog quality reminiscent of Dinh Van and Mauboussin.

PRESERVATION REQUIREMENTS:
PRESERVE EXACTLY: the band thickness, the exact setting, any engravings, the central element, and all metal finishes.

SPECIFIC FOCUS: emphasize the flat signet face and engraving

FRAMING:
close-up, ring shown from frontal 3/4 angle

STAGING:
- Scene: floating on subtle shadow with soft professional lighting
- Suggested elements: marble surface, silk fabric, velvet cushion
- Stone rendering: deep glossy jet-black stone, polished mirror finish
- Overall mood: dramatic, mysterious
- Color palette: ivory, warm gold

TECHNICAL SPECS:
- Output format: square 1:1
- Resolution: 1024x1024
- Style: editorial jewelry catalog, magazine quality
- Sharp focus on the jewelry, professional product photography
```

### `PromptFallbackProvider`

**Responsabilité** : fournir un prompt générique si une catégorie n'a pas encore de `CategoryVisualPrompt` configuré pour un type donné.

**Codé en dur** (pas en BDD, c'est le filet de sécurité) :

```php
public function getGenericPromptFor(VisualType $type): CategoryVisualPrompt
{
    $prompt = new CategoryVisualPrompt();
    $prompt->setVisualType($type);
    $prompt->setVersion(0); // version 0 = fallback
    
    match($type) {
        VisualType::VIGNETTE => [
            $prompt->setFraming("centered product shot, full piece visible"),
            $prompt->setStaging("on clean neutral surface with soft professional lighting"),
            $prompt->setProps(['white surface', 'soft shadow']),
        ],
        VisualType::WORN => [
            $prompt->setFraming("product worn by a model in natural pose"),
            $prompt->setStaging("neutral background, editorial portrait style"),
            $prompt->setProps(['soft natural light']),
        ],
        VisualType::LIFESTYLE => [
            $prompt->setFraming("product in contextual scene, shallow depth of field"),
            $prompt->setStaging("elegant everyday setting with ambient warm light"),
            $prompt->setProps(['natural materials', 'soft bokeh']),
        ],
    };
    
    return $prompt;
}
```

### `BrandStyleProvider`

**Responsabilité** : fournir le bloc d'identité marque, commun à tous les prompts.

**Version initiale (en dur)** :
```
BRAND IDENTITY: French jewelry maison aesthetic. Timeless elegance, natural warm lighting, neutral sophisticated palette (ivory, warm beige, soft gold undertones), editorial catalog quality reminiscent of Dinh Van and Mauboussin.
```

**Évolution possible** : déplacer en BDD si besoin d'édition, mais pas prioritaire.

### `TechnicalSpecsProvider`

**Responsabilité** : fournir les spécifications techniques par type de visuel.

Retourne un bloc texte adapté à chaque `VisualType` (format, résolution, style).

### `ImageStorage`

**Responsabilité** : abstraction du stockage d'images via Flysystem.

**Méthodes** :
```php
public function storeSourcePhoto(UploadedFile $file, Product $product): string
public function storeGeneratedVisual(string $base64Data, Product $product, VisualType $type, int $variant): string
public function delete(string $path): void
public function getPublicUrl(string $path): string
```

**Structure recommandée du storage** :
```
products/
  {product_id}/
    sources/
      01-front.jpg
      02-three-quarter.jpg
      ...
    generated/
      vignette-v1.webp
      vignette-v2.webp
      vignette-v3.webp
      worn-v1.webp
      ...
```

### `GenerateVisualHandler`

**Responsabilité** : traiter un message de génération (un seul visuel à la fois).

**Pseudo-code** :
```php
public function __invoke(GenerateVisualMessage $message): void
{
    // 1. Rate limiting
    if (!$this->rateLimiter->consume(1)->isAccepted()) {
        throw new RecoverableMessageHandlingException('Rate limit');
    }
    
    // 2. Budget check
    $this->budgetGuard->ensureBudgetAvailable();
    
    // 3. Charger produit + sources
    $product = $this->products->find($message->productId);
    $sourcesBase64 = $this->loadSourcesAsBase64($product);
    
    // 4. Construire le prompt
    $promptResult = $this->promptBuilder->buildForVisual($product, $message->type);
    
    // 5. Appel Gemini
    try {
        $response = $this->gemini->generate($promptResult->content, $sourcesBase64);
    } catch (GeminiApiException $e) {
        $this->createFailedVisual($message, $promptResult, $e);
        return;
    }
    
    // 6. Stocker image générée
    $path = $this->storage->storeGeneratedVisual(
        $response->getImageData(),
        $product,
        $message->type,
        $message->variantNumber
    );
    
    // 7. Créer GeneratedVisual en BDD (status PENDING_REVIEW)
    $visual = new GeneratedVisual(...);
    $this->em->persist($visual);
    
    // 8. Enregistrer le coût
    $this->budgetGuard->recordCall(0.04);
    
    // 9. Vérifier si produit prêt pour review
    if ($this->isProductReadyForReview($product)) {
        $product->setStatus(ProductStatus::READY_FOR_REVIEW);
    }
    
    $this->em->flush();
}
```

---

## 7. Configuration Messenger

### config/packages/messenger.yaml

```yaml
framework:
    messenger:
        failure_transport: failed
        
        transports:
            gemini_async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    queue_name: gemini_generation
                retry_strategy:
                    max_retries: 2
                    delay: 5000
                    multiplier: 2
                    max_delay: 30000
            
            failed:
                dsn: 'doctrine://default?queue_name=failed'

        routing:
            App\Message\GenerateVisualMessage: gemini_async
```

### config/packages/rate_limiter.yaml

```yaml
framework:
    rate_limiter:
        gemini_api:
            policy: 'token_bucket'
            limit: 15
            rate: { interval: '1 minute', amount: 15 }
```

### Lancement du worker

En dev :
```bash
symfony console messenger:consume gemini_async -vv
```

En prod : via supervisor ou systemd (à configurer).

---

## 8. Back-office EasyAdmin

### Dashboards CRUD à créer

- **`CategoryCrudController`** : CRUD standard, avec sous-onglet pour gérer les `CategoryVisualPrompt` associés
- **`SubcategoryCrudController`** : CRUD standard
- **`StoneCrudController`** : CRUD standard avec help text pédagogique pour `visualDescription`
- **`CategoryVisualPromptCrudController`** : CRUD avec action custom "Tester" (lance une génération sur un produit exemple)
- **`ProductCrudController`** : le plus complexe, voir ci-dessous
- **`GeneratedVisualCrudController`** : interface de validation, voir ci-dessous

### `ProductCrudController`

**Page edit/new** :
- Champs standards (name, category, subcategory, stone, etc.)
- Champ upload multiple pour `SourcePhoto` (avec preview)
- Bouton "Générer les visuels" qui dispatche les 9 messages Messenger
- Affichage des `GeneratedVisual` existants groupés par type

**Page index** :
- Filtres par `status` (DRAFT, PENDING_VISUALS, READY_FOR_REVIEW, PUBLISHED)
- Compteur visuels validés / visuels attendus

### `GeneratedVisualCrudController`

**Page index** :
- Filtres : par produit, par type, par status
- Vue en grille (pas tableau) avec preview de l'image

**Page edit** :
- Affichage côte à côte : photos sources originales | visuel généré
- Actions : Approve, Reject, Regenerate (dispatche un nouveau message)
- Champ lecture seule : `promptUsed` (pour debug)
- Affichage de la `categoryPromptVersion`

---

## 9. Commandes CLI

### `app:import:catalogue`

**Objectif** : importer le CSV initial (fourni : `Catalogue_bijoux_nouveau.csv`) pour peupler la BDD.

**Options** :
- `--file=` : chemin du CSV (default: var/import/catalogue.csv)
- `--dry-run` : simuler sans persister

**Comportement** :
1. Parse CSV (encodage Windows-1252, séparateur `;`)
2. Crée `Category`, `Subcategory`, `Stone` uniques si inexistantes
3. Crée les `Product` liés
4. Rapport final : X catégories créées, Y produits importés, Z pierres "INCONNU" à compléter
5. **Ne pas créer de `SourcePhoto`** : les photos seront uploadées depuis le back-office

### `app:migrate:generate-all-visuals`

**Objectif** : migration one-shot du catalogue existant (222 produits × 9 visuels = ~2000 appels Gemini).

**Options** :
- `--batch-size=10` : nombre de produits traités par minute
- `--delay=60` : délai entre batches (secondes)
- `--product-id=` : traiter un seul produit (pour tester)
- `--resume` : reprendre après un crash (skip les produits déjà traités)

**Comportement** :
1. Sélectionne les produits en status DRAFT qui ont au moins une SourcePhoto
2. Pour chaque batch : dispatche les messages Messenger
3. Attend le délai
4. Continue jusqu'à épuisement
5. Log progression (Progress bar Symfony)
6. Rapport final : X succès, Y échecs, coût estimé

---

## 10. Fixtures de seeding initial

### `CategoryFixtures` + `CategoryVisualPromptFixtures`

Créer les 4 catégories du catalogue (`Bague`, `Collier`, `Bracelet`, `Boucles d'oreilles`) avec leurs prompts initiaux.

**Exemple pour `Bague`** :

```php
$bague = new Category();
$bague->setName('Bague');
$bague->setSlug('bague');
$bague->setPreservationInstructions(
    'PRESERVE EXACTLY: the band thickness, the exact setting, any engravings, the central element, and all metal finishes. Do not modify ring size proportions or stone placement.'
);

// Prompt VIGNETTE
$vignette = new CategoryVisualPrompt();
$vignette->setCategory($bague);
$vignette->setVisualType(VisualType::VIGNETTE);
$vignette->setFraming('close-up, ring shown from frontal 3/4 angle, centered in frame');
$vignette->setStaging('floating on subtle shadow with soft professional lighting, slight elevation');
$vignette->setProps(['polished marble surface', 'draped silk fabric', 'velvet cushion']);
$vignette->setActive(true);
$vignette->setVersion(1);

// Prompt WORN
$worn = new CategoryVisualPrompt();
$worn->setCategory($bague);
$worn->setVisualType(VisualType::WORN);
$worn->setFraming('on a feminine hand with natural elegant pose, hand framed from wrist, waist-up composition');
$worn->setStaging('soft neutral background with warm undertones, editorial portrait style, shallow depth of field');
$worn->setProps(['softly blurred indoor scene', 'warm ambient light']);

// Prompt LIFESTYLE
$lifestyle = new CategoryVisualPrompt();
$lifestyle->setCategory($bague);
$lifestyle->setVisualType(VisualType::LIFESTYLE);
$lifestyle->setFraming('ring as clear focal point, styled within a contextual scene');
$lifestyle->setStaging('vanity table scene with perfume bottles and jewelry, soft morning light from window');
$lifestyle->setProps(['vintage perfume bottle', 'linen fabric', 'dried flowers', 'gold tray']);
```

**À créer pour les 4 catégories** (Bague, Collier, Bracelet, Boucles d'oreilles), avec des variations adaptées à chaque catégorie.

### `StoneFixtures`

Créer les pierres récurrentes du catalogue avec leurs descriptions visuelles :

```php
$stones = [
    [
        'name' => 'INCONNU',
        'visualDescription' => 'stone as shown in reference images, do not alter or infer type',
        'mood' => null,
        'palette' => [],
    ],
    [
        'name' => 'Onyx noir',
        'visualDescription' => 'deep glossy jet-black stone, polished mirror finish with sharp highlights',
        'mood' => 'dramatic, mysterious, sophisticated',
        'palette' => ['ivory', 'warm gold', 'deep burgundy'],
    ],
    [
        'name' => 'Turquoise',
        'visualDescription' => 'vibrant sky-blue to greenish-blue stone with natural subtle veining, slightly matte finish',
        'mood' => 'bohemian, warm, earthy',
        'palette' => ['terracotta', 'sand beige', 'warm brown'],
    ],
    [
        'name' => 'Pierre de Lune',
        'visualDescription' => 'milky white translucent stone with blue adularescence catching the light',
        'mood' => 'ethereal, dreamy, soft',
        'palette' => ['pale blue', 'ivory', 'silver'],
    ],
    [
        'name' => 'Labradorite',
        'visualDescription' => 'grey-green stone with iridescent flashes of blue and gold (labradorescence), metallic shimmer',
        'mood' => 'mystical, multi-dimensional',
        'palette' => ['charcoal grey', 'deep teal', 'pale gold'],
    ],
    [
        'name' => 'Malachite',
        'visualDescription' => 'rich emerald green with distinctive banded concentric patterns, glossy polish',
        'mood' => 'natural, luxurious, organic',
        'palette' => ['ivory', 'warm gold', 'dark brown'],
    ],
    [
        'name' => 'Lapis Lazuli',
        'visualDescription' => 'deep royal blue stone with subtle golden pyrite specks',
        'mood' => 'regal, ancient, profound',
        'palette' => ['gold', 'ivory', 'deep navy'],
    ],
    [
        'name' => 'Zirconium',
        'visualDescription' => 'brilliant clear crystal with sharp facets catching light prismatically',
        'mood' => 'brilliant, refined, classic',
        'palette' => ['ivory', 'silver', 'soft pink'],
    ],
    [
        'name' => 'Sodalite',
        'visualDescription' => 'deep blue stone with white marbled veining patterns',
        'mood' => 'calming, contemplative',
        'palette' => ['ivory', 'pale grey', 'soft gold'],
    ],
    [
        'name' => 'Cornaline',
        'visualDescription' => 'translucent orange-red stone with warm fiery glow',
        'mood' => 'warm, energetic, vibrant',
        'palette' => ['cream', 'warm gold', 'terracotta'],
    ],
    [
        'name' => 'Amazonite',
        'visualDescription' => 'soft turquoise-green stone with subtle white marbling',
        'mood' => 'calming, fresh, organic',
        'palette' => ['ivory', 'sand', 'warm gold'],
    ],
    [
        'name' => 'Rhodonite',
        'visualDescription' => 'pink to rose-red stone with dark grey veining',
        'mood' => 'tender, romantic, grounding',
        'palette' => ['cream', 'dusty rose', 'soft gold'],
    ],
    [
        'name' => 'Quartz Rose',
        'visualDescription' => 'soft translucent pink stone with gentle rosy glow',
        'mood' => 'romantic, tender, delicate',
        'palette' => ['ivory', 'dusty pink', 'warm gold'],
    ],
    [
        'name' => 'Agate blanche',
        'visualDescription' => 'milky white translucent stone with soft natural banding',
        'mood' => 'pure, serene, minimalist',
        'palette' => ['ivory', 'soft grey', 'pale gold'],
    ],
    [
        'name' => 'Péridot',
        'visualDescription' => 'bright olive to lime-green transparent stone with high clarity',
        'mood' => 'fresh, vibrant, natural',
        'palette' => ['ivory', 'warm gold', 'sage'],
    ],
    [
        'name' => 'Smoky Quartz',
        'visualDescription' => 'transparent smoky brown stone with warm depth',
        'mood' => 'grounding, earthy, sophisticated',
        'palette' => ['cream', 'warm gold', 'espresso'],
    ],
    [
        'name' => 'Nacre',
        'visualDescription' => 'iridescent mother-of-pearl with soft rainbow shimmer',
        'mood' => 'oceanic, luminous, delicate',
        'palette' => ['ivory', 'pale blue', 'silver'],
    ],
];
```

---

## 11. Étapes d'implémentation ordonnées

**Respect de l'ordre critique** : chaque étape valide la précédente. Ne pas sauter.

### Phase 1 — Fondations data (3-4h)
- [ ] Créer tous les enums
- [ ] Créer toutes les entités + migrations Doctrine
- [ ] Créer les repositories
- [ ] Créer les fixtures (Category, Stone, CategoryVisualPrompt)
- [ ] Lancer les fixtures, vérifier la BDD

### Phase 2 — Import catalogue existant (2h)
- [ ] Créer `ImportCatalogueCommand`
- [ ] Tester avec `--dry-run` sur le CSV fourni
- [ ] Lancer en réel, vérifier intégrité BDD

### Phase 3 — Cerveau IA (4h)
- [ ] `BrandStyleProvider`, `TechnicalSpecsProvider`, `PromptFallbackProvider`
- [ ] `PromptBuilder` + `PromptResult`
- [ ] **Tests unitaires** : vérifier les prompts générés sur 5 produits représentatifs de chaque catégorie
- [ ] Valider visuellement la qualité des prompts textuels avant de passer à la suite

### Phase 4 — Client Gemini (3h)
- [ ] `GeminiImageClient` + `GeminiResponse` + exceptions
- [ ] `BudgetGuard` + table/schéma usage
- [ ] **Test d'intégration** : générer 1 seule image sur 1 produit (validation end-to-end avec API réelle)

### Phase 5 — Queue async (2h)
- [ ] `GenerateVisualMessage` + `GenerateVisualHandler`
- [ ] Configuration Messenger + RateLimiter
- [ ] `ImageStorage` via Flysystem
- [ ] Test : dispatcher 1 message, vérifier le worker traite correctement

### Phase 6 — Back-office EasyAdmin (4-5h)
- [ ] CRUDs basiques : Category, Subcategory, Stone, CategoryVisualPrompt
- [ ] `ProductCrudController` avec upload multiple et bouton de génération
- [ ] `GeneratedVisualCrudController` avec vue de validation (approve/reject/regenerate)

### Phase 7 — Migration batch (2h)
- [ ] `MigrateAllVisualsCommand`
- [ ] Test sur 5 produits (limite via option)
- [ ] Lancement complet overnight sur les 222 produits

### Phase 8 — Polish et monitoring (2h)
- [ ] Dashboard de monitoring simple : coût mensuel, nb générations, taux d'échec
- [ ] Commande de nettoyage des visuels REJECTED (après X jours)
- [ ] Documentation README pour la gérante

**Temps total estimé** : ~22-25h de dev focused.

---

## 12. Tests prioritaires

### Tests unitaires indispensables

- **`PromptBuilderTest`** : pour chaque combinaison (catégorie × type × avec/sans pierre), vérifier que le prompt généré contient bien tous les blocs attendus
- **`PromptFallbackProviderTest`** : vérifier que le fallback est activé si pas de prompt en BDD
- **`BudgetGuardTest`** : simuler un dépassement de budget et vérifier l'exception

### Tests d'intégration

- **`GeminiImageClientTest`** avec HTTP client mocké : vérifier le retry sur 429, le parsing de réponse, la gestion d'erreur
- **`GenerateVisualHandlerTest`** : simuler un message complet et vérifier la chaîne (mocker Gemini)

### Tests manuels critiques (checklist)

Avant mise en prod :
- [ ] Uploader un produit test avec 3 photos, lancer les 9 générations
- [ ] Vérifier que les 9 `GeneratedVisual` sont bien créés avec status `PENDING_REVIEW`
- [ ] Approuver 1 visuel par type, vérifier transition `READY_FOR_REVIEW`
- [ ] Rejeter un visuel et regénérer
- [ ] Tenter de dépasser le budget (mock) et vérifier que rien ne part à l'API
- [ ] Simuler une erreur 429 répétée et vérifier le retry

---

## 13. Hors scope explicite

Pour éviter les dérives pendant l'implémentation, **ces éléments ne sont PAS traités** dans cette feature :

- ❌ Multi-langue/i18n (le site peut être multi-langue ailleurs, mais les prompts restent en anglais, les CRUDs back-office en français)
- ❌ Refonte de la logique de stockage d'images existante (déjà faite en amont)
- ❌ Intégration à l'interface publique de la boutique (on s'occupe juste d'alimenter la BDD, l'affichage existe déjà)
- ❌ Système de notification par email (juste log pour les échecs, l'admin checkera le dashboard)
- ❌ Mercure / notifications temps réel (polling / refresh suffit)
- ❌ Entraînement de LoRA ou modèle custom (on utilise Gemini clé en main)
- ❌ Génération de vidéos produits (Veo n'est pas dans le scope)
- ❌ SEO / alt text auto-généré sur les images (peut être ajouté plus tard)

---

## 14. Références & documentation

- **Gemini API docs** : https://ai.google.dev/gemini-api/docs/image-generation
- **Gemini pricing** : https://ai.google.dev/gemini-api/docs/pricing
- **Gemini rate limits** : https://ai.google.dev/gemini-api/docs/rate-limits
- **Obtenir une clé API** : https://aistudio.google.com/apikey
- **Dashboard budget** : console.cloud.google.com → Billing → Budgets & alerts
- **Symfony Messenger** : https://symfony.com/doc/current/messenger.html
- **Symfony Rate Limiter** : https://symfony.com/doc/current/rate_limiter.html
- **EasyAdmin** : https://symfony.com/bundles/EasyAdminBundle/current/index.html
- **Flysystem Bundle** : https://github.com/thephpleague/flysystem-bundle

---

## 15. Checklist finale avant démarrage

- [ ] Clé API Gemini obtenue et testée (un simple curl)
- [ ] Facturation Google Cloud activée (Tier 1)
- [ ] Budget alert configuré à 30 $/mois
- [ ] Refonte stockage images **validée et déployée**
- [ ] CSV du catalogue disponible dans `var/import/`
- [ ] Storage Flysystem configuré (local ou cloud)
- [ ] Messenger transport configuré (Doctrine transport OK pour débuter)
- [ ] Worker Messenger testé manuellement

---

**Fin du document.** Bonne implémentation !
