---
name: bricks-builder
version: 1.0.0
description: Pilote Bricks Builder (page builder WordPress) pour créer, modifier et convertir des pages. Se déclenche pour toute tâche impliquant Bricks - convertir du HTML/CSS, créer une page, ajouter ou modifier des sections, des éléments, gérer le responsive, optimiser le SEO d'un site Bricks. Inclut une bibliothèque de patterns, pièges connus, workflow recommandé, et un guide complet basé sur 640+ éléments testés en production.
---

# 🧱 Bricks Builder — Skill Complet (v1.0.0)

Skill pour piloter **Bricks Builder** (page builder WordPress) via les outils MCP. Couvre la création, modification et conversion de pages, le design responsive, le SEO et tous les patterns testés en production.

## ⭐ AVANT TOUT — Vérifier la version du skill

Au début de chaque conversation impliquant Bricks, appelle :

```js
check_skill_version({localVersion: "1.0.0"})  // remplace par la version au-dessus
```

Si la réponse indique qu'une version plus récente est disponible, **informe l'utilisateur** :

> "Tu utilises le skill bricks-builder v{LOCAL}, mais v{LATEST} est disponible. Va dans WP admin → Bricks MCP → 'Télécharger pour Claude' pour récupérer la nouvelle version."

Continue ensuite normalement avec la doc locale — pas besoin de bloquer le travail.

## Quand ce skill se déclenche

- Conversion **HTML/CSS → Bricks** (cloner un site, intégrer une maquette)
- Création d'une **section** (hero, features, footer, header, etc.)
- Ajout/modification/suppression d'**éléments** (buttons, headings, containers, social-icons, etc.)
- Mise en place du **responsive** (breakpoints, mobile-first)
- Optimisation **SEO** d'une page Bricks (hiérarchie Hn, schema markup, Core Web Vitals)
- **Refonte** d'une page existante
- **Debug** d'un comportement Bricks bizarre (élément invisible, gap qui ne s'applique pas, etc.)

## Les 10 règles d'or (à respecter TOUJOURS)

### 1. Cloner un format qui marche avant d'inventer

Bricks a des comportements **spécifiques à chaque installation** : `font-size` peut être string (`"44"`) ou objet (`{size, unit}`), les gaps fonctionnent différemment, etc. **Avant** de créer un élément avec une propriété incertaine, utiliser `find_elements` + `get_element` pour copier le format depuis un élément similaire existant.

### 2. Outil le plus léger possible

| Tâche | Outil | Tokens |
|---|---|---|
| Voir structure | `get_page_structure` ✅ | ~200 |
| Chercher élément | `find_elements` ✅ | ~80 |
| Modifier 1 élément | `update_element` ✅ | ~50 |
| Créer section | `batch_add` ✅ | ~500 |
| Refonte totale | `get_page_json` + `update_page_json` ⚠️ | ~2500 |

**Jamais `get_page_json` pour une petite modif.** Voir `references/outils-mcp.md` pour le détail.

### 3. Hiérarchie = 3 modifications minimum

`parent` et `children` sont au **niveau racine** de l'élément JSON, **pas dans `settings`**. Donc `update_element` ne peut PAS modifier la hiérarchie. Pour déplacer un élément entre parents, il faut modifier 3 éléments via `update_page_json` :

1. Ancien parent → retirer de `children`
2. Nouveau parent → ajouter à `children`
3. Élément lui-même → changer `parent`

### 4. Gap = TOUJOURS 3 propriétés ensemble

```json
{
  "_gap": {"size": "32", "unit": "px"},
  "_columnGap": "32",
  "_rowGap": "32"
}
```

`_gap` seul est souvent ignoré. **Note critique** : `_gap` et `_justifyContent: "space-between"` sont **indépendants** — `space-between` distribue les éléments aux extrémités et **ignore** `_gap` pour l'espace central. Mesurer le gap réel avec `getBoundingClientRect()`, pas se fier à `getComputedStyle().gap`.

### 5. Flex horizontal = `_direction: "row"` obligatoire

Sur cette installation Bricks, `flex-direction` par défaut est `column`. Pour un layout horizontal, **toujours** spécifier explicitement `_direction: "row"`.

### 6. Propriétés à connaître (vs. CSS standard)

