<?php
include_once("check_logado.php");
require_once("templates/header.php");

if (!isset($conn) || !($conn instanceof PDO)) {
    die("Conexão inválida.");
}

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$anoInput = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
$ano = ($anoInput !== null && $anoInput !== false) ? (int)$anoInput : null;
$mes = (int)(filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT) ?: 0);
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);
$negociacaoRealClause = "UPPER(COALESCE(ng.tipo_negociacao, '')) <> 'PRORROGACAO_AUTOMATICA'";
$savingExpr = "COALESCE(ng.saving, 0)";

if ($ano === null && !filter_has_var(INPUT_GET, 'ano')) {
    $stmtAno = $conn->query("SELECT MAX(YEAR(data_inicio_neg)) AS ano FROM tb_negociacao WHERE data_inicio_neg IS NOT NULL AND data_inicio_neg <> '0000-00-00'");
    $anoDb = $stmtAno->fetch(PDO::FETCH_ASSOC) ?: [];
    $ano = (int)($anoDb['ano'] ?? date('Y'));
}

$whereBase = "ng.data_inicio_neg IS NOT NULL
    AND ng.data_inicio_neg <> '0000-00-00'
    AND ng.saving IS NOT NULL
    AND COALESCE(ng.fk_usuario_neg, 0) > 0
    AND {$negociacaoRealClause}
    AND COALESCE(ng.saving, 0) <> 0
    AND YEAR(ng.data_inicio_neg) = :ano";
$paramsBase = [':ano' => $ano];

if ($hospitalId) {
    $whereBase .= " AND i.fk_hospital_int = :hospital_id";
    $paramsBase[':hospital_id'] = $hospitalId;
}

$whereTot = $whereBase;
$paramsTot = $paramsBase;
if ($mes > 0) {
    $whereTot .= " AND MONTH(ng.data_inicio_neg) = :mes";
    $paramsTot[':mes'] = $mes;
}

$sqlTot = "
    SELECT
        SUM({$savingExpr}) AS total_saving,
        COUNT(DISTINCT ng.id_negociacao) AS total_registros
    FROM tb_negociacao ng
    INNER JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int
    WHERE {$whereTot}
