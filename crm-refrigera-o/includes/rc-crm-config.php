<?php
/**
 * Refrigeração CRM — Configurações (9 abas em ordem alfabética)
 *
 * Abas: Atualizações | Backups | Comissões | Condições | Emails | Geral | IVA | Restaurar | WhatsApp
 *
 * @package Refrigeracao_CRM
 * @version 3.6.0
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
   DEFAULTS
   ============================================================ */
function rc_crm_defaults_empresa() {
    return [
        'empresa_nome'     => 'Nome da Sua Empresa, Lda.',
        'empresa_nif'      => '500 000 000',
        'empresa_morada'   => 'Rua Exemplo, 123',
        'empresa_cidade'   => '1000-000 Lisboa',
        'empresa_telefone' => '210 000 000',
        'empresa_email'    => 'geral@empresa.pt',
        'empresa_website'  => 'www.empresa.pt',

        'whatsapp_numero'        => '',
        'whatsapp_agendado_msg'  => "Olá {nome_cliente}! 👋\n\nAqui é da *{empresa_nome}*.\n\nConfirmamos o agendamento do seu serviço:\n\n📅 Data: {data}\n🔧 Equipamento: {equipamento}\n💰 Valor: {valor}\n\nQualquer dúvida, é só responder a esta mensagem!\n\nObrigado pela preferência 🙏",
        'whatsapp_lembrete_msg'  => "Olá {nome_cliente}! 🔔\n\nPassando para lembrar do seu serviço agendado:\n\n📅 {data}\n🔧 {equipamento}\n\nAté breve!\n*{empresa_nome}*",
        'whatsapp_concluido_msg' => "Olá {nome_cliente}! ✅\n\nO seu serviço foi concluído com sucesso.\n\n🔧 Equipamento: {equipamento}\n💰 Valor: {valor}\n\nAgradecemos a preferência! Se precisar de algo, é só chamar.\n\n*{empresa_nome}*\n📞 {empresa_telefone}",

        'iva_global'       => '23',
        'comissao_global'  => '10',

        'cond_validade'    => '30',
        'cond_pagamento'   => 'Transferência bancária ou MB Way',
        'cond_garantia'    => '1 ano sobre mão-de-obra e peças aplicadas',
        'cond_prazo'       => 'A combinar após aprovação',
        'cond_extra'       => 'Este orçamento não inclui trabalhos não descritos acima.',

        'email_agendado_ativo'   => '1',
        'email_agendado_assunto' => 'Confirmação de agendamento — {empresa_nome}',
        'email_agendado_corpo'   => "<h2 style=\"color:#1877F2;margin-top:0;\">Olá {nome_cliente},</h2>\n\n<p>Confirmamos o agendamento do seu serviço.</p>\n\n<table style=\"width:100%;border-collapse:collapse;margin:16px 0;\">\n<tr><td style=\"padding:10px;border-bottom:1px solid #eee;\"><strong>📅 Data:</strong></td><td style=\"padding:10px;border-bottom:1px solid #eee;\">{data_servico}</td></tr>\n<tr><td style=\"padding:10px;border-bottom:1px solid #eee;\"><strong>🔧 Equipamento:</strong></td><td style=\"padding:10px;border-bottom:1px solid #eee;\">{equipamento}</td></tr>\n<tr><td style=\"padding:10px;\"><strong>💰 Orçamento:</strong></td><td style=\"padding:10px;\">{orcamento}</td></tr>\n</table>\n\n<p>Se precisar de <strong>alterar a data</strong>, contacte-nos:</p>\n\n<p>📞 {empresa_telefone}<br>✉️ <a href=\"mailto:{empresa_email}\" style=\"color:#1877F2;\">{empresa_email}</a></p>\n\n<p style=\"margin-top:24px;\">Obrigado pela preferência!<br><strong>{empresa_nome}</strong></p>",

        'email_lembrete_ativo'   => '1',
        'email_lembrete_assunto' => 'Lembrete: serviço amanhã — {empresa_nome}',
        'email_lembrete_corpo'   => "<h2 style=\"color:#dba617;margin-top:0;\">Olá {nome_cliente},</h2>\n\n<p>Este é um <strong>lembrete</strong> do serviço agendado para amanhã.</p>\n\n<table style=\"width:100%;border-collapse:collapse;margin:16px 0;\">\n<tr><td style=\"padding:10px;border-bottom:1px solid #eee;\"><strong>📅 Data:</strong></td><td style=\"padding:10px;border-bottom:1px solid #eee;\">{data_servico}</td></tr>\n<tr><td style=\"padding:10px;\"><strong>🔧 Equipamento:</strong></td><td style=\"padding:10px;\">{equipamento}</td></tr>\n</table>\n\n<p>Caso precise de reagendar, contacte-nos:</p>\n\n<p>📞 {empresa_telefone}</p>\n\n<p style=\"margin-top:24px;\">Até amanhã!<br><strong>{empresa_nome}</strong></p>",

        'email_concluido_ativo'   => '1',
        'email_concluido_assunto' => 'Serviço concluído — {empresa_nome}',
        'email_concluido_corpo'   => "<h2 style=\"color:#00a32a;margin-top:0;\">Olá {nome_cliente},</h2>\n\n<p>O seu serviço foi <strong>concluído com sucesso</strong>. ✅</p>\n\n<table style=\"width:100%;border-collapse:collapse;margin:16px 0;\">\n<tr><td style=\"padding:10px;border-bottom:1px solid #eee;\"><strong>🔧 Equipamento:</strong></td><td style=\"padding:10px;border-bottom:1px solid #eee;\">{equipamento}</td></tr>\n<tr><td style=\"padding:10px;\"><strong>💰 Valor:</strong></td><td style=\"padding:10px;\">{orcamento}</td></tr>\n</table>\n\n<p>Agradecemos a sua preferência e esperamos que esteja tudo a seu agrado.</p>\n\n<p>Se tiver alguma questão, contacte-nos:</p>\n\n<p>📞 {empresa_telefone}<br>✉️ <a href=\"mailto:{empresa_email}\" style=\"color:#1877F2;\">{empresa_email}</a></p>\n\n<p style=\"margin-top:24px;\">Cumprimentos,<br><strong>{empresa_nome}</strong></p>",
    ];
}

