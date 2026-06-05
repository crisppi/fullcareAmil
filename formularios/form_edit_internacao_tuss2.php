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

    .tuss-field .w-desc { width: 100%; max-width: 520px; }
    .tuss-field .w-date { width: 138px; }
    .tuss-field .w-qty  { width: 82px; }
    .tuss-field .w-ok   { width: 78px; }
    .tuss-field .w-btns { width: 98px; }

    .tuss-field .form-control {
        width: 100% !important;
        margin: 0 !important;
        vertical-align: top;
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

    .tuss-field .w-btns > div {
        display: flex;
        gap: 8px;
        height: 42px;
        align-items: center;
        margin-top: 0;
    }
}

@media (max-width: 767.98px) {
    .tuss-field .form-inline { display: grid; grid-template-columns: 1fr; row-gap: 10px; }
}

.tuss-field label {
    font-size: .8rem;
    margin: 0;
    display: block;
    min-height: 20px;
    margin-bottom: 8px;
    line-height: 1.2;
}

/* Autocomplete TUSS */
.tuss-ac-wrap { position: relative; width: 100%; }
.tuss-ac-drop {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 99999;
    background: #fff;
    border: 1px solid #ced4da;
    border-radius: 4px;
    max-height: 220px;
    overflow-y: auto;
    box-shadow: 0 4px 12px rgba(0,0,0,.15);
}
.tuss-ac-item {
    padding: 7px 10px;
    cursor: pointer;
    font-size: .88rem;
    line-height: 1.3;
}
.tuss-ac-item:hover, .tuss-ac-item.active { background: #f0eaff; color: #3a184f; }
.tuss-ac-empty { padding: 8px 10px; color: #999; font-size: .85rem; }
</style>

<?php

/**
 *  form_edit_internacao_tuss2.php
 *  Espera: $BASE_URL, $intern, $tussGeral, $tussInt
 */

if (!isset($BASE_URL, $intern, $tussGeral)) {
    exit('Variáveis necessárias não definidas');
}

$tussInt = array_map(fn($t) => (array) $t, $tussInt ?? []);
if (!$tussInt) {
    $tussInt[] = [
        'tuss_solicitado'      => '',
        'data_realizacao_tuss' => date('Y-m-d'),
        'qtd_tuss_solicitado'  => '',
        'qtd_tuss_liberado'    => '',
        'tuss_liberado_sn'     => ''
    ];
}

// Mapa código → texto para pré-preencher o campo de edição
$tussMap = [];
foreach ($tussGeral as $tg) {
    $tussMap[$tg['cod_tuss']] = $tg['cod_tuss'] . ' - ' . $tg['terminologia_tuss'];
}

$jsonTuss = htmlspecialchars(json_encode($tussInt, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
?>

<!-- ===================== BLOCO TUSS ===================== -->
<div id="tussFieldsContainer" class="mt-4">
    <h5 class="mb-3">Procedimentos TUSS</h5>
    <input type="hidden" name="tuss_json" id="tuss_json" value="<?= $jsonTuss ?>">
    <input type="hidden" name="select_tuss" value="s">

    <?php foreach ($tussInt as $i => $t):
        $idx      = (int) $i;
        $tussCode = $t['tuss_solicitado'] ?? '';
        $tussText = $tussMap[$tussCode] ?? '';
    ?>

    <?php if (!empty($t['fk_int_tuss'])): ?>
    <input type="hidden" name="tuss[<?= $idx ?>][fk_int_tuss]" value="<?= (int) $t['fk_int_tuss'] ?>">
    <?php endif; ?>

    <div class="tuss-field border rounded p-3 mb-2">
        <div class="form-inline align-items-end flex-wrap">

            <?php if (!empty($t['fk_int_tuss'])): ?>
            <input type="hidden" name="tuss[<?= $idx ?>][id_tuss]"      value="<?= (int) $t['id_tuss'] ?>">
            <input type="hidden" name="tuss[<?= $idx ?>][fk_int_tuss]"  value="<?= (int) $t['fk_int_tuss'] ?>">
            <?php endif; ?>

            <!-- Descrição TUSS (autocomplete) -->
            <div class="form-group w-desc">
                <label>Descrição TUSS</label>
                <div class="tuss-ac-wrap">
                    <input type="text"
                           class="form-control form-control-sm tuss-ac-text"
                           placeholder="Digite código ou descrição"
                           autocomplete="off"
                           value="<?= htmlspecialchars($tussText) ?>">
                    <input type="hidden"
                           name="tuss[<?= $idx ?>][tuss_solicitado]"
                           class="tuss-ac-val"
                           value="<?= htmlspecialchars($tussCode) ?>">
                    <div class="tuss-ac-drop"></div>
                </div>
            </div>

            <!-- Data -->
            <div class="form-group w-date">
                <label>Data</label>
                <input type="date" class="form-control form-control-sm"
                       name="tuss[<?= $idx ?>][data_realizacao_tuss]"
                       value="<?= htmlspecialchars($t['data_realizacao_tuss']) ?>">
            </div>

            <!-- Qtd solicitada -->
            <div class="form-group w-qty">
                <label>Qtd Sol.</label>
                <input type="text" class="form-control form-control-sm"
                       name="tuss[<?= $idx ?>][qtd_tuss_solicitado]"
                       value="<?= htmlspecialchars($t['qtd_tuss_solicitado']) ?>">
            </div>

            <!-- Qtd liberada -->
            <div class="form-group w-qty">
                <label>Qtd Lib.</label>
                <input type="text" class="form-control form-control-sm"
                       name="tuss[<?= $idx ?>][qtd_tuss_liberado]"
                       value="<?= htmlspecialchars($t['qtd_tuss_liberado']) ?>">
            </div>

            <!-- Liberação S/N -->
            <div class="form-group w-ok">
                <label>OK?</label>
                <select class="form-control form-control-sm" name="tuss[<?= $idx ?>][tuss_liberado_sn]">
                    <option value=""  <?= $t['tuss_liberado_sn'] == ''  ? 'selected' : '' ?>></option>
                    <option value="s" <?= $t['tuss_liberado_sn'] == 's' ? 'selected' : '' ?>>S</option>
                    <option value="n" <?= $t['tuss_liberado_sn'] == 'n' ? 'selected' : '' ?>>N</option>
                </select>
            </div>

            <!-- Botões -->
            <div class="form-group w-btns">
                <label style="visibility:hidden">Ações</label>
                <div>
                    <button type="button" class="btn btn-success btn-sm btn-add">+</button>
                    <button type="button" class="btn btn-danger btn-sm btn-remove">&minus;</button>
                </div>
            </div>

        </div>
    </div>

    <?php endforeach; ?>

</div>

<script>
(function() {
    var TUSS_URL = '<?= $BASE_URL ?>ajax/search_tuss.php';

    function initTussAutocomplete(container) {
        container.querySelectorAll('.tuss-ac-text').forEach(function(inp) {
            if (inp.dataset.acInit) return;
            inp.dataset.acInit = '1';

            var wrap     = inp.closest('.tuss-ac-wrap');
            var hidden   = wrap.querySelector('.tuss-ac-val');
            var drop     = wrap.querySelector('.tuss-ac-drop');
            var timer, activeIdx = -1;

            function closeDrop() { drop.style.display = 'none'; activeIdx = -1; }
            function openDrop()  { drop.style.display = 'block'; }

            function renderResults(results) {
                activeIdx = -1;
                drop.innerHTML = '';
                if (!results.length) {
                    drop.innerHTML = '<div class="tuss-ac-empty">Nenhum resultado</div>';
                } else {
                    results.forEach(function(item) {
                        var div = document.createElement('div');
                        div.className = 'tuss-ac-item';
                        div.textContent = item.text;
                        div.addEventListener('mousedown', function(e) {
                            e.preventDefault();
                            inp.value    = item.text;
                            hidden.value = item.id;
                            closeDrop();
                            rebuildJson();
                        });
                        drop.appendChild(div);
                    });
                }
                openDrop();
            }

            function search(q) {
                fetch(TUSS_URL + '?q=' + encodeURIComponent(q), {credentials: 'same-origin'})
                    .then(function(r) { return r.json(); })
                    .then(function(d) { renderResults(d.results || []); })
                    .catch(function()  { closeDrop(); });
            }

            inp.addEventListener('input', function() {
                clearTimeout(timer);
                hidden.value = '';
                var q = inp.value.trim();
                if (q.length < 2) { closeDrop(); return; }
                timer = setTimeout(function() { search(q); }, 280);
            });

            inp.addEventListener('keydown', function(e) {
                var items = drop.querySelectorAll('.tuss-ac-item');
                if (!items.length) return;
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    activeIdx = Math.min(activeIdx + 1, items.length - 1);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    activeIdx = Math.max(activeIdx - 1, 0);
                } else if (e.key === 'Enter' && activeIdx >= 0) {
                    e.preventDefault();
                    items[activeIdx].dispatchEvent(new MouseEvent('mousedown'));
                    return;
                } else if (e.key === 'Escape') {
                    closeDrop(); return;
                } else { return; }
                items.forEach(function(el, i) { el.classList.toggle('active', i === activeIdx); });
                if (items[activeIdx]) items[activeIdx].scrollIntoView({block: 'nearest'});
            });

            inp.addEventListener('blur', function() { setTimeout(closeDrop, 150); });
        });
    }

    function rebuildJson() {
        var arr = [];
        document.querySelectorAll('#tussFieldsContainer .tuss-field').forEach(function(f) {
            arr.push({
                id_tuss:              (f.querySelector('[name$="[id_tuss]"]')              || {}).value || '',
                fk_int_tuss:          (f.querySelector('[name$="[fk_int_tuss]"]')          || {}).value || '',
                tuss_solicitado:      (f.querySelector('[name$="[tuss_solicitado]"]')      || {}).value || '',
                data_realizacao_tuss: (f.querySelector('[name$="[data_realizacao_tuss]"]') || {}).value || '',
                qtd_tuss_solicitado:  (f.querySelector('[name$="[qtd_tuss_solicitado]"]')  || {}).value || '',
                qtd_tuss_liberado:    (f.querySelector('[name$="[qtd_tuss_liberado]"]')    || {}).value || '',
                tuss_liberado_sn:     (f.querySelector('[name$="[tuss_liberado_sn]"]')     || {}).value || ''
            });
        });
        var el = document.getElementById('tuss_json');
        if (el) el.value = JSON.stringify(arr);
    }

    // Template para novas linhas: copia a primeira linha e zera valores
    function buildTemplate() {
        var first = document.querySelector('#tussFieldsContainer .tuss-field');
        if (!first) return null;
        var clone = first.cloneNode(true);
        clone.querySelectorAll('input:not([type="hidden"])').forEach(function(i) { i.value = ''; i.removeAttribute('data-ac-init'); });
        clone.querySelectorAll('input[type="hidden"].tuss-ac-val').forEach(function(i) { i.value = ''; });
        clone.querySelectorAll('select').forEach(function(s) { s.value = ''; });
        clone.querySelectorAll('.tuss-ac-drop').forEach(function(d) { d.innerHTML = ''; d.style.display = 'none'; });
        // Remove hidden fk_int_tuss / id_tuss (linha nova não tem FK)
        clone.querySelectorAll('[name$="[id_tuss]"], [name$="[fk_int_tuss]"]').forEach(function(el) { el.remove(); });
        return clone;
    }

    var container = document.getElementById('tussFieldsContainer');

    // Inicializa autocomplete nas linhas existentes
    initTussAutocomplete(container);
    rebuildJson();

    // Adicionar linha
    container.addEventListener('click', function(e) {
        if (!e.target.closest('.btn-add')) return;
        var idx = container.querySelectorAll('.tuss-field').length;
        var tpl = buildTemplate();
        if (!tpl) return;
        // Ajusta índices nos name: tuss[0][...] → tuss[N][...]
        tpl.querySelectorAll('[name]').forEach(function(el) {
            el.name = el.name.replace(/\[\d+\]/, '[' + idx + ']');
        });
        // Data padrão = hoje
        var dateIn = tpl.querySelector('[name$="[data_realizacao_tuss]"]');
        if (dateIn) dateIn.value = new Date().toISOString().slice(0, 10);

        container.appendChild(tpl);
        initTussAutocomplete(tpl);
        rebuildJson();
    });

    // Remover linha
    container.addEventListener('click', function(e) {
        if (!e.target.closest('.btn-remove')) return;
        if (container.querySelectorAll('.tuss-field').length > 1) {
            e.target.closest('.tuss-field').remove();
            rebuildJson();
        }
    });

    // Qualquer mudança nos inputs/selects
    container.addEventListener('input',  rebuildJson);
    container.addEventListener('change', rebuildJson);

    // Antes do submit
    var form = document.getElementById('myForm') || container.closest('form');
    if (form) form.addEventListener('submit', rebuildJson);
})();
</script>
