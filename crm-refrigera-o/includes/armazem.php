<?php
/**
 * Refrigeração CRM — Armazém de Equipamentos
 *
 * @package Refrigeracao_CRM
 * @version 3.5.2
 */

if (!defined('ABSPATH')) exit;

function rc_crm_armazem_page() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');

    global $wpdb;
    $table   = $wpdb->prefix . 'rc_equipamentos_armazem';
    $table_c = $wpdb->prefix . 'rc_clientes';

    $mensagem = '';

    /* ============================================================
       AÇÃO: CRIAR EQUIPAMENTO
       ============================================================ */
    if (isset($_POST['rc_acao']) && $_POST['rc_acao'] === 'criar_equipamento') {
        check_admin_referer('rc_criar_equipamento');

        $cliente_id   = !empty($_POST['cliente_id'])   ? intval($_POST['cliente_id'])   : null;
        $servico_id   = !empty($_POST['servico_id'])   ? intval($_POST['servico_id'])   : null;
        $equipamento  = sanitize_text_field($_POST['equipamento'] ?? '');
        $referencia   = sanitize_text_field($_POST['referencia_worten'] ?? '');
        $tecnico_id   = !empty($_POST['tecnico_id'])   ? intval($_POST['tecnico_id'])   : null;
        $data_entrada = sanitize_text_field($_POST['data_entrada'] ?? '');
        $observacoes  = sanitize_textarea_field($_POST['observacoes'] ?? '');

        if (empty($equipamento) || empty($data_entrada)) {
            $mensagem = '<div class="notice notice-error"><p>❌ Equipamento e data de entrada são obrigatórios.</p></div>';
        } else {
            $resultado = $wpdb->insert($table, [
                'cliente_id'         => $cliente_id,
                'servico_id'         => $servico_id,
                'equipamento'        => $equipamento,
                'referencia_worten'  => $referencia,
                'tecnico_id'         => $tecnico_id,
                'data_entrada'       => $data_entrada,
                'estado'             => 'em_armazem',
                'observacoes'        => $observacoes,
            ]);

            if ($resultado === false) {
                error_log('RC CRM - Erro INSERT armazém: ' . $wpdb->last_error);
                $mensagem = '<div class="notice notice-error"><p>❌ Erro ao guardar: ' . esc_html($wpdb->last_error) . '</p></div>';
            } else {
                $mensagem = '<div class="notice notice-success is-dismissible"><p>✅ Equipamento adicionado ao armazém.</p></div>';
            }
        }
    }

    /* ============================================================
       AÇÃO: MARCAR COMO DEVOLVIDO
       ============================================================ */
    if (isset($_POST['rc_acao']) && $_POST['rc_acao'] === 'marcar_devolvido') {
        check_admin_referer('rc_marcar_devolvido');

        $id       = intval($_POST['equipamento_id'] ?? 0);
        $data_dev = sanitize_text_field($_POST['data_devolucao'] ?? '');
        if (empty($data_dev)) $data_dev = current_time('Y-m-d');

        // Verificar se está em armazém
        $estado_atual = $wpdb->get_var($wpdb->prepare(
            "SELECT estado FROM $table WHERE id = %d",
            $id
        ));

        if ($estado_atual !== 'em_armazem') {
            $mensagem = '<div class="notice notice-warning"><p>⚠️ Este equipamento já não está em armazém.</p></div>';
        } else {
            $wpdb->update($table, [
                'estado'         => 'devolvido',
                'data_devolucao' => $data_dev,
            ], ['id' => $id]);

            $mensagem = '<div class="notice notice-success is-dismissible"><p>✅ Equipamento marcado como devolvido.</p></div>';
        }
    }

    /* ============================================================
       AÇÃO: REVERTER DEVOLUÇÃO
       ============================================================ */
    if (isset($_POST['rc_acao']) && $_POST['rc_acao'] === 'reverter_devolucao') {
        check_admin_referer('rc_reverter_devolucao');

        $id = intval($_POST['equipamento_id'] ?? 0);

        $wpdb->update($table, [
            'estado'         => 'em_armazem',
            'data_devolucao' => null,
        ], ['id' => $id]);

        $mensagem = '<div class="notice notice-success is-dismissible"><p>↩️ Devolução revertida.</p></div>';
    }

    /* ============================================================
       AÇÃO: ELIMINAR EQUIPAMENTO
       ============================================================ */
    if (isset($_POST['rc_acao']) && $_POST['rc_acao'] === 'eliminar_equipamento') {
        check_admin_referer('rc_eliminar_equipamento');

        $id = intval($_POST['equipamento_id'] ?? 0);
        $wpdb->delete($table, ['id' => $id]);

        $mensagem = '<div class="notice notice-success is-dismissible"><p>🗑️ Equipamento eliminado.</p></div>';
    }

    /* ============================================================
       FILTROS
       ============================================================ */
    $filtro_estado  = isset($_GET['f_estado'])  ? sanitize_text_field($_GET['f_estado'])  : 'em_armazem';
    $filtro_cliente = isset($_GET['f_cliente']) ? intval($_GET['f_cliente'])                : 0;

    // Whitelist de estados
    $estados_validos = ['em_armazem', 'devolvido', 'alerta', 'todos'];
    if (!in_array($filtro_estado, $estados_validos, true)) {
        $filtro_estado = 'em_armazem';
    }

    $where  = [];
    $params = [];

    if ($filtro_estado === 'em_armazem') {
        $where[] = "e.estado = 'em_armazem'";
    } elseif ($filtro_estado === 'devolvido') {
        $where[] = "e.estado = 'devolvido'";
    } elseif ($filtro_estado === 'alerta') {
        $where[] = "e.estado = 'em_armazem' AND DATEDIFF(CURDATE(), e.data_entrada) > 30";
    }
    // 'todos' → sem filtro de estado

    if ($filtro_cliente) {
        $where[]  = "e.cliente_id = %d";
        $params[] = $filtro_cliente;
    }

    $sql_where = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT e.*, c.nome AS cliente_nome, c.codigo AS cliente_codigo,
                   DATEDIFF(CURDATE(), e.data_entrada) AS dias_em_armazem
            FROM $table e
            LEFT JOIN $table_c c ON e.cliente_id = c.id
            $sql_where
            ORDER BY CASE WHEN e.estado = 'em_armazem' THEN 0 ELSE 1 END, e.data_entrada ASC";

    if (!empty($params)) $sql = $wpdb->prepare($sql, $params);

    $equipamentos = $wpdb->get_results($sql);

    /* ============================================================
       TOTAIS
       ============================================================ */
    $total_em_armazem = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE estado = 'em_armazem'");
    $total_alerta     = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE estado = 'em_armazem' AND DATEDIFF(CURDATE(), data_entrada) > 30");

    // Devolvidos este mês (usa range para usar índice)
    $inicio_mes = wp_date('Y-m-01') . ' 00:00:00';
    $fim_mes    = wp_date('Y-m-t')  . ' 23:59:59';

    $total_devolvidos_mes = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table
         WHERE estado = 'devolvido'
           AND data_devolucao >= %s
           AND data_devolucao <= %s",
        $inicio_mes, $fim_mes
    ));

    /* ============================================================
       LISTAS AUXILIARES
       ============================================================ */
    $clientes     = $wpdb->get_results("SELECT id, codigo, nome FROM $table_c ORDER BY nome ASC");
    $utilizadores = function_exists('rc_crm_obter_tecnicos')
        ? rc_crm_obter_tecnicos()
        : get_users(['orderby' => 'display_name']);

    /* ============================================================
       CACHE DE TÉCNICOS (evitar N+1)
       ============================================================ */
    $tecnicos_cache = [];
    if (!empty($equipamentos)) {
        $ids = array_unique(array_filter(array_column($equipamentos, 'tecnico_id')));
        if (!empty($ids)) {
            $users = get_users(['include' => $ids, 'fields' => ['ID', 'display_name']]);
            foreach ($users as $u) {
                $tecnicos_cache[$u->ID] = $u->display_name;
            }
        }
    }
    ?>
    <div class="wrap">
        <h1>📦 Equipamentos em Armazém</h1>
        <p class="description">Controlo de equipamentos à espera de devolução (ex: cancelamentos Worten).</p>

        <?php echo $mensagem; ?>

        <!-- Cards de estatísticas -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin:20px 0;">
            <div style="background:#fff; border-left:4px solid #2271b1; border-radius:10px; padding:20px;">
                <div style="font-size:28px; font-weight:700; color:#2271b1; line-height:1;"><?php echo (int) $total_em_armazem; ?></div>
                <div style="font-size:12px; color:#666; text-transform:uppercase; margin-top:6px;">Em Armazém</div>
            </div>
            <div style="background:#fff; border-left:4px solid #b32d2e; border-radius:10px; padding:20px;">
                <div style="font-size:28px; font-weight:700; color:#b32d2e; line-height:1;"><?php echo (int) $total_alerta; ?></div>
                <div style="font-size:12px; color:#666; text-transform:uppercase; margin-top:6px;">⚠️ Há mais de 30 dias</div>
            </div>
            <div style="background:#fff; border-left:4px solid #00a32a; border-radius:10px; padding:20px;">
                <div style="font-size:28px; font-weight:700; color:#00a32a; line-height:1;"><?php echo (int) $total_devolvidos_mes; ?></div>
                <div style="font-size:12px; color:#666; text-transform:uppercase; margin-top:6px;">Devolvidos este mês</div>
            </div>
        </div>

        <!-- Formulário: adicionar equipamento -->
        <div style="background:#fff; border-left:4px solid #00a32a; border-radius:8px; padding:20px; margin:20px 0;">
            <h2 style="margin-top:0; font-size:16px;">➕ Adicionar Equipamento ao Armazém</h2>
            <form method="post">
                <?php wp_nonce_field('rc_criar_equipamento'); ?>
                <input type="hidden" name="rc_acao" value="criar_equipamento">

                <table class="form-table">
                    <tr>
                        <th style="width:180px;"><label for="arm_equipamento">Equipamento *</label></th>
                        <td><input type="text" id="arm_equipamento" name="equipamento" class="large-text" required placeholder="Ex: Ar condicionado Samsung 12000 BTU"></td>
                    </tr>
                    <tr>
                        <th><label for="arm_cliente">Cliente Original</label></th>
                        <td>
                            <select id="arm_cliente" name="cliente_id" class="regular-text">
                                <option value="">— Nenhum —</option>
                                <?php foreach ($clientes as $c): ?>
                                    <option value="<?php echo esc_attr($c->id); ?>"><?php echo esc_html($c->codigo . ' — ' . $c->nome); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="arm_referencia">Referência Worten</label></th>
                        <td><input type="text" id="arm_referencia" name="referencia_worten" class="regular-text" placeholder="Ex: WRT-2026-00123"></td>
                    </tr>
                    <tr>
                        <th><label for="arm_tecnico">Técnico Responsável</label></th>
                        <td>
                            <select id="arm_tecnico" name="tecnico_id" class="regular-text">
                                <option value="">— Nenhum —</option>
                                <?php foreach ($utilizadores as $u): ?>
                                    <option value="<?php echo esc_attr($u->ID); ?>"><?php echo esc_html($u->display_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="arm_data">Data de Entrada *</label></th>
                        <td><input type="date" id="arm_data" name="data_entrada" value="<?php echo esc_attr(current_time('Y-m-d')); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="arm_obs">Observações</label></th>
                        <td><textarea id="arm_obs" name="observacoes" rows="3" class="large-text"></textarea></td>
                    </tr>
                </table>

                <p><button type="submit" class="button button-primary">➕ Adicionar ao Armazém</button></p>
            </form>
        </div>

        <!-- Filtros -->
        <form method="get" style="background:#fff; border:1px solid #ddd; border-radius:8px; padding:16px; margin:20px 0; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            <input type="hidden" name="page" value="rc-crm-armazem">

            <label>Estado:</label>
            <select name="f_estado">
                <option value="em_armazem" <?php selected($filtro_estado, 'em_armazem'); ?>>📦 Em Armazém</option>
                <option value="alerta"     <?php selected($filtro_estado, 'alerta'); ?>>⚠️ Mais de 30 dias</option>
                <option value="devolvido"  <?php selected($filtro_estado, 'devolvido'); ?>>✅ Devolvidos</option>
                <option value="todos"      <?php selected($filtro_estado, 'todos'); ?>>📋 Todos</option>
            </select>

            <label>Cliente:</label>
            <select name="f_cliente">
                <option value="0">👥 Todos</option>
                <?php foreach ($clientes as $c): ?>
                    <option value="<?php echo esc_attr($c->id); ?>" <?php selected($filtro_cliente, $c->id); ?>><?php echo esc_html($c->codigo . ' — ' . $c->nome); ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="button">🔍 Filtrar</button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=rc-crm-armazem')); ?>" class="button">✕ Limpar</a>
        </form>

        <!-- Tabela -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Estado</th>
                    <th>Equipamento</th>
                    <th>Referência</th>
                    <th>Cliente Original</th>
                    <th>Técnico</th>
                    <th>Data Entrada</th>
                    <th>Dias em Armazém</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($equipamentos)): ?>
                    <tr><td colspan="8" style="text-align:center; color:#999; padding:40px;">Nenhum equipamento registado.</td></tr>
                <?php else: ?>
                    <?php foreach ($equipamentos as $e):
                        $dias   = (int) $e->dias_em_armazem;
                        $alerta = ($e->estado === 'em_armazem' && $dias > 30);
                        $nome_tecnico = !empty($e->tecnico_id) ? ($tecnicos_cache[$e->tecnico_id] ?? '—') : '—';
                    ?>
                        <tr style="<?php echo $alerta ? 'background:#fff5f5 !important;' : ''; ?>">
                            <td>
                                <?php if ($e->estado === 'devolvido'): ?>
                                    <span style="background:#00a32a; color:#fff; padding:3px 10px; border-radius:10px; font-size:11px; font-weight:600;">✅ Devolvido</span>
                                <?php elseif ($alerta): ?>
                                    <span style="background:#b32d2e; color:#fff; padding:3px 10px; border-radius:10px; font-size:11px; font-weight:600;">⚠️ Alerta</span>
                                <?php else: ?>
                                    <span style="background:#2271b1; color:#fff; padding:3px 10px; border-radius:10px; font-size:11px; font-weight:600;">📦 Em Armazém</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo esc_html($e->equipamento); ?></strong></td>
                            <td><?php echo $e->referencia_worten ? esc_html($e->referencia_worten) : '—'; ?></td>
                            <td><?php echo $e->cliente_nome ? esc_html($e->cliente_codigo . ' — ' . $e->cliente_nome) : '—'; ?></td>
                            <td><?php echo esc_html($nome_tecnico); ?></td>
                            <td><?php echo esc_html(date_i18n('d/m/Y', strtotime($e->data_entrada))); ?></td>
                            <td>
                                <?php if ($e->estado === 'devolvido'): ?>
                                    <span style="color:#999;">—</span>
                                <?php else: ?>
                                    <strong style="color:<?php echo $alerta ? '#b32d2e' : '#333'; ?>;"><?php echo $dias; ?> dias</strong>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($e->estado === 'em_armazem'): ?>
                                    <form method="post" style="display:inline-block; margin:0;" onsubmit="return confirm('Confirmar devolução?');">
                                        <?php wp_nonce_field('rc_marcar_devolvido'); ?>
                                        <input type="hidden" name="rc_acao" value="marcar_devolvido">
                                        <input type="hidden" name="equipamento_id" value="<?php echo esc_attr($e->id); ?>">
                                        <input type="hidden" name="data_devolucao" value="<?php echo esc_attr(current_time('Y-m-d')); ?>">
                                        <button type="submit" class="button button-primary" style="font-size:11px;">✅ Devolvido</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" style="display:inline-block; margin:0;" onsubmit="return confirm('Reverter devolução?');">
                                        <?php wp_nonce_field('rc_reverter_devolucao'); ?>
                                        <input type="hidden" name="rc_acao" value="reverter_devolucao">
                                        <input type="hidden" name="equipamento_id" value="<?php echo esc_attr($e->id); ?>">
                                        <button type="submit" class="button" style="font-size:11px;">↩️ Reverter</button>
                                    </form>
                                <?php endif; ?>

                                <form method="post" style="display:inline-block; margin:0 0 0 4px;" onsubmit="return confirm('Eliminar este registo?');">
                                    <?php wp_nonce_field('rc_eliminar_equipamento'); ?>
                                    <input type="hidden" name="rc_acao" value="eliminar_equipamento">
                                    <input type="hidden" name="equipamento_id" value="<?php echo esc_attr($e->id); ?>">
                                    <button type="submit" class="button" style="color:#b32d2e; font-size:11px;" title="Eliminar registo">🗑️</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}