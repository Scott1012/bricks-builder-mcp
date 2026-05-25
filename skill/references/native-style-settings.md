# Bricks Native Style Settings

But : traduire le panneau Style Bricks en settings JSON natifs, avant d'utiliser du CSS custom. Cette page couvre les contrôles CSS hérités Bricks 2.3.x, c'est-à-dire les clés `_...` disponibles sur les éléments.

## Valeurs

| Type | Format |
|---|---|
| px simple | string sans unité : `"24"` |
| unité non-px | string avec unité : `"1.2em"`, `"80%"`, `"100vh"` |
| auto | `"auto"` |
| couleur | `{"raw":"#111827"}`, `{"raw":"rgba(...)"}`, `{"raw":"var(--token)"}` |
| palette Bricks | `{"raw":"var(--bricks-color-id)","id":"id"}` si l'id existe |
| responsive | suffixe sur la clé : `_padding:mobile_portrait` |
| hover/state | suffixe : `_background:hover`, `_typography:mobile_portrait:hover` |

Ne pas envoyer de nombres JSON pour les dimensions. Utiliser des strings.

## Layout

| CSS | Setting Bricks | Notes |
|---|---|---|
| `display` | `_display` | `block`, `flex`, `grid`, `none`, etc. |
| `flex-direction` sur layout elements | `_direction` | vérifié pour `section`, `container`, `block`, `div` |
| `flex-direction` hérité commun | `_flexDirection` | existe dans le schema commun, mais ne remplace pas `_direction` sur les layout elements |
| `flex-wrap` | `_flexWrap` | `nowrap`, `wrap`, `wrap-reverse` |
| `align-items` | `_alignItems` | axe croisé flex |
| `justify-content` | `_justifyContent` | axe principal flex |
| `align-self` | `_alignSelf` | item flex/grid |
| `flex-grow` | `_flexGrow` | string |
| `flex-shrink` | `_flexShrink` | string |
| `flex-basis` | `_flexBasis` | string, ex. `"320"` ou `"33.33%"` |
| `order` | `_order` | string ou nombre |
| `gap` | `_gap` + `_columnGap` + `_rowGap` | `_gap` seul peut être ignoré en frontend |
| `column-gap` | `_columnGap` | string sans unité pour px |
| `row-gap` | `_rowGap` | string sans unité pour px |

Flex fiable :

```json
{
  "_display": "flex",
  "_direction": "row",
  "_alignItems": "center",
  "_justifyContent": "space-between",
  "_gap": {"size": "24", "unit": "px"},
  "_columnGap": "24",
  "_rowGap": "24"
}
```

## Grid

| CSS | Setting Bricks | Notes |
|---|---|---|
| `display:grid` | `_display: "grid"` | obligatoire |
| `grid-template-columns` | `_gridTemplateColumns` | format vérifié |
| `grid-template-rows` | `_gridTemplateRows` | string CSS |
| `grid-auto-columns` | `_gridAutoColumns` | string CSS |
| `grid-auto-rows` | `_gridAutoRows` | string CSS |
| `grid-auto-flow` | `_gridAutoFlow` | `row`, `column`, `dense`, etc. |
| `grid-gap` | `_gridGap` | applique `gap` |
| `justify-items` | `_justifyItemsGrid` | items grid |
| `align-items` grid | `_alignItemsGrid` | items grid |
| `justify-content` grid | `_justifyContentGrid` | grille dans son conteneur |
| `align-content` grid | `_alignContentGrid` | grille dans son conteneur |
| `grid-column` | `_gridItemColumnSpan` | item grid |
| `grid-row` | `_gridItemRowSpan` | item grid |
| `justify-self` | `_gridItemJustifySelf` | item grid |

Grid fiable :

```json
{
  "_display": "grid",
  "_gridTemplateColumns": "repeat(3, 1fr)",
  "_gridTemplateColumns:tablet_portrait": "repeat(2, 1fr)",
  "_gridTemplateColumns:mobile_portrait": "1fr",
  "_gridGap": "24",
  "_columnGap": "24",
  "_rowGap": "24"
}
```

Ne pas utiliser `_columns` pour `grid-template-columns` : sur Bricks 2.3.2 il ne produit pas la grille attendue.

## Spacing

