# Milestone 16 — Phase 4 : Import adapte + finitions — TERMINE

**Date :** 2026-04-17
**Statut :** Termine

---

## Resume

Adaptation de la commande d'import des images catalogue pour creer des `SourcePhoto`
via Flysystem au lieu de remplir les champs VichUploader. Correction de la configuration
Flysystem pour resoudre `%kernel.project_dir%` via le processeur `resolve:`.
Verification end-to-end complete.

## Fichiers modifies

| Fichier | Modification |
|---|---|
| `src/Command/ImportCatalogueImagesCommand.php` | Remplacement complet : `ImageProcessor` + VichUploader → `ImageStorage` (Flysystem) + `SourcePhoto` entities. Import de toutes les photos (pas seulement 3). 1ere photo → `PhotoAngle::Front`, suivantes → `PhotoAngle::Other`. Mise a jour `visualStatus` → `PendingVisuals`. |
| `config/packages/flysystem.yaml` | `%env(IMAGE_STORAGE_PATH)%` → `%env(resolve:IMAGE_STORAGE_PATH)%` pour resolution du parametre `%kernel.project_dir%` |
| `docs/ROADMAP.md` | Phase 4 cochee |

## Ce qui fonctionne

- `app:import-catalogue-images --dry-run` : liste 221 produits, 532 photos sans modification
- `app:import-catalogue-images` : importe 532 SourcePhoto en BDD + fichiers Flysystem
- Chemins Flysystem corrects : `{productId}/sources/{position}.{ext}`
- 1ere photo = angle `front`, suivantes = angle `other`
- 221 produits passes en `visual_status = pending_visuals`
- Fichiers physiques dans `var/storage/products/{id}/sources/`
- 12 CategoryVisualPrompt charges via fixtures
- PHPStan niveau 6 : 0 erreur
- PHP CS Fixer : 0 diff
- Tailwind + asset-map compiles

## Pipeline end-to-end

1. `ddev delete --omit-snapshot && ddev start` — environnement propre
2. `doctrine:migrations:migrate` — schema complet (56 queries)
3. `doctrine:fixtures:load` — 222 produits + 12 prompts visuels
4. `app:import-catalogue-images` — 532 SourcePhoto (BDD + Flysystem)
5. Admin : produits visibles avec sources, CategoryVisualPrompt accessibles
6. Bouton "Generer" dispatche les messages Messenger
7. Worker `messenger:consume gemini_async` consomme les messages

## Milestone 16 — Statut final

Toutes les 4 phases sont terminees :
- Phase 1 : Modele de donnees (enums, entites, migration, fixtures)
- Phase 2 : Cerveau IA + Queue (Gemini client, prompts, budget, messenger)
- Phase 3 : Back-office EasyAdmin (CRUDs, validation, approval handler)
- Phase 4 : Import adapte + finitions (commande SourcePhoto, verification e2e)
