# Milestone 19 — Frais de port, douane et conformité pré-ouverture — en attente d'arbitrage

> **Statut (2026-09-03) : ouvert, bloqué sur les décisions d'Estelle.**
> Aucun code n'a été modifié. Ce fichier consigne l'analyse réglementaire du
> 2026-09-03, le rapport partagé avec Estelle, les décisions à prendre et le
> travail technique qui en découle. Le développeur reprendra ici quand la
> réponse d'Estelle sera connue.
>
> Rapport partagé avec Estelle (non technique) :
> https://claude.ai/code/artifact/16a40f7e-e31e-476d-b53d-560a67efec74
> Copie locale : [`M19_report_estelle_2026-09-03.html`](M19_report_estelle_2026-09-03.html)

---

## Contexte

Le modèle d'expédition (M05, durci en août 2026) date d'avant plusieurs
changements réglementaires : port inclus dans le prix affiché via
`ShippingTier`, zone offerte = UE-27 + Royaume-Uni + Suisse (France
métropolitaine seulement), surcharge « tier × 2 » par article partout ailleurs
(`Order.shippingSurchargeEur`), aucune prise en compte des droits de douane.

Faits établis le 2026-09-03 (Estelle gère seule, la boutique est en production
mais **pas encore ouverte**, toutes les pièces sont légères, Estelle achète
chez des grossistes et reconditionne, elle veut rester discrète sur la
provenance) :

### Ce qui a changé

| Zone | Constat | Source |
|---|---|---|
| UE | Rien à changer : le règlement géoblocage 2018/302 autorise des frais de livraison différents par destination (DOM-TOM compris). Digital Fairness Act = proposition attendue T4 2026, pas de droit positif. | EUR-Lex 2018/302, Your Europe |
| UE (imports) | Franchise de 150 € supprimée au 2026-07-01, droit forfaitaire de 3 €/ligne. Ne concerne le site que pour les **colis de retour** venant de hors UE (à déclarer « Returned goods »). | Commission, La Poste |
| États-Unis | De minimis 800 $ suspendu depuis 2025-08-29 pour tous les modes, y compris postal (permanent au 2027-07-01 via OBBBA, confirmé en justice le 2026-08-13). **Colissimo : l'expéditeur paie les droits à l'achat de l'étiquette sur laposte.fr** (guichet interdit, max 650 €). Origine UE : ~11 % MFN (HTS 7117) + 10 % Section 301 depuis 2026-07-24 ; origine Chine : ~35 %. Frais de gestion La Poste 1 € + 0,40 € + 2,5 % des droits. | Federal Register 2026-06-24, aide La Poste, Colissimo entreprise |
| Canada | Seuil postal 20 CAD inchangé : la cliente paie TPS/TVH + droits + 9,95 CAD Postes Canada à la réception. | Postes Canada |
| Royaume-Uni | Envoi ≤ 135 £ à un particulier ⇒ immatriculation TVA UK obligatoire + 20 % au checkout (règle 2021, toujours en vigueur). Non géré par le site aujourd'hui. Relief douanier 135 £ supprimé en octobre 2028. | GOV.UK |
| Suisse | Destinataire paie 8,1 % de TVA dès ~63 CHF + frais Poste suisse 13 CHF + 3 %. | Poste suisse, ch.ch |
| Rétractation | **Fonction de rétractation en ligne obligatoire depuis le 2026-06-19** pour tout contrat conclu via une interface en ligne (ordonnance 2026-2, art. L221-21, transposition de la directive 2023/2673 → art. 11a CRD). Sanction jusqu'à 75 000 € et délai porté à 12 mois. Le site ne propose qu'e-mail et formulaire de contact. **Ceci invalide l'arbitrage du 2026-08-18** qui classait le webform de rétractation en « bonus UX ». | Village Justice, Trusted Shops, HLC |
| Garantie | Directive 2024/825 (étiquette harmonisée de garantie légale) applicable au 2026-09-27. | Global Policy Watch |
| GPSR | Art. 19 : chaque offre en ligne doit afficher nom + adresse du fabricant. Vendre sous la marque Alma Stella Paris fait d'Estelle le « fabricant » (art. 13) ⇒ bloc fixe avec ses coordonnées professionnelles, jamais le grossiste. Contrepartie : attestations de conformité (nickel, plomb, cadmium) à garder. | Authorised Rep Compliance, Xictron |