";
$stmt = $conn->prepare($sqlTot);
foreach ($paramsTot as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$tot = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$totalSaving = (float)($tot['total_saving'] ?? 0);
$totalRegistros = (int)($tot['total_registros'] ?? 0);

$sqlHosp = "
    SELECT
        COALESCE(h.nome_hosp, 'Sem hospital') AS hospital,
        SUM({$savingExpr}) AS total_saving,
        COUNT(DISTINCT ng.id_negociacao) AS total_registros,
        SUM(COALESCE(ng.qtd, 0)) AS total_qtd
    FROM tb_negociacao ng
    INNER JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    WHERE {$whereTot}
    GROUP BY h.id_hospital, h.nome_hosp
    ORDER BY total_saving DESC
    LIMIT 15
";
$stmt = $conn->prepare($sqlHosp);
foreach ($paramsTot as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$hospRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sqlMensal = "
    SELECT
        MONTH(ng.data_inicio_neg) AS mes,
        SUM({$savingExpr}) AS total_saving
    FROM tb_negociacao ng
    INNER JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int
    WHERE {$whereBase}
    GROUP BY mes
    ORDER BY mes
";
$stmt = $conn->prepare($sqlMensal);
foreach ($paramsBase as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$mensalRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$monthNames = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
$monthFullNames = [
    1 => 'Janeiro',
    2 => 'Fevereiro',
    3 => 'Março',
    4 => 'Abril',
    5 => 'Maio',
    6 => 'Junho',
    7 => 'Julho',
    8 => 'Agosto',
    9 => 'Setembro',
    10 => 'Outubro',
    11 => 'Novembro',
    12 => 'Dezembro',
];
$selectedMonthLabel = $mes >= 1 && $mes <= 12 ? $monthFullNames[$mes] : '';
$labelsMes = $monthNames;
$savingMensalMap = array_fill(1, 12, 0.0);
foreach ($mensalRows as $row) {
    $m = (int)($row['mes'] ?? 0);
    if ($m >= 1 && $m <= 12) {
        $savingMensalMap[$m] = (float)($row['total_saving'] ?? 0);
    }
}
$savingMensal = array_values($savingMensalMap);

$labelsHosp = array_map(fn($r) => $r['hospital'] ?: 'Sem hospital', $hospRows);
$savingHosp = array_map(fn($r) => (float)$r['total_saving'], $hospRows);
$countHosp = array_map(fn($r) => (int)$r['total_registros'], $hospRows);
$qtdDiariasHosp = array_map(fn($r) => (int)($r['total_qtd'] ?? 0), $hospRows);

$sqlTipoValor = "
    SELECT
        COALESCE(ng.tipo_negociacao, 'Não informado') AS tipo,
        SUM({$savingExpr}) AS total_saving,
        COUNT(DISTINCT ng.id_negociacao) AS total_registros
    FROM tb_negociacao ng
    INNER JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int
    WHERE {$whereTot}
    GROUP BY tipo
    ORDER BY total_saving DESC
";
$stmt = $conn->prepare($sqlTipoValor);
foreach ($paramsTot as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$tipoRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sqlTipoMensal = "
    SELECT
        MONTH(ng.data_inicio_neg) AS mes,
        COALESCE(ng.tipo_negociacao, 'Não informado') AS tipo,
        SUM({$savingExpr}) AS total_saving,
        COUNT(DISTINCT ng.id_negociacao) AS total_registros
    FROM tb_negociacao ng
    INNER JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int
    WHERE {$whereBase}
    GROUP BY mes, tipo
    ORDER BY mes, tipo
";
$stmt = $conn->prepare($sqlTipoMensal);
foreach ($paramsBase as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$tipoMensalRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$tipoLabels = array_map(fn($r) => (string)($r['tipo'] ?: 'Não informado'), $tipoRows);
$tipoSavingValues = array_map(fn($r) => (float)($r['total_saving'] ?? 0), $tipoRows);
$tipoCountValues = array_map(fn($r) => (int)($r['total_registros'] ?? 0), $tipoRows);

$tipoMonthlyMap = [];
$tipoMonthlyCountMap = [];
foreach ($tipoLabels as $tipoLabel) {
    $tipoMonthlyMap[$tipoLabel] = array_fill(1, 12, 0.0);
    $tipoMonthlyCountMap[$tipoLabel] = array_fill(1, 12, 0);
}
foreach ($tipoMensalRows as $row) {
    $mesRef = (int)($row['mes'] ?? 0);
    $tipoRef = (string)($row['tipo'] ?: 'Não informado');
    if ($mesRef >= 1 && $mesRef <= 12 && isset($tipoMonthlyMap[$tipoRef])) {
        $tipoMonthlyMap[$tipoRef][$mesRef] = (float)($row['total_saving'] ?? 0);
        $tipoMonthlyCountMap[$tipoRef][$mesRef] = (int)($row['total_registros'] ?? 0);
    }
}

$tipoPalette = [
    'rgba(159, 196, 214, 0.92)',
    'rgba(196, 168, 132, 0.9)',
    'rgba(184, 150, 176, 0.9)',
    'rgba(136, 174, 162, 0.92)',
    'rgba(127, 143, 176, 0.9)',
    'rgba(203, 183, 142, 0.88)',
];
$tipoLineDatasets = [];
$tipoCountLineDatasets = [];
$tipoColorIndex = 0;
foreach ($tipoLabels as $tipoLabel) {
    $color = $tipoPalette[$tipoColorIndex % count($tipoPalette)];
    $tipoLineDatasets[] = [
        'label' => $tipoLabel,
        'data' => array_values($tipoMonthlyMap[$tipoLabel] ?? array_fill(1, 12, 0.0)),
        'borderColor' => $color,
        'backgroundColor' => 'rgba(0,0,0,0)',
        'borderWidth' => 2,
        'pointRadius' => 3,
        'tension' => 0.28,
        'fill' => false,
    ];
    $tipoCountLineDatasets[] = [
        'label' => $tipoLabel,
        'data' => array_values($tipoMonthlyCountMap[$tipoLabel] ?? array_fill(1, 12, 0)),
        'borderColor' => $color,
        'backgroundColor' => 'rgba(0,0,0,0)',
        'borderWidth' => 2,
        'pointRadius' => 3,
        'tension' => 0.28,
        'fill' => false,
    ];
    $tipoColorIndex++;
}
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260516-rounded-bars"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>
<style>
.saving-type-grid {
    display:flex;
    flex-wrap:nowrap;
    gap:12px;
    margin-top:12px;
    align-items:stretch;
}
@media (max-width: 992px) {
    .saving-type-grid {
        flex-direction:column;
    }
}
.saving-type-grid .bi-panel {
    flex: 1 1 0;
    width: calc(50% - 6px);
    max-width: calc(50% - 6px);
    height: 400px;
    min-height: 400px;
    display: flex;
    flex-direction: column;
    min-width: 0;
    box-sizing: border-box;
    overflow: hidden;
}
.saving-type-grid .bi-panel + .bi-panel {
    margin-top: 0;
}
@media (max-width: 992px) {
    .saving-type-grid .bi-panel {
        width: 100%;
        max-width: 100%;
    }
}
.saving-type-panel h3 {
    margin-bottom:12px;
}
.saving-type-panel .bi-chart {
    flex: 1 1 auto;
    height: 300px;
    min-height: 300px;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.saving-section-stack {
    display:grid;
    gap:12px;
    margin-top:12px;
}
.saving-section-stack .bi-panel + .bi-panel {
    margin-top: 0;
}
.saving-period-chip {
    align-items: center;
    background: rgba(255, 255, 255, 0.82);
    border: 1px solid rgba(64, 37, 75, 0.16);
    border-radius: 999px;
    color: #35123c;
    display: inline-flex;
    font-size: .82rem;
    font-weight: 800;
    line-height: 1.2;
    min-height: 34px;
    padding: .45rem .8rem;
    white-space: nowrap;
}
</style>

<div class="bi-wrapper bi-theme bi-ie-page">
    <div class="bi-header">
        <h1 class="bi-title">Saving por Hospital</h1>
        <div class="bi-header-actions">
            <div class="saving-period-chip">Ano <?= e($ano) ?><?= $selectedMonthLabel !== '' ? ' • ' . e($selectedMonthLabel) : '' ?></div>
            <a class="bi-btn bi-btn-secondary" href="<?= $BASE_URL ?>bi/saving-por-auditor" title="Saving por Auditor">Saving por Auditor</a>
        </div>
    </div>

    <form class="bi-panel bi-filters" method="get">
        <div class="bi-filter">
            <label>Hospital</label>
            <select name="hospital_id">
                <option value="">Todos</option>
                <?php foreach ($hospitais as $h): ?>
                    <option value="<?= (int)$h['id_hospital'] ?>" <?= $hospitalId == $h['id_hospital'] ? 'selected' : '' ?>>
                        <?= e($h['nome_hosp']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Mês</label>
            <select name="mes">
                <option value="0">Todos</option>
                <?php foreach ($monthFullNames as $m => $monthName): ?>
                    <option value="<?= $m ?>" <?= $mes === $m ? 'selected' : '' ?>><?= e($monthName) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Ano</label>
            <input type="number" name="ano" value="<?= e($ano) ?>">
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="bi-panel">
        <div class="bi-kpis kpi-dashboard-v2">
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-1">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-piggy-bank"></i></span>
                    <small>Total saving</small>
                </div>
                <strong>R$ <?= number_format($totalSaving, 2, ',', '.') ?></strong>
                <span class="kpi-trend kpi-trend-up"><i class="bi bi-cash-stack"></i>Resultado negociado</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-2">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-list-ol"></i></span>
                    <small>Negociações</small>
                </div>
                <strong><?= $totalRegistros ?></strong>
                <span class="kpi-trend kpi-trend-neutral"><i class="bi bi-file-earmark-bar-graph"></i>Itens no período</span>
            </div>
        </div>
    </div>

    <div class="bi-panel" style="margin-top:16px;">
        <h3>Saving por hospital (R$)</h3>
        <div class="bi-chart ie-chart-sm"><canvas id="chartSavingHospital"></canvas></div>
    </div>
    <div class="bi-panel">
        <h3>Quantidade de negociações por hospital</h3>
        <div class="bi-chart ie-chart-sm"><canvas id="chartQtdeHospital"></canvas></div>
    </div>
    <div class="bi-panel">
        <h3>Quantidade de diárias trocadas por hospital</h3>
        <div class="bi-chart ie-chart-sm"><canvas id="chartDiariasHospital"></canvas></div>
    </div>
    <div class="bi-panel">
        <h3>Evolução mensal do saving (ano completo)</h3>
        <div class="bi-chart ie-chart-md"><canvas id="chartSavingEvolucao"></canvas></div>
    </div>
    <div class="saving-type-grid">
        <div class="bi-panel saving-type-panel">
            <h3>Tipo de saving por valor</h3>
            <div class="bi-chart ie-chart-sm"><canvas id="chartTipoSavingValor"></canvas></div>
        </div>
        <div class="bi-panel saving-type-panel">
            <h3>Tipo de saving por quantidade</h3>
            <div class="bi-chart ie-chart-sm"><canvas id="chartTipoSavingQuantidade"></canvas></div>
        </div>
    </div>
    <div class="saving-section-stack">
        <div class="bi-panel">
            <h3>Evolução mensal por tipo de saving</h3>
            <div class="bi-chart ie-chart-md"><canvas id="chartTipoSavingMensal"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3>Evolução mensal por tipo de saving em quantidade</h3>
            <div class="bi-chart ie-chart-md"><canvas id="chartTipoSavingMensalQuantidade"></canvas></div>
        </div>
    </div>
</div>

<script>
const labelsHosp = <?= json_encode($labelsHosp) ?>;
const savingHosp = <?= json_encode($savingHosp) ?>;
const countHosp = <?= json_encode($countHosp) ?>;
const qtdDiariasHosp = <?= json_encode($qtdDiariasHosp) ?>;
const labelsMes = <?= json_encode($labelsMes) ?>;
const savingMensal = <?= json_encode($savingMensal) ?>;
const tipoLabels = <?= json_encode($tipoLabels, JSON_UNESCAPED_UNICODE) ?>;
const tipoSavingValues = <?= json_encode($tipoSavingValues) ?>;
const tipoCountValues = <?= json_encode($tipoCountValues) ?>;
const tipoLineDatasets = <?= json_encode($tipoLineDatasets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const tipoCountLineDatasets = <?= json_encode($tipoCountLineDatasets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const tipoPalette = <?= json_encode($tipoPalette) ?>;

const biValueLabelPlugin = {
    afterDatasetsDraw: function(chart) {
        const ctx = chart.ctx;
        ctx.save();

        chart.data.datasets.forEach(function(dataset, datasetIndex) {
            const meta = chart.getDatasetMeta(datasetIndex);
            if (!meta || meta.hidden) return;

            meta.data.forEach(function(element, index) {
                const rawValue = Number(dataset.data[index] || 0);
                if (!Number.isFinite(rawValue)) return;

                const isMoney = !!dataset.isMoney;
                const valueMode = String(dataset.valueMode || (isMoney ? 'money' : 'count'));
                const label = valueMode === 'money'
                    ? formatMoneyCompact(rawValue)
                    : Number(rawValue).toLocaleString('pt-BR');

                ctx.font = '600 12px Poppins, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'bottom';
                ctx.fillStyle = isMoney ? '#f4fbff' : '#f6e6ff';
                ctx.shadowColor = 'rgba(8, 20, 38, 0.35)';
                ctx.shadowBlur = 6;

                if (chart.config.type === 'line') {
                    ctx.fillText(label, element._model.x, element._model.y - 10);
                } else {
                    const topY = Math.min(element._model.base, element._model.y);
                    ctx.fillText(label, element._model.x, topY - 8);
                }
            });
        });

        ctx.restore();
    }
};

function formatMoney(value) {
    return 'R$ ' + Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatMoneyCompact(value) {
    const absValue = Math.abs(Number(value || 0));
    if (absValue >= 1000) {
        return 'R$ ' + Number(value || 0).toLocaleString('pt-BR', { maximumFractionDigits: 0 });
    }
    return formatMoney(value);
}

function barChart(ctx, labels, data, color) {
    const scales = window.biChartScales ? window.biChartScales() : undefined;
    if (scales && scales.yAxes && scales.yAxes[0] && scales.yAxes[0].ticks) {
        scales.yAxes[0].ticks.callback = function (value) {
            return window.biMoneyTick ? window.biMoneyTick(value) : ('R$ ' + Number(value || 0).toLocaleString('pt-BR'));
        };
    }
    return new Chart(ctx, {
        type: 'bar',
        data: { labels, datasets: [{ data, backgroundColor: color, isMoney: true }] },
        plugins: [biValueLabelPlugin],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            biValueLabels: false,
            layout: {
                padding: {
                    top: 26
                }
            },
            legend: { display: false },
            scales: scales,
            tooltips: {
                callbacks: {
                    label: function (tooltipItem) {
                        return window.biMoneyTick ? window.biMoneyTick(tooltipItem.yLabel) : formatMoney(tooltipItem.yLabel);
                    }
                }
            }
        }
    });
}

function lineChart(ctx, labels, data, color, money = true) {
    const opts = {
        responsive: true,
        maintainAspectRatio: false,
        scales: window.biChartScales ? window.biChartScales() : undefined
    };
    if (money) {
        opts.tooltips = {
            callbacks: {
                label: function (tooltipItem) {
                    return window.biMoneyTick ? window.biMoneyTick(tooltipItem.yLabel) : ('R$ ' + Number(tooltipItem.yLabel || 0).toLocaleString('pt-BR'));
                }
            }
        };
        if (opts.scales && opts.scales.yAxes && opts.scales.yAxes[0] && opts.scales.yAxes[0].ticks) {
            opts.scales.yAxes[0].ticks.callback = function (value) {
                return window.biMoneyTick ? window.biMoneyTick(value) : ('R$ ' + Number(value || 0).toLocaleString('pt-BR'));
            };
        }
    }
    return new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Saving',
                data,
                borderColor: color,
                backgroundColor: 'rgba(0, 0, 0, 0)',
                borderWidth: 2,
                pointBackgroundColor: color,
                pointRadius: 4,
                tension: 0.35,
                fill: false,
                isMoney: money
            }]
        },
        plugins: [biValueLabelPlugin],
        options: Object.assign({}, opts, {
            layout: {
                padding: {
                    top: 22
                }
            }
        })
    });
}

function categoryBarChart(ctx, labels, data, isMoney) {
    const scales = window.biChartScales ? window.biChartScales() : undefined;
    if (scales && scales.yAxes && scales.yAxes[0] && scales.yAxes[0].ticks) {
        scales.yAxes[0].ticks.callback = function (value) {
            return isMoney
                ? (window.biMoneyTick ? window.biMoneyTick(value) : formatMoneyCompact(value))
                : Number(value || 0).toLocaleString('pt-BR');
        };
    }
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor: tipoPalette,
                borderColor: 'rgba(11, 24, 39, 0.15)',
                borderWidth: 1,
                isMoney: isMoney,
                valueMode: isMoney ? 'money' : 'count'
            }]
        },
        plugins: [biValueLabelPlugin],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            biValueLabels: false,
            layout: {
                padding: {
                    top: 18
                }
            },
            legend: {
                display: false
            },
            scales: scales,
            tooltips: {
                callbacks: {
                    label: function (tooltipItem, chartData) {
                        const idx = tooltipItem.index;
                        const label = chartData.labels[idx] || '';
                        const value = chartData.datasets[0].data[idx] || 0;
                        return isMoney
                            ? (label + ': ' + formatMoney(value))
                            : (label + ': ' + Number(value).toLocaleString('pt-BR'));
                    }
                }
            }
        }
    });
}

