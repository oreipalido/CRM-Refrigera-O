<?php
/**
 * Refrigeração CRM — Páginas Admin (Clientes, Serviços, Exportações)
 *
 * @package Refrigeracao_CRM
 * @version 3.5.0
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
   HELPER: obter página por título (substitui get_page_by_title)
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
   INTERCEPTAR EDIÇÃO (redireciona para a página frontend)
   ============================================================ */
add_action('admin_init', 'rc_crm_interceptar_edicao');
function rc_crm_interceptar_edicao() {
    if (!current_user_can('manage_options')) return;
    if (!isset($_GET['page'])) return;

    if ($_GET['page'] === 'rc-crm-clientes' && isset($_GET['action']) && $_GET['action'] === 'editar' && isset($_GET['id'])) {
        $pagina = rc_crm_get_page_by_title('Cadastrar Cliente');
        if ($pagina) {
            wp_safe_redirect(add_query_arg('rc_editar', intval($_GET['id']), get_permalink($pagina->ID)));
            exit;
        }
    }

    if ($_GET['page'] === 'rc-crm-servicos' && isset($_GET['action']) && $_GET['action'] === 'editar' && isset($_GET['id'])) {
        $pagina = rc_crm_get_page_by_title('Cadastrar Serviço');
        if ($pagina) {
            wp_safe_redirect(add_query_arg('rc_editar', intval($_GET['id']), get_permalink($pagina->ID)));
            exit;
        }
    }
}

/* ============================================================
   PÁGINA: CLIENTES
   ============================================================ */
