<?php
/**
 * Refrigeração CRM — Painel do Técnico + Minhas Comissões
 *
 * @package Refrigeracao_CRM
 * @version 3.6.8
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
   SHORTCODE: [rc_dashboard_tecnico]
   ============================================================ */
add_shortcode('rc_dashboard_tecnico', 'rc_crm_dashboard_tecnico');
function rc_crm_dashboard_tecnico() {
    if (!rc_crm_pode_acessar_painel()) return 'Acesso restrito.';

    global $wpdb;
    $table_s = $wpdb->prefix . 'rc_servicos';
    $table_c = $wpdb->prefix . 'rc_clientes';

    $user    = wp_get_current_user();
    $e_admin = rc_crm_e_admin();

    /* -------- Filtro de técnico -------- */
    $tecnico_filtro = 0;
    if ($e_admin && isset($_GET['rc_tecnico'])) {
        $tecnico_filtro = intval($_GET['rc_tecnico']);
    } elseif (!$e_admin) {
        $tecnico_filtro = $user->ID;
    }

    $where  = [];
    $params = [];

    if ($tecnico_filtro) {
        $where[]  = "s.tecnico_id = %d";
        $params[] = $tecnico_filtro;
    }

    $sql_where = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT s.*, c.nome AS cliente_nome, c.telefone AS cliente_telefone, c.cidade AS cliente_cidade
            FROM $table_s s
            LEFT JOIN $table_c c ON s.cliente_id = c.id
            $sql_where
            ORDER BY s.data_agendada ASC";

    if (!empty($params)) $sql = $wpdb->prepare($sql, $params);

    $todos = $wpdb->get_results($sql);

    /* -------- Timezone consistente -------- */
    $hoje       = wp_date('Y-m-d');
    $proximos_7 = wp_date('Y-m-d', strtotime('+7 days'));

    $servicos_hoje       = [];
    $servicos_semana     = [];
    $atrasados           = [];
    $concluidos_recentes = [];

    foreach ($todos as $s) {
        if (!$s->data_agendada) continue;
        $data = date('Y-m-d', strtotime($s->data_agendada));

        if ($s->status === 'concluido') {
            if (strtotime($s->data_agendada) >= strtotime('-7 days')) {
                $concluidos_recentes[] = $s;
            }
            continue;
        }
        if ($s->status === 'cancelado') continue;

        if ($data < $hoje)            $atrasados[]       = $s;
        elseif ($data === $hoje)      $servicos_hoje[]   = $s;
        elseif ($data <= $proximos_7) $servicos_semana[] = $s;
    }

    $status_labels = [
        'instalacao' => ['Instalação', '#1877F2'],
        'orcamento'  => ['Orçamento',  '#dba617'],
        'concluido'  => ['Concluído',  '#00a32a'],
        'cancelado'  => ['Cancelado',  '#b32d2e'],
    ];

    /* -------- Cache de técnicos -------- */
    $tecnicos_cache = [];
    if ($e_admin) {
        $ids_servicos = array_filter(array_column($todos, 'tecnico_id'));
        $ids_unicos   = array_unique($ids_servicos);
        if (!empty($ids_unicos)) {
            $users = get_users(['include' => $ids_unicos, 'fields' => ['ID', 'display_name']]);
            foreach ($users as $u) {
                $tecnicos_cache[$u->ID] = $u->display_name;
            }
        }
    }

    /* -------- Nonce para AJAX -------- */
    $nonce_ajax = wp_create_nonce('rc_mudar_status');

    ob_start();
    ?>
    <div class="rc-dash-tec" data-nonce="<?php echo esc_attr($nonce_ajax); ?>">
        <div class="rc-dash-tec-topo">
            <div>
                <h1>👋 Olá, <?php echo esc_html($user->display_name); ?>!</h1>
                <p class="rc-dash-tec-sub"><?php echo esc_html(date_i18n('l, d \d\e F \d\e Y')); ?></p>
            </div>
            <div class="rc-dash-tec-acoes">
                <?php if ($e_admin): ?>
                    <form method="get" class="rc-dash-tec-filtro">
                        <select name="rc_tecnico" onchange="this.form.submit()">
                            <option value="0">Todos os técnicos</option>
                            <?php foreach (rc_crm_obter_tecnicos() as $u): ?>
                                <option value="<?php echo esc_attr($u->ID); ?>" <?php selected($tecnico_filtro, $u->ID); ?>>
                                    <?php echo esc_html($u->display_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                <?php endif; ?>
                <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="rc-dash-tec-sair" title="Terminar sessão">↪ Sair</a>
            </div>
        </div>

        <div class="rc-dash-tec-stats">
            <div class="rc-stat hoje"><div class="rc-stat-num"><?php echo count($servicos_hoje); ?></div><div class="rc-stat-lbl">Hoje</div></div>
            <div class="rc-stat semana"><div class="rc-stat-num"><?php echo count($servicos_semana); ?></div><div class="rc-stat-lbl">Próx. 7 dias</div></div>
            <div class="rc-stat atrasado"><div class="rc-stat-num"><?php echo count($atrasados); ?></div><div class="rc-stat-lbl">Atrasados</div></div>
            <div class="rc-stat concluido"><div class="rc-stat-num"><?php echo count($concluidos_recentes); ?></div><div class="rc-stat-lbl">Concluídos</div></div>
        </div>

        <?php if (!empty($atrasados)): ?>
            <div class="rc-dash-tec-seccao rc-atrasados">
                <h2>⚠️ Serviços Atrasados</h2>
                <div class="rc-servicos-grid">
                    <?php foreach ($atrasados as $s) rc_crm_render_card_servico($s, $status_labels, true, $e_admin, $tecnicos_cache); ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="rc-dash-tec-seccao">
            <h2>📅 Hoje (<?php echo count($servicos_hoje); ?>)</h2>
            <?php if (empty($servicos_hoje)): ?>
                <p class="rc-dash-tec-vazio">Sem serviços agendados para hoje. 🎉</p>
            <?php else: ?>
                <div class="rc-servicos-grid">
                    <?php foreach ($servicos_hoje as $s) rc_crm_render_card_servico($s, $status_labels, false, $e_admin, $tecnicos_cache); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="rc-dash-tec-seccao">
            <h2>🗓️ Próximos 7 dias (<?php echo count($servicos_semana); ?>)</h2>
            <?php if (empty($servicos_semana)): ?>
                <p class="rc-dash-tec-vazio">Sem serviços agendados.</p>
            <?php else: ?>
                <div class="rc-servicos-grid">
                    <?php foreach ($servicos_semana as $s) rc_crm_render_card_servico($s, $status_labels, false, $e_admin, $tecnicos_cache); ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($concluidos_recentes)): ?>
            <div class="rc-dash-tec-seccao">
                <h2>✅ Concluídos Recentemente</h2>
                <div class="rc-servicos-grid">
                    <?php foreach ($concluidos_recentes as $s) rc_crm_render_card_servico($s, $status_labels, false, $e_admin, $tecnicos_cache); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/* ============================================================
   CARD DE SERVIÇO
   ============================================================ */
if (!function_exists('rc_crm_render_card_servico')) {
    function rc_crm_render_card_servico($s, $status_labels, $atrasado = false, $e_admin = false, $tecnicos_cache = []) {
        $label     = $status_labels[$s->status] ?? ['—', '#999'];
        $data_hora = $s->data_agendada ? date_i18n('d/m/Y H:i', strtotime($s->data_agendada)) : '—';
        $orc       = $s->orcamento ? number_format((float) $s->orcamento, 2, ',', '.') . ' €' : '—';

        $nome_tecnico = '';
        if ($e_admin && !empty($s->tecnico_id)) {
            $nome_tecnico = $tecnicos_cache[$s->tecnico_id] ?? '';
        }

        $tel_limpo = $s->cliente_telefone ? preg_replace('/\D/', '', $s->cliente_telefone) : '';
        $whatsapp  = !empty($tel_limpo) ? 'https://wa.me/351' . $tel_limpo : '';
        ?>
        <div class="rc-servico-card <?php echo $atrasado ? 'rc-servico-atrasado' : ''; ?>" data-id="<?php echo esc_attr($s->id); ?>">
            <div class="rc-servico-card-topo">
                <span class="rc-servico-data">📅 <?php echo esc_html($data_hora); ?></span>
                <span class="rc-badge-status" style="background:<?php echo esc_attr($label[1]); ?>;"><?php echo esc_html($label[0]); ?></span>
            </div>

            <?php if (function_exists('rc_crm_render_tags_servico')): ?>
                <?php $tags_html = rc_crm_render_tags_servico($s->id); ?>
                <?php if ($tags_html): ?>
                    <div style="margin-bottom:8px;"><?php echo $tags_html; ?></div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="rc-servico-card-corpo">
                <div class="rc-servico-cliente"><?php echo esc_html($s->cliente_nome ?: '—'); ?></div>
                <?php if ($s->cliente_cidade): ?><div class="rc-servico-info">📍 <?php echo esc_html($s->cliente_cidade); ?></div><?php endif; ?>
                <?php if ($s->equipamento): ?><div class="rc-servico-info">🔧 <?php echo esc_html($s->equipamento); ?></div><?php endif; ?>
                <div class="rc-servico-info">💰 <?php echo esc_html($orc); ?></div>
                <?php if ($nome_tecnico): ?>
                    <div class="rc-servico-info">👤 <?php echo esc_html($nome_tecnico); ?></div>
                <?php endif; ?>
            </div>

            <div class="rc-servico-card-acoes">
                <?php if ($whatsapp): ?>
                    <a href="<?php echo esc_url($whatsapp); ?>" target="_blank" rel="noopener" class="rc-btn-whats" title="Enviar WhatsApp">💬</a>
                <?php endif; ?>
                <select class="rc-mudar-status" data-id="<?php echo esc_attr($s->id); ?>" title="Alterar status do serviço">
                    <option value="">Mudar status...</option>
                    <option value="instalacao">Instalação</option>
                    <option value="orcamento">Orçamento</option>
                    <option value="concluido">✓ Concluir</option>
                    <option value="cancelado">Cancelar</option>
                </select>
            </div>
        </div>
        <?php
    }
}

/* ============================================================
   AJAX: MUDAR STATUS
   ============================================================ */
add_action('wp_ajax_rc_mudar_status_servico', 'rc_crm_ajax_mudar_status_servico');
function rc_crm_ajax_mudar_status_servico() {
    if (!rc_crm_pode_acessar_painel()) {
        wp_send_json_error('Sem permissão');
    }

    check_ajax_referer('rc_mudar_status', 'nonce');

    $servico_id  = isset($_POST['servico_id']) ? intval($_POST['servico_id']) : 0;
    $novo_status = isset($_POST['status'])     ? sanitize_text_field($_POST['status']) : '';

    if (!$servico_id || !$novo_status) {
        wp_send_json_error('Dados incompletos');
    }

    $status_validos = ['instalacao', 'orcamento', 'concluido', 'cancelado'];
    if (!in_array($novo_status, $status_validos, true)) {
        wp_send_json_error('Status inválido');
    }

    global $wpdb;
    $table_s = $wpdb->prefix . 'rc_servicos';

    if (!rc_crm_e_admin()) {
        $user_id    = get_current_user_id();
        $tecnico_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT tecnico_id FROM $table_s WHERE id = %d",
            $servico_id
        ));

        if ($tecnico_id !== $user_id) {
            wp_send_json_error('Sem permissão.');
        }
    }

    $status_anterior = $wpdb->get_var($wpdb->prepare(
        "SELECT status FROM $table_s WHERE id = %d",
        $servico_id
    ));

    if ($status_anterior === null) {
        wp_send_json_error('Serviço não encontrado.');
    }

    if ($status_anterior === $novo_status) {
        wp_send_json_success(['mensagem' => 'Status já estava definido.']);
    }

    $dados = ['status' => $novo_status];

    if ($novo_status === 'concluido') {
        $dados['data_conclusao'] = current_time('mysql');
    } else {
        $dados['data_conclusao']      = null;
        $dados['comissao_percentual'] = null;
        $dados['comissao_valor']      = null;
        $dados['comissao_paga_em']    = null;
    }

    $wpdb->update($table_s, $dados, ['id' => $servico_id]);

    if ($novo_status === 'concluido' && $status_anterior !== 'concluido') {
        if (function_exists('rc_crm_calcular_comissao_servico')) {
            rc_crm_calcular_comissao_servico($servico_id);
        }
        if (function_exists('rc_crm_disparar_notificacoes_servico')) {
            rc_crm_disparar_notificacoes_servico($servico_id, $status_anterior);
        }
    }

    wp_send_json_success(['mensagem' => 'Status atualizado!']);
}

