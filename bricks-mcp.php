<?php
/**
 * Plugin Name: Bricks Builder MCP Server
 * Plugin URI: https://github.com/Scott1012/bricks-builder-mcp
 * Description: Serveur MCP optimisé pour piloter Bricks Builder depuis Claude (Cowork/Desktop). Gère les pages, éléments, ordre des sections + génère le fichier .plugin Cowork prêt à uploader, avec skill bricks-builder embarqué (7000+ lignes de doc).
 * Version: 3.6.1
 * Author: Mathieu Maap
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BRICKS_MCP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BRICKS_MCP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BRICKS_MCP_VERSION', '3.6.1');

// URL du repo GitHub pour l'auto-update (Releases)
// Modifiable via l'option 'bricks_mcp_github_repo' dans WP admin
define('BRICKS_MCP_DEFAULT_GITHUB_REPO', 'https://github.com/Scott1012/bricks-builder-mcp/');

class BricksMCPServer {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** @var \YahnisElsts\PluginUpdateChecker\v5\Vcs\PluginUpdateChecker|null */
    private $update_checker = null;

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        // Handler pour la génération du fichier .plugin Cowork
        add_action('admin_post_bricks_download_plugin', [$this, 'handle_download_plugin']);
        // Handler pour le bouton "Vérifier les MAJ maintenant"
        add_action('admin_post_bricks_check_updates', [$this, 'handle_check_updates']);

        // Auto-update via GitHub Releases (plugin-update-checker)
        $this->setup_update_checker();
    }

    /**
     * Configure l'auto-update via GitHub Releases.
     * Le plugin vérifie automatiquement les nouvelles versions toutes les 12h
     * (modifiable via le bouton "Vérifier les MAJ maintenant").
     */
    private function setup_update_checker() {
        $puc_loader = BRICKS_MCP_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';
        if (!file_exists($puc_loader)) {
            return; // Pas de PUC installé
        }
        require_once $puc_loader;

        $repo_url = get_option('bricks_mcp_github_repo', BRICKS_MCP_DEFAULT_GITHUB_REPO);
        if (empty($repo_url)) {
            return;
        }

        try {
            $this->update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
                $repo_url,
                __FILE__,
                'bricks-mcp-server'
            );
            $this->update_checker->setBranch('main');
            // v3.3.3 — On NE PAS appeler enableReleaseAssets() : ça force PUC à télécharger
            // un asset zip qui doit être attaché à la release. Sans l'appel, PUC utilise
            // le zip auto-généré GitHub du tag (qui marche tout seul, pas besoin d'attacher
            // un fichier à la release manuellement).
        } catch (Exception $e) {
            // Silently fail si le repo est invalide
            $this->update_checker = null;
        }
    }

    /**
     * Force la vérification immédiate des mises à jour (bouton dans l'admin).
     */
    public function handle_check_updates() {
        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé.', 'Erreur', ['response' => 403]);
        }
        check_admin_referer('bricks_mcp_check_updates');

        $result = 'no_checker';
        if ($this->update_checker) {
            $update = $this->update_checker->checkForUpdates();
            if ($update !== null) {
                $result = 'update_available';
            } else {
                $result = 'up_to_date';
            }
        }

        $redirect = add_query_arg([
            'page'           => 'bricks-mcp-server',
            'updates_check'  => $result,
        ], admin_url('admin.php'));
        wp_safe_redirect($redirect);
        exit;
    }

    public function add_admin_menu() {
        add_menu_page(
            'Bricks MCP Server',
            'Bricks MCP',
            'manage_options',
            'bricks-mcp-server',
            [$this, 'render_admin_page'],
            'dashicons-code',
            90
        );
    }

    public function register_settings() {
        register_setting('bricks_mcp_settings', 'bricks_mcp_api_key');
        register_setting('bricks_mcp_settings', 'bricks_mcp_github_repo');
    }

    public function register_rest_routes() {
        $namespace = 'bricks-mcp/v2';
        
        // Routes existantes (compatibilité v1)
        register_rest_route($namespace, '/list-pages', [
            'methods' => 'GET',
            'callback' => [$this, 'api_list_pages'],
            'permission_callback' => [$this, 'check_api_key']
        ]);

        register_rest_route($namespace, '/get-page-json', [
            'methods' => 'POST',
            'callback' => [$this, 'api_get_page_json'],
            'permission_callback' => [$this, 'check_api_key']
        ]);

        register_rest_route($namespace, '/update-page-json', [
            'methods' => 'POST',
            'callback' => [$this, 'api_update_page_json'],
            'permission_callback' => [$this, 'check_api_key']
        ]);

        register_rest_route($namespace, '/analyze-json', [
            'methods' => 'POST',
            'callback' => [$this, 'api_analyze_json'],
            'permission_callback' => [$this, 'check_api_key']
        ]);

        // ROUTES OPTIMISÉES v2.0
        
        // 1. get_page_structure - Vue d'ensemble légère
        register_rest_route($namespace, '/get-page-structure', [
            'methods' => 'POST',
            'callback' => [$this, 'api_get_page_structure'],
            'permission_callback' => [$this, 'check_api_key']
        ]);

        // 2. find_elements - Recherche ciblée
        register_rest_route($namespace, '/find-elements', [
            'methods' => 'POST',
            'callback' => [$this, 'api_find_elements'],
            'permission_callback' => [$this, 'check_api_key']
        ]);

        // 3. get_element - Récupère UN élément
        register_rest_route($namespace, '/get-element', [
            'methods' => 'POST',
            'callback' => [$this, 'api_get_element'],
            'permission_callback' => [$this, 'check_api_key']
        ]);

        // 4. update_element - Modifie UN élément
        register_rest_route($namespace, '/update-element', [
            'methods' => 'POST',
            'callback' => [$this, 'api_update_element'],
            'permission_callback' => [$this, 'check_api_key']
        ]);

        // 5. add_element - Ajoute UN élément
        register_rest_route($namespace, '/add-element', [
            'methods' => 'POST',
            'callback' => [$this, 'api_add_element'],
            'permission_callback' => [$this, 'check_api_key']
        ]);

        // 6. batch_add - Ajoute PLUSIEURS éléments
        register_rest_route($namespace, '/batch-add', [
            'methods' => 'POST',
            'callback' => [$this, 'api_batch_add'],
            'permission_callback' => [$this, 'check_api_key']
        ]);

        // 7. delete_element - Supprime UN élément
        register_rest_route($namespace, '/delete-element', [
            'methods' => 'POST',
            'callback' => [$this, 'api_delete_element'],
            'permission_callback' => [$this, 'check_api_key']
        ]);

        // 🆕 8. reorder_sections - Réorganise l'ordre des sections (v3.0)
        register_rest_route($namespace, '/reorder-sections', [
            'methods' => 'POST',
            'callback' => [$this, 'api_reorder_sections'],
            'permission_callback' => [$this, 'check_api_key']
        ]);

        // 🆕 9. create_page - Crée une nouvelle page WordPress en mode Bricks (v3.3)
        register_rest_route($namespace, '/create-page', [
            'methods' => 'POST',
            'callback' => [$this, 'api_create_page'],
            'permission_callback' => [$this, 'check_api_key']
        ]);

        // 🆕 v3.4 — Gestion avancée des pages
        register_rest_route($namespace, '/delete-page', [
            'methods' => 'POST',
            'callback' => [$this, 'api_delete_page'],
            'permission_callback' => [$this, 'check_api_key']
        ]);
        register_rest_route($namespace, '/update-page-meta', [
            'methods' => 'POST',
            'callback' => [$this, 'api_update_page_meta'],
            'permission_callback' => [$this, 'check_api_key']
        ]);
        register_rest_route($namespace, '/duplicate-page', [
            'methods' => 'POST',
            'callback' => [$this, 'api_duplicate_page'],
            'permission_callback' => [$this, 'check_api_key']
        ]);
        register_rest_route($namespace, '/set-homepage', [
            'methods' => 'POST',
            'callback' => [$this, 'api_set_homepage'],
            'permission_callback' => [$this, 'check_api_key']
        ]);

        // 🆕 v3.5 — Health check, médias, menus, styles globaux
        register_rest_route($namespace, '/health', [
            'methods' => 'GET',
            'callback' => [$this, 'api_health'],
            'permission_callback' => [$this, 'check_api_key']
        ]);
        register_rest_route($namespace, '/list-all-pages', [
            'methods' => 'GET',
            'callback' => [$this, 'api_list_all_pages'],
            'permission_callback' => [$this, 'check_api_key']
        ]);
        register_rest_route($namespace, '/upload-media', [
            'methods' => 'POST',
            'callback' => [$this, 'api_upload_media'],
            'permission_callback' => [$this, 'check_api_key']
        ]);
        register_rest_route($namespace, '/list-media', [
            'methods' => 'POST',
            'callback' => [$this, 'api_list_media'],
            'permission_callback' => [$this, 'check_api_key']
        ]);
        register_rest_route($namespace, '/list-menus', [
            'methods' => 'GET',
            'callback' => [$this, 'api_list_menus'],
            'permission_callback' => [$this, 'check_api_key']
        ]);
        register_rest_route($namespace, '/add-menu-item', [
            'methods' => 'POST',
            'callback' => [$this, 'api_add_menu_item'],
            'permission_callback' => [$this, 'check_api_key']
        ]);
        register_rest_route($namespace, '/get-global-styles', [
            'methods' => 'GET',
            'callback' => [$this, 'api_get_global_styles'],
            'permission_callback' => [$this, 'check_api_key']
        ]);
        register_rest_route($namespace, '/update-global-styles', [
            'methods' => 'POST',
            'callback' => [$this, 'api_update_global_styles'],
            'permission_callback' => [$this, 'check_api_key']
        ]);
        register_rest_route($namespace, '/list-color-palette', [
            'methods' => 'GET',
            'callback' => [$this, 'api_list_color_palette'],
            'permission_callback' => [$this, 'check_api_key']
        ]);
        register_rest_route($namespace, '/add-color-to-palette', [
            'methods' => 'POST',
            'callback' => [$this, 'api_add_color_to_palette'],
            'permission_callback' => [$this, 'check_api_key']
        ]);

        // ============================================================
        // 🆕 v3.6 — INSPECTION + CUSTOM CODE + FONTS + CODE EXEC (Phase A)
        // ============================================================
        register_rest_route($namespace, '/list-bricks-options', ['methods' => 'GET', 'callback' => [$this, 'api_list_bricks_options'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/get-bricks-option', ['methods' => 'POST', 'callback' => [$this, 'api_get_bricks_option'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/get-custom-code', ['methods' => 'GET', 'callback' => [$this, 'api_get_custom_code'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/set-custom-code', ['methods' => 'POST', 'callback' => [$this, 'api_set_custom_code'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/get-code-execution-status', ['methods' => 'GET', 'callback' => [$this, 'api_get_code_execution_status'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/set-code-execution', ['methods' => 'POST', 'callback' => [$this, 'api_set_code_execution'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/list-custom-fonts', ['methods' => 'GET', 'callback' => [$this, 'api_list_custom_fonts'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/register-custom-font', ['methods' => 'POST', 'callback' => [$this, 'api_register_custom_font'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/delete-custom-font', ['methods' => 'POST', 'callback' => [$this, 'api_delete_custom_font'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/register-google-font-locally', ['methods' => 'POST', 'callback' => [$this, 'api_register_google_font_locally'], 'permission_callback' => [$this, 'check_api_key']]);

        // ============================================================
        // 🆕 v3.6 — GLOBAL CLASSES + THEME STYLES + PAGE CODE (Phase B)
        // ============================================================
        register_rest_route($namespace, '/list-global-classes', ['methods' => 'GET', 'callback' => [$this, 'api_list_global_classes'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/create-global-class', ['methods' => 'POST', 'callback' => [$this, 'api_create_global_class'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/update-global-class', ['methods' => 'POST', 'callback' => [$this, 'api_update_global_class'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/delete-global-class', ['methods' => 'POST', 'callback' => [$this, 'api_delete_global_class'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/list-theme-styles', ['methods' => 'GET', 'callback' => [$this, 'api_list_theme_styles'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/create-theme-style', ['methods' => 'POST', 'callback' => [$this, 'api_create_theme_style'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/update-theme-style', ['methods' => 'POST', 'callback' => [$this, 'api_update_theme_style'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/delete-theme-style', ['methods' => 'POST', 'callback' => [$this, 'api_delete_theme_style'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/get-page-custom-code', ['methods' => 'POST', 'callback' => [$this, 'api_get_page_custom_code'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/set-page-custom-code', ['methods' => 'POST', 'callback' => [$this, 'api_set_page_custom_code'], 'permission_callback' => [$this, 'check_api_key']]);

        // ============================================================
        // 🆕 v3.6 — STYLE MANAGER 2.2 + COMPONENTS (Phase C)
        // ============================================================
        register_rest_route($namespace, '/list-typography-scales', ['methods' => 'GET', 'callback' => [$this, 'api_list_typography_scales'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/set-typography-scale', ['methods' => 'POST', 'callback' => [$this, 'api_set_typography_scale'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/list-spacing-scales', ['methods' => 'GET', 'callback' => [$this, 'api_list_spacing_scales'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/set-spacing-scale', ['methods' => 'POST', 'callback' => [$this, 'api_set_spacing_scale'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/list-css-variables', ['methods' => 'GET', 'callback' => [$this, 'api_list_css_variables'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/set-css-variable', ['methods' => 'POST', 'callback' => [$this, 'api_set_css_variable'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/list-components', ['methods' => 'GET', 'callback' => [$this, 'api_list_components'], 'permission_callback' => [$this, 'check_api_key']]);
    }

    // Vérification de la clé API
    public function check_api_key($request) {
        $api_key = get_option('bricks_mcp_api_key');
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'Aucune clé API configurée', ['status' => 500]);
        }
        
        $header_key = isset($_SERVER['HTTP_X_API_KEY']) ? sanitize_text_field($_SERVER['HTTP_X_API_KEY']) : '';
        
        if ($header_key === $api_key) {
            return true;
        }

        return new WP_Error('invalid_api_key', 'Clé API invalide', ['status' => 401]);
    }

    // =====================================================
    // FONCTIONS EXISTANTES (compatibilité)
    // =====================================================

    public function api_list_pages($request) {
        $args = [
            'post_type'      => 'page',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            // v3.3.1 — Inclure aussi les drafts et private (pas seulement publish par défaut)
            'post_status'    => ['publish', 'draft', 'private', 'pending'],
            // v3.3.2 — meta_query avec EXISTS explicite pour matcher même les valeurs vides
            'meta_query'     => [
                [
                    'key'     => '_bricks_page_content_2',
                    'compare' => 'EXISTS',
                ],
            ],
        ];

        $pages = get_posts($args);
        $pages_data = [];

        foreach ($pages as $page) {
            $pages_data[] = [
                'id' => $page->ID,
                'title' => $page->post_title,
                'url' => get_permalink($page->ID),
                'status' => $page->post_status
            ];
        }

        return rest_ensure_response($pages_data);
    }

    public function api_get_page_json($request) {
        $page_id = $request->get_param('pageId');

        if (!$page_id) {
            return new WP_Error('missing_page_id', 'pageId requis', ['status' => 400]);
        }

        $json_data = get_post_meta($page_id, '_bricks_page_content_2', true);

        if (empty($json_data) || !is_array($json_data)) {
            return rest_ensure_response([]);
        }

        return rest_ensure_response($json_data);
    }

    public function api_update_page_json($request) {
        $page_id = $request->get_param('pageId');
        $new_json = $request->get_param('newJsonData');

        if (!$page_id || !$new_json) {
            return new WP_Error('missing_params', 'pageId et newJsonData requis', ['status' => 400]);
        }

        if (!is_array($new_json)) {
            return new WP_Error('invalid_format', 'newJsonData doit être un tableau', ['status' => 400]);
        }

        // Forcer la mise à jour (delete + add)
        delete_post_meta($page_id, '_bricks_page_content_2');
        add_post_meta($page_id, '_bricks_page_content_2', $new_json, true);

        // Vider le cache Bricks
        $this->clear_bricks_cache($page_id);

        return rest_ensure_response([
            'success' => true,
            'message' => 'Page mise à jour',
            'pageId' => $page_id,
            'url' => get_permalink($page_id),
            'updatedAt' => current_time('mysql'),
            'cacheCleared' => true
        ]);
    }

    public function api_analyze_json($request) {
        $json_data = $request->get_param('jsonData');

        if (!$json_data) {
            return new WP_Error('missing_data', 'jsonData requis', ['status' => 400]);
        }

        if (is_string($json_data)) {
            $json_data = json_decode($json_data, true);
        }

        $analysis = [
            'type' => gettype($json_data),
            'isArray' => is_array($json_data),
            'count' => is_array($json_data) ? count($json_data) : 0,
            'keys' => is_array($json_data) ? array_keys($json_data) : [],
            'sample' => is_array($json_data) && count($json_data) > 0 ? array_slice($json_data, 0, 2) : null
        ];

        return rest_ensure_response($analysis);
    }

    // =====================================================
    // 🆕 NOUVELLES FONCTIONS OPTIMISÉES v2.0
    // =====================================================

    /**
     * 1. get_page_structure - Vue d'ensemble LÉGÈRE
     * Économise ~80% de tokens vs get_page_json
     */
    public function api_get_page_structure($request) {
        $page_id = $request->get_param('pageId');

        if (!$page_id) {
            return new WP_Error('missing_page_id', 'pageId requis', ['status' => 400]);
        }

        $json_data = get_post_meta($page_id, '_bricks_page_content_2', true);

        if (empty($json_data) || !is_array($json_data)) {
            return rest_ensure_response([]);
        }

        // Créer une vue légère : seulement id, name, parent, children, aperçu texte
        $structure = [];
        foreach ($json_data as $element) {
            $item = [
                'id' => $element['id'] ?? '',
                'name' => $element['name'] ?? '',
                'parent' => $element['parent'] ?? 0,
                'children' => $element['children'] ?? []
            ];

            // Ajouter un aperçu du texte si présent (max 50 chars)
            if (isset($element['settings']['text'])) {
                $text = strip_tags($element['settings']['text']);
                $item['textPreview'] = mb_substr($text, 0, 50);
            }

            $structure[] = $item;
        }

        return rest_ensure_response($structure);
    }

    /**
     * 2. find_elements - Recherche CIBLÉE par critères
     * Très économe en tokens
     */
    public function api_find_elements($request) {
        $page_id = $request->get_param('pageId');
        $criteria = $request->get_param('criteria') ?? [];
        $limit = $request->get_param('limit') ?? 100;

        if (!$page_id) {
            return new WP_Error('missing_page_id', 'pageId requis', ['status' => 400]);
        }

        $json_data = get_post_meta($page_id, '_bricks_page_content_2', true);

        if (empty($json_data) || !is_array($json_data)) {
            return rest_ensure_response(['elements' => [], 'total' => 0]);
        }

        $results = [];
        $count = 0;

        foreach ($json_data as $element) {
            if ($count >= $limit) break;

            $match = true;

            // Filtre par type
            if (isset($criteria['type'])) {
                if (($element['name'] ?? '') !== $criteria['type']) {
                    $match = false;
                }
            }

            // Filtre par parent
            if (isset($criteria['parent']) && $match) {
                if (($element['parent'] ?? '') !== $criteria['parent']) {
                    $match = false;
                }
            }

            // Filtre par texte contenu
            if (isset($criteria['hasText']) && $match) {
                $element_text = $element['settings']['text'] ?? '';
                if (stripos($element_text, $criteria['hasText']) === false) {
                    $match = false;
                }
            }

            // Filtre par classe CSS
            if (isset($criteria['className']) && $match) {
                $classes = $element['settings']['_cssClasses'] ?? [];
                if (!in_array($criteria['className'], $classes)) {
                    $match = false;
                }
            }

            if ($match) {
                $results[] = $element;
                $count++;
            }
        }

        return rest_ensure_response([
            'elements' => $results,
            'total' => count($results),
            'criteria' => $criteria,
            'limit' => $limit
        ]);
    }

    /**
     * 3. get_element - Récupère UN SEUL élément en détail
     */
    public function api_get_element($request) {
        $page_id = $request->get_param('pageId');
        $element_id = $request->get_param('elementId');

        if (!$page_id || !$element_id) {
            return new WP_Error('missing_params', 'pageId et elementId requis', ['status' => 400]);
        }

        $json_data = get_post_meta($page_id, '_bricks_page_content_2', true);

        if (empty($json_data) || !is_array($json_data)) {
            return new WP_Error('page_not_found', 'Page non trouvée', ['status' => 404]);
        }

        foreach ($json_data as $element) {
            if (($element['id'] ?? '') === $element_id) {
                return rest_ensure_response($element);
            }
        }

        return new WP_Error('element_not_found', 'Élément non trouvé', ['status' => 404]);
    }

    /**
     * 4. update_element - Modifie UN SEUL élément
     * Fusion récursive profonde qui préserve toutes les propriétés
     */
    public function api_update_element($request) {
        $page_id = $request->get_param('pageId');
        $element_id = $request->get_param('elementId');
        $new_settings = $request->get_param('newSettings');

        if (!$page_id || !$element_id || !$new_settings) {
            return new WP_Error('missing_params', 'pageId, elementId et newSettings requis', ['status' => 400]);
        }

        $json_data = get_post_meta($page_id, '_bricks_page_content_2', true);

        if (empty($json_data) || !is_array($json_data)) {
            return new WP_Error('page_not_found', 'Page non trouvée', ['status' => 404]);
        }

        $element_found = false;

        foreach ($json_data as &$element) {
            if (($element['id'] ?? '') === $element_id) {
                // Fusionner les settings de manière récursive PROFONDE
                if (!isset($element['settings'])) {
                    $element['settings'] = [];
                }
                $element['settings'] = $this->array_merge_recursive_distinct($element['settings'], $new_settings);
                $element_found = true;
                break;
            }
        }
        unset($element);

        if (!$element_found) {
            return new WP_Error('element_not_found', 'Élément non trouvé', ['status' => 404]);
        }

        // Sauvegarder
        delete_post_meta($page_id, '_bricks_page_content_2');
        add_post_meta($page_id, '_bricks_page_content_2', $json_data, true);
        $this->clear_bricks_cache($page_id);

        return rest_ensure_response([
            'success' => true,
            'message' => 'Élément modifié',
            'elementId' => $element_id,
            'pageId' => $page_id,
            'url' => get_permalink($page_id)
        ]);
    }

    /**
     * 5. add_element - Ajoute UN SEUL élément
     */
    public function api_add_element($request) {
        $page_id = $request->get_param('pageId');
        $element = $request->get_param('element');
        $position = $request->get_param('position');

        if (!$page_id || !$element) {
            return new WP_Error('missing_params', 'pageId et element requis', ['status' => 400]);
        }

        $json_data = get_post_meta($page_id, '_bricks_page_content_2', true);

        if (!is_array($json_data)) {
            $json_data = [];
        }

        // Ajouter à la position spécifiée ou à la fin
        if ($position !== null && is_numeric($position)) {
            array_splice($json_data, $position, 0, [$element]);
        } else {
            $json_data[] = $element;
        }

        // Sauvegarder
        delete_post_meta($page_id, '_bricks_page_content_2');
        add_post_meta($page_id, '_bricks_page_content_2', $json_data, true);
        $this->clear_bricks_cache($page_id);

        return rest_ensure_response([
            'success' => true,
            'message' => 'Élément ajouté',
            'elementId' => $element['id'] ?? null,
            'pageId' => $page_id,
            'url' => get_permalink($page_id)
        ]);
    }

    /**
     * 6. batch_add - Ajoute PLUSIEURS éléments en une fois
     */
    public function api_batch_add($request) {
        $page_id = $request->get_param('pageId');
        $elements = $request->get_param('elements');

        if (!$page_id || !$elements || !is_array($elements)) {
            return new WP_Error('missing_params', 'pageId et elements (array) requis', ['status' => 400]);
        }

        $json_data = get_post_meta($page_id, '_bricks_page_content_2', true);

        if (!is_array($json_data)) {
            $json_data = [];
        }

        // Ajouter tous les éléments
        foreach ($elements as $element) {
            $json_data[] = $element;
        }

        // Sauvegarder
        delete_post_meta($page_id, '_bricks_page_content_2');
        add_post_meta($page_id, '_bricks_page_content_2', $json_data, true);
        $this->clear_bricks_cache($page_id);

        return rest_ensure_response([
            'success' => true,
            'message' => count($elements) . ' éléments ajoutés',
            'elementsAdded' => count($elements),
            'pageId' => $page_id,
            'url' => get_permalink($page_id)
        ]);
    }

    /**
     * 7. delete_element - Supprime UN élément
     */
    public function api_delete_element($request) {
        $page_id = $request->get_param('pageId');
        $element_id = $request->get_param('elementId');

        if (!$page_id || !$element_id) {
            return new WP_Error('missing_params', 'pageId et elementId requis', ['status' => 400]);
        }

        $json_data = get_post_meta($page_id, '_bricks_page_content_2', true);

        if (empty($json_data) || !is_array($json_data)) {
            return new WP_Error('page_not_found', 'Page non trouvée', ['status' => 404]);
        }

        $element_found = false;
        $new_json_data = [];

        // Retirer l'élément
        foreach ($json_data as $element) {
            if (($element['id'] ?? '') === $element_id) {
                $element_found = true;
                continue; // Skip cet élément
            }
            $new_json_data[] = $element;
        }

        if (!$element_found) {
            return new WP_Error('element_not_found', 'Élément non trouvé', ['status' => 404]);
        }

        // Nettoyer les références parent/enfant
        foreach ($new_json_data as &$element) {
            if (isset($element['children']) && is_array($element['children'])) {
                $element['children'] = array_filter($element['children'], function($child_id) use ($element_id) {
                    return $child_id !== $element_id;
                });
                $element['children'] = array_values($element['children']); // Réindexer
            }
        }
        unset($element);

        // Sauvegarder
        delete_post_meta($page_id, '_bricks_page_content_2');
        add_post_meta($page_id, '_bricks_page_content_2', $new_json_data, true);
        $this->clear_bricks_cache($page_id);

        return rest_ensure_response([
            'success' => true,
            'message' => 'Élément supprimé',
            'elementId' => $element_id,
            'pageId' => $page_id,
            'url' => get_permalink($page_id)
        ]);
    }

    // =====================================================
    // 🆕 NOUVELLE FONCTION v3.0 : REORDER_SECTIONS
    // =====================================================

    /**
     * 8. reorder_sections - Réorganise l'ordre des sections principales (parent: 0)
     * 
     * CRITIQUE : Dans Bricks Builder, l'ordre dans le tableau JSON détermine
     * l'ordre de rendu sur le frontend. Cette fonction permet de réorganiser
     * les sections principales pour placer le header en haut, etc.
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function api_reorder_sections($request) {
        $page_id = $request->get_param('pageId');
        $ordered_ids = $request->get_param('orderedIds');

        if (!$page_id || !is_array($ordered_ids)) {
            return new WP_Error('missing_params', 'pageId et orderedIds (array) requis', ['status' => 400]);
        }

        // Récupérer les données actuelles
        $current_data = get_post_meta($page_id, '_bricks_page_content_2', true);

        if (!is_array($current_data)) {
            return new WP_Error('page_not_found', 'Aucune donnée Bricks trouvée pour cette page', ['status' => 404]);
        }

        // Séparer les éléments parent:0 des autres
        $root_elements = [];  // Éléments avec parent: 0
        $child_elements = []; // Tous les autres éléments

        foreach ($current_data as $element) {
            if (isset($element['parent']) && $element['parent'] === 0) {
                $root_elements[$element['id']] = $element;
            } else {
                $child_elements[] = $element;
            }
        }

        // Créer le nouveau tableau dans l'ordre demandé
        $reordered_root = [];

        // D'abord, ajouter les éléments dans l'ordre spécifié
        foreach ($ordered_ids as $id) {
            if (isset($root_elements[$id])) {
                $reordered_root[] = $root_elements[$id];
                unset($root_elements[$id]); // Marquer comme traité
            }
        }

        // Ensuite, ajouter les éléments root non spécifiés (à la fin)
        foreach ($root_elements as $element) {
            $reordered_root[] = $element;
        }

        // Reconstruire le tableau final : root elements + child elements
        $new_data = array_merge($reordered_root, $child_elements);

        // Sauvegarder
        delete_post_meta($page_id, '_bricks_page_content_2');
        add_post_meta($page_id, '_bricks_page_content_2', $new_data, true);

        // Vider le cache
        $this->clear_bricks_cache($page_id);

        return rest_ensure_response([
            'success' => true,
            'message' => 'Ordre des sections réorganisé avec succès',
            'reordered_count' => count($reordered_root),
            'new_order' => array_column($reordered_root, 'id'),
            'pageId' => $page_id,
            'url' => get_permalink($page_id)
        ]);
    }

    // =====================================================
    // FONCTIONS UTILITAIRES
    // =====================================================

    /**
     * Vider le cache Bricks Builder
     */
    private function clear_bricks_cache($page_id) {
        delete_post_meta($page_id, '_bricks_page_css');
        delete_post_meta($page_id, '_bricks_inline_css');
        delete_option('bricks_css_cache');
        
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        do_action('bricks/save_post', $page_id);
    }

    /**
     * Fusion récursive de tableaux qui préserve TOUTES les propriétés
     * Contrairement à array_merge qui écrase les sous-tableaux
     */
    private function array_merge_recursive_distinct($array1, $array2) {
        $merged = $array1;

        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                // Fusionner récursivement les tableaux
                $merged[$key] = $this->array_merge_recursive_distinct($merged[$key], $value);
            } else {
                // Remplacer ou ajouter la valeur
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * 🆕 v3.3 — Crée une nouvelle page WordPress en mode Bricks Builder.
     * Endpoint POST /wp-json/bricks-mcp/v2/create-page
     *
     * Params attendus :
     *   - title (string, requis) : titre de la page
     *   - slug (string, optionnel) : slug URL
     *   - status (string, optionnel) : 'publish' | 'draft' | 'private' (défaut: publish)
     *   - setAsHomepage (bool, optionnel) : si true, configure comme page d'accueil
     *
     * Retourne : { id, title, url, status, isHomepage }
     */
    public function api_create_page($request) {
        $title = sanitize_text_field($request->get_param('title'));
        if (empty($title)) {
            return new WP_Error('missing_title', 'Le paramètre "title" est requis', ['status' => 400]);
        }

        $slug          = sanitize_title($request->get_param('slug'));
        $status        = $request->get_param('status') ?: 'publish';
        $allowed_status = ['publish', 'draft', 'private', 'pending'];
        if (!in_array($status, $allowed_status, true)) {
            $status = 'publish';
        }
        $set_as_homepage = (bool) $request->get_param('setAsHomepage');

        // Création de la page
        $post_args = [
            'post_title'   => $title,
            'post_content' => '',
            'post_status'  => $status,
            'post_type'    => 'page',
            'post_author'  => get_current_user_id() ?: 1,
        ];
        if (!empty($slug)) {
            $post_args['post_name'] = $slug;
        }

        $page_id = wp_insert_post($post_args, true);

        if (is_wp_error($page_id)) {
            return new WP_Error('create_failed', 'Erreur lors de la création de la page : ' . $page_id->get_error_message(), ['status' => 500]);
        }

        // Activer Bricks Builder sur cette page
        // v3.3.4 — Utiliser le même pattern que api_update_page_json pour éviter
        // les problèmes de update_post_meta qui ne crée pas la ligne dans certaines configs WP.
        delete_post_meta($page_id, '_bricks_editor_mode');
        add_post_meta($page_id, '_bricks_editor_mode', 'bricks', true);

        $initial_content = [
            [
                'id'       => substr(md5(uniqid()), 0, 6),
                'name'     => 'section',
                'parent'   => 0,
                'children' => [],
                'settings' => [],
            ],
        ];
        delete_post_meta($page_id, '_bricks_page_content_2');
        add_post_meta($page_id, '_bricks_page_content_2', $initial_content, true);

        // Si demandé, configurer comme page d'accueil
        $is_homepage = false;
        if ($set_as_homepage && $status === 'publish') {
            update_option('show_on_front', 'page');
            update_option('page_on_front', $page_id);
            $is_homepage = true;
        }

        return [
            'success'    => true,
            'id'         => $page_id,
            'title'      => $title,
            'url'        => get_permalink($page_id),
            'status'     => $status,
            'isHomepage' => $is_homepage,
            'message'    => "Page créée avec succès et activée en mode Bricks Builder",
        ];
    }

    /**
     * 🆕 v3.4 — Supprime une page WordPress.
     * Endpoint POST /wp-json/bricks-mcp/v2/delete-page
     *
     * Params : pageId (requis), force (bool, default false)
     *   - force=false : page mise à la corbeille (récupérable)
     *   - force=true  : suppression définitive (irréversible)
     */
    public function api_delete_page($request) {
        $page_id = (int) $request->get_param('pageId');
        if (empty($page_id)) {
            return new WP_Error('missing_pageId', 'Le paramètre "pageId" est requis', ['status' => 400]);
        }

        $page = get_post($page_id);
        if (!$page || $page->post_type !== 'page') {
            return new WP_Error('not_a_page', "Le post {$page_id} n'existe pas ou n'est pas une page", ['status' => 404]);
        }

        $force = (bool) $request->get_param('force');

        // Si la page est définie comme page d'accueil, on ne la supprime pas — sécurité
        $homepage_id = (int) get_option('page_on_front');
        if ($page_id === $homepage_id) {
            return new WP_Error('is_homepage', "Cette page est la page d'accueil. Change d'abord la page d'accueil avant de la supprimer.", ['status' => 409]);
        }

        $title = $page->post_title;
        $result = wp_delete_post($page_id, $force);

        if (!$result) {
            return new WP_Error('delete_failed', "Erreur lors de la suppression de la page {$page_id}", ['status' => 500]);
        }

        return [
            'success' => true,
            'id'      => $page_id,
            'title'   => $title,
            'mode'    => $force ? 'definitive' : 'corbeille',
            'message' => $force
                ? "Page supprimée définitivement"
                : "Page mise à la corbeille (récupérable depuis WP Admin → Pages → Corbeille)",
        ];
    }

    /**
     * 🆕 v3.4 — Met à jour les meta-données d'une page (titre, slug, statut, parent).
     * Endpoint POST /wp-json/bricks-mcp/v2/update-page-meta
     *
     * Params : pageId (requis), title, slug, status, parentId (tous optionnels — seuls
     * les champs fournis sont modifiés)
     */
    public function api_update_page_meta($request) {
        $page_id = (int) $request->get_param('pageId');
        if (empty($page_id)) {
            return new WP_Error('missing_pageId', 'Le paramètre "pageId" est requis', ['status' => 400]);
        }

        $page = get_post($page_id);
        if (!$page || $page->post_type !== 'page') {
            return new WP_Error('not_a_page', "Le post {$page_id} n'existe pas ou n'est pas une page", ['status' => 404]);
        }

        $update_args = ['ID' => $page_id];
        $changes = [];

        $title = $request->get_param('title');
        if ($title !== null && $title !== '') {
            $update_args['post_title'] = sanitize_text_field($title);
            $changes['title'] = $update_args['post_title'];
        }

        $slug = $request->get_param('slug');
        if ($slug !== null && $slug !== '') {
            $update_args['post_name'] = sanitize_title($slug);
            $changes['slug'] = $update_args['post_name'];
        }

        $status = $request->get_param('status');
        if ($status !== null && $status !== '') {
            $allowed = ['publish', 'draft', 'private', 'pending'];
            if (in_array($status, $allowed, true)) {
                $update_args['post_status'] = $status;
                $changes['status'] = $status;
            }
        }

        $parent_id = $request->get_param('parentId');
        if ($parent_id !== null) {
            $update_args['post_parent'] = (int) $parent_id;
            $changes['parentId'] = (int) $parent_id;
        }

        if (count($update_args) === 1) {
            return new WP_Error('no_changes', 'Aucun champ à modifier fourni (title, slug, status, parentId).', ['status' => 400]);
        }

        $result = wp_update_post($update_args, true);
        if (is_wp_error($result)) {
            return new WP_Error('update_failed', 'Erreur lors de la mise à jour : ' . $result->get_error_message(), ['status' => 500]);
        }

        return [
            'success' => true,
            'id'      => $page_id,
            'changes' => $changes,
            'url'     => get_permalink($page_id),
            'message' => 'Page mise à jour avec succès',
        ];
    }

    /**
     * 🆕 v3.4 — Duplique une page existante (titre, contenu Bricks, settings).
     * Endpoint POST /wp-json/bricks-mcp/v2/duplicate-page
     *
     * Params :
     *   - sourcePageId (requis) : ID de la page à dupliquer
     *   - newTitle (optionnel)  : titre de la copie (défaut: "Copie de {original}")
     *   - status (optionnel)    : 'publish' | 'draft' | 'private' (défaut: 'draft')
     */
    public function api_duplicate_page($request) {
        $source_id = (int) $request->get_param('sourcePageId');
        if (empty($source_id)) {
            return new WP_Error('missing_sourcePageId', 'Le paramètre "sourcePageId" est requis', ['status' => 400]);
        }

        $source = get_post($source_id);
        if (!$source || $source->post_type !== 'page') {
            return new WP_Error('not_a_page', "La page source {$source_id} n'existe pas", ['status' => 404]);
        }

        $new_title = $request->get_param('newTitle');
        if (empty($new_title)) {
            $new_title = 'Copie de ' . $source->post_title;
        } else {
            $new_title = sanitize_text_field($new_title);
        }

        $status = $request->get_param('status') ?: 'draft';
        $allowed_status = ['publish', 'draft', 'private', 'pending'];
        if (!in_array($status, $allowed_status, true)) {
            $status = 'draft';
        }

        // Créer la nouvelle page (sans le contenu, on copie via meta après)
        $new_id = wp_insert_post([
            'post_title'   => $new_title,
            'post_content' => $source->post_content,
            'post_status'  => $status,
            'post_type'    => 'page',
            'post_author'  => get_current_user_id() ?: $source->post_author,
        ], true);

        if (is_wp_error($new_id)) {
            return new WP_Error('duplicate_failed', 'Erreur lors de la duplication : ' . $new_id->get_error_message(), ['status' => 500]);
        }

        // Copier le contenu Bricks (le meta principal)
        $bricks_content = get_post_meta($source_id, '_bricks_page_content_2', true);
        delete_post_meta($new_id, '_bricks_editor_mode');
        add_post_meta($new_id, '_bricks_editor_mode', 'bricks', true);
        delete_post_meta($new_id, '_bricks_page_content_2');
        add_post_meta($new_id, '_bricks_page_content_2', $bricks_content ?: [], true);

        // Copier les autres meta Bricks éventuels (settings de page, header/footer override, etc.)
        $bricks_metas = ['_bricks_page_settings', '_bricks_page_header', '_bricks_page_footer'];
        foreach ($bricks_metas as $meta_key) {
            $value = get_post_meta($source_id, $meta_key, true);
            if (!empty($value)) {
                delete_post_meta($new_id, $meta_key);
                add_post_meta($new_id, $meta_key, $value, true);
            }
        }

        return [
            'success'      => true,
            'id'           => $new_id,
            'title'        => $new_title,
            'url'          => get_permalink($new_id),
            'status'       => $status,
            'sourcePageId' => $source_id,
            'message'      => "Page dupliquée depuis '{$source->post_title}' (avec son contenu Bricks)",
        ];
    }

    /**
     * 🆕 v3.4 — Définit une page comme page d'accueil du site.
     * Endpoint POST /wp-json/bricks-mcp/v2/set-homepage
     *
     * Params :
     *   - pageId (requis) : ID de la page à mettre comme accueil
     *   - reset (optionnel) : si true, remet l'accueil sur les derniers articles
     */
    public function api_set_homepage($request) {
        $reset = (bool) $request->get_param('reset');

        if ($reset) {
            update_option('show_on_front', 'posts');
            delete_option('page_on_front');
            return [
                'success' => true,
                'message' => "Page d'accueil réinitialisée sur les derniers articles",
            ];
        }

        $page_id = (int) $request->get_param('pageId');
        if (empty($page_id)) {
            return new WP_Error('missing_pageId', 'Le paramètre "pageId" est requis (ou reset=true pour reset)', ['status' => 400]);
        }

        $page = get_post($page_id);
        if (!$page || $page->post_type !== 'page') {
            return new WP_Error('not_a_page', "La page {$page_id} n'existe pas", ['status' => 404]);
        }

        if ($page->post_status !== 'publish') {
            return new WP_Error('not_published', "La page {$page_id} doit être publiée pour être page d'accueil", ['status' => 409]);
        }

        update_option('show_on_front', 'page');
        update_option('page_on_front', $page_id);

        return [
            'success' => true,
            'id'      => $page_id,
            'title'   => $page->post_title,
            'url'     => get_permalink($page_id),
            'message' => "'{$page->post_title}' est maintenant la page d'accueil du site",
        ];
    }

    // ============================================================
    // 🆕 v3.5 — HEALTH, MÉDIAS, MENUS, STYLES GLOBAUX
    // ============================================================

    /**
     * Endpoint GET /health — Test de connexion + infos système.
     * Utile pour debug : vérifier qu'on parle bien au bon site, version active, etc.
     */
    public function api_health($request) {
        global $wp_version;
        $bricks_active = false;
        $bricks_version = null;
        if (defined('BRICKS_VERSION')) {
            $bricks_active = true;
            $bricks_version = BRICKS_VERSION;
        } elseif (function_exists('is_plugin_active') && is_plugin_active('bricks/bricks.php')) {
            $bricks_active = true;
        }

        return [
            'success'        => true,
            'plugin_version' => BRICKS_MCP_VERSION,
            'wp_version'     => $wp_version,
            'php_version'    => PHP_VERSION,
            'bricks_active'  => $bricks_active,
            'bricks_version' => $bricks_version,
            'site_name'      => get_bloginfo('name'),
            'site_url'       => home_url(),
            'is_multisite'   => is_multisite(),
            'timestamp'      => current_time('mysql'),
        ];
    }

    /**
     * Endpoint GET /list-all-pages — Toutes les pages WP, pas seulement celles avec meta Bricks.
     * Utile pour voir l'inventaire complet avant de créer/dupliquer.
     */
    public function api_list_all_pages($request) {
        $pages = get_posts([
            'post_type'      => 'page',
            'posts_per_page' => -1,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
            'post_status'    => ['publish', 'draft', 'private', 'pending'],
        ]);

        $homepage_id = (int) get_option('page_on_front');
        $data = [];
        foreach ($pages as $page) {
            $has_bricks = !empty(get_post_meta($page->ID, '_bricks_page_content_2', true));
            $data[] = [
                'id'           => $page->ID,
                'title'        => $page->post_title,
                'slug'         => $page->post_name,
                'url'          => get_permalink($page->ID),
                'status'       => $page->post_status,
                'parent'       => $page->post_parent,
                'is_homepage'  => $page->ID === $homepage_id,
                'has_bricks'   => $has_bricks,
                'modified'     => $page->post_modified,
            ];
        }
        return $data;
    }

    /**
     * Endpoint POST /upload-media — Upload une image dans la médiathèque depuis URL.
     * Params : sourceUrl (requis), title, alt, caption (opt)
     */
    public function api_upload_media($request) {
        $source_url = esc_url_raw($request->get_param('sourceUrl'));
        if (empty($source_url)) {
            return new WP_Error('missing_url', 'sourceUrl est requis', ['status' => 400]);
        }

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Télécharger le fichier dans le tmp
        $tmp = download_url($source_url, 60);
        if (is_wp_error($tmp)) {
            return new WP_Error('download_failed', 'Impossible de télécharger : ' . $tmp->get_error_message(), ['status' => 500]);
        }

        // Détecter le nom de fichier
        // v3.6.1 — Si title fourni, l'utiliser comme base du nom (slugifié) pour avoir des fichiers parlants
        $title_for_name = $request->get_param('title');
        $url_filename = basename(parse_url($source_url, PHP_URL_PATH));
        // Détecter l'extension depuis l'URL (priorité) ou par défaut .jpg
        $ext = 'jpg';
        if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|avif)$/i', $url_filename, $m_ext)) {
            $ext = strtolower($m_ext[1]);
        } elseif (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|avif)([?#]|$)/i', $source_url, $m_ext)) {
            $ext = strtolower($m_ext[1]);
        }

        if (!empty($title_for_name)) {
            $filename = sanitize_title($title_for_name) . '.' . $ext;
        } elseif (!empty($url_filename) && preg_match('/\.(jpg|jpeg|png|gif|webp|svg|avif)$/i', $url_filename)) {
            $filename = $url_filename;
        } else {
            $filename = 'upload-' . time() . '.' . $ext;
        }

        $file_array = [
            'name'     => $filename,
            'tmp_name' => $tmp,
        ];

        $attachment_id = media_handle_sideload($file_array, 0);
        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            return new WP_Error('sideload_failed', 'Erreur upload : ' . $attachment_id->get_error_message(), ['status' => 500]);
        }

        // Méta optionnels
        $title   = $request->get_param('title');
        $alt     = $request->get_param('alt');
        $caption = $request->get_param('caption');
        if (!empty($title)) {
            wp_update_post(['ID' => $attachment_id, 'post_title' => sanitize_text_field($title)]);
        }
        if (!empty($alt)) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt));
        }
        if (!empty($caption)) {
            wp_update_post(['ID' => $attachment_id, 'post_excerpt' => sanitize_text_field($caption)]);
        }

        return [
            'success'   => true,
            'id'        => $attachment_id,
            'url'       => wp_get_attachment_url($attachment_id),
            'filename'  => $filename,
            'sourceUrl' => $source_url,
            'message'   => 'Image uploadée dans la médiathèque',
        ];
    }

    /**
     * Endpoint POST /list-media — Liste des médias paginée.
     * Params : page (default 1), perPage (default 20), search (opt)
     */
    public function api_list_media($request) {
        $page    = max(1, (int) $request->get_param('page') ?: 1);
        $perPage = max(1, min(100, (int) $request->get_param('perPage') ?: 20));
        $search  = $request->get_param('search');

        $args = [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => $perPage,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];
        if (!empty($search)) {
            $args['s'] = sanitize_text_field($search);
        }

        $query = new WP_Query($args);
        $items = [];
        foreach ($query->posts as $att) {
            $items[] = [
                'id'        => $att->ID,
                'title'     => $att->post_title,
                'filename'  => basename(get_attached_file($att->ID)),
                'url'       => wp_get_attachment_url($att->ID),
                'mime_type' => $att->post_mime_type,
                'alt'       => get_post_meta($att->ID, '_wp_attachment_image_alt', true),
                'date'      => $att->post_date,
            ];
        }

        return [
            'items'      => $items,
            'total'      => (int) $query->found_posts,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int) $query->max_num_pages,
        ];
    }

    /**
     * Endpoint GET /list-menus — Liste les menus de navigation.
     */
    public function api_list_menus($request) {
        $menus = wp_get_nav_menus();
        $data = [];
        foreach ($menus as $menu) {
            $items = wp_get_nav_menu_items($menu->term_id);
            $data[] = [
                'id'         => $menu->term_id,
                'name'       => $menu->name,
                'slug'       => $menu->slug,
                'item_count' => is_array($items) ? count($items) : 0,
                'locations'  => array_keys(array_filter(get_nav_menu_locations(), function ($id) use ($menu) {
                    return (int) $id === (int) $menu->term_id;
                })),
            ];
        }
        return $data;
    }

    /**
     * Endpoint POST /add-menu-item — Ajoute un item à un menu.
     * Params :
     *   - menuId (requis)
     *   - pageId (optionnel, pour lier à une page WP)
     *   - customUrl + label (optionnel, pour un lien custom)
     *   - parentItemId (optionnel)
     */
    public function api_add_menu_item($request) {
        $menu_id = (int) $request->get_param('menuId');
        if (empty($menu_id)) {
            return new WP_Error('missing_menuId', 'menuId est requis', ['status' => 400]);
        }
        $menu = wp_get_nav_menu_object($menu_id);
        if (!$menu) {
            return new WP_Error('menu_not_found', "Menu {$menu_id} introuvable", ['status' => 404]);
        }

        $page_id      = (int) $request->get_param('pageId');
        $custom_url   = $request->get_param('customUrl');
        $label        = $request->get_param('label');
        $parent_id    = (int) $request->get_param('parentItemId');

        $item_args = ['menu-item-status' => 'publish'];

        if ($page_id > 0) {
            $page = get_post($page_id);
            if (!$page || $page->post_type !== 'page') {
                return new WP_Error('not_a_page', "Page {$page_id} introuvable", ['status' => 404]);
            }
            $item_args['menu-item-object-id'] = $page_id;
            $item_args['menu-item-object']    = 'page';
            $item_args['menu-item-type']      = 'post_type';
            $item_args['menu-item-title']     = $label ?: $page->post_title;
        } elseif (!empty($custom_url)) {
            $item_args['menu-item-url']    = esc_url_raw($custom_url);
            $item_args['menu-item-title']  = $label ?: $custom_url;
            $item_args['menu-item-type']   = 'custom';
        } else {
            return new WP_Error('missing_target', 'Fournir pageId ou customUrl+label', ['status' => 400]);
        }

        if ($parent_id > 0) {
            $item_args['menu-item-parent-id'] = $parent_id;
        }

        $item_id = wp_update_nav_menu_item($menu_id, 0, $item_args);
        if (is_wp_error($item_id)) {
            return new WP_Error('add_failed', $item_id->get_error_message(), ['status' => 500]);
        }

        return [
            'success' => true,
            'item_id' => $item_id,
            'menu_id' => $menu_id,
            'message' => "Item ajouté au menu '{$menu->name}'",
        ];
    }

    /**
     * Endpoint GET /get-global-styles — Récupère les settings globaux Bricks.
     */
    public function api_get_global_styles($request) {
        return [
            'success'           => true,
            'global_settings'   => get_option('bricks_global_settings', []),
            'color_palette'     => get_option('bricks_color_palette', []),
            'global_classes'    => get_option('bricks_global_classes', []),
            'theme_styles'      => get_option('bricks_theme_styles', []),
        ];
    }

    /**
     * Endpoint POST /update-global-styles — Met à jour les settings globaux.
     * Params : settings (objet) — fusionné avec les settings existants.
     */
    public function api_update_global_styles($request) {
        $settings = $request->get_param('settings');
        if (!is_array($settings)) {
            return new WP_Error('invalid_settings', 'settings doit être un objet', ['status' => 400]);
        }

        $existing = get_option('bricks_global_settings', []);
        if (!is_array($existing)) {
            $existing = [];
        }
        $merged = $this->array_merge_recursive_distinct($existing, $settings);
        update_option('bricks_global_settings', $merged);

        return [
            'success'  => true,
            'updated'  => array_keys($settings),
            'message'  => 'Settings globaux Bricks mis à jour',
        ];
    }

    /**
     * Endpoint GET /list-color-palette — Récupère la palette de couleurs Bricks.
     */
    public function api_list_color_palette($request) {
        $palette = get_option('bricks_color_palette', []);
        if (!is_array($palette)) {
            $palette = [];
        }
        return [
            'success' => true,
            'palette' => $palette,
            'count'   => count($palette),
        ];
    }

    /**
     * Endpoint POST /add-color-to-palette — Ajoute une couleur à la palette.
     * Params : name (requis), hex (requis ex: '#ff6b35')
     */
    public function api_add_color_to_palette($request) {
        $name = sanitize_text_field($request->get_param('name'));
        $hex  = sanitize_text_field($request->get_param('hex'));
        if (empty($name) || empty($hex)) {
            return new WP_Error('missing_params', 'name et hex sont requis', ['status' => 400]);
        }
        if (!preg_match('/^#?[0-9a-fA-F]{3,8}$/', $hex)) {
            return new WP_Error('invalid_hex', 'hex doit être au format #ffffff', ['status' => 400]);
        }
        if (strpos($hex, '#') !== 0) {
            $hex = '#' . $hex;
        }

        $palette = get_option('bricks_color_palette', []);
        if (!is_array($palette)) {
            $palette = [];
        }

        $color_id = substr(md5(uniqid()), 0, 6);
        $palette[] = [
            'id'    => $color_id,
            'name'  => $name,
            'raw'   => $hex,
        ];
        update_option('bricks_color_palette', $palette);

        return [
            'success'       => true,
            'color_id'      => $color_id,
            'name'          => $name,
            'hex'           => $hex,
            'palette_count' => count($palette),
            'message'       => "Couleur '{$name}' ajoutée à la palette globale",
        ];
    }

    // ============================================================
    // 🆕 v3.6 — PHASE A : INSPECTION + CUSTOM CODE + FONTS + CODE EXEC
    // ============================================================

    /**
     * GET /list-bricks-options — Dump toutes les options WP commençant par "bricks_".
     * Outil debug essentiel pour cartographier ce qui existe en base.
     */
    public function api_list_bricks_options($request) {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'bricks\\_%' ORDER BY option_name", ARRAY_A);
        $out = [];
        foreach ($rows as $row) {
            $value = maybe_unserialize($row['option_value']);
            $out[] = [
                'name'   => $row['option_name'],
                'type'   => is_array($value) ? 'array' : (is_object($value) ? 'object' : gettype($value)),
                'size'   => is_string($value) ? strlen($value) : (is_array($value) ? count($value) : null),
                'preview' => is_array($value) || is_object($value)
                    ? array_slice(array_keys((array) $value), 0, 20)
                    : (is_string($value) && strlen($value) > 200 ? substr($value, 0, 200) . '...' : $value),
            ];
        }
        return ['success' => true, 'count' => count($out), 'options' => $out];
    }

    /**
     * POST /get-bricks-option — Récupère une option Bricks spécifique en intégralité.
     * Params : name (str, ex: 'bricks_global_settings')
     */
    public function api_get_bricks_option($request) {
        $name = sanitize_text_field($request->get_param('name'));
        if (empty($name)) {
            return new WP_Error('missing_name', 'name est requis', ['status' => 400]);
        }
        if (strpos($name, 'bricks_') !== 0) {
            return new WP_Error('invalid_name', 'Le nom doit commencer par "bricks_"', ['status' => 400]);
        }
        $value = get_option($name);
        return ['success' => true, 'name' => $name, 'value' => $value];
    }

    /**
     * GET /get-custom-code — Récupère le custom code global Bricks (header/body/footer).
     * Stocké dans bricks_global_settings sous les clés customCss/customScriptsHeader/customScriptsBodyHeader/customScriptsBodyFooter.
     */
    public function api_get_custom_code($request) {
        $settings = get_option('bricks_global_settings', []);
        if (!is_array($settings)) $settings = [];
        return [
            'success' => true,
            'customCss'                  => $settings['customCss'] ?? '',
            'customScriptsHeader'        => $settings['customScriptsHeader'] ?? '',
            'customScriptsBodyHeader'    => $settings['customScriptsBodyHeader'] ?? '',
            'customScriptsBodyFooter'    => $settings['customScriptsBodyFooter'] ?? '',
        ];
    }

    /**
     * POST /set-custom-code — Met à jour le custom code global Bricks.
     * Params (tous optionnels, seuls les champs fournis sont modifiés) :
     *   - customCss (CSS injecté dans <head>)
     *   - customScriptsHeader (HTML/scripts injectés dans <head>) — IDÉAL pour Google Fonts <link>
     *   - customScriptsBodyHeader (HTML injecté juste après <body>)
     *   - customScriptsBodyFooter (HTML injecté juste avant </body>)
     */
    public function api_set_custom_code($request) {
        $settings = get_option('bricks_global_settings', []);
        if (!is_array($settings)) $settings = [];

        $changed = [];
        foreach (['customCss', 'customScriptsHeader', 'customScriptsBodyHeader', 'customScriptsBodyFooter'] as $key) {
            $value = $request->get_param($key);
            if ($value !== null) {
                // On accepte aussi la chaîne vide pour vider un champ
                $settings[$key] = (string) $value;
                $changed[] = $key;
            }
        }

        if (empty($changed)) {
            return new WP_Error('no_changes', 'Aucun champ fourni (customCss, customScriptsHeader, customScriptsBodyHeader, customScriptsBodyFooter)', ['status' => 400]);
        }

        update_option('bricks_global_settings', $settings);
        return ['success' => true, 'updated' => $changed, 'message' => 'Custom code Bricks mis à jour'];
    }

    /**
     * GET /get-code-execution-status — État de l'exécution des code elements.
     * Bricks 1.9.7+ désactive code execution par défaut pour sécurité.
     * Stocké dans bricks_global_settings sous executeCodeAllowed (ou similaire) + capabilities WP par rôle.
     */
    public function api_get_code_execution_status($request) {
        $settings = get_option('bricks_global_settings', []);
        if (!is_array($settings)) $settings = [];

        // Tester plusieurs noms de clé possibles selon la version Bricks
        $enabled = false;
        $key_used = null;
        foreach (['executeCodeAllowed', 'codeExecutionEnabled', 'executeCode'] as $key) {
            if (isset($settings[$key])) {
                $enabled = (bool) $settings[$key];
                $key_used = $key;
                break;
            }
        }

        // Récupérer les rôles autorisés (capability bricks_execute_code)
        $allowed_roles = [];
        if (function_exists('wp_roles')) {
            $roles = wp_roles()->roles;
            foreach ($roles as $role_slug => $role_data) {
                if (!empty($role_data['capabilities']['bricks_execute_code'])) {
                    $allowed_roles[] = $role_slug;
                }
            }
        }

        return [
            'success'       => true,
            'enabled'       => $enabled,
            'setting_key'   => $key_used,
            'allowed_roles' => $allowed_roles,
            'note'          => 'Bricks 1.9.7+ désactive par défaut. Pour activer : Bricks → Settings → Custom Code → Enable code execution + cocher les rôles. La capability WP est "bricks_execute_code".',
        ];
    }

    /**
     * POST /set-code-execution — Active/désactive l'exécution des code elements + rôles autorisés.
     * Params :
     *   - enabled (bool, requis)
     *   - roles (array of role slugs, ex: ['administrator']) — rôles qui peuvent exécuter du code
     */
    public function api_set_code_execution($request) {
        $enabled = (bool) $request->get_param('enabled');
        $roles_param = $request->get_param('roles');
        if (!is_array($roles_param)) $roles_param = [];

        $settings = get_option('bricks_global_settings', []);
        if (!is_array($settings)) $settings = [];
        // On essaie d'écrire sur les 2 clés possibles selon la version pour être safe
        $settings['executeCodeAllowed'] = $enabled;
        $settings['codeExecutionEnabled'] = $enabled;
        update_option('bricks_global_settings', $settings);

        // Mettre à jour la capability bricks_execute_code par rôle
        if (function_exists('wp_roles')) {
            $all_roles = wp_roles()->roles;
            foreach (array_keys($all_roles) as $role_slug) {
                $role = get_role($role_slug);
                if (!$role) continue;
                if (in_array($role_slug, $roles_param, true) && $enabled) {
                    $role->add_cap('bricks_execute_code');
                } else {
                    $role->remove_cap('bricks_execute_code');
                }
            }
        }

        return [
            'success'       => true,
            'enabled'       => $enabled,
            'allowed_roles' => $roles_param,
            'message'       => $enabled
                ? "Code execution activé pour les rôles : " . implode(', ', $roles_param)
                : "Code execution désactivé",
            'security_note' => 'Bricks exige aussi des "code signatures" valides — chaque code element doit être édité+sauvé par un user autorisé pour fonctionner. Voir Bricks → Code Review.',
        ];
    }

    /**
     * GET /list-custom-fonts — Liste les custom fonts enregistrées dans Bricks Font Manager.
     * Bricks stocke les custom fonts dans le custom post type "bricks_fonts".
     */
    public function api_list_custom_fonts($request) {
        $fonts = get_posts([
            'post_type'      => 'bricks_fonts',
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'draft', 'private'],
        ]);
        $out = [];
        foreach ($fonts as $font) {
            $faces = get_post_meta($font->ID, 'bricks_font_face_rules', true);
            $out[] = [
                'id'         => $font->ID,
                'name'       => $font->post_title,
                'fontFamily' => get_post_meta($font->ID, 'font_family', true) ?: $font->post_title,
                'faces'      => $faces ?: [],
                'status'     => $font->post_status,
            ];
        }
        return ['success' => true, 'count' => count($out), 'fonts' => $out];
    }

    /**
     * POST /register-custom-font — Enregistre une custom font dans Bricks Font Manager.
     * Params :
     *   - name (str) : nom interne (ex: "Anton")
     *   - fontFamily (str) : font-family CSS (ex: "Anton")
     *   - faces (array) : liste de variantes [{ weight: 400, style: "normal", url: "https://.../anton-regular.woff2" }]
     */
    public function api_register_custom_font($request) {
        $name = sanitize_text_field($request->get_param('name'));
        $font_family = sanitize_text_field($request->get_param('fontFamily'));
        $faces = $request->get_param('faces');
        if (empty($name)) {
            return new WP_Error('missing_name', 'name est requis', ['status' => 400]);
        }
        if (empty($font_family)) {
            $font_family = $name;
        }
        if (!is_array($faces) || empty($faces)) {
            return new WP_Error('missing_faces', 'faces (array) est requis avec au moins une variante', ['status' => 400]);
        }

        $font_id = wp_insert_post([
            'post_type'   => 'bricks_fonts',
            'post_title'  => $name,
            'post_status' => 'publish',
        ], true);
        if (is_wp_error($font_id)) {
            return new WP_Error('insert_failed', $font_id->get_error_message(), ['status' => 500]);
        }

        update_post_meta($font_id, 'font_family', $font_family);
        update_post_meta($font_id, 'bricks_font_face_rules', $faces);

        return [
            'success'    => true,
            'id'         => $font_id,
            'name'       => $name,
            'fontFamily' => $font_family,
            'facesCount' => count($faces),
            'message'    => "Font '{$font_family}' enregistrée dans Bricks Font Manager",
        ];
    }

    /**
     * POST /delete-custom-font — Supprime une custom font.
     * Params : id (int)
     */
    public function api_delete_custom_font($request) {
        $id = (int) $request->get_param('id');
        if (empty($id)) {
            return new WP_Error('missing_id', 'id est requis', ['status' => 400]);
        }
        $post = get_post($id);
        if (!$post || $post->post_type !== 'bricks_fonts') {
            return new WP_Error('not_a_font', "L'id {$id} n'est pas une bricks_fonts", ['status' => 404]);
        }
        $name = $post->post_title;
        wp_delete_post($id, true);
        return ['success' => true, 'id' => $id, 'name' => $name, 'message' => "Custom font '{$name}' supprimée"];
    }

    /**
     * POST /register-google-font-locally — Télécharge un Google Font et l'enregistre dans Bricks Font Manager.
     * Params :
     *   - name (str) : nom de la font Google (ex: "Anton", "Inter")
     *   - weights (array) : poids souhaités, par défaut [400] (ex: [400, 700, 900])
     */
    public function api_register_google_font_locally($request) {
        $name = sanitize_text_field($request->get_param('name'));
        $weights = $request->get_param('weights');
        if (!is_array($weights) || empty($weights)) {
            $weights = [400];
        }
        if (empty($name)) {
            return new WP_Error('missing_name', 'name est requis (ex: "Anton")', ['status' => 400]);
        }

        // 1. Construire l'URL Google Fonts CSS
        $weight_str = implode(';', array_map('intval', $weights));
        $css_url = "https://fonts.googleapis.com/css2?family=" . rawurlencode($name) . ":wght@{$weight_str}&display=swap";

        // 2. Récupérer le CSS — UA Chrome complet IMPÉRATIF pour que Google serve des woff2
        // (sans Chrome/X.X.X.X dans le UA, Google sert du .ttf qui ne match pas notre regex)
        $response = wp_remote_get($css_url, [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ],
        ]);
        if (is_wp_error($response)) {
            return new WP_Error('fetch_failed', 'Impossible de récupérer le CSS Google : ' . $response->get_error_message(), ['status' => 502]);
        }
        $css = wp_remote_retrieve_body($response);
        if (empty($css)) {
            return new WP_Error('empty_css', 'CSS Google vide — la font existe-t-elle ?', ['status' => 404]);
        }

        // 3. Parser les @font-face pour extraire les URLs (priorité woff2 > woff > ttf)
        preg_match_all('/@font-face\s*\{[^}]+\}/s', $css, $blocks);
        $faces = [];
        $seen = []; // déduplication par weight+style (Google sert une @font-face par range Unicode)
        foreach ($blocks[0] as $block) {
            preg_match('/font-weight:\s*([0-9]+)/', $block, $m_w);
            preg_match('/font-style:\s*(\w+)/', $block, $m_s);
            // Capture .woff2 en priorité, sinon .woff, sinon .ttf
            $url = null;
            if (preg_match('/url\((https:\/\/[^)]+\.woff2)\)/', $block, $m_u)) {
                $url = $m_u[1];
            } elseif (preg_match('/url\((https:\/\/[^)]+\.woff)\)/', $block, $m_u)) {
                $url = $m_u[1];
            } elseif (preg_match('/url\((https:\/\/[^)]+\.ttf)\)/', $block, $m_u)) {
                $url = $m_u[1];
            }
            if (!$url) continue;

            $weight = $m_w[1] ?? '400';
            $style  = $m_s[1] ?? 'normal';
            $key = $weight . '|' . $style;
            // Garder uniquement la première URL par weight+style (la "latin" qu'on prend en premier)
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $faces[] = [
                    'weight' => $weight,
                    'style'  => $style,
                    'url'    => $url,
                ];
            }
        }

        if (empty($faces)) {
            return new WP_Error('no_faces', 'Aucun fichier de font (woff2/woff/ttf) trouvé dans le CSS Google. La font existe-t-elle bien ? Format CSS Google reçu : ' . substr($css, 0, 200), ['status' => 404]);
        }

        // 4. Créer la custom font dans Bricks
        $font_id = wp_insert_post([
            'post_type'   => 'bricks_fonts',
            'post_title'  => $name,
            'post_status' => 'publish',
        ], true);
        if (is_wp_error($font_id)) {
            return new WP_Error('insert_failed', $font_id->get_error_message(), ['status' => 500]);
        }
        update_post_meta($font_id, 'font_family', $name);
        update_post_meta($font_id, 'bricks_font_face_rules', $faces);

        return [
            'success'    => true,
            'id'         => $font_id,
            'name'       => $name,
            'facesCount' => count($faces),
            'faces'      => $faces,
            'message'    => "Google Font '{$name}' enregistrée localement (URLs woff2 hébergées par Google, pas de download local)",
            'note'       => 'Pour héberger localement les fichiers (mieux pour RGPD), il faut télécharger les .woff2 et les uploader. Les URLs ici pointent encore vers fonts.gstatic.com.',
        ];
    }

    // ============================================================
    // 🆕 v3.6 — PHASE B : GLOBAL CLASSES + THEME STYLES + PAGE CODE
    // ============================================================

    /**
     * GET /list-global-classes — Liste les classes CSS globales Bricks.
     */
    public function api_list_global_classes($request) {
        $classes = get_option('bricks_global_classes', []);
        if (!is_array($classes)) $classes = [];
        return ['success' => true, 'count' => count($classes), 'classes' => $classes];
    }

    /**
     * POST /create-global-class — Crée une classe CSS globale Bricks.
     * Params : name (str), settings (object) — settings Bricks à appliquer (ex: { _typography: ..., _padding: ... })
     */
    public function api_create_global_class($request) {
        $name = sanitize_text_field($request->get_param('name'));
        $settings = $request->get_param('settings');
        if (empty($name)) {
            return new WP_Error('missing_name', 'name est requis', ['status' => 400]);
        }
        if (!is_array($settings)) $settings = [];

        $classes = get_option('bricks_global_classes', []);
        if (!is_array($classes)) $classes = [];
        $id = substr(md5(uniqid()), 0, 6);
        $classes[] = [
            'id'       => $id,
            'name'     => $name,
            'settings' => $settings,
        ];
        update_option('bricks_global_classes', $classes);
        return ['success' => true, 'id' => $id, 'name' => $name, 'totalClasses' => count($classes), 'message' => "Classe '{$name}' créée"];
    }

    /**
     * POST /update-global-class — Modifie une classe globale par id.
     * Params : id (str), name (opt), settings (opt object)
     */
    public function api_update_global_class($request) {
        $id = sanitize_text_field($request->get_param('id'));
        if (empty($id)) {
            return new WP_Error('missing_id', 'id est requis', ['status' => 400]);
        }
        $classes = get_option('bricks_global_classes', []);
        if (!is_array($classes)) $classes = [];

        $found = false;
        foreach ($classes as &$cls) {
            if (isset($cls['id']) && $cls['id'] === $id) {
                $name = $request->get_param('name');
                $settings = $request->get_param('settings');
                if ($name !== null) $cls['name'] = sanitize_text_field($name);
                if (is_array($settings)) $cls['settings'] = $settings;
                $found = true;
                break;
            }
        }
        unset($cls);
        if (!$found) {
            return new WP_Error('not_found', "Classe id {$id} introuvable", ['status' => 404]);
        }
        update_option('bricks_global_classes', $classes);
        return ['success' => true, 'id' => $id, 'message' => 'Classe mise à jour'];
    }

    /**
     * POST /delete-global-class — Supprime une classe globale par id.
     */
    public function api_delete_global_class($request) {
        $id = sanitize_text_field($request->get_param('id'));
        if (empty($id)) {
            return new WP_Error('missing_id', 'id est requis', ['status' => 400]);
        }
        $classes = get_option('bricks_global_classes', []);
        if (!is_array($classes)) $classes = [];
        $before = count($classes);
        $classes = array_values(array_filter($classes, function ($c) use ($id) {
            return !(isset($c['id']) && $c['id'] === $id);
        }));
        if (count($classes) === $before) {
            return new WP_Error('not_found', "Classe id {$id} introuvable", ['status' => 404]);
        }
        update_option('bricks_global_classes', $classes);
        return ['success' => true, 'id' => $id, 'remaining' => count($classes), 'message' => 'Classe supprimée'];
    }

    /**
     * GET /list-theme-styles — Liste les theme styles Bricks.
     */
    public function api_list_theme_styles($request) {
        $styles = get_option('bricks_theme_styles', []);
        if (!is_array($styles)) $styles = [];
        return ['success' => true, 'count' => count($styles), 'themeStyles' => $styles];
    }

    /**
     * POST /create-theme-style — Crée un theme style Bricks.
     * Params : name (str), settings (object), conditions (opt array)
     */
    public function api_create_theme_style($request) {
        $name = sanitize_text_field($request->get_param('name'));
        $settings = $request->get_param('settings');
        $conditions = $request->get_param('conditions');
        if (empty($name)) {
            return new WP_Error('missing_name', 'name est requis', ['status' => 400]);
        }
        if (!is_array($settings)) $settings = [];

        $styles = get_option('bricks_theme_styles', []);
        if (!is_array($styles)) $styles = [];
        $id = substr(md5(uniqid()), 0, 8);
        $styles[$id] = [
            'id'         => $id,
            'name'       => $name,
            'settings'   => $settings,
            'conditions' => is_array($conditions) ? $conditions : [],
        ];
        update_option('bricks_theme_styles', $styles);
        return ['success' => true, 'id' => $id, 'name' => $name, 'totalStyles' => count($styles), 'message' => "Theme style '{$name}' créé"];
    }

    /**
     * POST /update-theme-style — Modifie un theme style.
     */
    public function api_update_theme_style($request) {
        $id = sanitize_text_field($request->get_param('id'));
        if (empty($id)) {
            return new WP_Error('missing_id', 'id est requis', ['status' => 400]);
        }
        $styles = get_option('bricks_theme_styles', []);
        if (!is_array($styles)) $styles = [];
        if (!isset($styles[$id])) {
            return new WP_Error('not_found', "Theme style id {$id} introuvable", ['status' => 404]);
        }
        $name = $request->get_param('name');
        $settings = $request->get_param('settings');
        $conditions = $request->get_param('conditions');
        if ($name !== null) $styles[$id]['name'] = sanitize_text_field($name);
        if (is_array($settings)) $styles[$id]['settings'] = $settings;
        if (is_array($conditions)) $styles[$id]['conditions'] = $conditions;
        update_option('bricks_theme_styles', $styles);
        return ['success' => true, 'id' => $id, 'message' => 'Theme style mis à jour'];
    }

    /**
     * POST /delete-theme-style — Supprime un theme style.
     */
    public function api_delete_theme_style($request) {
        $id = sanitize_text_field($request->get_param('id'));
        if (empty($id)) {
            return new WP_Error('missing_id', 'id est requis', ['status' => 400]);
        }
        $styles = get_option('bricks_theme_styles', []);
        if (!is_array($styles)) $styles = [];
        if (!isset($styles[$id])) {
            return new WP_Error('not_found', "Theme style id {$id} introuvable", ['status' => 404]);
        }
        unset($styles[$id]);
        update_option('bricks_theme_styles', $styles);
        return ['success' => true, 'id' => $id, 'remaining' => count($styles), 'message' => 'Theme style supprimé'];
    }

    /**
     * POST /get-page-custom-code — Récupère le custom code (CSS/JS) d'une page Bricks.
     * Stocké dans post meta '_bricks_page_settings' sous customCss/customScripts.
     */
    public function api_get_page_custom_code($request) {
        $page_id = (int) $request->get_param('pageId');
        if (empty($page_id)) {
            return new WP_Error('missing_pageId', 'pageId est requis', ['status' => 400]);
        }
        $page_settings = get_post_meta($page_id, '_bricks_page_settings', true);
        if (!is_array($page_settings)) $page_settings = [];
        return [
            'success'              => true,
            'pageId'               => $page_id,
            'customCss'            => $page_settings['customCss'] ?? '',
            'customScripts'        => $page_settings['customScripts'] ?? '',
        ];
    }

    /**
     * POST /set-page-custom-code — Définit du CSS/JS spécifique à une page.
     * Params : pageId (int), customCss (str opt), customScripts (str opt)
     */
    public function api_set_page_custom_code($request) {
        $page_id = (int) $request->get_param('pageId');
        if (empty($page_id)) {
            return new WP_Error('missing_pageId', 'pageId est requis', ['status' => 400]);
        }
        $page = get_post($page_id);
        if (!$page) {
            return new WP_Error('not_found', "Page {$page_id} introuvable", ['status' => 404]);
        }

        $page_settings = get_post_meta($page_id, '_bricks_page_settings', true);
        if (!is_array($page_settings)) $page_settings = [];

        $changed = [];
        $css = $request->get_param('customCss');
        $js  = $request->get_param('customScripts');
        if ($css !== null) { $page_settings['customCss'] = (string) $css; $changed[] = 'customCss'; }
        if ($js  !== null) { $page_settings['customScripts'] = (string) $js;  $changed[] = 'customScripts'; }

        if (empty($changed)) {
            return new WP_Error('no_changes', 'Aucun champ fourni (customCss, customScripts)', ['status' => 400]);
        }

        delete_post_meta($page_id, '_bricks_page_settings');
        add_post_meta($page_id, '_bricks_page_settings', $page_settings, true);

        return ['success' => true, 'pageId' => $page_id, 'updated' => $changed, 'message' => 'Custom code de la page mis à jour'];
    }

    // ============================================================
    // 🆕 v3.6 — PHASE C : STYLE MANAGER 2.2 + COMPONENTS
    // ============================================================

    /**
     * GET /list-typography-scales — Liste les typography scales (Bricks 2.2 Style Manager).
     */
    public function api_list_typography_scales($request) {
        $scales = get_option('bricks_typography_scales', []);
        if (!is_array($scales)) $scales = [];
        return ['success' => true, 'count' => count($scales), 'scales' => $scales];
    }

    /**
     * POST /set-typography-scale — Définit/met à jour une typography scale.
     * Params : id (str), name (str), values (object) — ex: { h1: "64px", h2: "48px", body: "16px" }
     */
    public function api_set_typography_scale($request) {
        $id = sanitize_text_field($request->get_param('id'));
        $name = sanitize_text_field($request->get_param('name'));
        $values = $request->get_param('values');
        if (empty($id)) $id = substr(md5(uniqid()), 0, 8);
        if (empty($name)) $name = 'Scale ' . $id;
        if (!is_array($values)) $values = [];

        $scales = get_option('bricks_typography_scales', []);
        if (!is_array($scales)) $scales = [];
        $scales[$id] = ['id' => $id, 'name' => $name, 'values' => $values];
        update_option('bricks_typography_scales', $scales);
        return ['success' => true, 'id' => $id, 'name' => $name, 'totalScales' => count($scales), 'message' => "Typography scale '{$name}' enregistrée"];
    }

    /**
     * GET /list-spacing-scales — Liste les spacing scales (Bricks 2.2).
     */
    public function api_list_spacing_scales($request) {
        $scales = get_option('bricks_spacing_scales', []);
        if (!is_array($scales)) $scales = [];
        return ['success' => true, 'count' => count($scales), 'scales' => $scales];
    }

    /**
     * POST /set-spacing-scale — Définit/met à jour une spacing scale.
     * Params : id (str), name (str), values (object) — ex: { xs: "8px", sm: "16px", md: "24px", lg: "48px" }
     */
    public function api_set_spacing_scale($request) {
        $id = sanitize_text_field($request->get_param('id'));
        $name = sanitize_text_field($request->get_param('name'));
        $values = $request->get_param('values');
        if (empty($id)) $id = substr(md5(uniqid()), 0, 8);
        if (empty($name)) $name = 'Scale ' . $id;
        if (!is_array($values)) $values = [];

        $scales = get_option('bricks_spacing_scales', []);
        if (!is_array($scales)) $scales = [];
        $scales[$id] = ['id' => $id, 'name' => $name, 'values' => $values];
        update_option('bricks_spacing_scales', $scales);
        return ['success' => true, 'id' => $id, 'name' => $name, 'totalScales' => count($scales), 'message' => "Spacing scale '{$name}' enregistrée"];
    }

    /**
     * GET /list-css-variables — Liste les CSS variables globales (Bricks 2.2).
     */
    public function api_list_css_variables($request) {
        $vars = get_option('bricks_css_variables', []);
        if (!is_array($vars)) $vars = [];
        return ['success' => true, 'count' => count($vars), 'variables' => $vars];
    }

    /**
     * POST /set-css-variable — Crée/modifie une CSS variable globale.
     * Params : name (str, ex: "--primary"), value (str, ex: "#FD5B2C")
     */
    public function api_set_css_variable($request) {
        $name = sanitize_text_field($request->get_param('name'));
        $value = sanitize_text_field($request->get_param('value'));
        if (empty($name)) {
            return new WP_Error('missing_name', 'name est requis (ex: "--primary")', ['status' => 400]);
        }
        if (strpos($name, '--') !== 0) {
            $name = '--' . $name;
        }

        $vars = get_option('bricks_css_variables', []);
        if (!is_array($vars)) $vars = [];

        $found = false;
        foreach ($vars as &$v) {
            if (isset($v['name']) && $v['name'] === $name) {
                $v['value'] = $value;
                $found = true;
                break;
            }
        }
        unset($v);
        if (!$found) {
            $vars[] = ['id' => substr(md5(uniqid()), 0, 6), 'name' => $name, 'value' => $value];
        }
        update_option('bricks_css_variables', $vars);
        return ['success' => true, 'name' => $name, 'value' => $value, 'totalVariables' => count($vars), 'message' => "CSS variable '{$name}' enregistrée"];
    }

    /**
     * GET /list-components — Liste les components Bricks (templates avec type=component).
     * Stockés dans le CPT bricks_template avec post meta _bricks_template_type='component'.
     */
    public function api_list_components($request) {
        $components = get_posts([
            'post_type'      => 'bricks_template',
            'posts_per_page' => -1,
            'meta_key'       => '_bricks_template_type',
            'meta_value'     => 'component',
            'post_status'    => ['publish', 'draft', 'private'],
        ]);
        $out = [];
        foreach ($components as $cmp) {
            $out[] = [
                'id'       => $cmp->ID,
                'name'     => $cmp->post_title,
                'slug'     => $cmp->post_name,
                'modified' => $cmp->post_modified,
                'status'   => $cmp->post_status,
            ];
        }
        return ['success' => true, 'count' => count($out), 'components' => $out];
    }

    /**
     * Page d'administration
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $api_key = get_option('bricks_mcp_api_key');

        if (isset($_POST['generate_api_key'])) {
            check_admin_referer('bricks_mcp_generate_key');
            $api_key = 'bricks_' . bin2hex(random_bytes(8));
            update_option('bricks_mcp_api_key', $api_key);
            echo '<div class="notice notice-success is-dismissible"><p>Nouvelle clé API générée avec succès !</p></div>';
        }

        // Gestion du formulaire URL GitHub
        if (isset($_POST['save_github_repo'])) {
            check_admin_referer('bricks_mcp_save_github_repo');
            $new_repo = isset($_POST['github_repo']) ? esc_url_raw(trim($_POST['github_repo'])) : '';
            update_option('bricks_mcp_github_repo', $new_repo);
            echo '<div class="notice notice-success is-dismissible"><p>URL du repo GitHub mise à jour. Reconfigure ton plugin (recharge la page) pour activer la nouvelle URL.</p></div>';
        }

        // Notification du résultat de "Vérifier les MAJ"
        if (isset($_GET['updates_check'])) {
            $check = sanitize_text_field($_GET['updates_check']);
            if ($check === 'update_available') {
                echo '<div class="notice notice-warning is-dismissible"><p><strong>Une mise à jour est disponible !</strong> Va dans <a href="' . esc_url(admin_url('update-core.php')) . '">Tableau de bord → Mises à jour</a> pour l\'installer.</p></div>';
            } elseif ($check === 'up_to_date') {
                echo '<div class="notice notice-success is-dismissible"><p>Le plugin est à jour ✓</p></div>';
            } elseif ($check === 'no_checker') {
                echo '<div class="notice notice-error is-dismissible"><p>Auto-update non configuré (URL GitHub manquante ou invalide).</p></div>';
            }
        }

        $site_url      = home_url();
        $site_name     = get_bloginfo('name');
        $is_configured = !empty($api_key);
        $github_repo   = get_option('bricks_mcp_github_repo', BRICKS_MCP_DEFAULT_GITHUB_REPO);
        ?>
        <div class="wrap bricks-mcp-admin">
            <h1>🔌 Bricks MCP — Connexion à Claude</h1>
            <p class="description" style="font-size:14px;margin-bottom:24px;">
                Cette page te permet de connecter ton site WordPress à <strong>Claude</strong> (Cowork ou Desktop) pour piloter Bricks Builder en langage naturel.
            </p>

            <!-- ÉTAPE 1 : Clé API -->
            <div style="background:#fff;padding:24px;margin:16px 0;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);border-left:4px solid <?php echo $is_configured ? '#00a32a' : '#dba617'; ?>;">
                <h2 style="margin-top:0;">Étape 1 — Clé API <?php echo $is_configured ? '<span style="color:#00a32a;font-weight:normal;font-size:14px;">✓ Configurée</span>' : '<span style="color:#dba617;font-weight:normal;font-size:14px;">⚠ À générer</span>'; ?></h2>
                <p>La clé API permet à Claude de s'authentifier auprès de ce site. <strong>Garde-la secrète</strong> — elle donne un accès complet aux pages Bricks.</p>

                <?php if ($is_configured): ?>
                    <div style="background:#f6f7f7;padding:14px;margin:12px 0;border-radius:4px;font-family:Menlo,Consolas,monospace;font-size:13px;word-break:break-all;">
                        <?php echo esc_html($api_key); ?>
                    </div>
                <?php else: ?>
                    <p style="color:#dba617;"><em>Aucune clé n'est encore générée. Clique sur le bouton ci-dessous.</em></p>
                <?php endif; ?>

                <form method="post" style="margin-top:12px;">
                    <?php wp_nonce_field('bricks_mcp_generate_key'); ?>
                    <button type="submit" name="generate_api_key" class="button <?php echo $is_configured ? '' : 'button-primary'; ?>">
                        <?php echo $is_configured ? 'Régénérer la clé' : 'Générer la clé API'; ?>
                    </button>
                    <?php if ($is_configured): ?>
                        <span class="description" style="margin-left:12px;">⚠ Régénérer invalidera l'ancienne clé. Tu devras retélécharger le plugin Claude.</span>
                    <?php endif; ?>
                </form>
            </div>

            <!-- ÉTAPE 2 : Télécharger pour Claude -->
            <div style="background:#fff;padding:24px;margin:16px 0;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);border-left:4px solid <?php echo $is_configured ? '#2271b1' : '#c3c4c7'; ?>;<?php echo $is_configured ? '' : 'opacity:.6;'; ?>">
                <h2 style="margin-top:0;">Étape 2 — Connecter à Claude</h2>
                <p>Télécharge le fichier <code>.plugin</code> ci-dessous, puis <strong>glisse-le dans Claude Cowork</strong> (menu Plugins → Browse plugins → Upload). Claude saura tout seul comment se connecter à ce site.</p>

                <?php if (!$is_configured): ?>
                    <p style="color:#dba617;font-weight:600;">⚠ Génère d'abord la clé API à l'étape 1.</p>
                <?php else: ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:16px;">
                        <input type="hidden" name="action" value="bricks_download_plugin">
                        <?php wp_nonce_field('bricks_mcp_download_plugin'); ?>

                        <table class="form-table" role="presentation" style="margin-bottom:16px;">
                            <tr>
                                <th scope="row" style="width:180px;"><label for="bricks_site_url">URL du site</label></th>
                                <td>
                                    <input type="url" id="bricks_site_url" name="site_url" value="<?php echo esc_attr($site_url); ?>" class="regular-text" required>
                                    <p class="description">URL publique utilisée par Claude pour appeler ce site (auto-détectée).</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="bricks_plugin_label">Nom du projet</label></th>
                                <td>
                                    <input type="text" id="bricks_plugin_label" name="plugin_label" value="<?php echo esc_attr($site_name); ?>" class="regular-text">
                                    <p class="description">Apparaîtra dans Claude pour identifier ce site (ex: "JT Carrelage").</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Pré-production (SSL)</th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="bricks_insecure_ssl" name="insecure_ssl" value="1">
                                        <strong>Ignorer les erreurs de certificat SSL</strong>
                                    </label>
                                    <p class="description">À cocher uniquement si ce site est sur une URL temporaire d'hébergeur (ex: <code>*.odns.fr</code>, <code>*.live-website.com</code>) avec un certificat SSL invalide. <strong>À ne JAMAIS utiliser en production.</strong></p>
                                </td>
                            </tr>
                        </table>

                        <button type="submit" class="button button-primary button-hero">
                            ⬇ Télécharger le plugin Claude
                        </button>
                    </form>

                    <div style="margin-top:24px;padding:16px;background:#f0f6fc;border-radius:4px;border-left:3px solid #2271b1;">
                        <strong>Une fois téléchargé :</strong>
                        <ol style="margin:8px 0 0 20px;">
                            <li>Ouvre <strong>Claude Cowork</strong> sur ton ordinateur</li>
                            <li>Clique sur l'icône <strong>Plugins</strong> (en haut)</li>
                            <li>Glisse le fichier <code>.plugin</code> dans la fenêtre, ou clique "Upload"</li>
                            <li>Valide l'installation. C'est terminé !</li>
                        </ol>
                    </div>

                    <div style="margin-top:16px;padding:16px;background:#f0fdf4;border-radius:4px;border-left:3px solid #00a32a;">
                        <strong>🧠 Skill bricks-builder embarqué :</strong> ce plugin Claude inclut <strong>7000+ lignes de doc Bricks</strong> (patterns, pitfalls, workflow, design web, SEO, structures JSON validées). Claude saura immédiatement comment convertir du HTML/CSS en Bricks, éviter les pièges connus et appliquer les bonnes pratiques.
                    </div>
                <?php endif; ?>
            </div>

            <!-- MISES À JOUR DU PLUGIN -->
            <div style="background:#fff;padding:24px;margin:16px 0;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);border-left:4px solid #6c757d;">
                <h2 style="margin-top:0;">🔄 Mises à jour du plugin</h2>
                <p>Version actuelle : <strong>v<?php echo esc_html(BRICKS_MCP_VERSION); ?></strong>. Le plugin vérifie automatiquement les nouvelles versions toutes les 12h sur GitHub.</p>

                <p>Repo GitHub utilisé : <code><?php echo esc_html($github_repo); ?></code></p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px;">
                    <input type="hidden" name="action" value="bricks_check_updates">
                    <?php wp_nonce_field('bricks_mcp_check_updates'); ?>
                    <button type="submit" class="button button-primary">🔍 Vérifier les MAJ maintenant</button>
                </form>

                <details style="margin-top:16px;">
                    <summary style="cursor:pointer;font-weight:600;">⚙ Modifier l'URL du repo GitHub</summary>
                    <form method="post" style="margin-top:12px;">
                        <?php wp_nonce_field('bricks_mcp_save_github_repo'); ?>
                        <input type="url" name="github_repo" value="<?php echo esc_attr($github_repo); ?>" class="regular-text" style="width:100%;max-width:600px;">
                        <p class="description">URL du repo GitHub où sont publiées les Releases (ex: <code>https://github.com/Scott1012/bricks-builder-mcp/</code>).</p>
                        <button type="submit" name="save_github_repo" class="button">Enregistrer l'URL</button>
                    </form>
                </details>
            </div>

            <!-- INFOS TECHNIQUES (replié) -->
            <details style="background:#fff;padding:16px 24px;margin:16px 0;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);">
                <summary style="cursor:pointer;font-weight:600;font-size:15px;">🛠 Détails techniques (pour les curieux)</summary>
                <div style="margin-top:16px;">
                    <p><strong>Endpoint REST :</strong> <code><?php echo esc_url(rest_url('bricks-mcp/v2/')); ?></code></p>
                    <p><strong>Authentification :</strong> header <code>X-API-Key</code></p>
                    <p><strong>Outils MCP exposés :</strong></p>
                    <ul style="list-style:disc;margin-left:30px;columns:2;">
                        <li><code>list-pages</code></li>
                        <li><code>get-page-json</code></li>
                        <li><code>update-page-json</code></li>
                        <li><code>get-page-structure</code></li>
                        <li><code>find-elements</code></li>
                        <li><code>get-element</code></li>
                        <li><code>update-element</code></li>
                        <li><code>add-element</code></li>
                        <li><code>batch-add</code></li>
                        <li><code>delete-element</code></li>
                        <li><code>reorder-sections</code></li>
                        <li><code>analyze-json</code></li>
                    </ul>
                    <p><strong>Package npm utilisé côté Claude :</strong> <code>bricks-builder-mcp</code></p>
                </div>
            </details>
        </div>
        <?php
    }

    /**
     * Génère le fichier .plugin (zip Cowork) et le streame en téléchargement.
     * Le zip contient :
     *   .claude-plugin/plugin.json   — manifeste Cowork
     *   .mcp.json                    — déclaration du serveur MCP avec env vars
     *   README.md                    — doc minimale
     */
    public function handle_download_plugin() {
        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé.', 'Erreur', ['response' => 403]);
        }
        check_admin_referer('bricks_mcp_download_plugin');

        $api_key = get_option('bricks_mcp_api_key');
        if (empty($api_key)) {
            wp_die('Clé API manquante. Génère-la d\'abord à l\'étape 1.', 'Erreur', ['response' => 400]);
        }

        $site_url = isset($_POST['site_url']) ? esc_url_raw(trim($_POST['site_url'])) : home_url();
        $site_url = untrailingslashit($site_url);
        $label    = isset($_POST['plugin_label']) ? sanitize_text_field($_POST['plugin_label']) : get_bloginfo('name');
        $insecure_ssl = !empty($_POST['insecure_ssl']);

        if (empty($label)) {
            $label = 'Mon site Bricks';
        }

        // Slug kebab-case pour le nom du plugin
        $slug = sanitize_title($label);
        if (empty($slug)) {
            $slug = sanitize_title(parse_url($site_url, PHP_URL_HOST));
        }
        $plugin_name = 'bricks-' . $slug;

        // 1. plugin.json
        $plugin_json = wp_json_encode([
            'name'        => $plugin_name,
            'version'     => '1.0.0',
            'description' => sprintf('Connecte Claude au site %s pour piloter Bricks Builder.', $label),
            'author'      => [
                'name' => $label,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // 2. .mcp.json — déclaration du serveur MCP avec env vars
        $env_vars = [
            'WORDPRESS_URL' => $site_url,
            'API_KEY'       => $api_key,
        ];
        if ($insecure_ssl) {
            $env_vars['INSECURE_SSL'] = 'true';
        }

        $mcp_json = wp_json_encode([
            'mcpServers' => [
                'bricks-mcp' => [
                    'command' => 'npx',
                    'args'    => ['-y', 'bricks-builder-mcp'],
                    'env'     => $env_vars,
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // 3. README.md
        $readme = "# {$plugin_name}\n\n"
                . "Plugin Cowork généré automatiquement pour le site **{$label}**.\n\n"
                . "Permet à Claude de piloter Bricks Builder sur ce site via le package npm `bricks-builder-mcp`.\n\n"
                . "**Site cible :** {$site_url}\n\n"
                . "**Généré le :** " . date('Y-m-d H:i') . "\n\n"
                . "Pour installer : glisse ce fichier dans Claude Cowork (Plugins → Upload).\n";

        // Construction du zip
        if (!class_exists('ZipArchive')) {
            wp_die('Le module ZipArchive de PHP n\'est pas disponible sur ce serveur. Contacte ton hébergeur.', 'Erreur', ['response' => 500]);
        }

        $tmp_file = tempnam(sys_get_temp_dir(), 'bricks-plugin-');
        $zip = new ZipArchive();
        if ($zip->open($tmp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            wp_die('Impossible de créer l\'archive zip.', 'Erreur', ['response' => 500]);
        }
        $zip->addFromString('.claude-plugin/plugin.json', $plugin_json);
        $zip->addFromString('.mcp.json', $mcp_json);
        $zip->addFromString('README.md', $readme);

        // 4. Embarquer le skill bricks-builder (SKILL.md + references/)
        // Permet à Claude d'avoir directement la connaissance HTML/CSS → Bricks à l'install
        $skill_dir = BRICKS_MCP_PLUGIN_DIR . 'skill';
        if (is_dir($skill_dir)) {
            $skill_dir = rtrim($skill_dir, '/\\');
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($skill_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $rel_path = substr($file->getPathname(), strlen($skill_dir) + 1);
                    // Chemin Unix dans le zip (normalisation des séparateurs Windows)
                    $rel_path = str_replace('\\', '/', $rel_path);
                    $zip_path = 'skills/bricks-builder/' . $rel_path;
                    $zip->addFile($file->getPathname(), $zip_path);
                }
            }
        }

        $zip->close();

        // Stream du fichier en download
        if (ob_get_length()) {
            ob_end_clean();
        }
        $filename = $plugin_name . '.plugin';
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmp_file));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($tmp_file);
        @unlink($tmp_file);
        exit;
    }
}

// Initialiser le plugin
function bricks_mcp_server_init() {
    return BricksMCPServer::get_instance();
}
add_action('plugins_loaded', 'bricks_mcp_server_init');