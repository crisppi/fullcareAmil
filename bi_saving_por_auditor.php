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
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$auditorId = filter_input(INPUT_GET, 'auditor_id', FILTER_VALIDATE_INT) ?: null;

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")->fetchAll(PDO::FETCH_ASSOC);
$auditores = $conn->query("SELECT id_usuario, usuario_user FROM tb_user ORDER BY usuario_user")->fetchAll(PDO::FETCH_ASSOC);
$auditorOptions = array_column($auditores, 'usuario_user', 'id_usuario');
$negociacaoRealClause = "UPPER(COALESCE(ng.tipo_negociacao, '')) <> 'PRORROGACAO_AUTOMATICA'";
$savingExpr = "COALESCE(ng.saving, 0)";

if ($ano === null && !filter_has_var(INPUT_GET, 'ano')) {
    $stmtAno = $conn->query("SELECT MAX(YEAR(data_inicio_neg)) AS ano FROM tb_negociacao WHERE data_inicio_neg IS NOT NULL AND data_inicio_neg <> '0000-00-00'");
    $anoDb = $stmtAno->fetch(PDO::FETCH_ASSOC) ?: [];
    $ano = (int)($anoDb['ano'] ?? date('Y'));
}

$where = "ng.data_inicio_neg IS NOT NULL
    AND ng.data_inicio_neg <> '0000-00-00'
    AND ng.saving IS NOT NULL
    AND COALESCE(ng.fk_usuario_neg, 0) > 0
    AND {$negociacaoRealClause}
    AND COALESCE(ng.saving, 0) <> 0
    AND YEAR(ng.data_inicio_neg) = :ano";
$params = [':ano' => $ano];

if ($hospitalId) {
    $where .= " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = $hospitalId;
}
if ($auditorId) {
    $where .= " AND ng.fk_usuario_neg = :auditor_id";
    $params[':auditor_id'] = $auditorId;
}

$sqlTotals = "
    SELECT
        SUM({$savingExpr}) AS total_saving,
        COUNT(DISTINCT ng.id_negociacao) AS total_registros,
        AVG({$savingExpr}) AS avg_saving
    FROM tb_negociacao ng
    INNER JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int
    WHERE {$where}
";

$stmt = $conn->prepare($sqlTotals);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$totals = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$totalSaving = (float)($totals['total_saving'] ?? 0);
$totalRegistros = (int)($totals['total_registros'] ?? 0);
$mediaSaving = (float)($totals['avg_saving'] ?? 0);

$sqlAuditorResumo = "
    SELECT
        ng.fk_usuario_neg AS auditor_id,
        COALESCE(u.usuario_user, 'Sem auditor') AS auditor,
        SUM({$savingExpr}) AS total_saving,
        COUNT(DISTINCT ng.id_negociacao) AS total_registros
    FROM tb_negociacao ng
    INNER JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int
    LEFT JOIN tb_user u ON u.id_usuario = ng.fk_usuario_neg
    WHERE {$where}
    GROUP BY auditor_id, auditor
    ORDER BY total_saving DESC
    LIMIT 20
";

$stmt = $conn->prepare($sqlAuditorResumo);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$auditorResumo = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$selectedAuditorLabel = $auditorId ? ($auditorOptions[$auditorId] ?? 'Auditor selecionado') : 'Todos os auditores';

$timelineAuditores = [];
$seen = [];
if ($auditorId) {
    $timelineAuditores[] = [
        'id' => $auditorId,
        'label' => $selectedAuditorLabel,
    ];
    $seen[$auditorId] = true;
}
foreach ($auditorResumo as $row) {
    if (count($timelineAuditores) >= 4) {
        break;
    }
    $auditorKey = (int)($row['auditor_id'] ?? 0);
    if ($auditorKey <= 0 || isset($seen[$auditorKey])) {
        continue;
    }
    $timelineAuditores[] = [
        'id' => $auditorKey,
        'label' => $row['auditor'] ?: ($auditorOptions[$auditorKey] ?? 'Sem auditor'),
    ];
    $seen[$auditorKey] = true;
}

