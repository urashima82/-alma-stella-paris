# Milestone 16 — Phase 1 — Modèle de données — TERMINÉ

## Fichiers créés
- `src/Enum/VisualType.php` — Enum : Vignette, Worn, Lifestyle (avec label/badgeColor)
- `src/Enum/VisualStatus.php` — Enum : Generating, PendingReview, Approved, Rejected, Failed
- `src/Enum/VisualWorkflowStatus.php` — Enum : Draft, PendingVisuals, ReadyForReview, VisualsApproved
- `src/Enum/PhotoAngle.php` — Enum : Front, ThreeQuarter, Detail, Back, Other
- `src/Entity/CategoryVisualPrompt.php` — Prompts visuels par catégorie × type, versionnés
- `src/Entity/SourcePhoto.php` — Photos sources brutes (Flysystem)
- `src/Entity/GeneratedVisual.php` — Visuels générés par IA avec workflow d'approbation
- `src/Entity/GeminiUsageLog.php` — Log des coûts API Gemini
- `src/Repository/CategoryVisualPromptRepository.php` — Méthode findActiveForCategory
- `src/Repository/SourcePhotoRepository.php` — Repository de base
- `src/Repository/GeneratedVisualRepository.php` — Méthodes findByProductGroupedByType, countByStatus, findPendingReviewForProduct, hasApprovedForAllTypes
- `src/Repository/GeminiUsageLogRepository.php` — Méthode getCurrentMonthTotal
- `src/DataFixtures/CategoryVisualPromptFixtures.php` — 12 prompts (4 catégories × 3 types) + preservationInstructions
- `config/packages/flysystem.yaml` — Stockage local Flysystem

## Fichiers modifiés
- `src/Entity/ProductCategory.php` — Ajout preservationInstructions (text, nullable), specificFocus (text, nullable), OneToMany → CategoryVisualPrompt, méthodes getVisualPromptFor/hasVisualPromptFor
- `src/Entity/Product.php` — Ajout visualStatus (VisualWorkflowStatus, default Draft), OneToMany → SourcePhoto (orphanRemoval), OneToMany → GeneratedVisual, méthode getApprovedVisualFor
- `migrations/Version20260408140632.php` — 4 nouvelles tables + 3 colonnes ajoutées
- `src/DataFixtures/AppFixtures.php` — addReference sur les catégories racines pour DependentFixtureInterface
- `.env` — Ajout GEMINI_API_KEY, GEMINI_MONTHLY_BUDGET_USD, IMAGE_STORAGE_PATH
- `docs/ROADMAP.md` — Phase 1 cochée
- `docs/ARCHITECTURE.md` — 4 nouvelles entités documentées

## État de la BDD
- Tables ajoutées : `category_visual_prompt`, `source_photo`, `generated_visual`, `gemini_usage_log`
- Colonnes ajoutées : `product.visual_status`, `product_category.preservation_instructions`, `product_category.specific_focus`

## Ce qui fonctionne
- Migration exécutée sans erreur (56 requêtes SQL)
- Fixtures chargées : 12 CategoryVisualPrompt, preservationInstructions sur 4 catégories racines
- 222 produits avec visual_status = 'draft'
- PHPStan niveau 6 : zéro erreur (153 fichiers)
- PHP CS Fixer : zéro diff (154 fichiers)
- league/flysystem-bundle v3.7 installé et configuré

## Point d'attention pour la phase suivante
- `AppFixtures` expose des références `category-Rings`, `category-Earrings`, `category-Bracelets`, `category-Necklaces`, `category-Sets` pour les fixtures dépendantes
- La catégorie "Sets" n'a pas de preservationInstructions (pas de prompts IA, les coffrets ne sont pas générés individuellement)
- Le transport Messenger est déjà configuré en Doctrine (`doctrine://default?auto_setup=0`) dans `.env` — Phase 2 devra ajouter le routing et les transports nommés dans `messenger.yaml`
- Flysystem pointe vers `var/storage/products` (variable `IMAGE_STORAGE_PATH`)
