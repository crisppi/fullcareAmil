<?php
/*------------------------------------------------------------
 *  BLOCO — PRORROGAÇÕES DINÂMICAS (completo)
 *-----------------------------------------------------------
 * Pré-requisitos (já existentes no seu contexto):
 *   - $conn, $BASE_URL
 *   - $intern['id_internacao']
 *   - $dados_acomodacao (array de strings)
 *   - $prorList (array de linhas)
 *-----------------------------------------------------------*/

/* helper de opções */
function optAcomod(array $lista, $sel = ''): string
{
    $out = '<option value=""></option>';
    sort($lista, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($lista as $a) {
        $aEsc = htmlspecialchars((string)$a, ENT_QUOTES, 'UTF-8');
        $selA = ($a === $sel) ? ' selected' : '';
        $out .= "<option value=\"{$aEsc}\"{$selA}>{$aEsc}</option>";
    }
    return $out;
}

function dateToTs(?string $date): ?int
{
    if (!$date) return null;
    $ts = strtotime(substr((string)$date, 0, 10));
    return $ts ? (int)$ts : null;
}
function daysExclusive(int $startTs, int $endTs): int
{
    if ($endTs <= $startTs) return 0;
    return (int)floor(($endTs - $startTs) / 86400);
}
function computeCoverageAndGaps(array $intervals, int $startTs, int $endTs): array
{
    $totalDays = daysExclusive($startTs, $endTs);
    if ($totalDays <= 0) {
        return [0, 0, []];
    }

    if (!$intervals) {
        return [0, $totalDays, [[date('d/m/Y', $startTs), date('d/m/Y', $endTs - 86400)]]];
    }

    usort($intervals, fn($a, $b) => $a['s'] <=> $b['s']);
    $merged = [];
    foreach ($intervals as $it) {
        if (empty($merged)) {
            $merged[] = $it;
            continue;
        }
        $lastIdx = count($merged) - 1;
        if ($it['s'] <= $merged[$lastIdx]['e']) {
            if ($it['e'] > $merged[$lastIdx]['e']) {
                $merged[$lastIdx]['e'] = $it['e'];
            }
            continue;
        }
        $merged[] = $it;
    }

    $coveredDays = 0;
    $gaps = [];
    $cursor = $startTs;
    foreach ($merged as $range) {
        if ($range['s'] > $cursor) {
            $gaps[] = [date('d/m/Y', $cursor), date('d/m/Y', $range['s'] - 86400)];
        }
        $coveredDays += daysExclusive($range['s'], $range['e']);
        if ($range['e'] > $cursor) {
            $cursor = $range['e'];
        }
    }

    if ($cursor < $endTs) {
        $gaps[] = [date('d/m/Y', $cursor), date('d/m/Y', $endTs - 86400)];
    }

    $missingDays = max(0, $totalDays - $coveredDays);
    return [$coveredDays, $missingDays, $gaps];
}

/* garante pelo menos 1 linha exibida */
$prorList = array_map(fn($r) => (array)$r, $prorList ?? []);
if (!$prorList) {
    $prorList[] = ['acomod' => '', 'ini' => '', 'fim' => '', 'diarias' => '', 'isolamento' => 'n'];
}

// Última data prorrogada para sugerir nova data inicial
$lastProrTs = null;
foreach ($prorList as $p) {
    $fimTs = dateToTs($p['fim'] ?? null);
    $iniTs = dateToTs($p['ini'] ?? null);
    $cand = $fimTs ?: $iniTs;
    if ($cand && ($lastProrTs === null || $cand > $lastProrTs)) {
        $lastProrTs = $cand;
    }
}
$defaultIni = $lastProrTs ? date('Y-m-d', $lastProrTs) : '';

// Sinalizador período em aberto
$pr_pendente_label = '';
$internStartTs = dateToTs($intern['data_intern_int'] ?? null);
$altaStmt = $conn->prepare("SELECT MAX(data_alta_alt) AS data_alta_alt FROM tb_alta WHERE fk_id_int_alt = :id");
$altaStmt->bindValue(':id', (int)$intern['id_internacao'], PDO::PARAM_INT);
$altaStmt->execute();
$altaRow = $altaStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$internEndTs = dateToTs($altaRow['data_alta_alt'] ?? null) ?: strtotime(date('Y-m-d'));
$todayTs = strtotime(date('Y-m-d'));
$maxProrTs = $internEndTs ? min($internEndTs, $todayTs) : $todayTs;
$maxProrDate = date('Y-m-d', $maxProrTs);

if ($internStartTs && $internEndTs && $internEndTs > $internStartTs) {
    $intervals = [];
    foreach ($prorList as $p) {
        $iniTs = dateToTs($p['ini'] ?? null);
        if (!$iniTs) continue;
        $fimBaseTs = dateToTs($p['fim'] ?? null) ?: ($internEndTs - 86400);
        $fimTs = $fimBaseTs + 86400;
        if ($fimTs <= $internStartTs || $iniTs >= $internEndTs) continue;
        $iniTs = max($iniTs, $internStartTs);
        $fimTs = min($fimTs, $internEndTs);
        $intervals[] = ['s' => $iniTs, 'e' => $fimTs];
    }
    [$coveredDays, $missingDays, $gaps] = computeCoverageAndGaps($intervals, $internStartTs, $internEndTs);
    if ($missingDays > 0) {
        $parts = array_map(fn($g) => $g[0] . ' → ' . $g[1], $gaps);
        $pr_pendente_label = $missingDays . ' dias | ' . implode(' • ', $parts);
    }
}
$dadosAltaProrrog = [];
if (isset($dados_alta) && is_array($dados_alta)) {
    $dadosAltaProrrog = $dados_alta;
    sort($dadosAltaProrrog, SORT_ASC);
}
$prorrogAltaDataValue = '';
if (!empty($altaDataHoraValue)) {
    $prorrogAltaDataValue = (string)$altaDataHoraValue;
} elseif (!empty($altaAtual['data_alta_alt'])) {
    $tsAltaProrrog = strtotime((string)$altaAtual['data_alta_alt']);
    if ($tsAltaProrrog) {
        $prorrogAltaDataValue = date('Y-m-d\TH:i', $tsAltaProrrog);
    }
}
$prorrogAltaAtiva = $prorrogAltaDataValue !== '' || !empty($altaAtual['tipo_alta_alt']);
?>
<style>
/* ===================== PRORROGAÇÕES — ESTILO ===================== */
/* Linha em grade (>=768px) */
@media (min-width: 768px) {
    .pror-row .form-grid {
        display: grid;
        grid-template-columns:
            clamp(180px, 24vw, 320px)
            /* Acomodação com largura controlada */
            160px
            /* Data inicial */
            160px
            /* Data final   */
            110px
            /* Diárias      */
            140px
            /* Isolamento   */
            110px;
        /* Botões       */
        column-gap: 12px;
        align-items: end;
    }

    .pror-row .form-group {
        margin: 0 !important;
    }

    .pror-row .form-control,
    .pror-row .btn {
        height: calc(1.5em + .5rem + 2px);
        padding: .25rem .5rem;
        font-size: .875rem;
        /* .form-control-sm */
        line-height: 1.5;
    }

    .pror-row .w-btns>.btn-group {
        display: flex;
        gap: 8px;
    }
}

/* Empilhado em telas pequenas */
@media (max-width: 767.98px) {
    .pror-row .form-grid {
        display: grid;
        grid-template-columns: 1fr;
        row-gap: 10px;
    }
}

/* Aparência da linha */
.pror-row {
    border: 1px solid rgba(0, 0, 0, .08);
    background: #f5f5f9;
}

.pror-row label {
    font-size: .8rem;
    margin-bottom: .2rem;
    display: block;
}

.pror-row .diarias-readonly {
    text-align: center;
    font-weight: 700;
    background: #f1f3f5;
}

/* Popup rápido */
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

.custom-dialog-footer {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-top: 10px
}

.custom-dialog-header .close {
    cursor: pointer;
    font-size: 1.5rem
}

.custom-dialog-footer .confirm {
    background: #28a745;
    color: #fff;
    border: none;
    border-radius: 5px;
    padding: 10px 20px
}

.custom-dialog-footer .cancel {
    background: #dc3545;
    color: #fff;
    border: none;
    border-radius: 5px;
    padding: 10px 20px
}

.custom-dialog-footer .confirm:hover {
    background: #218838
}

.custom-dialog-footer .cancel:hover {
    background: #c82333
}

.prorrog-pendente-badge {
    background: #ffe7ef;
    color: #b42346;
    border: 1px solid #e55353;
    border-radius: 999px;
    padding: 6px 14px;
    font-weight: 600;
    font-size: 0.85rem;
    white-space: nowrap;
    margin-left: auto;
}
.prorrog-head {
    position: relative;
    min-height: 34px;
    margin-bottom: 12px;
}
.prorrog-head h4 {
    margin: 0;
}
.prorrog-head .prorrog-pendente-badge {
    position: absolute;
    top: 0;
    right: 0;
}
.prorrog-inline-alta {
    margin-top: 16px;
    padding: 16px 18px;
    border: 1px solid #e6dced;
    border-radius: 16px;
    background: linear-gradient(180deg, #fcf9ff 0%, #f7f2fb 100%);
}
.prorrog-inline-alta__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}
.prorrog-inline-alta__title {
    margin: 0;
    color: #3a184f;
    font-size: 1rem;
    font-weight: 600;
}
.prorrog-inline-alta__hint {
    margin: 4px 0 0;
    color: #6f5a7e;
    font-size: .84rem;
}
.prorrog-inline-alta__toggle {
    display: inline-flex;
    gap: 8px;
    flex-wrap: wrap;
}
.prorrog-inline-alta__toggle .btn {
    min-width: 84px;
    border-radius: 999px;
    font-weight: 600;
}
.prorrog-inline-alta__toggle .btn.is-active {
    background: #5e2363;
    border-color: #5e2363;
    color: #fff;
}
.prorrog-inline-alta__fields {
    display: none;
}
.prorrog-inline-alta__fields.is-visible {
    display: block;
}
@media (max-width: 991.98px) {
    .prorrog-head {
        min-height: 0;
    }
    .prorrog-head .prorrog-pendente-badge {
        position: static;
        display: inline-flex;
        margin-top: 8px;
    }
}
</style>

<div>
    <div class="prorrog-head">
        <h4 class="mb-0">Editar Prorrogação</h4>
        <?php if (!empty($pr_pendente_label)): ?>
            <span class="prorrog-pendente-badge">Período em aberto: <?= htmlspecialchars($pr_pendente_label) ?></span>
        <?php endif; ?>
    </div>
    <script>
        window.PRORROG_MAX_DATE = "<?= htmlspecialchars($maxProrDate, ENT_QUOTES, 'UTF-8') ?>";
    </script>

    <!-- chaves principais -->
    <input type="hidden" name="type" value="edit_prorrogacao">
    <input type="hidden" id="fk_internacao_pror" name="fk_internacao_pror" value="<?= (int)$intern['id_internacao'] ?>">
    <input type="hidden" id="fk_usuario_pror" name="fk_usuario_pror" value="<?= (int)($_SESSION['id_usuario'] ?? 0) ?>">
    <input type="hidden" name="select_prorrog" id="select_prorrog_hidden" value="s">

    <!-- JSON oculto -->
    <input type="hidden" id="prorrogacoes_json" name="prorrogacoes_json">
    <input type="hidden" id="prorrog_gerar_alta" name="prorrog_gerar_alta" value="<?= $prorrogAltaAtiva ? 's' : 'n' ?>">
    <input type="hidden" id="prorrog_fk_usuario_alt" name="prorrog_fk_usuario_alt" value="<?= (int)($_SESSION['id_usuario'] ?? 0) ?>">
    <input type="hidden" id="prorrog_usuario_alt" name="prorrog_usuario_alt" value="<?= htmlspecialchars((string)($_SESSION['email_user'] ?? 'sistema'), ENT_QUOTES, 'UTF-8') ?>">

    <div id="prorContainer">
        <?php foreach ($prorList as $i => $p): $idx = (int)$i; ?>
        <div class="pror-row rounded p-3 mb-2">
            <input type="hidden" name="pror[<?= $idx ?>][id_prorrogacao]" value="<?= (int)($p['id_prorrogacao'] ?? 0) ?>">
            <div class="form-grid">
                <div class="form-group w-acom">
                    <label>Acomodação</label>
                    <select class="form-control form-control-sm" name="pror[<?= $idx ?>][acomod]">
                        <?= optAcomod($dados_acomodacao, $p['acomod'] ?? '') ?>
                    </select>
                </div>

                <div class="form-group w-ini">
                    <label>Data inicial</label>
                    <?php
                    $iniVal = $p['ini'] ?? '';
                    if ($iniVal === '' && $defaultIni) {
                        $iniVal = $defaultIni;
                    }
                    ?>
                    <input type="date" class="form-control form-control-sm" name="pror[<?= $idx ?>][ini]"
                        value="<?= htmlspecialchars($iniVal) ?>">
                </div>

                <div class="form-group w-fim">
                    <label>Data final</label>
                    <input type="date" class="form-control form-control-sm" name="pror[<?= $idx ?>][fim]"
                        value="<?= htmlspecialchars($p['fim'] ?? '') ?>">
                </div>

                <div class="form-group w-dia">
                    <label>Diárias</label>
                    <input type="text" class="form-control form-control-sm diarias-readonly"
                        name="pror[<?= $idx ?>][diarias]" value="<?= htmlspecialchars($p['diarias'] ?? '') ?>" readonly>
                </div>

                <div class="form-group w-iso">
                    <label>Isolamento</label>
                    <?php $iso = $p['isolamento'] ?? 'n'; ?>
                    <select class="form-control form-control-sm" name="pror[<?= $idx ?>][isolamento]">
                        <option value="n" <?= $iso === 'n' ? 'selected' : '' ?>>Não</option>
                        <option value="s" <?= $iso === 's' ? 'selected' : '' ?>>Sim</option>
                    </select>
                </div>

                <div class="form-group w-btns">
                    <label style="visibility:hidden">Ações</label>
                    <div class="btn-group">
                        <button type="button" class="btn btn-success btn-sm btn-add-pror">+</button>
                        <button type="button" class="btn btn-danger  btn-sm btn-del-pror">−</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="prorrog-inline-alta">
        <div class="prorrog-inline-alta__header">
            <div>
                <h5 class="prorrog-inline-alta__title">Paciente teve alta nesta prorrogação?</h5>
                <p class="prorrog-inline-alta__hint">Se sim, já salvamos a alta junto da atualização da internação.</p>
            </div>
            <div class="prorrog-inline-alta__toggle" role="group" aria-label="Paciente teve alta">
                <button type="button" class="btn btn-outline-secondary<?= !$prorrogAltaAtiva ? ' is-active' : '' ?>" data-prorrog-inline-alta="n">Não</button>
                <button type="button" class="btn btn-outline-primary<?= $prorrogAltaAtiva ? ' is-active' : '' ?>" data-prorrog-inline-alta="s">Sim</button>
            </div>
        </div>
        <div class="prorrog-inline-alta__fields<?= $prorrogAltaAtiva ? ' is-visible' : '' ?>" id="prorrog-inline-alta-fields">
            <div class="row g-2">
                <div class="form-group col-sm-3">
                    <label class="control-label" for="prorrog_data_alta_alt">Data/Hora Alta</label>
                    <input type="datetime-local" class="form-control form-control-sm" id="prorrog_data_alta_alt"
                        name="prorrog_data_alta_alt" step="60"
                        value="<?= htmlspecialchars($prorrogAltaDataValue, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group col-sm-4">
                    <label class="control-label" for="prorrog_tipo_alta_alt">Motivo Alta</label>
                    <select class="form-control form-control-sm" id="prorrog_tipo_alta_alt" name="prorrog_tipo_alta_alt">
                        <option value="">Selecione o motivo da alta</option>
                        <?php foreach ($dadosAltaProrrog as $altaMotivo): ?>
                            <option value="<?= htmlspecialchars((string)$altaMotivo, ENT_QUOTES, 'UTF-8') ?>" <?= (($altaAtual['tipo_alta_alt'] ?? '') === $altaMotivo) ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string)$altaMotivo, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <hr>
