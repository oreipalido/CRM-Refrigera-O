<?php
/**
 * Refrigeração CRM — Handlers de Formulários
 *
 * @package Refrigeracao_CRM
 * @version 3.5.2
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
   HELPER: verificar se código de cliente já existe
   ============================================================ */
function rc_crm_cliente_codigo_existe($codigo, $excluir_id = 0) {
    global $wpdb;
    $table = $wpdb->prefix . 'rc_clientes';

    $sql    = "SELECT id FROM $table WHERE codigo = %s";
    $params = [$codigo];

    if ($excluir_id) {
        $sql .= " AND id != %d";
        $params[] = $excluir_id;
    }

    return (bool) $wpdb->get_var($wpdb->prepare($sql, $params));
}

/* ============================================================
   HANDLER: GUARDAR CLIENTE
   ============================================================ */
add_action('admin_post_rc_salvar_cliente', 'rc_crm_salvar_cliente');
function rc_crm_salvar_cliente() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão');
    check_admin_referer('rc_salvar_cliente');

    global $wpdb;
    $table = $wpdb->prefix . 'rc_clientes';

    /* -------- Dados do POST -------- */
    $cliente_id  = isset($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
    $editando    = $cliente_id > 0;
    $from_admin  = !empty($_POST['rc_from_admin']);

    $codigo       = sanitize_text_field($_POST['codigo']       ?? '');
    $nome         = sanitize_text_field($_POST['nome']         ?? '');
    $telefone     = sanitize_text_field($_POST['telefone']     ?? '');
    $email        = sanitize_email($_POST['email']             ?? '');
    $nif          = sanitize_text_field($_POST['nif']          ?? '');
    $tipo_cliente = sanitize_text_field($_POST['tipo_cliente'] ?? 'particular');
    $endereco     = sanitize_text_field($_POST['endereco']     ?? '');
    $complemento  = sanitize_text_field($_POST['complemento']  ?? '');
    $cidade       = sanitize_text_field($_POST['cidade']       ?? '');
    $cep          = sanitize_text_field($_POST['cep']          ?? '');
    $observacoes  = sanitize_textarea_field($_POST['observacoes'] ?? '');

    $redirect = !empty($_POST['rc_redirect']) ? esc_url_raw($_POST['rc_redirect']) : '';

    /* -------- Função de erro (guarda valores em TRANSIENT) -------- */
    $redir_erro = function($codigo_erro, $msg = '') use (
        $redirect, $from_admin, $cliente_id,
        $codigo, $nome, $telefone, $email, $nif, $tipo_cliente,
        $endereco, $complemento, $cidade, $cep, $observacoes
    ) {
        /* Guardar valores repostos em transient (válido 60s) */
        $repost = [
            'codigo'       => $codigo,
            'nome'         => $nome,
            'telefone'     => $telefone,
            'email'        => $email,
            'nif'          => $nif,
            'tipo_cliente' => $tipo_cliente,
            'endereco'     => $endereco,
            'complemento'  => $complemento,
            'cidade'       => $cidade,
            'cep'          => $cep,
            'observacoes'  => $observacoes,
        ];
        set_transient('rc_crm_repost_' . get_current_user_id(), $repost, 60);

        if ($from_admin) {
            wp_safe_redirect(add_query_arg('msg', $codigo_erro, admin_url('admin.php?page=rc-crm-clientes')));
        } elseif ($redirect) {
            wp_safe_redirect(add_query_arg('rc_msg', $codigo_erro, $redirect));
        } else {
            wp_safe_redirect(admin_url('admin.php?page=rc-crm-clientes&msg=' . $codigo_erro));
        }
        exit;
    };

    /* -------- Validações -------- */
    if (empty($codigo) || empty($nome) || empty($telefone)) {
        $redir_erro('erro');
    }

    if (rc_crm_cliente_codigo_existe($codigo, $cliente_id)) {
        $redir_erro('codigo_duplicado');
    }

    $nif_limpo = preg_replace('/\D/', '', $nif);
    if (!empty($nif_limpo)) {
        if (!function_exists('rc_crm_validar_nif') || !rc_crm_validar_nif($nif_limpo)) {
            $redir_erro('nif_invalido');
        }
    }

    $tipos_validos = function_exists('rc_crm_tipos_cliente')
        ? array_keys(rc_crm_tipos_cliente())
        : ['particular'];

    if (!in_array($tipo_cliente, $tipos_validos, true)) {
        $tipo_cliente = 'particular';
    }

    /* -------- Geolocalização (Nominatim) -------- */
    $latitude  = isset($_POST['latitude'])  ? sanitize_text_field($_POST['latitude'])  : null;
    $longitude = isset($_POST['longitude']) ? sanitize_text_field($_POST['longitude']) : null;

    if ((!$latitude || !$longitude) && !empty($endereco) && !empty($cidade)) {
        $endereco_completo = urlencode($endereco . ', ' . $cidade . ', Portugal');
        $response = wp_remote_get(
            "https://nominatim.openstreetmap.org/search?q={$endereco_completo}&format=json&limit=1",
            [
                'timeout' => 10,
                'headers' => ['User-Agent' => 'RefrigeracaoCRM/1.0'],
            ]
        );

        if (!is_wp_error($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($data[0])) {
                $latitude  = $data[0]['lat'];
                $longitude = $data[0]['lon'];
            }
        }
    }

    /* -------- Dados para gravar -------- */
    $dados = [
        'codigo'       => $codigo,
        'nome'         => $nome,
        'telefone'     => $telefone,
        'email'        => $email,
        'nif'          => $nif_limpo,
        'tipo_cliente' => $tipo_cliente,
        'endereco'     => $endereco,
        'complemento'  => $complemento,
        'cidade'       => $cidade,
        'cep'          => $cep,
        'observacoes'  => $observacoes,
        'latitude'     => $latitude,
        'longitude'    => $longitude,
    ];

    /* -------- Insert / Update -------- */
    $wpdb->suppress_errors(false);

    if ($editando) {
        $resultado = $wpdb->update($table, $dados, ['id' => $cliente_id]);
        $msg = 'atualizado';
    } else {
        $resultado = $wpdb->insert($table, $dados);
        $msg = 'salvo';
    }

    if ($resultado === false) {
        error_log('RC CRM - Erro INSERT/UPDATE cliente: ' . $wpdb->last_error);
        $redir_erro('bd_erro', $wpdb->last_error);
    }

    /* -------- Redirecionar -------- */
    if ($from_admin) {
        wp_safe_redirect(add_query_arg('msg', $msg, admin_url('admin.php?page=rc-crm-clientes')));
    } elseif ($redirect) {
        wp_safe_redirect(add_query_arg('rc_msg', $msg, $redirect));
    } else {
        wp_safe_redirect(admin_url('admin.php?page=rc-crm-clientes&msg=' . $msg));
    }
    exit;
}

