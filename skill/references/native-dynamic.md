# Bricks Dynamic, Queries, Templates, Components

But : utiliser les surfaces dynamiques Bricks natives quand la page dépend de WordPress, CPT, ACF, Query Loop, templates ou composants.

## Dynamic Data

Utiliser les tags dynamiques Bricks/WordPress au lieu de texte hardcodé dans les templates, archives, CPT, WooCommerce et query loops.

Exemples courants :

```text
{post_title}
{post_excerpt}
{post_content}
{post_date}
{post_url}
{featured_image}
{site_title}
{acf_field_name}
```

Un tag dynamique peut rendre vide hors contexte. Vérifier sur le template ou la page réelle.

## Query Loops

Utiliser une query loop native pour :

- grilles d'articles/CPT ;
- archives ;
- listings filtrables ;
- produits Woo ;
- témoignages/projets depuis CPT.

Workflow :

1. Identifier post type, taxonomies, champs et source de données.
2. Créer ou réutiliser un wrapper query loop natif.
3. Construire l'item une seule fois avec éléments natifs.
4. Vérifier le rendu avec plusieurs items.

## Query Et Filtres Natifs

| Besoin | Élément |
|---|---|
| résumé de résultats | `query-results-summary` |
| filtres actifs | `filter-active-filters` |
| checkbox | `filter-checkbox` |
| datepicker | `filter-datepicker` |
| radio | `filter-radio` |
| range | `filter-range` |
| recherche | `filter-search` |
| select | `filter-select` |
| submit/reset | `filter-submit` |

Ces éléments doivent être reliés à une query réelle.

Règles :

- activer `Bricks > Settings > Query filters` avant de créer des filtres ;
- chaque filtre doit cibler une `Target Query` Bricks existante ;
- ne pas placer un filtre dans un component Bricks sauf si la racine du component est la query loop ;
- appeler `get_filter_schema()` avant de configurer un filtre natif ;
- si `get_element_schema(filter-*)` revient du fallback officiel et ne montre que les styles, créer un filtre minimal en UI Bricks puis lire l'élément avec `get_element` pour récupérer les clés exactes de liaison.

## Image Cover Dans Query Loop

Ne pas faire un cover avec `_position:absolute` sur l'image dynamique d'une Query Loop sans test : les clones peuvent se dimensionner sur toute la section.

Pattern sûr :

- wrapper carte/image : `_display: "block"`, `_aspectRatio: "4/3"`, `_overflow: "hidden"` ;
- image dynamique : `image.useDynamicData: "{featured_image}"`, `_display: "block"`, `_width: "100%"`, `_height: "100%"` ;
- pour le recadrage, préférer un style scoped à une classe image/wrapper (`object-fit: cover`) si aucun setting natif fiable n'est exposé par `get_element_schema("image")` ;
- toujours valider avec `verify_element` sur desktop et mobile, car les erreurs Query Loop se voient seulement sur les clones frontend.

## WordPress Elements

| Besoin | Élément |
|---|---|
| grille/liste posts | `posts` |
| pagination | `pagination` |
| recherche | `search` |
| shortcode | `shortcode` |
| sidebar | `sidebar` |
| widget WordPress | `wordpress` |
| menu WP | `nav-menu` |

## Single / Post Elements

| Besoin | Élément |
|---|---|
| auteur | `post-author` |
| commentaires | `post-comments` |
| contenu | `post-content` |
| extrait | `post-excerpt` |
| meta date/auteur/etc. | `post-meta` |
| navigation précédent/suivant | `post-navigation` |
| barre lecture | `post-reading-progress-bar` |
| temps de lecture | `post-reading-time` |
| partage social | `post-sharing` |
| taxonomies | `post-taxonomy` |
| titre | `post-title` |
| table des matières | `post-toc` |
| posts liés | `related-posts` |

## Templates

Templates fréquents :

- header ;
- footer ;
- section ;
- popup ;
- single ;
- archive ;
- search ;
- 404 ;
- WooCommerce.

Ne pas confondre :

- template conditions : où le template s'applique ;
- `_conditions` : si un élément s'affiche.

## Popups

Un popup est un template avec :

- contenu Bricks du template ;
- settings popup/template ;
- ouverture/fermeture via interactions ou déclencheur natif.

Ne pas confondre `_interactions` avec les settings propres du template popup.

## Components Bricks 2.x

Un composant contient une définition globale et des instances. Les instances peuvent contenir :

```text
cid
instanceId
parentComponent
parentInstanceId
rootComponent
rootInstanceId
slotChildren
```

Règles :

- ne jamais supprimer ces clés ;
- modifier seulement les settings nécessaires ;
- remplir les slots prévus plutôt que casser la structure ;
- garder `cid` et `instanceId` intacts.

## Conditions Et Interactions

| Besoin | Setting |
|---|---|
| afficher/masquer un élément | `_conditions` |
| interaction click/scroll/view | `_interactions` |
| cacher builder | `_hideElementBuilder` |
| cacher frontend | `_hideElementFrontend` |
| attributs data/aria | `_attributes` |

Pour un comportement avancé, privilégier l'UI/schema Bricks plutôt qu'un JS custom si Bricks le couvre nativement.
