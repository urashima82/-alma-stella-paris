# Milestone 14 — Two-level category hierarchy — Status report

## Ce qui est terminé et fonctionne

### 14a — Entity & migration
- `ProductCategory` : `parent` (ManyToOne self-ref nullable) + `children` (OneToMany, OrderBy position)
- Validateur `MaxCategoryDepth` (class-level) : empêche un 3e niveau
- Validateur `LeafCategory` (sur `Product::$category`) : empêche d'attacher un produit à un parent qui a des enfants
- Méthodes helpers : `isLeaf()`, `isRoot()`, `hasChildren()`, `getTotalPublishedProductCount()`, `getTreeLabel()`, `getTreeSortKey()`
- Migration modifiée avec `parent_id INT DEFAULT NULL` + FK + index
- Gedmo Sortable installé : `#[Gedmo\SortablePosition]` sur `position`, `#[Gedmo\SortableGroup]` sur `parent`

### 14b — EasyAdmin category management (PARTIELLEMENT)
- Liste avec tree view : template `admin/field/category_tree_label.html.twig` (↳ prefix pour enfants, bold pour parents)
- Colonne handle `☰` via template `admin/field/sortable_handle.html.twig`
- Tri DQL custom via `IFNULL` (custom DQL function dans `src/Doctrine/IfNullFunction.php`, enregistrée dans `doctrine.yaml`)
- Formulaire : sélecteur parent (dropdown filtré sur racines)
- Compteur produits agrégé (`totalPublishedProductCount`)
- Template custom `admin/category/index.html.twig` (extends EasyAdmin crud/index)

#### Drag & drop — CORRIGÉ
- Le handle `☰` s'affiche dans une colonne dédiée
- Le JS de drag & drop est dans `admin/category/index.html.twig` (block `content_footer`)
- L'endpoint AJAX `POST /admin/category/reorder` existe (`CategoryReorderController`)
- **Bugs corrigés** :
  1. Le sélecteur `.table-responsive table tbody` ne matchait rien — EasyAdmin n'a pas de wrapper `.table-responsive`. Corrigé → `table.datagrid tbody`
  2. `DOMContentLoaded` remplacé par une IIFE — le script est en bas de page, le DOM est déjà prêt
  3. `draggable="true"` déplacé du `<span>` vers le `<tr>` (activé dynamiquement au `mousedown` sur le handle) — meilleur ghost image et pas d'interférence avec les clics sur la ligne
  4. `getRowId()` simplifié → utilise `tr[data-id]` (attribut natif EasyAdmin) au lieu de chercher des checkboxes
- **Drag de groupe (parent ↔ enfants)** :
  - `data-parent-id` ajouté sur le handle span via le template Twig
  - Drag d'un parent → déplace la ligne parent + toutes ses lignes enfants en bloc
  - Drag d'un enfant → réordonne uniquement parmi ses frères/sœurs (même parent)
  - L'indicateur visuel (ligne dorée) s'affiche aux frontières du groupe cible (haut du premier / bas du dernier enfant)
  - Le backend (`CategoryReorderController`) groupe déjà par parent → les positions sont recalculées correctement

### 14c — EasyAdmin product form
- Champ catégorie filtré (QueryBuilder) : feuilles uniquement (sous-catégories + parents sans enfants)
- Affichage hiérarchique `Parent → Enfant` dans la liste produits via `__toString()`

### 14d — Catalog controller & routing
- Route unique `shop_catalog` avec `{parentSlug}/{childSlug}` optionnels
- `/shop` → tous les produits
- `/shop/necklaces` → tous les colliers (toutes sous-catégories)
- `/shop/necklaces/pendants` → seulement les pendentifs
- `/shop/anklets` → anklets directement (parent sans enfants)
- 404 sur slugs invalides ✓
- Breadcrumbs structurés (JSON-LD) à 2 niveaux ✓
- FR fonctionne (`/fr/boutique/colliers/pendentifs`) ✓

### 14e — Shop sidebar (category filters)
- Sidebar desktop : sticky, collapsible, indicateurs gold, compteurs, lignes verticales
- Mobile : bouton "Filtrer" + drawer slide-in avec overlay fade + fermeture Escape
- Stimulus controllers : `collapsible_controller.js`, `category_drawer_controller.js`

### 14f — Navbar dropdown
- Desktop : mega-menu hover sur "Boutique" avec grille de catégories (`nav_dropdown_controller.js`)
- Mobile : lien simple "Boutique" (sans sous-éléments, simplifié à la demande)

### 14g — Fixtures
- 6 parents + 11 sous-catégories (Anklets et Sets sans enfants)
- 12 produits réassignés aux feuilles

### 14h — Repository & Twig
- `findAllOrdered()` : arbre complet (parents + enfants triés)
- `findRootCategories()`, `findChildrenByParent()`, `findLeafCategories()`, `findBySlug()`
- `findVisibleQuery()` : supporte filtre par parent (tous enfants) ou par feuille
- Fonction Twig `category_path()` pour URLs hiérarchiques
- Extension `nav_categories()` pour le menu
- ARCHITECTURE.md mis à jour

## Fichiers créés/modifiés

### Nouveaux fichiers
- `src/Validator/MaxCategoryDepth.php` + `MaxCategoryDepthValidator.php`
- `src/Validator/LeafCategory.php` + `LeafCategoryValidator.php`
- `src/Doctrine/IfNullFunction.php`
- `src/Controller/Admin/CategoryReorderController.php`
- `src/Twig/CategoryNavExtension.php`
- `assets/controllers/collapsible_controller.js`
- `assets/controllers/category_drawer_controller.js`
- `assets/controllers/nav_dropdown_controller.js`
- `templates/admin/field/sortable_handle.html.twig`
- `templates/admin/field/category_tree_label.html.twig`
- `templates/admin/category/index.html.twig`
- `config/packages/stof_doctrine_extensions.yaml`

### Fichiers modifiés
- `src/Entity/ProductCategory.php` — parent/children + Gedmo attributes
- `src/Entity/Product.php` — LeafCategory validator
- `src/Repository/ProductCategoryRepository.php` — nouvelles méthodes
- `src/Repository/ProductRepository.php` — findVisibleQuery supporte hiérarchie
- `src/Controller/Shop/CatalogController.php` — refonte complète des routes
- `src/Controller/Admin/ProductCategoryCrudController.php` — tree view + reorder
- `src/Controller/Admin/ProductCrudController.php` — filtre catégories feuilles
- `src/Twig/LocaleProductExtension.php` — category_path() + UrlGeneratorInterface
- `src/DataFixtures/AppFixtures.php` — hiérarchie de catégories
- `templates/shop/catalog/index.html.twig` — sidebar + drawer + grille
- `templates/shop/base.html.twig` — navbar dropdown + mobile simplifié
- `templates/shop/product/show.html.twig` — breadcrumbs hiérarchiques
- `templates/sitemap.xml.twig` — URLs hiérarchiques
- `translations/messages.en.yaml` — clés catalog.filter_*
- `translations/messages.fr.yaml` — idem FR
- `migrations/Version20260408140632.php` — parent_id + FK
- `config/packages/doctrine.yaml` — IFNULL DQL function
- `config/bundles.php` — StofDoctrineExtensionsBundle
- `docs/ARCHITECTURE.md` — Stimulus controllers + entity description
- `docs/ROADMAP.md` — toutes les tâches cochées

## Packages ajoutés
- `gedmo/doctrine-extensions` ^3.22
- `stof/doctrine-extensions-bundle` ^1.15