</div>

<!-- Popup de confirmação -->
<div id="customDialog" class="custom-dialog" role="dialog" aria-modal="true" aria-labelledby="dlgTitle">
    <div class="custom-dialog-content">
        <div class="custom-dialog-header">
            <span id="dlgTitle">Atenção</span>
            <span class="close" onclick="closeDialog()">&times;</span>
        </div>
        <div class="custom-dialog-body">
            <p>Deseja prorrogar por mais de 15&nbsp;dias?</p>
        </div>
        <div class="custom-dialog-footer">
            <button class="confirm" onclick="confirmDialog(true)">Sim</button>
            <button class="cancel" onclick="confirmDialog(false)">Não</button>
        </div>
    </div>
</div>

<script>
/* ===================== PRORROGAÇÕES — JS ===================== */
/* Popup */
let dialogResolve = null;

function openDialog() {
    const el = document.getElementById('customDialog');
    const footer = el ? el.querySelector('.custom-dialog-footer') : null;
    if (footer) footer.style.display = 'flex';
    document.getElementById('customDialog').style.display = 'block';
}

function closeDialog() {
    document.getElementById('customDialog').style.display = 'none';
}

function confirmDialog(res) {
    closeDialog();
    if (dialogResolve) dialogResolve(res);
}

function askOver15() {
    return new Promise(r => {
        dialogResolve = r;
        openDialog();
    });
}

