<?php
/**
 * Refrigeração CRM — Orçamento (página imprimível)
 *
 * @package Refrigeracao_CRM
 * @version 3.5.0
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
   INTERCEPTAR ?rc_orcamento=ID
   ============================================================ */
add_action('template_redirect', 'rc_crm_interceptar_orcamento');
function rc_crm_interceptar_orcamento() {
    if (!isset($_GET['rc_orcamento'])) return;

    $servico_id = intval($_GET['rc_orcamento']);
    if (!$servico_id) return;

    if (!rc_crm_pode_acessar_painel() && !rc_crm_e_cliente_dono($servico_id)) {
        wp_die('Acesso restrito.', 'Sem permissão', ['response' => 403]);
    }

    rc_crm_renderizar_orcamento($servico_id);
    exit;
}

/* ============================================================
   HELPER: verifica se o utilizador atual é o cliente dono do serviço
   ============================================================ */
if (!function_exists('rc_crm_e_cliente_dono')) {
    function rc_crm_e_cliente_dono($servico_id) {
        if (!is_user_logged_in()) return false;

        $cliente_id_user = (int) get_user_meta(get_current_user_id(), 'rc_cliente_id', true);
        if (!$cliente_id_user) return false;

        global $wpdb;
        $cliente_id_servico = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT cliente_id FROM {$wpdb->prefix}rc_servicos WHERE id = %d",
            $servico_id
        ));

        return $cliente_id_servico === $cliente_id_user;
    }
}

/* ============================================================
   SHORTCODE [rc_orcamento]
   ============================================================ */
add_shortcode('rc_orcamento', 'rc_crm_shortcode_orcamento');
function rc_crm_shortcode_orcamento($atts) {
    $atts = shortcode_atts(['id' => 0], $atts);
    $servico_id = intval($atts['id']);

    if (!$servico_id && isset($_GET['rc_orcamento'])) {
        $servico_id = intval($_GET['rc_orcamento']);
    }

    if (!$servico_id) return 'Orçamento não especificado.';

    if (!rc_crm_pode_acessar_painel() && !rc_crm_e_cliente_dono($servico_id)) {
        return 'Acesso restrito.';
    }

    ob_start();
    rc_crm_renderizar_orcamento($servico_id);
    return ob_get_clean();
}

/* ============================================================
   RENDER
   ============================================================ */
