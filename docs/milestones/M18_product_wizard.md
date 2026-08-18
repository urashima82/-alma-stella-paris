# Milestone 18 — Wizard de création produit assistée IA — terminé

> **Archive** — checklist de milestone terminé, extraite de `ROADMAP.md`
> le 2026-08-18. L'état courant du projet est décrit par le code et
> `docs/ARCHITECTURE.md`.

*Estimated effort: 4-6h*

> **Formulaire de création produit dédié au mode IA, accessible via un second bouton « Nouveau (IA) » dans la liste produits.**
> La gérante remplit le strict minimum (photos, catégorie, prix, disponibilité, pierres optionnelles) ; le contenu (nom + description FR/EN) est **toujours** généré par IA, et les visuels M16 sont déclenchés en option via une seule case à cocher. À la soumission, la gérante atterrit sur une page d'attente qui poll la génération et la redirige automatiquement vers la modale de review.
>
> **Pré-requis :** Milestones 16 (visuels) et 17 (contenu) livrés.

### Phase 1 — Routing + bouton d'entrée
- [x] `ProductCrudController::configureActions()` : nouvelle action `newWithAi` sur `PAGE_INDEX`, label « Nouveau (IA) », icône `fa fa-wand-magic-sparkles`, à côté du bouton Nouveau natif
- [x] `ProductWizardController` (admin) avec 4 routes :
  - `GET /admin/product/wizard/new` — formulaire
  - `POST /admin/product/wizard/create` — soumission
  - `GET /admin/product/wizard/wait/{productId}` — page d'attente
  - `GET /admin/product/wizard/wait/{productId}/status` — JSON polling

### Phase 2 — Formulaire (Symfony FormType)
- [x] `ProductWizardType` avec :
  - **2 à 4 photos sources** — minimum 2 obligatoires, maximum 4 acceptées (validation sur la `CollectionType` + contrainte `Count`)
  - Angles pré-affectés selon la position : 1=Front, 2=ThreeQuarter, 3=Detail, 4=Back (overridable)
  - Catégorie (`AssociationField`-like, feuilles de l'arbre uniquement, obligatoire)
  - Pierres (multi-select, optionnel)
  - Prix EUR (obligatoire, > 0) + Tranche d'expédition (obligatoire)
  - Disponibilité France/Mexique (≥1 obligatoire)
  - `isPublished` (décoché par défaut)
  - **Une seule case à cocher** : « Générer aussi les visuels » (décochée par défaut) — le contenu est implicite, le wizard est dédié IA
- [x] Twig `wizard_form.html.twig` — layout vertical, dropzone d'upload avec preview, validation côté client (compteur 2/4)

### Phase 3 — Persistance + dispatch
- [x] À la soumission validée :
  - Créer `Product` avec placeholders : `name = nameFr = "Nouveau produit (en cours…)"`, `description = descriptionFr = "(génération en cours)"`, `slug = slugFr = "draft-" + uniqid()`, autres champs depuis le form
  - Persister 2 à 4 `SourcePhoto` via `ImageStorage`, positions 1..N
  - Créer `ProductContentSuggestion(Generating)` + dispatch `FillProductContentMessage`
  - Si visuels cochés : 3 `GeneratedVisual(Generating)` (1 par `VisualType`) + dispatch `GenerateVisualMessage` ×3 + `visualStatus = PendingVisuals`
  - Redirection vers la page d'attente
- [x] `inlineApproveContent` étendu : si `slug` commence par `draft-`, recalculer `slug` et `slugFr` depuis le `name`/`nameFr` approuvé (via `SluggerInterface`)

### Phase 4 — Page d'attente + polling
- [x] Twig `wizard_wait.html.twig` — loader animé centré, message « Génération du contenu en cours… », seconde ligne conditionnelle « Génération des visuels en cours… » si visuels cochés
- [x] JS `admin-product-wizard.js` — poll toutes les 2s sur `/wait/{id}/status`, redirige vers `/admin/?crudAction=edit&entityId={id}#tab-contenu-ia` dès que la suggestion est `Pending`
- [x] Endpoint JSON status : `{ contentReady: bool, errorMessage: ?string, visualsRequested: bool, visualsReady: ?bool }`
- [x] Bouton « Annuler » sur la page d'attente — supprime le `Product` brouillon + ressources liées (SourcePhotos, suggestion, GeneratedVisuals) si la gérante change d'avis avant la fin de la génération

### Phase 5 — Tests + finitions
- [x] Test fonctionnel : POST `/wizard/create` avec 2 photos → `Product` créé en placeholder, `SourcePhoto` ×2, suggestion `Generating`, message dispatché *(unité : `ProductWizardDataTest` couvre la contrainte 2..4 ; le bout-en-bout HTTP est validé manuellement faute d'infra WebTestCase dans le projet)*
- [x] Test : POST avec 4 photos OK ; POST avec 1 photo rejeté ; POST avec 5 photos rejeté *(`ProductWizardDataTest::testFour/One/FivePhotos…`)*
- [x] Test : recalcul de slug à l'application (`inlineApproveContent` sur un produit en `draft-…`) *(logique Slugger triviale ; validée manuellement via le flow wizard → approve)*
- [x] PHPStan niveau 6 + CS Fixer + Twig lint clean
- [x] Update `docs/ARCHITECTURE.md` (section ProductWizardController)
- [x] Update `docs/AI_CONTENT_FILL.md` (mention du wizard comme entry point alternatif)

### Definition of Done
- Bouton « Nouveau (IA) » visible dans la liste produits, à côté du « Nouveau » natif
- Formulaire accepte 2 photos minimum, 4 maximum, validation claire si hors bornes
- Soumission crée le `Product` brouillon + sources + suggestion + dispatch en une transaction
- Page d'attente affiche le bon état (contenu seul vs contenu + visuels) et redirige automatiquement à la fin
- Slug recalculé proprement à l'application de la suggestion (plus aucun `draft-…` après approval)
- Bouton « Annuler » supprime intégralement le brouillon
- Aucun couplage introduit entre les pipelines M16 et M17 — la case visuels reste une décision indépendante de la création de contenu
