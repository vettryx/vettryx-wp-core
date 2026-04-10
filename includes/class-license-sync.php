<?php
/**
 * Classe: License Sync
 * Arquivo: includes/class-license-sync.php
 * Descrição: Gerencia a sincronização de licenças com o VETTRYX Hub.
 * Autor: VETTRYX Tech
 * Data: 2026-04-09
 */

// Segurança: Evita acesso direto ao arquivo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Vettryx_License_Sync {

    // Constantes
    const HUB_API_URL = 'https://hub.vettryx.com.br/api/v1/licenses/sync/';
    const OPTION_LICENSE_KEY = 'vettryx_license_key';
    const OPTION_SYNC_CACHE = 'vettryx_sync_cache';
    const OPTION_SYNC_TIMESTAMP = 'vettryx_sync_timestamp';
    const CRON_HOOK = 'vettryx_sync_license_cron';
    const SYNC_INTERVAL = 12 * HOUR_IN_SECONDS; // 12 horas
    const CACHE_DURATION = 12 * HOUR_IN_SECONDS; // 12 horas

    // Construtor
    public function __construct() {
        // Registra o evento cron para sincronização automática
        add_action( 'init', [ $this, 'schedule_cron' ] );
        add_action( self::CRON_HOOK, [ $this, 'sync_with_hub' ] );

        // AJAX para sincronização manual
        add_action( 'wp_ajax_vettryx_sync_license', [ $this, 'ajax_sync_license' ] );

        // Carrega as permissões após sincronização
        add_action( 'plugins_loaded', [ $this, 'enforce_module_permissions' ] );
    }

    /**
     * Agenda o evento cron para sincronização automática
     */
    public function schedule_cron() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'twicedaily', self::CRON_HOOK );
        }
    }

    /**
     * Sincroniza a licença com o Hub via API
     */
    public function sync_with_hub() {
        $license_key = get_option( self::OPTION_LICENSE_KEY );

        // Se não houver chave de licença, não faz nada
        if ( empty( $license_key ) ) {
            return false;
        }

        // Verifica o cache antes de fazer a requisição
        $cached_data = get_option( self::OPTION_SYNC_CACHE );
        $cache_timestamp = get_option( self::OPTION_SYNC_TIMESTAMP );

        if ( ! empty( $cached_data ) && ! empty( $cache_timestamp ) ) {
            $age = time() - $cache_timestamp;
            if ( $age < self::CACHE_DURATION ) {
                // Cache ainda é válido
                return $cached_data;
            }
        }

        // Faz a requisição ao Hub
        $response = wp_remote_get(
            self::HUB_API_URL,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $license_key,
                    'User-Agent'    => 'VETTRYX-WP-Core/2.12.2',
                ],
                'timeout' => 10,
                'sslverify' => true,
            ]
        );

        // Trata erros de conexão
        if ( is_wp_error( $response ) ) {
            error_log( 'VETTRYX Sync Error: ' . $response->get_error_message() );
            return false;
        }

        // Decodifica a resposta JSON
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        // Trata erros HTTP
        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code !== 200 ) {
            error_log( 'VETTRYX Sync HTTP Error: ' . $status_code );
            return false;
        }

        // Valida a estrutura da resposta
        if ( ! isset( $data['modules_enabled'] ) || ! is_array( $data['modules_enabled'] ) ) {
            error_log( 'VETTRYX Sync: Invalid response structure' );
            return false;
        }

        // Armazena os dados em cache
        update_option( self::OPTION_SYNC_CACHE, $data );
        update_option( self::OPTION_SYNC_TIMESTAMP, time() );

        return $data;
    }

    /**
     * AJAX: Sincronização manual via painel administrativo
     */
    public function ajax_sync_license() {
        check_ajax_referer( 'vettryx_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permissão negada' ] );
        }

        // Limpa o cache para forçar uma sincronização fresca
        delete_option( self::OPTION_SYNC_CACHE );
        delete_option( self::OPTION_SYNC_TIMESTAMP );

        // Executa a sincronização
        $result = $this->sync_with_hub();

        if ( $result === false ) {
            wp_send_json_error( [ 'message' => 'Erro ao sincronizar com o Hub' ] );
        }

        wp_send_json_success( [
            'message' => 'Sincronização concluída com sucesso',
            'data' => $result
        ] );
    }

    /**
     * Obtém os módulos habilitados pela licença
     */
    public function get_enabled_modules() {
        $cache = get_option( self::OPTION_SYNC_CACHE );

        if ( empty( $cache ) || ! isset( $cache['modules_enabled'] ) ) {
            return [];
        }

        return $cache['modules_enabled'];
    }

    /**
     * Verifica se um módulo está habilitado pela licença
     */
    public function is_module_enabled( $module_slug ) {
        $enabled_modules = $this->get_enabled_modules();

        foreach ( $enabled_modules as $module ) {
            if ( isset( $module['slug'] ) && $module['slug'] === $module_slug ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtém os módulos disponíveis no catálogo
     */
    public function get_available_modules() {
        $cache = get_option( self::OPTION_SYNC_CACHE );

        if ( empty( $cache ) || ! isset( $cache['modules_available'] ) ) {
            return [];
        }

        return $cache['modules_available'];
    }

    /**
     * Obtém a informação de status da licença
     */
    public function get_license_status() {
        $cache = get_option( self::OPTION_SYNC_CACHE );

        if ( empty( $cache ) || ! isset( $cache['license'] ) ) {
            return null;
        }

        return $cache['license'];
    }

    /**
     * Salva a chave de licença
     */
    public function save_license_key( $license_key ) {
        $license_key = sanitize_text_field( $license_key );

        // Valida se é um UUID válido
        if ( ! $this->is_valid_uuid( $license_key ) ) {
            return false;
        }

        update_option( self::OPTION_LICENSE_KEY, $license_key );

        // Limpa o cache para forçar sincronização
        delete_option( self::OPTION_SYNC_CACHE );
        delete_option( self::OPTION_SYNC_TIMESTAMP );

        // Executa sincronização imediata
        return $this->sync_with_hub();
    }

    /**
     * Obtém a chave de licença
     */
    public function get_license_key() {
        return get_option( self::OPTION_LICENSE_KEY, '' );
    }

    /**
     * Valida um UUID
     */
    private function is_valid_uuid( $uuid ) {
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        return preg_match( $pattern, $uuid ) === 1;
    }

    /**
     * Aplica as permissões de módulos da licença
     * Desativa módulos que não estão na lista de habilitados
     */
    public function enforce_module_permissions() {
        // Apenas no painel administrativo
        if ( ! is_admin() ) {
            return;
        }

        // Obtém a licença salva
        $license_key = $this->get_license_key();
        if ( empty( $license_key ) ) {
            return;
        }

        // Se não há cache, tenta sincronizar
        $enabled_modules = $this->get_enabled_modules();
        if ( empty( $enabled_modules ) ) {
            $this->sync_with_hub();
            $enabled_modules = $this->get_enabled_modules();
        }

        if ( empty( $enabled_modules ) ) {
            return;
        }

        // Extrai os slugs dos módulos habilitados
        $enabled_slugs = array_column( $enabled_modules, 'slug' );

        // Obtém os módulos ativos localmente
        $active_modules = get_option( 'vettryx_active_modules', [] );

        // Desativa módulos que não estão na lista de habilitados
        $updated_modules = [];
        foreach ( $active_modules as $module_path ) {
            // Extrai o slug do caminho do módulo (ex: modules/essential-seo/vettryx-wp-essential-seo.php)
            $module_slug = $this->extract_module_slug( $module_path );

            if ( in_array( $module_slug, $enabled_slugs, true ) ) {
                $updated_modules[] = $module_path;
            }
        }

        // Atualiza a lista de módulos ativos
        if ( count( $updated_modules ) !== count( $active_modules ) ) {
            update_option( 'vettryx_active_modules', $updated_modules );
        }
    }

    /**
     * Extrai o slug do módulo a partir do caminho do arquivo
     * Mapeia corretamente para os slugs retornados pelo Hub
     * Exemplo: modules/essential-seo/vettryx-wp-essential-seo.php -> vettryx-wp-essential-seo
     */
    private function extract_module_slug( $module_path ) {
        // Extrai o nome do arquivo do módulo (ex: vettryx-wp-essential-seo.php)
        preg_match( '/modules\/[^\/]+\/(vettryx-wp-[^\/]+)\.php$/', $module_path, $matches );
        
        if ( isset( $matches[1] ) ) {
            return $matches[1];
        }
        
        // Fallback: extrai a pasta do módulo para compatibilidade
        preg_match( '/modules\/([^\/]+)\/', $module_path, $matches );
        return isset( $matches[1] ) ? 'vettryx-wp-' . $matches[1] : '';
    }
}
