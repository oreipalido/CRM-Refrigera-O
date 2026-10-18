<?php
if (!defined('ABSPATH')) exit;

/**
 * Renderizar etiquetas de um serviço (helper reutilizável)
 */
function rc_crm_render_tags_servico($servico_id, $inline = true) {
    if (!function_exists('rc_crm_obter_tags_servico')) return '';

    $tags = rc_crm_obter_tags_servico($servico_id);
    if (empty($tags)) return '';

    $disponiveis = rc_crm_tags_disponiveis();
    $html = $inline ? '<span class="rc-tags-lista">' : '<div class="rc-tags-lista">';

    foreach ($tags as $slug) {
        if (!isset($disponiveis[$slug])) continue;
        $tag = $disponiveis[$slug];
        $html .= '<span class="rc-tag-badge" style="background:' . esc_attr($tag[1]) . ';">' . esc_html($tag[0]) . '</span>';
    }

    $html .= $inline ? '</span>' : '</div>';
    return $html;
}

/**
 * CSS das etiquetas no admin e frontend
 */
add_action('admin_head', 'rc_crm_tags_css');
add_action('wp_head', 'rc_crm_tags_css');
function rc_crm_tags_css() {
    ?>
    <style>
    .rc-tags-lista { display: inline-flex; gap: 4px; flex-wrap: wrap; }
    .rc-tag-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 700;
        color: #fff;
        white-space: nowrap;
        letter-spacing: 0.3px;
    }
    .rc-tags-checkbox-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 8px;
        margin-top: 8px;
    }
    .rc-tag-checkbox {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 12px;
        background: #f9f9f9;
        border: 2px solid #e5e5e5;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.15s;
        font-size: 14px;
        font-weight: 600;
    }
    .rc-tag-checkbox:hover { background: #f0f0f0; border-color: #ccc; }
    .rc-tag-checkbox input { margin: 0; }
    .rc-tag-checkbox.selecionado { background: #fff; border-color: currentColor; box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
    </style>
    <?php
}