$sqlMonthly = "
    SELECT
        MONTH(ng.data_inicio_neg) AS mes,
        ng.fk_usuario_neg AS auditor_id,
        SUM({$savingExpr}) AS total_saving
    FROM tb_negociacao ng
    INNER JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int
    WHERE {$where}
    GROUP BY mes, auditor_id
    ORDER BY mes, auditor_id
";

$stmt = $conn->prepare($sqlMonthly);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$monthlyRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$monthNames = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
$lineMonthlyByAuditor = [];
foreach ($timelineAuditores as $auditor) {
    $lineMonthlyByAuditor[$auditor['id']] = array_fill(1, 12, 0.0);
}
$totalMonthly = array_fill(1, 12, 0.0);
foreach ($monthlyRows as $row) {
    $mes = (int)($row['mes'] ?? 0);
    if ($mes < 1 || $mes > 12) {
        continue;
    }
    $auditorKey = (int)($row['auditor_id'] ?? 0);
    $valor = (float)($row['total_saving'] ?? 0);
    $totalMonthly[$mes] += $valor;
    if (isset($lineMonthlyByAuditor[$auditorKey])) {
        $lineMonthlyByAuditor[$auditorKey][$mes] = $valor;
    }
}

$lineDatasets = [[
    'label' => 'Total saving',
    'data' => array_values($totalMonthly),
    'borderColor' => 'rgba(18, 65, 134, 0.85)',
    'backgroundColor' => 'rgba(18, 65, 134, 0.08)',
    'borderWidth' => 2,
    'tension' => 0.35,
    'pointRadius' => 2,
    'fill' => true,
]];

$lineColors = [
    'rgba(72, 154, 255, 0.8)',
    'rgba(255, 161, 64, 0.8)',
    'rgba(255, 99, 132, 0.8)',
    'rgba(0, 200, 150, 0.8)',
];
$colorIndex = 0;
foreach ($timelineAuditores as $auditor) {
    $audProducts = $lineMonthlyByAuditor[$auditor['id']] ?? array_fill(1, 12, 0.0);
    $lineDatasets[] = [
        'label' => $auditor['label'],
        'data' => array_values($audProducts),
        'borderColor' => $lineColors[$colorIndex % count($lineColors)],
        'backgroundColor' => 'rgba(0, 0, 0, 0)',
        'borderWidth' => 2,
        'pointRadius' => 3,
        'tension' => 0.3,
        'fill' => false,
    ];
    $colorIndex++;
}

$sqlTipoResumo = "
    SELECT
        COALESCE(ng.tipo_negociacao, 'Não informado') AS tipo,
        SUM({$savingExpr}) AS total_saving,
        COUNT(DISTINCT ng.id_negociacao) AS total_registros
    FROM tb_negociacao ng
    INNER JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int
    WHERE {$where}
    GROUP BY tipo
    ORDER BY total_saving DESC
";
$stmt = $conn->prepare($sqlTipoResumo);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$tipoResumoRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sqlTipoMensal = "
    SELECT
        MONTH(ng.data_inicio_neg) AS mes,
        COALESCE(ng.tipo_negociacao, 'Não informado') AS tipo,
        SUM({$savingExpr}) AS total_saving,
        COUNT(DISTINCT ng.id_negociacao) AS total_registros
    FROM tb_negociacao ng
    INNER JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int
    WHERE {$where}
    GROUP BY mes, tipo
    ORDER BY mes, tipo
