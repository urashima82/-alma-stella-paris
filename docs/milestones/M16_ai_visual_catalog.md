# Milestone 16 — Catalogue IA (génération visuels) — terminé

> **Archive** — checklist de milestone terminé, extraite de `ROADMAP.md`
> le 2026-08-18. Les groupes de confort B/C/D (dashboard conso, UX avancée,
> robustesse) ont été **reportés au backlog V2** le même jour — voir la fin
> de ce fichier. L'état courant du projet est décrit par le code et
> `docs/ARCHITECTURE.md`. Résumés de phase : `M16_PHASE1_DONE.md` à
> `M16_PHASE4_DONE.md`.

*Estimated effort: 20-25h*

> **Génération automatique de visuels produits par IA (Gemini 2.5 Flash Image).**
> La gérante uploade des photos smartphone "brutes" d'un bijou et obtient
> 3 visuels professionnels (vignette, porté, lifestyle) × 3 variantes chacun.
> Validation humaine obligatoire avant publication.
>
> **Specs complètes :** `docs/CATALOGUE-IA-SPECS.md`
> **Audit :** `docs/CATALOGUE_IA_AUDIT.md`
> **Plan d'adaptation :** `docs/CATALOGUE_IA_PLAN.md`

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

### Phase 5 — Améliorations IHM admin
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

## Déféré au backlog V2 (arbitrage du 2026-08-18)

Les trois groupes de confort de la Phase 5 sortent du scope du milestone ;
détail conservé ici, entrées correspondantes dans le backlog V2 de `ROADMAP.md` :

**Groupe B — Dashboard consommation IA** : page `Consommation IA` dans le menu
admin ; coût mois en cours (€ + USD via `CurrencyConverter`) ; comparaison mois
précédent + tendance % ; top 10 produits les plus coûteux ; ventilation par type
de visuel ; graphique d'évolution sur 30 jours ; indicateur budget restant +
alerte > 80 %.

**Groupe C — UX avancée** : comparaison côte à côte des variantes d'un même
type ; preview du prompt complet avant lancement ; vue cross-produit « Visuels
en attente » + badge notification dans le menu ; comparaison source ↔ généré
côte à côte ; override de prompt spécifique par produit.

**Groupe D — Robustesse & finitions** : polling temps réel pendant la
génération (statut `Generating`) ; historique complet par produit (incluant
rejets / échecs) ; téléchargement de l'image source haute résolution.
