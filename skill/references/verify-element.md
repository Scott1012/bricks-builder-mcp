# ⭐ verify_element — Vérification en 1 appel

> Disponible depuis Bricks MCP v3.7 / MCP server v3.6. **L'outil obligatoire après chaque batch_add ou update_element significatif.**

---

## TL;DR

```js
verify_element({pageId: 32, elementId: "section_hero", viewport: "desktop"})
```

Retourne :
- **Une image** (screenshot crop sur l'élément) — tu la vois directement
- **Un report structuré** : `{score: "8/10", checks: [{ok, label, expected?, got?, hint?}, ...]}`

Plus de "ça me semble bon en lisant le JSON". Tu vois, tu compares, tu corriges.

---

## Pourquoi cet outil existe

Avant `verify_element`, vérifier un élément demandait **3-4 appels** :
- `screenshot-website-fast.take_screenshot` (fullpage 1920, lourd)
- `playwright.browser_navigate` + `browser_evaluate` (custom script)
- `playwright.browser_take_screenshot` (cibler avec selector)
- Comparer manuellement les computed styles avec ce que tu as demandé

Résultat : les chats sautaient l'étape ("ça a l'air bon"), les bugs s'accumulaient.

`verify_element` fait tout en 1 appel et **t'oblige à voir** ce que tu as construit.

---

## Anatomie du report

```js
{
  success: true,
  url: "https://site.com/page/",
  selector: ".brxe-section_hero",
  name: "section",
  label: "Hero Section",
  viewport: "desktop",
  report: {
    score: "8/10",
    checks: [
      {ok: true,  label: "Élément trouvé dans le DOM (.brxe-section_hero)"},
      {ok: true,  label: "Élément visible (1920 × 720)"},
      {ok: true,  label: "5 enfant(s) attendu(s) → 5 dans le DOM"},
      {ok: true,  label: "display = flex"},
      {ok: false, label: "gap = 32px",
                  expected: "32px", got: "0px",
                  hint: "Ajouter les 3 propriétés : _gap + _columnGap + _rowGap"},
      {ok: false, label: "flex-direction = row",
                  expected: "row", got: "column",
                  hint: "Ajouter _direction: 'row' explicitement (défaut Bricks = column)"},
      {ok: true,  label: "padding-top = 120px"},
      {ok: false, label: "Police chargée: Anton",
                  hint: "Charger via set_custom_code({customScriptsHeader: '<link Google Fonts>'})"},
      {ok: true,  label: "Aucune erreur console"},
      {ok: true,  label: "Pas de débordement horizontal"}
    ]
  },
  computed: { /* tous les computed styles bruts */ },
  loadedFonts: ["sans-serif", "Inter"]
}
```

Plus l'image dans la réponse MCP — tu la vois inline.

---

## Workflow imposé

```
batch_add (1 section, ≤ 10 éléments)
   ↓
verify_element (parent de la section, en desktop)
   ↓
   ├─ score ≥ 9/10 → next section
   └─ score < 9/10 → corriger avec hints → re-verify
                          ↓
                     score ≥ 9/10 → next
```

**Tu n'enchaînes JAMAIS deux `batch_add` sans `verify_element` entre les deux.**

---

## Multi-viewport (responsive)

Une section faite en desktop doit aussi marcher en mobile. À la fin de chaque section importante :

```js
verify_element({pageId, elementId, viewport: "desktop"})
verify_element({pageId, elementId, viewport: "mobile_portrait"})
```

Viewports disponibles : `desktop` (1920×1080), `tablet` (991×1200), `mobile_landscape` (767×600), `mobile_portrait` (478×800).

Si mobile casse, ajouter `_padding:mobile_portrait`, `_widthMax:mobile_portrait`, etc. (cf. `pitfalls.md` § 8).

---

## Limites à connaître

- **Chromium requis** : `npx playwright install chromium` une fois après install du MCP. Si pas installé, l'outil renvoie un message clair.
- **Pages publiées uniquement** : `verify_element` accède au front. Une page en draft n'est pas accessible (sauf à logger Playwright, pas supporté pour l'instant).
- **JS dynamique** : si la section dépend d'un JS qui s'exécute après 2s (animation tardive, lazy load), augmente le délai en re-lancant après une `update_element`.
- **Comparaison souple** : les valeurs CSS sont normalisées (espaces, unités, `0px === 0`). Si un check rouge te semble être un faux positif, regarde `expected` vs `got` brut.

---

## Cas d'erreur courants

### Élément introuvable dans le DOM

```js
{success: false, error: "Élément .brxe-xyz introuvable dans le DOM..."}
```

Causes possibles :
- L'élément a `_display: none` ou `_hidden`
- Le parent est caché
- `update_page_json` a partiellement échoué → vérifier avec `get_page_structure`
- La page n'est pas publiée (status `draft`)

### Score 0/X avec tous les checks ok manquants

Computed style introuvable → l'élément est dans le DOM mais sa **bounding box est 0×0**. Souvent un parent flex avec un enfant `width: 0`. Le check "Élément visible" sera rouge.

### Police non chargée

Bricks 2.3 **ne génère pas** automatiquement les `@font-face` pour les Google Fonts depuis `register_google_font_locally`. **Toujours** charger via `set_custom_code({customScriptsHeader: "<link...>"})`. Cf. `bricks-2.3-formats.md` § 7.

---

## Économie de tokens

- Image PNG crop de l'élément = en moyenne 30-80KB base64 (~10-30k tokens). À ne pas appeler 50 fois pour rien.
- Report texte seul = ~300-800 tokens.
- **Bon réflexe** : appel `verify_element` après chaque batch_add notable (= toutes les 5-10 minutes de construction), pas après chaque `update_element` trivial (changement de couleur).

---

*Outil ajouté en v3.7 plugin / v3.6 MCP server. Issu d'observations : les chats sautaient la vérification et accumulaient les bugs.*
