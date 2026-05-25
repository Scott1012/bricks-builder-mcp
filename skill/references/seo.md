# Bricks SEO

But : intégrer les bases SEO pendant la construction Bricks sans transformer le skill en cours SEO complet.

## Structure On-Page

- Un seul `h1` par page.
- Hiérarchie logique : `h1` -> `h2` -> `h3`, ne pas choisir un tag pour sa taille visuelle.
- Les CTA et liens importants doivent être de vrais liens/boutons, pas uniquement du texte décoratif.
- Les sections doivent avoir des titres compréhensibles hors contexte.
- Les contenus locaux doivent citer les services, villes/zones et preuves de confiance sans bourrage.

## Meta Et URL

À gérer via l'outil SEO du site si présent, sinon WP/theme :

- title unique, orienté intention ;
- meta description claire avec bénéfice + zone si local ;
- slug court, lisible, sans dates inutiles ;
- canonical correcte ;
- noindex uniquement si volontaire.

## Images

- Upload via `upload_local_file` ou `upload_local_files_batch` avec `optimize: true`.
- Alt descriptif et utile, pas une suite de mots-clés.
- Nom de fichier propre si possible.
- Éviter les images énormes au-dessus de 2000px sauf besoin réel.
- Vérifier `media_health` via `verify_element` / `audit_page`.

## Performance

Priorités :

- limiter le CSS custom global ;
- éviter les librairies JS inutiles ;
- charger les fonts nécessaires seulement ;
- préférer WebP/AVIF optimisé ;
- définir dimensions/aspect-ratio sur médias pour limiter le CLS ;
- ne pas injecter de vidéos lourdes sans poster/fallback.

Google Fonts : si la font ne sort pas en frontend via Font Manager, charger le `<link>` dans `set_custom_code({customScriptsHeader})`.

## Schema Markup

Ajouter du JSON-LD seulement si les données sont vraies et maintenables.

Types fréquents :

| Cas | Type schema.org |
|---|---|
| entreprise locale | `LocalBusiness` ou sous-type métier |
| article | `Article` / `BlogPosting` |
| FAQ visible sur page | `FAQPage` |
| fil d'Ariane | `BreadcrumbList` |
| produit WooCommerce | préférer schema Woo/SEO plugin |

Pour une entreprise locale, vérifier : nom, URL, téléphone, adresse, zone servie, horaires, image, réseaux, coordonnées si disponibles.

## Local SEO

Pour artisans/commerces locaux :

- NAP cohérent : name/address/phone ;
- zone d'intervention visible ;
- preuves locales : réalisations, avis, photos, villes ;
- CTA clair téléphone/devis ;
- page contact complète ;
- liens internes vers services et zones.

## Checklist Avant Livraison

- H1 unique.
- H2/H3 cohérents.
- Images avec alt et poids raisonnable.
- CTA principaux visibles desktop/mobile.
- Pas d'overflow mobile.
- Page title/meta prévus.
- Schema JSON-LD seulement si fiable.
- Contenu suffisamment spécifique au métier et à la zone.
