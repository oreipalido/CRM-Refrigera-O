<?php
/**
 * Refrigeração CRM — Gestão de Utilizadores
 * Técnicos + Clientes (área de cliente) + Ligação com o CRM
 *
 * @package Refrigeracao_CRM
 * @version 3.5.0
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
   CONSTANTES
   ============================================================ */
if (!defined('RC_CRM_ROLES_PERMITIDAS')) {
    define('RC_CRM_ROLES_PERMITIDAS', ['tecnico', 'cliente_lss', 'subscriber', 'editor', 'author', 'contributor']);
}

/* ============================================================
   PÁGINA PRINCIPAL
   ============================================================ */
function rc_crm_utilizadores_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Sem permissão.');
    }

    $mensagem = '';

    /* ------------------------------------------------------------
       1) CRIAR TÉCNICO
       ------------------------------------------------------------ */
    if (isset($_POST['rc_acao']) && $_POST['rc_acao'] === 'criar_tecnico') {
        check_admin_referer('rc_criar_tecnico');

        $nome     = sanitize_text_field($_POST['nome'] ?? '');
        $email    = sanitize_email($_POST['email'] ?? '');
        $telefone = sanitize_text_field($_POST['telefone'] ?? '');

        if (empty($nome) || empty($email)) {
            $mensagem = '<div class="notice notice-error"><p>❌ Nome e email são obrigatórios.</p></div>';
        } elseif (!is_email($email)) {
            $mensagem = '<div class="notice notice-error"><p>❌ Email inválido.</p></div>';
        } elseif (email_exists($email)) {
            $mensagem = '<div class="notice notice-error"><p>❌ Já existe um utilizador com este email.</p></div>';
        } else {
            $username_base = sanitize_user(strtolower(explode('@', $email)[0]), true);
            $username = $username_base;
            $i = 1;
            while (username_exists($username)) {
                $username = $username_base . $i;
                $i++;
            }

            $user_id = wp_create_user($username, wp_generate_password(20, true), $email);

            if (is_wp_error($user_id)) {
                $mensagem = '<div class="notice notice-error"><p>❌ Erro: ' . esc_html($user_id->get_error_message()) . '</p></div>';
            } else {
                $user = new WP_User($user_id);
                $user->set_role('tecnico');

                wp_update_user([
                    'ID'           => $user_id,
                    'display_name' => $nome,
                    'first_name'   => $nome,
                ]);

                if (!empty($telefone)) {
                    update_user_meta($user_id, 'rc_telefone', $telefone);
                }

                $email_enviado = false;
                $chave = get_password_reset_key($user);

                if (!is_wp_error($chave)) {
                    $url_reset = network_site_url(
                        'wp-login.php?action=rp&key=' . $chave . '&login=' . rawurlencode($user->user_login),
                        'login'
                    );

                    $assunto  = 'Bem-vindo à equipa — ' . get_bloginfo('name');
                    $corpo    = "Olá {$nome},\n\n";
                    $corpo   .= "Foi criada uma conta de técnico para si no sistema da " . get_bloginfo('name') . ".\n\n";
                    $corpo   .= "Para definir a password, clique em:\n" . $url_reset . "\n\n";
                    $corpo   .= "Utilizador: {$username}\nEmail: {$email}\n\n";
                    $corpo   .= "Painel do técnico: " . home_url('/painel-do-tecnico/') . "\n\n";
                    $corpo   .= "Bem-vindo!\n" . get_bloginfo('name');

                    $email_enviado = wp_mail($email, $assunto, $corpo);
                }

                /**
                 * Hook para outros módulos reagirem à criação de um técnico
                 */
                do_action('rc_crm_novo_tecnico_criado', $user_id, $email);

                $mensagem  = '<div class="notice notice-success is-dismissible"><p>✅ Técnico <strong>' . esc_html($nome) . '</strong> criado!</p>';
                if ($email_enviado) {
                    $mensagem .= '<p>📧 Email enviado para <strong>' . esc_html($email) . '</strong>.</p>';
                } else {
                    $mensagem .= '<p>⚠️ <strong>Atenção:</strong> o utilizador foi criado, mas o email pode não ter sido enviado. Verifique a configuração SMTP.</p>';
                }
                $mensagem .= '</div>';
            }
        }
    }

    /* ------------------------------------------------------------
       2) ALTERAR ROLE
       ------------------------------------------------------------ */
    if (isset($_POST['rc_acao']) && $_POST['rc_acao'] === 'alterar_role') {
        check_admin_referer('rc_alterar_role');

        $user_id   = intval($_POST['user_id'] ?? 0);
        $nova_role = sanitize_text_field($_POST['nova_role'] ?? '');

        if (!in_array($nova_role, RC_CRM_ROLES_PERMITIDAS, true)) {
            $mensagem = '<div class="notice notice-error"><p>❌ Role inválida.</p></div>';
        } else {
            $user = new WP_User($user_id);
            if ($user->ID && $user->ID !== get_current_user_id()) {
                $user->set_role($nova_role);
                do_action('rc_crm_role_alterada', $user_id, $nova_role);
                $mensagem = '<div class="notice notice-success is-dismissible"><p>✅ Role atualizada para <strong>' . esc_html($user->display_name) . '</strong>.</p></div>';
            }
        }
    }

    /* ------------------------------------------------------------
       3) ELIMINAR UTILIZADOR
       ------------------------------------------------------------ */
    if (isset($_POST['rc_acao']) && $_POST['rc_acao'] === 'eliminar_utilizador') {
        check_admin_referer('rc_eliminar_utilizador');

        $user_id = intval($_POST['user_id'] ?? 0);

        if ($user_id === get_current_user_id()) {
            $mensagem = '<div class="notice notice-warning"><p>⚠️ Não pode eliminar-se a si mesmo.</p></div>';
        } else {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            $user = new WP_User($user_id);
            $nome = $user->display_name;
            wp_delete_user($user_id);
            do_action('rc_crm_utilizador_eliminado', $user_id);
            $mensagem = '<div class="notice notice-success is-dismissible"><p>🗑️ Utilizador <strong>' . esc_html($nome) . '</strong> eliminado.</p></div>';
        }
    }

    /* ------------------------------------------------------------
       4) REENVIAR LINK DE ACESSO
       ------------------------------------------------------------ */
    if (isset($_POST['rc_acao']) && $_POST['rc_acao'] === 'enviar_link_cliente') {
        check_admin_referer('rc_enviar_link_cliente');

        $user_id = intval($_POST['user_id'] ?? 0);
        $user    = new WP_User($user_id);

        if ($user->ID) {
            $chave = get_password_reset_key($user);
            if (!is_wp_error($chave)) {
                $url_reset = network_site_url(
                    'wp-login.php?action=rp&key=' . $chave . '&login=' . rawurlencode($user->user_login),
                    'login'
                );

                $assunto  = 'Acesso à sua área de cliente — ' . get_bloginfo('name');
                $corpo    = "Olá {$user->display_name},\n\n";
                $corpo   .= "Foi criada uma área de cliente para consultar os seus serviços.\n\n";
                $corpo   .= "Para definir a password e aceder, clique em:\n" . $url_reset . "\n\n";
                $corpo   .= "Depois de entrar, aceda em: " . home_url('/minha-conta/') . "\n\n";
                $corpo   .= "Cumprimentos,\n" . get_bloginfo('name');

                $enviado = wp_mail($user->user_email, $assunto, $corpo);

                if ($enviado) {
                    $mensagem = '<div class="notice notice-success is-dismissible"><p>✅ Link enviado para <strong>' . esc_html($user->user_email) . '</strong>.</p></div>';
                } else {
                    $mensagem = '<div class="notice notice-warning"><p>⚠️ Não foi possível enviar o email. Verifique a configuração SMTP.</p></div>';
                }
            }
        }
    }

    /* ------------------------------------------------------------
       5) CRIAR CONTA PARA CLIENTE DO CRM
       ------------------------------------------------------------ */
    if (isset($_POST['rc_acao']) && $_POST['rc_acao'] === 'criar_conta_cliente') {
        check_admin_referer('rc_criar_conta_cliente');

        global $wpdb;
        $cliente_id = intval($_POST['cliente_id'] ?? 0);
        $cliente = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rc_clientes WHERE id = %d",
            $cliente_id
        ));

        if (!$cliente) {
            $mensagem = '<div class="notice notice-error"><p>❌ Cliente não encontrado.</p></div>';
        } elseif (empty($cliente->email)) {
            $mensagem = '<div class="notice notice-error"><p>❌ Cliente não tem email cadastrado.</p></div>';
        } elseif (email_exists($cliente->email)) {
            $mensagem = '<div class="notice notice-warning"><p>⚠️ Já existe um utilizador com este email.</p></div>';
        } else {
            $username_base = sanitize_user(strtolower(explode('@', $cliente->email)[0]), true);
            $username = $username_base;
            $i = 1;
            while (username_exists($username)) {
                $username = $username_base . $i;
                $i++;
            }

            $user_id = wp_create_user($username, wp_generate_password(20, true), $cliente->email);

            if (is_wp_error($user_id)) {
                $mensagem = '<div class="notice notice-error"><p>❌ Erro: ' . esc_html($user_id->get_error_message()) . '</p></div>';
            } else {
                $user = new WP_User($user_id);
                $user->set_role('cliente_lss');

                wp_update_user([
                    'ID'           => $user_id,
                    'display_name' => $cliente->nome,
                    'first_name'   => $cliente->nome,
                ]);

                update_user_meta($user_id, 'rc_cliente_id', $cliente_id);
                update_user_meta($user_id, 'rc_telefone', $cliente->telefone);

                $email_enviado = false;
                $chave = get_password_reset_key($user);

                if (!is_wp_error($chave)) {
                    $url_reset = network_site_url(
                        'wp-login.php?action=rp&key=' . $chave . '&login=' . rawurlencode($user->user_login),
                        'login'
                    );

                    $assunto  = 'Acesso à sua área de cliente — ' . get_bloginfo('name');
                    $corpo    = "Olá {$cliente->nome},\n\n";
                    $corpo   .= "Criámos uma área de cliente para consultar os seus serviços.\n\n";
                    $corpo   .= "Para definir a password e aceder, clique em:\n" . $url_reset . "\n\n";
                    $corpo   .= "Utilizador: {$username}\nEmail: {$cliente->email}\n\n";
                    $corpo   .= "Depois de entrar: " . home_url('/minha-conta/') . "\n\n";
                    $corpo   .= "Cumprimentos,\n" . get_bloginfo('name');

                    $email_enviado = wp_mail($cliente->email, $assunto, $corpo);
                }

                do_action('rc_crm_nova_conta_cliente_criada', $user_id, $cliente_id);

                $mensagem  = '<div class="notice notice-success is-dismissible"><p>✅ Conta criada para <strong>' . esc_html($cliente->nome) . '</strong>.</p>';
                if ($email_enviado) {
                    $mensagem .= '<p>📧 Email enviado para <strong>' . esc_html($cliente->email) . '</strong>.</p>';
                } else {
                    $mensagem .= '<p>⚠️ <strong>Atenção:</strong> a conta foi criada, mas o email pode não ter sido enviado.</p>';
                }
                $mensagem .= '</div>';
            }
        }
    }

    /* ------------------------------------------------------------
       LISTAGEM
       ------------------------------------------------------------ */
    $filtro_role = isset($_GET['role']) ? sanitize_text_field($_GET['role']) : '';

    $args = [
        'orderby' => 'display_name',
        'order'   => 'ASC',
    ];

    if ($filtro_role && in_array($filtro_role, RC_CRM_ROLES_PERMITIDAS, true)) {
        $args['role'] = $filtro_role;
    }

    $utilizadores     = get_users($args);
    $current_user_id  = get_current_user_id();

    // Contadores por role (para os separadores)
    $total_todos    = count(get_users());
    $total_tecnicos = count(get_users(['role' => 'tecnico']));
    $total_clientes = count(get_users(['role' => 'cliente_lss']));

    ?>
    <div class="wrap">
        <h1>👷 Gestão de Utilizadores</h1>
        <p class="description">
            Técnicos acedem o painel em <code>/painel-do-tecnico/</code>.
            Clientes acedem em <code>/minha-conta/</code>.
        </p>

        <?php echo $mensagem; ?>

        <!-- ============================================================
             FORMULÁRIO: CRIAR TÉCNICO
             ============================================================ -->
        <div style="background:#fff; border-left:4px solid #2271b1; border-radius:8px; padding:20px; margin:24px 0; max-width:900px;">
            <h2 style="margin-top:0; font-size:16px;">➕ Adicionar Novo Técnico</h2>
            <form method="post">
                <?php wp_nonce_field('rc_criar_tecnico'); ?>
                <input type="hidden" name="rc_acao" value="criar_tecnico">

                <table class="form-table" style="margin:0;">
                    <tr>
                        <th style="width:140px;"><label for="rc_nome">Nome *</label></th>
                        <td><input type="text" id="rc_nome" name="nome" class="regular-text" required placeholder="Ex: João Silva"></td>
                    </tr>
                    <tr>
                        <th><label for="rc_email">Email *</label></th>
                        <td>
                            <input type="email" id="rc_email" name="email" class="regular-text" required>
                            <p class="description">Receberá um email para criar a password.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="rc_telefone">Telefone</label></th>
                        <td><input type="tel" id="rc_telefone" name="telefone" class="regular-text"></td>
                    </tr>
                </table>

                <p style="margin-top:16px;">
                    <button type="submit" class="button button-primary">➕ Criar Técnico</button>
                </p>
            </form>
        </div>

        <!-- ============================================================
             SEPARADORES / FILTROS
             ============================================================ -->
        <h2>👥 Utilizadores</h2>

        <ul class="subsubsub" style="margin:8px 0 12px;">
            <li>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rc-crm-utilizadores')); ?>"
                   class="<?php echo $filtro_role === '' ? 'current' : ''; ?>">
                    Todos <span class="count">(<?php echo (int) $total_todos; ?>)</span>
                </a> |
            </li>
            <li>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rc-crm-utilizadores&role=tecnico')); ?>"
                   class="<?php echo $filtro_role === 'tecnico' ? 'current' : ''; ?>">
                    Técnicos <span class="count">(<?php echo (int) $total_tecnicos; ?>)</span>
                </a> |
            </li>
            <li>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rc-crm-utilizadores&role=cliente_lss')); ?>"
                   class="<?php echo $filtro_role === 'cliente_lss' ? 'current' : ''; ?>">
                    Clientes <span class="count">(<?php echo (int) $total_clientes; ?>)</span>
                </a>
            </li>
        </ul>

        <!-- ============================================================
             TABELA
             ============================================================ -->
        <table class="wp-list-table widefat fixed striped" style="margin-top:12px;">
            <thead>
                <tr>
                    <th style="width:50px;">ID</th>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Telefone</th>
                    <th>Role</th>
                    <th style="width:340px;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($utilizadores)): ?>
                    <tr><td colspan="6" style="text-align:center; padding:20px; color:#999;">Nenhum utilizador encontrado.</td></tr>
                <?php else: ?>
                    <?php foreach ($utilizadores as $u):
                        $roles       = (array) $u->roles;
                        $role_atual  = !empty($roles) ? $roles[0] : '—';
                        $e_tecnico   = in_array('tecnico', $roles, true);
                        $e_cliente   = in_array('cliente_lss', $roles, true);
                        $e_admin     = in_array('administrator', $roles, true);
                        $e_self      = ($u->ID === $current_user_id);
                        $telefone    = get_user_meta($u->ID, 'rc_telefone', true);
                        $cliente_id  = get_user_meta($u->ID, 'rc_cliente_id', true);
                    ?>
                        <tr>
                            <td><?php echo esc_html($u->ID); ?></td>
                            <td>
                                <strong><?php echo esc_html($u->display_name); ?></strong>
                                <?php if ($e_self): ?><span style="color:#999;"> (você)</span><?php endif; ?>
                            </td>
                            <td><?php echo esc_html($u->user_email); ?></td>
                            <td>
                                <?php echo $telefone ? esc_html($telefone) : '<span style="color:#ccc;">—</span>'; ?>
                            </td>
                            <td>
                                <?php if ($e_admin): ?>
                                    <span style="background:#2271b1; color:#fff; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600;">Administrador</span>
                                <?php elseif ($e_tecnico): ?>
                                    <span style="background:#00a32a; color:#fff; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600;">Técnico</span>
                                <?php elseif ($e_cliente): ?>
                                    <span style="background:#7c3aed; color:#fff; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600;">Cliente</span>
                                    <?php if ($cliente_id): ?>
                                        <br><small style="color:#999;">ID CRM: <?php echo esc_html($cliente_id); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="background:#ddd; color:#666; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600;"><?php echo esc_html($role_atual); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($e_admin): ?>
                                    <em style="color:#999;">Administrador</em>
                                <?php elseif ($e_self): ?>
                                    <em style="color:#999;">Você mesmo</em>
                                <?php else: ?>

                                    <?php if ($e_cliente || $e_tecnico): ?>
                                        <form method="post" style="display:inline-block; margin:0 4px 4px 0;">
                                            <?php wp_nonce_field('rc_enviar_link_cliente'); ?>
                                            <input type="hidden" name="rc_acao" value="enviar_link_cliente">
                                            <input type="hidden" name="user_id" value="<?php echo esc_attr($u->ID); ?>">
                                            <button type="submit" class="button button-small" style="font-size:11px;" title="Envia email com link para o utilizador criar password">📧 Reenviar Acesso</button>
                                        </form>
                                    <?php endif; ?>

                                    <form method="post" style="display:inline-block; margin:0 4px 4px 0;">
                                        <?php wp_nonce_field('rc_alterar_role'); ?>
                                        <input type="hidden" name="rc_acao" value="alterar_role">
                                        <input type="hidden" name="user_id" value="<?php echo esc_attr($u->ID); ?>">
                                        <?php if ($e_tecnico): ?>
                                            <input type="hidden" name="nova_role" value="subscriber">
                                            <button type="submit" class="button button-small" style="font-size:11px;">❌ Remover técnico</button>
                                        <?php elseif ($e_cliente): ?>
                                            <input type="hidden" name="nova_role" value="subscriber">
                                            <button type="submit" class="button button-small" style="font-size:11px;">❌ Remover cliente</button>
                                        <?php else: ?>
                                            <input type="hidden" name="nova_role" value="tecnico">
                                            <button type="submit" class="button button-primary button-small" style="font-size:11px;">✓ Tornar técnico</button>
                                        <?php endif; ?>
                                    </form>

                                    <form method="post" style="display:inline-block; margin:0;"
                                          onsubmit="return confirm('Eliminar permanentemente <?php echo esc_js($u->display_name); ?>?');">
                                        <?php wp_nonce_field('rc_eliminar_utilizador'); ?>
                                        <input type="hidden" name="rc_acao" value="eliminar_utilizador">
                                        <input type="hidden" name="user_id" value="<?php echo esc_attr($u->ID); ?>">
                                        <button type="submit" class="button button-small" style="color:#b32d2e; font-size:11px;">🗑️ Eliminar</button>
                                    </form>

                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- ============================================================
             AJUDA
             ============================================================ -->
        <div style="margin-top:30px; padding:20px; background:#fff; border-left:4px solid #dba617; border-radius:6px; max-width:900px;">
            <h3 style="margin-top:0;">ℹ️ Como funciona</h3>
            <ul style="line-height:1.8;">
                <li><strong>Técnicos</strong> → acedem em <code>/painel-do-tecnico/</code> e veem os seus serviços.</li>
                <li><strong>Clientes</strong> → acedem em <code>/minha-conta/</code> e veem os seus serviços.</li>
                <li>Para criar conta de cliente, vá a <strong>Clientes → Editar</strong> e clique em <strong>📧 Criar conta do cliente</strong>.</li>
                <li>Use <strong>📧 Reenviar Acesso</strong> para reenviar o link de definição de password.</li>
            </ul>
        </div>
    </div>
    <?php
}