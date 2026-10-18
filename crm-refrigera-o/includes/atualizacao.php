<?php
/**
 * Refrigeração CRM — Atualizações via ZIP
 *
 * @package Refrigeracao_CRM
 * @version 3.5.1
 */

if (!defined('ABSPATH')) exit;

if (!defined('RC_CRM_BACKUP_UPDATES_DIR')) {
    define('RC_CRM_BACKUP_UPDATES_DIR', 'rc-updates');
}
if (!defined('RC_CRM_MAX_ZIP_SIZE')) {
    define('RC_CRM_MAX_ZIP_SIZE', 52428800); // 50 MB
}

/* ============================================================
   LOG
   ============================================================ */
function rc_crm_registar_update($tipo, $versao_de, $versao_para, $mensagem, $sucesso = true) {
    $log = get_option('rc_crm_update_log', []);
    if (!is_array($log)) $log = [];

    $log[] = [
        'data'        => current_time('mysql'),
        'tipo'        => $tipo,
        'versao_de'   => $versao_de,
        'versao_para' => $versao_para,
        'mensagem'    => $mensagem,
        'sucesso'     => (bool) $sucesso,
        'user'        => wp_get_current_user()->display_name,
    ];

    if (count($log) > 20) {
        $log = array_slice($log, -20);
    }

    update_option('rc_crm_update_log', $log);
}

/* ============================================================
   DIRETÓRIO DE BACKUPS DE UPDATE
   ============================================================ */
function rc_crm_get_updates_backup_dir() {
    $upload = wp_upload_dir();
    $dir = $upload['basedir'] . '/' . RC_CRM_BACKUP_UPDATES_DIR;
    if (!file_exists($dir)) {
        wp_mkdir_p($dir);
    }
    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Deny from all\n");
    }
    return $dir;
}

/* ============================================================
   PÁGINA (render dentro da aba Atualizações)
   ============================================================ */
