<?php
/**
 * Refrigeração CRM — Notificações por Email
 *
 * @package Refrigeracao_CRM
 * @version 3.5.0
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
   CONSTANTES
   ============================================================ */
if (!defined('RC_CRM_TIPOS_NOTIF')) {
    define('RC_CRM_TIPOS_NOTIF', ['agendado', 'lembrete', 'concluido']);
}

/* ============================================================
   TAGS HTML PERMITIDAS NOS EMAILS
   ============================================================ */
function rc_crm_tags_permitidas() {
    return [
        'p' => ['style' => true, 'class' => true],
        'br' => [],
        'strong' => ['style' => true],
        'b' => ['style' => true],
        'em' => ['style' => true],
        'i' => ['style' => true],
        'u' => ['style' => true],
        'a' => ['href' => true, 'target' => true, 'style' => true, 'class' => true, 'rel' => true],
        'ul' => ['style' => true, 'class' => true],
        'ol' => ['style' => true, 'class' => true],
        'li' => ['style' => true],
        'h1' => ['style' => true],
        'h2' => ['style' => true],
        'h3' => ['style' => true],
        'h4' => ['style' => true],
        'blockquote' => ['style' => true],
        'table' => ['style' => true, 'class' => true, 'border' => true, 'cellpadding' => true, 'cellspacing' => true, 'width' => true, 'role' => true],
        'thead' => ['style' => true],
        'tbody' => ['style' => true],
        'tr' => ['style' => true],
        'td' => ['style' => true, 'colspan' => true, 'rowspan' => true, 'align' => true],
        'th' => ['style' => true, 'colspan' => true, 'rowspan' => true, 'align' => true],
        'hr' => ['style' => true],
        'span' => ['style' => true, 'class' => true],
        'div' => ['style' => true, 'class' => true],
        'img' => ['src' => true, 'alt' => true, 'width' => true, 'height' => true, 'style' => true, 'class' => true],
    ];
}

/* ============================================================
   RENDER: substituir variáveis {…}
   ============================================================ */
function rc_crm_render_variaveis($texto, $servico, $cliente) {
    $config = rc_crm_get_config();

    $substituicoes = [
        '{nome_cliente}'     => $cliente->nome ?? '',
        '{email_cliente}'    => $cliente->email ?? '',
        '{telefone_cliente}' => $cliente->telefone ?? '',
        '{codigo_cliente}'   => $cliente->codigo ?? '',
        '{equipamento}'      => $servico->equipamento ?? '',
        '{data_servico}'     => !empty($servico->data_agendada)
            ? date_i18n('d/m/Y \à\s H:i', strtotime($servico->data_agendada))
            : '',
        '{orcamento}'        => !empty($servico->orcamento)
            ? number_format((float) $servico->orcamento, 2, ',', '.') . ' €'
            : '',
        '{observacoes}'      => $servico->observacoes ?? '',
        '{empresa_nome}'     => $config['empresa_nome'] ?? '',
        '{empresa_email}'    => $config['empresa_email'] ?? '',
        '{empresa_telefone}' => $config['empresa_telefone'] ?? '',
    ];

    return str_replace(array_keys($substituicoes), array_values($substituicoes), $texto);
}

/* ============================================================
   PROCESSAR CORPO: HTML ou texto simples
   ============================================================ */
function rc_crm_processar_corpo_email($texto) {
    $tem_html = preg_match('/<(p|h[1-6]|ul|ol|li|strong|em|a|br|table|tr|td|th|div|span|img|blockquote|hr)\b/i', $texto);

    if ($tem_html) {
        return wp_kses($texto, rc_crm_tags_permitidas());
    }

    return wpautop(esc_html($texto));
}

/* ============================================================
   BOTÃO WHATSAPP NO EMAIL
   ============================================================ */
function rc_crm_botao_whatsapp_email($servico_id) {
    if (!function_exists('rc_crm_link_whatsapp_servico')) return '';

    $url = rc_crm_link_whatsapp_servico($servico_id, 'agendado');
    if (empty($url)) return '';

    $html  = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:24px 0;">';
    $html .= '<tr><td align="center">';
    $html .= '<a href="' . esc_url($url) . '" target="_blank" rel="noopener" style="display:inline-block; background:#25d366; color:#fff; padding:14px 28px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px;">';
    $html .= '💬 Falar no WhatsApp';
    $html .= '</a>';
    $html .= '</td></tr></table>';

    return $html;
}

/* ============================================================
   SANITIZAR NOME PARA O HEADER "From:"
   ============================================================ */
