<?php
include_once("check_logado.php");
include_once("globals.php");
require_once("app/services/TextAutomationService.php");

$pageTitle = 'Assistente de Auditoria Médica com IA';
$service = new TextAutomationService($conn);
$internacaoId = filter_input(INPUT_GET, 'id_internacao', FILTER_VALIDATE_INT);
$analysisType = trim((string)($_GET['analysis_type'] ?? 'completa'));
$analyses = $service->getAvailableAnalyses();
if (!array_key_exists($analysisType, $analyses)) {
    $analysisType = 'completa';
}
$internacoes = [];
$generated = null;
$error = null;
$listError = null;

try {
    $internacoes = $service->listInternacoesForSelect();
} catch (Throwable $e) {
    $listError = $e->getMessage();
}

if ($internacaoId) {
    try {
        $generated = $service->generateTexts($internacaoId, $analysisType);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if (!function_exists('ta_e')) {
    function ta_e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('ta_date')) {
    function ta_date($value): string
    {
        if (!$value || $value === '0000-00-00') {
            return 'Não informada';
        }
        $time = strtotime((string)$value);
        return $time ? date('d/m/Y', $time) : 'Não informada';
    }
}

include_once("templates/header.php");
?>

<style>
    .container-fluid.mt-5.pt-4 {
        margin-top: 12px !important;
        padding-top: 0 !important;
        padding-bottom: 14px !important;
    }

    .automation-card {
        border-radius: 12px;
        border: 1px solid #e7e7e7;
        padding: 1rem 1.1rem;
        background: #fff;
        box-shadow: 0 10px 20px rgba(95, 35, 99, 0.07);
    }

    .automation-card h4 {
        color: #5e2363;
        font-weight: 600;
        font-size: .94rem;
    }

    .automation-title-card {
        border: 1px solid rgba(94, 35, 99, 0.18);
        border-radius: 14px;
        background: linear-gradient(135deg, #fbf6fc 0%, #f2e7f5 100%);
        box-shadow: 0 12px 26px rgba(95, 35, 99, 0.11);
        margin-bottom: 1rem;
        padding: 1rem 1.1rem;
        position: relative;
        overflow: hidden;
    }

    .automation-title-card::before {
        background: linear-gradient(180deg, #5e2363 0%, #77cfc7 100%);
        border-radius: 999px;
        bottom: 14px;
        content: "";
        left: 0;
        position: absolute;
        top: 14px;
        width: 5px;
    }

    .automation-title-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .8rem;
    }

    .automation-title-row h1 {
        color: #35123c;
        font-size: 1.35rem;
        font-weight: 800;
        margin: 0;
        padding-left: .35rem;
    }

    .automation-info-button {
        align-items: center;
        border: 1px solid rgba(94, 35, 99, .32);
        border-radius: 50%;
        background: #ffffff;
        color: #5e2363;
        display: inline-flex;
        font-weight: 800;
        height: 32px;
        justify-content: center;
        line-height: 1;
        min-width: 32px;
        padding: 0;
        width: 32px;
    }

    .automation-info-text {
        border-top: 1px solid rgba(94, 35, 99, 0.1);
        color: #75657b;
        font-size: .82rem;
        line-height: 1.45;
        margin: .85rem 0 0;
        padding-top: .85rem;
    }

    .automation-panel {
        border: 1px solid rgba(94, 35, 99, 0.12);
        border-radius: 16px;
        background: linear-gradient(135deg, #ffffff 0%, #fbf7fc 100%);
        box-shadow: 0 14px 28px rgba(95, 35, 99, 0.08);
        padding: 1.1rem;
    }

    .automation-panel .form-label {
        color: #5e2363;
        font-weight: 700;
    }

    .automation-panel .form-control,
    .automation-panel .btn {
        min-height: 42px;
    }

    .automation-panel select.form-control {
        min-height: 42px;
    }

    .automation-hidden-select {
        position: absolute;
        width: 1px;
        height: 1px;
        opacity: 0;
        pointer-events: none;
    }

    .automation-search-wrap {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: .5rem;
        position: relative;
    }

    .automation-search-hint {
        color: #88708e;
        font-size: .68rem;
        line-height: 1.3;
        min-height: 18px;
    }

    .automation-search-results {
        border: 1px solid rgba(94, 35, 99, .16);
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 12px 24px rgba(42, 28, 47, .12);
        max-height: 230px;
        overflow-y: auto;
        padding: .3rem;
        z-index: 4;
    }

    .automation-result-item {
        border: 0;
        border-radius: 8px;
        background: transparent;
        color: #35283b;
        display: block;
        font-size: .78rem;
        line-height: 1.3;
        padding: .55rem .65rem;
        text-align: left;
        width: 100%;
    }

    .automation-result-item:hover,
    .automation-result-item:focus {
        background: #f4ebf6;
        color: #5e2363;
        outline: none;
    }

    .automation-action-buttons {
        display: grid;
        grid-template-columns: minmax(150px, 1fr) minmax(96px, .42fr);
        gap: .7rem;
    }

    .automation-actions-spacer {
        visibility: hidden;
        white-space: nowrap;
    }

    .automation-context-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .85rem;
        margin-bottom: 1rem;
    }

    .automation-context-card {
        border: 1px solid rgba(94, 35, 99, 0.1);
        border-radius: 12px;
        background: #fff;
        padding: .9rem 1rem;
        box-shadow: 0 10px 22px rgba(38, 50, 56, 0.05);
        min-height: 86px;
    }

    .automation-context-card small {
        display: block;
        color: #8b7790;
        font-size: .68rem;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
        margin-bottom: .3rem;
    }

    .automation-context-card strong {
        display: block;
        color: #35123c;
        font-size: .92rem;
        line-height: 1.25;
    }

    .automation-source-badge {
        border-radius: 999px;
        background: #efe6f1;
        color: #5e2363;
        font-size: .7rem;
        font-weight: 700;
        padding: .3rem .65rem;
    }

    .automation-card textarea {
        width: 100%;
        min-height: 320px;
        resize: vertical;
        border-radius: 8px;
        border: 1px solid #d9d9d9;
        padding: 0.7rem;
        font-size: 0.82rem;
        line-height: 1.55;
        color: #2f2434;
    }
    .container-fluid .alert,
    .container-fluid .form-label,
    .container-fluid .form-control,
    .container-fluid .btn,
    .container-fluid ul,
    .container-fluid li {
        font-size: .78rem;
    }

    @media (max-width: 991px) {
        .automation-context-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .automation-action-buttons {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 575px) {
        .automation-context-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>

<main class="container-fluid mt-5 pt-4">
        <div class="automation-title-card">
            <div class="automation-title-row">
                <h1>Auditoria Médica Inteligente</h1>
                <button type="button" class="automation-info-button" id="automation_info_button"
                    aria-expanded="false" aria-controls="automation_info_text" title="Como usar">i</button>
            </div>
            <p class="automation-info-text d-none" id="automation_info_text">
                Pesquise uma internação e escolha o tipo de análise para gerar um texto com IA baseado nos dados
                registrados do paciente, visitas, UTI, negociação, antecedentes e prorrogações.
            </p>
        </div>

        <?php if ($listError): ?>
        <div class="alert alert-warning"><?= ta_e($listError) ?></div>
        <?php endif; ?>

        <form class="automation-panel row g-3 mb-4" method="GET" id="automation_form">
            <div class="col-lg-6">
                <label for="internacao_search" class="form-label">Internação</label>
                <div class="automation-search-wrap">
                    <input type="text" class="form-control" id="internacao_search"
                        placeholder="Digite paciente, hospital ou ID para pesquisar" autocomplete="off">
                    <div class="automation-search-results d-none" id="internacao_search_results"></div>
                    <select class="form-control automation-hidden-select" name="id_internacao" id="id_internacao"
                        tabindex="-1" aria-hidden="true">
                        <option value="">Selecione o paciente</option>
                        <?php foreach ($internacoes as $item): ?>
                        <?php
                            $optionId = (int)($item['id_internacao'] ?? 0);
                            $optionLabel = '#' . $optionId . ' - ' . ($item['nome_pac'] ?? 'Paciente') . ' | '
                                . ($item['nome_hosp'] ?? 'Hospital') . ' | ' . ta_date($item['data_intern_int'] ?? null);
                        ?>
                        <option value="<?= $optionId ?>" <?= $optionId === (int)$internacaoId ? 'selected' : '' ?>
                            data-search="<?= ta_e(mb_strtolower($optionLabel, 'UTF-8')) ?>">
                            <?= ta_e($optionLabel) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="automation-search-hint" id="internacao_search_hint">
                        A busca filtra a lista e seleciona automaticamente quando houver uma correspondência clara.
                    </div>
                </div>
            </div>
            <div class="col-lg-3">
                <label for="analysis_type" class="form-label">Análise</label>
                <select class="form-control" name="analysis_type" id="analysis_type" required>
                    <?php foreach ($analyses as $key => $label): ?>
                    <option value="<?= ta_e($key) ?>" <?= $key === $analysisType ? 'selected' : '' ?>>
                        <?= ta_e($label) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label automation-actions-spacer" aria-hidden="true">Ações</label>
                <div class="automation-action-buttons">
                    <button type="submit" class="btn btn-primary">Gerar com IA</button>
                    <button type="button" class="btn btn-outline-secondary" id="clear_automation">
                        Limpar
                    </button>
                </div>
            </div>
        </form>

        <?php if ($error): ?>
        <div class="alert alert-danger"><?= ta_e($error) ?></div>
        <?php endif; ?>

        <?php if ($generated): ?>
        <?php $context = $generated['context'] ?? []; ?>
        <section>
            <div class="automation-context-grid">
                <div class="automation-context-card">
                    <small>Paciente</small>
                    <strong><?= ta_e($context['nome_pac'] ?? 'Paciente não informado') ?></strong>
                </div>
                <div class="automation-context-card">
                    <small>Data internação</small>
                    <strong><?= ta_e(ta_date($context['data_intern_int'] ?? null)) ?></strong>
                </div>
                <div class="automation-context-card">
                    <small>Hospital</small>
                    <strong><?= ta_e($context['nome_hosp'] ?? 'Hospital não informado') ?></strong>
                </div>
                <div class="automation-context-card">
                    <small>Análise</small>
                    <strong><?= ta_e($generated['analysis_label'] ?? $analyses[$analysisType]) ?></strong>
                </div>
            </div>

            <?php if (!empty($generated['ai_warning'])): ?>
            <div class="alert alert-warning"><?= ta_e($generated['ai_warning']) ?></div>
            <?php endif; ?>

            <div class="automation-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <h4>Texto gerado por IA</h4>
                        <span class="automation-source-badge">
                            <?= ($generated['ai_source'] ?? '') === 'openai' ? 'OpenAI / ChatGPT' : 'Rascunho local' ?>
                        </span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                        onclick="copyText('ai-text')">Copiar</button>
                </div>
                <textarea id="ai-text" readonly><?= ta_e($generated['ai_text'] ?? '') ?></textarea>
            </div>
        </section>
        <?php endif; ?>
    </main>

<script>
document.title = 'Assistente de Auditoria Médica com IA | FullCare';

(function initAutomationInfo() {
    const button = document.getElementById('automation_info_button');
    const text = document.getElementById('automation_info_text');
    if (!button || !text) return;

    button.addEventListener('click', function() {
        const isHidden = text.classList.contains('d-none');
        text.classList.toggle('d-none', !isHidden);
        button.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
    });
})();

function copyText(elementId) {
    const el = document.getElementById(elementId);
    if (!el) return;
    el.select();
    el.setSelectionRange(0, 99999);
    document.execCommand('copy');
}

(function initInternacaoSearch() {
    const search = document.getElementById('internacao_search');
    const select = document.getElementById('id_internacao');
    const hint = document.getElementById('internacao_search_hint');
    const results = document.getElementById('internacao_search_results');
    const form = document.getElementById('automation_form');
    const clearButton = document.getElementById('clear_automation');
    if (!search || !select) return;

    const options = Array.from(select.options).map((option) => ({
        option,
        value: option.value,
        text: option.textContent.trim(),
        normalized: (option.dataset.search || option.textContent || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
    }));

    function hideResults() {
        if (!results) return;
        results.classList.add('d-none');
        results.innerHTML = '';
    }

    function setHint(total, selectedText) {
        if (!hint) return;
        if (selectedText) {
            hint.textContent = 'Selecionado: ' + selectedText;
            return;
        }
        if (total === 0) {
            hint.textContent = 'Nenhuma internação encontrada para essa busca.';
            return;
        }
        hint.textContent = total + ' resultado(s) encontrado(s). Continue digitando para refinar.';
    }

    function normalize(value) {
        return (value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function selectOption(option) {
        if (!option || !option.value) return;
        select.value = option.value;
        search.value = option.textContent.trim();
        setHint(1, search.value);
        hideResults();
    }

    function syncSearchWithSelected() {
        const selected = select.options[select.selectedIndex];
        if (selected && selected.value) {
            selectOption(selected);
        }
    }

    function renderResults(visible, query) {
        if (!results) return;
        results.innerHTML = '';

        if (query === '' || visible.length === 0) {
            hideResults();
            return;
        }

        visible.slice(0, 10).forEach((option) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'automation-result-item';
            item.textContent = option.textContent.trim();
            item.addEventListener('mousedown', function(event) {
                event.preventDefault();
                selectOption(option);
            });
            results.appendChild(item);
        });

        results.classList.remove('d-none');
    }

    function filterOptions() {
        const query = normalize(search.value);
        let visible = [];

        options.forEach(({ option, value, normalized }) => {
            if (!value) {
                option.hidden = false;
                return;
            }

            const show = query === '' || normalized.includes(query);
            option.hidden = !show;
            if (show) {
                visible.push(option);
            }
        });

        if (query === '') {
            select.value = '';
            hideResults();
            setHint(visible.length, '');
            return;
        }

        const exact = visible.find((option) => {
            const optionText = normalize(option.textContent);
            return option.value === query.replace(/^#/, '') || optionText === query;
        });

        if (exact) {
            select.value = exact.value;
            renderResults(visible, query);
            setHint(visible.length, exact.textContent.trim());
            return;
        }

        if (visible.length === 1) {
            select.value = visible[0].value;
            renderResults(visible, query);
            setHint(1, visible[0].textContent.trim());
            return;
        }

        select.value = '';
        renderResults(visible, query);
        setHint(visible.length, '');
    }

    search.addEventListener('input', filterOptions);
    search.addEventListener('focus', filterOptions);
    search.addEventListener('blur', function() {
        window.setTimeout(hideResults, 160);
    });
    search.addEventListener('keydown', function(event) {
        if (event.key === 'Enter' && select.value) {
            event.preventDefault();
            search.closest('form').submit();
        }
    });
    if (form) {
        form.addEventListener('submit', function(event) {
            if (select.value) return;
            event.preventDefault();
            filterOptions();
            if (select.value) {
                form.submit();
                return;
            }
            search.focus();
            if (hint) {
                hint.textContent = 'Digite mais detalhes até selecionar uma internação.';
            }
        });
    }
    if (clearButton) {
        clearButton.addEventListener('click', function() {
            search.value = '';
            select.value = '';
            hideResults();
            window.location.href = window.location.pathname;
        });
    }
    select.addEventListener('change', syncSearchWithSelected);

    syncSearchWithSelected();
})();
</script>
<?php include_once("templates/footer.php"); ?>
