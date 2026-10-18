<?php
/**
 * Refrigeração CRM — Menu Admin + Dashboard
 *
 * @package Refrigeracao_CRM
 * @version 3.5.0
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
   HELPER: obter página por título (substitui get_page_by_title deprecated)
   ============================================================ */
if (!function_exists('rc_crm_get_page_by_title')) {
    function rc_crm_get_page_by_title($title) {
        $pages = get_posts([
            'post_type'      => 'page',
            'title'          => $title,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'no_found_rows'  => true,
        ]);
        return !empty($pages) ? $pages[0] : null;
    }
}

/* ============================================================
   MENU
   ============================================================ */
add_action('admin_menu', 'rc_crm_admin_menu');
function rc_crm_admin_menu() {

    /* -------- Menu principal -------- */
    add_menu_page(
        'Refrigeração CRM',
        'Refrigeração CRM',
        'manage_options',
        'rc-crm',
        'rc_crm_dashboard_page',
        'dashicons-admin-tools',
        26
    );

    add_submenu_page('rc-crm', 'Painel',       '📊 Painel',       'manage_options', 'rc-crm',              'rc_crm_dashboard_page');
    add_submenu_page('rc-crm', 'Pesquisa',     '🔍 Pesquisar',    'manage_options', 'rc-crm-pesquisa',     'rc_crm_pesquisa_page');
    add_submenu_page('rc-crm', 'Calendário',   '📅 Calendário',   'manage_options', 'rc-crm-calendario',   'rc_crm_calendario_page');
    add_submenu_page('rc-crm', 'Clientes',     '👥 Clientes',     'manage_options', 'rc-crm-clientes',     'rc_crm_clientes_page');
    add_submenu_page('rc-crm', 'Serviços',     '🔧 Serviços',     'manage_options', 'rc-crm-servicos',     'rc_crm_servicos_page');
    add_submenu_page('rc-crm', 'Armazém',      '📦 Armazém',      'manage_options', 'rc-crm-armazem',      'rc_crm_armazem_page');
    add_submenu_page('rc-crm', 'Relatórios',   '📊 Relatórios',   'manage_options', 'rc-crm-relatorios',   'rc_crm_relatorios_page');
    add_submenu_page('rc-crm', 'Comissões',    '💰 Comissões',    'manage_options', 'rc-crm-comissoes',    'rc_crm_comissoes_page');
    add_submenu_page('rc-crm', 'Utilizadores', '👷 Utilizadores', 'manage_options', 'rc-crm-utilizadores', 'rc_crm_utilizadores_page');

    /* -------- Configurações (única página com abas) -------- */
    add_submenu_page(
        'rc-crm',
        'Configurações',
        '⚙️ Configurações',
        'manage_options',
        'rc-crm-config',
        'rc_crm_config_page'
    );
}

/* ============================================================
   REDIRECT: slugs antigos → abas de Configurações
   (compatibilidade com bookmarks/links antigos)
   ============================================================ */
add_action('admin_init', 'rc_crm_redirecionar_slugs_antigos');
function rc_crm_redirecionar_slugs_antigos() {
    if (!isset($_GET['page'])) return;
    if (!current_user_can('manage_options')) return;

    $page = sanitize_text_field($_GET['page']);

    $mapa = [
        'rc-crm-atualizacoes' => 'atualizacoes',
        'rc-crm-backups'      => 'backups',
    ];

    if (isset($mapa[$page])) {
        wp_safe_redirect(admin_url('admin.php?page=rc-crm-config&tab=' . $mapa[$page]));
        exit;
    }
}

/* ============================================================
   DASHBOARD
   ============================================================ */
