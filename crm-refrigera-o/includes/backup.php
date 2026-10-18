<?php
/**
 * Refrigeração CRM — Backups
 *
 * @package Refrigeracao_CRM
 * @version 3.5.1
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
   CONSTANTES
   ============================================================ */
if (!defined('RC_CRM_BACKUP_MAX_ANEXO_MB')) {
    define('RC_CRM_BACKUP_MAX_ANEXO_MB', 20);
}
if (!defined('RC_CRM_BACKUP_RETENCAO')) {
    define('RC_CRM_BACKUP_RETENCAO', 4);
}

/* ============================================================
   DIRETÓRIO
   ============================================================ */
function rc_crm_backup_dir() {
    $upload = wp_upload_dir();
    $dir = $upload['basedir'] . '/rc-backups';
    if (!file_exists($dir)) wp_mkdir_p($dir);

    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Deny from all\n");
    }
    return $dir;
}

/* ============================================================
   GERAR BACKUP
   ============================================================ */
function rc_crm_gerar_backup() {
    global $wpdb;
    $dir  = rc_crm_backup_dir();
    $data = wp_date('Y-m-d_H-i-s');
    $ficheiros = [];

    /* -------- CLIENTES -------- */
    $clientes = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}rc_clientes ORDER BY id ASC",
        ARRAY_A
    );
    $fp_clientes = $dir . '/clientes_' . $data . '.csv';
    $f = fopen($fp_clientes, 'w');
    fwrite($f, "\xEF\xBB\xBF");
    if (!empty($clientes)) {
        fputcsv($f, array_keys($clientes[0]), ';');
        foreach ($clientes as $c) {
            $c = array_map(function($v) {
                return str_replace(["\r\n", "\r", "\n"], ' ', (string) $v);
            }, $c);
            fputcsv($f, $c, ';');
        }
    }
    fclose($f);
    $ficheiros['clientes'] = $fp_clientes;

    /* -------- SERVIÇOS -------- */
    $servicos = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}rc_servicos ORDER BY id ASC",
        ARRAY_A
    );
    $fp_servicos = $dir . '/servicos_' . $data . '.csv';
    $f = fopen($fp_servicos, 'w');
    fwrite($f, "\xEF\xBB\xBF");
    if (!empty($servicos)) {
        fputcsv($f, array_keys($servicos[0]), ';');
        foreach ($servicos as $s) {
            $s = array_map(function($v) {
                return str_replace(["\r\n", "\r", "\n"], ' ', (string) $v);
            }, $s);
            fputcsv($f, $s, ';');
        }
    }
    fclose($f);
    $ficheiros['servicos'] = $fp_servicos;

    /* -------- CONFIG -------- */
    $config = get_option('rc_crm_config', []);
    $fp_config = $dir . '/config_' . $data . '.json';
    file_put_contents(
        $fp_config,
        json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
    $ficheiros['config'] = $fp_config;

    /* -------- INFO -------- */
    $versao_plugin = defined('RC_CRM_VERSION') ? RC_CRM_VERSION : '?';
    $resumo  = "=== BACKUP REFRIGERAÇÃO CRM ===\n";
    $resumo .= "Data: " . date_i18n('d/m/Y H:i:s') . "\n";
    $resumo .= "Site: " . home_url() . "\n";
    $resumo .= "Versão do plugin: " . $versao_plugin . "\n\n";
    $resumo .= "Total de clientes: " . count($clientes) . "\n";
    $resumo .= "Total de serviços: " . count($servicos) . "\n";
    $fp_resumo = $dir . '/info_' . $data . '.txt';
    file_put_contents($fp_resumo, $resumo);
    $ficheiros['info'] = $fp_resumo;

    update_option('rc_crm_ultimo_backup', current_time('mysql'));
    rc_crm_limpar_backups_antigos();

    return $ficheiros;
}

/* ============================================================
   LIMPEZA AUTOMÁTICA
   ============================================================ */
