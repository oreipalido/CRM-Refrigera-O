<?php
/**
 * Refrigeração CRM — Comissões
 *
 * @package Refrigeracao_CRM
 * @version 3.5.0
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
   CÁLCULO
   ============================================================ */
function rc_crm_obter_comissao_tecnico($tecnico_id) {
    if (empty($tecnico_id)) return 0;

    $override = get_user_meta($tecnico_id, 'rc_comissao_percentual', true);
    if ($override !== '' && $override !== false && is_numeric($override)) {
        return max(0, min(100, floatval($override)));
    }

    $config = rc_crm_get_config();
    return max(0, min(100, floatval($config['comissao_global'] ?? 0)));
}

function rc_crm_calcular_comissao_servico($servico_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'rc_servicos';

    $servico = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $servico_id
    ));
    if (!$servico) return false;

    if ($servico->status !== 'concluido') return false;

    if (empty($servico->tecnico_id)) {
        $wpdb->update($table, [
            'comissao_percentual' => 0,
            'comissao_valor'      => 0,
        ], ['id' => $servico_id]);
        return false;
    }

    $percentual  = rc_crm_obter_comissao_tecnico($servico->tecnico_id);
    $valor_total = floatval($servico->orcamento);
    $comissao    = round($valor_total * ($percentual / 100), 2);

    $wpdb->update($table, [
        'comissao_percentual' => $percentual,
        'comissao_valor'      => $comissao,
    ], ['id' => $servico_id]);

    return true;
}

function rc_crm_marcar_comissao_paga($servico_id, $pagar = true) {
    global $wpdb;
    $table = $wpdb->prefix . 'rc_servicos';

    // Só permite marcar/desmarcar serviços concluídos
    $status = $wpdb->get_var($wpdb->prepare(
        "SELECT status FROM $table WHERE id = %d",
        $servico_id
    ));

    if ($status !== 'concluido') return false;

    return $wpdb->update($table, [
        'comissao_paga_em' => $pagar ? current_time('mysql') : null,
    ], ['id' => $servico_id]) !== false;
}

/* ============================================================
   PERFIL DO UTILIZADOR — Campo de comissão
   ============================================================ */
add_action('show_user_profile', 'rc_crm_campo_comissao_perfil');
add_action('edit_user_profile', 'rc_crm_campo_comissao_perfil');
function rc_crm_campo_comissao_perfil($user) {
    if (!current_user_can('manage_options')) return;
    if (!rc_crm_e_tecnico_ou_admin($user->ID)) return;

    $config   = rc_crm_get_config();
    $global   = floatval($config['comissao_global'] ?? 0);
    $override = get_user_meta($user->ID, 'rc_comissao_percentual', true);
    ?>
    <h2>💰 Comissão</h2>
    <table class="form-table">
        <tr>
            <th><label for="rc_comissao_percentual">% de Comissão</label></th>
            <td>
                <input type="number" step="0.01" min="0" max="100" id="rc_comissao_percentual" name="rc_comissao_percentual" value="<?php echo esc_attr($override); ?>" class="small-text" placeholder="<?php echo esc_attr($global); ?>">
                <span>%</span>
                <p class="description">
                    Deixe <strong>vazio</strong> para usar a % global (<strong><?php echo esc_html($global); ?>%</strong>).<br>
                    Preencha para dar uma % diferente a este técnico.
                </p>
            </td>
        </tr>
    </table>
    <?php
}

add_action('edit_user_profile_update', 'rc_crm_salvar_comissao_tecnico');
add_action('personal_options_update',  'rc_crm_salvar_comissao_tecnico');
function rc_crm_salvar_comissao_tecnico($user_id) {
    if (!current_user_can('manage_options')) return;
    if (!isset($_POST['rc_comissao_percentual'])) return;

    $valor = sanitize_text_field($_POST['rc_comissao_percentual']);

    if ($valor === '') {
        delete_user_meta($user_id, 'rc_comissao_percentual');
    } else {
        // Limita entre 0 e 100
        $valor = max(0, min(100, floatval($valor)));
        update_user_meta($user_id, 'rc_comissao_percentual', $valor);
    }
}

/* ============================================================
   COLUNA NA LISTA DE UTILIZADORES
   ============================================================ */
add_filter('manage_users_columns', 'rc_crm_coluna_comissao_users');
function rc_crm_coluna_comissao_users($cols) {
    $cols['rc_comissao'] = '💰 Comissão';
    return $cols;
}

