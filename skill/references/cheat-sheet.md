# ⚡ REFERENCE - Bricks MCP Cheat Sheet

> Quick Reference pour travailler efficacement avec Bricks Builder via MCP.

---

## 🎯 Arbre de Décision Rapide

```
Que dois-je faire ?

├─ Lister pages         → list_bricks_pages          (~100 tokens)
├─ Voir structure       → get_page_structure         (~200 tokens)
├─ Chercher élément     → find_elements              (~80 tokens)
├─ Modifier 1 élément   → update_element             (~50 tokens)
├─ Ajouter 1 élément    → add_element                (~100 tokens)
├─ Créer section        → batch_add                  (~500 tokens)
├─ Réorganiser ordre    → reorder_sections           (~100 tokens)
├─ Refonte totale       → get_page_json + update     (~2500 tokens)
└─ Vérifier visuel      → screenshot ou script JS
```

---

## 📊 Tableau de Décision par Tâche

| Tâche | Outil(s) | Tokens |
|-------|----------|--------|
| Lister pages | `list_bricks_pages` | 100 |
| Voir structure | `get_page_structure` | 200 |
| Chercher boutons | `find_elements` | 80 |
| Changer 1 couleur | `find_elements` + `update_element` | 130 |
| Changer 5 couleurs | `find_elements` + 5× `update_element` | 330 |
| Ajouter 1 bouton | `add_element` | 100 |
| Créer section (5-10 éléments) | `batch_add` | 500 |
| Créer header + placer en 1er | `batch_add` + `reorder_sections` | 600 |
| Créer page complète | 3-5× `batch_add` | 1500 |
| Refonte totale | `get_page_json` + `update_page_json` | 2500 |

---

## ⚠️ FORMATS VALIDÉS SUR CETTE INSTALLATION

**IMPORTANT** : Les snippets ci-dessous utilisent les formats **validés par observation réelle** sur cette installation Bricks.

**Règles critiques** :
- `"font-size": "44"` → STRING (pas `{ "size": "44", "unit": "px" }`)
- `_gap` + `_columnGap` + `_rowGap` → TOUJOURS ensemble
- `line-height` → OBJET `{ "size": "1.5", "unit": "" }` accepté

**En cas de doute** : Consulter [BRICKS-BUILDER-GUIDE_UPDATED.md](BRICKS-BUILDER-GUIDE_UPDATED.md) section "⚡ RÈGLES ISSUES DE L'OBSERVATION RÉELLE"

---

## 🎨 Snippets Courants

### Bouton CTA

```json
{
  "id": "cta_btn",
  "name": "button",
  "parent": "container_id",
  "children": [],
  "settings": {
    "text": "Prendre Rendez-vous",
    "link": { "url": "#contact", "newTab": false },
    "_background": { "color": { "hex": "#ff6b35" } },
    "_typography": {
      "font-size": "18",
      "font-weight": "700",
      "color": { "hex": "#ffffff" }
    },
    "_padding": {
      "top": "18",
      "bottom": "18",
      "left": "48",
      "right": "48"
    },
    "_borderRadius": {
      "top-left": "8px",
      "top-right": "8px",
      "bottom-right": "8px",
      "bottom-left": "8px"
    }
  }
}
```

---

### Section Hero

```json
{
  "id": "hero_sec",
  "name": "section",
  "parent": 0,
  "children": ["hero_cont"],
  "settings": {
    "tag": "section",
    "_cssId": "hero",
    "_padding": {
      "top": "120",
      "bottom": "120"
    },
    "_padding:mobile_portrait": {
      "top": "80",
      "bottom": "80"
    },
    "_background": {
      "color": { "hex": "#1a1a1a" }
    },
    "_justifyContent": "center",
    "_alignItems": "center"
  }
}
```

---

### Container Standard

```json
{
  "id": "main_cont",
  "name": "container",
  "parent": "section_id",
  "children": [],
  "settings": {
    "tag": "div",
    "_width": "100%",
    "_maxWidth": "1200px",
    "_margin": {
      "left": "auto",
      "right": "auto"
    },
    "_padding": {
      "left": "24",
      "right": "24"
    }
  }
}
```

