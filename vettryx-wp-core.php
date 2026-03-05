<?php
/**
 * Plugin Name: VETTRYX WP Core
 * Plugin URI: https://github.com/vettryx/vettryx-wp-core
 * Description: Sistema central de ferramentas e módulos da VETTRYX Tech.
 * Version: 1.0.0
 * Author: VETTRYX Tech
 * Author URI:  https://vettryx.com.br
 * Text Domain: vettryx-wp-core
 * License:     GPLv3
 */

// Segurança: Impede o acesso direto ao arquivo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Vettryx_Core {

    // Nome da chave que vai salvar os dados no banco (wp_options)
    private $option_name = 'vettryx_active_modules';
    private $modules_dir;

    public function __construct() {
        // Define o caminho absoluto da pasta modules
        $this->modules_dir = plugin_dir_path( __FILE__ ) . 'modules/';

        // 1. Carrega os módulos ativos assim que os plugins são iniciados
        add_action( 'plugins_loaded', [ $this, 'load_active_modules' ] );

        // 2. Hooks para criar o menu no painel de administração
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'save_modules_state' ] );
    }

    /**
     * Dá o require_once APENAS nos módulos que o cliente ativou
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
     * Cria o menu lateral "VETTRYX Tech" no WordPress
     */
    public function add_admin_menu() {
        add_menu_page(
            'VETTRYX Tech - Módulos',      // Título da página
            'VETTRYX Tech',                // Nome no menu lateral
            'manage_options',              // Capacidade (só admin vê)
            'vettryx-core-modules',        // Slug da URL
            [ $this, 'render_admin_page' ],// Função que desenha a tela
            'dashicons-superhero',         // Ícone bonitão
            80                             // Posição no menu
        );
    }

    /**
     * Varre a pasta /modules/ e encontra os plugins lá dentro
     */
    private function get_available_modules() {
        $modules = [];
        // Pega todas as pastas dentro de modules/
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
                    break; // Achou o arquivo principal, vai pro próximo diretório
                }
            }
        }
        return $modules;
    }

    /**
     * Desenha a interface do painel (HTML puro)
     */
    public function render_admin_page() {
        $available_modules = $this->get_available_modules();
        $active_modules    = get_option( $this->option_name, [] );
        ?>
        <div class="wrap">
            <h1>VETTRYX Tech - Gerenciamento de Ferramentas</h1>
            <p>Ative ou desative os módulos contratados para este site.</p>
            
            <form method="post" action="options.php">
                <?php 
                // Segurança do WordPress para formulários
                settings_fields( 'vettryx_modules_group' ); 
                ?>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">Módulos Disponíveis</th>
                            <td>
                                <fieldset>
                                    <?php foreach ( $available_modules as $module ) : ?>
                                        <?php $checked = in_array( $module['path'], $active_modules ) ? 'checked="checked"' : ''; ?>
                                        <label style="display: block; margin-bottom: 10px;">
                                            <input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[]" value="<?php echo esc_attr( $module['path'] ); ?>" <?php echo $checked; ?>>
                                            <strong><?php echo esc_html( $module['name'] ); ?></strong> 
                                            <br><span style="color: #666; font-size: 12px;">Caminho: <?php echo esc_html( $module['path'] ); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button( 'Salvar Módulos' ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Registra a variável no banco de dados para o formulário funcionar
     */
    public function save_modules_state() {
        register_setting( 'vettryx_modules_group', $this->option_name, [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize_modules_array' ]
        ] );
    }

    /**
     * Limpa os dados antes de salvar (Segurança contra injeção de código)
     */
    public function sanitize_modules_array( $input ) {
        if ( ! is_array( $input ) ) {
            return [];
        }
        return array_map( 'sanitize_text_field', $input );
    }
}

// Inicia a máquina
new Vettryx_Core();
