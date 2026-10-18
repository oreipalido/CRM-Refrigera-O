<?php
/**
 * Refrigeração CRM — PWA (Progressive Web App)
 *
 * @package Refrigeracao_CRM
 * @version 3.5.2
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
   HEAD — meta tags PWA
   ============================================================ */
add_action('wp_head', 'rc_crm_pwa_head', 1);
function rc_crm_pwa_head() {
    // Só injeta no frontend (não no admin)
    if (is_admin()) return;

    $manifest_url = RC_CRM_URL . 'assets/manifest.json';
    $icon_url     = RC_CRM_URL . 'assets/icon-192.svg';
    $nome_site    = get_bloginfo('name');
    $descricao    = get_bloginfo('description');
    ?>
    <link rel="manifest" href="<?php echo esc_url($manifest_url); ?>">
    <meta name="theme-color" content="#1877F2">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?php echo esc_attr($nome_site); ?>">
    <?php if (!empty($descricao)): ?>
        <meta name="description" content="<?php echo esc_attr($descricao); ?>">
    <?php endif; ?>
    <link rel="apple-touch-icon" href="<?php echo esc_url($icon_url); ?>">
    <link rel="icon" type="image/svg+xml" href="<?php echo esc_url($icon_url); ?>">
    <?php
}

/* ============================================================
   SERVICE WORKER — rota dedicada em /rc-sw.js
   Necessário porque o scope de um SW não pode ser mais amplo
   que o diretório onde o ficheiro SW está.
   ============================================================ */
add_action('init', 'rc_crm_pwa_rewrite_sw');
function rc_crm_pwa_rewrite_sw() {
    add_rewrite_rule('^rc-sw\.js$', 'index.php?rc_crm_sw=1', 'top');
}

/**
 * Adiciona a query var
 */
add_filter('query_vars', 'rc_crm_pwa_query_vars');
function rc_crm_pwa_query_vars($vars) {
    $vars[] = 'rc_crm_sw';
    return $vars;
}

/**
 * Serve o service-worker.js na rota /rc-sw.js
 * Injeta a versão do plugin como constante antes do ficheiro.
 */
add_action('template_redirect', 'rc_crm_pwa_serve_sw');
function rc_crm_pwa_serve_sw() {
    if (get_query_var('rc_crm_sw') !== '1') return;

    $sw_file = RC_CRM_PATH . 'assets/service-worker.js';

    if (!file_exists($sw_file)) {
        status_header(404);
        exit;
    }

    $versao = defined('RC_CRM_VERSION') ? RC_CRM_VERSION : 'dev';

    $sw_content  = "const RC_CACHE_VERSION = '" . esc_js($versao) . "';\n";
    $sw_content .= file_get_contents($sw_file);

    nocache_headers();
    header('Content-Type: application/javascript; charset=utf-8');
    header('Service-Worker-Allowed: /');
    echo $sw_content;
    exit;
}

/**
 * Flush rewrite rules na ativação (uma vez por versão)
 */
add_action('admin_init', 'rc_crm_pwa_flush_rewrites');
function rc_crm_pwa_flush_rewrites() {
    $flag_version = 'rc_crm_pwa_rewrite_version';

    if (get_option($flag_version) !== RC_CRM_VERSION) {
        flush_rewrite_rules(false);
        update_option($flag_version, RC_CRM_VERSION);
    }
}

/* ============================================================
   FOOTER — registo do Service Worker
   ============================================================ */
add_action('wp_footer', 'rc_crm_pwa_sw_register', 99);
function rc_crm_pwa_sw_register() {
    if (is_admin()) return;
    ?>
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/rc-sw.js', {
                scope: '/'
            }).then(function (reg) {
                console.log('✅ PWA Service Worker registado:', reg.scope);
            }).catch(function (err) {
                console.warn('⚠️ Falha no registo do Service Worker:', err);
            });
        });
    }
    </script>
    <?php
}

/* ============================================================
   MIME TYPE — garantir application/json para manifest
   ============================================================ */
add_filter('mime_types', 'rc_crm_pwa_mime_types');
function rc_crm_pwa_mime_types($mimes) {
    $mimes['json'] = 'application/json';
    return $mimes;
}