# Bricks Native Content

But : créer ou gérer du contenu WordPress qui alimente Bricks : posts, pages, CPT, taxonomies, meta et champs ACF.

## Quand L'utiliser

Utiliser cette référence si une page Bricks affiche du contenu dynamique :

- réalisations/projets/chantiers ;
- témoignages/avis ;
- équipe, FAQ, événements ;
- articles/blog ;
- Query Loop Bricks alimentée par un post type ;
- champs ACF utilisés en dynamic data.

Ne pas la charger pour une simple conversion HTML/CSS statique.

## Outils

| Outil | Usage |
|---|---|
| `list_post_types` | découvrir post types, labels, taxonomies, supports |
| `create_post` | créer post/page/CPT avec contenu, meta, taxonomies, image |
| `update_post` | modifier un post/CPT existant |
| `delete_post` | corbeille ou suppression définitive |
| `get_post` | lire un post/CPT précis |
| `list_posts` | lister avec filtres |
| `create_taxonomy_term` | créer ou retrouver un terme |

## Workflow

```js
list_post_types()
```

Identifier :

- nom technique du post type ;
- taxonomies attachées ;
- support `thumbnail` si image mise en avant ;
- champs/meta nécessaires ;
- relation avec les Query Loops Bricks.

Créer les termes si nécessaire :

```js
create_taxonomy_term({taxonomy: "categorie_projet", name: "Salle de bain"})
```

Uploader les images locales :

```js
upload_local_files_batch({
  items: [
    {localPath: "/path/photo.jpg", title: "Salle de bain beige", alt: "Salle de bain rénovée"}
  ],
  optimize: true
})
```

Créer le contenu :

```js
create_post({
  postType: "realisation",
  title: "Salle de bain beige et bois",
  content: "<p>Description longue...</p>",
  excerpt: "Faïence murale grand format et sol imitation bois.",
  featuredImageId: 56,
  meta: {
    localisation: "Chalon-sur-Saône",
    galerie_photos: [56, 57, 58],
    mise_en_avant: 1
  },
  taxonomies: {
    categorie_projet: ["salle-de-bain"]
  }
})
```

Vérifier :

```js
list_posts({postType: "realisation", perPage: 50})
```

## ACF Et Meta

Si ACF est actif, le plugin utilise `update_field()` quand possible, sinon `update_post_meta()`.

Formats fréquents :

| Type | Format |
|---|---|
| text/textarea/url/email | string |
| number/range | number |
| true_false | `0` ou `1` |
| date_picker | `YYYY-MM-DD` |
| image/file | media ID |
| gallery | array de media IDs |
| select/radio | value string |
| checkbox | array de values |
| post_object/relationship | ID ou array d'IDs |
| repeater | array d'objets |
| group | object |
| flexible content | array avec `acf_fc_layout` |

## Pièges

- Si l'image mise en avant ne s'affiche pas, vérifier que le CPT supporte `thumbnail`.
- Les taxonomies acceptent slug ou nom ; préférer les slugs stables.
- Pour Query Loops, créer assez de contenu réel avant de juger le design.
- Ne pas mettre des URLs externes dans un champ ACF image/gallery : uploader en médiathèque et stocker les IDs.
- Les champs complexes ACF Pro peuvent nécessiter validation frontend avec Bricks dynamic data.

## Avec Bricks

Après création du contenu :

1. Configurer ou vérifier la Query Loop.
2. Utiliser les éléments natifs adaptés : `post-title`, `post-excerpt`, `post-featured-image`, `post-meta`, etc.
3. Utiliser les dynamic tags Bricks pour ACF/meta.
4. Vérifier le rendu avec `verify_element` puis `audit_page`.
