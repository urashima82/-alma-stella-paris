# AI Content Fill (Milestone 17)

> **⚠️ RÉCIT DE CONCEPTION — figé à avril 2026.** Écrit au moment de la conception
> du Milestone 17, aujourd'hui livré, et non mis à jour depuis. Le code fait foi
> pour l'état courant ; ce document ne vaut que pour le *pourquoi* des choix de
> conception.

> Génération automatique des contenus textuels d'un produit (FR + EN) par IA
> multimodale (Gemini 2.5 Flash) à partir des photos sources et du contexte
> taxonomique (catégorie, pierres). Validation humaine obligatoire avant
> écriture en base.

Ce pipeline est **totalement indépendant** du pipeline IA visuel (M16) :
modèle Gemini distinct, queue, statuts, services, onglet admin séparés. Les
deux peuvent être lancés isolément ; un changement dans l'un n'affecte
jamais l'autre.

---

## 1. Architecture

```
ProductCrudController (tab "Contenu IA")
        │  inlineGenerateContent / inlineRegenerate / inlineApprove / inlineReject / inlineUpdate
        │  aiContentStatus (polling)
        ▼
FillProductContentMessage  ──► Messenger (gemini_async)
                                       │
                                       ▼
                           FillProductContentHandler
                                       │
            ┌──────────────────────────┼──────────────────────────────┐
            ▼                          ▼                              ▼
      RateLimiter            ProductContentFiller              BudgetGuard
   (15 req/min, shared)              │                       (shared monthly $)
                                     ▼
                            ContentPromptBuilder
                            (BrandVoice + FewShot + Context + Fallback)
                                     │
                                     ▼
                              GeminiTextClient
                          (Gemini 2.5 Flash multimodal,
                           responseSchema = strict JSON)
                                     │
                                     ▼
                          ProductContentSuggestion
                                  persisted
                          (Generating → Pending)
```

---

## 2. Statuts d'une suggestion

| Statut       | Rôle                                                        |
|--------------|-------------------------------------------------------------|
| `Generating` | Worker en cours d'exécution, contenu pas encore prêt.       |
| `Pending`    | Contenu produit, en attente de revue par la gérante.        |
| `Approved`   | (Réservé pour usage futur — actuellement on saute à Applied). |
| `Applied`    | Copié sur l'entité `Product`. Suggestion archivée.          |
| `Rejected`   | Rejetée (manuellement ou via régénération). Garde l'historique. |

L'invariant UI : il y a au plus **une suggestion active** (Generating ou
Pending) par produit. Lorsque la gérante régénère, l'ancienne pending est
marquée Rejected.

---

## 3. Stratégie de prompt

Le prompt système assemble cinq blocs, dans cet ordre :

1. **Brand voice** (`ContentBrandVoiceProvider`) — règles de ton, de
   structure (≤ 90 mots, deux paragraphes), de vocabulaire interdit.
2. **Task instructions** — sortie attendue : 4 champs `nameFr`, `nameEn`,
   `descriptionFr`, `descriptionEn`. Accents UTF-8 obligatoires en FR.