/* ============================================================
   GETTER
   ============================================================ */
function rc_crm_get_config() {
    $defaults = rc_crm_defaults_empresa();
    $salvo = get_option('rc_crm_config', []);
    if (!is_array($salvo)) $salvo = [];
    return wp_parse_args($salvo, $defaults);
}

/* ============================================================
   IVA POR SERVIÇO
   ============================================================ */
function rc_crm_obter_iva_servico($servico) {
    if (isset($servico->iva_percentual) && $servico->iva_percentual !== null && $servico->iva_percentual !== '') {
        return floatval($servico->iva_percentual);
    }
    $config = rc_crm_get_config();
    return floatval($config['iva_global'] ?? 23);
}

/* ============================================================
   SANITIZAÇÃO
   ============================================================ */
if (!function_exists('rc_crm_sanitizar_config')) {
    function rc_crm_sanitizar_config($campo, $valor) {
        $campos_html     = ['email_agendado_corpo', 'email_lembrete_corpo', 'email_concluido_corpo'];
        $campos_textarea = ['whatsapp_agendado_msg', 'whatsapp_lembrete_msg', 'whatsapp_concluido_msg', 'cond_extra'];
        $campos_numericos= ['iva_global', 'comissao_global', 'cond_validade'];
        $campos_email    = ['empresa_email'];
        $campos_bool     = ['email_agendado_ativo', 'email_lembrete_ativo', 'email_concluido_ativo'];

        if (in_array($campo, $campos_html, true))      return wp_kses_post($valor);
        if (in_array($campo, $campos_textarea, true))  return sanitize_textarea_field($valor);
        if (in_array($campo, $campos_numericos, true)) return is_numeric($valor) ? (string) $valor : '0';
        if (in_array($campo, $campos_email, true))     return sanitize_email($valor);
        if (in_array($campo, $campos_bool, true))      return ($valor === '1' || $valor === 1 || $valor === 'on') ? '1' : '0';

        return sanitize_text_field($valor);
    }
}

/* ============================================================
   HANDLERS
   ============================================================ */
