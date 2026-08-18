# Milestone 17 — Remplissage IA des contenus produit — terminé

> **Archive** — checklist de milestone terminé, extraite de `ROADMAP.md`
> le 2026-08-18 (tests manuels validés par la gérante). L'état courant du
> projet est décrit par le code et `docs/ARCHITECTURE.md`.

*Estimated effort: 8-10h*

> **Génération automatique des contenus textuels d'un produit par IA (Gemini 2.5 Flash, multimodal vision → texte structuré).**
> À partir des photos sources d'un produit, plus du contexte taxonomique déjà renseigné par la gérante (catégorie, pierres), l'IA propose : `name`, `nameFr`, `description`, `descriptionFr`. Validation humaine obligatoire (review / edit / approve) avant écriture en base.
>
> **Pré-requis :** Milestone 16 livrée (`SourcePhoto`, `BudgetGuard`, queue Messenger, onglet IA EasyAdmin réutilisés).

### Phase 1 — Modèle de données
- [x] Enum `ContentSuggestionStatus` (Generating, Pending, Approved, Rejected, Applied) — `Generating` ajouté pour distinguer proprement « worker en cours » de « prête à review »
- [x] Entité `ProductContentSuggestion` (productId, nameEn, nameFr, descriptionEn, descriptionFr, status, modelUsed, requestId, generatedAt, appliedAt, contextSnapshot JSON, additionalContext)
- [x] Relation OneToMany `Product → ProductContentSuggestion`
- [x] Migration unifiée modifiée (table `product_content_suggestion` + colonne `operation` ajoutée à `gemini_usage_log`) — **recréation DDEV à lancer manuellement**
- [x] Fixtures : 2 suggestions exemples (`ProductContentSuggestionFixtures`)

### Phase 2 — Cerveau IA + Client Gemini text + Queue
- [x] `GeminiTextClient` (endpoint `gemini-2.5-flash:generateContent`, support `inlineData` images + `responseSchema` JSON, retry 429 + backoff `[2s, 4s, 8s]`)
- [x] `ContentSuggestionResult` DTO + `ContentSuggestionException`
- [x] `ContentPromptBuilder` :
  - Voix éditoriale dans un service dédié `ContentBrandVoiceProvider` (séparé du `BrandStyleProvider` visuel pour respecter l'indépendance des pipelines)
  - 4 paires few-shot **textuelles** (`ContentFewShotProvider`) couvrant les 4 catégories ; au moins 1 sans pierre nommée pour ancrer le fallback
  - Contexte dynamique injecté : catégorie (+ parent + specific focus), pierres (nom + couleur + vertus)
  - 4 branches de fallback (`CONTEXT IS COMPLETE` / `CATEGORY UNKNOWN` / `STONE UNKNOWN` / `NEITHER … GIVEN`)
  - `additionalContext` libre injecté en `ADDITIONAL STEERING (mandatory)` lors de la régénération
  - JSON schema strict — 4 champs requis, `temperature = 0.7`
- [x] `ProductContentFiller` service (orchestrateur : load sources → snapshot contexte → build prompt → call Gemini → parse → DTO `ContentSuggestionResult`)
- [x] `BudgetGuard` étendu : nouveaux env `GEMINI_FLASH_TEXT_MODEL` + `GEMINI_FLASH_TEXT_COST_USD`, budget mensuel partagé avec M16
- [x] `Message + Handler` : `FillProductContentMessage`, `FillProductContentHandler` (transport `gemini_async`, rate limiter `gemini_api` partagé 15 req/min)

### Phase 3 — Back-office EasyAdmin
- [x] `ProductContentSuggestionCrudController` (read-only listing, filtres produit/statut, item au menu « Génération IA »)
- [x] Onglet **séparé** « Contenu IA » sur la fiche produit (l'indépendance pipelines image/contenu prime sur la consigne d'origine de mutualiser dans « Visuels IA »), conditions d'activation :
  - Au moins 1 `SourcePhoto` ⇒ obligatoire (bouton désactivé, bandeau rouge bloquant)
  - Catégorie renseignée ⇒ recommandée (bandeau jaune non bloquant)
  - Pierres renseignées ⇒ recommandées (bandeau jaune non bloquant)
- [x] Polling JS dédié `admin-ai-content.js` + endpoint `aiContentStatus` (séparé d'`aiStatus` pour respecter l'indépendance)
- [x] Carte de review : 4 champs éditables (`nameFr`, `nameEn`, `descriptionFr`, `descriptionEn`) — pas de dropdown taxonomie
- [x] Boutons « Appliquer » (sauvegarde + copie vers Product + status `Applied`) / « Rejeter » / « Régénérer » (avec prompt natif pour instruction additionnelle libre)
- [x] `contextSnapshot` affiché en lecture seule (collapsible JSON) dans la carte

### Phase 4 — Tests + finitions
- [x] Tests unitaires : `ContentPromptBuilderTest` (4 branches de fallback + steering + schéma), `ContentFewShotProviderTest` (couverture catégories + scénario sans pierre), `ContentSuggestionStatusTest`
- [x] Tests end-to-end happy path & fallback **manuels** — validés par la gérante le 2026-08-18
- [x] Logging usage : nouveau champ `operation` (`GeminiOperation::Visual` / `TextFill`) sur `GeminiUsageLog`, passé explicitement par chaque handler
- [x] PHPStan niveau 6 + CS Fixer + Twig lint clean
- [x] Doc : `docs/AI_CONTENT_FILL.md` (prompt strategy, fallback strategy, JSON schema, indépendance des pipelines)
- [x] Update `docs/ARCHITECTURE.md`

### Definition of Done
- Bouton "Générer contenu" actif dès qu'au moins 1 SourcePhoto est uploadée
- Bandeau d'avertissement non-bloquant si catégorie ou pierres manquantes
- Worker appelle Gemini 2.5 Flash multimodal → `ProductContentSuggestion` persistée avec les 4 champs FR + EN
- `contextSnapshot` capturé pour traçabilité (savoir si la suggestion a été générée avec/sans taxonomie)
- Modale admin permet d'éditer chaque champ avant validation
- "Appliquer" copie les 4 valeurs sur l'entité `Product` et marque la suggestion `Applied`
- Régénération avec instruction additionnelle libre fonctionnelle
- BudgetGuard partage le même budget que la génération visuelle
- Style des descriptions FR cohérent avec le ton de `catalogue.csv` (vérifié sur 5 produits tests, dont au moins 1 sans pierre renseignée)
