# Bricks Native API

But : savoir quelle surface native Bricks utiliser avant d'écrire du CSS/JS custom.

## Surfaces natives

| Besoin | Surface Bricks | MCP |
|---|---|---|
| Structure de page | éléments Bricks JSON | `batch_add`, `update_element`, `update_page_json` |
| Style local | settings d'élément | `update_element` |
| Schema d'élément | registre runtime Bricks | `get_element_schema` |
| Style réutilisable | global classes | `list_global_classes`, `create_global_class`, `update_global_class` |
| Couleurs réutilisables | palette / variables | `list_color_palette`, `add_color_to_palette`, `list_css_variables`, `set_css_variable` |
| Defaults globaux | theme styles | `list_theme_styles`, `create_theme_style`, `update_theme_style` |
| Fonts | Font Manager / header link | `list_custom_fonts`, `register_custom_font`, `register_google_font_locally`, `set_custom_code` |
| CSS global | custom code global | `get_custom_code`, `set_custom_code` |
| CSS page | custom code page | `get_page_custom_code`, `set_page_custom_code` |
| Images/assets | media library | `upload_local_file`, `list_media` |
| Contenu dynamique | WordPress posts/CPT/taxonomies/meta | `list_post_types`, `create_post`, `update_post`, `list_posts` |

## Modèle de données Bricks

Le contenu Bricks est un tableau plat d'éléments, pas un arbre imbriqué :

```json
{
  "id": "abc123",
  "name": "section",
  "parent": 0,
  "children": ["def456"],
  "settings": {},
  "label": "Hero"
}
```

Règles :

- `parent` pointe vers l'ID parent ou `0` pour une section racine.
- `children` contient les IDs directs, dans l'ordre.
- Pour déplacer/supprimer/reparent, maintenir les deux côtés.
- Ne jamais supprimer des clés inconnues sur composants/templates : Bricks peut en dépendre.

## Éléments Bricks à privilégier

| Source HTML/CSS | Élément Bricks recommandé |
|---|---|
| `<section>` pleine largeur | `section` |
| wrapper `.container` | `container`, `block` ou `div` selon le site |
| flex/grid wrapper | `block`/`div` avec `_display` |
| `h1`-`h6` | `heading` |
| paragraphe simple | `text-basic` |
| contenu riche | `text` |
| lien CTA | `button` |
| image | `image` |
| icône simple | `icon` ou SVG/media si nécessaire |
| liste | `list` ou `social-icons` |
| navigation | `nav-menu` si menu WP, sinon blocks/liens natifs |
| formulaire | `form` |
| FAQ | `accordion` |
| onglets | `tabs` |
| carrousel | `slider`/`carousel` natif |
| boucle dynamique | query loop Bricks |
| posts/archive | `posts`, `post-*`, `pagination`, `search`, selon contexte |
| breadcrumbs/rating/logo | éléments natifs dédiés si disponibles |
| shortcode/HTML contrôlé | `shortcode`, `html` ou `code` selon besoin |
| popup/modal | feature Bricks native si disponible, sinon CSS/JS page |

Pour un élément complexe, appeler `get_element_schema({element: "nom-bricks"})`. Si le format reste ambigu, créer/lire un exemple natif via l'UI puis `get_element` avant modification.

## Design system natif

Ordre recommandé :

1. Palette / CSS variables pour les tokens.
2. Theme styles pour defaults globaux (`body`, headings, buttons).
3. Global classes pour composants réutilisables (`card`, `btn-primary`, `section-dark`).
4. Framework/classes existants si le site utilise ACSS ou un système de classes.
5. Settings locaux pour variations ponctuelles.
6. Custom CSS seulement pour états/pseudos/effets que Bricks ne pilote pas proprement.

## Global classes

Format natif :

```json
{
  "_cssGlobalClasses": ["classId"]
}
```

`_cssClasses: "class-name"` peut appliquer du CSS frontend, mais l'ID dans `_cssGlobalClasses` garde mieux la liaison Bricks/UI.

## Custom code

Utiliser `set_custom_code` pour :

- variables CSS globales ;
- liens de fonts dans le header si nécessaire ;
- petits utilitaires partagés ;
- scripts globaux rares.

Utiliser `set_page_custom_code` pour :

- animation ou override local ;
- CSS temporaire pendant migration ;
- pseudo-classes/états non exposés par les settings Bricks.

Éviter d'y mettre le layout principal si Bricks peut le faire nativement.

## Composants, slots, templates

Sur Bricks 2.x, certaines structures ont des métadonnées critiques. Si elles existent dans le JSON, les préserver lors des mutations :

- `cid`
- `instanceId`
- `parentComponent`
- `parentInstanceId`
- `rootComponent`
- `rootInstanceId`
- `slotChildren`
- `_hideElementFrontend`

Les templates, popups et conditions ne sont pas de simples sections :

- template/header/footer/popup ont leurs propres settings et conditions ;
- popup settings et interactions d'ouverture sont deux systèmes séparés ;
- les éléments dynamiques/query/woocommerce doivent être clonés ou créés avec un outil dédié plutôt qu'inventés.

## Fonts

Le Font Manager peut rendre la font disponible dans l'UI, mais le frontend doit réellement charger les fichiers. Vérifier le `font-family` computed et le rendu. Pour Google Fonts, un `<link>` dans le header global est souvent le chemin le plus fiable.

## Médias

Toujours préférer la media library :

- uploader/optimiser ;
- renseigner alt/title ;
- utiliser l'élément `image` ou background natif ;
- vérifier `media_health`.

## Règle

Si Bricks sait faire nativement une fonction mais que le MCP ne l'expose pas clairement, documenter le manque et éviter un gros workaround définitif.
