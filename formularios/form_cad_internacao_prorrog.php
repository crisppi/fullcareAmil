<?php
$prorrogDefaultAcomod = '';
$prorrogDefaultIni = '';
$prorrogFkInternacaoValue = (int)($id_internacao ?? 0);
if ($prorrogFkInternacaoValue <= 0 && isset($ultimoReg)) {
    $prorrogFkInternacaoValue = is_array($ultimoReg)
        ? (int)($ultimoReg['id_internacao'] ?? 0)
        : (int)$ultimoReg;
}

if (!empty($prorrogIntern) && is_array($prorrogIntern)) {
    $ultimaProrrog = null;
    $ultimaProrrogTs = 0;

    foreach ($prorrogIntern as $row) {
        if (!is_array($row)) {
            continue;
        }

        $fim = trim((string)($row['prorrog1_fim_pror'] ?? ''));
        $ini = trim((string)($row['prorrog1_ini_pror'] ?? ''));
        $baseData = $fim !== '' ? $fim : $ini;
        $baseTs = $baseData !== '' ? strtotime($baseData) : false;
        $idAtual = (int)($row['id_prorrogacao'] ?? 0);

        if ($baseTs === false) {
            continue;
        }

        if ($ultimaProrrog === null || $baseTs > $ultimaProrrogTs || ($baseTs === $ultimaProrrogTs && $idAtual > (int)($ultimaProrrog['id_prorrogacao'] ?? 0))) {
            $ultimaProrrog = $row;
            $ultimaProrrogTs = $baseTs;
        }
    }

    if ($ultimaProrrog) {
        $prorrogDefaultAcomod = trim((string)($ultimaProrrog['acomod1_pror'] ?? ''));
        $dataBaseNovaProrrog = trim((string)($ultimaProrrog['prorrog1_fim_pror'] ?? ''));
        if ($dataBaseNovaProrrog === '') {
            $dataBaseNovaProrrog = trim((string)($ultimaProrrog['prorrog1_ini_pror'] ?? ''));
        }
        if ($dataBaseNovaProrrog !== '') {
            $tsNovaProrrog = strtotime($dataBaseNovaProrrog);
            if ($tsNovaProrrog) {
                $prorrogDefaultIni = date('Y-m-d', $tsNovaProrrog);
            }
        }
    }
}

function prorrogDateValue($value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    $ts = strtotime($value);
    return $ts ? date('Y-m-d', $ts) : substr($value, 0, 10);
}

$prorrogEditRows = (isset($prorrogEditRows) && is_array($prorrogEditRows))
    ? array_values(array_filter($prorrogEditRows, 'is_array'))
    : [];
usort($prorrogEditRows, static function ($a, $b) {
    $aDate = strtotime((string)($a['prorrog1_ini_pror'] ?? '')) ?: 0;
    $bDate = strtotime((string)($b['prorrog1_ini_pror'] ?? '')) ?: 0;
    if ($aDate === $bDate) {
        return (int)($a['id_prorrogacao'] ?? 0) <=> (int)($b['id_prorrogacao'] ?? 0);
    }
    return $aDate <=> $bDate;
});
$prorrogInitialRows = $prorrogEditRows ?: [[
    'acomod1_pror' => $prorrogDefaultAcomod,
    'prorrog1_ini_pror' => $prorrogDefaultIni,
    'prorrog1_fim_pror' => '',
    'diarias_1' => '',
    'isol_1_pror' => 'n',
]];
?>
<style>
.prorrogacao-container .form-group.row {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: flex-start;
}

.prorrogacao-container .form-group {
    margin-bottom: 15px;
}

.prorrogacao-container .form-group label {
    margin-bottom: 2px;
    font-weight: 400;
}

.prorrogacao-container .form-control {
    width: 100%;
    padding: 5px;
}

.prorrogacao-container .btn {
    padding: 5px 10px;
    font-size: 0.9rem;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

.prorrogacao-container .btn-add {
    background-color: #007bff;
    color: white;
}

.prorrogacao-container .btn-remove {
    background-color: #dc3545;
    color: #fff;
}

.prorrogacao-container #prorrogacoes-json {
    margin-top: 20px;
    width: 100%;
    padding: 10px;
    font-size: 1rem;
}

.prorrogacao-container .prorrog-alert {
    display: none;
    width: 100%;
    margin: 4px 0 0;
    padding: 8px 10px;
    border-radius: 6px;
    border: 1px solid #f5c2c7;
    background: #f8d7da;
    color: #8b1e25;
    font-size: 0.85em;
    line-height: 1.2;
}
.adicional-card {
    background:#f5f5f9;
    border-radius:22px;
    border:1px solid #ebe1f5;
    box-shadow:0 12px 28px rgba(45,18,70,.08);
    padding:22px 24px;
}
.adicional-card__header {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:.75rem;
    margin-bottom:18px;
}
.adicional-card__title {
    display:flex;
    align-items:center;
    margin:0;
    color:#3a184f;
    font-weight:600;
}
.adicional-card__marker {
    width:6px;
    height:26px;
    border-radius:10px;
    margin-right:12px;
    background:linear-gradient(180deg,#8f5ff3,#b995ff);
}

#container-prorrog .adicional-card .field-container {
    display: grid !important;
    grid-template-columns: repeat(auto-fit, minmax(155px, 1fr));
    gap: 14px;
    align-items: end;
    width: 100%;
}

#container-prorrog .adicional-card .field-container > .form-group[class*="col-"] {
    width: 100% !important;
    min-width: 0 !important;
    max-width: none !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
    margin: 0 !important;
}

#container-prorrog .adicional-card .field-container > [style*="display:none"] {
    display: none !important;
}

#container-prorrog .adicional-card .form-control,
#container-prorrog .adicional-card .form-control-sm.form-control {
    width: 100% !important;
    min-height: 42px !important;
    height: 42px !important;
}

#container-prorrog .adicional-card textarea.form-control {
    min-height: 92px !important;
    height: auto !important;
}

