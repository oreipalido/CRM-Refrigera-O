<?php
/**
 * Refrigeração CRM — Calendário de Serviços
 * Com botão "+ Novo" em cada dia + modal
 *
 * @package Refrigeracao_CRM
 * @version 3.6.1
 */

if (!defined('ABSPATH')) exit;

function rc_crm_calendario_page() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');

    global $wpdb;
    $table_s    = $wpdb->prefix . 'rc_servicos';
    $table_c    = $wpdb->prefix . 'rc_clientes';
    $table_tags = $wpdb->prefix . 'rc_servico_tags';

    /* -------- Filtros -------- */
    $mes            = isset($_GET['mes'])        ? sanitize_text_field($_GET['mes'])        : date('Y-m');
    $filtro_tecnico = isset($_GET['f_tecnico'])  ? intval($_GET['f_tecnico'])                : 0;
    $filtro_status  = isset($_GET['f_status'])   ? sanitize_text_field($_GET['f_status'])   : '';
    $filtro_tag     = isset($_GET['f_tag'])      ? sanitize_text_field($_GET['f_tag'])      : '';

    if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = date('Y-m');

    $primeiro_dia = $mes . '-01';
    $ultimo_dia   = date('Y-m-t', strtotime($primeiro_dia));
    $mes_anterior = date('Y-m', strtotime($primeiro_dia . ' -1 month'));
    $mes_seguinte = date('Y-m', strtotime($primeiro_dia . ' +1 month'));
    $mes_atual    = date('Y-m');

    /* -------- Query -------- */
    $where  = ["s.data_agendada >= %s AND s.data_agendada < %s"];
    $params = [$primeiro_dia . ' 00:00:00', $ultimo_dia . ' 23:59:59'];

    if (!empty($filtro_tecnico)) { $where[] = "s.tecnico_id = %d"; $params[] = $filtro_tecnico; }
    if (!empty($filtro_status))  { $where[] = "s.status = %s";     $params[] = $filtro_status; }
    if (!empty($filtro_tag)) {
        $where[]  = "EXISTS (SELECT 1 FROM $table_tags t WHERE t.servico_id = s.id AND t.tag_slug = %s)";
        $params[] = $filtro_tag;
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

    $status_labels = [
        'instalacao' => ['Instalação', '#1877F2'],
        'orcamento'  => ['Orçamento',  '#dba617'],
        'concluido'  => ['Concluído',  '#00a32a'],
        'cancelado'  => ['Cancelado',  '#b32d2e'],
    ];

    $nome_mes         = date_i18n('F Y', strtotime($primeiro_dia));
    $tags_disponiveis = function_exists('rc_crm_tags_disponiveis') ? rc_crm_tags_disponiveis() : [];
    $tecnicos         = function_exists('rc_crm_obter_tecnicos')   ? rc_crm_obter_tecnicos()   : get_users(['orderby' => 'display_name']);
    $hoje             = date('Y-m-d');

    /* -------- URL da página "Cadastrar Serviço" -------- */
    if (function_exists('rc_crm_get_page_by_title')) {
        $pagina_servico = rc_crm_get_page_by_title('Cadastrar Serviço');
    } else {
        $pagina_servico = get_page_by_title('Cadastrar Serviço');
    }
    $url_base_novo_servico = $pagina_servico
        ? get_permalink($pagina_servico->ID)
        : admin_url('admin.php?page=rc-crm-servicos');

    /* -------- Helper: serializa um serviço para JSON -------- */
    $serializar_servico = function($s) use ($status_labels) {
        $label = $status_labels[$s->status] ?? ['—', '#999'];
        return [
            'id'          => $s->id,
            'hora'        => date('H:i', strtotime($s->data_agendada)),
            'cliente'     => $s->cliente_nome,
            'codigo'      => $s->cliente_codigo,
            'telefone'    => $s->cliente_telefone,
            'equipamento' => $s->equipamento,
            'status'      => $label[0],
            'cor'         => $label[1],
            'orcamento'   => $s->orcamento ? number_format($s->orcamento, 2, ',', '.') . ' €' : '—',
            'url_editar'  => admin_url('admin.php?page=rc-crm-servicos&action=editar&id=' . $s->id),
            'whatsapp'    => $s->cliente_telefone
                ? 'https://wa.me/351' . preg_replace('/\D/', '', $s->cliente_telefone)
                : '',
            'tags_html'   => function_exists('rc_crm_render_tags_servico')
                ? rc_crm_render_tags_servico($s->id)
                : '',
        ];
    };
    ?>
    <style>
    .rc-cal { max-width: 1400px; }
    .rc-cal-topo { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin: 20px 0; }
    .rc-cal-nav { display: flex; align-items: center; gap: 10px; }
    .rc-cal-nav a { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 8px; background: #fff; border: 1px solid #ddd; color: #333; text-decoration: none; font-size: 18px; font-weight: 700; }
    .rc-cal-nav a:hover { background: #1877F2; color: #fff; border-color: #1877F2; }
    .rc-cal-nav .titulo { font-size: 20px; font-weight: 700; color: #1a1a1a; min-width: 200px; text-align: center; }
    .rc-cal-nav .btn-hoje { background: #00a32a; color: #fff; border-color: #00a32a; font-size: 13px; padding: 0 18px; width: auto; height: 40px; }
    .rc-cal-nav .btn-hoje:hover { background: #007a1f; border-color: #007a1f; }

    .rc-cal-filtros { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 12px 16px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
    .rc-cal-filtros select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; min-width: 140px; }
    .rc-cal-filtros label { font-weight: 600; color: #333; font-size: 13px; }

    .rc-cal-grid { background: #fff; border: 1px solid #e0e0e0; border-radius: 10px; overflow: hidden; }
    .rc-cal-cabecalho { display: grid; grid-template-columns: repeat(7, 1fr); background: #1877F2; }
    .rc-cal-cabecalho div { padding: 12px; text-align: center; color: #fff; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }

    .rc-cal-semana { display: grid; grid-template-columns: repeat(7, 1fr); }
    .rc-cal-dia {
        border-right: 1px solid #f0f0f0;
        border-bottom: 1px solid #f0f0f0;
        min-height: 110px;
        padding: 8px;
        cursor: pointer;
        transition: background 0.15s;
        position: relative;
        overflow: hidden;
    }
    .rc-cal-dia:nth-child(7n) { border-right: none; }
    .rc-cal-dia:hover { background: #f9f9f9; }
    .rc-cal-dia.fora { background: #fafafa; color: #ccc; cursor: default; }
    .rc-cal-dia.fora:hover { background: #fafafa; }
    .rc-cal-dia.hoje { background: #fff9e5; }
    .rc-cal-dia.hoje .numero-dia { background: #dba617; color: #fff; }
    .rc-cal-dia .numero-dia {
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

    /* Botão "+ Novo" no canto do dia */
    .rc-cal-add {
        position: absolute;
        top: 6px;
        right: 6px;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: #1877F2;
        color: #fff !important;
        border: none;
        font-size: 16px;
        font-weight: 700;
        line-height: 1;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        opacity: 0;
        transition: opacity 0.15s, transform 0.15s;
        z-index: 2;
        padding: 0;
    }
    .rc-cal-dia:hover .rc-cal-add { opacity: 1; }
    .rc-cal-add:hover { background: #0C63D4; transform: scale(1.15); }

    .rc-cal-bloco {
        font-size: 11px;
        padding: 3px 6px;
        border-radius: 4px;
        color: #fff;
        margin-bottom: 3px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        cursor: pointer;
        transition: opacity 0.15s;
    }
    .rc-cal-bloco:hover { opacity: 0.85; }
    .rc-cal-mais { font-size: 10px; color: #666; font-weight: 600; text-align: right; margin-top: 2px; }

    .rc-cal-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        z-index: 99999;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .rc-cal-modal-overlay.aberto { display: flex; }
    .rc-cal-modal {
        background: #fff;
        border-radius: 12px;
        max-width: 700px;
        width: 100%;
        max-height: 85vh;
        overflow-y: auto;
        padding: 28px;
        position: relative;
    }
    .rc-cal-modal h2 { margin: 0 0 20px; font-size: 20px; }
    .rc-cal-modal-fechar {
        position: absolute;
        top: 16px;
        right: 20px;
        background: none;
        border: none;
        font-size: 28px;
        cursor: pointer;
        color: #999;
        line-height: 1;
    }
    .rc-cal-modal-fechar:hover { color: #333; }

    /* Botão "+ Novo Serviço" no topo do modal */
    .rc-cal-modal-novo {
        display: block;
        width: 100%;
        padding: 14px;
        background: #1877F2;
        color: #fff !important;
        text-align: center;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 700;
        font-size: 15px;
        margin-bottom: 20px;
        transition: background 0.15s;
        box-sizing: border-box;
    }
    .rc-cal-modal-novo:hover { background: #0C63D4; }

    .rc-cal-modal-item {
        padding: 16px;
        border-radius: 8px;
        background: #f9f9f9;
        margin-bottom: 10px;
        border-left: 4px solid #1877F2;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
    }
    .rc-cal-modal-item-info { flex: 1; }
    .rc-cal-modal-item-hora { font-weight: 700; color: #1877F2; font-size: 15px; }
    .rc-cal-modal-item-cliente { font-weight: 700; color: #1a1a1a; margin: 4px 0; font-size: 15px; }
    .rc-cal-modal-item-equip { font-size: 13px; color: #666; margin-bottom: 6px; }
    .rc-cal-modal-item-tags { margin-top: 6px; }
    .rc-cal-modal-item-acoes { flex-shrink: 0; }
    .rc-cal-modal-item-acoes a {
        display: inline-block;
        padding: 8px 16px;
        border-radius: 6px;
        background: #1877F2;
        color: #fff !important;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
    }
    .rc-cal-modal-item-acoes a:hover { opacity: 0.9; }

    @media (max-width: 900px) {
        .rc-cal-dia { min-height: 80px; font-size: 11px; }
        .rc-cal-bloco { font-size: 10px; padding: 2px 4px; }
        .rc-cal-nav .titulo { font-size: 16px; min-width: 150px; }
        .rc-cal-add { opacity: 1; }
    }
    </style>

    <div class="wrap rc-cal">
        <h1>📅 Calendário de Serviços</h1>

        <div class="rc-cal-topo">
            <div class="rc-cal-nav">
                <a href="<?php echo esc_url(add_query_arg(['mes' => $mes_anterior])); ?>" title="Mês anterior" aria-label="Mês anterior">‹</a>
                <span class="titulo"><?php echo esc_html(ucfirst($nome_mes)); ?></span>
                <a href="<?php echo esc_url(add_query_arg(['mes' => $mes_seguinte])); ?>" title="Mês seguinte" aria-label="Mês seguinte">›</a>
                <a href="<?php echo esc_url(add_query_arg(['mes' => $mes_atual])); ?>" class="btn-hoje">Hoje</a>
            </div>

            <form method="get" class="rc-cal-filtros">
                <input type="hidden" name="page" value="rc-crm-calendario">
                <input type="hidden" name="mes" value="<?php echo esc_attr($mes); ?>">

                <label>Técnico:</label>
                <select name="f_tecnico" onchange="this.form.submit()">
                    <option value="0">👥 Todos</option>
                    <?php foreach ($tecnicos as $u): ?>
                        <option value="<?php echo esc_attr($u->ID); ?>" <?php selected($filtro_tecnico, $u->ID); ?>>👤 <?php echo esc_html($u->display_name); ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Status:</label>
                <select name="f_status" onchange="this.form.submit()">
                    <option value="">📋 Todos</option>
                    <?php foreach ($status_labels as $slug => $info): ?>
                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($filtro_status, $slug); ?>><?php echo esc_html($info[0]); ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Etiqueta:</label>
                <select name="f_tag" onchange="this.form.submit()">
                    <option value="">🏷️ Todas</option>
                    <?php foreach ($tags_disponiveis as $slug => $tag): ?>
                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($filtro_tag, $slug); ?>><?php echo esc_html($tag[0]); ?></option>
                    <?php endforeach; ?>
                </select>

                <?php if ($filtro_tecnico || $filtro_status || $filtro_tag): ?>
                    <a href="<?php echo esc_url(add_query_arg(['f_tecnico' => false, 'f_status' => false, 'f_tag' => false])); ?>" style="color:#b32d2e; font-weight:600; text-decoration:none; font-size:13px;">✕ Limpar</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="rc-cal-grid">
            <div class="rc-cal-cabecalho">
                <div>Seg</div><div>Ter</div><div>Qua</div><div>Qui</div><div>Sex</div><div>Sáb</div><div>Dom</div>
            </div>

            <?php
            $total_celulas             = ceil(($primeiro_dia_semana - 1 + $dias_no_mes) / 7) * 7;
            $primeiro_dia_mes_anterior = date('t', strtotime($primeiro_dia . ' -1 month'));

            for ($i = 0; $i < $total_celulas; $i += 7):
                echo '<div class="rc-cal-semana">';

                for ($j = 1; $j <= 7; $j++):
                    $celula     = $i + $j;
                    $eh_dia_mes = ($celula >= $primeiro_dia_semana && $celula < $primeiro_dia_semana + $dias_no_mes);

                    if ($eh_dia_mes):
                        $numero_dia    = $celula - $primeiro_dia_semana + 1;
                        $data_completa = sprintf('%s-%02d', $mes, $numero_dia);
                        $servicos_dia  = $por_dia[$data_completa] ?? [];
                        $classe_hoje   = ($data_completa === $hoje) ? 'hoje' : '';

                        $todos_json = !empty($servicos_dia)
                            ? json_encode(array_map($serializar_servico, $servicos_dia), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                            : '[]';

                        /* URL para criar novo serviço nesta data (com hora padrão 09:00) */
                        $url_novo = add_query_arg([
                            'rc_data_agendada' => $data_completa . 'T09:00',
                        ], $url_base_novo_servico);
                        ?>
                        <div class="rc-cal-dia <?php echo esc_attr($classe_hoje); ?>"
                             data-dia="<?php echo esc_attr($data_completa); ?>"
                             data-servicos="<?php echo count($servicos_dia); ?>"
                             data-todos='<?php echo esc_attr($todos_json); ?>'>

                            <a href="<?php echo esc_url($url_novo); ?>"
                               class="rc-cal-add"
                               title="Criar novo serviço em <?php echo esc_attr(date_i18n('d/m/Y', strtotime($data_completa))); ?>"
                               aria-label="Criar novo serviço nesta data"
                               onclick="event.stopPropagation();">+</a>

                            <span class="numero-dia"><?php echo $numero_dia; ?></span>

                            <?php if (!empty($servicos_dia)): ?>
                                <?php foreach (array_slice($servicos_dia, 0, 3) as $s):
                                    $label = $status_labels[$s->status] ?? ['—', '#999'];
                                    $hora  = date('H:i', strtotime($s->data_agendada));
                                ?>
                                    <div class="rc-cal-bloco" style="background:<?php echo esc_attr($label[1]); ?>;">
                                        <?php echo esc_html($hora . ' · ' . mb_substr($s->cliente_nome ?: '—', 0, 15)); ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (count($servicos_dia) > 3): ?>
                                    <div class="rc-cal-mais">+<?php echo count($servicos_dia) - 3; ?> mais</div>
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
                        echo '<div class="rc-cal-dia fora"><span class="numero-dia">' . esc_html($numero_fora) . '</span></div>';
                    endif;
                endfor;

                echo '</div>';
            endfor;
            ?>
        </div>

        <div style="margin-top:20px; padding:16px 20px; background:#fff; border:1px solid #e0e0e0; border-radius:8px; display:flex; gap:20px; flex-wrap:wrap; align-items:center;">
            <strong style="color:#333; font-size:13px;">Legenda:</strong>
            <?php foreach ($status_labels as $slug => $info): ?>
                <span style="display:inline-flex; align-items:center; gap:6px; font-size:13px;">
                    <span style="display:inline-block; width:14px; height:14px; border-radius:3px; background:<?php echo esc_attr($info[1]); ?>;"></span>
                    <?php echo esc_html($info[0]); ?>
                </span>
            <?php endforeach; ?>
            <span style="margin-left:auto; font-size:12px; color:#999;">
                💡 Passe o rato sobre um dia para ver o botão <strong>+</strong> de novo serviço
            </span>
        </div>
    </div>

    <div class="rc-cal-modal-overlay" id="rc-cal-modal">
        <div class="rc-cal-modal">
            <button class="rc-cal-modal-fechar" id="rc-cal-modal-fechar" aria-label="Fechar">&times;</button>
            <h2 id="rc-cal-modal-titulo">Serviços</h2>
            <div id="rc-cal-modal-lista"></div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof rcAjax === 'undefined') {
            window.rcAjax = { url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>' };
        }

        const baseUrlNovo = '<?php echo esc_js($url_base_novo_servico); ?>';
        const overlay     = document.getElementById('rc-cal-modal');
        const fechar      = document.getElementById('rc-cal-modal-fechar');
        const titulo      = document.getElementById('rc-cal-modal-titulo');
        const lista       = document.getElementById('rc-cal-modal-lista');

        const dias = document.querySelectorAll('.rc-cal-dia:not(.fora)');

        dias.forEach(function(dia) {
            dia.addEventListener('click', function(e) {
                // Se clicou no botão "+" → deixa o link funcionar
                if (e.target.closest('.rc-cal-add')) return;

                // Se clicou num bloco individual → vai para edição
                const bloco = e.target.closest('.rc-cal-bloco');
                if (bloco) {
                    const data = dia.dataset.todos;
                    try {
                        const todos = JSON.parse(data);
                        const texto = bloco.textContent.trim();
                        const encontrado = todos.find(function(s) {
                            return texto.indexOf(s.hora) === 0;
                        });
                        if (encontrado) {
                            window.location.href = encontrado.url_editar;
                            return;
                        }
                    } catch (err) { console.error(err); }
                    return;
                }

                // Espaço vazio → abre modal
                let todos = [];
                try {
                    todos = JSON.parse(dia.dataset.todos || '[]');
                } catch (err) {
                    console.error(err);
                    return;
                }

                const data = dia.dataset.dia;
                const partes = data.split('-');
                const dataFormatada = partes[2] + '/' + partes[1] + '/' + partes[0];

                // Construir conteúdo do modal (mesmo com 0 serviços)
                let html = '';

                // Botão "+ Novo Serviço" no topo
                const urlNovo = baseUrlNovo + (baseUrlNovo.indexOf('?') >= 0 ? '&' : '?') + 'rc_data_agendada=' + encodeURIComponent(data + 'T09:00');
                html += '<a href="' + urlNovo + '" class="rc-cal-modal-novo">➕ Novo Serviço em ' + dataFormatada + '</a>';

                if (todos.length === 0) {
                    html += '<p style="text-align:center;color:#999;padding:20px;">Sem serviços agendados neste dia.</p>';
                } else {
                    todos.forEach(function(s) {
                        html += '<div class="rc-cal-modal-item" style="border-left-color:' + s.cor + ';">';
                        html += '<div class="rc-cal-modal-item-info">';
                        html += '<div class="rc-cal-modal-item-hora">🕐 ' + s.hora + '</div>';
                        html += '<div class="rc-cal-modal-item-cliente">👤 ' + (s.cliente || '—') + ' <span style="color:#999;font-weight:400;font-size:13px;">(' + (s.codigo || '—') + ')</span></div>';
                        html += '<div class="rc-cal-modal-item-equip">🔧 ' + (s.equipamento || '—') + '</div>';
                        html += '<div class="rc-cal-modal-item-equip">💰 ' + s.orcamento + ' · Status: <span style="background:' + s.cor + ';color:#fff;padding:2px 8px;border-radius:8px;font-size:11px;">' + s.status + '</span></div>';
                        if (s.tags_html) html += '<div class="rc-cal-modal-item-tags">' + s.tags_html + '</div>';
                        html += '</div>';
                        html += '<div class="rc-cal-modal-item-acoes">';
                        if (s.whatsapp) html += '<a href="' + s.whatsapp + '" target="_blank" rel="noopener" style="background:#25d366;margin-bottom:6px;display:block;">💬 WhatsApp</a>';
                        html += '<a href="' + s.url_editar + '">✏️ Editar</a>';
                        html += '</div></div>';
                    });
                }

                titulo.textContent = '📅 ' + dataFormatada + ' — ' + todos.length + ' serviço(s)';
                lista.innerHTML = html;
                overlay.classList.add('aberto');
            });
        });

        function fecharModal() { overlay.classList.remove('aberto'); }
        fechar.addEventListener('click', fecharModal);
        overlay.addEventListener('click', function(e) { if (e.target === overlay) fecharModal(); });
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape') fecharModal(); });
    });
    </script>
    <?php
}