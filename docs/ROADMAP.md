# ROADMAP.md — Alma Stella Paris

> Development is organized in **testable milestones**. Each milestone must be
> fully functional and manually testable before starting the next.
> Claude Code must not skip ahead or partially implement a milestone.

---

## How to use this roadmap

Each milestone has a **Definition of Done (DoD)** — a checklist of what must
work before the milestone is considered complete. When a milestone is done,
ask the developer to validate before proceeding.

When every box of a milestone is checked and validated, move its full
checklist to `docs/milestones/` (archive file per milestone) and add it to
the table below — this file only carries the milestones still open.

---

## Completed milestones (archived)

Full checklists live in [`docs/milestones/`](milestones/), listed here in
order of completion:

| # | Milestone | Archive |
|---|---|---|
| 0 | Project bootstrap | [M00_project_bootstrap.md](milestones/M00_project_bootstrap.md) |
| 1 | Product catalog (admin + data layer) | [M01_product_catalog_admin.md](milestones/M01_product_catalog_admin.md) |
| 2 | Public catalog (frontend) | [M02_public_catalog.md](milestones/M02_public_catalog.md) |
| 3 | Internationalization (FR/EN) | [M03_internationalization.md](milestones/M03_internationalization.md) |
| 4 | Currency selector | [M04_currency_selector.md](milestones/M04_currency_selector.md) |
| 5 | Cart & Stripe checkout | [M05_cart_stripe_checkout.md](milestones/M05_cart_stripe_checkout.md) |
| 6 | Admin authentication (Magic Link) | [M06_admin_magic_link.md](milestones/M06_admin_magic_link.md) |
| 7 | EasyAdmin order management | [M07_easyadmin_orders.md](milestones/M07_easyadmin_orders.md) |
| 8 | Customer accounts | [M08_customer_accounts.md](milestones/M08_customer_accounts.md) |
| 12 | SEO & performance | [M12_seo_performance.md](milestones/M12_seo_performance.md) |
| 9 | Promotions & discount codes | [M09_promotions.md](milestones/M09_promotions.md) |
| 10 | Automated emails & customer testimonials | [M10_emails_testimonials.md](milestones/M10_emails_testimonials.md) |
| 13 | Security audit & hardening | [M13_security_hardening.md](milestones/M13_security_hardening.md) |
| 14 | Two-level category hierarchy | [M14_category_hierarchy.md](milestones/M14_category_hierarchy.md) |
| 10b | EUR base currency migration | [M10B_eur_base_currency.md](milestones/M10B_eur_base_currency.md) |
| 11 | Instagram feed (Behold.so) | [M11_instagram_feed.md](milestones/M11_instagram_feed.md) |
| 15 | Guide des pierres & filtre boutique | [M15_stone_guide.md](milestones/M15_stone_guide.md) |
| 18 | Wizard de création produit assistée IA | [M18_product_wizard.md](milestones/M18_product_wizard.md) |

---

## Milestone 12 — Social publishing
*Estimated effort: 4-5h*

### Tasks
- [ ] Pinterest API client (`PinterestApiClient` service)
- [ ] TikTok Shop API client (`TikTokShopApiClient` service)
- [ ] Instagram deep link generator
- [ ] `SocialPublisher` service orchestrating all three
- [ ] EasyAdmin action button "Publish to social media" on product detail + index
- [ ] Modal with checkboxes (Pinterest ☑ / TikTok Shop ☑ / Instagram ☑)
- [ ] Flash messages per channel (success/error)
- [ ] `social_publish_log` table tracking publish history per product per channel

### Definition of Done
- Click "Publish to social media" on a product → modal appears
- Deselect Pinterest → only TikTok and Instagram are processed
- Pinterest: Pin appears in the connected Pinterest Business account
- TikTok Shop: product appears in the TikTok Seller catalog
- Instagram: deep link opens the Instagram app with pre-filled caption
- Publish history visible on product detail page in EasyAdmin

---

## Milestone 16 — Catalogue IA (génération visuels)
*Estimated effort: 20-25h*

