# Plan d'implémentation — Pipeline hybride de génération IA

> **Statut** : Plan prêt à exécuter, code non démarré.
> **Créé** : 2026-04-27.
> **Référence Milestone** : 16 — Phase 5 (Améliorations IHM admin) Groupe A.ter.

---

## 1. Contexte & objectif

### Le problème observé
Le modèle `gemini-2.5-flash-image` (utilisé actuellement) échoue avec `IMAGE_OTHER` sur ~80% des tentatives Vignette et Lifestyle. Seul le type **Worn** (porté sur main) marche systématiquement.

**Diagnostic** : Gemini 2.5 Flash Image est un modèle **conversationnel** doué pour l'édition simple, mais **incapable de composer une scène complexe tout en préservant un produit avec fidélité**. Pour un site e-commerce, **la fidélité du bijou n'est pas négociable**.

### Stratégie validée
**Pipeline hybride** : chaque type de visuel utilise l'outil le plus adapté.

| Type | Tool cible | Raison |
|---|---|---|
| **Vignette** | Imagen 4 | Préservation produit forte |
| **Worn** | Gemini 2.5 Flash Image (existant) | Fonctionne déjà, édition simple |
| **Lifestyle** | Imagen 4 | Composition de scène + préservation |

### Hypothèse importante sur les sources
**Les photos sources sont pré-traitées côté client** (Estelle détoure sur son téléphone via les fonctions natives iOS/Android ou un app type Photoroom avant upload). Donc :
- Pas besoin de pipeline `rembg` côté serveur.
- Pas de service tiers de background removal à déployer.
- Les sources arrivent prêtes à être consommées par Imagen / Gemini.

