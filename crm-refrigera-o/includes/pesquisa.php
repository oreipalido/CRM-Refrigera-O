<?php
/**
 * Refrigeração CRM — Pesquisa Global
 *
 * @package Refrigeracao_CRM
 * @version 3.5.1
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
   HELPER: normalizar texto (remover acentos + minúsculas)
   ============================================================ */
if (!function_exists('rc_crm_normalizar_texto')) {
    function rc_crm_normalizar_texto($texto) {
        $texto = mb_strtolower($texto, 'UTF-8');
        $acentos = [
            'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a','å'=>'a',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
            'ç'=>'c','ñ'=>'n',
        ];
        return strtr($texto, $acentos);
    }
}

/* ============================================================
   HELPER: destacar termo no texto (preserva acentos originais)
   ============================================================ */
if (!function_exists('rc_crm_destacar_termo')) {
    function rc_crm_destacar_termo($texto, $termo) {
        if (empty($termo) || empty($texto)) return esc_html($texto);

        $texto_original = (string) $texto;
        $texto_norm     = rc_crm_normalizar_texto($texto_original);
        $termo_norm     = rc_crm_normalizar_texto($termo);

        $pos = mb_strpos($texto_norm, $termo_norm, 0, 'UTF-8');
        if ($pos === false) return esc_html($texto_original);

        /* ⚠️ Importante: o comprimento a cortar é o do TERMO ORIGINAL
           (não do termo normalizado) para preservar acentos. */
        $tamanho_termo = mb_strlen($termo, 'UTF-8');

        $antes  = mb_substr($texto_original, 0, $pos, 'UTF-8');
        $match  = mb_substr($texto_original, $pos, $tamanho_termo, 'UTF-8');
        $depois = mb_substr($texto_original, $pos + $tamanho_termo, null, 'UTF-8');

        return esc_html($antes)
             . '<mark class="rc-pg-mark">' . esc_html($match) . '</mark>'
             . esc_html($depois);
    }
}

/* ============================================================
   PÁGINA
   ============================================================ */