| Tu veux | Bricks utilise |
|---|---|
| `max-width` | `_widthMax` (sans `px`) |
| `border-radius` sur bouton | `_border.radius` (PAS `_borderRadius`) |
| Centrage horizontal | `container` (auto) ou `div` + `_margin: {left:auto, right:auto}` (manuel) |
| Icônes sociales | `name: "social-icons"` (élément dédié, PAS `icon` + liens manuels) |
| Hover/focus | `_cssCustom` (Bricks ne supporte pas les pseudo-classes natives) |

### 7. `getComputedStyle()` = seule preuve fiable

Pour vérifier qu'une propriété est appliquée : delta `before`/`after` via `getComputedStyle(el)`. **Ne PAS** se fier à la présence de la règle dans une balise `<style>` (peut être overridée) ni au JSON Bricks (peut être ignoré au rendu).

### 8. Screenshots = `waitForMS: 2000` toujours

Font Awesome, Google Fonts et autres assets externes chargent **1-2s après le DOM**. Sans délai, les icônes apparaissent comme des carrés blancs ⬜ et les fonts par défaut sont visibles. **Toujours** :

```javascript
take_screenshot({ url, width: 1920, waitForMS: 2000 })
```

### 9. ⭐ `verify_element` après CHAQUE batch_add / update significatif

**Plus de "ça me semble bon"**. Après chaque section ou modification non-triviale, appelle :

```js
verify_element({pageId, elementId, viewport: "desktop"})
// Retourne : screenshot crop + report {score, checks: [{ok, label, expected, got, hint}]}
```

L'outil :
- Lance un browser headless (waitForMS: 2000 intégré)
- Compare computed style vs settings attendus (gap, padding, typography, border-radius)
- Détecte fonts non chargées, console errors, débordement horizontal, children manquants
- Te montre une **image** (que tu peux voir) + un **report structuré** (que tu peux exploiter)

**Workflow imposé** :
```
batch_add (1 section, ≤ 10 éléments)
   ↓
verify_element (le PARENT de la section)
   ↓
   ├─ score ≥ 9/10 → next section
   └─ score < 9/10 → corriger avec les hints → re-verify
```

Détails et exemples : `references/verify-element.md`.

### 10. ⭐ `report_missing_feature` si Bricks le fait mais MCP non

**Avant d'inventer un workaround pour quelque chose que Bricks fait nativement** :

1. Cherche dans `references/outils-mcp*.md` si un outil l'expose déjà
2. Si pas trouvé, lis la doc officielle Bricks (`academy.bricksbuilder.io`)
3. Si Bricks supporte nativement la feature ET aucun outil MCP ne l'expose / l'outil est buggy → **`report_missing_feature` AVANT de bricoler**

```js
report_missing_feature({
  title: "Pas d'outil pour Interactions API",
  bricksFeature: "Interactions API (event-based)",
  bricksDocUrl: "https://academy.bricksbuilder.io/article/interactions/",
  whatItShouldDo: "Ajouter trigger click + action show element à un bouton",
  whatITried: "update_element avec _interactions: [...] — settings écrit mais Bricks ignore",
  proposedTool: "set_element_interactions",
  bricksVersion: "2.3.2",
  context: "Page X, construction modal galerie"
})
```

**À ne PAS reporter** : limites natives de Bricks (Bricks ne sait pas faire X). Dans ce cas, code librement une alternative (CSS keyframes, JS via `set_page_custom_code`).

Détails : `references/feedback-system.md`.

## Workflow recommandé : PETIT → VÉRIFIER → SUIVANT

```
❌ MAUVAIS : 50 éléments d'un coup → screenshot final → 10 problèmes → tout refaire
✅ BON     : 5-10 éléments → vérif rapide → 5-10 éléments → vérif rapide → vérif approfondie
```

**Système de vérification à 3 niveaux** :

- **🟢 Niveau 1 (~200 tokens)** : script JS via `browser_evaluate` — après chaque batch
- **🟡 Niveau 2 (~500-800 tokens)** : script JS complet + screenshot ciblé — toutes les 3-4 sections
- **🔴 Niveau 3 (~1500-2000 tokens)** : multi-viewport + comparaison + screenshots — fin de page / livraison

Détails dans `references/workflow.md` (méthodologie complète).

## Arbre de décision rapide

