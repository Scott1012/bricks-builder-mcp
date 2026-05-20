# 🗃 CPT Seeding — Créer du contenu dans les Custom Post Types

> Disponible depuis Bricks MCP plugin v4.0.0 / MCP server v3.9.0.

---

## Pourquoi

Les sites Bricks modernes utilisent massivement les **Custom Post Types** (CPT) pour :
- Galerie de réalisations (CPT `chantier`, `projet`, `realisation`)
- Témoignages (CPT `avis_client`, `temoignage`)
- Catalogue produits (CPT `produit`)
- Blog (post natif WP, ou CPT `article`)
- Équipe (CPT `membre`, `equipe`)
- FAQ, événements, etc.

Les **Query Loops Bricks** s'appuient sur ces CPT pour générer dynamiquement le contenu. Sans seeding automatisé, l'AI doit demander à l'utilisateur de créer tout le contenu à la main dans WP admin — pas terrible.

Avec ces outils, l'AI peut **seed un site complet en quelques appels**.

---

## Workflow recommandé pour un site avec CPT

### 1. Découvrir les post types disponibles

```js
list_post_types()
// → {postTypes: [
//     {name: "page", label: "Pages", taxonomies: [], ...},
//     {name: "post", label: "Articles", taxonomies: ["category", "post_tag"], ...},
//     {name: "chantier", label: "Chantiers", taxonomies: ["categorie_chantier"], hierarchical: false, ...},
//     {name: "avis_client", label: "Avis Clients", taxonomies: [], ...}
//   ]}
```

L'AI sait maintenant : il y a un CPT `chantier` avec une taxonomie `categorie_chantier`.

### 2. Créer les termes de taxonomie

```js
create_taxonomy_term({taxonomy: "categorie_chantier", name: "Salle de bain"})
create_taxonomy_term({taxonomy: "categorie_chantier", name: "Intérieur"})
create_taxonomy_term({taxonomy: "categorie_chantier", name: "Terrasse"})
// → idempotent : retourne l'ID existant si le terme existe déjà
```

### 3. Uploader les médias (utiliser `upload_local_files_batch` avec optimize: true)

```js
upload_local_files_batch({
  items: [
    {localPath: "/path/jt-salle-de-bain-1.png", title: "Salle de bain beige parquet", alt: "..."},
    // ...
  ],
  optimize: true  // conversion WebP auto
})
// → Récupère les IDs WP des images pour les passer en featuredImageId / meta gallery
```

### 4. Créer les posts CPT avec meta + taxonomies

```js
create_post({
  postType: "chantier",
  title: "Salle de bain — Beige & parquet",
  content: "<p>Description longue...</p>",
  excerpt: "Faïence murale grand format, sol imitation parquet bois clair.",
  featuredImageId: 56,  // ID retourné par upload_local_file
  meta: {
    localisation: "Saône-et-Loire",
    description_courte: "...",
    galerie_photos: [56, 57, 58],     // ACF gallery field = array d'IDs
    date_chantier: "2024-09-15",
    mise_en_avant: 1                    // ACF true_false = 0 ou 1
  },
  taxonomies: {
    categorie_chantier: ["salle-de-bain"]  // par slug OU par ID
  }
})
```

L'outil :
- Détecte si ACF est installé → route les `meta` via `update_field()` (gère les fields complexes : repeater, gallery, relationship, post_object, etc.)
- Sinon → fallback sur `update_post_meta()`
- Résout les slugs de taxonomies en IDs automatiquement
- Crée les termes manquants à la volée

### 5. Lister/vérifier ce qu'on a créé

```js
list_posts({
  postType: "chantier",
  taxonomyFilter: {categorie_chantier: "salle-de-bain"},
  perPage: 50
})
// → {total, items: [{id, title, slug, url, editLink, featuredImageUrl, ...}]}
```

---

## Champs ACF complexes — formats attendus

L'AI passe le format **brut** dans `meta`, l'outil détecte ACF et fait le mapping.