add_action('admin_post_rc_email_teste', 'rc_crm_email_teste_handler');
function rc_crm_email_teste_handler() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão');
    check_admin_referer('rc_email_teste');

    $para = sanitize_email($_POST['email_teste'] ?? '');
    if (!is_email($para)) {
        wp_safe_redirect(admin_url('admin.php?page=rc-crm-config&tab=emails&teste=email_invalido'));
        exit;
    }

    $config  = rc_crm_get_config();
    $assunto = '✅ Teste de envio — ' . $config['empresa_nome'];

    $corpo_txt = "<h2 style=\"color:#1877F2;margin-top:0;\">Teste de envio</h2>\n\n"
               . "<p>Este é um email de teste.</p>\n\n"
               . "<p><strong>Data/hora:</strong> " . esc_html(date_i18n('d/m/Y H:i:s')) . "</p>";

    if (function_exists('rc_crm_template_email')) {
        $corpo_html = rc_crm_template_email($corpo_txt, $config);
    } else {
        $corpo_html = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;color:#333;">' . $corpo_txt . '</body></html>';
    }

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $config['empresa_nome'] . ' <' . $config['empresa_email'] . '>',
    ];

    $enviado = wp_mail($para, $assunto, $corpo_html, $headers);
    wp_safe_redirect(admin_url('admin.php?page=rc-crm-config&tab=emails&teste=' . ($enviado ? 'ok' : 'falhou')));
    exit;
}

add_action('admin_post_rc_restaurar_template', 'rc_crm_restaurar_template');
function rc_crm_restaurar_template() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão');
    check_admin_referer('rc_restaurar_template');

    $tipo = sanitize_text_field($_GET['tipo'] ?? '');
    if (!in_array($tipo, ['agendado', 'lembrete', 'concluido'], true)) {
        wp_safe_redirect(admin_url('admin.php?page=rc-crm-config&tab=emails&restaurado=erro'));
        exit;
    }

    $defaults = rc_crm_defaults_empresa();
    $config   = rc_crm_get_config();

    $config['email_' . $tipo . '_assunto'] = $defaults['email_' . $tipo . '_assunto'];
    $config['email_' . $tipo . '_corpo']   = $defaults['email_' . $tipo . '_corpo'];
    update_option('rc_crm_config', $config);

    wp_safe_redirect(admin_url('admin.php?page=rc-crm-config&tab=emails&restaurado=' . $tipo));
    exit;
}

add_action('admin_post_rc_restaurar_whatsapp', 'rc_crm_restaurar_whatsapp');
function rc_crm_restaurar_whatsapp() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão');
    check_admin_referer('rc_restaurar_whatsapp');

    $tipo = sanitize_text_field($_GET['tipo'] ?? '');
    if (!in_array($tipo, ['agendado', 'lembrete', 'concluido'], true)) {
        wp_safe_redirect(admin_url('admin.php?page=rc-crm-config&tab=whatsapp&restaurado=erro'));
        exit;
    }

    $defaults = rc_crm_defaults_empresa();
    $config   = rc_crm_get_config();

    $config['whatsapp_' . $tipo . '_msg'] = $defaults['whatsapp_' . $tipo . '_msg'];
    update_option('rc_crm_config', $config);

    wp_safe_redirect(admin_url('admin.php?page=rc-crm-config&tab=whatsapp&restaurado=whatsapp_' . $tipo));
    exit;
}

/* ============================================================
   GUARDAR CONFIGURAÇÕES (comum a todas as abas)
   ============================================================ */
function rc_crm_config_processar_guardar() {
    if (!isset($_POST['rc_config_nonce'])) return null;
    if (!wp_verify_nonce($_POST['rc_config_nonce'], 'rc_salvar_config')) {
        return ['tipo' => 'error', 'msg' => '❌ Erro de segurança.'];
    }

    $campos = array_keys(rc_crm_defaults_empresa());
    $novo   = rc_crm_get_config(); // mantém valores não submetidos

    foreach ($campos as $c) {
        // Checkboxes não vêm no POST se desmarcados
        if (in_array($c, ['email_agendado_ativo', 'email_lembrete_ativo', 'email_concluido_ativo'], true)) {
            $novo[$c] = isset($_POST[$c]) ? '1' : '0';
            continue;
        }

        // Campos que não estão no form atual → manter o valor antigo
        if (!isset($_POST[$c])) continue;

        $novo[$c] = rc_crm_sanitizar_config($c, wp_unslash($_POST[$c]));
    }

    update_option('rc_crm_config', $novo);
    return ['tipo' => 'success', 'msg' => '✅ Configurações guardadas.'];
}

