<?php
/**
 * Refrigeração CRM — Roles e Permissões
 *
 * @package Refrigeracao_CRM
 * @version 3.6.4
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
   CRIAR ROLES (técnico + cliente)
   ============================================================ */
function rc_crm_criar_role_tecnico() {
    /* Role Técnico */
    if (!get_role('tecnico')) {
        add_role('tecnico', 'Técnico', [
            'read'              => true,
            'rc_acessar_painel' => true,
        ]);
    } else {
        $role = get_role('tecnico');
        $role->add_cap('read');
        $role->add_cap('rc_acessar_painel');
    }

    /* Role Cliente */
    if (!get_role('cliente_lss')) {
        add_role('cliente_lss', 'Cliente LSS', [
            'read'            => true,
            'rc_area_cliente' => true,
        ]);
    } else {
        $role = get_role('cliente_lss');
        $role->add_cap('read');
        $role->add_cap('rc_area_cliente');
    }

    $admin = get_role('administrator');
    if ($admin) {
        $admin->add_cap('rc_acessar_painel');
        $admin->add_cap('rc_area_cliente');
    }
}

/* ============================================================
   PERMISSÕES
   ============================================================ */
function rc_crm_pode_acessar_painel() {
    if (!is_user_logged_in()) return false;
    return current_user_can('manage_options') || current_user_can('rc_acessar_painel');
}

function rc_crm_e_admin() {
    return current_user_can('manage_options');
}

function rc_crm_e_cliente() {
    return current_user_can('rc_area_cliente') && !current_user_can('manage_options');
}

/**
 * Verificar se um user_id é técnico ou admin
 */
function rc_crm_e_tecnico_ou_admin($user_id) {
    if (empty($user_id)) return false;
    $user = get_userdata($user_id);
    if (!$user) return false;

    $roles = (array) $user->roles;
    return in_array('tecnico', $roles, true) || in_array('administrator', $roles, true);
}

/* ============================================================
   OBTER TÉCNICOS
   ============================================================ */
function rc_crm_obter_tecnicos() {
    $utilizadores = get_users([
        'orderby'  => 'display_name',
        'order'    => 'ASC',
        'role__in' => ['tecnico', 'administrator'],
    ]);

    usort($utilizadores, function($a, $b) {
        return strcasecmp($a->display_name, $b->display_name);
    });

    return $utilizadores;
}

/* ============================================================
   REDIRECT APÓS LOGIN
   ============================================================ */
add_filter('login_redirect', 'rc_crm_login_redirect', 10, 3);
function rc_crm_login_redirect($redirect_to, $requested, $user) {
    if (is_wp_error($user) || !($user instanceof WP_User)) return $redirect_to;

    $roles = (array) $user->roles;

    /* Cliente → minha-conta */
    if (in_array('cliente_lss', $roles, true)) {
        $pagina = get_page_by_path('minha-conta');
        if ($pagina) return get_permalink($pagina->ID);
    }

    /* Técnico → painel-do-tecnico */
    if (in_array('tecnico', $roles, true) && !in_array('administrator', $roles, true)) {
        $pagina = get_page_by_path('painel-do-tecnico');
        if ($pagina) return get_permalink($pagina->ID);
    }

    return $redirect_to;
}

/* ============================================================
   BLOQUEAR ACESSO AO wp-admin PARA CLIENTES E TÉCNICOS
   ============================================================ */
add_action('admin_init', 'rc_crm_bloquear_admin_nao_admins');
function rc_crm_bloquear_admin_nao_admins() {
    if (wp_doing_ajax()) return;
    if (current_user_can('manage_options')) return;

    $user = wp_get_current_user();
    $roles = (array) $user->roles;

    /* Cliente → minha-conta */
    if (in_array('cliente_lss', $roles, true)) {
        $pagina = get_page_by_path('minha-conta');
        $url = $pagina ? get_permalink($pagina->ID) : home_url('/');
        wp_safe_redirect($url);
        exit;
    }

    /* Técnico → painel-do-tecnico */
    if (in_array('tecnico', $roles, true) && !in_array('administrator', $roles, true)) {
        $pagina = get_page_by_path('painel-do-tecnico');
        $url = $pagina ? get_permalink($pagina->ID) : home_url('/');
        wp_safe_redirect($url);
        exit;
    }
}

/* ============================================================
   ESCONDER BARRA DE ADMIN PARA CLIENTES E TÉCNICOS
   ============================================================ */
add_action('after_setup_theme', 'rc_crm_esconder_barra_nao_admins');
function rc_crm_esconder_barra_nao_admins() {
    if (!is_user_logged_in()) return;
    if (current_user_can('manage_options')) return;

    $user  = wp_get_current_user();
    $roles = (array) $user->roles;

    if (in_array('cliente_lss', $roles, true) || in_array('tecnico', $roles, true)) {
        show_admin_bar(false);
    }
}

/* ============================================================
   GARANTIR PERMISSÕES EM ATUALIZAÇÕES
   ============================================================ */
add_action('admin_init', 'rc_crm_garantir_permissoes');
function rc_crm_garantir_permissoes() {
    $versao_instalada = get_option('rc_crm_permissoes_versao', '0');
    if (version_compare($versao_instalada, RC_CRM_VERSION, '>=')) return;

    rc_crm_criar_role_tecnico();
    update_option('rc_crm_permissoes_versao', RC_CRM_VERSION);
}