```
Que dois-je faire ?
├─ Lister pages         → list_bricks_pages          (~100)
├─ Voir structure       → get_page_structure         (~200)
├─ Chercher élément     → find_elements              (~80)
├─ Modifier 1 élément   → update_element             (~50)
├─ Ajouter 1 élément    → add_element                (~100)
├─ Créer section        → batch_add                  (~500)
├─ Réorganiser ordre    → reorder_sections           (~100)
├─ Refonte totale       → get_page_json + update     (~2500)
├─ ⭐ Vérifier section   → verify_element            (image + report)
├─ Vérifier rapide JS   → browser_evaluate (script JS)
├─ Vérifier visuel full → screenshot avec waitForMS:2000
└─ ⭐ Signaler un trou   → report_missing_feature    (si Bricks le fait mais MCP non)
```

## Index des references — où trouver quoi

Quand tu attaques une tâche Bricks, **consulte le fichier de référence approprié** :

| Besoin | Fichier à lire |
|---|---|
| ⭐ **VERIFY ELEMENT** — workflow petit-à-petit-vérifier-petit-à-petit, comment lire le report, comment corriger les checks rouges | `references/verify-element.md` |
| ⭐ **FEEDBACK SYSTEM** — quand et comment remonter un manque d'outil MCP (pas une limite Bricks) | `references/feedback-system.md` |
| ⭐ **FORMATS BRICKS 2.3 validés en production** — À LIRE EN PREMIER pour ne pas refaire les erreurs des autres IA (border-radius, line-height, colors avec rgba/var, Google Fonts, etc.) | `references/bricks-2.3-formats.md` |
| ⭐ **OUTILS MCP étendus** (~40 nouveaux outils ajoutés en v3.4 / v3.5 / v3.6) — pages CRUD, médias, menus, custom code, fonts, classes, theme styles, etc. | `references/outils-mcp-extended.md` |
| **API native Bricks** (Custom Code, Fonts, Theme Styles, Global Classes, Code Execution, etc.) — où chaque chose est en DB et avec quel outil MCP la piloter | `references/bricks-native-api.md` |
| Snippets JSON prêts à l'emploi (CTA, hero, container, grid, image) — **vérifier les formats avec bricks-2.3-formats.md d'abord** | `references/cheat-sheet.md` |
| Doc des 11 outils MCP originaux (lecture/écriture éléments) | `references/outils-mcp.md` |
| Patterns techniques (color, typography, flexbox, grid, etc.) + catalogue modules | `references/patterns.md` |
| 13 pièges fréquents avec solutions testées (lit le préambule **bricks-2.3-formats.md** d'abord) | `references/pitfalls.md` |
| Méthodologie complète (workflow, vérifications, économie tokens) | `references/workflow.md` |
| Guide général exhaustif (architecture, breakpoints, hiérarchie, exemples) — **certains formats peuvent être obsolètes pour Bricks 2.3, croiser avec bricks-2.3-formats.md** | `references/guide-complet.md` |
| Faire du **design web professionnel** (hiérarchie visuelle, palettes, typo) | `references/design-web.md` |
| Optimiser le **SEO** (hiérarchie Hn, schema, Core Web Vitals, local SEO) | `references/seo.md` |
| Exemples concrets de structures JSON pour tous types d'éléments | `references/test-structures.json` |

## Workflow conseillé pour une nouvelle tâche

1. **Lire le fichier de référence** correspondant à la tâche (voir tableau ci-dessus)
2. **Explorer** la page existante : `list_bricks_pages` → `get_page_structure`
3. **Cloner** des formats existants si propriétés incertaines : `find_elements` + `get_element`
4. **Construire petit à petit** : `batch_add` (≤ 10 éléments par appel)
5. ⭐ **`verify_element` après CHAQUE batch significatif** — image + report en 1 appel. Score < 9/10 → corriger avec les hints avant de continuer.
6. **`report_missing_feature`** si tu butes sur quelque chose que Bricks fait nativement mais le MCP non
7. **Screenshot final fullpage** (`screenshot-website-fast` avec waitForMS: 2000) pour validation visuelle de bout en bout

## Notes importantes

- **Cette installation Bricks** a été observée avec 640+ éléments en production. Les règles ci-dessus sont **spécifiques à cette installation** — d'autres installations peuvent avoir des comportements différents pour `font-size`, `_gap`, etc.
- **En cas de doute**, toujours cloner depuis un élément similaire existant plutôt que d'inventer un format.
- **L'ordre dans le tableau JSON** = ordre de rendu sur la page. Header doit être premier, footer dernier. Utiliser `reorder_sections` pour corriger.

---

*Skill créé à partir de 7000+ lignes de doc accumulées en production sur Bricks Builder.*