3. **Fallback instructions** — adapté en fonction du contexte présent.
4. **Few-shot examples** (`ContentFewShotProvider`) — 3 à 4 paires
   « catégorie+pierres → contenu attendu » figées, **textuel uniquement**
   (pas d'images embarquées, pour rester découplé du pipeline visuel).
5. **Dynamic context** — la catégorie (avec parent) et les pierres
   réellement attachées au produit, capturées dans `contextSnapshot` JSON.

Si la gérante régénère avec une instruction libre, un sixième bloc
`ADDITIONAL STEERING (mandatory)` est ajouté.

### 3.1 Stratégie de fallback

| Catégorie | Pierres | Comportement                                                                  |
|-----------|---------|-------------------------------------------------------------------------------|
| ✓         | ✓       | `CONTEXT IS COMPLETE` — l'IA s'appuie sur les valeurs fournies sans contradiction. |
| ✗         | ✓       | `CATEGORY UNKNOWN` — l'IA déduit le type de bijou depuis les photos.          |
| ✓         | ✗       | `STONE UNKNOWN` — l'IA décrit la pierre par couleur/apparence sans la nommer (risque d'erreur d'identification). |
| ✗         | ✗       | `NEITHER … GIVEN` — mode purement descriptif visuel, contenu plus générique attendu. |

`usedFallback` est `true` dès que catégorie OU pierres manquent ; il est logué
dans `GeminiUsageLog` (via le worker).

---

## 4. JSON schema (responseSchema Gemini)

Forcé strict, exactement quatre champs requis :

```json
{
  "type": "OBJECT",
  "properties": {
    "nameFr":         { "type": "STRING" },
    "nameEn":         { "type": "STRING" },
    "descriptionFr":  { "type": "STRING" },
    "descriptionEn":  { "type": "STRING" }
  },
  "required": ["nameFr", "nameEn", "descriptionFr", "descriptionEn"]
}
```

`temperature = 0.7` pour laisser un peu de variabilité créative tout en
restant ancré sur le contexte fourni.

---

## 5. Indépendance des pipelines (M16 ↔ M17)

| Aspect                | Visuels (M16)                  | Contenu (M17)                       |
|-----------------------|--------------------------------|-------------------------------------|
| Modèle Gemini         | `gemini-3-pro-image-preview`   | `gemini-2.5-flash`                  |
| Variable d'env        | `GEMINI_PRO_MODEL`             | `GEMINI_FLASH_TEXT_MODEL`           |
| Coût par appel        | `GEMINI_PRO_COST_USD`          | `GEMINI_FLASH_TEXT_COST_USD`        |
| Message               | `GenerateVisualMessage`        | `FillProductContentMessage`        |
| Handler               | `GenerateVisualHandler`        | `FillProductContentHandler`        |
| Statut produit        | `Product.visualStatus`         | (aucun — l'état vit sur la suggestion) |
| Onglet EasyAdmin      | Visuels IA                     | Contenu IA                          |
| Polling endpoint      | `aiStatus`                     | `aiContentStatus`                   |
| JS controller         | `admin-ai-poll.js`             | `admin-ai-content.js`               |
| Statuts métier        | `VisualStatus` (5 états)       | `ContentSuggestionStatus` (5 états) |
| Log `GeminiUsageLog`  | `operation = visual`           | `operation = text_fill`             |

Ressources **partagées** (volontairement) :

- `BudgetGuard` — budget mensuel commun (`GEMINI_MONTHLY_BUDGET_USD`).
- Rate limiter `gemini_api` — 15 req/min globales.
- Transport Messenger `gemini_async`.
- `ImageStorage` — lecture des photos sources.
- `BrandStyleProvider` (visuel) vs `ContentBrandVoiceProvider` (texte) :
  **séparés volontairement**, pas de fusion.

---

## 6. Conditions d'activation du bouton « Générer le contenu »

| Pré-requis                  | Comportement                                                        |
|-----------------------------|---------------------------------------------------------------------|
| ≥ 1 `SourcePhoto`           | **Obligatoire**. Bouton désactivé sinon, bandeau rouge bloquant.    |
| Catégorie renseignée        | Recommandée. Bandeau jaune non bloquant si absente.                 |
| Pierres renseignées         | Recommandées. Bandeau jaune non bloquant si absentes.               |
| Génération déjà en cours    | Bouton désactivé tant qu'une suggestion `Generating` existe.        |

---

## 7. Modale de revue (Pending)

Quatre champs éditables (`nameFr`, `nameEn`, `descriptionFr`, `descriptionEn`),
plus :

- **Régénérer** — ouvre un prompt natif demandant une instruction
  additionnelle (optionnelle), rejette la suggestion courante, en crée une
  nouvelle, dispatche le message.
- **Rejeter** — passe la suggestion en `Rejected`, garde l'historique.
- **Appliquer au produit** — sauvegarde les éditions (`inlineUpdateContent`),
  copie les 4 champs sur le `Product`, marque `Applied`, recharge la page.

Le `contextSnapshot` JSON est affiché en lecture seule (collapsible) pour
que la gérante voie quelles infos l'IA a reçues, indépendamment d'éventuels
changements ultérieurs sur le produit.

---

## 8. Variables d'environnement

```dotenv
# .env (committed defaults)
GEMINI_API_KEY=                            # filled in .env.local
GEMINI_MONTHLY_BUDGET_USD=30               # shared with M16
GEMINI_PRO_MODEL=gemini-3-pro-image-preview   # M16 only
GEMINI_PRO_COST_USD=0.120
GEMINI_FLASH_TEXT_MODEL=gemini-2.5-flash      # M17 only
GEMINI_FLASH_TEXT_COST_USD=0.005
```

---

## 9. Tests

`tests/Service/Content/` couvre :

- `ContentFewShotProviderTest` — vérifie qu'au moins 3 catégories sont
  représentées et qu'au moins un exemple omet la pierre (apprentissage du
  fallback).
- `ContentPromptBuilderTest` — vérifie les quatre branches de fallback,
  l'injection du `additionalContext`, le schéma JSON forcé.
- `tests/Enum/ContentSuggestionStatusTest` — vérifie label + badge + ordre.
- `tests/Form/Admin/ProductWizardDataTest` — couvre la contrainte Callback
  qui borne à 2..4 le nombre de photos uploadées (slots vides ignorés).

Tests d'intégration end-to-end (worker + Gemini réel) effectués
manuellement depuis l'admin — voir DoD du Milestone 17 dans `ROADMAP.md`.

---

## 10. Wizard de création (entry point alternatif — Milestone 18)

Le `ProductWizardController` (`/admin/product/wizard/...`) est un parcours
dédié à la création initiale d'un produit, conçu pour minimiser la saisie
de la gérante.

```
ProductCrudController index
        │  bouton « Nouveau (IA) »
        ▼
GET  /admin/product/wizard/new        ── ProductWizardType (form)
POST /admin/product/wizard/create     ── persist Product (placeholders + slug `draft-…`)
                                          + 2..4 SourcePhoto via ImageStorage
                                          + ProductContentSuggestion(Generating)
                                          + dispatch FillProductContentMessage
                                          [optional] + 3 GeneratedVisual(Generating)
                                                     + dispatch GenerateVisualMessage ×3
                                                     + visualStatus = PendingVisuals
        ▼
GET  /admin/product/wizard/wait/{id}  ── page d'attente avec spinner
GET  .../wait/{id}/status             ── JSON { contentReady, errorMessage,
                                                visualsRequested, visualsReady }
POST /admin/product/wizard/cancel/{id} ── supprime le brouillon (fichiers + entités)
        │
        │ JS poll 2s — redirige dès que `contentReady = true`
        ▼
ProductCrudController edit (#tab-contenu-ia)
        │  inlineApproveContent — étendu : si `slug` commence par `draft-`,
        │  recalcule `slug` et `slugFr` via AsciiSlugger sur le nom approuvé.
        ▼
Produit avec slug réel + contenu appliqué
```

Le wizard et la modale de revue inline restent **indépendants** des
pipelines visuels (M16) et contenu (M17) sous-jacents : il ne fait que
les déclencher. La case « Générer aussi les visuels » est la seule
décision couplée à M16 ; en l'absence de coche, aucun `GeneratedVisual`
n'est créé.
