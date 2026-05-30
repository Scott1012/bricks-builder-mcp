# Bricks MCP Tools

But : choisir le bon outil MCP sans charger toute la page ni inventer un workflow.

## Démarrage

```js
health_check()
check_skill_version({localVersion})
get_global_styles()
get_element_schema()
get_filter_schema()
list_color_palette()
list_global_classes()
list_theme_styles()
list_css_variables()
list_custom_fonts()
list_components()
```

`get_element_schema()` sans argument retourne le catalogue compact des éléments Bricks disponibles. Avec `element`, il retourne les contrôles runtime de cet élément. Si Bricks ne liste pas un élément natif dans le registre local, l'outil peut utiliser le schema officiel Bricks Academy en fallback ; vérifier ensuite avec `get_element` sur un exemple créé en UI si la liaison dynamique est sensible.

`get_filter_schema()` est l'outil préféré pour les Query Filters Bricks. Il donne les clés de liaison utiles (`filterQueryId`, `filterSource`, `filterTaxonomy`, etc.), les points de départ par type de filtre, et dit si les Query Filters sont activés côté Bricks. Démarrer par le plus petit payload possible, vérifier, puis ajouter les options avancées une par une.

## Pages

| Besoin | Outil |
|---|---|
| lister pages Bricks | `list_bricks_pages` |
| lister toutes les pages WP | `list_all_pages` |
| créer une page Bricks | `create_page` |
| supprimer une page | `delete_page` |
| titre/slug/status/parent | `update_page_meta` |
| dupliquer avec contenu Bricks | `duplicate_page` |
| définir accueil | `set_homepage` |

## Structure Bricks

| Besoin | Outil | Règle |
|---|---|---|
| vue légère | `get_page_structure` | défaut pour comprendre une page |
| recherche ciblée | `find_elements` | préférer à `get_page_json` |
| détail d'un élément | `get_element` | avant patch complexe |
| schema d'un élément | `get_element_schema` | avant d'inventer un format |
| modifier settings | `update_element` | merge récursif, ne déplace pas |
| ajouter un élément | `add_element` | synchronise `parent.children` si `parent` existe |
| ajouter une section | `batch_add` | synchronise `parent.children`, 5-10 éléments max puis vérifier |
| supprimer un élément | `delete_element` | nettoie parent/children |
| réordonner sections | `reorder_sections` | sections top-level |
| modifier parent/children | `get_page_json` + `update_page_json` | nécessaire pour reparent |
| analyser un JSON | `analyze_json_structure` | avant import massif |

Ne pas enchaîner deux `batch_add` significatifs sans `verify_element`.

## Styles Et Design System

| Besoin | Outil |
|---|---|
| settings globaux Bricks | `get_global_styles`, `update_global_styles` |
| palette | `list_color_palette`, `add_color_to_palette` |
| CSS variables | `list_css_variables`, `set_css_variable` |
| spacing scales | `list_spacing_scales`, `set_spacing_scale` |
| typography scales | `list_typography_scales`, `set_typography_scale` |
| global classes | `list_global_classes`, `create_global_class`, `update_global_class`, `delete_global_class` |
| theme styles | `list_theme_styles`, `create_theme_style`, `update_theme_style`, `delete_theme_style` |
| custom code global | `get_custom_code`, `set_custom_code` |
| custom code page | `get_page_custom_code`, `set_page_custom_code` |

Ordre recommandé : palette/variables -> theme styles -> global classes -> settings locaux -> custom CSS seulement si nécessaire.

## Médias Et Fonts

| Besoin | Outil | Notes |
|---|---|---|
| upload fichier local | `upload_local_file` | méthode préférée, optimise WebP |
| upload local batch | `upload_local_files_batch` | chunks raisonnables |
| upload URL/data URI | `upload_media`, `upload_media_batch` | éviter data URI si fichier local disponible |
| lister médias | `list_media` | retrouver IDs/URLs |
| fonts Bricks | `list_custom_fonts`, `register_custom_font`, `delete_custom_font` | Font Manager |
| Google Font locale | `register_google_font_locally` | peut ne pas suffire au frontend |
| charger Google Font frontend | `set_custom_code({customScriptsHeader})` | méthode fiable |
| code elements Bricks | `get_code_execution_status`, `set_code_execution` | après modification de code exécutable, prévenir que signature manuelle Bricks est requise |

Pour le JS spécifique page, utiliser `set_page_custom_code({customScriptsBodyFooter})`. `customScripts` existe seulement comme alias legacy vers body footer.

## Menus Et Contenu

| Besoin | Outil |
|---|---|
| menus WP | `list_menus`, `add_menu_item` |
| post types disponibles | `list_post_types` |
| créer/modifier posts ou CPT | `create_post`, `update_post` |
| lire/lister posts ou CPT | `get_post`, `list_posts` |
| supprimer posts ou CPT | `delete_post` |
| taxonomies | `create_taxonomy_term` |

Pour contenu dynamique alimentant des Query Loops Bricks, lire [native-content.md](native-content.md).

## Vérification

| Besoin | Outil |
|---|---|
| vérifier un bloc | `verify_element` |
| audit technique fullpage | `audit_page` |
| audit webdesign général | `audit_design_page` |

Lire [verification.md](verification.md) pour l'ordre exact.

## Maintenance MCP

| Besoin | Outil |
|---|---|
| signaler une feature Bricks non exposée | `report_missing_feature` |
| lire les gaps ouverts | `list_missing_features` |
| fermer un gap traité | `resolve_missing_feature` |
| debug options Bricks | `list_bricks_options`, `get_bricks_option` |

Reporter seulement si Bricks supporte la feature nativement et que le MCP ne l'expose pas correctement.