function rc_crm_clientes_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'rc_clientes';

    $search      = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $filtro_tipo = isset($_GET['f_tipo']) ? sanitize_text_field($_GET['f_tipo']) : '';

    $where_arr = [];
    $params = [];

    if (!empty($search)) {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where_arr[] = "(nome LIKE %s OR codigo LIKE %s OR telefone LIKE %s OR cidade LIKE %s OR nif LIKE %s)";
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    }

    if (!empty($filtro_tipo)) {
        $where_arr[] = "tipo_cliente = %s";
        $params[] = $filtro_tipo;
    }

    $where = !empty($where_arr) ? 'WHERE ' . implode(' AND ', $where_arr) : '';

    $per_page = 20;
    $paged    = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset   = ($paged - 1) * $per_page;

    if (!empty($params)) {
        $total    = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table $where", $params));
        $clientes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table $where ORDER BY created_at DESC LIMIT %d OFFSET %d",
            array_merge($params, [$per_page, $offset])
        ));
    } else {
        $total    = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $clientes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        ));
    }
    $total_pages = (int) ceil($total / $per_page);

    /* -------- Mensagens -------- */
    if (isset($_GET['msg'])) {
        $msg_txt = '';
        $tipo_notice = 'notice-success';

        $msgs = [
            'salvo'              => ['✅ Cliente salvo com sucesso.', 'notice-success'],
            'atualizado'         => ['✅ Cliente atualizado com sucesso.', 'notice-success'],
            'eliminado'          => ['🗑️ Cliente eliminado.', 'notice-success'],
            'conta_criada'       => ['✅ Conta de cliente criada com sucesso!', 'notice-success'],
            'sem_email'          => ['⚠️ O cliente não tem email cadastrado. Adicione um email primeiro.', 'notice-warning'],
            'email_existe'       => ['⚠️ Já existe um utilizador com este email.', 'notice-warning'],
            'nif_invalido'       => ['⚠️ O NIF introduzido não é válido.', 'notice-error'],
            'codigo_duplicado'   => ['⚠️ Já existe um cliente com este código.', 'notice-error'],
            'cliente_tem_servicos' => ['⚠️ Este cliente tem serviços associados. Elimine ou reatribua os serviços primeiro.', 'notice-error'],
        ];

        if (isset($msgs[$_GET['msg']])) {
            $msg_txt = $msgs[$_GET['msg']][0];
            $tipo_notice = $msgs[$_GET['msg']][1];
        } elseif ($_GET['msg'] === 'bd_erro') {
            $detalhe = isset($_GET['detalhe']) ? ' Detalhe: ' . urldecode($_GET['detalhe']) : '';
            $msg_txt = '❌ Erro na base de dados.' . $detalhe;
            $tipo_notice = 'notice-error';
        }

        if ($msg_txt) {
            echo '<div class="notice ' . esc_attr($tipo_notice) . ' is-dismissible"><p>' . esc_html($msg_txt) . '</p></div>';
        }
    }

    $tipos_cliente = function_exists('rc_crm_tipos_cliente') ? rc_crm_tipos_cliente() : [];
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Clientes</h1>
        <button type="button" id="rc-toggle-novo-cliente" class="page-title-action">➕ Novo Cliente</button>
        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=rc_exportar_clientes'), 'rc_exportar_clientes')); ?>" class="page-title-action">📥 Exportar CSV</a>
        <hr class="wp-header-end">

        <!-- Formulário inline: Novo Cliente -->
        <div id="rc-form-novo-cliente" class="rc-form-inline" style="display:none;">
            <div class="rc-form-inline-header">
                <h2>➕ Novo Cliente</h2>
                <button type="button" class="rc-form-inline-fechar" onclick="document.getElementById('rc-form-novo-cliente').style.display='none';">✕ Fechar</button>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="rc-crm-form rc-form-modal">
                <?php wp_nonce_field('rc_salvar_cliente', '_wpnonce'); ?>
                <input type="hidden" name="action" value="rc_salvar_cliente">
                <input type="hidden" name="rc_redirect" value="<?php echo esc_url(admin_url('admin.php?page=rc-crm-clientes')); ?>">
                <input type="hidden" name="rc_from_admin" value="1">

                <div class="rc-linha">
                    <p class="rc-campo">
                        <input type="text" id="rc_new_codigo" name="codigo" placeholder=" " required>
                        <label for="rc_new_codigo">Código</label>
                    </p>
                    <p class="rc-campo">
                        <input type="text" id="rc_new_nome" name="nome" placeholder=" " required>
                        <label for="rc_new_nome">Nome</label>
                    </p>
                </div>

                <div class="rc-linha">
                    <p class="rc-campo">
                        <input type="tel" id="rc_new_telefone" name="telefone" placeholder=" " required>
                        <label for="rc_new_telefone">Telefone</label>
                    </p>
                    <p class="rc-campo">
                        <input type="email" id="rc_new_email" name="email" placeholder=" ">
                        <label for="rc_new_email">Email</label>
                    </p>
                </div>

                <div class="rc-linha">
                    <p class="rc-campo">
                        <input type="text" id="rc_new_nif" name="nif" placeholder=" " maxlength="9" pattern="\d{9}">
                        <label for="rc_new_nif">NIF (opcional)</label>
                    </p>
                    <p class="rc-campo">
                        <select id="rc_new_tipo_cliente" name="tipo_cliente" required>
                            <?php foreach ($tipos_cliente as $slug => $label): ?>
                                <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="rc_new_tipo_cliente">Tipo de Cliente</label>
                    </p>
                </div>

                <div class="rc-linha">
                    <p class="rc-campo">
                        <input type="text" id="rc_new_cep" name="cep" placeholder=" ">
                        <label for="rc_new_cep">CEP</label>
                    </p>
                    <p class="rc-botao-cep">
                        <button type="button" class="rc-modal-buscar-cep">🔍 Procurar CEP</button>
                    </p>
                </div>

                <div class="rc-linha rc-full">
                    <p class="rc-campo">
                        <input type="text" id="rc_new_endereco" name="endereco" placeholder=" ">
                        <label for="rc_new_endereco">Endereço</label>
                    </p>
                </div>

                <div class="rc-linha">
                    <p class="rc-campo">
                        <input type="text" id="rc_new_complemento" name="complemento" placeholder=" ">
                        <label for="rc_new_complemento">Complemento</label>
                    </p>
                    <p class="rc-campo">
                        <input type="text" id="rc_new_cidade" name="cidade" placeholder=" " required>
                        <label for="rc_new_cidade">Cidade</label>
                    </p>
                </div>

                <div class="rc-linha rc-full">
                    <p class="rc-campo">
                        <textarea id="rc_new_observacoes" name="observacoes" rows="3" placeholder=" "></textarea>
                        <label for="rc_new_observacoes">Observações</label>
                    </p>
                </div>

                <p class="submit" style="display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" class="button" onclick="document.getElementById('rc-form-novo-cliente').style.display='none';">✕ Cancelar</button>
                    <button type="submit" class="button button-primary">💾 Salvar Cliente</button>
                </p>
            </form>
        </div>

        <form method="get">
            <input type="hidden" name="page" value="rc-crm-clientes">
            <p class="search-box" style="display:flex; gap:8px; flex-wrap:wrap;">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Pesquisar clientes...">
                <select name="f_tipo">
                    <option value="">Todos os tipos</option>
                    <?php foreach ($tipos_cliente as $slug => $label): ?>
                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($filtro_tipo, $slug); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button">Filtrar</button>
                <?php if (!empty($search) || !empty($filtro_tipo)): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=rc-crm-clientes')); ?>" class="button">Limpar</a>
                <?php endif; ?>
            </p>
        </form>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nome</th>
                    <th>Tipo</th>
                    <th>NIF</th>
                    <th>Telefone</th>
                    <th>Email</th>
                    <th>Cidade</th>
                    <th>Localização</th>
                    <th>Conta</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clientes)): ?>
                    <tr><td colspan="10">Nenhum cliente encontrado.</td></tr>
                <?php else: ?>
                    <?php foreach ($clientes as $c):
                        $tipo_slug  = !empty($c->tipo_cliente) ? $c->tipo_cliente : 'particular';
                        $nif_val    = !empty($c->nif) ? $c->nif : '';
                        $tipo_label = $tipos_cliente[$tipo_slug] ?? '👤 Particular';

                        $user_conta = null;
                        if (!empty($c->email)) {
                            $user_conta = get_user_by('email', $c->email);
                        }
                        $tem_conta = $user_conta && in_array('cliente_lss', (array) $user_conta->roles);
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($c->codigo); ?></strong></td>
                            <td><?php echo esc_html($c->nome); ?></td>
                            <td><span style="background:#f0f0f0; color:#333; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600; white-space:nowrap;"><?php echo esc_html($tipo_label); ?></span></td>
                            <td><?php echo $nif_val ? esc_html($nif_val) : '—'; ?></td>
                            <td><?php echo esc_html($c->telefone); ?></td>
                            <td><?php echo esc_html($c->email); ?></td>
                            <td><?php echo esc_html($c->cidade); ?></td>
                            <td>
                                <?php if (!empty($c->latitude) && !empty($c->longitude)): ?>
                                    <a href="https://www.google.com/maps?q=<?php echo esc_attr($c->latitude . ',' . $c->longitude); ?>" target="_blank" rel="noopener">Ver mapa</a>
                                <?php elseif (!empty($c->endereco) && !empty($c->cidade)): ?>
                                    <a href="https://www.google.com/maps?q=<?php echo esc_attr(urlencode($c->endereco . ', ' . $c->cidade . ', Portugal')); ?>" target="_blank" rel="noopener">Ver mapa</a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($tem_conta): ?>
                                    <span style="background:#7c3aed; color:#fff; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600; white-space:nowrap;">✅ Ativa</span>
                                <?php elseif (!empty($c->email)): ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline; margin:0;" onsubmit="return confirm('Criar conta de acesso para <?php echo esc_js($c->nome); ?>? O cliente receberá um email.');">
                                        <?php wp_nonce_field('rc_criar_conta_cliente'); ?>
                                        <input type="hidden" name="action" value="rc_criar_conta_cliente">
                                        <input type="hidden" name="cliente_id" value="<?php echo esc_attr($c->id); ?>">
                                        <button type="submit" class="button button-small" style="font-size:11px; padding:2px 10px; white-space:nowrap;">📧 Criar conta</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:#ccc; font-size:11px; white-space:nowrap;">Sem email</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=rc-crm-clientes&action=editar&id=' . $c->id)); ?>" style="font-weight:600;">Editar</a> |
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=rc_eliminar_cliente&id=' . $c->id), 'rc_eliminar_cliente')); ?>" onclick="return confirm('Eliminar este cliente?')" style="color:#b32d2e;">Eliminar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php echo paginate_links([
                        'base'    => add_query_arg('paged', '%#%'),
                        'format'  => '',
                        'current' => $paged,
                        'total'   => $total_pages,
                    ]); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggle = document.getElementById('rc-toggle-novo-cliente');
        const form = document.getElementById('rc-form-novo-cliente');
        if (toggle && form) {
            toggle.addEventListener('click', function() {
                if (form.style.display === 'none' || form.style.display === '') {
                    form.style.display = 'block';
                    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    form.style.display = 'none';
                }
            });
        }
    });
    </script>
    <?php
}
/* ============================================================
   HANDLER: CRIAR CONTA DE CLIENTE
   ============================================================ */