```json
{
  "_margin": {"top": "0", "right": "auto", "bottom": "24", "left": "auto"},
  "_padding": {"top": "80", "right": "32", "bottom": "80", "left": "32"},
  "_padding:mobile_portrait": {"top": "48", "right": "20", "bottom": "48", "left": "20"}
}
```

| CSS | Setting |
|---|---|
| `margin` | `_margin` |
| `padding` | `_padding` |

Les propriétés directionnelles possibles sont `top`, `right`, `bottom`, `left`. Il n'est pas obligatoire de remplir les quatre côtés.

## Sizing

| CSS | Setting Bricks |
|---|---|
| `width` | `_width` |
| `min-width` | `_widthMin` |
| `max-width` | `_widthMax` |
| `height` | `_height` |
| `min-height` | `_heightMin` |
| `max-height` | `_heightMax` |
| `aspect-ratio` | `_aspectRatio` |

```json
{
  "_width": "100%",
  "_widthMax": "1280",
  "_heightMin": "520",
  "_aspectRatio": "16/9"
}
```

Ne pas utiliser `_maxWidth`, `_minHeight`, `_borderRadius`.

## Typography

```json
{
  "_typography": {
    "font-family": "Inter",
    "font-size": "18",
    "font-weight": "600",
    "font-style": "normal",
    "line-height": "1.4",
    "letter-spacing": "-0.01em",
    "text-align": "center",
    "text-transform": "uppercase",
    "text-decoration": "none",
    "color": {"raw": "#111827"}
  }
}
```

| CSS | Propriété dans `_typography` |
|---|---|
| `font-family` | `font-family` |
| `font-size` | `font-size` |
| `font-weight` | `font-weight` |
| `font-style` | `font-style` |
| `line-height` | `line-height` |
| `letter-spacing` | `letter-spacing` |
| `text-align` | `text-align` |
| `text-transform` | `text-transform` |
| `text-decoration` | `text-decoration` |
| `color` | `color` |

`font-size` et `line-height` doivent rester des strings. Les objets `{size, unit}` peuvent produire un mauvais rendu.

## Background

| CSS / besoin | Setting |
|---|---|
| couleur de fond | `_background.color` |
| image de fond | `_background.image` |
| vidéo de fond | `_background.video` |
| `background-size` | `_background.size` |
| `background-position` | `_background.position` |
| `background-repeat` | `_background.repeat` |
| `background-attachment` | `_background.attachment` |
| gradient Bricks | `_gradient` |

```json
{
  "_background": {
    "color": {"raw": "#13161A"}
  }
}
```

```json
{
  "_background": {
    "image": {"id": 123, "url": "https://example.com/bg.webp", "size": "full"},
    "size": "cover",
    "position": "center center",
    "repeat": "no-repeat",
    "attachment": "scroll"
  }
}
```

Pour un gradient simple en conversion, préférer CSS custom local si aucun exemple Bricks natif existant n'est disponible. La clé `_gradient` est native, mais son objet est plus riche que `linear-gradient(...)`.

## Border

```json
{
  "_border": {
    "width": {"top": "1", "right": "1", "bottom": "1", "left": "1"},
    "style": "solid",
    "color": {"raw": "rgba(255,255,255,.12)"},
    "radius": {"top": "16", "right": "16", "bottom": "16", "left": "16"}
  }
}
```

| CSS | Setting |
|---|---|
| `border-width` | `_border.width` |
| `border-style` | `_border.style` |
| `border-color` | `_border.color` |
| `border-radius` | `_border.radius` |

Styles valides : `none`, `solid`, `dashed`, `dotted`, `double`, `groove`, `ridge`, `inset`, `outset`.

## Shadow

```json
{
  "_boxShadow": {
    "values": {"offsetX": "0", "offsetY": "10", "blur": "24", "spread": "0"},
    "color": {"raw": "rgba(0,0,0,.22)"},
    "inset": false
  }
}
```

| CSS | Setting |
|---|---|
| `box-shadow` | `_boxShadow` |
| `text-shadow` sur contrôles dédiés | clé d'élément de type `text-shadow` |

Le format plat legacy `{horizontal, vertical, blur, color}` ne produit pas d'ombre fiable.

## Filters