function rc_crm_dashboard_page() {
    global $wpdb;
    $table_c = $wpdb->prefix . 'rc_clientes';
    $table_s = $wpdb->prefix . 'rc_servicos';
    $table_a = $wpdb->prefix . 'rc_equipamentos_armazem';

    /* -------- Cache de 5 minutos -------- */
    $cache_key = 'rc_crm_dashboard_stats';
    $stats = get_transient($cache_key);

    if ($stats === false) {
        $stats = [
            'total_clientes'       => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_c"),
            'total_servicos'       => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_s"),
            'total_pendentes'      => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_s WHERE status IN ('instalacao','orcamento')"),
            'total_concluidos'     => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_s WHERE status = 'concluido'"),
            'total_cancelados'     => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_s WHERE status = 'cancelado'"),
            'total_armazem'        => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_a WHERE estado = 'em_armazem'"),
            'total_armazem_alerta' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_a WHERE estado = 'em_armazem' AND DATEDIFF(CURDATE(), data_entrada) > 30"),
            'receita'              => (float) $wpdb->get_var("SELECT SUM(orcamento) FROM $table_s WHERE status = 'concluido'"),
        ];
        set_transient($cache_key, $stats, 5 * MINUTE_IN_SECONDS);
    }

    $total_clientes       = $stats['total_clientes'];
    $total_servicos       = $stats['total_servicos'];
    $total_pendentes      = $stats['total_pendentes'];
    $total_concluidos     = $stats['total_concluidos'];
    $total_cancelados     = $stats['total_cancelados'];
    $total_armazem        = $stats['total_armazem'];
    $total_armazem_alerta = $stats['total_armazem_alerta'];
    $receita              = $stats['receita'];

    $proximos = $wpdb->get_results(
        "SELECT s.*, c.nome AS cliente_nome
         FROM $table_s s
         LEFT JOIN $table_c c ON s.cliente_id = c.id
         WHERE s.data_agendada >= NOW()
           AND s.status IN ('instalacao','orcamento')
         ORDER BY s.data_agendada ASC
         LIMIT 5"
    );

    $ultimos_clientes = $wpdb->get_results("SELECT * FROM $table_c ORDER BY created_at DESC LIMIT 5");
    $ultimos_servicos = $wpdb->get_results(
        "SELECT s.*, c.nome AS cliente_nome
         FROM $table_s s
         LEFT JOIN $table_c c ON s.cliente_id = c.id
         ORDER BY s.created_at DESC
         LIMIT 5"
    );

    $pagina_cadastro_cliente = rc_crm_get_page_by_title('Cadastrar Cliente');
    $pagina_cadastro_servico = rc_crm_get_page_by_title('Cadastrar Serviço');
    $pagina_painel_clientes  = rc_crm_get_page_by_title('Clientes');

    $url_cadastro_cliente = $pagina_cadastro_cliente ? get_permalink($pagina_cadastro_cliente->ID) : admin_url('admin.php?page=rc-crm-clientes');
    $url_cadastro_servico = $pagina_cadastro_servico ? get_permalink($pagina_cadastro_servico->ID) : admin_url('admin.php?page=rc-crm-servicos');
    $url_painel_clientes  = $pagina_painel_clientes  ? get_permalink($pagina_painel_clientes->ID)  : admin_url('admin.php?page=rc-crm-clientes');

    $status_labels = [
        'instalacao' => ['Instalação', '#1877F2'],
        'orcamento'  => ['Orçamento',  '#dba617'],
        'concluido'  => ['Concluído',  '#00a32a'],
        'cancelado'  => ['Cancelado',  '#b32d2e'],
    ];
    ?>
    <style>
    .rc-dash { max-width: 1200px; }
    .rc-dash h1 { display: flex; align-items: center; gap: 10px; }
    .rc-dash-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin: 20px 0 30px; }
    .rc-dash-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .rc-dash-card .rc-num { font-size: 32px; font-weight: 700; line-height: 1; margin-bottom: 6px; }
    .rc-dash-card .rc-lbl { font-size: 13px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
    .rc-dash-card.azul     .rc-num { color: #1877F2; }
    .rc-dash-card.verde    .rc-num { color: #00a32a; }
    .rc-dash-card.laranja  .rc-num { color: #dba617; }
    .rc-dash-card.vermelho .rc-num { color: #b32d2e; }
    .rc-dash-card.roxo     .rc-num { color: #7c3aed; }
    .rc-dash-seccao { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
    .rc-dash-seccao h2 { margin-top: 0; font-size: 16px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
    .rc-dash-acoes { display: flex; flex-wrap: wrap; gap: 10px; }
    .rc-dash-acoes a { display: inline-flex; align-items: center; gap: 6px; padding: 12px 18px; background: #1877F2; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; transition: opacity 0.2s; }
    .rc-dash-acoes a:hover { opacity: 0.85; color: #fff; }
    .rc-dash-acoes a.verde    { background: #00a32a; }
    .rc-dash-acoes a.laranja  { background: #dba617; }
    .rc-dash-acoes a.roxo     { background: #7c3aed; }
    .rc-dash-acoes a.vermelho { background: #b32d2e; }
    .rc-dash-lista { list-style: none; margin: 0; padding: 0; }
    .rc-dash-lista li { padding: 10px 0; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; gap: 10px; }
    .rc-dash-lista li:last-child { border-bottom: none; }
    .rc-dash-lista .rc-data { font-family: monospace; color: #1877F2; font-weight: 600; min-width: 120px; }
    .rc-dash-lista .rc-info { flex: 1; }
    .rc-dash-lista .rc-info small { color: #999; }
    .rc-dash-vazio { color: #999; font-style: italic; padding: 10px 0; }
    .rc-dash-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; color: #fff; }
    </style>

    <div class="wrap rc-dash">
        <h1>🔧 Refrigeração CRM — Painel de Controlo</h1>

        <div class="rc-dash-cards">
            <div class="rc-dash-card azul">
                <div class="rc-num"><?php echo (int) $total_clientes; ?></div>
                <div class="rc-lbl">Clientes</div>
            </div>
            <div class="rc-dash-card roxo">
                <div class="rc-num"><?php echo (int) $total_servicos; ?></div>
                <div class="rc-lbl">Serviços</div>
            </div>
            <div class="rc-dash-card laranja">
                <div class="rc-num"><?php echo (int) $total_pendentes; ?></div>
                <div class="rc-lbl">Pendentes</div>
            </div>
            <div class="rc-dash-card verde">
                <div class="rc-num"><?php echo (int) $total_concluidos; ?></div>
                <div class="rc-lbl">Concluídos</div>
            </div>
            <div class="rc-dash-card vermelho">
                <div class="rc-num"><?php echo (int) $total_cancelados; ?></div>
                <div class="rc-lbl">Cancelados</div>
            </div>
            <div class="rc-dash-card verde">
                <div class="rc-num"><?php echo esc_html(number_format($receita, 2, ',', '.')); ?>€</div>
                <div class="rc-lbl">Receita</div>
            </div>
            <div class="rc-dash-card azul">
                <div class="rc-num"><?php echo (int) $total_armazem; ?></div>
                <div class="rc-lbl">📦 Em Armazém</div>
            </div>
            <?php if ($total_armazem_alerta > 0): ?>
                <div class="rc-dash-card vermelho">
                    <div class="rc-num"><?php echo (int) $total_armazem_alerta; ?></div>
                    <div class="rc-lbl">⚠️ Armazém +30 dias</div>
                </div>
            <?php endif; ?>
        </div>

        <div class="rc-dash-seccao">
            <h2>⚡ Ações Rápidas</h2>
            <div class="rc-dash-acoes">
                <a href="<?php echo esc_url(admin_url('admin.php?page=rc-crm-pesquisa')); ?>" class="roxo">🔍 Pesquisar</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rc-crm-calendario')); ?>" class="roxo">📅 Calendário</a>
                <a href="<?php echo esc_url($url_cadastro_cliente); ?>">➕ Cadastrar Cliente</a>
                <a href="<?php echo esc_url($url_cadastro_servico); ?>" class="roxo">➕ Cadastrar Serviço</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rc-crm-clientes')); ?>" class="verde">👥 Ver Clientes</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rc-crm-servicos')); ?>" class="verde">🔧 Ver Serviços</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rc-crm-armazem')); ?>" class="laranja">📦 Armazém</a>
                <a href="<?php echo esc_url($url_painel_clientes); ?>" class="laranja">📊 Painel Frontend</a>
            </div>
        </div>

        <div class="rc-dash-seccao">
            <h2>📅 Próximos Agendamentos</h2>
            <?php if (empty($proximos)): ?>
                <p class="rc-dash-vazio">Nenhum agendamento futuro.</p>
            <?php else: ?>
                <ul class="rc-dash-lista">
                    <?php foreach ($proximos as $p):
                        $label = $status_labels[$p->status] ?? ['—', '#999'];
                    ?>
                        <li>
                            <span class="rc-data"><?php echo esc_html(date_i18n('d/m H:i', strtotime($p->data_agendada))); ?></span>
                            <span class="rc-info">
                                <strong><?php echo esc_html($p->cliente_nome ?: '—'); ?></strong>
                                <br><small><?php echo esc_html($p->equipamento); ?></small>
                            </span>
                            <span class="rc-dash-badge" style="background:<?php echo esc_attr($label[1]); ?>;"><?php echo esc_html($label[0]); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
            <div class="rc-dash-seccao">
                <h2>🆕 Últimos Clientes</h2>
                <?php if (empty($ultimos_clientes)): ?>
                    <p class="rc-dash-vazio">Nenhum cliente cadastrado.</p>
                <?php else: ?>
                    <ul class="rc-dash-lista">
                        <?php foreach ($ultimos_clientes as $c): ?>
                            <li>
                                <span class="rc-info">
                                    <strong><?php echo esc_html($c->nome); ?></strong>
                                    <br><small><?php echo esc_html($c->codigo . ' — ' . $c->cidade); ?></small>
                                </span>
                                <span style="color:#999;font-size:12px;"><?php echo esc_html(date_i18n('d/m/Y', strtotime($c->created_at))); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="rc-dash-seccao">
                <h2>🔧 Últimos Serviços</h2>
                <?php if (empty($ultimos_servicos)): ?>
                    <p class="rc-dash-vazio">Nenhum serviço registado.</p>
                <?php else: ?>
                    <ul class="rc-dash-lista">
                        <?php foreach ($ultimos_servicos as $s):
                            $label = $status_labels[$s->status] ?? ['—', '#999'];
                        ?>
                            <li>
                                <span class="rc-info">
                                    <strong><?php echo esc_html($s->cliente_nome ?: '—'); ?></strong>
                                    <br><small><?php echo esc_html($s->equipamento); ?></small>
                                </span>
                                <span class="rc-dash-badge" style="background:<?php echo esc_attr($label[1]); ?>;"><?php echo esc_html($label[0]); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}