/* ============================================================
   PÁGINA — DESPACHO DE ABAS
   ============================================================ */
function rc_crm_config_page() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');

    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'geral';

    // Ordem alfabética
    $abas_validas = ['atualizacoes', 'backups', 'comissoes', 'condicoes', 'emails', 'geral', 'iva', 'restaurar', 'whatsapp'];
    if (!in_array($tab, $abas_validas, true)) $tab = 'geral';

    $abas = [
        'atualizacoes' => ['label' => '🔄 Atualizações', 'callback' => 'rc_crm_config_render_atualizacoes'],
        'backups'      => ['label' => '💾 Backups',      'callback' => 'rc_crm_config_render_backups'],
        'comissoes'    => ['label' => '💼 Comissões',    'callback' => 'rc_crm_config_render_comissoes'],
        'condicoes'    => ['label' => '📋 Condições',    'callback' => 'rc_crm_config_render_condicoes'],
        'emails'       => ['label' => '📧 Emails',       'callback' => 'rc_crm_config_render_emails'],
        'geral'        => ['label' => '🏢 Geral',        'callback' => 'rc_crm_config_render_geral'],
        'iva'          => ['label' => '💰 IVA',          'callback' => 'rc_crm_config_render_iva'],
        'restaurar'    => ['label' => '♻️ Restaurar',    'callback' => 'rc_crm_config_render_restaurar'],
        'whatsapp'     => ['label' => '💬 WhatsApp',     'callback' => 'rc_crm_config_render_whatsapp'],
    ];

    // Processar guardar (comum)
    $resultado = rc_crm_config_processar_guardar();
    ?>
    <div class="wrap rc-config-wrapper">
        <h1>⚙️ Configurações do CRM</h1>

        <nav class="nav-tab-wrapper rc-config-tabs">
            <?php foreach ($abas as $slug => $info): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rc-crm-config&tab=' . $slug)); ?>"
                   class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html($info['label']); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="rc-config-tab-content">
            <?php
            if ($resultado) {
                echo '<div class="notice notice-' . esc_attr($resultado['tipo']) . ' is-dismissible"><p>' . esc_html($resultado['msg']) . '</p></div>';
            }

            if (isset($_GET['teste'])) {
                if ($_GET['teste'] === 'ok')                 echo '<div class="notice notice-success is-dismissible"><p>✅ Email de teste enviado!</p></div>';
                elseif ($_GET['teste'] === 'falhou')         echo '<div class="notice notice-error"><p>❌ Falha ao enviar. Verifique a configuração do servidor de email (WP Mail SMTP).</p></div>';
                elseif ($_GET['teste'] === 'email_invalido') echo '<div class="notice notice-error"><p>❌ Email inválido.</p></div>';
            }

            if (isset($_GET['restaurado'])) {
                if ($_GET['restaurado'] === 'erro') {
                    echo '<div class="notice notice-error"><p>❌ Erro ao restaurar.</p></div>';
                } else {
                    echo '<div class="notice notice-success is-dismissible"><p>♻️ Template <strong>' . esc_html($_GET['restaurado']) . '</strong> restaurado.</p></div>';
                }
            }

            $callback = $abas[$tab]['callback'];
            if (function_exists($callback)) {
                call_user_func($callback);
            } else {
                echo '<p>Secção indisponível.</p>';
            }
            ?>
        </div>
    </div>
    <?php
}

/* ============================================================
   RENDER: GERAL (empresa + nº WhatsApp)
   ============================================================ */