function openErrorDialog(msg) {
    const el = document.getElementById('customDialog');
    if (!el) return;
    const body = el.querySelector('.custom-dialog-body');
    if (body) body.textContent = msg;
    const footer = el.querySelector('.custom-dialog-footer');
    if (footer) footer.style.display = 'none';
    el.style.display = 'block';
}

/* Utilidades */
function diffDays(d1, d2) {
    return Math.ceil((new Date(d2) - new Date(d1)) / 86400000);
}

function parseDateOnly(value) {
    const raw = (value || '').toString().trim();
    if (!raw) return null;

    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
        const [y, m, d] = raw.split('-').map(Number);
        return new Date(y, m - 1, d);
    }

    if (/^\d{2}\/\d{2}\/\d{4}$/.test(raw)) {
        const [d, m, y] = raw.split('/').map(Number);
        return new Date(y, m - 1, d);
    }

    const normalized = raw.length >= 10 ? raw.slice(0, 10) : raw;
    const fallback = new Date(normalized + 'T00:00:00');
    return Number.isNaN(fallback.getTime()) ? null : fallback;
}

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

function getSuggestedAltaDateFromProrrog() {
    const rows = Array.from(document.querySelectorAll('#prorContainer .pror-row'));
    for (let i = rows.length - 1; i >= 0; i -= 1) {
        const fim = rows[i].querySelector('[name$="[fim]"]')?.value || '';
        if (fim) return formatDateToLocalDateTime(fim);
        const ini = rows[i].querySelector('[name$="[ini]"]')?.value || '';
        if (ini) return formatDateToLocalDateTime(ini);
    }
    const internDate = getInternacaoDateForProrrog();
    if (!(internDate instanceof Date) || Number.isNaN(internDate.getTime())) {
        return '';
    }
    return formatDateToLocalDateTime(internDate.toISOString().split('T')[0]);
}