function rc_crm_atualizacoes_page() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');

    $mensagem = '';
    $tipo_msg = 'success';

    /* ── UPLOAD DE UPDATE ─────────────────────────────────── */
    if (isset($_POST['rc_acao']) && $_POST['rc_acao'] === 'upload_update') {
        check_admin_referer('rc_upload_update');
        if (!isset($_FILES['update_zip']) || $_FILES['update_zip']['error'] !== UPLOAD_ERR_OK) {
            $mensagem = '❌ Nenhum ficheiro recebido.';
            $tipo_msg = 'error';
        } else {
            $resultado = rc_crm_processar_update_zip($_FILES['update_zip']);
            $mensagem = $resultado['mensagem'];
            $tipo_msg = $resultado['sucesso'] ? 'success' : 'error';
        }
    }

    /* ── LIMPAR LOG ───────────────────────────────────────── */
    if (isset($_POST['rc_acao']) && $_POST['rc_acao'] === 'limpar_log') {
        check_admin_referer('rc_limpar_log');
        delete_option('rc_crm_update_log');
        $mensagem = '✅ Log de atualizações limpo.';
    }

    /* ── LIMPAR BACKUPS ───────────────────────────────────── */
    if (isset($_POST['rc_acao']) && $_POST['rc_acao'] === 'limpar_backups_update') {
        check_admin_referer('rc_limpar_backups_update');
        $dir = rc_crm_get_updates_backup_dir();
        $ficheiros = glob($dir . '/*');
        if (!is_array($ficheiros)) $ficheiros = [];

        $apagados = 0;
        foreach ($ficheiros as $f) {
            if (is_file($f) && basename($f) !== '.htaccess') {
                if (@unlink($f)) $apagados++;
            }
        }
        $mensagem = "✅ {$apagados} backup(s) eliminado(s).";
    }

    /* ── INFO ─────────────────────────────────────────────── */
    $versao_ativa       = defined('RC_CRM_VERSION') ? RC_CRM_VERSION : '—';
    $ultima_atualizacao = get_option('rc_crm_ultima_atualizacao', '');
    $log = get_option('rc_crm_update_log', []);
    if (!is_array($log)) $log = [];

    $dir_updates = rc_crm_get_updates_backup_dir();
    $backups_update = [];
    if (is_dir($dir_updates)) {
        $files = glob($dir_updates . '/*');
        if (is_array($files)) {
            $backups_update = array_filter($files, function($f) {
                return is_file($f) && basename($f) !== '.htaccess';
            });
        }
    }
    ?>
    <div class="rc-atualizacoes">
        <?php if ($mensagem): ?>
            <div class="notice notice-<?php echo esc_attr($tipo_msg); ?> is-dismissible">
                <p><?php echo wp_kses_post($mensagem); ?></p>
            </div>
        <?php endif; ?>

        <div class="rc-update-estado">
            <div class="rc-update-estado-item">
                <div class="rc-update-label">Versão Ativa</div>
                <div class="rc-update-valor azul"><?php echo esc_html($versao_ativa); ?></div>
            </div>
            <div class="rc-update-estado-item">
                <div class="rc-update-label">Última Atualização</div>
                <div class="rc-update-valor verde">
                    <?php echo $ultima_atualizacao ? esc_html(date_i18n('d/m/Y H:i', strtotime($ultima_atualizacao))) : '—'; ?>
                </div>
            </div>
            <div class="rc-update-estado-item">
                <div class="rc-update-label">Backups de Update</div>
                <div class="rc-update-valor roxo"><?php echo count($backups_update); ?></div>
            </div>
            <div class="rc-update-estado-item">
                <div class="rc-update-label">PHP / WP</div>
                <div class="rc-update-valor laranja"><?php echo esc_html(PHP_VERSION); ?> / <?php echo esc_html(get_bloginfo('version')); ?></div>
            </div>
        </div>

        <div class="rc-update-info">
            <h3>📋 Como atualizar o plugin</h3>
            <ol>
                <li><strong>Crie o ZIP</strong> com a nova versão do plugin.</li>
                <li>O ZIP deve conter o ficheiro <code>refrigeracao-crm.php</code> na raiz (ou dentro de uma pasta <code>refrigeracao-crm/</code>).</li>
                <li>Faça o upload abaixo e clique em <strong>🔄 Atualizar</strong>.</li>
                <li>O sistema faz <strong>backup automático</strong> antes de substituir.</li>
                <li>Se algo falhar, o sistema faz <strong>rollback automático</strong>.</li>
            </ol>
            <p><strong>⚠️ A base de dados NÃO é afetada</strong> — apenas os ficheiros do plugin são substituídos.</p>
            <p>💡 Para <strong>restaurar dados</strong> (CSV), use a aba <strong>♻️ Restaurar</strong>.</p>
        </div>

        <div class="rc-update-acao">
            <h2>📦 Atualizar via ZIP</h2>
            <form method="post" enctype="multipart/form-data" onsubmit="return confirm('Confirmar atualização?\n\nO sistema fará backup automático antes.\n\nContinuar?');">
                <?php wp_nonce_field('rc_upload_update'); ?>
                <input type="hidden" name="rc_acao" value="upload_update">

                <div class="rc-update-upload">
                    <input type="file" name="update_zip" accept=".zip" required id="rc_update_zip">
                    <label for="rc_update_zip" class="rc-update-file-label">
                        <span class="rc-update-file-icon">📁</span>
                        <span class="rc-update-file-text">Escolher ficheiro ZIP</span>
                    </label>
                    <span id="rc_update_file_name" class="rc-update-file-name">Nenhum ficheiro selecionado</span>
                </div>

                <p>
                    <button type="submit" class="button button-primary button-hero">🔄 Atualizar Plugin</button>
                </p>
            </form>
        </div>

        <?php if (!empty($backups_update)): ?>
            <div class="rc-update-acao">
                <h2>💾 Backups de Update Existentes (<?php echo count($backups_update); ?>)</h2>

                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>Ficheiro</th><th>Tamanho</th><th>Data</th></tr></thead>
                    <tbody>
                        <?php foreach ($backups_update as $b): ?>
                            <tr>
                                <td><code><?php echo esc_html(basename($b)); ?></code></td>
                                <td><?php echo esc_html(size_format(@filesize($b))); ?></td>
                                <td><?php echo esc_html(date_i18n('d/m/Y H:i', @filemtime($b))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <form method="post" style="margin-top:12px;" onsubmit="return confirm('Eliminar todos os backups de update?');">
                    <?php wp_nonce_field('rc_limpar_backups_update'); ?>
                    <input type="hidden" name="rc_acao" value="limpar_backups_update">
                    <button type="submit" class="button" style="color:#b32d2e;">🗑️ Eliminar Todos</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if (!empty($log)): ?>
            <div class="rc-update-acao">
                <h2>📜 Histórico de Atualizações</h2>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr><th>Data</th><th>Tipo</th><th>Versão</th><th>Mensagem</th><th>Utilizador</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_reverse($log) as $entrada): ?>
                            <tr>
                                <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($entrada['data']))); ?></td>
                                <td>
                                    <?php if (!empty($entrada['sucesso'])): ?>
                                        <span style="color:#00a32a; font-weight:600;">✅ <?php echo esc_html($entrada['tipo']); ?></span>
                                    <?php else: ?>
                                        <span style="color:#b32d2e; font-weight:600;">❌ <?php echo esc_html($entrada['tipo']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($entrada['versao_de']) && !empty($entrada['versao_para'])): ?>
                                        <?php echo esc_html($entrada['versao_de']); ?> → <strong><?php echo esc_html($entrada['versao_para']); ?></strong>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td><?php echo esc_html($entrada['mensagem']); ?></td>
                                <td><?php echo esc_html($entrada['user']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <form method="post" style="margin-top:12px;" onsubmit="return confirm('Limpar o log?');">
                    <?php wp_nonce_field('rc_limpar_log'); ?>
                    <input type="hidden" name="rc_acao" value="limpar_log">
                    <button type="submit" class="button">🗑️ Limpar Histórico</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const input = document.getElementById('rc_update_zip');
        const nome  = document.getElementById('rc_update_file_name');
        if (input && nome) {
            input.addEventListener('change', function() {
                nome.textContent = this.files[0] ? this.files[0].name : 'Nenhum ficheiro selecionado';
                nome.style.color = this.files[0] ? '#1877F2' : '#999';
                nome.style.fontWeight = this.files[0] ? '600' : '400';
            });
        }
    });
    </script>
    <?php
}

