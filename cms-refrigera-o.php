<?php
/**
 * Plugin Name:       Refrigeração CRM
 * Plugin URI:        https://lsfrios.pt/
 * Description:       Sistema de gestão de clientes, agendamentos, orçamentos e serviços para empresas de refrigeração.
 * Version:           3.7.3
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Edmar Barros & Dee
 * Author URI:        https://lsfrios.pt/
 * License:           GPL2
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       refrigeracao-crm
 * Domain Path:       /languages
 *
 * @package Refrigeracao_CRM
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
   CONSTANTES
   ============================================================ */
define('RC_CRM_VERSION', '3.7.3');
define('RC_CRM_PATH',    plugin_dir_path(__FILE__));
define('RC_CRM_URL',     plugin_dir_url(__FILE__));

/* ============================================================
   INCLUDES
   ============================================================ */
require_once RC_CRM_PATH . 'includes/database.php';
require_once RC_CRM_PATH . 'includes/roles.php';
require_once RC_CRM_PATH . 'includes/rc-crm-config.php';
require_once RC_CRM_PATH . 'includes/modo-escuro.php';
require_once RC_CRM_PATH . 'includes/etiquetas.php';
require_once RC_CRM_PATH . 'includes/comissoes.php';
require_once RC_CRM_PATH . 'includes/whatsapp.php';
require_once RC_CRM_PATH . 'includes/notificacoes.php';
require_once RC_CRM_PATH . 'includes/utilizadores.php';
require_once RC_CRM_PATH . 'includes/armazem.php';
require_once RC_CRM_PATH . 'includes/pesquisa.php';
require_once RC_CRM_PATH . 'includes/calendario.php';
require_once RC_CRM_PATH . 'includes/calendario-tecnico.php';
require_once RC_CRM_PATH . 'includes/area-cliente.php';
require_once RC_CRM_PATH . 'includes/orcamento.php';
require_once RC_CRM_PATH . 'includes/relatorios.php';
require_once RC_CRM_PATH . 'includes/pwa.php';
require_once RC_CRM_PATH . 'includes/backup.php';
require_once RC_CRM_PATH . 'includes/atualizacao.php';
require_once RC_CRM_PATH . 'includes/admin-menu.php';
require_once RC_CRM_PATH . 'includes/admin-pages.php';
require_once RC_CRM_PATH . 'includes/form-handlers.php';
require_once RC_CRM_PATH . 'includes/shortcodes.php';
require_once RC_CRM_PATH . 'includes/servicos-shortcodes.php';
require_once RC_CRM_PATH . 'includes/frontend-panel.php';
require_once RC_CRM_PATH . 'includes/dashboard-tecnico.php';
require_once RC_CRM_PATH . 'includes/cron.php';

/* ============================================================
   ATIVAÇÃO / DESATIVAÇÃO
   ============================================================ */
register_activation_hook(__FILE__, 'rc_crm_activate');
function rc_crm_activate() {
    rc_crm_create_tables();
    rc_crm_schedule_cron();
    rc_crm_criar_role_tecnico();
    rc_crm_migrar_itens_antigos();
    update_option('rc_crm_permissoes_versao', RC_CRM_VERSION);

    if (function_exists('rc_crm_pwa_rewrite_sw')) {
        rc_crm_pwa_rewrite_sw();
        flush_rewrite_rules(false);
    }
}

register_deactivation_hook(__FILE__, 'rc_crm_deactivate');
function rc_crm_deactivate() {
    wp_clear_scheduled_hook('rc_crm_daily_maintenance');
    wp_clear_scheduled_hook('rc_crm_weekly_backup');
    flush_rewrite_rules(false);
}

/* ============================================================
   HELPER: detetar página do CRM
   ============================================================ */