if (!function_exists('rc_crm_sanitizar_from_nome')) {
    function rc_crm_sanitizar_from_nome($nome) {
        // Remove caracteres que quebram o header RFC 5322
        $nome = str_replace(['<', '>', ',', '"', "\r", "\n"], '', $nome);
        return trim($nome);
    }
}

/* ============================================================
   ENVIAR NOTIFICAÇÃO
   ============================================================ */
function rc_crm_enviar_notificacao($tipo, $servico_id) {
    // Whitelist do tipo
    if (!in_array($tipo, RC_CRM_TIPOS_NOTIF, true)) {
        return false;
    }

    $config = rc_crm_get_config();

    /* -------- Ativo? -------- */
    $chave_ativa = 'email_' . $tipo . '_ativo';
    if (empty($config[$chave_ativa]) || $config[$chave_ativa] !== '1') {
        return false;
    }

    global $wpdb;
    $table_s = $wpdb->prefix . 'rc_servicos';

    $servico = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_s WHERE id = %d",
        $servico_id
    ));
    if (!$servico) return false;

    /* -------- Evitar duplicados -------- */
    $campo_enviado = 'email_' . $tipo . '_enviado_em';
    if (!empty($servico->$campo_enviado)) {
        return false; // já foi enviado
    }

    /* -------- Cliente -------- */
    $cliente = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}rc_clientes WHERE id = %d",
        $servico->cliente_id
    ));
    if (!$cliente || empty($cliente->email)) return false;

    /* -------- Templates -------- */
    $assunto_tpl = $config['email_' . $tipo . '_assunto'] ?? '';
    $corpo_tpl   = $config['email_' . $tipo . '_corpo']   ?? '';
    if (empty($assunto_tpl) || empty($corpo_tpl)) return false;

    $assunto          = rc_crm_render_variaveis($assunto_tpl, $servico, $cliente);
    $corpo_processado = rc_crm_render_variaveis($corpo_tpl, $servico, $cliente);
    $corpo_html       = rc_crm_processar_corpo_email($corpo_processado);

    /* -------- Botão WhatsApp -------- */
    $botao_wa = rc_crm_botao_whatsapp_email($servico_id);
    if (!empty($botao_wa)) {
        $corpo_html .= $botao_wa;
    }

    /* -------- Template final -------- */
    $corpo_final = rc_crm_template_email($corpo_html, $config);

    /* -------- Headers -------- */
    $from_nome  = rc_crm_sanitizar_from_nome($config['empresa_nome']);
    $from_email = sanitize_email($config['empresa_email']);

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $from_nome . ' <' . $from_email . '>',
        'Reply-To: ' . $from_email,
    ];

    /* -------- Enviar -------- */
    $enviado = wp_mail($cliente->email, $assunto, $corpo_final, $headers);

    if ($enviado) {
        // Campo já validado pela whitelist → seguro
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_s SET $campo_enviado = %s WHERE id = %d",
            current_time('mysql'),
            $servico_id
        ));

        do_action('rc_crm_notificacao_enviada', $tipo, $servico_id, $cliente->email);
    } else {
        error_log("RC CRM - Falha ao enviar notificação '$tipo' para o serviço #$servico_id ({$cliente->email})");
    }

    return $enviado;
}

/* ============================================================
   TEMPLATE DE EMAIL (wrapper HTML)
   ============================================================ */
function rc_crm_template_email($conteudo_html, $config) {
    $ano = date('Y');
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body style="margin:0; padding:0; background:#f0f0f0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f0f0f0; padding:30px 15px;">
            <tr>
                <td align="center">
                    <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.08); max-width:600px;">
                        <tr>
                            <td style="background:#2271b1; padding:24px 30px; text-align:center;">
                                <div style="color:#fff; font-size:20px; font-weight:700;"><?php echo esc_html($config['empresa_nome']); ?></div>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:30px; color:#333; font-size:15px; line-height:1.6;">
                                <?php
                                // $conteudo_html já foi sanitizado com wp_kses() pelo caller
                                echo $conteudo_html;
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="background:#f9f9f9; padding:20px 30px; text-align:center; font-size:12px; color:#999; border-top:1px solid #eee;">
                                <p style="margin:0 0 6px;"><?php echo esc_html($config['empresa_nome']); ?></p>
                                <p style="margin:0 0 6px;">
                                    <?php echo esc_html($config['empresa_morada']); ?>, <?php echo esc_html($config['empresa_cidade']); ?>
                                </p>
                                <p style="margin:0;">
                                    📞 <?php echo esc_html($config['empresa_telefone']); ?> ·
                                    ✉️ <?php echo esc_html($config['empresa_email']); ?>
                                </p>
                                <p style="margin:12px 0 0; color:#ccc;">© <?php echo esc_html($ano); ?> <?php echo esc_html($config['empresa_nome']); ?></p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

