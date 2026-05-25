# Bricks Verification

But : ne jamais valider une page uniquement parce que le JSON semble correct.

## Après Chaque Section

```js
verify_element({
  pageId,
  elementId,
  viewports: ["desktop", "mobile_portrait"]
})
```

Utiliser sur le parent de section ou le bloc principal modifié.

Corriger avant de continuer si le report signale :

- élément introuvable ou invisible ;
- overflow horizontal ;
- flex/grid non appliqué ;
- gap/padding ignoré ;
- fonts non chargées ;
- images cassées ou alt manquant ;
- containers visibles vides ;
- incohérence évidente entre frères ;
- erreurs console.

## Checks `verify_element`

| Check | But |
|---|---|
| expected styles | compare settings attendus vs computed styles |
| overflow | détecte débordements horizontaux |
| console errors | erreurs JS frontend |
| media health | images/videos cassées, alt manquant |
| empty containers | blocs Bricks visibles sans contenu |
| sibling coherence | alignements/tailles incohérents entre frères |

Les catégories peuvent être désactivées ponctuellement :

```js
verify_element({
  pageId,
  elementId,
  checks: {empty_containers: false}
})
```

## Audit Technique Fullpage

```js
audit_page({
  pageId,
  viewports: ["desktop", "mobile_portrait"],
  maxAnnotations: 30
})
```

À utiliser :

- au début d'une refonte pour l'état initial ;
- après plusieurs sections ;
- avant livraison.

`audit_page` cherche surtout les bugs objectifs : overflow global, médias, containers vides, cohérence siblings. Il ne remplace pas une critique webdesign.

## Audit Webdesign Général

```js
audit_design_page({
  pageId,
  viewports: ["desktop", "mobile_portrait"],
  brief: "contexte métier, niveau de gamme, objectif principal",
  maxSections: 8
})
```

L'IA doit inspecter les screenshots, pas seulement le JSON. Chercher les problèmes qu'un humain exigeant verrait :

- hiérarchie confuse ;
- rythme vertical cassé ;
- section qui ne semble pas appartenir au même site ;
- cartes pas homogènes ;
- CTA trop long, mal placé ou faible ;
- image mal cadrée ou collée au header ;
- largeur de texte/cards peu crédible ;
- typo trop petite/grande ou mauvais rapport eyebrow/titre/body ;
- mobile correct techniquement mais pas harmonieux ;
- rendu trop template, trop vide, trop lourd ou pas assez premium.

## Findings Design

Format attendu :

```json
{
  "severity": "critical|major|minor",
  "confidence": "high|medium|low",
  "viewport": "desktop",
  "section": "section 3 / Services",
  "issue": "Les cards n'ont pas le même rythme visuel.",
  "whyItMatters": "La section paraît moins finie que le reste.",
  "suggestedFix": "Uniformiser la hauteur, ajouter des icônes cohérentes et réduire la largeur max."
}
```

Règles :

- Ne pas transformer chaque différence en bug.
- Ne pas se cacher derrière "subjectif" si le défaut nuit à l'harmonie.
- Mettre `confidence: "low"` seulement si le choix dépend clairement du goût du client.
- Après retour humain, convertir les ratés en calibration pour les prochains audits.

## Livraison

Avant de conclure :

```js
audit_page({pageId, viewports: ["desktop", "mobile_portrait"]})
audit_design_page({pageId, viewports: ["desktop", "mobile_portrait"], brief})
```

La page est livrable seulement si :

- aucun problème critique technique ;
- les sections modifiées ont été vérifiées ;
- les findings design majeurs sont corrigés ou assumés ;
- le CSS custom restant est justifié ;
- la structure Bricks reste éditable par un humain.