add_action('admin_post_rc_criar_conta_cliente', 'rc_crm_criar_conta_cliente_handler');
function rc_crm_criar_conta_cliente_handler() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão');
    check_admin_referer('rc_criar_conta_cliente');

    global $wpdb;
    $cliente_id = intval($_POST['cliente_id']);
    $redirect   = admin_url('admin.php?page=rc-crm-clientes');

    $cliente = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}rc_clientes WHERE id = %d",
        $cliente_id
    ));

    if (!$cliente) {
        wp_safe_redirect(add_query_arg('msg', 'bd_erro', $redirect));
        exit;
    }

    if (empty($cliente->email)) {
        wp_safe_redirect(add_query_arg('msg', 'sem_email', $redirect));
        exit;
    }

    if (email_exists($cliente->email)) {
        $user_existente = get_user_by('email', $cliente->email);
        $cliente_meta   = get_user_meta($user_existente->ID, 'rc_cliente_id', true);

        if ($cliente_meta == $cliente_id) {
            $user_existente->set_role('cliente_lss');
            wp_safe_redirect(add_query_arg('msg', 'conta_criada', $redirect));
            exit;
        } else {
            wp_safe_redirect(add_query_arg('msg', 'email_existe', $redirect));
            exit;
        }
    }

    $username_base = sanitize_user(strtolower(explode('@', $cliente->email)[0]), true);
    $username = $username_base;
    $i = 1;
    while (username_exists($username)) {
        $username = $username_base . $i;
        $i++;
    }

    $user_id = wp_create_user($username, wp_generate_password(20, true), $cliente->email);

    if (is_wp_error($user_id)) {
        error_log('RC CRM - Erro criar conta cliente: ' . $user_id->get_error_message());
        wp_safe_redirect(add_query_arg(['msg' => 'bd_erro', 'detalhe' => urlencode($user_id->get_error_message())], $redirect));
        exit;
    }

    $user = new WP_User($user_id);
    $user->set_role('cliente_lss');

    wp_update_user([
        'ID'           => $user_id,
        'display_name' => $cliente->nome,
        'first_name'   => $cliente->nome,
    ]);

    update_user_meta($user_id, 'rc_cliente_id', $cliente_id);
    update_user_meta($user_id, 'rc_telefone', $cliente->telefone);

    $chave = get_password_reset_key($user);
    if (!is_wp_error($chave)) {
        $url_reset = network_site_url(
            'wp-login.php?action=rp&key=' . $chave . '&login=' . rawurlencode($user->user_login),
            'login'
        );

        $assunto  = 'Acesso à sua área de cliente — ' . get_bloginfo('name');
        $corpo    = "Olá {$cliente->nome},\n\n";
        $corpo   .= "Criámos uma área de cliente para poder consultar os seus serviços.\n\n";
        $corpo   .= "Para definir a password e aceder, clique no link abaixo:\n";
        $corpo   .= $url_reset . "\n\n";
        $corpo   .= "Os seus dados de acesso:\n";
        $corpo   .= "Utilizador: {$username}\n";
        $corpo   .= "Email: {$cliente->email}\n\n";
        $corpo   .= "Depois de definir a password, aceda em:\n";
        $corpo   .= home_url('/minha-conta/') . "\n\n";
        $corpo   .= "Cumprimentos,\n" . get_bloginfo('name');

        wp_mail($cliente->email, $assunto, $corpo);
    }

    do_action('rc_crm_nova_conta_cliente_criada', $user_id, $cliente_id);

    wp_safe_redirect(add_query_arg('msg', 'conta_criada', $redirect));
    exit;
}

/* ============================================================
   PÁGINA: SERVIÇOS
   ============================================================ */
