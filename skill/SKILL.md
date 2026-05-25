---
name: bricks-builder
version: 1.6.0
description: Convertir, créer et maintenir des pages WordPress Bricks Builder avec une approche native-first. Utiliser ce skill pour transformer HTML/CSS en structure Bricks éditable, choisir les bons éléments Bricks, appliquer les settings natifs, limiter le CSS custom, gérer responsive/design system, et vérifier le rendu frontend.
---

# Bricks Builder

Objectif : transformer une intention HTML/CSS en page Bricks propre, éditable dans l'UI, maintenable, avec le maximum de settings natifs et le minimum de CSS custom.

## Priorité

1. Traduire la structure en éléments Bricks natifs.
2. Traduire le CSS en settings Bricks natifs.
3. Utiliser le design system Bricks existant : palette, variables, global classes, theme styles, fonts.
4. Ajouter du CSS custom seulement si Bricks ne sait pas faire ou si le format natif échoue en frontend.
5. Vérifier visuellement et techniquement après chaque bloc significatif.

## Workflow minimal

```js
health_check()
list_bricks_pages()
get_global_styles()
get_element_schema()
list_color_palette()
list_global_classes()
list_theme_styles()
list_css_variables()
list_custom_fonts()
```

Pour une page existante :

```js
get_page_structure({pageId})
find_elements({pageId, criteria: {type: "section"}, limit: 30})
```

Puis travailler par section :

```text
analyser la section source
-> choisir éléments Bricks natifs
-> batch_add ou update_element
-> verify_element desktop + mobile
-> corriger
-> section suivante
```

## Références à charger

| Besoin | Lire |
|---|---|
| Convertir HTML/CSS vers Bricks | [html-css-to-bricks.md](references/html-css-to-bricks.md) |
| Choisir un élément/bloc Bricks natif | [native-elements.md](references/native-elements.md) |
| Trouver un paramètre/style Bricks natif | [native-style-settings.md](references/native-style-settings.md) |
| Choisir où mettre les styles et assets | [bricks-native-api.md](references/bricks-native-api.md) |
| Formats JSON fiables Bricks 2.3.x | [bricks-2.3-formats.md](references/bricks-2.3-formats.md) |
| Workflow complet de refonte/conversion | [workflow.md](references/workflow.md) |
| Outils MCP disponibles et ordre d'usage | [mcp-tools.md](references/mcp-tools.md) |
| Vérification technique + audit design | [verification.md](references/verification.md) |
| Query/dynamic data/templates/composants | [native-dynamic.md](references/native-dynamic.md) |
| Contenu WordPress dynamique / CPT | [native-content.md](references/native-content.md) |
| WooCommerce | [native-woocommerce.md](references/native-woocommerce.md) |
| SEO, schema markup, performance | [seo.md](references/seo.md) |
| Remonter un manque MCP | [feedback-system.md](references/feedback-system.md) |

## Règles rapides

- Structure : `section` top-level, puis `container`/`block`/`div`, puis contenus.
- Layout horizontal : définir explicitement `_display: "flex"` et `_direction: "row"`.
- Gap fiable : utiliser `_gap` + `_columnGap` + `_rowGap`.
- Grid fiable : utiliser `_display: "grid"` + `_gridTemplateColumns`, pas `_columns`.
- Largeur max : utiliser `_widthMax`, pas `_maxWidth`.
- Border radius : utiliser `_border.radius`, pas `_borderRadius`.
- Shadow/transform/filter simples : utiliser `_boxShadow`, `_transform`, `_cssFilters`.
- Typo : utiliser `_typography` avec valeurs string.
- Couleur : utiliser `_background.color.raw` ou palette/variable, pas de clés custom inventées.
- Responsive : utiliser les suffixes Bricks, ex. `_padding:mobile_portrait`.
- Déplacement d'élément : `parent` et `children` sont au niveau racine, donc `update_page_json`.
- Élément complexe : appeler `get_element_schema({element: "..."})` ou lire un élément natif existant, ne pas inventer le format.

## Vérification obligatoire

Après chaque section ou batch :

```js
verify_element({
  pageId,
  elementId,
  viewports: ["desktop", "mobile_portrait"]
})
```

Avant livraison :

```js
audit_page({pageId, viewports: ["desktop", "mobile_portrait"]})
audit_design_page({pageId, viewports: ["desktop", "mobile_portrait"], brief: "objectif et positionnement"})
```

Valider en frontend : le JSON stocké ne suffit jamais.
