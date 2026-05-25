# Bricks Native Elements

But : choisir le bon élément Bricks natif au lieu de recréer un bloc figé en HTML/CSS. Cette liste cible Bricks 2.3.x.

## Structure JSON

Chaque élément Bricks utilise la même enveloppe :

```json
{
  "id": "abc123",
  "name": "button",
  "parent": "parent1",
  "children": [],
  "settings": {"text": "Demander un devis"},
  "label": "CTA principal"
}
```

Champs racine utiles : `id`, `name`, `parent`, `children`, `settings`, `selectors`, `label`, `themeStyles`.

Règle : `parent` et `children` sont au niveau racine. `settings` ne sert qu'au contenu, comportement et style.

## Mise En Page

| Élément Bricks | `name` | Usage |
|---|---|---|
| Section | `section` | top-level, pleine largeur, racine de section |
| Conteneur | `container` | wrapper de contenu principal |
| Bloc | `block` | wrapper courant pour cards, colonnes, groupes |
| Div | `div` | wrapper léger |
| Diviseur | `divider` | séparation visuelle |
| Liste | `list` | liste native avec items, icônes et séparateurs |

Contrôles de contenu des wrappers : `link`, `tag`, `customTag`.

Structure standard :

```text
section
container / block
heading, text-basic, button, image, ...
```

## Basique

| Élément Bricks | `name` | Contrôles de contenu principaux |
|---|---|---|
| Titre | `heading` | `text`, `tag`, `customTag`, `type`, `style`, `link` |
| Texte basique | `text-basic` | `text`, `tag`, `customTag`, `link`, `wordsLimit`, `readMore` |
| Texte enrichi | `text` | `text`, `type`, `style`, `wordsLimit`, `readMore` |
| Lien texte | `text-link` | `text`, `link`, `icon`, `iconPosition`, `gap`, `iconSize`, `iconColor`, `iconBackground`, `iconBorder` |
| Bouton | `button` | `text`, `tag`, `size`, `style`, `circle`, `outline`, `link`, `icon`, `iconPosition`, `iconGap`, `iconSpace` |
| Icône | `icon` | `icon`, `iconColor`, `iconSize`, `link` |
| Image | `image` | `image`, `tag`, `customTag`, `sources`, `altText`, `caption`, `captionCustom`, `loading`, `showTitle`, `stretch`, `link`, `url`, `newTab` |
| Vidéo | `video` | `videoType`, `aspectRatio`, `objectFit`, `youTubeId`, `vimeoId`, `media`, `fileUrl`, `videoPoster`, `overlay` |

Exemples sûrs :

```json
{"name": "heading", "settings": {"text": "Titre", "tag": "h2"}}
```

```json
{"name": "button", "settings": {"text": "Demander un devis", "link": {"type": "external", "url": "/contact/"}}}
```

```json
{"name": "image", "settings": {"image": {"id": 123, "url": "https://example.com/image.webp", "size": "full"}, "altText": "Description"}}
```

## Médias

| Élément | `name` | Usage |
|---|---|---|
| Audio | `audio` | lecteur audio |
| Carousel | `carousel` | carousel média/contenu |
| Galerie image | `image-gallery` | galerie/grille d'images |
| Instagram feed | `instagram-feed` | flux Instagram |
| Logo | `logo` | logo site dynamique |
| Slider | `slider` | slider classique |
| Slider imbriqué | `slider-nested` | slider avec enfants Bricks |
| SVG | `svg` | SVG natif |

Pour les médias, préférer un ID WordPress media (`image.id`, `video.media`) plutôt qu'une URL externe.

## Général Et Interactif

| Élément | `name` | Usage |
|---|---|---|
| Accordéon | `accordion` | FAQ simple par items |
| Accordéon imbriqué | `accordion-nested` | FAQ/contenus avec enfants Bricks |
| Alert | `alert` | message d'alerte |
| Animated typing | `animated-typing` | texte animé |
| Back to top | `back-to-top` | retour haut page |
| Breadcrumbs | `breadcrumbs` | fil d'Ariane |
| Code | `code` | HTML/CSS/JS contrôlé |
| Countdown | `countdown` | compte à rebours |
| Counter | `counter` | compteur numérique |
| Dropdown | `dropdown` | menu déroulant/contenu |
| Facebook page | `facebook-page` | embed Facebook |
| Form | `form` | formulaire natif Bricks |
| HTML | `html` | HTML brut |
| Icon box | `icon-box` | icône + titre + texte |
| Icon list | `social-icons` | liste d'icônes avec labels/liens |
| Map | `map` | Google Map |
| Map connector | `map-connector` | connecteur carte |
| Map Leaflet | `map-leaflet` | carte Leaflet |
| Nav nested | `nav-nested` | navigation avancée |
| Offcanvas | `offcanvas` | panneau latéral/mobile |
| Pie chart | `pie-chart` | graphique camembert |
| Pricing tables | `pricing-tables` | tables de prix |
| Progress bar | `progress-bar` | barre progression |
| Rating | `rating` | étoiles/notation |
| Slot | `slot` | slot de composant Bricks |
| Tabs | `tabs` | onglets classiques |
| Tabs nested | `tabs-nested` | onglets avec enfants Bricks |
| Team members | `team-members` | équipe |
| Template | `template` | inclusion template Bricks |
| Testimonials | `testimonials` | témoignages |
| Toggle | `toggle` | bouton toggle |
| Toggle mode | `toggle-mode` | mode clair/sombre |