function rc_crm_servicos_page() {
    global $wpdb;
    $table_s    = $wpdb->prefix . 'rc_servicos';
    $table_c    = $wpdb->prefix . 'rc_clientes';
    $table_i    = $wpdb->prefix . 'rc_orcamento_itens';
    $table_tags = $wpdb->prefix . 'rc_servico_tags';

    if (isset($_GET['msg'])) {
        $msgs = [
            'salvo'      => 'Serviço salvo com sucesso.',
            'atualizado' => 'Serviço atualizado com sucesso.',
            'duplicado'  => 'Serviço duplicado com sucesso.',
        ];
        $msg_txt = $msgs[$_GET['msg']] ?? 'Serviço eliminado.';
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($msg_txt) . '</p></div>';
    }

    $filtro_status  = isset($_GET['f_status'])  ? sanitize_text_field($_GET['f_status'])  : '';
    $filtro_tecnico = isset($_GET['f_tecnico']) ? intval($_GET['f_tecnico'])                : 0;
    $filtro_tag     = isset($_GET['f_tag'])     ? sanitize_text_field($_GET['f_tag'])       : '';

    $where = [];
    $params = [];

    if (!empty($filtro_status))  { $where[] = "s.status = %s";     $params[] = $filtro_status; }
    if (!empty($filtro_tecnico)) { $where[] = "s.tecnico_id = %d"; $params[] = $filtro_tecnico; }
    if (!empty($filtro_tag)) {
        $where[] = "EXISTS (SELECT 1 FROM $table_tags t WHERE t.servico_id = s.id AND t.tag_slug = %s)";
        $params[] = $filtro_tag;
    }

    $sql_where = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT s.*, c.nome AS cliente_nome, c.telefone AS cliente_telefone,
                   (SELECT COUNT(*) FROM $table_i i WHERE i.servico_id = s.id) AS num_itens
            FROM $table_s s
            LEFT JOIN $table_c c ON s.cliente_id = c.id
            $sql_where
            ORDER BY s.data_agendada DESC";

    if (!empty($params)) $sql = $wpdb->prepare($sql, $params);

    $servicos = $wpdb->get_results($sql);

    $status_labels = [
        'instalacao' => ['Instalação', '#2271b1'],
        'orcamento'  => ['Orçamento',  '#dba617'],
        'concluido'  => ['Concluído',  '#00a32a'],
        'cancelado'  => ['Cancelado',  '#b32d2e'],
    ];

    $config           = function_exists('rc_crm_get_config') ? rc_crm_get_config() : [];
    $iva_global       = floatval($config['iva_global'] ?? 23);
    $tags_disponiveis = rc_crm_tags_disponiveis();
    $tecnicos         = rc_crm_obter_tecnicos();
    $clientes_lista   = $wpdb->get_results("SELECT id, codigo, nome FROM $table_c ORDER BY nome ASC");

    $pagina_orcamento   = rc_crm_get_page_by_title('Orçamento');
    $url_base_orcamento = $pagina_orcamento ? get_permalink($pagina_orcamento->ID) : '';
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Serviços</h1>
        <button type="button" id="rc-toggle-novo-servico" class="page-title-action">➕ Novo Serviço</button>
        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=rc_exportar_servicos'), 'rc_exportar_servicos')); ?>" class="page-title-action">📥 Exportar CSV</a>
        <hr class="wp-header-end">

        <!-- Formulário inline: Novo Serviço -->
        <div id="rc-form-novo-servico" class="rc-form-inline" style="display:none;">
            <div class="rc-form-inline-header">
                <h2>➕ Novo Serviço</h2>
                <button type="button" class="rc-form-inline-fechar" onclick="document.getElementById('rc-form-novo-servico').style.display='none';">✕ Fechar</button>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="rc-crm-form rc-form-modal">
                <?php wp_nonce_field('rc_salvar_servico', '_wpnonce'); ?>
                <input type="hidden" name="action" value="rc_salvar_servico">
                <input type="hidden" name="rc_redirect" value="<?php echo esc_url(admin_url('admin.php?page=rc-crm-servicos')); ?>">
                <input type="hidden" name="rc_from_admin" value="1">

                <div class="rc-linha rc-full">
                    <p class="rc-campo">
                        <select id="rc_new_s_cliente_id" name="cliente_id" required>
                            <option value=""></option>
                            <?php foreach ($clientes_lista as $c): ?>
                                <option value="<?php echo esc_attr($c->id); ?>"><?php echo esc_html($c->codigo . ' — ' . $c->nome); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="rc_new_s_cliente_id">Cliente</label>
                    </p>
                </div>

                <div class="rc-linha rc-full">
                    <p class="rc-campo">
                        <input type="text" id="rc_new_s_equipamento" name="equipamento" placeholder=" ">
                        <label for="rc_new_s_equipamento">Equipamento (resumo)</label>
                    </p>
                </div>

                <div class="rc-linha">
                    <p class="rc-campo">
                        <select id="rc_new_s_tecnico_id" name="tecnico_id">
                            <option value=""></option>
                            <?php foreach ($tecnicos as $u): ?>
                                <option value="<?php echo esc_attr($u->ID); ?>"><?php echo esc_html($u->display_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="rc_new_s_tecnico_id">Técnico Responsável</label>
                    </p>
                    <p class="rc-campo">
                        <select id="rc_new_s_status" name="status" required>
                            <option value="instalacao">Instalação</option>
                            <option value="orcamento">Orçamento</option>
                            <option value="concluido">Concluído</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                        <label for="rc_new_s_status">Status</label>
                    </p>
                </div>

                <div class="rc-linha rc-full">
                    <p class="rc-campo">
                        <input type="datetime-local" id="rc_new_s_data_agendada" name="data_agendada" placeholder=" ">
                        <label for="rc_new_s_data_agendada">Data Agendada</label>
                    </p>
                </div>

                <!-- Etiquetas -->
                <div class="rc-itens-wrapper" style="background:#fff; border:1px solid #e5e5e5; padding:16px;">
                    <div class="rc-itens-header">
                        <h3 style="margin:0 0 4px; font-size:15px;">🏷️ Etiquetas</h3>
                    </div>
                    <div class="rc-tags-checkbox-grid">
                        <?php foreach ($tags_disponiveis as $slug => $tag): ?>
                            <label class="rc-tag-checkbox" style="color:<?php echo esc_attr($tag[1]); ?>;">
                                <input type="checkbox" name="tags[]" value="<?php echo esc_attr($slug); ?>">
                                <span><?php echo esc_html($tag[0]); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Itens -->
                <div class="rc-itens-wrapper" style="margin-top:16px;">
                    <div class="rc-itens-header">
                        <h3 style="margin:0 0 4px; font-size:15px;">💰 Itens do Orçamento</h3>
                    </div>

                    <div class="rc-itens-tabela">
                        <div class="rc-itens-cabecalho">
                            <div>Descrição</div>
                            <div>Qtd.</div>
                            <div>Preço Unit.</div>
                            <div>Total</div>
                            <div></div>
                        </div>
                        <div id="rc-new-s-itens-lista">
                            <div class="rc-item-linha">
                                <input type="text" name="item_descricao[]" placeholder="Descrição do item">
                                <input type="number" name="item_qtd[]" step="0.01" min="0.01" value="1" class="rc-new-s-item-qtd">
                                <input type="number" name="item_preco[]" step="0.01" min="0" value="0" class="rc-new-s-item-preco">
                                <div class="rc-item-total">0,00 €</div>
                                <button type="button" class="rc-item-remover rc-new-s-remover">✕</button>
                            </div>
                        </div>
                        <button type="button" id="rc-new-s-add-item" class="rc-add-item-btn">➕ Adicionar Item</button>

                        <div class="rc-totais-wrapper">
                            <div class="rc-totais-linha">
                                <span>Subtotal</span>
                                <strong id="rc-new-s-subtotal">0,00 €</strong>
                            </div>
                            <div class="rc-totais-linha">
                                <span style="display:flex; align-items:center; gap:8px;">
                                    IVA
                                    <input type="number" id="rc-new-s-iva" name="iva_percentual" step="0.01" min="0" max="100" value="<?php echo esc_attr($iva_global); ?>" style="width:70px; padding:4px 8px; font-size:14px;">
                                    <span>%</span>
                                </span>
                                <strong id="rc-new-s-iva-valor">0,00 €</strong>
                            </div>
                            <div class="rc-totais-linha rc-total-final">
                                <span>TOTAL</span>
                                <strong id="rc-new-s-total">0,00 €</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rc-linha rc-full" style="margin-top:16px;">
                    <p class="rc-campo">
                        <textarea id="rc_new_s_observacoes" name="observacoes" rows="3" placeholder=" "></textarea>
                        <label for="rc_new_s_observacoes">Observações</label>
                    </p>
                </div>

                <p class="submit" style="display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" class="button" onclick="document.getElementById('rc-form-novo-servico').style.display='none';">✕ Cancelar</button>
                    <button type="submit" class="button button-primary">💾 Salvar Serviço</button>
                </p>
            </form>
        </div>

        <form method="get">
            <input type="hidden" name="page" value="rc-crm-servicos">
            <p class="search-box" style="display:flex; gap:8px; flex-wrap:wrap;">
                <select name="f_status">
                    <option value="">Todos os status</option>
                    <?php foreach ($status_labels as $valor => $info): ?>
                        <option value="<?php echo esc_attr($valor); ?>" <?php selected($filtro_status, $valor); ?>><?php echo esc_html($info[0]); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="f_tecnico">
                    <option value="">👥 Todos os técnicos</option>
                    <?php foreach ($tecnicos as $u): ?>
                        <option value="<?php echo esc_attr($u->ID); ?>" <?php selected($filtro_tecnico, $u->ID); ?>>👤 <?php echo esc_html($u->display_name); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="f_tag">
                    <option value="">Todas as etiquetas</option>
                    <?php foreach ($tags_disponiveis as $slug => $tag): ?>
                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($filtro_tag, $slug); ?>><?php echo esc_html($tag[0]); ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="button">🔍 Filtrar</button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rc-crm-servicos')); ?>" class="button">✕ Limpar</a>
            </p>
        </form>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr><th>Cliente</th><th>Equipamento</th><th>Etiquetas</th><th>Itens</th><th>Técnico</th><th>Data Agendada</th><th>Subtotal</th><th>Total</th><th>Status</th><th>Ações</th></tr>
            </thead>
            <tbody>
                <?php if (empty($servicos)): ?>
                    <tr><td colspan="10">Nenhum serviço encontrado.</td></tr>
                <?php else: ?>
                    <?php foreach ($servicos as $s):
                        $label       = $status_labels[$s->status] ?? ['—', '#999'];
                        $url_orc     = $url_base_orcamento ? add_query_arg('rc_orcamento', $s->id, $url_base_orcamento) : '';
                        $url_dup     = wp_nonce_url(admin_url('admin-post.php?action=rc_duplicar_servico&id=' . $s->id), 'rc_duplicar_servico');
                        $iva_servico = (isset($s->iva_percentual) && $s->iva_percentual !== null && $s->iva_percentual !== '') ? floatval($s->iva_percentual) : $iva_global;
                        $iva_valor_s = floatval($s->orcamento) * ($iva_servico / 100);
                        $total_s     = floatval($s->orcamento) + $iva_valor_s;
                    ?>
                        <tr>
                            <td><?php echo esc_html($s->cliente_nome ?: '—'); ?></td>
                            <td><?php echo esc_html($s->equipamento); ?></td>
                            <td><?php echo rc_crm_render_tags_servico($s->id); ?></td>
                            <td><span style="background:#f0f0f0; color:#666; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600;"><?php echo (int) $s->num_itens; ?> itens</span></td>
                            <td>
                                <?php
                                if ($s->tecnico_id) {
                                    $user = get_userdata($s->tecnico_id);
                                    echo $user ? esc_html($user->display_name) : '—';
                                } else { echo '—'; }
                                ?>
                            </td>
                            <td><?php echo $s->data_agendada ? esc_html(date_i18n('d/m/Y H:i', strtotime($s->data_agendada))) : '—'; ?></td>
                            <td><?php echo $s->orcamento ? esc_html(number_format($s->orcamento, 2, ',', '.')) . ' €' : '—'; ?></td>
                            <td><strong style="color:#2271b1;"><?php echo number_format($total_s, 2, ',', '.'); ?> €</strong></td>
                            <td><span style="color:<?php echo esc_attr($label[1]); ?>;font-weight:600;"><?php echo esc_html($label[0]); ?></span></td>
                            <td>
                                <?php if ($url_orc): ?>
                                    <a href="<?php echo esc_url($url_orc); ?>" target="_blank" rel="noopener" style="font-weight:600;color:#7c3aed;">📄 Orçamento</a> |
                                <?php endif; ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=rc-crm-servicos&action=editar&id=' . $s->id)); ?>" style="font-weight:600;">Editar</a> |
                                <a href="<?php echo esc_url($url_dup); ?>" style="font-weight:600; color:#2271b1;" onclick="return confirm('Duplicar este serviço?')">📋 Duplicar</a>
                                <?php if (function_exists('rc_crm_botao_whatsapp_dropdown')): ?>
                                    | <?php echo rc_crm_botao_whatsapp_dropdown($s->id, $s->cliente_telefone); ?>
                                <?php endif; ?>
                                <?php if ($s->status !== 'concluido' && $s->status !== 'cancelado'): ?>
                                    | <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=rc_concluir_servico&id=' . $s->id), 'rc_concluir_servico')); ?>" style="color:#00a32a;font-weight:600;" onclick="return confirm('Marcar como concluído?')">✓ Concluir</a>
                                <?php endif; ?>
                                | <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=rc_eliminar_servico&id=' . $s->id), 'rc_eliminar_servico')); ?>" onclick="return confirm('Eliminar este serviço?')" style="color:#b32d2e;">Eliminar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggle = document.getElementById('rc-toggle-novo-servico');
        const form = document.getElementById('rc-form-novo-servico');
        if (toggle && form) {
            toggle.addEventListener('click', function() {
                if (form.style.display === 'none' || form.style.display === '') {
                    form.style.display = 'block';
                    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    form.style.display = 'none';
                }
            });
        }

        const listaItens = document.getElementById('rc-new-s-itens-lista');
        const addItemBtn = document.getElementById('rc-new-s-add-item');
        const subtotalEl = document.getElementById('rc-new-s-subtotal');
        const ivaPercEl  = document.getElementById('rc-new-s-iva');
        const ivaValorEl = document.getElementById('rc-new-s-iva-valor');
        const totalEl    = document.getElementById('rc-new-s-total');

        if (listaItens && addItemBtn) {
            function formatar(v) { return v.toFixed(2).replace('.', ',') + ' €'; }

            function calcularTotais() {
                let subtotal = 0;
                listaItens.querySelectorAll('.rc-item-linha').forEach(function(linha) {
                    const qtd   = parseFloat(linha.querySelector('.rc-new-s-item-qtd').value)   || 0;
                    const preco = parseFloat(linha.querySelector('.rc-new-s-item-preco').value) || 0;
                    const sub   = qtd * preco;
                    subtotal += sub;
                    linha.querySelector('.rc-item-total').textContent = formatar(sub);
                });

                const ivaPerc  = parseFloat(ivaPercEl.value) || 0;
                const ivaValor = subtotal * (ivaPerc / 100);

                subtotalEl.textContent = formatar(subtotal);
                ivaValorEl.textContent = formatar(ivaValor);
                totalEl.textContent    = formatar(subtotal + ivaValor);
            }

            function criarLinha() {
                const div = document.createElement('div');
                div.className = 'rc-item-linha';
                div.innerHTML = `
                    <input type="text" name="item_descricao[]" placeholder="Descrição do item">
                    <input type="number" name="item_qtd[]" step="0.01" min="0.01" value="1" class="rc-new-s-item-qtd">
                    <input type="number" name="item_preco[]" step="0.01" min="0" value="0" class="rc-new-s-item-preco">
                    <div class="rc-item-total">0,00 €</div>
                    <button type="button" class="rc-item-remover rc-new-s-remover">✕</button>
                `;
                return div;
            }

            addItemBtn.addEventListener('click', function() {
                listaItens.appendChild(criarLinha());
                calcularTotais();
            });

            listaItens.addEventListener('input', function(e) {
                if (e.target.classList.contains('rc-new-s-item-qtd') || e.target.classList.contains('rc-new-s-item-preco')) {
                    calcularTotais();
                }
            });

            ivaPercEl.addEventListener('input', calcularTotais);

            listaItens.addEventListener('click', function(e) {
                if (e.target.classList.contains('rc-new-s-remover')) {
                    const linhas = listaItens.querySelectorAll('.rc-item-linha');
                    if (linhas.length > 1) {
                        e.target.closest('.rc-item-linha').remove();
                        calcularTotais();
                    } else {
                        alert('O orçamento precisa ter pelo menos 1 item.');
                    }
                }
            });

            calcularTotais();
        }

        document.querySelectorAll('.rc-tag-checkbox input[type="checkbox"]').forEach(function(cb) {
            cb.addEventListener('change', function() {
                this.closest('.rc-tag-checkbox').classList.toggle('selecionado', this.checked);
            });
        });
    });
    </script>
    <?php
}

