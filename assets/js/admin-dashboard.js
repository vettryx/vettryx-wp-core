/**
 * Arquivo: assets/js/admin-dashboard.js
 * Descrição: Script para o painel de administração de módulos do VETTRYX WP Core.
 * Autor: VETTRYX Tech
 * Data: 2026-03-14
 */

jQuery(document).ready(function($) {

    // 1. Lógica do Toggle Instantâneo (Rank Math Style)
    $('.vtx-module-checkbox').on('change', function() {
        let checkbox = $(this);
        let modulePath = checkbox.data('module');
        let isActive = checkbox.is(':checked');
        let statusLabel = checkbox.closest('.vtx-card-footer').find('.vtx-status-label');

        // Feedback visual imediato
        if(isActive) {
            statusLabel.text('ATIVO').css('color', '#00a32a');
        } else {
            statusLabel.text('INATIVO').css('color', '#8c8f94');
        }

        // Envia para o banco de dados via AJAX
        $.ajax({
            url: vtxCore.ajax_url,
            type: 'POST',
            data: {
                action: 'vettryx_toggle_module',
                nonce: vtxCore.nonce,
                module: modulePath,
                active: isActive
            },
            success: function(response) {
                if(!response.success) {
                    let errorMsg = response.data && response.data.message 
                        ? response.data.message 
                        : 'Erro de permissão ao salvar módulo.';
                    alert(errorMsg);
                    checkbox.prop('checked', !isActive); // Reverte o visual em caso de erro
                }
            },
            error: function() {
                alert('Erro de conexão ao salvar módulo.');
                checkbox.prop('checked', !isActive);
            }
        });
    });

    // 2. Lógica do Botão de Atualização
    $('#vtx-check-updates-btn').on('click', function(e) {
        e.preventDefault();
        let btn = $(this);
        let originalText = btn.html();

        btn.html('<span class="dashicons dashicons-update" style="margin-top: 3px;"></span> Verificando...').prop('disabled', true);

        $.ajax({
            url: vtxCore.ajax_url,
            type: 'POST',
            data: {
                action: 'vettryx_check_updates',
                nonce: vtxCore.nonce
            },
            success: function(response) {
                if(response.success) {
                    btn.html('<span class="dashicons dashicons-yes-alt" style="margin-top: 3px; color: #00a32a;"></span> Verificação Concluída');
                    // Redireciona para a tela de atualizações do WP após 2 segundos
                    setTimeout(function() {
                        window.location.href = 'update-core.php';
                    }, 2000);
                } else {
                    btn.html('Erro ao verificar').prop('disabled', false);
                }
            }
        });
    });

    // 3. Lógica do Botão de Sincronização de Licença
    $('#vtx-sync-license-btn').on('click', function(e) {
        e.preventDefault();
        let btn = $(this);
        let originalText = btn.html();

        btn.html('<span class="dashicons dashicons-update" style="margin-top: 3px;"></span> Sincronizando...').prop('disabled', true);

        $.ajax({
            url: vtxCore.ajax_url,
            type: 'POST',
            data: {
                action: 'vettryx_sync_license',
                nonce: vtxCore.nonce
            },
            success: function(response) {
                if(response.success) {
                    btn.html('<span class="dashicons dashicons-yes-alt" style="margin-top: 3px; color: #00a32a;"></span> Sincronizado!');
                    // Recarrega a página após 2 segundos
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    btn.html('Erro ao sincronizar').prop('disabled', false);
                    alert('Erro: ' + response.data.message);
                }
            },
            error: function() {
                btn.html('Erro de conexão').prop('disabled', false);
                alert('Erro de conexão ao sincronizar a licença.');
            }
        });
    });

});
