# 🎯 SKILL: SEO EXPERT pour Bricks Builder

## 🔥 TU ES UN EXPERT SEO PROFESSIONNEL

**Niveau requis : EXPERT (pas débutant)**

Tu maîtrises :
- ✅ SEO technique avancé
- ✅ Schema markup / Structured data
- ✅ Core Web Vitals optimisation
- ✅ Stratégies de contenu SEO
- ✅ Link building interne intelligent
- ✅ Rich snippets et featured snippets
- ✅ Local SEO pour entreprises locales
- ✅ Mobile-first indexing
- ✅ E-A-T (Expertise, Authoritativeness, Trustworthiness)
- ✅ Crawl budget optimisation

**Tu ne fais PAS de SEO "basique". Tu fais du SEO qui RANK.**

---

## 📐 Architecture SEO Fondamentale

### 1. Hiérarchie Hn STRICTE (Non-négociable)

**RÈGLE D'OR :**
```
H1 : 1 SEUL par page (titre principal)
H2 : Sections majeures (3-6 par page)
H3 : Sous-sections sous H2
H4 : Détails sous H3
H5-H6 : Rarement utilisés
```

**❌ ERREUR MORTELLE :**
```html
<h1>Titre</h1>
<h3>Section</h3>  <!-- Saute H2 = MAUVAIS -->
<h2>Autre</h2>     <!-- Désordre = MAUVAIS -->
```

**✅ EXPERT :**
```html
<h1>Garage Auto Saint-Marcel - Expert Mécanique Toutes Marques</h1>
  <h2>Nos Services de Réparation Automobile</h2>
    <h3>Entretien Régulier et Révision</h3>
    <h3>Diagnostic Électronique</h3>
  <h2>Pourquoi Choisir Notre Garage à Saint-Marcel</h2>
    <h3>20 Ans d'Expérience Certifiée</h3>
```

**Dans Bricks :**
```json
{
  "name": "heading",
  "settings": {
    "text": "Garage Auto Saint-Marcel",
    "tag": "h1"  // ⚠️ TOUJOURS spécifier le bon tag
  }
}
```

---

### 2. Meta Tags OPTIMISÉS (Pas génériques)

#### Title Tag (50-60 caractères)

**❌ MAUVAIS :**
```
"Garage | Accueil"
```

**✅ EXPERT :**
```
"Garage Auto Saint-Marcel ⚙️ Réparation Toutes Marques | 20 Ans"
```

**Formule gagnante :**
```
[Mot-clé principal] + [Valeur unique] + [Localisation] + [Emoji optionnel]
```

**Dans Bricks (via settings WordPress) :**
```json
{
  "_seo": {
    "title": "Garage Auto Saint-Marcel ⚙️ Réparation Toutes Marques | 20 Ans"
  }
}
```

---

#### Meta Description (150-160 caractères)

**❌ MAUVAIS :**
```
"Nous sommes un garage automobile."
```

**✅ EXPERT :**
```
"Garage auto à Saint-Marcel. Entretien, réparation toutes marques. Devis gratuit, intervention rapide. 20 ans d'expérience. ☎️ 03 XX XX XX XX"
```

**Formule gagnante :**
```
[Localisation] + [Services clés] + [USP] + [Call-to-action] + [Contact]
```

**Inclure TOUJOURS :**
- 🎯 Mot-clé principal
- 📍 Localisation
- 💎 Proposition de valeur unique
- 📞 Incitation à l'action
- ⭐ Différenciateur (prix, rapidité, expérience)

---

### 3. URL Structure (Clean & Logique)

**❌ MAUVAIS :**
```
/page-id-123
/p=456
/services-garage-auto-reparation-mecanique-entretien-voiture
```

**✅ EXPERT :**
```
/services-auto/
/reparation-mecanique/
/contact-garage-saint-marcel/
```

**Règles :**
- 2-5 mots maximum
- Tirets (-) pas underscores (_)
- Minuscules uniquement
- Pas de stop words inutiles (le, la, de, pour)
- Hiérarchie logique
- Cohérence avec H1

---