/* ============================================================
   HANDLERS DE AÇÕES (eliminar, concluir, duplicar)
   ============================================================ */

/* --- Eliminar cliente (com proteção) --- */
add_action('admin_post_rc_eliminar_cliente', 'rc_crm_eliminar_cliente');
function rc_crm_eliminar_cliente() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão');
    check_admin_referer('rc_eliminar_cliente');

    global $wpdb;
    $id = intval($_GET['id']);

    // 1) Bloquear se tiver serviços associados
    $tem_servicos = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}rc_servicos WHERE cliente_id = %d",
        $id
    ));

    if ($tem_servicos > 0) {
        wp_safe_redirect(add_query_arg('msg', 'cliente_tem_servicos', admin_url('admin.php?page=rc-crm-clientes')));
        exit;
    }

    // 2) Eliminar utilizador WP associado (se existir)
    $cliente = $wpdb->get_row($wpdb->prepare(
        "SELECT email FROM {$wpdb->prefix}rc_clientes WHERE id = %d",
        $id
    ));

    if ($cliente && !empty($cliente->email)) {
        $user = get_user_by('email', $cliente->email);
        if ($user && in_array('cliente_lss', (array) $user->roles, true)) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($user->ID);
        }
    }

    // 3) Eliminar registo CRM
    $wpdb->delete($wpdb->prefix . 'rc_clientes', ['id' => $id]);

    wp_safe_redirect(admin_url('admin.php?page=rc-crm-clientes&msg=eliminado'));
    exit;
}

