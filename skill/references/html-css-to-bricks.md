# HTML/CSS To Bricks Mapping

But : convertir une page HTML/CSS en Bricks sans recréer un site figé en CSS custom.

## Principe

Convertir l'intention, pas le DOM exact. Une page Bricks propre doit rester éditable : sections lisibles, composants réutilisables, styles dans les settings ou global classes.

## Structure

| HTML source | Bricks |
|---|---|
| `body`, page wrapper | page Bricks + theme styles |
| `header` | section/header template ou section top |
| `main` | sections top-level |
| `section` | `section` |
| `article`, `footer` | `section` |
| `.container`, `.wrapper` | `container` / `block` / `div` |
| `.grid`, `.cards` | wrapper avec `_display: "grid"` |
| `.row`, `.flex` | wrapper avec `_display: "flex"` |
| `nav` | `nav-menu` si menu WP, sinon `div`/liens |
| `h1`-`h6` | `heading` |
| `p`, petit texte | `text-basic` |
| `span`, inline text | `text-basic` ou conserver dans parent texte |
| contenu HTML riche | `text` |
| `a.btn`, CTA | `button` |
| lien texte | `text-link` |
| `img`, `picture` | `image` |
| `video` | `video` ou background vidéo natif |
| `ul/li` simple | `list` |
| liste d'icônes | `social-icons` ou `list` selon contenu |
| `blockquote` | `text-basic` avec tag/style adapté |
| `hr` | `divider` |
| `iframe`, embed, script | `code` seulement si nécessaire et validé |
| formulaire | `form` |
| FAQ | `accordion` |
| tabs | `tabs` |
| slider/carousel | `slider` / `carousel` |

Bricks stocke ensuite les éléments en tableau plat : chaque élément a `id`, `name`, `parent`, `children`, `settings`. Ne pas livrer une structure imbriquée si l'outil attend le JSON Bricks brut.

## Layout CSS

| CSS | Bricks setting |
|---|---|
| `display:block` | `_display: "block"` |
| `display:flex` | `_display: "flex"` |
| `flex-direction` | `_direction` |
| `align-items` | `_alignItems` |
| `justify-content` | `_justifyContent` |
| `gap` | `_gap` + `_columnGap` + `_rowGap` |
| `display:grid` | `_display: "grid"` |
| `grid-template-columns` | `_gridTemplateColumns` |
| `grid-template-rows` | `_gridTemplateRows` |
| `grid-auto-flow` | `_gridAutoFlow` |
| `flex-wrap` | `_flexWrap` |
| `max-width` | `_widthMax` |
| `width` | `_width` |
| `min-height` | `_heightMin` |
| `height` | `_height` |
| `max-height` | `_heightMax` |
| `aspect-ratio` | `_aspectRatio` |
| `position` | `_position` |
| offsets `top/right/bottom/left` | `_top`, `_right`, `_bottom`, `_left` |
| `overflow` | `_overflow` |
| `z-index` | `_zIndex` |

## Spacing

| CSS | Bricks setting |
|---|---|
| `padding` | `_padding` |
| `margin` | `_margin` |
| responsive padding | `_padding:mobile_portrait`, etc. |
| centered container | `_margin.right/left: "auto"` |

## Typography

| CSS | Bricks setting |
|---|---|
| `font-family` | `_typography.font-family` ou theme style |
| `font-size` | `_typography.font-size` |
| `font-weight` | `_typography.font-weight` |
| `line-height` | `_typography.line-height` |
| `letter-spacing` | `_typography.letter-spacing` |
| `text-align` | `_typography.text-align` |
| `font-style` | `_typography.font-style` |
| `text-transform` | `_typography.text-transform` |
| `color` | `_typography.color` |
| headings globaux | theme styles / global classes |

## Couleurs et backgrounds

