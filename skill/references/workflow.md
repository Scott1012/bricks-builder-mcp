# 🎯 MÉTHODOLOGIE OPTIMISÉE BRICKS BUILDER

> Guide pour travailler efficacement avec Bricks : économie de tokens, itérations progressives, vérifications intelligentes.

---

## 📋 SOMMAIRE

1. [Principes Fondamentaux](#1-principes-fondamentaux)
2. [Workflow par Type de Tâche](#2-workflow-par-type-de-tâche)
3. [Système de Vérification à 3 Niveaux](#3-système-de-vérification-à-3-niveaux)
4. [Économie de Tokens](#4-économie-de-tokens)
5. [Checklists de Vérification](#5-checklists-de-vérification)
6. [Scripts de Diagnostic](#6-scripts-de-diagnostic)

---

## 1. PRINCIPES FONDAMENTAUX

### 🧩 Principe #0 : TOUJOURS Cloner un Format Qui Marche (RÈGLE ABSOLUE)

**Avant d'inventer un format de propriété, clone un élément existant similaire.**

#### Pourquoi ?

Bricks a des comportements **spécifiques à chaque installation** :
- Certaines propriétés acceptent des strings (`"font-size": "44"`)
- D'autres des objets (`"line-height": { "size": "1.5", "unit": "" }`)
- Le guide théorique ne peut pas couvrir toutes les variations

**La seule source de vérité = ce qui fonctionne déjà sur l'installation.**

#### Procédure (3 étapes)

```javascript
// ÉTAPE 1 : Trouver un élément similaire existant
find_elements({
  pageId: 640,
  criteria: { type: "heading" }  // Même type que ce que tu veux créer
})

// ÉTAPE 2 : Récupérer ses settings complets
get_element({
  pageId: 640,
  elementId: "abc123"  // ID d'un élément qui fonctionne
})
// → Tu obtiens le format EXACT des settings

// ÉTAPE 3 : Copier le format (noms de clés + types de valeurs)
// Exemple : si tu vois "font-size": "48", utilise une STRING
// Si tu vois "line-height": { "size": "1.5" }, utilise un OBJET

// ✅ ADAPTER uniquement les VALEURS (texte, couleur, taille)
// ❌ NE PAS changer les TYPES (string vs objet)
```

#### Exemple Concret

**Besoin** : Créer un nouveau heading avec typo custom

**❌ MAUVAIS (inventer le format) :**
```json
{
  "_typography": {
    "fontSize": { "size": "44", "unit": "px" }  // ← Inventé, peut ne pas marcher
  }
}
```

**✅ BON (cloner un heading existant) :**
```javascript
// 1. Trouver headings existants
find_elements({ pageId: 640, criteria: { type: "heading" } })
// → Trouve "hero_title"

// 2. Récupérer son format
get_element({ pageId: 640, elementId: "hero_title" })
// → settings._typography = { "font-size": "56", "font-weight": "700" }

// 3. Copier le format
{
  "_typography": {
    "font-size": "44",      // ✅ STRING (comme l'original)
    "font-weight": "600"    // ✅ STRING (comme l'original)
  }
}
```

#### Quand Appliquer Ce Principe ?

**TOUJOURS pour :**
- `_typography` (formats variables selon installation)
- `_background` avec gradient/image (structure complexe)
- `_border` (width peut être string ou objet)
- `_margin` / `_padding` responsive (formats multiples possibles)
- Toute propriété que tu utilises **pour la première fois** sur ce projet

**Exceptions (formats stables) :**
- `_display: "flex"` (toujours string simple)
- `_cssClasses: "maClasse"` (toujours string)
- `text: "Mon texte"` (toujours string)

#### Gain

- ✅ **Fiabilité +80%** : Tu copies ce qui marche déjà
- ✅ **Zéro tentative inutile** : Pas de "essai/erreur" sur les formats
- ✅ **Économie tokens** : Pas de boucle de correction

---

### 🎯 Principe #1 : PETIT → VÉRIFIER → SUIVANT

```
❌ ANCIEN (gourmand en tokens, erreurs non détectées)
────────────────────────────────────────────────────
1. Créer 50 éléments d'un coup
2. Screenshot final
3. Découvrir 10 problèmes
4. Tout refaire

✅ NOUVEAU (économe, erreurs détectées tôt)
────────────────────────────────────────────────────
1. Créer section 1 (5-10 éléments)
2. Vérification RAPIDE
3. Créer section 2
4. Vérification RAPIDE
5. Après 3-4 sections → Vérification APPROFONDIE
6. Continuer...
```

### 📊 Ratio Optimal

| Tâche | Création | Vérif Rapide | Vérif Approfondie |
|-------|----------|--------------|-------------------|
| Petite modif (1-3 éléments) | 1 | 0 | 1 à la fin |
| Section moyenne (5-15 éléments) | 1 | 1 | - |
| Page complète | 5-8 | 5-8 | 2-3 |

---

### ⚡ Workflow Détaillé en 8 Étapes (CRITIQUE)

**Règle d'Or** : TOUJOURS tester le **contexte complet**, pas juste l'élément ciblé.

**Étapes obligatoires** :

**1. Screenshot AVANT (desktop + mobile)**
```javascript
screenshot-website-fast:take_screenshot({
  url: "https://site.com/page/",
  width: 1440,
  waitForMS: 2000  // ⚠️ TOUJOURS inclure pour chargement assets (Font Awesome, Google Fonts)
})
```

**2. Snapshot JS Contexte COMPLET**
```javascript
// ❌ MAUVAIS : Tester uniquement la cible
const target = document.querySelector('#brxe-footer_content');

// ✅ BON : Tester cible + contexte
const elementsToCheck = [
  '#brxe-footer_content',  // Cible
  '#brxe-footer_text',     // Enfant 1
  '#brxe-footer_social',   // Enfant 2
  '#brxe-footer_line',     // Sibling
  '#brxe-hero_footer'      // Parent
];

elementsToCheck.forEach(sel => {
  const el = document.querySelector(sel);
  if (el) {
    console.log(sel, {
      top: el.getBoundingClientRect().top,
      left: el.getBoundingClientRect().left,
      width: el.getBoundingClientRect().width,
      height: el.getBoundingClientRect().height
    });
  }
});
```

**3. Modification** via `update_element` ou `update_page_json`

**4. Screenshot APRÈS** (avec même délai qu'étape 1)
```javascript
screenshot-website-fast:take_screenshot({
  url: "https://site.com/page/",
  width: 1440,
  waitForMS: 2000  // ⚠️ TOUJOURS inclure
})
```

**5. Snapshot JS APRÈS** (mêmes éléments qu'étape 2)

**6. Comparer TOUT**
- Screenshots : différences visuelles
- JS : valeurs techniques (position, size, gaps)
- **Contexte** : vérifier que siblings/parent/enfants sont OK

**7. Valider ou Rollback**
- Si 1 seul problème détecté → Rollback
- Corriger puis recommencer workflow

**Exemple d'erreur évitée** :

```
Modifié : footer_content (padding)
Testé : footer_text alignment ✅
Oublié : footer_line position ❌
Résultat : Barre blanche cassée (gap 25px au lieu de 24px)
```

**Avec workflow complet** : footer_line aurait été testé, problème détecté immédiatement.

**Date de validation** : 2024-12-14
**Source** : Sessions responsive + corrections header/footer

---


### 🧩 Principe : Apprendre depuis l’installation (cloner un format qui marche)

Quand une propriété Bricks te semble “incertaine” (ex: typographie, background avancé, grid), **n’invente pas le format**.

Procédure :
1. `find_elements` pour repérer un élément **similaire** qui existe déjà (même type : heading, button, container, etc.).
2. `get_element` sur cet élément.
3. **Copie le format exact** des `settings` (noms de clés + types de valeurs : string vs objet) et adapte uniquement les valeurs.

Objectif : **mimer les patterns réels** de l’installation plutôt que de se fier uniquement au guide.

### ✅ Preuve de rendu : computed style (pas la recherche dans `<style>`)

Pour confirmer qu’un setting Bricks est bien pris en compte, la preuve n’est pas “je retrouve la règle CSS dans une balise `<style>`”.
La preuve fiable = **`getComputedStyle` + delta avant/après** sur l’élément ciblé.

⚙️ Cadence : conserve la méthodologie existante (vérifier par lots), mais :
- à chaque **Vérification RAPIDE**, contrôle 2–3 propriétés clés (ex: `display`, `gap`, `padding`, `fontSize`, `backgroundImage`)
- si un delta ne bouge pas → tenter **1 correction de format** (clé alternative, type string vs objet), puis re-check
- si toujours KO → produire un **REPORT** (pour enrichir la doc)


## 2. WORKFLOW PAR TYPE DE TÂCHE

### 🔧 TYPE A : PETITE MODIFICATION (1-5 éléments)

```
ÉTAPES:
1. get_element (élément cible)
2. update_element (modification)
3. → Vérification APPROFONDIE finale

TOKENS: ~500-1000
VÉRIFICATIONS: 1 seule (approfondie) à la fin
```

**Exemple : Changer couleur d'un bouton**
```javascript
// 1. Récupérer
get_element({ pageId: 640, elementId: "btn123" })

// 2. Modifier
update_element({ 
  pageId: 640, 
  elementId: "btn123",
  newSettings: { "_background": { "color": { "hex": "#28A745" } } }
})

// 3. Vérification APPROFONDIE (voir section 3)
```

---

### 🏗️ TYPE B : CRÉATION DE SECTION (5-20 éléments)

```
ÉTAPES:
1. Planifier la structure (mental, pas d'API)
2. batch_add (5-10 éléments max par batch)
3. → Vérification RAPIDE
4. batch_add (suite si nécessaire)
5. → Vérification RAPIDE
6. Ajustements update_element
7. → Vérification APPROFONDIE

TOKENS: ~2000-4000
VÉRIFICATIONS: 2-3 rapides + 1 approfondie
```

**Exemple : Créer une section "Features"**
```javascript
// PHASE 1: Structure de base
batch_add({
  pageId: 640,
  elements: [
    { id: "feat01", name: "section", parent: 0, children: ["featCont"], settings: {...} },
    { id: "featCont", name: "container", parent: "feat01", children: ["featHead", "featGrid"], settings: {...} },
    { id: "featHead", name: "div", parent: "featCont", children: ["featTitle", "featDesc"], settings: {...} },
    { id: "featTitle", name: "heading", parent: "featHead", children: [], settings: { tag: "h2", text: "..." } },
    { id: "featDesc", name: "text-basic", parent: "featHead", children: [], settings: {...} }
  ]
})

// → VÉRIFICATION RAPIDE (structure OK?)

// PHASE 2: Contenu (cards)
batch_add({
  pageId: 640,
  elements: [
    { id: "featGrid", name: "div", parent: "featCont", children: ["card1", "card2", "card3"], settings: {...} },
    { id: "card1", name: "div", parent: "featGrid", children: [...], settings: {...} },
    // ... autres cards
  ]
})

// → VÉRIFICATION RAPIDE (cards affichées?)

// PHASE 3: Ajustements
update_element({ /* corrections */ })

// → VÉRIFICATION APPROFONDIE
```

---

### 📄 TYPE C : PAGE COMPLÈTE / COPIE DE SITE

```
ÉTAPES:
1. ANALYSE source (structure, breakpoints, couleurs)
2. Créer CSS global (variables, classes communes)
3. Pour chaque section:
   a. batch_add (structure)
   b. → Vérification RAPIDE
   c. Ajustements
4. Après 3-4 sections → Vérification APPROFONDIE
5. Responsive (toutes sections)
6. → Vérification APPROFONDIE mobile
7. Polish final

TOKENS: ~10000-20000
VÉRIFICATIONS: 6-10 rapides + 3-4 approfondies
```

**Ordre recommandé :**
```
1. CSS Global + Variables     → pas de vérif
2. Header                     → RAPIDE
3. Hero                       → RAPIDE
4. Section 1                  → RAPIDE
   ─── VÉRIFICATION APPROFONDIE Desktop ───
5. Section 2                  → RAPIDE
6. Section 3                  → RAPIDE
7. Section 4                  → RAPIDE
   ─── VÉRIFICATION APPROFONDIE Desktop ───
8. Footer                     → RAPIDE
9. Responsive ALL             → RAPIDE par breakpoint
   ─── VÉRIFICATION APPROFONDIE Mobile ───
10. Polish                    → APPROFONDIE finale
```

---

## 3. SYSTÈME DE VÉRIFICATION À 3 NIVEAUX

### 🟢 NIVEAU 1 : VÉRIFICATION RAPIDE (~200 tokens)

**Quand :** Après chaque batch_add ou groupe de modifications

**Méthode : Script JS via Playwright**

```javascript
// SCRIPT VÉRIFICATION RAPIDE
browser_evaluate({
  function: `() => {
    const check = (sel) => {
      const el = document.querySelector(sel);
      if (!el) return { exists: false };
      const rect = el.getBoundingClientRect();
      const s = getComputedStyle(el);
      return {
        exists: true,
        visible: rect.width > 0 && rect.height > 0,
        width: Math.round(rect.width),
        height: Math.round(rect.height),
        display: s.display,
        children: el.children.length
      };
    };
    
    return {
      viewport: window.innerWidth,
      // Adapter les sélecteurs à la section créée
      section: check('.ma-nouvelle-section'),
      container: check('.ma-nouvelle-section .container'),
      title: check('.ma-nouvelle-section h2'),
      grid: check('.ma-nouvelle-section .grid')
    };
  }`
})
```

**Critères OK :**
- ✅ `exists: true` pour tous les éléments attendus
- ✅ `visible: true` (width/height > 0)
- ✅ `children` correspond au nombre attendu

**Si problème détecté :** Corriger AVANT de continuer

---

### 🟡 NIVEAU 2 : VÉRIFICATION APPROFONDIE (~500-800 tokens)

**Quand :** 
- Après 3-4 sections créées
- Avant de passer au responsive
- À la fin d'une tâche moyenne

**Méthode : Script JS complet + Screenshot ciblé**

```javascript
// SCRIPT VÉRIFICATION APPROFONDIE
browser_evaluate({
  function: `() => {
    const getFullInfo = (sel) => {
      const el = document.querySelector(sel);
      if (!el) return null;
      const rect = el.getBoundingClientRect();
      const s = getComputedStyle(el);
      return {
        // Position
        top: Math.round(rect.top),
        left: Math.round(rect.left),
        right: Math.round(window.innerWidth - rect.right),
        bottom: Math.round(rect.bottom),
        width: Math.round(rect.width),
        height: Math.round(rect.height),
        
        // Layout
        display: s.display,
        flexDirection: s.flexDirection,
        justifyContent: s.justifyContent,
        alignItems: s.alignItems,
        gap: s.gap,
        
        // Spacing
        margin: s.margin,
        padding: s.padding,
        
        // Typo
        fontSize: s.fontSize,
        fontWeight: s.fontWeight,
        color: s.color,
        
        // Background
        background: s.background?.substring(0, 50),
        
        // Autres
        position: s.position,
        zIndex: s.zIndex,
        overflow: s.overflow
      };
    };
    
    // Vérifier TOUTES les sections principales
    return {
      viewport: { width: window.innerWidth, height: window.innerHeight },
      header: getFullInfo('.header'),
      hero: getFullInfo('.hero'),
      heroContent: getFullInfo('.hero-content'),
      section1: getFullInfo('#solutions'),
      section2: getFullInfo('#aides'),
      footer: getFullInfo('.footer')
    };
  }`
})
```

**Points à vérifier :**
| Élément | Vérification |
|---------|--------------|
| Marges latérales | `left` et `right` cohérents (ex: 24px) |
| Gaps | `gap` correspond au design |
| Alignement | Sections alignées (même `left`) |
| Overflow | Pas de `overflow: hidden` non voulu |
| Z-index | Header > contenu |

**+ Screenshot si doute :**
```javascript
browser_take_screenshot({ filename: "check-desktop.png", fullPage: false })
```

---

### 🔴 NIVEAU 3 : VÉRIFICATION COMPLÈTE (~1500-2000 tokens)

**Quand :**
- Fin de création de page
- Après responsive complet
- Avant livraison finale
- Quand le client signale un problème

**Méthode : Multi-viewport + Comparaison + Screenshots**

```javascript
// ÉTAPE 1: Desktop (1200px)
browser_resize({ width: 1200, height: 800 })
browser_navigate({ url: "https://..." })

// Script de diagnostic complet
browser_evaluate({
  function: `() => {
    const results = {
      viewport: { width: window.innerWidth, height: window.innerHeight },
      errors: [],
      warnings: [],
      elements: {}
    };
    
    // 1. Vérifier tous les containers
    document.querySelectorAll('.container').forEach((el, i) => {
      const rect = el.getBoundingClientRect();
      const s = getComputedStyle(el);
      
      // Check marges latérales
      const leftMargin = Math.round(rect.left);
      const rightMargin = Math.round(window.innerWidth - rect.right);
      
      if (Math.abs(leftMargin - rightMargin) > 5) {
        results.warnings.push(\`Container \${i}: marges asymétriques (L:\${leftMargin} R:\${rightMargin})\`);
      }
      
      // Check largeur max
      if (rect.width > 1200) {
        results.errors.push(\`Container \${i}: trop large (\${rect.width}px > 1200px)\`);
      }
      
      results.elements[\`container_\${i}\`] = {
        width: Math.round(rect.width),
        left: leftMargin,
        right: rightMargin
      };
    });
    
    // 2. Vérifier les gaps/espacements
    document.querySelectorAll('[class*="grid"]').forEach((el, i) => {
      const s = getComputedStyle(el);
      if (s.gap === 'normal' || s.gap === '0px') {
        results.warnings.push(\`Grid \${i}: pas de gap défini\`);
      }
    });
    
    // 3. Vérifier les textes tronqués
    document.querySelectorAll('h1, h2, h3, p').forEach((el, i) => {
      if (el.scrollWidth > el.clientWidth) {
        results.errors.push(\`Texte tronqué: "\${el.textContent.substring(0, 30)}..."\`);
      }
    });
    
    // 4. Vérifier les images
    document.querySelectorAll('img').forEach((el, i) => {
      if (!el.complete || el.naturalHeight === 0) {
        results.errors.push(\`Image non chargée: \${el.src?.substring(0, 50)}\`);
      }
    });
    
    // 5. Vérifier les liens
    document.querySelectorAll('a').forEach((el, i) => {
      const href = el.getAttribute('href');
      if (!href || href === '#' || href === '') {
        results.warnings.push(\`Lien vide: "\${el.textContent?.substring(0, 20)}"\`);
      }
    });
    
    // 6. Vérifier z-index header
    const header = document.querySelector('.header, header');
    if (header) {
      const s = getComputedStyle(header);
      if (parseInt(s.zIndex) < 100) {
        results.warnings.push('Header: z-index trop bas');
      }
    }
    
    return results;
  }`
})

// Screenshot Desktop
browser_take_screenshot({ filename: "final-desktop.png", fullPage: true })

// ÉTAPE 2: Mobile (375px)
browser_resize({ width: 375, height: 812 })
browser_navigate({ url: "https://..." })

// Même script de diagnostic
// ...

// Screenshot Mobile
browser_take_screenshot({ filename: "final-mobile.png", fullPage: true })
```

**Checklist de validation finale :**

```
DESKTOP (1200px+)
─────────────────
□ Toutes les sections visibles
□ Marges latérales cohérentes (24px typiquement)
□ Containers centrés (max-width 1100-1200px)
□ Gaps corrects entre éléments
□ Pas de texte tronqué
□ Images chargées
□ Header sticky fonctionne
□ Liens fonctionnels

TABLET (768px)
─────────────────
□ Grid passe en 2 colonnes ou 1
□ Navigation adaptée
□ Marges ajustées

MOBILE (375px)
─────────────────
□ Tout en 1 colonne
□ Menu burger fonctionne
□ Marges latérales (16-24px)
□ Texte lisible (min 14px)
□ Boutons cliquables (min 44px hauteur)
□ Pas de scroll horizontal
```

---

## 4. ÉCONOMIE DE TOKENS

### 📉 Comparatif des Méthodes

| Action | ❌ Coûteux | ✅ Économe |
|--------|-----------|-----------|
| Voir structure | `get_page_json` (~5000 tokens) | `get_page_structure` (~500 tokens) |
| Chercher élément | `get_page_json` + recherche | `find_elements` (~300 tokens) |
| Vérifier rendu | Screenshot full page | Script JS ciblé (~200 tokens) |
| Modifier 1 prop | `get_page_json` + `update_page_json` | `update_element` (~100 tokens) |

### 🎯 Règles d'Or

```
1. JAMAIS get_page_json sauf pour refonte totale
2. TOUJOURS get_page_structure d'abord
3. PRÉFÉRER find_elements pour chercher
4. UTILISER update_element pour modifications ciblées
5. GROUPER avec batch_add (max 10-15 éléments)
6. SCRIPTS JS plutôt que screenshots pour vérifier
7. SCREENSHOTS seulement pour validation visuelle finale
```

### 📊 Budget Tokens par Tâche

| Tâche | Budget Recommandé |
|-------|-------------------|
| Petite modif | 500-1000 tokens |
| Section moyenne | 2000-4000 tokens |
| Page complète | 10000-20000 tokens |
| Copie site complet | 20000-40000 tokens |

---

## 5. CHECKLISTS DE VÉRIFICATION

### ✅ Checklist RAPIDE (après chaque batch)

```javascript
// Copier-coller ce script
`() => {
  const check = (s) => {
    const e = document.querySelector(s);
    return e ? { ok: true, w: e.offsetWidth, h: e.offsetHeight } : { ok: false };
  };
  return {
    // ADAPTER CES SÉLECTEURS
    section: check('.nouvelle-section'),
    container: check('.nouvelle-section .container'),
    content: check('.nouvelle-section .content')
  };
}`
```

### ✅ Checklist APPROFONDIE (après 3-4 sections)

```
STRUCTURE
□ Tous les éléments existent
□ Hiérarchie parent/children correcte
□ Ordre des sections correct

LAYOUT
□ Display flex/grid appliqué
□ Gaps définis (vérifier _columnGap/_rowGap)
□ Alignement correct

SPACING
□ Marges latérales cohérentes
□ Padding interne correct
□ Espacement entre sections

RESPONSIVE
□ Breakpoints définis
□ Pas de débordement
```

### ✅ Checklist COMPLÈTE (fin de projet)

```
DESKTOP
□ Largeur max containers (1100-1200px)
□ Centrage horizontal
□ Tous les gaps
□ Typographie (tailles, couleurs)
□ Hover states
□ Header sticky + z-index

MOBILE
□ Stack en colonnes
□ Marges latérales (16-24px)
□ Menu burger
□ Touch targets (44px min)
□ Font sizes (14px min)
□ Pas de scroll horizontal

FONCTIONNEL
□ Liens fonctionnels
□ Formulaires
□ Images chargées
□ Animations fluides

ACCESSIBILITÉ
□ Aria-labels
□ Alt images
□ Contraste couleurs
□ Focus visible
```

---

## 6. SCRIPTS DE DIAGNOSTIC

### Script 1 : Diagnostic Rapide Universel

```javascript
// Utiliser après chaque modification
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
        fullWidth: Math.abs(r.width - vw) < 5,
        left: Math.round(r.left),
        right: Math.round(vw - r.right)
      });
    });
    
    return results;
  }`
})
```

### Script 2 : Diagnostic Marges Mobile

```javascript
// Utiliser sur mobile (375px)
browser_evaluate({
  function: `() => {
    const vw = window.innerWidth;
    const issues = [];
    
    document.querySelectorAll('.container, [class*="content"], [class*="grid"]').forEach(el => {
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

### Script 3 : Diagnostic Gaps

```javascript
// Vérifier que les gaps sont appliqués
browser_evaluate({
  function: `() => {
    const results = [];
    
    document.querySelectorAll('[class*="grid"], [class*="flex"], [class*="cards"]').forEach(el => {
      const s = getComputedStyle(el);
      results.push({
        class: el.className.substring(0, 30),
        display: s.display,
        gap: s.gap,
        columnGap: s.columnGap,
        rowGap: s.rowGap,
        hasGap: s.gap !== 'normal' && s.gap !== '0px'
      });
    });
    
    return results;
  }`
})
```

### Script 4 : Comparaison avec Original

```javascript
// Pour copie de site - comparer les métriques
browser_evaluate({
  function: `() => {
    const getMetrics = (sel) => {
      const el = document.querySelector(sel);
      if (!el) return null;
      const r = el.getBoundingClientRect();
      const s = getComputedStyle(el);
      return {
        width: Math.round(r.width),
        height: Math.round(r.height),
        marginL: Math.round(r.left),
        marginR: Math.round(window.innerWidth - r.right),
        padding: s.padding,
        gap: s.gap,
        fontSize: s.fontSize
      };
    };
    
    return {
      viewport: window.innerWidth,
      // ADAPTER les sélecteurs
      header: getMetrics('.header'),
      hero: getMetrics('.hero'),
      heroContent: getMetrics('.hero-content'),
      cards: getMetrics('.cards-grid'),
      footer: getMetrics('.footer')
    };
  }`
})

// Exécuter sur les 2 sites et comparer les valeurs
```

---

## 📋 RÉSUMÉ WORKFLOW

```
┌─────────────────────────────────────────────────────────┐
│                    PETITE MODIF                         │
├─────────────────────────────────────────────────────────┤
│ get_element → update_element → VÉRIF APPROFONDIE        │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│                  CRÉATION SECTION                       │
├─────────────────────────────────────────────────────────┤
│ batch_add (5-10) → VÉRIF RAPIDE                        │
│ batch_add (suite) → VÉRIF RAPIDE                       │
│ update_element (ajust.) → VÉRIF APPROFONDIE            │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│                   PAGE COMPLÈTE                         │
├─────────────────────────────────────────────────────────┤
│ CSS Global                                              │
│ Header → RAPIDE                                         │
│ Hero → RAPIDE                                           │
│ Section 1 → RAPIDE                                      │
│ ═══════════ APPROFONDIE Desktop ═══════════            │
│ Section 2 → RAPIDE                                      │
│ Section 3 → RAPIDE                                      │
│ Footer → RAPIDE                                         │
│ ═══════════ APPROFONDIE Desktop ═══════════            │
│ Responsive → RAPIDE par breakpoint                      │
│ ═══════════ APPROFONDIE Mobile ═══════════             │
│ ═══════════ COMPLÈTE Finale ═══════════                │
└─────────────────────────────────────────────────────────┘
```

---

## 🚨 ERREURS À DÉTECTER TÔT

| Symptôme | Cause Probable | Vérification |
|----------|---------------|--------------|
| Élément invisible | parent/children mal liés | `get_element` des deux |
| Pas de gap | `_gap` seul sans `_columnGap/_rowGap` | Script diagnostic gaps |
| Marges asymétriques | Padding/margin incohérent | Script marges mobile |
| Texte tronqué | Overflow hidden ou width fixe | Inspect visuel |
| Menu burger ne marche pas | mobileMenuBreakpoint mal configuré | Test manuel |
| Header sous contenu | z-index trop bas | Script z-index |

---

*Guide méthodologique créé le 14/12/2025*
*Optimisé pour économie de tokens et détection précoce des erreurs*
