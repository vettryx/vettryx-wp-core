<?php
/**
 * Plugin Name: VETTRYX WP Core
 * Plugin URI:  https://github.com/vettryx/vettryx-wp-core
 * Description: Plugin principal da VETTRYX Tech para gerenciar os módulos contratados e garantir a conformidade com a LGPD/GDPR, além de facilitar a manutenção e atualização dos plugins internos.
 * Version:     2.1.15
 * Author:      VETTRYX Tech
 * Author URI:  https://vettryx.com.br
 * Text Domain: vettryx-wp-core
 * License:     GPLv3
 */

// Evita acesso direto ao arquivo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Inclui o autoload do Composer para carregar o Plugin Update Checker
class Vettryx_WP_Core {

    // Nome da opção no banco de dados onde os módulos ativos serão salvos
    private $option_name = 'vettryx_active_modules';
    private $modules_dir;

    // Instância do Plugin Update Checker para atualizações automáticas via GitHub
    private $update_checker;

    public function __construct() {
        // Define o caminho para a pasta de módulos, que deve estar dentro do plugin
        $this->modules_dir = plugin_dir_path( __FILE__ ) . 'modules/';

        // 1. Carrega os módulos ativos quando o plugin é carregado
        add_action( 'plugins_loaded', [ $this, 'load_active_modules' ] );

        // 2. Adiciona o menu de administração para gerenciar os módulos e registra a configuração para salvar os módulos ativos
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'save_modules_state' ] );

        // 3. Registra a conformidade com a WP Consent API para garantir que o plugin esteja em conformidade com as leis de privacidade (LGPD/GDPR)
        add_action( 'plugins_loaded', [ $this, 'register_consent_api' ] );

        // 4. Inicializa o Plugin Update Checker para permitir atualizações automáticas do plugin via GitHub, facilitando a manutenção e distribuição de novas versões.
        add_action( 'plugins_loaded', [ $this, 'init_update_checker' ] );

        // 5. Carrega o Design System Global da VETTRYX (Submódulo)
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    /**
     * Carrega o CSS do UI Core e do Dashboard apenas na página do plugin
     */
    public function enqueue_admin_assets( $hook ) {
        if ( strpos( $hook, 'vettryx-core-modules' ) !== false ) {
            
            // Variáveis Globais (Single Source of Truth) vindas da pasta assets
            wp_enqueue_style( 
                'vtx-ui-variables', 
                plugin_dir_url( __FILE__ ) . 'assets/vettryx-ui-core/css/base/variables.css', 
                [], 
                '1.0.0' 
            );

            // CSS Estrutural do Dashboard
            wp_enqueue_style( 
                'vtx-admin-dashboard', 
                plugin_dir_url( __FILE__ ) . 'assets/css/admin-dashboard.css', 
                ['vtx-ui-variables'], 
                '1.4.0' 
            );
        }
    }

    /**
     * Inicializa o Plugin Update Checker (GitHub)
     */
    public function init_update_checker() {

        // Verifica se o arquivo do PUC existe antes de tentar incluí-lo, para evitar erros caso o autoload do Composer não esteja configurado corretamente.
        $puc_file = plugin_dir_path(__FILE__) . 'vendor/plugin-update-checker/plugin-update-checker.php';

        // Se o arquivo do PUC não existir, simplesmente retorna sem inicializar o sistema de atualização, permitindo que o plugin funcione normalmente sem atualizações automáticas.
        if (!file_exists($puc_file)) {
            return;
        }

        // Inclui o arquivo do PUC para ter acesso às suas funcionalidades e classes necessárias para configurar o sistema de atualização automática.
        require_once $puc_file;

        // Configura o PUC para apontar para o repositório correto no GitHub
        $this->update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/vettryx/vettryx-wp-core',
            __FILE__,
            'vettryx-wp-core'
        );

        // Define a branch que o PUC deve monitorar para atualizações (pode ser 'main', 'master' ou qualquer outra)
        $this->update_checker->setBranch('main');

        // Habilita o suporte para arquivos de lançamento (release assets) no GitHub, permitindo que o PUC baixe o .zip do release automaticamente.
        $this->update_checker->getVcsApi()->enableReleaseAssets();

        // Adiciona um filtro para personalizar as informações do plugin exibidas na tela de atualizações, incluindo os ícones personalizados.
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

        // Para cada módulo ativo, inclui o arquivo correspondente para carregar suas funcionalidades. O caminho do módulo é relativo à pasta do plugin, e o arquivo deve existir para ser incluído corretamente.
        foreach ( $active_modules as $module_path ) {
            $full_path = plugin_dir_path( __FILE__ ) . $module_path;
            if ( file_exists( $full_path ) ) {
                require_once $full_path;
            }
        }
    }

    /**
     * Adiciona um item de menu no painel de administração para gerenciar os módulos ativos
     */
    public function add_admin_menu() {

        // Carrega o ícone do menu a partir do arquivo menu-icon.php, que retorna a string base64 do SVG. Isso permite que o menu tenha um ícone personalizado sem depender de arquivos externos.
        $vettryx_icon = require plugin_dir_path( __FILE__ ) . 'includes/menu-icon.php';

        add_menu_page(
            'VETTRYX Tech - Módulos',                             // Título da página
            'VETTRYX Tech',                                       // Nome no menu lateral
            'manage_options',                                     // Capacidade (só admin vê)
            'vettryx-core-modules',                               // Slug da URL
            [ $this, 'render_admin_page' ],                       // Função que desenha a tela
            'data:image/svg+xml;base64,' . $vettryx_icon,         // Ícone
            3                                                     // Posição no menu
        );

        // Renomeia o submenu principal para "Painel"
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
     * Busca os módulos disponíveis na pasta "modules" e retorna um array com nome e caminho de cada um
     */
    private function get_available_modules() {
        $modules = [];
        // Busca todas as pastas dentro da pasta de módulos, cada pasta representa um módulo diferente. O GLOB_ONLYDIR garante que apenas diretórios sejam retornados, ignorando arquivos soltos.
        $dirs = glob( $this->modules_dir . '*', GLOB_ONLYDIR );
        
        if ( ! $dirs ) return $modules;

        foreach ( $dirs as $dir ) {
            // Pega todos os arquivos .php na raiz dessa pasta
            $files = glob( $dir . '/*.php' );
            foreach ( $files as $file ) {
                // Lê as primeiras linhas para achar o nome do plugin
                $content = file_get_contents( $file, false, null, 0, 8192 );
                if ( preg_match( '/^[ \t\/*#@]*Plugin Name:(.*)$/mi', $content, $match ) ) {
                    $modules[] = [
                        'name' => trim( $match[1] ),
                        'path' => str_replace( plugin_dir_path( __FILE__ ), '', $file )
                    ];
                    break; // Se encontrou o nome do plugin, não precisa ler os outros arquivos dessa pasta, já que cada pasta representa um módulo.
                }
            }
        }
        return $modules;
    }

    /**
     * Renderiza a página de administração onde o usuário pode ativar ou desativar os módulos disponíveis
     */
    public function render_admin_page() {
        $active_modules = get_option( $this->option_name, [] ); 
        
        // Mapeamento de descrições e ícones para cada módulo
        $module_info = [
            'modules/cookie-manager/vettryx-wp-cookie-manager.php' => [
                'name' => 'Cookie Manager',
                'desc' => 'Gerenciador de consentimento nativo e gerador de políticas integrado à WP Consent API (LGPD).',
                'icon' => 'dashicons-shield'
            ],
            'modules/fast-gallery/vettryx-wp-fast-gallery.php' => [
                'name' => 'Fast Gallery',
                'desc' => 'Gerenciador simplificado de álbuns de serviços com fotos de Antes e Depois flexíveis.',
                'icon' => 'dashicons-format-gallery'
            ],
            'modules/site-signature/vettryx-wp-site-signature.php' => [
                'name' => 'Site Signature',
                'desc' => 'Personalização white-label do painel administrativo e assinatura da agência no rodapé.',
                'icon' => 'dashicons-admin-customizer'
            ],
            'modules/tracking-manager/vettryx-wp-tracking-manager.php' => [
                'name' => 'Tracking Manager',
                'desc' => 'Injeção otimizada de scripts (GA4, Meta Pixel) com bloqueio nativo pré-consentimento.',
                'icon' => 'dashicons-chart-area'
            ]
        ];

        $available_modules = $this->get_available_modules();
        ?>
        <div class="vtx-dashboard-wrap">
            <div class="vtx-dashboard-header">
                <div>
                    <h1>VETTRYX Tech</h1>
                    <p>Ative e gerencie os módulos do ecossistema para este cliente.</p>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields( 'vettryx_modules_group' ); ?>
                
                <div class="vtx-modules-grid">
                    <?php foreach ( $available_modules as $module ) : ?>
                        <?php 
                            $path = $module['path'];
                            $is_active = in_array( $path, $active_modules ); 
                            
                            $name = isset($module_info[$path]) ? $module_info[$path]['name'] : $module['name'];
                            $desc = isset($module_info[$path]) ? $module_info[$path]['desc'] : 'Módulo do ecossistema VETTRYX.';
                            $icon = isset($module_info[$path]) ? $module_info[$path]['icon'] : 'dashicons-admin-plugins';
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
                                <?php if ($is_active) : ?>
                                    <span style="font-size: 12px; color: #00a32a; font-weight: 600;">ATIVO</span>
                                <?php else : ?>
                                    <span style="font-size: 12px; color: #8c8f94; font-weight: 600;">INATIVO</span>
                                <?php endif; ?>
                                
                                <label class="vtx-toggle">
                                    <input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[]" value="<?php echo esc_attr( $path ); ?>" <?php checked( $is_active, true ); ?>>
                                    <span class="vtx-toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 20px; text-align: right;">
                    <button type="submit" class="vtx-save-btn">Salvar Alterações</button>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Registra a configuração para salvar os módulos ativos no banco de dados (wp_options)
     */
    public function save_modules_state() {
        register_setting( 'vettryx_modules_group', $this->option_name, [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize_modules_array' ]
        ] );
    }

    /**
     * Função de sanitização para o array de módulos ativos, garantindo que apenas strings sejam salvas e evitando possíveis problemas de segurança
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
