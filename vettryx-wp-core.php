<?php
/**
 * Plugin Name: VETTRYX WP Core
 * Plugin URI:  https://github.com/vettryx/vettryx-wp-core
 * Description: Sistema central de ferramentas e módulos da VETTRYX Tech.
 * Version:     1.0.2
 * Author:      VETTRYX Tech
 * Author URI:  https://vettryx.com.br
 * Text Domain: vettryx-wp-core
 * License:     GPLv3
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Vettryx_Core {

    private $option_name = 'vettryx_active_modules';
    private $modules_dir;

    // CORREÇÃO DO PHP 8.2+
    private $update_checker = null;

    public function __construct() {

        $this->modules_dir = plugin_dir_path( __FILE__ ) . 'modules/';

        add_action( 'plugins_loaded', [ $this, 'load_active_modules' ] );

        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'save_modules_state' ] );

        add_action( 'plugins_loaded', [ $this, 'register_consent_api' ] );

        add_action( 'plugins_loaded', [ $this, 'init_update_checker' ] );
    }

    /**
     * Inicializa o Plugin Update Checker (GitHub)
     */
    public function init_update_checker() {

        $puc_file = plugin_dir_path(__FILE__) . 'vendor/plugin-update-checker/plugin-update-checker.php';

        if (!file_exists($puc_file)) {
            return;
        }

        require_once $puc_file;

        $this->update_checker =
            \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
                'https://github.com/vettryx/vettryx-wp-core',
                __FILE__,
                'vettryx-wp-core'
            );

        // $this->update_checker->setAuthentication('SEU_TOKEN_AQUI');

        $this->update_checker->getVcsApi()->enableReleaseAssets();

        $this->update_checker->addFilter('github_release_asset', function($asset, $release){
            if (isset($asset->name) && $asset->name === 'vettryx-wp-core.zip') {
                return $asset;
            }
            return false;
        });

        $this->update_checker->addResultFilter(function ($info) {

            $info->icons = [
                '1x' => plugin_dir_url(__FILE__) . 'assets/icon-128x128.png',
                '2x' => plugin_dir_url(__FILE__) . 'assets/icon-256x256.png',
            ];

            return $info;
        });
    }

    /**
     * Declaração de conformidade com a WP Consent API
     */
    public function register_consent_api() {
        $plugin_slug = plugin_basename( __FILE__ );
        add_filter( "wp_consent_api_registered_{$plugin_slug}", '__return_true' );
    }

    /**
     * Carrega apenas módulos ativos
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
     * Menu admin
     */
    public function add_admin_menu() {

        $icon_path = plugin_dir_path( __FILE__ ) . 'includes/menu-icon.php';

        $vettryx_icon = '';

        if (file_exists($icon_path)) {
            $vettryx_icon = require $icon_path;
        }

        add_menu_page(
            'VETTRYX Tech - Módulos',
            'VETTRYX Tech',
            'manage_options',
            'vettryx-core-modules',
            [ $this, 'render_admin_page' ],
            $vettryx_icon ? 'data:image/svg+xml;base64,' . $vettryx_icon : 'dashicons-admin-generic',
            80
        );
    }

    /**
     * Detecta módulos disponíveis
     */
    private function get_available_modules() {

        $modules = [];

        $dirs = glob( $this->modules_dir . '*', GLOB_ONLYDIR );

        if ( ! $dirs ) {
            return $modules;
        }

        foreach ( $dirs as $dir ) {

            $files = glob( $dir . '/*.php' );

            foreach ( $files as $file ) {

                $content = file_get_contents( $file, false, null, 0, 8192 );

                if ( preg_match( '/^[ \t\/*#@]*Plugin Name:(.*)$/mi', $content, $match ) ) {

                    $modules[] = [
                        'name' => trim( $match[1] ),
                        'path' => str_replace( plugin_dir_path( __FILE__ ), '', $file )
                    ];

                    break;
                }
            }
        }

        return $modules;
    }

    /**
     * Tela admin
     */
    public function render_admin_page() {

        $available_modules = $this->get_available_modules();
        $active_modules    = get_option( $this->option_name, [] );
        ?>

        <div class="wrap">

            <h1><?php _e( 'VETTRYX Tech - Gerenciamento de Ferramentas', 'vettryx-wp-core' ); ?></h1>

            <p><?php _e( 'Ative ou desative os módulos contratados para este site.', 'vettryx-wp-core' ); ?></p>

            <form method="post" action="options.php">

                <?php settings_fields( 'vettryx_modules_group' ); ?>

                <table class="form-table">
                    <tbody>

                        <tr>

                            <th scope="row"><?php _e( 'Módulos Disponíveis', 'vettryx-wp-core' ); ?></th>

                            <td>

                                <fieldset>

                                    <?php foreach ( $available_modules as $module ) : ?>

                                        <?php $checked = in_array( $module['path'], $active_modules ) ? 'checked="checked"' : ''; ?>

                                        <label style="display:block;margin-bottom:10px;">

                                            <input
                                                type="checkbox"
                                                name="<?php echo esc_attr( $this->option_name ); ?>[]"
                                                value="<?php echo esc_attr( $module['path'] ); ?>"
                                                <?php echo $checked; ?>
                                            >

                                            <strong><?php echo esc_html( $module['name'] ); ?></strong>

                                            <br>

                                            <span style="color:#666;font-size:12px;">
                                                Caminho: <?php echo esc_html( $module['path'] ); ?>
                                            </span>

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
     * Registra opção
     */
    public function save_modules_state() {

        register_setting(
            'vettryx_modules_group',
            $this->option_name,
            [
                'type'              => 'array',
                'sanitize_callback' => [ $this, 'sanitize_modules_array' ]
            ]
        );
    }

    /**
     * Sanitização
     */
    public function sanitize_modules_array( $input ) {

        if ( ! is_array( $input ) ) {
            return [];
        }

        return array_map( 'sanitize_text_field', $input );
    }
}

new Vettryx_Core();