function rc_crm_config_render_geral() {
    $config = rc_crm_get_config();
    ?>
    <form method="post">
        <?php wp_nonce_field('rc_salvar_config', 'rc_config_nonce'); ?>

        <h2>🏢 Dados da Empresa</h2>
        <table class="form-table">
            <tr><th><label for="empresa_nome">Nome</label></th><td><input type="text" id="empresa_nome" name="empresa_nome" value="<?php echo esc_attr($config['empresa_nome']); ?>" class="regular-text" required></td></tr>
            <tr><th><label for="empresa_nif">NIF</label></th><td><input type="text" id="empresa_nif" name="empresa_nif" value="<?php echo esc_attr($config['empresa_nif']); ?>" class="regular-text"></td></tr>
            <tr><th><label for="empresa_morada">Morada</label></th><td><input type="text" id="empresa_morada" name="empresa_morada" value="<?php echo esc_attr($config['empresa_morada']); ?>" class="regular-text"></td></tr>
            <tr><th><label for="empresa_cidade">Cidade + CEP</label></th><td><input type="text" id="empresa_cidade" name="empresa_cidade" value="<?php echo esc_attr($config['empresa_cidade']); ?>" class="regular-text"></td></tr>
            <tr><th><label for="empresa_telefone">Telefone</label></th><td><input type="text" id="empresa_telefone" name="empresa_telefone" value="<?php echo esc_attr($config['empresa_telefone']); ?>" class="regular-text"></td></tr>
            <tr><th><label for="empresa_email">Email</label></th><td><input type="email" id="empresa_email" name="empresa_email" value="<?php echo esc_attr($config['empresa_email']); ?>" class="regular-text"></td></tr>
            <tr><th><label for="empresa_website">Website</label></th><td><input type="text" id="empresa_website" name="empresa_website" value="<?php echo esc_attr($config['empresa_website']); ?>" class="regular-text"></td></tr>
        </table>

        <h2 style="margin-top:30px;">💬 WhatsApp</h2>
        <table class="form-table">
            <tr>
                <th><label for="whatsapp_numero">Número WhatsApp da Empresa</label></th>
                <td>
                    <input type="text" id="whatsapp_numero" name="whatsapp_numero" value="<?php echo esc_attr($config['whatsapp_numero']); ?>" class="regular-text" placeholder="Ex: 934 537 507">
                    <p class="description">Deixe vazio para usar o telefone da empresa. Formato: <code>351XXXXXXXXX</code> (o 351 é adicionado automaticamente).</p>
                </td>
            </tr>
        </table>

        <?php submit_button('💾 Guardar Configurações'); ?>
    </form>
    <?php
}

/* ============================================================
   RENDER: WHATSAPP (só mensagens)
   ============================================================ */
function rc_crm_config_render_whatsapp() {
    $config = rc_crm_get_config();

    $render_msg = function($tipo, $titulo) use ($config) {
        ?>
        <div style="background:#fff; border-left:4px solid #25d366; border-radius:8px; padding:20px; margin:20px 0; max-width:900px;">
            <h3 style="margin-top:0; display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                <span><?php echo esc_html($titulo); ?></span>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=rc_restaurar_whatsapp&tipo=' . $tipo), 'rc_restaurar_whatsapp')); ?>" class="button button-small" style="font-size:12px;" onclick="return confirm('Restaurar mensagem padrão?');">♻️ Restaurar</a>
            </h3>
            <table class="form-table">
                <tr>
                    <th style="width:120px;"><label for="whatsapp_<?php echo esc_attr($tipo); ?>_msg">Mensagem</label></th>
                    <td>
                        <textarea id="whatsapp_<?php echo esc_attr($tipo); ?>_msg" name="whatsapp_<?php echo esc_attr($tipo); ?>_msg" rows="8" class="large-text" style="font-family:Menlo,Consolas,monospace; font-size:13px; line-height:1.5;"><?php echo esc_textarea($config['whatsapp_' . $tipo . '_msg']); ?></textarea>
                        <p class="description">Aceita emojis e asteriscos para negrito (*texto*). Use <code>\n</code> para quebras de linha.</p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    };
    ?>
    <form method="post">
        <?php wp_nonce_field('rc_salvar_config', 'rc_config_nonce'); ?>

        <h2>💬 Mensagens WhatsApp</h2>
        <p class="description">
            <strong>Variáveis:</strong>
            <code>{nome_cliente}</code>
            <code>{codigo_cliente}</code>
            <code>{equipamento}</code>
            <code>{data}</code>
            <code>{valor}</code>
            <code>{empresa_nome}</code>
            <code>{empresa_telefone}</code>
        </p>

        <?php
        $render_msg('agendado',  '📅 Confirmação de Agendamento');
        $render_msg('lembrete',  '🔔 Lembrete');
        $render_msg('concluido', '✅ Agradecimento');
        ?>

        <?php submit_button('💾 Guardar Mensagens'); ?>
    </form>
    <?php
}

