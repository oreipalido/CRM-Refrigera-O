<?php
/**
 * Refrigeração CRM — Área do Cliente + Portal + Redirect por Role
 *
 * @package Refrigeracao_CRM
 * @version 3.7.0
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
   CONFIGURAÇÃO: slug da página do portal
   ============================================================ */
if (!function_exists('rc_crm_portal_slug')) {
    function rc_crm_portal_slug() {
        return apply_filters('rc_crm_portal_slug', 'portal');
    }
}

/* ============================================================
   REDIRECT AUTOMÁTICO POR ROLE
   ============================================================ */
add_action('template_redirect', 'rc_crm_redirect_portal_por_role');
function rc_crm_redirect_portal_por_role() {
    if (!is_user_logged_in()) return;
    if (!is_page(rc_crm_portal_slug())) return;
    if (isset($_GET['rc_no_redirect'])) return;

    $destino = rc_crm_obter_url_painel_utilizador();
    if ($destino) {
        wp_safe_redirect($destino);
        exit;
    }
}

/* ============================================================
   HELPER: URL do painel consoante a role
   ============================================================ */
if (!function_exists('rc_crm_obter_url_painel_utilizador')) {
    function rc_crm_obter_url_painel_utilizador($user_id = 0) {
        if (!$user_id) $user_id = get_current_user_id();
        if (!$user_id) return '';

        $user = get_userdata($user_id);
        if (!$user) return '';

        $roles = (array) $user->roles;

        if (in_array('administrator', $roles, true) || user_can($user_id, 'manage_options')) {
            return admin_url('admin.php?page=rc-crm');
        }
        if (in_array('tecnico', $roles, true) || user_can($user_id, 'rc_acessar_painel')) {
            return home_url('/painel-do-tecnico/');
        }
        if (in_array('cliente_lss', $roles, true) || user_can($user_id, 'rc_area_cliente')) {
            return home_url('/minha-conta/');
        }

        return home_url('/');
    }
}

/* ============================================================
   HELPER: descrição da role
   ============================================================ */
if (!function_exists('rc_crm_descrever_role')) {
    function rc_crm_descrever_role($user_id = 0) {
        if (!$user_id) $user_id = get_current_user_id();
        if (!$user_id) return 'visitante';

        $user = get_userdata($user_id);
        if (!$user) return 'visitante';

        $roles = (array) $user->roles;

        if (in_array('administrator', $roles, true)) return 'administrador';
        if (in_array('tecnico', $roles, true))       return 'técnico';
        if (in_array('cliente_lss', $roles, true))   return 'cliente';

        return 'utilizador';
    }
}

/* ============================================================
   SHORTCODE: [rc_portal]
   ============================================================ */
