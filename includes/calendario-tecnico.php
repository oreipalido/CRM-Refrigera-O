<?php
/**
 * Refrigeração CRM — Calendário do Técnico (frontend)
 *
 * Shortcode: [rc_calendario_tecnico]
 * Mostra os serviços do técnico logado em vista de calendário mensal.
 *
 * @package Refrigeracao_CRM
 * @version 3.7.3
 */

if (!defined('ABSPATH')) exit;

add_shortcode('rc_calendario_tecnico', 'rc_crm_calendario_tecnico_shortcode');
function rc_crm_calendario_tecnico_shortcode() {
    if (!is_user_logged_in()) {
        return '<div class="rc-ac-login-aviso"><p>Precisa iniciar sessão para ver o calendário.</p></div>';
    }

    $user    = wp_get_current_user();
    $e_admin = rc_crm_e_admin();

    // Só técnicos e admins
    if (!$e_admin && !rc_crm_e_tecnico_ou_admin($user->ID)) {
        return '<div class="rc-ac-login-aviso"><p>Esta área é reservada a técnicos.</p></div>';
    }

    global $wpdb;
    $table_s = $wpdb->prefix . 'rc_servicos';
    $table_c = $wpdb->prefix . 'rc_clientes';

    /* -------- Mês atual -------- */
    $mes = isset($_GET['mes']) ? sanitize_text_field($_GET['mes']) : date('Y-m');
    if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = date('Y-m');

    $primeiro_dia = $mes . '-01';
    $ultimo_dia   = date('Y-m-t', strtotime($primeiro_dia));
    $mes_anterior = date('Y-m', strtotime($primeiro_dia . ' -1 month'));
    $mes_seguinte = date('Y-m', strtotime($primeiro_dia . ' +1 month'));
    $mes_atual    = date('Y-m');

    /* -------- Query: serviços do técnico neste mês -------- */
    $where  = ["s.data_agendada >= %s AND s.data_agendada < %s"];
    $params = [$primeiro_dia . ' 00:00:00', $ultimo_dia . ' 23:59:59'];

    // Admin vê todos; técnico vê os dele
    if (!$e_admin) {
        $where[]  = "s.tecnico_id = %d";
        $params[] = $user->ID;
    } elseif (isset($_GET['f_tecnico'])) {
        $where[]  = "s.tecnico_id = %d";
        $params[] = intval($_GET['f_tecnico']);
    }

    $sql = "SELECT s.*, c.nome AS cliente_nome, c.codigo AS cliente_codigo, c.telefone AS cliente_telefone
            FROM $table_s s
            LEFT JOIN $table_c c ON s.cliente_id = c.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY s.data_agendada ASC";

    $servicos = $wpdb->get_results($wpdb->prepare($sql, $params));

    /* -------- Agrupar por dia -------- */
    $por_dia = [];
    foreach ($servicos as $s) {
        $dia = date('Y-m-d', strtotime($s->data_agendada));
        if (!isset($por_dia[$dia])) $por_dia[$dia] = [];
        $por_dia[$dia][] = $s;
    }

    $primeiro_dia_semana = (int) date('N', strtotime($primeiro_dia));
    $dias_no_mes         = (int) date('t', strtotime($primeiro_dia));
    $hoje                = date('Y-m-d');

    $status_labels = [
        'instalacao' => ['Instalação', '#1877F2'],
        'orcamento'  => ['Orçamento',  '#dba617'],
        'concluido'  => ['Concluído',  '#00a32a'],
        'cancelado'  => ['Cancelado',  '#b32d2e'],
    ];

    $nome_mes = date_i18n('F Y', strtotime($primeiro_dia));

    /* -------- Total do mês -------- */
    $total_mes = count($servicos);

    ob_start();
    ?>
    <div class="rc-cal-tec">
        <div class="rc-cal-tec-topo">
            <div class="rc-cal-tec-nav">
                <a href="<?php echo esc_url(add_query_arg('mes', $mes_anterior)); ?>" title="Mês anterior">‹</a>
                <span class="rc-cal-tec-titulo"><?php echo esc_html(ucfirst($nome_mes)); ?></span>
                <a href="<?php echo esc_url(add_query_arg('mes', $mes_seguinte)); ?>" title="Mês seguinte">›</a>
                <a href="<?php echo esc_url(add_query_arg('mes', $mes_atual)); ?>" class="rc-cal-tec-hoje">Hoje</a>
            </div>
            <div class="rc-cal-tec-info">
                📅 <strong><?php echo (int) $total_mes; ?></strong> serviço(s) em <?php echo esc_html(date_i18n('F', strtotime($primeiro_dia))); ?>
            </div>
        </div>

        <div class="rc-cal-tec-grid">
            <div class="rc-cal-tec-cabecalho">
                <div>Seg</div><div>Ter</div><div>Qua</div><div>Qui</div><div>Sex</div><div>Sáb</div><div>Dom</div>
            </div>

            <?php
            $total_celulas = ceil(($primeiro_dia_semana - 1 + $dias_no_mes) / 7) * 7;
            $primeiro_dia_mes_anterior = date('t', strtotime($primeiro_dia . ' -1 month'));

            for ($i = 0; $i < $total_celulas; $i += 7):
                echo '<div class="rc-cal-tec-semana">';

                for ($j = 1; $j <= 7; $j++):
                    $celula = $i + $j;
                    $eh_dia_mes = ($celula >= $primeiro_dia_semana && $celula < $primeiro_dia_semana + $dias_no_mes);

                    if ($eh_dia_mes):
                        $numero_dia    = $celula - $primeiro_dia_semana + 1;
                        $data_completa = sprintf('%s-%02d', $mes, $numero_dia);
                        $servicos_dia  = $por_dia[$data_completa] ?? [];
                        $classe_hoje   = ($data_completa === $hoje) ? 'hoje' : '';
                        ?>
                        <div class="rc-cal-tec-dia <?php echo esc_attr($classe_hoje); ?>" data-dia="<?php echo esc_attr($data_completa); ?>">
                            <span class="rc-cal-tec-numero"><?php echo $numero_dia; ?></span>

                            <?php if (!empty($servicos_dia)): ?>
                                <?php foreach (array_slice($servicos_dia, 0, 3) as $s):
                                    $label = $status_labels[$s->status] ?? ['—', '#999'];
                                    $hora  = date('H:i', strtotime($s->data_agendada));
                                ?>
                                    <div class="rc-cal-tec-bloco" style="background:<?php echo esc_attr($label[1]); ?>;">
                                        <?php echo esc_html($hora . ' · ' . mb_substr($s->cliente_nome ?: '—', 0, 15)); ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (count($servicos_dia) > 3): ?>
                                    <div class="rc-cal-tec-mais">+<?php echo count($servicos_dia) - 3; ?> mais</div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php
                    else:
                        if ($celula < $primeiro_dia_semana) {
                            $numero_fora = $primeiro_dia_mes_anterior - ($primeiro_dia_semana - $celula - 1);
                        } else {
                            $numero_fora = $celula - ($primeiro_dia_semana + $dias_no_mes - 1);
                        }
                        echo '<div class="rc-cal-tec-dia fora"><span class="rc-cal-tec-numero">' . esc_html($numero_fora) . '</span></div>';
                    endif;
                endfor;

                echo '</div>';
            endfor;
            ?>
        </div>

        <!-- Lista detalhada do mês -->
        <?php if (!empty($servicos)): ?>
            <div class="rc-cal-tec-lista">
                <h3>📋 Detalhes do mês</h3>
                <?php foreach ($servicos as $s):
                    $label = $status_labels[$s->status] ?? ['—', '#999'];
                ?>
                    <div class="rc-cal-tec-item" style="border-left-color:<?php echo esc_attr($label[1]); ?>;">
                        <div class="rc-cal-tec-item-data">
                            <strong><?php echo esc_html(date_i18n('d/m', strtotime($s->data_agendada))); ?></strong>
                            <small><?php echo esc_html(date_i18n('H:i', strtotime($s->data_agendada))); ?></small>
                        </div>
                        <div class="rc-cal-tec-item-info">
                            <strong><?php echo esc_html($s->cliente_nome ?: '—'); ?></strong>
                            <small><?php echo esc_html($s->equipamento ?: '(sem equipamento)'); ?></small>
                        </div>
                        <div class="rc-cal-tec-item-acoes">
                            <span class="rc-cal-tec-badge" style="background:<?php echo esc_attr($label[1]); ?>;"><?php echo esc_html($label[0]); ?></span>
                            <?php if ($s->cliente_telefone): ?>
                                <a href="https://wa.me/351<?php echo esc_attr(preg_replace('/\D/', '', $s->cliente_telefone)); ?>"
                                   target="_blank" rel="noopener" class="rc-cal-tec-whats" title="WhatsApp">💬</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="rc-cal-tec-vazio">
                <p>🎉 Sem serviços agendados neste mês.</p>
            </div>
        <?php endif; ?>
    </div>

    <style>
    .rc-cal-tec {
        max-width: 1100px;
        margin: 0 auto;
        padding: 20px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    .rc-cal-tec-topo {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 20px;
    }
    .rc-cal-tec-nav {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .rc-cal-tec-nav a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background: #fff;
        border: 1px solid #ddd;
        color: #333;
        text-decoration: none;
        font-size: 18px;
        font-weight: 700;
        transition: all 0.15s;
    }
    .rc-cal-tec-nav a:hover {
        background: #1877F2;
        color: #fff;
        border-color: #1877F2;
    }
    .rc-cal-tec-titulo {
        font-size: 20px;
        font-weight: 700;
        color: #1a1a1a;
        min-width: 200px;
        text-align: center;
    }
    .rc-cal-tec-hoje {
        background: #00a32a !important;
        color: #fff !important;
        border-color: #00a32a !important;
        width: auto !important;
        padding: 0 18px !important;
        font-size: 13px !important;
    }
    .rc-cal-tec-info {
        font-size: 14px;
        color: #666;
        background: #f9f9f9;
        padding: 8px 16px;
        border-radius: 8px;
    }
    .rc-cal-tec-info strong { color: #1877F2; }

    .rc-cal-tec-grid {
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 30px;
    }
    .rc-cal-tec-cabecalho {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        background: #1877F2;
    }
    .rc-cal-tec-cabecalho div {
        padding: 12px;
        text-align: center;
        color: #fff;
        font-weight: 700;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .rc-cal-tec-semana {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
    }
    .rc-cal-tec-dia {
        border-right: 1px solid #f0f0f0;
        border-bottom: 1px solid #f0f0f0;
        min-height: 100px;
        padding: 8px;
        position: relative;
        background: #fff;
        transition: background 0.15s;
    }
    .rc-cal-tec-dia:nth-child(7n) { border-right: none; }
    .rc-cal-tec-dia:hover { background: #f9f9f9; }
    .rc-cal-tec-dia.fora {
        background: #fafafa;
        color: #ccc;
    }
    .rc-cal-tec-dia.hoje {
        background: #fff9e5;
    }
    .rc-cal-tec-dia.hoje .rc-cal-tec-numero {
        background: #dba617;
        color: #fff;
    }
    .rc-cal-tec-numero {
        display: inline-block;
        width: 26px;
        height: 26px;
        line-height: 26px;
        text-align: center;
        border-radius: 50%;
        font-weight: 700;
        font-size: 13px;
        color: #333;
        margin-bottom: 6px;
    }
    .rc-cal-tec-bloco {
        font-size: 11px;
        padding: 3px 6px;
        border-radius: 4px;
        color: #fff;
        margin-bottom: 3px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        cursor: default;
    }
    .rc-cal-tec-mais {
        font-size: 10px;
        color: #666;
        font-weight: 600;
        text-align: right;
        margin-top: 2px;
    }
    .rc-cal-tec-lista {
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        padding: 20px;
    }
    .rc-cal-tec-lista h3 {
        margin: 0 0 16px;
        font-size: 16px;
        color: #333;
        padding-bottom: 12px;
        border-bottom: 2px solid #f0f2f5;
    }
    .rc-cal-tec-item {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 12px;
        background: #f9f9f9;
        border-radius: 8px;
        border-left: 4px solid #1877F2;
        margin-bottom: 10px;
    }
    .rc-cal-tec-item-data {
        min-width: 70px;
        text-align: center;
    }
    .rc-cal-tec-item-data strong {
        display: block;
        font-size: 16px;
        color: #1877F2;
    }
    .rc-cal-tec-item-data small {
        color: #999;
        font-size: 12px;
    }
    .rc-cal-tec-item-info {
        flex: 1;
    }
    .rc-cal-tec-item-info strong {
        display: block;
        font-size: 15px;
        color: #1a1a1a;
        margin-bottom: 4px;
    }
    .rc-cal-tec-item-info small {
        color: #666;
        font-size: 13px;
    }
    .rc-cal-tec-item-acoes {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .rc-cal-tec-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
        color: #fff;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
    }
    .rc-cal-tec-whats {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: #25d366;
        color: #fff;
        text-decoration: none;
        font-size: 18px;
    }
    .rc-cal-tec-whats:hover { opacity: 0.85; }
    .rc-cal-tec-vazio {
        text-align: center;
        padding: 60px 20px;
        color: #999;
        background: #f9f9f9;
        border-radius: 10px;
    }

    @media (max-width: 900px) {
        .rc-cal-tec-dia { min-height: 80px; font-size: 11px; }
        .rc-cal-tec-bloco { font-size: 10px; padding: 2px 4px; }
        .rc-cal-tec-titulo { font-size: 16px; min-width: 150px; }
        .rc-cal-tec-item { flex-wrap: wrap; }
    }

    /* Modo escuro */
    body.rc-dark-mode .rc-cal-tec-titulo { color: #e0e0e0; }
    body.rc-dark-mode .rc-cal-tec-info { background: #2c2c2c; color: #a0a0a0; }
    body.rc-dark-mode .rc-cal-tec-grid,
    body.rc-dark-mode .rc-cal-tec-lista {
        background: #2c2c2c;
        border-color: #444;
    }
    body.rc-dark-mode .rc-cal-tec-dia {
        background: #2c2c2c;
        border-color: #3a3a3a;
        color: #e0e0e0;
    }
    body.rc-dark-mode .rc-cal-tec-dia:hover { background: #3a3a3a; }
    body.rc-dark-mode .rc-cal-tec-dia.fora { background: #262626; color: #555; }
    body.rc-dark-mode .rc-cal-tec-dia.hoje { background: #3a2f0a; }
    body.rc-dark-mode .rc-cal-tec-numero { color: #e0e0e0; }
    body.rc-dark-mode .rc-cal-tec-lista h3 {
        color: #e0e0e0;
        border-bottom-color: #444;
    }
    body.rc-dark-mode .rc-cal-tec-item { background: #3a3a3a; }
    body.rc-dark-mode .rc-cal-tec-item-info strong { color: #fff; }
    body.rc-dark-mode .rc-cal-tec-item-info small { color: #a0a0a0; }
    body.rc-dark-mode .rc-cal-tec-vazio { background: #2c2c2c; color: #a0a0a0; }
    body.rc-dark-mode .rc-cal-tec-nav a {
        background: #2c2c2c;
        border-color: #444;
        color: #e0e0e0;
    }
    </style>
    <?php
    return ob_get_clean();
}