/* ============================================================
   RENDER: EMAILS (só templates)
   ============================================================ */
function rc_crm_config_render_emails() {
    $config = rc_crm_get_config();

    $render_email = function($tipo, $titulo, $cor, $descricao) use ($config) {
        ?>
        <div style="background:#fff; border-left:4px solid <?php echo esc_attr($cor); ?>; border-radius:8px; padding:20px; margin:20px 0; max-width:900px;">
            <h3 style="margin-top:0; display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                <label style="display:flex; align-items:center; gap:8px; margin:0;">
                    <input type="checkbox" name="email_<?php echo esc_attr($tipo); ?>_ativo" value="1" <?php checked($config['email_' . $tipo . '_ativo'], '1'); ?>>
                    <?php echo esc_html($titulo); ?>
                </label>
                <span>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=rc_preview_email&tipo=' . $tipo), 'rc_preview_email')); ?>" target="_blank" rel="noopener" class="button button-small" style="font-size:12px;">👁️ Pré-visualizar</a>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=rc_restaurar_template&tipo=' . $tipo), 'rc_restaurar_template')); ?>" class="button button-small" style="font-size:12px;" onclick="return confirm('Restaurar template padrão?');">♻️ Restaurar</a>
                </span>
            </h3>
            <p class="description" style="margin:6px 0 12px;"><?php echo esc_html($descricao); ?></p>
            <table class="form-table">
                <tr>
                    <th style="width:120px;"><label for="email_<?php echo esc_attr($tipo); ?>_assunto">Assunto</label></th>
                    <td><input type="text" id="email_<?php echo esc_attr($tipo); ?>_assunto" name="email_<?php echo esc_attr($tipo); ?>_assunto" value="<?php echo esc_attr($config['email_' . $tipo . '_assunto']); ?>" class="large-text"></td>
                </tr>
                <tr>
                    <th><label for="email_<?php echo esc_attr($tipo); ?>_corpo">Corpo</label></th>
                    <td>
                        <textarea id="email_<?php echo esc_attr($tipo); ?>_corpo" name="email_<?php echo esc_attr($tipo); ?>_corpo" rows="14" class="large-text" style="font-family:Menlo,Consolas,monospace; font-size:13px; line-height:1.5;"><?php echo esc_textarea($config['email_' . $tipo . '_corpo']); ?></textarea>
                        <p class="description">Aceita <strong>HTML</strong>. Se não usar tags, formatação automática.</p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    };
    ?>
    <form method="post">
        <?php wp_nonce_field('rc_salvar_config', 'rc_config_nonce'); ?>

        <h2>📧 Templates de Email</h2>
        <div style="background:#fff9e5; border-left:4px solid #dba617; border-radius:8px; padding:16px; margin:16px 0; max-width:900px;">
            <strong>📝 Variáveis:</strong> <code>{nome_cliente}</code> <code>{email_cliente}</code> <code>{telefone_cliente}</code> <code>{codigo_cliente}</code> <code>{equipamento}</code> <code>{data_servico}</code> <code>{orcamento}</code> <code>{observacoes}</code> <code>{empresa_nome}</code> <code>{empresa_email}</code> <code>{empresa_telefone}</code>
        </div>

        <?php
        $render_email('agendado',  'Confirmação de agendamento', '#1877F2', 'Enviado quando um serviço é criado.');
        $render_email('lembrete',  'Lembrete 1 dia antes',       '#dba617', 'Enviado às 9h do dia anterior.');
        $render_email('concluido', 'Serviço concluído',          '#00a32a', 'Enviado quando o status muda para Concluído.');
        ?>

        <?php submit_button('💾 Guardar Templates'); ?>
    </form>

    <hr style="margin: 40px 0 20px; max-width:900px;">
    <h2>🧪 Testar Envio de Email</h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex; gap:10px; align-items:center; max-width:500px;">
        <?php wp_nonce_field('rc_email_teste'); ?>
        <input type="hidden" name="action" value="rc_email_teste">
        <input type="email" name="email_teste" class="regular-text" required value="<?php echo esc_attr(get_option('admin_email')); ?>">
        <button type="submit" class="button">📤 Enviar Teste</button>
    </form>
    <?php
}

/* ============================================================
   RENDER: IVA
   ============================================================ */