| CSS | Bricks |
|---|---|
| couleur répétée | palette / CSS variable |
| `background-color` | `_background.color` |
| overlay simple | `_background.color.raw: "rgba(...)"` |
| image de fond | `_background.image` + `size/position/repeat/attachment` |
| gradient | `_gradient` si objet Bricks natif existant, sinon CSS custom local |
| vidéo de fond | background vidéo natif si disponible |

## Borders, radius, shadows

| CSS | Bricks |
|---|---|
| `border` | `_border.width/style/color` |
| `border-radius` | `_border.radius` |
| `box-shadow` | `_boxShadow.values` + `_boxShadow.color` |
| `opacity` | `_opacity` |
| `filter` | `_cssFilters` |
| `transform` | `_transform` |
| `transform-origin` | `_transformOrigin` |
| hover shadow/transform | suffixe `:hover` si simple, sinon CSS custom |

## Classes et variables

Le comportement à viser est celui du convertisseur natif Bricks 2.3 : classes CSS vers global classes, `:root` variables vers variables globales, propriétés simples vers contrôles natifs, reste en custom CSS.

Stratégie agent :

- Si une classe source existe déjà en global class : appliquer son ID via `_cssGlobalClasses`.
- Si une classe source correspond à un framework actif (ACSS, Tailwind traduit, Bootstrap traduit) : réutiliser la classe existante.
- Si une classe source revient plusieurs fois : créer une global class Bricks.
- Si une classe source est unique et simple : convertir en settings natifs locaux.
- Si une classe contient pseudo/animation/gradient complexe : garder en CSS custom court.

## Responsive

Utiliser les suffixes Bricks :

```json
{
  "_direction": "row",
  "_direction:mobile_portrait": "column",
  "_padding": {"top": "96", "right": "32", "bottom": "96", "left": "32"},
  "_padding:mobile_portrait": {"top": "56", "right": "20", "bottom": "56", "left": "20"}
}
```

Éviter les media queries custom pour un style que Bricks peut gérer par suffixe.

## Composants réutilisables

Quand un motif revient :

- bouton primaire/secondaire ;
- card ;
- section sombre/claire ;
- container max-width ;
- kicker/eyebrow ;
- grille de cards ;
- item témoignage ;
- item FAQ.

Créer ou réutiliser une global class, puis appliquer `_cssGlobalClasses`.

## Ce qui reste en CSS custom

Accepter du CSS custom pour :

- pseudo-classes complexes (`:hover`, `:focus-visible`, `:before`, `:after`) ;
- animations/keyframes ;
- gradients sans objet Bricks natif disponible ;
- effets avancés (`clip-path`, masks, blend modes) ;
- intégrations tierces ;
- correctif temporaire documenté.

Refuser le CSS custom pour :

- padding/margin simples ;
- typo simple ;
- couleur simple ;
- max-width ;
- flex/grid de base ;
- border-radius ;
- responsive simple.

JS, iframes et ressources externes doivent être traités comme risqués : ne pas les convertir en `code` element sans validation explicite, car Bricks peut exiger activation/signature.

## Quand basculer vers le code

Basculer vers un module code seulement si au moins un de ces points est vrai :

- Bricks ne rend pas le composant de façon fiable en frontend ;
- le JSON natif devient trop fragile ou trop ambigu ;
- la logique dépend d'interactions JS ou d'un rendu conditionnel avancé ;
- reproduire le module en natif impose beaucoup de wrappers, overrides ou hacks.

Ne pas basculer en code pour :

- réorganiser des sections ;
- éditer des textes ;
- ajuster des couleurs, espacements, rayons, typo ;
- corriger un layout simple.

Quand un module part en code, garder si possible autour :

- la section Bricks ;
- les titres/textes éditables ;
- les espacements et couleurs principaux.

## Checklist de conversion

- Chaque section source a une section Bricks claire.
- Les wrappers importants ont un rôle évident.
- Les styles répétés sont en tokens/classes, pas dupliqués partout.
- Les settings principaux sont éditables dans Bricks.
- Le CSS custom restant est court et justifié.
- Desktop et mobile sont vérifiés en frontend.