function syncProrrogInlineAltaBounds() {
    const dataInput = document.getElementById('prorrog_data_alta_alt');
    if (!dataInput) return;

    const internDate = getInternacaoDateForProrrog();
    dataInput.max = formatLocalDateTimeNow();
    if (internDate instanceof Date && !Number.isNaN(internDate.getTime())) {
        dataInput.min = formatDateToLocalDateTime(internDate.toISOString().split('T')[0]);
    } else {
        dataInput.removeAttribute('min');
    }
}

function syncProrrogInlineAlta(flag) {
    const hidden = document.getElementById('prorrog_gerar_alta');
    const fields = document.getElementById('prorrog-inline-alta-fields');
    const btnNao = document.querySelector('[data-prorrog-inline-alta="n"]');
    const btnSim = document.querySelector('[data-prorrog-inline-alta="s"]');
    const dataInput = document.getElementById('prorrog_data_alta_alt');
    const motivoInput = document.getElementById('prorrog_tipo_alta_alt');
    const enabled = flag === 's';

    if (hidden) hidden.value = enabled ? 's' : 'n';
    if (fields) fields.classList.toggle('is-visible', enabled);
    if (btnNao) btnNao.classList.toggle('is-active', !enabled);
    if (btnSim) btnSim.classList.toggle('is-active', enabled);
    if (dataInput) {
        dataInput.required = enabled;
        syncProrrogInlineAltaBounds();
        if (!enabled) {
            dataInput.value = '';
        } else if (!dataInput.value) {
            dataInput.value = getSuggestedAltaDateFromProrrog() || formatLocalDateTimeNow();
        }
    }
    if (motivoInput) {
        motivoInput.required = enabled;
        if (!enabled) {
            motivoInput.value = '';
        }
    }
}