| Type ACF | Format à passer | Exemple |
|---|---|---|
| Text / Textarea / URL / Email | string | `"description courte"` |
| Number / Range | number | `42` |
| True/False | 0 ou 1 | `1` |
| Date Picker | string `Y-m-d` | `"2024-09-15"` |
| Image | media ID (number) | `56` |
| Gallery | array d'IDs | `[56, 57, 58]` |
| File | media ID | `74` |
| Select / Radio | value (string) | `"option_1"` |
| Checkbox | array of values | `["a", "b"]` |
| Post Object / Relationship | ID ou array d'IDs | `123` ou `[123, 124]` |
| Repeater | array d'objects | `[{sub_field_1: "...", sub_field_2: 5}, {...}]` |
| Group | object | `{sub_field_1: "...", sub_field_2: 5}` |
| Flexible Content | array avec `acf_fc_layout` | `[{acf_fc_layout: "text_block", content: "..."}]` |

---

## Pièges connus

### CPT pas accessible en REST
Si `list_post_types` montre `showInRest: false` pour un CPT, c'est qu'il a été enregistré sans `show_in_rest => true`. Tu peux quand même créer/modifier les posts via les outils MCP (qui passent par `wp_insert_post()` direct, pas par REST WP) — mais les Query Loops Bricks marcheront. C'est juste que `/wp-json/wp/v2/{cpt}` ne fonctionnera pas pour les outils tiers.

### ACF Pro vs ACF Free
`update_field()` est dispo dans les deux versions. Les fields complexes (repeater, flexible content, clone) nécessitent ACF Pro. Si l'AI passe un repeater sans ACF Pro, ça stockera comme array brut dans `wp_postmeta` mais Bricks ne pourra pas l'afficher en Dynamic Data correctement.

### Featured image pas affichée
Si `featuredImageId` est passé mais l'image ne s'affiche pas en frontend : vérifier que le CPT supporte `thumbnail` (visible dans `supports` de `list_post_types`). Si non supporté, ajouter dans la registration du CPT côté code.

### Slug duplicate
Si tu crées plusieurs posts avec le même titre, WP appendra `-2`, `-3`, etc. au slug. Pour forcer un slug précis, passer le paramètre `slug` explicitement.

### Taxonomy slug vs name
`taxonomies: {categorie_chantier: ["salle-de-bain"]}` accepte slug OU name. L'outil cherche d'abord par slug, puis par name. Si ni l'un ni l'autre n'existe → crée le terme à la volée avec ce nom.

---

## Exemple complet : seeding 12 posts JT Carrelage

```js
// 1. Découvrir
const types = await list_post_types();
// Confirme : chantier, avis_client, categorie_chantier

// 2. Termes taxonomie
for (const cat of ["Salle de bain", "Intérieur", "Terrasse"]) {
  await create_taxonomy_term({taxonomy: "categorie_chantier", name: cat});
}

// 3. Médias (assume déjà fait via upload_local_files_batch)
// → on a les IDs : logoId, hero, chantier1Cover, chantier1Gallery[], etc.

// 4. Avis clients
const avis1 = await create_post({
  postType: "avis_client",
  title: "Sophie & Marc D.",
  content: "« Travail d'une précision remarquable. »",
  meta: {note: 5, localisation_client: "Chalon", initiales: "SD", source: "google", afficher_home: 1}
});

// 5. Chantiers avec liaison avis
await create_post({
  postType: "chantier",
  title: "Salle de bain — Beige & parquet",
  content: "<p>Description longue...</p>",
  excerpt: "Faïence murale ton beige, parquet bois clair.",
  featuredImageId: 56,
  meta: {
    localisation: "Saône-et-Loire",
    galerie_photos: [56, 57, 58],
    avis_lie: avis1.id,
    date_chantier: "2024-09-15",
    mise_en_avant: 1
  },
  taxonomies: {categorie_chantier: ["salle-de-bain"]}
});
```

---

*Outils ajoutés en v4.0.0 plugin / v3.9.0 MCP server.*
