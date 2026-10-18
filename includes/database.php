<?php
if (!defined('ABSPATH')) exit;

function rc_crm_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_clientes = $wpdb->prefix . 'rc_clientes';
    $table_servicos = $wpdb->prefix . 'rc_servicos';
    $table_itens    = $wpdb->prefix . 'rc_orcamento_itens';
    $table_tags     = $wpdb->prefix . 'rc_servico_tags';
    $table_armazem  = $wpdb->prefix . 'rc_equipamentos_armazem';

    $sql_clientes = "CREATE TABLE $table_clientes (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        codigo varchar(20) NOT NULL,
        nome varchar(255) NOT NULL,
        telefone varchar(20) NOT NULL,
        email varchar(100),
        nif varchar(20),
        tipo_cliente varchar(20) DEFAULT 'particular',
        endereco varchar(255),
        complemento varchar(100),
        cidade varchar(100),
        cep varchar(10),
        latitude decimal(10,8),
        longitude decimal(11,8),
        observacoes text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY codigo (codigo)
    ) $charset_collate;";

    $sql_servicos = "CREATE TABLE $table_servicos (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        cliente_id mediumint(9) NOT NULL,
        equipamento varchar(255),
        tecnico_id bigint(20),
        status varchar(20) DEFAULT 'instalacao',
        data_agendada datetime,
        data_conclusao datetime,
        orcamento decimal(10,2),
        iva_percentual decimal(5,2) DEFAULT NULL,
        iva_motivo_isencao varchar(255) DEFAULT NULL,
        comissao_percentual decimal(5,2) DEFAULT NULL,
        comissao_valor decimal(10,2) DEFAULT NULL,
        comissao_paga_em datetime DEFAULT NULL,
        observacoes text,
        email_agendado_enviado_em datetime DEFAULT NULL,
        email_lembrete_enviado_em datetime DEFAULT NULL,
        email_concluido_enviado_em datetime DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY cliente_id (cliente_id),
        KEY tecnico_id (tecnico_id),
        KEY status (status)
    ) $charset_collate;";

    $sql_itens = "CREATE TABLE $table_itens (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        servico_id mediumint(9) NOT NULL,
        descricao varchar(255) NOT NULL,
        quantidade decimal(10,2) NOT NULL DEFAULT 1,
        preco_unitario decimal(10,2) NOT NULL DEFAULT 0,
        ordem int(11) NOT NULL DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY servico_id (servico_id)
    ) $charset_collate;";

    $sql_tags = "CREATE TABLE $table_tags (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        servico_id mediumint(9) NOT NULL,
        tag_slug varchar(50) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY servico_id (servico_id),
        KEY tag_slug (tag_slug)
    ) $charset_collate;";

    $sql_armazem = "CREATE TABLE $table_armazem (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        cliente_id mediumint(9) DEFAULT NULL,
        servico_id mediumint(9) DEFAULT NULL,
        equipamento varchar(255) NOT NULL,
        referencia_worten varchar(100) DEFAULT NULL,
        tecnico_id bigint(20) DEFAULT NULL,
        data_entrada date NOT NULL,
        data_devolucao date DEFAULT NULL,
        estado varchar(20) DEFAULT 'em_armazem',
        observacoes text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY cliente_id (cliente_id),
        KEY servico_id (servico_id),
        KEY estado (estado)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_clientes);
    dbDelta($sql_servicos);
    dbDelta($sql_itens);
    dbDelta($sql_tags);
    dbDelta($sql_armazem);
}

function rc_crm_migrar_itens_antigos() {
    global $wpdb;
    $table_s = $wpdb->prefix . 'rc_servicos';
    $table_i = $wpdb->prefix . 'rc_orcamento_itens';

    $servicos_sem_itens = $wpdb->get_results(
        "SELECT s.* FROM $table_s s
         WHERE NOT EXISTS (SELECT 1 FROM $table_i i WHERE i.servico_id = s.id)
         AND (s.equipamento IS NOT NULL AND s.equipamento != '')
         AND (s.orcamento IS NOT NULL AND s.orcamento > 0)"
    );

    if (empty($servicos_sem_itens)) return 0;

    $migrados = 0;
    foreach ($servicos_sem_itens as $s) {
        $wpdb->insert($table_i, [
            'servico_id' => $s->id, 'descricao' => $s->equipamento,
            'quantidade' => 1, 'preco_unitario' => $s->orcamento, 'ordem' => 1,
        ]);
        $migrados++;
    }

    update_option('rc_crm_itens_migrados', current_time('mysql'));
    return $migrados;
}

