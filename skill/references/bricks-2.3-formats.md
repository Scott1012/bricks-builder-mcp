# 🧬 Bricks 2.3 — Formats validés en production

> **Validé sur JT Carrelage le 2026-04-29** — Bricks Builder v2.3.2 / WordPress 6.9.4 / PHP 8.1.34

Ce document liste **tous les formats Bricks 2.3 que j'ai validés en production**. Avant d'inventer un format, **vérifie ici en premier**. Si un format n'est pas listé, regarde aussi `references/test-structures.json` ou clone depuis un élément existant via `find_elements` + `get_element`.

⚠️ **CES FORMATS REMPLACENT** ce qui peut être trouvé dans les autres fichiers du skill (qui datent d'une autre installation Bricks). En cas de conflit, **fais confiance à ce document**.

---

## 1. Border-radius

### ❌ NE PAS faire (ne s'applique pas)
```json
"_borderRadius": {
  "top-left": "8px",
  "top-right": "8px",
  "bottom-right": "8px",
  "bottom-left": "8px"
}
```
**Pourquoi** : Bricks 2.3 ignore le `_borderRadius` flat, surtout sur les éléments `image`. Le CSS rendu ne contient pas de `border-radius`.

### ✅ Format Bricks 2.3
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

**Notes importantes** :
- Imbriqué dans `_border`, pas en flat
- Clés `top/right/bottom/left` (pas `top-left/top-right/...`)
- **Accepte les unités CSS** : `"8"`, `"8px"`, `"50%"`, `"999"`, `"1rem"`
- Pour un cercle parfait sur image carrée : `"50%"` (sémantique) OU `"999"` (trick CSS, marche aussi)
- Exemple bouton pill : `{top: "999", right: "999", bottom: "999", left: "999"}`

---

## 2. Line-height

### ❌ NE PAS faire
```json
"line-height": {"size": "1.5", "unit": ""}
```
**Pourquoi** : Bricks 2.3 affiche `[object Object]` dans le builder UI et le CSS frontend ne contient PAS de `line-height`.

### ✅ Format Bricks 2.3
```json
"line-height": "1.5"
```

**Notes** :
- Toujours en **string simple**
- Sans unité pour un ratio (`"1.5"`, `"0.88"`)
- Avec unité si nécessaire (`"24px"`, `"1.5rem"`)

---

## 3. Couleurs

### Format hex simple (couleur opaque)
```json
{"color": {"hex": "#FD5B2C"}}
```

### Couleur avec transparence (rgba)
```json
{"color": {"raw": "rgba(0,0,0,0.45)"}}
```
**Pourquoi `raw` et pas `hex`** : `{hex: "#000000"}` ne supporte pas l'alpha. Le `raw` accepte n'importe quelle valeur CSS valide.

### Variable CSS (référence à un design system)
```json
{"color": {"raw": "var(--primary)"}}
```
**Important** : pour que `var(--primary)` fonctionne, il faut que la variable soit déclarée dans le `<head>` du site (via `set_custom_code({customCss: ":root { --primary: #FD5B2C; }"})`). Voir Section 7.

### Couleur de palette globale
```json
{"color": {"raw": "var(--bricks-color-XXXXX)", "id": "XXXXX", "name": "Quoti Orange"}}
```
**Note** : référencer une couleur via son `id` permet la cohérence (changer la couleur en palette → propage). Format à confirmer en récupérant un élément qui en utilise.

---

## 4. Typography (police, taille, etc.)

### Format de base
```json
"_typography": {
  "font-family": "Anton, Impact, sans-serif",
  "font-size": "108",
  "font-weight": "400",
  "color": {"hex": "#ffffff"},
  "text-transform": "uppercase",
  "line-height": "0.88",
  "letter-spacing": "-3px"
}
```

**Notes** :
- `font-size` en **string** (sans unité = px par défaut)
- `font-weight` en **string** (`"400"`, pas `400`)
- `line-height` en **string** (cf section 2)
- `letter-spacing` en **string** avec unité
- `text-transform` : `uppercase / lowercase / capitalize / none`

### Avec font-family de variable CSS
```json
"font-family": "var(--font-display)"
```
**Confirmé OK** en Bricks 2.3 — la variable CSS résout au runtime.

### Text-shadow
```json
"text-shadow": {
  "values": {"offsetX": "0", "offsetY": "2", "blur": "20"},
  "color": {"hex": "rgba(0,0,0,0.45)"}
}
```
Note : `color.hex` accepte les rgba dans cette propriété (à confirmer si vraiment hex ou si il faut raw).

---

## 5. Padding / Margin

```json
"_padding": {
  "top": "16",
  "right": "28",
  "bottom": "16",
  "left": "28"
}
```

**Notes** :
- Valeurs en **string** sans unité (px par défaut)
- Avec unité : `"16px"`, `"1rem"`, `"50%"`
- Variable CSS : `"var(--space-md)"` (à confirmer)
- Format **flat** `{top, right, bottom, left}` (PAS imbriqué)

---

## 6. Background

### Couleur unie
```json
"_background": {"color": {"hex": "#0f172a"}}
```

### Vidéo en arrière-plan (natif Bricks)
```json
"_background": {
  "color": {"hex": "#1a1a1a"},
  "videoUrl": "https://example.com/video.mp4",
  "videoPlayOnce": false,
  "videoAspectRatio": "16:9",
  "videoScale": "1"
}
```
**Validé en production** : MP4 hébergé sur CDN externe, autoplay+loop+muted automatiques.

### Gradient (NON validé — à investiguer)
```json
"_background": {
  "gradient": {
    "type": "linear",
    "angle": 180,
    "stops": [
      {"color": "rgba(0,0,0,0.15)", "position": 0},
      {"color": "rgba(0,0,0,0.65)", "position": 100}
    ]
  }
}
```
⚠️ **Ce format n'a PAS produit de gradient sur Bricks 2.3** dans nos tests. Le CSS rendu était vide. Probablement que Bricks 2.3 attend un format différent (peut-être `_gradient` séparé). En attendant : utiliser une couleur unie avec rgba (`{color: {raw: "rgba(0,0,0,0.45)"}}`) pour les overlays.

---

## 7. Custom Code global (la SEULE méthode validée pour Google Fonts)

Bricks Font Manager (`register_google_font_locally`) crée bien un post `bricks_fonts`, **mais Bricks NE génère PAS de `@font-face` frontend** depuis ce post. Conséquence : la font ne se charge pas, fallback navigateur (Times Roman).

### ✅ Méthode officielle : injecter le `<link>` Google Fonts dans le `<head>` global

```python
set_custom_code({
  "customScriptsHeader": "<link rel='preconnect' href='https://fonts.googleapis.com'><link rel='preconnect' href='https://fonts.gstatic.com' crossorigin><link href='https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@400;500;600;700&display=swap' rel='stylesheet'>"
})
```

Une fois fait, `font-family: "Anton"` ou `var(--font-display)` (si la variable est définie) marchera dans tous les éléments.

---

## 8. CSS Variables — design system

Bricks 2.3 **ne génère PAS** automatiquement le `:root { --... }` à partir de l'option `bricks_css_variables` (que `set_css_variable` modifie). Pour que les variables soient **vraiment utilisables** dans les éléments :

### ✅ Méthode validée : injecter les variables via `set_custom_code`

```python
set_custom_code({
  "customCss": """
:root {
  --primary: #FD5B2C;
  --dark: #252322;
  --cream: #FAF7F0;
  --font-display: Anton, Impact, sans-serif;
  --font-body: Inter, system-ui, sans-serif;
  --radius-pill: 999px;
  --space-md: 32px;
  --overlay-dark: rgba(0,0,0,0.45);
}
"""
})
```

Ensuite, dans les éléments : `{color: {raw: "var(--primary)"}}`, `font-family: "var(--font-display)"`, etc.

### Pourquoi pas `set_css_variable` directement ?

L'outil `set_css_variable` met à jour l'option DB `bricks_css_variables` mais **Bricks 2.3 ne lit pas cette option** pour générer le CSS frontend. C'est probablement réservé à un mécanisme natif Bricks (Style Manager 2.2) qu'on n'a pas encore identifié.

→ **Convention actuelle** : utiliser `set_custom_code({customCss})` pour TOUTES les variables CSS du site.

---

## 9. Global Classes — réutilisabilité

Même problème que les CSS variables : `create_global_class` enregistre dans `bricks_global_classes` mais **Bricks ne génère PAS le CSS** des classes pour le frontend.

### ✅ Méthode validée : générer le CSS des classes via `set_custom_code`

```python
set_custom_code({
  "customCss": """
:root { --primary: #FD5B2C; ... }

.btn-primary {
  background-color: var(--primary);
  color: var(--white);
  padding: 16px 28px;
  border-radius: var(--radius-pill);
  font-family: var(--font-body);
}
.btn-primary:hover { transform: translateY(-1px); }

.btn-dark { ... }
"""
})
```

Ensuite, sur un élément : `{_cssClasses: "btn-primary"}` — la classe HTML est ajoutée et le CSS s'applique.

⚠️ **Attention à la spécificité** : Bricks génère du CSS par ID (`#brxe-btn1 { background: ... }`) avec spécificité 100. Une classe `.btn-primary` a spécificité 10 → le hardcodé ID GAGNE. Pour que la classe override, soit :
- Ne pas mettre de settings hardcodés dans l'élément (juste `_cssClasses`)
- OU utiliser `!important` dans la classe (moche)
- OU utiliser un sélecteur plus spécifique (`.btn-primary { ... }` reste spécifité 10, donc augmenter via `body .btn-primary`)

---

## 10. Theme Styles — pas validé

Comme les classes globales et variables, `create_theme_style` enregistre dans `bricks_theme_styles` mais **Bricks 2.3 ne génère PAS le CSS** des theme styles pour le frontend.

→ **À investiguer** : trouver le vrai mécanisme natif Bricks 2.3 (probablement via le panneau Theme Styles de l'UI builder qui stocke peut-être ailleurs).

---

## 11. SVG inline — utiliser un data URI dans `image`

Pour insérer un SVG inline dans une page, **NE PAS** utiliser un élément `code` (Bricks 1.9.7+ désactive l'exécution du code par défaut → SVG est échappé en texte). Utiliser un élément `image` avec un **data URI** :

```json
{
  "name": "image",
  "settings": {
    "image": {
      "url": "data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22...",
      "external": true,
      "filename": "logo.svg"
    },
    "_width": "85px"
  }
}
```

**Encoder le SVG en URL-encoded** : `urllib.parse.quote(svg, safe='')` ou équivalent. Garde le SVG en une seule ligne, escape les `"` et `<>`.

Validé en production : un SVG complet de 1871 chars (logo Quoti) intégré sans souci.

---

## 12. update_element — merge récursif (pas replace)

`update_element` **fusionne** récursivement les `newSettings` avec les settings existants — il ne **remplace** PAS l'élément.

### Conséquence
Si un élément a `_background.color.hex: "#FD5B2C"` et qu'on appelle :
```python
update_element({elementId: "btn1", newSettings: {_cssClasses: "btn-primary"}})
```
L'élément aura **les deux** : `_background.color.hex: "#FD5B2C"` ET `_cssClasses: "btn-primary"`.

Du coup le `#brxe-btn1 { background: #FD5B2C }` (spécificité ID = 100) override la classe `.btn-primary { background: var(--primary) }` (spécificité = 10).

### Solutions
- **Pour purger un setting** : utiliser `update_page_json` complet (récupérer le JSON, modifier, renvoyer)
- **TODO MCP** : ajouter un endpoint `replace_element_settings` qui REMPLACE complètement les settings

### Le param `label` (v3.6.2+)
`update_element` accepte aussi un param `label` (au niveau racine, pas dans settings) qui **renomme l'élément** dans la structure du builder Bricks. Très utile pour la lisibilité.
```python
update_element({elementId: "secher", label: "Hero Section"})
```

---

## 13. Code Execution Bricks 1.9.7+

L'exécution du contenu des éléments `code` (HTML/JS/SVG inline) est **désactivée par défaut**.

### Activer
```python
set_code_execution({enabled: true, roles: ["administrator"]})
```

### Limite
Même activé, Bricks exige des **code signatures** valides — chaque `code` element doit être édité+sauvé par un user autorisé via le builder pour fonctionner. Un `code` créé via update_page_json n'aura pas de signature → ne s'exécutera pas.

### Workaround recommandé
Éviter le `code` element. Utiliser :
- `image` natif avec data URI SVG
- `_background.videoUrl` natif sur la section pour une vidéo
- `set_custom_code` pour CSS/JS global

---

## 14. Récap rapide — workflow recommandé pour un nouveau site

```python
# 1. Cartographier l'état initial
list_bricks_options()      # voir les options DB existantes
list_color_palette()       # palette existante
list_custom_fonts()        # fonts dispo
list_global_classes()      # classes existantes
get_custom_code()          # CSS/scripts global

# 2. Setup design system (méthode validée 2026-04)
set_custom_code({
  customCss: """
:root { --primary: ...; --font-display: ...; ... }
.btn-primary { ... }
""",
  customScriptsHeader: "<link href='https://fonts.googleapis.com/...' rel='stylesheet'>"
})

# 3. Créer la palette pour mémoire (utile dans le builder même si pas exploitée frontend)
add_color_to_palette({name: "Quoti Orange", hex: "#FD5B2C"})

# 4. Créer les pages avec methods natives
create_page({title: "Accueil", status: "publish", setAsHomepage: true})

# 5. Construire la page avec batch_add ou update_page_json en utilisant :
#    - couleurs : {raw: "var(--primary)"} ou {hex: "#..."}
#    - fonts : "var(--font-display)" dans font-family
#    - classes : _cssClasses: "btn-primary"
#    - vidéo bg : _background.videoUrl
#    - SVG : image element avec data URI
#    - line-height : string simple "1.5"
#    - border-radius : _border.radius nested

# 6. Renommer les éléments pour lisibilité dans le builder
update_element({elementId: "secher", label: "Hero Section"})
```

---

*Document créé le 2026-04-29 lors de la session de production sur JT Carrelage. Validé sur Bricks 2.3.2.*