if (!function_exists('rc_crm_e_pagina_do_crm')) {
    function rc_crm_e_pagina_do_crm($hook = '') {
        if (!empty($hook) && (strpos($hook, 'rc-crm') !== false || strpos($hook, 'refrigeracao-crm') !== false)) {
            return true;
        }
        if (isset($_GET['page'])) {
            $page = sanitize_text_field($_GET['page']);
            if (strpos($page, 'rc-crm') === 0 || strpos($page, 'refrigeracao-crm') === 0) {
                return true;
            }
        }
        return false;
    }
}

/* ============================================================
   HELPER: URL de asset
   ============================================================ */
if (!function_exists('rc_crm_asset_url')) {
    function rc_crm_asset_url($relativo) {
        $relativo = ltrim($relativo, '/');
        $path = RC_CRM_PATH . $relativo;
        if (!file_exists($path)) {
            return false;
        }
        return RC_CRM_URL . $relativo;
    }
}

/* ============================================================
   FRONTEND — CSS + JS
   ============================================================ */
add_action('wp_enqueue_scripts', 'rc_crm_enqueue_assets_frontend');
function rc_crm_enqueue_assets_frontend() {

    $url_frontend = rc_crm_asset_url('assets/rc-frontend.css');
    $url_area     = rc_crm_asset_url('assets/rc-area-cliente.css');
    $url_modo_css = rc_crm_asset_url('assets/rc-modo-escuro.css');
    $url_cep_js   = rc_crm_asset_url('assets/rc-cep.js');
    $url_modo_js  = rc_crm_asset_url('assets/rc-modo-escuro.js');

    if ($url_frontend) {
        wp_enqueue_style('rc-frontend', $url_frontend, [], RC_CRM_VERSION);
    }
    if ($url_area) {
        wp_enqueue_style('rc-area-cliente', $url_area, $url_frontend ? ['rc-frontend'] : [], RC_CRM_VERSION);
    }
    if ($url_modo_css) {
        wp_enqueue_style('rc-modo-escuro', $url_modo_css, [], RC_CRM_VERSION);
    }
    if ($url_cep_js) {
        wp_enqueue_script('rc-cep', $url_cep_js, [], RC_CRM_VERSION, true);
        wp_localize_script('rc-cep', 'rcAjax', ['url' => admin_url('admin-ajax.php')]);
    }
    if ($url_modo_js) {
        wp_enqueue_script('rc-modo-escuro', $url_modo_js, [], RC_CRM_VERSION, false);
    }
}

/* ============================================================
   ADMIN — CSS + JS
   ============================================================ */
add_action('admin_enqueue_scripts', 'rc_crm_enqueue_assets_admin');
function rc_crm_enqueue_assets_admin($hook = '') {
    if (!rc_crm_e_pagina_do_crm($hook)) {
        return;
    }

    $url_frontend = rc_crm_asset_url('assets/rc-frontend.css');
    $url_admin    = rc_crm_asset_url('assets/rc-admin.css');
    $url_modo_css = rc_crm_asset_url('assets/rc-modo-escuro.css');
    $url_modo_js  = rc_crm_asset_url('assets/rc-modo-escuro.js');

    if ($url_frontend) {
        wp_enqueue_style('rc-frontend', $url_frontend, [], RC_CRM_VERSION);
    }
    if ($url_admin) {
        wp_enqueue_style('rc-admin', $url_admin, $url_frontend ? ['rc-frontend'] : [], RC_CRM_VERSION);
    }
    if ($url_modo_css) {
        wp_enqueue_style('rc-modo-escuro', $url_modo_css, [], RC_CRM_VERSION);
    }
    if ($url_modo_js) {
        wp_enqueue_script('rc-modo-escuro', $url_modo_js, [], RC_CRM_VERSION, false);
    }
}

/* ============================================================
   LIMPEZA
   ============================================================ */
add_action('wp_enqueue_scripts', 'rc_crm_desregistar_assets_antigos', 100);
function rc_crm_desregistar_assets_antigos() {
    wp_deregister_style('rc-panel');
}