### Alternative à explorer pour Vignette (si Imagen pas assez fidèle)
Puisque les sources arrivent déjà détourées, la Vignette peut **se passer d'IA** :
- Prendre la meilleure photo source (angle frontal, fond déjà propre/transparent)
- Compositer sur un fond neutre via `Intervention/Image` (déjà installé)
- 100% fidélité garantie (c'est la photo originale, juste mise sur fond clean)

À garder en réserve si Imagen ne préserve pas le bijou avec la fidélité voulue. Implémentation en `BackgroundCompositingGenerator` (sans appel HTTP, traitement local rapide).

### Périmètre Phase 1 (cette implémentation)
- ✅ Architecture découplée (interface + router)
- ✅ Nouveau client Imagen 4
- ✅ Routage Vignette + Lifestyle → Imagen
- ✅ Worn reste sur Gemini
- ⏳ Phase 2 (post-validation) : ajustements prompts spécifiques Imagen, éventuellement migrer Worn
- ⏳ Phase 3 (si Imagen Vignette pas 100% fidèle) : `BackgroundCompositingGenerator` local — bascule Vignette sur compositing direct sans IA

---

## 2. État actuel (avant cette refonte)

### Fichiers concernés
- `src/Service/Gemini/GeminiImageClient.php` — client HTTP Gemini, méthode `generate(string $prompt, array $imageBase64Array): GeminiResponse`
- `src/Service/Gemini/GeminiResponse.php` — DTO de réponse (imageData, mimeType, requestId)
- `src/Service/Gemini/GeminiApiException.php` — exception métier
- `src/Service/Gemini/BudgetGuard.php` — contrôle budget mensuel USD
- `src/MessageHandler/GenerateVisualHandler.php` — handler async qui appelle le client
- `src/Service/Visual/ImageStorage.php` — Flysystem (sources + générés)
- `src/Service/Prompt/PromptBuilder.php` — assemble le prompt par type/catégorie
- `config/services.yaml` — DI (passes `gemini.api_key` et `gemini.budget` en env)
- `.env` / `.env.local` — `GEMINI_API_KEY`, `GEMINI_MONTHLY_BUDGET_USD`

### Comportement actuel du handler
1. Vérifie rate limiter (15 req/min)
2. Vérifie budget mensuel
3. Récupère le `Product` et ses `SourcePhoto[]`
4. Le controller a déjà pré-créé une `GeneratedVisual` en `Generating` (depuis fix précédent)
5. Construit le prompt via `PromptBuilder`
6. Encode toutes les sources en base64
7. Appelle `GeminiImageClient::generate()`
8. Sur succès : `setStatus(PendingReview)` + path + requestId
9. Sur échec : `setStatus(Failed)` + errorMessage

---

## 3. Architecture cible

### Interface commune

**Nouveau** : `src/Service/Generator/VisualGeneratorInterface.php`

```php
namespace App\Service\Generator;

use App\Enum\VisualType;

interface VisualGeneratorInterface
{
    /**
     * @param string[] $sourceBase64
     * @throws VisualGenerationException
     */
    public function generate(
        string $prompt,
        array $sourceBase64,
        VisualType $visualType,
    ): GeneratedVisualResult;
}
```

**Nouveau DTO** : `src/Service/Generator/GeneratedVisualResult.php`

```php
namespace App\Service\Generator;

final readonly class GeneratedVisualResult
{
    public function __construct(
        public string $imageBase64,
        public string $mimeType,
        public string $requestId,
        public string $modelName,    // 'gemini-2.5-flash-image' | 'imagen-4.0-...'
        public float $estimatedCostUsd,
    ) {}
}
```

**Nouvelle exception** : `src/Service/Generator/VisualGenerationException.php` (super-classe de `GeminiApiException`).

### Implémentations

1. **`GeminiVisualGenerator`** (`src/Service/Generator/GeminiVisualGenerator.php`)
   - Wrapper sur `GeminiImageClient` existant (rester non-cassant)
   - Convertit `GeminiResponse` → `GeneratedVisualResult`
   - Convertit `GeminiApiException` → `VisualGenerationException`

2. **`ImagenVisualGenerator`** (`src/Service/Generator/ImagenVisualGenerator.php`) — **NOUVEAU**
   - Client HTTP pour `imagen-4.0-generate-preview-001` (ou la version stable au moment du dev)
   - Endpoint : `https://generativelanguage.googleapis.com/v1beta/models/{model}:predict`
   - Auth : header `x-goog-api-key: {GEMINI_API_KEY}` (la clé existante)
   - Format requête (à valider avec doc) :
     ```json
     {
       "instances": [{
         "prompt": "...",
         "referenceImages": [
           {"referenceType": "REFERENCE_TYPE_SUBJECT", "referenceImage": {"bytesBase64Encoded": "..."}}
         ]
       }],
       "parameters": {
         "sampleCount": 1,
         "aspectRatio": "4:5"
       }
     }
     ```
   - Format réponse : `{"predictions": [{"bytesBase64Encoded": "...", "mimeType": "image/png"}]}`

### Router

**Nouveau** : `src/Service/Generator/VisualGeneratorRouter.php`

```php
namespace App\Service\Generator;

use App\Enum\VisualType;

final class VisualGeneratorRouter
{
    public function __construct(
        private readonly GeminiVisualGenerator $gemini,
        private readonly ImagenVisualGenerator $imagen,
    ) {}

    public function for(VisualType $type): VisualGeneratorInterface
    {
        return match ($type) {
            VisualType::Worn => $this->gemini,
            VisualType::Vignette, VisualType::Lifestyle => $this->imagen,
        };
    }
}
```

### Modification du handler

`src/MessageHandler/GenerateVisualHandler.php` :
- Remplace l'injection `GeminiImageClient $geminiClient` par `VisualGeneratorRouter $generatorRouter`
- Dans `__invoke()` :
  ```php
  $generator = $this->generatorRouter->for($message->type);
  try {
      $result = $generator->generate($prompt, $sourcesBase64, $message->type);
  } catch (VisualGenerationException $e) {
      // ... persistance Failed
  }
  ```
- Persiste `$result->modelName` dans un nouveau champ `GeneratedVisual::modelUsed` (champ à ajouter ci-dessous)
- Coût enregistré via `$result->estimatedCostUsd` au lieu d'une constante

### Modèle de données

Ajouter à `src/Entity/GeneratedVisual.php` :
```php
#[ORM\Column(length: 50, nullable: true)]
private ?string $modelUsed = null;
```

Pour la traçabilité (« cette image a été générée par Gemini vs Imagen »).

**Migration Doctrine** : modifier la migration existante (rappel CLAUDE.md règle TEMPORAIRE) puis `ddev delete --omit-snapshot && ddev start`. Ou ajouter une colonne nullable simple si on ne veut pas tout reseed.

---

## 4. Configuration

### `.env`
```dotenv
###> Imagen 4 ###
IMAGEN_MODEL=imagen-4.0-generate-preview-001
IMAGEN_API_KEY=${GEMINI_API_KEY}    # même clé Google AI Studio
IMAGEN_MONTHLY_BUDGET_USD=30        # ou séparé du budget Gemini
###< Imagen 4 ###
```

### `config/services.yaml`
Bind les paramètres :
```yaml
parameters:
    imagen.model: '%env(IMAGEN_MODEL)%'
    imagen.api_key: '%env(IMAGEN_API_KEY)%'

services:
    App\Service\Generator\ImagenVisualGenerator:
        arguments:
            $model: '%imagen.model%'
            $apiKey: '%imagen.api_key%'
            $httpClient: '@http_client'
```

### Rate limiter (optionnel mais recommandé)
Imagen et Gemini ont des quotas différents. Ajouter dans `config/packages/rate_limiter.yaml` :
```yaml
framework:
    rate_limiter:
        gemini_api:    # existant
            policy: 'token_bucket'
            limit: 15
            rate: { interval: '1 minute' }
        imagen_api:    # nouveau
            policy: 'token_bucket'
            limit: 10
            rate: { interval: '1 minute' }
```

Et dans `ImagenVisualGenerator`, injecter `RateLimiterFactory $imagenApiLimiter`.

---

## 5. Plan de tâches Phase 1

### Tâches de code (dans cet ordre)

- [ ] **T1** — Créer `VisualGeneratorInterface`, `GeneratedVisualResult`, `VisualGenerationException` (3 fichiers)
- [ ] **T2** — Créer `GeminiVisualGenerator` qui wrap le `GeminiImageClient` existant
- [ ] **T3** — Créer `ImagenVisualGenerator` (HTTP POST, parsing réponse, gestion erreurs)
- [ ] **T4** — Créer `VisualGeneratorRouter`
- [ ] **T5** — Ajouter `modelUsed` à `GeneratedVisual` (entité + migration unifiée + getters/setters)
- [ ] **T6** — Modifier `GenerateVisualHandler` pour utiliser `VisualGeneratorRouter`
- [ ] **T7** — Ajouter rate limiter `imagen_api` + config services.yaml + variables `.env`
- [ ] **T8** — Recréer DDEV (`ddev delete --omit-snapshot && ddev start` + fixtures + import)
- [ ] **T9** — Tester génération sur produit 2 (Vignette, Worn, Lifestyle)
- [ ] **T10** — PHPStan + CS Fixer + Twig lint clean
- [ ] **T11** — Mettre à jour `docs/ROADMAP.md` Phase 5 Groupe A.ter

### Critères de succès Phase 1
- ✅ Sur 5 tentatives Vignette via Imagen : taux de succès ≥ 80%
- ✅ Sur 5 tentatives Lifestyle via Imagen : taux de succès ≥ 60%
- ✅ Bijou reconnaissable (couleur, forme, gravure préservées) sur les visuels approuvés
- ✅ Worn continue à passer (régression-free)
- ✅ Modal « Voir le prompt » affiche bien le `modelUsed`

---

## 6. Tests manuels recommandés

Une fois la Phase 1 implémentée :

1. **Recharger les fixtures** : `ddev exec php bin/console doctrine:fixtures:load --no-interaction`
2. **Importer les images catalogue** : `ddev exec php bin/console app:import-catalogue-images`
3. **Aller sur** `/admin` (login admin), produit id=2 (Chevalière Trèfle Bordeaux)
4. **Cliquer « Générer les 3 visuels »**
5. **Observer** :
   - Vignette doit apparaître via Imagen (vérifier `model_used` en BDD)
   - Worn doit apparaître via Gemini (comme avant)
   - Lifestyle doit apparaître via Imagen
6. **Approuver les visuels acceptables**, rejeter les autres, régénérer 1-2 fois
7. **Vérifier la fidélité** : le bijou est-il reconnaissable ? Les détails préservés ?

Si succès → propagation à 5 autres produits de catégories différentes pour valider la robustesse.

---

## 7. Backlog Phases 2 et 3

### Phase 2 — Optimisations Imagen
- Ajuster les prompts spécifiques à Imagen (prompts différents de Gemini, peut-être plus directs)
- Tester `imagen-4.0-ultra` si la qualité standard insuffisante
- Évaluer migration Worn vers Imagen pour cohérence

### Phase 3 — Compositing local pour Vignette (fidélité 100%)
**Déclencheur** : si Imagen sur Vignette ne préserve pas à 100% les détails du bijou.

**Hypothèse de travail** : les sources sont déjà détourées par Estelle sur son téléphone (iOS « Recadrer le sujet » / Photoroom / Magic Eraser Android). Donc **pas besoin de service de background removal côté serveur** — on a juste besoin de compositer.

**Architecture** :
- Nouveau `BackgroundCompositingGenerator implements VisualGeneratorInterface`
- Aucun appel HTTP externe — uniquement `Intervention/Image` (déjà installé)
- Routeur : Vignette → `BackgroundCompositingGenerator`
- Pas de coût IA pour la Vignette
- Méthode :
  1. Prendre la photo source primaire (angle Front, position 1)
  2. Si elle a un canal alpha (PNG transparent) : la composer directement sur un fond neutre
  3. Si elle a un fond uni (JPG photographié sur fond blanc) : appliquer un seuil pour extraire l'objet
  4. Compositer sur un fond gradient subtil (ivoire → beige clair, cohérent charte Alma Stella)
  5. Ajouter une ombre portée douce
  6. Sauvegarder en WebP dans Flysystem

**Avantage** : 100% fidélité garantie (c'est la photo originale, juste sur fond neutre), 0$ coût récurrent, latence ~100ms, pas de dépendance externe.

**Si la cliente n'a pas détouré ses sources** (cas marginal) : Imagen reste le fallback (status: Failed avec message "Photo source non détourée — utilisez la fonction « Recadrer le sujet » de votre téléphone avant l'upload" ?).

---

## 8. Pièges connus à éviter

### Sur Imagen 4
- L'endpoint exact peut être `imagen-4.0-generate-preview-001` ou `imagen-4.0-generate-001` selon la stabilité au moment du dev. **Vérifier dans Google AI Studio** la liste des modèles dispos avec ta clé.
- Imagen retourne `predictions[].bytesBase64Encoded` (pas `candidates[].content.parts[].inlineData.data` comme Gemini).
- Le rate limiting Imagen est plus strict que Gemini (vérifier les quotas dans la console GCP).
- L'aspect ratio supporté : `1:1`, `9:16`, `16:9`, `3:4`, `4:3`. Pour notre `4:5`, utiliser `4:3` ou `3:4` puis crop côté `ImageProcessor`.

### Sur le pipeline existant
- **Ne pas créer de nouvelle migration Doctrine** — règle CLAUDE.md TEMPORAIRE. Modifier la migration initiale et recréer DDEV.
- **Le polling JS** (`admin-ai-poll.js`) n'a pas besoin de modifications — il drive le worker via `/ai-status` indépendamment du modèle.
- **Le `BudgetGuard`** doit potentiellement être splitté (un par modèle) si les coûts divergent fort. Pour Phase 1, garder un seul budget consolidé.

### Sur la traçabilité
- Le champ `modelUsed` permet de filtrer les visuels par modèle dans EasyAdmin (utile pour debug/comparaison).
- Le `geminiRequestId` actuel devient un `providerRequestId` plus générique (ou on garde le nom et on accepte la dette technique).

---

## 9. Vérification finale (avant de marquer fait)

- [ ] PHPStan niveau 6 passe sans erreur
- [ ] PHP CS Fixer clean
- [ ] Twig lint OK
- [ ] Tous les générateurs sont injectables via DI (debug:container montre les services)
- [ ] Routes admin inchangées (`/admin/product/inline-generate-*` fonctionnent toujours)
- [ ] `messenger:consume gemini_async` fonctionne en CLI (cron fallback)
- [ ] Les fixtures de prompts catégorie sont compatibles avec les 2 modèles (prompts un peu plus directs si besoin)
- [ ] Documentation : ce fichier est marqué « Phase 1 done » avec commit hash
- [ ] `docs/ROADMAP.md` Phase 5 Groupe A.ter mis à jour

---

## 10. Repères de fichiers existants (pour reprise rapide)

| Quoi | Où |
|---|---|
| Handler async actuel | `src/MessageHandler/GenerateVisualHandler.php` |
| Client Gemini actuel | `src/Service/Gemini/GeminiImageClient.php` |
| Construction prompt | `src/Service/Prompt/PromptBuilder.php` |
| Stockage Flysystem | `src/Service/Visual/ImageStorage.php` |
| Workspace UI | `templates/admin/product/_ai_workspace.html.twig` |
| Polling endpoint | `ProductCrudController::aiStatus()` |
| Polling JS | `public/js/admin-ai-poll.js` |
| Actions JS | `public/js/admin-ai-actions.js` |
| Lightbox JS | `public/js/admin-lightbox.js` |
| CSS workspace | `public/css/admin.css` (sections AI Workspace + Admin lightbox) |
| Cron prod (déjà documenté) | `docs/DEPLOYMENT.md` section "AI image generation queue" |

---

**Fin du plan.** Tu peux vider le contexte. Au prochain démarrage, lis ce fichier en premier puis attaque la T1.