---

### Titre H1

```json
{
  "id": "main_h1",
  "name": "heading",
  "parent": "container_id",
  "children": [],
  "settings": {
    "text": "Garage Saint-Marcel - Expert Auto Toutes Marques",
    "tag": "h1",
    "_typography": {
      "font-size": "56",
      "font-weight": "700",
      "color": { "hex": "#ffffff" },
      "line-height": { "size": "1.2", "unit": "" }
    },
    "_typography:mobile_portrait": {
      "font-size": "36"
    },
    "_margin": {
      "bottom": "24"
    }
  }
}
```

---

### Card (Carte Service)

```json
{
  "id": "service_card",
  "name": "div",
  "parent": "grid_container",
  "children": [],
  "settings": {
    "tag": "div",
    "_cssClasses": "card",
    "_cssCustom": ".card { transition: all 0.3s ease; } .card:hover { transform: translateY(-8px); box-shadow: 0 20px 25px rgba(0,0,0,0.15); }",
    "_padding": {
      "top": "40",
      "bottom": "40",
      "left": "32",
      "right": "32"
    },
    "_background": { "color": { "hex": "#ffffff" } },
    "_borderRadius": {
      "top-left": "12px",
      "top-right": "12px",
      "bottom-right": "12px",
      "bottom-left": "12px"
    },
    "_boxShadow": {
      "horizontal": "0",
      "vertical": "4",
      "blur": "24",
      "color": "rgba(0,0,0,0.08)"
    }
  }
}
```

---

### Grid 3 Colonnes

```json
{
  "id": "grid_3col",
  "name": "div",
  "parent": "container_id",
  "children": ["card1", "card2", "card3"],
  "settings": {
    "_display": "grid",
    "_gridTemplateColumns": "repeat(3, 1fr)",
    "_gridTemplateColumns:tablet_portrait": "1fr",
    "_gap": { "size": "32", "unit": "px" },
    "_columnGap": "32",
    "_rowGap": "32"
  }
}
```

---

### Image Optimisée

```json
{
  "id": "hero_img",
  "name": "image",
  "parent": "container_id",
  "children": [],
  "settings": {
    "image": {
      "url": "garage-facade.webp",
      "alt": "Façade du Garage Saint-Marcel avec enseigne lumineuse"
    },
    "lazyLoad": true,
    "_width": "100%",
    "_aspectRatio": "16/9"
  }
}
```

---

### Icônes Réseaux Sociaux

```json
{
  "id": "footer_social",
  "name": "social-icons",
  "parent": "footer_container",
  "children": [],
  "settings": {
    "icons": [
      {
        "icon": "fab fa-facebook-f",
        "link": "https://facebook.com/votre-page",
        "text": "Facebook"
      },
      {
        "icon": "fab fa-instagram",
        "link": "https://instagram.com/votre-compte",
        "text": "Instagram"
      },
      {
        "icon": "fab fa-linkedin-in",
        "link": "https://linkedin.com/company/votre-entreprise",
        "text": "LinkedIn"
      }
    ],
    "_display": "flex",
    "_direction": "row",
    "_gap": { "size": "16", "unit": "px" },
    "_columnGap": "16",
    "_rowGap": "16",
    "_typography": {
      "font-size": "24",
      "color": { "hex": "#ffffff" }
    },
    "_cssCustom": ".brxe-social-icons a { transition: all 0.3s ease; } .brxe-social-icons a:hover { color: #ff6b35; transform: scale(1.1); }"
  }
}
```

---

## 🎨 Palettes de Couleurs Prêtes

### Garage Auto
```
Primaire   : #1a1a1a (noir profond)
Secondaire : #4a5568 (gris bleuté)
Accent     : #ff6b35 (orange vif)
Neutres    : #ffffff, #f7fafc
```