/* ============================================================
   JS DO DASHBOARD
   ============================================================ */
add_action('wp_footer', 'rc_crm_dashboard_js');
function rc_crm_dashboard_js() {
    if (!is_singular()) return;

    global $post;
    if (!$post || !has_shortcode($post->post_content, 'rc_dashboard_tecnico')) return;
    ?>
    <script>
    if (typeof rcAjax === 'undefined') {
        window.rcAjax = { url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>' };
    }
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const dash = document.querySelector('.rc-dash-tec');
        const nonce = dash ? dash.dataset.nonce : '';

        document.querySelectorAll('.rc-mudar-status').forEach(function(sel) {
            sel.addEventListener('change', function() {
                const status = this.value;
                if (!status) return;

                const card  = this.closest('.rc-servico-card');
                const id    = this.dataset.id;
                const badge = card.querySelector('.rc-badge-status');

                this.disabled = true;

                const formData = new FormData();
                formData.append('action', 'rc_mudar_status_servico');
                formData.append('servico_id', id);
                formData.append('status', status);
                formData.append('nonce', nonce);

                fetch(rcAjax.url, { method: 'POST', body: formData, credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const cores = { instalacao: '#1877F2', orcamento: '#dba617', concluido: '#00a32a', cancelado: '#b32d2e' };
                            const nomes = { instalacao: 'Instalação', orcamento: 'Orçamento', concluido: 'Concluído', cancelado: 'Cancelado' };
                            badge.style.background = cores[status];
                            badge.textContent = nomes[status];

                            if (status === 'concluido' || status === 'cancelado') {
                                card.style.opacity = '0.5';
                                setTimeout(() => { card.style.display = 'none'; }, 500);
                            }
                        } else {
                            alert('Erro: ' + (data.data || 'Não foi possível'));
                        }
                        sel.disabled = false;
                        sel.value = '';
                    })
                    .catch(function(err) {
                        console.error(err);
                        alert('Erro de ligação.');
                        sel.disabled = false;
                        sel.value = '';
                    });
            });
        });
    });
    </script>
    <?php
}

