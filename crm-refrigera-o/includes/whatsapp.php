<?php
/**
 * Refrigeração CRM — WhatsApp
 *
 * @package Refrigeracao_CRM
 * @version 3.5.0
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
   CONSTANTES
   ============================================================ */
if (!defined('RC_CRM_TIPOS_WHATSAPP')) {
    define('RC_CRM_TIPOS_WHATSAPP', ['agendado', 'lembrete', 'concluido']);
}

/* ============================================================
   HELPER: normalizar número de telefone para formato wa.me
   ============================================================ */
if (!function_exists('rc_crm_normalizar_numero')) {
    /**
     * Normaliza um número para formato internacional (sem +).
     * - Se tiver 9 dígitos (formato PT) → adiciona 351
     * - Se já tiver prefixo internacional → mantém
     * - Remove tudo o que não seja dígito
     */
    function rc_crm_normalizar_numero($numero) {
        $limpo = preg_replace('/\D/', '', (string) $numero);

        if (empty($limpo)) return '';

        // 9 dígitos = número PT sem indicativo
        if (strlen($limpo) === 9) {
            return '351' . $limpo;
        }

        // 12 dígitos começados por 351 = número PT com indicativo
        if (strlen($limpo) === 12 && substr($limpo, 0, 3) === '351') {
            return $limpo;
        }

        // Outros casos: devolve como está (assume que já tem indicativo)
        return $limpo;
    }
}

/* ============================================================
   OBTER NÚMERO DA EMPRESA
   ============================================================ */
function rc_crm_obter_numero_whatsapp() {
    $config = rc_crm_get_config();
    $numero = !empty($config['whatsapp_numero']) ? $config['whatsapp_numero'] : ($config['empresa_telefone'] ?? '');

    return rc_crm_normalizar_numero($numero);
}

/* ============================================================
   CONSTRUIR URL wa.me
   ============================================================ */
function rc_crm_construir_whatsapp_url($numero_cliente, $mensagem) {
    $numero_limpo = rc_crm_normalizar_numero($numero_cliente);
    if (empty($numero_limpo)) return '';

    return 'https://wa.me/' . $numero_limpo . '?text=' . rawurlencode($mensagem);
}

/* ============================================================
   OBTER MENSAGEM (com variáveis substituídas)
   ============================================================ */
function rc_crm_obter_mensagem_whatsapp($tipo, $servico_id) {
    // Whitelist do tipo
    if (!in_array($tipo, RC_CRM_TIPOS_WHATSAPP, true)) {
        return '';
    }

    $config = rc_crm_get_config();

    global $wpdb;
    $servico = $wpdb->get_row($wpdb->prepare(
        "SELECT s.*, c.nome AS cliente_nome, c.telefone AS cliente_telefone, c.codigo AS cliente_codigo
         FROM {$wpdb->prefix}rc_servicos s
         LEFT JOIN {$wpdb->prefix}rc_clientes c ON s.cliente_id = c.id
         WHERE s.id = %d",
        $servico_id
    ));
    if (!$servico) return '';

    $chave    = 'whatsapp_' . $tipo . '_msg';
    $template = $config[$chave] ?? '';

    if (empty($template)) {
        $defaults = rc_crm_defaults_empresa();
        $template = $defaults[$chave] ?? '';
    }

    if (empty($template)) return '';

    $substituicoes = [
        '{nome_cliente}'     => $servico->cliente_nome ?? '',
        '{codigo_cliente}'   => $servico->cliente_codigo ?? '',
        '{equipamento}'      => $servico->equipamento ?? '',
        '{data}'             => !empty($servico->data_agendada)
            ? date_i18n('d/m/Y \à\s H:i', strtotime($servico->data_agendada))
            : '(data a confirmar)',
        '{valor}'            => !empty($servico->orcamento)
            ? number_format((float) $servico->orcamento, 2, ',', '.') . ' €'
            : '(a definir)',
        '{empresa_nome}'     => $config['empresa_nome'] ?? '',
        '{empresa_telefone}' => $config['empresa_telefone'] ?? '',
    ];

    return str_replace(array_keys($substituicoes), array_values($substituicoes), $template);
}

