# 🎨 SKILL: Design Web Professionnel pour Bricks Builder

## 🎯 Objectif de ce Skill

Créer des sites web **visuellement professionnels** avec Bricks Builder, pas juste fonctionnels.

**Niveau cible : 9/10 en design**

---

## 📏 Règles d'Or du Design Web

### Principe #1 : Hiérarchie Visuelle

**La hiérarchie guide l'œil de l'utilisateur.**

```
GROS = Important
petit = secondaire
```

**Application :**
- H1 (titre principal) : 48-72px, ultra visible
- H2 (sections) : 32-48px, visible
- H3 (sous-sections) : 24-32px, lisible
- Body (texte courant) : 16-18px, confortable
- Small (mentions légales) : 14px, discret

**❌ ERREUR COURANTE :**
```
H1 : 32px
H2 : 28px  ← Trop proche, pas de hiérarchie claire
```

**✅ BON :**
```
H1 : 56px  
H2 : 36px  ← Différence nette = hiérarchie claire
H3 : 24px
```

---

### Principe #2 : Espacement (Whitespace)

**L'espace vide est ton ami. Ne remplis pas tout.**

#### Système d'Espacement (Multiple de 8)

```
8px   - Micro (entre texte et icône)
16px  - Petit (entre éléments proches)
24px  - Moyen (entre groupes d'éléments)
32px  - Grand (séparation de contenu)
48px  - Énorme (séparation forte)
64px  - Section padding vertical (petit)
80px  - Section padding vertical (moyen)
120px - Section padding vertical (grand)
```

**Règle : Utilise TOUJOURS des multiples de 8px**

**❌ ERREUR :**
```json
{
  "_padding": {
    "top": {"size": "35", "unit": "px"},    ← 35px n'est pas un multiple de 8
    "bottom": {"size": "67", "unit": "px"}  ← 67px non plus
  }
}
```

**✅ BON :**
```json
{
  "_padding": {
    "top": {"size": "32", "unit": "px"},    ← 32 = 8×4
    "bottom": {"size": "64", "unit": "px"}  ← 64 = 8×8
  }
}
```

---

### Principe #3 : Typographie

**Maximum 2 polices par site. Pas plus.**

#### Combinaisons Gagnantes

**Option 1 : Moderne & Clean**
- Titres : **Inter** (ou Montserrat)
- Texte : **Inter** (même police, poids différents)

**Option 2 : Élégant & Pro**
- Titres : **Playfair Display** (serif)
- Texte : **Source Sans Pro** (sans-serif)

**Option 3 : Tech & Moderne**
- Titres : **Poppins**
- Texte : **Poppins** (poids lighter)

#### Tailles & Poids Recommandés

```
H1 : 48-72px, font-weight: 700-900
H2 : 32-48px, font-weight: 600-700
H3 : 24-32px, font-weight: 600
Body : 16-18px, font-weight: 400
Line-height : 1.5-1.8 (jamais moins de 1.4)
```

**❌ ERREUR :**
```json
{
  "fontSize": {"size": "16", "unit": "px"},
  "lineHeight": "1.0"  ← Texte étouffé, illisible
}
```

**✅ BON :**
```json
{
  "fontSize": {"size": "18", "unit": "px"},
  "lineHeight": "1.6"  ← Respire, agréable à lire
}
```

---

### Principe #4 : Couleurs (Palette)

**Maximum 3-4 couleurs principales + neutres**

#### Système de Couleurs

```
Couleur Primaire (60%) : Ton identité (ex: bleu)
Couleur Secondaire (30%) : Complément (ex: gris foncé)
Couleur Accent (10%) : Call-to-actions (ex: orange)
Neutres : Blanc, gris clair, gris foncé, noir
```

#### Exemples de Palettes Pro

**Garage Auto (exemple) :**
```
Primaire : #1a1a1a (noir profond) - 60%
Secondaire : #4a5568 (gris bleuté) - 30%
Accent : #ff6b35 (orange vif) - 10%
Neutres : #ffffff, #f7fafc, #2d3748
```

