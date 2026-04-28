# 🛠️ OUTILS DISPONIBLES - Bricks MCP & Vérification

> Référence complète des outils pour manipuler Bricks Builder et vérifier le rendu.

---

## 📋 SOMMAIRE

1. [Outils MCP Bricks (10 outils)](#1-outils-mcp-bricks)
2. [Outil Screenshot](#2-outil-screenshot)
3. [Outils Playwright](#3-outils-playwright)
4. [Stratégie de Choix](#4-stratégie-de-choix)
5. [Workflows Types](#5-workflows-types)

---

## 1. OUTILS MCP BRICKS

### 📖 LECTURE (4 outils)

#### `list_bricks_pages`
```javascript
list_bricks_pages()
```
| Info | Valeur |
|------|--------|
| **Usage** | Liste toutes les pages Bricks du site |
| **Retourne** | `[{ id, title, url, status }]` |
| **Tokens** | ~100 |
| **Quand** | Début de conversation pour découvrir les pages |

---

#### `get_page_structure` ⭐ RECOMMANDÉ
```javascript
get_page_structure({ pageId: 640 })
```
| Info | Valeur |
|------|--------|
| **Usage** | Vue d'ensemble LÉGÈRE de la structure |
| **Retourne** | `[{ id, name, parent, children, textPreview }]` |
| **Tokens** | ~200 (vs ~2000 pour get_page_json) |
| **Quand** | Comprendre la structure, naviguer, trouver où ajouter |

---

#### `find_elements` ⭐ RECOMMANDÉ
```javascript
find_elements({
  pageId: 640,
  criteria: { type: "button" },
  limit: 100
})

// Critères disponibles :
{ type: "heading" }           // Par type d'élément
{ type: "button", parent: "container_id" }  // Type + parent
{ hasText: "garage" }         // Contient ce texte
{ className: "btn-primary" }  // Par classe CSS
```
| Info | Valeur |
|------|--------|
| **Usage** | Recherche ciblée d'éléments |
| **Tokens** | ~50-200 |
| **Quand** | Trouver éléments spécifiques avant modification |

---

#### `get_element`
```javascript
get_element({
  pageId: 640,
  elementId: "btn123"
})
```
| Info | Valeur |
|------|--------|
| **Usage** | Détails complets d'UN élément |
| **Tokens** | ~100-300 |
| **Quand** | Besoin des settings complets d'un élément précis |

---

#### `get_page_json` ⚠️ COÛTEUX
```javascript
get_page_json({ pageId: 640 })
```
| Info | Valeur |
|------|--------|
| **Usage** | Récupère TOUT le JSON de la page |
| **Tokens** | ~1500-3000 |
| **Quand** | **UNIQUEMENT** pour refonte complète |

---

### ✏️ MODIFICATION (4 outils)

#### `update_element` ⭐ RECOMMANDÉ
```javascript
update_element({
  pageId: 640,
  elementId: "btn123",
  newSettings: {
    "_background": { "color": { "hex": "#ff6b35" } },
    "_padding": { "top": "24", "bottom": "24" }
  }
})
```
| Info | Valeur |
|------|--------|
| **Usage** | Modifie UN élément (fusion récursive des settings) |
| **Tokens** | ~50-100 |
| **Quand** | Changement ciblé (couleur, texte, padding, etc.) |

**⚠️ Important :** Les settings sont FUSIONNÉS (merge), pas remplacés. Seules les propriétés spécifiées sont modifiées.

---

#### `delete_element`
```javascript
delete_element({
  pageId: 640,
  elementId: "old_section"
})
```
| Info | Valeur |
|------|--------|
| **Usage** | Supprime un élément |
| **Tokens** | ~50 |
| **Note** | Nettoie automatiquement les références parent/enfant |

---

#### `reorder_sections` ⭐ IMPORTANT
```javascript
reorder_sections({
  pageId: 640,
  orderedIds: ["header_pro", "hero_section", "services_section", "footer"]
})
```
| Info | Valeur |
|------|--------|
| **Usage** | Réorganise l'ordre des sections principales (parent: 0) |
| **Tokens** | ~100 |
| **Quand** | Après création d'un header/footer pour le placer correctement |

**⚠️ CRITIQUE :** L'ordre dans le tableau JSON = ordre de rendu sur la page !

---

#### `update_page_json` ⚠️ COÛTEUX
```javascript
update_page_json({
  pageId: 640,
  newJsonData: [ /* tableau complet */ ]
})
```
| Info | Valeur |
|------|--------|
| **Usage** | Remplace TOUT le JSON de la page |
| **Tokens** | ~1500-3000 |
| **Quand** | Après `get_page_json` pour refonte complète |

---

### ➕ CRÉATION (2 outils)

#### `add_element`
```javascript
add_element({
  pageId: 640,
  element: {
    id: "newbtn",
    name: "button",
    parent: "container_id",
    children: [],
    settings: { text: "Cliquer" }
  },
  position: 5  // optionnel
})
```
| Info | Valeur |
|------|--------|
| **Usage** | Ajoute UN élément |
| **Tokens** | ~100 |
| **Quand** | Ajout simple d'un seul élément |

---

#### `batch_add` ⭐ RECOMMANDÉ
```javascript
batch_add({
  pageId: 640,
  elements: [
    { id: "sec1", name: "section", parent: 0, children: ["cont1"], settings: {...} },
    { id: "cont1", name: "container", parent: "sec1", children: ["h1", "p"], settings: {...} },
    { id: "h1", name: "heading", parent: "cont1", children: [], settings: { tag: "h2", text: "..." } },
    { id: "p", name: "text-basic", parent: "cont1", children: [], settings: {...} }
  ]
})
```
| Info | Valeur |
|------|--------|
| **Usage** | Ajoute PLUSIEURS éléments en une fois |
| **Tokens** | ~300-600 |
| **Limite** | Max 10-15 éléments par appel |
| **Quand** | Création de section complète |

---

### 🔍 ANALYSE (1 outil)

#### `analyze_json_structure`
```javascript
analyze_json_structure({
  jsonData: [ /* données JSON */ ]
})
```
| Info | Valeur |
|------|--------|
| **Usage** | Analyse la structure d'un JSON Bricks |
| **Quand** | Debug, comprendre une structure importée |

---

## 🧭 GUIDE DE DÉCISION : QUEL OUTIL POUR QUELLE MODIFICATION ?

**RÈGLE CRITIQUE DÉCOUVERTE** : Les champs `parent` et `children` sont au **niveau racine** de l'élément JSON, PAS dans `settings`.

| Ce que tu veux modifier | Outil à utiliser | Raison |
|-------------------------|------------------|--------|
| **Propriétés CSS/visuelles** | `update_element` | Rapide, léger (~50 tokens) |
| Couleur, taille, padding, margin | ✅ `update_element` | Modifie `settings` uniquement |
| Texte, contenu, liens | ✅ `update_element` | Modifie `settings` uniquement |
| **Hiérarchie/structure** | `update_page_json` | Seule méthode qui fonctionne |
| Changer le parent d'un élément | ⚠️ `update_page_json` | `parent` est au niveau racine |
| Ajouter/retirer des enfants | ⚠️ `update_page_json` | `children` est au niveau racine |
| Déplacer un élément | ⚠️ `update_page_json` | Doit modifier 3 éléments (ancien parent, nouveau parent, élément) |
| **Ordre des sections** | `reorder_sections` | Outil dédié, optimisé |
| Réorganiser sections top-level | ✅ `reorder_sections` | Plus simple que `update_page_json` |

### ⚡ Workflow : Déplacer un Élément Entre Parents

**IMPOSSIBLE avec `update_element`** → Utilise `update_page_json` :

```javascript
// ÉTAPE 1 : Récupérer le JSON complet
const json = get_page_json({ pageId: 640 })

// ÉTAPE 2 : Modifier 3 éléments
for (let element of json) {
  // 1. Retirer de l'ancien parent
  if (element.id === 'old_parent_id') {
    element.children = element.children.filter(id => id !== 'element_to_move')
  }
  // 2. Ajouter au nouveau parent
  else if (element.id === 'new_parent_id') {
    if (!element.children.includes('element_to_move')) {
      element.children.push('element_to_move')
    }
  }
  // 3. Changer le parent de l'élément
  else if (element.id === 'element_to_move') {
    element.parent = 'new_parent_id'
  }
}

// ÉTAPE 3 : Soumettre le JSON modifié
update_page_json({ pageId: 640, newJsonData: json })
```

**Token Cost** :
- `update_element` : ~50 tokens (settings CSS seulement)
- `update_page_json` : ~2500 tokens (tout le JSON)

**Règle d'or** : Utilise `update_element` autant que possible, réserve `update_page_json` pour les modifications structurelles.

---

## 2. OUTIL SCREENSHOT

### ⚠️ RÈGLE D'OR : TOUJOURS Ajouter un Délai

**OBLIGATOIRE** : Ajoute `waitForMS: 2000` à CHAQUE screenshot pour assurer que tout est chargé (Font Awesome, Google Fonts, images, etc.)

```javascript
// ✅ FORMAT STANDARD (à utiliser systématiquement)
screenshot-website-fast:take_screenshot({
  url: "https://site.com/page/",
  width: 1920,
  waitForMS: 2000,  // ⚠️ TOUJOURS inclure ce délai
  fullPage: true
})
```

**Pourquoi ?** Assets externes (Font Awesome, Google Fonts) chargent 1-2s après le DOM. Sans délai, tu risques de voir des carrés blancs à la place des icônes ou des fonts par défaut.

### Syntaxe Complète

```javascript
screenshot-website-fast:take_screenshot({
  url: "https://site.com/page/",
  width: 1920,
  waitForMS: 2000,        // Délai en millisecondes
  fullPage: true,
  waitUntil: "domcontentloaded"
})
```

### Paramètres

| Paramètre | Type | Défaut | Description |
|-----------|------|--------|-------------|
| `url` | string | **REQUIS** | URL complète de la page |
| `width` | number | 1920 | Largeur viewport (px) |
| `waitForMS` | number | - | ⚠️ **RECOMMANDÉ 2000** - Délai avant capture (ms) |
| `fullPage` | boolean | true | Capture toute la page ou viewport |
| `waitUntil` | string | "domcontentloaded" | Event d'attente |

### Largeurs Recommandées

| Device | Width | Usage |
|--------|-------|-------|
| Desktop | 1920 | Vérification principale |
| Desktop small | 1200 | Container max-width |
| Tablet | 768 | Responsive tablet |
| Mobile | 375 | Responsive mobile (iPhone) |

### Options waitUntil

| Valeur | Description |
|--------|-------------|
| `"load"` | Attend tout (images, CSS, JS) - plus lent |
| `"domcontentloaded"` | DOM ready - rapide (recommandé) |
| `"networkidle0"` | Réseau inactif complet |
| `"networkidle2"` | Max 2 connexions réseau |

### Exemples

```javascript
// ✅ Desktop full page (FORMAT RECOMMANDÉ)
screenshot-website-fast:take_screenshot({
  url: "https://monsite.com/",
  width: 1920,
  waitForMS: 2000  // ⚠️ TOUJOURS inclure
})

// ✅ Mobile
screenshot-website-fast:take_screenshot({
  url: "https://monsite.com/",
  width: 375,
  waitForMS: 2000  // ⚠️ TOUJOURS inclure
})

// ✅ Viewport uniquement (pas scroll)
screenshot-website-fast:take_screenshot({
  url: "https://monsite.com/",
  width: 1920,
  waitForMS: 2000,  // ⚠️ TOUJOURS inclure
  fullPage: false
})

// ✅ Chargement complet garanti (assets lourds)
screenshot-website-fast:take_screenshot({
  url: "https://monsite.com/",
  width: 1920,
  waitForMS: 3000,      // Délai plus long si beaucoup d'assets
  waitUntil: "networkidle0"
})
```

### Quand Utiliser

✅ **TOUJOURS après :**
- Création nouvelle section
- Création header/footer
- `reorder_sections`
- Modification couleurs/typo importantes
- Fin de tâche

⚠️ **OPTIONNEL pour :**
- Changement texte seul
- Modification liens/URLs
- Ajout meta SEO

---

## 3. OUTILS PLAYWRIGHT

### Navigation

#### `browser_navigate`
```javascript
browser_navigate({ url: "https://site.com/page/" })
```

#### `browser_navigate_back`
```javascript
browser_navigate_back()
```

### Viewport

#### `browser_resize`
```javascript
browser_resize({ width: 375, height: 812 })
```

### Exécution JavaScript

#### `browser_evaluate` ⭐ ÉCONOME EN TOKENS
```javascript
browser_evaluate({
  function: `() => {
    const el = document.querySelector('.hero');
    if (!el) return null;
    const rect = el.getBoundingClientRect();
    const s = getComputedStyle(el);
    return {
      width: Math.round(rect.width),
      height: Math.round(rect.height),
      padding: s.padding,
      display: s.display
    };
  }`
})
```

**Usage :** Vérification rapide sans screenshot (~200 tokens vs image)

### Scripts de Diagnostic Utiles

#### Diagnostic Rapide (toutes sections)
```javascript
browser_evaluate({
  function: `() => {
    const vw = window.innerWidth;
    const results = { viewport: vw, sections: [] };
    
    document.querySelectorAll('section, [class*="section"]').forEach((s, i) => {
      const r = s.getBoundingClientRect();
      results.sections.push({
        index: i,
        class: s.className.substring(0, 30),
        visible: r.width > 0 && r.height > 0,
        left: Math.round(r.left),
        right: Math.round(vw - r.right)
      });
    });
    
    return results;
  }`
})
```

#### Diagnostic Marges Mobile
```javascript
browser_evaluate({
  function: `() => {
    const vw = window.innerWidth;
    const issues = [];
    
    document.querySelectorAll('.container, [class*="content"]').forEach(el => {
      const r = el.getBoundingClientRect();
      const left = Math.round(r.left);
      const right = Math.round(vw - r.right);
      
      if (left < 12 || right < 12) {
        issues.push({
          element: el.className.substring(0, 30),
          left,
          right,
          issue: 'Trop près du bord'
        });
      }
    });
    
    return { viewport: vw, issues };
  }`
})
```

#### Diagnostic Gaps
```javascript
browser_evaluate({
  function: `() => {
    const results = [];
    
    document.querySelectorAll('[class*="grid"], [class*="flex"]').forEach(el => {
      const s = getComputedStyle(el);
      results.push({
        class: el.className.substring(0, 30),
        display: s.display,
        gap: s.gap,
        columnGap: s.columnGap,
        rowGap: s.rowGap
      });
    });
    
    return results;
  }`
})
```

### Autres Outils Playwright

| Outil | Usage |
|-------|-------|
| `browser_click` | Cliquer sur élément |
| `browser_type` | Taper du texte |
| `browser_snapshot` | Snapshot accessibilité |
| `browser_wait_for` | Attendre texte/temps |
| `browser_tabs` | Gérer onglets |

---

## 4. STRATÉGIE DE CHOIX

### Arbre de Décision

```
Que dois-je faire ?

├─ Lister les pages ?
│  └→ list_bricks_pages
│
├─ Voir la structure ?
│  └→ get_page_structure (200 tokens) ✅
│
├─ Chercher un élément ?
│  └→ find_elements (80 tokens) ✅
│
├─ Modifier 1 élément ?
│  └→ find_elements + update_element (130 tokens) ✅
│
├─ Réorganiser ordre sections ?
│  └→ reorder_sections (100 tokens)
│
├─ Modifier 5 éléments similaires ?
│  └→ find_elements + 5× update_element (330 tokens)
│
├─ Ajouter 1 élément ?
│  └→ add_element (100 tokens)
│
├─ Créer 1 section (5-10 éléments) ?
│  └→ batch_add (500 tokens) ✅
│
├─ Créer header/footer ?
│  └→ batch_add + reorder_sections + screenshot (700 tokens)
│
├─ Créer page complète ?
│  └→ 3-5× batch_add progressivement (1500 tokens)
│
├─ Refonte totale ?
│  └→ get_page_json + modifications + update_page_json (2500 tokens) ⚠️
│
├─ Vérifier structure OK ?
│  └→ browser_evaluate (script JS) (200 tokens) ✅
│
└─ Vérifier rendu visuel ?
   └→ screenshot-website-fast:take_screenshot
```

### Règle d'Or

**Utilise TOUJOURS l'outil le PLUS LÉGER possible.**

| ❌ Coûteux | ✅ Économe |
|-----------|-----------|
| `get_page_json` (~2000 tokens) | `get_page_structure` (~200 tokens) |
| `get_page_json` + recherche | `find_elements` (~80 tokens) |
| Screenshot systématique | Script JS ciblé (~200 tokens) |
| `update_page_json` complet | `update_element` ciblé (~50 tokens) |

---

## 5. WORKFLOWS TYPES

### Workflow A : Modification Simple

```javascript
// 1. Chercher le bouton
find_elements({ pageId: 640, criteria: { type: "button" } })

// 2. Modifier
update_element({
  pageId: 640,
  elementId: "btn123",
  newSettings: { "_background": { "color": { "hex": "#28a745" } } }
})

// 3. Vérifier (script JS ou screenshot)
browser_evaluate({ function: `() => {...}` })
// OU
screenshot-website-fast:take_screenshot({ url: "...", width: 1920 })

// Total : ~200-300 tokens
```

---

### Workflow B : Création Section

```javascript
// 1. Créer structure
batch_add({
  pageId: 640,
  elements: [
    { id: "feat01", name: "section", parent: 0, children: ["featCont"], settings: {...} },
    { id: "featCont", name: "container", parent: "feat01", children: ["featTitle", "featGrid"], settings: {...} },
    // ... 5-10 éléments
  ]
})

// 2. Vérification rapide (script JS)
browser_evaluate({ function: `() => { return { section: !!document.querySelector('#features') }; }` })

// 3. Si OK, screenshot final
screenshot-website-fast:take_screenshot({ url: "...", width: 1920 })

// Total : ~600-800 tokens
```

---

### Workflow C : Création Header + Placement

```javascript
// 1. Créer header
batch_add({
  pageId: 640,
  elements: [
    { id: "header_pro", name: "section", parent: 0, children: ["header_cont"], settings: {...} },
    { id: "header_cont", name: "container", parent: "header_pro", children: ["logo", "nav"], settings: {...} },
    // ...
  ]
})

// 2. ⚠️ IMPORTANT : Placer header EN PREMIER
reorder_sections({
  pageId: 640,
  orderedIds: ["header_pro", "hero_section", "services_section", "footer"]
})

// 3. Vérifier visuellement
screenshot-website-fast:take_screenshot({ url: "...", width: 1920 })

// 4. Analyser : "Header en haut ? ✅"

// Total : ~700 tokens
```

---

### Workflow D : Page Complète

```
1. CSS Global (batch_add code element)        → pas de vérif
2. Header (batch_add + reorder_sections)      → SCREENSHOT
3. Hero (batch_add)                           → script JS rapide
4. Section 1 (batch_add)                      → script JS rapide
   ════════ SCREENSHOT Desktop ════════
5. Section 2 (batch_add)                      → script JS rapide
6. Section 3 (batch_add)                      → script JS rapide
7. Footer (batch_add)                         → script JS rapide
   ════════ SCREENSHOT Desktop ════════
8. Responsive (update_element × N)            → par breakpoint
   ════════ SCREENSHOT Mobile (375px) ════════
9. Polish final
   ════════ SCREENSHOT Final ════════
```

---

## 📋 RÉCAPITULATIF TOKENS

| Outil | Tokens |
|-------|--------|
| `list_bricks_pages` | ~100 |
| `get_page_structure` | ~200 |
| `find_elements` | ~80 |
| `get_element` | ~100-300 |
| `get_page_json` | ~1500-3000 ⚠️ |
| `update_element` | ~50 |
| `delete_element` | ~50 |
| `reorder_sections` | ~100 |
| `add_element` | ~100 |
| `batch_add` | ~300-600 |
| `update_page_json` | ~1500-3000 ⚠️ |
| `browser_evaluate` (script JS) | ~200 |
| `screenshot` | Variable (image) |

---

## ⚠️ RÈGLES ABSOLUES

1. **JAMAIS** `get_page_json` pour petite modification
2. **TOUJOURS** `find_elements` avant de modifier
3. **PRÉFÉRER** `batch_add` à `add_element` en boucle
4. **LIMITER** batch_add à 10-15 éléments max
5. **GÉNÉRER** des IDs uniques (6 caractères)
6. **RÉORGANISER** avec `reorder_sections` après création header/footer
7. **VÉRIFIER** avec script JS pour économiser, screenshot pour valider
8. **ANALYSER** et auto-corriger si problème détecté

---

*Guide outils créé le 14/12/2025*
