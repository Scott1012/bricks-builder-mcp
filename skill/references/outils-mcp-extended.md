# 🛠 Outils MCP — Extension v3.4 / v3.5 / v3.6

> Ce document complète `outils-mcp.md` (qui couvre les 11 outils originaux). Ici sont listés les **~40 outils ajoutés** entre v3.4 et v3.6.

État au 2026-04-29 : plugin WP **v3.6.2** + MCP server **v3.5.1**.

---

## 🟦 Outils v3.4 — Gestion des pages

### `create_page`
Crée une nouvelle page WordPress et l'active immédiatement en mode Bricks. Init avec une section vide.
```js
create_page({title: "Accueil", slug: "accueil", status: "publish", setAsHomepage: true})
```

### `delete_page`
Supprime une page. Par défaut → corbeille (récupérable). `force: true` = définitive.
Refuse de supprimer la page d'accueil active.
```js
delete_page({pageId: 32, force: false})
```

### `update_page_meta`
Met à jour titre / slug / statut / parent d'une page (sans toucher au contenu Bricks).
```js
update_page_meta({pageId: 32, title: "Nouveau titre", slug: "nouveau-slug", status: "publish", parentId: 0})
```

### `duplicate_page`
Duplique une page **avec son contenu Bricks complet**. La copie est en draft par défaut.
```js
duplicate_page({sourcePageId: 32, newTitle: "Copie de Accueil", status: "draft"})
```

### `set_homepage`
Définit une page comme page d'accueil du site (la page doit être publiée).
```js
set_homepage({pageId: 32})
set_homepage({reset: true})  // remet sur les derniers articles
```

---

## 🟦 Outils v3.5 — Health, médias, navigation, styles globaux

### `health_check`
Test connexion + versions (plugin / WP / PHP / Bricks / multisite).
```js
health_check()
// → { success, plugin_version, wp_version, php_version, bricks_active, bricks_version, site_name, site_url, is_multisite, timestamp }
```

### `list_all_pages`
Toutes les pages WP (pas seulement Bricks). Inclut le flag `has_bricks` pour chaque.
```js
list_all_pages()
```

### `upload_media`
Télécharge une image depuis URL → médiathèque WP. Retourne l'URL WP utilisable dans Bricks.
```js
upload_media({sourceUrl: "https://example.com/photo.jpg", title: "Photo client", alt: "Description"})
```
**Note v3.6.1** : si `title` fourni, il est utilisé comme nom de fichier (slugifié) au lieu du basename de l'URL.

### `list_media`
Liste paginée des médias (filtrable par recherche).
```js
list_media({page: 1, perPage: 20, search: "logo"})
```

### `list_menus` + `add_menu_item`
Lister/ajouter aux menus de navigation WP.
```js
list_menus()
// → [{id, name, slug, item_count, locations}]

add_menu_item({menuId: 2, pageId: 32, label: "Accueil"})
add_menu_item({menuId: 2, customUrl: "https://...", label: "Lien externe"})
```

### `get_global_styles` / `update_global_styles`
Récupère/met à jour les **settings globaux** Bricks (postTypes, builderMode, etc.). Pas le design system.
```js
get_global_styles()
update_global_styles({settings: {builderMode: "dark"}})
```

### `list_color_palette` / `add_color_to_palette`
Palette de couleurs Bricks globale (pour usage dans le builder).
```js
add_color_to_palette({name: "Quoti Orange", hex: "#FD5B2C"})
list_color_palette()
```

---

## 🟦 Outils v3.6 — Phase A : Inspection + Custom Code + Fonts + Code Execution

### `list_bricks_options`
**Outil debug essentiel** : dump TOUTES les options WP `bricks_*`. Sert à cartographier ce qui existe en base avant de modifier.
```js
list_bricks_options()
// → [{name, type, size, preview}, ...]
```

### `get_bricks_option`
Récupère une option spécifique en intégralité.
```js
get_bricks_option({name: "bricks_global_settings"})
```

### `get_custom_code` / `set_custom_code`
**LE plus important pour le design system.** Modifie le code custom global Bricks (4 emplacements).
```js
set_custom_code({
  customCss: ":root { --primary: #FD5B2C; } .btn-primary { ... }",
  customScriptsHeader: "<link href='https://fonts.googleapis.com/...' rel='stylesheet'>",
  customScriptsBodyHeader: "<!-- juste après <body> -->",
  customScriptsBodyFooter: "<!-- juste avant </body> -->"
})
```
**Cas d'usage critique** : c'est là qu'on charge les Google Fonts (cf `bricks-2.3-formats.md` section 7).

### `get_code_execution_status` / `set_code_execution`
Bricks 1.9.7+ désactive l'exécution des `code` elements par défaut. À activer pour SVG/scripts inline.
```js
set_code_execution({enabled: true, roles: ["administrator"]})
```
⚠️ Bricks exige aussi des **code signatures** — un `code` créé via API n'aura pas de signature. Préférer les éléments natifs (image avec data URI, video bg, etc.).