function getInternacaoDateForProrrog() {
    const internDateField = document.getElementById('data_intern_int');
    if (!internDateField || !internDateField.value) return null;
    const internDate = parseDateOnly(internDateField.value);
    if (!(internDate instanceof Date) || Number.isNaN(internDate.getTime())) {
        return null;
    }
    return internDate;
}

function setFirstProrrogationDate() {
    const firstInitialDateField = document.querySelector('#prorContainer .pror-row [name$="[ini]"]');
    const firstFinalDateField = document.querySelector('#prorContainer .pror-row [name$="[fim]"]');
    const internDate = getInternacaoDateForProrrog();
    if (!firstInitialDateField || !internDate || firstInitialDateField.value) return;
    const internDateFormatted = internDate.toISOString().split('T')[0];
    firstInitialDateField.value = internDateFormatted;
    if (firstFinalDateField) firstFinalDateField.min = internDateFormatted;
}

function reindexNames() {
    $('#prorContainer .pror-row').each(function(i) {
        $(this).find('[name]').each(function() {
            this.name = this.name.replace(/pror\[\d+]/, 'pror[' + i + ']');
        });
    });
}

function syncJson() {
    const linhas = [];
    $('#prorContainer .pror-row').each(function() {
        const $r = $(this);
        linhas.push({
            id_prorrogacao: parseInt($r.find('[name$="[id_prorrogacao]"]').val() || '0', 10) || 0,
            acomod: $r.find('[name$="[acomod]"]').val() || '',
            ini: $r.find('[name$="[ini]"]').val() || '',
            fim: $r.find('[name$="[fim]"]').val() || '',
            diarias: $r.find('[name$="[diarias]"]').val() || '',
            isolamento: $r.find('[name$="[isolamento]"]').val() || 'n'
        });
    });
    $('#prorrogacoes_json').val(JSON.stringify(linhas));
}