### SaaS Tech
```
Primaire   : #0066cc (bleu)
Secondaire : #2d3748 (gris foncé)
Accent     : #10b981 (vert)
Neutres    : #ffffff, #f9fafb
```

### Restaurant
```
Primaire   : #8b4513 (marron)
Secondaire : #2c1810 (marron foncé)
Accent     : #d4af37 (or)
Neutres    : #ffffff, #faf8f5
```

### E-commerce Mode
```
Primaire   : #000000 (noir)
Secondaire : #9ca3af (gris moyen)
Accent     : #ef4444 (rouge)
Neutres    : #ffffff, #f3f4f6
```

### Médical / Santé
```
Primaire   : #0891b2 (cyan)
Secondaire : #164e63 (cyan foncé)
Accent     : #22c55e (vert)
Neutres    : #ffffff, #f0fdfa
```

### Immobilier
```
Primaire   : #1e3a5f (bleu marine)
Secondaire : #64748b (gris ardoise)
Accent     : #f59e0b (ambre)
Neutres    : #ffffff, #f8fafc
```

---

## 📏 Système d'Espacement (Multiple de 8)

```
8px   - Micro (entre texte et icône)
16px  - Petit (entre éléments proches)
24px  - Moyen (entre groupes)
32px  - Grand (séparation de contenu)
48px  - Énorme (séparation forte)
64px  - Section padding vertical (petit)
80px  - Section padding vertical (moyen)
120px - Section padding vertical (grand)
```

**Règle : TOUJOURS des multiples de 8px**

---

## 📐 Tailles Typographie

### Desktop
```
H1   : 48-72px, weight 700-900
H2   : 32-48px, weight 600-700
H3   : 24-32px, weight 600
Body : 16-18px, weight 400
Line-height : 1.4-1.8
```

### Mobile
```
H1   : 32-40px
H2   : 24-32px
H3   : 20-24px
Body : 16px (jamais moins)
```

---

## 🔧 Propriétés Settings Fréquentes

### Typography
```json
{
  "_typography": {
    "font-family": "Poppins, sans-serif",
    "font-size": "18",
    "font-weight": "400",
    "color": { "hex": "#1a1a1a" },
    "line-height": { "size": "1.6", "unit": "" }
  }
}
```

### Padding/Margin
```json
{
  "_padding": {
    "top": "80",
    "right": "24",
    "bottom": "80",
    "left": "24"
  },
  "_margin": {
    "top": "0",
    "right": "auto",
    "bottom": "0",
    "left": "auto"
  }
}
```

### Background
```json
{
  "_background": {
    "color": { "hex": "#1a1a1a" }
  }
}

// Avec image
{
  "_background": {
    "image": {
      "url": "image.jpg",
      "size": "cover",
      "position": "center center"
    }
  }
}

// Avec gradient
{
  "_background": {
    "gradient": {
      "type": "linear",
      "angle": 135,
      "stops": [
        { "color": "#0066CC", "position": 0 },
        { "color": "#00509E", "position": 100 }
      ]
    }
  }
}
```

### Border
```json
{
  "_border": {
    "style": "solid",
    "width": { "top": "2px", "right": "2px", "bottom": "2px", "left": "2px" },
    "color": { "hex": "#ff6b35" }
  },
  "_borderRadius": {
    "top-left": "8px",
    "top-right": "8px",
    "bottom-right": "8px",
    "bottom-left": "8px"
  }
}
```

### Flexbox
```json
{
  "_display": "flex",
  "_direction": "row",
  "_gap": { "size": "32", "unit": "px" },
  "_justifyContent": "center",
  "_alignItems": "center",
  "_flexWrap": "wrap"
}
```

### Grid (⚠️ avec columnGap/rowGap)
```json
{
  "_display": "grid",
  "_gridTemplateColumns": "repeat(3, 1fr)",
  "_gap": { "size": "32", "unit": "px" },
  "_columnGap": "32",
  "_rowGap": "32"
}
```

---

## 🔍 Types d'Éléments Bricks

### Structure
```
section       - Section principale (parent: 0)
container     - Container avec max-width
div           - Div générique
block         - Bloc (utilisé dans nav)
```