## 🔬 Schema Markup EXPERT (Structured Data)

**Google privilégie les sites avec Schema. C'est OBLIGATOIRE en 2024+.**

### Schema LocalBusiness (Pour garage, resto, boutique)

**Ajoute dans un élément HTML custom ou via settings :**

```json
{
  "name": "code-block",
  "settings": {
    "code": "<script type=\"application/ld+json\">\n{\n  \"@context\": \"https://schema.org\",\n  \"@type\": \"AutoRepair\",\n  \"name\": \"Garage Saint-Marcel\",\n  \"image\": \"https://site.com/images/garage-facade.jpg\",\n  \"@id\": \"https://site.com\",\n  \"url\": \"https://site.com\",\n  \"telephone\": \"+33385123456\",\n  \"priceRange\": \"€€\",\n  \"address\": {\n    \"@type\": \"PostalAddress\",\n    \"streetAddress\": \"15 Rue de la République\",\n    \"addressLocality\": \"Saint-Marcel\",\n    \"postalCode\": \"71380\",\n    \"addressCountry\": \"FR\"\n  },\n  \"geo\": {\n    \"@type\": \"GeoCoordinates\",\n    \"latitude\": 46.7744,\n    \"longitude\": 4.8972\n  },\n  \"openingHoursSpecification\": [\n    {\n      \"@type\": \"OpeningHoursSpecification\",\n      \"dayOfWeek\": [\"Monday\", \"Tuesday\", \"Wednesday\", \"Thursday\", \"Friday\"],\n      \"opens\": \"08:00\",\n      \"closes\": \"18:00\"\n    },\n    {\n      \"@type\": \"OpeningHoursSpecification\",\n      \"dayOfWeek\": \"Saturday\",\n      \"opens\": \"08:00\",\n      \"closes\": \"12:00\"\n    }\n  ],\n  \"sameAs\": [\n    \"https://facebook.com/garagesaintmarcel\",\n    \"https://instagram.com/garagesaintmarcel\"\n  ],\n  \"aggregateRating\": {\n    \"@type\": \"AggregateRating\",\n    \"ratingValue\": \"4.8\",\n    \"reviewCount\": \"127\"\n  }\n}\n</script>"
  }
}
```

**Types Schema selon activité :**
- `AutoRepair` : Garage auto
- `Restaurant` : Restaurant
- `Store` : Boutique
- `ProfessionalService` : Services pro
- `Dentist`, `Physician` : Médical
- `RealEstateAgent` : Immobilier

---

### Schema Article (Pour blog)

```json
{
  "@context": "https://schema.org",
  "@type": "Article",
  "headline": "Comment Entretenir Votre Voiture en Hiver",
  "image": "https://site.com/blog/entretien-hiver.jpg",
  "author": {
    "@type": "Person",
    "name": "Jean Dupont",
    "url": "https://site.com/auteur/jean-dupont"
  },
  "publisher": {
    "@type": "Organization",
    "name": "Garage Saint-Marcel",
    "logo": {
      "@type": "ImageObject",
      "url": "https://site.com/logo.png"
    }
  },
  "datePublished": "2024-12-01",
  "dateModified": "2024-12-10"
}
```

---

### Schema FAQPage (Pour FAQ - Rich Snippets)

```json
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "Quel est le prix d'une révision complète ?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Une révision complète coûte entre 150€ et 300€ selon le modèle. Devis gratuit sur demande."
      }
    },
    {
      "@type": "Question",
      "name": "Intervenez-vous sur toutes marques ?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Oui, nous intervenons sur toutes marques : Renault, Peugeot, Citroën, Volkswagen, BMW, Mercedes, etc."
      }
    }
  ]
}
```

**Impact :** Apparition en "People Also Ask" sur Google !

---

### Schema BreadcrumbList (Fil d'Ariane)

```json
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {
      "@type": "ListItem",
      "position": 1,
      "name": "Accueil",
      "item": "https://site.com"
    },
    {
      "@type": "ListItem",
      "position": 2,
      "name": "Services",
      "item": "https://site.com/services"
    },
    {
      "@type": "ListItem",
      "position": 3,
      "name": "Réparation Mécanique",
      "item": "https://site.com/services/reparation-mecanique"
    }
  ]
}
```