function rc_crm_pesquisa_page() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');

    global $wpdb;
    $table_c    = $wpdb->prefix . 'rc_clientes';
    $table_s    = $wpdb->prefix . 'rc_servicos';
    $table_tags = $wpdb->prefix . 'rc_servico_tags';

    $termo  = isset($_GET['q']) ? sanitize_text_field(trim($_GET['q'])) : '';
    $limite = 20;

    $clientes       = [];
    $servicos       = [];
    $total_clientes = 0;
    $total_servicos = 0;

    if (!empty($termo) && mb_strlen($termo, 'UTF-8') >= 2) {
        $like = '%' . $wpdb->esc_like($termo) . '%';

        /* ============================================================
           CLIENTES
           ============================================================ */
        $where_c = "nome LIKE %s OR codigo LIKE %s OR telefone LIKE %s OR email LIKE %s
                    OR cidade LIKE %s OR endereco LIKE %s OR cep LIKE %s OR observacoes LIKE %s
                    OR nif LIKE %s OR tipo_cliente LIKE %s";

        $total_clientes = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_c WHERE $where_c",
            $like, $like, $like, $like, $like, $like, $like, $like, $like, $like
        ));

        $sql_c = "SELECT * FROM $table_c
                  WHERE $where_c
                  ORDER BY
                     CASE WHEN codigo LIKE %s THEN 0 ELSE 1 END,
                     CASE WHEN nome LIKE %s THEN 0 ELSE 1 END,
                     nome ASC
                  LIMIT %d";

        $clientes = $wpdb->get_results($wpdb->prepare(
            $sql_c,
            $like, $like, $like, $like, $like, $like, $like, $like, $like, $like,
            $like, $like,
            $limite
        ));

        /* ============================================================
           SERVIÇOS
           ============================================================ */
        $where_s = "s.equipamento LIKE %s OR s.observacoes LIKE %s OR s.iva_motivo_isencao LIKE %s
                    OR c.nome LIKE %s OR c.codigo LIKE %s OR c.telefone LIKE %s OR c.nif LIKE %s
                    OR EXISTS (SELECT 1 FROM $table_tags t WHERE t.servico_id = s.id AND t.tag_slug LIKE %s)";

        $total_servicos = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM $table_s s
             LEFT JOIN $table_c c ON s.cliente_id = c.id
             WHERE $where_s",
            $like, $like, $like, $like, $like, $like, $like, $like
        ));

        $sql_s = "SELECT s.*, c.nome AS cliente_nome, c.codigo AS cliente_codigo, c.telefone AS cliente_telefone
                  FROM $table_s s
                  LEFT JOIN $table_c c ON s.cliente_id = c.id
                  WHERE $where_s
                  ORDER BY s.data_agendada DESC, s.id DESC
                  LIMIT %d";

        $servicos = $wpdb->get_results($wpdb->prepare(
            $sql_s,
            $like, $like, $like, $like, $like, $like, $like, $like,
            $limite
        ));
    }

    $status_labels = [
        'instalacao' => ['Instalação', '#2271b1'],
        'orcamento'  => ['Orçamento',  '#dba617'],
        'concluido'  => ['Concluído',  '#00a32a'],
        'cancelado'  => ['Cancelado',  '#b32d2e'],
    ];

    $tipos_cliente = function_exists('rc_crm_tipos_cliente') ? rc_crm_tipos_cliente() : [];

    /* -------- URL base do orçamento (usando helper não-deprecated) -------- */
    if (function_exists('rc_crm_get_page_by_title')) {
        $pagina_orcamento = rc_crm_get_page_by_title('Orçamento');
    } else {
        $pagina_orcamento = get_page_by_title('Orçamento');
    }
    $url_base_orcamento = $pagina_orcamento ? get_permalink($pagina_orcamento->ID) : '';
    ?>
    <style>
    .rc-pg-search { max-width: 1200px; }
    .rc-pg-search-box { background: #fff; border: 1px solid #ddd; border-radius: 12px; padding: 20px; margin: 20px 0; display: flex; gap: 10px; align-items: center; }
    .rc-pg-search-box input[type="search"] { flex: 1; padding: 14px 18px; font-size: 16px; border: 2px solid #e0e0e0; border-radius: 10px; }
    .rc-pg-search-box input[type="search"]:focus { outline: none; border-color: #2271b1; box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.15); }
    .rc-pg-search-box button { padding: 14px 28px; background: #2271b1; color: #fff; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; }
    .rc-pg-search-box button:hover { background: #135e96; }
    .rc-pg-search-box a.limpar { padding: 14px 20px; color: #666; text-decoration: none; font-size: 14px; border-radius: 10px; background: #f5f5f5; }
    .rc-pg-resultados-info { background: #fff; border-left: 4px solid #2271b1; border-radius: 8px; padding: 14px 20px; margin-bottom: 20px; font-size: 15px; }
    .rc-pg-resultados-info strong { color: #2271b1; font-size: 18px; }
    .rc-pg-seccao { background: #fff; border: 1px solid #e0e0e0; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
    .rc-pg-seccao h2 { margin: 0 0 16px; font-size: 16px; padding-bottom: 12px; border-bottom: 2px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
    .rc-pg-seccao h2 .contador { background: #2271b1; color: #fff; padding: 4px 12px; border-radius: 20px; font-size: 13px; }
    .rc-pg-item { padding: 14px 0; border-bottom: 1px solid #f5f5f5; display: flex; justify-content: space-between; align-items: center; gap: 16px; }
    .rc-pg-item:last-child { border-bottom: none; }
    .rc-pg-item-info { flex: 1; min-width: 0; }
    .rc-pg-item-titulo { font-weight: 700; color: #1a1a1a; font-size: 15px; margin-bottom: 4px; }
    .rc-pg-item-meta { font-size: 13px; color: #777; }
    .rc-pg-item-acoes { flex-shrink: 0; }
    .rc-pg-item-acoes a { display: inline-block; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 600; background: #f0f0f0; color: #333; margin-left: 4px; }
    .rc-pg-item-acoes a:hover { background: #2271b1; color: #fff; }
    .rc-pg-vazio { text-align: center; padding: 60px 20px; color: #999; }
    .rc-pg-vazio h3 { font-size: 18px; color: #666; margin: 0 0 8px; }
    .rc-pg-mark { background: #fff3a3; padding: 0 2px; border-radius: 2px; color: #1a1a1a; }
    .rc-pg-mais { display: block; text-align: center; margin-top: 12px; padding: 10px; background: #f9f9f9; border-radius: 6px; color: #2271b1; text-decoration: none; font-weight: 600; font-size: 13px; }
    .rc-pg-mais:hover { background: #f0f0f0; }
    </style>

    <div class="wrap rc-pg-search">
        <h1>🔍 Pesquisa Global</h1>
        <p class="description">Pesquise em clientes e serviços ao mesmo tempo. Aceita nome, código, telefone, email, NIF, tipo, equipamento, cidade, etiquetas.</p>

        <form method="get" class="rc-pg-search-box">
            <input type="hidden" name="page" value="rc-crm-pesquisa">
            <input type="search" name="q" value="<?php echo esc_attr($termo); ?>" placeholder="🔎 Digite qualquer coisa... (mínimo 2 caracteres)" autofocus>
            <button type="submit">Pesquisar</button>
            <?php if (!empty($termo)): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=rc-crm-pesquisa')); ?>" class="limpar">✕ Limpar</a>
            <?php endif; ?>
        </form>

        <?php if (empty($termo)): ?>
            <div class="rc-pg-vazio">
                <h3>💡 O que pode pesquisar?</h3>
                <p><strong>Clientes:</strong> nome, código, telefone, email, NIF, tipo, cidade, endereço, CEP<br>
                <strong>Serviços:</strong> equipamento, observações, cliente associado, etiquetas</p>
            </div>
        <?php elseif (mb_strlen($termo, 'UTF-8') < 2): ?>
            <div class="rc-pg-vazio"><h3>⚠️ Termo muito curto</h3><p>Escreva pelo menos <strong>2 caracteres</strong>.</p></div>
        <?php else: ?>

            <div class="rc-pg-resultados-info">
                Resultados para: <strong><?php echo esc_html($termo); ?></strong>
                — <?php echo (int) ($total_clientes + $total_servicos); ?> encontrado(s)
                (<?php echo (int) $total_clientes; ?> cliente(s) + <?php echo (int) $total_servicos; ?> serviço(s))
            </div>

            <?php if ($total_clientes === 0 && $total_servicos === 0): ?>
                <div class="rc-pg-vazio">
                    <h3>😕 Nada encontrado</h3>
                    <p>Não há resultados para "<strong><?php echo esc_html($termo); ?></strong>".</p>
                </div>
            <?php else: ?>

                <?php if (!empty($clientes)): ?>
                    <div class="rc-pg-seccao">
                        <h2>👥 Clientes <span class="contador"><?php echo (int) $total_clientes; ?></span></h2>
                        <?php foreach ($clientes as $c):
                            $tipo_slug  = $c->tipo_cliente ?? 'particular';
                            $tipo_label = $tipos_cliente[$tipo_slug] ?? '—';
                        ?>
                            <div class="rc-pg-item">
                                <div class="rc-pg-item-info">
                                    <div class="rc-pg-item-titulo">
                                        <?php echo rc_crm_destacar_termo($c->nome, $termo); ?>
                                        <span style="color:#999; font-size:13px; font-weight:400;">(<?php echo rc_crm_destacar_termo($c->codigo, $termo); ?>)</span>
                                        <span style="background:#f0f0f0; color:#333; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; margin-left:6px;"><?php echo esc_html($tipo_label); ?></span>
                                    </div>
                                    <div class="rc-pg-item-meta">
                                        <?php if ($c->nif): ?>🆔 <?php echo rc_crm_destacar_termo($c->nif, $termo); ?><?php endif; ?>
                                        <?php if ($c->telefone): ?> · 📞 <?php echo rc_crm_destacar_termo($c->telefone, $termo); ?><?php endif; ?>
                                        <?php if ($c->cidade): ?> · 📍 <?php echo rc_crm_destacar_termo($c->cidade, $termo); ?><?php endif; ?>
                                        <?php if ($c->email): ?> · ✉️ <?php echo rc_crm_destacar_termo($c->email, $termo); ?><?php endif; ?>
                                    </div>
                                </div>
                                <div class="rc-pg-item-acoes">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=rc-crm-clientes&action=editar&id=' . $c->id)); ?>" title="Editar cliente">✏️ Editar</a>
                                    <?php if ($c->latitude && $c->longitude): ?>
                                        <a href="https://www.google.com/maps?q=<?php echo esc_attr($c->latitude . ',' . $c->longitude); ?>" target="_blank" rel="noopener" title="Ver no mapa">🗺️ Mapa</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($total_clientes > count($clientes)): ?>
                            <a class="rc-pg-mais" href="<?php echo esc_url(add_query_arg(['page' => 'rc-crm-clientes', 's' => $termo], admin_url('admin.php'))); ?>">
                                Ver os <?php echo (int) $total_clientes; ?> clientes no CRM →
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($servicos)): ?>
                    <div class="rc-pg-seccao">
                        <h2>🔧 Serviços <span class="contador"><?php echo (int) $total_servicos; ?></span></h2>
                        <?php foreach ($servicos as $s):
                            $label   = $status_labels[$s->status] ?? ['—', '#999'];
                            $url_orc = $url_base_orcamento ? add_query_arg('rc_orcamento', $s->id, $url_base_orcamento) : '';
                        ?>
                            <div class="rc-pg-item">
                                <div class="rc-pg-item-info">
                                    <div class="rc-pg-item-titulo">
                                        <?php echo rc_crm_destacar_termo($s->equipamento ?: '(sem equipamento)', $termo); ?>
                                    </div>
                                    <div class="rc-pg-item-meta">
                                        <span style="background:<?php echo esc_attr($label[1]); ?>; color:#fff; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; margin-right:6px;"><?php echo esc_html($label[0]); ?></span>
                                        <?php if ($s->cliente_nome): ?>👤 <?php echo rc_crm_destacar_termo($s->cliente_nome, $termo); ?> (<?php echo rc_crm_destacar_termo($s->cliente_codigo, $termo); ?>)<?php endif; ?>
                                        <?php if ($s->data_agendada): ?> · 📅 <?php echo esc_html(date_i18n('d/m/Y', strtotime($s->data_agendada))); ?><?php endif; ?>
                                        <?php if ($s->orcamento): ?> · 💰 <?php echo esc_html(number_format((float) $s->orcamento, 2, ',', '.')); ?> €<?php endif; ?>
                                    </div>
                                    <?php if (function_exists('rc_crm_render_tags_servico')): ?>
                                        <?php $tags_html = rc_crm_render_tags_servico($s->id); ?>
                                        <?php if ($tags_html): ?><div style="margin-top:6px;"><?php echo $tags_html; ?></div><?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="rc-pg-item-acoes">
                                    <?php if ($url_orc): ?><a href="<?php echo esc_url($url_orc); ?>" target="_blank" rel="noopener" title="Ver orçamento">📄 Orçamento</a><?php endif; ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=rc-crm-servicos&action=editar&id=' . $s->id)); ?>" title="Editar serviço">✏️ Editar</a>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($total_servicos > count($servicos)): ?>
                            <a class="rc-pg-mais" href="<?php echo esc_url(add_query_arg(['page' => 'rc-crm-servicos'], admin_url('admin.php'))); ?>">
                                Ver os <?php echo (int) $total_servicos; ?> serviços no CRM →
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}