## Form

Le formulaire Bricks est un élément riche. Contrôles principaux :

| Besoin | Contrôles |
|---|---|
| champs | `fields`, `showLabels`, `requiredAsterisk`, `disableBrowserValidation`, `validateAllFieldsOnSubmit` |
| style champs | `fieldMargin`, `fieldPadding`, `fieldBackgroundColor`, `fieldBorder`, `fieldTypography`, `placeholderTypography`, `labelTypography` |
| bouton submit | `submitButtonText`, `submitButtonSize`, `submitButtonStyle`, `submitButtonWidth`, `submitButtonMargin`, `submitButtonTypography`, `submitButtonBackgroundColor`, `submitButtonBorder`, `submitButtonIcon`, `submitButtonIconPosition` |
| actions | `actions`, `successMessage`, `redirect`, `emailSubject`, `emailTo`, `emailToCustom`, `fromEmail`, `fromName`, `replyToEmail`, `emailContent`, `webhooks` |
| intégrations | `mailchimp*`, `sendgrid*`, `enableRecaptcha`, `enableTurnstile`, `enableHCaptcha` |
| compte/post | `login*`, `registration*`, `lostPassword*`, `resetPassword*`, `createPost*`, `updatePost*` |

Pour un formulaire réel, partir d'un formulaire natif Bricks minimal puis modifier les contrôles nécessaires. Ne pas créer `fields` complexes à l'aveugle.

## Navigation Et WordPress

| Élément | `name` | Usage |
|---|---|---|
| Menu navigation WP | `nav-menu` | menu WordPress classique |
| Pagination | `pagination` | pagination WP/query |
| Posts | `posts` | liste/grille posts |
| Search | `search` | recherche WordPress |
| Shortcode | `shortcode` | shortcode plugin/WP |
| Sidebar | `sidebar` | sidebar WordPress |
| WordPress | `wordpress` | widget WordPress |

Pour une navigation éditable, utiliser `nav-menu` ou `nav-nested`, pas une suite de liens hardcodés.

## Query Et Filtres

| Élément | `name` |
|---|---|
| Résumé résultats | `query-results-summary` |
| Filtres actifs | `filter-active-filters` |
| Checkbox filter | `filter-checkbox` |
| Datepicker filter | `filter-datepicker` |
| Radio filter | `filter-radio` |
| Range filter | `filter-range` |
| Search filter | `filter-search` |
| Select filter | `filter-select` |
| Submit filter | `filter-submit` |

Voir [native-dynamic.md](native-dynamic.md) pour query loops, templates, filtres et post elements.

## Single / Post

| Élément | `name` |
|---|---|
| Auteur | `post-author` |
| Commentaires | `post-comments` |
| Contenu | `post-content` |
| Extrait | `post-excerpt` |
| Meta | `post-meta` |
| Navigation post | `post-navigation` |
| Reading progress | `post-reading-progress-bar` |
| Reading time | `post-reading-time` |
| Partage | `post-sharing` |
| Taxonomie | `post-taxonomy` |
| Titre | `post-title` |
| Table des matières | `post-toc` |
| Related posts | `related-posts` |

## Produit WooCommerce

Éléments produit natifs :

```text
product-add-to-cart
product-additional-information
product-content
product-gallery
product-meta
product-price
product-rating
product-related
product-reviews
product-short-description
product-stock
product-tabs
product-title
product-upsells
```

Voir [native-woocommerce.md](native-woocommerce.md) pour panier, checkout, compte et archives WooCommerce.

## Components Bricks 2.x

Un component instance peut contenir des clés racine critiques :

```text
cid
instanceId
parentComponent
parentInstanceId
rootComponent
rootInstanceId
slotChildren
```

Ne pas supprimer ces clés. Pour modifier un composant, patcher seulement le setting ciblé ou remplir le slot prévu.

## Choix Rapide

| Besoin | Premier choix |
|---|---|
| Hero | `section` + `container/block` + `heading` + `text-basic` + `button` + `image` |
| Grille de cards | wrapper `block` en grid + cards `block` + `icon/image` + `heading` + `text-basic` |
| CTA | `section` + `heading` + `text-basic` + `button` |
| FAQ | `accordion-nested` si contenu riche, `accordion` si simple |
| Témoignages | cards custom ou `testimonials` |
| Header | template header + `logo` + `nav-menu`/`nav-nested` + `button` |
| Formulaire contact | `form` |
| Blog archive | `posts` ou query loop + post elements |
| Single post | post elements natifs |
| Produit Woo | template Woo + product elements natifs |

## Ne Pas Faire

- Ne pas remplacer un `button`, `image`, `form`, `nav-menu`, post element ou élément Woo natif par du HTML custom si l'élément Bricks existe.
- Ne pas confondre `text` rich text et `text-basic` simple.
- Ne pas mettre une page entière dans un seul `code`/`html`.
- Ne pas inventer des clés de contenu pour les éléments complexes : partir du schema Bricks ou d'un élément créé dans l'UI.
