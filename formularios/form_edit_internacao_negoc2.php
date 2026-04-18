<?php

declare(strict_types=1);
error_reporting(E_ALL);
date_default_timezone_set('America/Sao_Paulo');

/*──────────────────── helpers universais ───────────────────*/
if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('sel')) {
    /** selected — case-insensitive, suporta null/int  */
    function sel($v, $exp): string
    {
        return strcasecmp((string) $v, (string) $exp) === 0 ? 'selected' : '';
    }
}
if (!function_exists('fmtDate')) {
    /** Converte vários formatos para YYYY-MM-DD (para <input type="date">) */
    function fmtDate($d): string
    {
        if (!$d)
            return '';
        if ($d instanceof DateTimeInterface)
            return $d->format('Y-m-d');
        if (preg_match('#^\d{2}/\d{2}/\d{4}$#', $d)) {
            $tmp = DateTime::createFromFormat('d/m/Y', $d);
            return $tmp ? $tmp->format('Y-m-d') : '';
        }
        if (preg_match('#^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$#', $d)) {
            return substr($d, 0, 10);
        }
        return (string) $d;
    }
}
if (!function_exists('getProp')) {
    function getProp($var, string $key): string
    {
        if (is_array($var) && isset($var[$key]))
            return (string) $var[$key];
        if (is_object($var) && isset($var->$key))
            return (string) $var->$key;
        return '';
    }
}

// Compatibilidade para PHP < 8.0
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

/*──────────────────── valida variáveis ─────────────────────*/
if (!isset($dados_acomodacao, $intern)) {
    exit('Variáveis necessárias não definidas');
}

$acomodacoesNegocList = !empty($acomodacoesNegoc) ? $acomodacoesNegoc : $dados_acomodacao;
$fallbackAcomodacoes = [
    ['id_acomodacao' => 0, 'acomodacao_aco' => 'UTI', 'valor_aco' => 0],
    ['id_acomodacao' => 0, 'acomodacao_aco' => 'Semi', 'valor_aco' => 0],
    ['id_acomodacao' => 0, 'acomodacao_aco' => 'Apto', 'valor_aco' => 0],
];

if (!function_exists('normalizeNegAcomodPhp')) {
    function normalizeNegAcomodPhp($value): string
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return '';
        }
        $parts = explode('-', $raw, 2);
        return mb_strtolower(trim($parts[1] ?? $parts[0]));
    }
}

if (!function_exists('negotiationDefaultsPhp')) {
    function negotiationDefaultsPhp(string $tipo): array
    {
        $tipo = mb_strtoupper(trim($tipo));
        if ($tipo === 'TROCA UTI/APTO') return ['troca_de' => 'UTI', 'troca_para' => 'Apto', 'use_alta' => true];
        if ($tipo === 'TROCA UTI/SEMI') return ['troca_de' => 'UTI', 'troca_para' => 'Semi', 'use_alta' => true];
        if ($tipo === 'TROCA SEMI/APTO') return ['troca_de' => 'Semi', 'troca_para' => 'Apto', 'use_alta' => true];
        if ($tipo === 'GLOSA UTI' || $tipo === 'TARDIA UTI') return ['troca_de' => 'UTI', 'troca_para' => 'UTI', 'use_alta' => false];
        if ($tipo === 'GLOSA SEMI') return ['troca_de' => 'Semi', 'troca_para' => 'Semi', 'use_alta' => false];
        if (in_array($tipo, ['GLOSA APTO', '1/2 DIARIA APTO', 'TARDIA APTO', 'DIARIA ADM'], true)) {
            return ['troca_de' => 'Apto', 'troca_para' => 'Apto', 'use_alta' => false];
        }
        return ['troca_de' => '', 'troca_para' => '', 'use_alta' => false];
    }
}

if (!function_exists('diffDaysNegotiationPhp')) {
    function diffDaysNegotiationPhp($inicio, $fim): int
    {
        $ini = fmtDate($inicio);
        $end = fmtDate($fim);
        if ($ini === '' || $end === '') {
            return 0;
        }
        try {
            $d1 = new DateTime($ini);
            $d2 = new DateTime($end);
            $diff = (int)$d1->diff($d2)->format('%r%a');
            return max(1, $diff);
        } catch (Throwable $e) {
            return 0;
        }
    }
}

$acomodValorMap = [];
$acomodJsList = [];
foreach (array_merge($acomodacoesNegocList, $fallbackAcomodacoes) as $acItem) {
    if (is_object($acItem)) {
        $id = (int)($acItem->id_acomodacao ?? 0);
        $nome = trim((string)($acItem->acomodacao_aco ?? ''));
        $valor = (float)($acItem->valor_aco ?? 0);
    } elseif (is_array($acItem)) {
        $id = (int)($acItem['id_acomodacao'] ?? 0);
        $nome = trim((string)($acItem['acomodacao_aco'] ?? ''));
        $valor = (float)($acItem['valor_aco'] ?? 0);
    } else {
        $id = 0;
        $nome = trim((string)$acItem);
        $valor = 0.0;
    }
    $key = normalizeNegAcomodPhp($nome);
    if ($key !== '') {
        if (!array_key_exists($key, $acomodValorMap)) {
            $acomodValorMap[$key] = $valor;
            $acomodJsList[] = [
                'id_acomodacao' => $id,
                'acomodacao_aco' => $nome,
                'valor_aco' => $valor,
            ];
        }
    }
}
$fcNegValMap = [
    'UTI' => 0.0,
    'Semi' => 0.0,
    'Apto' => 0.0,
];
foreach ($acomodJsList as $item) {
    $nomeCanon = mb_strtolower(trim((string)($item['acomodacao_aco'] ?? '')));
    $valorCanon = (float)($item['valor_aco'] ?? 0);

    if ($fcNegValMap['UTI'] <= 0 && $nomeCanon === 'uti') {
        $fcNegValMap['UTI'] = $valorCanon;
    }
    if ($fcNegValMap['Semi'] <= 0 && $nomeCanon === 'semi') {
        $fcNegValMap['Semi'] = $valorCanon;
    }
    if ($fcNegValMap['Apto'] <= 0 && ($nomeCanon === 'apto' || $nomeCanon === 'apartamento')) {
        $fcNegValMap['Apto'] = $valorCanon;
    }
}
foreach ($acomodJsList as $item) {
    $nomeCanon = mb_strtolower(trim((string)($item['acomodacao_aco'] ?? '')));
    $valorCanon = (float)($item['valor_aco'] ?? 0);
    if ($fcNegValMap['UTI'] <= 0 && strpos($nomeCanon, 'uti') !== false) {
        $fcNegValMap['UTI'] = $valorCanon;
    }
    if ($fcNegValMap['Semi'] <= 0 && strpos($nomeCanon, 'semi') !== false) {
        $fcNegValMap['Semi'] = $valorCanon;
    }
    if ($fcNegValMap['Apto'] <= 0 && (
        strpos($nomeCanon, 'apto') !== false ||
        strpos($nomeCanon, 'apart') !== false ||
        strpos($nomeCanon, 'enferm') !== false
    )) {
        $fcNegValMap['Apto'] = $valorCanon;
    }
}
$acomodacoesNegocRenderList = $acomodJsList;

