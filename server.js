#!/usr/bin/env node

import { Server } from "@modelcontextprotocol/sdk/server/index.js";
import {
  ListToolsRequestSchema,
  CallToolRequestSchema,
} from "@modelcontextprotocol/sdk/types.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import fs from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

// ===== v3.6.0 — Playwright (lazy import pour verify_element) =====
// Chargé à la demande pour ne pas crasher si chromium pas installé.
let _chromium = null;
let _browser = null;
let _browserContext = null;

async function getChromium() {
  if (_chromium) return _chromium;
  try {
    const mod = await import('playwright-core');
    _chromium = mod.chromium;
    return _chromium;
  } catch (err) {
    throw new Error(
      "playwright-core introuvable. Réinstalle le package : npm i -g bricks-builder-mcp"
    );
  }
}

async function getBrowser() {
  if (_browser && _browser.isConnected()) return _browser;
  const chromium = await getChromium();
  try {
    _browser = await chromium.launch({ headless: true });
    logToFile('[VERIFY] Browser Chromium lancé');
    return _browser;
  } catch (err) {
    // Chromium pas installé
    throw new Error(
      "Chromium n'est pas installé pour Playwright.\n" +
      "Lance UNE FOIS dans un terminal : npx playwright install chromium\n" +
      "Détail : " + err.message
    );
  }
}

async function getNewPage(viewport) {
  const browser = await getBrowser();
  const sizes = {
    desktop: { width: 1920, height: 1080 },
    tablet: { width: 991, height: 1200 },
    mobile_landscape: { width: 767, height: 600 },
    mobile_portrait: { width: 478, height: 800 },
  };
  const size = sizes[viewport] || sizes.desktop;
  if (!_browserContext) {
    _browserContext = await browser.newContext({
      viewport: size,
      // verify_element doit toujours tolérer les certs auto-signés (pré-prod fréquent).
      // C'est de la lecture front, aucun risque sécurité.
      ignoreHTTPSErrors: true,
    });
  } else {
    // Adapter le viewport si différent
  }
  const page = await _browserContext.newPage();
  await page.setViewportSize(size);
  return page;
}

// Cleanup à exit
process.on('exit', () => {
  if (_browser) {
    try { _browser.close(); } catch {}
  }
});

// ===== v3.7.0 — Helpers upload local =====
// Le MCP server tourne sur la machine user (lancé par Claude Desktop via stdio),
// donc il a accès direct au filesystem user. Il peut lire les fichiers et les
// encoder en base64 LOCALEMENT pour les envoyer au plugin sans jamais que les
// bytes passent dans le contexte de l'AI.

const MIME_BY_EXT = {
  png: 'image/png',
  jpg: 'image/jpeg',
  jpeg: 'image/jpeg',
  gif: 'image/gif',
  webp: 'image/webp',
  svg: 'image/svg+xml',
  avif: 'image/avif',
  mp4: 'video/mp4',
  webm: 'video/webm',
  mov: 'video/quicktime',
};

function readLocalFileAsDataUri(localPath) {
  if (!fs.existsSync(localPath)) {
    throw new Error(`Fichier introuvable : ${localPath}`);
  }
  const stat = fs.statSync(localPath);
  if (!stat.isFile()) {
    throw new Error(`Pas un fichier : ${localPath}`);
  }
  const ext = (localPath.split('.').pop() || '').toLowerCase();
  const mime = MIME_BY_EXT[ext];
  if (!mime) {
    throw new Error(`Extension non supportée : ${ext} (formats: ${Object.keys(MIME_BY_EXT).join(', ')})`);
  }
  const bytes = fs.readFileSync(localPath);
  const b64 = bytes.toString('base64');
  return {
    dataUri: `data:${mime};base64,${b64}`,
    size: stat.size,
    mime,
    ext,
    basename: localPath.split('/').pop(),
  };
}
process.on('SIGINT', () => {
  if (_browser) {
    try { _browser.close(); } catch {}
  }
  process.exit(0);
});

// Hex → rgb/rgba pour matcher getComputedStyle qui renvoie toujours rgb()
function hexToRgb(hex) {
  hex = hex.replace('#', '');
  if (hex.length === 3) {
    hex = hex.split('').map(c => c + c).join('');
  }
  if (hex.length !== 6 && hex.length !== 8) return null;
  const r = parseInt(hex.substring(0, 2), 16);
  const g = parseInt(hex.substring(2, 4), 16);
  const b = parseInt(hex.substring(4, 6), 16);
  if (isNaN(r) || isNaN(g) || isNaN(b)) return null;
  if (hex.length === 8) {
    const a = parseInt(hex.substring(6, 8), 16) / 255;
    return `rgba(${r},${g},${b},${a.toFixed(2)})`;
  }
  return `rgb(${r},${g},${b})`;
}

// Normaliser une valeur rgba/rgb (supprime espaces, arrondit alpha)
function normaliseColor(s) {
  s = s.replace(/\s+/g, '');
  // rgba(0,0,0,0) → transparent
  if (s === 'rgba(0,0,0,0)' || s === 'transparent') return 'transparent';
  return s;
}

// Normaliser les valeurs CSS pour comparaison (rgba/spaces/units/hex/vh)
function normaliseCssValue(val, viewportContext) {
  if (val == null) return '';
  let s = String(val).trim().toLowerCase();
  s = s.replace(/\s+/g, '');

  // Hex → rgb pour matcher getComputedStyle
  if (/^#[0-9a-f]{3,8}$/.test(s)) {
    const rgb = hexToRgb(s);
    if (rgb) return normaliseColor(rgb);
  }

  // Couleurs rgb/rgba : normaliser pour comparer
  if (s.startsWith('rgb')) {
    return normaliseColor(s);
  }

  // vh / vw → px en fonction du viewport actuel (getComputedStyle renvoie en px)
  if (viewportContext) {
    const vhMatch = s.match(/^(\d+(?:\.\d+)?)vh$/);
    if (vhMatch && viewportContext.height) {
      return Math.round(parseFloat(vhMatch[1]) * viewportContext.height / 100) + 'px';
    }
    const vwMatch = s.match(/^(\d+(?:\.\d+)?)vw$/);
    if (vwMatch && viewportContext.width) {
      return Math.round(parseFloat(vwMatch[1]) * viewportContext.width / 100) + 'px';
    }
    // svh, dvh, lvh : on assimile à vh pour l'instant (approximatif mais OK pour la plupart des cas)
    const svhMatch = s.match(/^(\d+(?:\.\d+)?)(?:svh|dvh|lvh)$/);
    if (svhMatch && viewportContext.height) {
      return Math.round(parseFloat(svhMatch[1]) * viewportContext.height / 100) + 'px';
    }
  }

  // Normalise "0px" et "0" et "0%"
  if (s === '0' || s === '0px' || s === '0%') return '0';
  // Si valeur sans unité fournie (ex "32") et getComputedStyle renvoie "32px"
  if (/^\d+(\.\d+)?$/.test(s)) s += 'px';
  return s;
}

function generateHint(prop, expected, got) {
  if (prop === 'gap' || prop === 'column-gap' || prop === 'row-gap') {
    if (got === '0' || got === '0px' || got === 'normal') {
      return "Ajouter les 3 propriétés : _gap + _columnGap + _rowGap (cf bricks-2.3-formats.md)";
    }
  }
  if (prop === 'flex-direction' && got === 'column' && expected === 'row') {
    return "Ajouter _direction: 'row' explicitement (défaut Bricks = column)";
  }
  if (prop.startsWith('border-') && prop.endsWith('-radius')) {
    return "Utiliser _border.radius (imbriqué) — PAS _borderRadius flat";
  }
  if (prop === 'font-family' && got.includes('Times') || got.includes('serif')) {
    return "Police pas chargée — set_custom_code({customScriptsHeader: '<link Google Fonts>'})";
  }
  if (prop === 'background-color' && got === 'rgba(0,0,0,0)') {
    return "Background transparent — utiliser {color: {raw: 'rgba(...)'}} pour rgba (PAS hex)";
  }
  return null;
}

// ===== v3.10 — Moteur de checks pluggable =====
// Chaque check est une fonction Node-side qui reçoit le `audit` collecté
// dans la page (siblings, media, emptyContainers) et retourne des entrées
// {ok, severity, label, hint, bbox} à pousser dans le report.
// Sévérités : 'critical' (bug bloquant), 'warning' (probablement un bug),
// 'info' (juste à savoir). 'ok' false = compte comme échec dans le score.

function buildSiblingCoherenceChecks(audit) {
  const checks = [];
  const siblings = audit.siblings || [];
  if (siblings.length === 0) return checks;

  const all = [audit.self, ...siblings];

  // 1) text-align mixé entre frères directs
  // v3.11.2 : `start` et `left` sont équivalents en LTR (idem `end`/`right`),
  // on les normalise avant de comparer pour éviter les faux positifs.
  // (LTR par défaut, le check est sur des sites WordPress majoritairement français)
  const normalizeAlign = (val) => {
    if (val === 'start') return 'left';
    if (val === 'end') return 'right';
    return val;
  };
  const aligns = [...new Set(all.map(s => normalizeAlign(s['text-align'])).filter(Boolean))];
  if (aligns.length > 1) {
    checks.push({
      ok: false,
      severity: 'warning',
      label: `text-align mixé entre frères directs (${aligns.join(', ')})`,
      hint: "Frères directs avec text-align différents — souvent un bug visuel. Vérifie l'intention design ou aligne-les sur la même valeur.",
    });
  }

  // 2) Jumps de font-size > 2.5x entre frères (info seulement, c'est parfois voulu pour H1/sous-titre)
  const withFontSize = all.filter(s => s['font-size-px'] && s['font-size-px'] > 0);
  if (withFontSize.length >= 2) {
    const sizes = withFontSize.map(s => s['font-size-px']);
    const min = Math.min(...sizes);
    const max = Math.max(...sizes);
    if (min > 0 && max / min > 2.5) {
      checks.push({
        ok: true,
        severity: 'info',
        label: `font-size variable entre frères (${Math.round(min)}px → ${Math.round(max)}px)`,
        hint: "Écart > 2.5x entre frères — normal pour H1/sous-titre, à vérifier sinon.",
      });
    }
  }

  return checks;
}

function buildEmptyContainerChecks(audit) {
  const checks = [];
  const empties = audit.emptyContainers || [];
  if (empties.length === 0) {
    checks.push({ ok: true, label: "Aucun container vide anormal détecté (≥ 50×50px)" });
    return checks;
  }
  const sorted = empties.slice().sort((a, b) => b.area - a.area).slice(0, 5);
  checks.push({
    ok: false,
    severity: 'warning',
    label: `${empties.length} container(s) visible(s) sans contenu (≥ 50×50px)`,
    got: sorted.map(c => `${c.classes[0] || c.id || '?'} ${c.rect.w}×${c.rect.h}`),
    bboxes: sorted.map(c => c.rect),
    hint: "Conteneurs vides détectés — bug fréquent quand align-items: stretch écrase un block-vide à des proportions absurdes (dot 6px qui devient 180px, etc). Vérifie aspect-ratio fixe ou ajoute du contenu.",
  });
  return checks;
}

function buildMediaHealthChecks(audit) {
  const checks = [];
  const { images = [], videos = [] } = (audit.media || {});

  if (images.length > 0) {
    const broken = images.filter(i => !i.loaded);
    if (broken.length === 0) {
      checks.push({ ok: true, label: `${images.length} image(s) chargée(s) (naturalWidth > 0)` });
    } else {
      checks.push({
        ok: false,
        severity: 'critical',
        label: `${broken.length}/${images.length} image(s) PAS chargée(s) (naturalWidth = 0)`,
        got: broken.slice(0, 3).map(i => (i.src || '').split('/').pop() || '(no src)'),
        hint: "Image(s) non chargée(s) — vérifie l'URL src, le lazy-loading qui ne se déclenche pas, ou un 404. À éviter en production.",
      });
    }
    const noAlt = images.filter(i => !i.alt || i.alt.trim().length === 0);
    if (noAlt.length > 0) {
      checks.push({
        ok: false,
        severity: 'warning',
        label: `${noAlt.length}/${images.length} image(s) sans attribut alt`,
        hint: "alt manquant — bloque l'accessibilité et le SEO. Renseigne alt à l'upload via upload_local_file({alt: '...'}).",
      });
    }
  }

  if (videos.length > 0) {
    const notLoaded = videos.filter(v => !v.loaded);
    if (notLoaded.length === 0) {
      checks.push({ ok: true, label: `${videos.length} vidéo(s) chargée(s) (readyState ≥ 2)` });
    } else {
      checks.push({
        ok: false,
        severity: 'warning',
        label: `${notLoaded.length}/${videos.length} vidéo(s) pas prête(s) (readyState < 2)`,
        hint: "Vidéo(s) non prête(s) — peut être normal si autoplay désactivé sur mobile. Sinon vérifier src/codec.",
      });
    }
  }

  return checks;
}