### Contenu
```
heading       - Titre H1-H6 (settings.tag: "h1")
text-basic    - Paragraphe/span
text-link     - Lien textuel
rich-text     - Éditeur riche
image         - Image
icon          - Icône
video         - Vidéo
```

### Navigation
```
nav-nested    - Navigation avec burger intégré
dropdown      - Menu déroulant
```

### Interactifs
```
button        - Bouton
form          - Formulaire
slider        - Carrousel
tabs          - Onglets
accordion     - Accordéon
```

### Spéciaux
```
code          - Code HTML/CSS/JS personnalisé
```

---

## ⚡ Commandes Rapides

### Trouver tous les boutons
```javascript
find_elements({ pageId: 640, criteria: { type: "button" } })
```

### Trouver headings dans une section
```javascript
find_elements({ pageId: 640, criteria: { type: "heading", parent: "section_id" } })
```

### Chercher texte spécifique
```javascript
find_elements({ pageId: 640, criteria: { hasText: "garage" } })
```

### Modifier couleur d'un élément
```javascript
update_element({
  pageId: 640,
  elementId: "btn123",
  newSettings: {
    "_typography": { "color": { "hex": "#0066cc" } }
  }
})
```

### Réorganiser sections (header en premier)
```javascript
reorder_sections({
  pageId: 640,
  orderedIds: ["header_pro", "hero_section", "services_section", "footer"]
})
```

### Générer ID unique
```javascript
function generateId() {
  return Math.random().toString(36).substr(2, 6);
}
```

---

## 🎯 Breakpoints Responsive

| Suffixe | Device | Largeur |
|---------|--------|---------|
| (aucun) | Desktop | > 1024px |
| `:tablet_landscape` | Tablet paysage | ≤ 1024px |
| `:tablet_portrait` | Tablet portrait | ≤ 991px |
| `:mobile_landscape` | Mobile paysage | ≤ 768px |
| `:mobile_portrait` | Mobile portrait | ≤ 478px |

**Exemple :**
```json
{
  "_padding": { "top": "120", "bottom": "120" },
  "_padding:tablet_portrait": { "top": "80", "bottom": "80" },
  "_padding:mobile_portrait": { "top": "60", "bottom": "60" }
}
```

---

## 🎯 SEO Quick Checklist

### On-Page
- [ ] 1 H1 unique avec mot-clé principal
- [ ] Hiérarchie Hn respectée (H1 > H2 > H3)
- [ ] Title 50-60 caractères
- [ ] Meta description 150-160 caractères
- [ ] Images alt text descriptifs
- [ ] Images WebP + lazy load
- [ ] Schema markup implémenté

### Technical
- [ ] LCP < 2.5s
- [ ] Mobile responsive
- [ ] HTTPS
- [ ] Sitemap.xml

---

## 💡 Formules Gagnantes

### Title Tag
```
[Mot-clé] + [Valeur unique] + [Localisation] + [Emoji]
```

### Meta Description
```
[Localisation] + [Services] + [USP] + [CTA] + [Contact]
```

### H1
```
[Entreprise] + [Activité] + [Localisation] + [Différenciateur]
```

**Exemple :**
```
H1 : "Garage Saint-Marcel - Expert Auto Toutes Marques | 20 Ans"
```

---

## 📋 Règles d'Or (Toujours)

1. ✅ Utilise l'outil le PLUS LÉGER possible
2. ✅ TOUJOURS `find_elements` avant `update_element`
3. ✅ IDs uniques (6 caractères) pour nouveaux éléments
4. ✅ Vérification après modification importante
5. ✅ Multiples de 8px pour espacement
6. ✅ 1 seul H1 par page
7. ✅ Schema markup sur toutes les pages
8. ✅ Images WebP + alt text
9. ✅ Vérifier Desktop ET Mobile
10. ✅ `_gap` + `_columnGap` + `_rowGap` ensemble pour grids

---

**Cette cheat sheet = gain de temps maximal !** ⚡
