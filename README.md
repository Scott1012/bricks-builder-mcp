# Bricks Builder MCP

Serveur **Model Context Protocol (MCP)** qui permet à Claude (Desktop, Cowork, Claude Code) de **piloter Bricks Builder** sur un site WordPress : créer des pages, ajouter des sections, modifier des éléments, uploader des images optimisées, vérifier visuellement le rendu, etc.

💬 **Communauté Discord** : [https://discord.gg/rX22zHRzH](https://discord.gg/rX22zHRzH) — discussions Bricks + IA, partage de builds, signalement de bugs.

---

## Comment ça marche

```
Claude (Desktop / Cowork / Code)
    ↓ MCP (stdio)
bricks-builder-mcp  ← ce package npm, lancé par Claude
    ↓ HTTPS + clé API
WordPress + plugin "Bricks Builder MCP Server"
    ↓
Tes pages Bricks Builder
```

Tu installes **2 choses** :
1. Le plugin WordPress (sur ton site)
2. Le serveur MCP (ce package npm, lancé automatiquement par Claude)

---

## Installation — La méthode recommandée (Cowork, 1 clic)

C'est la méthode **complète et la plus simple**. Tu obtiens : les outils MCP **+ le skill embarqué** (doc, règles d'or, patterns Bricks 2.3, cheat-sheets, etc.) — Claude est immédiatement opérationnel.