function multiLineChart(ctx, labels, datasets) {
    const scales = window.biChartScales ? window.biChartScales() : undefined;
    if (scales && scales.yAxes && scales.yAxes[0] && scales.yAxes[0].ticks) {
        scales.yAxes[0].ticks.callback = function (value) {
            return Number(value || 0).toLocaleString('pt-BR');
        };
    }
    return new Chart(ctx, {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: scales,
            legend: {
                position: 'bottom',
                labels: { fontColor: '#eaf6ff', boxWidth: 14 }
            },
            tooltips: {
                callbacks: {
                    label: function (tooltipItem, data) {
                        const ds = data.datasets[tooltipItem.datasetIndex] || {};
                        return (ds.label ? ds.label + ': ' : '') + Number(tooltipItem.yLabel || 0).toLocaleString('pt-BR');
                    }
                }
            }
        }
    });
}

function multiLineCountChart(ctx, labels, datasets) {
    const scales = window.biChartScales ? window.biChartScales() : undefined;
    if (scales && scales.yAxes && scales.yAxes[0] && scales.yAxes[0].ticks) {
        scales.yAxes[0].ticks.callback = function (value) {
            return Number(value || 0).toLocaleString('pt-BR');
        };
    }
    return new Chart(ctx, {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: scales,
            legend: {
                position: 'bottom',
                labels: { fontColor: '#eaf6ff', boxWidth: 14 }
            },
            tooltips: {
                callbacks: {
                    label: function (tooltipItem, data) {
                        const ds = data.datasets[tooltipItem.datasetIndex] || {};
                        return (ds.label ? ds.label + ': ' : '') + Number(tooltipItem.yLabel || 0).toLocaleString('pt-BR');
                    }
                }
            }
        }
    });
}