### Économie réelle d'une commande US aujourd'hui

Pièce affichée 89 € (tier 10 € inclus), surcharge actuelle 10 € :
Colissimo zone C ~35 € + droits ~21 % de 79 € ≈ 17 € + gestion ≈ 2 €
= **~54 € de coût réel contre 20 € encaissés**.

Tarifs Colissimo 2026 (< 500 g) : zone A (UE, CH) 14,99 € ; UK 18,99 € ;
zone C (US, CA) 35,19 € ; France < 250 g ~5 €.

---

## Décisions en attente (Estelle)

1. **Configuration d'ouverture** (recommandé : B) :
   - A — UE + Suisse seulement.
   - B — Tout sauf États-Unis et Royaume-Uni (destinations « la cliente paie à l'arrivée » = même étiquette laposte.fr que la Suisse).
   - C — Tout, États-Unis compris, en DDP « droits inclus ».
2. **Forfait par zone et par commande** (recommandé) à la place de la surcharge par article ; montants proposés à titre indicatif : UE/CH offert, US 39 € droits inclus, reste du monde 29 €.
3. **Royaume-Uni** : retirer des pays livrables (recommandé) ou immatriculation TVA UK.
4. **Sourcing** : grossistes européens (recommandé, pas d'importation à gérer) ou Mexique avec déclaration commerciale au retour (~4 % + TVA 20 %).
5. **Adresse** : domiciliation commerciale ou adresse actuelle dans le bloc fabricant GPSR (lié au backlog « données perso d'Estelle »).

## Décisions en attente (développeur)

- Schéma produit : champ pays d'origine (enum, défaut France, **admin seulement**, jamais affiché), code SH constant `7117.19`, poids non nécessaire.
- Grille de zones : nouvelle table de réglages ou extension de `ShippingSettings` ; forfait par commande, composante « droits » en % de la valeur pour la zone US si C est retenue.
- Liste des pays livrables : sortir `CheckoutController::INCLUDED_ZONE_COUNTRY_CODES` du code vers un réglage admin (Estelle ouvre/ferme une destination seule).

---

## Sous-étapes envisagées (à confirmer après arbitrage)

Indépendantes de la réponse d'Estelle (obligations légales avant ouverture) :

- [ ] 19a — Fonction de rétractation en ligne (« Renoncer au contrat ici ») : page accessible depuis pied de page, CGV et compte ; référence de commande + e-mail ; accusé de réception horodaté par e-mail ; pattern des pages token-gated. Remplace l'entrée « Withdrawal webform » du backlog V2.
- [ ] 19b — Étiquette harmonisée de garantie légale (directive 2024/825) sur les fiches produit et dans les CGV.
- [ ] 19c — Bloc fabricant GPSR sur les fiches produit (Alma Stella Paris + adresse professionnelle) ; champ origine admin-only sur le produit.
- [ ] 19d — Mise à jour des textes livraison / CGV : `terms.prices_text_2` (« livraison offerte sur toutes les commandes ») est déjà faux, mentions taxes à la réception pour Suisse et Canada, procédure « Returned goods » dans les retours.

Dépendantes de l'arbitrage :

- [ ] 19e — Liste des pays livrables en réglage admin (remplace la constante du contrôleur).
- [ ] 19f — Grille de forfaits par zone et par commande, éditable en admin, remplace la surcharge « tier × 2 » ; preview Stimulus et recalcul serveur alignés.
- [ ] 19g — Si configuration C : ligne « droits de douane inclus » pour les États-Unis, composante en % de la valeur.
- [ ] 19h — Retrait du Royaume-Uni de la zone offerte ou des pays livrables.

## Definition of Done

- Le site n'affiche aucune promesse de livraison offerte sur une destination où la cliente paie des frais à la réception sans en être avertie.
- Une commande hors zone offerte ne coûte jamais plus à Estelle que ce que le forfait de zone couvre, marge assumée exceptée.
- Rétractation en ligne, étiquette de garantie et bloc fabricant en place avant l'ouverture publique.
- Estelle peut ouvrir ou fermer une destination et changer un forfait sans intervention technique.