add_filter('manage_users_custom_column', 'rc_crm_coluna_comissao_users_valor', 10, 3);
function rc_crm_coluna_comissao_users_valor($valor, $coluna, $user_id) {
    if ($coluna !== 'rc_comissao') return $valor;
    if (!rc_crm_e_tecnico_ou_admin($user_id)) return '—';

    $override = get_user_meta($user_id, 'rc_comissao_percentual', true);
    $config   = rc_crm_get_config();
    $global   = floatval($config['comissao_global'] ?? 0);

    if ($override !== '' && $override !== false && is_numeric($override)) {
        return '<strong style="color:#00a32a;">' . esc_html(number_format(floatval($override), 2, ',', '.')) . '%</strong>';
    }

    return '<span style="color:#666;">' . esc_html(number_format($global, 2, ',', '.')) . '% (global)</span>';
}

/* ============================================================
   PÁGINA: GESTÃO DE COMISSÕES
   ============================================================ */
function rc_crm_comissoes_page() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');

    global $wpdb;
    $table_s = $wpdb->prefix . 'rc_servicos';

    /* -------- Ações POST -------- */
    if (isset($_POST['rc_acao']) && in_array($_POST['rc_acao'], ['marcar_paga', 'desmarcar_paga'], true)) {
        $acao = $_POST['rc_acao'];
        check_admin_referer($acao === 'marcar_paga' ? 'rc_marcar_paga' : 'rc_desmarcar_paga');

        $ids  = isset($_POST['servico_ids']) ? array_map('intval', $_POST['servico_ids']) : [];
        $pagar = ($acao === 'marcar_paga');
        $ok = 0;

        foreach ($ids as $id) {
            if (rc_crm_marcar_comissao_paga($id, $pagar)) $ok++;
        }

        $emoji = $pagar ? '✅' : '↩️';
        $texto = $pagar ? 'marcada(s) como paga(s)' : 'desmarcada(s)';
        echo '<div class="notice notice-success is-dismissible"><p>' . $emoji . ' ' . (int) $ok . ' comissão(ões) ' . esc_html($texto) . '.</p></div>';
    }

    /* -------- Filtros -------- */
    $filtro_tecnico = isset($_GET['f_tecnico']) ? intval($_GET['f_tecnico'])            : 0;
    $filtro_pago    = isset($_GET['f_pago'])    ? sanitize_text_field($_GET['f_pago'])  : '';
    $filtro_mes     = isset($_GET['f_mes'])     ? sanitize_text_field($_GET['f_mes'])   : '';

    $where  = ["s.status = 'concluido'", "s.comissao_valor IS NOT NULL"];
    $params = [];

    if ($filtro_tecnico) {
        $where[]  = "s.tecnico_id = %d";
        $params[] = $filtro_tecnico;
    }
    if ($filtro_pago === 'sim') {
        $where[] = "s.comissao_paga_em IS NOT NULL";
    } elseif ($filtro_pago === 'nao') {
        $where[] = "s.comissao_paga_em IS NULL";
    }
    if ($filtro_mes && preg_match('/^\d{4}-\d{2}$/', $filtro_mes)) {
        $where[]  = "DATE_FORMAT(s.data_conclusao, '%%Y-%%m') = %s";
        $params[] = $filtro_mes;
    }

    $sql_where = 'WHERE ' . implode(' AND ', $where);

    $sql = "SELECT s.id, s.data_conclusao, s.orcamento, s.comissao_percentual, s.comissao_valor,
                   s.comissao_paga_em, s.tecnico_id,
                   c.nome AS cliente_nome, c.codigo AS cliente_codigo
            FROM $table_s s
            LEFT JOIN {$wpdb->prefix}rc_clientes c ON s.cliente_id = c.id
            $sql_where
            ORDER BY s.data_conclusao DESC";

    if (!empty($params)) {
        $sql = $wpdb->prepare($sql, $params);
    }

    $comissoes = $wpdb->get_results($sql);

    /* -------- Totais -------- */
    $total_geral = 0;
    $total_pago  = 0;
    $total_pendente = 0;

    foreach ($comissoes as $c) {
        $total_geral += floatval($c->comissao_valor);
        if ($c->comissao_paga_em) $total_pago += floatval($c->comissao_valor);
        else                      $total_pendente += floatval($c->comissao_valor);
    }

    /* -------- Resumo por técnico -------- */
    $por_tecnico = [];
    foreach ($comissoes as $c) {
        $tid = (int) $c->tecnico_id;
        if (!isset($por_tecnico[$tid])) {
            $por_tecnico[$tid] = ['total' => 0, 'pago' => 0, 'pendente' => 0, 'num' => 0];
        }
        $por_tecnico[$tid]['total'] += floatval($c->comissao_valor);
        $por_tecnico[$tid]['num']++;
        if ($c->comissao_paga_em) $por_tecnico[$tid]['pago'] += floatval($c->comissao_valor);
        else                      $por_tecnico[$tid]['pendente'] += floatval($c->comissao_valor);
    }

    $tecnicos = rc_crm_obter_tecnicos();
    ?>
    <style>
    .rc-com-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin: 20px 0; }
    .rc-com-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 10px; padding: 20px; }
    .rc-com-card .rc-num { font-size: 28px; font-weight: 700; line-height: 1.1; margin-bottom: 6px; }
    .rc-com-card .rc-lbl { font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
    .rc-com-card.azul .rc-num { color: #2271b1; }
    .rc-com-card.verde .rc-num { color: #00a32a; }
    .rc-com-card.laranja .rc-num { color: #dba617; }
    .rc-com-tecnicos { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 12px; margin: 20px 0; }
    .rc-com-tec { background: #fff; border-left: 4px solid #2271b1; border-radius: 8px; padding: 16px; }
    .rc-com-tec .nome { font-weight: 700; color: #1a1a1a; margin-bottom: 8px; }
    .rc-com-tec .linha { display: flex; justify-content: space-between; font-size: 13px; padding: 3px 0; }
    .rc-com-tec .linha.total { border-top: 1px solid #eee; margin-top: 6px; padding-top: 8px; font-weight: 700; color: #2271b1; font-size: 15px; }
    .rc-pago { background: #00a32a; color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
    .rc-pendente { background: #dba617; color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
    </style>

    <div class="wrap">
        <h1>💰 Gestão de Comissões</h1>
        <p class="description">Comissões calculadas automaticamente quando um serviço é marcado como <strong>Concluído</strong>.</p>

        <div class="rc-com-cards">
            <div class="rc-com-card azul">
                <div class="rc-num"><?php echo number_format($total_geral, 2, ',', '.'); ?>€</div>
                <div class="rc-lbl">Total em Comissões</div>
            </div>
            <div class="rc-com-card verde">
                <div class="rc-num"><?php echo number_format($total_pago, 2, ',', '.'); ?>€</div>
                <div class="rc-lbl">Já Pago</div>
            </div>
            <div class="rc-com-card laranja">
                <div class="rc-num"><?php echo number_format($total_pendente, 2, ',', '.'); ?>€</div>
                <div class="rc-lbl">Por Pagar</div>
            </div>
        </div>

        <?php if (!empty($por_tecnico)): ?>
            <h2>👷 Resumo por Técnico</h2>
            <div class="rc-com-tecnicos">
                <?php
                // Otimização: uma única query para todos os técnicos
                $ids_tecnicos = array_keys($por_tecnico);
                $users_cache = [];
                if (!empty($ids_tecnicos)) {
                    $users = get_users(['include' => $ids_tecnicos, 'fields' => ['ID', 'display_name']]);
                    foreach ($users as $u) {
                        $users_cache[$u->ID] = $u->display_name;
                    }
                }

                foreach ($por_tecnico as $tid => $dados):
                    $nome = $users_cache[$tid] ?? '(eliminado)';
                ?>
                    <div class="rc-com-tec">
                        <div class="nome">👤 <?php echo esc_html($nome); ?></div>
                        <div class="linha"><span>Serviços:</span><strong><?php echo (int) $dados['num']; ?></strong></div>
                        <div class="linha"><span>✅ Pago:</span><strong style="color:#00a32a;"><?php echo number_format($dados['pago'], 2, ',', '.'); ?>€</strong></div>
                        <div class="linha"><span>⏳ Pendente:</span><strong style="color:#dba617;"><?php echo number_format($dados['pendente'], 2, ',', '.'); ?>€</strong></div>
                        <div class="linha total"><span>Total:</span><span><?php echo number_format($dados['total'], 2, ',', '.'); ?>€</span></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="get" style="background:#fff; border:1px solid #ddd; border-radius:8px; padding:16px; margin:20px 0; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            <input type="hidden" name="page" value="rc-crm-comissoes">

            <label>Técnico:</label>
            <select name="f_tecnico">
                <option value="0">👥 Todos</option>
                <?php foreach ($tecnicos as $u): ?>
                    <option value="<?php echo esc_attr($u->ID); ?>" <?php selected($filtro_tecnico, $u->ID); ?>>👤 <?php echo esc_html($u->display_name); ?></option>
                <?php endforeach; ?>
            </select>

            <label>Estado:</label>
            <select name="f_pago">
                <option value="">📋 Todos</option>
                <option value="sim" <?php selected($filtro_pago, 'sim'); ?>>✅ Pagos</option>
                <option value="nao" <?php selected($filtro_pago, 'nao'); ?>>⏳ Por pagar</option>
            </select>

            <label>Mês:</label>
            <input type="month" name="f_mes" value="<?php echo esc_attr($filtro_mes); ?>">

            <button type="submit" class="button">🔍 Filtrar</button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=rc-crm-comissoes')); ?>" class="button">✕ Limpar</a>
        </form>

        <form method="post">
            <?php wp_nonce_field('rc_marcar_paga'); ?>
            <input type="hidden" name="rc_acao" value="marcar_paga">

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:30px;"><input type="checkbox" id="rc-check-all"></th>
                        <th>Data Conclusão</th>
                        <th>Cliente</th>
                        <th>Técnico</th>
                        <th style="text-align:right;">Subtotal</th>
                        <th style="text-align:center;">%</th>
                        <th style="text-align:right;">Comissão</th>
                        <th style="text-align:center;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($comissoes)): ?>
                        <tr><td colspan="8" style="text-align:center; color:#999; padding:40px;">Nenhuma comissão encontrada com estes filtros.</td></tr>
                    <?php else: ?>
                        <?php
                        // Cache de técnicos usados
                        $tecnicos_ids = array_unique(array_filter(array_column($comissoes, 'tecnico_id')));
                        $tecnicos_cache = [];
                        if (!empty($tecnicos_ids)) {
                            $users = get_users(['include' => $tecnicos_ids, 'fields' => ['ID', 'display_name']]);
                            foreach ($users as $u) {
                                $tecnicos_cache[$u->ID] = $u->display_name;
                            }
                        }

                        foreach ($comissoes as $c):
                            $nome_tec = $c->tecnico_id ? ($tecnicos_cache[$c->tecnico_id] ?? '—') : '—';
                            $pago = !empty($c->comissao_paga_em);
                        ?>
                            <tr>
                                <td><input type="checkbox" name="servico_ids[]" value="<?php echo esc_attr($c->id); ?>" class="rc-check-item"></td>
                                <td><?php echo $c->data_conclusao ? esc_html(date_i18n('d/m/Y', strtotime($c->data_conclusao))) : '—'; ?></td>
                                <td><?php echo esc_html($c->cliente_nome ?: '—'); ?> <small style="color:#999;">(<?php echo esc_html($c->cliente_codigo); ?>)</small></td>
                                <td><?php echo esc_html($nome_tec); ?></td>
                                <td style="text-align:right;"><?php echo number_format(floatval($c->orcamento), 2, ',', '.'); ?> €</td>
                                <td style="text-align:center;"><?php echo number_format(floatval($c->comissao_percentual), 2, ',', '.'); ?>%</td>
                                <td style="text-align:right;"><strong style="color:#00a32a;"><?php echo number_format(floatval($c->comissao_valor), 2, ',', '.'); ?> €</strong></td>
                                <td style="text-align:center;">
                                    <?php if ($pago): ?>
                                        <span class="rc-pago">✅ Pago</span><br>
                                        <small style="color:#999;"><?php echo esc_html(date_i18n('d/m/Y', strtotime($c->comissao_paga_em))); ?></small>
                                    <?php else: ?>
                                        <span class="rc-pendente">⏳ Pendente</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if (!empty($comissoes)): ?>
                <p style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="submit" class="button button-primary">✅ Marcar selecionadas como pagas</button>
                    <button type="submit" name="rc_acao" value="desmarcar_paga" class="button" onclick="return confirm('Desmarcar as selecionadas como pagas?');">↩️ Desmarcar selecionadas</button>
                </p>
            <?php endif; ?>
        </form>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const all = document.getElementById('rc-check-all');
            if (all) {
                all.addEventListener('change', function() {
                    document.querySelectorAll('.rc-check-item').forEach(function(c) {
                        c.checked = all.checked;
                    });
                });
            }
        });
        </script>
    </div>
    <?php
}