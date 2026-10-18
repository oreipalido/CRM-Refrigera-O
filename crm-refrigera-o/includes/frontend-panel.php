<?php
/**
 * Refrigeração CRM — Painel de Clientes (frontend, shortcode [rc_painel_clientes])
 *
 * @package Refrigeracao_CRM
 * @version 3.5.1
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
   SHORTCODE
   ============================================================ */
add_shortcode('rc_painel_clientes', 'rc_crm_painel_clientes');
function rc_crm_painel_clientes() {
    if (!current_user_can('manage_options')) return 'Acesso restrito.';

    global $wpdb;
    $table = $wpdb->prefix . 'rc_clientes';

    $per_page = 12;
    $paged    = isset($_GET['rc_pag']) ? max(1, intval($_GET['rc_pag'])) : 1;
    $offset   = ($paged - 1) * $per_page;

    $cidade_filtro = isset($_GET['rc_cidade']) ? sanitize_text_field($_GET['rc_cidade']) : '';

    /* -------- Construir WHERE + params corretamente -------- */
    $where  = '';
    $params = [];
    if (!empty($cidade_filtro)) {
        $where    = "WHERE cidade = %s";
        $params[] = $cidade_filtro;
    }

    if (!empty($params)) {
        $total    = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table $where",
            $params
        ));
        $clientes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table $where ORDER BY nome ASC LIMIT %d OFFSET %d",
            array_merge($params, [$per_page, $offset])
        ));
    } else {
        $total    = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $clientes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY nome ASC LIMIT %d OFFSET %d",
            $per_page, $offset
        ));
    }

    $total_pages = (int) ceil($total / $per_page);

    $cidades = $wpdb->get_col("SELECT DISTINCT cidade FROM $table WHERE cidade != '' ORDER BY cidade ASC");

    /* -------- URL base de edição -------- */
    if (function_exists('rc_crm_get_page_by_title')) {
        $pagina_editar = rc_crm_get_page_by_title('Cadastrar Cliente');
    } else {
        $pagina_editar = get_page_by_title('Cadastrar Cliente');
    }
    $url_base_editar = $pagina_editar ? get_permalink($pagina_editar->ID) : '';

    /* -------- Nonce para o AJAX -------- */
    $nonce_ajax = wp_create_nonce('rc_servicos_cliente');

    ob_start();
    ?>
    <div class="rc-painel" data-nonce="<?php echo esc_attr($nonce_ajax); ?>">
        <div class="rc-painel-controles">
            <input type="text" id="rc-busca" placeholder="🔍 Pesquisar por nome, código, telefone...">
            <select id="rc-filtro-cidade">
                <option value="">Todas as cidades</option>
                <?php foreach ($cidades as $c): ?>
                    <option value="<?php echo esc_attr($c); ?>" <?php selected($cidade_filtro, $c); ?>><?php echo esc_html($c); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if (empty($clientes)): ?>
            <p class="rc-sem-resultados">Nenhum cliente encontrado.</p>
        <?php else: ?>
            <div class="rc-grid" id="rc-grid">
                <?php foreach ($clientes as $c):
                    $busca_dados    = strtolower($c->nome . ' ' . $c->codigo . ' ' . $c->telefone . ' ' . $c->cidade . ' ' . $c->email);
                    $inicial        = $c->nome ? strtoupper(mb_substr($c->nome, 0, 1)) : '?';
                    $telefone_limpo = preg_replace('/\D/', '', $c->telefone);
                    $whatsapp       = !empty($telefone_limpo) ? 'https://wa.me/351' . $telefone_limpo : '';
                    $maps           = ($c->latitude && $c->longitude) ? 'https://www.google.com/maps?q=' . $c->latitude . ',' . $c->longitude : '';
                    $url_editar     = $url_base_editar ? add_query_arg('rc_editar', (int) $c->id, $url_base_editar) : '';
                ?>
                    <div class="rc-card"
                         data-busca="<?php echo esc_attr($busca_dados); ?>"
                         data-cidade="<?php echo esc_attr($c->cidade); ?>"
                         data-id="<?php echo esc_attr($c->id); ?>"
                         data-codigo="<?php echo esc_attr($c->codigo); ?>"
                         data-nome="<?php echo esc_attr($c->nome); ?>"
                         data-inicial="<?php echo esc_attr($inicial); ?>"
                         data-telefone="<?php echo esc_attr($c->telefone); ?>"
                         data-email="<?php echo esc_attr($c->email); ?>"
                         data-endereco="<?php echo esc_attr(trim($c->endereco . ' ' . $c->complemento)); ?>"
                         data-cidade-completa="<?php echo esc_attr($c->cidade); ?>"
                         data-cep="<?php echo esc_attr($c->cep); ?>"
                         data-obs="<?php echo esc_attr($c->observacoes); ?>"
                         data-mapa="<?php echo esc_attr($maps); ?>"
                         data-whatsapp="<?php echo esc_attr($whatsapp); ?>"
                         data-editar="<?php echo esc_attr($url_editar); ?>">
                        <div class="rc-card-topo">
                            <div class="rc-avatar"><?php echo esc_html($inicial); ?></div>
                            <div>
                                <span class="rc-card-codigo"><?php echo esc_html($c->codigo); ?></span>
                                <div class="rc-card-nome"><?php echo esc_html($c->nome); ?></div>
                            </div>
                        </div>
                        <?php if ($c->telefone): ?><div class="rc-card-info">📞 <?php echo esc_html($c->telefone); ?></div><?php endif; ?>
                        <?php if ($c->email): ?><div class="rc-card-info">✉️ <?php echo esc_html($c->email); ?></div><?php endif; ?>
                        <?php if ($c->cidade): ?><div class="rc-card-info">📍 <?php echo esc_html($c->cidade); ?></div><?php endif; ?>

                        <div class="rc-card-acoes">
                            <?php if ($whatsapp): ?>
                                <a href="<?php echo esc_url($whatsapp); ?>" target="_blank" rel="noopener" class="rc-btn-whats" onclick="event.stopPropagation();">💬 WhatsApp</a>
                            <?php endif; ?>
                            <?php if ($maps): ?>
                                <a href="<?php echo esc_url($maps); ?>" target="_blank" rel="noopener" class="rc-btn-mapa" onclick="event.stopPropagation();">🗺️ Mapa</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="rc-paginacao">
                    <?php
                    $args_pag = ['base' => add_query_arg('rc_pag', '%#%'), 'format' => '', 'current' => $paged, 'total' => $total_pages];
                    if (!empty($cidade_filtro)) {
                        $args_pag['add_args'] = ['rc_cidade' => $cidade_filtro];
                    }
                    echo paginate_links($args_pag);
                    ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="rc-modal-overlay" id="rc-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="rc-modal-nome">
        <div class="rc-modal">
            <button class="rc-modal-fechar" id="rc-modal-fechar" aria-label="Fechar">&times;</button>
            <div class="rc-modal-topo">
                <div class="rc-avatar" id="rc-modal-avatar"></div>
                <div>
                    <span class="rc-codigo" id="rc-modal-codigo"></span>
                    <h2 id="rc-modal-nome"></h2>
                </div>
            </div>
            <div id="rc-modal-conteudo"></div>

            <div class="rc-modal-seccao" id="rc-modal-servicos" style="display:none;">
                <h3>🔧 Histórico de Serviços</h3>
                <div id="rc-modal-servicos-lista"></div>
            </div>

            <div class="rc-modal-acoes" id="rc-modal-acoes"></div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        /* -------- Fallback rcAjax -------- */
        if (typeof rcAjax === 'undefined') {
            window.rcAjax = { url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>' };
        }

        /* -------- Escapar HTML para usar em innerHTML -------- */
        function escHtml(s) {
            if (s === null || s === undefined) return '';
            const d = document.createElement('div');
            d.textContent = String(s);
            return d.innerHTML;
        }

        /* -------- Normalizar para busca (remover acentos) -------- */
        function normalizar(s) {
            return String(s).toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '');
        }

        const painel     = document.querySelector('.rc-painel');
        const nonce      = painel ? painel.dataset.nonce : '';
        const busca      = document.getElementById('rc-busca');
        const filtroCid  = document.getElementById('rc-filtro-cidade');
        const cards      = document.querySelectorAll('.rc-card');
        const overlay    = document.getElementById('rc-modal-overlay');
        const modalFechar= document.getElementById('rc-modal-fechar');

        /* -------- Filtro client-side (busca + cidade) -------- */
        function filtrar() {
            const termo  = normalizar(busca ? busca.value.trim() : '');
            const cidade = filtroCid ? filtroCid.value : '';

            cards.forEach(function(card) {
                const buscaOk  = !termo || normalizar(card.dataset.busca).includes(termo);
                const cidadeOk = !cidade || card.dataset.cidade === cidade;
                card.style.display = (buscaOk && cidadeOk) ? '' : 'none';
            });
        }

        if (busca)     busca.addEventListener('input', filtrar);
        if (filtroCid) filtroCid.addEventListener('change', filtrar);

        /* -------- Abrir modal -------- */
        cards.forEach(function(card) {
            card.addEventListener('click', function() {
                const d = this.dataset;

                document.getElementById('rc-modal-codigo').textContent = d.codigo || '';
                document.getElementById('rc-modal-nome').textContent   = d.nome   || '';
                document.getElementById('rc-modal-avatar').textContent = d.inicial || '?';

                let html = '';
                if (d.telefone)       html += '<div class="rc-modal-linha"><strong>Telefone</strong><span>'      + escHtml(d.telefone)       + '</span></div>';
                if (d.email)          html += '<div class="rc-modal-linha"><strong>Email</strong><span>'         + escHtml(d.email)          + '</span></div>';
                if (d.endereco)       html += '<div class="rc-modal-linha"><strong>Endereço</strong><span>'      + escHtml(d.endereco)       + '</span></div>';
                if (d.cidadeCompleta) html += '<div class="rc-modal-linha"><strong>Cidade</strong><span>'        + escHtml(d.cidadeCompleta) + '</span></div>';
                if (d.cep)            html += '<div class="rc-modal-linha"><strong>CEP</strong><span>'           + escHtml(d.cep)            + '</span></div>';
                if (d.obs)            html += '<div class="rc-modal-linha"><strong>Observações</strong><span>'   + escHtml(d.obs)            + '</span></div>';
                document.getElementById('rc-modal-conteudo').innerHTML = html;

                let acoes = '';
                if (d.whatsapp && /^https?:\/\//.test(d.whatsapp)) {
                    acoes += '<a href="' + escHtml(d.whatsapp) + '" target="_blank" rel="noopener" class="rc-btn-whats">💬 WhatsApp</a>';
                }
                if (d.mapa && /^https?:\/\//.test(d.mapa)) {
                    acoes += '<a href="' + escHtml(d.mapa) + '" target="_blank" rel="noopener" class="rc-btn-mapa">🗺️ Maps</a>';
                }
                if (d.editar && /^https?:\/\//.test(d.editar)) {
                    acoes += '<a href="' + escHtml(d.editar) + '" class="rc-btn-mapa" style="background:#7c3aed;color:#fff !important;">✏️ Editar</a>';
                }
                document.getElementById('rc-modal-acoes').innerHTML = acoes;

                /* -------- Carregar serviços via AJAX -------- */
                const clienteId = d.id;
                const seccao    = document.getElementById('rc-modal-servicos');
                const lista     = document.getElementById('rc-modal-servicos-lista');
                seccao.style.display = 'block';
                lista.innerHTML = '<p style="color:#999;">A carregar...</p>';

                const url = rcAjax.url + '?action=rc_servicos_cliente&cliente_id=' + encodeURIComponent(clienteId) + '&nonce=' + encodeURIComponent(nonce);

                fetch(url, { credentials: 'same-origin' })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            lista.innerHTML = data.data.html;
                        } else {
                            lista.innerHTML = '<p style="color:#b32d2e;">' + escHtml(data.data || 'Erro ao carregar serviços.') + '</p>';
                        }
                    })
                    .catch(function(err) {
                        console.error(err);
                        lista.innerHTML = '<p style="color:#b32d2e;">Erro de ligação.</p>';
                    });

                overlay.classList.add('rc-aberto');
            });
        });

        /* -------- Fechar modal -------- */
        if (modalFechar) modalFechar.addEventListener('click', function() { overlay.classList.remove('rc-aberto'); });
        if (overlay)     overlay.addEventListener('click', function(e) { if (e.target === overlay) overlay.classList.remove('rc-aberto'); });
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape') overlay.classList.remove('rc-aberto'); });
    });
    </script>
    <?php
    return ob_get_clean();
}