/*──────────────────── normaliza negociações ─────────────────*/
/* ───────── normaliza NEGOCIAÇÕES + limpa id-hífen ───────── */
$negociacoesInt = array_map(static function ($n) {
    $n = (array) $n;                           // garante array

    // Se o valor tem hífen (ex.: 3-UTI), mantém só o que vem depois
    foreach (['troca_de', 'troca_para'] as $campo) {
        if (!empty($n[$campo]) && str_contains($n[$campo], '-')) {
            [$id, $nome] = explode('-', $n[$campo], 2);
            $n[$campo] = trim($nome);        // fica só “UTI”, “Apto” etc.
        }
    }
    return $n;
}, $negociacoesInt ?? []);

if (!$negociacoesInt) {
    $negociacoesInt[] = [
        'tipo_negociacao' => '',
        'data_inicio_neg' => '',
        'data_fim_neg' => '',
        'troca_de' => '',
        'troca_para' => '',
        'qtd' => '',
        'saving' => '',
    ];
}


/*──────────────────── funções de <option> ──────────────────*/
if (!function_exists('optionsTipoNegociacao')) {
    function optionsTipoNegociacao(string $sel = ''): string
    {
        $tipos = [
            'TROCA UTI/APTO',
            'TROCA UTI/SEMI',
            'TROCA SEMI/APTO',
            'VESPERA',
            'GLOSA UTI',
            'GLOSA APTO',
            'GLOSA SEMI',
            '1/2 DIARIA APTO',
            'TARDIA APTO',
            'TARDIA UTI',
            'DIARIA ADM'
        ];
        $html = '<option value="">Selecione</option>';
        foreach ($tipos as $t) {
            $html .= '<option value="' . h($t) . '" ' . sel($t, $sel) . '>' . h($t) . '</option>';
        }
        return $html;
    }
}
if (!function_exists('optionsAcomod')) {
    function optionsAcomod(array $acoms, string $sel = ''): string
    {
        $html = '<option value=""></option>';
        $selNorm = trim((string)$sel);

        foreach ($acoms as $ac) {
            if (is_object($ac)) {
                $value = trim((string)($ac->acomodacao_aco ?? ''));
                $display = $value;
                $valorDia = (float)($ac->valor_aco ?? 0);
            } elseif (is_array($ac)) {
                $value = trim((string)($ac['acomodacao_aco'] ?? ''));
                $display = $value;
                $valorDia = (float)($ac['valor_aco'] ?? 0);
            } else {
                $value = trim((string)$ac);
                [$id, $nome] = array_pad(explode('-', $value, 2), 2, '');
                $display = trim($nome) !== '' ? trim($nome) : $value;
                $valorDia = 0.0;
            }
            if ($value === '') {
                continue;
            }
            $selected = '';
            if (strcasecmp($value, $selNorm) === 0 || strcasecmp($display, $selNorm) === 0) {
                $selected = ' selected';
            }

            $html .= '<option value="' . h($value) . '" data-valor="' . h((string)$valorDia) . '"' . $selected . '>'
                . h($display) . '</option>';
        }

        if ($html === '<option value=""></option>' && $selNorm !== '') {
            $html .= '<option value="' . h($selNorm) . '" selected>' . h($selNorm) . '</option>';
        }
        return $html;
    }
}

if (!function_exists('sel')) {
    /**
     * Retorna 'selected'
     * - ignora maiúsc./minúsc.
     * - desconsidera espaços antes/depois
     */
    function sel($v, $exp): string
    {
        return strcasecmp(trim((string) $v), trim((string) $exp)) === 0
            ? 'selected' : '';
    }
}