```json
{
  "_cssFilters": {
    "brightness": "120",
    "contrast": "105",
    "blur": "2",
    "grayscale": "0",
    "hue-rotate": "0",
    "invert": "0",
    "opacity": "100",
    "saturate": "100",
    "sepia": "0"
  }
}
```

Bricks convertit les valeurs unitless : `"120"` devient `brightness(1.2)`, `"2"` sur `blur` devient `2px`.

## Transform

```json
{
  "_transform": {
    "translateX": "0",
    "translateY": "-6px",
    "translateZ": "0",
    "scaleX": "1.03",
    "scaleY": "1.03",
    "scaleZ": "1",
    "rotateX": "0deg",
    "rotateY": "0deg",
    "rotateZ": "2deg",
    "skewX": "0deg",
    "skewY": "0deg",
    "perspective": "0"
  },
  "_transformOrigin": "center"
}
```

Utiliser `rotateZ`, pas `rotate`. Utiliser `scaleX/scaleY`, pas `scale`.

## Position Et Visuel

| CSS | Setting |
|---|---|
| `position` | `_position` |
| `top` | `_top` |
| `right` | `_right` |
| `bottom` | `_bottom` |
| `left` | `_left` |
| `z-index` | `_zIndex` |
| `visibility` | `_visibility` |
| `overflow` | `_overflow` |
| `opacity` | `_opacity` |
| `cursor` | `_cursor` |
| `isolation` | `_isolation` |
| `mix-blend-mode` | `_mixBlendMode` |
| `pointer-events` | `_pointerEvents` |
| `perspective` | `_perspective` |
| `perspective-origin` | `_perspectiveOrigin` |
| `content` | `_content` |
| `transition` | `_cssTransition` |

## Motion, Shape, Scroll

| Besoin | Setting |
|---|---|
| masonry enable | `_useMasonry` |
| masonry columns | `_masonryColumn` |
| masonry gutter | `_masonryGutter` |
| masonry order | `_masonryHorizontalOrder` |
| masonry transition duration | `_masonryTransitionDuration` |
| masonry reveal | `_masonryTransitionMode` |
| shape divider | `_shapeDividers` |
| element parallax | `_motionElementParallax` |
| parallax speed X/Y | `_motionElementParallaxSpeedX`, `_motionElementParallaxSpeedY` |
| background parallax | `_motionBackgroundParallax` |
| background parallax speed | `_motionBackgroundParallaxSpeed` |
| parallax start | `_motionStartVisiblePercent` |
| scroll snap type | `_scrollSnapType` |
| scroll snap align | `_scrollSnapAlign` |
| scroll snap stop | `_scrollSnapStop` |

## Classes, Attributs, Custom

| Besoin | Setting |
|---|---|
| classe CSS texte | `_cssClasses` |
| classe globale Bricks | `_cssGlobalClasses` |
| ID CSS/HTML | `_cssId` |
| CSS custom de l'élément | `_cssCustom` |
| attributs `data-*`, `aria-*` | `_attributes` |
| conditions | `_conditions` |
| interactions | `_interactions` |
| cacher builder | `_hideElementBuilder` |
| cacher frontend | `_hideElementFrontend` |

`_cssClasses` reçoit un nom de classe texte. `_cssGlobalClasses` reçoit un tableau d'IDs de classes globales Bricks.

## Breakpoints

Suffixes fréquents :

```text
:tablet_landscape
:tablet_portrait
:mobile_landscape
:mobile_portrait
```

Exemple :

```json
{
  "_gridTemplateColumns": "repeat(3, 1fr)",
  "_gridTemplateColumns:tablet_portrait": "repeat(2, 1fr)",
  "_gridTemplateColumns:mobile_portrait": "1fr",
  "_direction": "row",
  "_direction:mobile_portrait": "column"
}
```

## Quand Utiliser CSS Custom

Utiliser CSS custom seulement pour :

- pseudo-éléments `::before` / `::after` ;
- sélecteurs avancés non exposés par Bricks ;
- keyframes et animations complexes ;
- gradients sans objet Bricks natif connu ;
- masks, clip-path, blend modes avancés ;
- intégrations tierces.

Ne pas utiliser CSS custom pour spacing, typo, couleur, max-width, flex/grid de base, border radius, shadow simple, transform simple, filters simples ou responsive simple.