**SaaS Tech :**
```
Primaire : #0066cc (bleu)
Secondaire : #2d3748 (gris foncé)
Accent : #10b981 (vert)
Neutres : #ffffff, #f9fafb, #111827
```

**Restaurant :**
```
Primaire : #8b4513 (marron)
Secondaire : #2c1810 (marron foncé)
Accent : #d4af37 (or)
Neutres : #ffffff, #faf8f5, #1a0f0a
```

#### Contraste Minimum (WCAG)

```
Texte normal : 4.5:1
Texte large (>18px) : 3:1
Boutons/CTAs : 4.5:1
```

**Vérifie toujours sur : https://webaim.org/resources/contrastchecker/**

---

### Principe #5 : Layout (Mise en Page)

#### Container Principal

```json
{
  "name": "container",
  "settings": {
    "_width": "100%",
    "_maxWidth": {"size": "1200", "unit": "px"},  ← Jamais plus large
    "_margin": {
      "left": "auto",
      "right": "auto"
    },
    "_padding": {
      "left": {"size": "24", "unit": "px"},
      "right": {"size": "24", "unit": "px"}
    }
  }
}
```

#### Grid System (12 colonnes)

**3 colonnes égales :**
```json
{
  "_direction": "row",
  "_gap": {"size": "32", "unit": "px"},
  "_justifyContent": "space-between"
}
// Chaque enfant : width 31% (ou flex: 1)
```

**2 colonnes (60/40) :**
```json
// Enfant 1 : width 58%
// Enfant 2 : width 38%
// Gap : 4% (ou 32px)
```

---

## 🎨 Patterns de Sections Pro

### Hero Section (Version Moderne)

```json
{
  "id": "hero",
  "name": "section",
  "settings": {
    "_height": "100vh",  // Plein écran
    "_padding": {
      "top": {"size": "120", "unit": "px"},
      "bottom": {"size": "120", "unit": "px"}
    },
    "_background": {
      "color": {"hex": "#1a1a1a"},
      "image": {
        "url": "...",
        "overlay": {
          "color": {"hex": "#000000"},
          "opacity": 0.5
        }
      }
    },
    "_justifyContent": "center",
    "_alignItems": "center"
  }
}
```

**Contenu Hero :**
```
1. H1 (56-72px, bold, blanc, centré)
2. Sous-titre (20-24px, léger, gris clair)
3. CTA Primaire (bouton large, couleur accent)
4. CTA Secondaire (optionnel, outline)
```

---

### Cards Section (Services/Features)

```json
{
  "name": "section",
  "settings": {
    "_padding": {
      "top": {"size": "80", "unit": "px"},
      "bottom": {"size": "80", "unit": "px"}
    },
    "_background": {
      "color": {"hex": "#f7fafc"}  // Gris très clair
    }
  }
}
```

**Grid 3 Cards :**
```json
{
  "name": "container",
  "settings": {
    "_direction": "row",
    "_gap": {"size": "32", "unit": "px"},
    "_flexWrap": "wrap"
  }
}
```