1. **Installe le plugin WordPress** : télécharge le `.zip` depuis les **[Releases GitHub](https://github.com/Scott1012/bricks-builder-mcp/releases)** → WP admin → Extensions → Ajouter → Téléverser → Activer
2. Dans WP admin → **Bricks MCP** → clique sur **"Télécharger pour Claude"**. Tu reçois un fichier `.plugin` qui contient tout : config MCP (URL + clé API auto-générée) + skill + doc
3. Glisse ce `.plugin` dans la fenêtre Cowork → Activate

C'est fini. Aucun terminal, aucun JSON à éditer.

### (Optionnel mais recommandé) Activer `verify_element`

Dans un terminal, une seule fois :

```bash
npx playwright install chromium
```

Ça télécharge Chromium (~130 Mo) pour que Claude puisse prendre des screenshots de tes pages et vérifier le rendu visuellement.

### Test final

Demande à Claude : *"Liste les pages Bricks de mon site"*. Si ça répond, tout marche.

---

## Méthode alternative — Claude Desktop / Claude Code (config manuelle)

> ⚠️ **À savoir** : cette méthode te donne accès **aux outils MCP** mais pas au **skill** (doc + règles d'or + patterns). Claude doit deviner les bonnes pratiques sans la doc, ce qui multiplie les erreurs en construction.
>
> **Recommandé** : utilise plutôt la méthode Cowork ci-dessus, ou bien clone le repo skill localement (voir plus bas).

1. **Installe le plugin WordPress** : pareil que ci-dessus (étape 1 de la méthode recommandée)
2. **Génère ta clé API** : WP admin → **Bricks MCP** → "Générer une clé API" → copie-la (`bricks_...`)
3. **Édite ton `claude_desktop_config.json`** (Cmd+, dans Claude Desktop → Developer → Edit Config) :

```json
{
  "mcpServers": {
    "bricks-mcp": {
      "command": "npx",
      "args": ["-y", "bricks-builder-mcp"],
      "env": {
        "WORDPRESS_URL": "https://ton-site.com",
        "API_KEY": "bricks_xxxxxxxx"
      }
    }
  }
}
```

4. **Redémarre Claude Desktop**.

### Pour récupérer le skill manuellement (Claude Code uniquement)

```bash
curl -L https://github.com/Scott1012/bricks-builder-mcp/archive/main.tar.gz | tar -xz -C /tmp && \
  mkdir -p ~/.claude/skills && \
  cp -r /tmp/bricks-builder-mcp-main/skill ~/.claude/skills/bricks-builder
```

Claude Code détectera automatiquement le skill au prochain démarrage.

---

## Variables d'environnement

| Variable | Requis | Description |
|---|---|---|
| `WORDPRESS_URL` | oui | URL de ton site (ex : `https://mon-site.com`) |
| `API_KEY` | oui | Clé API (générée à l'étape 2) |
| `INSECURE_SSL` | non | `true` pour ignorer un certificat SSL invalide. **Pré-prod uniquement.** |

---

## Outils disponibles (résumé)

**Pages & structure**
- `list_bricks_pages`, `get_page_structure`, `get_page_json`, `update_page_json`
- `create_page`, `delete_page`, `update_page_meta`, `duplicate_page`, `set_homepage`

**Éléments**
- `find_elements`, `get_element`, `update_element` (avec param `label` pour renommer dans le builder), `add_element`, `batch_add`, `delete_element`, `reorder_sections`

**⭐ Vérification visuelle (v3.10+)**
- `verify_element` — screenshot crop + report. **À utiliser après chaque batch_add significatif.**
  - Compare computed styles vs settings attendus, fonts, erreurs JS, débordement horizontal
  - **v3.10 — Multi-viewport en 1 call** : `viewports: ["desktop", "mobile_portrait"]` → 1 report + 1 screenshot par viewport
  - **v3.10 — Cohérence siblings** : text-align mixé entre frères, jumps font-size anormaux
  - **v3.10 — Containers vides anormaux** : tout bloc ≥ 50×50px sans contenu (attrape les wrappers écrasés par `align-items: stretch`)
  - **v3.10 — Santé des médias** : `naturalWidth > 0` sur les `<img>` (lazy-load cassé), `readyState ≥ 2` sur les `<video>`, alt présents
  - Chaque check porte une `severity` (`critical` / `warning` / `info`) et un `hint` actionnable. Désactivable par catégorie via `checks: { sibling_coherence: false }`.

**⭐ Audit page entière (v3.11+)**
- `audit_page` — fullpage screenshot **avec annotations dessinées** + report consolidé. Complète `verify_element` (qui est ciblé sur 1 élément).
  - Scanne TOUS les éléments Bricks d'une page d'un coup
  - Dessine des cadres colorés (rouge=critical, orange=warning, jaune=info) sur les zones problématiques directement sur le screenshot
  - Multi-viewport en 1 call (par défaut `["desktop", "mobile_portrait"]`)
  - Idéal pour : état initial avant refonte, démo client, audit après une grosse vague de modifs
  - Checks intégrés : containers vides, images cassées + alt manquants, text-align mixé, débordement horizontal global de la page

**⭐ Upload optimisé (v3.8+)**
- `upload_local_file`, `upload_local_files_batch` — l'IA donne juste le path local, le MCP server lit le fichier, conversion WebP automatique (-80 à -95% de poids), renommage SEO via le `title`
- `upload_media`, `upload_media_batch` (accepte URL HTTP/HTTPS **ou** data URI)
- `list_media`

**Design system Bricks natif**
- `set_custom_code`, `get_custom_code` (CSS + scripts header/footer)
- `register_google_font_locally`, `list_custom_fonts`
- `list_color_palette`, `add_color_to_palette`
- `list_typography_scales`, `set_typography_scale`, `list_spacing_scales`, `set_spacing_scale`
- `list_global_classes`, `create_global_class`, `update_global_class`
- `list_theme_styles`, `create_theme_style`

**Menu, médias, navigation**
- `list_menus`, `add_menu_item`, `list_all_pages`

**Code & sécurité**
- `set_code_execution`, `get_code_execution_status`
- `set_page_custom_code`, `get_page_custom_code`

**Feedback (⭐ v3.7+)**
- `report_missing_feature` — si Bricks supporte une feature nativement mais que le MCP ne l'expose pas, le chat AI le remonte pour qu'on l'ajoute
- `list_missing_features`, `resolve_missing_feature`

Doc complète + cheat-sheets + patterns dans le skill `bricks-builder` embarqué dans le plugin WP.

---

## Mise à jour

Le plugin WP a un système d'auto-update via GitHub Releases. WP admin → Extensions → "Vérifier les mises à jour" → update.

Le serveur MCP se met à jour automatiquement au prochain lancement de Claude Desktop (grâce à `npx -y`).

---

## Aide

- Bug ou question → [Discord](https://discord.gg/rX22zHRzH)
- Issue technique → [GitHub Issues](https://github.com/Scott1012/bricks-builder-mcp/issues)

---

## Licence

MIT
