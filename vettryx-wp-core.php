<?php
/**
 * Plugin Name: VETTRYX WP Core
 * Plugin URI:  https://github.com/vettryx/vettryx-wp-core
 * Description: Plugin principal da VETTRYX Tech para gerenciar os módulos contratados e garantir a conformidade com a LGPD/GDPR, além de facilitar a manutenção e atualização dos plugins internos.
 * Version:     2.8.5
 * Author:      VETTRYX Tech
 * Author URI:  https://vettryx.com.br
 * Text Domain: vettryx-wp-core
 * License:     Proprietária (Uso Comercial Exclusivo)
 */

// Segurança: Evita acesso direto ao arquivo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Classe principal do plugin
class Vettryx_WP_Core {

    // Nome da opção no banco de dados onde os módulos ativos serão salvos
    private $option_name = 'vettryx_active_modules';
    private $modules_dir;

    // Instância do Plugin Update Checker para atualizações automáticas via GitHub
    private $update_checker;

    // Construtor
    public function __construct() {
        // Define o caminho para a pasta de módulos, que deve estar dentro do plugin
        $this->modules_dir = plugin_dir_path( __FILE__ ) . 'modules/';

        // 1. Carrega os módulos ativos quando o plugin é carregado
        add_action( 'plugins_loaded', [ $this, 'load_active_modules' ] );

        // 2. Adiciona o menu de administração para gerenciar os módulos e registra a configuração para salvar os módulos ativos
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'save_modules_state' ] );

        // 3. Registra a conformidade com a WP Consent API (LGPD/GDPR)
        add_action( 'plugins_loaded', [ $this, 'register_consent_api' ] );

        // 4. Inicializa o Plugin Update Checker via GitHub
        add_action( 'plugins_loaded', [ $this, 'init_update_checker' ] );

        // 5. Carrega o Design System Global da VETTRYX
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // 6. Rotas AJAX para o painel dinâmico
        add_action( 'wp_ajax_vettryx_toggle_module', [ $this, 'ajax_toggle_module' ] );
        add_action( 'wp_ajax_vettryx_check_updates', [ $this, 'ajax_check_updates' ] );
    }

    /**
     * Carrega o CSS do UI Core e do Dashboard apenas na página do plugin
     */
    public function enqueue_admin_assets( $hook ) {
        if ( strpos( $hook, 'vettryx-core-modules' ) !== false ) {
            
            // 1. Carrega o CSS do UI Core e do Dashboard apenas na página do plugin
            wp_enqueue_style( 
                'vtx-ui-variables', 
                plugin_dir_url( __FILE__ ) . 'assets/vettryx-ui-core/css/base/variables.css', 
                [], 
                '1.0.0' 
            );

            // 2. Carrega o CSS do Dashboard
            wp_enqueue_style( 
                'vtx-admin-dashboard', 
                plugin_dir_url( __FILE__ ) . 'assets/css/admin-dashboard.css', 
                ['vtx-ui-variables'], 
                '1.4.0' 
            );

            // 3. Script do Dashboard (AJAX)
            wp_enqueue_script( 
                'vtx-admin-js', 
                plugin_dir_url( __FILE__ ) . 'assets/js/admin-dashboard.js', 
                ['jquery'], 
                '1.0.0', 
                true 
            );

            // 4. Injeta o URL do admin-ajax.php e o Nonce de segurança para o JS
            wp_localize_script( 'vtx-admin-js', 'vtxCore', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'vettryx_admin_nonce' )
            ]);
        }
    }

    /**
     * Inicializa o Plugin Update Checker via API Proprietária da VETTRYX
     */
    public function init_update_checker() {
        $puc_file = plugin_dir_path(__FILE__) . 'vendor/plugin-update-checker/plugin-update-checker.php';

        if (!file_exists($puc_file)) {
            return;
        }

        require_once $puc_file;

        // Aponta para o servidor proxy da VETTRYX em vez do GitHub direto
        $this->update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://api.vettryx.com.br/?action=get_metadata&slug=vettryx-wp-core',
            __FILE__,
            'vettryx-wp-core'
        );

        // Mantém os ícones personalizados na tela de atualização do WordPress
        $this->update_checker->addResultFilter(function ($info) {
            $info->icons = [
                '1x' => plugin_dir_url(__FILE__) . 'assets/icon-128x128.png',
                '2x' => plugin_dir_url(__FILE__) . 'assets/icon-256x256.png',
            ];
            return $info;
        });
    }

    /**
     * Declaração de conformidade com a WP Consent API (LGPD/GDPR)
     */
    public function register_consent_api() {
        $plugin_slug = plugin_basename( __FILE__ );
        add_filter( "wp_consent_api_registered_{$plugin_slug}", '__return_true' );
    }

    /**
     * Carrega os módulos ativos listados no banco de dados (wp_options)
     */
    public function load_active_modules() {
        $active_modules = get_option( $this->option_name, [] );

        foreach ( $active_modules as $module_path ) {
            $full_path = plugin_dir_path( __FILE__ ) . $module_path;
            if ( file_exists( $full_path ) ) {
                require_once $full_path;
            }
        }
    }

    /**
     * Adiciona o menu no painel de administração (wp_menu)
     */
    public function add_admin_menu() {
        $vettryx_icon = require plugin_dir_path( __FILE__ ) . 'includes/menu-icon.php';

        add_menu_page(
            'VETTRYX Tech - Módulos',
            'VETTRYX Tech',
            'manage_options',
            'vettryx-core-modules',
            [ $this, 'render_admin_page' ],
            'data:image/svg+xml;base64,' . $vettryx_icon,
            3
        );

        add_submenu_page(
            'vettryx-core-modules',
            'Painel Geral',
            'Painel',
            'manage_options',
            'vettryx-core-modules',
            [ $this, 'render_admin_page' ]
        );
    }

    /**
     * Busca os módulos disponíveis lendo os cabeçalhos dinamicamente (wp_file_data)
     */
    private function get_available_modules() {
        $modules = [];
        $dirs = glob( $this->modules_dir . '*', GLOB_ONLYDIR );
        
        if ( ! $dirs ) return $modules;

        foreach ( $dirs as $dir ) {
            $files = glob( $dir . '/*.php' );
            foreach ( $files as $file ) {
                
                // O WP lê o arquivo e extrai os cabeçalhos que pedimos
                $plugin_data = get_file_data( $file, [
                    'Name'        => 'Plugin Name',
                    'Description' => 'Description',
                    'Icon'        => 'Vettryx Icon'
                ] );

                // Se encontrou o 'Name', sabemos que é o arquivo principal do módulo
                if ( ! empty( $plugin_data['Name'] ) ) {
                    $modules[] = [
                        'name' => $plugin_data['Name'],
                        'desc' => $plugin_data['Description'],
                        'icon' => ! empty( $plugin_data['Icon'] ) ? $plugin_data['Icon'] : 'dashicons-admin-plugins',
                        'path' => str_replace( plugin_dir_path( __FILE__ ), '', $file )
                    ];
                    break;
                }
            }
        }
        return $modules;
    }

    /**
     * AJAX: Salva o estado do módulo instantaneamente
     */
    public function ajax_toggle_module() {
        check_ajax_referer( 'vettryx_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

        $module_path = sanitize_text_field( $_POST['module'] );
        $is_active = filter_var( $_POST['active'], FILTER_VALIDATE_BOOLEAN );

        $active_modules = get_option( $this->option_name, [] );

        if ( $is_active ) {
            if ( ! in_array( $module_path, $active_modules ) ) {
                $active_modules[] = $module_path;
            }
        } else {
            $active_modules = array_diff( $active_modules, [ $module_path ] );
        }

        update_option( $this->option_name, $active_modules );
        wp_send_json_success();
    }

    /**
     * AJAX: Força a verificação de atualizações no GitHub
     */
    public function ajax_check_updates() {
        check_ajax_referer( 'vettryx_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

        if ( $this->update_checker ) {
            $this->update_checker->checkForUpdates();
            wp_send_json_success();
        }
        wp_send_json_error();
    }

    /**
     * Renderiza a página de administração de módulos (wp_admin_page)
     */
    public function render_admin_page() {
        $active_modules = get_option( $this->option_name, [] ); 
        $available_modules = $this->get_available_modules();
        ?>
        <div class="vtx-dashboard-wrap">
            <div class="vtx-dashboard-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>VETTRYX Tech</h1>
                    <p>Ative e gerencie os módulos do ecossistema para este cliente.</p>
                </div>
                <div>
                    <button type="button" id="vtx-check-updates-btn" class="button button-secondary">
                        <span class="dashicons dashicons-update" style="margin-top: 3px;"></span> Verificar Atualizações
                    </button>
                </div>
            </div>
                
            <div class="vtx-modules-grid">
                <?php foreach ( $available_modules as $module ) : ?>
                    <?php 
                        $path = $module['path'];
                        $is_active = in_array( $path, $active_modules ); 
                        $name = $module['name'];
                        $desc = $module['desc'];
                        $icon = $module['icon'];
                    ?>
                    
                    <div class="vtx-module-card">
                        <div class="vtx-card-body">
                            <div class="vtx-card-icon">
                                <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                            </div>
                            <h3 class="vtx-card-title"><?php echo esc_html( $name ); ?></h3>
                            <p class="vtx-card-desc"><?php echo esc_html( $desc ); ?></p>
                        </div>
                        
                        <div class="vtx-card-footer">
                            <span class="vtx-status-label" style="font-size: 12px; font-weight: 600; color: <?php echo $is_active ? '#00a32a' : '#8c8f94'; ?>;">
                                <?php echo $is_active ? 'ATIVO' : 'INATIVO'; ?>
                            </span>
                            
                            <label class="vtx-toggle">
                                <input type="checkbox" class="vtx-module-checkbox" data-module="<?php echo esc_attr( $path ); ?>" <?php checked( $is_active, true ); ?>>
                                <span class="vtx-toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Registra a configuração no banco de dados (wp_options)
     */
    public function save_modules_state() {
        register_setting( 'vettryx_modules_group', $this->option_name, [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize_modules_array' ]
        ] );
    }

    /**
     * Função de sanitização (sanitize_text_field)
     */
    public function sanitize_modules_array( $input ) {
        if ( ! is_array( $input ) ) {
            return [];
        }
        return array_map( 'sanitize_text_field', $input );
    }
}

// Inicializa o plugin
new Vettryx_WP_Core();