function rc_crm_config_render_iva() {
    $config = rc_crm_get_config();
    ?>
    <form method="post">
        <?php wp_nonce_field('rc_salvar_config', 'rc_config_nonce'); ?>

        <h2>💰 IVA Padrão</h2>
        <p class="description">Este valor é usado por defeito em novos serviços. Pode ser alterado individualmente em cada serviço.</p>

        <table class="form-table">
            <tr>
                <th><label for="iva_global">% de IVA Padrão</label></th>
                <td>
                    <input type="number" step="0.01" min="0" max="100" id="iva_global" name="iva_global" value="<?php echo esc_attr($config['iva_global']); ?>" style="width:100px">
                    <span>%</span>
                </td>
            </tr>
        </table>

        <?php submit_button('💾 Guardar IVA'); ?>
    </form>
    <?php
}

/* ============================================================
   RENDER: COMISSÕES
   ============================================================ */
function rc_crm_config_render_comissoes() {
    $config = rc_crm_get_config();
    ?>
    <form method="post">
        <?php wp_nonce_field('rc_salvar_config', 'rc_config_nonce'); ?>

        <h2>💼 Comissões</h2>
        <p class="description">Comissão aplicada aos técnicos por defeito. Pode ser personalizada em cada técnico no perfil dele.</p>

        <table class="form-table">
            <tr>
                <th><label for="comissao_global">% de Comissão Global</label></th>
                <td>
                    <input type="number" step="0.01" min="0" max="100" id="comissao_global" name="comissao_global" value="<?php echo esc_attr($config['comissao_global']); ?>" style="width:100px">
                    <span>%</span>
                </td>
            </tr>
        </table>

        <?php submit_button('💾 Guardar Comissões'); ?>
    </form>
    <?php
}

/* ============================================================
   RENDER: CONDIÇÕES
   ============================================================ */
function rc_crm_config_render_condicoes() {
    $config = rc_crm_get_config();
    ?>
    <form method="post">
        <?php wp_nonce_field('rc_salvar_config', 'rc_config_nonce'); ?>

        <h2>📋 Condições Padrão dos Orçamentos</h2>
        <p class="description">Estas condições aparecem em todos os orçamentos gerados.</p>

        <table class="form-table">
            <tr><th><label for="cond_validade">Validade (dias)</label></th><td><input type="number" id="cond_validade" name="cond_validade" value="<?php echo esc_attr($config['cond_validade']); ?>" min="1" max="365" style="width:100px"></td></tr>
            <tr><th><label for="cond_pagamento">Pagamento</label></th><td><input type="text" id="cond_pagamento" name="cond_pagamento" value="<?php echo esc_attr($config['cond_pagamento']); ?>" class="regular-text"></td></tr>
            <tr><th><label for="cond_garantia">Garantia</label></th><td><input type="text" id="cond_garantia" name="cond_garantia" value="<?php echo esc_attr($config['cond_garantia']); ?>" class="regular-text"></td></tr>
            <tr><th><label for="cond_prazo">Prazo</label></th><td><input type="text" id="cond_prazo" name="cond_prazo" value="<?php echo esc_attr($config['cond_prazo']); ?>" class="regular-text"></td></tr>
            <tr><th><label for="cond_extra">Observação</label></th><td><textarea id="cond_extra" name="cond_extra" rows="3" class="large-text"><?php echo esc_textarea($config['cond_extra']); ?></textarea></td></tr>
        </table>

        <?php submit_button('💾 Guardar Condições'); ?>
    </form>
    <?php
}

/* ============================================================
   RENDER: ATUALIZAÇÕES (delega para a função do atualizacao.php)
   ============================================================ */
function rc_crm_config_render_atualizacoes() {
    if (function_exists('rc_crm_atualizacoes_page')) {
        rc_crm_atualizacoes_page();
    } else {
        echo '<p>Módulo de atualizações indisponível.</p>';
    }
}

/* ============================================================
   RENDER: BACKUPS (delega para a função do backup.php)
   ============================================================ */
function rc_crm_config_render_backups() {
    if (function_exists('rc_crm_backups_page')) {
        rc_crm_backups_page();
    } else {
        echo '<p>Módulo de backups indisponível.</p>';
    }
}

/* ============================================================
   RENDER: RESTAURAR
   ============================================================ */
