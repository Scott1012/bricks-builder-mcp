<?php
/**
 * Plugin Name: Bricks Builder MCP Server
 * Plugin URI: https://github.com/Scott1012/bricks-builder-mcp
 * Description: Serveur MCP optimisé pour piloter Bricks Builder depuis Claude et Codex. Gère les pages, éléments, ordre des sections + génère le fichier .plugin Cowork et l'installeur Codex, avec skill bricks-builder embarqué.
 * Version: 4.3.7
 * Author: Mathieu Maap
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BRICKS_MCP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BRICKS_MCP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BRICKS_MCP_VERSION', '4.3.7');

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
        // Handler public pour le script d'installation Codex
        add_action('admin_post_bricks_stream_codex_installer', [$this, 'handle_stream_codex_installer']);
        add_action('admin_post_nopriv_bricks_stream_codex_installer', [$this, 'handle_stream_codex_installer']);
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
        register_rest_route($namespace, '/get-element-schema', [
            'methods' => 'POST',
            'callback' => [$this, 'api_get_element_schema'],
            'permission_callback' => [$this, 'check_api_key']
        ]);
        register_rest_route($namespace, '/get-filter-schema', [
            'methods' => 'GET',
            'callback' => [$this, 'api_get_filter_schema'],
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

        // ===== v3.7.0 — Verify element + Feedback system + Batch upload =====
        register_rest_route($namespace, '/verify-element-info', ['methods' => 'POST', 'callback' => [$this, 'api_verify_element_info'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/report-missing-feature', ['methods' => 'POST', 'callback' => [$this, 'api_report_missing_feature'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/list-missing-features', ['methods' => 'GET', 'callback' => [$this, 'api_list_missing_features'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/resolve-missing-feature', ['methods' => 'POST', 'callback' => [$this, 'api_resolve_missing_feature'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/upload-media-batch', ['methods' => 'POST', 'callback' => [$this, 'api_upload_media_batch'], 'permission_callback' => [$this, 'check_api_key']]);

        // ===== v3.9.0 — Skill versioning =====
        register_rest_route($namespace, '/skill-version', ['methods' => 'GET', 'callback' => [$this, 'api_skill_version'], 'permission_callback' => [$this, 'check_api_key']]);

        // ===== v4.0.0 — Custom Post Types =====
        register_rest_route($namespace, '/list-post-types', ['methods' => 'GET', 'callback' => [$this, 'api_list_post_types'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/create-post', ['methods' => 'POST', 'callback' => [$this, 'api_create_post'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/update-post', ['methods' => 'POST', 'callback' => [$this, 'api_update_post'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/delete-post', ['methods' => 'POST', 'callback' => [$this, 'api_delete_post'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/get-post', ['methods' => 'POST', 'callback' => [$this, 'api_get_post'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/list-posts', ['methods' => 'POST', 'callback' => [$this, 'api_list_posts'], 'permission_callback' => [$this, 'check_api_key']]);
        register_rest_route($namespace, '/create-taxonomy-term', ['methods' => 'POST', 'callback' => [$this, 'api_create_taxonomy_term'], 'permission_callback' => [$this, 'check_api_key']]);
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
        // v3.6.2 — paramètre optionnel `label` pour renommer l'élément dans la structure Bricks
        $new_label = $request->get_param('label');

        if (!$page_id || !$element_id) {
            return new WP_Error('missing_params', 'pageId et elementId requis', ['status' => 400]);
        }
        // newSettings peut être null si on veut juste mettre à jour le label
        if (empty($new_settings) && $new_label === null) {
            return new WP_Error('missing_params', 'Fournir newSettings ou label', ['status' => 400]);
        }

        $json_data = get_post_meta($page_id, '_bricks_page_content_2', true);

        if (empty($json_data) || !is_array($json_data)) {
            return new WP_Error('page_not_found', 'Page non trouvée', ['status' => 404]);
        }

        $element_found = false;
        $code_signature_warning = null;

        foreach ($json_data as &$element) {
            if (($element['id'] ?? '') === $element_id) {
                $will_execute_code = is_array($new_settings) && array_key_exists('executeCode', $new_settings)
                    ? (bool) $new_settings['executeCode']
                    : !empty($element['settings']['executeCode']);
                if (
                    ($element['name'] ?? '') === 'code'
                    && is_array($new_settings)
                    && $this->settings_contain_any_key($new_settings, ['code', 'cssCode', 'javascriptCode'])
                    && $will_execute_code
                ) {
                    $code_signature_warning = 'Attention : le contenu d’un élément Bricks "code" exécutable a été modifié via API. Bricks peut ne pas l’exécuter tant que le Code element n’est pas re-signé manuellement dans le builder / Code Review.';
                }
                // Fusionner les settings de manière récursive PROFONDE
                if (!empty($new_settings)) {
                    if (!isset($element['settings'])) {
                        $element['settings'] = [];
                    }
                    $element['settings'] = $this->array_merge_recursive_distinct($element['settings'], $new_settings);
                }
                // v3.6.2 — Mise à jour du label (au niveau racine, hors settings)
                if ($new_label !== null) {
                    $element['label'] = sanitize_text_field($new_label);
                }
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
            'warning' => $code_signature_warning,
            'requiresManualCodeSignature' => $code_signature_warning !== null,
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

        $synced_parents = $this->sync_parent_child_links_for_elements($json_data, [$element['id'] ?? null]);

        // Sauvegarder
        delete_post_meta($page_id, '_bricks_page_content_2');
        add_post_meta($page_id, '_bricks_page_content_2', $json_data, true);
        $this->clear_bricks_cache($page_id);

        return rest_ensure_response([
            'success' => true,
            'message' => 'Élément ajouté',
            'elementId' => $element['id'] ?? null,
            'parentsSynced' => $synced_parents,
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
        $added_ids = [];
        foreach ($elements as $element) {
            $json_data[] = $element;
            if (!empty($element['id'])) {
                $added_ids[] = $element['id'];
            }
        }

        $synced_parents = $this->sync_parent_child_links_for_elements($json_data, $added_ids);

        // Sauvegarder
        delete_post_meta($page_id, '_bricks_page_content_2');
        add_post_meta($page_id, '_bricks_page_content_2', $json_data, true);
        $this->clear_bricks_cache($page_id);

        return rest_ensure_response([
            'success' => true,
            'message' => count($elements) . ' éléments ajoutés',
            'elementsAdded' => count($elements),
            'parentsSynced' => $synced_parents,
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

    private function settings_contain_any_key($settings, $keys) {
        if (!is_array($settings)) {
            return false;
        }

        foreach ($settings as $key => $value) {
            if (in_array((string) $key, $keys, true)) {
                return true;
            }
            if (is_array($value) && $this->settings_contain_any_key($value, $keys)) {
                return true;
            }
        }

        return false;
    }

    private function sync_parent_child_links_for_elements(&$json_data, $target_ids) {
        if (!is_array($json_data) || empty($target_ids)) {
            return [];
        }

        $target_ids = array_values(array_unique(array_filter(array_map('strval', $target_ids))));
        if (empty($target_ids)) {
            return [];
        }

        $index_by_id = [];
        foreach ($json_data as $index => $element) {
            if (!empty($element['id'])) {
                $index_by_id[(string) $element['id']] = $index;
            }
        }

        $target_ids = array_values(array_filter($target_ids, function($id) use ($index_by_id) {
            return isset($index_by_id[$id]);
        }));
        if (empty($target_ids)) {
            return [];
        }

        // Un enfant ne doit être référencé que par son parent effectif.
        foreach ($json_data as &$element) {
            if (!empty($element['children']) && is_array($element['children'])) {
                $children = array_filter($element['children'], function($child_id) use ($target_ids) {
                    return !in_array((string) $child_id, $target_ids, true);
                });
                $element['children'] = array_values(array_unique(array_map('strval', $children)));
            }
        }
        unset($element);

        $synced_parent_ids = [];
        foreach ($target_ids as $child_id) {
            $child_index = $index_by_id[$child_id];
            $parent_id = $json_data[$child_index]['parent'] ?? null;

            if ($parent_id === null || $parent_id === '' || (string) $parent_id === '0') {
                continue;
            }

            $parent_key = (string) $parent_id;
            if (!isset($index_by_id[$parent_key])) {
                continue;
            }

            $parent_index = $index_by_id[$parent_key];
            if (empty($json_data[$parent_index]['children']) || !is_array($json_data[$parent_index]['children'])) {
                $json_data[$parent_index]['children'] = [];
            }

            $json_data[$parent_index]['children'][] = $child_id;
            $json_data[$parent_index]['children'] = array_values(array_unique(array_map('strval', $json_data[$parent_index]['children'])));
            $synced_parent_ids[] = $parent_key;
        }

        return array_values(array_unique($synced_parent_ids));
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
     * Endpoint POST /get-element-schema — Lit le registre runtime Bricks.
     *
     * Sans `element`, retourne le catalogue compact des éléments disponibles.
     * Avec `element`, retourne les contrôles Bricks de cet élément.
     */
    public function api_get_element_schema($request) {
        if (!class_exists('\Bricks\Elements')) {
            return new WP_Error(
                'bricks_not_active',
                'Bricks Builder doit être actif pour lire les schemas des éléments.',
                ['status' => 503]
            );
        }

        $element = sanitize_key((string) $request->get_param('element'));
        $catalog_only = $this->parse_bool_param($request->get_param('catalogOnly'), empty($element));
        $include_inherited = $this->parse_bool_param($request->get_param('includeInherited'), true);
        $include_raw = $this->parse_bool_param($request->get_param('raw'), false);

        $registry = $this->get_bricks_elements_registry();
        if (empty($registry)) {
            return new WP_Error(
                'empty_element_registry',
                'Impossible de lire le registre Bricks\\Elements::$elements.',
                ['status' => 500]
            );
        }

        $schemas = [];
        foreach ($registry as $element_name => $entry) {
            $element_name = (string) $element_name;
            $object = $this->get_bricks_element_object($element_name, $entry);
            if (!$object) {
                continue;
            }

            $schema = $this->build_bricks_element_schema($element_name, $object, $include_raw);
            if ($schema) {
                $schemas[$element_name] = $schema;
            }
        }

        ksort($schemas);

        if ($catalog_only || empty($element)) {
            $catalog = [];
            foreach ($schemas as $name => $schema) {
                $catalog[] = [
                    'name' => $name,
                    'label' => $schema['label'],
                    'category' => $schema['category'],
                    'controlCount' => $schema['controlCount'],
                    'nestable' => $schema['nestable'],
                    'source' => 'runtime',
                ];
            }

            foreach ($this->get_official_element_schema_fallback_catalog() as $name => $fallback) {
                if (isset($schemas[$name])) {
                    continue;
                }

                $catalog[] = [
                    'name' => $name,
                    'label' => $fallback['label'],
                    'category' => $fallback['category'],
                    'controlCount' => null,
                    'nestable' => $fallback['nestable'],
                    'source' => 'official_schema_fallback',
                ];
            }

            usort($catalog, function ($a, $b) {
                return strcmp($a['name'], $b['name']);
            });

            return [
                'success' => true,
                'mode' => 'catalog',
                'source' => 'Bricks runtime registry + official schema fallback catalog',
                'bricks_version' => defined('BRICKS_VERSION') ? BRICKS_VERSION : null,
                'total_elements' => count($catalog),
                'catalog' => $catalog,
                'usage' => [
                    'schema_for_one_element' => ['element' => 'button'],
                    'with_common_style_controls' => ['element' => 'button', 'includeInherited' => true],
                ],
            ];
        }

        $source = 'Bricks runtime registry';
        $schema = $schemas[$element] ?? null;

        if (!$schema) {
            $schema = $this->get_official_bricks_element_schema($element);
            $source = 'Bricks Academy official schema fallback';
        }

        if (!$schema) {
            return new WP_Error(
                'element_schema_not_found',
                sprintf('Element "%s" introuvable dans le registre Bricks et dans le fallback schema officiel.', $element),
                [
                    'status' => 404,
                    'element' => $element,
                    'suggestions' => $this->find_similar_strings($element, array_keys($schemas)),
                    'fallbackCatalog' => array_keys($this->get_official_element_schema_fallback_catalog()),
                ]
            );
        }

        if ($include_inherited) {
            $schema['inheritedControls'] = $this->get_inherited_bricks_style_controls();
        }

        return [
            'success' => true,
            'mode' => 'element',
            'source' => $source,
            'bricks_version' => defined('BRICKS_VERSION') ? BRICKS_VERSION : null,
            'schema' => $schema,
            'notes' => [
                'controls_source' => $source === 'Bricks runtime registry'
                    ? 'Contrôles renvoyés par get_controls() sur la classe runtime Bricks.'
                    : 'Schema JSON officiel Bricks Academy utilisé parce que le registre runtime local ne liste pas cet élément.',
                'format_warning' => 'Le type de contrôle Bricks indique la clé et l’intention. Pour les formats sensibles, valider avec verify_element après écriture.',
                'query_filter_warning' => strpos($element, 'filter-') === 0
                    ? 'Les filtres Query doivent être activés dans Bricks > Settings > Query filters et reliés à une Target Query réelle. Si le schema officiel ne montre que les styles, créer un exemple en UI puis inspecter avec get_element avant automatisation.'
                    : null,
            ],
        ];
    }

    public function api_get_filter_schema($request) {
        if (!class_exists('\Bricks\Elements')) {
            return new WP_Error(
                'bricks_not_active',
                'Bricks Builder doit être actif pour lire le schema des filtres Query.',
                ['status' => 503]
            );
        }

        $filters_enabled = null;
        $detection_source = 'unavailable';

        if (class_exists('\Bricks\Helpers') && method_exists('\Bricks\Helpers', 'enabled_query_filters')) {
            try {
                $filters_enabled = (bool) \Bricks\Helpers::enabled_query_filters();
                $detection_source = 'Bricks\\Helpers::enabled_query_filters';
            } catch (Throwable $e) {
                $filters_enabled = null;
                $detection_source = 'Bricks\\Helpers::enabled_query_filters (error)';
            }
        }

        return [
            'success' => true,
            'bricks_version' => defined('BRICKS_VERSION') ? BRICKS_VERSION : null,
            'filters_enabled' => $filters_enabled,
            'detection_source' => $detection_source,
            'enable_filters_hint' => 'Activer Bricks > Settings > Performance > "Enable query sort / filter / live search". Sans cela, les filtres peuvent rendre vide ou ne rien piloter côté frontend.',
            'schema_type' => 'guide_schema',
            'notes' => [
                'Cet outil donne les clés métier utiles pour brancher les filtres Query. Ce n’est pas un schema runtime exhaustif généré par Bricks.',
                'Les sous-options listées ici reprennent la structure documentée et testée par d’autres intégrations Bricks MCP. Elles servent de référence rapide tant qu’un vrai filtre UI n’a pas encore été inspecté sur le site.',
                'Pour une version Bricks future ou un cas limite, créer un filtre minimal dans l’UI puis inspecter avec get_element reste la méthode de vérification finale.',
                'filterQueryId doit cibler l’ID Bricks de l’élément loop (6 caractères), pas un post ID WordPress.',
            ],
            'filter_elements' => [
                'filter-checkbox' => [
                    'label' => 'Filter - Checkbox',
                    'supports_source' => ['taxonomy', 'wpField', 'customField'],
                    'required' => ['filterQueryId', 'filterSource'],
                    'bricks_2_3' => [
                        'show_more_less' => [
                            'description' => 'Load more / show less pour les longues listes.',
                            'limitOptions' => 'Nombre d’options visibles avant le bouton "show more".',
                            'showMoreText' => 'Texte du bouton "show more". Supporte %number% pour le nombre d’éléments cachés.',
                            'showLessText' => 'Texte du bouton "show less".',
                            'styling_note' => 'Styles du bouton : showMoreButtonSize, showMoreButtonStyle, showMoreButtonOutline, showMoreButtonTypography, showMoreButtonBackground, showMoreButtonBorder.',
                        ],
                        'countAlignEnd' => 'Aligne le compteur en fin de ligne quand displayMode=default.',
                    ],
                ],
                'filter-radio' => [
                    'label' => 'Filter - Radio',
                    'supports_source' => ['taxonomy', 'wpField', 'customField'],
                    'supports_action' => ['filter', 'sort', 'per_page'],
                    'required' => ['filterQueryId', 'filterSource'],
                    'bricks_2_3' => [
                        'show_more_less' => [
                            'description' => 'Load more / show less pour les longues listes.',
                            'limitOptions' => 'Nombre d’options visibles avant le bouton "show more".',
                            'showMoreText' => 'Texte du bouton "show more". Supporte %number% pour le nombre d’éléments cachés.',
                            'showLessText' => 'Texte du bouton "show less".',
                            'styling_note' => 'Styles du bouton : showMoreButtonSize, showMoreButtonStyle, showMoreButtonOutline, showMoreButtonTypography, showMoreButtonBackground, showMoreButtonBorder.',
                        ],
                        'countAlignEnd' => 'Aligne le compteur en fin de ligne quand displayMode=default.',
                    ],
                ],
                'filter-select' => [
                    'label' => 'Filter - Select',
                    'supports_source' => ['taxonomy', 'wpField', 'customField'],
                    'supports_action' => ['filter', 'sort', 'per_page'],
                    'required' => ['filterQueryId', 'filterSource'],
                    'bricks_2_3' => [
                        'choices_js' => [
                            'description' => 'Select enrichi via Choices.js. Ajoute recherche, multi-sélection et styles avancés.',
                            'choicesJs' => 'Active le mode Choices.js.',
                            'choicesPosition' => 'Position du dropdown : auto | bottom | top.',
                            'search' => [
                                'choicesSearch' => 'Active la recherche dans le dropdown.',
                                'choicesSearchPlaceholder' => 'Placeholder du champ de recherche.',
                                'choicesNoResultsText' => 'Texte affiché si aucun résultat.',
                                'choicesNoChoicesText' => 'Texte affiché si aucune option disponible.',
                                'styling_note' => 'Styles : choicesSearchBackground, choicesSearchTypography, choicesSearchInputTypography, choicesSearchInputPadding.',
                            ],
                            'multiple' => [
                                'enableMultiple' => 'Active la multi-sélection.',
                                'filterMultiLogic' => 'Logique de combinaison des valeurs multiples.',
                                'styling_note' => 'Styles des pills : choicesPillGap, choicesPillBackground, choicesPillBorder, choicesPillTypography.',
                            ],
                            'styling_note' => 'Styles généraux : choicesPadding, choicesBackgroundColor, choicesBorderBase, choicesBorderColor, choicesBorderRadius, choicesFontSize, choicesTextColor, choicesArrowColor, choicesItemPadding, choicesDropdownBackground, choicesHighlightBackground, choicesHighlightTextColor, choicesDisabledBackground, choicesDisabledTextColor.',
                        ],
                    ],
                ],
                'filter-search' => [
                    'label' => 'Filter - Search (Live Search)',
                    'supports_source' => [],
                    'required' => ['filterQueryId'],
                ],
                'filter-range' => [
                    'label' => 'Filter - Range',
                    'supports_source' => ['taxonomy', 'wpField', 'customField'],
                    'required' => ['filterQueryId', 'filterSource'],
                    'bricks_2_3' => [
                        'decimalPlaces' => 'Nombre de décimales affichées.',
                        'inputUseCustomStepper' => 'Affiche des boutons +/- quand displayMode=input.',
                        'stepper_styling_note' => 'Styles du stepper : inputStepperGap, inputStepperMarginStart, inputStepperBackground, inputStepperBorder, inputStepperTypography.',
                    ],
                ],
                'filter-datepicker' => [
                    'label' => 'Filter - Datepicker',
                    'supports_source' => ['wpField', 'customField'],
                    'required' => ['filterQueryId', 'filterSource'],
                    'bricks_2_3' => [
                        'dateFormat' => 'Format d’affichage Flatpickr, ex: d/m/Y ou M j, Y.',
                    ],
                ],
                'filter-submit' => [
                    'label' => 'Filter - Submit button',
                    'supports_source' => [],
                    'required' => ['filterQueryId'],
                    'note' => 'Utile quand filterApplyOn=click sur les autres filtres.',
                ],
                'filter-active-filters' => [
                    'label' => 'Filter - Active Filters',
                    'supports_source' => [],
                    'required' => ['filterQueryId'],
                ],
            ],
            'common_settings' => [
                'filterQueryId' => 'ID Bricks de la Query Loop cible (container/posts avec hasLoop=true).',
                'filterSource' => 'taxonomy | wpField | customField',
                'filterAction' => 'filter | sort | per_page',
                'filterApplyOn' => 'change | click',
                'filterNiceName' => 'Nom de paramètre URL optionnel pour une URL propre.',
                'filterTaxonomy' => 'Slug de taxonomie quand filterSource=taxonomy.',
                'wpPostField' => 'Champ WP quand filterSource=wpField : post_id, post_date, post_author, post_type, post_status, post_modified.',
            ],
            'filterQueryId_note' => 'filterQueryId doit être l’ID Bricks 6 caractères de l’élément loop, pas un post ID WordPress.',
            'workflow_example' => [
                '1. create_or_find_query_loop' => 'Créer ou récupérer un élément Query Loop avec hasLoop=true.',
                '2. note_query_element_id' => 'Conserver l’ID Bricks de cet élément, ex: abc123.',
                '3. add_filter_element' => 'Ajouter filter-checkbox/filter-radio/filter-select sur la même page.',
                '4. bind_filter_to_query' => 'Renseigner filterQueryId="abc123", puis filterSource et éventuellement filterTaxonomy.',
                '5. enable_query_filters' => 'Activer Query filters dans Bricks > Settings > Performance si ce n’est pas déjà fait.',
                '6. verify_frontend' => 'Valider sur le frontend avec plusieurs posts/termes réels.',
            ],
        ];
    }

    private function get_official_element_schema_fallback_catalog() {
        return [
            'filter-active-filters' => ['label' => 'Filter - Active Filters', 'category' => 'filter', 'nestable' => false],
            'filter-checkbox' => ['label' => 'Filter - Checkbox', 'category' => 'filter', 'nestable' => false],
            'filter-datepicker' => ['label' => 'Filter - Datepicker', 'category' => 'filter', 'nestable' => false],
            'filter-radio' => ['label' => 'Filter - Radio', 'category' => 'filter', 'nestable' => false],
            'filter-range' => ['label' => 'Filter - Range', 'category' => 'filter', 'nestable' => false],
            'filter-search' => ['label' => 'Filter - Search', 'category' => 'filter', 'nestable' => false],
            'filter-select' => ['label' => 'Filter - Select', 'category' => 'filter', 'nestable' => false],
            'filter-submit' => ['label' => 'Filter - Submit / Reset', 'category' => 'filter', 'nestable' => false],
        ];
    }

    private function get_official_bricks_element_schema($element_name) {
        $element_name = sanitize_key((string) $element_name);
        if ($element_name === '') {
            return null;
        }

        $cache_key = 'bricks_mcp_schema_' . md5($element_name);
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $url = sprintf(
            'https://academy.bricksbuilder.io/schema-resolved/elements/%s.json',
            rawurlencode($element_name)
        );

        $response = wp_remote_get($url, [
            'timeout' => 8,
            'redirection' => 3,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data) || empty($data['metadata']) || !is_array($data['metadata'])) {
            return null;
        }

        $schema = $this->convert_official_element_schema($element_name, $data, $url);
        if ($schema) {
            set_transient($cache_key, $schema, DAY_IN_SECONDS);
        }

        return $schema;
    }

    private function convert_official_element_schema($fallback_name, $data, $url) {
        $metadata = isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [];
        $settings = isset($data['settings']) && is_array($data['settings']) ? $data['settings'] : [];
        $name = isset($metadata['name']) ? sanitize_key((string) $metadata['name']) : $fallback_name;

        if ($name === '') {
            return null;
        }

        $compact_controls = [];
        $settings_properties = [];

        foreach ($settings as $key => $setting) {
            if (!is_array($setting)) {
                continue;
            }

            $key = (string) $key;
            $compact_controls[] = $this->compact_official_schema_setting($key, $setting);
            $settings_properties[$key] = $this->official_setting_to_json_schema($setting, $key);
        }

        return [
            'name' => $name,
            'label' => isset($data['title']) ? wp_strip_all_tags((string) $data['title']) : (isset($metadata['label']) ? wp_strip_all_tags((string) $metadata['label']) : $name),
            'category' => isset($metadata['category']) ? (string) $metadata['category'] : 'unknown',
            'nestable' => !empty($metadata['nestable']),
            'controlCount' => count($compact_controls),
            'controls' => $compact_controls,
            'settingsSchema' => [
                'type' => 'object',
                'additionalProperties' => true,
                'properties' => empty($settings_properties) ? new stdClass() : $settings_properties,
            ],
            'minimalElement' => [
                'id' => 'example_id',
                'name' => $name,
                'parent' => 'parent_id',
                'children' => [],
                'settings' => new stdClass(),
            ],
            'officialSchemaUrl' => $url,
            'officialSchemaVersion' => isset($data['schemaVersion']) ? (string) $data['schemaVersion'] : null,
        ];
    }

    private function compact_official_schema_setting($key, $setting) {
        $out = [
            'key' => $key,
            'type' => isset($setting['controlType']) ? (string) $setting['controlType'] : 'mixed',
            'label' => isset($setting['label']) ? wp_strip_all_tags((string) $setting['label']) : null,
            'valueFormat' => $this->control_value_format(isset($setting['controlType']) ? (string) $setting['controlType'] : 'mixed'),
        ];

        if (!empty($setting['options']) && is_array($setting['options'])) {
            $out['options'] = $this->compact_control_options($setting['options']);
        }

        if (!empty($setting['css'])) {
            $out['css'] = $this->sanitize_schema_value($setting['css'], 0, 4);
        }

        if (!empty($setting['valueSchema'])) {
            $out['valueSchema'] = $this->sanitize_schema_value($setting['valueSchema'], 0, 5);
        }

        return array_filter($out, function ($value) {
            return $value !== null && $value !== [];
        });
    }

    private function official_setting_to_json_schema($setting, $key) {
        $schema = [];
        if (!empty($setting['valueSchema']) && is_array($setting['valueSchema'])) {
            $schema = $this->sanitize_schema_value($setting['valueSchema'], 0, 6);
        }

        if (!is_array($schema) || empty($schema)) {
            $schema = [
                'description' => isset($setting['label']) ? wp_strip_all_tags((string) $setting['label']) : $key,
            ];
        }

        if (isset($setting['label']) && empty($schema['description'])) {
            $schema['description'] = wp_strip_all_tags((string) $setting['label']);
        }

        return $schema;
    }

    private function parse_bool_param($value, $default = false) {
        if ($value === null) {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed === null ? $default : $parsed;
    }

    private function get_bricks_elements_registry() {
        if (!class_exists('\Bricks\Elements')) {
            return [];
        }

        try {
            if (isset(\Bricks\Elements::$elements) && is_array(\Bricks\Elements::$elements)) {
                return \Bricks\Elements::$elements;
            }
        } catch (Throwable $e) {
            // Fallback ci-dessous si Bricks change la visibilité de la propriété.
        }

        try {
            if (method_exists('\Bricks\Elements', 'get_elements')) {
                $elements = \Bricks\Elements::get_elements();
                return is_array($elements) ? $elements : [];
            }
        } catch (Throwable $e) {
            return [];
        }

        return [];
    }

    private function get_bricks_element_object($element_name, $entry) {
        if (is_object($entry)) {
            return $entry;
        }

        $class = null;
        if (is_string($entry)) {
            $class = $entry;
        } elseif (is_array($entry)) {
            if (isset($entry['instance']) && is_object($entry['instance'])) {
                return $entry['instance'];
            }
            $class = $entry['class'] ?? $entry['element_class'] ?? null;
        }

        if (!is_string($class) || !class_exists($class)) {
            return null;
        }

        try {
            return new $class(['name' => $element_name]);
        } catch (Throwable $e) {
            try {
                return new $class([]);
            } catch (Throwable $e2) {
                try {
                    return new $class();
                } catch (Throwable $e3) {
                    return null;
                }
            }
        }
    }

    private function build_bricks_element_schema($element_name, $object, $include_raw = false) {
        $controls = $this->get_bricks_element_controls($object);
        $compact_controls = [];
        $settings_properties = [];

        foreach ($controls as $key => $control) {
            if (!is_array($control)) {
                continue;
            }

            $type = isset($control['type']) ? (string) $control['type'] : '';
            if (in_array($type, ['group', 'section', 'tab', 'separator', 'data'], true)) {
                continue;
            }

            $compact_controls[] = $this->compact_bricks_control((string) $key, $control, $include_raw);
            $settings_properties[(string) $key] = $this->control_to_json_schema($control, (string) $key);
        }

        return [
            'name' => $element_name,
            'label' => $this->get_bricks_element_label($object, $element_name),
            'category' => $this->get_bricks_element_category($object),
            'nestable' => $this->is_bricks_element_nestable($object),
            'controlCount' => count($compact_controls),
            'controls' => $compact_controls,
            'settingsSchema' => [
                'type' => 'object',
                'additionalProperties' => true,
                'properties' => empty($settings_properties) ? new stdClass() : $settings_properties,
            ],
            'minimalElement' => [
                'id' => 'example_id',
                'name' => $element_name,
                'parent' => 'parent_id',
                'children' => [],
                'settings' => new stdClass(),
            ],
        ];
    }

    private function get_bricks_element_controls($object) {
        try {
            if (method_exists($object, 'get_controls')) {
                $controls = $object->get_controls();
                return is_array($controls) ? $controls : [];
            }
        } catch (Throwable $e) {
            return [];
        }

        if (isset($object->controls) && is_array($object->controls)) {
            return $object->controls;
        }

        return [];
    }

    private function compact_bricks_control($key, $control, $include_raw = false) {
        $type = isset($control['type']) ? (string) $control['type'] : 'mixed';
        $out = [
            'key' => $key,
            'type' => $type,
            'label' => isset($control['label']) ? wp_strip_all_tags((string) $control['label']) : null,
            'group' => isset($control['group']) ? (string) $control['group'] : null,
            'tab' => isset($control['tab']) ? (string) $control['tab'] : null,
            'default' => array_key_exists('default', $control) ? $this->sanitize_schema_value($control['default']) : null,
            'required' => !empty($control['required']),
            'valueFormat' => $this->control_value_format($type),
        ];

        if (!empty($control['options']) && is_array($control['options'])) {
            $out['options'] = $this->compact_control_options($control['options']);
        }

        if (!empty($control['fields']) && is_array($control['fields'])) {
            $fields = [];
            foreach ($control['fields'] as $field_key => $field) {
                if (is_array($field)) {
                    $fields[] = $this->compact_bricks_control((string) $field_key, $field, false);
                }
            }
            $out['fields'] = $fields;
        }

        if (!empty($control['css'])) {
            $out['css'] = $this->sanitize_schema_value($control['css'], 0, 4);
        }

        if ($include_raw) {
            $out['raw'] = $this->sanitize_schema_value($control, 0, 5);
        }

        return array_filter($out, function ($value) {
            return $value !== null && $value !== [];
        });
    }

    private function compact_control_options($options) {
        $compact = [];
        $count = 0;
        foreach ($options as $value => $label) {
            if ($count >= 120) {
                $compact[] = ['truncated' => true, 'remaining' => count($options) - $count];
                break;
            }

            if (is_array($label)) {
                $compact[] = [
                    'value' => (string) $value,
                    'label' => isset($label['label']) ? wp_strip_all_tags((string) $label['label']) : (isset($label['name']) ? wp_strip_all_tags((string) $label['name']) : (string) $value),
                ];
            } elseif (is_scalar($label)) {
                $compact[] = [
                    'value' => (string) $value,
                    'label' => wp_strip_all_tags((string) $label),
                ];
            }
            $count++;
        }
        return $compact;
    }

    private function sanitize_schema_value($value, $depth = 0, $max_depth = 5) {
        if ($depth > $max_depth) {
            return '[truncated]';
        }
        if ($value === null || is_scalar($value)) {
            return $value;
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                if (is_object($item) && $item instanceof Closure) {
                    continue;
                }
                $out[$key] = $this->sanitize_schema_value($item, $depth + 1, $max_depth);
            }
            return $out;
        }
        if (is_object($value)) {
            if ($value instanceof Closure) {
                return null;
            }
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }
            return '[' . get_class($value) . ']';
        }
        return null;
    }

    private function control_to_json_schema($control, $key = '') {
        $type = isset($control['type']) ? (string) $control['type'] : 'mixed';
        $schema = [
            'description' => isset($control['label']) ? wp_strip_all_tags((string) $control['label']) : $key,
            'controlType' => $type,
            'valueFormat' => $this->control_value_format($type),
        ];

        switch ($type) {
            case 'checkbox':
            case 'toggle':
                $schema['type'] = 'boolean';
                break;
            case 'number':
                $schema['type'] = ['string', 'number'];
                break;
            case 'select':
                $schema['type'] = 'string';
                if (!empty($control['options']) && is_array($control['options'])) {
                    $schema['enum'] = array_map('strval', array_keys($control['options']));
                }
                break;
            case 'repeater':
            case 'gallery':
                $schema['type'] = 'array';
                break;
            case 'color':
            case 'typography':
            case 'background':
            case 'border':
            case 'box-shadow':
            case 'dimensions':
            case 'image':
            case 'link':
            case 'icon':
                $schema['type'] = 'object';
                break;
            default:
                $schema['type'] = ['string', 'number', 'boolean', 'object', 'array'];
                break;
        }

        return $schema;
    }

    private function control_value_format($type) {
        switch ($type) {
            case 'color':
                return 'object: {"raw":"#111827"} ou {"raw":"var(--bricks-color-id)","id":"id"}';
            case 'dimensions':
                return 'object: {top,right,bottom,left} avec valeurs string, px sans unité si possible';
            case 'typography':
                return 'object Bricks typography; line-height en string';
            case 'background':
                return 'object Bricks background; color via {"raw":"..."}';
            case 'border':
                return 'object Bricks border; radius sous radius.{top,right,bottom,left}';
            case 'box-shadow':
                return 'object: {values:{offsetX,offsetY,blur,spread}, color:{raw}}';
            case 'image':
                return 'object media: {id,url,size}';
            case 'gallery':
                return 'array de medias: [{id,url,size}]';
            case 'link':
                return 'object lien: {type,url,newTab,nofollow}';
            case 'icon':
                return 'object icone: {library,icon,svg}';
            case 'repeater':
                return 'array d’objets selon fields';
            case 'checkbox':
            case 'toggle':
                return 'boolean';
            case 'select':
            case 'text':
            case 'textarea':
            case 'editor':
            case 'code':
                return 'string';
            case 'number':
                return 'string ou number; préférer string pour valeurs CSS';
            default:
                return 'format selon contrôle Bricks runtime';
        }
    }

    private function get_bricks_element_label($object, $fallback) {
        try {
            if (method_exists($object, 'get_label')) {
                $label = $object->get_label();
                if (is_string($label) && $label !== '') {
                    return wp_strip_all_tags($label);
                }
            }
        } catch (Throwable $e) {
            // Fallback ci-dessous.
        }

        if (isset($object->label) && is_string($object->label) && $object->label !== '') {
            return wp_strip_all_tags($object->label);
        }

        return $fallback;
    }

    private function get_bricks_element_category($object) {
        if (isset($object->category) && is_string($object->category) && $object->category !== '') {
            return $object->category;
        }
        try {
            if (method_exists($object, 'get_category')) {
                $category = $object->get_category();
                if (is_string($category) && $category !== '') {
                    return $category;
                }
            }
        } catch (Throwable $e) {
            // Fallback ci-dessous.
        }
        return 'general';
    }

    private function is_bricks_element_nestable($object) {
        if (isset($object->nestable)) {
            return (bool) $object->nestable;
        }
        if (isset($object->is_nestable)) {
            return (bool) $object->is_nestable;
        }
        try {
            if (method_exists($object, 'is_nestable')) {
                return (bool) $object->is_nestable();
            }
        } catch (Throwable $e) {
            return false;
        }
        return false;
    }

    private function find_similar_strings($needle, $haystack) {
        $scores = [];
        foreach ($haystack as $candidate) {
            similar_text((string) $needle, (string) $candidate, $percent);
            if ($percent >= 35) {
                $scores[(string) $candidate] = $percent;
            }
        }
        arsort($scores);
        return array_slice(array_keys($scores), 0, 8);
    }

    private function get_inherited_bricks_style_controls() {
        return [
            'source' => 'common Bricks style controls, compact MCP reference',
            'responsiveSyntax' => '{key}:{breakpoint} ex: _padding:mobile_portrait',
            'stateSyntax' => '{key}:{state} ex: _background:hover',
            'controls' => [
                ['key' => '_display', 'format' => 'string', 'examples' => ['block', 'flex', 'grid', 'none']],
                ['key' => '_direction', 'format' => 'string', 'note' => 'flex-direction fiable sur section/container/block/div'],
                ['key' => '_flexDirection', 'format' => 'string', 'note' => 'contrôle hérité; ne remplace pas toujours _direction sur layout elements'],
                ['key' => '_flexWrap', 'format' => 'string'],
                ['key' => '_alignItems', 'format' => 'string'],
                ['key' => '_justifyContent', 'format' => 'string'],
                ['key' => '_columnGap', 'format' => 'string px sans unité'],
                ['key' => '_rowGap', 'format' => 'string px sans unité'],
                ['key' => '_gridTemplateColumns', 'format' => 'string CSS', 'note' => 'validé Bricks 2.3.2'],
                ['key' => '_gridTemplateRows', 'format' => 'string CSS'],
                ['key' => '_gridGap', 'format' => 'string px sans unité'],
                ['key' => '_margin', 'format' => '{top,right,bottom,left} strings'],
                ['key' => '_padding', 'format' => '{top,right,bottom,left} strings'],
                ['key' => '_width', 'format' => 'string'],
                ['key' => '_widthMin', 'format' => 'string'],
                ['key' => '_widthMax', 'format' => 'string'],
                ['key' => '_height', 'format' => 'string'],
                ['key' => '_heightMin', 'format' => 'string'],
                ['key' => '_heightMax', 'format' => 'string'],
                ['key' => '_aspectRatio', 'format' => 'string CSS'],
                ['key' => '_typography', 'format' => 'object; color via {raw}; line-height string'],
                ['key' => '_background', 'format' => 'object; color via {raw}'],
                ['key' => '_border', 'format' => 'object; radius sous radius.{top,right,bottom,left}'],
                ['key' => '_boxShadow', 'format' => '{values:{offsetX,offsetY,blur,spread}, color:{raw}}'],
                ['key' => '_cssFilters', 'format' => 'object; brightness/contrast en strings unitless ex "120"'],
                ['key' => '_transform', 'format' => 'object; translate/rotate/scale'],
                ['key' => '_position', 'format' => 'string'],
                ['key' => '_top/_right/_bottom/_left', 'format' => 'string'],
                ['key' => '_zIndex', 'format' => 'string ou number'],
                ['key' => '_cssClasses', 'format' => 'string classes séparées par espaces'],
            ],
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
     * v3.7.3 — Helper : télécharge la source vers un fichier tmp.
     * Accepte URL HTTP/HTTPS OU data URI (data:image/png;base64,...).
     * Retourne ['tmp' => path, 'mime' => mime|null, 'is_data_uri' => bool] ou WP_Error.
     */
    private function _download_source_to_tmp($source_url) {
        require_once ABSPATH . 'wp-admin/includes/file.php';

        // Data URI : on parse en base64 et on écrit dans un tmp
        if (stripos($source_url, 'data:') === 0) {
            if (!preg_match('#^data:([^;]+);base64,(.+)$#i', $source_url, $m)) {
                return new WP_Error('invalid_data_uri', 'Data URI mal formée (attendu : data:mime;base64,...)');
            }
            $mime = strtolower($m[1]);
            $bytes = base64_decode($m[2], true);
            if ($bytes === false) {
                return new WP_Error('invalid_b64', 'Base64 invalide dans la data URI');
            }
            $tmp = wp_tempnam();
            if (!$tmp) {
                return new WP_Error('tmp_failed', 'Impossible de créer le fichier temporaire');
            }
            if (file_put_contents($tmp, $bytes) === false) {
                @unlink($tmp);
                return new WP_Error('tmp_write_failed', 'Impossible d\'écrire le fichier temporaire');
            }
            return ['tmp' => $tmp, 'mime' => $mime, 'is_data_uri' => true];
        }

        // URL HTTP/HTTPS : comportement existant
        $tmp = download_url($source_url, 60);
        if (is_wp_error($tmp)) {
            return $tmp;
        }
        return ['tmp' => $tmp, 'mime' => null, 'is_data_uri' => false];
    }

    /**
     * v3.8.0 — Convertit un attachment image en WebP optimisé (qualité 80, max 2000px).
     * Remplace le fichier original et met à jour les meta WP.
     * Skip si déjà WebP, SVG, ou format non-convertible.
     * Retourne ['optimized' => bool, 'originalSize', 'optimizedSize', 'savings', 'reason'?] ou WP_Error.
     */
    private function _optimize_attachment_to_webp($attachment_id, $quality = 80, $max_width = 2000) {
        $original_path = get_attached_file($attachment_id);
        if (!$original_path || !file_exists($original_path)) {
            return ['optimized' => false, 'reason' => 'attachment file not found'];
        }

        $ext = strtolower(pathinfo($original_path, PATHINFO_EXTENSION));

        // Skip si déjà WebP
        if ($ext === 'webp') {
            return ['optimized' => false, 'reason' => 'already webp', 'size' => filesize($original_path)];
        }

        // Skip pour SVG, AVIF, vidéos, etc.
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif'])) {
            return ['optimized' => false, 'reason' => 'format not convertible (' . $ext . ')'];
        }

        $original_size = filesize($original_path);

        $editor = wp_get_image_editor($original_path);
        if (is_wp_error($editor)) {
            return ['optimized' => false, 'reason' => 'editor: ' . $editor->get_error_message()];
        }

        // Resize si plus grand que $max_width (garde les proportions)
        $dimensions = $editor->get_size();
        if ($dimensions && $dimensions['width'] > $max_width) {
            $editor->resize($max_width, null, false);
        }

        $editor->set_quality($quality);

        // Nouveau path .webp
        $webp_path = preg_replace('/\.[^.]+$/', '.webp', $original_path);

        $saved = $editor->save($webp_path, 'image/webp');
        if (is_wp_error($saved)) {
            return ['optimized' => false, 'reason' => 'save: ' . $saved->get_error_message()];
        }

        $new_size = filesize($webp_path);

        // Si le WebP est plus gros que l'original (rare mais possible sur petits PNG), on garde l'original
        if ($new_size >= $original_size) {
            @unlink($webp_path);
            return [
                'optimized' => false,
                'reason' => 'webp larger than original',
                'originalSize' => $original_size,
                'attemptedSize' => $new_size,
            ];
        }

        // Remplace le fichier original par le webp
        @unlink($original_path);
        update_attached_file($attachment_id, $webp_path);

        // MAJ MIME en BD
        wp_update_post([
            'ID' => $attachment_id,
            'post_mime_type' => 'image/webp',
        ]);

        // Régénère les meta (sizes etc.)
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $meta = wp_generate_attachment_metadata($attachment_id, $webp_path);
        if (!empty($meta)) {
            wp_update_attachment_metadata($attachment_id, $meta);
        }

        return [
            'optimized' => true,
            'originalSize' => $original_size,
            'optimizedSize' => $new_size,
            'savings' => round((1 - $new_size / $original_size) * 100, 1) . '%',
            'newFile' => basename($webp_path),
        ];
    }

    /**
     * v3.7.3 — Map MIME → extension pour les data URIs.
     */
    private function _ext_from_mime($mime) {
        $map = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'image/avif' => 'avif',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
        ];
        return $map[strtolower((string) $mime)] ?? null;
    }

    /**
     * Endpoint POST /upload-media — Upload une image dans la médiathèque depuis URL ou data URI.
     * Params : sourceUrl (requis — URL HTTP/HTTPS ou "data:mime;base64,..."), title, alt, caption (opt)
     */
    public function api_upload_media($request) {
        // v3.7.3 — Ne pas passer par esc_url_raw pour les data URIs (les casserait).
        $source_url = (string) $request->get_param('sourceUrl');
        $is_data_uri = stripos($source_url, 'data:') === 0;
        if (!$is_data_uri) {
            $source_url = esc_url_raw($source_url);
        }
        if (empty($source_url)) {
            return new WP_Error('missing_url', 'sourceUrl est requis', ['status' => 400]);
        }

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // v3.7.3 — helper qui gère URL HTTP/HTTPS ET data URIs
        $dl = $this->_download_source_to_tmp($source_url);
        if (is_wp_error($dl)) {
            return new WP_Error('download_failed', 'Impossible de récupérer la source : ' . $dl->get_error_message(), ['status' => 500]);
        }
        $tmp = $dl['tmp'];

        // Détecter le nom de fichier
        $title_for_name = $request->get_param('title');
        $url_filename = $is_data_uri ? '' : basename(parse_url($source_url, PHP_URL_PATH));
        // Extension : depuis le MIME (data URI) sinon depuis l'URL
        $ext = 'jpg';
        if ($is_data_uri && !empty($dl['mime'])) {
            $maybe_ext = $this->_ext_from_mime($dl['mime']);
            if ($maybe_ext) $ext = $maybe_ext;
        } elseif (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|avif|mp4|webm|mov)$/i', $url_filename, $m_ext)) {
            $ext = strtolower($m_ext[1]);
        } elseif (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|avif|mp4|webm|mov)([?#]|$)/i', $source_url, $m_ext)) {
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

        // v3.8.0 — Optimisation WebP optionnelle après upload
        $optimization = null;
        $optimize = $request->get_param('optimize');
        if ($optimize) {
            $optimization = $this->_optimize_attachment_to_webp($attachment_id);
        }

        return [
            'success'   => true,
            'id'        => $attachment_id,
            'url'       => wp_get_attachment_url($attachment_id),
            'filename'  => $optimization && !empty($optimization['optimized']) ? $optimization['newFile'] : $filename,
            // v3.7.4 — Ne pas écho le b64 entier des data URIs (économie contexte)
            'sourceUrl' => $is_data_uri ? '(data URI ' . strlen($source_url) . ' chars)' : $source_url,
            'optimization' => $optimization,
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
        $legacy_scripts = $page_settings['customScripts'] ?? '';
        $body_footer_scripts = $page_settings['customScriptsBodyFooter'] ?? '';
        return [
            'success'                 => true,
            'pageId'                  => $page_id,
            'customCss'               => $page_settings['customCss'] ?? '',
            // Legacy MCP alias. Bricks 2.3 uses the three location-specific keys below.
            'customScripts'           => $legacy_scripts !== '' ? $legacy_scripts : $body_footer_scripts,
            'customScriptsHeader'     => $page_settings['customScriptsHeader'] ?? '',
            'customScriptsBodyHeader' => $page_settings['customScriptsBodyHeader'] ?? '',
            'customScriptsBodyFooter' => $body_footer_scripts,
            'hasLegacyCustomScripts'  => $legacy_scripts !== '',
        ];
    }

    /**
     * POST /set-page-custom-code — Définit du CSS/JS spécifique à une page.
     * Params : pageId (int), customCss, customScriptsHeader, customScriptsBodyHeader,
     * customScriptsBodyFooter. customScripts reste un alias legacy vers body footer.
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
        if ($css !== null) {
            $page_settings['customCss'] = (string) $css;
            $changed[] = 'customCss';
        }

        $legacy_js = $request->get_param('customScripts');
        if ($legacy_js !== null) {
            $page_settings['customScriptsBodyFooter'] = (string) $legacy_js;
            unset($page_settings['customScripts']);
            $changed[] = 'customScriptsBodyFooter';
            $changed[] = 'customScriptsLegacyAlias';
        }

        foreach (['customScriptsHeader', 'customScriptsBodyHeader', 'customScriptsBodyFooter'] as $key) {
            $value = $request->get_param($key);
            if ($value !== null) {
                $page_settings[$key] = (string) $value;
                $changed[] = $key;
            }
        }

        if (empty($changed)) {
            return new WP_Error('no_changes', 'Aucun champ fourni (customCss, customScriptsHeader, customScriptsBodyHeader, customScriptsBodyFooter, customScripts legacy)', ['status' => 400]);
        }

        delete_post_meta($page_id, '_bricks_page_settings');
        add_post_meta($page_id, '_bricks_page_settings', $page_settings, true);
        $this->clear_bricks_cache($page_id);

        return ['success' => true, 'pageId' => $page_id, 'updated' => array_values(array_unique($changed)), 'message' => 'Custom code de la page mis à jour'];
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
        $host          = wp_parse_url($site_url, PHP_URL_HOST);
        $suggest_insecure_ssl = is_string($host) && preg_match('/(?:odns\.fr|live-website\.com|tempurl|staging|preprod)/i', $host);

        $codex_script_url = '';
        $codex_command_1 = '';
        $codex_command_2 = '';
        if ($is_configured) {
            $codex_script_url = $this->build_codex_installer_url($site_url, $site_name, $suggest_insecure_ssl);
            $safe_slug = sanitize_title($site_name);
            if (empty($safe_slug) && $host) {
                $safe_slug = sanitize_title($host);
            }
            if (empty($safe_slug)) {
                $safe_slug = 'bricks-site';
            }
            $tmp_script = '/tmp/bricks-' . $safe_slug . '-install-codex.sh';
            $curl_flags = $suggest_insecure_ssl ? '-kfsSL' : '-fsSL';
            $codex_command_1 = "curl {$curl_flags} " . escapeshellarg($codex_script_url) . " -o " . escapeshellarg($tmp_script);
            $codex_command_2 = "bash " . escapeshellarg($tmp_script);
        }
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

                <?php
                // v3.9.0 — Badge de version skill
                $current_skill = $this->_read_skill_version();
                $last_dl_skill = get_option('bricks_mcp_last_downloaded_skill_version', null);
                if ($current_skill && $last_dl_skill && version_compare($last_dl_skill, $current_skill, '<')) :
                ?>
                    <div style="background:#fef3c7;border-left:4px solid #d97706;padding:12px 16px;margin:16px 0;border-radius:4px;">
                        <strong>⚠ Mise à jour de la doc disponible</strong> — Skill embarqué dans ton dernier <code>.plugin</code> : <strong>v<?php echo esc_html($last_dl_skill); ?></strong>. Version actuelle : <strong>v<?php echo esc_html($current_skill); ?></strong>.<br>
                        <em>Re-télécharge le <code>.plugin</code> ci-dessous et réinstalle-le dans Cowork pour avoir la doc à jour.</em>
                    </div>
                <?php elseif ($current_skill && $last_dl_skill) : ?>
                    <div style="background:#dcfce7;border-left:4px solid #16a34a;padding:8px 12px;margin:16px 0;border-radius:4px;font-size:13px;">
                        ✓ Skill v<?php echo esc_html($current_skill); ?> à jour dans ton dernier <code>.plugin</code>.
                    </div>
                <?php elseif ($current_skill) : ?>
                    <div style="background:#eff6ff;border-left:4px solid #2271b1;padding:8px 12px;margin:16px 0;border-radius:4px;font-size:13px;">
                        ℹ Version skill disponible : <strong>v<?php echo esc_html($current_skill); ?></strong>. Télécharge le <code>.plugin</code> ci-dessous pour l'embarquer.
                    </div>
                <?php endif; ?>

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

            <!-- ÉTAPE 3 : Installer dans Codex -->
            <div style="background:#fff;padding:24px;margin:16px 0;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);border-left:4px solid <?php echo $is_configured ? '#7c3aed' : '#c3c4c7'; ?>;<?php echo $is_configured ? '' : 'opacity:.6;'; ?>">
                <h2 style="margin-top:0;">Étape 3 — Connecter à Codex</h2>
                <p>Copie-colle les <strong>2 commandes</strong> ci-dessous dans le Terminal. Elles vont créer le plugin local Codex, récupérer le skill bricks-builder à jour depuis GitHub et configurer le MCP de ce site automatiquement.</p>

                <?php if (!$is_configured): ?>
                    <p style="color:#dba617;font-weight:600;">⚠ Génère d'abord la clé API à l'étape 1.</p>
                <?php else: ?>
                    <div style="background:#f5f3ff;border-left:4px solid #7c3aed;padding:12px 16px;margin:16px 0;border-radius:4px;">
                        <strong>Bonus :</strong> la doc/skill n'est pas figée dans un fichier téléchargé. Le script va chercher le dossier <code>skill/</code> à jour sur GitHub au moment de l'installation. Si tu mets à jour la doc, il suffit de relancer ces 2 commandes.
                    </div>

                    <p style="margin:16px 0 8px;"><strong>Commande 1</strong></p>
                    <textarea readonly onclick="this.select();" style="width:100%;min-height:58px;font-family:Menlo,Consolas,monospace;font-size:12px;"><?php echo esc_textarea($codex_command_1); ?></textarea>

                    <p style="margin:16px 0 8px;"><strong>Commande 2</strong></p>
                    <textarea readonly onclick="this.select();" style="width:100%;min-height:58px;font-family:Menlo,Consolas,monospace;font-size:12px;"><?php echo esc_textarea($codex_command_2); ?></textarea>

                    <div style="margin-top:24px;padding:16px;background:#faf5ff;border-radius:4px;border-left:3px solid #7c3aed;">
                        <strong>Notes :</strong>
                        <ul style="margin:8px 0 0 20px;list-style:disc;">
                            <li>Le lien de téléchargement du script est signé et valable 24h.</li>
                            <li>Si Codex est déjà ouvert, relance-le après l'installation.</li>
                            <li><?php echo $suggest_insecure_ssl ? 'Le mode SSL invalide a été activé automatiquement pour cette URL.' : 'Le mode SSL invalide n\'est pas activé par défaut.'; ?></li>
                        </ul>
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

        // v3.9.0 — Enregistre la version skill embarquée dans CE .plugin
        // pour pouvoir détecter ensuite si une MAJ est dispo.
        $current_skill_version = $this->_read_skill_version();
        if ($current_skill_version) {
            update_option('bricks_mcp_last_downloaded_skill_version', $current_skill_version, false);
        }

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

    private function build_codex_installer_url($site_url, $label, $insecure_ssl = false) {
        $site_url = untrailingslashit(esc_url_raw(trim($site_url)));
        $label = sanitize_text_field($label);
        if (empty($label)) {
            $label = 'Mon site Bricks';
        }

        $expires = time() + DAY_IN_SECONDS;
        $token = $this->build_codex_installer_token($site_url, $label, $insecure_ssl, $expires);

        $query = http_build_query([
            'action'       => 'bricks_stream_codex_installer',
            'site_url'     => $site_url,
            'plugin_label' => $label,
            'insecure_ssl' => $insecure_ssl ? '1' : '0',
            'expires'      => $expires,
            'token'        => $token,
        ], '', '&', PHP_QUERY_RFC3986);

        return admin_url('admin-post.php') . '?' . $query;
    }

    private function build_codex_installer_token($site_url, $label, $insecure_ssl, $expires) {
        $payload = implode('|', [
            untrailingslashit((string) $site_url),
            (string) $label,
            $insecure_ssl ? '1' : '0',
            (string) $expires,
        ]);
        return hash_hmac('sha256', $payload, wp_salt('auth'));
    }

    /**
     * Stream public d'un script d'installation Codex protégé par URL signée.
     * La clé API reste côté serveur WordPress et n'apparaît jamais dans la commande.
     */
    public function handle_stream_codex_installer() {
        $site_url = isset($_GET['site_url']) ? wp_unslash($_GET['site_url']) : home_url();
        $site_url = untrailingslashit(esc_url_raw(trim($site_url)));
        $label = isset($_GET['plugin_label']) ? wp_unslash($_GET['plugin_label']) : get_bloginfo('name');
        $label = sanitize_text_field($label);
        $insecure_ssl = !empty($_GET['insecure_ssl']) && $_GET['insecure_ssl'] !== '0';
        $expires = isset($_GET['expires']) ? (int) $_GET['expires'] : 0;
        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';

        if (empty($label)) {
            $label = 'Mon site Bricks';
        }

        if (!$expires || $expires < time()) {
            wp_die('Lien expiré. Retourne dans WordPress pour régénérer les commandes Codex.', 'Lien expiré', ['response' => 403]);
        }

        $expected_token = $this->build_codex_installer_token($site_url, $label, $insecure_ssl, $expires);
        if (empty($token) || !hash_equals($expected_token, $token)) {
            wp_die('Signature invalide.', 'Erreur', ['response' => 403]);
        }

        $api_key = get_option('bricks_mcp_api_key');
        if (empty($api_key)) {
            wp_die('Clé API manquante. Génère-la d\'abord dans WordPress.', 'Erreur', ['response' => 400]);
        }

        $slug = sanitize_title($label);
        if (empty($slug)) {
            $slug = sanitize_title(parse_url($site_url, PHP_URL_HOST));
        }
        $plugin_name = 'bricks-' . $slug;
        $github_repo = untrailingslashit(get_option('bricks_mcp_github_repo', BRICKS_MCP_DEFAULT_GITHUB_REPO));
        $archive_url = $github_repo . '/archive/refs/heads/main.tar.gz';

        $env_vars = [
            'WORDPRESS_URL' => $site_url,
            'API_KEY'       => $api_key,
        ];
        if ($insecure_ssl) {
            $env_vars['INSECURE_SSL'] = 'true';
        }

        $current_skill_version = $this->_read_skill_version();
        $codex_plugin_version = $current_skill_version ?: BRICKS_MCP_VERSION;

        $plugin_manifest = wp_json_encode([
            'name'        => $plugin_name,
            'version'     => $codex_plugin_version,
            'description' => sprintf('Connecte Codex au site %s pour piloter Bricks Builder.', $label),
            'author'      => [
                'name' => 'Bricks Builder MCP',
                'url'  => 'https://github.com/Scott1012/bricks-builder-mcp',
            ],
            'skills'      => './skills/',
            'mcpServers'  => './.mcp.json',
            'interface'   => [
                'displayName'      => $label,
                'shortDescription' => 'Pilote Bricks Builder pour ce site',
                'longDescription'  => sprintf('Plugin local Codex généré automatiquement pour connecter le site %s à Bricks Builder MCP.', $label),
                'developerName'    => 'Bricks Builder MCP',
                'category'         => 'Productivity',
                'capabilities'     => ['Interactive', 'Write'],
                'websiteURL'       => 'https://github.com/Scott1012/bricks-builder-mcp',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $mcp_json = wp_json_encode([
            'mcpServers' => [
                'bricks-mcp' => [
                    'command' => 'npx',
                    'args'    => ['-y', 'bricks-builder-mcp'],
                    'env'     => $env_vars,
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $installer = $this->build_codex_installer_script(
            $plugin_name,
            $plugin_manifest,
            $mcp_json,
            $archive_url
        );

        if ($current_skill_version) {
            update_option('bricks_mcp_last_downloaded_skill_version', $current_skill_version, false);
        }

        if (ob_get_length()) {
            ob_end_clean();
        }

        header('Content-Type: text/x-shellscript; charset=utf-8');
        header('Content-Disposition: inline; filename="' . $plugin_name . '-install-codex.sh"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo $installer;
        exit;
    }

    private function build_codex_installer_script($plugin_name, $plugin_manifest, $mcp_json, $archive_url) {
        $plugin_name_py = wp_json_encode($plugin_name, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $marketplace_entry_py = wp_json_encode([
            'name' => $plugin_name,
            'source' => [
                'source' => 'local',
                'path'   => './plugins/' . $plugin_name,
            ],
            'policy' => [
                'installation'   => 'AVAILABLE',
                'authentication' => 'ON_INSTALL',
            ],
            'category' => 'Productivity',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return "#!/bin/bash\n"
            . "set -euo pipefail\n\n"
            . "PLUGIN_NAME=" . escapeshellarg($plugin_name) . "\n"
            . "ARCHIVE_URL=" . escapeshellarg($archive_url) . "\n"
            . "PLUGIN_ROOT=\"\$HOME/plugins/\$PLUGIN_NAME\"\n"
            . "PLUGIN_SKILL_DIR=\"\$PLUGIN_ROOT/skills/bricks-builder\"\n"
            . "PLUGIN_META_DIR=\"\$PLUGIN_ROOT/.codex-plugin\"\n"
            . "MARKETPLACE_DIR=\"\$HOME/.agents/plugins\"\n"
            . "TMP_DIR=\"\$(mktemp -d)\"\n\n"
            . "cleanup() {\n"
            . "  rm -rf \"\$TMP_DIR\"\n"
            . "}\n"
            . "trap cleanup EXIT\n\n"
            . "need_cmd() {\n"
            . "  if ! command -v \"\$1\" >/dev/null 2>&1; then\n"
            . "    echo \"Commande manquante : \$1\" >&2\n"
            . "    exit 1\n"
            . "  fi\n"
            . "}\n\n"
            . "need_cmd curl\n"
            . "need_cmd tar\n"
            . "need_cmd python3\n\n"
            . "mkdir -p \"\$PLUGIN_META_DIR\" \"\$PLUGIN_SKILL_DIR\" \"\$MARKETPLACE_DIR\"\n\n"
            . "cat > \"\$PLUGIN_META_DIR/plugin.json\" <<'JSON'\n"
            . $plugin_manifest . "\n"
            . "JSON\n\n"
            . "cat > \"\$PLUGIN_ROOT/.mcp.json\" <<'JSON'\n"
            . $mcp_json . "\n"
            . "JSON\n\n"
            . "echo \"Téléchargement du skill bricks-builder depuis GitHub...\"\n"
            . "curl -fsSL \"\$ARCHIVE_URL\" -o \"\$TMP_DIR/repo.tar.gz\"\n"
            . "tar -xzf \"\$TMP_DIR/repo.tar.gz\" -C \"\$TMP_DIR\"\n"
            . "SOURCE_SKILL_FILE=\"\$(find \"\$TMP_DIR\" -type f -name 'SKILL.md' -path '*/skill/SKILL.md' | head -n 1)\"\n"
            . "if [ -z \"\$SOURCE_SKILL_FILE\" ]; then\n"
            . "  echo \"Impossible de trouver le dossier skill/ dans l'archive GitHub.\" >&2\n"
            . "  exit 1\n"
            . "fi\n"
            . "SOURCE_SKILL_DIR=\"\${SOURCE_SKILL_FILE%/SKILL.md}\"\n"
            . "rm -rf \"\$PLUGIN_SKILL_DIR\"\n"
            . "mkdir -p \"\$PLUGIN_SKILL_DIR\"\n"
            . "cp -R \"\$SOURCE_SKILL_DIR\"/. \"\$PLUGIN_SKILL_DIR/\"\n\n"
            . "python3 <<'PY'\n"
            . "import json\n"
            . "import os\n\n"
            . "marketplace_path = os.path.expanduser('~/.agents/plugins/marketplace.json')\n"
            . "plugin_name = " . $plugin_name_py . "\n"
            . "entry = " . $marketplace_entry_py . "\n\n"
            . "if os.path.exists(marketplace_path):\n"
            . "    with open(marketplace_path, 'r', encoding='utf-8') as fh:\n"
            . "        data = json.load(fh)\n"
            . "else:\n"
            . "    data = {\n"
            . "        'name': 'personal',\n"
            . "        'interface': {'displayName': 'Personal'},\n"
            . "        'plugins': [],\n"
            . "    }\n\n"
            . "plugins = [p for p in data.get('plugins', []) if p.get('name') != plugin_name]\n"
            . "plugins.append(entry)\n"
            . "data['plugins'] = plugins\n"
            . "data.setdefault('name', 'personal')\n"
            . "data.setdefault('interface', {'displayName': 'Personal'})\n\n"
            . "with open(marketplace_path, 'w', encoding='utf-8') as fh:\n"
            . "    json.dump(data, fh, ensure_ascii=False, indent=2)\n"
            . "    fh.write('\\n')\n"
            . "PY\n\n"
            . "CODEX_BIN=\"\"\n"
            . "if command -v codex >/dev/null 2>&1; then\n"
            . "  CODEX_BIN=\"\$(command -v codex)\"\n"
            . "elif [ -x \"/Applications/Codex.app/Contents/Resources/codex\" ]; then\n"
            . "  CODEX_BIN=\"/Applications/Codex.app/Contents/Resources/codex\"\n"
            . "elif [ -x \"\$HOME/Applications/Codex.app/Contents/Resources/codex\" ]; then\n"
            . "  CODEX_BIN=\"\$HOME/Applications/Codex.app/Contents/Resources/codex\"\n"
            . "fi\n\n"
            . "if [ -n \"\$CODEX_BIN\" ]; then\n"
            . "  echo \"Activation/rafraîchissement du plugin dans Codex...\"\n"
            . "  \"\$CODEX_BIN\" plugin add \"\$PLUGIN_NAME@personal\"\n"
            . "else\n"
            . "  echo \"CLI Codex introuvable. Lance manuellement : /Applications/Codex.app/Contents/Resources/codex plugin add \$PLUGIN_NAME@personal\"\n"
            . "fi\n\n"
            . "echo\n"
            . "echo \"Installation Codex terminée pour \$PLUGIN_NAME.\"\n"
            . "echo \"Relance Codex si le plugin n'apparaît pas tout de suite.\"\n";
    }

    // =====================================================
    // v3.7.0 — VERIFY ELEMENT INFO
    // =====================================================
    //
    // Retourne tout ce qu'il faut au MCP server pour faire une
    // vérification visuelle + technique d'un élément :
    // - URL frontend de la page (avec ancre vers l'élément)
    // - Sélecteur CSS Bricks (.brxe-{id})
    // - Settings attendus (typography, gap, padding, dimensions,
    //   background, border-radius) extraits depuis la DB
    // - Label de l'élément + nom + nombre d'enfants
    //
    public function api_verify_element_info($request) {
        $page_id = (int) $request->get_param('pageId');
        $element_id = $request->get_param('elementId');

        if (!$page_id || !$element_id) {
            return new WP_Error('missing_params', 'pageId et elementId requis', ['status' => 400]);
        }

        $json_data = get_post_meta($page_id, '_bricks_page_content_2', true);
        if (empty($json_data) || !is_array($json_data)) {
            return new WP_Error('page_not_found', 'Page non trouvée', ['status' => 404]);
        }

        $target = null;
        foreach ($json_data as $el) {
            if (($el['id'] ?? '') === $element_id) {
                $target = $el;
                break;
            }
        }
        if (!$target) {
            return new WP_Error('element_not_found', 'Élément non trouvé sur cette page', ['status' => 404]);
        }

        $permalink = get_permalink($page_id);
        if (!$permalink) {
            return new WP_Error('no_permalink', 'Impossible de calculer le permalink', ['status' => 500]);
        }

        $settings = $target['settings'] ?? [];
        $expected = $this->extract_expected_styles($settings);

        return rest_ensure_response([
            'success' => true,
            'pageId' => $page_id,
            'elementId' => $element_id,
            'url' => $permalink,
            'urlWithAnchor' => $permalink . '#brxe-' . $element_id,
            // Bricks utilise id="brxe-{id}" (id HTML attr, toujours présent),
            // pas .brxe-{id} (classe absente sur les sections).
            'selector' => '#brxe-' . $element_id,
            'name' => $target['name'] ?? null,
            'label' => $target['label'] ?? null,
            'childrenCount' => isset($target['children']) && is_array($target['children']) ? count($target['children']) : 0,
            'expected' => $expected,
            'rawSettings' => $settings,
        ]);
    }

    /**
     * Helper : ajoute "px" si pas déjà d'unité. Gère array {size, unit} et string.
     * Évite le bug "100vh" + "px" → "100vhpx".
     */
    private function with_unit($val, $default_unit = 'px') {
        if (is_array($val) && isset($val['size'])) {
            $size = (string) $val['size'];
            $unit = $val['unit'] ?? $default_unit;
            // Si size contient déjà une unité, ne pas en ajouter
            if (preg_match('/(px|vh|vw|em|rem|%|fr|ch|ex|cm|mm|in|pt|pc|svh|dvh|lvh)$/i', $size)) {
                return $size;
            }
            return $size . $unit;
        }
        if (is_numeric($val)) {
            return $val . $default_unit;
        }
        if (is_string($val) && $val !== '') {
            // Déjà une unité ? On garde tel quel.
            if (preg_match('/(px|vh|vw|em|rem|%|fr|ch|ex|cm|mm|in|pt|pc|svh|dvh|lvh|auto|none|normal)$/i', $val)) {
                return $val;
            }
            return $val . $default_unit;
        }
        return null;
    }

    /**
     * Convertit les settings Bricks en propriétés CSS attendues lisibles
     * pour comparaison avec getComputedStyle côté front.
     */
    private function extract_expected_styles($settings) {
        if (!is_array($settings)) {
            return [];
        }
        $expected = [];

        // Display + flex
        if (isset($settings['_display'])) {
            $expected['display'] = $settings['_display'];
        }
        if (isset($settings['_direction'])) {
            $expected['flex-direction'] = $settings['_direction'];
        }
        if (isset($settings['_justifyContent'])) {
            $expected['justify-content'] = $settings['_justifyContent'];
        }
        if (isset($settings['_alignItems'])) {
            $expected['align-items'] = $settings['_alignItems'];
        }
        if (isset($settings['_gap'])) {
            $expected['gap'] = $this->with_unit($settings['_gap']);
        }
        if (isset($settings['_columnGap'])) {
            $expected['column-gap'] = $this->with_unit($settings['_columnGap']);
        }
        if (isset($settings['_rowGap'])) {
            $expected['row-gap'] = $this->with_unit($settings['_rowGap']);
        }

        // Width / Height
        if (isset($settings['_widthMax'])) {
            $expected['max-width'] = $this->with_unit($settings['_widthMax']);
        }
        if (isset($settings['_width'])) {
            $expected['width'] = $this->with_unit($settings['_width']);
        }
        if (isset($settings['_height'])) {
            $expected['height'] = $this->with_unit($settings['_height']);
        }

        // Padding / Margin (flat ou shorthand)
        foreach (['_padding' => 'padding', '_margin' => 'margin'] as $key => $prop) {
            if (isset($settings[$key]) && is_array($settings[$key])) {
                $sides = $settings[$key];
                $expected[$prop . '-top'] = isset($sides['top']) ? $this->with_unit($sides['top']) : null;
                $expected[$prop . '-right'] = isset($sides['right']) ? $this->with_unit($sides['right']) : null;
                $expected[$prop . '-bottom'] = isset($sides['bottom']) ? $this->with_unit($sides['bottom']) : null;
                $expected[$prop . '-left'] = isset($sides['left']) ? $this->with_unit($sides['left']) : null;
            }
        }

        // Background color (raw rgba ou hex)
        if (isset($settings['_background']['color'])) {
            $bg = $settings['_background']['color'];
            if (is_array($bg)) {
                if (isset($bg['raw'])) {
                    $expected['background-color'] = $bg['raw'];
                } elseif (isset($bg['hex'])) {
                    $expected['background-color'] = $bg['hex'];
                }
            }
        }

        // Typography
        if (isset($settings['_typography']) && is_array($settings['_typography'])) {
            $typo = $settings['_typography'];
            if (isset($typo['font-size'])) {
                $expected['font-size'] = $this->with_unit($typo['font-size']);
            }
            if (isset($typo['line-height'])) {
                $expected['line-height'] = (string) $typo['line-height'];
            }
            if (isset($typo['font-family'])) {
                $expected['font-family'] = $typo['font-family'];
            }
            if (isset($typo['font-weight'])) {
                $expected['font-weight'] = (string) $typo['font-weight'];
            }
            if (isset($typo['color']['raw'])) {
                $expected['color'] = $typo['color']['raw'];
            } elseif (isset($typo['color']['hex'])) {
                $expected['color'] = $typo['color']['hex'];
            }
            if (isset($typo['text-align'])) {
                $expected['text-align'] = $typo['text-align'];
            }
        }

        // Border-radius (imbriqué)
        if (isset($settings['_border']['radius']) && is_array($settings['_border']['radius'])) {
            $r = $settings['_border']['radius'];
            $expected['border-top-left-radius'] = isset($r['top']) ? $this->with_unit($r['top']) : null;
            $expected['border-top-right-radius'] = isset($r['right']) ? $this->with_unit($r['right']) : null;
            $expected['border-bottom-right-radius'] = isset($r['bottom']) ? $this->with_unit($r['bottom']) : null;
            $expected['border-bottom-left-radius'] = isset($r['left']) ? $this->with_unit($r['left']) : null;
        }

        // Nettoyer les null
        return array_filter($expected, function($v) { return $v !== null; });
    }

    // =====================================================
    // v3.7.0 — FEEDBACK SYSTEM (missing features)
    // =====================================================
    //
    // Permet à un chat AI de signaler qu'une feature native Bricks
    // n'est pas exposée via MCP (ou outil buggy). Stocké dans l'option
    // WP `bricks_mcp_feedback` (array). Pas utilisé pour les features
    // que Bricks ne supporte pas nativement — dans ce cas l'AI doit
    // coder une alternative (CSS / JS via set_page_custom_code).
    //
    public function api_report_missing_feature($request) {
        $title = sanitize_text_field((string) $request->get_param('title'));
        $bricks_feature = sanitize_text_field((string) $request->get_param('bricksFeature'));
        $bricks_doc_url = esc_url_raw((string) $request->get_param('bricksDocUrl'));
        $what_it_should_do = sanitize_textarea_field((string) $request->get_param('whatItShouldDo'));
        $what_i_tried = sanitize_textarea_field((string) $request->get_param('whatITried'));
        $proposed_tool = sanitize_text_field((string) $request->get_param('proposedTool'));
        $bricks_version = sanitize_text_field((string) $request->get_param('bricksVersion'));
        $context = sanitize_textarea_field((string) $request->get_param('context'));

        if (empty($title) || empty($bricks_feature)) {
            return new WP_Error('missing_params', 'title et bricksFeature sont requis', ['status' => 400]);
        }

        $feedback = get_option('bricks_mcp_feedback', []);
        if (!is_array($feedback)) {
            $feedback = [];
        }

        // Dédupe par titre normalisé → augmente occurrences
        $key = strtolower(preg_replace('/\s+/', '-', trim($title)));
        $existing_index = null;
        foreach ($feedback as $i => $item) {
            if (($item['key'] ?? '') === $key && ($item['status'] ?? 'open') === 'open') {
                $existing_index = $i;
                break;
            }
        }

        $now = current_time('mysql');
        if ($existing_index !== null) {
            $feedback[$existing_index]['occurrences'] = (int)($feedback[$existing_index]['occurrences'] ?? 1) + 1;
            $feedback[$existing_index]['lastSeenAt'] = $now;
            // Ajouter le contexte si fourni
            if (!empty($context)) {
                $feedback[$existing_index]['contexts'][] = ['at' => $now, 'context' => $context];
            }
            update_option('bricks_mcp_feedback', $feedback, false);
            return rest_ensure_response([
                'success' => true,
                'id' => $feedback[$existing_index]['id'],
                'status' => 'incremented',
                'occurrences' => $feedback[$existing_index]['occurrences'],
                'message' => 'Feedback existant trouvé — occurrences incrémentées.',
            ]);
        }

        $new_item = [
            'id' => uniqid('fbk_', false),
            'key' => $key,
            'title' => $title,
            'bricksFeature' => $bricks_feature,
            'bricksDocUrl' => $bricks_doc_url,
            'whatItShouldDo' => $what_it_should_do,
            'whatITried' => $what_i_tried,
            'proposedTool' => $proposed_tool,
            'bricksVersion' => $bricks_version,
            'contexts' => !empty($context) ? [['at' => $now, 'context' => $context]] : [],
            'status' => 'open',
            'occurrences' => 1,
            'createdAt' => $now,
            'lastSeenAt' => $now,
        ];
        $feedback[] = $new_item;
        update_option('bricks_mcp_feedback', $feedback, false);

        return rest_ensure_response([
            'success' => true,
            'id' => $new_item['id'],
            'status' => 'created',
            'message' => 'Feedback enregistré. Remontera dans list_missing_features.',
            'totalOpen' => count(array_filter($feedback, function($it) { return ($it['status'] ?? 'open') === 'open'; })),
        ]);
    }

    public function api_list_missing_features($request) {
        $status_filter = $request->get_param('status'); // 'open' | 'resolved' | null (= tous)
        $feedback = get_option('bricks_mcp_feedback', []);
        if (!is_array($feedback)) {
            $feedback = [];
        }

        if (!empty($status_filter)) {
            $feedback = array_values(array_filter($feedback, function($it) use ($status_filter) {
                return ($it['status'] ?? 'open') === $status_filter;
            }));
        }

        // Tri : occurrences DESC, puis createdAt DESC
        usort($feedback, function($a, $b) {
            $oa = (int)($a['occurrences'] ?? 1);
            $ob = (int)($b['occurrences'] ?? 1);
            if ($oa !== $ob) return $ob - $oa;
            return strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? '');
        });

        return rest_ensure_response([
            'success' => true,
            'count' => count($feedback),
            'items' => $feedback,
        ]);
    }

    public function api_resolve_missing_feature($request) {
        $id = sanitize_text_field((string) $request->get_param('id'));
        $resolution_note = sanitize_textarea_field((string) $request->get_param('resolutionNote'));

        if (empty($id)) {
            return new WP_Error('missing_params', 'id requis', ['status' => 400]);
        }

        $feedback = get_option('bricks_mcp_feedback', []);
        if (!is_array($feedback)) {
            return new WP_Error('not_found', 'Aucun feedback enregistré', ['status' => 404]);
        }

        $found = false;
        foreach ($feedback as &$item) {
            if (($item['id'] ?? '') === $id) {
                $item['status'] = 'resolved';
                $item['resolvedAt'] = current_time('mysql');
                $item['resolutionNote'] = $resolution_note;
                $found = true;
                break;
            }
        }
        unset($item);

        if (!$found) {
            return new WP_Error('not_found', 'Feedback id introuvable', ['status' => 404]);
        }

        update_option('bricks_mcp_feedback', $feedback, false);
        return rest_ensure_response(['success' => true, 'id' => $id, 'status' => 'resolved']);
    }

    // =====================================================
    // v4.0.0 — CUSTOM POST TYPES (CPT)
    // =====================================================
    //
    // Permet la création/édition/listing de posts dans n'importe quel post_type
    // (CPT custom : chantier, avis_client, etc.). Support meta (ACF compatible),
    // taxonomies (avec résolution slug→ID + création à la volée), featured image.
    //

    /**
     * Helper : applique les meta fields (route ACF update_field si dispo, sinon update_post_meta).
     */
    private function _apply_post_meta($post_id, $meta) {
        if (!is_array($meta)) return;
        $has_acf = function_exists('update_field');
        foreach ($meta as $key => $value) {
            if ($has_acf) {
                update_field($key, $value, $post_id);
            } else {
                update_post_meta($post_id, $key, $value);
            }
        }
    }

    /**
     * Helper : résout les termes (slugs OU IDs) en IDs et les assigne au post.
     * Crée le terme à la volée si slug inexistant et que l'user a la capability.
     */
    private function _apply_post_taxonomies($post_id, $taxonomies) {
        if (!is_array($taxonomies)) return [];
        $results = [];
        foreach ($taxonomies as $taxonomy => $terms) {
            if (!taxonomy_exists($taxonomy)) {
                $results[$taxonomy] = ['error' => 'Taxonomy inexistante'];
                continue;
            }
            if (!is_array($terms)) $terms = [$terms];
            $term_ids = [];
            foreach ($terms as $t) {
                if (is_numeric($t)) {
                    $term_ids[] = (int) $t;
                    continue;
                }
                // String → cherche par slug puis par name
                $existing = get_term_by('slug', $t, $taxonomy);
                if (!$existing) {
                    $existing = get_term_by('name', $t, $taxonomy);
                }
                if ($existing) {
                    $term_ids[] = (int) $existing->term_id;
                } else {
                    // Créer le terme
                    $new = wp_insert_term($t, $taxonomy);
                    if (!is_wp_error($new)) {
                        $term_ids[] = (int) $new['term_id'];
                    }
                }
            }
            if (!empty($term_ids)) {
                $r = wp_set_post_terms($post_id, $term_ids, $taxonomy, false);
                $results[$taxonomy] = is_wp_error($r) ? ['error' => $r->get_error_message()] : $term_ids;
            }
        }
        return $results;
    }

    public function api_list_post_types($request) {
        $pts = get_post_types(['_builtin' => false], 'objects');
        // Inclure aussi 'page' et 'post' pour info
        $builtin = get_post_types(['_builtin' => true, 'public' => true], 'objects');
        $all = array_merge($builtin, $pts);

        $result = [];
        foreach ($all as $pt) {
            $taxonomies = get_object_taxonomies($pt->name, 'names');
            $result[] = [
                'name' => $pt->name,
                'label' => $pt->label ?? $pt->name,
                'showInRest' => !empty($pt->show_in_rest),
                'restBase' => $pt->rest_base ?? $pt->name,
                'public' => !empty($pt->public),
                'hierarchical' => !empty($pt->hierarchical),
                'supports' => get_all_post_type_supports($pt->name),
                'taxonomies' => array_values($taxonomies),
                'builtin' => !empty($pt->_builtin),
            ];
        }

        return rest_ensure_response([
            'success' => true,
            'count' => count($result),
            'postTypes' => $result,
        ]);
    }

    public function api_create_post($request) {
        $post_type = sanitize_key($request->get_param('postType') ?? '');
        $title = $request->get_param('title');

        if (empty($post_type) || empty($title)) {
            return new WP_Error('missing_params', 'postType et title sont requis', ['status' => 400]);
        }
        if (!post_type_exists($post_type)) {
            return new WP_Error('post_type_not_found', "Le post_type '$post_type' n'existe pas. Utilise list_post_types pour voir ce qui est disponible.", ['status' => 400]);
        }

        $postarr = [
            'post_type' => $post_type,
            'post_title' => sanitize_text_field($title),
            'post_status' => sanitize_key($request->get_param('status') ?? 'publish'),
        ];

        $optional_fields = [
            'content' => 'post_content',
            'excerpt' => 'post_excerpt',
            'slug' => 'post_name',
            'date' => 'post_date',
            'author' => 'post_author',
        ];
        foreach ($optional_fields as $param => $wp_key) {
            $val = $request->get_param($param);
            if ($val !== null && $val !== '') {
                $postarr[$wp_key] = ($param === 'content' || $param === 'excerpt') ? wp_kses_post($val) : $val;
            }
        }

        $post_id = wp_insert_post($postarr, true);
        if (is_wp_error($post_id)) {
            return new WP_Error('insert_failed', $post_id->get_error_message(), ['status' => 500]);
        }

        // Featured image
        $thumb_id = $request->get_param('featuredImageId');
        if (!empty($thumb_id)) {
            set_post_thumbnail($post_id, (int) $thumb_id);
        }

        // Meta (ACF compatible)
        $meta = $request->get_param('meta');
        if (is_array($meta)) {
            $this->_apply_post_meta($post_id, $meta);
        }

        // Taxonomies (slugs ou IDs, création à la volée)
        $tax_results = [];
        $taxonomies = $request->get_param('taxonomies');
        if (is_array($taxonomies)) {
            $tax_results = $this->_apply_post_taxonomies($post_id, $taxonomies);
        }

        return rest_ensure_response([
            'success' => true,
            'id' => $post_id,
            'postType' => $post_type,
            'title' => get_the_title($post_id),
            'url' => get_permalink($post_id),
            'editLink' => admin_url('post.php?post=' . $post_id . '&action=edit'),
            'status' => get_post_status($post_id),
            'taxonomiesApplied' => $tax_results,
        ]);
    }

    public function api_update_post($request) {
        $post_id = (int) $request->get_param('postId');
        if (!$post_id || !get_post($post_id)) {
            return new WP_Error('not_found', 'Post introuvable', ['status' => 404]);
        }

        $postarr = ['ID' => $post_id];
        $field_map = [
            'title' => 'post_title',
            'content' => 'post_content',
            'excerpt' => 'post_excerpt',
            'slug' => 'post_name',
            'status' => 'post_status',
            'date' => 'post_date',
        ];
        foreach ($field_map as $param => $wp_key) {
            $val = $request->get_param($param);
            if ($val !== null) {
                if ($param === 'content' || $param === 'excerpt') {
                    $postarr[$wp_key] = wp_kses_post($val);
                } else {
                    $postarr[$wp_key] = $val;
                }
            }
        }

        if (count($postarr) > 1) {
            $r = wp_update_post($postarr, true);
            if (is_wp_error($r)) {
                return new WP_Error('update_failed', $r->get_error_message(), ['status' => 500]);
            }
        }

        $thumb_id = $request->get_param('featuredImageId');
        if ($thumb_id !== null) {
            if (empty($thumb_id)) {
                delete_post_thumbnail($post_id);
            } else {
                set_post_thumbnail($post_id, (int) $thumb_id);
            }
        }

        $meta = $request->get_param('meta');
        if (is_array($meta)) {
            $this->_apply_post_meta($post_id, $meta);
        }

        $tax_results = [];
        $taxonomies = $request->get_param('taxonomies');
        if (is_array($taxonomies)) {
            $tax_results = $this->_apply_post_taxonomies($post_id, $taxonomies);
        }

        return rest_ensure_response([
            'success' => true,
            'id' => $post_id,
            'url' => get_permalink($post_id),
            'taxonomiesApplied' => $tax_results,
        ]);
    }

    public function api_delete_post($request) {
        $post_id = (int) $request->get_param('postId');
        $force = (bool) $request->get_param('force');

        if (!$post_id || !get_post($post_id)) {
            return new WP_Error('not_found', 'Post introuvable', ['status' => 404]);
        }

        $r = wp_delete_post($post_id, $force);
        if (!$r) {
            return new WP_Error('delete_failed', 'Suppression échouée', ['status' => 500]);
        }

        return rest_ensure_response([
            'success' => true,
            'id' => $post_id,
            'deleted' => $force ? 'permanent' : 'trash',
        ]);
    }

    public function api_get_post($request) {
        $post_id = (int) $request->get_param('postId');
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('not_found', 'Post introuvable', ['status' => 404]);
        }

        // Récupère meta + taxonomies
        $meta_all = get_post_meta($post_id);
        $meta = [];
        foreach ($meta_all as $k => $v) {
            if (strpos($k, '_') === 0) continue; // skip _internal
            $meta[$k] = (count($v) === 1) ? maybe_unserialize($v[0]) : array_map('maybe_unserialize', $v);
        }

        // ACF formaté si disponible
        $acf_fields = function_exists('get_fields') ? get_fields($post_id) : null;

        $taxonomies = [];
        foreach (get_object_taxonomies($post->post_type) as $tax) {
            $terms = wp_get_post_terms($post_id, $tax, ['fields' => 'all']);
            if (!is_wp_error($terms) && !empty($terms)) {
                $taxonomies[$tax] = array_map(function($t) {
                    return ['id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug];
                }, $terms);
            }
        }

        $thumb_id = get_post_thumbnail_id($post_id);

        return rest_ensure_response([
            'success' => true,
            'id' => $post_id,
            'postType' => $post->post_type,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'slug' => $post->post_name,
            'status' => $post->post_status,
            'date' => $post->post_date,
            'url' => get_permalink($post_id),
            'editLink' => admin_url('post.php?post=' . $post_id . '&action=edit'),
            'featuredImageId' => $thumb_id ?: null,
            'featuredImageUrl' => $thumb_id ? wp_get_attachment_url($thumb_id) : null,
            'meta' => $meta,
            'acfFields' => $acf_fields,
            'taxonomies' => $taxonomies,
        ]);
    }

    public function api_list_posts($request) {
        $post_type = sanitize_key($request->get_param('postType') ?? '');
        if (empty($post_type) || !post_type_exists($post_type)) {
            return new WP_Error('invalid_post_type', "Le post_type est requis et doit exister", ['status' => 400]);
        }

        $args = [
            'post_type' => $post_type,
            'posts_per_page' => min(100, max(1, (int) ($request->get_param('perPage') ?? 20))),
            'paged' => max(1, (int) ($request->get_param('page') ?? 1)),
            'post_status' => $request->get_param('status') ?? 'publish',
            'orderby' => $request->get_param('orderBy') ?? 'date',
            'order' => $request->get_param('order') ?? 'DESC',
        ];

        $search = $request->get_param('search');
        if (!empty($search)) {
            $args['s'] = sanitize_text_field($search);
        }

        $tax_filter = $request->get_param('taxonomyFilter');
        if (is_array($tax_filter) && !empty($tax_filter)) {
            $tax_query = [];
            foreach ($tax_filter as $tax => $term) {
                if (!taxonomy_exists($tax)) continue;
                $tax_query[] = [
                    'taxonomy' => $tax,
                    'field' => is_numeric($term) ? 'term_id' : 'slug',
                    'terms' => $term,
                ];
            }
            if (!empty($tax_query)) {
                $args['tax_query'] = $tax_query;
            }
        }

        $meta_query = $request->get_param('metaQuery');
        if (is_array($meta_query) && !empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }

        $q = new WP_Query($args);
        $items = [];
        foreach ($q->posts as $p) {
            $thumb_id = get_post_thumbnail_id($p->ID);
            $items[] = [
                'id' => $p->ID,
                'title' => $p->post_title,
                'slug' => $p->post_name,
                'status' => $p->post_status,
                'date' => $p->post_date,
                'url' => get_permalink($p->ID),
                'editLink' => admin_url('post.php?post=' . $p->ID . '&action=edit'),
                'excerpt' => $p->post_excerpt,
                'featuredImageId' => $thumb_id ?: null,
                'featuredImageUrl' => $thumb_id ? wp_get_attachment_url($thumb_id) : null,
            ];
        }

        return rest_ensure_response([
            'success' => true,
            'total' => (int) $q->found_posts,
            'page' => (int) $args['paged'],
            'perPage' => (int) $args['posts_per_page'],
            'totalPages' => (int) $q->max_num_pages,
            'items' => $items,
        ]);
    }

    public function api_create_taxonomy_term($request) {
        $taxonomy = sanitize_key($request->get_param('taxonomy') ?? '');
        $name = $request->get_param('name');
        if (empty($taxonomy) || empty($name)) {
            return new WP_Error('missing_params', 'taxonomy et name requis', ['status' => 400]);
        }
        if (!taxonomy_exists($taxonomy)) {
            return new WP_Error('taxonomy_not_found', "Taxonomy '$taxonomy' inexistante", ['status' => 400]);
        }

        $args = [];
        $slug = $request->get_param('slug');
        if (!empty($slug)) $args['slug'] = sanitize_title($slug);
        $description = $request->get_param('description');
        if (!empty($description)) $args['description'] = wp_kses_post($description);
        $parent_id = $request->get_param('parentId');
        if (!empty($parent_id)) $args['parent'] = (int) $parent_id;

        // Idempotent : si déjà existant par slug, retourner son ID
        $check_slug = !empty($slug) ? sanitize_title($slug) : sanitize_title($name);
        $existing = get_term_by('slug', $check_slug, $taxonomy);
        if (!$existing) {
            $existing = get_term_by('name', $name, $taxonomy);
        }
        if ($existing) {
            return rest_ensure_response([
                'success' => true,
                'id' => (int) $existing->term_id,
                'taxonomy' => $taxonomy,
                'name' => $existing->name,
                'slug' => $existing->slug,
                'created' => false,
                'message' => 'Terme déjà existant — retourne son ID.',
            ]);
        }

        $r = wp_insert_term(sanitize_text_field($name), $taxonomy, $args);
        if (is_wp_error($r)) {
            return new WP_Error('insert_failed', $r->get_error_message(), ['status' => 500]);
        }

        $term = get_term($r['term_id'], $taxonomy);
        return rest_ensure_response([
            'success' => true,
            'id' => (int) $r['term_id'],
            'taxonomy' => $taxonomy,
            'name' => $term->name,
            'slug' => $term->slug,
            'created' => true,
        ]);
    }

    // =====================================================
    // v3.9.0 — SKILL VERSIONING
    // =====================================================
    //
    // Lit la version skill depuis le frontmatter de SKILL.md.
    // Compare avec la dernière version qui a été embarquée dans
    // un .plugin téléchargé (stockée en option WP).
    //

    /**
     * Lit la version du skill depuis le frontmatter de SKILL.md.
     * Retourne string (ex: "1.0.0") ou null si introuvable.
     */
    private function _read_skill_version() {
        $skill_md = BRICKS_MCP_PLUGIN_DIR . 'skill/SKILL.md';
        if (!file_exists($skill_md)) {
            return null;
        }
        $content = file_get_contents($skill_md);
        // Parse frontmatter YAML : "version: x.y.z"
        if (preg_match('/^version:\s*([0-9]+\.[0-9]+\.[0-9]+)\s*$/m', $content, $m)) {
            return $m[1];
        }
        return null;
    }

    public function api_skill_version($request) {
        $current = $this->_read_skill_version();
        $last_downloaded = get_option('bricks_mcp_last_downloaded_skill_version', null);

        $local_version = $request->get_param('localVersion');
        $is_outdated = false;
        $update_message = null;

        // Si l'IA fournit sa version locale, c'est la source la plus fiable.
        // lastDownloadedSkillVersion n'est qu'un indicateur admin côté WP.
        if ($local_version && $current) {
            if (version_compare($local_version, $current, '<')) {
                $is_outdated = true;
                $update_message = sprintf(
                    "Ton skill local est en v%s, la version actuelle côté serveur est v%s. Demande à l'utilisateur de re-télécharger le .plugin depuis WP admin → Bricks MCP.",
                    $local_version,
                    $current
                );
            }
        } elseif ($current && $last_downloaded && version_compare($last_downloaded, $current, '<')) {
            $is_outdated = true;
            $update_message = sprintf(
                "Une mise à jour skill est disponible (v%s → v%s). Re-télécharge le .plugin depuis WP admin → Bricks MCP.",
                $last_downloaded,
                $current
            );
        }

        return rest_ensure_response([
            'success' => true,
            'currentSkillVersion' => $current,
            'lastDownloadedSkillVersion' => $last_downloaded,
            'localVersionProvided' => $local_version,
            'isOutdated' => $is_outdated,
            'updateMessage' => $update_message,
            'pluginVersion' => BRICKS_MCP_VERSION,
        ]);
    }

    // =====================================================
    // v3.7.0 — UPLOAD MEDIA BATCH
    // =====================================================
    //
    // Upload de plusieurs images en 1 appel. Continue même si
    // certaines échouent. Retourne {successes: [...], failures: [...]}.
    //
    public function api_upload_media_batch($request) {
        $items = $request->get_param('items');
        if (!is_array($items) || empty($items)) {
            return new WP_Error('missing_items', 'items (array) requis', ['status' => 400]);
        }
        // v3.8.0 — Optimize au niveau du batch (s'applique à tous les items)
        $optimize_batch = (bool) $request->get_param('optimize');

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $successes = [];
        $failures = [];

        foreach ($items as $index => $item) {
            // v3.7.3 — Accepte URL HTTP/HTTPS OU data URI ("data:mime;base64,...")
            $raw_source = (string) ($item['sourceUrl'] ?? '');
            $is_data_uri = stripos($raw_source, 'data:') === 0;
            $source_url = $is_data_uri ? $raw_source : esc_url_raw($raw_source);

            $title_param = $item['title'] ?? null;
            $alt = $item['alt'] ?? null;
            $caption = $item['caption'] ?? null;

            if (empty($source_url)) {
                $failures[] = ['index' => $index, 'error' => 'sourceUrl manquant', 'item' => $item];
                continue;
            }

            $dl = $this->_download_source_to_tmp($source_url);
            if (is_wp_error($dl)) {
                $failures[] = [
                    'index' => $index,
                    'sourceUrl' => $is_data_uri ? '(data URI)' : $source_url,
                    'error' => 'download: ' . $dl->get_error_message()
                ];
                continue;
            }
            $tmp = $dl['tmp'];

            // Détection extension (data URI → mime, sinon URL)
            $url_filename = $is_data_uri ? '' : basename(parse_url($source_url, PHP_URL_PATH));
            $ext = 'jpg';
            if ($is_data_uri && !empty($dl['mime'])) {
                $maybe_ext = $this->_ext_from_mime($dl['mime']);
                if ($maybe_ext) $ext = $maybe_ext;
            } elseif (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|avif|mp4|webm|mov)$/i', $url_filename, $m_ext)) {
                $ext = strtolower($m_ext[1]);
            } elseif (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|avif|mp4|webm|mov)([?#]|$)/i', $source_url, $m_ext)) {
                $ext = strtolower($m_ext[1]);
            }

            if (!empty($title_param)) {
                $filename = sanitize_title($title_param) . '.' . $ext;
            } elseif (!empty($url_filename) && preg_match('/\.(jpg|jpeg|png|gif|webp|svg|avif|mp4|webm|mov)$/i', $url_filename)) {
                $filename = $url_filename;
            } else {
                $filename = 'upload-' . time() . '-' . $index . '.' . $ext;
            }

            $file_array = ['name' => $filename, 'tmp_name' => $tmp];
            $attachment_id = media_handle_sideload($file_array, 0);

            if (is_wp_error($attachment_id)) {
                @unlink($tmp);
                $failures[] = ['index' => $index, 'sourceUrl' => $source_url, 'error' => 'sideload: ' . $attachment_id->get_error_message()];
                continue;
            }

            if (!empty($title_param)) {
                wp_update_post(['ID' => $attachment_id, 'post_title' => sanitize_text_field($title_param)]);
            }
            if (!empty($alt)) {
                update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt));
            }
            if (!empty($caption)) {
                wp_update_post(['ID' => $attachment_id, 'post_excerpt' => sanitize_text_field($caption)]);
            }

            // v3.8.0 — Optimisation WebP par item
            $optimization = null;
            if ($optimize_batch) {
                $optimization = $this->_optimize_attachment_to_webp($attachment_id);
            }

            $successes[] = [
                'index' => $index,
                'id' => $attachment_id,
                'url' => wp_get_attachment_url($attachment_id),
                'filename' => $optimization && !empty($optimization['optimized']) ? $optimization['newFile'] : $filename,
                // v3.7.4 — Ne pas écho le b64 entier des data URIs (économie contexte)
                'sourceUrl' => $is_data_uri ? '(data URI ' . strlen($source_url) . ' chars)' : $source_url,
                'optimization' => $optimization,
            ];
        }

        return rest_ensure_response([
            'success' => true,
            'total' => count($items),
            'uploaded' => count($successes),
            'failed' => count($failures),
            'successes' => $successes,
            'failures' => $failures,
        ]);
    }
}

// Initialiser le plugin
function bricks_mcp_server_init() {
    return BricksMCPServer::get_instance();
}
add_action('plugins_loaded', 'bricks_mcp_server_init');