> **Génération automatique de visuels produits par IA (Gemini 2.5 Flash Image).**
> La gérante uploade des photos smartphone "brutes" d'un bijou et obtient
> 3 visuels professionnels (vignette, porté, lifestyle) × 3 variantes chacun.
> Validation humaine obligatoire avant publication.
>
> **Specs complètes :** `docs/CATALOGUE-IA-SPECS.md`
> **Audit :** `docs/CATALOGUE_IA_AUDIT.md`
> **Plan d'adaptation :** `docs/CATALOGUE_IA_PLAN.md`
>
> Cette feature est découpée en **4 phases** (voir plan d'adaptation).
> Chaque phase se termine par un commit et un résumé dans `docs/milestones/`.

### Phase 1 — Modèle de données
- [x] 4 enums (VisualType, VisualStatus, VisualWorkflowStatus, PhotoAngle)
- [x] 4 entités (CategoryVisualPrompt, SourcePhoto, GeneratedVisual, GeminiUsageLog)
- [x] Enrichir ProductCategory (preservationInstructions, specificFocus)
- [x] Enrichir Product (visualStatus, relations SourcePhoto/GeneratedVisual)
- [x] Installer + configurer `league/flysystem-bundle`
- [x] Modifier migration existante, recréer environnement DDEV
- [x] Fixtures : CategoryVisualPromptFixtures (12 prompts)

### Phase 2 — Cerveau IA + Client Gemini + Queue
- [x] Services Prompt (PromptBuilder, BrandStyleProvider, TechnicalSpecsProvider, PromptFallbackProvider)
- [x] Client Gemini (GeminiImageClient, GeminiResponse, GeminiApiException)
- [x] BudgetGuard (contrôle budget mensuel)
- [x] ImageStorage (Flysystem)
- [x] Message + Handler (GenerateVisualMessage, GenerateVisualHandler)
- [x] Config Messenger async + Rate Limiter + .env

### Phase 3 — Back-office EasyAdmin
- [x] CategoryVisualPromptCrudController (CRUD prompts visuels)
- [x] GeneratedVisualCrudController (validation approve/reject/regenerate)
- [x] VisualApprovalHandler (copie Flysystem → VichUploader)
- [x] Enrichir ProductCrudController (upload SourcePhoto, bouton Générer, visuels)
- [x] Enrichir ProductCategoryCrudController (champs IA)
- [x] Section "Génération IA" dans le menu admin

### Phase 4 — Import adapté + finitions
- [x] Adapter ImportCatalogueImagesCommand (SourcePhoto via Flysystem)
- [x] Vérification end-to-end complète
- [x] Test pipeline : fixtures → import → génération → approbation

### Phase 5 — Améliorations IHM admin (en cours)
> Refonte UX du back-office pour réduire la friction lors du workflow de
> génération IA. Validée par la gérante le 2026-04-27.

#### Groupe A — Page produit unifiée
- [x] Workspace IA inline dans la page produit (galerie compacte, photos sources, actions)
- [x] Affichage des visuels générés en grille 3 colonnes desktop (par type)
- [x] Lightbox au clic (vanilla JS dédié à l'admin via `admin-lightbox.js`)
- [x] Upload drag & drop des photos sources directement depuis la page produit
- [x] Suppression d'une photo source en place
- [x] Boutons d'action inline sur chaque visuel (approuver / rejeter / régénérer)
- [x] Modale de prévisualisation du prompt utilisé (`<dialog>` natif)
- [x] Génération sélective par type (3 boutons : Vignette / Porté / Lifestyle)
- [x] Suppression du fieldset "Photos" Vich (workflow 100% piloté par l'IA)
- [x] Bandeau "Images publiées" en haut du workspace (read-only, lightbox)
- [x] Badges de statut colorés sur chaque vignette
- [x] **Découpe en onglets** : "Fiche produit" / "Visuels IA" via `FormField::addTab()`
- [x] **Workspace compact** : bandeau publiés horizontal + sources/générés en 2 colonnes côte à côte
- [x] Suppression du fieldset "Génération IA" de la sidebar (doublon)
- [x] **Polling hybride** : endpoint `/ai-status` consume 1 message par poll JS (2s) + lock flock contre les courses concurrentes
- [x] Cron fallback `messenger:consume gemini_async --limit=10` documenté pour O2Switch
- [x] Auto-reload de la page quand un statut visuel change (poll JS détecte la transition)

#### Groupe A.ter — Refonte pipeline IA (modèle unifié Gemini 3 Pro)
> Plan : `docs/AI_GENERATION_PIPELINE.md`. Validé le 2026-04-27.
> Diagnostic initial : Gemini 2.5 Flash Image échouait avec `IMAGE_OTHER` sur ~80% des Vignettes/Lifestyle. Investigation Imagen 4 → text-to-image only (pas de référence subject). Bascule sur Gemini 3 Pro Image Preview (jusqu'à 14 reference images, préservation produit native).
- [x] Architecture découplée : `VisualGeneratorInterface`, `GeneratedVisualResult`, `VisualGenerationException`
- [x] `GeminiVisualGenerator` paramétrable (modèle + coût injectés via DI)
- [x] `GeminiImageClient` paramétrable (endpoint construit dynamiquement à partir du modèle)
- [x] `VisualGeneratorRouter` (extensible si re-différenciation par type plus tard)
- [x] Champ `modelUsed` sur `GeneratedVisual` (traçabilité du modèle utilisé)
- [x] `GenerateVisualHandler` branché sur le routeur, coût remonté dynamiquement
- [x] Variables `.env` `GEMINI_PRO_MODEL` + `GEMINI_PRO_COST_USD`
- [x] Migration unifiée mise à jour (colonne `model_used VARCHAR(50)`), DDEV recréé
- [x] Tests manuels validés : Vignette, Porté, Lifestyle sur Chevalière Trèfle Bordeaux
- [x] Prompt Rings/Vignette ajusté : suppression "floating" → ancrage au sol avec contact shadow
- [x] PHPStan niveau 6 + CS Fixer + Twig lint clean

#### Groupe B — Dashboard consommation IA
- [ ] Page `Consommation IA` dans le menu admin
- [ ] Coût mois en cours (€ + USD, conversion via `CurrencyConverter`)
- [ ] Comparaison mois précédent + tendance %
- [ ] Top 10 produits les plus coûteux
- [ ] Ventilation par type de visuel
- [ ] Graphique d'évolution sur 30 jours
- [ ] Indicateur budget restant + alerte > 80%

#### Groupe C — UX avancée
- [ ] Comparaison côte à côte des variantes d'un même type
- [ ] Preview du prompt complet avant lancement de la génération
- [ ] Vue cross-produit "Visuels en attente" + badge notification dans le menu
- [ ] Comparaison source ↔ généré côte à côte
- [ ] Override de prompt spécifique par produit

#### Groupe D — Robustesse & finitions
- [ ] Polling temps réel pendant la génération (statut `Generating`)
- [ ] Historique complet par produit (incluant rejets / échecs)
- [ ] Téléchargement de l'image source haute résolution

### Vérification du milestone (en cours)
> Les 4 phases de développement sont terminées. Le milestone est en phase
> de vérification manuelle avant validation finale.

### Definition of Done
- Upload de photos sources via ProductCrud → SourcePhoto créées en BDD + Flysystem
- Bouton "Générer" dispatche 9 messages Messenger (3 types × 3 variantes)
- Worker consomme les messages → appel Gemini → GeneratedVisual créés
- Interface de validation : approve → copie vers VichUploader, reject, regenerate
- BudgetGuard bloque si budget mensuel dépassé
- Rate limiter respecte 15 req/min
- Prompts éditables dans EasyAdmin par la gérante
- Fallback prompt si catégorie non configurée
- Import images existantes → SourcePhoto (une seule commande)
- PHPStan niveau 6 passe, CS Fixer clean

---

## Milestone 17 — Remplissage IA des contenus produit
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
- [ ] Tests end-to-end happy path & fallback **manuels** (à effectuer par la gérante après recréation DDEV — voir DoD)
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

---

## V2 backlog (post-launch, not in current scope)

- ~~Wishlist persistence (tied to customer account)~~ ✅ Implemented
- ~~Multi-currency Stripe charges (vs current cosmetic conversion)~~ ✅ Replaced by EUR base currency (Milestone 10b)
- Lookbook / editorial seasonal pages
- Referral program ("Give $10, Get $10")
- Loyalty program (3 orders → automatic discount)
- Faire/Ankorstore B2B wholesale channel