### `list_custom_fonts` / `register_custom_font` / `delete_custom_font`
Custom fonts Bricks (CPT `bricks_fonts`).
```js
register_custom_font({
  name: "MaFont",
  fontFamily: "MaFont",
  faces: [{weight: 400, style: "normal", url: "https://.../font.woff2"}]
})
```
⚠️ **Limitation connue** (cf `bricks-2.3-formats.md`) : Bricks 2.3 **ne génère PAS** automatiquement le `@font-face` frontend depuis `bricks_fonts`. Pour les Google Fonts, utiliser `set_custom_code({customScriptsHeader})` avec un `<link>`.

### `register_google_font_locally`
Télécharge un Google Font (parse le CSS Google) et l'enregistre dans le Font Manager.
```js
register_google_font_locally({name: "Anton", weights: [400]})
register_google_font_locally({name: "Inter", weights: [400, 500, 600, 700]})
```
⚠️ Même limitation que ci-dessus — utile pour avoir la font dans le UI Bricks, mais le `<link>` Google reste nécessaire pour le frontend.

---

## 🟦 Outils v3.6 — Phase B : Global Classes + Theme Styles + Page Code

### Global Classes — `list_global_classes`, `create_global_class`, `update_global_class`, `delete_global_class`
```js
create_global_class({
  name: "btn-primary",
  settings: {_background: {color: {raw: "var(--primary)"}}, _padding: {...}}
})
```
⚠️ **Limitation** : Bricks 2.3 **ne génère PAS le CSS** des classes depuis `bricks_global_classes`. Pour qu'une classe marche frontend, dupliquer le CSS via `set_custom_code({customCss: ".btn-primary { ... }"})`.

### Theme Styles — `list_theme_styles`, `create_theme_style`, `update_theme_style`, `delete_theme_style`
```js
create_theme_style({
  name: "Default Quoti",
  settings: {typography: {...}, headings: {...}}
})
```
⚠️ **Même limitation** que les global classes — pas exploité par Bricks frontend. Dupliquer dans `customCss`.

### Page-specific Custom Code — `get_page_custom_code` / `set_page_custom_code`
CSS/JS spécifique à une page (Page Settings → Custom Code dans le builder).
```js
set_page_custom_code({pageId: 32, customCss: ".hero { ... }", customScripts: "..."})
```

---

## 🟦 Outils v3.6 — Phase C : Style Manager 2.2 + Components

### Typography Scales — `list_typography_scales` / `set_typography_scale`
```js
set_typography_scale({
  name: "Default",
  values: {h1: "64px", h2: "48px", h3: "32px", body: "16px"}
})
```

### Spacing Scales — `list_spacing_scales` / `set_spacing_scale`
```js
set_spacing_scale({
  name: "Default",
  values: {xs: "8px", sm: "16px", md: "24px", lg: "48px"}
})
```

### CSS Variables — `list_css_variables` / `set_css_variable`
Stocke dans `bricks_css_variables` (pas exploité par Bricks frontend, idem global classes).
```js
set_css_variable({name: "primary", value: "#FD5B2C"})
```
**Pour vraiment utiliser les variables CSS**, voir Section 8 de `bricks-2.3-formats.md` — utiliser `set_custom_code({customCss: ":root { --primary: #FD5B2C; }"})`.

### Components — `list_components`
Liste les components Bricks 2.x (CPT `bricks_template` avec type=component).
```js
list_components()
```

---

## 🟦 v3.6.2 — `update_element` étendu

`update_element` accepte maintenant un param **`label`** au niveau racine pour renommer un élément dans la structure du builder Bricks.
```js
update_element({pageId: 32, elementId: "secher", label: "Hero Section"})
update_element({pageId: 32, elementId: "navinr", label: "Header Pill"})
```
Avec `label`, le builder Bricks affiche un nom parlant au lieu de "Div / Div / Div / Div...". **À utiliser systématiquement** pour rendre la structure lisible.

`newSettings` est aussi devenu **optionnel** si on fournit juste un label.

---

## 📋 Récap par catégorie — quel outil pour quoi ?

### Workflow début de site
1. `health_check` → confirmer connexion
2. `list_bricks_options` → état initial
3. `set_custom_code({customCss, customScriptsHeader})` → poser les fondations design system
4. `add_color_to_palette` → palette pour le builder
5. `create_page({setAsHomepage: true})` → créer la home

### Workflow construction de page
1. `update_page_json` ou `batch_add` → poser la structure
2. `update_element` (avec `label`) → renommer chaque bloc pour la lisibilité
3. `update_element` → tweaker les détails
4. `upload_media` → ajouter les images du client
5. `list_menus` + `add_menu_item` → intégrer les pages au menu

### Workflow design system propre
1. `set_custom_code({customCss: ":root { --primary: ...; } .btn-primary { ... }"})` → variables + classes
2. `set_custom_code({customScriptsHeader: "<link Google Fonts>"})` → polices web
3. Sur les éléments : `_cssClasses: "btn-primary"` ou `{color: {raw: "var(--primary)"}}` ou `font-family: "var(--font-display)"`

### Workflow gestion site multi-pages
- `list_all_pages` → inventaire
- `duplicate_page` → templates réutilisables
- `delete_page` → nettoyage corbeille
- `set_homepage` → bascule home
- `update_page_meta` → renommage / SEO

---

*Doc créée le 2026-04-29 — mise à jour à chaque release majeure de plugin/MCP.*