/* --- Eliminar serviço --- */
add_action('admin_post_rc_eliminar_servico', 'rc_crm_eliminar_servico');
function rc_crm_eliminar_servico() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão');
    check_admin_referer('rc_eliminar_servico');

    global $wpdb;
    $id = intval($_GET['id']);

    $wpdb->delete($wpdb->prefix . 'rc_orcamento_itens', ['servico_id' => $id]);
    $wpdb->delete($wpdb->prefix . 'rc_servico_tags',     ['servico_id' => $id]);
    $wpdb->delete($wpdb->prefix . 'rc_servicos',         ['id' => $id]);

    wp_safe_redirect(admin_url('admin.php?page=rc-crm-servicos&msg=eliminado'));
    exit;
}

/* --- Concluir serviço --- */
add_action('admin_post_rc_concluir_servico', 'rc_crm_concluir_servico');
function rc_crm_concluir_servico() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão');
    check_admin_referer('rc_concluir_servico');

    global $wpdb;
    $id = intval($_GET['id']);

    $status_anterior = $wpdb->get_var($wpdb->prepare(
        "SELECT status FROM {$wpdb->prefix}rc_servicos WHERE id = %d",
        $id
    ));

    $wpdb->update(
        $wpdb->prefix . 'rc_servicos',
        ['status' => 'concluido', 'data_conclusao' => current_time('mysql')],
        ['id' => $id]
    );

    if (function_exists('rc_crm_calcular_comissao_servico'))   rc_crm_calcular_comissao_servico($id);
    if (function_exists('rc_crm_disparar_notificacoes_servico')) rc_crm_disparar_notificacoes_servico($id, $status_anterior);

    wp_safe_redirect(admin_url('admin.php?page=rc-crm-servicos&msg=atualizado'));
    exit;
}