?>
<style>
    .negoc-row {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 12px;
        background: #f5f5f9
    }

    .negoc-row label {
        font-weight: 600;
        font-size: .9rem;
        margin-bottom: 4px
    }

    .negoc-row .form-control {
        font-size: .9rem;
        padding: 4px 6px
    }

    .negoc-row .btn {
        font-size: .85rem;
        padding: 4px 8px
    }

    .negoc-row__actions {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 25px;
    }

    .btn-trash-negoc {
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        border: 1px solid rgba(180, 35, 24, 0.18);
        background: linear-gradient(180deg, #fff7f7 0%, #ffe9eb 100%);
        color: #b42318;
        font-size: 15px;
        line-height: 1;
        cursor: pointer;
        transition: transform 120ms ease, background-color 120ms ease, border-color 120ms ease, box-shadow 120ms ease;
    }

    .btn-trash-negoc:hover {
        background: linear-gradient(180deg, #ffecef 0%, #ffd9de 100%);
        color: #912018;
        border-color: rgba(145, 32, 24, 0.28);
        transform: translateY(-1px);
    }

    .neg-trash-check {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .neg-trash-check:checked + .btn-trash-negoc {
        background: #dc3545;
        color: #fff;
        border-color: #dc3545;
        box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.14);
        transform: none;
    }

    .titulo-abas {
        background: #0d6efd;
        padding: 6px 10px;
        border-radius: 4px 4px 0 0;
        margin-bottom: 6px
    }

    .titulo-abas h7 {
        color: #fff;
        margin: 0
    }

    #container-negoc {

    }
</style>

<script>
    window.fcNegValMap = <?= json_encode($fcNegValMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.fcFillNegotiationRowCore = function(selectEl) {
        function findRow(el) {
            while (el && (!el.className || String(el.className).indexOf('negociation-field-container') === -1)) {
                el = el.parentNode;
            }
            return el;
        }
        function getField(row, name) {
            return row ? row.querySelector('[name="' + name + '"]') : null;
        }
        function getValor(label) {
            var wanted = (label || '').toString().trim().toLowerCase();
            var list = window.__NEGOC_ACOMODACOES || [];
            for (var j = 0; j < list.length; j++) {
                var exactItem = list[j] || {};
                var exactNome = ((exactItem.acomodacao_aco || '') + '').trim().toLowerCase();
                if (exactNome === wanted) return parseFloat(exactItem.valor_aco || 0) || 0;
                if (wanted === 'apto' && (exactNome === 'apartamento' || exactNome === 'apto')) {
                    return parseFloat(exactItem.valor_aco || 0) || 0;
                }
            }
            for (var i = 0; i < list.length; i++) {
                var item = list[i] || {};
                var nome = ((item.acomodacao_aco || '') + '').trim().toLowerCase();
                if (nome === wanted) return parseFloat(item.valor_aco || 0) || 0;
                if (wanted === 'apto' && (nome.indexOf('apart') !== -1 || nome.indexOf('apto') !== -1 || nome.indexOf('enferm') !== -1)) {
                    return parseFloat(item.valor_aco || 0) || 0;
                }
                if (wanted === 'semi' && nome.indexOf('semi') !== -1) {
                    return parseFloat(item.valor_aco || 0) || 0;
                }
                if (wanted === 'uti' && nome === 'uti') {
                    return parseFloat(item.valor_aco || 0) || 0;
                }
                if (wanted === 'uti' && nome.indexOf('uti') !== -1) {
                    return parseFloat(item.valor_aco || 0) || 0;
                }
            }
            var values = window.fcNegValMap || {};
            return parseFloat(values[label] || 0) || 0;
        }
        function buildSelect(select, chosenLabel) {
            if (!select) return;
            var chosen = (chosenLabel || '').toString();
            var labels = ['UTI', 'Semi', 'Apto'];
            select.innerHTML = '<option value=""></option>';
            for (var i = 0; i < labels.length; i++) {
                var label = labels[i];
                var option = document.createElement('option');
                option.value = label;
                option.text = label;
                option.setAttribute('data-valor', String(getValor(label)));
                if (label === chosen) option.selected = true;
                select.appendChild(option);
            }
        }
        function diffDays(startValue, endValue) {
            if (!startValue || !endValue) return 1;
            var d1 = new Date(startValue + 'T00:00:00');
            var d2 = new Date(endValue + 'T00:00:00');
            var diff = Math.round((d2.getTime() - d1.getTime()) / 86400000);
            return diff >= 0 ? Math.max(1, diff) : 1;
        }
        function recalcRow(row) {
            var tipoLocalEl = getField(row, 'tipo_negociacao');
            var trocaDeLocalEl = getField(row, 'troca_de');
            var trocaParaLocalEl = getField(row, 'troca_para');
            var inicioLocalEl = getField(row, 'data_inicio_neg');
            var fimLocalEl = getField(row, 'data_fim_neg');
            var qtdLocalEl = getField(row, 'qtd');
            var savingLocalEl = getField(row, 'saving');
            var savingShowLocalEl = getField(row, 'saving_show');
            var tipoLocal = ((tipoLocalEl && tipoLocalEl.value) || '').toString().toUpperCase().trim();
            var qtdLocal = diffDays(inicioLocalEl ? inicioLocalEl.value : '', fimLocalEl ? fimLocalEl.value : '');
            if (qtdLocalEl) qtdLocalEl.value = String(qtdLocal);
            var deLabel = trocaDeLocalEl && trocaDeLocalEl.selectedOptions && trocaDeLocalEl.selectedOptions[0]
                ? (trocaDeLocalEl.selectedOptions[0].textContent || trocaDeLocalEl.value || '')
                : (trocaDeLocalEl ? trocaDeLocalEl.value || '' : '');
            var paraLabel = trocaParaLocalEl && trocaParaLocalEl.selectedOptions && trocaParaLocalEl.selectedOptions[0]
                ? (trocaParaLocalEl.selectedOptions[0].textContent || trocaParaLocalEl.value || '')
                : (trocaParaLocalEl ? trocaParaLocalEl.value || '' : '');
            var deLocal = getValor(deLabel);
            var paraLocal = getValor(paraLabel);
            var savingLocal = 0;
            if (tipoLocal.indexOf('TROCA') === 0) savingLocal = (deLocal - paraLocal) * qtdLocal;
            else if (tipoLocal.indexOf('1/2 DIARIA') !== -1) savingLocal = (deLocal / 2) * qtdLocal;
            else savingLocal = deLocal * qtdLocal;
            if (savingLocalEl) savingLocalEl.value = savingLocal.toFixed(2);
            if (savingShowLocalEl) {
                savingShowLocalEl.value = 'R$ ' + Math.abs(savingLocal).toFixed(2);
                savingShowLocalEl.style.color = savingLocal >= 0 ? 'green' : 'red';
            }
        }

        var row = findRow(selectEl);
        if (!row) return;

        var tipo = ((selectEl && selectEl.value) || '').toString().toUpperCase().trim();
        var trocaDe = '';
        var trocaPara = '';
        var useAlta = false;
        if (tipo === 'TROCA UTI/APTO') { trocaDe = 'UTI'; trocaPara = 'Apto'; useAlta = true; }
        else if (tipo === 'TROCA UTI/SEMI') { trocaDe = 'UTI'; trocaPara = 'Semi'; useAlta = true; }
        else if (tipo === 'TROCA SEMI/APTO') { trocaDe = 'Semi'; trocaPara = 'Apto'; useAlta = true; }
        else if (tipo === 'GLOSA UTI' || tipo === 'TARDIA UTI') { trocaDe = 'UTI'; trocaPara = 'UTI'; useAlta = false; }
        else if (tipo === 'GLOSA SEMI') { trocaDe = 'Semi'; trocaPara = 'Semi'; useAlta = false; }
        else if (tipo === 'GLOSA APTO' || tipo === '1/2 DIARIA APTO' || tipo === 'TARDIA APTO' || tipo === 'DIARIA ADM') {
            trocaDe = 'Apto'; trocaPara = 'Apto'; useAlta = false;
        }

        var trocaDeEl = getField(row, 'troca_de');
        var trocaParaEl = getField(row, 'troca_para');
        var inicioEl = getField(row, 'data_inicio_neg');
        var fimEl = getField(row, 'data_fim_neg');
        var qtdEl = getField(row, 'qtd');
        var savingEl = getField(row, 'saving');
        var savingShowEl = getField(row, 'saving_show');
        var internInput = document.getElementById('data_intern_int');
        var altaInput = document.getElementById('data_alta_alt');
        var internDate = internInput && internInput.value ? internInput.value : '';
        var altaDate = altaInput && altaInput.value ? altaInput.value.slice(0, 10) : '';

        buildSelect(trocaDeEl, trocaDe);
        buildSelect(trocaParaEl, trocaPara);

        if (inicioEl && internDate) inicioEl.value = internDate;
        if (fimEl) fimEl.value = (useAlta && altaDate) ? altaDate : (inicioEl && inicioEl.value ? inicioEl.value : internDate);

        recalcRow(row);

        if (inicioEl && !inicioEl.dataset.fcBound) {
            inicioEl.dataset.fcBound = '1';
            inicioEl.onchange = function() { recalcRow(row); };
        }
        if (fimEl && !fimEl.dataset.fcBound) {
            fimEl.dataset.fcBound = '1';
            fimEl.onchange = function() { recalcRow(row); };
        }
        if (trocaDeEl && !trocaDeEl.dataset.fcBound) {
            trocaDeEl.dataset.fcBound = '1';
            trocaDeEl.onchange = function() { recalcRow(row); };
        }
        if (trocaParaEl && !trocaParaEl.dataset.fcBound) {
            trocaParaEl.dataset.fcBound = '1';
            trocaParaEl.onchange = function() { recalcRow(row); };
        }
    };
</script>

<div>
    <h4 class="mb-3">Editar Negociação</h4>


    <!-- garante que cai no fluxo UPDATE/CREATE -->
    <input type="hidden" name="type" value="update_editar">

    <!-- toggle que ativa/desativa o bloco de negociações -->
    <input type="hidden" name="select_negoc" id="select_negoc" value="s">

    <!-- aqui cai o JSON montado pelo JS -->
    <!-- chaves principais -->
    <input type="hidden" name="type" value="edit_negociacao">
    <input type="hidden" id="fk_id_int" value="<?= h(getProp($intern, 'id_internacao')) ?>">
    <input type="hidden" id="fk_usuario_neg" value="<?= h($_SESSION['id_usuario'] ?? '') ?>">
    <input type="hidden" id="negociacoes_json" name="negociacoes_json">
    <input type="hidden" id="negociacoes_delete_ids" name="negociacoes_delete_ids" value="[]">


    <div id="negotiationFieldsContainer" >
        <?php foreach ($negociacoesInt as $neg): ?>
            <?php
            $tipoNeg = trim((string)($neg['tipo_negociacao'] ?? ''));
            $defaultsNeg = negotiationDefaultsPhp($tipoNeg);
            $dataInicioNeg = fmtDate($neg['data_inicio_neg'] ?? $neg['data_inicio_negoc'] ?? '');
            $dataFimNeg = fmtDate($neg['data_fim_neg'] ?? $neg['data_fim_negoc'] ?? '');
            $trocaDeNeg = trim((string)($neg['troca_de'] ?? ''));
            $trocaParaNeg = trim((string)($neg['troca_para'] ?? ''));

            if ($trocaDeNeg === '' && $defaultsNeg['troca_de'] !== '') {
                $trocaDeNeg = $defaultsNeg['troca_de'];
            }
            if ($trocaParaNeg === '' && $defaultsNeg['troca_para'] !== '') {
                $trocaParaNeg = $defaultsNeg['troca_para'];
            }

            if ($dataInicioNeg === '') {
                $dataInicioNeg = fmtDate($intern['data_intern_int'] ?? '');
            }
            if ($dataFimNeg === '' && !empty($defaultsNeg['use_alta'])) {
                $dataFimNeg = fmtDate(substr((string)($altaAtual['data_alta_alt'] ?? ''), 0, 10));
            }
            if ($dataFimNeg === '') {
                $dataFimNeg = $dataInicioNeg;
            }

            $qtdNeg = (int)($neg['qtd'] ?? 0);
            if ($qtdNeg <= 0) {
                $qtdNeg = diffDaysNegotiationPhp($dataInicioNeg, $dataFimNeg);
            }

            $savingNeg = (string)($neg['saving'] ?? '');
            if ($tipoNeg !== '') {
                $deValor = (float)($acomodValorMap[normalizeNegAcomodPhp($trocaDeNeg)] ?? 0);
                $paraValor = (float)($acomodValorMap[normalizeNegAcomodPhp($trocaParaNeg)] ?? 0);
                $tipoUpper = mb_strtoupper($tipoNeg);
                if (strpos($tipoUpper, 'TROCA') === 0) {
                    $savingNeg = number_format(($deValor - $paraValor) * max(0, $qtdNeg), 2, '.', '');
                } elseif (strpos($tipoUpper, '1/2 DIARIA') !== false) {
                    $savingNeg = number_format(($deValor / 2) * max(0, $qtdNeg), 2, '.', '');
                } else {
                    $savingNeg = number_format($deValor * max(0, $qtdNeg), 2, '.', '');
                }
            }
            ?>
            <div  class="negociation-field-container negoc-row">
                <input type="hidden" name="neg_id" value="<?= h($neg['id_negociacao'] ?? '') ?>">
                <div class="form-group col-sm-2">
                    <label>
                        Tipo Negociação
                        <span class="assist-anchor" data-assist-key="negociacao_tipo"></span>
                    </label>
                    <select name="tipo_negociacao" class="form-control" onchange="fcFillNegotiationRowCore(this)">
                        <?= optionsTipoNegociacao($neg['tipo_negociacao'] ?? '') ?>
                    </select>
                </div>

                <div class="form-group col-sm-1">
                    <label>Data inicial</label>
                    <input type="date" name="data_inicio_neg" class="form-control"
                        value="<?= h($dataInicioNeg) ?>">
                </div>

                <div class="form-group col-sm-1">
                    <label>Data final</label>
                    <input type="date" name="data_fim_neg" class="form-control"
                        value="<?= h($dataFimNeg) ?>">
                </div>

                <div class="form-group col-sm-2">
                    <?php /* DEBUG */
                    echo '<!-- valor banco: [', h($neg['troca_de'] ?? ''), '] -->';
                    ?>

                    <label>Acomod. Solicitada</label>
                    <select name="troca_de" class="form-control" data-current="<?= h($trocaDeNeg) ?>">
                        <?= optionsAcomod($acomodacoesNegocRenderList, $trocaDeNeg) ?>
                    </select>
                </div>

                <div class="form-group col-sm-2">
                    <label>Acomod. Liberada</label>
                    <select name="troca_para" class="form-control" data-current="<?= h($trocaParaNeg) ?>">
                        <?= optionsAcomod($acomodacoesNegocRenderList, $trocaParaNeg) ?>
                    </select>
                </div>

                <div class="form-group col-sm-1">
                    <label>Quantidade</label>
                    <input type="number" name="qtd" class="form-control" min="1" max="30"
                        value="<?= h((string)$qtdNeg) ?>">
                </div>

                <div class="form-group col-sm-1">
                    <label>Saving</label>
                    <input type="text" name="saving_show" class="form-control" readonly
                        value="<?= $savingNeg !== '' ? 'R$ ' . number_format((float) $savingNeg, 2, ',', '.') : '' ?>">
                    <input type="hidden" name="saving" value="<?= h($savingNeg) ?>">
                </div>
                <div class="form-group col-md-1 mb-2">
                    <div class="negoc-row__actions">
                    <button type="button" class="btn btn-success btn-sm btn-add-negoc" title="Adicionar negociação" onclick="return (function(btn){var tpl=document.getElementById('negotiationRowTemplate');var row=btn.closest('.negociation-field-container');if(!row){return false;}var newRow=(tpl&&tpl.content&&tpl.content.firstElementChild)?tpl.content.firstElementChild.cloneNode(true):row.cloneNode(true);newRow.querySelectorAll('.neg-trash-check, .btn-trash-negoc').forEach(function(el){el.remove();});newRow.querySelectorAll('input, select').forEach(function(el){if(el.name==='neg_id'||el.name==='saving'||el.name==='saving_show'||el.name==='data_inicio_neg'||el.name==='data_fim_neg'||el.name==='qtd'){el.value='';}if(el.tagName==='SELECT'){el.selectedIndex=0;}if(el.type==='checkbox'){el.checked=false;}});row.parentNode.insertBefore(newRow,row.nextSibling);return false;})(this);">+</button>
                    <button type="button" class="btn btn-danger btn-sm btn-del-negoc" title="Remover negociação" onclick="return (function(btn){var row=btn.closest('.negociation-field-container');if(!row){return false;}var rows=document.querySelectorAll('#negotiationFieldsContainer .negociation-field-container');if(rows.length>1){row.remove();return false;}row.querySelectorAll('input, select').forEach(function(el){if(el.type==='checkbox'){el.checked=false;return;}if(el.tagName==='SELECT'){el.selectedIndex=0;return;}el.value='';});return false;})(this);">−</button>
                    <?php if (!empty($neg['id_negociacao'])): ?>
                        <input
                            type="checkbox"
                            id="neg-delete-<?= h((string)($neg['id_negociacao'] ?? '')) ?>"
                            name="neg_delete_ids[]"
                            value="<?= h((string)($neg['id_negociacao'] ?? '')) ?>"
                            class="neg-trash-check">
                        <label
                            for="neg-delete-<?= h((string)($neg['id_negociacao'] ?? '')) ?>"
                            class="btn-trash-negoc"
                            title="Marcar negociação para exclusão"
                            aria-label="Marcar negociação para exclusão">
                            <i class="fas fa-trash-alt" aria-hidden="true"></i>
                        </label>
                    <?php endif; ?>
                    </div>
                </div>

            </div>
        <?php endforeach; ?>
    </div>
    <template id="negotiationRowTemplate">
        <div class="negociation-field-container negoc-row">
            <input type="hidden" name="neg_id" value="">
            <div class="form-group col-sm-2">
                <label>
                    Tipo Negociação
                    <span class="assist-anchor" data-assist-key="negociacao_tipo"></span>
                </label>
                <select name="tipo_negociacao" class="form-control" onchange="fcFillNegotiationRowCore(this)">
                    <?= optionsTipoNegociacao('') ?>
                </select>
            </div>
            <div class="form-group col-sm-1">
                <label>Data inicial</label>
                <input type="date" name="data_inicio_neg" class="form-control" value="">
            </div>
            <div class="form-group col-sm-1">
                <label>Data final</label>
                <input type="date" name="data_fim_neg" class="form-control" value="">
            </div>
            <div class="form-group col-sm-2">
                <label>Acomod. Solicitada</label>
                <select name="troca_de" class="form-control" data-current="">
                    <?= optionsAcomod($acomodacoesNegocRenderList, '') ?>
                </select>
            </div>
            <div class="form-group col-sm-2">
                <label>Acomod. Liberada</label>
                <select name="troca_para" class="form-control" data-current="">
                    <?= optionsAcomod($acomodacoesNegocRenderList, '') ?>
                </select>
            </div>
            <div class="form-group col-sm-1">
                <label>Quantidade</label>
                <input type="number" name="qtd" class="form-control" min="1" max="30" value="">
            </div>
            <div class="form-group col-sm-1">
                <label>Saving</label>
                <input type="text" name="saving_show" class="form-control" readonly value="">
                <input type="hidden" name="saving" value="">
            </div>
            <div class="form-group col-md-1 mb-2">
                <div class="negoc-row__actions">
                    <button type="button" class="btn btn-success btn-sm btn-add-negoc" title="Adicionar negociação" onclick="return (function(btn){var tpl=document.getElementById('negotiationRowTemplate');var row=btn.closest('.negociation-field-container');if(!row){return false;}var newRow=(tpl&&tpl.content&&tpl.content.firstElementChild)?tpl.content.firstElementChild.cloneNode(true):row.cloneNode(true);newRow.querySelectorAll('.neg-trash-check, .btn-trash-negoc').forEach(function(el){el.remove();});newRow.querySelectorAll('input, select').forEach(function(el){if(el.name==='neg_id'||el.name==='saving'||el.name==='saving_show'||el.name==='data_inicio_neg'||el.name==='data_fim_neg'||el.name==='qtd'){el.value='';}if(el.tagName==='SELECT'){el.selectedIndex=0;}if(el.type==='checkbox'){el.checked=false;}});row.parentNode.insertBefore(newRow,row.nextSibling);return false;})(this);">+</button>
                    <button type="button" class="btn btn-danger btn-sm btn-del-negoc" title="Remover negociação" onclick="return (function(btn){var row=btn.closest('.negociation-field-container');if(!row){return false;}var rows=document.querySelectorAll('#negotiationFieldsContainer .negociation-field-container');if(rows.length>1){row.remove();return false;}row.querySelectorAll('input, select').forEach(function(el){if(el.type==='checkbox'){el.checked=false;return;}if(el.tagName==='SELECT'){el.selectedIndex=0;return;}el.value='';});return false;})(this);">−</button>
                </div>
            </div>
        </div>
    </template>
    <hr>
</div>

<script>
    window.__NEGOC_ACOMODACOES = <?= json_encode($acomodJsList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.__NEGOC_ACOMODACOES = Array.isArray(window.__NEGOC_ACOMODACOES) ? window.__NEGOC_ACOMODACOES : [];
    window.__NEGOC_DELETE_IDS = Array.isArray(window.__NEGOC_DELETE_IDS) ? window.__NEGOC_DELETE_IDS : [];
    ['UTI', 'Semi', 'Apto'].forEach(function(label) {
        var exists = window.__NEGOC_ACOMODACOES.some(function(item) {
            return normalizeNegValue((item && item.acomodacao_aco) || '') === normalizeNegValue(label);
        });
        if (!exists) {
            window.__NEGOC_ACOMODACOES.push({ id_acomodacao: 0, acomodacao_aco: label, valor_aco: 0 });
        }
    });

    function normalizeNegValue(v) {
        const raw = (v || '').toString().trim();
        if (!raw) return '';
        const parts = raw.split('-');
        return (parts.length > 1 ? parts.slice(1).join('-') : raw).trim().toLowerCase();
    }

    function safeNum(n) {
        const v = parseFloat(n);
        return isFinite(v) ? v : 0;
    }

    function getRowElement(el) {
        return el ? el.closest('.negociation-field-container') : null;
    }

    function getField(row, name) {
        return row ? row.querySelector(`[name="${name}"]`) : null;
    }

    function ensureBaseOptionsNative(select) {
        if (!select) return;
        const hasRealOptions = Array.from(select.options || []).some(function(opt) {
            return ((opt.value || '').toString().trim() !== '');
        });
        if (hasRealOptions) return;

        select.innerHTML = '<option value=""></option>';
        (window.__NEGOC_ACOMODACOES || []).forEach(function(item) {
            const label = ((item && item.acomodacao_aco) || '').toString().trim();
            if (!label) return;
            const option = document.createElement('option');
            option.value = item && parseInt(item.id_acomodacao || 0, 10) > 0
                ? `${item.id_acomodacao}-${label}`
                : label;
            option.textContent = label;
            option.dataset.valor = safeNum((item && item.valor_aco) || 0);
            select.appendChild(option);
        });
    }

    function ensureSelectOptionNative(select, label) {
        if (!select) return '';
        const wanted = (label || '').toString().trim();
        if (!wanted) return '';
        ensureBaseOptionsNative(select);

        const wantedNorm = normalizeNegValue(wanted);
        let found = Array.from(select.options || []).find(function(opt) {
            return normalizeNegValue(opt.value) === wantedNorm || normalizeNegValue(opt.textContent) === wantedNorm;
        });

        if (!found) {
            const meta = findAcomodMeta(wanted);
            found = document.createElement('option');
            found.value = meta && parseInt(meta.id_acomodacao || 0, 10) > 0
                ? `${meta.id_acomodacao}-${meta.acomodacao_aco}`
                : wanted;
            found.textContent = wanted;
            found.dataset.valor = safeNum((meta && meta.valor_aco) || 0);
            select.appendChild(found);
        }

        select.value = found.value;
        select.dataset.current = wanted;
        return found.value;
    }

    function calcSavingNative(row) {
        const tipoEl = getField(row, 'tipo_negociacao');
        const trocaDeEl = getField(row, 'troca_de');
        const trocaParaEl = getField(row, 'troca_para');
        const qtdEl = getField(row, 'qtd');
        const savingEl = getField(row, 'saving');
        const savingShowEl = getField(row, 'saving_show');
        if (!tipoEl || !trocaDeEl || !trocaParaEl || !qtdEl || !savingEl || !savingShowEl) return;

        const tipo = (tipoEl.value || '').toString().trim().toUpperCase();
        const trocaDeSelected = trocaDeEl.selectedOptions && trocaDeEl.selectedOptions[0] ? trocaDeEl.selectedOptions[0] : null;
        const trocaParaSelected = trocaParaEl.selectedOptions && trocaParaEl.selectedOptions[0] ? trocaParaEl.selectedOptions[0] : null;
        const deLabel = trocaDeSelected
            ? ((trocaDeSelected.textContent || trocaDeSelected.value || '').toString().trim())
            : ((trocaDeEl.value || '').toString().trim());
        const paraLabel = trocaParaSelected
            ? ((trocaParaSelected.textContent || trocaParaSelected.value || '').toString().trim())
            : ((trocaParaEl.value || '').toString().trim());
        const de = resolveNegotiationValueByLabel(deLabel);
        const para = resolveNegotiationValueByLabel(paraLabel);
        const qtd = parseInt(qtdEl.value, 10) || 0;
        let s = 0;
        if (tipo.startsWith('TROCA')) s = (de - para) * qtd;
        else if (tipo.includes('1/2 DIARIA')) s = qtd * (de / 2);
        else s = qtd * de;

        savingEl.value = s.toFixed(2);
        savingShowEl.value = `R$ ${Math.abs(s).toFixed(2)}`;
        savingShowEl.style.color = s >= 0 ? 'green' : 'red';
    }

    function applyNegotiationDefaultsNative(row, forceDates) {
        const tipoEl = getField(row, 'tipo_negociacao');
        if (!tipoEl || !tipoEl.value) return;
        const negIdEl = getField(row, 'neg_id');
        const hasSavedRecord = !!((negIdEl && negIdEl.value) || '').toString().trim();
        const defaults = negotiationTypeDefaults(tipoEl.value);
        const trocaDeEl = getField(row, 'troca_de');
        const trocaParaEl = getField(row, 'troca_para');
        const inicioEl = getField(row, 'data_inicio_neg');
        const fimEl = getField(row, 'data_fim_neg');
        const qtdEl = getField(row, 'qtd');
        const internInput = document.getElementById('data_intern_int');
        const altaInput = document.getElementById('data_alta_alt');
        const internDate = ((internInput && internInput.value) || '').toString().trim();
        const altaDateTime = ((altaInput && altaInput.value) || '').toString().trim();
        const altaDate = altaDateTime ? altaDateTime.slice(0, 10) : '';

        if (defaults.trocaDe && (!hasSavedRecord || forceDates || !(trocaDeEl && trocaDeEl.value))) {
            ensureSelectOptionNative(trocaDeEl, defaults.trocaDe);
        }
        if (defaults.trocaPara && (!hasSavedRecord || forceDates || !(trocaParaEl && trocaParaEl.value))) {
            ensureSelectOptionNative(trocaParaEl, defaults.trocaPara);
        }

        if (inicioEl && (forceDates || !hasSavedRecord || !inicioEl.value) && internDate) {
            inicioEl.value = internDate;
        }
        if (fimEl && (forceDates || !hasSavedRecord || !fimEl.value)) {
            fimEl.value = defaults.useAlta && altaDate ? altaDate : ((inicioEl && inicioEl.value) || internDate || '');
        }
        if (qtdEl && (!hasSavedRecord || forceDates || !qtdEl.value)) {
            const qtdCalc = diffDaysForNegotiation(
                (inicioEl && inicioEl.value) || '',
                (fimEl && fimEl.value) || ''
            );
            if (qtdCalc > 0) qtdEl.value = qtdCalc;
        }
    }

    window.fcFillNegotiationRow = function(selectEl) {
        var row = getRowElement(selectEl);
        if (!row) return;

        var tipo = ((selectEl && selectEl.value) || '').toString().trim().toUpperCase();
        var trocaDeEl = getField(row, 'troca_de');
        var trocaParaEl = getField(row, 'troca_para');
        var inicioEl = getField(row, 'data_inicio_neg');
        var fimEl = getField(row, 'data_fim_neg');
        var qtdEl = getField(row, 'qtd');
        var savingEl = getField(row, 'saving');
        var savingShowEl = getField(row, 'saving_show');
        var internInput = document.getElementById('data_intern_int');
        var altaInput = document.getElementById('data_alta_alt');
        var internDate = ((internInput && internInput.value) || '').toString().trim();
        var altaDateTime = ((altaInput && altaInput.value) || '').toString().trim();
        var altaDate = altaDateTime ? altaDateTime.slice(0, 10) : '';
        var trocaDe = '';
        var trocaPara = '';
        var useAlta = false;

        if (tipo === 'TROCA UTI/APTO') { trocaDe = 'UTI'; trocaPara = 'Apto'; useAlta = true; }
        else if (tipo === 'TROCA UTI/SEMI') { trocaDe = 'UTI'; trocaPara = 'Semi'; useAlta = true; }
        else if (tipo === 'TROCA SEMI/APTO') { trocaDe = 'Semi'; trocaPara = 'Apto'; useAlta = true; }
        else if (tipo === 'GLOSA UTI' || tipo === 'TARDIA UTI') { trocaDe = 'UTI'; trocaPara = 'UTI'; useAlta = false; }
        else if (tipo === 'GLOSA SEMI') { trocaDe = 'Semi'; trocaPara = 'Semi'; useAlta = false; }
        else if (tipo === 'GLOSA APTO' || tipo === '1/2 DIARIA APTO' || tipo === 'TARDIA APTO' || tipo === 'DIARIA ADM') {
            trocaDe = 'Apto'; trocaPara = 'Apto'; useAlta = false;
        }

        function buildOptions(select, selectedLabel) {
            if (!select) return;
            var selectedNorm = normalizeNegValue(selectedLabel || '');
            var base = ['UTI', 'Semi', 'Apto'];
            var extra = (window.__NEGOC_ACOMODACOES || []).map(function(item) {
                return ((item && item.acomodacao_aco) || '').toString().trim();
            }).filter(Boolean);
            var labels = [];
            base.concat(extra).forEach(function(label) {
                var norm = normalizeNegValue(label);
                if (!norm) return;
                if (labels.some(function(existing) { return normalizeNegValue(existing) === norm; })) return;
                labels.push(label);
            });

            select.innerHTML = '<option value=""></option>';
            labels.forEach(function(label) {
                var meta = findAcomodMeta(label);
                var valor = safeNum(meta && meta.valor_aco ? meta.valor_aco : 0);
                var value = meta && parseInt(meta.id_acomodacao || 0, 10) > 0
                    ? String(meta.id_acomodacao) + '-' + label
                    : label;
                var option = document.createElement('option');
                option.value = value;
                option.textContent = label;
                option.dataset.valor = valor;
                if (normalizeNegValue(label) === selectedNorm) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
            select.dataset.current = selectedLabel || '';
        }

        buildOptions(trocaDeEl, trocaDe);
        buildOptions(trocaParaEl, trocaPara);

        if (inicioEl && internDate) {
            inicioEl.value = internDate;
        }
        if (fimEl) {
            fimEl.value = useAlta && altaDate ? altaDate : ((inicioEl && inicioEl.value) || internDate || '');
        }
        if (qtdEl) {
            var qtdCalc = diffDaysForNegotiation(
                (inicioEl && inicioEl.value) || '',
                (fimEl && fimEl.value) || ''
            );
            qtdEl.value = qtdCalc > 0 ? qtdCalc : 1;
        }

        calcSavingNative(row);

        if (savingEl && savingShowEl && (!savingShowEl.value || savingShowEl.value === 'R$ 0.00')) {
            var deSel = trocaDeEl && trocaDeEl.selectedOptions && trocaDeEl.selectedOptions[0] ? trocaDeEl.selectedOptions[0] : null;
            var paraSel = trocaParaEl && trocaParaEl.selectedOptions && trocaParaEl.selectedOptions[0] ? trocaParaEl.selectedOptions[0] : null;
            var deLabel = deSel
                ? ((deSel.textContent || deSel.value || '') + '').trim()
                : ((trocaDeEl && trocaDeEl.value) || '').toString().trim();
            var paraLabel = paraSel
                ? ((paraSel.textContent || paraSel.value || '') + '').trim()
                : ((trocaParaEl && trocaParaEl.value) || '').toString().trim();
            var deVal = resolveNegotiationValueByLabel(deLabel);
            var paraVal = resolveNegotiationValueByLabel(paraLabel);
            var qtdVal = parseInt((qtdEl && qtdEl.value) || '0', 10) || 0;
            var s = 0;
            if (tipo.indexOf('TROCA') === 0) s = (deVal - paraVal) * qtdVal;
            else if (tipo.indexOf('1/2 DIARIA') !== -1) s = (deVal / 2) * qtdVal;
            else s = deVal * qtdVal;
            savingEl.value = s.toFixed(2);
            savingShowEl.value = 'R$ ' + Math.abs(s).toFixed(2);
            savingShowEl.style.color = s >= 0 ? 'green' : 'red';
        }

        if (window.jQuery) {
            genJSON();
        }
    };

    function parseDateOnly(value) {
        const raw = (value || '').toString().trim();
        if (!raw) return null;
        const base = raw.length >= 10 ? raw.slice(0, 10) : raw;
        const parts = base.split('-');
        if (parts.length !== 3) return null;
        const y = parseInt(parts[0], 10);
        const m = parseInt(parts[1], 10);
        const d = parseInt(parts[2], 10);
        if (!y || !m || !d) return null;
        return new Date(y, m - 1, d);
    }

    function formatDateOnly(date) {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) return '';
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function diffDaysForNegotiation(startValue, endValue) {
        const start = parseDateOnly(startValue);
        const end = parseDateOnly(endValue);
        if (!start || !end) return 0;
        const ms = end.getTime() - start.getTime();
        const days = Math.round(ms / 86400000);
        return Math.max(1, days);
    }

    function findOptionValue($select, wantedLabel) {
        const wanted = normalizeNegValue(wantedLabel);
        if (!wanted) return '';
        const $match = $select.find('option').filter(function() {
            return normalizeNegValue($(this).val()) === wanted
                || normalizeNegValue($(this).text()) === wanted;
        }).first();
        return $match.length ? ($match.val() || '') : '';
    }

    function findAcomodMeta(label) {
        const wanted = normalizeNegValue(label);
        const list = Array.isArray(window.__NEGOC_ACOMODACOES) ? window.__NEGOC_ACOMODACOES : [];
        const exact = list.find(function(item) {
            return normalizeNegValue((item && item.acomodacao_aco) || '') === wanted;
        });
        if (exact) return exact;
        if (wanted === 'apto') {
            return list.find(function(item) {
                const nome = normalizeNegValue((item && item.acomodacao_aco) || '');
                return nome === 'apartamento' || nome === 'apto';
            }) || null;
        }
        return null;
    }

    function resolveNegotiationValueByLabel(label) {
        const raw = (label || '').toString().trim();
        if (!raw) return 0;

        const meta = findAcomodMeta(raw);
        if (meta) {
            return safeNum(meta.valor_aco);
        }

        const norm = normalizeNegValue(raw);
        const values = window.fcNegValMap || {};
        if (norm === 'uti') return safeNum(values.UTI);
        if (norm === 'semi') return safeNum(values.Semi);
        if (norm === 'apto' || norm === 'apartamento') return safeNum(values.Apto);
        return 0;
    }

    function ensureBaseOptions($select) {
        const hasRealOptions = $select.find('option').filter(function() {
            return (($(this).val() || '').toString().trim() !== '');
        }).length > 0;
        if (hasRealOptions) {
            return;
        }

        let html = '<option value=""></option>';
        window.__NEGOC_ACOMODACOES.forEach(function(item) {
            const label = ((item && item.acomodacao_aco) || '').toString().trim();
            if (!label) return;
            const value = item && parseInt(item.id_acomodacao || 0, 10) > 0
                ? `${item.id_acomodacao}-${label}`
                : label;
            const valor = safeNum((item && item.valor_aco) || 0);
            html += `<option value="${value}" data-valor="${valor}">${label}</option>`;
        });
        $select.html(html);
    }

    function ensureSelectOption($select, label) {
        const wanted = (label || '').toString().trim();
        if (!wanted) return '';
        ensureBaseOptions($select);

        let value = findOptionValue($select, wanted);
        if (value) {
            return value;
        }

        const meta = findAcomodMeta(wanted);
        const valor = meta ? safeNum(meta.valor_aco) : 0;
        value = meta && parseInt(meta.id_acomodacao || 0, 10) > 0
            ? `${meta.id_acomodacao}-${meta.acomodacao_aco}`
            : wanted;

        const option = new Option(wanted, value, true, true);
        $(option).attr('data-valor', valor);
        $select.append(option);
        return value;
    }

    function negotiationTypeDefaults(tipo) {
        const t = (tipo || '').toString().trim().toUpperCase();
        const defaults = {
            trocaDe: '',
            trocaPara: '',
            useAlta: false
        };
        if (t === 'TROCA UTI/APTO') return { trocaDe: 'UTI', trocaPara: 'Apto', useAlta: true };
        if (t === 'TROCA UTI/SEMI') return { trocaDe: 'UTI', trocaPara: 'Semi', useAlta: true };
        if (t === 'TROCA SEMI/APTO') return { trocaDe: 'Semi', trocaPara: 'Apto', useAlta: true };
        if (t === 'GLOSA UTI' || t === 'TARDIA UTI') return { trocaDe: 'UTI', trocaPara: 'UTI', useAlta: false };
        if (t === 'GLOSA SEMI') return { trocaDe: 'Semi', trocaPara: 'Semi', useAlta: false };
        if (t === 'GLOSA APTO' || t === '1/2 DIARIA APTO' || t === 'TARDIA APTO' || t === 'DIARIA ADM') {
            return { trocaDe: 'Apto', trocaPara: 'Apto', useAlta: false };
        }
        return defaults;
    }

    function applyNegotiationDefaults($c, forceDates = false) {
        const tipo = ($c.find('[name="tipo_negociacao"]').val() || '').toString().trim();
        if (!tipo) return;
        const hasSavedRecord = (($c.find('[name="neg_id"]').val() || '').toString().trim() !== '');

        const defaults = negotiationTypeDefaults(tipo);
        const $trocaDe = $c.find('[name="troca_de"]');
        const $trocaPara = $c.find('[name="troca_para"]');
        const $inicio = $c.find('[name="data_inicio_neg"]');
        const $fim = $c.find('[name="data_fim_neg"]');
        const $qtd = $c.find('[name="qtd"]');
        const internDate = ($('#data_intern_int').val() || '').toString().trim();
        const altaDateTime = ($('#data_alta_alt').val() || '').toString().trim();
        const altaDate = altaDateTime ? altaDateTime.slice(0, 10) : '';

        ensureBaseOptions($trocaDe);
        ensureBaseOptions($trocaPara);

        if (defaults.trocaDe && (!hasSavedRecord || forceDates || !$trocaDe.val())) {
            const matchDe = ensureSelectOption($trocaDe, defaults.trocaDe);
            if (matchDe) {
                $trocaDe.val(matchDe).attr('data-current', defaults.trocaDe);
            }
        }
        if (defaults.trocaPara && (!hasSavedRecord || forceDates || !$trocaPara.val())) {
            const matchPara = ensureSelectOption($trocaPara, defaults.trocaPara);
            if (matchPara) {
                $trocaPara.val(matchPara).attr('data-current', defaults.trocaPara);
            }
        }

        if (forceDates || !hasSavedRecord || !$inicio.val()) {
            if (internDate) {
                $inicio.val(internDate);
            }
        }
        if (forceDates || !hasSavedRecord || !$fim.val()) {
            $fim.val(defaults.useAlta && altaDate ? altaDate : ($inicio.val() || internDate || ''));
        }

        if (!hasSavedRecord || forceDates || !$qtd.val()) {
            const qtdCalc = diffDaysForNegotiation($inicio.val(), $fim.val());
            if (qtdCalc > 0) {
                $qtd.val(qtdCalc);
            }
        }
    }

    function calcSaving($c) {
        const tipo = $c.find('[name="tipo_negociacao"]').val().toUpperCase().trim();
        const deLabel = (($c.find('[name="troca_de"] option:selected').text() || $c.find('[name="troca_de"]').val() || '') + '').trim();
        const paraLabel = (($c.find('[name="troca_para"] option:selected').text() || $c.find('[name="troca_para"]').val() || '') + '').trim();
        const de = resolveNegotiationValueByLabel(deLabel);
        const para = resolveNegotiationValueByLabel(paraLabel);
        const qtd = parseInt($c.find('[name="qtd"]').val(), 10) || 0;
        let s = 0;
        if (tipo.startsWith('TROCA')) s = (de - para) * qtd;
        else if (tipo.includes('1/2 DIARIA')) s = qtd * (de / 2);
        else s = qtd * de;
        $c.find('[name="saving"]').val(s.toFixed(2));
        $c.find('[name="saving_show"]').val(`R$ ${Math.abs(s).toFixed(2)}`)
            .css('color', s >= 0 ? 'green' : 'red');
    }

    function genJSON() {
        const arr = [];
        $('#negotiationFieldsContainer .negociation-field-container').each(function () {
            const $c = $(this);
            const item = {
                id: $c.find('[name="neg_id"]').val() || '',
                tipo_negociacao: $c.find('[name="tipo_negociacao"]').val(),
                data_inicio_neg: $c.find('[name="data_inicio_neg"]').val(),
                data_fim_neg: $c.find('[name="data_fim_neg"]').val(),
                troca_de: $c.find('[name="troca_de"]').val(),
                troca_para: $c.find('[name="troca_para"]').val(),
                qtd: parseInt($c.find('[name="qtd"]').val(), 10) || 0,
                saving: parseFloat($c.find('[name="saving"]').val()) || 0
            };
            // só adiciona se tiver tipo e qtd
            if (item.tipo_negociacao && item.qtd) {
                arr.push(item);
            }
        });
        $('#negociacoes_json').val(JSON.stringify(arr));
        $('#negociacoes_delete_ids').val(JSON.stringify(window.__NEGOC_DELETE_IDS || []));
    }

    window.refreshNegotiationRows = function(forceDates = false) {
        $('#negotiationFieldsContainer .negociation-field-container').each(function () {
            const $row = $(this);
            applyNegotiationDefaults($row, forceDates);
            calcSaving($row);
        });
        genJSON();
    };

    window.handleNegotiationTypeChange = function(el) {
        if (window.fcFillNegotiationRowCore) {
            window.fcFillNegotiationRowCore(el);
        }
    };

    // dispara a cada mudança
    $('#negotiationFieldsContainer').on('input change', 'select,input', function () {
        const $row = $(this).closest('.negociation-field-container');
        if ($(this).attr('name') === 'data_inicio_neg' || $(this).attr('name') === 'data_fim_neg') {
            const qtdCalc = diffDaysForNegotiation(
                $row.find('[name="data_inicio_neg"]').val(),
                $row.find('[name="data_fim_neg"]').val()
            );
            if (qtdCalc > 0) {
                $row.find('[name="qtd"]').val(qtdCalc);
            }
        }
        calcSaving($row);
        genJSON();
    });
    // e no submit do form, pra garantir
    $('form').on('submit', genJSON);

    // inicializa
    $(function () {
        document.querySelectorAll('#negotiationFieldsContainer select[name="tipo_negociacao"]').forEach(function(sel) {
            sel.addEventListener('change', function() {
                window.handleNegotiationTypeChange(this);
            });
        });
        if (typeof window.refreshNegotiationRows === 'function') {
            window.refreshNegotiationRows(false);
        } else {
            document.querySelectorAll('#negotiationFieldsContainer .negociation-field-container').forEach(function(row) {
                calcSavingNative(row);
            });
            genJSON();
        }
    });

    let debounce;
    $(document).on('input', '#negotiationFieldsContainer :input', function () {
        const $r = $(this).closest('.negociation-field-container');
        calcSaving($r);
        clearTimeout(debounce);
        debounce = setTimeout(genJSON, 200);
    });

    function buildNegotiationRow() {
        const template = document.getElementById('negotiationRowTemplate');
        if (template && template.content && template.content.firstElementChild) {
            return $(template.content.firstElementChild.cloneNode(true));
        }

        const $fallbackSource = $('#negotiationFieldsContainer .negociation-field-container').last();
        if (!$fallbackSource.length) {
            return $();
        }

        const $fallbackRow = $fallbackSource.clone(false, false);
        clearNegotiationRow($fallbackRow);
        $fallbackRow.find('.neg-trash-check, .btn-trash-negoc').remove();
        return $fallbackRow;
    }

    window.addNegotiationField = function(btn) {
        const $new = buildNegotiationRow();
        if (!$new.length) {
            return false;
        }
        const $targetRow = btn ? $(btn).closest('.negociation-field-container') : $('.negociation-field-container').last();
        if ($targetRow.length) {
            $new.insertAfter($targetRow);
        } else {
            $('#negotiationFieldsContainer').append($new);
        }
        clearNegotiationRow($new);
        genJSON();
        return false;
    };

    function clearNegotiationRow($row) {
        $row.find('[name="neg_id"]').val('');
        $row.find('[name="tipo_negociacao"]').val('');
        $row.find('[name="data_inicio_neg"]').val('');
        $row.find('[name="data_fim_neg"]').val('');
        $row.find('[name="troca_de"]').val('').attr('data-current', '');
        $row.find('[name="troca_para"]').val('').attr('data-current', '');
        $row.find('[name="qtd"]').val('');
        $row.find('[name="saving"]').val('');
        $row.find('[name="saving_show"]').val('').css('color', '');
    }

    window.removeNegotiationField = function(btn) {
        const $row = $(btn).closest('.negociation-field-container');
        const existingId = ($row.find('[name="neg_id"]').val() || '').toString().trim();
        const hasContent = [
            'tipo_negociacao',
            'data_inicio_neg',
            'data_fim_neg',
            'troca_de',
            'troca_para',
            'qtd'
        ].some(function(name) {
            return (($row.find(`[name="${name}"]`).val() || '').toString().trim() !== '');
        });

        if ((existingId || hasContent) && !window.confirm('Deseja remover esta negociação? A exclusão será concluída ao clicar em Atualizar.')) {
            return;
        }

        if (existingId) {
            const parsedId = parseInt(existingId, 10);
            if (parsedId > 0) {
                window.__NEGOC_DELETE_IDS = Array.isArray(window.__NEGOC_DELETE_IDS) ? window.__NEGOC_DELETE_IDS : [];
                if (!window.__NEGOC_DELETE_IDS.includes(parsedId)) {
                    window.__NEGOC_DELETE_IDS.push(parsedId);
                }
            }
        }

        if ($('.negociation-field-container').length > 1) {
            $row.remove();
        } else {
            clearNegotiationRow($row);
        }
        genJSON();
        return false;
    };

    $(document).on('change', '.neg-trash-check', function() {
        genJSON();
    });

    $(document).on('click', '.btn-add-negoc', function(event) {
        event.preventDefault();
        window.addNegotiationField(this);
    });

    $(document).on('click', '.btn-del-negoc', function(event) {
        event.preventDefault();
        window.removeNegotiationField(this);
    });

</script>
