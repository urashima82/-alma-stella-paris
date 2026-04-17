# Milestone 16 — Phase 3 : Back-office EasyAdmin

**Date :** 2026-04-17
**Statut :** Terminé

---

## Résumé

Intégration complète du back-office EasyAdmin pour la gestion des visuels IA :
CRUD des prompts visuels, interface de validation (approve/reject/regenerate),
copie automatique des visuels approuvés vers les images produit, et bouton
de génération dans le ProductCrud.

## Fichiers créés

| Fichier | Rôle |
|---|---|
| `src/Controller/Admin/CategoryVisualPromptCrudController.php` | CRUD prompts visuels par catégorie × type |
| `src/Controller/Admin/GeneratedVisualCrudController.php` | Validation visuels : approve, reject, regenerate |
| `src/Service/Visual/VisualApprovalHandler.php` | Copie Flysystem → VichUploader avec traitement image |

## Fichiers modifiés

| Fichier | Modification |
|---|---|
| `src/Controller/Admin/ProductCrudController.php` | Actions "Générer les visuels" + "Voir les visuels", fieldset IA |
| `src/Controller/Admin/ProductCategoryCrudController.php` | Champs preservationInstructions + specificFocus |
| `src/Controller/Admin/DashboardController.php` | Section "Génération IA" dans le menu sidebar |
| `docs/ARCHITECTURE.md` | Ajout des nouveaux controllers et service |
| `docs/ROADMAP.md` | Phase 3 cochée |

## Fonctionnalités

### CategoryVisualPromptCrudController
- Liste avec filtres par catégorie et statut actif
- Formulaire avec fieldsets : identification (catégorie, type, version, actif)
  et contenu du prompt (framing, staging, props en ArrayField)
- Labels et aide en français

### GeneratedVisualCrudController
- Liste avec aperçu image, badges statut/type, filtres par produit/type/status
- Actions custom :
  - **Approuver** : passe en Approved, copie l'image vers VichUploader via VisualApprovalHandler,
    met à jour visualStatus du produit si tous les types ont un visuel approuvé
  - **Rejeter** : passe en Rejected
  - **Regénérer** : dispatche un nouveau GenerateVisualMessage, passe en Generating
- Page détail avec prompt complet, version, request ID Gemini, message d'erreur

### VisualApprovalHandler
- Lit l'image depuis Flysystem → fichier temporaire → ImageProcessor (crop 4:5, WebP)
- Copie vers `public/uploads/products/` avec nom unique
- Mapping : Vignette → thumbnail, Worn → wornPhoto, Lifestyle → contextPhoto

### ProductCrudController enrichi
- Action "Générer les visuels" : dispatche 9 messages (3 types × 3 variantes),
  passe visualStatus en PendingVisuals
- Action "Voir les visuels" : redirige vers GeneratedVisualCrud filtré par produit
- Fieldset "Génération IA" avec sélecteur visualStatus

### ProductCategoryCrudController enrichi
- Champs preservationInstructions et specificFocus (TextareaField, edit only)
- Aide contextuelle en français

### Menu admin
- Section "Génération IA" entre Catalogue et Ventes
- Lien "Prompts visuels" et "Visuels générés" avec badge compteur des visuels en attente

## Qualité

- PHPStan niveau 6 : 0 erreur
- PHP CS Fixer : 0 diff