function rc_crm_limpar_backups_antigos() {
    $dir = rc_crm_backup_dir();
    $ficheiros = glob($dir . '/*');
    if (!is_array($ficheiros)) return;

    $por_data = [];
    foreach ($ficheiros as $f) {
        $nome = basename($f);
        if (preg_match('/_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\./', $nome, $m)) {
            $por_data[$m[1]][] = $f;
        }
    }
    krsort($por_data);

    $i = 0;
    foreach ($por_data as $timestamp => $grupo) {
        $i++;
        if ($i > RC_CRM_BACKUP_RETENCAO) {
            foreach ($grupo as $f) {
                if (is_file($f)) @unlink($f);
            }
        }
    }
}

/* ============================================================
   LISTAR BACKUPS
   ============================================================ */
function rc_crm_listar_backups() {
    $dir = rc_crm_backup_dir();
    $ficheiros = glob($dir . '/*');
    if (!is_array($ficheiros)) $ficheiros = [];

    $por_data = [];
    foreach ($ficheiros as $f) {
        $nome = basename($f);
        if (preg_match('/_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\./', $nome, $m)) {
            $timestamp = $m[1];
            if (!isset($por_data[$timestamp])) {
                $por_data[$timestamp] = [
                    'timestamp'      => $timestamp,
                    'data_formatada' => date_i18n('d/m/Y H:i:s', strtotime(str_replace('_', ' ', $timestamp))),
                    'ficheiros'      => [],
                    'tamanho_total'  => 0,
                ];
            }
            $por_data[$timestamp]['ficheiros'][] = $f;
            $por_data[$timestamp]['tamanho_total'] += @filesize($f);
        }
    }
    krsort($por_data);
    return $por_data;
}

/* ============================================================
   ENVIAR BACKUP POR EMAIL
   ============================================================ */
function rc_crm_enviar_backup_email($ficheiros) {
    if (empty($ficheiros) || !is_array($ficheiros)) return false;

    $tamanho_total = 0;
    foreach ($ficheiros as $path) {
        if (file_exists($path)) $tamanho_total += filesize($path);
    }
    $limite_bytes = RC_CRM_BACKUP_MAX_ANEXO_MB * 1024 * 1024;

    $para    = get_option('admin_email');
    $site    = get_bloginfo('name');
    $assunto = '💾 Backup automático — ' . $site;

    $corpo  = "Backup automático do Refrigeração CRM.\n\n";
    $corpo .= "Site: " . home_url() . "\n";
    $corpo .= "Data: " . date_i18n('d/m/Y H:i:s') . "\n\n";
    $corpo .= "Ficheiros:\n";
    foreach ($ficheiros as $tipo => $path) {
        if (file_exists($path)) {
            $corpo .= "- " . basename($path) . " (" . size_format(filesize($path)) . ")\n";
        }
    }

    $anexos = [];
    if ($tamanho_total <= $limite_bytes) {
        $anexos = array_values(array_filter($ficheiros, 'file_exists'));
    } else {
        $corpo .= "\n⚠️ Os ficheiros são demasiado grandes para anexar ("
               . size_format($tamanho_total) . "). Faça download pela página de Backups.\n";
    }

    $corpo .= "\nBackups guardados em:\n" . rc_crm_backup_dir();

    $enviado = wp_mail($para, $assunto, $corpo, [], $anexos);

    if (!$enviado) {
        error_log('RC CRM - Falha ao enviar email de backup para ' . $para);
    }

    return $enviado;
}

/* ============================================================
   CRON SEMANAL
   ============================================================ */
add_action('rc_crm_weekly_backup', 'rc_crm_executar_backup_semanal');
function rc_crm_executar_backup_semanal() {
    $ficheiros = rc_crm_gerar_backup();
    rc_crm_enviar_backup_email($ficheiros);
}

/* ============================================================
   PÁGINA (render dentro da aba Backups)
   ============================================================ */