/* ============================================================
   AJAX: devolver serviços de um cliente
   ============================================================ */
add_action('wp_ajax_rc_servicos_cliente', 'rc_crm_ajax_servicos_cliente');
function rc_crm_ajax_servicos_cliente() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Sem permissão');
    }

    check_ajax_referer('rc_servicos_cliente', 'nonce');

    $cliente_id = isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : 0;
    if (!$cliente_id) {
        wp_send_json_error('Cliente inválido');
    }

    global $wpdb;
    $servicos = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}rc_servicos WHERE cliente_id = %d ORDER BY created_at DESC",
        $cliente_id
    ));

    $status_labels = [
        'instalacao' => ['Instalação', '#2271b1'],
        'orcamento'  => ['Orçamento',  '#dba617'],
        'concluido'  => ['Concluído',  '#00a32a'],
        'cancelado'  => ['Cancelado',  '#b32d2e'],
    ];

    $html = '';
    if (empty($servicos)) {
        $html = '<p style="color:#999;font-style:italic;">Nenhum serviço registado.</p>';
    } else {
        foreach ($servicos as $s) {
            $label = $status_labels[$s->status] ?? ['—', '#999'];
            $data  = $s->data_agendada ? date_i18n('d/m/Y', strtotime($s->data_agendada)) : '—';
            $orc   = $s->orcamento ? number_format((float) $s->orcamento, 2, ',', '.') . ' €' : '—';

            $html .= '<div class="rc-servico-item">';
            $html .=   '<div class="rc-servico-topo">';
            $html .=     '<span class="rc-servico-equip">' . esc_html($s->equipamento) . '</span>';
            $html .=     '<span class="rc-badge-status" style="background:' . esc_attr($label[1]) . ';">' . esc_html($label[0]) . '</span>';
            $html .=   '</div>';
            $html .=   '<div class="rc-servico-meta">📅 ' . esc_html($data) . ' · 💰 ' . esc_html($orc) . '</div>';
            $html .= '</div>';
        }
    }

    wp_send_json_success(['html' => $html]);
}