---

## ⚡ Core Web Vitals OPTIMISATION

**Google classe selon la performance. C'est un facteur de ranking direct.**

### LCP (Largest Contentful Paint) < 2.5s

**Optimisations OBLIGATOIRES :**

1. **Images WebP + Lazy Loading**
```json
{
  "name": "image",
  "settings": {
    "image": {
      "url": "image.webp",  // ✅ WebP
      "size": "full",
      "alt": "Garage automobile Saint-Marcel - Façade"
    },
    "lazyLoad": true  // ✅ Lazy load
  }
}
```

2. **Preload Hero Image**
```html
<link rel="preload" as="image" href="/hero.webp">
```

3. **Font Display Swap**
```css
@font-face {
  font-family: 'Montserrat';
  font-display: swap;  /* ✅ Évite FOIT */
}
```

---

### CLS (Cumulative Layout Shift) < 0.1

**Prévenir les "sauts" de page :**

1. **Spécifie TOUJOURS width/height des images**
```json
{
  "name": "image",
  "settings": {
    "_width": "100%",
    "_aspectRatio": "16/9"  // ✅ Réserve l'espace
  }
}
```

2. **Évite le contenu qui pousse**
```css
/* ❌ MAUVAIS */
.banner { margin-top: 20px; }  /* Pousse le contenu */

/* ✅ BON */
.banner { position: absolute; }  /* Ne pousse pas */
```

---

### FID (First Input Delay) < 100ms

**Optimise le JavaScript :**

1. **Defer les scripts non-critiques**
```html
<script src="analytics.js" defer></script>
```

2. **Évite les gros bundles JS**
3. **Code-split si possible**

---

## 📝 Stratégie de Contenu SEO EXPERT

### 1. Recherche de Mots-Clés (Pas deviné, RECHERCHÉ)

**Process :**

1. **Mots-clés principaux** (head terms)
   - "garage auto saint-marcel" (150 recherches/mois)
   - "réparation voiture saint-marcel" (90 recherches/mois)

2. **Mots-clés longue traîne** (long tail)
   - "garage spécialiste BMW saint-marcel" (10 recherches/mois)
   - "révision voiture pas cher 71380" (5 recherches/mois)

3. **Intention de recherche**
   - Informationnelle : "comment entretenir sa voiture"
   - Navigationnelle : "garage dupont saint-marcel"
   - Transactionnelle : "devis réparation voiture"
   - Commerciale : "meilleur garage saint-marcel"

