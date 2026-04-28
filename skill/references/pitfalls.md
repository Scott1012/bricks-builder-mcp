# 🚨 BRICKS PITFALLS - Pièges Courants et Solutions

> Catalogue des erreurs fréquentes avec solutions testées

---

## 1. 🎨 Icônes Font Awesome Invisibles (Carrés Blancs)

### Symptôme
Les icônes `social-icons` ou `icon-box` s'affichent comme des carrés blancs ⬜ au lieu des icônes Font Awesome.

### Cause
Screenshot pris **AVANT** le chargement de Font Awesome CSS (1-2s après DOM ready).

### Timeline Réelle
```
0ms      : DOM ready → <i class="fab fa-facebook"></i> existe
+500ms   : Font Awesome CSS commence à charger
+1500ms  : ::before content injecté → icône visible ✅
```

### ❌ Code Problématique
```javascript
// Créer social-icons
batch_add({
  elements: [{
    name: "social-icons",
    settings: {
      icons: [
        { icon: { library: "fontawesomeBrands", icon: "fab fa-facebook" }}
      ]
    }
  }]
})

// Screenshot immédiat (TROP RAPIDE !)
screenshot-website-fast:take_screenshot({
  url: "https://site.com/",
  width: 1920
})
// → Résultat : carrés blancs ⬜ (Font Awesome pas encore chargé)
```

### ✅ Solution
```javascript
// Créer social-icons
batch_add({
  elements: [{
    name: "social-icons",
    settings: {
      icons: [
        { icon: { library: "fontawesomeBrands", icon: "fab fa-facebook" }}
      ]
    }
  }]
})

// Screenshot avec délai (CORRECT)
screenshot-website-fast:take_screenshot({
  url: "https://site.com/",
  width: 1920,
  waitForMS: 2000  // ⚠️ CRITIQUE - Attendre Font Awesome
})
// → Résultat : icônes visibles ✅
```

### Preuve
Re-prendre un screenshot après 2s **sans modifier quoi que ce soit** → les icônes apparaissent magiquement.

### Règle d'Or
**TOUJOURS** inclure `waitForMS: 2000` dans CHAQUE screenshot, pas seulement pour Font Awesome.

---

## 2. ⚡ Gap ne Fonctionne Pas (Éléments Collés)

### Symptôme
Malgré `_gap: "24"`, les éléments restent collés sans espacement.

### Cause
Bricks nécessite **3 propriétés** pour que gap fonctionne : `_gap` + `_columnGap` + `_rowGap`.

### ❌ Code Problématique
```javascript
{
  "_display": "flex",
  "_gap": "24"  // ❌ Ignoré si seul
}
```

### ✅ Solution
```javascript
{
  "_display": "flex",
  "_gap": {"size": "24", "unit": "px"},
  "_columnGap": "24",  // ⚠️ OBLIGATOIRE
  "_rowGap": "24"      // ⚠️ OBLIGATOIRE
}
```

### Règle
**TOUJOURS** utiliser les 3 propriétés ensemble, même si tu veux juste un gap horizontal.

---

## 3. 🔄 Flex Horizontal ne Marche Pas (Empilage Vertical)

### Symptôme
`_display: "flex"` empile les éléments verticalement au lieu d'horizontalement.

### Cause
Défaut Bricks = `flex-direction: column`. Il faut **explicitement** définir `row`.

### ❌ Code Problématique
```javascript
{
  "_display": "flex",
  "_justifyContent": "space-between"
  // ❌ Pas de _direction → défaut = column
}
```

### ✅ Solution
```javascript
{
  "_display": "flex",
  "_direction": "row",  // ⚠️ OBLIGATOIRE pour horizontal
  "_justifyContent": "space-between"
}
```

### Règle
**TOUJOURS** spécifier `_direction: "row"` pour layout horizontal.

---

## 4. 📏 Width Max ne Fonctionne Pas

### Symptôme
Container déborde malgré tentative de limiter la largeur max.

### Cause
Mauvaise propriété utilisée : `_maxWidth` n'existe pas dans Bricks.

### ❌ Code Problématique
```javascript
{
  "_maxWidth": "1200px"  // ❌ Propriété inexistante
}
```

### ✅ Solution
```javascript
{
  "_widthMax": "1200"  // ✅ Bonne propriété (sans "px")
}
```

### Règle
Utilise `_widthMax`, `_heightMax`, `_widthMin`, `_heightMin` (pas `_maxWidth`).

---

## 5. 🔲 Border-Radius Bouton Ignoré

### Symptôme
`_borderRadius` sur un bouton ne fonctionne pas.

### Cause
Les boutons utilisent une structure différente : `_border.radius` au lieu de `_borderRadius`.

### ❌ Code Problématique
```javascript
{
  "name": "button",
  "settings": {
    "_borderRadius": "8"  // ❌ Ignoré sur boutons
  }
}
```

### ✅ Solution
```javascript
{
  "name": "button",
  "settings": {
    "_border": {
      "radius": {
        "top": "8",
        "right": "8",
        "bottom": "8",
        "left": "8"
      }
    }
  }
}
```

### Règle
**TOUJOURS** cloner un bouton existant pour obtenir le format exact.

