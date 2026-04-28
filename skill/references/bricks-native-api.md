# 🧱 Bricks Native API — Cartographie complète

> Comment Bricks Builder fonctionne **nativement** : pour chaque domaine (fonts, code, classes, theme, etc.), où c'est dans l'admin WP, quelle option DB, quel outil MCP, et quelles limites.

**Règle d'or** : avant de coder un workaround ou d'embarquer du HTML inline, **vérifier qu'il n'y a pas une fonctionnalité native** qui fait le job.

---

## 1. Custom Code Global (Header / Body)

**Localisation admin** : WP Admin → Bricks → Settings → Custom Code

**Option DB** : `bricks_global_settings`, sous-clés :

- `customCss` — CSS injecté dans `<head>`
- `customScriptsHeader` — HTML/scripts dans `<head>` ⭐ **idéal pour Google Fonts `<link>`**
- `customScriptsBodyHeader` — juste après l'ouverture de `<body>`
- `customScriptsBodyFooter` — juste avant `</body>`

**Outils MCP** : `get_custom_code`, `set_custom_code`

**Cas d'usage typiques** :

- **Charger Google Fonts** : `set_custom_code({customScriptsHeader: '<link href="https://fonts.googleapis.com/css2?family=Anton&display=swap" rel="stylesheet">'})`
- **CSS global pour normalize/reset** : `set_custom_code({customCss: 'html { scroll-behavior: smooth; }'})`
- **Scripts analytics** : `set_custom_code({customScriptsBodyFooter: '<script src="..."></script>'})`

**Limites** : pas de minification, pas de versioning, pas de purge auto. À utiliser pour ce qui est **global et stable**.

---

## 2. Custom Fonts / Font Manager

**Localisation admin** : WP Admin → Bricks → Custom Fonts (CPT) ou Builder → Settings → Font Manager (Bricks 2.0+)

**Stockage DB** : Custom Post Type `bricks_fonts` + post meta :

- `font_family` (string) — la `font-family` CSS
- `bricks_font_face_rules` (array) — variantes : `[{weight: 400, style: 'normal', url: '...'}]`

**Outils MCP** : `list_custom_fonts`, `register_custom_font`, `delete_custom_font`, `register_google_font_locally`

**Méthode native pour utiliser une Google Font (recommandé)** :

```
register_google_font_locally({name: "Anton", weights: [400, 700, 900]})
```

→ Le MCP fetche le CSS Google, parse les URLs `.woff2`, crée le post `bricks_fonts` avec les bonnes variantes. Une fois enregistrée, la font apparaît dans tous les selects font-family du builder.

**Méthode si on a déjà les .woff2 (uploadés ailleurs)** :

```
register_custom_font({
  name: "MaCustomFont",
  fontFamily: "MaCustomFont",
  faces: [
    {weight: 400, style: "normal", url: "https://.../mafont-regular.woff2"},
    {weight: 700, style: "normal", url: "https://.../mafont-bold.woff2"}
  ]
})
```

**Limites** :

- Les fichiers ne sont pas téléchargés localement par défaut (les URLs `register_google_font_locally` pointent encore vers `fonts.gstatic.com`). Pour RGPD strict (hébergement local), il faut télécharger les `.woff2` manuellement et les uploader via `upload_media`.
- Pas de support direct des variable fonts (à confirmer selon version Bricks).

---

## 3. Code Execution (Code element security)

**Important** : depuis Bricks 1.9.7, **l'exécution des code elements est désactivée par défaut**. Sans activation, un `<script>` ou `<svg>` inline dans un `code` element est échappé en texte (donc invisible).

**Localisation admin** : WP Admin → Bricks → Settings → Custom Code → Enable code execution + cocher les rôles autorisés.

**Option DB** : `bricks_global_settings.executeCodeAllowed` + capability WP `bricks_execute_code` par rôle.

**Outils MCP** : `get_code_execution_status`, `set_code_execution`

**Activer pour les admins** :

```
set_code_execution({enabled: true, roles: ["administrator"]})
```

**Important** : Bricks exige aussi des **code signatures** valides — chaque `code` element doit être édité+sauvé par un user avec full builder access pour fonctionner. Voir Bricks → Code Review.

**Conséquence pour Claude/MCP** :

- Si on crée un `code` element via `add_element` ou `update_page_json`, il N'aura PAS de signature valide. → Il faut l'éditer manuellement dans le builder Bricks pour le signer.
- Workaround : pour du SVG inline, utiliser un `image` element avec `data:image/svg+xml,...` (data URI), ne nécessite pas de code execution.
- Workaround pour `<video>` : utiliser `_background.videoUrl` sur la section (élément natif Bricks).

---

## 4. Global Classes

**Localisation admin** : Builder → Global Class Manager (icône `{ }` dans le panel)

**Option DB** : `bricks_global_classes` (array) :

```
[
  {id: "abc123", name: "btn-primary", settings: {_background: {color: {hex: "#FD5B2C"}}, _padding: {...}}},
  ...
]
```

**Outils MCP** : `list_global_classes`, `create_global_class`, `update_global_class`, `delete_global_class`

**Usage** :

1. Créer la classe : `create_global_class({name: "btn-primary", settings: {...}})`
2. L'appliquer à un élément : `update_element({elementId: "...", newSettings: {_cssClasses: "btn-primary"}})`
3. Modifier la classe propage à tous les éléments l'utilisant

**Avantages** :