/* ============================================================
   HANDLER: GUARDAR SERVIÇO
   ============================================================ */
add_action('admin_post_rc_salvar_servico', 'rc_crm_salvar_servico');
function rc_crm_salvar_servico() {
    if (!current_user_can('manage_options')) wp_die('Sem permissão');
    check_admin_referer('rc_salvar_servico');

    global $wpdb;
    $table_s = $wpdb->prefix . 'rc_servicos';
    $table_i = $wpdb->prefix . 'rc_orcamento_itens';

    /* -------- Dados do POST -------- */
    $servico_id = isset($_POST['servico_id']) ? intval($_POST['servico_id']) : 0;
    $editando   = $servico_id > 0;
    $from_admin = !empty($_POST['rc_from_admin']);

    $cliente_id    = intval($_POST['cliente_id'] ?? 0);
    $equipamento   = sanitize_text_field($_POST['equipamento'] ?? '');
    $tecnico_id    = !empty($_POST['tecnico_id']) ? intval($_POST['tecnico_id']) : null;
    $status        = sanitize_text_field($_POST['status'] ?? '');
    $data_agendada = !empty($_POST['data_agendada']) ? sanitize_text_field($_POST['data_agendada']) : null;
    $observacoes   = sanitize_textarea_field($_POST['observacoes'] ?? '');

    /* -------- IVA (com clamp 0-100) -------- */
    $iva_percentual = isset($_POST['iva_percentual']) ? floatval($_POST['iva_percentual']) : null;
    if ($iva_percentual !== null) {
        $iva_percentual = max(0, min(100, $iva_percentual));
    }

    $iva_motivo = isset($_POST['iva_motivo_isencao']) ? sanitize_text_field($_POST['iva_motivo_isencao']) : '';
    if ($iva_percentual > 0) $iva_motivo = '';

    $redirect = !empty($_POST['rc_redirect']) ? esc_url_raw($_POST['rc_redirect']) : '';

    /* -------- Validações -------- */
    if (!$cliente_id) {
        wp_die('É necessário selecionar um cliente. <br><br><a href="javascript:history.back()">← Voltar</a>');
    }

    $status_validos = ['instalacao', 'orcamento', 'concluido', 'cancelado'];
    if (!in_array($status, $status_validos, true)) {
        $status = 'instalacao';
    }

    /* -------- Processar itens do orçamento -------- */
    $itens    = [];
    $subtotal = 0;

    if (isset($_POST['item_descricao']) && is_array($_POST['item_descricao'])) {
        $total_linhas = count($_POST['item_descricao']);

        for ($i = 0; $i < $total_linhas; $i++) {
            $desc = sanitize_text_field($_POST['item_descricao'][$i] ?? '');
            $qtd  = floatval($_POST['item_qtd'][$i]   ?? 0);
            $p    = floatval($_POST['item_preco'][$i] ?? 0);

            if (empty($desc) || $qtd <= 0) continue;

            $itens[] = [
                'descricao'      => $desc,
                'quantidade'     => $qtd,
                'preco_unitario' => $p,
                'ordem'          => count($itens) + 1,
            ];
            $subtotal += $qtd * $p;
        }
    }

    /* -------- Status anterior -------- */
    $status_anterior = null;
    if ($editando) {
        $status_anterior = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM $table_s WHERE id = %d",
            $servico_id
        ));
    }

    /* -------- Dados para gravar -------- */
    $dados = [
        'cliente_id'         => $cliente_id,
        'equipamento'        => $equipamento,
        'tecnico_id'         => $tecnico_id,
        'status'             => $status,
        'data_agendada'      => $data_agendada,
        'orcamento'          => $subtotal,
        'iva_percentual'     => $iva_percentual,
        'iva_motivo_isencao' => $iva_motivo,
        'observacoes'        => $observacoes,
    ];

    if ($status === 'concluido') {
        $dados['data_conclusao'] = current_time('mysql');
    } else {
        $dados['data_conclusao'] = null;
    }

    /* -------- Insert / Update -------- */
    $wpdb->suppress_errors(false);

    if ($editando) {
        $resultado = $wpdb->update($table_s, $dados, ['id' => $servico_id]);
        $msg = 'atualizado';
        $novo_id = $servico_id;
        $wpdb->delete($table_i, ['servico_id' => $servico_id]);
    } else {
        $resultado = $wpdb->insert($table_s, $dados);
        $novo_id = $wpdb->insert_id;
        $msg = 'salvo';
    }

    if ($resultado === false) {
        error_log('RC CRM - Erro INSERT/UPDATE serviço: ' . $wpdb->last_error);
        wp_die(
            'Erro ao guardar serviço: ' . esc_html($wpdb->last_error) .
            '<br><br><a href="javascript:history.back()">← Voltar</a>'
        );
    }

    /* -------- Inserir itens -------- */
    foreach ($itens as $item) {
        $item['servico_id'] = $novo_id;
        $wpdb->insert($table_i, $item);
    }

    /* -------- Tags -------- */
    if (function_exists('rc_crm_guardar_tags_servico')) {
        $tags = (isset($_POST['tags']) && is_array($_POST['tags']))
            ? array_map('sanitize_text_field', $_POST['tags'])
            : [];
        rc_crm_guardar_tags_servico($novo_id, $tags);
    }

    /* -------- Comissões -------- */
    if ($status === 'concluido' && $status_anterior !== 'concluido') {
        if (function_exists('rc_crm_calcular_comissao_servico')) {
            rc_crm_calcular_comissao_servico($novo_id);
        }
    } elseif ($status !== 'concluido') {
        $wpdb->update($table_s, [
            'comissao_percentual' => null,
            'comissao_valor'      => null,
        ], ['id' => $novo_id]);
    }

    /* -------- Notificações -------- */
    if (function_exists('rc_crm_disparar_notificacoes_servico')) {
        rc_crm_disparar_notificacoes_servico($novo_id, $status_anterior);
    }

    /* -------- Redirecionar -------- */
    if ($from_admin) {
        wp_safe_redirect(add_query_arg('msg', $msg, admin_url('admin.php?page=rc-crm-servicos')));
    } elseif ($redirect) {
        wp_safe_redirect(add_query_arg('rc_msg', $msg, $redirect));
    } else {
        wp_safe_redirect(admin_url('admin.php?page=rc-crm-servicos&msg=' . $msg));
    }
    exit;
}