// Configuration du fichier de log
const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const LOG_FILE = join(__dirname, 'bricks-mcp-debug.log');

function logToFile(message) {
  const timestamp = new Date().toISOString();
  const logMessage = `[${timestamp}] ${message}\n`;
  try {
    fs.appendFileSync(LOG_FILE, logMessage);
  } catch (err) {
    // Ignorer les erreurs de log
  }
}

// Remplacer console.error
const originalConsoleError = console.error;
console.error = (...args) => {
  const message = args.map(arg => 
    typeof arg === 'object' ? JSON.stringify(arg, null, 2) : String(arg)
  ).join(' ');
  logToFile(message);
  originalConsoleError(...args);
};

logToFile('========================================');
logToFile('DÉMARRAGE BRICKS MCP SERVER v3.0');
logToFile('========================================');

// Configuration WordPress
// Priorité : variables d'environnement > valeurs par défaut (ancien site, pour compatibilité)
const WORDPRESS_URL = process.env.WORDPRESS_URL || "https://aidetravauxfibre0002.live-website.com";
const API_KEY = process.env.API_KEY || "bricks_syectnbripq";
const API_ENDPOINT = `${WORDPRESS_URL}/wp-json/bricks-mcp/v2`;

// Mode SSL non-strict (utile pour les sites de pré-production avec certificats invalides)
// À UTILISER UNIQUEMENT EN PRÉ-PROD ! En production, le certificat doit être valide.
const INSECURE_SSL = process.env.INSECURE_SSL === 'true' || process.env.INSECURE_SSL === '1';
if (INSECURE_SSL) {
  process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';
  logToFile('[WARN] INSECURE_SSL activé : la vérification du certificat SSL est désactivée. À utiliser uniquement en pré-prod !');
}

logToFile(`[CONFIG] WORDPRESS_URL = ${WORDPRESS_URL}`);
logToFile(`[CONFIG] API_KEY = ${API_KEY ? API_KEY.substring(0, 10) + '...' : 'NON DÉFINIE'}`);
logToFile(`[CONFIG] INSECURE_SSL = ${INSECURE_SSL}`);

if (!process.env.WORDPRESS_URL || !process.env.API_KEY) {
  logToFile('[WARN] WORDPRESS_URL ou API_KEY non définies dans l\'environnement, utilisation des valeurs par défaut');
}

const mcpServer = new Server(
  {
    name: "bricks-mcp-v3",
    version: "3.0.0",
  },
  {
    capabilities: {
      tools: {},
    },
  }
);

// Fonction pour appeler l'API WordPress
async function callWordPressAPI(endpoint, method = "GET", body = null) {
  const options = {
    method,
    headers: {
      "Content-Type": "application/json",
      "X-API-Key": API_KEY,
    },
  };

  if (body) {
    options.body = JSON.stringify(body);
    console.error(`[LOG] Envoi vers ${endpoint}:`, JSON.stringify(body, null, 2));
  }

  try {
    const response = await fetch(`${API_ENDPOINT}${endpoint}`, options);
    console.error(`[LOG] Réponse status: ${response.status}`);

    if (!response.ok) {
      const errorText = await response.text();
      console.error(`[LOG] Erreur API: ${errorText}`);
      throw new Error(`API Error: ${response.statusText}`);
    }

    const data = await response.json();
    console.error(`[LOG] Réponse reçue:`, JSON.stringify(data, null, 2));
    return data;
  } catch (error) {
    console.error(`[LOG] Exception:`, error.message);
    throw new Error(`Erreur API: ${error.message}`);
  }
}

