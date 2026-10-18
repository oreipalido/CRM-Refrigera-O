<?php
/**
 * Refrigeração CRM — Shortcodes + Formulários + Barra de Navegação + Homepage
 *
 * @package Refrigeracao_CRM
 * @version 3.7.3
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
   HELPER: role atual do utilizador
   ============================================================ */
if (!function_exists('rc_crm_role_atual')) {
    function rc_crm_role_atual() {
        if (!is_user_logged_in()) return 'visitante';

        $user  = wp_get_current_user();
        $roles = (array) $user->roles;

        if (in_array('administrator', $roles, true))   return 'admin';
        if (in_array('tecnico', $roles, true))         return 'tecnico';
        if (in_array('cliente_lss', $roles, true))     return 'cliente';

        return 'outro';
    }
}

/* ============================================================
   SHORTCODE: [rc_nav]
   ============================================================ */
add_shortcode('rc_nav', 'rc_crm_nav_shortcode');
function rc_crm_nav_shortcode($atts) {
    $atts = shortcode_atts([
        'pagina' => '',
        'modo'   => '',
    ], $atts);

    $role     = rc_crm_role_atual();
    $pagina   = $atts['pagina'];
    $compacto = ($atts['modo'] === 'compacto');

    $botoes = [];

    if ($role === 'admin') {
        $botoes = [
            ['icon' => '🏠', 'label' => 'Painel CRM',      'url' => admin_url('admin.php?page=rc-crm'),           'slug' => 'painel-crm'],
            ['icon' => '👥', 'label' => 'Clientes',        'url' => admin_url('admin.php?page=rc-crm-clientes'),  'slug' => 'clientes'],
            ['icon' => '🔧', 'label' => 'Serviços',        'url' => admin_url('admin.php?page=rc-crm-servicos'),  'slug' => 'servicos'],
            ['icon' => '📅', 'label' => 'Calendário',      'url' => admin_url('admin.php?page=rc-crm-calendario'),'slug' => 'calendario'],
            ['icon' => '💰', 'label' => 'Comissões',       'url' => admin_url('admin.php?page=rc-crm-comissoes'), 'slug' => 'comissoes'],
            ['icon' => '📊', 'label' => 'Relatórios',      'url' => admin_url('admin.php?page=rc-crm-relatorios'),'slug' => 'relatorios'],
            ['icon' => '⚙️', 'label' => 'Configurações',   'url' => admin_url('admin.php?page=rc-crm-config'),    'slug' => 'config'],
        ];
    } elseif ($role === 'tecnico') {
        $botoes = [
            ['icon' => '🏠', 'label' => 'Painel',      'url' => home_url('/painel-do-tecnico/'), 'slug' => 'painel-tecnico'],
            ['icon' => '📅', 'label' => 'Calendário',  'url' => home_url('/calendario/'),        'slug' => 'calendario'],
            ['icon' => '💰', 'label' => 'Comissões',   'url' => home_url('/minhas-comissoes/'),  'slug' => 'minhas-comissoes'],
        ];
    } elseif ($role === 'cliente') {
        $botoes = [
            ['icon' => '🏠', 'label' => 'Meus Serviços', 'url' => home_url('/minha-conta/?tab=servicos'), 'slug' => 'minha-conta'],
            ['icon' => '➕', 'label' => 'Novo Pedido',   'url' => home_url('/minha-conta/?tab=novo'),     'slug' => 'novo-pedido'],
            ['icon' => '👤', 'label' => 'Meus Dados',    'url' => home_url('/minha-conta/?tab=dados'),    'slug' => 'meus-dados'],
        ];
    } else {
        $botoes = [
            ['icon' => '🔑', 'label' => 'Entrar',            'url' => wp_login_url(home_url('/portal/')), 'slug' => 'entrar'],
            ['icon' => '👤', 'label' => 'Área de Cliente',   'url' => home_url('/minha-conta/'),          'slug' => 'minha-conta'],
            ['icon' => '👷', 'label' => 'Painel do Técnico', 'url' => home_url('/painel-do-tecnico/'),    'slug' => 'painel-tecnico'],
        ];
    }

    $botao_sair = is_user_logged_in()
        ? ['icon' => '↪', 'label' => 'Sair', 'url' => wp_logout_url(home_url('/'))]
        : null;

    ob_start();
    ?>
    <div class="rc-nav <?php echo $compacto ? 'rc-nav-compacto' : ''; ?>" data-role="<?php echo esc_attr($role); ?>">

        <div class="rc-nav-botoes">
            <?php foreach ($botoes as $b): ?>
                <a href="<?php echo esc_url($b['url']); ?>"
                   class="rc-nav-btn <?php echo $pagina === $b['slug'] ? 'rc-nav-btn-ativo' : ''; ?>"
                   title="<?php echo esc_attr($b['label']); ?>">
                    <span class="rc-nav-btn-icon"><?php echo esc_html($b['icon']); ?></span>
                    <?php if (!$compacto): ?><span class="rc-nav-btn-label"><?php echo esc_html($b['label']); ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="rc-nav-acoes">
            <button type="button"
                    class="rc-nav-btn rc-nav-dark"
                    id="rc-nav-dark-toggle"
                    title="Alternar modo escuro"
                    aria-label="Alternar modo escuro">
                <span class="rc-nav-btn-icon" id="rc-nav-dark-icon">🌙</span>
            </button>

            <?php if ($botao_sair): ?>
                <a href="<?php echo esc_url($botao_sair['url']); ?>"
                   class="rc-nav-btn rc-nav-sair"
                   title="Terminar sessão">
                    <span class="rc-nav-btn-icon"><?php echo esc_html($botao_sair['icon']); ?></span>
                    <?php if (!$compacto): ?><span class="rc-nav-btn-label"><?php echo esc_html($botao_sair['label']); ?></span><?php endif; ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <script>
    (function() {
        var btn  = document.getElementById('rc-nav-dark-toggle');
        var icon = document.getElementById('rc-nav-dark-icon');
        if (!btn || !icon) return;

        function atualizarIcone() {
            var escuro = document.body.classList.contains('rc-dark-mode');
            icon.textContent = escuro ? '☀️' : '🌙';
        }
        atualizarIcone();

        btn.addEventListener('click', function() {
            var escuro = document.body.classList.contains('rc-dark-mode');
            document.body.classList.toggle('rc-dark-mode', !escuro);
            document.documentElement.classList.toggle('rc-dark-mode', !escuro);
            try { localStorage.setItem('rc_dark_mode', escuro ? 'light' : 'dark'); } catch(e) {}
            atualizarIcone();
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/* ============================================================
   SHORTCODE: [rc_inicio]
   ============================================================ */
add_shortcode('rc_inicio', 'rc_crm_inicio_shortcode');
function rc_crm_inicio_shortcode() {
    $config = function_exists('rc_crm_get_config') ? rc_crm_get_config() : [];
    $logado = is_user_logged_in();

    $empresa_nome     = $config['empresa_nome']     ?? 'LSS Frios';
    $empresa_telefone = $config['empresa_telefone'] ?? '210 000 000';
    $empresa_email    = $config['empresa_email']    ?? 'geral@empresa.pt';

    $role = function_exists('rc_crm_role_atual') ? rc_crm_role_atual() : 'visitante';

    if ($role === 'admin') {
        $botoes = [
            ['icon' => '📊', 'label' => 'Painel CRM',    'desc' => 'Aceder ao sistema',           'url' => admin_url('admin.php?page=rc-crm'),           'cor' => 'azul'],
            ['icon' => '👥', 'label' => 'Clientes',      'desc' => 'Gerir clientes',              'url' => admin_url('admin.php?page=rc-crm-clientes'),  'cor' => 'roxo'],
            ['icon' => '🔧', 'label' => 'Serviços',      'desc' => 'Gerir serviços',              'url' => admin_url('admin.php?page=rc-crm-servicos'),  'cor' => 'verde'],
            ['icon' => '📅', 'label' => 'Calendário',    'desc' => 'Ver agendamentos',            'url' => admin_url('admin.php?page=rc-crm-calendario'),'cor' => 'laranja'],
        ];
    } elseif ($role === 'tecnico') {
        $botoes = [
            ['icon' => '🏠', 'label' => 'Meu Painel',    'desc' => 'Serviços do dia',             'url' => home_url('/painel-do-tecnico/'), 'cor' => 'azul'],
            ['icon' => '📅', 'label' => 'Calendário',    'desc' => 'Ver agenda do mês',           'url' => home_url('/calendario/'),        'cor' => 'laranja'],
            ['icon' => '💰', 'label' => 'Comissões',     'desc' => 'Ver as minhas comissões',     'url' => home_url('/minhas-comissoes/'),  'cor' => 'verde'],
        ];
    } elseif ($role === 'cliente') {
        $botoes = [
            ['icon' => '🏠', 'label' => 'Meus Serviços', 'desc' => 'Ver os meus serviços',        'url' => home_url('/minha-conta/?tab=servicos'), 'cor' => 'azul'],
            ['icon' => '➕', 'label' => 'Novo Pedido',   'desc' => 'Solicitar novo serviço',      'url' => home_url('/minha-conta/?tab=novo'),     'cor' => 'verde'],
            ['icon' => '👤', 'label' => 'Meus Dados',    'desc' => 'Atualizar os meus dados',     'url' => home_url('/minha-conta/?tab=dados'),    'cor' => 'roxo'],
        ];
    } else {
        $botoes = [
            ['icon' => '🔑', 'label' => 'Entrar',           'desc' => 'Já sou cliente/técnico',    'url' => wp_login_url(home_url('/portal/')), 'cor' => 'azul'],
            ['icon' => '👤', 'label' => 'Área de Cliente',  'desc' => 'Consultar os meus serviços','url' => home_url('/minha-conta/'),          'cor' => 'verde'],
            ['icon' => '👷', 'label' => 'Painel do Técnico','desc' => 'Aceder aos serviços do dia','url' => home_url('/painel-do-tecnico/'),   'cor' => 'laranja'],
            ['icon' => '📞', 'label' => 'Contactos',        'desc' => 'Falar com a equipa',        'url' => home_url('/contactos/'),            'cor' => 'roxo'],
        ];
    }

    ob_start();
    ?>
    <div class="rc-inicio">

        <div class="rc-inicio-hero">
            <div class="rc-inicio-hero-conteudo">
                <h1><?php echo esc_html($empresa_nome); ?></h1>
                <p>Especialistas em refrigeração, ar condicionado e assistência técnica</p>
                <?php if ($logado): ?>
                    <?php
                    $user  = wp_get_current_user();
                    $role_nome = function_exists('rc_crm_descrever_role') ? rc_crm_descrever_role() : 'utilizador';
                    ?>
                    <p class="rc-inicio-bemvindo">Olá, <strong><?php echo esc_html($user->display_name); ?></strong>! Entraste como <strong><?php echo esc_html($role_nome); ?></strong>.</p>
                <?php else: ?>
                    <p class="rc-inicio-tagline">Escolhe como queres entrar 👇</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="rc-inicio-botoes">
            <?php foreach ($botoes as $b): ?>
                <a href="<?php echo esc_url($b['url']); ?>" class="rc-inicio-btn rc-inicio-btn-<?php echo esc_attr($b['cor']); ?>">
                    <span class="rc-inicio-btn-icon"><?php echo esc_html($b['icon']); ?></span>
                    <span class="rc-inicio-btn-texto">
                        <strong><?php echo esc_html($b['label']); ?></strong>
                        <small><?php echo esc_html($b['desc']); ?></small>
                    </span>
                    <span class="rc-inicio-btn-seta">→</span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="rc-inicio-servicos">
            <h2>Os nossos serviços</h2>
            <div class="rc-inicio-servicos-grid">
                <div class="rc-inicio-servico">
                    <span class="rc-inicio-servico-icon">❄️</span>
                    <h3>Ar Condicionado</h3>
                    <p>Instalação, manutenção e reparação de sistemas de ar condicionado.</p>
                </div>
                <div class="rc-inicio-servico">
                    <span class="rc-inicio-servico-icon">🧊</span>
                    <h3>Câmaras Frigoríficas</h3>
                    <p>Instalação e assistência a câmaras de refrigeração e congeladores.</p>
                </div>
                <div class="rc-inicio-servico">
                    <span class="rc-inicio-servico-icon">🔧</span>
                    <h3>Assistência Técnica</h3>
                    <p>Reparações rápidas e serviço de manutenção preventiva.</p>
                </div>
                <div class="rc-inicio-servico">
                    <span class="rc-inicio-servico-icon">📋</span>
                    <h3>Orçamentos</h3>
                    <p>Orçamentos gratuitos e sem compromisso para qualquer serviço.</p>
                </div>
            </div>
        </div>

        <div class="rc-inicio-contactos">
            <h2>Fala connosco</h2>
            <div class="rc-inicio-contactos-grid">
                <a href="tel:<?php echo esc_attr(preg_replace('/\D/', '', $empresa_telefone)); ?>" class="rc-inicio-contacto">
                    <span>📞</span>
                    <strong><?php echo esc_html($empresa_telefone); ?></strong>
                </a>
                <a href="mailto:<?php echo esc_attr($empresa_email); ?>" class="rc-inicio-contacto">
                    <span>✉️</span>
                    <strong><?php echo esc_html($empresa_email); ?></strong>
                </a>
                <a href="<?php echo esc_url(home_url('/contactos/')); ?>" class="rc-inicio-contacto">
                    <span>📍</span>
                    <strong>Ver contactos</strong>
                </a>
            </div>
        </div>

        <div class="rc-inicio-rodape">
            <p>© <?php echo esc_html(date_i18n('Y')); ?> <?php echo esc_html($empresa_nome); ?> — Todos os direitos reservados</p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/* ============================================================
   SHORTCODE: [rc_nav_acao]
   ============================================================ */
add_shortcode('rc_nav_acao', 'rc_crm_nav_acao_shortcode');
function rc_crm_nav_acao_shortcode($atts) {
    $atts = shortcode_atts([
        'label' => 'Botão',
        'url'   => '#',
        'icon'  => '',
        'cor'   => 'azul',
    ], $atts);

    $cores = ['azul', 'vermelho', 'verde', 'laranja', 'roxo'];
    $cor = in_array($atts['cor'], $cores, true) ? $atts['cor'] : 'azul';

    return '<a href="' . esc_url($atts['url']) . '" class="rc-nav-btn rc-nav-btn-' . esc_attr($cor) . '" style="display:inline-flex;margin:4px 6px 4px 0;">'
        . ($atts['icon'] ? '<span class="rc-nav-btn-icon">' . esc_html($atts['icon']) . '</span>' : '')
        . '<span class="rc-nav-btn-label">' . esc_html($atts['label']) . '</span>'
        . '</a>';
}

/* ============================================================
   NOTA: [rc_link_painel] está definido em area-cliente.php
   ============================================================ */

/* ============================================================
   FILTRO: esconder itens do menu (fallback)
   ============================================================ */
add_filter('wp_nav_menu_objects', 'rc_crm_esconder_paginas_menu', 10, 2);
function rc_crm_esconder_paginas_menu($items, $args) {
    if (current_user_can('manage_options')) return $items;

    $role = rc_crm_role_atual();

    $permitidas_por_role = [
        'admin'     => [],
        'tecnico'   => ['painel-do-tecnico', 'minhas-comissoes', 'calendario', 'portal'],
        'cliente'   => ['minha-conta', 'portal'],
        'visitante' => ['portal', 'contactos', 'sobre', 'servicos'],
        'outro'     => ['portal'],
    ];
    $permitidas = $permitidas_por_role[$role] ?? [];

    $paginas_crm = ['painel-do-tecnico', 'minhas-comissoes', 'minha-conta', 'portal', 'calendario'];

    $sempre_escondidas = [
        'orcamento', 'cadastrar-cliente', 'cadastrar-servico',
        'cadastro', 'lista-de-clientes', 'clientes', 'manual',
        'como-cadastrar-servicos', 'como-cadastrar-clientes',
    ];

    foreach ($items as $key => $item) {
        $slug = $item->post_name ?? '';
        if (empty($slug) && !empty($item->url)) {
            $url_path = parse_url($item->url, PHP_URL_PATH);
            if ($url_path) $slug = trim($url_path, '/');
        }

        if (in_array($slug, $sempre_escondidas, true)) {
            unset($items[$key]);
            continue;
        }

        $post_id = $item->object_id ?? 0;
        if ($post_id && get_post_meta($post_id, '_rc_crm_internal', true) === '1') {
            unset($items[$key]);
            continue;
        }

        if (in_array($slug, $paginas_crm, true) && !in_array($slug, $permitidas, true)) {
            unset($items[$key]);
        }
    }

    return array_values($items);
}

/* ============================================================
   SHORTCODE: [rc_crm_pagina_interna]
   ============================================================ */
add_shortcode('rc_crm_pagina_interna', 'rc_crm_marcar_pagina_interna');
function rc_crm_marcar_pagina_interna() {
    $post_id = get_the_ID();
    if ($post_id) {
        update_post_meta($post_id, '_rc_crm_internal', '1');
    }
    return '';
}