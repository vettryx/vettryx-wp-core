<?php
/**
 * Plugin Name: VETTRYX WP Core
 * Plugin URI:  https://github.com/vettryx/vettryx-wp-core
 * Description: Plugin principal da VETTRYX Tech para gerenciar os módulos contratados e garantir a conformidade com a LGPD/GDPR, além de facilitar a manutenção e atualização dos plugins internos.
 * Version:     1.2.0
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
class Vettryx_Core {

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
            80                                                    // Posição no menu
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
        $available_modules = $this->get_available_modules();
        $active_modules    = get_option( $this->option_name, [] );
        ?>
        <div class="wrap">
            <h1><?php _e( 'VETTRYX Tech - Gerenciamento de Ferramentas', 'vettryx-wp-core' ); ?></h1>
            <p><?php _e( 'Ative ou desative os módulos contratados para este site.', 'vettryx-wp-core' ); ?></p>
            
            <form method="post" action="options.php">
                <?php 
                // Segurança do WordPress para formulários
                settings_fields( 'vettryx_modules_group' ); 
                ?>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><?php _e( 'Módulos Disponíveis', 'vettryx-wp-core' ); ?></th>
                            <td>
                                <fieldset>
                                    <?php foreach ( $available_modules as $module ) : ?>
                                        <?php $checked = in_array( $module['path'], $active_modules ) ? 'checked="checked"' : ''; ?>
                                        <label style="display: block; margin-bottom: 10px;">
                                            <input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[]" value="<?php echo esc_attr( $module['path'] ); ?>" <?php echo $checked; ?>>
                                            <strong><?php echo esc_html( $module['name'] ); ?></strong> 
                                            <br><span style="color: #666; font-size: 12px;"><?php _e( 'Caminho:', 'vettryx-wp-core' ); ?> <?php echo esc_html( $module['path'] ); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button( __( 'Salvar Módulos', 'vettryx-wp-core' ) ); ?>
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
new Vettryx_Core();
