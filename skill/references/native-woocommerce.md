# Bricks WooCommerce Native Elements

But : utiliser les éléments WooCommerce natifs Bricks dans les templates Woo au lieu de shortcodes ou HTML custom.

## Templates Woo

Templates courants :

- single product ;
- product archive ;
- cart ;
- checkout ;
- my account ;
- order thank you ;
- empty cart ;
- mini cart/offcanvas.

Un template Woo doit avoir les bonnes conditions de template. Sans condition, il peut ne jamais s'afficher.

## Single Product

| Besoin | Élément natif |
|---|---|
| titre produit | `product-title` |
| galerie produit | `product-gallery` |
| prix | `product-price` |
| note | `product-rating` |
| description courte | `product-short-description` |
| description longue | `product-content` |
| add to cart | `product-add-to-cart` |
| stock | `product-stock` |
| meta/SKU/catégories | `product-meta` |
| informations additionnelles | `product-additional-information` |
| tabs | `product-tabs` |
| avis | `product-reviews` |
| produits liés | `product-related` |
| upsells | `product-upsells` |

## Archive Produits

| Besoin | Élément natif |
|---|---|
| grille produits | `woocommerce-products` ou query loop produits |
| description archive | `woocommerce-products-archive-description` |
| filtres produits | `woocommerce-products-filter` |
| tri | `woocommerce-products-orderby` |
| pagination | `woocommerce-products-pagination` |
| total résultats | `woocommerce-products-total-results` |
| breadcrumbs | `woocommerce-breadcrumbs` |
| notices | `woocommerce-notice` |

## Cart

| Besoin | Élément natif |
|---|---|
| items panier | `woocommerce-cart-items` |
| totaux / collaterals | `woocommerce-cart-collaterals` |
| coupon | `woocommerce-cart-coupon` |
| mini cart | `woocommerce-mini-cart` |
| notices | `woocommerce-notice` |

## Checkout

| Besoin | Élément natif |
|---|---|
| login checkout | `woocommerce-checkout-login` |
| coupon checkout | `woocommerce-checkout-coupon` |
| détails client | `woocommerce-checkout-customer-details` |
| review commande | `woocommerce-checkout-order-review` |
| table commande | `woocommerce-checkout-order-table` |
| paiement | `woocommerce-checkout-order-payment` |
| merci / order received | `woocommerce-checkout-thankyou` |
| notices | `woocommerce-notice` |

## My Account

| Besoin | Élément natif |
|---|---|
| page compte wrapper | `woocommerce-account-page` |
| login | `woocommerce-account-form-login` |
| register | `woocommerce-account-form-register` |
| lost password | `woocommerce-account-form-lost-password` |
| reset password | `woocommerce-account-form-reset-password` |
| edit account | `woocommerce-account-form-edit-account` |
| addresses | `woocommerce-account-addresses` |
| edit address | `woocommerce-account-form-edit-address` |
| orders | `woocommerce-account-orders` |
| view order | `woocommerce-account-view-order` |
| downloads | `woocommerce-account-downloads` |
| payment methods | `woocommerce-account-payment-methods` |
| add payment method | `woocommerce-account-add-payment-method` |

## Hooks Et Notices

| Besoin | Élément natif |
|---|---|
| notices Woo | `woocommerce-notice` |
| hook Woo template | `woocommerce-template-hook` |
| breadcrumbs Woo | `woocommerce-breadcrumbs` |

Les notices sont obligatoires sur cart/checkout/account : sans `woocommerce-notice`, les erreurs et succès peuvent être invisibles.

## Règles

- Utiliser les éléments Woo natifs dans les templates Woo.
- Garder les template conditions cohérentes avec la page Woo visée.
- Vérifier avec un vrai produit, un panier et un checkout test.
- Les produits sont des posts : les tags dynamiques post fonctionnent parfois, mais les éléments Woo dédiés sont préférables.
- Ne pas remplacer un élément Woo natif par shortcode sauf absence confirmée sur l'installation.
