# 🧱 GUIDE COMPLET BRICKS BUILDER POUR IA

> Guide exhaustif pour manipuler Bricks Builder via API MCP.
> Basé sur l'expérience réelle de conversion d'un site HTML complet vers Bricks.

---

## 📋 TABLE DES MATIÈRES

1. [Architecture JSON Bricks](#1-architecture-json-bricks)
2. [Structure d'un Élément](#2-structure-dun-élément)
3. [Types d'Éléments Disponibles](#3-types-déléments-disponibles)
4. [Système de Propriétés CSS](#4-système-de-propriétés-css)
5. [Breakpoints Responsive](#5-breakpoints-responsive)
6. [Hiérarchie Parent/Enfant](#6-hiérarchie-parentenfant)
7. [Outils MCP Disponibles](#7-outils-mcp-disponibles)
8. [Patterns Courants](#8-patterns-courants)
9. [Pièges à Éviter](#9-pièges-à-éviter)
10. [Exemples Pratiques](#10-exemples-pratiques)

---

## 1. ARCHITECTURE JSON BRICKS

---

## ⚡ RÈGLES ISSUES DE L'OBSERVATION RÉELLE (Installation-Specific)

> **PRIORITÉ ABSOLUE** : En cas de conflit entre cette section et le reste du guide, **TOUJOURS** appliquer les règles ci-dessous. Elles ont été validées par observation directe sur l'installation en production.

### 🎨 Typographie

**Format validé :**
```json
"_typography": {
  "font-size": "44"  // ✅ STRING (pas objet)
}
```

**❌ Format à éviter (ne fonctionne pas sur cette installation) :**
```json
"_typography": {
  "font-size": { "size": "44", "unit": "px" }  // ❌ OBJET
}
```

**Raison** : Bricks interprète `font-size` comme une chaîne et ajoute automatiquement "px" comme unité par défaut.

**Date de validation** : 2024-12-14
**Source** : Observation conversion site complet (640+ éléments testés)

---

### 📐 Layout - Gaps

**Format validé (TOUJOURS utiliser les 3 propriétés ensemble) :**
```json
{
  "_gap": { "size": "80", "unit": "px" },
  "_columnGap": "80",
  "_rowGap": "80"
}
```

**❌ Format incomplet (peut ne pas s'appliquer) :**
```json
{
  "_gap": { "size": "80", "unit": "px" }  // ❌ Seul, peut être ignoré
}
```

**Raison** : Sur cette installation Bricks, `_gap` seul n'est pas toujours pris en compte. Les propriétés `_columnGap` et `_rowGap` assurent l'application correcte.

**Date de validation** : 2024-12-14
**Source** : Debugging grids récalcitrantes (section features, section services)

---

### 🔄 Layout - Gap vs JustifyContent

**DÉCOUVERTE CRITIQUE** : `_gap` et `_justifyContent` sont INDÉPENDANTS.

**Format validé :**
```json
{
  "_display": "flex",
  "_gap": {"size": "32", "unit": "px"},
  "_columnGap": "32",
  "_rowGap": "32",
  "_justifyContent": "center"  // ou "flex-start"
}
```

**❌ Piège fréquent (gap réel ≠ gap setting) :**
```json
{
  "_gap": {"size": "32", "unit": "px"},
  "_columnGap": "32",
  "_rowGap": "32",
  "_justifyContent": "space-between"  // ← Ceci DISTRIBUE l'espace
}

// Résultat :
// - Gap setting : 32px ✅
// - Gap RÉEL entre éléments : 466px ❌
```

**Raison** :
- `_gap` définit l'espacement minimum ENTRE éléments adjacents
- `justifyContent` définit la DISTRIBUTION des éléments dans le container
- `space-between` place les éléments aux extrémités, ignorant `_gap` pour l'espace central

**Comment vérifier** :
```javascript
// NE PAS se fier uniquement à :
getComputedStyle(container).gap  // Peut être "32px"

// TOUJOURS mesurer le gap réel :
const el1 = document.querySelector('#brxe-element1');
const el2 = document.querySelector('#brxe-element2');
const gapRéel = el2.getBoundingClientRect().left - el1.getBoundingClientRect().right;
console.log('Gap réel:', gapRéel + 'px');
```

**Solutions** :
- Pour gap régulier → `"_justifyContent": "flex-start"` ou `"center"`
- Pour distribuer uniformément → `"space-evenly"` (respecte gap mieux que space-between)
- Toujours vérifier le gap RÉEL, pas juste le setting

**Date de validation** : 2024-12-14
**Source** : Bug header Services (gap 32px setting, 466px réel)

---

### 📏 Dimensions - Largeur Maximale

**Format validé :**
```json
"_widthMax": "1200"  // ✅ STRING sans unité
```

**❌ Format à éviter (ne fonctionne pas sur cette installation) :**
```json
"_maxWidth": "1200px"  // ❌ Propriété inexistante, génère RIEN
```

**Raison** : Bricks utilise `_widthMax` (pas `_maxWidth`). La valeur est une chaîne sans unité, "px" est ajouté automatiquement dans le CSS généré.

**Date de validation** : 2024-12-14
**Source** : Bug détecté création page Services (conteneurs non contraints en largeur)

---

### 🔄 Layout - Direction Flex

**Format validé (TOUJOURS spécifier explicitement) :**
```json
{
  "_display": "flex",
  "_direction": "row"  // ✅ OBLIGATOIRE pour layout horizontal
}
```

**❌ Comportement par défaut (non documenté) :**
```json
{
  "_display": "flex"
  // ❌ Bricks applique flex-direction: column par défaut !
}
```

**Raison** : Sur cette installation, Bricks applique `flex-direction: column` par défaut. Si tu veux un layout horizontal (navigation, header, boutons côte à côte), tu DOIS spécifier `_direction: "row"` explicitement.

**Date de validation** : 2024-12-14
**Source** : Header empilé verticalement au lieu d'horizontal (page Services)

---

### 🎨 Border-Radius - Cas Spécial Boutons

**Pour éléments normaux (div, section, container) :**
```json
"_borderRadius": {
  "top-left": "8px",
  "top-right": "8px",
  "bottom-right": "8px",
  "bottom-left": "8px"
}
```

**Pour les BOUTONS (type "button") - Format différent :**
```json
"_border": {
  "radius": {
    "top": "8",
    "right": "8",
    "bottom": "8",
    "left": "8"
  }
}
```

**Raison** : Les boutons Bricks utilisent une structure `_border.radius` (pas `_borderRadius` directement). Valeurs sans unité ("px" ajouté automatiquement).

**Date de validation** : 2024-12-14
**Source** : Boutons sans border-radius (page Services)

---

### ⚠️ Structure JSON - Niveau Racine vs Settings

**DÉCOUVERTE CRITIQUE** : Les champs `parent` et `children` sont au **niveau racine** de l'élément JSON, PAS dans `settings`.

**Structure complète d'un élément :**
```json
{
  "id": "element_id",           // ← NIVEAU RACINE
  "name": "div",                // ← NIVEAU RACINE
  "parent": "parent_id",        // ← NIVEAU RACINE ⚠️
  "children": ["child1"],       // ← NIVEAU RACINE ⚠️
  "settings": {                 // ← Objet settings
    "_display": "flex",
    "_background": {...},
    // ... toutes les propriétés CSS/contenu
  }
}
```

**Conséquence :**
- ✅ `update_element` modifie **uniquement** `settings`
- ❌ `update_element` **NE PEUT PAS** modifier `parent` ou `children`
- ✅ Pour modifier `parent`/`children` → utiliser `update_page_json`

**Exemple INCORRECT (ne marche pas) :**
```javascript
update_element({
  elementId: "my_element",
  newSettings: {
    "parent": "new_parent"  // ❌ parent n'est PAS dans settings
  }
})
```

**Exemple CORRECT :**
```javascript
// 1. Récupérer JSON complet
const json = get_page_json({pageId: 640})

// 2. Modifier les 3 éléments concernés
for (let element of json) {
  if (element.id === 'old_parent') {
    element.children = element.children.filter(id => id !== 'my_element')
  }
  else if (element.id === 'new_parent') {
    if (!element.children.includes('my_element')) {
      element.children.push('my_element')
    }
  }
  else if (element.id === 'my_element') {
    element.parent = 'new_parent'
  }
}

// 3. Envoyer JSON complet
update_page_json({pageId: 640, newJsonData: json})
```

**RÈGLE ABSOLUE** : Quand tu déplaces un élément, tu DOIS modifier 3 éléments :
1. Ancien parent (retirer de `children`)
2. Nouveau parent (ajouter à `children`)
3. Élément lui-même (changer `parent`)

**Date de validation** : 2024-12-14
**Source** : Bug icônes sociales footer (hiérarchie cassée, 2h de debug)

---

### 🔗 Type Élément - social-icons

**NOUVEAU type d'élément Bricks** : `social-icons` pour afficher des icônes sociales (Facebook, Instagram, Twitter, etc.) avec liens.

**Format validé :**
```json
{
  "name": "social-icons",
  "parent": "container_id",
  "children": [],
  "settings": {
    "icons": [
      {
        "icon": {
          "library": "fontawesomeBrands",
          "icon": "fab fa-facebook"
        },
        "link": {
          "url": "https://facebook.com/page",
          "type": "external"
        }
      },
      {
        "icon": {
          "library": "fontawesomeBrands",
          "icon": "fab fa-instagram"
        },
        "link": {
          "url": "https://instagram.com/page",
          "type": "external"
        }
      }
    ],
    "_typography": {
      "font-size": "24",
      "color": {"hex": "#ffffff"}
    },
    "_cssCustom": ".brxe-social-icons { display: flex; gap: 24px; list-style: none; padding: 0; margin: 0; } .brxe-social-icons li { background: none !important; padding: 0; } .brxe-social-icons a { background: none !important; padding: 0; } .brxe-social-icons span { display: none; } .brxe-social-icons .icon { font-size: 24px; color: white; }"
  }
}
```

**Propriété clé** : `icons` (tableau d'objets)

Chaque objet icône contient :
- `icon.library` : `"fontawesomeBrands"` (réseaux sociaux), `"fontawesome"` (génériques), `"themify"`
- `icon.icon` : classe CSS (ex: `"fab fa-facebook"`)
- `link.url` : URL du lien
- `link.type` : `"external"` ou `"internal"`
- `label` : (optionnel) Texte affiché à côté de l'icône
- `background` : (optionnel) Couleur de fond du bouton

**Personnalisation CSS** :
Par défaut, Bricks ajoute backgrounds colorés et labels textuels. Pour avoir des icônes simples :

```css
.brxe-social-icons li { background: none !important; }
.brxe-social-icons a { background: none !important; padding: 0; }
.brxe-social-icons span { display: none; }  /* Cacher labels */
.brxe-social-icons .icon { font-size: 24px; color: white; }
```

**Avantages** :
- ✅ Font Awesome chargé automatiquement
- ✅ Structure HTML optimale générée
- ✅ Support natif des liens

**Date de validation** : 2024-12-14
**Source** : Création icônes sociales footer (après échec avec type `icon` standard)

---

### 🔍 Méthode de Vérification Fiable

**✅ Preuve qu'une propriété fonctionne :**
```javascript
// AVANT modification
const before = getComputedStyle(element).fontSize;

// Appliquer update_element

// APRÈS modification
const after = getComputedStyle(element).fontSize;

// Vérifier DELTA
if (before !== after) {
  // ✅ Propriété appliquée
}
```

**❌ Preuve non fiable :**
- Rechercher la règle CSS dans `<style>` → peut être présente mais overridée
- Inspecter le JSON Bricks seul → peut être dans le JSON mais ignoré au rendu

**Raison** : Seul `getComputedStyle` reflète le rendu **final** après cascade CSS complète.

**Date de validation** : 2024-12-14
**Source** : BRICKS-WORKFLOW.md (anciennement “BRICKS-METHODOLOGY-OPTIMIZED”), ligne 62-70

---

### 📌 Procédure "Cloner un Format Qui Marche"

**Avant de créer un élément avec une propriété incertaine** (ex: background avancé, grid complexe, typography), **clone un élément existant similaire** :

```javascript
// 1. Trouver un élément similaire
find_elements({
  pageId: 640,
  criteria: { type: "heading" }  // Même type que ce que tu veux créer
})

// 2. Récupérer ses settings complets
get_element({
  pageId: 640,
  elementId: "existing_heading_id"
})

// 3. COPIER le format exact des clés + types de valeurs
// 4. Adapter UNIQUEMENT les valeurs (texte, couleur, taille)
```

**Objectif** : Mimer les patterns réels de l'installation plutôt que se fier uniquement au guide théorique.

**Date de validation** : 2024-12-14
**Source** : BRICKS-WORKFLOW.md (anciennement “BRICKS-METHODOLOGY-OPTIMIZED”), ligne 52-61

---

### 📝 Changelog des Règles Observées

| Date | Règle Ajoutée | Contexte |
|------|---------------|----------|
| 2024-12-14 | `font-size` string vs objet | Conversion site complet, 640+ éléments |
| 2024-12-14 | `_gap` + `_columnGap` + `_rowGap` ensemble | Debug grids sections features/services |
| 2024-12-14 | Vérification via `getComputedStyle` | Méthodologie optimisée |
| 2024-12-14 | Procédure "Cloner format" | Principe fondamental méthodologie |
| 2024-12-14 | `_widthMax` (pas `_maxWidth`) | Bug page Services (conteneurs non contraints) |
| 2024-12-14 | `_direction: "row"` obligatoire pour flex horizontal | Bug page Services (header vertical) |
| 2024-12-14 | Border-radius boutons = `_border.radius` | Bug page Services (boutons sans radius) |
| 2024-12-14 | `parent`/`children` = niveau racine (pas `settings`) | Bug icônes footer (2h debug hiérarchie) |
| 2024-12-14 | Type `social-icons` pour icônes sociales | Création icônes footer (échec type `icon`) |
| 2024-12-14 | `_gap` vs `_justifyContent` indépendants | Bug header Services (gap 32px → 466px réel) |
| 2024-12-14 | `container` vs `div` comportements CSS | Bug footer alignment (98px décalage) |

**Instructions pour mise à jour** : Lorsqu'un nouveau comportement spécifique est observé, l'ajouter ici avec date + contexte.

---

### Structure Globale
Une page Bricks est un **tableau JSON** d'éléments. Chaque élément a un `id` unique et peut avoir des enfants.

```json
[
  {
    "id": "abc123",
    "name": "section",
    "parent": 0,
    "children": ["def456", "ghi789"],
    "settings": { ... }
  },
  {
    "id": "def456",
    "name": "container",
    "parent": "abc123",
    "children": ["jkl012"],
    "settings": { ... }
  }
]
```

### Règles Fondamentales
- `parent: 0` = élément racine (premier niveau de la page)
- L'**ordre dans le tableau** détermine l'ordre de rendu
- Les `children` sont des références aux `id` des enfants
- Chaque `id` doit être **unique** (généralement 6 caractères alphanumériques)

---

## 2. STRUCTURE D'UN ÉLÉMENT

### Squelette Complet
```json
{
  "id": "unique6",
  "name": "div",
  "parent": "parentId",
  "children": ["child1", "child2"],
  "settings": {
    // Contenu
    "text": "Mon texte",
    "tag": "div",
    
    // Identifiants CSS
    "_cssId": "monId",
    "_cssClasses": "classe1 classe2",
    "_cssCustom": ".classe1 { color: red; }",
    
    // Layout
    "_display": "flex",
    "_direction": "row",
    "_alignItems": "center",
    "_justifyContent": "space-between",
    "_gap": { "size": "24", "unit": "px" },
    
    // Dimensions
    "_width": "100%",
    "_maxWidth": "1200px",
    "_height": "auto",
    
    // Espacement
    "_margin": { "top": "0", "right": "auto", "bottom": "0", "left": "auto" },
    "_padding": { "top": "24", "right": "24", "bottom": "24", "left": "24" },
    
    // Typographie
    "_typography": {
      "font-size": "16",
      "font-weight": "600",
      "line-height": { "size": "1.5", "unit": "" }
    },
    
    // Fond et bordures
    "_background": { "color": { "hex": "#FFFFFF" } },
    "_border": { "width": "1px", "style": "solid", "color": { "hex": "#E2E8F0" } },
    "_borderRadius": { "top-left": "8px", "top-right": "8px", "bottom-right": "8px", "bottom-left": "8px" },
    
    // Attributs HTML
    "_attributes": [
      { "id": "attr1", "name": "aria-label", "value": "Description" }
    ],
    
    // Visibilité responsive
    "_hidden": {
      "desktop": false,
      "tablet_portrait": false,
      "mobile_portrait": true
    }
  },
  "label": ".maClasse"
}
```

---

## 3. TYPES D'ÉLÉMENTS DISPONIBLES

### Éléments de Structure
| Name | Description | Usage |
|------|-------------|-------|
| `section` | Section HTML5 | Conteneur principal de page |
| `container` | Conteneur avec max-width | Wrapper de contenu |
| `div` | Division générique | Groupement d'éléments |
| `block` | Bloc générique | Utilisé dans les navigations |

#### ⚠️ Différence Container vs Div - Comportements CSS

**DÉCOUVERTE IMPORTANTE** : Les types `container` et `div` ont des comportements CSS **automatiques** différents.

**Type `container` :**
```json
{
  "name": "container",
  "settings": {
    "_widthMax": "1400"
  }
}
```

**Comportement automatique** :
- Classe CSS : `brxe-container`
- Bricks ajoute automatiquement : `margin: 0 auto`
- **Centrage horizontal automatique** ✅
- Idéal pour : Conteneurs de page, sections centrées

**Type `div` :**
```json
{
  "name": "div",
  "settings": {
    "_widthMax": "1400"
  }
}
```

**Comportement automatique** :
- Classe CSS : `brxe-div`
- **Pas de margin auto** ❌
- Alignement par défaut : left
- **Centrage manuel requis** :
  ```json
  {
    "_widthMax": "1400",
    "_margin": {
      "left": "auto",
      "right": "auto"
    }
  }
  ```

**Exemple Problème Réel :**

**Symptôme** : Footer text aligné différemment du hero text

**Cause** :
```
hero_content  (type: container) → left: 218px (centré auto)
footer_content (type: div)      → left: 120px (pas centré)
```

**Solution** :
```json
{
  "elementId": "footer_content",
  "newSettings": {
    "_widthMax": "1400",
    "_margin": {"left": "auto", "right": "auto"}
  }
}
```

**Règle** : Utilise `container` pour éléments devant être centrés. Si tu utilises `div`, ajoute `margin: auto` manuellement.

**Date de validation** : 2024-12-14
**Source** : Bug alignment footer Services

---

### Éléments de Contenu
| Name | Description | Usage |
|------|-------------|-------|
| `heading` | Titres h1-h6 | `settings.tag: "h1"` |
| `text-basic` | Texte simple | Paragraphes, spans |
| `text-link` | Lien textuel | Navigation, CTA |
| `rich-text` | Éditeur riche | Contenu formaté |
| `image` | Image | `settings.image: { url: "..." }` |
| `icon` | Icône | SVG ou font icons |
| `video` | Vidéo | YouTube, Vimeo, fichier |

### Éléments de Formulaire
| Name | Description |
|------|-------------|
| `form` | Formulaire complet |
| `input` | Champ de saisie |
| `textarea` | Zone de texte |
| `select` | Liste déroulante |
| `checkbox` | Case à cocher |
| `submit` | Bouton de soumission |

### Éléments Spéciaux
| Name | Description |
|------|-------------|
| `code` | Code HTML/CSS/JS personnalisé |
| `nav-nested` | Navigation avec menu burger intégré |
| `dropdown` | Menu déroulant |
| `toggle` | Bouton toggle |
| `slider` | Carrousel |
| `tabs` | Onglets |
| `accordion` | Accordéon |
| `social-icons` | Icônes réseaux sociaux cliquables (voir détails ci-dessous) |

#### ⭐ Type `social-icons` - Documentation Complète

**Découverte** : 2024-12-14 (création icônes footer)

Le type `social-icons` est un élément spécialisé pour afficher des icônes de réseaux sociaux avec liens.

**Structure JSON complète** :
```json
{
  "id": "footer_social",
  "name": "social-icons",
  "parent": "footer_container",
  "children": [],
  "settings": {
    "icons": [
      {
        "icon": "fab fa-facebook-f",
        "link": "https://facebook.com/garage-saint-marcel",
        "text": "Facebook"
      },
      {
        "icon": "fab fa-instagram",
        "link": "https://instagram.com/garagesaintmarcel",
        "text": "Instagram"
      },
      {
        "icon": "fab fa-linkedin-in",
        "link": "https://linkedin.com/company/garage-saint-marcel",
        "text": "LinkedIn"
      }
    ],
    "_display": "flex",
    "_direction": "row",
    "_gap": { "size": "16", "unit": "px" },
    "_columnGap": "16",
    "_rowGap": "16",
    "_typography": {
      "font-size": "24",
      "color": { "hex": "#ffffff" }
    }
  }
}
```

**Propriétés spécifiques** :
- `settings.icons` : Array d'objets avec `icon`, `link`, `text`
- `icon` : Classes Font Awesome (ex: `"fab fa-facebook-f"`)
- `link` : URL complète du profil social
- `text` : Label (accessibilité)

**Styling** :
- `_typography` contrôle la taille et couleur des icônes
- Supporte flex/grid comme les autres éléments
- Hover states gérés via `_cssCustom`

**Exemple avec hover** :
```json
{
  "settings": {
    "icons": [...],
    "_typography": {
      "font-size": "24",
      "color": { "hex": "#ffffff" }
    },
    "_cssCustom": ".brxe-social-icons a { transition: all 0.3s ease; } .brxe-social-icons a:hover { color: #ff6b35; transform: scale(1.1); }"
  }
}
```

---

## 4. SYSTÈME DE PROPRIÉTÉS CSS

### Propriétés de Layout

#### Display
```json
"_display": "flex" | "grid" | "block" | "inline-flex" | "none"
```

#### Flexbox
```json
"_direction": "row" | "column" | "row-reverse" | "column-reverse",
"_alignItems": "flex-start" | "center" | "flex-end" | "stretch" | "baseline",
"_justifyContent": "flex-start" | "center" | "flex-end" | "space-between" | "space-around",
"_flexWrap": "wrap" | "nowrap",
"_gap": { "size": "24", "unit": "px" }
```

#### Grid
```json
"_display": "grid",
"_gridTemplateColumns": "1fr 1fr" | "repeat(3, 1fr)" | "repeat(auto-fit, minmax(300px, 1fr))",
"_gridTemplateRows": "auto",
"_gap": { "size": "32", "unit": "px" },
"_columnGap": "32",
"_rowGap": "32"
```

### ⚠️ IMPORTANT : Gap vs ColumnGap/RowGap

Bricks a un comportement particulier avec les gaps :

```json
// ❌ Peut ne pas fonctionner seul
"_gap": { "size": "80", "unit": "px" }

// ✅ Ajouter aussi les propriétés séparées pour assurer le fonctionnement
"_gap": { "size": "80", "unit": "px" },
"_columnGap": "80",
"_rowGap": "80"
```

### Propriétés d'Espacement

#### Margin
```json
// Format objet complet
"_margin": {
  "top": "0",
  "right": "auto",
  "bottom": "24px",
  "left": "auto"
}

// Format avec unités
"_margin": {
  "top": { "size": "24", "unit": "px" },
  "bottom": { "size": "48", "unit": "px" }
}
```

#### Padding
```json
"_padding": {
  "top": "24",
  "right": "24",
  "bottom": "24",
  "left": "24"
}

// Ou avec unités explicites
"_padding": {
  "left": { "size": "24", "unit": "px" },
  "right": { "size": "24", "unit": "px" }
}
```

### Propriétés de Typographie

**Note importante (format réel Bricks)** : sur cette installation, `font-size` doit être une **chaîne** (ex. `"44"`) et non un objet `{ size, unit }`. Bricks interprète la valeur en **px** par défaut.

```json
"_typography": {
  "font-family": "Poppins, sans-serif",
  "font-size": "48",
  "font-weight": "700",
  "line-height": { "size": "1.2", "unit": "" },
  "letter-spacing": { "size": "0.5", "unit": "px" },
  "text-transform": "uppercase",
  "text-align": "center",
  "color": { "hex": "#1A202C" }
}
```

### Propriétés de Fond

```json
"_background": {
  "color": { "hex": "#FFFFFF" }
}

// Avec gradient
"_background": {
  "gradient": {
    "type": "linear",
    "angle": 135,
    "stops": [
      { "color": "#0066CC", "position": 0 },
      { "color": "#00509E", "position": 100 }
    ]
  }
}

// Avec image
"_background": {
  "image": {
    "url": "https://example.com/image.jpg",
    "size": "cover",
    "position": "center center",
    "repeat": "no-repeat"
  }
}
```

### Propriétés de Bordure

```json
"_border": {
  "width": { "top": "1px", "right": "1px", "bottom": "1px", "left": "1px" },
  "style": "solid",
  "color": { "hex": "#E2E8F0" }
}

"_borderRadius": {
  "top-left": "8px",
  "top-right": "8px",
  "bottom-right": "8px",
  "bottom-left": "8px"
}
```

### Propriétés de Transformation

```json
"_transform": {
  "translateX": "0px",
  "translateY": "-8px",
  "rotate": "0deg",
  "scale": "1"
}

"_transition": "all 0.3s ease"
```

---

## 5. BREAKPOINTS RESPONSIVE

### Suffixes de Breakpoint

| Suffixe | Breakpoint | Largeur Max |
|---------|------------|-------------|
| *(aucun)* | Desktop | > 1024px |
| `:tablet_landscape` | Tablette paysage | ≤ 1024px |
| `:tablet_portrait` | Tablette portrait | ≤ 991px |
| `:mobile_landscape` | Mobile paysage | ≤ 768px |
| `:mobile_portrait` | Mobile portrait | ≤ 478px |

### Utilisation

```json
{
  "settings": {
    // Desktop (base)
    "_display": "grid",
    "_gridTemplateColumns": "1fr 1fr",
    "_gap": { "size": "80", "unit": "px" },
    "_padding": { "left": "24", "right": "24" },
    
    // Tablette portrait
    "_gridTemplateColumns:tablet_portrait": "1fr",
    "_gap:tablet_portrait": { "size": "48", "unit": "px" },
    
    // Mobile portrait
    "_gap:mobile_portrait": { "size": "32", "unit": "px" },
    "_padding:mobile_portrait": { "left": "16", "right": "16" }
  }
}
```

### ⚠️ RÈGLE IMPORTANTE : Héritage des Breakpoints

Les propriétés **cascadent vers le bas** :
- Desktop → Tablet Landscape → Tablet Portrait → Mobile Landscape → Mobile Portrait

Si tu définis une valeur pour `:tablet_portrait`, elle s'applique aussi à `:mobile_portrait` SAUF si tu définis explicitement une valeur pour `:mobile_portrait`.

### Visibilité par Breakpoint

```json
"_hidden": {
  "desktop": false,
  "tablet_landscape": false,
  "tablet_portrait": true,
  "mobile_landscape": true,
  "mobile_portrait": true
}

// Ou via display
"_display": "flex",
"_display:tablet_portrait": "none",
"_display:mobile_portrait": "none"
```

---

## 6. HIÉRARCHIE PARENT/ENFANT

### Règles de Liaison

1. **Le parent référence ses enfants** dans `children: ["id1", "id2"]`
2. **L'enfant référence son parent** dans `parent: "parentId"`
3. **Les deux doivent être cohérents** sinon l'élément n'apparaît pas

### Exemple de Structure Complète

```json
[
  {
    "id": "section1",
    "name": "section",
    "parent": 0,
    "children": ["container1"]
  },
  {
    "id": "container1",
    "name": "container",
    "parent": "section1",
    "children": ["heading1", "text1"]
  },
  {
    "id": "heading1",
    "name": "heading",
    "parent": "container1",
    "children": [],
    "settings": {
      "tag": "h1",
      "text": "Mon Titre"
    }
  },
  {
    "id": "text1",
    "name": "text-basic",
    "parent": "container1",
    "children": [],
    "settings": {
      "text": "Mon paragraphe",
      "tag": "p"
    }
  }
]
```

### Ordre d'Affichage

L'ordre dans le tableau JSON = ordre de rendu sur la page.

Pour réorganiser les sections, utiliser `reorder_sections` :
```javascript
reorder_sections(pageId, ["header_id", "hero_id", "content_id", "footer_id"])
```

---

## 7. OUTILS MCP DISPONIBLES

### Lecture

| Outil | Usage | Tokens |
|-------|-------|--------|
| `list_bricks_pages` | Liste toutes les pages Bricks | Faible |
| `get_page_structure` | Vue d'ensemble légère (id, name, parent, children) | Faible |
| `get_page_json` | JSON complet de la page | Élevé |
| `find_elements` | Recherche par critères (type, classe, texte) | Moyen |
| `get_element` | Détails complets d'un élément | Faible |
| `analyze_json_structure` | Analyse structure d'un JSON | Moyen |

### Écriture

| Outil | Usage |
|-------|-------|
| `update_element` | Modifier les settings d'un élément existant |
| `add_element` | Ajouter un nouvel élément |
| `batch_add` | Ajouter plusieurs éléments en une fois |
| `delete_element` | Supprimer un élément |
| `update_page_json` | Remplacer tout le JSON de la page |
| `reorder_sections` | Réorganiser l'ordre des sections racines |

### Stratégie d'Utilisation

```
1. EXPLORER : get_page_structure (comprendre la hiérarchie)
2. CHERCHER : find_elements (trouver les éléments spécifiques)
3. DÉTAILLER : get_element (obtenir les settings complets)
4. MODIFIER : update_element (changer une propriété)
5. VÉRIFIER : Playwright screenshot ou browser
```

---

## 8. PATTERNS COURANTS

### Header Sticky

```json
{
  "id": "header",
  "name": "div",
  "parent": 0,
  "settings": {
    "tag": "custom",
    "customTag": "header",
    "_cssClasses": "header",
    "_cssCustom": ".header { position: fixed; top: 0; left: 0; right: 0; z-index: 1000; background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); }",
    "_padding": { "top": "16", "bottom": "16" }
  }
}
```

### Container Centré

```json
{
  "id": "container",
  "name": "container",
  "settings": {
    "_cssClasses": "container",
    "_maxWidth": "1200px",
    "_margin": { "left": "auto", "right": "auto" },
    "_padding": { "left": "24", "right": "24" },
    "_padding:mobile_portrait": { "left": "16", "right": "16" }
  }
}
```

### Grid Responsive

```json
{
  "settings": {
    "_display": "grid",
    "_gridTemplateColumns": "repeat(3, 1fr)",
    "_gridTemplateColumns:tablet_portrait": "repeat(2, 1fr)",
    "_gridTemplateColumns:mobile_portrait": "1fr",
    "_gap": { "size": "32", "unit": "px" },
    "_columnGap": "32",
    "_rowGap": "32"
  }
}
```

### Bouton avec Hover

```json
{
  "id": "btn1",
  "name": "text-link",
  "settings": {
    "text": "Mon Bouton",
    "link": { "type": "external", "url": "#action" },
    "_cssClasses": "btn btn-primary",
    "_cssCustom": ".btn-primary { background: linear-gradient(135deg, #0066CC, #00509E); color: white; padding: 12px 24px; border-radius: 8px; transition: all 0.3s ease; } .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,0.15); }"
  }
}
```

### Menu Burger (nav-nested)

```json
{
  "id": "burger",
  "name": "nav-nested",
  "parent": "header",
  "children": ["navItems"],
  "settings": {
    "mobileMenu": true,
    "mobileMenuBreakpoint": "tablet_portrait",
    "_cssCustom": ".brx-nav-nested-items { flex-direction: column; gap: 16px; padding: 24px; background: white; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }"
  }
}
```

### Card avec Hover

```json
{
  "settings": {
    "_cssClasses": "card",
    "_cssCustom": ".card { background: white; border-radius: 16px; padding: 32px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: all 0.3s ease; } .card:hover { transform: translateY(-8px); box-shadow: 0 20px 25px rgba(0,0,0,0.15); }",
    "_display": "flex",
    "_direction": "column",
    "_gap": { "size": "16", "unit": "px" }
  }
}
```

---

## 9. PIÈGES À ÉVITER

### ❌ Erreur 1 : Oublier les deux sens de la relation parent/enfant

```json
// ❌ FAUX - L'enfant ne sera pas affiché
{
  "id": "parent",
  "children": []  // Manque "child" ici
},
{
  "id": "child",
  "parent": "parent"
}

// ✅ CORRECT
{
  "id": "parent",
  "children": ["child"]
},
{
  "id": "child",
  "parent": "parent"
}
```

### ❌ Erreur 2 : Gap sans columnGap/rowGap

```json
// ❌ Peut ne pas fonctionner
"_gap": { "size": "80", "unit": "px" }

// ✅ Ajouter les deux
"_gap": { "size": "80", "unit": "px" },
"_columnGap": "80",
"_rowGap": "80"
```

### ❌ Erreur 3 : Mauvais format de margin/padding responsive

```json
// ❌ Incohérent
"_margin": { "bottom": { "size": "40", "unit": "px" } },
"_margin:mobile_portrait": { "bottom": "40px" }  // Format string

// ✅ Cohérent (choisir un format)
"_margin": { "bottom": "40" },
"_margin:mobile_portrait": { "bottom": "40" }
```

### ❌ Erreur 4 : Oublier que les breakpoints cascadent

```json
// Si tu veux différentes valeurs mobile_landscape et mobile_portrait,
// tu DOIS définir les deux explicitement

"_padding:tablet_portrait": { "left": "24", "right": "24" },
// ⚠️ Ceci s'applique AUSSI à mobile_landscape et mobile_portrait
// sauf si tu les définis explicitement
"_padding:mobile_portrait": { "left": "16", "right": "16" }
```

### ❌ Erreur 5 : Utiliser batch_add pour nav-nested children

Les éléments `block` dans `nav-nested` ont des règles spéciales. Il faut parfois mettre à jour le JSON complet de la page plutôt qu'utiliser batch_add.

### ❌ Erreur 6 : Oublier le CSS personnalisé pour les pseudo-classes

Bricks ne supporte pas directement `:hover`, `:focus`, etc. dans les settings. Utiliser `_cssCustom` :

```json
"_cssCustom": ".monElement:hover { background: blue; } .monElement:focus { outline: 2px solid blue; }"
```

### ❌ Erreur 7 : ID dupliqués

Chaque `id` DOIT être unique dans toute la page. Utiliser des préfixes ou des générateurs aléatoires.

---

## 10. EXEMPLES PRATIQUES

### Créer une Section Hero Complète

```json
[
  {
    "id": "hero01",
    "name": "section",
    "parent": 0,
    "children": ["heroBg", "heroContainer"],
    "settings": {
      "_cssId": "hero",
      "_cssClasses": "hero",
      "_padding": { "top": "120", "bottom": "80" },
      "_padding:mobile_portrait": { "top": "100", "bottom": "60" }
    }
  },
  {
    "id": "heroBg",
    "name": "div",
    "parent": "hero01",
    "children": [],
    "settings": {
      "_cssClasses": "hero-background",
      "_cssCustom": ".hero-background { position: absolute; inset: 0; background: linear-gradient(135deg, #E8F4FD 0%, #F7FAFC 50%, #FFF5F5 100%); z-index: -1; }"
    }
  },
  {
    "id": "heroContainer",
    "name": "container",
    "parent": "hero01",
    "children": ["heroLeft", "heroRight"],
    "settings": {
      "_cssClasses": "container hero-content",
      "_display": "grid",
      "_gridTemplateColumns": "1fr 1fr",
      "_gridTemplateColumns:tablet_portrait": "1fr",
      "_gap": { "size": "80", "unit": "px" },
      "_columnGap": "80",
      "_rowGap": "80",
      "_gap:tablet_portrait": { "size": "48", "unit": "px" },
      "_alignItems": "center",
      "_padding": { "left": "24", "right": "24" }
    }
  },
  {
    "id": "heroLeft",
    "name": "div",
    "parent": "heroContainer",
    "children": ["heroBadge", "heroTitle", "heroDesc"],
    "settings": {
      "_cssClasses": "hero-left"
    }
  },
  {
    "id": "heroBadge",
    "name": "text-basic",
    "parent": "heroLeft",
    "children": [],
    "settings": {
      "text": "⚡ Nouveau",
      "tag": "div",
      "_cssClasses": "hero-badge",
      "_cssCustom": ".hero-badge { display: inline-flex; padding: 8px 16px; background: #FF6B35; color: white; border-radius: 50px; font-size: 14px; font-weight: 600; margin-bottom: 24px; }"
    }
  },
  {
    "id": "heroTitle",
    "name": "heading",
    "parent": "heroLeft",
    "children": [],
    "settings": {
      "tag": "h1",
      "text": "Mon Super Titre<br><span class=\"text-gradient\">Avec Gradient</span>",
      "_typography": { "font-size": "48" },
      "_typography:mobile_portrait": { "font-size": "32" }
    }
  },
  {
    "id": "heroDesc",
    "name": "text-basic",
    "parent": "heroLeft",
    "children": [],
    "settings": {
      "text": "Description de mon hero avec du texte explicatif.",
      "tag": "p",
      "_typography": { "font-size": "18", "color": { "hex": "#4A5568" } }
    }
  },
  {
    "id": "heroRight",
    "name": "div",
    "parent": "heroContainer",
    "children": ["heroCard"],
    "settings": {
      "_cssClasses": "hero-right"
    }
  },
  {
    "id": "heroCard",
    "name": "div",
    "parent": "heroRight",
    "children": [],
    "settings": {
      "_cssClasses": "hero-card",
      "_cssCustom": ".hero-card { background: white; border-radius: 16px; padding: 32px; box-shadow: 0 25px 50px rgba(0,0,0,0.15); }"
    }
  }
]
```

### Modifier un Élément Existant

```javascript
// Changer la couleur et le padding d'un bouton
update_element({
  pageId: 640,
  elementId: "btn123",
  newSettings: {
    "_background": { "color": { "hex": "#28A745" } },
    "_padding": { "top": "16", "right": "32", "bottom": "16", "left": "32" },
    "_padding:mobile_portrait": { "top": "12", "right": "24", "bottom": "12", "left": "24" }
  }
})
```

### Ajouter des Marges Responsives

```javascript
// Ajouter du padding latéral sur mobile
update_element({
  pageId: 640,
  elementId: "container1",
  newSettings: {
    "_padding:mobile_portrait": { "left": "24", "right": "24" },
    "_padding:mobile_landscape": { "left": "24", "right": "24" }
  }
})
```

### Chercher et Modifier en Masse

```javascript
// 1. Trouver tous les boutons
const buttons = find_elements({
  pageId: 640,
  criteria: { className: "btn" }
})

// 2. Modifier chaque bouton
for (const btn of buttons.elements) {
  update_element({
    pageId: 640,
    elementId: btn.id,
    newSettings: {
      "_borderRadius": { "top-left": "12px", "top-right": "12px", "bottom-right": "12px", "bottom-left": "12px" }
    }
  })
}
```

---

## 📝 CHECKLIST DE CONVERSION HTML → BRICKS

### Avant de commencer
- [ ] Analyser la structure HTML source
- [ ] Identifier les breakpoints utilisés
- [ ] Lister les variables CSS (couleurs, fonts, espacements)
- [ ] Repérer les animations/transitions

### Structure
- [ ] Créer les sections principales (header, hero, sections, footer)
- [ ] Établir la hiérarchie des containers
- [ ] Vérifier les relations parent/children

### Styling Desktop
- [ ] Appliquer les couleurs et fonts
- [ ] Configurer les layouts (flex/grid)
- [ ] Ajouter les espacements (margin/padding)
- [ ] Implémenter les effets hover via _cssCustom

### Responsive
- [ ] Configurer tablet_portrait
- [ ] Configurer mobile_portrait
- [ ] Vérifier les marges latérales sur mobile
- [ ] Tester la navigation mobile (burger)

### Validation
- [ ] Screenshot comparatif Desktop (1200px+)
- [ ] Screenshot comparatif Tablet (768-991px)
- [ ] Screenshot comparatif Mobile (375px)
- [ ] Test des interactions (hover, click, formulaires)

---

## 🔗 RESSOURCES

- Documentation Bricks : https://academy.bricksbuilder.io/
- API MCP Bricks : Voir les outils disponibles dans le contexte
- Breakpoints CSS standard : Desktop > 1024px, Tablet ≤ 991px, Mobile ≤ 478px

---

*Guide créé le 14/12/2025 - Basé sur la conversion du site "Aide Travaux Fibre by DFT Expertise"*