/* ============================================================
   PROCESSAR UPDATE ZIP
   ============================================================ */
function rc_crm_processar_update_zip($file) {
    if ($file['size'] > RC_CRM_MAX_ZIP_SIZE) {
        return ['sucesso' => false, 'mensagem' => '❌ Ficheiro demasiado grande (máx. 50 MB).'];
    }

    if (!preg_match('/\.zip$/i', $file['name'])) {
        return ['sucesso' => false, 'mensagem' => '❌ Ficheiro deve ter extensão .zip.'];
    }

    if (!class_exists('ZipArchive')) {
        return ['sucesso' => false, 'mensagem' => '❌ ZipArchive não disponível no servidor.'];
    }

    $plugin_dir = RC_CRM_PATH;
    if (!is_writable($plugin_dir)) {
        return ['sucesso' => false, 'mensagem' => '❌ A pasta do plugin não tem permissões de escrita. Contacte o seu hosting.'];
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/misc.php';

    $upload = wp_handle_upload($file, ['test_form' => false, 'mimes' => ['zip' => 'application/zip']]);

    if (isset($upload['error'])) {
        return ['sucesso' => false, 'mensagem' => '❌ Erro no upload: ' . $upload['error']];
    }

    $zip_path = $upload['file'];
    $tmp_dir  = trailingslashit(get_temp_dir()) . 'rc_update_' . time() . '_' . wp_generate_password(6, false);

    if (!wp_mkdir_p($tmp_dir)) {
        @unlink($zip_path);
        return ['sucesso' => false, 'mensagem' => '❌ Não foi possível criar pasta temporária.'];
    }

    $unzip = unzip_file($zip_path, $tmp_dir);

    if (is_wp_error($unzip)) {
        @unlink($zip_path);
        rc_crm_rmdir_recursive($tmp_dir);
        return ['sucesso' => false, 'mensagem' => '❌ Erro a descomprimir: ' . $unzip->get_error_message()];
    }

    $raiz_plugin = rc_crm_localizar_raiz_plugin($tmp_dir);

    if (!$raiz_plugin) {
        @unlink($zip_path);
        rc_crm_rmdir_recursive($tmp_dir);
        return ['sucesso' => false, 'mensagem' => '❌ ZIP inválido: não foi encontrado <code>refrigeracao-crm.php</code>.'];
    }

    $novo_ficheiro_principal = trailingslashit($raiz_plugin) . 'refrigeracao-crm.php';
    $nova_versao  = rc_crm_extrair_versao_do_ficheiro($novo_ficheiro_principal);
    $versao_atual = defined('RC_CRM_VERSION') ? RC_CRM_VERSION : '0.0.0';

    /* -------- Backup do plugin atual -------- */
    $backup_dir  = rc_crm_get_updates_backup_dir();
    $backup_name = 'backup-v' . $versao_atual . '-' . date('Y-m-d_H-i-s') . '.zip';
    $backup_path = $backup_dir . '/' . $backup_name;

    $backup_resultado = rc_crm_criar_backup_plugin($backup_path);

    if (!$backup_resultado['sucesso']) {
        @unlink($zip_path);
        rc_crm_rmdir_recursive($tmp_dir);
        return ['sucesso' => false, 'mensagem' => '❌ Falha ao criar backup: ' . $backup_resultado['mensagem']];
    }

    /* -------- Backup interno (para rollback) -------- */
    $backup_interno = trailingslashit(get_temp_dir()) . 'rc_plugin_backup_' . time() . '_' . wp_generate_password(6, false);
    wp_mkdir_p($backup_interno);

    $itens_substituir = ['refrigeracao-crm.php', 'includes', 'assets'];

    foreach ($itens_substituir as $item) {
        $origem  = $plugin_dir . $item;
        $destino = $backup_interno . '/' . $item;
        if (file_exists($origem)) {
            rc_crm_copy_recursive($origem, $destino);
        }
    }

    /* -------- Substituir -------- */
    $erros = [];
    foreach ($itens_substituir as $item) {
        $origem_novo = trailingslashit($raiz_plugin) . $item;
        $destino     = $plugin_dir . $item;

        if (!file_exists($origem_novo)) continue;

        if (file_exists($destino)) {
            if (is_dir($destino)) rc_crm_rmdir_recursive($destino);
            else @unlink($destino);
        }

        if (is_dir($origem_novo)) {
            $ok = rc_crm_copy_recursive($origem_novo, $destino);
            if (!$ok) $erros[] = "Falha em: {$item}";
        } else {
            $ok = @copy($origem_novo, $destino);
            if (!$ok) $erros[] = "Falha em: {$item}";
        }
    }

    /* -------- Rollback se houver erros -------- */
    if (!empty($erros)) {
        foreach ($itens_substituir as $item) {
            $origem_backup = $backup_interno . '/' . $item;
            $destino = $plugin_dir . $item;
            if (file_exists($origem_backup)) {
                if (file_exists($destino)) {
                    if (is_dir($destino)) rc_crm_rmdir_recursive($destino);
                    else @unlink($destino);
                }
                rc_crm_copy_recursive($origem_backup, $destino);
            }
        }

        @unlink($zip_path);
        rc_crm_rmdir_recursive($tmp_dir);
        rc_crm_rmdir_recursive($backup_interno);

        rc_crm_registar_update('Update (rollback)', $versao_atual, $nova_versao, 'Falha: ' . implode(', ', $erros), false);

        return ['sucesso' => false, 'mensagem' => '❌ <strong>Erro na atualização</strong>. Rollback automático efetuado.<br><br>Erros: ' . implode('<br>', $erros)];
    }

    update_option('rc_crm_ultima_atualizacao', current_time('mysql'));
    update_option('rc_crm_ultima_versao_backup', $backup_name);
    rc_crm_registar_update('Update ZIP', $versao_atual, $nova_versao, 'Backup: ' . $backup_name, true);

    @unlink($zip_path);
    rc_crm_rmdir_recursive($tmp_dir);
    rc_crm_rmdir_recursive($backup_interno);

    return [
        'sucesso'  => true,
        'mensagem' => '✅ <strong>Atualização concluída!</strong><br><br>' .
                      '<strong>Versão anterior:</strong> ' . esc_html($versao_atual) . '<br>' .
                      '<strong>Nova versão:</strong> ' . esc_html($nova_versao) . '<br>' .
                      '<strong>Backup:</strong> <code>' . esc_html($backup_name) . '</code>'
    ];
}

/* ============================================================
   LOCALIZAR RAIZ DO PLUGIN
   ============================================================ */
function rc_crm_localizar_raiz_plugin($dir) {
    if (file_exists($dir . '/refrigeracao-crm.php')) return $dir;

    $subpastas = glob($dir . '/*', GLOB_ONLYDIR);
    if (is_array($subpastas)) {
        foreach ($subpastas as $sub) {
            if (file_exists($sub . '/refrigeracao-crm.php')) return $sub;
        }
    }

    if (is_array($subpastas)) {
        foreach ($subpastas as $sub) {
            $subsub = glob($sub . '/*', GLOB_ONLYDIR);
            if (is_array($subsub)) {
                foreach ($subsub as $s) {
                    if (file_exists($s . '/refrigeracao-crm.php')) return $s;
                }
            }
        }
    }

    return false;
}

/* ============================================================
   EXTRAIR VERSÃO
   ============================================================ */
function rc_crm_extrair_versao_do_ficheiro($ficheiro) {
    if (!file_exists($ficheiro)) return '0.0.0';

    $fp = @fopen($ficheiro, 'r');
    if (!$fp) return '0.0.0';

    $cabecalho = fread($fp, 2048);
    fclose($fp);

    if (!$cabecalho) return '0.0.0';

    if (preg_match('/Version:\s*([\d\.]+)/i', $cabecalho, $m)) {
        return trim($m[1]);
    }

    return '0.0.0';
}

/* ============================================================
   CRIAR BACKUP DO PLUGIN
   ============================================================ */
function rc_crm_criar_backup_plugin($zip_path) {
    if (!class_exists('ZipArchive')) {
        return ['sucesso' => false, 'mensagem' => 'ZipArchive indisponível.'];
    }

    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return ['sucesso' => false, 'mensagem' => 'Não foi possível criar o ZIP.'];
    }

    $plugin_dir = rtrim(RC_CRM_PATH, '/\\');
    if (!is_dir($plugin_dir)) {
        $zip->close();
        return ['sucesso' => false, 'mensagem' => 'Pasta do plugin não encontrada.'];
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($plugin_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    $adicionados = 0;
    foreach ($iterator as $item) {
        $path = $item->getRealPath();
        $relative = 'refrigeracao-crm/' . ltrim(str_replace($plugin_dir, '', $path), '/\\');
        $relative = str_replace('\\', '/', $relative);

        if ($item->isDir()) {
            $zip->addEmptyDir($relative);
        } else {
            if ($zip->addFile($path, $relative)) $adicionados++;
        }
    }

    $zip->close();

    if (!file_exists($zip_path) || filesize($zip_path) === 0) {
        return ['sucesso' => false, 'mensagem' => 'ZIP vazio.'];
    }

    return ['sucesso' => true, 'mensagem' => "Backup com {$adicionados} ficheiros."];
}

/* ============================================================
   RESTAURO CSV — função chamada pela aba Restaurar
   (o formulário está em rc-crm-config.php)
   ============================================================ */
function rc_crm_processar_restauro($tipo, $file) {
    global $wpdb;

    if (!in_array($tipo, ['clientes', 'servicos'], true)) {
        return ['sucesso' => false, 'mensagem' => '❌ Tipo inválido.'];
    }

    $conteudo = @file_get_contents($file['tmp_name']);
    if ($conteudo === false) {
        return ['sucesso' => false, 'mensagem' => '❌ Não foi possível ler o ficheiro.'];
    }

    $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo);

    $primeira_linha = strtok($conteudo, "\n");
    $separador = (substr_count($primeira_linha, ';') >= substr_count($primeira_linha, ',')) ? ';' : ',';

    $linhas = [];
    $fp = fopen('php://temp', 'r+');
    fwrite($fp, $conteudo);
    rewind($fp);
    while (($linha = fgetcsv($fp, 0, $separador)) !== false) {
        $linhas[] = $linha;
    }
    fclose($fp);

    if (count($linhas) < 2) {
        return ['sucesso' => false, 'mensagem' => '❌ Ficheiro vazio.'];
    }

    $cabecalho   = array_shift($linhas);
    $inseridos   = 0;
    $atualizados = 0;
    $erros       = 0;

    if ($tipo === 'clientes') {
        $table = $wpdb->prefix . 'rc_clientes';

        foreach ($linhas as $linha) {
            if (count($linha) !== count($cabecalho)) continue;

            $dados = @array_combine($cabecalho, $linha);
            if (!$dados || empty($dados['codigo']) || empty($dados['nome'])) {
                $erros++;
                continue;
            }

            $dados_limpos = [
                'codigo'       => sanitize_text_field($dados['codigo']),
                'nome'         => sanitize_text_field($dados['nome']),
                'telefone'     => !empty($dados['telefone']) ? sanitize_text_field($dados['telefone']) : '',
                'email'        => !empty($dados['email']) ? sanitize_email($dados['email']) : '',
                'nif'          => !empty($dados['nif']) ? preg_replace('/\D/', '', $dados['nif']) : '',
                'tipo_cliente' => !empty($dados['tipo_cliente']) ? sanitize_text_field($dados['tipo_cliente']) : 'particular',
                'endereco'     => !empty($dados['endereco']) ? sanitize_text_field($dados['endereco']) : '',
                'complemento'  => !empty($dados['complemento']) ? sanitize_text_field($dados['complemento']) : '',
                'cidade'       => !empty($dados['cidade']) ? sanitize_text_field($dados['cidade']) : '',
                'cep'          => !empty($dados['cep']) ? sanitize_text_field($dados['cep']) : '',
                'latitude'     => !empty($dados['latitude']) ? floatval($dados['latitude']) : null,
                'longitude'    => !empty($dados['longitude']) ? floatval($dados['longitude']) : null,
                'observacoes'  => !empty($dados['observacoes']) ? sanitize_textarea_field($dados['observacoes']) : '',
            ];

            $existente_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE codigo = %s",
                $dados_limpos['codigo']
            ));

            if ($existente_id) {
                $wpdb->update($table, $dados_limpos, ['id' => $existente_id]);
                $atualizados++;
            } else {
                $wpdb->insert($table, $dados_limpos);
                $inseridos++;
            }
        }

        rc_crm_registar_update('Restauro Clientes', '—', '—', "{$inseridos} novos, {$atualizados} atualizados", true);

        return [
            'sucesso'  => true,
            'mensagem' => "✅ <strong>Restauro de Clientes!</strong><br>🆕 Inseridos: {$inseridos}<br>🔄 Atualizados: {$atualizados}<br>⚠️ Ignorados: {$erros}"
        ];
    }

    if ($tipo === 'servicos') {
        $table          = $wpdb->prefix . 'rc_servicos';
        $table_clientes = $wpdb->prefix . 'rc_clientes';

        $servicos_unicos = [];
        foreach ($linhas as $linha) {
            if (count($linha) !== count($cabecalho)) continue;
            $dados = @array_combine($cabecalho, $linha);
            if (!$dados || empty($dados['servico_id'])) continue;

            $sid = intval($dados['servico_id']);
            if (!isset($servicos_unicos[$sid])) $servicos_unicos[$sid] = $dados;
        }

        foreach ($servicos_unicos as $sid => $dados) {
            $cliente_id = 0;
            if (!empty($dados['cliente_codigo'])) {
                $cliente_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table_clientes WHERE codigo = %s",
                    sanitize_text_field($dados['cliente_codigo'])
                ));
            }
            if (!$cliente_id) { $erros++; continue; }

            $wpdb->insert($table, [
                'cliente_id'    => intval($cliente_id),
                'equipamento'   => !empty($dados['equipamento']) ? sanitize_text_field($dados['equipamento']) : '',
                'status'        => !empty($dados['status']) ? sanitize_text_field($dados['status']) : 'instalacao',
                'data_agendada' => !empty($dados['data_agendada']) ? $dados['data_agendada'] : null,
                'orcamento'     => !empty($dados['subtotal']) ? floatval(str_replace(',', '.', $dados['subtotal'])) : 0,
                'observacoes'   => !empty($dados['observacoes']) ? sanitize_textarea_field($dados['observacoes']) : '',
            ]);
            $inseridos++;
        }

        rc_crm_registar_update('Restauro Serviços', '—', '—', "{$inseridos} novos, {$erros} erros", true);

        return [
            'sucesso'  => true,
            'mensagem' => "✅ <strong>Restauro de Serviços!</strong><br>🆕 Serviços: {$inseridos}<br>⚠️ Ignorados: {$erros}"
        ];
    }

    return ['sucesso' => false, 'mensagem' => '❌ Tipo não suportado.'];
}

