<style>
@media (min-width: 768px) {
    .tuss-field .form-inline {
        display: grid;
        grid-template-columns: minmax(320px, 520px) 138px 82px 82px 78px 98px;
        column-gap: 12px;
        align-items: start;
        justify-content: start;
        width: auto;
    }

    .tuss-field .form-group {
        margin: 0 !important;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        min-width: 0;
    }

    .tuss-field .w-desc {
        width: 100%;
        max-width: 520px;
    }

    .tuss-field .w-date {
        width: 138px;
    }

    .tuss-field .w-qty {
        width: 82px;
    }

    .tuss-field .w-ok {
        width: 78px;
    }

    .tuss-field .w-btns {
        width: 98px;
    }

    .tuss-field .bootstrap-select {
        display: block !important;
        width: 100% !important;
        margin: 0 !important;
    }

    .tuss-field .bootstrap-select > .dropdown-toggle,
    .tuss-field .form-control {
        width: 100% !important;
        margin: 0 !important;
        vertical-align: top;
    }

    .tuss-field .bootstrap-select > .dropdown-toggle,
    .tuss-field .form-control {
        min-height: 42px !important;
        height: 42px !important;
        padding-top: 8px !important;
        padding-bottom: 8px !important;
        line-height: 1.5 !important;
        border-radius: 12px !important;
        box-sizing: border-box !important;
    }

    .tuss-field input.form-control {
        padding-left: 12px !important;
        padding-right: 12px !important;
    }

    .tuss-field select.form-control {
        padding-right: 28px !important;
    }

    .tuss-field .bootstrap-select > .dropdown-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 0 !important;
    }

    .tuss-field .bootstrap-select > .dropdown-toggle .filter-option,
    .tuss-field .bootstrap-select > .dropdown-toggle .filter-option-inner,
    .tuss-field .bootstrap-select > .dropdown-toggle .filter-option-inner-inner {
        line-height: 24px !important;
    }

    .tuss-field .w-btns > div {
        display: flex;
        gap: 8px;
        height: 42px;
        align-items: center;
        margin-top: 0;
    }
}

/* ====== Empilhado em telas pequenas ====== */
@media (max-width: 767.98px) {
    .tuss-field .form-inline {
        display: grid;
        grid-template-columns: 1fr;
        row-gap: 10px;
    }
}

/* Labels compactos */
.tuss-field label {
    font-size: .8rem;
    margin: 0;
    display: block;
    min-height: 20px;
    margin-bottom: 8px;
    line-height: 1.2;
}
</style>



<?php

/**
 *  form_edit_internacao_tuss2.php
 *  ------------------------------
 *  Bloco de edição de TUSS para usar dentro do <form>
 *  Espera: $BASE_URL, $intern, $tussGeral, $tussInt
 */

if (!isset($BASE_URL, $intern, $tussGeral)) {
    exit('Variáveis necessárias não definidas');
}

$tussInt = array_map(fn($t) => (array) $t, $tussInt ?? []);
if (!$tussInt) {
    // placeholder de 1 linha vazia
    $tussInt[] = [
        'tuss_solicitado' => '',
        'data_realizacao_tuss' => date('Y-m-d'),
        'qtd_tuss_solicitado' => '',
        'qtd_tuss_liberado' => '',
        'tuss_liberado_sn' => ''
    ];
}

function optionsTuss(array $all, $sel = ''): string
{
    $out = '<option value="">...</option>';
    $seen = [];
    foreach ($all as $t) {
        $val = $t['cod_tuss'];
        if (isset($seen[$val])) {
            continue;
        }
        $txt = $t['cod_tuss'] . ' - ' . $t['terminologia_tuss'];
        $selA = $val === $sel ? ' selected' : '';
        $out .= "<option value=\"$val\"$selA>$txt</option>";
        $seen[$val] = true;
    }
    return $out;
}

