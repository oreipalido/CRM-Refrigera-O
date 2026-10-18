<?php
/**
 * Refrigeração CRM — Modo Escuro
 *
 * NOTA: O carregamento do modo escuro no ADMIN é feito diretamente
 * pelo refrigeracao-crm.php. Este ficheiro só garante a versão no frontend.
 *
 * @package Refrigeracao_CRM
 * @version 3.6.2
 */

if (!defined('ABSPATH')) exit;

/**
 * Carregar CSS e JS do modo escuro no FRONTEND
 */
add_action('wp_enqueue_scripts', 'rc_crm_carregar_modo_escuro_frontend');
function rc_crm_carregar_modo_escuro_frontend() {
    // Se já foi enfileirado pelo principal, não duplicar
    if (wp_style_is('rc-modo-escuro', 'enqueued')) {
        return;
    }

    wp_enqueue_style(
        'rc-modo-escuro',
        RC_CRM_URL . 'assets/rc-modo-escuro.css',
        [],
        RC_CRM_VERSION
    );

    wp_enqueue_script(
        'rc-modo-escuro',
        RC_CRM_URL . 'assets/rc-modo-escuro.js',
        [],
        RC_CRM_VERSION,
        false
    );
}