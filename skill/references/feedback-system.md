# MCP Feedback System

But : remonter les manques du MCP quand Bricks sait faire nativement quelque chose mais que l'outil n'est pas disponible ou pas fiable.

## Quand Reporter

Reporter si les trois conditions sont vraies :

1. Bricks supporte la feature nativement.
2. Le MCP ne l'expose pas, ou l'expose mal.
3. Un test ou une tentative montre que le format actuel ne marche pas.

Ne pas reporter :

- une limite native Bricks ;
- une animation/effet qui relève normalement de CSS/JS custom ;
- une feature dont un outil MCP existe déjà mais n'a pas encore été cherché.

## Workflow

```text
besoin -> vérifier outils MCP -> vérifier doc/schema Bricks -> essayer format natif
  -> si ça marche : continuer
  -> si Bricks le supporte mais le MCP bloque : report_missing_feature
  -> si Bricks ne le supporte pas : alternative CSS/JS propre
```

## Report

```js
report_missing_feature({
  title: "Pas d'outil pour Interactions API",
  bricksFeature: "Interactions API",
  bricksDocUrl: "https://academy.bricksbuilder.io/article/interactions/",
  whatItShouldDo: "Ajouter un trigger click qui ouvre un offcanvas",
  whatITried: "update_element avec _interactions écrit en DB mais ignoré au rendu",
  proposedTool: "set_element_interactions",
  bricksVersion: "2.3.2",
  context: "Page galerie, besoin d'ouverture au clic"
})
```

Champs importants :

- `bricksFeature` : nom de la feature native.
- `bricksDocUrl` : preuve que Bricks le supporte.
- `whatITried` : preuve que ce n'est pas juste une supposition.
- `proposedTool` : forme d'outil MCP souhaitée.

## Maintenance

```js
list_missing_features({status: "open"})
resolve_missing_feature({
  id: "fbk_xxx",
  resolutionNote: "Ajouté en vX.Y.Z"
})
```

Les doublons sont regroupés par titre normalisé avec un compteur d'occurrences. Prioriser les gaps récurrents.