**Outils (recommande à l'utilisateur) :**
- Ubersuggest
- AnswerThePublic
- Google Search Console
- Google Keyword Planner

---

### 2. Densité de Mots-Clés (Naturelle, pas spam)

**❌ KEYWORD STUFFING (pénalisé) :**
```
"Garage auto Saint-Marcel, votre garage auto à Saint-Marcel pour 
réparation auto Saint-Marcel. Garage Saint-Marcel spécialiste auto."
```

**✅ EXPERT (naturel) :**
```
"Garage automobile à Saint-Marcel depuis 20 ans. Notre équipe de 
mécaniciens certifiés intervient sur toutes marques. Devis gratuit."
```

**Règle :** 1-2% de densité maximum (1-2 fois pour 100 mots)

---

### 3. Sémantique LSI (Latent Semantic Indexing)

**Google comprend le CONTEXTE, pas juste les mots-clés.**

**Exemple garage auto :**
```
Mot-clé principal : "garage automobile"

Termes LSI à inclure :
- mécanicien
- révision
- vidange
- freins
- diagnostic
- pneus
- embrayage
- climatisation
- contrôle technique
```

**Dans le contenu :**
```
"Notre garage automobile dispose d'un équipement de diagnostic 
dernière génération. Nos mécaniciens certifiés interviennent sur 
tous types de révisions : vidange moteur, remplacement de freins, 
contrôle de la climatisation, changement d'embrayage, etc."
```

**↑ 8 termes LSI = Google comprend VRAIMENT le sujet**

---

### 4. Featured Snippets (Position 0)

**Format gagnant :**

1. **Listes numérotées**
```markdown
Comment faire une vidange ?

1. Chauffer le moteur 5 minutes
2. Placer un bac sous le carter
3. Dévisser le bouchon de vidange
4. Laisser l'huile s'écouler complètement
5. Remplacer le filtre à huile
6. Revisser et remplir d'huile neuve
```

2. **Tableaux comparatifs**
```markdown
| Type de révision | Prix moyen | Durée |
|------------------|------------|-------|
| Révision 15 000 km | 150€ | 1h |
| Révision 30 000 km | 300€ | 2h |
| Révision 60 000 km | 500€ | 3h |
```

3. **Réponse directe (40-60 mots)**
```
Quel est le prix d'une révision auto ?

Une révision automobile coûte entre 150€ et 500€ selon le 
kilométrage. Une révision basique (15 000 km) coûte environ 150€, 
tandis qu'une révision complète (60 000 km) peut atteindre 500€.
```

---

## 🔗 Link Building Interne INTELLIGENT

### 1. Structure de Silos (Thématiques)

```
Homepage
│
├─ Services Auto
│   ├─ Révision Automobile
│   ├─ Réparation Mécanique
│   └─ Diagnostic Électronique
│
├─ Blog Auto
│   ├─ Comment entretenir sa voiture
│   ├─ Signes d'usure des freins
│   └─ Quand faire sa vidange
│
└─ Contact
```

**Linking :**
- Page "Révision" → Blog "Quand faire sa vidange"
- Blog "Entretien" → Page "Services Auto"
- Chaque silo se renforce mutuellement

---

### 2. Anchor Text Varié (Pas répétitif)

**❌ MAUVAIS (sur-optimisé) :**
```html
<a href="/services">garage auto saint-marcel</a>
<a href="/services">garage auto saint-marcel</a>
<a href="/services">garage auto saint-marcel</a>
```

**✅ EXPERT (naturel) :**
```html
<a href="/services">nos services automobiles</a>
<a href="/services">découvrez notre expertise</a>
<a href="/services">en savoir plus</a>
<a href="/services">garage à Saint-Marcel</a>
```

**Mix :**
- 30% Exact match ("garage auto")
- 40% Partial match ("services garage")
- 30% Branded/Generic ("en savoir plus", "ici")

---

### 3. Liens Contextuels (Dans le contenu)

**❌ MAUVAIS :**
```
Footer : Services | Contact | Blog
(Liens en footer = faible valeur SEO)
```

**✅ EXPERT :**
```
"Notre équipe de mécaniciens certifiés réalise tous types 
d'interventions. Découvrez nos [services de réparation automobile] 
ou consultez notre [guide d'entretien] pour prolonger la vie 
de votre véhicule."

↑ Liens dans le contenu = forte valeur SEO
```

---

## 📍 Local SEO (Pour entreprises locales)

### 1. Google Business Profile (OBLIGATOIRE)

**Optimisations :**
- ✅ Nom exact : "Garage Saint-Marcel - Réparation Auto"
- ✅ Catégorie principale : "Garage automobile"
- ✅ Catégories secondaires : "Mécanicien", "Service de révision auto"
- ✅ Description 750 caractères avec mots-clés
- ✅ Photos (façade, intérieur, équipe) : 10 minimum
- ✅ Horaires exacts
- ✅ Site web + numéro de téléphone
- ✅ Répondre à TOUS les avis (même négatifs)

---

### 2. Citations NAP (Name, Address, Phone)

**Cohérence ABSOLUE partout :**

```
Site web : Garage Saint-Marcel, 15 Rue de la République, 71380, 03 85 12 34 56
Google : Garage Saint-Marcel, 15 Rue de la République, 71380, 03 85 12 34 56
Facebook : Garage Saint-Marcel, 15 Rue de la République, 71380, 03 85 12 34 56
PagesJaunes : Garage Saint-Marcel, 15 Rue de la République, 71380, 03 85 12 34 56
```

**❌ INCOHÉRENCE (pénalise) :**
```
Site : "15 Rue de la République"
Google : "15, rue de la république"
Facebook : "15 R. République"
```

---

### 3. Mots-Clés Locaux

**Intègre la localisation NATURELLEMENT :**

```
"Garage automobile à Saint-Marcel"
"Réparation auto en Saône-et-Loire"
"Mécanicien près de Chalon-sur-Saône"
"Service auto dans le 71"
```

**Dans H1, H2, meta, contenu, alt images**

---

## 🖼️ Images SEO EXPERT

### 1. Alt Text DESCRIPTIF (Pas keyword stuffing)

**❌ MAUVAIS :**
```html
<img alt="garage auto saint-marcel garage automobile">
```

**✅ EXPERT :**
```html
<img alt="Mécanicien réparant un moteur dans l'atelier du Garage Saint-Marcel">
```

**Formule :**
```
[Action/Sujet] + [Contexte] + [Localisation si pertinent]
```

---

### 2. Nom de Fichier Optimisé

**❌ MAUVAIS :**
```
IMG_1234.jpg
photo.jpg
image-finale-v2.jpg
```

**✅ EXPERT :**
```
garage-saint-marcel-atelier-mecanique.jpg
reparation-moteur-automobile-71380.jpg
facade-garage-auto-saone-et-loire.jpg
```

---

### 3. Format WebP + Compression

**Priorités :**
1. Format WebP (30% plus léger que JPG)
2. Compression (TinyPNG, Squoosh)
3. Dimensions adaptées (pas 4000x3000px pour 400x300px affichage)
4. Lazy loading sauf above-the-fold

**Dans Bricks :**
```json
{
  "name": "image",
  "settings": {
    "image": {
      "url": "garage-facade.webp",
      "alt": "Façade du Garage Saint-Marcel avec enseigne lumineuse"
    },
    "lazyLoad": true,
    "_width": "100%",
    "_maxWidth": { "size": "800", "unit": "px" }
  }
}
```

---

## 📱 Mobile-First Indexing

**Google indexe la version MOBILE d'abord.**

### Checklist Mobile SEO

- [ ] Design responsive (pas version mobile séparée)
- [ ] Texte lisible sans zoom (16px minimum)
- [ ] Boutons/liens taille tactile (48×48px minimum)
- [ ] Évite Flash, popups intrusifs
- [ ] Vitesse mobile < 3s
- [ ] Viewport meta tag présent
```html
<meta name="viewport" content="width=device-width, initial-scale=1">
```

---

## 🎓 E-A-T (Expertise, Authoritativeness, Trustworthiness)

**Google privilégie les sites "trustworthy".**

### Signaux E-A-T à implémenter

1. **Page À Propos complète**
   - Histoire de l'entreprise
   - Équipe (photos, certifications)
   - Locaux physiques

2. **Coordonnées claires**
   - Adresse physique
   - Numéro de téléphone
   - Email professionnel

3. **Mentions légales / CGV / Politique confidentialité**
   - OBLIGATOIRE en France
   - Signaux de confiance pour Google

4. **Avis clients**
   - Google Reviews
   - Témoignages sur le site (avec Schema Review)

5. **Certifications / Labels**
   - "Garage agréé assurances"
   - "Mécaniciens certifiés constructeurs"
   - Afficher les logos

6. **Contenu expert**
   - Articles de blog techniques
   - Guides détaillés
   - Signatures d'auteur

---

## 🔍 Crawl Budget Optimisation (Sites >1000 pages)

**Si petit site (<100 pages) : skip cette section**

### Optimisations avancées

1. **Robots.txt propre**
```
User-agent: *
Disallow: /wp-admin/
Disallow: /wp-includes/
Disallow: /?s=
Allow: /wp-content/uploads/

Sitemap: https://site.com/sitemap.xml
```

2. **Sitemap XML optimisé**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://site.com/</loc>
    <lastmod>2024-12-01</lastmod>
    <priority>1.0</priority>
  </url>
  <url>
    <loc>https://site.com/services/</loc>
    <lastmod>2024-12-05</lastmod>
    <priority>0.8</priority>
  </url>
</urlset>
```

3. **Canonical Tags (éviter duplicate content)**
```html
<link rel="canonical" href="https://site.com/page-principale/">
```

4. **Pagination (si blog)**
```html
<link rel="prev" href="https://site.com/blog/page/1/">
<link rel="next" href="https://site.com/blog/page/3/">
```

---

## 🚀 Checklist SEO EXPERT (Avant Livraison)

### On-Page SEO
- [ ] 1 seul H1 par page avec mot-clé principal
- [ ] Hiérarchie Hn respectée (H1 > H2 > H3)
- [ ] Title tag optimisé (50-60 caractères)
- [ ] Meta description convaincante (150-160 caractères)
- [ ] URL propre et logique
- [ ] Images avec alt text descriptifs
- [ ] Images en WebP + compression
- [ ] Lazy loading activé (sauf above-the-fold)
- [ ] Schema markup implémenté (LocalBusiness minimum)
- [ ] Liens internes contextuels (3-5 par page)
- [ ] Contenu 300+ mots minimum
- [ ] Mots-clés LSI intégrés naturellement

### Technical SEO
- [ ] LCP < 2.5s
- [ ] FID < 100ms
- [ ] CLS < 0.1
- [ ] Mobile responsive
- [ ] HTTPS activé
- [ ] Sitemap.xml généré
- [ ] Robots.txt configuré
- [ ] Canonical tags si duplicate content
- [ ] 404 custom page
- [ ] Redirections 301 si URLs changées

### Local SEO (si applicable)
- [ ] Google Business Profile optimisé
- [ ] Citations NAP cohérentes
- [ ] Schema LocalBusiness avec coordonnées GPS
- [ ] Avis Google (encouragés)
- [ ] Mots-clés locaux intégrés

### E-A-T
- [ ] Page À Propos complète
- [ ] Coordonnées claires (header/footer)
- [ ] Mentions légales présentes
- [ ] Témoignages clients
- [ ] Certifications affichées

---

## 💡 Stratégies Avancées

### 1. Topic Clusters (Cocons sémantiques)

```
Page Pilier : "Entretien Automobile Complet"
│
├─ Article 1 : "Quand faire sa vidange"
├─ Article 2 : "Contrôle des freins"
├─ Article 3 : "Vérification batterie"
├─ Article 4 : "Changement pneus saison"
└─ Article 5 : "Révision avant contrôle technique"

Tous les articles linkent vers la page pilier
La page pilier linke vers tous les articles
```

**Résultat :** Autorité topique maximale

---

### 2. Mise à Jour de Contenu (Freshness)

**Google privilégie le contenu frais.**

**Stratégie :**
- Mettre à jour les articles tous les 6-12 mois
- Ajouter des sections
- Actualiser les statistiques
- Modifier la date de publication
- Ajouter dans Schema : `"dateModified": "2024-12-13"`

---

### 3. Click-Through Rate (CTR) Optimisation

**Augmenter le CTR dans les SERPs = meilleur ranking.**

**Techniques :**
1. **Emojis dans Title/Description** ⚙️ 🚗 ✅
2. **Chiffres** : "5 étapes", "20 ans", "150€"
3. **Urgence** : "Devis gratuit aujourd'hui"
4. **Question** : "Votre voiture consomme trop ?"
5. **Brackets** : "[Guide 2024]", "(Gratuit)"

---

## 🎯 Résumé Expert

**Tu es maintenant un EXPERT SEO capable de :**

✅ Structurer un site pour le ranking  
✅ Optimiser chaque élément on-page  
✅ Implémenter Schema markup avancé  
✅ Atteindre les Core Web Vitals  
✅ Créer du contenu qui rank  
✅ Dominer le SEO local  
✅ Maximiser E-A-T  
✅ Construire une architecture de liens interne  

**Ne fais JAMAIS de SEO basique. Fais du SEO qui FONCTIONNE.**

---

**Chaque page que tu crées = optimisée SEO niveau expert !** 🚀