function rc_crm_tags_disponiveis() {
    return [
        'a_devolver'        => ['🔴 A devolver', '#dc3232'],
        'urgente'           => ['🟡 Urgente', '#dba617'],
        'garantia'          => ['🟢 Garantia', '#00a32a'],
        'manutencao'        => ['🔵 Manutenção', '#2271b1'],
        'cliente_ausente'   => ['⚪ Cliente ausente', '#999'],
        'orcamento_pendente'=> ['🟣 Orçamento pendente', '#7c3aed'],
    ];
}

function rc_crm_tipos_cliente() {
    return [
        'particular' => '👤 Particular',
        'empresa'    => '🏢 Empresa',
        'worten'     => '🔵 Worten',
        'outro'      => '📌 Outro',
    ];
}

function rc_crm_obter_tags_servico($servico_id) {
    global $wpdb;
    return $wpdb->get_col($wpdb->prepare(
        "SELECT tag_slug FROM {$wpdb->prefix}rc_servico_tags WHERE servico_id = %d",
        $servico_id
    ));
}

function rc_crm_guardar_tags_servico($servico_id, $tags = []) {
    global $wpdb;
    $table = $wpdb->prefix . 'rc_servico_tags';
    $wpdb->delete($table, ['servico_id' => $servico_id]);

    $disponiveis = array_keys(rc_crm_tags_disponiveis());
    foreach ($tags as $tag) {
        if (in_array($tag, $disponiveis)) {
            $wpdb->insert($table, ['servico_id' => $servico_id, 'tag_slug' => $tag]);
        }
    }
}

/**
 * Validar NIF português (algoritmo do dígito de controlo)
 */
function rc_crm_validar_nif($nif) {
    $nif = preg_replace('/\D/', '', $nif);
    if (strlen($nif) !== 9) return false;
    if (in_array($nif[0], ['0'])) return false;

    $soma = 0;
    for ($i = 0; $i < 8; $i++) {
        $soma += intval($nif[$i]) * (9 - $i);
    }
    $resto = $soma % 11;
    $digito = ($resto < 2) ? 0 : 11 - $resto;

    return $digito === intval($nif[8]);
}

/**
 * Verificar se todas as colunas esperadas existem
 * Corre no admin_init e mostra aviso se algo faltar
 */
add_action('admin_init', 'rc_crm_verificar_estrutura_bd');
function rc_crm_verificar_estrutura_bd() {
    if (!isset($_GET['page']) || strpos($_GET['page'], 'rc-crm') === false) return;

    global $wpdb;
    $table = $wpdb->prefix . 'rc_clientes';

    // Verificar se a tabela existe primeiro
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) return;

    $colunas_esperadas = ['nif', 'tipo_cliente', 'latitude', 'longitude'];
    $colunas_existentes = $wpdb->get_col("DESCRIBE $table", 0);

    $faltam = [];
    foreach ($colunas_esperadas as $col) {
        if (!in_array($col, $colunas_existentes)) {
            $faltam[] = $col;
        }
    }

    if (!empty($faltam)) {
        echo '<div class="notice notice-error" style="border-left-color:#b32d2e;">';
        echo '<p><strong>⚠️ Refrigeração CRM — Problema na base de dados</strong></p>';
        echo '<p>As seguintes colunas estão em falta na tabela <code>' . esc_html($table) . '</code>: <strong>' . esc_html(implode(', ', $faltam)) . '</strong></p>';
        echo '<p>Isto pode fazer com que os formulários falhem <strong>silenciosamente</strong>. Para corrigir:</p>';
        echo '<ol>';
        echo '<li>Vá a <strong>Plugins</strong> e <strong>desative</strong> o Refrigeração CRM</li>';
        echo '<li><strong>Ative</strong> novamente</li>';
        echo '<li>Se o problema persistir, execute manualmente no Adminer:<br>';
        foreach ($faltam as $col) {
            if ($col === 'nif') echo '<code>ALTER TABLE ' . esc_html($table) . ' ADD COLUMN nif VARCHAR(20) AFTER email;</code><br>';
            if ($col === 'tipo_cliente') echo '<code>ALTER TABLE ' . esc_html($table) . ' ADD COLUMN tipo_cliente VARCHAR(20) DEFAULT \'particular\' AFTER nif;</code><br>';
            if ($col === 'latitude') echo '<code>ALTER TABLE ' . esc_html($table) . ' ADD COLUMN latitude DECIMAL(10,8) AFTER cep;</code><br>';
            if ($col === 'longitude') echo '<code>ALTER TABLE ' . esc_html($table) . ' ADD COLUMN longitude DECIMAL(11,8) AFTER latitude;</code><br>';
        }
        echo '</li>';
        echo '</ol>';
        echo '</div>';
    }
}