/* ============================================================
   LINK WHATSAPP PARA UM SERVIÇO
   ============================================================ */
function rc_crm_link_whatsapp_servico($servico_id, $tipo = 'agendado', $cliente_telefone = '') {
    global $wpdb;

    if (empty($cliente_telefone)) {
        $cliente_telefone = $wpdb->get_var($wpdb->prepare(
            "SELECT c.telefone FROM {$wpdb->prefix}rc_servicos s
             LEFT JOIN {$wpdb->prefix}rc_clientes c ON s.cliente_id = c.id
             WHERE s.id = %d",
            $servico_id
        ));
    }

    if (empty($cliente_telefone)) return '';

    $mensagem = rc_crm_obter_mensagem_whatsapp($tipo, $servico_id);
    if (empty($mensagem)) return '';

    return rc_crm_construir_whatsapp_url($cliente_telefone, $mensagem);
}

/* ============================================================
   BOTÃO DROPDOWN (na listagem de serviços)
   ============================================================ */
function rc_crm_botao_whatsapp_dropdown($servico_id, $cliente_telefone) {
    if (empty($cliente_telefone)) return '';

    $tipos = [
        'agendado'  => '📅 Confirmação',
        'lembrete'  => '🔔 Lembrete',
        'concluido' => '✅ Agradecimento',
    ];

    // Pré-calcular URLs e filtrar os vazios
    $links = [];
    foreach ($tipos as $tipo => $label) {
        $url = rc_crm_link_whatsapp_servico($servico_id, $tipo, $cliente_telefone);
        if (!empty($url)) {
            $links[$tipo] = ['url' => $url, 'label' => $label];
        }
    }

    if (empty($links)) return '';

    $html  = '<span class="rc-wa-wrapper" style="position:relative; display:inline-block;">';
    $html .= '<button type="button" class="rc-wa-toggle" aria-haspopup="true" aria-expanded="false" style="background:#25d366; color:#fff; border:none; padding:4px 10px; border-radius:5px; font-size:11px; font-weight:600; cursor:pointer;">💬 WhatsApp ▾</button>';
    $html .= '<span class="rc-wa-menu" role="menu" style="display:none; position:absolute; top:100%; right:0; margin-top:4px; background:#fff; border:1px solid #ddd; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,0.15); z-index:99; min-width:180px; padding:4px 0;">';

    foreach ($links as $tipo => $info) {
        $html .= '<a href="' . esc_url($info['url']) . '" target="_blank" rel="noopener" role="menuitem" class="rc-wa-item" style="display:block; padding:8px 14px; color:#333; text-decoration:none; font-size:13px; white-space:nowrap;">' . esc_html($info['label']) . '</a>';
    }

    $html .= '</span></span>';
    return $html;
}

/* ============================================================
   CSS + JS DO DROPDOWN (apenas nas páginas do CRM)
   ============================================================ */
add_action('admin_footer', 'rc_crm_whatsapp_assets');
function rc_crm_whatsapp_assets() {
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, 'rc-crm') === false) return;
    ?>
    <style>
    .rc-wa-item:hover,
    .rc-wa-item:focus { background: #f0f0f0; outline: none; }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.rc-wa-toggle').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const menu = this.nextElementSibling;
                const estaAberto = menu.style.display === 'block';

                // Fechar todos
                document.querySelectorAll('.rc-wa-menu').forEach(function(m) {
                    m.style.display = 'none';
                });
                document.querySelectorAll('.rc-wa-toggle').forEach(function(b) {
                    b.setAttribute('aria-expanded', 'false');
                });

                menu.style.display = estaAberto ? 'none' : 'block';
                this.setAttribute('aria-expanded', estaAberto ? 'false' : 'true');
            });
        });

        document.addEventListener('click', function() {
            document.querySelectorAll('.rc-wa-menu').forEach(function(m) {
                m.style.display = 'none';
            });
            document.querySelectorAll('.rc-wa-toggle').forEach(function(b) {
                b.setAttribute('aria-expanded', 'false');
            });
        });
    });
    </script>
    <?php
}