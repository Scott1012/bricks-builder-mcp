# 🧩 BRICKS PATTERNS - RÉFÉRENCE TECHNIQUE

> **Objectif** : Documentation ultra-compacte des patterns Bricks pour création rapide et fiable d'éléments.
>
> **Stratégie** : Essayer logique CSS standard → Vérifier → Cloner si échec

**Date de création** : 14/12/2025

---

## TABLE DES MATIÈRES

1. [Règles d'Or](#1-règles-dor)
2. [Patterns Universels](#2-patterns-universels)
3. [Catalogue Modules](#3-catalogue-modules)
4. [Cheat Codes](#4-cheat-codes)

---

## 1. RÈGLES D'OR

### 🎯 RÈGLE #1 : Essayer Logique CSS Standard d'abord

Bricks suit la logique CSS/HTML dans **95% des cas**.

**Propriétés sûres** (pas besoin de cloner) :
- ✅ Layout flexbox/grid : `_display`, `_direction`, `_justifyContent`, `_alignItems`
- ✅ Dimensions : `_width`, `_height`, `_widthMin`, `_heightMin`, `_widthMax`, `_heightMax`
- ✅ Position : `_position`, `_top`, `_left`, `_right`, `_bottom`, `_zIndex`
- ✅ Spacing structure : `_margin`, `_padding` (format objet standard)
- ✅ Contenus : `text`, `tag`, `link.url`, `link.type`

**Action** : Crée élément normalement avec logique CSS, teste avec `getComputedStyle()`

---

### ⚠️ RÈGLE #2 : Cloner SI Échec Seulement

**Propriétés ambiguës** (cloner si échec) :
- `_typography` (STRING vs OBJET selon installation)
- `_gap` (peut nécessiter `_columnGap` + `_rowGap`)
- `_background` avec gradient/image (structure complexe)
- `_border` sur boutons (format différent)

**Workflow** :
```javascript
// 1. ESSAYER logique standard
update_element({
  elementId: "test_id",
  newSettings: {
    "_typography": {
      "font-size": "24"  // Logique CSS standard
    }
  }
})

// 2. VÉRIFIER avec getComputedStyle()
const before = getComputedStyle(element).fontSize
// Appliquer modification
const after = getComputedStyle(element).fontSize

if (before !== after) {
  // ✅ Propriété appliquée, logique standard OK
} else {
  // ❌ Propriété ignorée → PASSER À ÉTAPE 3
}

// 3. SI ÉCHEC → Cloner format qui marche
find_elements({ criteria: { type: "heading" }})
get_element({ elementId: "working_heading" })
// Copier format exact + adapter valeurs
```

---

### 💾 RÈGLE #3 : Utiliser BRICKS-TEST-STRUCTURES.json comme Référence

**BRICKS-TEST-STRUCTURES.json** contient tous modules avec propriétés activées.

**Cas d'usage** :
- Besoin format `social-icons` ? → Cherche `"name": "social-icons"`
- Besoin dropdown complexe ? → Cherche `"name": "dropdown"`
- Copie structure trouvée + adapte valeurs

**Avantage** : Formats validés sur l'installation, pas théoriques.

---

### 🔍 RÈGLE #4 : getComputedStyle() = Seule Source de Vérité

**❌ Méthodes NON fiables** :
- Rechercher CSS dans `<style>` → peut être overridé
- Inspecter JSON Bricks seul → peut être ignoré au rendu

**✅ Méthode fiable** :
```javascript
const before = getComputedStyle(element).fontSize
// Appliquer modification
const after = getComputedStyle(element).fontSize

if (before === after) {
  console.log("❌ Propriété ignorée")
} else {
  console.log("✅ Propriété appliquée:", after)
}
```

**Raison** : `getComputedStyle()` reflète le rendu **final** après cascade CSS complète.

---

### 🧬 RÈGLE #5 : Modifier Hiérarchie = 3 Éléments

**DÉCOUVERTE CRITIQUE** : `parent` et `children` sont au **niveau racine**, PAS dans `settings`.

**Conséquence** :
- ✅ `update_element` modifie **uniquement** `settings`
- ❌ `update_element` **NE PEUT PAS** modifier `parent` ou `children`
- ✅ Pour hiérarchie → `update_page_json`

**Workflow déplacer élément** :
```javascript
const json = get_page_json({ pageId: 640 })

for (let element of json) {
  // 1. Retirer de ancien parent
  if (element.id === 'old_parent') {
    element.children = element.children.filter(id => id !== 'my_element')
  }
  // 2. Ajouter au nouveau parent
  else if (element.id === 'new_parent') {
    if (!element.children.includes('my_element')) {
      element.children.push('my_element')
    }
  }
  // 3. Changer parent de l'élément
  else if (element.id === 'my_element') {
    element.parent = 'new_parent'
  }
}

update_page_json({ pageId: 640, newJsonData: json })
```

---

## 2. PATTERNS UNIVERSELS

### 📐 PATTERN: STRUCTURE_ELEMENT

**Structure universelle** (tous éléments) :
```json
{
  "id": "xxxxxx",           // 6 caractères alphanumériques
  "name": "div",            // Type d'élément
  "parent": 0,              // 0 = top-level, sinon ID parent
  "children": [],           // Array d'IDs enfants
  "settings": {}            // Propriétés spécifiques
}
```

**Règles** :
- `parent: 0` → élément racine
- Ordre tableau = ordre rendu
- ID unique (6 chars)

---

### 🎨 PATTERN: COLOR

**3 formats validés** :

**Format 1 : Variable CSS** (préféré)
```json
{
  "raw": "var(--auron-neutral-950)",
  "id": "qdnxmm",
  "name": "Neutral 950"
}
```

**Format 2 : HEX simple**
```json
{
  "hex": "#ffffff"
}
```

**Format 3 : RGB/HSL complet**
```json
{
  "rgb": "rgb(255, 255, 255)"
}
```

**Usage** :
```json
{
  "_typography": {
    "color": {COLOR_OBJECT}
  },
  "_background": {
    "color": {COLOR_OBJECT}
  }
}
```

---

### ✍️ PATTERN: TYPOGRAPHY

**Structure complète** :
```json
"_typography": {
  "color": {COLOR_OBJECT},
  "font-size": "STRING",              // ⚠️ STRING, pas objet
  "font-weight": "STRING",
  "font-family": "STRING",
  "font-style": "normal|italic|oblique",
  "font-variation-settings": "STRING",
  "text-align": "left|center|right|justify",
  "text-transform": "none|uppercase|lowercase|capitalize",
  "text-decoration": "none|underline|line-through|overline",
  "text-wrap": "wrap|nowrap|pretty|balance",
  "white-space": "normal|nowrap|pre|pre-wrap|break-spaces",
  "line-height": "STRING" | {"size": "STRING", "unit": ""},
  "letter-spacing": "STRING",
  "text-shadow": {
    "values": {
      "offsetX": "STRING",
      "offsetY": "STRING",
      "blur": "STRING"
    },
    "color": {COLOR_OBJECT}
  }
}
```

**⚠️ Installation-Specific** :
- `font-size` peut être STRING ou OBJET
- Toujours cloner depuis élément existant si incertain

---

### 🖼️ PATTERN: BORDER

**Structure standard** :
```json
"_border": {
  "width": {
    "top": "STRING",
    "right": "STRING",
    "bottom": "STRING",
    "left": "STRING"
  },
  "style": "none|solid|dashed|dotted|hidden|double|groove|ridge|inset|outset",
  "color": {COLOR_OBJECT},
  "radius": {
    "top": "STRING",
    "right": "STRING",
    "bottom": "STRING",
    "left": "STRING"
  }
}
```

**⚠️ Exception boutons** :
Sur type `button`, utiliser `_border.radius` (PAS `_borderRadius`)

---

### 📏 PATTERN: SPACING

**Margin et Padding** (structure identique) :
```json
"_margin": {
  "top": "STRING",
  "right": "STRING",
  "bottom": "STRING",
  "left": "STRING"
}
```

**Valeurs spéciales** :
- `"auto"` → centrage
- `"0"` → aucune marge
- `"24"` → 24px (unité ajoutée auto)

---

### 🌈 PATTERN: BACKGROUND

**Structure complète** :
```json
"_background": {
  "color": {COLOR_OBJECT},
  "image": {
    "url": "STRING",
    "external": BOOLEAN,
    "filename": "STRING"
  },
  "blendMode": "normal|multiply|screen|overlay|...",
  "attachment": "scroll|fixed",
  "position": "STRING",      // Ex: "top right", "center left"
  "repeat": "repeat|repeat-x|repeat-y|no-repeat",
  "size": "auto|cover|contain",
  "videoUrl": "STRING",
  "videoAspectRatio": "STRING",
  "videoScale": "STRING",
  "videoStartTime": "STRING",
  "videoEndTime": "STRING",
  "videoPlayOnce": BOOLEAN,
  "videoShowAtBreakpoint": "desktop|tablet|mobile",
  "videoPoster": {IMAGE_OBJECT},
  "videoPosterYouTube": BOOLEAN
}
```

---

### 📦 PATTERN: FLEXBOX

**Layout flex complet** :
```json
{
  "_display": "flex",
  "_direction": "row|column|row-reverse|column-reverse",
  "_flexWrap": "nowrap|wrap|wrap-reverse",
  "_justifyContent": "flex-start|center|flex-end|space-between|space-around|space-evenly",
  "_alignItems": "flex-start|center|flex-end|baseline|stretch",
  "_alignSelf": "auto|flex-start|center|flex-end|baseline|stretch",
  "_gap": {"size": "STRING", "unit": "px"},
  "_columnGap": "STRING",
  "_rowGap": "STRING",
  "_flexGrow": "STRING",
  "_flexShrink": "STRING",
  "_flexBasis": "STRING",
  "_order": "STRING"
}
```

**⚠️ Installation-Specific** :
- `_direction: "row"` OBLIGATOIRE pour horizontal (défaut = column)
- `_gap` + `_columnGap` + `_rowGap` TOUJOURS ensemble
- `_gap` et `_justifyContent` sont INDÉPENDANTS

---

### 🎲 PATTERN: GRID

**Layout grid complet** :
```json
{
  "_display": "grid",
  "_gridGap": "STRING",
  "_gridTemplateRows": "STRING",
  "_gridTemplateColumns": "STRING",
  "_gridAutoColumns": "auto|min-content|max-content",
  "_gridAutoRows": "auto|min-content|max-content",
  "_gridAutoFlow": "row|column|dense",
  "_justifyItemsGrid": "flex-start|center|flex-end|stretch",
  "_alignItemsGrid": "flex-start|center|flex-end|stretch",
  "_justifyContentGrid": "flex-start|center|flex-end|space-between|space-around|space-evenly",
  "_alignContentGrid": "flex-start|center|flex-end|space-between|space-around|space-evenly"
}
```

---

### 🔄 PATTERN: TRANSFORM

**Transformations CSS** :
```json
"_transform": {
  "translateX": "STRING",
  "translateY": "STRING",
  "scaleX": "STRING",
  "scaleY": "STRING",
  "rotateX": "STRING",
  "rotateY": "STRING",
  "rotateZ": "STRING",
  "skewX": "STRING",
  "skewY": "STRING"
}
```

**Transform Origin** :
```json
"_transformOrigin": "center|top|left|right|bottom|top left|..."
```

---

### 🌑 PATTERN: BOXSHADOW

**Ombre portée** :
```json
"_boxShadow": {
  "values": {
    "offsetX": "STRING",
    "offsetY": "STRING",
    "blur": "STRING",
    "spread": "STRING"
  },
  "color": {COLOR_OBJECT},
  "inset": BOOLEAN
}
```

---

### 🔗 PATTERN: LINK

**Lien universel** (button, text-link, heading, etc.) :
```json
"link": {
  "type": "external|internal|media|taxonomy|meta",
  "url": "STRING",           // si type=external
  "postId": "STRING",        // si type=internal
  "newTab": BOOLEAN,
  "rel": "STRING",
  "ariaLabel": "STRING",
  "title": "STRING"
}
```

---

### 🎭 PATTERN: ICON

**Icône Font Awesome** :
```json
"icon": {
  "library": "fontawesomeSolid|fontawesomeRegular|fontawesomeBrands|themify|ionicons",
  "icon": "STRING"          // Classe CSS (ex: "fas fa-home")
}
```

**Propriétés styling** :
```json
{
  "iconSize": "STRING",
  "iconWidth": "STRING",
  "iconHeight": "STRING",
  "iconColor": {COLOR_OBJECT},
  "iconBackground": {COLOR_OBJECT},
  "iconBorder": {BORDER_OBJECT},
  "iconPosition": "left|right|top|bottom"
}
```

---

### 🔁 PATTERN: QUERY_BUILDER

**WordPress loops** :
```json
{
  "hasLoop": true,
  "query": {
    "objectType": "post|page|product|...",
    "useQueryEditor": BOOLEAN,
    "infinite_scroll": BOOLEAN,
    "infinite_scroll_margin": "STRING",
    "infinite_scroll_delay": "STRING",
    "ajax_loader_animation": "ellipsis|spinner|...",
    "ajax_loader_scale": "STRING",
    "ajax_loader_color": {COLOR_OBJECT},
    "ajax_loader_selector": "STRING",
    "no_results_text": "STRING"
  }
}
```

---

### 📱 PATTERN: RESPONSIVE_IMAGE

**Images sources multiples** (breakpoints) :
```json
"sources": [
  {
    "id": "xxxxxx",
    "breakpoint": "mobile_portrait|mobile_landscape|tablet|desktop",
    "image": {IMAGE_OBJECT}
  }
]
```

---

### 🌅 PATTERN: GRADIENT

**Dégradés CSS** :
```json
"_gradient": {
  "applyTo": "overlay|background",
  "cssSelector": "STRING",
  "gradientType": "linear|radial",
  "radialShape": "circle|ellipse",
  "radialSize": "closest-side|farthest-side|closest-corner|farthest-corner",
  "radialPosition": "STRING",
  "repeat": BOOLEAN,
  "colors": [
    {
      "id": "xxxxxx",
      "color": {COLOR_OBJECT},
      "stop": "STRING"        // Pourcentage
    }
  ]
}
```

---

### 🔺 PATTERN: SHAPE_DIVIDERS

**Séparateurs SVG** :
```json
"_shapeDividers": [
  {
    "id": "xxxxxx",
    "shape": "vertical-stroke|wave|curve|...",
    "height": "STRING",
    "width": "STRING",
    "rotate": "STRING",
    "horizontalAlign": "flex-start|center|flex-end",
    "verticalAlign": "top|center|bottom",
    "top": "STRING",
    "right": "STRING",
    "bottom": "STRING",
    "left": "STRING",
    "front": BOOLEAN,
    "fill": {COLOR_OBJECT},
    "flipHorizontal": BOOLEAN,
    "flipVertical": BOOLEAN,
    "overflow": BOOLEAN
  }
]
```

---

## 3. CATALOGUE MODULES

### 📦 Structure

**container**
- **Props spécifiques** : `-`
- **Patterns** : FLEXBOX, GRID, SPACING, BACKGROUND, BORDER
- **Comportement** : `margin: 0 auto` automatique (centrage)
- **Usage** : Conteneur de page centré

**div**
- **Props spécifiques** : `tag`, `classConverterComponent`
- **Patterns** : FLEXBOX, GRID, SPACING, BACKGROUND, BORDER
- **Comportement** : Pas de margin auto (manuel requis)
- **Usage** : Groupement d'éléments

**section**
- **Props spécifiques** : `-`
- **Patterns** : FLEXBOX, GRID, SPACING, BACKGROUND, BORDER
- **Usage** : Section HTML5 sémantique

**block**
- **Props spécifiques** : `tag`, `hasLoop`, `query`
- **Patterns** : FLEXBOX, GRID, SPACING, QUERY_BUILDER
- **Usage** : Bloc générique (navigations, loops)

---

### ✍️ Contenu

**heading**
- **Props spécifiques** : `text`, `tag` (h1-h6), `type`, `style`, `link`
- **Patterns** : LINK, TYPOGRAPHY, SPACING
- **Cloner depuis** : `find_elements({ criteria: { type: "heading" }})`

**text-basic**
- **Props spécifiques** : `text`, `tag`, `wordsLimit`, `readMore`, `link`
- **Patterns** : LINK, TYPOGRAPHY, SPACING
- **Usage** : Texte simple (paragraphes, spans)

**text**
- **Props spécifiques** : `text` (HTML), `type`, `style`
- **Patterns** : TYPOGRAPHY, SPACING
- **Usage** : Texte enrichi formaté

**text-link**
- **Props spécifiques** : `text`, `link`, `icon`, `iconSize`, `iconPosition`
- **Patterns** : LINK, ICON, TYPOGRAPHY, SPACING
- **Usage** : Lien textuel (navigation, CTA)

---

### 🎯 Interaction

**button**
- **Props spécifiques** : `text`, `style`, `size`, `circle`, `outline`, `link`, `icon`, `iconPosition`, `iconGap`
- **Patterns** : LINK, ICON, TYPOGRAPHY, BORDER, BACKGROUND, SPACING
- **Piège** : `_border.radius` (PAS `_borderRadius`)
- **Cloner depuis** : `find_elements({ criteria: { type: "button" }})`

**icon**
- **Props spécifiques** : `icon`, `iconColor`, `iconSize`, `link`
- **Patterns** : ICON, LINK
- **Usage** : Icône standalone

**social-icons**
- **Props spécifiques** : `icons[]` (array), `direction`, `alignIcons`, `justifyIcons`, `gap`, `gapItem`
- **Patterns** : ICON, TYPOGRAPHY, FLEXBOX
- **⚠️ Vérification** : TOUJOURS utiliser `waitForMS: 2000` lors du screenshot (Font Awesome charge après le DOM)
- **Structure** :
```json
{
  "icons": [
    {
      "icon": {
        "library": "fontawesomeBrands",
        "icon": "fab fa-facebook"
      },
      "link": {
        "url": "https://facebook.com/page",
        "type": "external"
      },
      "text": "Facebook"
    }
  ]
}
```
- **Cloner depuis** : BRICKS-TEST-STRUCTURES.json → `"name": "social-icons"`

---

### 🖼️ Média

**image**
- **Props spécifiques** : `image`, `sources[]`, `tag`, `caption`, `loading`, `stretch`, `link`, `popupIcon`
- **Patterns** : LINK, RESPONSIVE_IMAGE, SPACING
- **Propriétés responsive** :
```json
{
  "_aspectRatio": "STRING",
  "_objectFit": "cover|contain|fill|...",
  "_objectPosition": "STRING"
}
```

**video**
- **Props spécifiques** : `videoType`, `youTubeId`, `vimeoId`, `fileUrl`, `previewImage`, `iframeTitle`
- **Patterns** : BACKGROUND (video), SPACING
- **Types** : `youtube|vimeo|file`

---

### 🧭 Navigation

**nav-nested**
- **Props spécifiques** : `tag`, `ariaLabel`, `gap`, `itemPadding`, `multiLevel`, `multiLevelBackText`
- **Patterns** : TYPOGRAPHY, BORDER, BACKGROUND, SPACING, TRANSFORM
- **Structure enfants** : `block` (ul) → `text-link` + `dropdown` + `toggle`
- **Cloner depuis** : BRICKS-TEST-STRUCTURES.json → `"name": "nav-nested"`

**dropdown**
- **Props spécifiques** : `text`, `caretSize`, `caretColor`, `toggleOn`, `contentWidth`, `megaMenu`
- **Patterns** : TYPOGRAPHY, BORDER, BACKGROUND, TRANSFORM
- **Parent** : nav-nested
- **Enfants** : div.brx-dropdown-content → text-link[]

**toggle**
- **Props spécifiques** : `animation`, `ariaLabel`, `toggleSelector`, `toggleAttribute`, `barScale`, `barColor`, `icon`
- **Patterns** : ICON
- **Usage** : Menu burger, accordéon trigger

---

### 📐 Mise en page

**divider**
- **Props spécifiques** : `height`, `width`, `style`, `direction`, `justifyContent`, `color`, `icon`, `link`
- **Patterns** : ICON, LINK
- **Usage** : Séparateur horizontal/vertical

**icon-box**
- **Props spécifiques** : `icon`, `content` (HTML)
- **Patterns** : ICON, TYPOGRAPHY, SPACING
- **Usage** : Icône + texte groupés

**list**
- **Props spécifiques** : `items[]`, `icon`, `iconPosition`, `titleTag`, `descriptionTypography`
- **Patterns** : ICON, TYPOGRAPHY, BORDER, BACKGROUND
- **Structure items** :
```json
{
  "items": [
    {
      "title": "STRING",
      "meta": "STRING",
      "description": "STRING",
      "link": {LINK_OBJECT},
      "highlight": BOOLEAN,
      "highlightLabel": "STRING"
    }
  ]
}
```

---

### 🎨 Avancé

**accordion**
- **Props spécifiques** : `accordions[]`, `titleTag`, `icon`, `iconExpanded`, `independentToggle`, `faqSchema`
- **Patterns** : TYPOGRAPHY, BORDER, BACKGROUND
- **Structure** :
```json
{
  "accordions": [
    {
      "title": "STRING",
      "subtitle": "STRING",
      "content": "HTML",
      "anchorId": "STRING"
    }
  ]
}
```

**tabs**
- **Props spécifiques** : `tabs[]`, `tabsLayout`, `tabsJustifyContent`, `activeTab`
- **Patterns** : TYPOGRAPHY, BORDER, BACKGROUND
- **Cloner depuis** : BRICKS-TEST-STRUCTURES.json → `"name": "tabs"`

**slider**
- **Props spécifiques** : `autoplay`, `pauseOnHover`, `loop`, `speed`, `navigation`, `pagination`
- **Patterns** : FLEXBOX, SPACING
- **Cloner depuis** : BRICKS-TEST-STRUCTURES.json → `"name": "slider"`

**offcanvas**
- **Props spécifiques** : `direction`, `effect`, `closeOn`, `width`, `height`, `ariaLabel`, `noScrollBody`
- **Patterns** : TYPOGRAPHY, BORDER, BACKGROUND, SPACING
- **Structure enfants** : `block.brx-offcanvas-inner` + `toggle` + `block.brx-offcanvas-backdrop`

---

### 🔄 Dynamique

**posts** (Query Loop)
- **Props spécifiques** : `hasLoop`, `query`
- **Patterns** : QUERY_BUILDER
- **Usage** : Afficher posts WordPress dynamiquement

**form**
- **Props spécifiques** : `fields[]`, `formAction`, `submitText`, `emailTo`
- **Patterns** : TYPOGRAPHY, BORDER, BACKGROUND, SPACING
- **Cloner depuis** : BRICKS-TEST-STRUCTURES.json → `"name": "form"`

---

### 🎪 Effets

**animated-typing**
- **Props spécifiques** : `strings[]`, `typeSpeed`, `backSpeed`, `loop`
- **Patterns** : TYPOGRAPHY
- **Cloner depuis** : BRICKS-TEST-STRUCTURES.json → `"name": "animated-typing"`

**countdown**
- **Props spécifiques** : `endDate`, `endTime`, `showDays`, `showHours`, `showMinutes`, `showSeconds`
- **Patterns** : TYPOGRAPHY, SPACING
- **Cloner depuis** : BRICKS-TEST-STRUCTURES.json → `"name": "countdown"`

---

## 4. CHEAT CODES

### 🔍 Comment choisir le bon type d'élément ?

```
Layout centré horizontal → container (margin auto automatique)
Layout sans centrage auto → div (margin manuel requis)
Icônes sociales → social-icons (PAS icon + liens manuels)
Navigation avec burger → nav-nested (menu burger intégré)
Titre sémantique → heading (avec tag h1-h6)
Texte enrichi → text (HTML supporté)
Liste d'items → list (structure items[])
Accordéon FAQ → accordion (faqSchema intégré)
Slider/carrousel → slider (autoplay, loop)
Popup latéral → offcanvas (direction, effect)
```

---

### 🎨 Comment appliquer une couleur ?

**3 formats possibles** :

```json
// 1. Variable CSS (préféré - thème cohérent)
{"raw": "var(--color-name)", "id": "xxx", "name": "Nom"}

// 2. HEX simple (rapide - couleur fixe)
{"hex": "#ffffff"}

// 3. RGB/HSL (complexe - dynamique)
{"rgb": "rgb(255,255,255)"}
```

**Règle** : Si incertain du format → Cloner depuis élément existant

---

### 📐 Comment centrer un élément ?

**Horizontal** :
```
Type container → Automatique (margin: 0 auto)
Type div → Manuel (_margin: {left: "auto", right: "auto"})
```

**Vertical** :
```
Parent flex → _alignItems: "center"
Parent grid → _alignItemsGrid: "center"
```

**Dans conteneur** :
```
Flex → _justifyContent: "center" + _alignItems: "center"
Grid → _justifyContentGrid: "center" + _alignItemsGrid: "center"
```

---

### 🔗 Comment créer hiérarchie parent/enfant ?

**TOUJOURS utiliser `update_page_json`** (PAS `update_element`)

**Workflow** :
```javascript
// 1. Récupérer JSON complet
const json = get_page_json({ pageId: 640 })

// 2. Modifier 3 éléments
for (let element of json) {
  if (element.id === 'old_parent') {
    element.children = element.children.filter(id => id !== 'element_to_move')
  }
  else if (element.id === 'new_parent') {
    element.children.push('element_to_move')
  }
  else if (element.id === 'element_to_move') {
    element.parent = 'new_parent'
  }
}

// 3. Soumettre
update_page_json({ pageId: 640, newJsonData: json })
```

---

### ⚠️ Comment éviter bugs fréquents ?

```
✅ Gap → TOUJOURS 3 propriétés (_gap + _columnGap + _rowGap)
✅ Flex horizontal → TOUJOURS _direction: "row" (défaut = column)
✅ Font-size → Cloner format (STRING vs OBJET selon installation)
✅ Width max → _widthMax (PAS _maxWidth)
✅ Border-radius boutons → _border.radius (PAS _borderRadius)
✅ Vérifier → getComputedStyle() avant/après (PAS juste JSON)
✅ Gap vs JustifyContent → Indépendants (space-between ignore gap)
✅ Container vs Div → Margin auto différent
```

---

### 📱 Quels breakpoints responsive ?

| Device | Width | Bricks key |
|--------|-------|------------|
| Desktop | > 1024px | (défaut) |
| Tablet | ≤ 991px | `:tablet` |
| Mobile landscape | ≤ 767px | `:mobile_landscape` |
| Mobile portrait | ≤ 478px | `:mobile_portrait` |

**Usage** :
```json
{
  "_typography": {
    "font-size": "56"
  },
  "_typography:mobile_portrait": {
    "font-size": "36"
  }
}
```

---

### 🛠️ Quel outil MCP utiliser ?

| Besoin | Outil | Raison |
|--------|-------|--------|
| Modifier CSS/contenu | `update_element` | Léger (~50 tokens) |
| Modifier parent/children | `update_page_json` | Seule option (~2500 tokens) |
| Réorganiser sections | `reorder_sections` | Optimisé pour ça |
| Créer 1 élément | `add_element` | Simple |
| Créer 5-15 éléments | `batch_add` | Atomique |
| Trouver éléments | `find_elements` | Recherche par critères |
| Voir JSON élément | `get_element` | Détails complets |
| Voir JSON page | `get_page_json` | Tous éléments |

---

### 🧪 Workflow création élément type

```
BESOIN : Créer section hero avec titre + CTA

1. CLONER formats si besoin
   find_elements({ criteria: { type: "heading" }})
   find_elements({ criteria: { type: "button" }})
   get_element() pour formats exacts

2. CRÉER structure
   section (parent: 0)
   └─ container (parent: section_id)
      ├─ heading (copier format cloné)
      └─ button (copier format cloné)

3. VÉRIFIER
   getComputedStyle() sur heading + button
   Screenshot avant/après
   Tester contexte (siblings, parent)

4. CORRIGER si besoin
   Rollback si problème
   Re-appliquer avec fix
```

---

### 📚 Sources de référence

**Ordre de consultation** :
1. **BRICKS-PATTERNS.md** (ce fichier) → Patterns + règles
2. **BRICKS-TEST-STRUCTURES.json** → Formats réels validés
3. **BRICKS-BUILDER-GUIDE_UPDATED.md** → Règles observées détaillées
4. **BRICKS-WORKFLOW.md** → Workflows + principes
5. **OUTILS.md** → Syntaxe MCP tools

---

*Documentation créée le 14/12/2025*
*Basée sur reverse-engineering 640+ éléments production*
