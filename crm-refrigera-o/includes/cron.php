<?php
/**
 * Refrigeração CRM — Cron (agendamentos automáticos)
 *
 * @package Refrigeracao_CRM
 * @version 3.5.0
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
   AGENDAR EVENTOS
   ============================================================ */
function rc_crm_schedule_cron() {
    // Lembrete diário às 09:00 (hora local do site)
    if (!wp_next_scheduled('rc_crm_daily_maintenance')) {
        $proximo = strtotime('today 09:00');
        if ($proximo < time()) {
            // Se já passou das 09:00, agenda para amanhã
            $proximo = strtotime('tomorrow 09:00');
        }
        wp_schedule_event($proximo, 'daily', 'rc_crm_daily_maintenance');
    }

    // Backup semanal às segundas às 03:00 (hora local do site)
    if (!wp_next_scheduled('rc_crm_weekly_backup')) {
        $proxima_segunda = strtotime('next monday 03:00');
        wp_schedule_event($proxima_segunda, 'weekly', 'rc_crm_weekly_backup');
    }
}

/* ============================================================
   LEMBRETES: 1 dia antes
   ============================================================ */
add_action('rc_crm_daily_maintenance', 'rc_crm_enviar_lembretes');
function rc_crm_enviar_lembretes() {
    global $wpdb;
    $table = $wpdb->prefix . 'rc_servicos';

    /* Usa wp_date() para respeitar a timezone do site */
    $amanha_inicio = wp_date('Y-m-d 00:00:00', strtotime('+1 day'));
    $amanha_fim    = wp_date('Y-m-d 23:59:59', strtotime('+1 day'));

    /* Filtra já em SQL os que precisam de lembrete */
    $servicos = $wpdb->get_results($wpdb->prepare(
        "SELECT id FROM $table
         WHERE s.data_agendada >= %s
           AND s.data_agendada <= %s
           AND status IN ('instalacao', 'orcamento')
           AND (email_lembrete_enviado_em IS NULL OR email_lembrete_enviado_em = '')",
        $amanha_inicio,
        $amanha_fim
    ));

    if (empty($servicos)) return;

    foreach ($servicos as $s) {
        if (function_exists('rc_crm_enviar_notificacao')) {
            rc_crm_enviar_notificacao('lembrete', (int) $s->id);
        }
    }
}