# ⭐ Feedback System — Remonter les manques MCP

> Disponible depuis Bricks MCP v3.7 / MCP server v3.6.

---

## Pourquoi ce système

Le MCP Bricks expose ~50 outils. Bricks Builder a probablement **3× plus** de features natives. Quand un chat AI tombe sur :

- Une feature Bricks documentée officiellement
- Pour laquelle aucun outil MCP n'existe (ou l'outil existant ne marche pas)

…au lieu d'inventer un workaround en silence, il **remonte** le manque. Le mainteneur du MCP lit la liste et ajoute les outils manquants.

C'est **un tracker de gaps plugin/MCP**, pas un tracker de limites Bricks.

---

## Quand utiliser `report_missing_feature`

```
Tu veux faire X
  ↓
Cherche dans la doc Bricks (academy.bricksbuilder.io)
  ↓
  ├─ Bricks ne supporte pas X
  │    → Code librement une alternative (CSS keyframes, JS via set_page_custom_code, etc.)
  │    → AUCUN report (c'est du dev normal)
  │
  └─ Bricks supporte X nativement
       ↓
       Essaye avec les outils MCP actuels (vérifie outils-mcp-extended.md)
       ↓
       ├─ Ça marche → done
       └─ MCP ne l'expose pas / outil buggy / setting ignoré
            → report_missing_feature (AVANT de bricoler)
```

---

## Format du report

```js
report_missing_feature({
  title: "Pas d'outil pour Interactions API",          // court, mnémonique
  bricksFeature: "Interactions API (event-based)",      // nom officiel Bricks
  bricksDocUrl: "https://academy.bricksbuilder.io/article/interactions/",  // PROUVE que Bricks le fait
  whatItShouldDo: "Ajouter trigger click + action show element à un bouton",
  whatITried: "update_element avec _interactions: [...] — settings écrit mais Bricks ignore au rendu",
  proposedTool: "set_element_interactions(elementId, interactions[])",
  bricksVersion: "2.3.2",
  context: "Construction d'une modal galerie sur la page JT Carrelage / accueil"
})
```

**Champs forts** :
- `bricksFeature` + `bricksDocUrl` → **prouvent** que Bricks le fait nativement (sinon = limite Bricks, ne pas reporter)
- `whatITried` → **prouve** que ce n'est pas un manque de connaissance MCP

---

## Déduplication automatique

Si plusieurs chats reportent le **même titre** (normalisé), le compteur `occurrences` s'incrémente au lieu de créer un doublon. Cela permet au mainteneur de prioriser : `occurrences: 5` → demande forte, à traiter en premier.

---

## Pour le mainteneur (chat de maintenance)

```js
list_missing_features({status: "open"})
// → [{id, title, bricksFeature, bricksDoc, tried, proposedTool, occurrences, createdAt, contexts: [...]}, ...]
//   Triés par occurrences DESC, puis createdAt DESC
```

Après avoir ajouté l'outil ou enrichi la doc :

```js
resolve_missing_feature({
  id: "fbk_xxx",
  resolutionNote: "Ajouté en v3.7.1 — set_element_interactions"
})
```

---

## Exemples concrets

### ✅ À reporter

- "Bricks a un Query Filter element (Pro 1.10+) mais le MCP n'a pas d'outil pour le configurer"
- "L'option `_template` permet d'attacher un template Bricks à un élément (doc Bricks) mais aucun outil MCP ne l'expose"
- "Bricks 2.0 a un système de Components réutilisables, le MCP a list_components mais pas attach_component_to_page"

### ❌ À NE PAS reporter (= limite Bricks → code une alternative)

- "Bricks n'a pas d'animation marquee/ticker native" → CSS `@keyframes` dans `set_page_custom_code({customCss})`
- "Bricks n'a pas de date picker" → utiliser un script JS dans `customScripts`
- "Pas de support natif WebGL" → injecter ton canvas via `set_page_custom_code`

### ❌ À NE PAS reporter (= toi tu n'as pas trouvé l'outil)

- "Pas d'outil pour modifier les fonts globales" → SI, c'est `set_custom_code({customScriptsHeader})` + `set_custom_code({customCss})`. Cherche d'abord.

---

## Workflow type pour un chat AI

1. Tu veux **construire une modal de galerie**
2. Cherche dans `outils-mcp-extended.md` → rien d'évident
3. Cherche dans `bricks-native-api.md` → trouve qu'il y a un `Offcanvas` element + `Interactions API`
4. Essaye `add_element({name: "offcanvas", ...})` → ça crée bien mais comment ouvrir au clic ?
5. Lit `https://academy.bricksbuilder.io/article/interactions/` → c'est censé être éditable via le panel Interactions
6. Essaye `update_element({_interactions: [{trigger: "click", action: "showElement", ...}]})` → settings écrits mais front ignore
7. **`report_missing_feature`** avec tous les détails → traité plus tard
8. Pendant ce temps, code une alternative : `set_page_custom_code({customScripts: "document.querySelector('.gallery-trigger').addEventListener('click', () => document.querySelector('.brxe-offcanvas').classList.add('open'))"})`

Le chat n'est pas bloqué, et le mainteneur sait quoi ajouter ensuite.

---

*Système ajouté en v3.7 plugin / v3.6 MCP server. Stocké dans l'option WP `bricks_mcp_feedback`.*