**Chaque Card :**
```json
{
  "name": "div",
  "settings": {
    "_width": "calc(33.33% - 22px)",  // 3 colonnes
    "_padding": {
      "top": {"size": "40", "unit": "px"},
      "bottom": {"size": "40", "unit": "px"},
      "left": {"size": "32", "unit": "px"},
      "right": {"size": "32", "unit": "px"}
    },
    "_background": {
      "color": {"hex": "#ffffff"}
    },
    "_border": {
      "radius": {
        "top": {"size": "12", "unit": "px"},
        "right": {"size": "12", "unit": "px"},
        "bottom": {"size": "12", "unit": "px"},
        "left": {"size": "12", "unit": "px"}
      }
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

### CTA Section (Call-to-Action)

```json
{
  "name": "section",
  "settings": {
    "_padding": {
      "top": {"size": "100", "unit": "px"},
      "bottom": {"size": "100", "unit": "px"}
    },
    "_background": {
      "color": {"hex": "#0066cc"}  // Couleur primaire
    },
    "_textAlign": "center"
  }
}
```

**Contenu :**
```
1. H2 (40-48px, blanc, bold)
2. Paragraphe court (18px, blanc avec opacité 0.9)
3. Gros bouton (accent ou blanc)
```

---

## 🔘 Composants Essentiels

### Bouton Primaire (CTA)

```json
{
  "name": "button",
  "settings": {
    "text": "Prendre Rendez-vous",
    "_background": {
      "color": {"hex": "#ff6b35"}  // Couleur accent
    },
    "_typography": {
      "fontSize": {"size": "18", "unit": "px"},
      "fontWeight": "700",
      "color": {"hex": "#ffffff"}
    },
    "_padding": {
      "top": {"size": "18", "unit": "px"},
      "bottom": {"size": "18", "unit": "px"},
      "left": {"size": "48", "unit": "px"},
      "right": {"size": "48", "unit": "px"}
    },
    "_border": {
      "radius": {
        "top": {"size": "8", "unit": "px"},
        "right": {"size": "8", "unit": "px"},
        "bottom": {"size": "8", "unit": "px"},
        "left": {"size": "8", "unit": "px"}
      }
    },
    "_transition": "all 0.3s ease",
    "_hover": {
      "_background": {
        "color": {"hex": "#e55a2b"}  // 10% plus foncé
      },
      "_transform": "translateY(-2px)",
      "_boxShadow": "0 8px 16px rgba(0,0,0,0.2)"
    }
  }
}
```

### Bouton Secondaire (Outline)

```json
{
  "name": "button",
  "settings": {
    "_background": "transparent",
    "_border": {
      "style": "solid",
      "width": "2px",
      "color": {"hex": "#ff6b35"}
    },
    "_typography": {
      "color": {"hex": "#ff6b35"}
    }
  }
}
```

---

## 📱 Responsive Design

### Breakpoints Standards

```
Mobile : < 768px
Tablet : 768px - 991px
Desktop : > 991px
```

### Règles Responsive

**Texte :**
```
Mobile : H1 36-48px, H2 28px, Body 16px
Desktop : H1 56-72px, H2 40px, Body 18px
```

**Padding :**
```
Mobile : Sections 48-64px vertical
Desktop : Sections 80-120px vertical
```

**Grid :**
```
Mobile : 1 colonne (empilé)
Tablet : 2 colonnes
Desktop : 3-4 colonnes
```

**Exemple JSON :**
```json
{
  "_width": "100%",
  "_widthTablet": "48%",
  "_widthDesktop": "31%"
}
```

---

## ✅ Checklist Design (Avant de Livrer)

### Typographie
- [ ] Maximum 2 polices différentes
- [ ] H1 unique par page
- [ ] Hiérarchie H1 > H2 > H3 respectée
- [ ] Line-height minimum 1.4
- [ ] Tailles cohérentes (multiples de 4 ou 8)

### Couleurs
- [ ] Palette définie (3-4 couleurs max)
- [ ] Contraste minimum 4.5:1 (texte)
- [ ] Couleur accent utilisée pour CTAs
- [ ] Pas plus de 5 couleurs différentes sur la page

### Espacement
- [ ] Multiples de 8px partout
- [ ] Sections : 80-120px padding vertical
- [ ] Cartes : 32-48px padding interne
- [ ] Gap entre éléments : 24-32px minimum

### Layout
- [ ] Container max-width 1200px
- [ ] Grids équilibrées (pas de colonnes bizarres)
- [ ] Responsive testé (mobile, tablet, desktop)
- [ ] Pas d'overflow horizontal

### Contenu
- [ ] Textes courts et percutants
- [ ] Pas de Lorem Ipsum visible
- [ ] Images optimisées (WebP, < 500KB)
- [ ] Alt text sur toutes les images

### UX
- [ ] Boutons bien visibles (min 48×48px)
- [ ] Zone de clic suffisante
- [ ] États hover définis
- [ ] Navigation claire
- [ ] Formulaires avec labels

---

## 🎯 Exemples de Sites 9/10

### Garage Auto Moderne

**Structure :**
```
1. Hero (100vh, image voiture, H1 court, 1 CTA)
2. Services (3 cards, icônes, titres courts)
3. Pourquoi Nous (4 points clés, icônes)
4. Témoignages (3 avis, photos clients)
5. CTA Final (fond couleur, gros bouton)
6. Footer (infos, liens, map)
```

**Palette :**
```
Noir : #1a1a1a (dominant)
Gris : #4a5568 (textes secondaires)
Orange : #ff6b35 (CTAs, accents)
Blanc : #ffffff (fond sections alternées)
```

**Typo :**
```
Montserrat (titres, bold 700)
Open Sans (texte, regular 400)
```

---

## 🚫 Erreurs à ÉVITER Absolument

### 1. Trop de Couleurs
❌ 8 couleurs différentes sur une page
✅ 3-4 couleurs maximum

### 2. Textes Trop Longs
❌ "Votre garage automobile à Saint-Marcel vous accueille du lundi au samedi pour tous vos besoins en mécanique automobile. Expert toutes marques avec plus de 20 ans d'expérience au service de votre véhicule."
✅ "Expert auto toutes marques. +20 ans d'expérience."

### 3. Trop d'Éléments
❌ 10 sections sur la page d'accueil
✅ 5-6 sections maximum

### 4. Espaces Incohérents
❌ 35px, 67px, 43px, 91px
✅ 32px, 64px, 48px, 96px (multiples de 8)

### 5. Hiérarchie Cassée
❌ H1 32px, H2 30px, H3 28px (trop proche)
✅ H1 56px, H2 36px, H3 24px (claire)

### 6. Boutons Invisibles
❌ Bouton gris sur fond gris clair
✅ Bouton orange vif sur fond blanc

### 7. Responsive Oublié
❌ Desktop uniquement
✅ Mobile-first design

---

## 💡 Workflow de Création

### Étape 1 : Définir la Palette
```
Utilisateur : "Site pour garage auto"