";
$stmt = $conn->prepare($sqlTipoMensal);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$tipoMensalRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$tipoLabels = array_map(fn($r) => (string)($r['tipo'] ?: 'Não informado'), $tipoResumoRows);
$tipoSavingValues = array_map(fn($r) => (float)($r['total_saving'] ?? 0), $tipoResumoRows);
$tipoCountValues = array_map(fn($r) => (int)($r['total_registros'] ?? 0), $tipoResumoRows);
$tipoPalette = [
    'rgba(159, 196, 214, 0.92)',
    'rgba(196, 168, 132, 0.9)',
    'rgba(184, 150, 176, 0.9)',
    'rgba(136, 174, 162, 0.92)',
    'rgba(127, 143, 176, 0.9)',
    'rgba(203, 183, 142, 0.88)',
];
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
$tipoLineDatasets = [];
$tipoCountLineDatasets = [];
$tipoColorIndex = 0;
foreach ($tipoLabels as $tipoLabel) {
    $tipoLineDatasets[] = [
        'label' => $tipoLabel,
        'data' => array_values($tipoMonthlyMap[$tipoLabel] ?? array_fill(1, 12, 0.0)),
        'borderColor' => $tipoPalette[$tipoColorIndex % count($tipoPalette)],
        'backgroundColor' => 'rgba(0, 0, 0, 0)',
        'borderWidth' => 2,
        'pointRadius' => 3,
        'tension' => 0.28,
        'fill' => false,
    ];
    $tipoCountLineDatasets[] = [
        'label' => $tipoLabel,
        'data' => array_values($tipoMonthlyCountMap[$tipoLabel] ?? array_fill(1, 12, 0)),
        'borderColor' => $tipoPalette[$tipoColorIndex % count($tipoPalette)],
        'backgroundColor' => 'rgba(0, 0, 0, 0)',
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
    align-items: stretch;
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
</style>

<div class="bi-wrapper bi-theme bi-ie-page">
    <div class="bi-header">
        <h1 class="bi-title">Audit Saving</h1>
        <div class="bi-header-actions">
            <span class="text-muted small">Filtro: <?= e($selectedAuditorLabel) ?> <?= e($hospitalId ? '| Hospital ID ' . $hospitalId : '') ?></span>
            <a class="bi-btn bi-btn-secondary" href="<?= $BASE_URL ?>bi/negociacoes-detalhadas" title="Negociações Detalhadas">Negociações</a>
            <a class="bi-btn bi-btn-secondary" href="<?= $BASE_URL ?>bi/saving" title="Dashboard Saving">Saving</a>
        </div>
    </div>

    <form class="bi-panel bi-filters" method="get">
        <div class="bi-filter">
            <label>Ano</label>
            <input type="number" name="ano" value="<?= e($ano) ?>">
        </div>
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
            <label>Auditor</label>
            <select name="auditor_id">
                <option value="">Todos</option>
                <?php foreach ($auditores as $a): ?>
                    <option value="<?= (int)$a['id_usuario'] ?>" <?= $auditorId == $a['id_usuario'] ? 'selected' : '' ?>>
                        <?= e($a['usuario_user']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
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
                <span class="kpi-trend kpi-trend-up"><i class="bi bi-cash-stack"></i>Resultado consolidado</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-2">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-list-ol"></i></span>
                    <small>Negociações</small>
                </div>
                <strong><?= $totalRegistros ?></strong>
                <span class="kpi-trend kpi-trend-neutral"><i class="bi bi-file-earmark-bar-graph"></i>Itens no período</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-3">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-calculator"></i></span>
                    <small>Ticket médio</small>
                </div>
                <strong>R$ <?= number_format($mediaSaving, 2, ',', '.') ?></strong>
                <span class="kpi-trend kpi-trend-neutral"><i class="bi bi-graph-up"></i>Média por negociação</span>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Resumo por auditor</h3>
        <?php if (!empty($auditorResumo)): ?>
            <div class="bi-table-wrapper">
                <table class="bi-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Auditor</th>
                            <th class="text-end">Saving (R$)</th>
                            <th class="text-end">Negociações</th>
                            <th class="text-end">% do total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditorResumo as $index => $row):
                            $valor = (float)($row['total_saving'] ?? 0);
                            $quantidade = (int)($row['total_registros'] ?? 0);
                            $participacao = $totalSaving > 0 ? ($valor / $totalSaving) * 100 : 0;
                        ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= e($row['auditor'] ?: 'Sem auditor') ?></td>
                                <td class="text-end">R$ <?= number_format($valor, 2, ',', '.') ?></td>
                                <td class="text-end"><?= $quantidade ?></td>
                                <td class="text-end"><?= number_format($participacao, 2, ',', '.') ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-muted">Nenhum registro encontrado para o filtro aplicado.</div>
        <?php endif; ?>
    </div>

    <div class="bi-panel">
        <h3>Evolução mensal <?= $auditorId ? 'de ' . e($selectedAuditorLabel) : 'do saving' ?></h3>
        <div class="bi-chart ie-chart-md">
            <canvas id="chartSavingTimeline"></canvas>
        </div>
    </div>
    <div class="saving-type-grid" style="margin-top:12px;">
        <div class="bi-panel saving-type-panel">
            <h3>Tipo de saving por valor</h3>
            <div class="bi-chart ie-chart-sm"><canvas id="chartTipoSavingValor"></canvas></div>
        </div>
        <div class="bi-panel saving-type-panel">
            <h3>Tipo de saving por quantidade</h3>
            <div class="bi-chart ie-chart-sm"><canvas id="chartTipoSavingQuantidade"></canvas></div>
        </div>
    </div>
    <div class="bi-panel">
        <h3>Evolução mensal por tipo de saving</h3>
        <div class="bi-chart ie-chart-md"><canvas id="chartTipoSavingMensal"></canvas></div>
    </div>
    <div class="bi-panel">
        <h3>Evolução mensal por tipo de saving em quantidade</h3>
        <div class="bi-chart ie-chart-md"><canvas id="chartTipoSavingMensalQuantidade"></canvas></div>
    </div>
</div>

<script>
const timelineMonths = <?= json_encode(array_values($monthNames)) ?>;
const lineChartDatasets = <?= json_encode($lineDatasets, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
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
                const label = isMoney
                    ? (window.biMoneyTick ? window.biMoneyTick(rawValue) : ('R$ ' + Number(rawValue || 0).toLocaleString('pt-BR')))
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

function lineChart(ctx, labels, datasets) {
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
            tooltips: {
                callbacks: {
                    label: function (tooltipItem, data) {
                        const ds = data.datasets[tooltipItem.datasetIndex] || {};
                        const label = ds.label ? ds.label + ': ' : '';
                        const value = Number(tooltipItem.yLabel || 0).toLocaleString('pt-BR');
                        return label + value;
                    }
                }
            }
        }
    });
}

