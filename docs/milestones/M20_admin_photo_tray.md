# Milestone 20 — Admin produits : upload multiple & ergonomie mobile

> Ouvert le 2026-09-20. Refonte de la section Produits de l'administration,
> motivée par l'usage réel : Estelle administre la boutique **depuis son
> téléphone**, et le parcours de création la trompait.
>
> Ce fichier garde les arbitrages et le *pourquoi*. L'état courant du code est
> décrit par le code et par la section « Admin back-office » de
> `docs/ARCHITECTURE.md`.

---

## Problèmes constatés

1. **Deux boutons de création côte à côte.** Le « Nouveau » natif d'EasyAdmin
   ouvrait un formulaire vierge incapable de produire une fiche exploitable —
   le contenu comme les visuels sont générés à partir des photos sources. Par
   habitude, c'est ce bouton-là qui était cliqué.

2. **Deux parcours d'upload incohérents.** Le wizard proposait 4 emplacements
   figés (un `<input type="file">` par slot, angle pré-affecté), l'édition un
   fichier à la fois — angle choisi *avant* le fichier, puis **rechargement
   complet de la page** à chaque photo, ce qui écrasait les modifications non
   enregistrées du formulaire produit.

3. **Interface non tenable au doigt.** Bouton de suppression révélé au `:hover`
   donc invisible sur mobile, légendes à 0.6rem (9,6 px), `<select>` à 0.65rem,
   cibles tactiles de 22 px, `window.confirm()` / `window.alert()`, et un seul
   media query sur tout le workspace IA.

## Bugs trouvés en chemin

- **Collision de positions.** `ImageStorage::storeSourcePhoto()` dérivait le
  chemin de stockage de la position, elle-même calculée en `COUNT() + 1`. Avec
  des photos en 1/2/3, supprimer la 2 ramenait `COUNT()` à 2 : l'upload suivant
  reprenait la position 3 et **écrasait le fichier de la photo 3** encore en
  base. Corrigé par `SourcePhotoRepository::nextPositionFor()` (`MAX + 1`) et un
  garde-fou d'unicité dans `ImageStorage`. Couvert par
  `tests/Service/Visual/ImageStorageTest.php`.
- **`PhotoAngle` n'est lu par aucun pipeline IA.** Ni `ContentPromptBuilder`, ni
  `VisualPromptBuilder`, ni les handlers : toutes les photos partent chez Gemini
  sans distinction. Constat remonté avant de coder — décision ci-dessous.

## Arbitrages (2026-09-20)

| Question | Décision | Raison |
|---|---|---|
| Bouton « Nouveau » natif | Visible pour le **super admin** uniquement | Masquer suffit pour Estelle ; la permission ferme aussi la route, tout en gardant une porte de sortie si le pipeline IA tombe |
| Transport de l'upload dans le wizard | **Staging client + un seul POST** | Aucune entité temporaire, aucun orphelin, annulation gratuite. Écartées : le brouillon produit créé d'emblée (purge à écrire, brouillons à masquer) et le stockage temporaire de session (endpoint + cron en plus) |
| Angle de vue | **Conservé**, auto-affecté et éditable | Zéro friction si l'ordre convient ; le champ reste disponible si on décide un jour de l'exploiter dans les prompts. Écartées : la suppression (migration, perte de métadonnée) et le branchement immédiat dans les prompts (change la qualité des sorties IA, demande sa propre validation) |
| Compression navigateur | **Oui, 2048 px max en WebP** | Une photo iPhone passe de 4-8 Mo à ~400 Ko : c'est ce qui rend le POST unique viable en 4G, et ça allège aussi chaque appel Gemini |

## Ce qui a été livré

- **Un composant unique**, `_source_photo_tray.html.twig` + `admin-photo-tray.js`,
  partagé par le wizard (mode `staged`) et l'onglet Visuels IA (mode `live`).
  Même geste des deux côtés : on choisit plusieurs photos d'un coup, on les voit,
  on règle l'angle, on valide.
- **Plus de rechargement de page en édition** : les mutations de photos sources
  répondent le fragment re-rendu, que le JS échange sur place.
- **Passe tactile** : suppression toujours visible, cibles ≥ 44 px, `<select>` à
  16 px (sinon iOS Safari zoome la page au focus), barre d'action collante avec
  `env(safe-area-inset-bottom)`, et une modale `<dialog>` (`window.AdminConfirm`)
  à la place de `window.confirm()`.
- **Garde-fou côté client dans le wizard** : le bouton de soumission reste
  verrouillé tant que le compte de photos ne satisfait pas la règle serveur —
  confort, pour prévenir l'erreur la plus courante plutôt que l'expliquer. Le
  vrai filet est la soumission XHR décrite plus bas.

## Suite du 2026-09-20 — perte des photos sur erreur de formulaire

Premier retour d'usage : un champ manquant (catégorie, prix) faisait re-rendre
la page, et les photos déjà choisies disparaissaient. Le verrou client ne
couvrait que le **nombre de photos**, pas les autres champs obligatoires.

La correction proposée — téléverser dès la sélection — a été écartée après
vérification : `Product.category` est en `nullable: false`, donc impossible de
créer le produit brouillon à l'ouverture du wizard pour y rattacher les photos,
sauf à rendre la colonne nullable pour *tous* les produits sur une base de
production.

Surtout, la cause racine n'est pas « les fichiers sont côté client » mais
« **la page se re-rend** ». Le formulaire est donc soumis en XHR : le serveur
répond `422` avec ses erreurs en JSON, le JS les peint à côté des champs, et on
ne navigue jamais. Les photos survivent ainsi à *toutes* les causes de rejet —
champ manquant, coupure réseau, refus serveur non anticipé — et pas seulement à
la validation.

Écartées au passage : le stockage temporaire de session (endpoint + modèle de
données distinct + déplacement des fichiers + cron de purge) et l'extension du
verrou client aux autres champs (colmatage : le serveur peut toujours refuser
pour un motif que le client ignore).

## Pièges rencontrés

- **CSP.** `SecurityHeadersSubscriber` pose `img-src 'self' data:` pour tout le
  site. Un `blob:` — le réflexe naturel pour un aperçu local — s'affiche en image
  cassée *sans erreur console*. Les aperçus passent donc par `FileReader` en
  `data:` URL, plutôt que d'élargir l'en-tête pour la boutique publique.
- **`<template>` inerte.** Une `<img>` clonée depuis un `<template>` ne charge
  rien si sa `src` est posée avant l'insertion dans le document : l'adoption ne
  relance pas le chargement. La carte est insérée d'abord, la `src` posée ensuite.
- **`hidden` battu par `display`.** Une règle auteur `display: flex` l'emporte sur
  le `[hidden] { display: none }` de la feuille du navigateur : la zone d'ajout
  serait restée visible une fois les 4 photos atteintes.
- **Toast générique sur 422.** `admin-toast.js` intercepte les mutations `/admin`
  et affichait « Une erreur est survenue » par-dessus les messages précis du
  formulaire. Un 422 est une réponse de validation, pas une panne : il est
  désormais ignoré par l'intercepteur.

## Reste ouvert

- Aucun réordonnancement des photos : la position ne sert qu'au nommage des
  fichiers et à l'affichage, et le glisser-déposer est pénible au doigt.
- L'angle reste décoratif tant que les prompts ne le lisent pas.
