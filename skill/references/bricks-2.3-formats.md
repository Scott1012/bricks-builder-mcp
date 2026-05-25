# Bricks 2.3.x JSON Formats

But : donner les formats sûrs à utiliser dans les settings Bricks. Un format est fiable seulement s'il produit le CSS attendu en frontend.

## Valeurs

- Dimensions : strings sans unité, ex. `"24"` pour `24px`.
- `auto` reste string, ex. `"auto"`.
- Responsive : suffixe sur la clé, ex. `_padding:mobile_portrait`.
- Couleurs : préférer `{ "raw": "#111827" }`, `{ "raw": "rgba(...)" }` ou `var(...)`.
- Les objets `{size, unit}` ne sont pas universels : utiliser seulement sur les contrôles où Bricks le génère déjà (`_gap` par exemple).

## Layout

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

`_gridTemplateColumns` est le format fiable Bricks 2.3.2 pour les grilles. `_columns` ne doit pas être utilisé pour `grid-template-columns`.

## Sizing

```json
{
  "_width": "100%",
  "_widthMin": "320",
  "_widthMax": "1280",
  "_height": "480",
  "_heightMin": "480",
  "_heightMax": "720",
  "_margin": {"top": "0", "right": "auto", "bottom": "0", "left": "auto"}
}
```

À éviter : `_maxWidth`, `_minHeight`.

## Spacing

```json
{
  "_padding": {"top": "80", "right": "32", "bottom": "80", "left": "32"},
  "_padding:mobile_portrait": {"top": "48", "right": "20", "bottom": "48", "left": "20"},
  "_margin": {"top": "0", "right": "0", "bottom": "24", "left": "0"}
}
```

## Typography

```json
{
  "_typography": {
    "font-family": "Inter",
    "font-size": "40",
    "font-weight": "700",
    "line-height": "1.1",
    "letter-spacing": "-0.02em",
    "text-align": "center",
    "font-style": "normal",
    "text-transform": "uppercase",
    "color": {"raw": "#111827"}
  },
  "_typography:mobile_portrait": {
    "font-size": "32",
    "line-height": "1.05"
  }
}
```

À éviter sans test : `font-size` ou `line-height` en objet `{size, unit}`.

## Background

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
    "color": {"raw": "rgba(0,0,0,.45)"}
  }
}
```

À éviter : `_background_color`, `_backgroundColor`. Pour un gradient simple, utiliser CSS custom local sauf si un objet `_gradient` Bricks natif existe déjà.

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

À éviter : `_borderRadius`.

## Position / overflow

```json
{
  "_position": "relative",
  "_overflow": "hidden",
  "_zIndex": "2",
  "_opacity": "1"
}
```

Offsets natifs : `_top`, `_right`, `_bottom`, `_left`.

## Effects

```json
{
  "_boxShadow": {
    "values": {"offsetX": "0", "offsetY": "10", "blur": "24", "spread": "0"},
    "color": {"raw": "rgba(0,0,0,.22)"},
    "inset": false
  },
  "_transform": {
    "translateY": "-6px",
    "rotateZ": "2deg",
    "scaleX": "1.03",
    "scaleY": "1.03"
  },
  "_transformOrigin": "center",
  "_cssFilters": {
    "brightness": "120",
    "contrast": "105",
    "blur": "2"
  }
}
```

À éviter : `_boxShadow` plat `{horizontal, vertical, blur, color}`, `_transform.rotate`, `_transform.scale`, valeurs `%` dans `_cssFilters`.

## Images et liens

```json
{
  "image": {"id": 123, "url": "https://example.com/image.webp", "alt": "Description"}
}
```

Préférer un media ID quand possible. Une URL seule peut casser en migration.

```json
{
  "link": {"type": "external", "url": "https://example.com"}
}
```

## Global class

```json
{
  "_cssGlobalClasses": ["abc123"]
}
```

## À retenir

| Besoin CSS | Setting Bricks |
|---|---|
| `display` | `_display` |
| `flex-direction` | `_direction` |
| `align-items` | `_alignItems` |
| `justify-content` | `_justifyContent` |
| `gap` | `_gap` + `_columnGap` + `_rowGap` |
| `grid-template-columns` | `_gridTemplateColumns` |
| `max-width` | `_widthMax` |
| `min-height` | `_heightMin` |
| `height` | `_height` |
| `opacity` | `_opacity` |
| `aspect-ratio` | `_aspectRatio` |
| `padding` | `_padding` |
| `margin` | `_margin` |
| `font-*`, `line-height`, couleur texte | `_typography` |
| `background-color` | `_background.color` |
| `border`, `border-radius` | `_border` |
| `box-shadow` | `_boxShadow.values` + `_boxShadow.color` |
| `filter` | `_cssFilters` |
| `transform` | `_transform` |
| classes globales Bricks | `_cssGlobalClasses` |
| image | `image.id`, `image.url`, `image.alt` |
| lien | `link.type`, `link.url` |

En cas de doute : `find_elements` + `get_element` sur un élément similaire, ou page lab + `verify_element`.
