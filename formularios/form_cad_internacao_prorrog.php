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
    margin-bottom: 5px;
    font-weight: bold;
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

        <!-- Linha inicial (sem botão "-") -->
        <div class="field-container form-group row">
            <input type="hidden" id="fk_internacao_pror" name="fk_internacao_pror" value="<?= $ultimoReg ?>">
            <input type="hidden" id="fk_usuario_pror" name="fk_usuario_pror" value="<?= $_SESSION["id_usuario"] ?>">

            <div class="form-group col-sm-2">
                <label class="control-label" for="acomod_solicitada_pror">Acomod. Solicitada</label>
                <select onchange="generateProrJSON()" class="form-control-sm form-control" id="acomod_solicitada_pror"
                    name="acomod_solicitada_pror">
                    <option value=""> </option>
                    <?php sort($dados_acomodacao, SORT_ASC);
                    foreach ($dados_acomodacao as $acomd) { ?>
                    <option value="<?= $acomd; ?>" data-valor="<?= htmlspecialchars((string)prorrogValorAcomod((string)$acomd, $prorrogAcomodValorMap), ENT_QUOTES, 'UTF-8'); ?>"><?= $acomd; ?></option>
                    <?php } ?>
                </select>
            </div>

            <div class="form-group col-sm-2">
                <label class="control-label" for="acomod1_pror">Acomod. Liberada</label>
                <select onchange="generateProrJSON()" class="form-control-sm form-control" id="acomod1_pror"
                    name="acomod1_pror">
                    <option value=""> </option>
                    <?php sort($dados_acomodacao, SORT_ASC);
                    foreach ($dados_acomodacao as $acomd) { ?>
                    <option value="<?= $acomd; ?>" data-valor="<?= htmlspecialchars((string)prorrogValorAcomod((string)$acomd, $prorrogAcomodValorMap), ENT_QUOTES, 'UTF-8'); ?>"><?= $acomd; ?></option>
                    <?php } ?>
                </select>
            </div>

            <div class="form-group col-sm-2">
                <label class="control-label" for="prorrog1_ini_pror">Data inicial</label>
                <input onchange="generateProrJSON()" type="date" class="form-control-sm form-control"
                    id="prorrog1_ini_pror" name="prorrog1_ini_pror">
            </div>

            <div class="form-group col-sm-2">
                <label class="control-label" for="prorrog1_fim_pror">Data final</label>
                <input onchange="generateProrJSON()" type="date" class="form-control-sm form-control"
                    id="prorrog1_fim_pror" name="prorrog1_fim_pror">
            </div>

            <div class="form-group col-sm-1">
                <label class="control-label" for="diarias_1">Diárias</label>
                <input type="text" style="text-align:center; font-weight:600; background-color:darkgray" readonly
                    class="form-control-sm form-control" id="diarias_1" name="diarias_1">
            </div>

            <div class="form-group col-sm-1">
                <label class="control-label" for="isol_1_pror">Isolamento</label>
                <select onchange="generateProrJSON()" class="form-control-sm form-control" id="isol_1_pror"
                    name="isol_1_pror">
                    <option value="n">Não</option>
                    <option value="s">Sim</option>
                </select>
            </div>

            <div class="form-group col-sm-1">
                <label class="control-label" for="saving_estimado_pror">Saving (R$)</label>
                <input onchange="generateProrJSON()" type="number" step="0.01" min="0" class="form-control-sm form-control"
                    id="saving_estimado_pror" name="saving_estimado_pror" placeholder="0,00">
            </div>

            <div class="form-group col-sm-2" style="margin-top:25px">
                <!-- apenas (+) na primeira linha -->
                <button type="button" class="btn btn-add" onclick="addField()">+</button>
            </div>

            <div class="error-message prorrog-alert" role="alert"></div>
        </div>
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
                    <label class="control-label" for="prorrog_data_alta_alt">Data/Hora Alta</label>
                    <input type="datetime-local" class="form-control-sm form-control" id="prorrog_data_alta_alt"
                        name="prorrog_data_alta_alt" step="60">
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
    const dataInternacao = getInternacaoDateForProrrog();
    const firstContainer = document.querySelector(".field-container");
    if (!firstContainer || !dataInternacao) return;

    const iniInput = firstContainer.querySelector('[name="prorrog1_ini_pror"]');
    if (iniInput) {
        iniInput.value = dataInternacao;
    }
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
    dataInput.max = now;
    if (internDate) {
        dataInput.min = formatDateToLocalDateTime(internDate);
    } else {
        dataInput.removeAttribute('min');
    }
}