- DRY (Don't Repeat Yourself) : un seul endroit pour les boutons primary, les cards, etc.
- Modifier 1 classe = changement partout

---

## 5. Theme Styles

**Localisation admin** : Builder → Settings (gear) → Theme Styles → Create

**Option DB** : `bricks_theme_styles` (object par id) :

```
{
  "uniqueId123": {
    id: "uniqueId123",
    name: "Default",
    settings: {typography: {...}, headings: {...}, buttons: {...}, ...},
    conditions: [/* conditions d'application */]
  }
}
```

**Outils MCP** : `list_theme_styles`, `create_theme_style`, `update_theme_style`, `delete_theme_style`

**Conditions d'application** : on peut rendre un theme style actif uniquement sur certaines pages, post types, archives, etc.

**Différence avec Global Classes** :

- Theme Styles = **defaults** pour les éléments HTML (h1, p, button, etc.) + Bricks elements
- Global Classes = **classes utilitaires** appliquées explicitement par `_cssClasses`

---

## 6. Color Palettes

**Localisation admin** : Builder → Color Palettes (icône palette)

**Option DB** : `bricks_color_palette` (array) :

```
[
  {id: "abc123", name: "Primary", raw: "#FD5B2C"},
  {id: "def456", name: "Dark", raw: "#252322"}
]
```

**Outils MCP** : `list_color_palette`, `add_color_to_palette`

**Usage** : référencer la couleur via son id dans n'importe quel setting Bricks au lieu de répéter le hex partout.

---

## 7. Page-specific Custom Code

**Localisation** : Builder → Page Settings (icône feuille) → Custom Code

**Stockage** : post meta `_bricks_page_settings` :

```
{customCss: "...", customScripts: "..."}
```

**Outils MCP** : `get_page_custom_code`, `set_page_custom_code`

**Usage** : CSS/JS qui ne doit s'appliquer qu'à une page spécifique (animations particulières, font cas isolé, override CSS local).

---

## 8. Style Manager 2.2 — Typography & Spacing scales, CSS Variables

**Localisation** : Builder → Style Manager (Bricks 2.2+)

**Options DB** :

- `bricks_typography_scales` — échelles typo réutilisables
- `bricks_spacing_scales` — échelles d'espacement
- `bricks_css_variables` — variables CSS globales (`--primary`, `--accent`...)

**Outils MCP** : `list_typography_scales`/`set_typography_scale`, `list_spacing_scales`/`set_spacing_scale`, `list_css_variables`/`set_css_variable`

**Typography scale** : `{h1: "64px", h2: "48px", h3: "32px", body: "16px", small: "14px"}`

**Spacing scale** : `{xs: "8px", sm: "16px", md: "24px", lg: "48px", xl: "96px"}`

**CSS variables** : utilisées partout via `var(--primary)`. Set via `set_css_variable({name: "primary", value: "#FD5B2C"})`.

---

## 9. Components (Bricks 2.x)

**Localisation** : Builder → Components

**Stockage** : CPT `bricks_template` avec post meta `_bricks_template_type = 'component'`.

**Outil MCP** : `list_components`

**Usage** : composants réutilisables (cards, sections, navigations) avec instances liées au master.

---

## 10. Inspection & Debug

**Outils MCP** : `list_bricks_options`, `get_bricks_option`, `health_check`

**Usage typique au début d'un projet** :

```
health_check()                 → versions, multisite, plugin Bricks actif
list_bricks_options()          → cartographie de ce qui est en base
list_color_palette()           → palette existante
list_global_classes()          → classes existantes
list_theme_styles()            → theme styles configurés
list_custom_fonts()            → fonts dispo
list_menus()                   → menus de nav existants
```

→ Donne une photo complète de l'état initial du site.

---

## Arbre de décision : où mettre quoi ?

```
J'ai besoin de :
├─ Charger une Google Font
│   └→ register_google_font_locally  (recommandé, méthode native Bricks)
│   ou set_custom_code({customScriptsHeader: '<link ...>'})  (si on veut le CDN Google)
│
├─ Définir des couleurs réutilisables
│   └→ add_color_to_palette  (pour utilisation au cas par cas dans les éléments)
│   ou set_css_variable      (pour utilisation dans CSS custom et settings)
│
├─ Définir un style de bouton réutilisable
│   └→ create_global_class("btn-primary", {settings...})
│
├─ Définir la typo par défaut du site (h1, h2, body)
│   └→ create_theme_style avec settings.typography
│
├─ CSS qui s'applique à tout le site
│   └→ set_custom_code({customCss: "..."})
│
├─ CSS qui s'applique à une seule page
│   └→ set_page_custom_code({pageId, customCss})
│
├─ Insérer un SVG dans une page
│   ├→ image element avec data:image/svg+xml,... (PRÉFÉRÉ — pas de code exec requis)
│   └→ ou code element + activation code execution + signature manuelle (plus rare)
│
└─ Insérer une vidéo de fond
    └→ section avec _background.videoUrl  (natif Bricks, pas de code exec)
```

---

## Anti-patterns à éviter

| ❌ Mauvaise approche | ✅ Bonne approche native |
|---|---|
| `<link>` Google Font dans un `code` element | `register_google_font_locally` ou `set_custom_code({customScriptsHeader})` |
| `<svg>` inline dans `code` element | `image` element avec data URI SVG |
| `<video>` HTML dans `code` element | `_background.videoUrl` sur la section |
| `@import` dans `_cssCustom` d'un élément | `set_custom_code({customCss})` au niveau global |
| Répéter `_background: {color: {hex: "#FD5B2C"}}` partout | `add_color_to_palette` puis utiliser l'id |
| Répéter les settings d'un bouton sur chaque button | `create_global_class("btn-primary")` puis appliquer |
| Définir typo individuellement sur chaque h1 | `create_theme_style` avec settings.headings |

---

*Référence créée le 2026-04-29 lors de la v3.6 du plugin / v3.5 du MCP. Mise à jour au fur et à mesure des découvertes.*