/* ============================================================
   AUXILIARES DE FICHEIROS
   ============================================================ */
if (!function_exists('rc_crm_rmdir_recursive')) {
    function rc_crm_rmdir_recursive($dir) {
        if (!is_dir($dir)) return false;
        $itens = @scandir($dir);
        if (!$itens) return false;

        foreach ($itens as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (is_dir($path)) rc_crm_rmdir_recursive($path);
            else @unlink($path);
        }
        return @rmdir($dir);
    }
}

if (!function_exists('rc_crm_copy_recursive')) {
    function rc_crm_copy_recursive($origem, $destino) {
        if (is_file($origem)) return @copy($origem, $destino);
        if (!is_dir($origem)) return false;

        if (!is_dir($destino)) {
            if (!wp_mkdir_p($destino)) return false;
        }

        $itens = @scandir($origem);
        if (!$itens) return false;

        $sucesso = true;
        foreach ($itens as $item) {
            if ($item === '.' || $item === '..') continue;
            $src = $origem . '/' . $item;
            $dst = $destino . '/' . $item;

            if (is_dir($src)) {
                if (!rc_crm_copy_recursive($src, $dst)) $sucesso = false;
            } else {
                if (!@copy($src, $dst)) $sucesso = false;
            }
        }
        return $sucesso;
    }
}

/* ============================================================
   VERIFICAÇÃO DE VERSÃO
   ============================================================ */
add_action('admin_init', 'rc_crm_update_verificar_versao_instalada');
function rc_crm_update_verificar_versao_instalada() {
    $versao_registada = get_option('rc_crm_versao_instalada', '');
    $versao_atual = defined('RC_CRM_VERSION') ? RC_CRM_VERSION : '';

    if ($versao_registada !== $versao_atual) {
        update_option('rc_crm_versao_instalada', $versao_atual);
    }
}