function syncProrrogAltaToggle(flag) {
    const hidden = document.getElementById('prorrog_gerar_alta');
    const fields = document.getElementById('prorrog-alta-fields');
    const btnNao = document.getElementById('prorrog-alta-btn-nao');
    const btnSim = document.getElementById('prorrog-alta-btn-sim');
    const dataInput = document.getElementById('prorrog_data_alta_alt');
    const motivoInput = document.getElementById('prorrog_tipo_alta_alt');
    const enabled = flag === 's';

    if (hidden) hidden.value = enabled ? 's' : 'n';
    if (fields) fields.classList.toggle('is-visible', enabled);
    if (btnNao) btnNao.classList.toggle('is-active', !enabled);
    if (btnSim) btnSim.classList.toggle('is-active', enabled);
    if (dataInput) {
        dataInput.required = enabled;
        syncProrrogAltaBounds();
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

document.addEventListener('DOMContentLoaded', function() {
    const toggleButtons = document.querySelectorAll('[data-prorrog-alta-toggle]');
    toggleButtons.forEach((button) => {
        button.addEventListener('click', function() {
            syncProrrogAltaToggle(this.getAttribute('data-prorrog-alta-toggle') || 'n');
        });
    });

    syncProrrogAltaToggle(document.getElementById('prorrog_gerar_alta')?.value || 'n');
    syncProrrogAltaBounds();
});

// Template de novas linhas (com "-" e "+")
function createProrrogationField() {
    const firstRow = document.querySelector('.field-container');
    const solicitadaRef = firstRow ? firstRow.querySelector('[name="acomod_solicitada_pror"]') : null;
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

    const optionsSolicitada = buildOptionsFromSelect(solicitadaRef);
    const optionsLiberada = buildOptionsFromSelect(liberadaRef);

    return `
        <div class="field-container form-group row">
            <input type="hidden" name="fk_internacao_pror" value="<?= $ultimoReg ?>">
            <input type="hidden" name="fk_usuario_pror" value="<?= $_SESSION["id_usuario"] ?>">

            <div class="form-group col-sm-2">
                <label class="control-label">Acomod. Solicitada</label>
                <select onchange="generateProrJSON()" class="form-control-sm form-control" name="acomod_solicitada_pror">
                    ${optionsSolicitada}
                </select>
            </div>

            <div class="form-group col-sm-2">
                <label class="control-label">Acomod. Liberada</label>
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

            <div class="form-group col-sm-1">
                <label class="control-label">Saving (R$)</label>
                <input onchange="generateProrJSON()" type="number" step="0.01" min="0" class="form-control-sm form-control" name="saving_estimado_pror" placeholder="0,00">
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

    const fieldContainers = document.querySelectorAll(".field-container");
    if (fieldContainers.length > 1) {
        const lastContainer = fieldContainers[fieldContainers.length - 2];
        const newContainer = fieldContainers[fieldContainers.length - 1];
        const lastEndDate = lastContainer.querySelector('[name="prorrog1_fim_pror"]').value;
        if (lastEndDate) newContainer.querySelector('[name="prorrog1_ini_pror"]').value = lastEndDate;
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

function calculateProrSaving(container) {
    if (!container) return;
    const solicitadaSel = container.querySelector('[name="acomod_solicitada_pror"]');
    const liberadaSel = container.querySelector('[name="acomod1_pror"]');
    const diarias = parseFloat(container.querySelector('[name="diarias_1"]')?.value || '0');
    const savingInput = container.querySelector('[name="saving_estimado_pror"]');
    if (!savingInput) return;

    const valorSolicitada = getAcomodValor(solicitadaSel);
    const valorLiberada = getAcomodValor(liberadaSel);

    let saving = 0;
    if (!Number.isNaN(valorSolicitada) && !Number.isNaN(valorLiberada) && !Number.isNaN(diarias) && diarias > 0) {
        saving = (valorSolicitada - valorLiberada) * diarias;
    }
    savingInput.value = saving.toFixed(2);
}

window.recalculateProrrogSavings = function recalculateProrrogSavings() {
    document.querySelectorAll('#fieldsContainer .field-container').forEach((container) => {
        calculateDiarias(container);
        calculateProrSaving(container);
    });
    generateProrJSON();
};

// Calcula diárias e valida datas
function calculateDiarias(container) {
    const dataAtual = new Date().toISOString().split("T")[0];
    const dataInternacao = getInternacaoDateForProrrog();
    const maxDate = window.PRORROG_MAX_DATE || dataAtual;

    const dataInicial = container.querySelector('[name="prorrog1_ini_pror"]').value;
    const dataFinal = container.querySelector('[name="prorrog1_fim_pror"]').value;
    const diariasField = container.querySelector('[name="diarias_1"]');
    const errorMessage = container.querySelector(".error-message");

    errorMessage.textContent = ""; // limpa
    errorMessage.style.display = "none";

    if (dataInicial) {
        const inicio = new Date(dataInicial);
        const internacao = dataInternacao ? new Date(dataInternacao) : null;
        if (internacao && inicio < internacao) {
            errorMessage.textContent = "A data inicial não pode ser menor que a data de internação.";
            errorMessage.style.display = "block";
            const dataInicialInput = container.querySelector('[name="prorrog1_ini_pror"]');
            if (dataInicialInput) {
                dataInicialInput.value = "";
                dataInicialInput.focus();
            }
            if (diariasField) diariasField.value = "";
            generateProrJSON();
            return;
        }
    }

    if (dataInicial && dataFinal) {
        const inicio = new Date(dataInicial);
        const fim = new Date(dataFinal);
        if (fim < inicio) {
            errorMessage.textContent = "A data final não pode ser menor que a data inicial.";
            errorMessage.style.display = "block";
            diariasField.value = "";
            return;
        }
        if (dataInicial && new Date(dataInicial) > new Date(maxDate)) {
            errorMessage.textContent = "Não é permitido prorrogar após a data atual.";
            errorMessage.style.display = "block";
            openProrrogError("Não é permitido prorrogar após a data atual.");
            diariasField.value = "";
            return;
        }
        if (dataFinal && new Date(dataFinal) > new Date(maxDate)) {
            errorMessage.textContent = "Não é permitido prorrogar após a data atual.";
            errorMessage.style.display = "block";
            openProrrogError("Não é permitido prorrogar após a data atual.");
            diariasField.value = "";
            return;
        }

        const diffTime = Math.abs(fim - inicio);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        diariasField.value = diffDays;
        calculateProrSaving(container);
        generateProrJSON();
    } else if (diariasField) {
        diariasField.value = "";
        calculateProrSaving(container);
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
    calculateDiarias(fieldContainer);
});

document.getElementById("fieldsContainer").addEventListener("change", (event) => {
    const target = event.target;
    if (!target) return;
    if (!target.matches('[name="acomod_solicitada_pror"], [name="acomod1_pror"]')) return;
    const fieldContainer = target.closest(".field-container");
    if (!fieldContainer) return;
    calculateProrSaving(fieldContainer);
    generateProrJSON();
    syncProrrogAltaBounds();
});

document.addEventListener("DOMContentLoaded", () => {
    if (!window.__PRORROG_ACOMOD_VALOR_MAP || Object.keys(window.__PRORROG_ACOMOD_VALOR_MAP).length === 0) {
        const map = {};
        document.querySelectorAll('#container-prorrog select[name="acomod_solicitada_pror"] option, #container-prorrog select[name="acomod1_pror"] option').forEach((opt) => {
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
    const first = document.querySelector(".field-container");
    const fk_internacao_pror = first ? (first.querySelector('input[name="fk_internacao_pror"]')?.value || '') : '';
    const fk_usuario_pror = first ? (first.querySelector('input[name="fk_usuario_pror"]')?.value || '') : '';

    const fieldContainers = document.querySelectorAll(".field-container");
    const prorrogationsWithContext = Array.from(fieldContainers).map((container) => ({
        fk_internacao_pror: fk_internacao_pror,
        fk_usuario_pror: fk_usuario_pror,
        acomod_solicitada_pror: container.querySelector('[name="acomod_solicitada_pror"]')?.value || '',
        acomod1_pror: container.querySelector('[name="acomod1_pror"]').value,
        prorrog1_ini_pror: container.querySelector('[name="prorrog1_ini_pror"]').value,
        prorrog1_fim_pror: container.querySelector('[name="prorrog1_fim_pror"]').value,
        isol_1_pror: container.querySelector('[name="isol_1_pror"]').value,
        diarias_1: container.querySelector('[name="diarias_1"]').value,
        saving_estimado_pror: container.querySelector('[name="saving_estimado_pror"]')?.value || '',
        tipo_negociacao_pror: 'PRORROGACAO_AUTOMATICA',
        __container: container
    }));

    const prorrogations = prorrogationsWithContext.map((row) => ({
        fk_internacao_pror: row.fk_internacao_pror,
        fk_usuario_pror: row.fk_usuario_pror,
        acomod_solicitada_pror: row.acomod_solicitada_pror,
        acomod1_pror: row.acomod1_pror,
        prorrog1_ini_pror: row.prorrog1_ini_pror,
        prorrog1_fim_pror: row.prorrog1_fim_pror,
        isol_1_pror: row.isol_1_pror,
        diarias_1: row.diarias_1,
        saving_estimado_pror: row.saving_estimado_pror,
        tipo_negociacao_pror: row.tipo_negociacao_pror,
    }));

    const jsonData = {
        prorrogations
    };
    document.getElementById("prorrogacoes-json").value = JSON.stringify(jsonData, null, 2);
    if (typeof window.syncNegociacoesFromProrrog === 'function') {
        window.syncNegociacoesFromProrrog(prorrogationsWithContext);
    }
}

// Limpa todos os inputs (mantém só a primeira linha)
function clearProrrogInputs() {
    const fieldContainers = document.querySelectorAll(".field-container");
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
    generateProrJSON();
}
</script>