// Définir les tools disponibles
mcpServer.setRequestHandler(ListToolsRequestSchema, async () => {
  return {
    tools: [
      // ===== OUTILS EXISTANTS (compatibilité) =====
      {
        name: "list_bricks_pages",
        description: "Liste toutes les pages Bricks Builder du site.",
        inputSchema: {
          type: "object",
          properties: {},
          required: [],
        },
      },
      {
        name: "get_page_json",
        description: "Récupère le JSON COMPLET d'une page. Utilise ceci pour les gros travaux ou refonte complète.",
        inputSchema: {
          type: "object",
          properties: {
            pageId: {
              type: "number",
              description: "L'ID de la page WordPress",
            },
          },
          required: ["pageId"],
        },
      },
      {
        name: "update_page_json",
        description: "Sauvegarde le JSON COMPLET d'une page. Utilise ceci après get_page_json pour grosse refonte.",
        inputSchema: {
          type: "object",
          properties: {
            pageId: {
              type: "number",
              description: "L'ID de la page",
            },
            newJsonData: {
              type: "array",
              description: "Le JSON complet (TABLEAU)",
              items: {
                type: "object",
              }
            },
          },
          required: ["pageId", "newJsonData"],
        },
      },
      {
        name: "analyze_json_structure",
        description: "Analyse la structure d'un JSON Bricks.",
        inputSchema: {
          type: "object",
          properties: {
            jsonData: {
              type: ["object", "array", "string"],
              description: "Le JSON à analyser",
            },
          },
          required: ["jsonData"],
        },
      },

      // ===== OUTILS v2.0 (OPTIMISÉS) =====
      
      {
        name: "get_page_structure",
        description: "Récupère une VUE D'ENSEMBLE LÉGÈRE de la page (juste id, name, parent, children, aperçu texte). Économise 80% de tokens vs get_page_json. Utilise pour comprendre la structure sans charger tous les détails.",
        inputSchema: {
          type: "object",
          properties: {
            pageId: {
              type: "number",
              description: "L'ID de la page",
            },
          },
          required: ["pageId"],
        },
      },
      
      {
        name: "find_elements",
        description: "Trouve des éléments par CRITÈRES sans charger toute la page. Très économe en tokens. Utilise pour chercher des boutons, headings, sections spécifiques, etc.",
        inputSchema: {
          type: "object",
          properties: {
            pageId: {
              type: "number",
              description: "L'ID de la page",
            },
            criteria: {
              type: "object",
              description: "Critères de recherche",
              properties: {
                type: {
                  type: "string",
                  description: "Type d'élément : heading, button, section, container, etc."
                },
                parent: {
                  type: "string",
                  description: "ID du parent"
                },
                hasText: {
                  type: "string",
                  description: "Texte contenu dans l'élément"
                },
                className: {
                  type: "string",
                  description: "Classe CSS de l'élément"
                }
              }
            },
            limit: {
              type: "number",
              description: "Nombre max de résultats (défaut: 100)",
            },
          },
          required: ["pageId"],
        },
      },
      
      {
        name: "get_element",
        description: "Récupère UN SEUL élément en détail. Utilise quand tu connais l'ID et veux les détails complets.",
        inputSchema: {
          type: "object",
          properties: {
            pageId: {
              type: "number",
              description: "L'ID de la page",
            },
            elementId: {
              type: "string",
              description: "L'ID de l'élément à récupérer",
            },
          },
          required: ["pageId", "elementId"],
        },
      },
      
      {
        name: "update_element",
        description: "Modifie UN SEUL élément sans recharger/renvoyer toute la page. Ultra économe en tokens. Utilise pour changer une couleur, un texte, etc. Permet aussi de renommer l'élément dans la structure Bricks via le paramètre `label`.",
        inputSchema: {
          type: "object",
          properties: {
            pageId: {
              type: "number",
              description: "L'ID de la page",
            },
            elementId: {
              type: "string",
              description: "L'ID de l'élément à modifier",
            },
            newSettings: {
              type: "object",
              description: "Les nouveaux settings (fusionnés avec les anciens). Optionnel si label est fourni seul.",
            },
            label: {
              type: "string",
              description: "Nom affiché dans la structure du builder Bricks (ex: 'Hero Section', 'Header Pill', 'Logo', 'CTA Buttons'). Modifie l'attribut `label` au niveau racine de l'élément. Optionnel.",
            },
          },
          required: ["pageId", "elementId"],
        },
      },
      
      {
        name: "add_element",
        description: "Ajoute UN SEUL nouvel élément à la page. Utilise pour ajouter un bouton, un paragraphe, etc.",
        inputSchema: {
          type: "object",
          properties: {
            pageId: {
              type: "number",
              description: "L'ID de la page",
            },
            element: {
              type: "object",
              description: "L'élément complet à ajouter (avec id, name, parent, children, settings)",
            },
            position: {
              type: "number",
              description: "Position dans le tableau (optionnel, défaut: fin)",
            },
          },
          required: ["pageId", "element"],
        },
      },
      
      {
        name: "batch_add",
        description: "Ajoute PLUSIEURS éléments en UNE SEULE fois. Utilise pour créer une section complète (5-10 éléments). Plus efficace que add_element en boucle.",
        inputSchema: {
          type: "object",
          properties: {
            pageId: {
              type: "number",
              description: "L'ID de la page",
            },
            elements: {
              type: "array",
              description: "Tableau des éléments à ajouter",
              items: {
                type: "object"
              }
            },
          },
          required: ["pageId", "elements"],
        },
      },
      
      {
        name: "delete_element",
        description: "Supprime UN élément et nettoie les références parent/enfant.",
        inputSchema: {
          type: "object",
          properties: {
            pageId: {
              type: "number",
              description: "L'ID de la page",
            },
            elementId: {
              type: "string",
              description: "L'ID de l'élément à supprimer",
            },
          },
          required: ["pageId", "elementId"],
        },
      },

      // ===== 🆕 NOUVEL OUTIL v3.0 : REORDER_SECTIONS =====
      
      {
        name: "reorder_sections",
        description: "Réorganise l'ordre d'affichage des sections principales (parent: 0). CRITIQUE : Dans Bricks, l'ordre dans le tableau JSON détermine l'ordre de rendu. Utilise TOUJOURS cet outil après création d'un header/footer pour le placer correctement. Exemple : reorder_sections(9, ['header_pro', 'hero_section', 'services_section']) met le header en premier.",
        inputSchema: {
          type: "object",
          properties: {
            pageId: {
              type: "number",
              description: "L'ID de la page WordPress",
            },
            orderedIds: {
              type: "array",
              items: { type: "string" },
              description: "Tableau des IDs des sections dans l'ordre souhaité (ex: ['header_pro', 'hero_section', 'services_section']). Les sections non listées seront placées à la fin.",
            },
          },
          required: ["pageId", "orderedIds"],
        },
      },

      // ===== OUTIL v3.2.0 — CRÉATION DE PAGES =====

      {
        name: "create_page",
        description: "Crée une nouvelle page WordPress et l'active immédiatement en mode Bricks Builder. Utilise pour démarrer un nouveau site ou ajouter une page (Accueil, Services, Contact, etc.). Renvoie l'ID de la page créée, prêt à être utilisé avec les autres outils MCP.",
        inputSchema: {
          type: "object",
          properties: {
            title: {
              type: "string",
              description: "Titre de la page (ex: 'Accueil', 'Services', 'Contact')",
            },
            slug: {
              type: "string",
              description: "Slug URL optionnel (ex: 'accueil'). Si omis, sera généré depuis le titre.",
            },
            status: {
              type: "string",
              enum: ["publish", "draft", "private"],
              description: "Statut de publication (défaut: 'publish')",
            },
            setAsHomepage: {
              type: "boolean",
              description: "Si true, configure cette page comme page d'accueil du site (défaut: false)",
            },
          },
          required: ["title"],
        },
      },

      // ===== OUTILS v3.3.0 — GESTION DES PAGES =====

      {
        name: "delete_page",
        description: "Supprime une page WordPress. Par défaut la met à la corbeille (récupérable). Utilise force=true pour supprimer définitivement (irréversible). Refuse de supprimer la page d'accueil active — la changer d'abord.",
        inputSchema: {
          type: "object",
          properties: {
            pageId: { type: "number", description: "L'ID de la page à supprimer" },
            force: { type: "boolean", description: "Si true, suppression définitive. Sinon, mise à la corbeille (défaut: false)" },
          },
          required: ["pageId"],
        },
      },
      {
        name: "update_page_meta",
        description: "Met à jour les méta-données d'une page (titre, slug URL, statut de publication, page parente). Seuls les champs fournis sont modifiés. Utile pour renommer, changer l'URL, publier un draft, organiser la hiérarchie.",
        inputSchema: {
          type: "object",
          properties: {
            pageId: { type: "number", description: "L'ID de la page à modifier" },
            title: { type: "string", description: "Nouveau titre (optionnel)" },
            slug: { type: "string", description: "Nouveau slug URL (optionnel, ex: 'accueil')" },
            status: { type: "string", enum: ["publish", "draft", "private", "pending"], description: "Nouveau statut de publication (optionnel)" },
            parentId: { type: "number", description: "ID de la page parente (0 = racine, optionnel)" },
          },
          required: ["pageId"],
        },
      },
      {
        name: "duplicate_page",
        description: "Duplique une page existante avec son contenu Bricks complet (sections, éléments, settings). Très utile pour créer des variantes à partir d'une page bien faite, ou pour partir d'un template existant. La copie est créée en draft par défaut.",
        inputSchema: {
          type: "object",
          properties: {
            sourcePageId: { type: "number", description: "ID de la page source à dupliquer" },
            newTitle: { type: "string", description: "Titre de la copie (optionnel, défaut: 'Copie de {original}')" },
            status: { type: "string", enum: ["publish", "draft", "private", "pending"], description: "Statut de la copie (défaut: 'draft')" },
          },
          required: ["sourcePageId"],
        },
      },
      {
        name: "set_homepage",
        description: "Définit une page comme page d'accueil du site WordPress. La page doit être publiée. Pour réinitialiser sur les derniers articles (mode WordPress par défaut), passer reset=true.",
        inputSchema: {
          type: "object",
          properties: {
            pageId: { type: "number", description: "ID de la page à mettre en accueil" },
            reset: { type: "boolean", description: "Si true, reset sur les derniers articles (pageId ignoré)" },
          },
        },
      },

      // ===== OUTILS v3.4.0 — HEALTH, MÉDIAS, MENUS, STYLES GLOBAUX =====

      {
        name: "health_check",
        description: "Test de connexion au site WordPress + infos système (versions plugin/WP/PHP/Bricks, multisite, URL). Utile pour debug et confirmer qu'on parle au bon site.",
        inputSchema: { type: "object", properties: {} },
      },
      {
        name: "list_all_pages",
        description: "Liste TOUTES les pages WordPress du site (pas seulement celles avec contenu Bricks). Inclut les pages WP standard. Utile pour avoir l'inventaire complet avant de créer/dupliquer/nettoyer.",
        inputSchema: { type: "object", properties: {} },
      },
      {
        name: "upload_media",
        description: "Télécharge une image depuis une URL et l'ajoute à la médiathèque WordPress. Renvoie l'URL WP de l'image, prête à utiliser dans les éléments Bricks (image, background, etc.). Très utile pour intégrer des photos client sans passer par la médiathèque WP manuellement.",
        inputSchema: {
          type: "object",
          properties: {
            sourceUrl: { type: "string", description: "URL publique de l'image à télécharger (jpg/png/webp/gif/svg)" },
            title: { type: "string", description: "Titre de l'image dans la médiathèque (optionnel)" },
            alt: { type: "string", description: "Alt text pour SEO/accessibilité (optionnel mais fortement recommandé)" },
            caption: { type: "string", description: "Légende affichée sous l'image (optionnel)" },
          },
          required: ["sourceUrl"],
        },
      },
      {
        name: "list_media",
        description: "Liste paginée des médias de la médiathèque WordPress avec leurs URLs. Filtrable par recherche. Utile pour retrouver des images déjà uploadées.",
        inputSchema: {
          type: "object",
          properties: {
            page: { type: "number", description: "Page de pagination (défaut: 1)" },
            perPage: { type: "number", description: "Nombre par page (défaut: 20, max: 100)" },
            search: { type: "string", description: "Recherche textuelle dans les titres/noms de fichier" },
          },
        },
      },
      {
        name: "list_menus",
        description: "Liste les menus de navigation WordPress du site avec leur nombre d'items et les emplacements assignés (header, footer, etc.). À utiliser avant add_menu_item.",
        inputSchema: { type: "object", properties: {} },
      },
      {
        name: "add_menu_item",
        description: "Ajoute un item à un menu WordPress de navigation. Soit on lie une page existante (via pageId), soit un lien custom (via customUrl + label). Très utile pour ajouter automatiquement les nouvelles pages au menu principal.",
        inputSchema: {
          type: "object",
          properties: {
            menuId: { type: "number", description: "ID du menu (cf list_menus)" },
            pageId: { type: "number", description: "ID de la page à ajouter (mode 'page')" },
            customUrl: { type: "string", description: "URL custom (mode 'lien externe')" },
            label: { type: "string", description: "Libellé visible. Si pageId fourni, par défaut prend le titre de la page" },
            parentItemId: { type: "number", description: "Mettre cet item comme enfant d'un autre item (sous-menu)" },
          },
          required: ["menuId"],
        },
      },
      {
        name: "get_global_styles",
        description: "Récupère les settings globaux de Bricks Builder pour ce site (typographie globale, breakpoints, palette, classes globales, theme styles). Utile au début d'un projet pour comprendre la base visuelle existante.",
        inputSchema: { type: "object", properties: {} },
      },
      {
        name: "update_global_styles",
        description: "Met à jour les settings globaux Bricks via fusion récursive. Utile pour appliquer une typo de site ou une convention CSS partout d'un coup. Seuls les champs fournis sont modifiés.",
        inputSchema: {
          type: "object",
          properties: {
            settings: { type: "object", description: "Settings à fusionner avec l'existant" },
          },
          required: ["settings"],
        },
      },
      {
        name: "list_color_palette",
        description: "Récupère la palette de couleurs globale Bricks (les couleurs nommées réutilisables sur tout le site).",
        inputSchema: { type: "object", properties: {} },
      },
      {
        name: "add_color_to_palette",
        description: "Ajoute une couleur à la palette globale Bricks. Permet ensuite de l'utiliser dans n'importe quel élément via son ID/nom plutôt que de répéter la valeur hex partout.",
        inputSchema: {
          type: "object",
          properties: {
            name: { type: "string", description: "Nom de la couleur (ex: 'Primaire', 'Accent orange')" },
            hex: { type: "string", description: "Valeur hexadécimale (ex: '#ff6b35' ou 'ff6b35')" },
          },
          required: ["name", "hex"],
        },
      },

      // ===== OUTILS v3.5.0 — PHASE A : INSPECTION + CUSTOM CODE + FONTS + CODE EXEC =====

      {
        name: "list_bricks_options",
        description: "Dump TOUTES les options WP commençant par 'bricks_'. Outil debug essentiel pour cartographier ce qui existe en base avant de modifier. Retourne nom, type, taille, aperçu de chaque option.",
        inputSchema: { type: "object", properties: {} },
      },
      {
        name: "get_bricks_option",
        description: "Récupère une option Bricks spécifique en intégralité (sans tronquer). Le nom doit commencer par 'bricks_'.",
        inputSchema: {
          type: "object",
          properties: { name: { type: "string", description: "Nom de l'option (ex: 'bricks_global_settings')" } },
          required: ["name"],
        },
      },
      {
        name: "get_custom_code",
        description: "Récupère le custom code global Bricks (CSS injecté dans <head>, scripts header, body header, body footer). C'est l'endroit natif pour charger Google Fonts via <link>.",
        inputSchema: { type: "object", properties: {} },
      },
      {
        name: "set_custom_code",
        description: "Met à jour le custom code global Bricks. customScriptsHeader est l'endroit IDÉAL pour charger Google Fonts via <link rel='stylesheet'>. Seuls les champs fournis sont modifiés.",
        inputSchema: {
          type: "object",
          properties: {
            customCss:               { type: "string", description: "CSS injecté dans <head>" },
            customScriptsHeader:     { type: "string", description: "HTML/scripts injectés dans <head> (idéal pour Google Fonts <link>)" },
            customScriptsBodyHeader: { type: "string", description: "HTML injecté juste après l'ouverture <body>" },
            customScriptsBodyFooter: { type: "string", description: "HTML injecté juste avant </body>" },
          },
        },
      },
      {
        name: "get_code_execution_status",
        description: "État de l'exécution des code elements Bricks. Bricks 1.9.7+ désactive code execution par défaut pour sécurité. À vérifier avant de tenter d'utiliser <script>/<svg> inline dans un code element.",
        inputSchema: { type: "object", properties: {} },
      },
      {
        name: "set_code_execution",
        description: "Active/désactive l'exécution des code elements Bricks + définit les rôles autorisés. Active la capability WP 'bricks_execute_code'. Note: Bricks exige aussi des code signatures valides côté builder.",
        inputSchema: {
          type: "object",
          properties: {
            enabled: { type: "boolean", description: "true pour activer, false pour désactiver" },
            roles:   { type: "array", items: { type: "string" }, description: "Rôles autorisés à exécuter du code (ex: ['administrator'])" },
          },
          required: ["enabled"],
        },
      },
      {
        name: "list_custom_fonts",
        description: "Liste les custom fonts enregistrées dans Bricks Font Manager (CPT bricks_fonts).",
        inputSchema: { type: "object", properties: {} },
      },
      {
        name: "register_custom_font",
        description: "Enregistre une custom font dans Bricks Font Manager. Une fois enregistrée, elle sera dispo dans les selects font-family. Utiliser cet outil quand on a déjà les URLs des fichiers .woff2.",
        inputSchema: {
          type: "object",
          properties: {
            name:       { type: "string", description: "Nom de la font (ex: 'Anton')" },
            fontFamily: { type: "string", description: "font-family CSS (souvent identique au name)" },
            faces:      { type: "array", items: { type: "object" }, description: "Variantes : [{ weight: 400, style: 'normal', url: 'https://.../font-regular.woff2' }, ...]" },
          },
          required: ["name", "faces"],
        },
      },
      {
        name: "delete_custom_font",
        description: "Supprime une custom font du Font Manager Bricks.",
        inputSchema: {
          type: "object",
          properties: { id: { type: "number", description: "ID du post bricks_fonts à supprimer" } },
          required: ["id"],
        },
      },
      {
        name: "register_google_font_locally",
        description: "Télécharge un Google Font et l'enregistre dans le Font Manager Bricks. Récupère automatiquement les URLs des .woff2 depuis Google Fonts CSS et crée le post bricks_fonts. C'est la méthode native pour utiliser une Google Font dans Bricks.",
        inputSchema: {
          type: "object",
          properties: {
            name:    { type: "string", description: "Nom de la font Google (ex: 'Anton', 'Inter', 'Bebas Neue')" },
            weights: { type: "array", items: { type: "number" }, description: "Poids souhaités (défaut: [400], ex: [400, 700, 900])" },
          },
          required: ["name"],
        },
      },

      // ===== OUTILS v3.5.0 — PHASE B : GLOBAL CLASSES + THEME STYLES + PAGE CODE =====

      {
        name: "list_global_classes",
        description: "Liste les classes CSS globales Bricks réutilisables sur tout le site.",
        inputSchema: { type: "object", properties: {} },
      },
      {
        name: "create_global_class",
        description: "Crée une classe CSS globale Bricks. Une fois créée, on peut l'appliquer à n'importe quel élément via _cssClasses pour réutiliser des styles partout.",
        inputSchema: {
          type: "object",
          properties: {
            name:     { type: "string", description: "Nom de la classe (ex: 'btn-primary', 'card')" },
            settings: { type: "object", description: "Settings Bricks à appliquer (ex: { _typography: ..., _padding: ..., _background: ... })" },
          },
          required: ["name", "settings"],
        },
      },
      {
        name: "update_global_class",
        description: "Modifie une classe globale par id. Tous les éléments qui utilisent cette classe seront affectés.",
        inputSchema: {
          type: "object",
          properties: {
            id:       { type: "string", description: "ID de la classe" },
            name:     { type: "string", description: "Nouveau nom (optionnel)" },
            settings: { type: "object", description: "Nouveaux settings (optionnel, remplace l'ancien)" },
          },
          required: ["id"],
        },
      },
      {
        name: "delete_global_class",
        description: "Supprime une classe globale Bricks.",
        inputSchema: {
          type: "object",
          properties: { id: { type: "string", description: "ID de la classe à supprimer" } },
          required: ["id"],
        },
      },
      {
        name: "list_theme_styles",
        description: "Liste les theme styles Bricks (styles globaux conditionnels appliqués selon contexte).",
        inputSchema: { type: "object", properties: {} },
      },
      {
        name: "create_theme_style",
        description: "Crée un theme style Bricks. Définit les styles par défaut globaux (typo, couleurs, buttons, etc.) avec conditions optionnelles d'application.",
        inputSchema: {
          type: "object",
          properties: {
            name:       { type: "string", description: "Nom du theme style" },
            settings:   { type: "object", description: "Settings (typography, colors, headings, buttons, etc.)" },
            conditions: { type: "array", items: { type: "object" }, description: "Conditions d'application (optionnel)" },
          },
          required: ["name", "settings"],
        },
      },
      {
        name: "update_theme_style",
        description: "Modifie un theme style existant.",
        inputSchema: {
          type: "object",
          properties: {
            id:         { type: "string", description: "ID du theme style" },
            name:       { type: "string", description: "Nouveau nom (opt)" },
            settings:   { type: "object", description: "Nouveaux settings (opt)" },
            conditions: { type: "array", items: { type: "object" }, description: "Nouvelles conditions (opt)" },
          },
          required: ["id"],
        },
      },
      {
        name: "delete_theme_style",
        description: "Supprime un theme style.",
        inputSchema: {
          type: "object",
          properties: { id: { type: "string", description: "ID du theme style" } },
          required: ["id"],
        },
      },
      {
        name: "get_page_custom_code",
        description: "Récupère le custom code (CSS/JS) spécifique à une page Bricks (Page Settings → Custom Code).",
        inputSchema: {
          type: "object",
          properties: { pageId: { type: "number", description: "ID de la page" } },
          required: ["pageId"],
        },
      },
      {
        name: "set_page_custom_code",
        description: "Définit du CSS et/ou JS spécifique à une page (Page Settings → Custom Code). Utile pour des animations, des polices spécifiques à cette page seulement, etc.",
        inputSchema: {
          type: "object",
          properties: {
            pageId:        { type: "number", description: "ID de la page" },
            customCss:     { type: "string", description: "CSS de la page (sera injecté dans <head> uniquement sur cette page)" },
            customScripts: { type: "string", description: "Scripts/HTML de la page (injectés dans <head>)" },
          },
          required: ["pageId"],
        },
      },

      // ===== OUTILS v3.5.0 — PHASE C : STYLE MANAGER 2.2 + COMPONENTS =====

      {
        name: "list_typography_scales",
        description: "Liste les typography scales globales Bricks (Style Manager 2.2). Définit des tailles de typo réutilisables pour h1, h2, body, etc.",
        inputSchema: { type: "object", properties: {} },
      },
      {
        name: "set_typography_scale",
        description: "Crée ou met à jour une typography scale (Style Manager 2.2). Si id existe, met à jour ; sinon crée une nouvelle.",
        inputSchema: {
          type: "object",
          properties: {
            id:     { type: "string", description: "ID de la scale (laisser vide pour créer)" },
            name:   { type: "string", description: "Nom de la scale (ex: 'Default')" },
            values: { type: "object", description: "Valeurs (ex: { h1: '64px', h2: '48px', body: '16px' })" },
          },
        },
      },
      {
        name: "list_spacing_scales",
        description: "Liste les spacing scales globales Bricks (Style Manager 2.2).",
        inputSchema: { type: "object", properties: {} },
      },
      {
        name: "set_spacing_scale",
        description: "Crée ou met à jour une spacing scale.",
        inputSchema: {
          type: "object",
          properties: {
            id:     { type: "string", description: "ID de la scale (laisser vide pour créer)" },
            name:   { type: "string", description: "Nom de la scale" },
            values: { type: "object", description: "Valeurs (ex: { xs: '8px', sm: '16px', md: '24px', lg: '48px' })" },
          },
        },
      },
      {
        name: "list_css_variables",
        description: "Liste les CSS variables globales Bricks (Style Manager 2.2). Variables réutilisables comme --primary, --accent, etc.",
        inputSchema: { type: "object", properties: {} },
      },
      {
        name: "set_css_variable",
        description: "Crée ou met à jour une CSS variable globale. Si la variable existe, sa valeur est mise à jour ; sinon elle est créée.",
        inputSchema: {
          type: "object",
          properties: {
            name:  { type: "string", description: "Nom (avec ou sans --, ex: '--primary' ou 'primary')" },
            value: { type: "string", description: "Valeur (ex: '#FD5B2C', '64px', '1.5')" },
          },
          required: ["name", "value"],
        },
      },
      {
        name: "list_components",
        description: "Liste les components Bricks (templates avec type=component, Bricks 2.x).",
        inputSchema: { type: "object", properties: {} },
      },

      // ===== v3.6.0 — VERIFY ELEMENT (vérification visuelle + technique) =====
      {
        name: "verify_element",
        description: "⭐ APRÈS CHAQUE batch_add OU update_element SIGNIFICATIF, utilise cet outil. Lance un browser headless, navigue sur la page, scroll vers l'élément, prend un screenshot crop et compare les computed styles avec les settings attendus. v3.10 : ajout de checks généralistes (cohérence siblings, containers vides, santé des médias) + multi-viewport en 1 call (param `viewports`). Retourne {screenshot(s) visibles par Claude, report: {score, checks}, computed}. Force l'évolution petit-à-petit-vérifier-petit-à-petit.",
        inputSchema: {
          type: "object",
          properties: {
            pageId: { type: "number", description: "L'ID de la page" },
            elementId: { type: "string", description: "L'ID de l'élément à vérifier (ex: 'section_abc')" },
            viewport: {
              type: "string",
              enum: ["desktop", "tablet", "mobile_landscape", "mobile_portrait"],
              description: "Taille d'écran à tester (défaut: desktop). Ignoré si `viewports` est fourni.",
            },
            viewports: {
              type: "array",
              items: { type: "string", enum: ["desktop", "tablet", "mobile_landscape", "mobile_portrait"] },
              description: "v3.10 : tester plusieurs viewports en 1 call. Renvoie un report + screenshot par viewport. Ex: ['desktop', 'mobile_portrait'].",
            },
            checks: {
              type: "object",
              description: "v3.10 : activer/désactiver des catégories de checks (toutes activées par défaut).",
              properties: {
                expected_styles: { type: "boolean", description: "Compare les computed styles aux settings attendus (défaut: true)" },
                sibling_coherence: { type: "boolean", description: "Détecte text-align mixé et jumps font-size entre frères directs (défaut: true)" },
                empty_containers: { type: "boolean", description: "Flag les blocs visibles ≥ 50×50px sans contenu (défaut: true)" },
                media_health: { type: "boolean", description: "Vérifie naturalWidth > 0 sur les <img>, readyState ≥ 2 sur les <video>, alt présents (défaut: true)" },
                overflow: { type: "boolean", description: "Détecte les débordements horizontaux (défaut: true)" },
                console_errors: { type: "boolean", description: "Remonte les erreurs JS console (défaut: true)" },
              },
            },
          },
          required: ["pageId", "elementId"],
        },
      },

      // ===== v3.11 — AUDIT_PAGE (fullpage screenshot + annotations + report consolidé) =====
      {
        name: "audit_page",
        description: "⭐ AUDIT GLOBAL d'une page Bricks en 1 call. Lance un browser headless, charge la page, scanne TOUS les éléments Bricks visibles, dessine des cadres colorés sur le screenshot fullpage là où il y a un problème (rouge=critical, orange=warning, jaune=info), et retourne un report consolidé avec severityCounts. Idéal pour un coup d'œil rapide 'qu'est-ce qui cloche sur cette page ?' avant ou après une refonte. Complète verify_element (qui est ciblé sur 1 élément).",
        inputSchema: {
          type: "object",
          properties: {
            pageId: { type: "number", description: "L'ID de la page WP à auditer" },
            viewports: {
              type: "array",
              items: { type: "string", enum: ["desktop", "tablet", "mobile_landscape", "mobile_portrait"] },
              description: "Viewports à tester (défaut: ['desktop', 'mobile_portrait']). Renvoie un screenshot annoté + un report par viewport.",
            },
            checks: {
              type: "object",
              description: "Activer/désactiver les catégories de checks. Toutes activées par défaut.",
              properties: {
                empty_containers: { type: "boolean", description: "Containers Bricks ≥ 50×50px sans contenu (défaut: true)" },
                media_health: { type: "boolean", description: "Images cassées (naturalWidth=0) + alt manquant (défaut: true)" },
                sibling_coherence: { type: "boolean", description: "text-align mixé entre frères Bricks (défaut: true)" },
                page_overflow: { type: "boolean", description: "Débordement horizontal global de la page (défaut: true)" },
              },
            },
            maxAnnotations: { type: "number", description: "Limite d'annotations dessinées sur le screenshot (défaut: 30, pour éviter d'écraser visuellement la page)" },
          },
          required: ["pageId"],
        },
      },

      // ===== v3.6.0 — FEEDBACK SYSTEM (missing MCP features) =====
      {
        name: "report_missing_feature",
        description: "À UTILISER UNIQUEMENT quand Bricks supporte une feature nativement (vérifié via doc officielle) MAIS le MCP ne l'expose pas correctement (outil manquant / buggy / setting ignoré). PAS pour les limites Bricks elles-mêmes — dans ce cas code une alternative libre (CSS/JS via set_page_custom_code). Le gestionnaire du MCP lit ces feedbacks pour combler les trous.",
        inputSchema: {
          type: "object",
          properties: {
            title: { type: "string", description: "Titre court du manque (ex: 'Pas d outil pour Interactions Bricks')" },
            bricksFeature: { type: "string", description: "Nom officiel Bricks de la feature (ex: 'Interactions API')" },
            bricksDocUrl: { type: "string", description: "Lien vers la doc Bricks qui prouve que la feature est native" },
            whatItShouldDo: { type: "string", description: "Ce que l'outil MCP devrait faire" },
            whatITried: { type: "string", description: "Ce que tu as tenté avec les outils actuels et le résultat" },
            proposedTool: { type: "string", description: "Nom d'outil suggéré (ex: 'set_element_interactions')" },
            bricksVersion: { type: "string", description: "Version Bricks du site (ex: '2.3.2')" },
            context: { type: "string", description: "Contexte du chat (URL page, ce que tu construisais)" },
          },
          required: ["title", "bricksFeature"],
        },
      },
      {
        name: "list_missing_features",
        description: "Liste les feedbacks remontés par d'autres chats. Pour le mainteneur du MCP qui veut savoir quoi prioriser.",
        inputSchema: {
          type: "object",
          properties: {
            status: { type: "string", enum: ["open", "resolved"], description: "Filtrer par statut (défaut: tous)" },
          },
        },
      },
      {
        name: "resolve_missing_feature",
        description: "Marque un feedback comme résolu (nouvel outil ajouté ou doc enrichie).",
        inputSchema: {
          type: "object",
          properties: {
            id: { type: "string", description: "ID du feedback à résoudre" },
            resolutionNote: { type: "string", description: "Comment c'est résolu (nouvel outil, doc, version)" },
          },
          required: ["id"],
        },
      },

      // ===== v3.7.0 — UPLOAD FROM LOCAL FILESYSTEM =====
      // Lit le fichier directement sur le disque (le MCP server tourne en local),
      // encode en base64 LOCALEMENT, et l'envoie au plugin via data URI.
      // L'AI ne voit JAMAIS les bytes — elle donne juste le path.
      {
        name: "upload_local_file",
        description: "⭐ UPLOAD OPTIMAL pour des fichiers locaux. L'AI donne juste le path, le MCP server lit le fichier en local et l'envoie au plugin. Aucun b64 ne transite par le contexte AI. Param `optimize: true` (recommandé) convertit en WebP qualité 80, redim à 2000px max, et renomme avec extension .webp. Idéal pour SEO + perf web. Retourne {success, id, url, filename, optimization: {originalSize, optimizedSize, savings}}.",
        inputSchema: {
          type: "object",
          properties: {
            localPath: { type: "string", description: "Chemin absolu du fichier local (ex: /Users/.../jt-assets/logo.png)" },
            title: { type: "string", description: "Titre WP (sert aussi de nom de fichier slugifié — utilisé pour SEO)" },
            alt: { type: "string", description: "Texte alternatif (OBLIGATOIRE pour SEO + accessibilité)" },
            caption: { type: "string", description: "Légende optionnelle" },
            optimize: { type: "boolean", description: "Convertit en WebP qualité 80, redim à 2000px max. Défaut: true (recommandé)." },
          },
          required: ["localPath"],
        },
      },
      {
        name: "upload_local_files_batch",
        description: "Upload plusieurs fichiers locaux en 1 appel. Même principe que upload_local_file mais en lot. Continue même si certains échouent. Recommandé pour intégrer un dossier d'assets (logos, photos, etc.) en 1 commande.",
        inputSchema: {
          type: "object",
          properties: {
            items: {
              type: "array",
              description: "Liste des fichiers à uploader",
              items: {
                type: "object",
                properties: {
                  localPath: { type: "string", description: "Chemin absolu du fichier local" },
                  title: { type: "string", description: "Titre WP (slugifié pour le nom de fichier)" },
                  alt: { type: "string", description: "Alt SEO" },
                  caption: { type: "string", description: "Légende" },
                },
                required: ["localPath"],
              },
            },
            optimize: { type: "boolean", description: "Convertit tous en WebP. Défaut: true." },
          },
          required: ["items"],
        },
      },

      // ===== v3.9.0 — Custom Post Types =====
      {
        name: "list_post_types",
        description: "Liste tous les post types enregistrés sur le site (built-in pages/posts + CPT custom comme chantier, avis_client, produit). Retourne pour chaque : name, label, supports, taxonomies associées, hierarchical, public, showInRest. À appeler en premier pour découvrir ce qui est dispo.",
        inputSchema: { type: "object", properties: {} },
      },
      {
        name: "create_post",
        description: "Crée un post dans n'importe quel post_type (page, post, ou CPT custom). Supporte meta (ACF compatible — auto-route via update_field si ACF est chargé), taxonomies (slugs OU IDs, création à la volée des termes manquants), featuredImageId. Pour seeder un site avec CPT (galerie, blog, témoignages, etc.).",
        inputSchema: {
          type: "object",
          properties: {
            postType: { type: "string", description: "Slug du post_type (ex: 'chantier', 'avis_client', 'page')" },
            title: { type: "string", description: "Titre du post" },
            content: { type: "string", description: "Contenu HTML (optionnel)" },
            excerpt: { type: "string", description: "Extrait (optionnel)" },
            slug: { type: "string", description: "Slug URL (auto-généré depuis title si omis)" },
            status: { type: "string", enum: ["publish", "draft", "private", "pending", "future"], description: "Statut (défaut: publish)" },
            featuredImageId: { type: "number", description: "ID de l'image WP (depuis upload_media/upload_local_file)" },
            meta: { type: "object", description: "Champs personnalisés (ACF compatible). Format {field_name: value}. Pour gallery: array d'IDs. Pour repeater: array d'objects. Pour relationship: array d'IDs." },
            taxonomies: { type: "object", description: "Format {taxonomy_slug: [term_slug_or_id, ...]}. Crée le terme à la volée s'il n'existe pas." },
            date: { type: "string", description: "Date publication ISO 8601 (défaut: maintenant)" },
            author: { type: "number", description: "ID de l'auteur" },
          },
          required: ["postType", "title"],
        },
      },
      {
        name: "update_post",
        description: "Modifie un post existant (CPT ou natif). Pour modifier juste les ACF, passe uniquement 'meta'. Pour retirer la featured image, passe featuredImageId: 0.",
        inputSchema: {
          type: "object",
          properties: {
            postId: { type: "number", description: "ID du post à modifier" },
            title: { type: "string" },
            content: { type: "string" },
            excerpt: { type: "string" },
            slug: { type: "string" },
            status: { type: "string", enum: ["publish", "draft", "private", "pending"] },
            featuredImageId: { type: "number", description: "0 pour retirer" },
            meta: { type: "object" },
            taxonomies: { type: "object" },
            date: { type: "string" },
          },
          required: ["postId"],
        },
      },
      {
        name: "delete_post",
        description: "Supprime un post. Par défaut → corbeille. force: true → suppression définitive.",
        inputSchema: {
          type: "object",
          properties: {
            postId: { type: "number" },
            force: { type: "boolean", description: "Défaut false (corbeille)" },
          },
          required: ["postId"],
        },
      },
      {
        name: "get_post",
        description: "Récupère un post avec tous ses champs (incluant meta brute + champs ACF formatés + taxonomies + featured image URL).",
        inputSchema: {
          type: "object",
          properties: {
            postId: { type: "number" },
          },
          required: ["postId"],
        },
      },
      {
        name: "list_posts",
        description: "Liste les posts d'un type donné avec filtres taxonomie/meta/search/pagination/order. Pour les Query Loops Bricks et inventaires.",
        inputSchema: {
          type: "object",
          properties: {
            postType: { type: "string" },
            perPage: { type: "number", description: "Défaut 20, max 100" },
            page: { type: "number", description: "Défaut 1" },
            search: { type: "string" },
            status: { type: "string", description: "Défaut 'publish'" },
            taxonomyFilter: { type: "object", description: "Format {taxonomy_slug: 'term-slug'}" },
            metaQuery: { type: "array", description: "WP_Query meta_query compatible" },
            orderBy: { type: "string", enum: ["date", "title", "menu_order", "meta_value"], description: "Défaut 'date'" },
            order: { type: "string", enum: ["ASC", "DESC"], description: "Défaut 'DESC'" },
          },
          required: ["postType"],
        },
      },
      {
        name: "create_taxonomy_term",
        description: "Crée un terme de taxonomie (ex: catégorie 'Salle de bain' dans 'categorie_chantier'). Idempotent : si terme existe déjà avec ce slug/nom, retourne son ID au lieu d'erreurer.",
        inputSchema: {
          type: "object",
          properties: {
            taxonomy: { type: "string", description: "Slug de la taxonomy (ex: 'categorie_chantier')" },
            name: { type: "string", description: "Nom du terme (ex: 'Salle de bain')" },
            slug: { type: "string", description: "Slug URL (auto-généré si omis)" },
            description: { type: "string" },
            parentId: { type: "number", description: "ID du terme parent (pour taxos hiérarchiques)" },
          },
          required: ["taxonomy", "name"],
        },
      },

      // ===== v3.7.0 — SKILL VERSIONING =====
      {
        name: "check_skill_version",
        description: "⭐ À APPELER AU DÉBUT DE CHAQUE CONVERSATION BRICKS. Compare ta version locale du skill (lue dans le frontmatter de SKILL.md, champ `version`) avec la version actuelle côté serveur. Si décalage, prévenir l'utilisateur de re-télécharger le .plugin depuis WP admin → Bricks MCP.",
        inputSchema: {
          type: "object",
          properties: {
            localVersion: {
              type: "string",
              description: "Version skill locale (ex: '1.0.0'), lue depuis le frontmatter du SKILL.md chargé dans ton contexte",
            },
          },
        },
      },

      // ===== v3.6.0 — UPLOAD MEDIA BATCH =====
      {
        name: "upload_media_batch",
        description: "Upload plusieurs images en 1 appel (vs upload_media qui en fait 1 à la fois). Continue même si certaines échouent. Retourne {uploaded, failed, successes: [{id, url, sourceUrl, filename}], failures: [{sourceUrl, error}]}.",
        inputSchema: {
          type: "object",
          properties: {
            items: {
              type: "array",
              description: "Liste des images à uploader",
              items: {
                type: "object",
                properties: {
                  sourceUrl: { type: "string", description: "URL source de l'image" },
                  title: { type: "string", description: "Titre WP (sert aussi de nom de fichier slugifié)" },
                  alt: { type: "string", description: "Texte alternatif (SEO + accessibilité)" },
                  caption: { type: "string", description: "Légende optionnelle" },
                },
                required: ["sourceUrl"],
              },
            },
          },
          required: ["items"],
        },
      },
    ],
  };
});