function rc_crm_renderizar_orcamento($servico_id) {
    global $wpdb;
    $table_i = $wpdb->prefix . 'rc_orcamento_itens';

    $servico = $wpdb->get_row($wpdb->prepare(
        "SELECT s.*, c.nome AS cliente_nome, c.telefone AS cliente_telefone,
                c.email AS cliente_email, c.endereco AS cliente_endereco,
                c.complemento AS cliente_complemento, c.cidade AS cliente_cidade,
                c.cep AS cliente_cep, c.codigo AS cliente_codigo, c.nif AS cliente_nif
         FROM {$wpdb->prefix}rc_servicos s
         LEFT JOIN {$wpdb->prefix}rc_clientes c ON s.cliente_id = c.id
         WHERE s.id = %d",
        $servico_id
    ));

    if (!$servico) {
        echo '<p style="text-align:center; padding:60px; font-family:sans-serif;">Serviço não encontrado.</p>';
        return;
    }

    /* -------- Permissões -------- */
    $e_admin   = rc_crm_e_admin();
    $e_tecnico = ((int) $servico->tecnico_id === get_current_user_id());
    $e_cliente = rc_crm_e_cliente_dono($servico_id);

    if (!$e_admin && !$e_tecnico && !$e_cliente) {
        echo '<p style="text-align:center; padding:60px; font-family:sans-serif;">Sem permissão para ver este orçamento.</p>';
        return;
    }

    /* -------- Itens -------- */
    $itens = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_i WHERE servico_id = %d ORDER BY ordem ASC",
        $servico_id
    ));

    if (empty($itens) && !empty($servico->equipamento)) {
        $itens = [(object) [
            'descricao'      => $servico->equipamento,
            'quantidade'     => 1,
            'preco_unitario' => $servico->orcamento ?: 0,
        ]];
    }

    /* -------- Configuração -------- */
    $config          = rc_crm_get_config();
    $iva_percentual  = rc_crm_obter_iva_servico($servico);
    $iva_motivo      = $servico->iva_motivo_isencao ?? '';

    /* -------- Número do orçamento (persistente) -------- */
    $num_orcamento = get_post_meta($servico_id, '_rc_num_orcamento', true);
    if (!$num_orcamento) {
        $ano = date('Y', strtotime($servico->created_at));
        $num_orcamento = sprintf('ORC-%s-%04d', $ano, $servico->id);
        update_post_meta($servico_id, '_rc_num_orcamento', $num_orcamento);
    }

    /* -------- Datas -------- */
    $data_emissao  = date_i18n('d/m/Y', strtotime($servico->created_at));
    $dias_validade = intval($config['cond_validade']);
    if ($dias_validade < 1) $dias_validade = 30;
    $data_validade_padrao = date('Y-m-d', strtotime($servico->created_at . ' +' . $dias_validade . ' days'));

    /* -------- Totais -------- */
    $subtotal = 0;
    foreach ($itens as $it) {
        $subtotal += floatval($it->quantidade) * floatval($it->preco_unitario);
    }
    $iva_valor     = $subtotal * ($iva_percentual / 100);
    $total_final   = $subtotal + $iva_valor;

    $subtotal_fmt    = number_format($subtotal, 2, ',', '.');
    $iva_valor_fmt   = number_format($iva_valor, 2, ',', '.');
    $total_final_fmt = number_format($total_final, 2, ',', '.');
    $valor_letras    = rc_crm_valor_em_letras($total_final);

    /* -------- URL de voltar (seguro) -------- */
    $url_voltar = wp_get_referer();
    if (!$url_voltar) $url_voltar = admin_url('admin.php?page=rc-crm-servicos');
    ?>
    <!DOCTYPE html>
    <html lang="pt">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Orçamento <?php echo esc_html($num_orcamento); ?> — <?php echo esc_html($config['empresa_nome']); ?></title>
        <link rel="icon" href="data:,">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
            * { box-sizing: border-box; }

            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                background: #f0f2f5;
                margin: 0;
                padding: 20px;
                color: #1a1a1a;
                font-size: 14px;
                line-height: 1.5;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }

            .orc-acoes { max-width: 210mm; margin: 0 auto 16px; display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }
            .orc-acoes button, .orc-acoes a {
                padding: 12px 24px;
                border-radius: 8px;
                border: none;
                font-size: 14px;
                font-weight: 600;
                font-family: 'Inter', sans-serif;
                cursor: pointer;
                text-decoration: none;
                transition: all 0.15s;
            }
            .orc-acoes .btn-imprimir { background: #1877F2; color: #fff; }
            .orc-acoes .btn-imprimir:hover { background: #0C63D4; }
            .orc-acoes .btn-editar { background: #7c3aed; color: #fff; }
            .orc-acoes .btn-editar:hover { background: #6d28d9; }
            .orc-acoes .btn-voltar { background: #fff; color: #333; border: 1px solid #ddd; }
            .orc-acoes .btn-voltar:hover { background: #f9f9f9; }

            .orc-folha {
                max-width: 210mm;
                margin: 0 auto;
                background: #fff;
                padding: 20mm;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                border-radius: 8px;
            }

            .orc-cabecalho {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                border-bottom: 3px solid #1877F2;
                padding-bottom: 20px;
                margin-bottom: 30px;
            }
            .orc-cabecalho .empresa h1 {
                margin: 0 0 8px;
                font-size: 22px;
                font-weight: 800;
                color: #1a1a1a;
                letter-spacing: -0.3px;
            }
            .orc-cabecalho .empresa p { margin: 3px 0; font-size: 13px; color: #65676B; font-weight: 400; }
            .orc-cabecalho .numero { text-align: right; }
            .orc-cabecalho .numero .label {
                font-size: 11px;
                color: #999;
                text-transform: uppercase;
                letter-spacing: 1px;
                font-weight: 600;
            }
            .orc-cabecalho .numero .valor {
                font-size: 24px;
                font-weight: 800;
                color: #1877F2;
                letter-spacing: -0.5px;
            }
            .orc-cabecalho .numero .datas { font-size: 12px; color: #65676B; margin-top: 8px; line-height: 1.8; }
            .orc-cabecalho .numero .datas input {
                border: 1px dashed #ccc;
                padding: 4px 6px;
                border-radius: 4px;
                font-size: 12px;
                font-family: 'Inter', sans-serif;
                background: #fafafa;
            }

            .orc-seccao { margin-bottom: 28px; }
            .orc-seccao h2 {
                font-size: 13px;
                color: #1877F2;
                text-transform: uppercase;
                letter-spacing: 1.2px;
                font-weight: 700;
                margin: 0 0 12px;
                padding-bottom: 6px;
                border-bottom: 1px solid #f0f2f5;
            }
            .orc-cliente { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 30px; font-size: 14px; }
            .orc-cliente .linha { display: flex; gap: 10px; }
            .orc-cliente .linha strong { min-width: 90px; color: #65676B; font-weight: 600; }
            .orc-cliente .linha span { color: #1a1a1a; font-weight: 400; }

            .orc-tabela { width: 100%; border-collapse: collapse; margin-top: 10px; }
            .orc-tabela th {
                background: #f0f2f5;
                padding: 12px;
                text-align: left;
                font-size: 11px;
                color: #1a1a1a;
                text-transform: uppercase;
                letter-spacing: 0.8px;
                font-weight: 700;
                border-bottom: 2px solid #1877F2;
            }
            .orc-tabela td { padding: 12px; border-bottom: 1px solid #f0f2f5; font-size: 14px; font-weight: 400; }
            .orc-tabela .col-desc { width: 50%; font-weight: 500; }
            .orc-tabela .col-qtd { width: 12%; text-align: center; }
            .orc-tabela .col-preco { width: 18%; text-align: right; }
            .orc-tabela .col-total { width: 20%; text-align: right; font-weight: 600; }

            .orc-totais { margin-top: 20px; display: flex; justify-content: flex-end; }
            .orc-totais .caixa { min-width: 320px; }
            .orc-totais .linha {
                display: flex;
                justify-content: space-between;
                padding: 10px 0;
                font-size: 14px;
                border-bottom: 1px solid #f0f2f5;
                font-weight: 500;
            }
            .orc-totais .linha span:first-child { color: #65676B; }
            .orc-totais .linha span:last-child { color: #1a1a1a; font-weight: 600; }
            .orc-totais .linha.total {
                border-top: 2px solid #1877F2;
                border-bottom: none;
                margin-top: 6px;
                padding-top: 14px;
                font-size: 20px;
                font-weight: 800;
                color: #1877F2;
                letter-spacing: -0.3px;
            }
            .orc-totais .linha.total span:first-child { color: #1877F2; font-weight: 800; }
            .orc-totais .linha.total span:last-child { color: #1877F2; font-weight: 800; }
            .orc-totais .letras {
                font-size: 12px;
                color: #65676B;
                font-style: italic;
                margin-top: 8px;
                text-align: right;
                font-weight: 400;
            }
            .orc-isencao-info { font-size: 11px; color: #65676B; font-style: italic; margin-top: 4px; }

            .orc-condicoes {
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid #f0f2f5;
                font-size: 12px;
                color: #65676B;
                line-height: 1.7;
            }
            .orc-condicoes h3 {
                font-size: 12px;
                color: #1a1a1a;
                margin: 0 0 8px;
                text-transform: uppercase;
                letter-spacing: 0.8px;
                font-weight: 700;
            }
            .orc-condicoes ul { margin: 0; padding-left: 20px; }
            .orc-condicoes li { margin-bottom: 4px; }

            .orc-assinatura { margin-top: 50px; display: flex; justify-content: space-between; gap: 40px; }
            .orc-assinatura .linha {
                flex: 1;
                border-top: 1px solid #1a1a1a;
                padding-top: 6px;
                text-align: center;
                font-size: 12px;
                color: #65676B;
                font-weight: 500;
            }

            @page { size: A4; margin: 15mm; }

            @media print {
                html, body { background: #fff !important; padding: 0 !important; margin: 0 !important; }
                .orc-acoes { display: none !important; }
                .orc-folha { box-shadow: none !important; padding: 0 !important; max-width: none !important; border-radius: 0 !important; }
                .orc-cabecalho .numero .datas input { border: none !important; background: transparent !important; padding: 0 !important; }
                * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            }
        </style>
    </head>
    <body>

    <div class="orc-acoes">
        <a href="<?php echo esc_url($url_voltar); ?>" class="btn-voltar">← Voltar</a>
        <?php if ($e_admin): ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=rc-crm-servicos&action=editar&id=' . $servico_id)); ?>" class="btn-editar">✏️ Editar Serviço</a>
        <?php endif; ?>
        <button onclick="window.print()" class="btn-imprimir">🖨️ Imprimir / Guardar PDF</button>
    </div>

    <div class="orc-folha">

        <div class="orc-cabecalho">
            <div class="empresa">
                <h1><?php echo esc_html($config['empresa_nome']); ?></h1>
                <p>NIF: <?php echo esc_html($config['empresa_nif']); ?></p>
                <p><?php echo esc_html($config['empresa_morada']); ?></p>
                <p><?php echo esc_html($config['empresa_cidade']); ?></p>
                <p>📞 <?php echo esc_html($config['empresa_telefone']); ?> · ✉️ <?php echo esc_html($config['empresa_email']); ?></p>
                <?php if (!empty($config['empresa_website'])): ?><p>🌐 <?php echo esc_html($config['empresa_website']); ?></p><?php endif; ?>
            </div>
            <div class="numero">
                <div class="label">Orçamento N.º</div>
                <div class="valor"><?php echo esc_html($num_orcamento); ?></div>
                <div class="datas">
                    <div>Emissão: <strong><?php echo esc_html($data_emissao); ?></strong></div>
                    <div>Validade: <input type="date" id="orc-validade" value="<?php echo esc_attr($data_validade_padrao); ?>"></div>
                </div>
            </div>
        </div>

        <div class="orc-seccao">
            <h2>Cliente</h2>
            <div class="orc-cliente">
                <div class="linha"><strong>Nome:</strong><span><?php echo esc_html($servico->cliente_nome); ?></span></div>
                <div class="linha"><strong>Código:</strong><span><?php echo esc_html($servico->cliente_codigo); ?></span></div>
                <?php if (!empty($servico->cliente_nif)): ?>
                    <div class="linha"><strong>NIF:</strong><span><?php echo esc_html($servico->cliente_nif); ?></span></div>
                <?php endif; ?>
                <?php if ($servico->cliente_telefone): ?>
                    <div class="linha"><strong>Telefone:</strong><span><?php echo esc_html($servico->cliente_telefone); ?></span></div>
                <?php endif; ?>
                <?php if ($servico->cliente_email): ?>
                    <div class="linha"><strong>Email:</strong><span><?php echo esc_html($servico->cliente_email); ?></span></div>
                <?php endif; ?>
                <?php if ($servico->cliente_endereco): ?>
                    <div class="linha" style="grid-column: 1 / -1;"><strong>Endereço:</strong><span><?php echo esc_html(trim($servico->cliente_endereco . ' ' . $servico->cliente_complemento)); ?><?php if ($servico->cliente_cep): ?>, <?php echo esc_html($servico->cliente_cep); ?><?php endif; ?> <?php echo esc_html($servico->cliente_cidade); ?></span></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="orc-seccao">
            <h2>Descrição do Serviço</h2>
            <table class="orc-tabela">
                <thead>
                    <tr>
                        <th class="col-desc">Descrição</th>
                        <th class="col-qtd">Qtd.</th>
                        <th class="col-preco">Preço Unit.</th>
                        <th class="col-total">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itens as $it):
                        $sub = floatval($it->quantidade) * floatval($it->preco_unitario);
                    ?>
                        <tr>
                            <td class="col-desc"><?php echo esc_html($it->descricao); ?></td>
                            <td class="col-qtd"><?php echo number_format(floatval($it->quantidade), 2, ',', '.'); ?></td>
                            <td class="col-preco"><?php echo number_format(floatval($it->preco_unitario), 2, ',', '.'); ?> €</td>
                            <td class="col-total"><?php echo number_format($sub, 2, ',', '.'); ?> €</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($servico->observacoes): ?>
                <div style="margin-top:20px; padding:14px; background:#f0f2f5; border-radius:6px; font-size:13px; color:#65676B; font-weight:400;">
                    <strong style="color:#1a1a1a; font-weight:600;">Observações:</strong><br>
                    <span style="white-space:pre-wrap;"><?php echo esc_html($servico->observacoes); ?></span>
                </div>
            <?php endif; ?>

            <div class="orc-totais">
                <div class="caixa">
                    <div class="linha"><span>Base Tributável (Subtotal)</span><span><?php echo esc_html($subtotal_fmt); ?> €</span></div>
                    <div class="linha">
                        <span>IVA (<?php echo number_format($iva_percentual, 2, ',', '.'); ?>%)</span>
                        <span><?php echo esc_html($iva_valor_fmt); ?> €</span>
                    </div>
                    <?php if ($iva_percentual == 0 && !empty($iva_motivo)): ?>
                        <div class="orc-isencao-info">Isenção: <?php echo esc_html($iva_motivo); ?></div>
                    <?php endif; ?>
                    <div class="linha total"><span>TOTAL A PAGAR</span><span><?php echo esc_html($total_final_fmt); ?> €</span></div>
                    <?php if ($valor_letras): ?>
                        <div class="letras"><?php echo esc_html($valor_letras); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="orc-condicoes">
            <h3>Condições</h3>
            <ul>
                <li><strong>Validade:</strong> este orçamento é válido até à data indicada acima.</li>
                <li><strong>Forma de pagamento:</strong> <?php echo esc_html($config['cond_pagamento']); ?>.</li>
                <li><strong>Garantia:</strong> <?php echo esc_html($config['cond_garantia']); ?>.</li>
                <li><strong>Prazo de execução:</strong> <?php echo esc_html($config['cond_prazo']); ?>.</li>
                <?php if (!empty($config['cond_extra'])): ?>
                    <li><?php echo esc_html($config['cond_extra']); ?></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="orc-assinatura">
            <div class="linha">Assinatura do Cliente</div>
            <div class="linha">A Empresa</div>
        </div>

    </div>

    </body>
    </html>
    <?php
}
/* ============================================================
   VALOR EM LETRAS (suporta até milhões)
   ============================================================ */
if (!function_exists('rc_crm_valor_em_letras')) {
    function rc_crm_valor_em_letras($valor) {
        if (empty($valor) || $valor == 0) return '';

        $valor    = floatval($valor);
        $inteiro  = (int) floor($valor);
        $centavos = (int) round(($valor - $inteiro) * 100);

        $unidades = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove'];
        $dezenas  = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];
        $especiais = [
            10 => 'dez', 11 => 'onze', 12 => 'doze', 13 => 'treze', 14 => 'catorze',
            15 => 'quinze', 16 => 'dezasseis', 17 => 'dezassete', 18 => 'dezoito', 19 => 'dezanove',
        ];
        $centenas = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];

        $converter_ate_999 = function($n) use ($unidades, $dezenas, $especiais, $centenas) {
            if ($n === 0) return '';
            if ($n === 100) return 'cem';

            $resultado = [];
            $c = intdiv($n, 100);
            $resto = $n % 100;

            if ($c > 0) $resultado[] = $centenas[$c];

            if ($resto >= 10 && $resto < 20) {
                $resultado[] = $especiais[$resto];
            } else {
                $d = intdiv($resto, 10);
                $u = $resto % 10;
                if ($d > 0) $resultado[] = $dezenas[$d];
                if ($u > 0) $resultado[] = $unidades[$u];
            }

            return implode(' e ', $resultado);
        };

        /* Converte qualquer número até 999.999.999 */
        $converter = function($n) use ($converter_ate_999) {
            if ($n === 0) return '';

            $partes = [];

            $milhoes = intdiv($n, 1000000);
            $n       = $n % 1000000;

            $milhares = intdiv($n, 1000);
            $n        = $n % 1000;

            if ($milhoes > 0) {
                $partes[] = ($milhoes === 1 ? 'um milhão' : $converter_ate_999($milhoes) . ' milhões');
            }

            if ($milhares > 0) {
                $partes[] = ($milhares === 1 ? 'mil' : $converter_ate_999($milhares) . ' mil');
            }

            if ($n > 0) {
                $partes[] = $converter_ate_999($n);
            }

            return implode(' e ', $partes);
        };

        $texto = $converter($inteiro);
        if (empty($texto)) $texto = 'zero';
        $texto .= ' euros';

        if ($centavos > 0) {
            $texto .= ' e ' . $converter($centavos) . ' cêntimos';
        }

        return ucfirst($texto);
    }
}