function rc_crm_config_render_restaurar() {
    $mensagem = '';
    $tipo_msg = 'success';

    /* -------- RESTAURAR BACKUP CSV -------- */
    if (isset($_POST['rc_acao']) && $_POST['rc_acao'] === 'restaurar_backup') {
        check_admin_referer('rc_restaurar_backup');

        $tipo = sanitize_text_field($_POST['tipo_backup'] ?? '');

        if (!isset($_FILES['backup_csv']) || $_FILES['backup_csv']['error'] !== UPLOAD_ERR_OK) {
            $mensagem = '❌ Nenhum ficheiro recebido.';
            $tipo_msg = 'error';
        } else {
            if (function_exists('rc_crm_processar_restauro')) {
                if ($tipo === 'todos') {
                    // Executar os 2 restauros sequencialmente
                    $r1 = rc_crm_processar_restauro('clientes', $_FILES['backup_csv']);
                    $r2 = rc_crm_processar_restauro('servicos', $_FILES['backup_csv']);

                    $mensagem = '<strong>Restauro de Todos:</strong><br>'
                              . '👥 Clientes: ' . strip_tags($r1['mensagem']) . '<br>'
                              . '🔧 Serviços: ' . strip_tags($r2['mensagem']);
                    $tipo_msg = ($r1['sucesso'] || $r2['sucesso']) ? 'success' : 'error';
                } else {
                    $resultado = rc_crm_processar_restauro($tipo, $_FILES['backup_csv']);
                    $mensagem  = $resultado['mensagem'];
                    $tipo_msg  = $resultado['sucesso'] ? 'success' : 'error';
                }
            } else {
                $mensagem = '❌ Módulo de restauro indisponível.';
                $tipo_msg = 'error';
            }
        }
    }
    ?>
    <div class="rc-restaurar">
        <?php if ($mensagem): ?>
            <div class="notice notice-<?php echo esc_attr($tipo_msg); ?> is-dismissible">
                <p><?php echo wp_kses_post($mensagem); ?></p>
            </div>
        <?php endif; ?>

        <div class="rc-restaurar-aviso">
            <h3 style="margin-top:0;">⚠️ Atenção — Ação Destrutiva</h3>
            <p>O restauro de um ficheiro CSV <strong>substitui / insere dados</strong> na base de dados do CRM.</p>
            <ul style="line-height:1.8; margin-bottom:0;">
                <li><strong>Clientes</strong> → atualiza os existentes (por código) e insere os novos.</li>
                <li><strong>Serviços</strong> → insere serviços novos. Ignora duplicados por ID.</li>
                <li><strong>Todos</strong> → executa clientes primeiro, depois serviços (ordem correta).</li>
                <li>Recomendamos <strong>fazer um backup manual</strong> antes de restaurar (aba 💾 Backups).</li>
                <li>O formato esperado é o mesmo CSV exportado pelo sistema.</li>
            </ul>
        </div>

        <form method="post" enctype="multipart/form-data" onsubmit="return confirm('⚠️ Esta ação vai ALTERAR os dados atuais.\n\nRecomendamos ter feito backup primeiro.\n\nContinuar?');">
            <?php wp_nonce_field('rc_restaurar_backup'); ?>
            <input type="hidden" name="rc_acao" value="restaurar_backup">

            <table class="form-table" style="max-width:900px;">
                <tr>
                    <th style="width:200px;"><label for="tipo_backup">Tipo de Backup</label></th>
                    <td>
                        <select name="tipo_backup" id="tipo_backup" required>
                            <option value="todos">📦 Todos (clientes + serviços)</option>
                            <option value="clientes">👥 Clientes</option>
                            <option value="servicos">🔧 Serviços</option>
                        </select>
                        <p class="description">Escolha o que pretende restaurar.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="backup_csv">Ficheiro CSV</label></th>
                    <td>
                        <input type="file" name="backup_csv" id="backup_csv" accept=".csv" required>
                        <p class="description">Aceita ficheiros <code>.csv</code> (separador <code>;</code> ou <code>,</code>, com ou sem BOM).</p>
                    </td>
                </tr>
            </table>

            <p>
                <button type="submit" class="button button-primary" style="background:#b32d2e; border-color:#b32d2e;">
                    ♻️ Restaurar Dados
                </button>
            </p>
        </form>
    </div>
    <?php
}