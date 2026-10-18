<?php
/**
 * Refrigeração CRM — Shortcode do Formulário de Serviço
 * Aceita ?rc_data_agendada=YYYY-MM-DDTHH:MM para pré-preencher a data.
 *
 * @package Refrigeracao_CRM
 * @version 3.6.1
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
   SHORTCODE: [rc_form_servico]
   ============================================================ */
add_shortcode('rc_form_servico', 'rc_crm_form_servico_completo');
function rc_crm_form_servico_completo() {
    if (!current_user_can('manage_options')) return 'Acesso restrito.';

    global $wpdb;
    $table_i    = $wpdb->prefix . 'rc_orcamento_itens';
    $table_tags = $wpdb->prefix . 'rc_servico_tags';

    /* -------- Dados auxiliares -------- */
    $clientes     = $wpdb->get_results("SELECT id, codigo, nome FROM {$wpdb->prefix}rc_clientes ORDER BY nome ASC");
    $utilizadores = function_exists('rc_crm_obter_tecnicos')
        ? rc_crm_obter_tecnicos()
        : get_users(['orderby' => 'display_name', 'order' => 'ASC']);

    $config     = rc_crm_get_config();
    $iva_padrao = floatval($config['iva_global'] ?? 23);

    /* -------- Modo edição? -------- */
    $editando         = false;
    $servico          = null;
    $itens            = [];
    $tags_servico     = [];
    $cliente_telefone = '';

    if (isset($_GET['rc_editar'])) {
        $id = intval($_GET['rc_editar']);
        $servico = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}rc_servicos WHERE id = %d",
            $id
        ));

        if ($servico) {
            $editando = true;

            $itens = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_i WHERE servico_id = %d ORDER BY ordem ASC",
                $id
            ));

            $tags_servico = $wpdb->get_col($wpdb->prepare(
                "SELECT tag_slug FROM $table_tags WHERE servico_id = %d",
                $id
            ));

            if ($servico->iva_percentual !== null && $servico->iva_percentual !== '') {
                $iva_padrao = floatval($servico->iva_percentual);
            }

            $cliente_telefone = $wpdb->get_var($wpdb->prepare(
                "SELECT telefone FROM {$wpdb->prefix}rc_clientes WHERE id = %d",
                $servico->cliente_id
            ));
        }
    }

    /* -------- Pré-preencher data (vindo do calendário) -------- */
    $data_inicial = '';
    if (!$editando && isset($_GET['rc_data_agendada'])) {
        $raw = sanitize_text_field($_GET['rc_data_agendada']);
        // Valida formato YYYY-MM-DD ou YYYY-MM-DDTHH:MM
        if (preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2})?$/', $raw)) {
            $data_inicial = $raw;
            // Se só veio a data, adiciona 09:00
            if (strpos($data_inicial, 'T') === false) {
                $data_inicial .= 'T09:00';
            }
        }
    }

    /* -------- Valor final do campo data -------- */
    if ($editando && $servico->data_agendada) {
        $valor_data = wp_date('Y-m-d\TH:i', strtotime($servico->data_agendada));
    } elseif ($data_inicial) {
        $valor_data = $data_inicial;
    } else {
        $valor_data = '';
    }

    /* -------- Garantir pelo menos 1 item -------- */
    if (empty($itens)) {
        $itens = [(object) ['descricao' => '', 'quantidade' => 1, 'preco_unitario' => 0]];
    }

    $iva_motivo       = ($editando && !empty($servico->iva_motivo_isencao)) ? $servico->iva_motivo_isencao : '';
    $tags_disponiveis = function_exists('rc_crm_tags_disponiveis') ? rc_crm_tags_disponiveis() : [];

    /* -------- Mensagens -------- */
    $msg = '';
    if (isset($_GET['rc_msg'])) {
        $msg_actual = sanitize_text_field($_GET['rc_msg']);
        switch ($msg_actual) {
            case 'salvo':
                $msg = '<div class="rc-mensagem rc-sucesso">✅ Serviço guardado com sucesso!</div>';
                break;
            case 'atualizado':
                $msg = '<div class="rc-mensagem rc-sucesso">✅ Serviço atualizado com sucesso!</div>';
                break;
            case 'duplicado':
                $msg = '<div class="rc-mensagem rc-sucesso">📋 Serviço duplicado com sucesso! Ajuste os dados e guarde.</div>';
                break;
            case 'erro':
                $msg = '<div class="rc-mensagem rc-erro">❌ Erro ao guardar. Verifique os campos.</div>';
                break;
        }
    }

    /* -------- URL de redirect de retorno (sem parâmetros do calendário) -------- */
    $permalink_atual = get_permalink() ?: home_url('/');

    ob_start();
    ?>
    <?php echo $msg; ?>

    <?php if ($editando): ?>
        <div style="max-width:720px; margin:0 auto 12px; display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap;">
            <?php if (function_exists('rc_crm_botao_whatsapp_dropdown') && !empty($cliente_telefone)): ?>
                <?php echo rc_crm_botao_whatsapp_dropdown($servico->id, $cliente_telefone); ?>
            <?php endif; ?>
            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=rc_duplicar_servico&id=' . $servico->id), 'rc_duplicar_servico')); ?>"
               class="button"
               style="padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:600; color:#1877F2; border:1px solid #1877F2; background:#fff;"
               onclick="return confirm('Duplicar este serviço?')">
                📋 Duplicar Serviço
            </a>
        </div>
    <?php endif; ?>

    <form method="post"
          action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
          class="rc-crm-form"
          id="rc-form-servico"
          autocomplete="off">
        <?php wp_nonce_field('rc_salvar_servico', '_wpnonce'); ?>
        <input type="hidden" name="action" value="rc_salvar_servico">
        <input type="hidden" name="rc_redirect" value="<?php echo esc_url($permalink_atual); ?>">
        <?php if ($editando): ?>
            <input type="hidden" name="servico_id" value="<?php echo esc_attr($servico->id); ?>">
        <?php endif; ?>

        <div class="rc-linha rc-full">
            <p class="rc-campo">
                <select id="rc_cliente_id" name="cliente_id" required>
                    <option value=""></option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?php echo esc_attr($c->id); ?>" <?php selected($editando ? $servico->cliente_id : 0, $c->id); ?>>
                            <?php echo esc_html($c->codigo . ' — ' . $c->nome); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="rc_cliente_id">Cliente</label>
            </p>
        </div>

        <div class="rc-linha rc-full">
            <p class="rc-campo">
                <input type="text" id="rc_equipamento" name="equipamento" placeholder=" "
                       value="<?php echo $editando ? esc_attr($servico->equipamento) : ''; ?>">
                <label for="rc_equipamento">Equipamento (resumo)</label>
            </p>
        </div>

        <div class="rc-linha">
            <p class="rc-campo">
                <select id="rc_tecnico_id" name="tecnico_id">
                    <option value=""></option>
                    <?php foreach ($utilizadores as $u): ?>
                        <option value="<?php echo esc_attr($u->ID); ?>" <?php selected($editando ? $servico->tecnico_id : 0, $u->ID); ?>>
                            <?php echo esc_html($u->display_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="rc_tecnico_id">Técnico Responsável</label>
            </p>
            <p class="rc-campo">
                <select id="rc_status" name="status" required>
                    <option value="instalacao" <?php selected($editando ? $servico->status : 'instalacao', 'instalacao'); ?>>Instalação</option>
                    <option value="orcamento"  <?php selected($editando ? $servico->status : '', 'orcamento'); ?>>Orçamento</option>
                    <option value="concluido"  <?php selected($editando ? $servico->status : '', 'concluido'); ?>>Concluído</option>
                    <option value="cancelado"  <?php selected($editando ? $servico->status : '', 'cancelado'); ?>>Cancelado</option>
                </select>
                <label for="rc_status">Status</label>
            </p>
        </div>

        <div class="rc-linha rc-full">
            <p class="rc-campo">
                <input type="datetime-local"
                       id="rc_data_agendada"
                       name="data_agendada"
                       placeholder=" "
                       value="<?php echo esc_attr($valor_data); ?>">
                <label for="rc_data_agendada">Data Agendada</label>
            </p>
        </div>

        <!-- Etiquetas -->
        <div class="rc-itens-wrapper" style="background:#fff; border:1px solid #e5e5e5;">
            <div class="rc-itens-header">
                <h3>🏷️ Etiquetas</h3>
                <p class="rc-itens-info">Marque as etiquetas aplicáveis a este serviço.</p>
            </div>
            <div class="rc-tags-checkbox-grid">
                <?php foreach ($tags_disponiveis as $slug => $tag):
                    $checked = in_array($slug, $tags_servico, true);
                ?>
                    <label class="rc-tag-checkbox <?php echo $checked ? 'selecionado' : ''; ?>" style="color:<?php echo esc_attr($tag[1]); ?>;">
                        <input type="checkbox" name="tags[]" value="<?php echo esc_attr($slug); ?>" <?php checked($checked); ?>>
                        <span><?php echo esc_html($tag[0]); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Itens do orçamento -->
        <div class="rc-itens-wrapper">
            <div class="rc-itens-header">
                <h3>💰 Itens do Orçamento</h3>
                <p class="rc-itens-info">Adicione os itens que compõem este serviço.</p>
            </div>

            <div class="rc-itens-tabela">
                <div class="rc-itens-cabecalho">
                    <div>Descrição</div>
                    <div>Qtd.</div>
                    <div>Preço Unit.</div>
                    <div>Total</div>
                    <div></div>
                </div>

                <div id="rc-itens-lista">
                    <?php foreach ($itens as $item): ?>
                        <div class="rc-item-linha">
                            <input type="text" name="item_descricao[]" placeholder="Descrição do item"
                                   value="<?php echo esc_attr($item->descricao ?? ''); ?>" required>
                            <input type="number" name="item_qtd[]" step="0.01" min="0.01" placeholder="1"
                                   value="<?php echo esc_attr($item->quantidade ?? 1); ?>" class="rc-item-qtd" required>
                            <input type="number" name="item_preco[]" step="0.01" min="0" placeholder="0.00"
                                   value="<?php echo esc_attr($item->preco_unitario ?? 0); ?>" class="rc-item-preco" required>
                            <div class="rc-item-total">0,00 €</div>
                            <button type="button" class="rc-item-remover" title="Remover item">✕</button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" id="rc-add-item" class="rc-add-item-btn">➕ Adicionar Item</button>

                <div class="rc-totais-wrapper">
                    <div class="rc-totais-linha">
                        <span>Subtotal</span>
                        <strong id="rc-subtotal">0,00 €</strong>
                    </div>

                    <div class="rc-totais-linha rc-linha-iva">
                        <span style="display:flex; align-items:center; gap:8px;">
                            IVA
                            <input type="number" id="rc-iva-percentual" name="iva_percentual" step="0.01" min="0" max="100"
                                   value="<?php echo esc_attr($iva_padrao); ?>"
                                   style="width:70px; padding:4px 8px; font-size:14px;">
                            <span>%</span>
                        </span>
                        <strong id="rc-iva-valor">0,00 €</strong>
                    </div>

                    <div class="rc-totais-linha rc-total-final">
                        <span>TOTAL</span>
                        <strong id="rc-total-final">0,00 €</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="rc-linha rc-full" id="rc-motivo-isencao-wrapper"
             style="<?php echo $iva_padrao > 0 ? 'display:none;' : ''; ?>">
            <p class="rc-campo">
                <input type="text" id="rc_iva_motivo" name="iva_motivo_isencao" placeholder=" "
                       value="<?php echo esc_attr($iva_motivo); ?>">
                <label for="rc_iva_motivo">Motivo da Isenção de IVA</label>
            </p>
        </div>

        <div class="rc-linha rc-full">
            <p class="rc-campo">
                <textarea id="rc_servico_obs" name="observacoes" rows="4" placeholder=" "><?php echo $editando ? esc_textarea($servico->observacoes) : ''; ?></textarea>
                <label for="rc_servico_obs">Observações</label>
            </p>
        </div>

        <p class="submit">
            <button type="submit"><?php echo $editando ? '💾 Atualizar Serviço' : '💾 Salvar Serviço'; ?></button>
        </p>
    </form>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const lista          = document.getElementById('rc-itens-lista');
        const addBtn         = document.getElementById('rc-add-item');
        const subtotalEl     = document.getElementById('rc-subtotal');
        const ivaPercEl      = document.getElementById('rc-iva-percentual');
        const ivaValorEl     = document.getElementById('rc-iva-valor');
        const totalFinalEl   = document.getElementById('rc-total-final');
        const motivoWrapper  = document.getElementById('rc-motivo-isencao-wrapper');

        if (!lista || !addBtn) return;

        function formatar(v) {
            return v.toFixed(2).replace('.', ',') + ' €';
        }

        function calcularTotais() {
            let subtotal = 0;

            lista.querySelectorAll('.rc-item-linha').forEach(function(linha) {
                const qtd   = parseFloat(linha.querySelector('.rc-item-qtd').value)   || 0;
                const preco = parseFloat(linha.querySelector('.rc-item-preco').value) || 0;
                const sub   = qtd * preco;
                subtotal += sub;
                linha.querySelector('.rc-item-total').textContent = formatar(sub);
            });

            const ivaPerc    = parseFloat(ivaPercEl.value) || 0;
            const ivaValor   = subtotal * (ivaPerc / 100);
            const totalFinal = subtotal + ivaValor;

            subtotalEl.textContent   = formatar(subtotal);
            ivaValorEl.textContent   = formatar(ivaValor);
            totalFinalEl.textContent = formatar(totalFinal);

            if (motivoWrapper) {
                motivoWrapper.style.display = (ivaPerc === 0) ? '' : 'none';
            }
        }

        function criarLinha() {
            const div = document.createElement('div');
            div.className = 'rc-item-linha';
            div.innerHTML = `
                <input type="text" name="item_descricao[]" placeholder="Descrição do item" required>
                <input type="number" name="item_qtd[]" step="0.01" min="0.01" placeholder="1" value="1" class="rc-item-qtd" required>
                <input type="number" name="item_preco[]" step="0.01" min="0" placeholder="0.00" value="0" class="rc-item-preco" required>
                <div class="rc-item-total">0,00 €</div>
                <button type="button" class="rc-item-remover" title="Remover item">✕</button>
            `;
            return div;
        }

        addBtn.addEventListener('click', function() {
            lista.appendChild(criarLinha());
            calcularTotais();
        });

        lista.addEventListener('input', function(e) {
            if (e.target.classList.contains('rc-item-qtd') || e.target.classList.contains('rc-item-preco')) {
                calcularTotais();
            }
        });

        ivaPercEl.addEventListener('input', calcularTotais);

        lista.addEventListener('click', function(e) {
            if (e.target.classList.contains('rc-item-remover')) {
                const linhas = lista.querySelectorAll('.rc-item-linha');
                if (linhas.length > 1) {
                    e.target.closest('.rc-item-linha').remove();
                    calcularTotais();
                } else {
                    alert('O orçamento precisa ter pelo menos 1 item.');
                }
            }
        });

        document.querySelectorAll('.rc-tag-checkbox input[type="checkbox"]').forEach(function(cb) {
            cb.addEventListener('change', function() {
                this.closest('.rc-tag-checkbox').classList.toggle('selecionado', this.checked);
            });
        });

        calcularTotais();
    });
    </script>
    <?php
    return ob_get_clean();
}