function recalcRow($row, changedName) {
    const ini = $row.find('[name$="[ini]"]').val();
    const fim = $row.find('[name$="[fim]"]').val();
    const $dia = $row.find('[name$="[diarias]"]');
    const maxDate = window.PRORROG_MAX_DATE;
    const internDate = getInternacaoDateForProrrog();

    if (changedName && changedName.endsWith('[ini]')) {
        $row.find('[name$="[fim]"]').attr('min', ini || null);
    }

    if (internDate && ini) {
        const initialDate = parseDateOnly(ini);
        if (!(initialDate instanceof Date) || Number.isNaN(initialDate.getTime()) || initialDate < internDate) {
            openErrorDialog('A data inicial da prorrogação não pode ser menor que a data de internação.');
            $row.find('[name$="[ini]"]').val('').focus();
            $dia.val('');
            syncJson();
            return;
        }
    }

    const iniDate = ini ? parseDateOnly(ini) : null;
    const fimDate = fim ? parseDateOnly(fim) : null;
    const maxAllowedDate = maxDate ? parseDateOnly(maxDate) : null;

    if (maxAllowedDate && iniDate && iniDate > maxAllowedDate) {
        openErrorDialog('Não é permitido prorrogar após a data atual/alta.');
        $row.find('[name$="[ini]"]').val('');
        $dia.val('');
        syncJson();
        return;
    }
    if (maxAllowedDate && fimDate && fimDate > maxAllowedDate) {
        openErrorDialog('Não é permitido prorrogar após a data atual/alta.');
        $row.find('[name$="[fim]"]').val('');
        $dia.val('');
        syncJson();
        return;
    }

    if (iniDate && fimDate && fimDate >= iniDate) {
        const dias = diffDays(ini, fim);
        if (dias > 15) {
            askOver15().then(ok => {
                if (!ok) {
                    if (changedName && changedName.endsWith('[fim]')) {
                        $row.find('[name$="[fim]"]').val('');
                    } else if (changedName && changedName.endsWith('[ini]')) {
                        $row.find('[name$="[ini]"]').val('');
                    }
                    $dia.val('');
                    syncJson();
                } else {
                    $dia.val(dias);
                    syncJson();
                }
            });
            return;
        }
        $dia.val(dias);
    } else {
        $dia.val('');
    }
    syncJson();
}