barChart(document.getElementById('chartSavingHospital'), labelsHosp, savingHosp, 'rgba(141, 208, 255, 0.7)');
new Chart(document.getElementById('chartQtdeHospital'), {
    type: 'bar',
    data: { labels: labelsHosp, datasets: [{ data: countHosp, backgroundColor: 'rgba(208, 113, 176, 0.7)', isMoney: false }] },
    plugins: [biValueLabelPlugin],
    options: {
        responsive: true,
        maintainAspectRatio: false,
        biValueLabels: false,
        layout: {
            padding: {
                top: 24
            }
        },
        legend: { display: false },
        scales: window.biChartScales ? window.biChartScales() : undefined
    }
});
new Chart(document.getElementById('chartDiariasHospital'), {
    type: 'bar',
    data: { labels: labelsHosp, datasets: [{ data: qtdDiariasHosp, backgroundColor: 'rgba(112, 214, 168, 0.72)', isMoney: false }] },
    plugins: [biValueLabelPlugin],
    options: {
        responsive: true,
        maintainAspectRatio: false,
        biValueLabels: false,
        layout: {
            padding: {
                top: 24
            }
        },
        legend: { display: false },
        scales: window.biChartScales ? window.biChartScales() : undefined
    }
});
lineChart(document.getElementById('chartSavingEvolucao'), labelsMes, savingMensal, 'rgba(255, 205, 86, 0.85)');
categoryBarChart(document.getElementById('chartTipoSavingValor'), tipoLabels, tipoSavingValues, false);
categoryBarChart(document.getElementById('chartTipoSavingQuantidade'), tipoLabels, tipoCountValues, false);
multiLineChart(document.getElementById('chartTipoSavingMensal'), labelsMes, tipoLineDatasets);
multiLineCountChart(document.getElementById('chartTipoSavingMensalQuantidade'), labelsMes, tipoCountLineDatasets);
</script>

<?php require_once("templates/footer.php"); ?>