function categoryBarChart(ctx, labels, data, isMoney) {
    const scales = window.biChartScales ? window.biChartScales() : undefined;
    if (scales && scales.yAxes && scales.yAxes[0] && scales.yAxes[0].ticks) {
        scales.yAxes[0].ticks.callback = function (value) {
            return isMoney
                ? (window.biMoneyTick ? window.biMoneyTick(value) : ('R$ ' + Number(value || 0).toLocaleString('pt-BR')))
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
                isMoney: isMoney
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
                            ? (label + ': ' + (window.biMoneyTick ? window.biMoneyTick(value) : ('R$ ' + Number(value || 0).toLocaleString('pt-BR'))))
                            : (label + ': ' + Number(value).toLocaleString('pt-BR'));
                    }
                }
            }
        }
    });
}

function lineCountChart(ctx, labels, datasets) {
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

const timelineCanvas = document.getElementById('chartSavingTimeline');
if (timelineCanvas) {
    lineChart(timelineCanvas, timelineMonths, lineChartDatasets);
}
const tipoValorCanvas = document.getElementById('chartTipoSavingValor');
if (tipoValorCanvas) {
    categoryBarChart(tipoValorCanvas, tipoLabels, tipoSavingValues, false);
}
const tipoQuantidadeCanvas = document.getElementById('chartTipoSavingQuantidade');
if (tipoQuantidadeCanvas) {
    categoryBarChart(tipoQuantidadeCanvas, tipoLabels, tipoCountValues, false);
}
const tipoMensalCanvas = document.getElementById('chartTipoSavingMensal');
if (tipoMensalCanvas) {
    lineChart(tipoMensalCanvas, timelineMonths, tipoLineDatasets);
}
const tipoMensalQtdCanvas = document.getElementById('chartTipoSavingMensalQuantidade');
if (tipoMensalQtdCanvas) {
    lineCountChart(tipoMensalQtdCanvas, timelineMonths, tipoCountLineDatasets);
}
</script>

<?php require_once("templates/footer.php"); ?>