add_shortcode('rc_portal', 'rc_crm_portal_shortcode');
function rc_crm_portal_shortcode() {
    $logado = is_user_logged_in();
    $user   = $logado ? wp_get_current_user() : null;

    ob_start();
    ?>
    <div class="rc-portal">
        <div class="rc-portal-capa">
            <h1>🔧 Refrigeração CRM</h1>
            <p>Bem-vindo ao sistema de gestão</p>
        </div>

        <?php if ($logado): ?>
            <?php
            $destino  = rc_crm_obter_url_painel_utilizador($user->ID);
            $role_des = rc_crm_descrever_role($user->ID);
            ?>
            <div class="rc-portal-bemvindo">
                <p>
                    Olá, <strong><?php echo esc_html($user->display_name); ?></strong>!
                    <br>
                    <small>Entraste como <strong><?php echo esc_html($role_des); ?></strong>.</small>
                </p>
            </div>

            <div class="rc-portal-grid">
                <a href="<?php echo esc_url($destino); ?>" class="rc-portal-card destaque">
                    <span class="rc-portal-icon">🚀</span>
                    <h3>Entrar no meu painel</h3>
                    <p>Acede ao painel de <strong><?php echo esc_html($role_des); ?></strong>.</p>
                </a>

                <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="rc-portal-card">
                    <span class="rc-portal-icon">↪</span>
                    <h3>Terminar sessão</h3>
                    <p>Sair da tua conta com segurança.</p>
                </a>
            </div>

        <?php else: ?>
            <div class="rc-portal-bemvindo">
                <p>Este portal dá acesso ao sistema de gestão. Escolhe como queres entrar:</p>
            </div>

            <div class="rc-portal-grid">
                <a href="<?php echo esc_url(wp_login_url(home_url('/portal/'))); ?>" class="rc-portal-card destaque">
                    <span class="rc-portal-icon">🔑</span>
                    <h3>Entrar</h3>
                    <p>Se já tens conta, faz login.</p>
                </a>

                <a href="<?php echo esc_url(home_url('/minha-conta/')); ?>" class="rc-portal-card">
                    <span class="rc-portal-icon">👤</span>
                    <h3>Área de Cliente</h3>
                    <p>Consulta os teus serviços e pedidos.</p>
                </a>

                <a href="<?php echo esc_url(home_url('/painel-do-tecnico/')); ?>" class="rc-portal-card">
                    <span class="rc-portal-icon">👷</span>
                    <h3>Painel do Técnico</h3>
                    <p>Acede aos serviços do dia.</p>
                </a>

                <a href="<?php echo esc_url(home_url('/contactos/')); ?>" class="rc-portal-card">
                    <span class="rc-portal-icon">📞</span>
                    <h3>Contactos</h3>
                    <p>Fala com a nossa equipa.</p>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <style>
    .rc-portal { max-width: 900px; margin: 40px auto; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    .rc-portal-capa { text-align: center; padding: 40px 20px; background: linear-gradient(135deg, #1877F2 0%, #0C63D4 100%); color: #fff; border-radius: 12px; margin-bottom: 30px; }
    .rc-portal-capa h1 { margin: 0 0 10px; font-size: 32px; color: #fff; border: none; padding: 0; }
    .rc-portal-capa p { margin: 0; opacity: 0.9; font-size: 16px; }
    .rc-portal-bemvindo { text-align: center; margin-bottom: 24px; color: #555; font-size: 15px; }
    .rc-portal-bemvindo strong { color: #1877F2; }
    .rc-portal-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; }
    .rc-portal-card { display: block; padding: 24px; background: #fff; border: 2px solid #e0e0e0; border-radius: 12px; text-decoration: none; color: #1a1a1a; transition: all 0.2s; text-align: center; }
    .rc-portal-card:hover { border-color: #1877F2; transform: translateY(-4px); box-shadow: 0 8px 24px rgba(24,119,242,0.15); color: #1877F2; }
    .rc-portal-card.destaque { border-color: #1877F2; background: linear-gradient(135deg, #1877F2 0%, #0C63D4 100%); color: #fff; }
    .rc-portal-card.destaque:hover { color: #fff; border-color: #0C63D4; }
    .rc-portal-card.destaque p { color: rgba(255,255,255,0.9); }
    .rc-portal-icon { display: block; font-size: 40px; margin-bottom: 12px; line-height: 1; }
    .rc-portal-card h3 { margin: 0 0 6px; font-size: 18px; font-weight: 700; border: none; padding: 0; }
    .rc-portal-card p { margin: 0; font-size: 13px; color: #666; line-height: 1.5; }
    @media (max-width: 600px) { .rc-portal-capa h1 { font-size: 24px; } .rc-portal-grid { grid-template-columns: 1fr; } }
    </style>
    <?php
    return ob_get_clean();
}

/* ============================================================
   SHORTCODE: [rc_link_painel]
   ============================================================ */
add_shortcode('rc_link_painel', 'rc_crm_link_painel_shortcode');
function rc_crm_link_painel_shortcode($atts) {
    $atts = shortcode_atts([
        'texto'  => 'Ir para o meu painel',
        'classe' => '',
    ], $atts);

    if (!is_user_logged_in()) {
        return '<a href="' . esc_url(wp_login_url(get_permalink())) . '" class="rc-btn-painel ' . esc_attr($atts['classe']) . '">🔑 ' . esc_html($atts['texto']) . '</a>';
    }

    $destino = rc_crm_obter_url_painel_utilizador();
    if (!$destino) return '';

    return '<a href="' . esc_url($destino) . '" class="rc-btn-painel ' . esc_attr($atts['classe']) . '">🚀 ' . esc_html($atts['texto']) . '</a>';
}

/* ============================================================
   SHORTCODE: [rc_area_cliente]
   ============================================================ */
add_shortcode('rc_area_cliente', 'rc_crm_area_cliente_shortcode');
function rc_crm_area_cliente_shortcode() {
    if (!is_user_logged_in()) {
        return '<div class="rc-ac-login-aviso">
            <h2>🔒 Área do Cliente</h2>
            <p>Precisa iniciar sessão para aceder à sua área.</p>
            <p><a href="' . esc_url(wp_login_url(get_permalink())) . '" class="rc-ac-btn">🔑 Entrar</a></p>
        </div>';
    }

    $user = wp_get_current_user();

    if (!rc_crm_e_cliente() && !current_user_can('manage_options')) {
        return '<div class="rc-ac-login-aviso"><p>Esta área é reservada a clientes.</p></div>';
    }

    global $wpdb;
    $cliente_id = get_user_meta($user->ID, 'rc_cliente_id', true);

    if (!$cliente_id) {
        return '<div class="rc-ac-login-aviso"><p>Não existe uma conta de cliente associada a este utilizador.</p></div>';
    }

    $cliente = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}rc_clientes WHERE id = %d",
        $cliente_id
    ));
    if (!$cliente) {
        return '<div class="rc-ac-login-aviso"><p>Conta de cliente não encontrada.</p></div>';
    }

    $mensagem_sucesso = '';
    $mensagem_erro    = '';

    /* -------- Atualizar perfil -------- */
    if (isset($_POST['rc_ac_nonce']) && wp_verify_nonce($_POST['rc_ac_nonce'], 'rc_ac_guardar_perfil')) {
        $telefone    = sanitize_text_field($_POST['telefone'] ?? '');
        $endereco    = sanitize_text_field($_POST['endereco'] ?? '');
        $complemento = sanitize_text_field($_POST['complemento'] ?? '');
        $cidade      = sanitize_text_field($_POST['cidade'] ?? '');
        $cep         = sanitize_text_field($_POST['cep'] ?? '');

        if (empty($telefone)) {
            $mensagem_erro = '⚠️ O telefone é obrigatório.';
        } else {
            // Geocodificação: se tem endereço+cidade e não tem coordenadas, geocodifica
            $latitude  = $cliente->latitude;
            $longitude = $cliente->longitude;

            if ((!$latitude || !$longitude) && !empty($endereco) && !empty($cidade)) {
                $endereco_completo = urlencode($endereco . ', ' . $cidade . ', Portugal');
                $response = wp_remote_get(
                    "https://nominatim.openstreetmap.org/search?q={$endereco_completo}&format=json&limit=1",
                    [
                        'timeout' => 10,
                        'headers' => ['User-Agent' => 'RefrigeracaoCRM/1.0'],
                    ]
                );
                if (!is_wp_error($response)) {
                    $data = json_decode(wp_remote_retrieve_body($response), true);
                    if (!empty($data[0])) {
                        $latitude  = $data[0]['lat'];
                        $longitude = $data[0]['lon'];
                    }
                }
            }

            $wpdb->update(
                $wpdb->prefix . 'rc_clientes',
                [
                    'telefone'    => $telefone,
                    'endereco'    => $endereco,
                    'complemento' => $complemento,
                    'cidade'      => $cidade,
                    'cep'         => $cep,
                    'latitude'    => $latitude,
                    'longitude'   => $longitude,
                ],
                ['id' => $cliente_id]
            );

            $cliente = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}rc_clientes WHERE id = %d",
                $cliente_id
            ));

            $mensagem_sucesso = '✅ Dados atualizados com sucesso!';
        }
    }

    /* -------- Novo pedido -------- */
    if (isset($_POST['rc_ac_nonce']) && wp_verify_nonce($_POST['rc_ac_nonce'], 'rc_ac_novo_pedido')) {
        $equipamento    = sanitize_text_field($_POST['equipamento'] ?? '');
        $descricao      = sanitize_textarea_field($_POST['descricao'] ?? '');
        $data_preferida = sanitize_text_field($_POST['data_preferida'] ?? '');

        if (empty($equipamento) || empty($descricao)) {
            $mensagem_erro = '⚠️ Preencha o equipamento e a descrição.';
        } else {
            $obs = "📝 Pedido do cliente ({$cliente->nome}):\n\n{$descricao}";

            if (!empty($data_preferida)) {
                $ts = strtotime($data_preferida);
                if ($ts && $ts >= strtotime('today')) {
                    $obs .= "\n\n📅 Data preferida: " . date_i18n('d/m/Y', $ts);
                }
            }

            $wpdb->insert($wpdb->prefix . 'rc_servicos', [
                'cliente_id'  => $cliente_id,
                'equipamento' => $equipamento,
                'status'      => 'instalacao',
                'observacoes' => $obs,
            ]);

            $novo_id = $wpdb->insert_id;

            wp_mail(
                get_option('admin_email'),
                '📥 Novo pedido de serviço — ' . $cliente->nome,
                "O cliente {$cliente->nome} (código {$cliente->codigo}) fez um novo pedido:\n\n"
                . "Equipamento: {$equipamento}\n"
                . "Descrição: {$descricao}\n"
                . (!empty($data_preferida) ? "Data preferida: " . date_i18n('d/m/Y', strtotime($data_preferida)) . "\n" : '')
                . "\nVer no admin: " . admin_url('admin.php?page=rc-crm-servicos&action=editar&id=' . $novo_id)
            );

            $mensagem_sucesso = '✅ Pedido enviado! Entraremos em contacto brevemente.';
        }
    }

    $servicos = $wpdb->get_results($wpdb->prepare(
        "SELECT s.*,
                (SELECT COUNT(*) FROM {$wpdb->prefix}rc_orcamento_itens i WHERE i.servico_id = s.id) AS num_itens
         FROM {$wpdb->prefix}rc_servicos s
         WHERE s.cliente_id = %d
         ORDER BY s.data_agendada DESC, s.created_at DESC",
        $cliente_id
    ));

    $config     = rc_crm_get_config();
    $iva_global = floatval($config['iva_global'] ?? 23);

    $status_labels = [
        'instalacao' => ['Em preparação',     '#1877F2'],
        'orcamento'  => ['Aguarda aprovação', '#dba617'],
        'concluido'  => ['Concluído',         '#00a32a'],
        'cancelado'  => ['Cancelado',         '#b32d2e'],
    ];

    if (function_exists('rc_crm_get_page_by_title')) {
        $pagina_orcamento = rc_crm_get_page_by_title('Orçamento');
    } else {
        $pagina_orcamento = get_page_by_title('Orçamento');
    }
    $url_orcamento_base = $pagina_orcamento ? get_permalink($pagina_orcamento->ID) : '';

    /* -------- Aba ativa (via URL ?tab=) -------- */
    $tab_ativa = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'servicos';
    if (!in_array($tab_ativa, ['servicos', 'novo', 'dados'], true)) $tab_ativa = 'servicos';

    /* -------- Mapa do endereço -------- */
    $mapa_query = '';
    if (!empty($cliente->endereco) && !empty($cliente->cidade)) {
        $mapa_query = trim($cliente->endereco . ' ' . $cliente->complemento . ', ' . $cliente->cidade . ', Portugal');
    } elseif (!empty($cliente->cep)) {
        $mapa_query = $cliente->cep . ', Portugal';
    }

    ob_start();
    ?>
    <div class="rc-ac">
        <div class="rc-ac-topo">
            <div>
                <h1>👋 Olá, <?php echo esc_html($cliente->nome ?: $user->display_name); ?>!</h1>
                <p class="rc-ac-sub">Bem-vindo à sua área de cliente — <?php echo esc_html($config['empresa_nome']); ?></p>
            </div>
        </div>

        <?php if ($mensagem_sucesso): ?>
            <div class="rc-ac-mensagem sucesso"><?php echo esc_html($mensagem_sucesso); ?></div>
        <?php endif; ?>

        <?php if ($mensagem_erro): ?>
            <div class="rc-ac-mensagem erro"><?php echo esc_html($mensagem_erro); ?></div>
        <?php endif; ?>

        <div class="rc-ac-tabs">
            <a href="<?php echo esc_url(add_query_arg('tab', 'servicos')); ?>" class="rc-ac-tab <?php echo $tab_ativa === 'servicos' ? 'ativa' : ''; ?>">🔧 Meus Serviços (<?php echo count($servicos); ?>)</a>
            <a href="<?php echo esc_url(add_query_arg('tab', 'novo')); ?>" class="rc-ac-tab <?php echo $tab_ativa === 'novo' ? 'ativa' : ''; ?>">➕ Novo Pedido</a>
            <a href="<?php echo esc_url(add_query_arg('tab', 'dados')); ?>" class="rc-ac-tab <?php echo $tab_ativa === 'dados' ? 'ativa' : ''; ?>">👤 Meus Dados</a>
        </div>

        <!-- ========== TAB: SERVIÇOS ========== -->
        <?php if ($tab_ativa === 'servicos'): ?>
            <div class="rc-ac-tab-conteudo ativa" id="rc-ac-servicos">
                <?php if (empty($servicos)): ?>
                    <div class="rc-ac-vazio">
                        <p>Ainda não tem serviços registados.</p>
                        <p>Clique em <strong>➕ Novo Pedido</strong> para solicitar um serviço.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($servicos as $s):
                        $label    = $status_labels[$s->status] ?? ['—', '#999'];
                        $iva_p    = ($s->iva_percentual !== null && $s->iva_percentual !== '')
                            ? floatval($s->iva_percentual)
                            : $iva_global;
                        $subtotal = floatval($s->orcamento);
                        $total    = $subtotal * (1 + $iva_p / 100);
                        $url_orc  = $url_orcamento_base ? add_query_arg('rc_orcamento', (int) $s->id, $url_orcamento_base) : '';
                    ?>
                        <div class="rc-ac-servico">
                            <div class="rc-ac-servico-topo">
                                <div>
                                    <span class="rc-ac-servico-numero">Serviço #<?php echo (int) $s->id; ?></span>
                                    <span class="rc-ac-badge" style="background:<?php echo esc_attr($label[1]); ?>;"><?php echo esc_html($label[0]); ?></span>
                                </div>
                                <span class="rc-ac-servico-data">
                                    <?php echo $s->data_agendada
                                        ? esc_html(date_i18n('d/m/Y H:i', strtotime($s->data_agendada)))
                                        : '<em style="color:#999;">Data a confirmar</em>'; ?>
                                </span>
                            </div>

                            <div class="rc-ac-servico-corpo">
                                <p><strong>Equipamento:</strong> <?php echo esc_html($s->equipamento ?: '—'); ?></p>
                                <?php if ($s->observacoes): ?>
                                    <p><strong>Observações:</strong><br>
                                        <span style="white-space:pre-wrap;"><?php echo esc_html($s->observacoes); ?></span>
                                    </p>
                                <?php endif; ?>
                                <?php if ($s->orcamento > 0): ?>
                                    <p class="rc-ac-servico-valor">
                                        <strong>Valor:</strong> <?php echo number_format($total, 2, ',', '.'); ?> €
                                        <small style="color:#666;">(IVA <?php echo number_format($iva_p, 0); ?>% incluído)</small>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <div class="rc-ac-servico-acoes">
                                <?php if ($url_orc && $s->orcamento > 0): ?>
                                    <a href="<?php echo esc_url($url_orc); ?>" target="_blank" rel="noopener" class="rc-ac-btn-mini">📄 Ver Orçamento</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- ========== TAB: NOVO PEDIDO ========== -->
        <?php if ($tab_ativa === 'novo'): ?>
            <div class="rc-ac-tab-conteudo ativa" id="rc-ac-novo">
                <h2>➕ Solicitar Novo Serviço</h2>
                <p style="color:#666;">Descreva o que precisa e entraremos em contacto brevemente.</p>

                <form method="post" class="rc-ac-form" autocomplete="off">
                    <?php wp_nonce_field('rc_ac_novo_pedido', 'rc_ac_nonce'); ?>

                    <label>Tipo de equipamento *</label>
                    <input type="text" name="equipamento" required placeholder="Ex: Ar condicionado, câmara frigorífica...">

                    <label>Descreva o problema / serviço *</label>
                    <textarea name="descricao" rows="5" required placeholder="Explique o que precisa..."></textarea>

                    <label>Data preferida (opcional)</label>
                    <input type="date" name="data_preferida" min="<?php echo esc_attr(date('Y-m-d')); ?>">

                    <p style="margin-top:20px;">
                        <button type="submit" class="rc-ac-btn-grande">📤 Enviar Pedido</button>
                    </p>
                </form>
            </div>
        <?php endif; ?>

        <!-- ========== TAB: DADOS ========== -->
        <?php if ($tab_ativa === 'dados'): ?>
            <div class="rc-ac-tab-conteudo ativa" id="rc-ac-dados">
                <h2>👤 Os Meus Dados</h2>
                <p style="color:#666;">
                    Pode atualizar os seus dados de contacto.
                    O <strong>email</strong>, <strong>nome</strong> e <strong>código</strong> são geridos pela nossa equipa — contacte-nos se precisar de os alterar.
                </p>

                <form method="post" class="rc-ac-form" autocomplete="off">
                    <?php wp_nonce_field('rc_ac_guardar_perfil', 'rc_ac_nonce'); ?>

                    <div class="rc-ac-form-linha">
                        <div>
                            <label>Código</label>
                            <input type="text" value="<?php echo esc_attr($cliente->codigo); ?>" disabled>
                        </div>
                        <div>
                            <label>Nome</label>
                            <input type="text" value="<?php echo esc_attr($cliente->nome); ?>" disabled>
                        </div>
                    </div>

                    <div class="rc-ac-form-linha">
                        <div>
                            <label>Telefone *</label>
                            <input type="tel" name="telefone" value="<?php echo esc_attr($cliente->telefone); ?>" required>
                        </div>
                        <div>
                            <label>Email</label>
                            <input type="email" value="<?php echo esc_attr($cliente->email); ?>" disabled>
                        </div>
                    </div>

                    <label>Endereço</label>
                    <input type="text" name="endereco" value="<?php echo esc_attr($cliente->endereco); ?>">

                    <div class="rc-ac-form-linha">
                        <div>
                            <label>Complemento</label>
                            <input type="text" name="complemento" value="<?php echo esc_attr($cliente->complemento); ?>">
                        </div>
                        <div>
                            <label>CEP</label>
                            <input type="text" name="cep" value="<?php echo esc_attr($cliente->cep); ?>">
                        </div>
                    </div>

                    <label>Cidade</label>
                    <input type="text" name="cidade" value="<?php echo esc_attr($cliente->cidade); ?>">

                    <?php if ($mapa_query): ?>
                        <div class="rc-mapa-wrapper" style="margin-top:20px;">
                            <p style="margin: 0 0 8px; font-size: 14px; color: #333;">
                                <strong>🗺️ Localização no Mapa</strong>
                                <small style="color: #999; font-weight: 400;">(atualiza ao guardar alterações)</small>
                            </p>
                            <iframe
                                width="100%"
                                height="300"
                                style="border: 1px solid #ddd; border-radius: 8px; display: block;"
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"
                                src="<?php echo esc_url('https://maps.google.com/maps?q=' . urlencode($mapa_query) . '&output=embed&z=15'); ?>">
                            </iframe>
                        </div>
                    <?php endif; ?>

                    <p style="margin-top:20px;">
                        <button type="submit" class="rc-ac-btn-grande">💾 Guardar Alterações</button>
                    </p>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}