Claude :
"Je propose cette palette :
- Noir profond #1a1a1a (dominant)
- Orange vif #ff6b35 (CTAs)
- Gris #4a5568 (secondaire)
- Blanc #ffffff (fond)

Validation ?"
```

### Étape 2 : Choisir les Polices
```
"Je propose :
- Titres : Montserrat (moderne, bold)
- Texte : Open Sans (lisible, pro)

OK ?"
```

### Étape 3 : Créer Section par Section
```
"Je crée le Hero :
- H1 : 56px, Montserrat Bold, blanc
- Sous-titre : 20px, Open Sans, gris clair
- Bouton : Orange, 18px, padding 18×48px
- Padding section : 120px vertical

Screenshot : [lien]

Valides-tu ?"
```

### Étape 4 : Vérifier et Itérer
```
Utilisateur : "Le bouton est trop petit"

Claude :
"Je l'agrandis :
- fontSize : 18px → 20px
- padding : 18×48px → 24×56px

Screenshot : [lien]"
```

---

## 🎓 Ressources pour Aller Plus Loin

**Inspiration Design :**
- Awwwards.com (sites primés)
- Dribbble.com (UI/UX)
- Behance.net (portfolios)

**Palettes de Couleurs :**
- Coolors.co (générateur)
- ColorHunt.co (palettes tendances)

**Typographie :**
- Google Fonts (polices gratuites)
- FontPair.co (combinaisons)

**Outils :**
- WebAIM Contrast Checker (contraste)
- PageSpeed Insights (performance)

---

## ✨ Résumé en 10 Points

1. **Hiérarchie claire** : H1 grand, H2 moyen, Body petit
2. **Espacement généreux** : Multiples de 8px
3. **2 polices max** : Titres + Texte
4. **3-4 couleurs** : Primaire + Secondaire + Accent + Neutres
5. **Container 1200px** : Jamais plus large
6. **Padding sections** : 80-120px vertical
7. **Boutons visibles** : Couleur accent, gros padding
8. **Responsive** : Mobile, Tablet, Desktop
9. **Contraste** : Minimum 4.5:1
10. **Simplicité** : Moins c'est plus

---

**Applique ces règles = Site 9/10 garanti !** 🎉
