# Bricks Conversion Workflow

But : convertir/refondre une page avec une cadence fiable et peu de CSS custom.

## 1. Découvrir

```js
health_check()
get_global_styles()
get_element_schema()
list_color_palette()
list_global_classes()
list_theme_styles()
list_css_variables()
list_custom_fonts()
```

Sur une page existante :

```js
get_page_structure({pageId})
find_elements({pageId, criteria: {type: "section"}, limit: 30})
```

Identifier :

- sections et ordre de lecture ;
- fonts, couleurs, espacements, rayons, ombres ;
- composants réutilisables ;
- global classes, variables, frameworks éventuels ;
- query presets/dynamic data si la page est dynamique ;
- assets nécessaires ;
- différences desktop/mobile.

## 2. Mapper

Ne pas copier le HTML brut. Traduire :

- structure HTML -> éléments Bricks ;
- classes CSS réutilisées -> global classes ;
- tokens CSS -> palette / variables ;
- règles CSS simples -> settings natifs ;
- pseudos/animations spécifiques -> CSS page/global seulement si nécessaire.

Si une page existe déjà, préférer une modification ciblée à un remplacement complet : chercher les éléments, patcher les settings, vérifier. Remplacer toute la page seulement pour une refonte assumée.

Lire [html-css-to-bricks.md](html-css-to-bricks.md) avant de créer la structure.

## 3. Construire par sections

Cadence :

```text
1 section ou 5-10 éléments
-> verify_element
-> corriger
-> continuer
```

Éviter les gros imports non vérifiés.

Avant une mutation risquée :

- dupliquer la page ou garder le JSON complet obtenu par `get_page_json` ;
- faire une modification minimale ;
- vérifier avant de continuer.

Pour une section :

- label clair ;
- largeur max explicite ;
- paddings desktop/mobile ;
- structure lisible dans Bricks ;
- global classes pour patterns répétés ;
- images avec alt ;
- aucun container vide sauf décor contrôlé.

## 4. Choisir le bon outil

| Besoin | Outil |
|---|---|
| chercher léger | `find_elements` |
| lire un élément | `get_element` |
| modifier settings | `update_element` |
| ajouter une section | `batch_add` |
| réordonner sections | `reorder_sections` |
| déplacer/reparent | `get_page_json` + `update_page_json` |
| CSS global/page | `set_custom_code`, `set_page_custom_code` |
| valider structure JSON | `analyze_json_structure` |
| vérifier un bloc | `verify_element` |
| audit fullpage | `audit_page` |
| audit design | `audit_design_page` |

## 5. Vérifier

Après chaque section :

```js
verify_element({
  pageId,
  elementId,
  viewports: ["desktop", "mobile_portrait"]
})
```

Corriger immédiatement :

- overflow horizontal ;
- gap/padding ignoré ;
- layout flex vertical au lieu d'horizontal ;
- images cassées ou sans alt ;
- tailles incohérentes entre cartes ;
- CTA cassé sans raison ;
- container visible vide ;
- typo ou font non chargée.

## 6. Auditer

Avant validation :

```js
audit_page({pageId, viewports: ["desktop", "mobile_portrait"]})
audit_design_page({pageId, viewports: ["desktop", "mobile_portrait"], brief})
```

L'audit design doit juger l'ensemble, pas seulement les bugs :

- hiérarchie visuelle ;
- rythme vertical ;
- équilibre image/texte ;
- cohérence des cards ;
- densité de contenu ;
- CTA et navigation ;
- mobile réellement harmonieux.

## 7. Quand un format manque

1. Appeler `get_element_schema({element: "nom-bricks"})`.
2. Si le schema reste ambigu, lire la doc Bricks officielle de l'élément ou du contrôle.
3. Chercher un élément natif existant avec `find_elements`.
4. Lire avec `get_element` et copier le format exact des settings.
5. Si aucun exemple n'existe : créer une page/section lab.
6. Valider avec `verify_element`, pas avec le JSON seul.

Ne pas écraser de métadonnées inconnues sur composants, slots, query loops ou templates. Si un élément contient `cid`, `instanceId`, `slotChildren`, `parentComponent` ou une clé similaire, travailler en patch minimal.

## 8. Livraison

Avant de dire que c'est fini :

- page vérifiée desktop + mobile ;
- aucun critical dans `audit_page` ;
- findings design traités ou assumés ;
- CSS custom restant justifié ;
- structure Bricks lisible pour un humain ;
- couleurs/typos/paddings principaux éditables dans Bricks.