function rc_crm_backups_page() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');

    $mensagem = '';

    /* -------- Criar backup -------- */
    if (isset($_POST['rc_acao']) && $_POST['rc_acao'] === 'criar_backup') {
        check_admin_referer('rc_criar_backup');

        $ficheiros = rc_crm_gerar_backup();

        if (!empty($_POST['enviar_email'])) {
            $enviado = rc_crm_enviar_backup_email($ficheiros);
            if ($enviado) {
                $mensagem = '<div class="notice notice-success is-dismissible"><p>✅ Backup criado e enviado por email para <strong>' . esc_html(get_option('admin_email')) . '</strong>.</p></div>';
            } else {
                $mensagem = '<div class="notice notice-warning"><p>⚠️ Backup criado, mas o email pode não ter sido enviado. Verifique a configuração SMTP.</p></div>';
            }
        } else {
            $mensagem = '<div class="notice notice-success is-dismissible"><p>✅ Backup criado com sucesso.</p></div>';
        }
    }

    /* -------- Eliminar backup -------- */
    if (isset($_POST['rc_acao']) && $_POST['rc_acao'] === 'eliminar_backup') {
        check_admin_referer('rc_eliminar_backup');
        $timestamp = sanitize_text_field($_POST['timestamp'] ?? '');
        $dir = rc_crm_backup_dir();
        $ficheiros = glob($dir . '/*' . $timestamp . '.*');
        if (is_array($ficheiros)) {
            foreach ($ficheiros as $f) @unlink($f);
        }
        $mensagem = '<div class="notice notice-success is-dismissible"><p>🗑️ Backup eliminado.</p></div>';
    }

    /* -------- Download -------- */
    if (isset($_GET['download'], $_GET['timestamp'])) {
        check_admin_referer('rc_download_backup');

        $timestamp = sanitize_text_field($_GET['timestamp']);
        $tipo      = sanitize_text_field($_GET['download']);

        if (!in_array($tipo, ['clientes', 'servicos', 'config', 'info'], true)) {
            wp_die('Tipo de ficheiro inválido.');
        }

        $dir = rc_crm_backup_dir();
        $ficheiros = glob($dir . '/' . $tipo . '_' . $timestamp . '.*');
        if (!empty($ficheiros) && is_readable($ficheiros[0])) {
            $path = $ficheiros[0];
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        }
        wp_die('Ficheiro não encontrado.');
    }

    $backups = rc_crm_listar_backups();
    $ultimo  = get_option('rc_crm_ultimo_backup', '');
    $proximo = wp_next_scheduled('rc_crm_weekly_backup');
    ?>
    <div class="rc-backups">
        <?php echo $mensagem; ?>

        <div class="rc-backup-estado">
            <div>
                <div class="rc-backup-label">Último backup</div>
                <div class="rc-backup-valor azul">
                    <?php echo $ultimo ? esc_html(date_i18n('d/m/Y H:i', strtotime($ultimo))) : '—'; ?>
                </div>
            </div>
            <div>
                <div class="rc-backup-label">Próximo automático</div>
                <div class="rc-backup-valor roxo">
                    <?php echo $proximo ? esc_html(date_i18n('d/m/Y H:i', $proximo)) : '—'; ?>
                </div>
            </div>
            <div>
                <div class="rc-backup-label">Frequência</div>
                <div class="rc-backup-valor verde">Semanal (segunda, 03:00)</div>
            </div>
            <div>
                <div class="rc-backup-label">Retenção</div>
                <div class="rc-backup-valor laranja">Últimos <?php echo (int) RC_CRM_BACKUP_RETENCAO; ?> backups</div>
            </div>
        </div>

        <div class="rc-backup-acao">
            <h2 style="margin-top:0; font-size:16px;">➕ Criar Backup Manual</h2>
            <p class="description">Gera uma cópia imediata de clientes, serviços e configurações.</p>
            <form method="post" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <?php wp_nonce_field('rc_criar_backup'); ?>
                <input type="hidden" name="rc_acao" value="criar_backup">
                <button type="submit" class="button button-primary">💾 Criar Backup Agora</button>
                <label style="display:flex; align-items:center; gap:6px; font-weight:normal;">
                    <input type="checkbox" name="enviar_email" value="1" checked>
                    Enviar também por email para <strong><?php echo esc_html(get_option('admin_email')); ?></strong>
                </label>
            </form>
        </div>

        <h2>📂 Backups Existentes (<?php echo count($backups); ?>)</h2>

        <?php if (empty($backups)): ?>
            <p class="rc-backup-vazio">Nenhum backup encontrado. Clique em <strong>Criar Backup Agora</strong> para gerar o primeiro.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped" style="margin-top:12px;">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tamanho Total</th>
                        <th>Ficheiros</th>
                        <th style="width:340px;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($backups as $b):
                        $timestamp = $b['timestamp'];
                        $tipos_presentes = [];
                        foreach ($b['ficheiros'] as $f) {
                            $nome = basename($f);
                            if (strpos($nome, 'clientes_') === 0) $tipos_presentes[] = 'clientes';
                            elseif (strpos($nome, 'servicos_') === 0) $tipos_presentes[] = 'servicos';
                            elseif (strpos($nome, 'config_') === 0) $tipos_presentes[] = 'config';
                            elseif (strpos($nome, 'info_') === 0) $tipos_presentes[] = 'info';
                        }
                    ?>
                        <tr>
                            <td><strong>📅 <?php echo esc_html($b['data_formatada']); ?></strong></td>
                            <td><?php echo esc_html(size_format($b['tamanho_total'])); ?></td>
                            <td><?php echo count($b['ficheiros']); ?> ficheiros</td>
                            <td>
                                <?php foreach (['clientes' => '👥 Clientes', 'servicos' => '🔧 Serviços', 'config' => '⚙️ Config', 'info' => '📄 Info'] as $tipo => $label): ?>
                                    <?php if (in_array($tipo, $tipos_presentes, true)): ?>
                                        <a href="<?php echo esc_url(wp_nonce_url(
                                            admin_url('admin.php?page=rc-crm-config&tab=backups&download=' . $tipo . '&timestamp=' . $timestamp),
                                            'rc_download_backup'
                                        )); ?>" class="button button-small" style="margin:2px 2px 2px 0; font-size:11px;">
                                            ⬇️ <?php echo esc_html($label); ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endforeach; ?>

                                <form method="post" style="display:inline-block; margin-top:4px;" onsubmit="return confirm('Eliminar este backup permanentemente?');">
                                    <?php wp_nonce_field('rc_eliminar_backup'); ?>
                                    <input type="hidden" name="rc_acao" value="eliminar_backup">
                                    <input type="hidden" name="timestamp" value="<?php echo esc_attr($timestamp); ?>">
                                    <button type="submit" class="button button-small" style="color:#b32d2e; font-size:11px;">🗑️ Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="rc-backup-info">
            <h3 style="margin-top:0;">ℹ️ Como funciona</h3>
            <ul style="line-height:1.8;">
                <li>Um <strong>backup automático</strong> é gerado todas as segundas-feiras às 03:00.</li>
                <li>O backup inclui <strong>clientes, serviços e configurações</strong>.</li>
                <li>É enviado <strong>por email</strong> para <code><?php echo esc_html(get_option('admin_email')); ?></code> com os ficheiros em anexo (se couberem no limite do servidor).</li>
                <li>Os backups são guardados em <code>wp-content/uploads/rc-backups/</code> (pasta protegida por <code>.htaccess</code>).</li>
                <li>São mantidos apenas os <strong>últimos <?php echo (int) RC_CRM_BACKUP_RETENCAO; ?> backups</strong> — os mais antigos são eliminados automaticamente.</li>
                <li>Pode fazer download de qualquer ficheiro a qualquer momento.</li>
            </ul>
        </div>
    </div>
    <?php
}