/* --- Duplicar serviço --- */
add_action('admin_post_rc_duplicar_servico', 'rc_crm_duplicar_servico');
function rc_crm_duplicar_servico() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão');
    check_admin_referer('rc_duplicar_servico');

    global $wpdb;
    $table_s    = $wpdb->prefix . 'rc_servicos';
    $table_i    = $wpdb->prefix . 'rc_orcamento_itens';
    $table_tags = $wpdb->prefix . 'rc_servico_tags';

    $id_original = intval($_GET['id']);
    $original = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_s WHERE id = %d", $id_original));

    if (!$original) {
        wp_safe_redirect(admin_url('admin.php?page=rc-crm-servicos&msg=erro'));
        exit;
    }

    $wpdb->insert($table_s, [
        'cliente_id'          => $original->cliente_id,
        'equipamento'         => $original->equipamento,
        'tecnico_id'          => $original->tecnico_id,
        'status'              => 'instalacao',
        'data_agendada'       => null,
        'data_conclusao'      => null,
        'orcamento'           => $original->orcamento,
        'iva_percentual'      => $original->iva_percentual,
        'iva_motivo_isencao'  => $original->iva_motivo_isencao,
        'comissao_percentual' => null,
        'comissao_valor'      => null,
        'comissao_paga_em'    => null,
        'observacoes'         => $original->observacoes,
        'email_agendado_enviado_em' => null,
        'email_lembrete_enviado_em' => null,
        'email_concluido_enviado_em' => null,
    ]);

    $novo_id = $wpdb->insert_id;

    $itens = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_i WHERE servico_id = %d ORDER BY ordem ASC",
        $id_original
    ));
    foreach ($itens as $item) {
        $wpdb->insert($table_i, [
            'servico_id'     => $novo_id,
            'descricao'      => $item->descricao,
            'quantidade'     => $item->quantidade,
            'preco_unitario' => $item->preco_unitario,
            'ordem'          => $item->ordem,
        ]);
    }

    $tags = $wpdb->get_col($wpdb->prepare(
        "SELECT tag_slug FROM $table_tags WHERE servico_id = %d",
        $id_original
    ));
    foreach ($tags as $tag_slug) {
        $wpdb->insert($table_tags, ['servico_id' => $novo_id, 'tag_slug' => $tag_slug]);
    }

    $pagina = rc_crm_get_page_by_title('Cadastrar Serviço');
    if ($pagina) {
        wp_safe_redirect(add_query_arg(['rc_editar' => $novo_id, 'rc_msg' => 'duplicado'], get_permalink($pagina->ID)));
    } else {
        wp_safe_redirect(admin_url('admin.php?page=rc-crm-servicos&msg=duplicado'));
    }
    exit;
}