---

## 6. 🔗 Impossible de Déplacer un Élément Entre Parents

### Symptôme
`update_element` ne permet pas de changer le parent d'un élément.

### Cause
`parent` et `children` sont au **niveau racine** de l'élément, PAS dans `settings`.

### ❌ Code Problématique
```javascript
// ❌ NE FONCTIONNERA JAMAIS
update_element({
  elementId: "my_element",
  newSettings: {
    parent: "new_parent"  // ❌ parent n'est pas dans settings
  }
})
```

### ✅ Solution
```javascript
// 1. Récupérer JSON complet
const json = get_page_json({ pageId: 640 })

// 2. Modifier 3 éléments
for (let el of json) {
  if (el.id === 'old_parent') {
    el.children = el.children.filter(id => id !== 'my_element')
  }
  else if (el.id === 'new_parent') {
    el.children.push('my_element')
  }
  else if (el.id === 'my_element') {
    el.parent = 'new_parent'
  }
}

// 3. Soumettre
update_page_json({ pageId: 640, newJsonData: json })
```

### Règle
Hiérarchie = `update_page_json` uniquement. `update_element` = settings CSS uniquement.

---

## 7. 🎨 Font-Size STRING vs OBJET

### Symptôme
`font-size` défini mais pas appliqué au rendu.

### Cause
Format varie selon installation : STRING (`"56"`) ou OBJET (`{"size": "56", "unit": "px"}`).

### ❌ Code Problématique
```javascript
{
  "_typography": {
    "font-size": {"size": "56", "unit": "px"}  // ❌ Peut ne pas marcher
  }
}
```

### ✅ Solution
```javascript
// 1. Cloner heading existant
find_elements({ criteria: { type: "heading" }})
get_element({ elementId: "existing_h1" })
// → settings._typography.font-size = "56" (STRING sur cette install)

// 2. Copier format exact
{
  "_typography": {
    "font-size": "56"  // ✅ STRING (format cloné)
  }
}
```

### Règle
**TOUJOURS** cloner `_typography` depuis élément similaire existant.

---

## 8. 📱 Responsive ne Fonctionne Pas

### Symptôme
Settings définis avec breakpoint (`:mobile_portrait`) mais pas appliqués.

### Cause
Clé de breakpoint mal orthographiée ou settings desktop écrasent.

### ❌ Code Problématique
```javascript
{
  "_padding": {"top": "120"},
  "_padding:mobile": {"top": "60"}  // ❌ Clé invalide
}
```

### ✅ Solution
```javascript
{
  "_padding": {"top": "120"},
  "_padding:mobile_portrait": {"top": "60"}  // ✅ Clé correcte
}
```

### Breakpoints Valides
- Desktop : (défaut, pas de suffixe)
- Tablet : `:tablet` (≤ 991px)
- Mobile landscape : `:mobile_landscape` (≤ 767px)
- Mobile portrait : `:mobile_portrait` (≤ 478px)

---

## 9. 🖼️ Container vs Div - Centrage

### Symptôme
Container centré automatiquement, div non centré malgré mêmes settings.

### Cause
Type `container` applique `margin: 0 auto` automatiquement, `div` non.

### ❌ Code Problématique
```javascript
{
  "name": "div",  // ❌ Pas de centrage auto
  "settings": {
    "_widthMax": "1200"
  }
}
// → Résultat : Div aligné à gauche
```

### ✅ Solution 1 : Utiliser container
```javascript
{
  "name": "container",  // ✅ Centrage auto
  "settings": {
    "_widthMax": "1200"
  }
}
```

### ✅ Solution 2 : Margin manuel sur div
```javascript
{
  "name": "div",
  "settings": {
    "_widthMax": "1200",
    "_margin": {
      "left": "auto",
      "right": "auto"
    }
  }
}
```

### Règle
Utilise `container` pour contenu centré, `div` pour layout manuel.

---

## 10. ❌ Modifications Non Visibles (Cache)

### Symptôme
Modification effectuée via `update_element` mais screenshot identique.

### Cause
Cache navigateur ou cache Bricks.

### Solution
```javascript
// 1. Modifier
update_element({ ... })

// 2. Attendre 1-2s
// (pas besoin de code, juste patienter)

// 3. Screenshot avec cache-busting
screenshot-website-fast:take_screenshot({
  url: "https://site.com/?nocache=" + Date.now(),
  waitForMS: 2000
})
```

### Règle
Toujours attendre 1-2s entre modification et vérification.

---

## 📋 Checklist Anti-Erreurs

Avant de créer/modifier un élément :

```
□ J'ai cloné le format depuis élément similaire existant (typo, border, gap)
□ J'utilise waitForMS: 2000 dans screenshots
□ Gap = 3 propriétés (_gap + _columnGap + _rowGap)
□ Flex horizontal = _direction: "row"
□ Width max = _widthMax (pas _maxWidth)
□ Bouton border = _border.radius (pas _borderRadius)
□ Modifier parent = update_page_json (pas update_element)
□ J'attends 1-2s entre modif et screenshot
```

---

*Guide créé le 14/12/2025*
*Basé sur cas réels rencontrés en production*