/* ============================================================
   DISPARAR NOTIFICAÇÕES CONFORME MUDANÇA DE ESTADO
   ============================================================ */
function rc_crm_disparar_notificacoes_servico($servico_id, $status_anterior = null) {
    global $wpdb;
    $servico = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}rc_servicos WHERE id = %d",
        $servico_id
    ));
    if (!$servico) return;

    /* Primeiro save → "agendado" */
    if ($status_anterior === null) {
        rc_crm_enviar_notificacao('agendado', $servico_id);
        return;
    }

    /* Transição para "concluido" → "concluido" */
    if ($status_anterior !== 'concluido' && $servico->status === 'concluido') {
        rc_crm_enviar_notificacao('concluido', $servico_id);
    }
}

/* ============================================================
   PREVIEW DE EMAIL (admin)
   ============================================================ */
add_action('admin_post_rc_preview_email', 'rc_crm_preview_email');
function rc_crm_preview_email() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão');
    check_admin_referer('rc_preview_email');

    $tipo = sanitize_text_field($_GET['tipo'] ?? 'agendado');

    // Whitelist
    if (!in_array($tipo, RC_CRM_TIPOS_NOTIF, true)) {
        wp_die('Tipo de notificação inválido.');
    }

    $config = rc_crm_get_config();

    $assunto_tpl = $config['email_' . $tipo . '_assunto'] ?? '';
    $corpo_tpl   = $config['email_' . $tipo . '_corpo']   ?? '';

    /* Dados fictícios para preview */
    $servico = (object) [
        'equipamento'   => 'Ar condicionado Samsung 12000 BTU',
        'data_agendada' => date('Y-m-d H:i:s', strtotime('+2 days 10:00')),
        'orcamento'     => 350.00,
        'observacoes'   => 'Instalação no 3.º andar. Necessário acesso ao exterior.',
    ];
    $cliente = (object) [
        'nome'     => 'João Silva (exemplo)',
        'email'    => 'joao.silva@exemplo.pt',
        'telefone' => '912 345 678',
        'codigo'   => 'CLI001',
    ];

    $assunto          = rc_crm_render_variaveis($assunto_tpl, $servico, $cliente);
    $corpo_processado = rc_crm_render_variaveis($corpo_tpl, $servico, $cliente);
    $corpo_html       = rc_crm_processar_corpo_email($corpo_processado);

    /* Botão WhatsApp fictício */
    $botao_wa  = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:24px 0;">';
    $botao_wa .= '<tr><td align="center">';
    $botao_wa .= '<a href="#" style="display:inline-block; background:#25d366; color:#fff; padding:14px 28px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px;">💬 Falar no WhatsApp</a>';
    $botao_wa .= '</td></tr></table>';
    $corpo_html .= $botao_wa;

    $corpo_final = rc_crm_template_email($corpo_html, $config);

    ?>
    <!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8">
        <title>Pré-visualização — <?php echo esc_html($assunto); ?></title>
        <style>
            body { margin:0; padding:0; background:#e5e5e5; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; }
            .pv-barra { background:#23282d; color:#fff; padding:14px 20px; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:100; }
            .pv-barra h1 { margin:0; font-size:16px; font-weight:600; }
            .pv-barra .pv-info { font-size:13px; color:#aaa; }
            .pv-barra .pv-info strong { color:#fff; }
            .pv-barra button { background:#2271b1; color:#fff; border:none; padding:10px 20px; border-radius:6px; font-size:14px; font-weight:600; cursor:pointer; }
            .pv-barra button:hover { background:#135e96; }
            .pv-conteudo { padding:30px 15px; }
            .pv-assunto { max-width:600px; margin:0 auto 16px; background:#fff; border-radius:8px; padding:14px 20px; font-size:14px; color:#333; }
            .pv-assunto strong { color:#2271b1; }
        </style>
    </head>
    <body>
        <div class="pv-barra">
            <div>
                <h1>📧 Pré-visualização do Email</h1>
                <div class="pv-info">Tipo: <strong><?php echo esc_html(ucfirst($tipo)); ?></strong> · Este é apenas um exemplo com dados fictícios</div>
            </div>
            <button onclick="window.close()">✕ Fechar</button>
        </div>

        <div class="pv-conteudo">
            <div class="pv-assunto">
                <strong>Assunto:</strong> <?php echo esc_html($assunto); ?>
            </div>
            <?php echo $corpo_final; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}