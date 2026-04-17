# Milestone 16 — Phase 2 — Cerveau IA + Client Gemini + Queue — TERMINÉ

## Fichiers créés

### Services Prompt
- `src/Service/Prompt/PromptResult.php` — DTO readonly (content, categoryPromptVersion, usedFallback)
- `src/Service/Prompt/BrandStyleProvider.php` — Bloc identité marque Alma Stella (en dur)
- `src/Service/Prompt/TechnicalSpecsProvider.php` — Specs techniques par VisualType (portrait 4:5, 819×1024)
- `src/Service/Prompt/PromptFallbackProvider.php` — Prompts génériques en mémoire (version 0, jamais persistés)
- `src/Service/Prompt/PromptBuilder.php` — Composition du prompt final (metadata → brand → preservation → framing → staging → specs)

### Client Gemini
- `src/Service/Gemini/GeminiImageClient.php` — Client HTTP Gemini, retry 429 (backoff 2s/4s/8s, max 3 tentatives)
- `src/Service/Gemini/GeminiResponse.php` — DTO readonly (imageData, mimeType, requestId)
- `src/Service/Gemini/GeminiApiException.php` — Exception custom (httpStatusCode, requestId)
- `src/Service/Gemini/BudgetGuard.php` — Contrôle budget mensuel via GeminiUsageLog

### Stockage images
- `src/Service/Visual/ImageStorage.php` — Abstraction Flysystem (store sources, store generated, read, delete)

### Queue async
- `src/Message/GenerateVisualMessage.php` — Message readonly (productId, type, variantNumber)
- `src/MessageHandler/GenerateVisualHandler.php` — Handler async (rate limit → budget → prompt → Gemini → store → persist)

### Exception
- `src/Exception/BudgetExceededException.php` — Exception budget dépassé

## Fichiers modifiés
- `config/packages/messenger.yaml` — Transport `gemini_async` (Doctrine, retry 2×, 5s→30s), transport `failed`, routing GenerateVisualMessage
- `config/packages/rate_limiter.yaml` — Ajout politique `gemini_api` (token_bucket, 15 req/min)
- `config/services.yaml` — Paramètres `gemini.api_key`, `gemini.monthly_budget_usd` + bindings `$geminiApiKey`, `$monthlyBudgetUsd`
- `docs/ROADMAP.md` — Phase 2 cochée
- `docs/ARCHITECTURE.md` — 5 nouveaux services documentés (PromptBuilder, GeminiImageClient, BudgetGuard, ImageStorage, GenerateVisualHandler)

## État de la BDD
- Aucune modification de schéma (Phase 2 n'ajoute pas de tables/colonnes)

## Ce qui fonctionne
- PHPStan niveau 6 : zéro erreur (166 fichiers)
- PHP CS Fixer : zéro diff (167 fichiers)
- PromptBuilder compose un prompt structuré en 6 blocs (metadata, brand, preservation, framing, staging, specs)
- PromptFallbackProvider fournit des prompts génériques (version 0) si catégorie non configurée
- GeminiImageClient gère le retry sur 429 avec backoff exponentiel
- BudgetGuard vérifie le budget mensuel et enregistre les coûts
- GenerateVisualHandler orchestre la chaîne complète : rate limit → budget → prompt → Gemini → stockage → persistance
- Messenger configuré : transport `gemini_async` (Doctrine), routing, retry 2× avec backoff
- Rate limiter `gemini_api` : token_bucket, 15 req/min

## Point d'attention pour la phase suivante
- Le handler utilise `RateLimiterFactory $geminiApiLimiter` — Symfony résout automatiquement le nom `gemini_api` via le suffixe `Limiter`
- Le coût estimé par image est de 0.039 $ (constante dans le handler)
- Les SourcePhoto sont lues depuis Flysystem via `ImageStorage::read()` — Phase 3 doit s'assurer que les photos sources sont bien stockées dans Flysystem
- Le handler crée des `GeneratedVisual` avec status `Failed` si l'appel Gemini échoue (pas de message perdu)
- Phase 3 devra créer `VisualApprovalHandler` pour la copie Flysystem → VichUploader lors de l'approbation
- Les tests end-to-end nécessitent une clé API Gemini valide dans `.env.local`