@media (max-width: 768px) {
    #container-prorrog .adicional-card .field-container {
        grid-template-columns: 1fr;
    }
    .prorrog-alta-fields > .form-group.row {
        grid-template-columns: 1fr !important;
        max-width: none !important;
    }
}
.custom-dialog {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 1050;
    background: rgba(0, 0, 0, .4)
}
.custom-dialog-content {
    background: #fff;
    margin: 15% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%;
    max-width: 600px;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, .2)
}
.custom-dialog-header {
    display: flex;
    justify-content: space-between;
    align-items: center
}
.custom-dialog-header .close {
    cursor: pointer;
    font-size: 1.5rem
}
.prorrog-alta-box {
    margin-top: 18px;
    padding: 16px 18px;
    border: 1px solid #e6dced;
    border-radius: 16px;
    background: linear-gradient(180deg, #fcf9ff 0%, #f7f2fb 100%);
}
.prorrog-alta-box__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}
.prorrog-alta-box__title {
    margin: 0;
    color: #3a184f;
    font-size: 1rem;
    font-weight: 600;
}
.prorrog-alta-box__hint {
    margin: 4px 0 0;
    color: #6f5a7e;
    font-size: .84rem;
}
.prorrog-alta-toggle {
    display: inline-flex;
    gap: 8px;
    flex-wrap: wrap;
}
.prorrog-alta-toggle .btn {
    min-width: 84px;
    border-radius: 999px;
    border: 1px solid #cbb7d8;
    background: #fff;
    color: #5e2363;
    font-weight: 600;
}
.prorrog-alta-toggle .btn.is-active {
    background: #5e2363;
    border-color: #5e2363;
    color: #fff;
}
.prorrog-alta-fields {
    display: none;
}
.prorrog-alta-fields.is-visible {
    display: block;
}
.prorrog-alta-fields > .form-group.row {
    display: grid !important;
    grid-template-columns: minmax(220px, 260px) minmax(260px, 320px);
    gap: 14px 18px;
    align-items: end;
    width: 100% !important;
    max-width: 620px !important;
    margin: 0 !important;
}
.prorrog-alta-fields > .form-group.row > .form-group[class*="col-"] {
    flex: none !important;
    width: 100% !important;
    min-width: 0 !important;
    max-width: none !important;
    margin: 0 !important;
    padding: 0 !important;
}
.prorrog-alta-fields .form-control,
.prorrog-alta-fields .form-control-sm.form-control,
.prorrog-alta-fields .bootstrap-select,
.prorrog-alta-fields .bootstrap-select > .dropdown-toggle {
    width: 100% !important;
    min-width: 0 !important;
    max-width: 100% !important;
}
.prorrog-alta-fields .bootstrap-select .filter-option-inner-inner {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.prorrog-ia-box {
    margin-top: 18px;
    padding: 14px;
    width: 100%;
    max-width: none;
    border-radius: 16px;
    border: 1px solid #bfdbfe;
    background: linear-gradient(135deg, #eff6ff 0%, #eef2ff 48%, #f8fafc 100%);
    box-shadow: inset 0 1px 0 rgba(255,255,255,.9), 0 14px 30px rgba(37,99,235,.08);
}
.prorrog-ia-box__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
.prorrog-ia-box__title-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.prorrog-ia-box__eyebrow {
    margin: 0 0 2px;
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: #1d4ed8;
}
.prorrog-ia-box__title {
    margin: 0;
    font-size: 1.08rem;
    font-weight: 800;
    color: #0f172a;
}
.prorrog-ia-powered {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 28px;
    padding: 4px 10px;
    border-radius: 999px;
    background: rgba(255,255,255,.78);
    border: 1px solid rgba(99,102,241,.22);
    color: #1d4ed8;
    font-size: .76rem;
    font-weight: 800;
    letter-spacing: .03em;
    text-transform: uppercase;
}
.prorrog-ia-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.prorrog-ia-context-row {
    display: block !important;
    width: 100% !important;
    max-width: 100% !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
}
.prorrog-ia-context-field {
    display: block !important;
    width: 100% !important;
    min-width: 0 !important;
    max-width: 100% !important;
    flex: 0 0 100% !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
    margin-bottom: 0 !important;
}
.prorrog-ia-box textarea.form-control,
#prorrog-ia-contexto {
    display: block;
    width: 100% !important;
    min-width: 100% !important;
    max-width: 100% !important;
    min-height: 96px !important;
    box-sizing: border-box !important;
}
.prorrog-ia-card {
    width: 100%;
    max-width: none;
    border: 1px solid #c7d2fe;
    border-radius: 12px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    overflow: hidden;
    box-shadow: 0 12px 28px rgba(37,99,235,.10);
}
.prorrog-ia-card__header {
    min-height: 44px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 8px 12px;
    background: linear-gradient(135deg, #dbeafe 0%, #eef2ff 50%, #ecfeff 100%);
    border-bottom: 1px solid #c7d2fe;
}
.prorrog-ia-card__header h5 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: #111827;
}
.prorrog-ia-toggle {
    width: 32px;
    height: 32px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #fff;
    color: #374151;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.prorrog-ia-card__body {
    padding: 12px;
}
.prorrog-ia-status {
    margin: 10px 12px 0;
    padding: 8px 10px;
    border-radius: 8px;
    font-weight: 700;
    font-size: .88rem;
}
.prorrog-ia-status--info { background: #e0f2fe; color: #075985; }
.prorrog-ia-status--success { background: #dcfce7; color: #166534; }
.prorrog-ia-status--error { background: #fee2e2; color: #991b1b; }
.prorrog-ia-badge {
    display: inline-flex;
    align-items: center;
    min-height: 28px;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: .78rem;
    font-weight: 800;
    letter-spacing: .04em;
}
.prorrog-ia-badge--ok { background: #dcfce7; color: #166534; }
.prorrog-ia-badge--info { background: #dbeafe; color: #1d4ed8; }
.prorrog-ia-badge--warn { background: #ffedd5; color: #c2410c; }
.prorrog-ia-badge--danger { background: #fee2e2; color: #b91c1c; }
.prorrog-ia-badge--neutral { background: #fef3c7; color: #92400e; }
.prorrog-ia-result-head { margin-bottom: 10px; }
.prorrog-ia-section {
    margin-top: 10px;
    color: #1f2937;
}
.prorrog-ia-section p,
.prorrog-ia-section ul { margin: 4px 0 0; }
.prorrog-ia-section ul { padding-left: 18px; }
.prorrog-ia-empty {
    margin: 0;
    color: #6b7280;
}
.prorrog-ia-final-alert {
    margin-top: 14px;
    padding: 12px 14px;
    border-radius: 10px;
    border: 1px solid #fecaca;
    background: linear-gradient(135deg, #fff1f2, #fee2e2);
    color: #b91c1c;
    font-weight: 800;
    line-height: 1.4;
}
</style>

<?php
if (!function_exists('prorrogNormAcomod')) {
    function prorrogNormAcomod(string $value): string
    {
        $value = trim($value);
        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }
        $value = preg_replace('/^\d+\s*-\s*/', '', $value);
        $value = str_replace(
            ['á', 'à', 'â', 'ã', 'ä', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï', 'ó', 'ò', 'ô', 'õ', 'ö', 'ú', 'ù', 'û', 'ü', 'ç'],
            ['a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'c'],
            $value
        );
        return trim($value);
    }
}

if (!function_exists('prorrogValorAcomod')) {
    function prorrogValorAcomod(string $acomod, array $map): float
    {
        $norm = prorrogNormAcomod($acomod);
        if (isset($map[$norm]) && is_numeric($map[$norm])) {
            return (float)$map[$norm];
        }
        return 0.0;
    }
}

$prorrogAcomodValorMap = [];
if (isset($acomodacao) && is_array($acomodacao)) {
    foreach ($acomodacao as $row) {
        if (!is_array($row)) continue;
        $nome = (string)($row['acomodacao_aco'] ?? $row['acomodacao'] ?? '');
        $valor = $row['valor_aco'] ?? $row['valor'] ?? null;
        if ($nome !== '' && is_numeric($valor)) {
            $prorrogAcomodValorMap[prorrogNormAcomod($nome)] = (float)$valor;
        }
    }
}
$dadosAltaProrrog = [];
if (isset($dados_alta) && is_array($dados_alta)) {
    $dadosAltaProrrog = $dados_alta;
    sort($dadosAltaProrrog, SORT_ASC);
}
?>

<div class="prorrogacao-container" id="container-prorrog" style="display:none;">
    <div class="adicional-card">
        <div class="adicional-card__header">
            <h4 class="adicional-card__title">
                <span class="adicional-card__marker"></span>
                Prorrogação
            </h4>

            <?php if (!empty($prorrogIntern) && count($prorrogIntern) > 0): ?>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalProrrog"
                id="openmodal">
                <i class="fas fa-eye"></i> Prorrogações Anteriores
            </button>
            <?php endif; ?>
        </div>
    <div id="fieldsContainer">

        <?php foreach ($prorrogInitialRows as $prorrogRowIndex => $prorrogRow): ?>
        <?php
            $prorrogRowAcomod = (string)($prorrogRow['acomod1_pror'] ?? '');
            $prorrogRowIni = prorrogDateValue($prorrogRow['prorrog1_ini_pror'] ?? '');
            $prorrogRowFim = prorrogDateValue($prorrogRow['prorrog1_fim_pror'] ?? '');
            $prorrogRowDiarias = (string)($prorrogRow['diarias_1'] ?? '');
            $prorrogRowIsol = (string)($prorrogRow['isol_1_pror'] ?? 'n');
        ?>
        <div class="field-container form-group row">
            <input type="hidden" <?= $prorrogRowIndex === 0 ? 'id="fk_internacao_pror"' : '' ?> name="fk_internacao_pror" value="<?= $prorrogFkInternacaoValue ?>">
            <input type="hidden" <?= $prorrogRowIndex === 0 ? 'id="fk_usuario_pror"' : '' ?> name="fk_usuario_pror" value="<?= (int)($_SESSION["id_usuario"] ?? 0) ?>">

            <div class="form-group col-sm-2">
                <label class="control-label" for="acomod1_pror_<?= $prorrogRowIndex ?>">Acomodação</label>
                <select onchange="generateProrJSON()" class="form-control-sm form-control" id="acomod1_pror_<?= $prorrogRowIndex ?>"
                    name="acomod1_pror">
                    <option value=""> </option>
                    <?php sort($dados_acomodacao, SORT_ASC);
                    foreach ($dados_acomodacao as $acomd) { ?>
                    <option value="<?= $acomd; ?>" data-valor="<?= htmlspecialchars((string)prorrogValorAcomod((string)$acomd, $prorrogAcomodValorMap), ENT_QUOTES, 'UTF-8'); ?>" <?= $prorrogRowAcomod === (string)$acomd ? 'selected' : '' ?>><?= $acomd; ?></option>
                    <?php } ?>
                </select>
            </div>

            <div class="form-group col-sm-2">
                <label class="control-label" for="prorrog1_ini_pror_<?= $prorrogRowIndex ?>">Data inicial</label>
                <input onchange="generateProrJSON()" type="date" class="form-control-sm form-control"
                    id="prorrog1_ini_pror_<?= $prorrogRowIndex ?>" name="prorrog1_ini_pror" value="<?= htmlspecialchars($prorrogRowIni, ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="form-group col-sm-2">
                <label class="control-label" for="prorrog1_fim_pror_<?= $prorrogRowIndex ?>">Data final</label>
                <input onchange="generateProrJSON()" type="date" class="form-control-sm form-control"
                    id="prorrog1_fim_pror_<?= $prorrogRowIndex ?>" name="prorrog1_fim_pror" value="<?= htmlspecialchars($prorrogRowFim, ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="form-group col-sm-1">
                <label class="control-label" for="diarias_1_<?= $prorrogRowIndex ?>">Diárias</label>
                <input type="text" style="text-align:center; font-weight:600; background-color:darkgray" readonly
                    class="form-control-sm form-control" id="diarias_1_<?= $prorrogRowIndex ?>" name="diarias_1" value="<?= htmlspecialchars($prorrogRowDiarias, ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="form-group col-sm-1">
                <label class="control-label" for="isol_1_pror_<?= $prorrogRowIndex ?>">Isolamento</label>
                <select onchange="generateProrJSON()" class="form-control-sm form-control" id="isol_1_pror_<?= $prorrogRowIndex ?>"
                    name="isol_1_pror">
                    <option value="n" <?= $prorrogRowIsol === 'n' ? 'selected' : '' ?>>Não</option>
                    <option value="s" <?= $prorrogRowIsol === 's' ? 'selected' : '' ?>>Sim</option>
                </select>
            </div>

            <div class="form-group col-sm-2" style="margin-top:25px">
                <?php if ($prorrogRowIndex > 0): ?>
                <button type="button" class="btn btn-remove" onclick="removeField(this)">-</button>
                <?php endif; ?>
                <button type="button" class="btn btn-add" onclick="addField()">+</button>
            </div>

            <div class="error-message prorrog-alert" role="alert"></div>
        </div>
        <?php endforeach; ?>
    </div>

    <input type="hidden" id="prorrogacoes-json" name="prorrogacoes-json">
    <input type="hidden" id="prorrog_gerar_alta" name="prorrog_gerar_alta" value="n">
    <input type="hidden" id="prorrog_fk_usuario_alt" name="prorrog_fk_usuario_alt" value="<?= (int)($_SESSION["id_usuario"] ?? 0) ?>">
    <input type="hidden" id="prorrog_usuario_alt" name="prorrog_usuario_alt" value="<?= htmlspecialchars((string)($_SESSION["email_user"] ?? 'sistema'), ENT_QUOTES, 'UTF-8') ?>">

    <div class="prorrog-alta-box">
        <div class="prorrog-alta-box__header">
            <div>
                <h5 class="prorrog-alta-box__title">Paciente teve alta nesta prorrogação?</h5>
                <p class="prorrog-alta-box__hint">Se sim, já fechamos a alta aqui sem precisar ir para outra tela.</p>
            </div>
            <div class="prorrog-alta-toggle" role="group" aria-label="Paciente teve alta">
                <button type="button" class="btn is-active" id="prorrog-alta-btn-nao" data-prorrog-alta-toggle="n">Não</button>
                <button type="button" class="btn" id="prorrog-alta-btn-sim" data-prorrog-alta-toggle="s">Sim</button>
            </div>
        </div>

        <div class="prorrog-alta-fields" id="prorrog-alta-fields">
            <div class="form-group row">
                <div class="form-group col-sm-3">
                    <label class="control-label" for="prorrog_data_alta_alt_display">Data/Hora Alta</label>
                    <input type="hidden" id="prorrog_data_alta_alt" name="prorrog_data_alta_alt">
                    <input type="text" class="form-control-sm form-control" id="prorrog_data_alta_alt_display"
                        placeholder="dd/mm/aaaa hh:mm" autocomplete="off">
                </div>
                <div class="form-group col-sm-4">
                    <label class="control-label" for="prorrog_tipo_alta_alt">Motivo Alta</label>
                    <select class="form-control-sm form-control" id="prorrog_tipo_alta_alt" name="prorrog_tipo_alta_alt">
                        <option value="">Selecione o motivo da alta</option>
                        <?php foreach ($dadosAltaProrrog as $altaMotivo): ?>
                            <option value="<?= htmlspecialchars((string)$altaMotivo, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$altaMotivo, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="prorrog-ia-box">
        <div class="prorrog-ia-box__header">
            <div class="prorrog-ia-box__title-wrap">
                <div>
                    <p class="prorrog-ia-box__eyebrow">Inteligência Artificial</p>
                    <h4 class="prorrog-ia-box__title">Parecer para prorrogação assistencial</h4>
                </div>
                <span class="prorrog-ia-powered">
                    <i class="bi bi-stars"></i>
                    IA conectada
                </span>
            </div>
            <div class="prorrog-ia-actions">
                <button type="button" class="btn btn-primary" id="btn-executar-prorrog-ia">
                    <i class="bi bi-cpu"></i>
                    Executar IA Prorrogação
                </button>
            </div>
        </div>
        <div class="prorrog-ia-context-row">
            <div class="prorrog-ia-context-field">
                <label class="control-label" for="prorrog-ia-contexto">Contexto complementar</label>
                <textarea class="form-control-sm form-control" id="prorrog-ia-contexto" rows="3" placeholder="Opcional: acrescente observações clínicas, plano de transição de cuidado, home care, barreiras de alta ou contexto assistencial relevante."></textarea>
                <small style="display:block;margin-top:6px;color:#475569;font-weight:600;">
                    A IA já considera automaticamente o relatório da auditoria, as ações da auditoria e a programação terapêutica desta tela.
                </small>
            </div>
        </div>
        <div class="prorrog-ia-card">
            <div class="prorrog-ia-card__header">
                <h5>Parecer IA de Prorrogação</h5>
                <button type="button" class="prorrog-ia-toggle" id="btn-toggle-prorrog-ia" aria-expanded="false" aria-controls="prorrog-ia-body">
                    <i class="bi bi-chevron-down"></i>
                </button>
            </div>
            <div id="prorrog-ia-status" class="prorrog-ia-status" hidden></div>
            <div class="prorrog-ia-card__body" id="prorrog-ia-body" hidden>
                <div id="prorrog-ia-content">
                    <p class="prorrog-ia-empty">Nenhum parecer gerado.</p>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>

<div class="modal fade" id="modalProrrog">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background:#5e2363;">
                <h4 class="page-title" style="color:white">Prorrogações</h4>
                <p class="page-description" style="color:white; margin-top:5px">Informações sobre prorrogações
                    anteriores</p>
            </div>
            <div class="modal-body">
                <?php
                if (empty($visitas)) {
                    echo ("<br>");
                    echo ("<p style='margin-left:100px'> <b>-- Esta internação ainda não possui Prorrogações  -- </b></p>");
                    echo ("<br>");
                } else { ?>
                <table class="table table-sm table-striped table-hover table-condensed">
                    <thead>
                        <tr>
                            <th scope="col" style="width:5%">Id</th>
                            <th scope="col" style="width:10%">Acomodação</th>
                            <th scope="col" style="width:15%">Início</th>
                            <th scope="col" style="width:15%">Fim</th>
                            <th scope="col" style="width:15%">Diárias</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prorrogIntern as $intern) {
                            $idProrrog = $intern["id_prorrogacao"] ?? "Desconhecido";
                            $acomod = $intern["acomod1_pror"] ?? "Desconhecido";
                            $inicio = !empty($intern['prorrog1_ini_pror']) ? date("d/m/Y", strtotime($intern['prorrog1_ini_pror'])) : "--";
                            $fim    = !empty($intern['prorrog1_fim_pror']) ? date("d/m/Y", strtotime($intern['prorrog1_fim_pror'])) : "--";
                            $diarias = $intern["diarias_1"] ?? "--";
                        ?>
                        <tr>
                            <td><?= $idProrrog ?></td>
                            <td><?= $acomod ?></td>
                            <td><?= $inicio ?></td>
                            <td><?= $fim ?></td>
                            <td><?= $diarias ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <?php } ?>
                <br>
            </div>
        </div>
    </div>
</div>

<div id="prorrogErrorDialog" class="custom-dialog" role="dialog" aria-modal="true" aria-labelledby="prorrogDlgTitle" style="display:none;">
    <div class="custom-dialog-content">
        <div class="custom-dialog-header">
            <span id="prorrogDlgTitle">Atenção</span>
            <span class="close" onclick="closeProrrogError()">&times;</span>
        </div>
        <div class="custom-dialog-body" id="prorrogErrorBody"></div>
    </div>
</div>

<script>
    window.PRORROG_MAX_DATE = "<?= date('Y-m-d') ?>";
</script>
<script>
window.__PRORROG_DEFAULT_FIRST__ = {
    acomod1_pror: <?= json_encode($prorrogDefaultAcomod, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    prorrog1_ini_pror: <?= json_encode($prorrogDefaultIni, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
};

function openProrrogError(msg) {
    const dlg = document.getElementById("prorrogErrorDialog");
    const body = document.getElementById("prorrogErrorBody");
    if (!dlg || !body) return;
    body.textContent = msg;
    dlg.style.display = "block";
}
function closeProrrogError() {
    const dlg = document.getElementById("prorrogErrorDialog");
    if (dlg) dlg.style.display = "none";
}

function addOneDayToDate(dateValue) {
    const base = String(dateValue || '').trim();
    if (!base) return '';
    const parts = base.split('-').map(Number);
    if (parts.length !== 3 || parts.some(Number.isNaN)) return '';
    const nextDate = new Date(parts[0], parts[1] - 1, parts[2] + 1);
    const yyyy = nextDate.getFullYear();
    const mm = String(nextDate.getMonth() + 1).padStart(2, '0');
    const dd = String(nextDate.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}

// Exibe o container ao carregar a página, se "select_prorrog" estiver marcado
document.addEventListener("DOMContentLoaded", function() {
    const selectProrrog = document.getElementById("select_prorrog");
    if (selectProrrog && selectProrrog.value === "s") {
        document.getElementById("container-prorrog").style.display = "block";
    }
});

// Preenche a data inicial da primeira linha com a data da internação
function getInternacaoDateForProrrog() {
    const hidden = document.getElementById("data_intern_int");
    const visibleDt = document.getElementById("data_intern_int_dt");

    if (hidden && hidden.value) return hidden.value;
    if (visibleDt && visibleDt.value) {
        const parts = String(visibleDt.value).split("T");
        return parts[0] || "";
    }
    return "";
}

function setFirstProrrogationDate() {
    const firstContainer = document.querySelector("#fieldsContainer .field-container");
    if (!firstContainer) return;

    const defaults = window.__PRORROG_DEFAULT_FIRST__ || {};
    const dataInternacao = getInternacaoDateForProrrog();
    const iniInput = firstContainer.querySelector('[name="prorrog1_ini_pror"]');
    const acomodInput = firstContainer.querySelector('[name="acomod1_pror"]');

    if (acomodInput && defaults.acomod1_pror && !acomodInput.value) {
        acomodInput.value = defaults.acomod1_pror;
    }

    if (iniInput && !iniInput.value) {
        iniInput.value = defaults.prorrog1_ini_pror || dataInternacao || '';
    }
}

function getManualProrrogMinDate() {
    return getInternacaoDateForProrrog();
}

const dataInternInput = document.getElementById("data_intern_int");
const dataInternVisible = document.getElementById("data_intern_int_dt");
if (dataInternInput) {
    dataInternInput.addEventListener("change", setFirstProrrogationDate);
    dataInternInput.addEventListener("input", setFirstProrrogationDate);
}
if (dataInternVisible) {
    dataInternVisible.addEventListener("change", setFirstProrrogationDate);
    dataInternVisible.addEventListener("input", setFirstProrrogationDate);
    dataInternVisible.addEventListener("blur", setFirstProrrogationDate);
}
document.addEventListener("DOMContentLoaded", setFirstProrrogationDate);

function formatLocalDateTimeNow() {
    const now = new Date();
    now.setSeconds(0, 0);
    const tzOffset = now.getTimezoneOffset() * 60000;
    return new Date(now.getTime() - tzOffset).toISOString().slice(0, 16);
}

function formatDateToLocalDateTime(dateValue) {
    const base = (dateValue || '').toString().trim();
    if (!base) return '';
    return `${base}T12:00`;
}

function formatLocalDateTimeToBR(value) {
    const iso = normalizeProrrogAltaDateTime(value);
    if (!iso) return '';
    const [datePart, timePart = '12:00'] = iso.split('T');
    const [yyyy, mm, dd] = datePart.split('-');
    return `${dd}/${mm}/${yyyy} ${timePart.slice(0, 5)}`;
}

function normalizeProrrogAltaDateTime(value) {
    const raw = (value || '').toString().trim().replace(',', ' ');
    if (!raw) return '';

    const isoMatch = raw.match(/^(\d{4})-(\d{2})-(\d{2})(?:[T\s](\d{2}):(\d{2}))?/);
    if (isoMatch) {
        return `${isoMatch[1]}-${isoMatch[2]}-${isoMatch[3]}T${isoMatch[4] || '12'}:${isoMatch[5] || '00'}`;
    }

    const brMatch = raw.match(/^(\d{2})\/(\d{2})\/(\d{4})(?:\s+(\d{1,2}):(\d{2}))?$/);
    if (brMatch) {
        const hh = String(brMatch[4] || '12').padStart(2, '0');
        const mi = String(brMatch[5] || '00').padStart(2, '0');
        return `${brMatch[3]}-${brMatch[2]}-${brMatch[1]}T${hh}:${mi}`;
    }

    return '';
}

function syncProrrogAltaHiddenFromDisplay() {
    const hidden = document.getElementById('prorrog_data_alta_alt');
    const display = document.getElementById('prorrog_data_alta_alt_display');
    if (!hidden || !display) return '';

    const normalized = normalizeProrrogAltaDateTime(display.value);
    hidden.value = normalized;
    if (normalized) {
        display.value = formatLocalDateTimeToBR(normalized);
    }
    return normalized;
}

function setProrrogAltaDateTime(value) {
    const hidden = document.getElementById('prorrog_data_alta_alt');
    const display = document.getElementById('prorrog_data_alta_alt_display');
    const normalized = normalizeProrrogAltaDateTime(value);
    if (hidden) hidden.value = normalized;
    if (display) display.value = normalized ? formatLocalDateTimeToBR(normalized) : '';
}

function getSuggestedAltaDateFromProrrog() {
    const rows = Array.from(document.querySelectorAll('#fieldsContainer .field-container'));
    for (let i = rows.length - 1; i >= 0; i -= 1) {
        const fim = rows[i].querySelector('[name="prorrog1_fim_pror"]')?.value || '';
        if (fim) return formatDateToLocalDateTime(fim);
        const ini = rows[i].querySelector('[name="prorrog1_ini_pror"]')?.value || '';
        if (ini) return formatDateToLocalDateTime(ini);
    }
    const internDate = getInternacaoDateForProrrog();
    return internDate ? formatDateToLocalDateTime(internDate) : '';
}

function syncProrrogAltaBounds() {
    const dataInput = document.getElementById('prorrog_data_alta_alt');
    if (!dataInput) return;

    const internDate = getInternacaoDateForProrrog();
    const now = formatLocalDateTimeNow();
    dataInput.dataset.max = now;
    if (internDate) {
        dataInput.dataset.min = formatDateToLocalDateTime(internDate);
    } else {
        delete dataInput.dataset.min;
    }
}

function syncProrrogAltaToggle(flag) {
    const hidden = document.getElementById('prorrog_gerar_alta');
    const fields = document.getElementById('prorrog-alta-fields');
    const btnNao = document.getElementById('prorrog-alta-btn-nao');
    const btnSim = document.getElementById('prorrog-alta-btn-sim');
    const dataInput = document.getElementById('prorrog_data_alta_alt');
    const dataDisplay = document.getElementById('prorrog_data_alta_alt_display');
    const motivoInput = document.getElementById('prorrog_tipo_alta_alt');
    const enabled = flag === 's';

    if (hidden) hidden.value = enabled ? 's' : 'n';
    if (fields) fields.classList.toggle('is-visible', enabled);
    if (btnNao) btnNao.classList.toggle('is-active', !enabled);
    if (btnSim) btnSim.classList.toggle('is-active', enabled);
    if (dataInput) {
        syncProrrogAltaBounds();
        if (!enabled) {
            setProrrogAltaDateTime('');
        } else if (!dataInput.value) {
            setProrrogAltaDateTime(getSuggestedAltaDateFromProrrog() || formatLocalDateTimeNow());
        }
    }
    if (dataDisplay) {
        dataDisplay.required = enabled;
    }
    if (motivoInput) {
        motivoInput.required = enabled;
        if (!enabled) {
            motivoInput.value = '';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const toggleButtons = document.querySelectorAll('[data-prorrog-alta-toggle]');
    toggleButtons.forEach((button) => {
        button.addEventListener('click', function() {
            syncProrrogAltaToggle(this.getAttribute('data-prorrog-alta-toggle') || 'n');
        });
    });

    syncProrrogAltaToggle(document.getElementById('prorrog_gerar_alta')?.value || 'n');
    syncProrrogAltaBounds();

    const dataDisplay = document.getElementById('prorrog_data_alta_alt_display');
    if (dataDisplay) {
        dataDisplay.addEventListener('change', syncProrrogAltaHiddenFromDisplay);
        dataDisplay.addEventListener('blur', syncProrrogAltaHiddenFromDisplay);
    }
});

// Template de novas linhas (com "-" e "+")
function createProrrogationField() {
    const firstRow = document.querySelector('#fieldsContainer .field-container');
    const liberadaRef = firstRow ? firstRow.querySelector('[name="acomod1_pror"]') : null;

    const buildOptionsFromSelect = (selectEl) => {
        if (!selectEl) return '<option value=""> </option>';
        return Array.from(selectEl.options).map((opt) => {
            const value = String(opt.value || '');
            const text = String(opt.text || '');
            const dataValor = opt.getAttribute('data-valor');
            const dataAttr = dataValor !== null ? ` data-valor="${dataValor}"` : '';
            return `<option value="${value}"${dataAttr}>${text}</option>`;
        }).join('');
    };

    const optionsLiberada = buildOptionsFromSelect(liberadaRef);

    return `
        <div class="field-container form-group row">
            <input type="hidden" name="fk_internacao_pror" value="<?= $ultimoReg ?>">
            <input type="hidden" name="fk_usuario_pror" value="<?= $_SESSION["id_usuario"] ?>">

            <div class="form-group col-sm-2">
                <label class="control-label">Acomodação</label>
                <select onchange="generateProrJSON()" class="form-control-sm form-control" name="acomod1_pror">
                    ${optionsLiberada}
                </select>
            </div>

            <div class="form-group col-sm-2">
                <label class="control-label">Data inicial</label>
                <input onchange="generateProrJSON()" type="date" class="form-control-sm form-control" name="prorrog1_ini_pror">
            </div>

            <div class="form-group col-sm-2">
                <label class="control-label">Data final</label>
                <input onchange="generateProrJSON()" type="date" class="form-control-sm form-control" name="prorrog1_fim_pror">
            </div>

            <div class="form-group col-sm-1">
                <label class="control-label">Diárias</label>
                <input type="text" style="text-align:center; font-weight:600; background-color:darkgray" readonly class="form-control-sm form-control" name="diarias_1">
            </div>

            <div class="form-group col-sm-1">
                <label class="control-label">Isolamento</label>
                <select onchange="generateProrJSON()" class="form-control-sm form-control" name="isol_1_pror">
                    <option value="n">Não</option>
                    <option value="s">Sim</option>
                </select>
            </div>

            <div class="form-group col-sm-2" style="margin-top:25px">
                <button type="button" class="btn btn-remove" onclick="removeField(this)">-</button>
                <button type="button" class="btn btn-add" onclick="addField()">+</button>
            </div>

            <div class="error-message prorrog-alert" role="alert"></div>
        </div>
    `;
}

// Adiciona nova linha e encadeia datas
function addField() {
    const fieldsContainer = document.getElementById("fieldsContainer");
    const newField = createProrrogationField();
    fieldsContainer.insertAdjacentHTML("beforeend", newField);

    const fieldContainers = document.querySelectorAll("#fieldsContainer .field-container");
    if (fieldContainers.length > 1) {
        const lastContainer = fieldContainers[fieldContainers.length - 2];
        const newContainer = fieldContainers[fieldContainers.length - 1];
        const lastEndDate = lastContainer.querySelector('[name="prorrog1_fim_pror"]').value;
        if (lastEndDate) {
            newContainer.querySelector('[name="prorrog1_ini_pror"]').value = lastEndDate;
        }
    }
    generateProrJSON();
}

// Remove linha (apenas nas linhas novas)
function removeField(button) {
    const fieldContainer = button.closest(".field-container");
    if (!fieldContainer) return;
    fieldContainer.remove();
    generateProrJSON();
}

function normAcomodKey(value) {
    return (value || '')
        .toString()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/^\d+\s*-\s*/, '')
        .trim();
}

function getAcomodValor(selectEl) {
    if (!selectEl) return 0;
    const selected = selectEl.options?.[selectEl.selectedIndex];
    const fromOption = parseFloat(selected?.getAttribute('data-valor') || '0');

    const map = window.__PRORROG_ACOMOD_VALOR_MAP || {};
    const keyByValue = normAcomodKey(selectEl.value);
    const keyByText = normAcomodKey(selected?.text || '');

    const fromMapValue = parseFloat(map[keyByValue] ?? 'NaN');
    const fromMapText = parseFloat(map[keyByText] ?? 'NaN');

    if (!Number.isNaN(fromMapValue)) return fromMapValue;
    if (!Number.isNaN(fromMapText)) return fromMapText;
    if (!Number.isNaN(fromOption)) return fromOption;
    return 0;
}

window.recalculateProrrogSavings = function recalculateProrrogSavings() {
    document.querySelectorAll('#fieldsContainer .field-container').forEach((container) => {
        calculateDiarias(container);
    });
    generateProrJSON();
};

function isCompleteProrrogDate(value) {
    return /^\d{4}-\d{2}-\d{2}$/.test(String(value || ''));
}

function parseProrrogDate(value) {
    const raw = String(value || '').trim();
    if (!isCompleteProrrogDate(raw)) return null;
    const parts = raw.split('-').map(Number);
    return new Date(parts[0], parts[1] - 1, parts[2]);
}

function formatProrrogDateBR(value) {
    const raw = String(value || '').trim();
    if (!isCompleteProrrogDate(raw)) return raw;
    const parts = raw.split('-');
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

function setProrrogRowError(container, message) {
    const errorMessage = container ? container.querySelector(".error-message") : null;
    if (!errorMessage) return;
    errorMessage.textContent = message || "";
    errorMessage.style.display = message ? "block" : "none";
}

function validateProrrogOverlaps() {
    const rows = Array.from(document.querySelectorAll("#fieldsContainer .field-container"));
    const intervals = [];
    let valid = true;

    rows.forEach((container) => {
        const errorMessage = container.querySelector(".error-message");
        if (errorMessage && errorMessage.dataset.overlap === "1") {
            errorMessage.textContent = "";
            errorMessage.style.display = "none";
            delete errorMessage.dataset.overlap;
        }
    });

    rows.forEach((container, index) => {
        const ini = container.querySelector('[name="prorrog1_ini_pror"]')?.value || "";
        const fim = container.querySelector('[name="prorrog1_fim_pror"]')?.value || "";
        if (!isCompleteProrrogDate(ini) || !isCompleteProrrogDate(fim)) return;

        const iniDate = parseProrrogDate(ini);
        const fimDate = parseProrrogDate(fim);
        if (!iniDate || !fimDate || fimDate <= iniDate) return;

        intervals.push({ index, container, ini, fim, iniDate, fimDate });
    });

    for (let i = 0; i < intervals.length; i += 1) {
        for (let j = i + 1; j < intervals.length; j += 1) {
            const a = intervals[i];
            const b = intervals[j];
            if (a.iniDate < b.fimDate && b.iniDate < a.fimDate) {
                const msgA = `Período já informado em outra linha (${formatProrrogDateBR(b.ini)} a ${formatProrrogDateBR(b.fim)}).`;
                const msgB = `Período já informado em outra linha (${formatProrrogDateBR(a.ini)} a ${formatProrrogDateBR(a.fim)}).`;
                const errA = a.container.querySelector(".error-message");
                const errB = b.container.querySelector(".error-message");
                if (errA) errA.dataset.overlap = "1";
                if (errB) errB.dataset.overlap = "1";
                setProrrogRowError(a.container, msgA);
                setProrrogRowError(b.container, msgB);
                valid = false;
            }
        }
    }

    return valid;
}

function validateProrrogRequiredRows() {
    const selectProrrog = document.getElementById("select_prorrog");
    if (!selectProrrog || selectProrrog.value !== "s") return true;

    const rows = Array.from(document.querySelectorAll("#fieldsContainer .field-container"));
    let valid = true;

    rows.forEach((container) => {
        const acomod = container.querySelector('[name="acomod1_pror"]')?.value || "";
        const ini = container.querySelector('[name="prorrog1_ini_pror"]')?.value || "";
        const fim = container.querySelector('[name="prorrog1_fim_pror"]')?.value || "";
        const iniDate = parseProrrogDate(ini);
        const fimDate = parseProrrogDate(fim);
        let message = "";

        if (!acomod) {
            message = "Informe a acomodação da prorrogação.";
        } else if (!isCompleteProrrogDate(ini)) {
            message = "Informe a data inicial da prorrogação.";
        } else if (!isCompleteProrrogDate(fim)) {
            message = "Informe a data final da prorrogação.";
        } else if (!iniDate || !fimDate || fimDate <= iniDate) {
            message = "A data final precisa ser maior que a data inicial.";
        }

        if (message) {
            setProrrogRowError(container, message);
            valid = false;
        }
    });

    return valid;
}

function validateProrrogAltaFields() {
    const altaFlag = document.getElementById('prorrog_gerar_alta')?.value || 'n';
    if (altaFlag !== 's') return true;

    const hidden = document.getElementById('prorrog_data_alta_alt');
    const display = document.getElementById('prorrog_data_alta_alt_display');
    const motivo = document.getElementById('prorrog_tipo_alta_alt');
    const normalized = syncProrrogAltaHiddenFromDisplay();

    if (!normalized) {
        if (display) display.focus();
        openProrrogError("Informe a Data/Hora Alta no formato dd/mm/aaaa hh:mm.");
        return false;
    }

    const min = hidden?.dataset?.min || '';
    const max = hidden?.dataset?.max || '';
    if ((min && normalized < min) || (max && normalized > max)) {
        if (display) display.focus();
        openProrrogError("A Data/Hora Alta precisa estar entre a data da internação e a data atual.");
        return false;
    }

    if (!motivo || !motivo.value) {
        if (motivo) motivo.focus();
        openProrrogError("Informe o Motivo Alta.");
        return false;
    }

    return true;
}

// Calcula diárias e valida datas
function calculateDiarias(container) {
    const dataAtual = new Date().toISOString().split("T")[0];
    const maxDate = window.PRORROG_MAX_DATE || dataAtual;

    const dataInicial = container.querySelector('[name="prorrog1_ini_pror"]').value;
    const dataFinal = container.querySelector('[name="prorrog1_fim_pror"]').value;
    const diariasField = container.querySelector('[name="diarias_1"]');
    const errorMessage = container.querySelector(".error-message");

    errorMessage.textContent = ""; // limpa
    errorMessage.style.display = "none";

    if ((dataInicial && !isCompleteProrrogDate(dataInicial)) || (dataFinal && !isCompleteProrrogDate(dataFinal))) {
        if (diariasField) diariasField.value = "";
        generateProrJSON();
        return;
    }

    if (dataInicial && dataFinal) {
        const inicio = parseProrrogDate(dataInicial);
        const fim = parseProrrogDate(dataFinal);
        const max = parseProrrogDate(maxDate);
        if (!inicio || !fim) {
            if (diariasField) diariasField.value = "";
            generateProrJSON();
            return;
        }
        if (fim < inicio) {
            errorMessage.textContent = "A data final não pode ser menor que a data inicial.";
            errorMessage.style.display = "block";
            diariasField.value = "";
            return;
        }
        if (max && inicio > max) {
            errorMessage.textContent = "Não é permitido prorrogar após a data atual.";
            errorMessage.style.display = "block";
            openProrrogError("Não é permitido prorrogar após a data atual.");
            diariasField.value = "";
            return;
        }
        if (max && fim > max) {
            errorMessage.textContent = "Não é permitido prorrogar após a data atual.";
            errorMessage.style.display = "block";
            openProrrogError("Não é permitido prorrogar após a data atual.");
            diariasField.value = "";
            return;
        }

        const diffTime = Math.abs(fim - inicio);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        diariasField.value = diffDays;
        validateProrrogOverlaps();
        generateProrJSON();
    } else if (diariasField) {
        diariasField.value = "";
        validateProrrogOverlaps();
    }
}

// Validação automática apenas após sair do campo de data (blur/focusout)
document.getElementById("fieldsContainer").addEventListener("focusout", (event) => {
    const target = event.target;
    if (!target) return;
    const isDateField = target.matches('[name="prorrog1_ini_pror"], [name="prorrog1_fim_pror"]');
    if (!isDateField) return;

    const fieldContainer = target.closest(".field-container");
    if (fieldContainer) calculateDiarias(fieldContainer);
});

// Recalcula também durante mudança/input de datas para não deixar diárias/saving zerados
document.getElementById("fieldsContainer").addEventListener("change", (event) => {
    const target = event.target;
    if (!target) return;
    if (!target.matches('[name="prorrog1_ini_pror"], [name="prorrog1_fim_pror"]')) return;
    const fieldContainer = target.closest(".field-container");
    if (!fieldContainer) return;
    calculateDiarias(fieldContainer);
});

document.getElementById("fieldsContainer").addEventListener("input", (event) => {
    const target = event.target;
    if (!target) return;
    if (!target.matches('[name="prorrog1_ini_pror"], [name="prorrog1_fim_pror"]')) return;
    const fieldContainer = target.closest(".field-container");
    if (!fieldContainer) return;
    const ini = fieldContainer.querySelector('[name="prorrog1_ini_pror"]')?.value || '';
    const fim = fieldContainer.querySelector('[name="prorrog1_fim_pror"]')?.value || '';
    if ((ini && !isCompleteProrrogDate(ini)) || (fim && !isCompleteProrrogDate(fim))) {
        const errorMessage = fieldContainer.querySelector(".error-message");
        const diariasField = fieldContainer.querySelector('[name="diarias_1"]');
        if (errorMessage) {
            errorMessage.textContent = "";
            errorMessage.style.display = "none";
        }
        if (diariasField) diariasField.value = "";
        generateProrJSON();
        return;
    }
    calculateDiarias(fieldContainer);
});

document.getElementById("fieldsContainer").addEventListener("change", (event) => {
    const target = event.target;
    if (!target) return;
    if (!target.matches('[name="acomod1_pror"]')) return;
    const fieldContainer = target.closest(".field-container");
    if (!fieldContainer) return;
    generateProrJSON();
    syncProrrogAltaBounds();
});

document.addEventListener("DOMContentLoaded", () => {
    if (!window.__PRORROG_ACOMOD_VALOR_MAP || Object.keys(window.__PRORROG_ACOMOD_VALOR_MAP).length === 0) {
        const map = {};
        document.querySelectorAll('#container-prorrog select[name="acomod1_pror"] option').forEach((opt) => {
            const key = normAcomodKey(opt.value || opt.text || '');
            const val = parseFloat(opt.getAttribute('data-valor') || 'NaN');
            if (key && !Number.isNaN(val) && !(key in map)) {
                map[key] = val;
            }
        });
        window.__PRORROG_ACOMOD_VALOR_MAP = map;
    }
    if (typeof window.recalculateProrrogSavings === 'function') {
        window.recalculateProrrogSavings();
    }
});

// Gera JSON das prorrogações
function generateProrJSON() {
    // pega fk_internacao e usuario da primeira linha (evita conflito de IDs repetidos)
    const first = document.querySelector("#fieldsContainer .field-container");
    const fk_internacao_pror = first ? (first.querySelector('input[name="fk_internacao_pror"]')?.value || '') : '';
    const fk_usuario_pror = first ? (first.querySelector('input[name="fk_usuario_pror"]')?.value || '') : '';

    const fieldContainers = document.querySelectorAll("#fieldsContainer .field-container");
    const prorrogationsWithContext = Array.from(fieldContainers).map((container) => ({
        fk_internacao_pror: fk_internacao_pror,
        fk_usuario_pror: fk_usuario_pror,
        acomod1_pror: container.querySelector('[name="acomod1_pror"]').value,
        prorrog1_ini_pror: container.querySelector('[name="prorrog1_ini_pror"]').value,
        prorrog1_fim_pror: container.querySelector('[name="prorrog1_fim_pror"]').value,
        isol_1_pror: container.querySelector('[name="isol_1_pror"]').value,
        diarias_1: container.querySelector('[name="diarias_1"]').value,
        __container: container
    }));

    const prorrogations = prorrogationsWithContext.map((row) => ({
        fk_internacao_pror: row.fk_internacao_pror,
        fk_usuario_pror: row.fk_usuario_pror,
        acomod1_pror: row.acomod1_pror,
        prorrog1_ini_pror: row.prorrog1_ini_pror,
        prorrog1_fim_pror: row.prorrog1_fim_pror,
        isol_1_pror: row.isol_1_pror,
        diarias_1: row.diarias_1,
    }));

    const jsonData = {
        prorrogations
    };
document.getElementById("prorrogacoes-json").value = JSON.stringify(jsonData, null, 2);
}

document.addEventListener("submit", function(event) {
    const form = event.target;
    if (!form || !form.querySelector || !form.querySelector("#container-prorrog")) return;
    if (!validateProrrogAltaFields()) {
        event.preventDefault();
        return;
    }
    generateProrJSON();
    if (!validateProrrogRequiredRows()) {
        event.preventDefault();
        openProrrogError("Preencha acomodação, data inicial e data final da prorrogação antes de salvar.");
        const firstError = form.querySelector("#container-prorrog .prorrog-alert[style*='block']");
        if (firstError) firstError.scrollIntoView({ behavior: "smooth", block: "center" });
        return;
    }
    if (!validateProrrogOverlaps()) {
        event.preventDefault();
        openProrrogError("Existem prorrogações com período repetido ou sobreposto. Ajuste as datas antes de salvar.");
        const firstError = form.querySelector("#container-prorrog .prorrog-alert[style*='block']");
        if (firstError) firstError.scrollIntoView({ behavior: "smooth", block: "center" });
    }
}, true);

// Limpa todos os inputs (mantém só a primeira linha)
function clearProrrogInputs() {
    const fieldContainers = document.querySelectorAll("#fieldsContainer .field-container");
    fieldContainers.forEach((container, index) => {
        if (index > 0) {
            container.remove();
        } else {
            container.querySelectorAll('input:not([type="hidden"])').forEach((input) => {
                input.value = '';
            });
            container.querySelectorAll('select').forEach((select) => {
                select.selectedIndex = 0;
            });
        }
    });
    setFirstProrrogationDate();
    generateProrJSON();
}
</script>
<script>
window.prorrogAiConfig = Object.assign({}, window.prorrogAiConfig || {}, {
    baseUrl: <?= json_encode((string)$BASE_URL, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
});
</script>
<script src="<?= $BASE_URL ?>js/prorrogacao_ai.js"></script>