/* Inicialização */
$(function() {
    const $container = $('#prorContainer');
    document.querySelectorAll('[data-prorrog-inline-alta]').forEach(function(button) {
        button.addEventListener('click', function() {
            syncProrrogInlineAlta(this.getAttribute('data-prorrog-inline-alta') || 'n');
        });
    });
    syncProrrogInlineAltaBounds();

    function getSuggestedIni() {
        let last = '';
        $container.find('.pror-row').each(function() {
            const $row = $(this);
            const fim = $row.find('[name$="[fim]"]').val();
            const ini = $row.find('[name$="[ini]"]').val();
            if (fim) last = fim;
            else if (ini) last = ini;
        });
        return last;
    }

    // change das datas com cálculo e popup
    $container.on('change', 'input[type="date"]', function() {
        recalcRow($(this).closest('.pror-row'), this.name);
        syncProrrogInlineAltaBounds();
    });

    // adicionar linha
    $container.on('click', '.btn-add-pror', function() {
        const suggestedIni = getSuggestedIni();
        const $clone = $container.find('.pror-row').last().clone();
        $clone.find('[name]').each(function() {
            this.value = '';
        });
        $clone.find('[name$="[fim]"]').removeAttr('min');
        if (suggestedIni) {
            $clone.find('[name$="[ini]"]').val(suggestedIni);
            $clone.find('[name$="[fim]"]').attr('min', suggestedIni);
        }
        $container.append($clone);
        reindexNames();
        recalcRow($clone);
        syncJson();
    });

    // remover linha (mínimo 1)
    $container.on('click', '.btn-del-pror', function() {
        if ($container.find('.pror-row').length > 1) {
            $(this).closest('.pror-row').remove();
            reindexNames();
            syncJson();
        }
    });

    // mudanças gerais (exceto data já tratada)
    $container.on('input change', 'select,input:not([type="date"])', syncJson);

    // primeiro sync (aplica min do fim se ini existir)
    $container.find('.pror-row').each(function() {
        const $row = $(this);
        const ini = $row.find('[name$="[ini]"]').val();
        if (ini) $row.find('[name$="[fim]"]').attr('min', ini);
        recalcRow($row);
    });

    setFirstProrrogationDate();
    const internDateField = document.getElementById('data_intern_int');
    if (internDateField) {
        const syncInitialDate = function() {
            setFirstProrrogationDate();
            const $firstRow = $container.find('.pror-row').first();
            if ($firstRow.length) recalcRow($firstRow, 'pror[0][ini]');
            syncJson();
        };
        ['change', 'input', 'blur'].forEach(function(evtName) {
            internDateField.addEventListener(evtName, syncInitialDate);
        });
    }

    // preenche datas iniciais vazias com a última data prorrogada
    let last = '';
    $container.find('.pror-row').each(function() {
        const $row = $(this);
        const $ini = $row.find('[name$="[ini]"]');
        const $fim = $row.find('[name$="[fim]"]');
        const ini = $ini.val();
        const fim = $fim.val();
        if (!ini && last) {
            $ini.val(last);
            $fim.attr('min', last);
            recalcRow($row);
        }
        if (fim) last = fim;
        else if (ini) last = ini;
    });
    syncJson();
    syncProrrogInlineAlta(document.getElementById('prorrog_gerar_alta')?.value || 'n');
});
</script>