/* ============================================================
   SHORTCODE: [rc_minhas_comissoes]
   ============================================================ */
add_shortcode('rc_minhas_comissoes', 'rc_crm_minhas_comissoes');
function rc_crm_minhas_comissoes() {
    if (!is_user_logged_in()) return 'Acesso restrito.';

    $user    = wp_get_current_user();
    $e_admin = rc_crm_e_admin();

    if (!$e_admin && !rc_crm_e_tecnico_ou_admin($user->ID)) {
        return 'Acesso restrito.';
    }

    $tecnico_id = $user->ID;
    if ($e_admin && isset($_GET['rc_tec'])) {
        $tecnico_id = intval($_GET['rc_tec']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'rc_servicos';

    $mes = isset($_GET['rc_mes']) ? sanitize_text_field($_GET['rc_mes']) : date('Y-m');
    if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = date('Y-m');

    $comissoes = $wpdb->get_results($wpdb->prepare(
        "SELECT s.id, s.data_conclusao, s.orcamento, s.comissao_percentual, s.comissao_valor, s.comissao_paga_em,
                c.nome AS cliente_nome, c.codigo AS cliente_codigo
         FROM $table s
         LEFT JOIN {$wpdb->prefix}rc_clientes c ON s.cliente_id = c.id
         WHERE s.tecnico_id = %d
           AND s.status = 'concluido'
           AND s.comissao_valor IS NOT NULL
           AND DATE_FORMAT(s.data_conclusao, '%%Y-%%m') = %s
         ORDER BY s.data_conclusao DESC",
        $tecnico_id, $mes
    ));

    $total_mes      = 0;
    $total_pago     = 0;
    $total_pendente = 0;

    foreach ($comissoes as $c) {
        $total_mes += floatval($c->comissao_valor);
        if ($c->comissao_paga_em) $total_pago += floatval($c->comissao_valor);
        else                       $total_pendente += floatval($c->comissao_valor);
    }

    $comissao_pct = rc_crm_obter_comissao_tecnico($tecnico_id);

    ob_start();
    ?>
    <div class="rc-painel" style="max-width:1000px;">
        <div class="rc-painel-topo">
            <h1>💰 As Minhas Comissões</h1>
            <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="rc-dash-tec-sair" title="Terminar sessão">↪ Sair</a>
        </div>

        <p style="color:#777; margin-bottom:20px;">
            A sua percentagem de comissão: <strong style="color:#00a32a; font-size:18px;"><?php echo number_format($comissao_pct, 2, ',', '.'); ?>%</strong>
        </p>

        <form method="get" style="display:flex; gap:10px; margin-bottom:20px; align-items:center; flex-wrap:wrap;">
            <label>Mês:</label>
            <input type="month" name="rc_mes" value="<?php echo esc_attr($mes); ?>" onchange="this.form.submit()">

            <?php if ($e_admin): ?>
                <label>Técnico:</label>
                <select name="rc_tec" onchange="this.form.submit()">
                    <?php foreach (rc_crm_obter_tecnicos() as $u): ?>
                        <option value="<?php echo esc_attr($u->ID); ?>" <?php selected($tecnico_id, $u->ID); ?>><?php echo esc_html($u->display_name); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </form>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:16px; margin-bottom:30px;">
            <div style="background:#fff; border-left:4px solid #1877F2; border-radius:8px; padding:20px;">
                <div style="font-size:12px; color:#666; text-transform:uppercase;">Total do Mês</div>
                <div style="font-size:24px; font-weight:700; color:#1877F2; margin-top:4px;"><?php echo number_format($total_mes, 2, ',', '.'); ?> €</div>
            </div>
            <div style="background:#fff; border-left:4px solid #00a32a; border-radius:8px; padding:20px;">
                <div style="font-size:12px; color:#666; text-transform:uppercase;">Já Pago</div>
                <div style="font-size:24px; font-weight:700; color:#00a32a; margin-top:4px;"><?php echo number_format($total_pago, 2, ',', '.'); ?> €</div>
            </div>
            <div style="background:#fff; border-left:4px solid #dba617; border-radius:8px; padding:20px;">
                <div style="font-size:12px; color:#666; text-transform:uppercase;">Por Pagar</div>
                <div style="font-size:24px; font-weight:700; color:#dba617; margin-top:4px;"><?php echo number_format($total_pendente, 2, ',', '.'); ?> €</div>
            </div>
        </div>

        <h2 style="font-size:16px;">📋 Detalhes</h2>
        <?php if (empty($comissoes)): ?>
            <p style="text-align:center; padding:40px; color:#999; background:#f9f9f9; border-radius:8px;">Nenhuma comissão neste mês.</p>
        <?php else: ?>
            <table style="width:100%; border-collapse:collapse; background:#fff; border-radius:8px; overflow:hidden;">
                <thead>
                    <tr style="background:#f5f5f5;">
                        <th style="padding:12px; text-align:left; font-size:12px; color:#666; text-transform:uppercase;">Data</th>
                        <th style="padding:12px; text-align:left; font-size:12px; color:#666; text-transform:uppercase;">Cliente</th>
                        <th style="padding:12px; text-align:right; font-size:12px; color:#666; text-transform:uppercase;">Valor Serviço</th>
                        <th style="padding:12px; text-align:center; font-size:12px; color:#666; text-transform:uppercase;">%</th>
                        <th style="padding:12px; text-align:right; font-size:12px; color:#666; text-transform:uppercase;">Comissão</th>
                        <th style="padding:12px; text-align:center; font-size:12px; color:#666; text-transform:uppercase;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comissoes as $c): ?>
                        <tr style="border-bottom:1px solid #f0f0f0;">
                            <td style="padding:12px; font-size:14px;"><?php echo esc_html(date_i18n('d/m/Y', strtotime($c->data_conclusao))); ?></td>
                            <td style="padding:12px; font-size:14px;"><?php echo esc_html($c->cliente_nome); ?> <small style="color:#999;">(<?php echo esc_html($c->cliente_codigo); ?>)</small></td>
                            <td style="padding:12px; text-align:right; font-size:14px;"><?php echo number_format(floatval($c->orcamento), 2, ',', '.'); ?> €</td>
                            <td style="padding:12px; text-align:center; font-size:14px; color:#666;"><?php echo number_format(floatval($c->comissao_percentual), 2, ',', '.'); ?>%</td>
                            <td style="padding:12px; text-align:right; font-size:14px; font-weight:700; color:#00a32a;"><?php echo number_format(floatval($c->comissao_valor), 2, ',', '.'); ?> €</td>
                            <td style="padding:12px; text-align:center; font-size:12px;">
                                <?php if ($c->comissao_paga_em): ?>
                                    <span style="background:#00a32a; color:#fff; padding:3px 10px; border-radius:10px; font-weight:600;">✅ Paga</span>
                                <?php else: ?>
                                    <span style="background:#dba617; color:#fff; padding:3px 10px; border-radius:10px; font-weight:600;">⏳ Pendente</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}