// Gérer les appels de tools
mcpServer.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;

  console.error(`\n========================================`);
  console.error(`[LOG] Tool appelé: ${name}`);
  console.error(`[LOG] Arguments reçus:`, JSON.stringify(args, null, 2));
  console.error(`========================================\n`);

  try {
    let result;

    switch (name) {
      // ===== OUTILS EXISTANTS =====
      case "list_bricks_pages":
        console.error(`[LOG] Exécution: list_bricks_pages`);
        result = await callWordPressAPI("/list-pages");
        break;

      case "get_page_json":
        console.error(`[LOG] Exécution: get_page_json avec pageId=${args.pageId}`);
        result = await callWordPressAPI("/get-page-json", "POST", {
          pageId: args.pageId,
        });
        break;

      case "update_page_json":
        console.error(`[LOG] Exécution: update_page_json`);
        console.error(`[LOG] pageId: ${args.pageId}`);
        console.error(`[LOG] newJsonData type:`, typeof args.newJsonData);
        console.error(`[LOG] newJsonData est un array?`, Array.isArray(args.newJsonData));
        result = await callWordPressAPI("/update-page-json", "POST", {
          pageId: args.pageId,
          newJsonData: args.newJsonData,
        });
        break;

      case "analyze_json_structure":
        console.error(`[LOG] Exécution: analyze_json_structure`);
        result = await callWordPressAPI("/analyze-json", "POST", {
          jsonData: args.jsonData,
        });
        break;

      // ===== OUTILS v2.0 =====
      
      case "get_page_structure":
        console.error(`[LOG] Exécution: get_page_structure avec pageId=${args.pageId}`);
        result = await callWordPressAPI("/get-page-structure", "POST", {
          pageId: args.pageId,
        });
        break;

      case "find_elements":
        console.error(`[LOG] Exécution: find_elements`);
        console.error(`[LOG] Critères:`, JSON.stringify(args.criteria, null, 2));
        result = await callWordPressAPI("/find-elements", "POST", {
          pageId: args.pageId,
          criteria: args.criteria || {},
          limit: args.limit || 100,
        });
        break;

      case "get_element":
        console.error(`[LOG] Exécution: get_element`);
        console.error(`[LOG] pageId: ${args.pageId}, elementId: ${args.elementId}`);
        result = await callWordPressAPI("/get-element", "POST", {
          pageId: args.pageId,
          elementId: args.elementId,
        });
        break;

      case "update_element":
        console.error(`[LOG] Exécution: update_element`);
        console.error(`[LOG] Modification de l'élément ${args.elementId}${args.label ? ` (label: "${args.label}")` : ''}`);
        result = await callWordPressAPI("/update-element", "POST", {
          pageId: args.pageId,
          elementId: args.elementId,
          newSettings: args.newSettings || {},
          label: args.label,
        });
        break;

      case "add_element":
        console.error(`[LOG] Exécution: add_element`);
        console.error(`[LOG] Ajout de l'élément:`, JSON.stringify(args.element, null, 2));
        result = await callWordPressAPI("/add-element", "POST", {
          pageId: args.pageId,
          element: args.element,
          position: args.position,
        });
        break;

      case "batch_add":
        console.error(`[LOG] Exécution: batch_add`);
        console.error(`[LOG] Ajout de ${args.elements.length} éléments`);
        result = await callWordPressAPI("/batch-add", "POST", {
          pageId: args.pageId,
          elements: args.elements,
        });
        break;

      case "delete_element":
        console.error(`[LOG] Exécution: delete_element`);
        console.error(`[LOG] Suppression de l'élément ${args.elementId}`);
        result = await callWordPressAPI("/delete-element", "POST", {
          pageId: args.pageId,
          elementId: args.elementId,
        });
        break;

      // ===== 🆕 NOUVEL OUTIL v3.0 : REORDER_SECTIONS =====
      
      case "reorder_sections":
        console.error(`[LOG] Exécution: reorder_sections`);
        console.error(`[LOG] pageId: ${args.pageId}`);
        console.error(`[LOG] orderedIds:`, JSON.stringify(args.orderedIds, null, 2));
        result = await callWordPressAPI("/reorder-sections", "POST", {
          pageId: args.pageId,
          orderedIds: args.orderedIds,
        });
        break;

      // ===== OUTIL v3.2.0 — CRÉATION DE PAGES =====

      case "create_page":
        console.error(`[LOG] Exécution: create_page avec title="${args.title}"`);
        result = await callWordPressAPI("/create-page", "POST", {
          title: args.title,
          slug: args.slug || "",
          status: args.status || "publish",
          setAsHomepage: args.setAsHomepage || false,
        });
        break;

      // ===== OUTILS v3.3.0 — GESTION DES PAGES =====

      case "delete_page":
        console.error(`[LOG] Exécution: delete_page pageId=${args.pageId} force=${args.force}`);
        result = await callWordPressAPI("/delete-page", "POST", {
          pageId: args.pageId,
          force: args.force || false,
        });
        break;

      case "update_page_meta":
        console.error(`[LOG] Exécution: update_page_meta pageId=${args.pageId}`);
        result = await callWordPressAPI("/update-page-meta", "POST", {
          pageId: args.pageId,
          title: args.title,
          slug: args.slug,
          status: args.status,
          parentId: args.parentId,
        });
        break;

      case "duplicate_page":
        console.error(`[LOG] Exécution: duplicate_page source=${args.sourcePageId}`);
        result = await callWordPressAPI("/duplicate-page", "POST", {
          sourcePageId: args.sourcePageId,
          newTitle: args.newTitle || "",
          status: args.status || "draft",
        });
        break;

      case "set_homepage":
        console.error(`[LOG] Exécution: set_homepage pageId=${args.pageId} reset=${args.reset}`);
        result = await callWordPressAPI("/set-homepage", "POST", {
          pageId: args.pageId,
          reset: args.reset || false,
        });
        break;

      // ===== OUTILS v3.4.0 — HEALTH, MÉDIAS, MENUS, STYLES GLOBAUX =====

      case "health_check":
        console.error(`[LOG] Exécution: health_check`);
        result = await callWordPressAPI("/health", "GET");
        break;

      case "list_all_pages":
        console.error(`[LOG] Exécution: list_all_pages`);
        result = await callWordPressAPI("/list-all-pages", "GET");
        break;

      case "upload_media":
        console.error(`[LOG] Exécution: upload_media depuis ${args.sourceUrl}`);
        result = await callWordPressAPI("/upload-media", "POST", {
          sourceUrl: args.sourceUrl,
          title: args.title,
          alt: args.alt,
          caption: args.caption,
        });
        break;

      case "list_media":
        console.error(`[LOG] Exécution: list_media page=${args.page} perPage=${args.perPage}`);
        result = await callWordPressAPI("/list-media", "POST", {
          page: args.page || 1,
          perPage: args.perPage || 20,
          search: args.search,
        });
        break;

      case "list_menus":
        console.error(`[LOG] Exécution: list_menus`);
        result = await callWordPressAPI("/list-menus", "GET");
        break;

      case "add_menu_item":
        console.error(`[LOG] Exécution: add_menu_item menuId=${args.menuId}`);
        result = await callWordPressAPI("/add-menu-item", "POST", {
          menuId: args.menuId,
          pageId: args.pageId,
          customUrl: args.customUrl,
          label: args.label,
          parentItemId: args.parentItemId,
        });
        break;

      case "get_global_styles":
        console.error(`[LOG] Exécution: get_global_styles`);
        result = await callWordPressAPI("/get-global-styles", "GET");
        break;

      case "update_global_styles":
        console.error(`[LOG] Exécution: update_global_styles`);
        result = await callWordPressAPI("/update-global-styles", "POST", {
          settings: args.settings || {},
        });
        break;

      case "list_color_palette":
        console.error(`[LOG] Exécution: list_color_palette`);
        result = await callWordPressAPI("/list-color-palette", "GET");
        break;

      case "add_color_to_palette":
        console.error(`[LOG] Exécution: add_color_to_palette name="${args.name}"`);
        result = await callWordPressAPI("/add-color-to-palette", "POST", {
          name: args.name,
          hex: args.hex,
        });
        break;

      // ===== OUTILS v3.5.0 — PHASE A : INSPECTION + CUSTOM CODE + FONTS + CODE EXEC =====

      case "list_bricks_options":
        result = await callWordPressAPI("/list-bricks-options", "GET");
        break;

      case "get_bricks_option":
        result = await callWordPressAPI("/get-bricks-option", "POST", { name: args.name });
        break;

      case "get_custom_code":
        result = await callWordPressAPI("/get-custom-code", "GET");
        break;

      case "set_custom_code":
        result = await callWordPressAPI("/set-custom-code", "POST", {
          customCss: args.customCss,
          customScriptsHeader: args.customScriptsHeader,
          customScriptsBodyHeader: args.customScriptsBodyHeader,
          customScriptsBodyFooter: args.customScriptsBodyFooter,
        });
        break;

      case "get_code_execution_status":
        result = await callWordPressAPI("/get-code-execution-status", "GET");
        break;

      case "set_code_execution":
        result = await callWordPressAPI("/set-code-execution", "POST", {
          enabled: args.enabled,
          roles: args.roles || [],
        });
        break;

      case "list_custom_fonts":
        result = await callWordPressAPI("/list-custom-fonts", "GET");
        break;

      case "register_custom_font":
        result = await callWordPressAPI("/register-custom-font", "POST", {
          name: args.name,
          fontFamily: args.fontFamily || args.name,
          faces: args.faces,
        });
        break;

      case "delete_custom_font":
        result = await callWordPressAPI("/delete-custom-font", "POST", { id: args.id });
        break;

      case "register_google_font_locally":
        result = await callWordPressAPI("/register-google-font-locally", "POST", {
          name: args.name,
          weights: args.weights || [400],
        });
        break;

      // ===== OUTILS v3.5.0 — PHASE B : GLOBAL CLASSES + THEME STYLES + PAGE CODE =====

      case "list_global_classes":
        result = await callWordPressAPI("/list-global-classes", "GET");
        break;

      case "create_global_class":
        result = await callWordPressAPI("/create-global-class", "POST", {
          name: args.name,
          settings: args.settings || {},
        });
        break;

      case "update_global_class":
        result = await callWordPressAPI("/update-global-class", "POST", {
          id: args.id,
          name: args.name,
          settings: args.settings,
        });
        break;

      case "delete_global_class":
        result = await callWordPressAPI("/delete-global-class", "POST", { id: args.id });
        break;

      case "list_theme_styles":
        result = await callWordPressAPI("/list-theme-styles", "GET");
        break;

      case "create_theme_style":
        result = await callWordPressAPI("/create-theme-style", "POST", {
          name: args.name,
          settings: args.settings || {},
          conditions: args.conditions || [],
        });
        break;

      case "update_theme_style":
        result = await callWordPressAPI("/update-theme-style", "POST", {
          id: args.id,
          name: args.name,
          settings: args.settings,
          conditions: args.conditions,
        });
        break;

      case "delete_theme_style":
        result = await callWordPressAPI("/delete-theme-style", "POST", { id: args.id });
        break;

      case "get_page_custom_code":
        result = await callWordPressAPI("/get-page-custom-code", "POST", { pageId: args.pageId });
        break;

      case "set_page_custom_code":
        result = await callWordPressAPI("/set-page-custom-code", "POST", {
          pageId: args.pageId,
          customCss: args.customCss,
          customScripts: args.customScripts,
        });
        break;

      // ===== OUTILS v3.5.0 — PHASE C : STYLE MANAGER 2.2 + COMPONENTS =====

      case "list_typography_scales":
        result = await callWordPressAPI("/list-typography-scales", "GET");
        break;

      case "set_typography_scale":
        result = await callWordPressAPI("/set-typography-scale", "POST", {
          id: args.id,
          name: args.name,
          values: args.values || {},
        });
        break;

      case "list_spacing_scales":
        result = await callWordPressAPI("/list-spacing-scales", "GET");
        break;

      case "set_spacing_scale":
        result = await callWordPressAPI("/set-spacing-scale", "POST", {
          id: args.id,
          name: args.name,
          values: args.values || {},
        });
        break;

      case "list_css_variables":
        result = await callWordPressAPI("/list-css-variables", "GET");
        break;

      case "set_css_variable":
        result = await callWordPressAPI("/set-css-variable", "POST", {
          name: args.name,
          value: args.value,
        });
        break;

      case "list_components":
        result = await callWordPressAPI("/list-components", "GET");
        break;

      // ===== v3.10 — VERIFY ELEMENT (multi-viewport + checks pluggables) =====
      case "verify_element": {
        const pageId = args.pageId;
        const elementId = args.elementId;
        // Backward compat : `viewport` (singulier) OU `viewports` (array)
        const viewportList = (Array.isArray(args.viewports) && args.viewports.length > 0)
          ? args.viewports
          : [args.viewport || "desktop"];
        const isMulti = viewportList.length > 1;
        const checksConfig = Object.assign({
          expected_styles: true,
          sibling_coherence: true,
          empty_containers: true,
          media_health: true,
          overflow: true,
          console_errors: true,
        }, args.checks || {});
        const viewportSizes = {
          desktop: { width: 1920, height: 1080 },
          tablet: { width: 991, height: 1200 },
          mobile_landscape: { width: 767, height: 600 },
          mobile_portrait: { width: 478, height: 800 },
        };

        // 1) Récupérer infos plugin une seule fois (URL, sélecteur, expected styles)
        const info = await callWordPressAPI("/verify-element-info", "POST", { pageId, elementId });

        // 2) Boucler sur chaque viewport — chacun = 1 page, 1 screenshot, 1 report
        const perViewport = [];

        for (const viewport of viewportList) {
          const viewportContext = viewportSizes[viewport] || viewportSizes.desktop;

          let page;
          try {
            page = await getNewPage(viewport);
          } catch (browserErr) {
            perViewport.push({
              viewport,
              success: false,
              error: browserErr.message,
              hint: "Installation Chromium requise pour verify_element. À défaut, utilise screenshot-website-fast en MCP externe.",
            });
            continue;
          }

          const consoleErrors = [];
          page.on('console', msg => {
            if (msg.type() === 'error') consoleErrors.push(msg.text());
          });
          page.on('pageerror', err => consoleErrors.push('PageError: ' + err.message));

          let screenshotBase64 = null;
          let audit = null;
          let loadedFonts = [];

          try {
            await page.goto(info.url, { waitUntil: 'domcontentloaded', timeout: 30000 });
            await page.waitForTimeout(2000);

            const element = await page.$(info.selector);
            if (!element) {
              const diagnostics = await page.evaluate(() => ({
                actualUrl: window.location.href,
                title: document.title,
                bodyClass: document.body?.className || '',
                brxeCount: document.querySelectorAll('[class*="brxe-"]').length,
                firstBrxe: document.querySelector('[class*="brxe-"]')?.className || null,
                htmlSnippet: document.documentElement.outerHTML.substring(0, 500),
              }));
              perViewport.push({
                viewport,
                success: false,
                error: `Élément ${info.selector} introuvable dans le DOM`,
                diagnostics,
                hint: diagnostics.brxeCount === 0
                  ? "Aucune classe .brxe-* trouvée — la page n'a probablement pas chargé le contenu Bricks (cert SSL, redirect, 404, page d'erreur)"
                  : `${diagnostics.brxeCount} éléments .brxe-* trouvés mais pas ${info.selector} — l'ID dans la DB ne correspond pas au rendu`,
              });
              continue;
            }

            await element.scrollIntoViewIfNeeded();
            await page.waitForTimeout(400);

            // === v3.10 : collecte enrichie en 1 evaluate (self + siblings + media + emptyContainers) ===
            audit = await page.evaluate((selector) => {
              const el = document.querySelector(selector);
              if (!el) return null;
              const cs = getComputedStyle(el);
              const rect = el.getBoundingClientRect();

              const realChildren = Array.from(el.children).filter(child => {
                if (child.id && child.id.startsWith('brxe-')) return true;
                return Array.from(child.classList).some(cls => cls.startsWith('brxe-'));
              });
              const bricksInternalChildren = Array.from(el.children).filter(child => {
                return !((child.id && child.id.startsWith('brxe-')) ||
                         Array.from(child.classList).some(cls => cls.startsWith('brxe-')));
              }).map(c => c.tagName.toLowerCase() + (c.className ? '.' + c.className.split(' ').filter(Boolean).join('.') : ''));

              const computed = {
                display: cs.display,
                'flex-direction': cs.flexDirection,
                'justify-content': cs.justifyContent,
                'align-items': cs.alignItems,
                gap: cs.gap,
                'column-gap': cs.columnGap,
                'row-gap': cs.rowGap,
                width: Math.round(rect.width) + 'px',
                height: Math.round(rect.height) + 'px',
                'max-width': cs.maxWidth,
                'padding-top': cs.paddingTop,
                'padding-right': cs.paddingRight,
                'padding-bottom': cs.paddingBottom,
                'padding-left': cs.paddingLeft,
                'margin-top': cs.marginTop,
                'margin-right': cs.marginRight,
                'margin-bottom': cs.marginBottom,
                'margin-left': cs.marginLeft,
                'background-color': cs.backgroundColor,
                'font-size': cs.fontSize,
                'font-family': cs.fontFamily,
                'font-weight': cs.fontWeight,
                'line-height': cs.lineHeight,
                color: cs.color,
                'text-align': cs.textAlign,
                'border-top-left-radius': cs.borderTopLeftRadius,
                'border-top-right-radius': cs.borderTopRightRadius,
                'border-bottom-right-radius': cs.borderBottomRightRadius,
                'border-bottom-left-radius': cs.borderBottomLeftRadius,
                visibility: cs.visibility,
                opacity: cs.opacity,
                childrenInDom: realChildren.length,
                childrenTotalDom: el.children.length,
                bricksInternalChildren,
                isVisible: rect.width > 0 && rect.height > 0 && cs.visibility !== 'hidden' && cs.opacity !== '0',
                hasOverflowX: el.scrollWidth > el.clientWidth,
              };

              // SIBLINGS (frères directs Bricks)
              const self = {
                id: el.id || '',
                'text-align': cs.textAlign,
                'font-size': cs.fontSize,
                'font-size-px': parseFloat(cs.fontSize),
              };
              let siblings = [];
              if (el.parentElement) {
                const realSiblings = Array.from(el.parentElement.children).filter(s => {
                  if (s === el) return false;
                  if (s.id && s.id.startsWith('brxe-')) return true;
                  return Array.from(s.classList).some(c => c.startsWith('brxe-'));
                });
                siblings = realSiblings.map(s => {
                  const scs = getComputedStyle(s);
                  const srect = s.getBoundingClientRect();
                  return {
                    id: s.id || '',
                    classes: Array.from(s.classList).filter(c => c.startsWith('brxe-')),
                    'text-align': scs.textAlign,
                    'font-size': scs.fontSize,
                    'font-size-px': parseFloat(scs.fontSize),
                    rect: { x: Math.round(srect.x), y: Math.round(srect.y), w: Math.round(srect.width), h: Math.round(srect.height) },
                  };
                });
              }

              // MEDIA (img + video à l'intérieur de l'élément)
              const images = Array.from(el.querySelectorAll('img')).map(img => ({
                src: img.currentSrc || img.src || '',
                naturalWidth: img.naturalWidth,
                naturalHeight: img.naturalHeight,
                alt: img.alt || '',
                loaded: img.naturalWidth > 0,
                loading: img.getAttribute('loading') || 'eager',
              }));
              const videos = Array.from(el.querySelectorAll('video')).map(v => ({
                src: v.currentSrc || v.src || '',
                readyState: v.readyState,
                paused: v.paused,
                loaded: v.readyState >= 2,
              }));

              // EMPTY CONTAINERS (descendants Bricks visibles ≥ 50×50px sans contenu)
              const allBrxe = Array.from(el.querySelectorAll('[id^="brxe-"], [class*="brxe-"]'));
              const emptyContainers = [];
              const MIN_AREA = 2500;
              allBrxe.forEach(b => {
                const brect = b.getBoundingClientRect();
                if (brect.width < 50 || brect.height < 50) return;
                const area = brect.width * brect.height;
                if (area < MIN_AREA) return;
                const hasText = (b.textContent || '').trim().length > 0;
                const hasMedia = b.querySelector('img, picture, svg, video, iframe') !== null;
                const hasInteractive = b.querySelector('a, button, input, select, textarea') !== null;
                const bcs = getComputedStyle(b);
                const hasBgImg = bcs.backgroundImage && bcs.backgroundImage !== 'none';
                if (!hasText && !hasMedia && !hasInteractive && !hasBgImg) {
                  emptyContainers.push({
                    id: b.id || '',
                    classes: Array.from(b.classList).filter(c => c.startsWith('brxe-')),
                    rect: { x: Math.round(brect.x), y: Math.round(brect.y), w: Math.round(brect.width), h: Math.round(brect.height) },
                    area: Math.round(area),
                  });
                }
              });

              return { computed, self, siblings, media: { images, videos }, emptyContainers };
            }, info.selector);

            // Fonts loaded
            loadedFonts = await page.evaluate(() => {
              const set = new Set();
              document.fonts.forEach(f => { if (f.status === 'loaded') set.add(f.family); });
              return Array.from(set);
            });

            // === Construction des checks ===
            const checks = [];
            const computed = audit.computed;
            checks.push({ ok: true, label: `Élément trouvé dans le DOM (${info.selector})` });
            checks.push({
              ok: computed.isVisible,
              label: `Élément visible (${computed.width} × ${computed.height})`,
              ...(computed.isVisible ? {} : { severity: 'critical', hint: "Largeur ou hauteur à 0 — vérifie les enfants ou le padding du parent" })
            });

            if (typeof info.childrenCount === 'number') {
              const ok = info.childrenCount === computed.childrenInDom;
              checks.push({
                ok,
                label: `${info.childrenCount} enfant(s) attendu(s) → ${computed.childrenInDom} dans le DOM`,
                ...(ok ? {} : { severity: 'warning', expected: info.childrenCount, got: computed.childrenInDom })
              });
            }

            // Expected styles (viewport-aware vh/vw)
            if (checksConfig.expected_styles) {
              const expected = info.expected || {};
              for (const [prop, expectedVal] of Object.entries(expected)) {
                const got = computed[prop];
                if (got === undefined) continue;
                const ok = normaliseCssValue(got, viewportContext) === normaliseCssValue(expectedVal, viewportContext);
                const check = { ok, label: `${prop} = ${expectedVal}` };
                if (!ok) {
                  check.severity = 'warning';
                  check.expected = expectedVal;
                  check.got = got;
                  const hint = generateHint(prop, expectedVal, got);
                  if (hint) check.hint = hint;
                }
                checks.push(check);
              }
            }

            // v3.10 : cohérence siblings
            if (checksConfig.sibling_coherence) {
              checks.push(...buildSiblingCoherenceChecks(audit));
            }
            // v3.10 : containers vides
            if (checksConfig.empty_containers) {
              checks.push(...buildEmptyContainerChecks(audit));
            }
            // v3.10 : santé médias
            if (checksConfig.media_health) {
              checks.push(...buildMediaHealthChecks(audit));
            }

            // Console errors : séparer JS vs réseau (429/timeout) — réseau = info, JS = échec
            if (checksConfig.console_errors) {
              const realErrors = consoleErrors.filter(e =>
                !e.includes('429') && !e.includes('net::ERR_') && !e.includes('Failed to load resource')
              );
              const networkErrors = consoleErrors.filter(e =>
                e.includes('429') || e.includes('net::ERR_') || e.includes('Failed to load resource')
              );
              if (realErrors.length > 0) {
                checks.push({
                  ok: false,
                  severity: 'critical',
                  label: `${realErrors.length} erreur(s) JS dans la console`,
                  got: realErrors.slice(0, 3),
                  hint: "Erreur JavaScript détectée — pas lié au serveur",
                });
              } else {
                checks.push({ ok: true, label: "Aucune erreur JS console" });
              }
              if (networkErrors.length > 0) {
                checks.push({
                  ok: true,
                  severity: 'info',
                  label: `${networkErrors.length} erreur(s) réseau (429/load) — non bloquant`,
                  got: networkErrors.slice(0, 2),
                });
              }
            }

            // Overflow horizontal
            if (checksConfig.overflow && computed.hasOverflowX) {
              checks.push({
                ok: false,
                severity: 'warning',
                label: "Débordement horizontal détecté",
                hint: "Un enfant dépasse la largeur du conteneur — vérifie les _widthMax et white-space",
              });
            }

            // Screenshot crop (fallback fullpage si l'élément ne peut pas être capturé seul)
            try {
              const buf = await element.screenshot({ type: 'png' });
              screenshotBase64 = buf.toString('base64');
            } catch (e) {
              const buf = await page.screenshot({ type: 'png', fullPage: false });
              screenshotBase64 = buf.toString('base64');
            }

            const okCount = checks.filter(c => c.ok).length;
            perViewport.push({
              viewport,
              success: true,
              report: { score: `${okCount}/${checks.length}`, checks },
              computed,
              loadedFonts,
              screenshotBase64,
            });
          } finally {
            if (page && !page.isClosed()) {
              await page.close().catch(() => {});
            }
          }
        }

        // 3) Construction de la réponse MCP
        const baseInfo = {
          url: info.url,
          urlWithAnchor: info.urlWithAnchor,
          selector: info.selector,
          name: info.name,
          label: info.label,
        };
        const responseContent = [];

        if (isMulti) {
          // Multi-viewport : 1 JSON consolidé + 1 image par viewport (les b64 sont retirés du JSON)
          const summary = {
            ...baseInfo,
            viewports: perViewport.map(vr => {
              const { screenshotBase64: _drop, ...rest } = vr;
              return rest;
            }),
          };
          responseContent.push({ type: "text", text: JSON.stringify(summary, null, 2) });
          for (const vr of perViewport) {
            if (vr.screenshotBase64) {
              responseContent.push({ type: "image", data: vr.screenshotBase64, mimeType: "image/png" });
            }
          }
        } else {
          // Single viewport : structure plate (backward compat)
          const vr = perViewport[0];
          let flatResult;
          if (vr.success) {
            flatResult = {
              success: true,
              ...baseInfo,
              viewport: vr.viewport,
              report: vr.report,
              computed: vr.computed,
              loadedFonts: vr.loadedFonts,
            };
          } else {
            flatResult = {
              success: false,
              ...baseInfo,
              viewport: vr.viewport,
              error: vr.error,
              diagnostics: vr.diagnostics,
              hint: vr.hint,
            };
          }
          responseContent.push({ type: "text", text: JSON.stringify(flatResult, null, 2) });
          if (vr.screenshotBase64) {
            responseContent.push({ type: "image", data: vr.screenshotBase64, mimeType: "image/png" });
          }
        }

        return { content: responseContent };
      }

      // ===== v3.11 — AUDIT_PAGE (fullpage + annotations + report) =====
      case "audit_page": {
        const pageId = args.pageId;
        const viewportList = (Array.isArray(args.viewports) && args.viewports.length > 0)
          ? args.viewports
          : ["desktop", "mobile_portrait"];
        const checksConfig = Object.assign({
          empty_containers: true,
          media_health: true,
          sibling_coherence: true,
          page_overflow: true,
        }, args.checks || {});
        const maxAnnotations = typeof args.maxAnnotations === 'number' ? args.maxAnnotations : 30;
        const viewportSizes = {
          desktop: { width: 1920, height: 1080 },
          tablet: { width: 991, height: 1200 },
          mobile_landscape: { width: 767, height: 600 },
          mobile_portrait: { width: 478, height: 800 },
        };

        // 1) Récupérer l'URL de la page via /list-pages (pas de plugin update nécessaire)
        const allPages = await callWordPressAPI("/list-pages", "GET");
        const pageMeta = (Array.isArray(allPages) ? allPages : []).find(p => p.id === pageId);
        if (!pageMeta) {
          return { content: [{ type: "text", text: JSON.stringify({ success: false, error: `Page ${pageId} introuvable dans list_bricks_pages`, hint: "Vérifie l'ID via list_bricks_pages." }, null, 2) }] };
        }
        const pageUrl = pageMeta.url;

        // 2) Boucler sur chaque viewport
        const perViewport = [];

        for (const viewport of viewportList) {
          let page;
          try {
            page = await getNewPage(viewport);
          } catch (browserErr) {
            perViewport.push({
              viewport,
              success: false,
              error: browserErr.message,
              hint: "Installation Chromium requise pour audit_page. Lance : npx playwright install chromium",
            });
            continue;
          }

          try {
            await page.goto(pageUrl, { waitUntil: 'load', timeout: 30000 });
            await page.waitForTimeout(1000);
            // v3.11.3 — Forcer toutes les <img loading="lazy"> en eager pour
            // déclencher leur chargement IMMÉDIATEMENT (sans attendre l'IntersectionObserver).
            // Sinon Bricks lazy-load les images et mon audit court trop tôt.
            await page.evaluate(() => {
              document.querySelectorAll('img[loading="lazy"]').forEach(img => {
                img.loading = 'eager';
              });
            });
            // Scroll bottom→top pour déclencher les autres lazy-load (background-image, custom JS)
            await page.evaluate(() => {
              return new Promise(resolve => {
                const totalHeight = document.documentElement.scrollHeight;
                let scrolled = 0;
                const step = 400;
                const timer = setInterval(() => {
                  window.scrollBy(0, step);
                  scrolled += step;
                  if (scrolled >= totalHeight) {
                    clearInterval(timer);
                    window.scrollTo(0, 0);
                    setTimeout(resolve, 500);
                  }
                }, 80);
              });
            });
            // networkidle après le scroll pour laisser les images se télécharger
            try {
              await page.waitForLoadState('networkidle', { timeout: 12000 });
            } catch {}
            // v3.11.3 — vrai check : naturalWidth > 0 (pas juste img.complete qui est trompeur).
            // img.complete = true pour les imgs qui ne se sont pas chargées du tout.
            try {
              await page.waitForFunction(
                () => Array.from(document.images).every(i => i.naturalWidth > 0 || !i.src),
                { timeout: 8000 }
              );
            } catch {} // si une img reste vraiment cassée, on continue (elle sera flaggée en broken_image)
            await page.waitForTimeout(500);

            // 3) Collecte d'issues globales sur toute la page
            const auditResult = await page.evaluate((cfg) => {
              const issues = [];
              const allBrxe = Array.from(document.querySelectorAll('[id^="brxe-"], [class*="brxe-"]'));

              // Helper : bbox en coordonnées absolues (page entière, pas viewport)
              const absBbox = (rect) => ({
                x: Math.round(rect.left + window.scrollX),
                y: Math.round(rect.top + window.scrollY),
                w: Math.round(rect.width),
                h: Math.round(rect.height),
              });

              // === Empty containers ===
              if (cfg.empty_containers) {
                // v3.11.5 — Bricks 2.3 rend ses images comme des <img class="brxe-image" id="brxe-{id}">
                // (pas un wrapper). Idem pour vidéos/iframes/svg. On skip ces balises : elles SONT le média.
                const MEDIA_TAGS = new Set(['IMG', 'PICTURE', 'SVG', 'VIDEO', 'IFRAME', 'CANVAS', 'AUDIO', 'OBJECT', 'EMBED']);
                const INTERACTIVE_TAGS = new Set(['A', 'BUTTON', 'INPUT', 'SELECT', 'TEXTAREA']);
                allBrxe.forEach(b => {
                  // Skip si l'élément EST déjà un média ou un interactif natif
                  if (MEDIA_TAGS.has(b.tagName) || INTERACTIVE_TAGS.has(b.tagName)) return;
                  const rect = b.getBoundingClientRect();
                  if (rect.width < 50 || rect.height < 50) return;
                  const area = rect.width * rect.height;
                  if (area < 2500) return;
                  const hasText = (b.textContent || '').trim().length > 0;
                  const hasMedia = b.querySelector('img, picture, svg, video, iframe, canvas, audio, object, embed') !== null;
                  const hasInteractive = b.querySelector('a, button, input, select, textarea') !== null;
                  const bcs = getComputedStyle(b);
                  const hasBgImg = bcs.backgroundImage && bcs.backgroundImage !== 'none';
                  // Détecte aussi les background-image sur les descendants directs (cas Bricks où le bg est sur un sub-div)
                  const hasChildBgImg = !hasBgImg && Array.from(b.children).some(c => {
                    const ccs = getComputedStyle(c);
                    return ccs.backgroundImage && ccs.backgroundImage !== 'none';
                  });
                  if (!hasText && !hasMedia && !hasInteractive && !hasBgImg && !hasChildBgImg) {
                    issues.push({
                      type: 'empty_container',
                      severity: 'warning',
                      element: b.id || (Array.from(b.classList).find(c => c.startsWith('brxe-')) || '?'),
                      label: `Container vide ${Math.round(rect.width)}×${Math.round(rect.height)}px`,
                      hint: "Bloc visible sans contenu — souvent un wrapper écrasé par align-items: stretch. Fixer aspect-ratio ou ajouter du contenu.",
                      bbox: absBbox(rect),
                    });
                  }
                });
              }

              // === Media health ===
              if (cfg.media_health) {
                const imgs = Array.from(document.querySelectorAll('img'));
                imgs.forEach(img => {
                  const rect = img.getBoundingClientRect();
                  if (rect.width < 1 || rect.height < 1) return;
                  if (!img.naturalWidth) {
                    issues.push({
                      type: 'broken_image',
                      severity: 'critical',
                      element: (img.src || '').split('/').pop() || '(no src)',
                      label: `Image non chargée : ${(img.src || '').split('/').pop() || '(no src)'}`,
                      hint: "naturalWidth = 0 — lazy-load non déclenché ou 404. Vérifie l'URL src et la stratégie de chargement.",
                      bbox: absBbox(rect),
                    });
                  } else if (!img.alt || !img.alt.trim()) {
                    issues.push({
                      type: 'no_alt',
                      severity: 'warning',
                      element: (img.src || '').split('/').pop() || '(no src)',
                      label: `alt manquant sur image`,
                      hint: "Renseigne alt à l'upload (upload_local_file({alt})) — accessibilité + SEO.",
                      bbox: absBbox(rect),
                    });
                  }
                });
              }

              // === Sibling coherence ===
              if (cfg.sibling_coherence) {
                // v3.11.2 : start === left, end === right en LTR (équivalents CSS)
                const dir = getComputedStyle(document.documentElement).direction || 'ltr';
                const normalizeAlign = (val) => {
                  if (dir === 'ltr') {
                    if (val === 'start') return 'left';
                    if (val === 'end') return 'right';
                  } else {
                    if (val === 'start') return 'right';
                    if (val === 'end') return 'left';
                  }
                  return val;
                };
                allBrxe.forEach(parent => {
                  const realChildren = Array.from(parent.children).filter(c =>
                    (c.id && c.id.startsWith('brxe-')) ||
                    Array.from(c.classList).some(cls => cls.startsWith('brxe-'))
                  );
                  if (realChildren.length < 2) return;
                  const aligns = [...new Set(realChildren.map(c => normalizeAlign(getComputedStyle(c).textAlign)))];
                  if (aligns.length > 1) {
                    const rect = parent.getBoundingClientRect();
                    issues.push({
                      type: 'mixed_text_align',
                      severity: 'warning',
                      element: parent.id || (Array.from(parent.classList).find(c => c.startsWith('brxe-')) || '?'),
                      label: `text-align mixé entre frères (${aligns.join(', ')})`,
                      hint: "Frères directs avec text-align différents — souvent un bug visuel. Aligner ou justifier le choix.",
                      bbox: absBbox(rect),
                    });
                  }
                });
              }

              // === Page overflow X ===
              if (cfg.page_overflow) {
                const doc = document.documentElement;
                if (doc.scrollWidth > doc.clientWidth + 1) {
                  issues.push({
                    type: 'page_overflow_x',
                    severity: 'critical',
                    element: 'document',
                    label: `Débordement horizontal de la page (${doc.scrollWidth}px > ${doc.clientWidth}px)`,
                    hint: "Un élément dépasse en largeur. Cherche les _widthMax manquants ou un white-space:nowrap.",
                    bbox: null,
                  });
                }
              }

              return {
                issues,
                pageDimensions: {
                  width: document.documentElement.clientWidth,
                  height: document.documentElement.scrollHeight,
                },
                totalBrxe: allBrxe.length,
              };
            }, checksConfig);

            // 4) Limiter les annotations (les plus sévères en premier)
            const severityRank = { critical: 0, warning: 1, info: 2 };
            const annotatable = auditResult.issues
              .filter(i => i.bbox)
              .sort((a, b) => (severityRank[a.severity] ?? 9) - (severityRank[b.severity] ?? 9))
              .slice(0, maxAnnotations);

            // 5) Injecter les overlays dans le DOM puis screenshot fullpage
            await page.evaluate((annotations) => {
              const colors = { critical: '#ef4444', warning: '#f59e0b', info: '#facc15' };
              const labelBg = { critical: 'rgba(239, 68, 68, 0.9)', warning: 'rgba(245, 158, 11, 0.9)', info: 'rgba(250, 204, 21, 0.9)' };
              annotations.forEach((ann, idx) => {
                const overlay = document.createElement('div');
                overlay.style.cssText = `
                  position: absolute;
                  left: ${ann.bbox.x}px;
                  top: ${ann.bbox.y}px;
                  width: ${ann.bbox.w}px;
                  height: ${ann.bbox.h}px;
                  border: 3px solid ${colors[ann.severity] || '#888'};
                  pointer-events: none;
                  z-index: 2147483646;
                  box-sizing: border-box;
                `;
                document.body.appendChild(overlay);

                // Petit label en haut à gauche du cadre avec le numéro
                const label = document.createElement('div');
                label.textContent = String(idx + 1);
                label.style.cssText = `
                  position: absolute;
                  left: ${ann.bbox.x}px;
                  top: ${Math.max(0, ann.bbox.y - 24)}px;
                  background: ${labelBg[ann.severity] || 'rgba(136,136,136,0.9)'};
                  color: white;
                  font-family: system-ui, sans-serif;
                  font-size: 14px;
                  font-weight: 700;
                  padding: 2px 8px;
                  border-radius: 3px;
                  pointer-events: none;
                  z-index: 2147483647;
                `;
                document.body.appendChild(label);
              });
            }, annotatable);

            // Fullpage screenshot
            const buf = await page.screenshot({ type: 'jpeg', quality: 80, fullPage: true });
            const screenshotBase64 = buf.toString('base64');

            // 6) Aggrégation
            const counts = { critical: 0, warning: 0, info: 0 };
            auditResult.issues.forEach(i => {
              counts[i.severity] = (counts[i.severity] || 0) + 1;
            });

            perViewport.push({
              viewport,
              success: true,
              pageDimensions: auditResult.pageDimensions,
              totalBrxeElements: auditResult.totalBrxe,
              severityCounts: counts,
              totalIssues: auditResult.issues.length,
              annotated: annotatable.length,
              // On numérote les issues annotées pour matcher avec les cadres sur le screenshot
              issues: auditResult.issues.map((iss, idx) => {
                const annIdx = annotatable.indexOf(iss);
                return annIdx >= 0 ? { ...iss, annotationNumber: annIdx + 1 } : iss;
              }),
              screenshotBase64,
            });
          } finally {
            if (page && !page.isClosed()) {
              await page.close().catch(() => {});
            }
          }
        }

        // 7) Réponse MCP : 1 JSON global + 1 image par viewport
        const responseContent = [];
        const summary = {
          pageId,
          pageUrl,
          pageTitle: pageMeta.title,
          viewports: perViewport.map(vr => {
            const { screenshotBase64: _drop, ...rest } = vr;
            return rest;
          }),
        };
        responseContent.push({ type: "text", text: JSON.stringify(summary, null, 2) });
        for (const vr of perViewport) {
          if (vr.screenshotBase64) {
            responseContent.push({ type: "image", data: vr.screenshotBase64, mimeType: "image/jpeg" });
          }
        }
        return { content: responseContent };
      }

      // ===== v3.6.0 — FEEDBACK SYSTEM =====
      case "report_missing_feature":
        result = await callWordPressAPI("/report-missing-feature", "POST", {
          title: args.title,
          bricksFeature: args.bricksFeature,
          bricksDocUrl: args.bricksDocUrl,
          whatItShouldDo: args.whatItShouldDo,
          whatITried: args.whatITried,
          proposedTool: args.proposedTool,
          bricksVersion: args.bricksVersion,
          context: args.context,
        });
        break;

      case "list_missing_features":
        result = await callWordPressAPI("/list-missing-features" + (args.status ? `?status=${encodeURIComponent(args.status)}` : ""), "GET");
        break;

      case "resolve_missing_feature":
        result = await callWordPressAPI("/resolve-missing-feature", "POST", {
          id: args.id,
          resolutionNote: args.resolutionNote,
        });
        break;

      case "upload_media_batch":
        result = await callWordPressAPI("/upload-media-batch", "POST", {
          items: args.items,
          optimize: args.optimize,
        });
        break;

      // ===== v3.7.0 — SKILL VERSIONING =====
      case "check_skill_version": {
        const qs = args.localVersion ? `?localVersion=${encodeURIComponent(args.localVersion)}` : "";
        result = await callWordPressAPI("/skill-version" + qs, "GET");
        break;
      }

      // ===== v3.9.0 — CUSTOM POST TYPES =====
      case "list_post_types":
        result = await callWordPressAPI("/list-post-types", "GET");
        break;

      case "create_post":
        result = await callWordPressAPI("/create-post", "POST", {
          postType: args.postType,
          title: args.title,
          content: args.content,
          excerpt: args.excerpt,
          slug: args.slug,
          status: args.status,
          featuredImageId: args.featuredImageId,
          meta: args.meta,
          taxonomies: args.taxonomies,
          date: args.date,
          author: args.author,
        });
        break;

      case "update_post":
        result = await callWordPressAPI("/update-post", "POST", {
          postId: args.postId,
          title: args.title,
          content: args.content,
          excerpt: args.excerpt,
          slug: args.slug,
          status: args.status,
          featuredImageId: args.featuredImageId,
          meta: args.meta,
          taxonomies: args.taxonomies,
          date: args.date,
        });
        break;

      case "delete_post":
        result = await callWordPressAPI("/delete-post", "POST", {
          postId: args.postId,
          force: args.force,
        });
        break;

      case "get_post":
        result = await callWordPressAPI("/get-post", "POST", {
          postId: args.postId,
        });
        break;

      case "list_posts":
        result = await callWordPressAPI("/list-posts", "POST", {
          postType: args.postType,
          perPage: args.perPage,
          page: args.page,
          search: args.search,
          status: args.status,
          taxonomyFilter: args.taxonomyFilter,
          metaQuery: args.metaQuery,
          orderBy: args.orderBy,
          order: args.order,
        });
        break;

      case "create_taxonomy_term":
        result = await callWordPressAPI("/create-taxonomy-term", "POST", {
          taxonomy: args.taxonomy,
          name: args.name,
          slug: args.slug,
          description: args.description,
          parentId: args.parentId,
        });
        break;

      // ===== v3.7.0 — UPLOAD FROM LOCAL FILESYSTEM =====
      case "upload_local_file": {
        try {
          const fileData = readLocalFileAsDataUri(args.localPath);
          logToFile(`[upload_local_file] ${args.localPath} (${fileData.size} bytes ${fileData.mime})`);
          // Le b64 est généré ici, dans le MCP server. Il part vers le plugin
          // mais ne transite JAMAIS par le contexte AI.
          result = await callWordPressAPI("/upload-media", "POST", {
            sourceUrl: fileData.dataUri,
            title: args.title || fileData.basename.replace(/\.[^.]+$/, ''),
            alt: args.alt,
            caption: args.caption,
            optimize: args.optimize !== false, // défaut true
          });
        } catch (err) {
          result = { success: false, error: err.message, localPath: args.localPath };
        }
        break;
      }

      case "upload_local_files_batch": {
        const batchItems = [];
        const readErrors = [];
        for (const item of (args.items || [])) {
          try {
            const fileData = readLocalFileAsDataUri(item.localPath);
            batchItems.push({
              sourceUrl: fileData.dataUri,
              title: item.title || fileData.basename.replace(/\.[^.]+$/, ''),
              alt: item.alt,
              caption: item.caption,
            });
          } catch (err) {
            readErrors.push({ localPath: item.localPath, error: err.message });
          }
        }
        if (batchItems.length === 0) {
          result = {
            success: false,
            error: "Aucun fichier lisible",
            readErrors,
          };
          break;
        }
        result = await callWordPressAPI("/upload-media-batch", "POST", {
          items: batchItems,
          optimize: args.optimize !== false, // défaut true
        });
        // Joindre les erreurs de lecture locales au résultat
        if (readErrors.length > 0) {
          result.localReadErrors = readErrors;
        }
        break;
      }

      default:
        console.error(`[LOG] Tool inconnu: ${name}`);
        result = { error: `Tool ${name} not found` };
    }

    console.error(`[LOG] Résultat:`, JSON.stringify(result, null, 2));
    return {
      content: [
        {
          type: "text",
          text: JSON.stringify(result, null, 2),
        },
      ],
    };
  } catch (error) {
    console.error(`[LOG] ERREUR:`, error.message);
    console.error(`[LOG] Stack:`, error.stack);
    return {
      content: [
        {
          type: "text",
          text: `Erreur: ${error.message}`,
        },
      ],
      isError: true,
    };
  }
});

// Démarrer le serveur
async function main() {
  const transport = new StdioServerTransport();
  await mcpServer.connect(transport);
  console.error("[READY] MCP Bricks Builder v3.0 démarré et connecté");
}

main().catch(console.error);