$jsonTuss = htmlspecialchars(json_encode($tussInt, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
?>

<!-- ===================== BLOCO TUSS ===================== -->
<div id="tussFieldsContainer" class="mt-4">
    <h5 class="mb-3">Procedimentos TUSS</h5>
    <!-- Campo oculto que o back-end processa -->
    <input type="hidden" name="tuss_json" id="tuss_json" value="<?= $jsonTuss ?>">
    <!-- Hidden que sinaliza envio -->
    <input type="hidden" name="select_tuss" value="s">

    <?php foreach ($tussInt as $i => $t):
        $idx = (int) $i; ?>

    <!-- Se já existir FK, guarda o id da internação-TUSS -->
    <?php if (!empty($t['fk_int_tuss'])): ?>
    <input type="hidden" name="tuss[<?= $idx ?>][fk_int_tuss]" value="<?= (int) $t['fk_int_tuss'] ?>">
    <?php endif; ?>

    <div class="tuss-field border rounded p-3 mb-2">
        <div class="form-inline align-items-end flex-wrap">

            <?php if (!empty($t['fk_int_tuss'])): ?>
            <input type="hidden" name="tuss[<?= $idx ?>][id_tuss]" value="<?= (int) $t['id_tuss'] ?>">
            <input type="hidden" name="tuss[<?= $idx ?>][fk_int_tuss]" value="<?= (int) $t['fk_int_tuss'] ?>">
            <?php endif; ?>

            <!-- Descrição TUSS -->
            <div class="form-group w-desc">
                <label>Descrição TUSS</label>
                <select class="selectpicker form-control form-control-sm" data-live-search="true" data-width="100%"
                    name="tuss[<?= $idx ?>][tuss_solicitado]">
                    <?= optionsTuss($tussGeral, $t['tuss_solicitado']) ?>
                </select>
            </div>

            <!-- Data -->
            <div class="form-group w-date">
                <label>Data</label>
                <input type="date" class="form-control form-control-sm" name="tuss[<?= $idx ?>][data_realizacao_tuss]"
                    value="<?= htmlspecialchars($t['data_realizacao_tuss']) ?>">
            </div>

            <!-- Qtd solicitada -->
            <div class="form-group w-qty">
                <label>Qtd Sol.</label>
                <input type="text" class="form-control form-control-sm" name="tuss[<?= $idx ?>][qtd_tuss_solicitado]"
                    value="<?= htmlspecialchars($t['qtd_tuss_solicitado']) ?>">
            </div>

            <!-- Qtd liberada -->
            <div class="form-group w-qty">
                <label>Qtd Lib.</label>
                <input type="text" class="form-control form-control-sm" name="tuss[<?= $idx ?>][qtd_tuss_liberado]"
                    value="<?= htmlspecialchars($t['qtd_tuss_liberado']) ?>">
            </div>

            <!-- Liberação S/N -->
            <div class="form-group w-ok">
                <label>OK?</label>
                <select class="form-control form-control-sm" name="tuss[<?= $idx ?>][tuss_liberado_sn]">
                    <option value="" <?= $t['tuss_liberado_sn'] == '' ? 'selected' : '' ?>></option>
                    <option value="s" <?= $t['tuss_liberado_sn'] == 's' ? 'selected' : '' ?>>S</option>
                    <option value="n" <?= $t['tuss_liberado_sn'] == 'n' ? 'selected' : '' ?>>N</option>
                </select>
            </div>

            <!-- Botões -->
            <div class="form-group w-btns">
                <label style="visibility:hidden">Ações</label>
                <div>
                    <button type="button" class="btn btn-success btn-sm btn-add">+</button>
                    <button type="button" class="btn btn-danger btn-sm btn-remove"
                        onclick="removeTussField(this)">&minus;</button>
                </div>
            </div>

        </div>
    </div>

    <?php endforeach; ?>

</div> <!-- /#tussFieldsContainer -->

<!-- ============ JS específico do bloco ============ -->
<script>
$(function() {
    const $selectTuss = $('#select_tuss');
    const $container = $('#tussFieldsContainer');
    const formSel = '#myForm';

    // 1) Toggle de exibição
    if ($selectTuss.length && $container.length) {
        const toggle = () => $container.toggle($selectTuss.val() === 's');
        $selectTuss.on('change', toggle);
        toggle();
    }

    // 2) Captura do template “cru” ANTES de init o plugin
    let templateHtml = '';
    const $first = $container.find('.tuss-field').first();
    if ($first.length) {
        const $temp = $first.clone();
        // Desembrulha qualquer wrapper do bootstrap-select
        $temp.find('.bootstrap-select').each(function() {
            const $wrap = $(this);
            $wrap.replaceWith($wrap.find('select'));
        });
        templateHtml = $temp.prop('outerHTML');
    }

    // 3) Helpers
    function initSelects(scope) {
        $(scope).find('select.selectpicker').each(function() {
            const $sel = $(this);
            // força largura a partir do data-width (100% no CSS do container)
            $sel.selectpicker('render').selectpicker('refresh');
        });
    }


    let lastJson = '';

    function rebuildJson() {
        const arr = [];
        $container.find('.tuss-field').each(function() {
            const $f = $(this);
            arr.push({
                id_tuss: $f.find('[name$="[id_tuss]"]').val() || '',
                fk_int_tuss: $f.find('[name$="[fk_int_tuss]"]').val() || '',
                tuss_solicitado: $f.find('[name$="[tuss_solicitado]"]').val() || '',
                data_realizacao_tuss: $f.find('[name$="[data_realizacao_tuss]"]').val() || '',
                qtd_tuss_solicitado: $f.find('[name$="[qtd_tuss_solicitado]"]').val() || '',
                qtd_tuss_liberado: $f.find('[name$="[qtd_tuss_liberado]"]').val() || '',
                tuss_liberado_sn: $f.find('[name$="[tuss_liberado_sn]"]').val() || ''
            });
        });
        const j = JSON.stringify(arr);
        if (j !== lastJson) {
            $('#tuss_json').val(j);
            lastJson = j;
        }
    }

    // 4) Inicialização
    initSelects(document);
    rebuildJson();

    // 5) Eventos
    $container
        // Adicionar novo
        .on('click', '.btn-add', function() {
            if (!templateHtml) return;
            const idx = $container.find('.tuss-field').length;
            // ajusta índice em todas as ocorrências de [n]
            const html = templateHtml.replace(/\[\d+\]/g, '[' + idx + ']');
            const $new = $(html);
            // limpa valores
            $new.find('input').val('');
            $new.find('select').val('');
            // anexa e inicializa só o <select> novo
            $container.append($new);
            initSelects($new);
            rebuildJson();
        })
        // Remover
        .on('click', '.btn-remove', function() {
            if ($container.find('.tuss-field').length > 1) {
                $(this).closest('.tuss-field').remove();
                rebuildJson();
            }
        })
        // Qualquer mudança
        .on('input change changed.bs.select', '.tuss-field input, .tuss-field select', rebuildJson);

    // 6) Antes do submit
    $(formSel).on('submit', rebuildJson);
});
</script>