/* ============================================================
   EXPORTAÇÃO CSV
   ============================================================ */

add_action('admin_post_rc_exportar_clientes', 'rc_crm_exportar_clientes');
function rc_crm_exportar_clientes() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão');
    check_admin_referer('rc_exportar_clientes');

    global $wpdb;
    $clientes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}rc_clientes ORDER BY nome ASC", ARRAY_A);

    rc_crm_enviar_csv('clientes', $clientes, [
        'codigo'       => 'Código',
        'nome'         => 'Nome',
        'tipo_cliente' => 'Tipo',
        'nif'          => 'NIF',
        'telefone'     => 'Telefone',
        'email'        => 'Email',
        'endereco'     => 'Endereço',
        'complemento'  => 'Complemento',
        'cidade'       => 'Cidade',
        'cep'          => 'CEP',
        'latitude'     => 'Latitude',
        'longitude'    => 'Longitude',
        'observacoes'  => 'Observações',
        'created_at'   => 'Criado em',
    ]);
}

add_action('admin_post_rc_exportar_servicos', 'rc_crm_exportar_servicos');
function rc_crm_exportar_servicos() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão');
    check_admin_referer('rc_exportar_servicos');

    global $wpdb;
    $table_i    = $wpdb->prefix . 'rc_orcamento_itens';
    $table_tags = $wpdb->prefix . 'rc_servico_tags';

    $linhas = $wpdb->get_results(
        "SELECT s.id AS servico_id, s.status, s.data_agendada, s.data_conclusao,
                s.equipamento, s.observacoes, s.tecnico_id, s.created_at,
                s.orcamento AS subtotal, s.iva_percentual, s.iva_motivo_isencao,
                s.comissao_percentual, s.comissao_valor, s.comissao_paga_em,
                c.nome AS cliente_nome, c.telefone AS cliente_telefone,
                c.email AS cliente_email, c.codigo AS cliente_codigo,
                i.descricao AS item_descricao, i.quantidade AS item_qtd, i.preco_unitario AS item_preco
         FROM {$wpdb->prefix}rc_servicos s
         LEFT JOIN {$wpdb->prefix}rc_clientes c ON s.cliente_id = c.id
         LEFT JOIN $table_i i ON i.servico_id = s.id
         ORDER BY s.id ASC, i.ordem ASC",
        ARRAY_A
    );

    foreach ($linhas as &$l) {
        if (!empty($l['tecnico_id'])) {
            $user = get_userdata($l['tecnico_id']);
            $l['tecnico_nome'] = $user ? $user->display_name : '';
        } else {
            $l['tecnico_nome'] = '';
        }

        $sub   = floatval($l['subtotal']);
        $iva_p = (isset($l['iva_percentual']) && $l['iva_percentual'] !== null && $l['iva_percentual'] !== '') ? floatval($l['iva_percentual']) : 23;
        $l['iva_valor_calc']     = $sub * ($iva_p / 100);
        $l['total_calc']         = $sub + $l['iva_valor_calc'];
        $l['iva_percentual_calc'] = $iva_p;

        $tags = $wpdb->get_col($wpdb->prepare(
            "SELECT tag_slug FROM $table_tags WHERE servico_id = %d",
            $l['servico_id']
        ));
        $l['tags'] = implode(', ', $tags);
    }
    unset($l);

    rc_crm_enviar_csv('servicos', $linhas, [
        'servico_id'         => 'ID Serviço',
        'cliente_codigo'     => 'Código Cliente',
        'cliente_nome'       => 'Cliente',
        'cliente_telefone'   => 'Telefone Cliente',
        'cliente_email'      => 'Email Cliente',
        'equipamento'        => 'Equipamento',
        'tags'               => 'Etiquetas',
        'tecnico_nome'       => 'Técnico',
        'status'             => 'Status',
        'data_agendada'      => 'Data Agendada',
        'data_conclusao'     => 'Data Conclusão',
        'item_descricao'     => 'Item — Descrição',
        'item_qtd'           => 'Item — Quantidade',
        'item_preco'         => 'Item — Preço Unitário',
        'subtotal'           => 'Subtotal (€)',
        'iva_percentual_calc'=> 'IVA (%)',
        'iva_valor_calc'     => 'IVA (€)',
        'total_calc'         => 'Total com IVA (€)',
        'iva_motivo_isencao' => 'Motivo Isenção IVA',
        'comissao_percentual'=> 'Comissão %',
        'comissao_valor'     => 'Comissão €',
        'comissao_paga_em'   => 'Comissão Paga em',
        'observacoes'        => 'Observações',
        'created_at'         => 'Criado em',
    ]);
}

/* ============================================================
   HELPER: Enviar CSV
   ============================================================ */
if (!function_exists('rc_crm_enviar_csv')) {
    function rc_crm_enviar_csv($nome_ficheiro, $dados, $colunas) {
        $filename = 'rc-' . $nome_ficheiro . '-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF"); // BOM UTF-8 para Excel
        fputcsv($output, array_values($colunas), ';');

        foreach ($dados as $linha) {
            $linha_formatada = [];
            foreach (array_keys($colunas) as $campo) {
                $valor = isset($linha[$campo]) ? $linha[$campo] : '';
                $valor = str_replace(["\r\n", "\r", "\n"], ' ', $valor);
                $linha_formatada[] = $valor;
            }
            fputcsv($output, $linha_formatada, ';');
        }

        fclose($output);
        exit;
    }
}