# Milestone 15 — Guide des pierres & filtre boutique — terminé

> **Archive** — checklist de milestone terminé, extraite de `ROADMAP.md`
> le 2026-08-18. L'état courant du projet est décrit par le code et
> `docs/ARCHITECTURE.md`.

*Estimated effort: 8-10h*

> **Storytelling + SEO + navigation.** Une page dédiée aux pierres naturelles
> utilisées dans les bijoux Alma Stella, avec un angle émotionnel et spirituel.
> Chaque pierre a sa fiche (vertus, origine, signification). Les pierres sont
> liées aux produits, ce qui permet un filtre par pierre dans la boutique et
> des liens croisés pierre ↔ produit.
>
> **Prérequis :** préparer un fichier `docs/STONES.md` contenant la liste des
> pierres avec leurs descriptions, vertus et origines avant de commencer
> l'implémentation.

### 15a — Entité & migration

- [x] Créer l'entité `Stone` :
  - `name` (string) — nom EN
  - `nameFr` (string) — nom FR
  - `slug` (string, unique) — auto-généré depuis `name`
  - `slugFr` (string, unique) — auto-généré depuis `nameFr`
  - `shortDescription` (string) — accroche courte EN (badges, tooltips)
  - `shortDescriptionFr` (string) — accroche courte FR
  - `description` (text) — description complète EN (page guide)
  - `descriptionFr` (text) — description complète FR
  - `funFact` (text, nullable) — « Le saviez-vous ? » EN
  - `funFactFr` (text, nullable) — « Le saviez-vous ? » FR
  - `traditions` (text, nullable) — traditions culturelles EN
  - `traditionsFr` (text, nullable) — traditions culturelles FR
  - `virtues` (text) — vertus émotionnelles/spirituelles EN
  - `virtuesFr` (text) — vertus émotionnelles/spirituelles FR
  - `chakra` (string, nullable) — chakra(s) associé(s) (ex: « Cœur », « Racine, Cœur »)
  - `color` (string) — couleur dominante (pour affichage visuel)
  - `lustre` (string, nullable) — éclat de la pierre (ex: « Vitreux à cireux »)
  - `origin` (string, nullable) — origine géographique
  - `care` (text, nullable) — conseils d'entretien EN
  - `careFr` (text, nullable) — conseils d'entretien FR
  - `imageName` (string, nullable) — photo de la pierre
  - `position` (integer) — ordre d'affichage
- [x] Relation `ManyToMany` bidirectionnelle entre `Product` et `Stone`
  - Côté propriétaire : `Product` (table de jointure `product_stone`)
  - Un produit peut avoir plusieurs pierres (ex: Duo Émeraude & Malachite)
  - Une pierre est liée à plusieurs produits
- [x] Modifier la migration initiale pour inclure les tables `stone` et `product_stone`
- [x] Recréer l'environnement DDEV et vérifier le schéma

### 15b — EasyAdmin

- [x] `StoneCrudController` :
  - Liste : nom FR, couleur (pastille colorée), nombre de produits liés, position
  - Formulaire : tous les champs bilingues, upload image, sélection des produits liés
  - Drag & drop pour réordonner (position)
- [x] Entrée menu « Pierres » dans la sidebar admin (section Catalogue)
- [x] `ProductCrudController` : ajouter champ `AssociationField` pour les pierres
  - Autocomplete multi-select dans le formulaire produit
  - Colonne « Pierres » visible dans la liste des produits

### 15c — Guide des pierres (pages publiques)

- [x] `StoneGuideController` :
  - Index : `/{_locale}/stones` (EN) / `/{_locale}/pierres` (FR)
  - Détail : `/{_locale}/stones/{slug}` (EN) / `/{_locale}/pierres/{slug}` (FR)
- [x] Template index (`templates/shop/stone/index.html.twig`) :
  - Hero section avec titre et texte d'introduction
  - Grille des pierres : image + nom + accroche courte
  - Au clic → page détail de la pierre
  - Responsive : 1 col (mobile) → 2 col (tablette) → 3 col (desktop)
- [x] Template détail (`templates/shop/stone/show.html.twig`) :
  - Image de la pierre en grand
  - Nom, description complète, vertus (angle émotionnel)
  - Origine géographique
  - Section « Bijoux avec cette pierre » : grille de produits liés (réutiliser le composant carte produit existant)
  - Breadcrumb : Accueil > Guide des pierres > [Nom de la pierre]
- [x] Styles cohérents avec le design system (tokens `alma-*`, typographie Cormorant/Inter)

### 15d — Filtre par pierre dans la boutique

- [x] Ajouter une section « Pierres » dans la sidebar du catalogue (sous les catégories)
  - Liste des pierres avec nombre de produits disponibles
  - Filtre cumulable avec le filtre catégorie existant
  - Option « Sans pierre » pour les bijoux en acier seul
- [x] Route : `/shop?stone={slug}` / `/boutique?stone={slug}` (query parameter)
  - Compatible avec le filtre catégorie : `/shop/bracelets?stone=onyx`
- [x] `CatalogController` : ajouter la logique de filtrage par pierre
- [x] `ProductRepository` : méthode de requête filtrée par pierre(s)
- [x] État actif dans la sidebar (pierre sélectionnée mise en surbrillance)
- [x] Mobile : section pierres intégrée dans le drawer « Filtrer » existant

### 15e — Intégration fiche produit

- [x] Sur la page produit (`show.html.twig`) : afficher les pierres du produit
  - Badges cliquables (lien vers la page détail de la pierre)
  - Style cohérent avec les badges matériaux existants (Acier inoxydable, etc.)
- [x] Tooltip ou texte court sous le badge avec la vertu principale

### 15f — Navigation & SEO

- [x] Lien « Nos pierres » / « Our stones » dans le footer (section « Découvrir »)
- [x] Meta title/description dynamiques sur les pages index et détail
- [x] Breadcrumb `BreadcrumbList` structured data sur les pages guide
- [x] `hreflang` alternates sur toutes les pages pierre
- [x] Ajouter les pages pierres au sitemap XML
- [x] Open Graph tags sur les pages détail

### 15g — DataFixtures

- [x] Charger les pierres depuis `docs/STONES.md` (ou tableau hardcodé dans les fixtures)
- [x] Associer les pierres aux produits existants dans les fixtures
  - Mapping basé sur le nom du produit (extraction automatique de la pierre depuis le nom)
  - Produits « Sans pierre » : aucune association
- [x] Vérifier que les fixtures se chargent sans erreur

### 15h — Documentation

- [x] Mettre à jour `docs/ARCHITECTURE.md` (nouvelle entité, contrôleur, routes)
- [x] Mettre à jour les traductions (`messages.en.yaml`, `messages.fr.yaml`)

### Definition of Done
- Créer une pierre dans EasyAdmin → apparaît dans le guide
- Page index `/fr/pierres` affiche toutes les pierres avec images et accroches
- Page détail `/fr/pierres/onyx` affiche description + vertus + produits liés
- Clic sur un produit lié → fiche produit, clic retour sur la pierre → fiche pierre
- Filtre boutique par pierre fonctionne seul et combiné avec le filtre catégorie
- « Sans pierre » affiche uniquement les bijoux sans association
- Fiche produit affiche les badges pierres cliquables
- Toutes les pages bilingues (FR/EN), accents français corrects
- Pages responsive (375px, 768px, desktop)
- Sitemap inclut les pages pierres
- SEO : meta tags, breadcrumbs structurés, Open Graph
- `PHPStan analyse` passe au niveau 6
- `php-cs-fixer fix` ne signale rien
- Fixtures chargent toutes les pierres et associations sans erreur

---
