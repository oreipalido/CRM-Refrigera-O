<?php
/**
 * Refrigeração CRM — Relatórios e Estatísticas
 *
 * @package Refrigeracao_CRM
 * @version 3.5.1
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
   PÁGINA DE RELATÓRIOS
   ============================================================ */
function rc_crm_relatorios_page() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');

    global $wpdb;
    $table_s    = $wpdb->prefix . 'rc_servicos';
    $table_c    = $wpdb->prefix . 'rc_clientes';
    $table_tags = $wpdb->prefix . 'rc_servico_tags';

    /* -------- Filtros -------- */
    $periodo        = isset($_GET['periodo'])    ? sanitize_text_field($_GET['periodo'])    : 'ano';
    $data_de        = isset($_GET['data_de'])    ? sanitize_text_field($_GET['data_de'])    : '';
    $data_ate       = isset($_GET['data_ate'])   ? sanitize_text_field($_GET['data_ate'])   : '';
    $filtro_tecnico = isset($_GET['f_tecnico'])  ? intval($_GET['f_tecnico'])                : 0;
    $filtro_status  = isset($_GET['f_status'])   ? sanitize_text_field($_GET['f_status'])   : '';
    $filtro_cidade  = isset($_GET['f_cidade'])   ? sanitize_text_field($_GET['f_cidade'])   : '';
    $filtro_tag     = isset($_GET['f_tag'])      ? sanitize_text_field($_GET['f_tag'])      : '';
    $filtro_cliente = isset($_GET['f_cliente'])  ? intval($_GET['f_cliente'])                : 0;

    /* -------- Período (usa wp_date para timezone consistente) -------- */
    switch ($periodo) {
        case 'mes':
            $inicio = wp_date('Y-m-01');
            $fim    = wp_date('Y-m-t');
            break;
        case 'trimestre':
            $trimestre = ceil((int) wp_date('n') / 3);
            $inicio    = wp_date('Y-' . str_pad(($trimestre - 1) * 3 + 1, 2, '0', STR_PAD_LEFT) . '-01');
            $fim       = wp_date('Y-m-t', strtotime($inicio . ' +2 months'));
            break;
        case 'tudo':
            $inicio = '2000-01-01';
            $fim    = wp_date('Y-12-31');
            break;
        case 'personalizado':
            $inicio = $data_de  ?: wp_date('Y-01-01');
            $fim    = $data_ate ?: wp_date('Y-m-d');
            break;
        default:
            $inicio = wp_date('Y-01-01');
            $fim    = wp_date('Y-12-31');
    }

    /* -------- WHERE geral (usa índices) -------- */
    $where_geral  = ["s.created_at >= %s AND s.created_at < %s"];
    $params_geral = [$inicio . ' 00:00:00', $fim . ' 23:59:59'];

    if (!empty($filtro_tecnico)) { $where_geral[] = "s.tecnico_id = %d"; $params_geral[] = $filtro_tecnico; }
    if (!empty($filtro_status))  { $where_geral[] = "s.status = %s";     $params_geral[] = $filtro_status; }
    if (!empty($filtro_cidade))  { $where_geral[] = "c.cidade = %s";     $params_geral[] = $filtro_cidade; }
    if (!empty($filtro_cliente)) { $where_geral[] = "s.cliente_id = %d"; $params_geral[] = $filtro_cliente; }
    if (!empty($filtro_tag)) {
        $where_geral[]  = "EXISTS (SELECT 1 FROM $table_tags t WHERE t.servico_id = s.id AND t.tag_slug = %s)";
        $params_geral[] = $filtro_tag;
    }

    $sql_where_geral = 'WHERE ' . implode(' AND ', $where_geral);

    /* -------- Totais -------- */
    $total_servicos = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_s s LEFT JOIN $table_c c ON s.cliente_id = c.id $sql_where_geral",
        $params_geral
    ));

    $total_concluidos = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_s s LEFT JOIN $table_c c ON s.cliente_id = c.id
         $sql_where_geral AND s.status = 'concluido'",
        $params_geral
    ));

    $receita_total = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(s.orcamento) FROM $table_s s LEFT JOIN $table_c c ON s.cliente_id = c.id
         $sql_where_geral AND s.status = 'concluido'",
        $params_geral
    ));

    $receita_pendente = (float) $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(s.orcamento) FROM $table_s s LEFT JOIN $table_c c ON s.cliente_id = c.id
         $sql_where_geral AND s.status IN ('instalacao','orcamento')",
        $params_geral
    ));

    $ticket_medio   = $total_concluidos > 0 ? $receita_total / $total_concluidos : 0;
    $taxa_conclusao = $total_servicos  > 0 ? ($total_concluidos / $total_servicos) * 100 : 0;

    /* -------- Gráfico mensal (últimos 12 meses) -------- */
    $where_mensal  = ["s.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)"];
    $params_mensal = [];

    if (!empty($filtro_tecnico)) { $where_mensal[] = "s.tecnico_id = %d"; $params_mensal[] = $filtro_tecnico; }
    if (!empty($filtro_status))  { $where_mensal[] = "s.status = %s";     $params_mensal[] = $filtro_status; }
    if (!empty($filtro_cidade))  { $where_mensal[] = "c.cidade = %s";     $params_mensal[] = $filtro_cidade; }
    if (!empty($filtro_cliente)) { $where_mensal[] = "s.cliente_id = %d"; $params_mensal[] = $filtro_cliente; }
    if (!empty($filtro_tag)) {
        $where_mensal[]  = "EXISTS (SELECT 1 FROM $table_tags t WHERE t.servico_id = s.id AND t.tag_slug = %s)";
        $params_mensal[] = $filtro_tag;
    }

    $sql_mensal = "SELECT DATE_FORMAT(s.created_at, '%Y-%m') AS mes, COUNT(*) AS total,
                          SUM(CASE WHEN s.status = 'concluido' THEN s.orcamento ELSE 0 END) AS receita
                   FROM $table_s s LEFT JOIN $table_c c ON s.cliente_id = c.id
                   WHERE " . implode(' AND ', $where_mensal) . "
                   GROUP BY mes ORDER BY mes ASC";

    if (!empty($params_mensal)) $sql_mensal = $wpdb->prepare($sql_mensal, $params_mensal);
    $receita_mensal = $wpdb->get_results($sql_mensal);

    $meses_pt = [
        '01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Abr','05'=>'Mai','06'=>'Jun',
        '07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez',
    ];

    $grafico_meses = [];
    for ($i = 11; $i >= 0; $i--) {
        $data = wp_date('Y-m', strtotime("-$i months"));
        $grafico_meses[$data] = ['total' => 0, 'receita' => 0];
    }
    foreach ($receita_mensal as $r) {
        if (isset($grafico_meses[$r->mes])) {
            $grafico_meses[$r->mes] = [
                'total'   => (int) $r->total,
                'receita' => (float) $r->receita,
            ];
        }
    }
    $max_receita_mes = max(array_column($grafico_meses, 'receita')) ?: 1;

    /* -------- Ranking de técnicos -------- */
    $where_tec  = ["s.created_at >= %s AND s.created_at < %s"];
    $params_tec = [$inicio . ' 00:00:00', $fim . ' 23:59:59'];

    if (!empty($filtro_status))  { $where_tec[] = "s.status = %s";     $params_tec[] = $filtro_status; }
    if (!empty($filtro_cidade))  { $where_tec[] = "c.cidade = %s";     $params_tec[] = $filtro_cidade; }
    if (!empty($filtro_cliente)) { $where_tec[] = "s.cliente_id = %d"; $params_tec[] = $filtro_cliente; }
    if (!empty($filtro_tag)) {
        $where_tec[]  = "EXISTS (SELECT 1 FROM $table_tags t WHERE t.servico_id = s.id AND t.tag_slug = %s)";
        $params_tec[] = $filtro_tag;
    }

    $sql_tec = "SELECT s.tecnico_id, COUNT(*) AS total,
                       SUM(CASE WHEN s.status = 'concluido' THEN 1 ELSE 0 END) AS concluidos,
                       SUM(CASE WHEN s.status = 'concluido' THEN s.orcamento ELSE 0 END) AS receita,
                       SUM(CASE WHEN s.status = 'concluido' THEN s.comissao_valor ELSE 0 END) AS comissao,
                       SUM(CASE WHEN s.status = 'concluido' AND s.comissao_paga_em IS NULL THEN s.comissao_valor ELSE 0 END) AS comissao_pendente
                FROM $table_s s LEFT JOIN $table_c c ON s.cliente_id = c.id
                WHERE " . implode(' AND ', $where_tec) . " AND s.tecnico_id IS NOT NULL
                GROUP BY s.tecnico_id ORDER BY receita DESC";

    $por_tecnico = $wpdb->get_results($wpdb->prepare($sql_tec, $params_tec));

    /* -------- Top 10 clientes -------- */
    $where_top  = ["s.created_at >= %s AND s.created_at < %s"];
    $params_top = [$inicio . ' 00:00:00', $fim . ' 23:59:59'];

    if (!empty($filtro_tecnico)) { $where_top[] = "s.tecnico_id = %d"; $params_top[] = $filtro_tecnico; }
    if (!empty($filtro_status))  { $where_top[] = "s.status = %s";     $params_top[] = $filtro_status; }
    if (!empty($filtro_cidade))  { $where_top[] = "c.cidade = %s";     $params_top[] = $filtro_cidade; }
    if (!empty($filtro_tag)) {
        $where_top[]  = "EXISTS (SELECT 1 FROM $table_tags t WHERE t.servico_id = s.id AND t.tag_slug = %s)";
        $params_top[] = $filtro_tag;
    }

    $sql_top = "SELECT s.cliente_id, c.nome, c.codigo, c.cidade, COUNT(*) AS total,
                       SUM(CASE WHEN s.status = 'concluido' THEN s.orcamento ELSE 0 END) AS receita
                FROM $table_s s LEFT JOIN $table_c c ON s.cliente_id = c.id
                WHERE " . implode(' AND ', $where_top) . "
                GROUP BY s.cliente_id ORDER BY receita DESC LIMIT 10";

    $top_clientes = $wpdb->get_results($wpdb->prepare($sql_top, $params_top));

    /* -------- Distribuição por status -------- */
    $where_status  = ["s.created_at >= %s AND s.created_at < %s"];
    $params_status = [$inicio . ' 00:00:00', $fim . ' 23:59:59'];

    if (!empty($filtro_tecnico)) { $where_status[] = "s.tecnico_id = %d"; $params_status[] = $filtro_tecnico; }
    if (!empty($filtro_cidade))  { $where_status[] = "c.cidade = %s";     $params_status[] = $filtro_cidade; }
    if (!empty($filtro_cliente)) { $where_status[] = "s.cliente_id = %d"; $params_status[] = $filtro_cliente; }
    if (!empty($filtro_tag)) {
        $where_status[]  = "EXISTS (SELECT 1 FROM $table_tags t WHERE t.servico_id = s.id AND t.tag_slug = %s)";
        $params_status[] = $filtro_tag;
    }

    $sql_status = "SELECT s.status, COUNT(*) AS total
                   FROM $table_s s LEFT JOIN $table_c c ON s.cliente_id = c.id
                   WHERE " . implode(' AND ', $where_status) . "
                   GROUP BY s.status";

    $por_status = $wpdb->get_results($wpdb->prepare($sql_status, $params_status));

    $status_info = [
        'instalacao' => ['Instalação', '#2271b1'],
        'orcamento'  => ['Orçamento',  '#dba617'],
        'concluido'  => ['Concluído',  '#00a32a'],
        'cancelado'  => ['Cancelado',  '#b32d2e'],
    ];

    $total_status = array_sum(array_column($por_status, 'total')) ?: 1;

    /* -------- Listas para os filtros -------- */
    $cidades_existentes = $wpdb->get_col("SELECT DISTINCT cidade FROM $table_c WHERE cidade != '' ORDER BY cidade ASC");
    $clientes_lista     = $wpdb->get_results("SELECT id, codigo, nome FROM $table_c ORDER BY nome ASC");
    $tags_disponiveis   = function_exists('rc_crm_tags_disponiveis') ? rc_crm_tags_disponiveis() : [];
    $tecnicos           = function_exists('rc_crm_obter_tecnicos')   ? rc_crm_obter_tecnicos()   : get_users(['orderby' => 'display_name']);

    $filtros_ativos = $filtro_tecnico || $filtro_status || $filtro_cidade || $filtro_tag || $filtro_cliente;

    /* -------- Cache de nomes de técnicos (para o ranking) -------- */
    $tecnicos_cache = [];
    if (!empty($por_tecnico)) {
        $ids = array_unique(array_filter(array_column($por_tecnico, 'tecnico_id')));
        if (!empty($ids)) {
            $users = get_users(['include' => $ids, 'fields' => ['ID', 'display_name']]);
            foreach ($users as $u) {
                $tecnicos_cache[$u->ID] = $u->display_name;
            }
        }
    }
    ?>
    <style>
    .rc-rel { max-width: 1400px; }
    .rc-rel-filtros { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 16px; margin: 20px 0; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
    .rc-rel-filtros select, .rc-rel-filtros input { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; min-width: 140px; }
    .rc-rel-filtros label { font-weight: 600; color: #333; font-size: 13px; }
    .rc-rel-filtros .rc-limpar { color: #b32d2e; text-decoration: none; font-weight: 600; font-size: 13px; padding: 8px; }
    .rc-filtros-ativos { background: #fff9e5; border-left: 4px solid #dba617; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px; font-size: 14px; }
    .rc-rel-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 30px; }
    .rc-rel-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 10px; padding: 20px; }
    .rc-rel-card .rc-num { font-size: 28px; font-weight: 700; line-height: 1.1; margin-bottom: 6px; }
    .rc-rel-card .rc-lbl { font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
    .rc-rel-card.azul .rc-num { color: #2271b1; }
    .rc-rel-card.roxo .rc-num { color: #7c3aed; }
    .rc-rel-card.verde .rc-num { color: #00a32a; }
    .rc-rel-card.laranja .rc-num { color: #dba617; }
    .rc-rel-card.vermelho .rc-num { color: #b32d2e; }
    .rc-rel-seccao { background: #fff; border: 1px solid #e0e0e0; border-radius: 10px; padding: 24px; margin-bottom: 20px; }
    .rc-rel-seccao h2 { margin: 0 0 20px; font-size: 16px; padding-bottom: 12px; border-bottom: 2px solid #f0f0f0; }
    .rc-grafico-barras { display: flex; align-items: flex-end; gap: 8px; height: 240px; padding: 20px 0; border-bottom: 1px solid #eee; }
    .rc-barra-grupo { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; position: relative; }
    .rc-barra { width: 100%; max-width: 60px; background: linear-gradient(180deg, #2271b1 0%, #135e96 100%); border-radius: 4px 4px 0 0; min-height: 2px; position: relative; }
    .rc-barra-valor { position: absolute; top: -22px; left: 50%; transform: translateX(-50%); font-size: 11px; font-weight: 700; color: #2271b1; white-space: nowrap; }
    .rc-barra-label { margin-top: 8px; font-size: 11px; color: #666; font-weight: 600; text-transform: uppercase; }
    .rc-tabela-rel { width: 100%; border-collapse: collapse; }
    .rc-tabela-rel th { background: #f9f9f9; padding: 12px; text-align: left; font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e0e0e0; }
    .rc-tabela-rel td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
    .rc-tabela-rel tr:hover td { background: #fafafa; }
    .rc-tabela-rel .rc-num { text-align: right; font-weight: 600; }
    .rc-tabela-rel .rc-num.verde { color: #00a32a; }
    .rc-badge-rank { display: inline-block; width: 26px; height: 26px; border-radius: 50%; background: #f0f0f0; color: #666; text-align: center; line-height: 26px; font-weight: 700; font-size: 12px; }
    .rc-badge-rank.top1 { background: #ffd700; color: #8a6d00; }
    .rc-badge-rank.top2 { background: #c0c0c0; color: #555; }
    .rc-badge-rank.top3 { background: #cd7f32; color: #fff; }
    .rc-status-linha { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
    .rc-status-nome { min-width: 110px; font-size: 13px; font-weight: 600; }
    .rc-status-barra-container { flex: 1; background: #f0f0f0; border-radius: 4px; height: 24px; overflow: hidden; }
    .rc-status-barra { height: 100%; border-radius: 4px; display: flex; align-items: center; justify-content: flex-end; padding-right: 8px; color: #fff; font-size: 12px; font-weight: 700; }
    .rc-sem-dados { text-align: center; padding: 40px; color: #999; font-style: italic; }
    .rc-rel-acoes { display: flex; gap: 10px; margin-top: 20px; }
    </style>

    <div class="wrap rc-rel">
        <h1>📊 Relatórios e Estatísticas</h1>

        <form method="get" class="rc-rel-filtros">
            <input type="hidden" name="page" value="rc-crm-relatorios">

            <label>Período:</label>
            <select name="periodo" onchange="this.form.submit()">
                <option value="mes"          <?php selected($periodo, 'mes'); ?>>Este mês</option>
                <option value="trimestre"    <?php selected($periodo, 'trimestre'); ?>>Este trimestre</option>
                <option value="ano"          <?php selected($periodo, 'ano'); ?>>Este ano</option>
                <option value="tudo"         <?php selected($periodo, 'tudo'); ?>>Todo o histórico</option>
                <option value="personalizado"<?php selected($periodo, 'personalizado'); ?>>Personalizado</option>
            </select>

            <?php if ($periodo === 'personalizado'): ?>
                <label>De:</label>
                <input type="date" name="data_de" value="<?php echo esc_attr($data_de); ?>">
                <label>Até:</label>
                <input type="date" name="data_ate" value="<?php echo esc_attr($data_ate); ?>">
            <?php endif; ?>

            <label>Técnico:</label>
            <select name="f_tecnico">
                <option value="0">👥 Todos</option>
                <?php foreach ($tecnicos as $u): ?>
                    <option value="<?php echo esc_attr($u->ID); ?>" <?php selected($filtro_tecnico, $u->ID); ?>>👤 <?php echo esc_html($u->display_name); ?></option>
                <?php endforeach; ?>
            </select>

            <label>Status:</label>
            <select name="f_status">
                <option value="">📋 Todos</option>
                <?php foreach ($status_info as $slug => $info): ?>
                    <option value="<?php echo esc_attr($slug); ?>" <?php selected($filtro_status, $slug); ?>><?php echo esc_html($info[0]); ?></option>
                <?php endforeach; ?>
            </select>

            <label>Cidade:</label>
            <select name="f_cidade">
                <option value="">📍 Todas</option>
                <?php foreach ($cidades_existentes as $cid): ?>
                    <option value="<?php echo esc_attr($cid); ?>" <?php selected($filtro_cidade, $cid); ?>><?php echo esc_html($cid); ?></option>
                <?php endforeach; ?>
            </select>

            <label>Etiqueta:</label>
            <select name="f_tag">
                <option value="">🏷️ Todas</option>
                <?php foreach ($tags_disponiveis as $slug => $tag): ?>
                    <option value="<?php echo esc_attr($slug); ?>" <?php selected($filtro_tag, $slug); ?>><?php echo esc_html($tag[0]); ?></option>
                <?php endforeach; ?>
            </select>

            <label>Cliente:</label>
            <select name="f_cliente">
                <option value="0">👥 Todos</option>
                <?php foreach ($clientes_lista as $cl): ?>
                    <option value="<?php echo esc_attr($cl->id); ?>" <?php selected($filtro_cliente, $cl->id); ?>><?php echo esc_html($cl->codigo . ' — ' . $cl->nome); ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="button button-primary">🔍 Filtrar</button>
            <?php if ($filtros_ativos || $periodo !== 'ano'): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rc-crm-relatorios')); ?>" class="rc-limpar">✕ Limpar filtros</a>
            <?php endif; ?>
        </form>

        <?php if ($filtros_ativos): ?>
            <div class="rc-filtros-ativos">
                <strong>⚡ Filtros ativos:</strong>
                <?php if ($filtro_tecnico): $u = get_userdata($filtro_tecnico); ?>
                    <span style="background:#2271b1; color:#fff; padding:2px 10px; border-radius:12px; font-size:12px; margin-left:6px;">👤 <?php echo esc_html($u ? $u->display_name : '—'); ?></span>
                <?php endif; ?>
                <?php if ($filtro_status): ?>
                    <span style="background:#dba617; color:#fff; padding:2px 10px; border-radius:12px; font-size:12px; margin-left:6px;">📌 <?php echo esc_html($status_info[$filtro_status][0] ?? $filtro_status); ?></span>
                <?php endif; ?>
                <?php if ($filtro_cidade): ?>
                    <span style="background:#00a32a; color:#fff; padding:2px 10px; border-radius:12px; font-size:12px; margin-left:6px;">📍 <?php echo esc_html($filtro_cidade); ?></span>
                <?php endif; ?>
                <?php if ($filtro_tag): ?>
                    <span style="background:#7c3aed; color:#fff; padding:2px 10px; border-radius:12px; font-size:12px; margin-left:6px;">🏷️ <?php echo esc_html($tags_disponiveis[$filtro_tag][0] ?? $filtro_tag); ?></span>
                <?php endif; ?>
                <?php if ($filtro_cliente): $cli = $wpdb->get_row($wpdb->prepare("SELECT nome, codigo FROM $table_c WHERE id = %d", $filtro_cliente)); ?>
                    <span style="background:#b32d2e; color:#fff; padding:2px 10px; border-radius:12px; font-size:12px; margin-left:6px;">👥 <?php echo esc_html($cli ? $cli->codigo . ' — ' . $cli->nome : $filtro_cliente); ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div style="color:#666; font-size:13px; margin-bottom:20px;">
            📅 <?php echo esc_html(date_i18n('d/m/Y', strtotime($inicio))); ?> até <?php echo esc_html(date_i18n('d/m/Y', strtotime($fim))); ?>
        </div>

        <!-- Cards de KPIs -->
        <div class="rc-rel-cards">
            <div class="rc-rel-card azul"><div class="rc-num"><?php echo (int) $total_servicos; ?></div><div class="rc-lbl">Serviços no Período</div></div>
            <div class="rc-rel-card verde"><div class="rc-num"><?php echo (int) $total_concluidos; ?></div><div class="rc-lbl">Concluídos</div></div>
            <div class="rc-rel-card roxo"><div class="rc-num"><?php echo esc_html(number_format($receita_total, 0, ',', '.')); ?>€</div><div class="rc-lbl">Receita (sem IVA)</div></div>
            <div class="rc-rel-card laranja"><div class="rc-num"><?php echo esc_html(number_format($receita_pendente, 0, ',', '.')); ?>€</div><div class="rc-lbl">Receita Pendente</div></div>
            <div class="rc-rel-card verde"><div class="rc-num"><?php echo esc_html(number_format($ticket_medio, 0, ',', '.')); ?>€</div><div class="rc-lbl">Ticket Médio</div></div>
            <div class="rc-rel-card <?php echo $taxa_conclusao >= 70 ? 'verde' : ($taxa_conclusao >= 40 ? 'laranja' : 'vermelho'); ?>"><div class="rc-num"><?php echo esc_html(number_format($taxa_conclusao, 0)); ?>%</div><div class="rc-lbl">Taxa de Conclusão</div></div>
        </div>

        <!-- Gráfico mensal -->
        <div class="rc-rel-seccao">
            <h2>📈 Receita Mensal (últimos 12 meses)</h2>
            <?php if (array_sum(array_column($grafico_meses, 'receita')) == 0): ?>
                <p class="rc-sem-dados">Sem dados para mostrar.</p>
            <?php else: ?>
                <div class="rc-grafico-barras">
                    <?php foreach ($grafico_meses as $mes => $dados):
                        $altura  = $max_receita_mes > 0 ? ($dados['receita'] / $max_receita_mes) * 100 : 0;
                        $mes_num = substr($mes, 5, 2);
                        $mes_ano = $meses_pt[$mes_num] . '/' . substr($mes, 2, 2);
                    ?>
                        <div class="rc-barra-grupo">
                            <div class="rc-barra" style="height:<?php echo max(2, $altura); ?>%;">
                                <?php if ($dados['receita'] > 0): ?>
                                    <span class="rc-barra-valor"><?php echo esc_html(number_format($dados['receita'], 0, ',', '.')); ?>€</span>
                                <?php endif; ?>
                            </div>
                            <div class="rc-barra-label"><?php echo esc_html($mes_ano); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Duas colunas: técnicos + status -->
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
            <div class="rc-rel-seccao">
                <h2>👷 Serviços por Técnico <?php if ($filtro_tecnico): ?><span style="font-size:11px; color:#999; font-weight:400;">(ranking geral)</span><?php endif; ?></h2>
                <?php if (empty($por_tecnico)): ?>
                    <p class="rc-sem-dados">Sem dados.</p>
                <?php else: ?>
                    <table class="rc-tabela-rel">
                        <thead>
                            <tr><th>#</th><th>Técnico</th><th class="rc-num">Serviços</th><th class="rc-num">Concluídos</th><th class="rc-num">Receita</th><th class="rc-num">Comissão</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($por_tecnico as $i => $t):
                                $nome      = $tecnicos_cache[$t->tecnico_id] ?? '(eliminado)';
                                $com_total = floatval($t->comissao);
                                $com_pend  = floatval($t->comissao_pendente);
                            ?>
                                <tr>
                                    <td><span class="rc-badge-rank <?php echo $i < 3 ? 'top' . ($i + 1) : ''; ?>"><?php echo $i + 1; ?></span></td>
                                    <td><strong><?php echo esc_html($nome); ?></strong></td>
                                    <td class="rc-num"><?php echo (int) $t->total; ?></td>
                                    <td class="rc-num"><?php echo (int) $t->concluidos; ?></td>
                                    <td class="rc-num verde"><?php echo esc_html(number_format((float) $t->receita, 2, ',', '.')); ?> €</td>
                                    <td class="rc-num" style="color:#7c3aed;">
                                        <strong><?php echo esc_html(number_format($com_total, 2, ',', '.')); ?> €</strong>
                                        <?php if ($com_pend > 0): ?><br><small style="color:#dba617; font-weight:600;"><?php echo esc_html(number_format($com_pend, 2, ',', '.')); ?> € pendente</small><?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="rc-rel-seccao">
                <h2>📊 Distribuição por Status</h2>
                <?php if (empty($por_status)): ?>
                    <p class="rc-sem-dados">Sem dados.</p>
                <?php else: ?>
                    <?php foreach ($por_status as $s):
                        $info = $status_info[$s->status] ?? ['Desconhecido', '#999'];
                        $perc = ($s->total / $total_status) * 100;
                    ?>
                        <div class="rc-status-linha">
                            <div class="rc-status-nome" style="color:<?php echo esc_attr($info[1]); ?>;"><?php echo esc_html($info[0]); ?></div>
                            <div class="rc-status-barra-container">
                                <div class="rc-status-barra" style="width:<?php echo max(5, $perc); ?>%; background:<?php echo esc_attr($info[1]); ?>;"><?php echo (int) $s->total; ?></div>
                            </div>
                            <div style="min-width:60px; text-align:right; font-weight:600; color:#666;"><?php echo esc_html(number_format($perc, 1)); ?>%</div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top 10 clientes -->
        <div class="rc-rel-seccao">
            <h2>🏆 Top 10 Clientes (por receita) <?php if ($filtro_cliente): ?><span style="font-size:11px; color:#999; font-weight:400;">(ranking geral)</span><?php endif; ?></h2>
            <?php if (empty($top_clientes)): ?>
                <p class="rc-sem-dados">Sem dados.</p>
            <?php else: ?>
                <table class="rc-tabela-rel">
                    <thead>
                        <tr><th>#</th><th>Código</th><th>Cliente</th><th>Cidade</th><th class="rc-num">Serviços</th><th class="rc-num">Receita</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_clientes as $i => $c): ?>
                            <tr>
                                <td><span class="rc-badge-rank <?php echo $i < 3 ? 'top' . ($i + 1) : ''; ?>"><?php echo $i + 1; ?></span></td>
                                <td><strong><?php echo esc_html($c->codigo); ?></strong></td>
                                <td><?php echo esc_html($c->nome ?: '—'); ?></td>
                                <td><?php echo esc_html($c->cidade ?: '—'); ?></td>
                                <td class="rc-num"><?php echo (int) $c->total; ?></td>
                                <td class="rc-num verde"><?php echo esc_html(number_format((float) $c->receita, 2, ',', '.')); ?> €</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Ações -->
        <div class="rc-rel-acoes">
            <?php
            $url_export = wp_nonce_url(
                add_query_arg([
                    'action'    => 'rc_exportar_relatorio',
                    'periodo'   => urlencode($periodo),
                    'data_de'   => urlencode($data_de),
                    'data_ate'  => urlencode($data_ate),
                    'f_tecnico' => $filtro_tecnico,
                    'f_status'  => urlencode($filtro_status),
                    'f_cidade'  => urlencode($filtro_cidade),
                    'f_tag'     => urlencode($filtro_tag),
                    'f_cliente' => $filtro_cliente,
                ], admin_url('admin-post.php')),
                'rc_exportar_relatorio'
            );
            ?>
            <a href="<?php echo esc_url($url_export); ?>" class="button button-primary">📥 Exportar Relatório CSV (com filtros)</a>
            <button onclick="window.print()" class="button">🖨️ Imprimir</button>
        </div>
    </div>
    <?php
}
/* ============================================================
   EXPORTAR RELATÓRIO CSV
   ============================================================ */
add_action('admin_post_rc_exportar_relatorio', 'rc_crm_exportar_relatorio');
function rc_crm_exportar_relatorio() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão');
    check_admin_referer('rc_exportar_relatorio');

    global $wpdb;
    $table_s    = $wpdb->prefix . 'rc_servicos';
    $table_c    = $wpdb->prefix . 'rc_clientes';
    $table_tags = $wpdb->prefix . 'rc_servico_tags';

    /* -------- Filtros -------- */
    $periodo        = isset($_GET['periodo'])    ? sanitize_text_field($_GET['periodo'])    : 'ano';
    $data_de        = isset($_GET['data_de'])    ? sanitize_text_field($_GET['data_de'])    : '';
    $data_ate       = isset($_GET['data_ate'])   ? sanitize_text_field($_GET['data_ate'])   : '';
    $filtro_tecnico = isset($_GET['f_tecnico'])  ? intval($_GET['f_tecnico'])                : 0;
    $filtro_status  = isset($_GET['f_status'])   ? sanitize_text_field($_GET['f_status'])   : '';
    $filtro_cidade  = isset($_GET['f_cidade'])   ? sanitize_text_field($_GET['f_cidade'])   : '';
    $filtro_tag     = isset($_GET['f_tag'])      ? sanitize_text_field($_GET['f_tag'])      : '';
    $filtro_cliente = isset($_GET['f_cliente'])  ? intval($_GET['f_cliente'])                : 0;

    /* -------- Período -------- */
    switch ($periodo) {
        case 'mes':
            $inicio = wp_date('Y-m-01');
            $fim    = wp_date('Y-m-t');
            break;
        case 'trimestre':
            $trimestre = ceil((int) wp_date('n') / 3);
            $inicio    = wp_date('Y-' . str_pad(($trimestre - 1) * 3 + 1, 2, '0', STR_PAD_LEFT) . '-01');
            $fim       = wp_date('Y-m-t', strtotime($inicio . ' +2 months'));
            break;
        case 'tudo':
            $inicio = '2000-01-01';
            $fim    = wp_date('Y-12-31');
            break;
        case 'personalizado':
            $inicio = $data_de  ?: wp_date('Y-01-01');
            $fim    = $data_ate ?: wp_date('Y-m-d');
            break;
        default:
            $inicio = wp_date('Y-01-01');
            $fim    = wp_date('Y-12-31');
    }

    $where  = ["s.created_at >= %s AND s.created_at < %s"];
    $params = [$inicio . ' 00:00:00', $fim . ' 23:59:59'];

    if (!empty($filtro_tecnico)) { $where[] = "s.tecnico_id = %d"; $params[] = $filtro_tecnico; }
    if (!empty($filtro_status))  { $where[] = "s.status = %s";     $params[] = $filtro_status; }
    if (!empty($filtro_cidade))  { $where[] = "c.cidade = %s";     $params[] = $filtro_cidade; }
    if (!empty($filtro_cliente)) { $where[] = "s.cliente_id = %d"; $params[] = $filtro_cliente; }
    if (!empty($filtro_tag)) {
        $where[]  = "EXISTS (SELECT 1 FROM $table_tags t WHERE t.servico_id = s.id AND t.tag_slug = %s)";
        $params[] = $filtro_tag;
    }

    $sql = "SELECT s.*, c.nome AS cliente_nome, c.codigo AS cliente_codigo
            FROM $table_s s LEFT JOIN $table_c c ON s.cliente_id = c.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY s.created_at DESC";

    $servicos = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

    /* -------- Cache de técnicos (evita N+1) -------- */
    $tecnicos_cache = [];
    if (!empty($servicos)) {
        $ids = array_unique(array_filter(array_column($servicos, 'tecnico_id')));
        if (!empty($ids)) {
            $users = get_users(['include' => $ids, 'fields' => ['ID', 'display_name']]);
            foreach ($users as $u) {
                $tecnicos_cache[$u->ID] = $u->display_name;
            }
        }
    }

    /* -------- Cache de tags (evita N+1) -------- */
    $tags_por_servico = [];
    if (!empty($servicos)) {
        $servicos_ids = array_column($servicos, 'id');
        if (!empty($servicos_ids)) {
            $placeholders = implode(',', array_fill(0, count($servicos_ids), '%d'));
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT servico_id, tag_slug FROM $table_tags WHERE servico_id IN ($placeholders)",
                $servicos_ids
            ));
            foreach ($rows as $r) {
                $tags_por_servico[$r->servico_id][] = $r->tag_slug;
            }
        }
    }

    /* -------- Enriquecer dados -------- */
    foreach ($servicos as &$s) {
        $s['tecnico_nome'] = !empty($s['tecnico_id'])
            ? ($tecnicos_cache[$s['tecnico_id']] ?? '')
            : '';

        $sub   = floatval($s['orcamento']);
        $iva_p = ($s['iva_percentual'] !== null && $s['iva_percentual'] !== '')
            ? floatval($s['iva_percentual'])
            : 23;

        $s['iva_p_calc'] = $iva_p;
        $s['iva_v_calc'] = $sub * ($iva_p / 100);
        $s['total_calc'] = $sub + $s['iva_v_calc'];
        $s['tags']       = isset($tags_por_servico[$s['id']]) ? implode(', ', $tags_por_servico[$s['id']]) : '';
    }
    unset($s);

    /* -------- Output -------- */
    $filename = 'relatorio-' . $inicio . '_a_' . $fim . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");

    fputcsv($output, [
        'ID', 'Data', 'Cliente', 'Código', 'Equipamento', 'Etiquetas', 'Técnico',
        'Status', 'Data Agendada', 'Data Conclusão',
        'Subtotal (€)', 'IVA (%)', 'IVA (€)', 'Total (€)', 'Motivo Isenção',
        'Comissão (€)', 'Comissão Paga em', 'Observações',
    ], ';');

    foreach ($servicos as $s) {
        fputcsv($output, [
            $s['id'],
            date_i18n('d/m/Y', strtotime($s['created_at'])),
            $s['cliente_nome'] ?: '',
            $s['cliente_codigo'] ?: '',
            str_replace(["\r\n", "\r", "\n"], ' ', $s['equipamento'] ?: ''),
            $s['tags'],
            $s['tecnico_nome'],
            $s['status'],
            $s['data_agendada']  ? date_i18n('d/m/Y H:i', strtotime($s['data_agendada']))  : '',
            $s['data_conclusao'] ? date_i18n('d/m/Y H:i', strtotime($s['data_conclusao'])) : '',
            number_format(floatval($s['orcamento']), 2, ',', '.'),
            number_format($s['iva_p_calc'], 2, ',', '.'),
            number_format($s['iva_v_calc'], 2, ',', '.'),
            number_format($s['total_calc'], 2, ',', '.'),
            $s['iva_motivo_isencao'] ?: '',
            !empty($s['comissao_valor']) ? number_format((float) $s['comissao_valor'], 2, ',', '.') : '',
            !empty($s['comissao_paga_em']) ? date_i18n('d/m/Y', strtotime($s['comissao_paga_em'])) : '',
            str_replace(["\r\n", "\r", "\n"], ' ', $s['observacoes'] ?: ''),
        ], ';');
    }

    fclose($output);
    exit;
}