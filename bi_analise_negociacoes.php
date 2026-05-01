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
$negociacaoRealClause = "UPPER(COALESCE(ng.tipo_negociacao, '')) <> 'PRORROGACAO_AUTOMATICA'";

$savingExpr = "COALESCE(
    NULLIF(ng.saving, 0),
    CASE
        WHEN UPPER(COALESCE(ng.tipo_negociacao, '')) LIKE 'TROCA%' THEN (COALESCE(aco_de.valor_aco, 0) - COALESCE(aco_para.valor_aco, 0)) * COALESCE(ng.qtd, 0)
        WHEN UPPER(COALESCE(ng.tipo_negociacao, '')) = 'ALTA TARDIA APTO' THEN COALESCE(NULLIF(aco_para.valor_aco, 0), COALESCE(aco_de.valor_aco, 0)) * COALESCE(NULLIF(ng.qtd, 0), 1)
        WHEN UPPER(COALESCE(ng.tipo_negociacao, '')) LIKE '%1/2 DIARIA%' THEN (COALESCE(aco_de.valor_aco, 0) / 2) * COALESCE(ng.qtd, 0)
        ELSE COALESCE(aco_de.valor_aco, 0) * COALESCE(ng.qtd, 0)
    END,
    0
)";

if ($ano === null && !filter_has_var(INPUT_GET, 'ano')) {
    $stmtAno = $conn->query("SELECT MAX(YEAR(data_inicio_neg)) AS ano FROM tb_negociacao WHERE data_inicio_neg IS NOT NULL AND data_inicio_neg <> '0000-00-00'");
    $anoDb = $stmtAno->fetch(PDO::FETCH_ASSOC) ?: [];
    $ano = (int)($anoDb['ano'] ?? date('Y'));
}

$where = "ng.data_inicio_neg IS NOT NULL
    AND ng.data_inicio_neg <> '0000-00-00'
    AND ng.saving IS NOT NULL
    AND {$negociacaoRealClause}
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

$sqlTipos = "
    SELECT
        COALESCE(NULLIF(ng.tipo_negociacao, ''), 'Não informado') AS tipo_negociacao,
        SUM({$savingExpr}) AS total_saving,
        COUNT(*) AS total_qtd
    FROM tb_negociacao ng
    INNER JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int
    LEFT JOIN tb_acomodacao aco_de ON aco_de.fk_hospital = i.fk_hospital_int
        AND LOWER(TRIM(aco_de.acomodacao_aco)) = LOWER(TRIM(IF(LOCATE('-', ng.troca_de) > 0, SUBSTRING_INDEX(ng.troca_de, '-', -1), ng.troca_de)))
    LEFT JOIN tb_acomodacao aco_para ON aco_para.fk_hospital = i.fk_hospital_int
        AND LOWER(TRIM(aco_para.acomodacao_aco)) = LOWER(TRIM(IF(LOCATE('-', ng.troca_para) > 0, SUBSTRING_INDEX(ng.troca_para, '-', -1), ng.troca_para)))
    WHERE {$where}
    GROUP BY tipo_negociacao
    ORDER BY total_saving DESC, total_qtd DESC, tipo_negociacao ASC
";
$stmt = $conn->prepare($sqlTipos);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$tipoRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sqlMensal = "
    SELECT
        MONTH(ng.data_inicio_neg) AS mes,
        COALESCE(NULLIF(ng.tipo_negociacao, ''), 'Não informado') AS tipo_negociacao,
        SUM({$savingExpr}) AS total_saving,
        COUNT(*) AS total_qtd
    FROM tb_negociacao ng
    INNER JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int
    LEFT JOIN tb_acomodacao aco_de ON aco_de.fk_hospital = i.fk_hospital_int
        AND LOWER(TRIM(aco_de.acomodacao_aco)) = LOWER(TRIM(IF(LOCATE('-', ng.troca_de) > 0, SUBSTRING_INDEX(ng.troca_de, '-', -1), ng.troca_de)))
    LEFT JOIN tb_acomodacao aco_para ON aco_para.fk_hospital = i.fk_hospital_int
        AND LOWER(TRIM(aco_para.acomodacao_aco)) = LOWER(TRIM(IF(LOCATE('-', ng.troca_para) > 0, SUBSTRING_INDEX(ng.troca_para, '-', -1), ng.troca_para)))
    WHERE {$where}
    GROUP BY mes, tipo_negociacao
    ORDER BY mes ASC, total_saving DESC, total_qtd DESC, tipo_negociacao ASC
";
$stmt = $conn->prepare($sqlMensal);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$monthlyRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$labelsTipos = [];
$savingValores = [];
$qtdValores = [];
$totalSaving = 0.0;
$totalQtd = 0;
foreach ($tipoRows as $row) {
    $labelsTipos[] = $row['tipo_negociacao'];
    $savingValores[] = (float)($row['total_saving'] ?? 0);
    $qtdValores[] = (int)($row['total_qtd'] ?? 0);
    $totalSaving += (float)($row['total_saving'] ?? 0);
    $totalQtd += (int)($row['total_qtd'] ?? 0);
}

$monthNames = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
$tiposMensais = [];
$monthlySavingTotals = array_fill(1, 12, 0.0);
$monthlyQtdTotals = array_fill(1, 12, 0);
foreach ($monthlyRows as $row) {
    $tipo = (string)($row['tipo_negociacao'] ?? 'Não informado');
    $mes = (int)($row['mes'] ?? 0);
    if ($mes < 1 || $mes > 12) {
        continue;
    }
    if (!isset($tiposMensais[$tipo])) {
        $tiposMensais[$tipo] = [
            'saving' => array_fill(1, 12, 0.0),
            'qtd' => array_fill(1, 12, 0),
        ];
    }
    $saving = (float)($row['total_saving'] ?? 0);
    $qtd = (int)($row['total_qtd'] ?? 0);
    $tiposMensais[$tipo]['saving'][$mes] = $saving;
    $tiposMensais[$tipo]['qtd'][$mes] = $qtd;
    $monthlySavingTotals[$mes] += $saving;
    $monthlyQtdTotals[$mes] += $qtd;
}

$palette = [
    'rgba(72, 154, 255, 0.88)',
    'rgba(255, 161, 64, 0.88)',
    'rgba(255, 99, 132, 0.88)',
    'rgba(0, 200, 150, 0.88)',
    'rgba(163, 102, 255, 0.88)',
    'rgba(255, 205, 86, 0.88)',
    'rgba(54, 162, 235, 0.88)',
    'rgba(201, 203, 207, 0.88)',
];

$doughnutColors = [];
foreach ($labelsTipos as $index => $label) {
    $doughnutColors[] = $palette[$index % count($palette)];
}

$monthlySavingDatasets = [];
$monthlyQtdDatasets = [];
$colorIndex = 0;
foreach ($labelsTipos as $tipo) {
    $baseColor = $palette[$colorIndex % count($palette)];
    $savingSeries = [];
    $qtdSeries = [];
    for ($mes = 1; $mes <= 12; $mes++) {
        $savingMes = (float)($tiposMensais[$tipo]['saving'][$mes] ?? 0);
        $qtdMes = (int)($tiposMensais[$tipo]['qtd'][$mes] ?? 0);
        $savingSeries[] = round($savingMes, 2);
        $qtdSeries[] = $qtdMes;
    }
    $monthlySavingDatasets[] = [
        'label' => $tipo,
        'data' => $savingSeries,
        'backgroundColor' => $baseColor,
        'borderColor' => $baseColor,
        'borderWidth' => 1,
    ];
    $monthlyQtdDatasets[] = [
        'label' => $tipo,
        'data' => $qtdSeries,
        'backgroundColor' => $baseColor,
        'borderColor' => $baseColor,
        'borderWidth' => 1,
    ];
    $colorIndex++;
}
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>
<style>
.bi-analise-grid-2 {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    margin-top: 16px;
    align-items: stretch;
}

.bi-analise-grid-2 > .bi-panel {
    margin-top: 0 !important;
}

.bi-analise-donut-panel {
    display: flex;
    flex-direction: column;
    height: 460px;
    min-height: 460px;
    overflow: hidden;
}

.bi-analise-donut-panel .bi-chart {
    position: relative;
    flex: 1 1 auto;
    min-height: 0;
    height: 100%;
}

.bi-analise-donut-panel canvas {
    display: block;
    width: 100% !important;
    height: 100% !important;
}

@media (max-width: 980px) {
    .bi-analise-grid-2 {
        grid-template-columns: 1fr;
    }

    .bi-analise-donut-panel {
        height: 400px;
        min-height: 400px;
    }

    .bi-analise-donut-panel .bi-chart {
        min-height: 0;
        height: 100%;
    }
}
</style>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Análise das Negociações</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted">Composição por tipo e evolução mensal</div>
            <a class="bi-btn bi-btn-secondary" href="<?= $BASE_URL ?>bi/negociacoes-detalhadas" title="Negociações Detalhadas">Detalhadas</a>
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
        <div class="bi-filter">
            <label>Ano</label>
            <input type="number" name="ano" value="<?= e($ano) ?>">
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="bi-panel">
        <div class="bi-kpis">
            <div class="bi-kpi">
                <small>Tipos de negociação</small>
                <strong><?= count($labelsTipos) ?></strong>
            </div>
            <div class="bi-kpi">
                <small>Total saving</small>
                <strong>R$ <?= number_format($totalSaving, 2, ',', '.') ?></strong>
            </div>
            <div class="bi-kpi">
                <small>Total quantidade</small>
                <strong><?= number_format($totalQtd, 0, ',', '.') ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-analise-grid-2">
        <div class="bi-panel bi-analise-donut-panel">
            <h3>Tipo de negociação por valor (%)</h3>
            <div class="bi-chart"><canvas id="chartTipoValor"></canvas></div>
        </div>
        <div class="bi-panel bi-analise-donut-panel">
            <h3>Tipo de negociação por quantidade (%)</h3>
            <div class="bi-chart"><canvas id="chartTipoQuantidade"></canvas></div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Evolução mensal por tipo: valor</h3>
        <div class="bi-chart"><canvas id="chartMensalValor"></canvas></div>
    </div>

    <div class="bi-panel">
        <h3>Evolução mensal por tipo: quantidade</h3>
        <div class="bi-chart"><canvas id="chartMensalQuantidade"></canvas></div>
    </div>
</div>

<script>
const labelsTipos = <?= json_encode($labelsTipos) ?>;
const savingValores = <?= json_encode($savingValores) ?>;
const qtdValores = <?= json_encode($qtdValores) ?>;
const doughnutColors = <?= json_encode($doughnutColors) ?>;
const labelsMes = <?= json_encode($monthNames) ?>;
const monthlySavingDatasets = <?= json_encode($monthlySavingDatasets) ?>;
const monthlyQtdDatasets = <?= json_encode($monthlyQtdDatasets) ?>;

const doughnutValuePlugin = {
    id: 'doughnutValuePlugin',
    afterDatasetsDraw: function(chart) {
        const dataset = chart.data.datasets[0];
        if (!dataset || !Array.isArray(dataset.data)) {
            return;
        }
        const total = dataset.data.reduce((acc, value) => acc + Number(value || 0), 0);
        if (!total) {
            return;
        }
        const canvasId = (chart.canvas && chart.canvas.id) ? chart.canvas.id : '';
        const ctx = chart.ctx;
        ctx.save();
        ctx.font = '600 11px Poppins, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        dataset.data.forEach(function(value, index) {
            const numericValue = Number(value || 0);
            if (!numericValue) {
                return;
            }
            const meta = chart.getDatasetMeta(0);
            const arc = meta && meta.data ? meta.data[index] : null;
            if (!arc || typeof arc.tooltipPosition !== 'function') {
                return;
            }
            const position = arc.tooltipPosition();
            const percent = (numericValue / total) * 100;
            const isMoneyChart = canvasId === 'chartTipoValor';
            const absolute = isMoneyChart
                ? ('R$ ' + numericValue.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))
                : numericValue.toLocaleString('pt-BR', { maximumFractionDigits: 0 });
            const label = [
                percent.toFixed(1).replace('.', ',') + '%',
                absolute
            ];
            ctx.fillStyle = '#ffffff';
            ctx.strokeStyle = 'rgba(9, 35, 56, 0.55)';
            ctx.lineWidth = 3;
            ctx.strokeText(label[0], position.x, position.y - 8);
            ctx.fillText(label[0], position.x, position.y - 8);
            ctx.strokeText(label[1], position.x, position.y + 8);
            ctx.fillText(label[1], position.x, position.y + 8);
        });

        ctx.restore();
    }
};

function percentLabel(value, total) {
    if (!total) return '0%';
    return (Number(value || 0) / total * 100).toFixed(1).replace('.', ',') + '%';
}

function makeDoughnut(canvasId, values, absoluteFormatter) {
    const total = values.reduce((acc, value) => acc + Number(value || 0), 0);
    return new Chart(document.getElementById(canvasId), {
        type: 'doughnut',
        plugins: [doughnutValuePlugin],
        data: {
            labels: labelsTipos,
            datasets: [{
                data: values,
                backgroundColor: doughnutColors,
                borderWidth: 1,
                borderColor: 'rgba(9, 35, 56, 0.4)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: { position: 'bottom', labels: { fontColor: '#eaf6ff' } },
            tooltips: {
                callbacks: {
                    label: function(tooltipItem, data) {
                        const idx = tooltipItem.index;
                        const label = data.labels[idx] || '';
                        const value = Number(data.datasets[0].data[idx] || 0);
                        return label + ': ' + percentLabel(value, total) + ' (' + absoluteFormatter(value) + ')';
                    }
                }
            }
        }
    });
}

function makeStackedBar(canvasId, datasets, kind) {
    const moneyChart = kind === 'money';
    return new Chart(document.getElementById(canvasId), {
        type: 'bar',
        data: {
            labels: labelsMes,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: { position: 'bottom', labels: { fontColor: '#eaf6ff' } },
            scales: {
                xAxes: [{ stacked: true, ticks: { fontColor: '#eaf6ff' }, gridLines: { color: 'rgba(255,255,255,0.08)' } }],
                yAxes: [{
                    stacked: true,
                    ticks: {
                        beginAtZero: true,
                        callback: function(value) {
                            if (moneyChart) {
                                return 'R$ ' + Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            }
                            return Number(value || 0).toLocaleString('pt-BR');
                        },
                        fontColor: '#eaf6ff'
                    },
                    gridLines: { color: 'rgba(255,255,255,0.08)' }
                }]
            },
            tooltips: {
                callbacks: {
                    label: function(tooltipItem, data) {
                        const dataset = data.datasets[tooltipItem.datasetIndex] || {};
                        const value = Number(tooltipItem.yLabel || 0);
                        const formatted = moneyChart
                            ? ('R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))
                            : value.toLocaleString('pt-BR');
                        return (dataset.label || '') + ': ' + formatted;
                    }
                }
            }
        }
    });
}

makeDoughnut('chartTipoValor', savingValores, function(value) {
    return 'R$ ' + Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
});
makeDoughnut('chartTipoQuantidade', qtdValores, function(value) {
    return Number(value || 0).toLocaleString('pt-BR');
});
makeStackedBar('chartMensalValor', monthlySavingDatasets, 'money');
makeStackedBar('chartMensalQuantidade', monthlyQtdDatasets, 'count');
</script>

<?php require_once("templates/footer.php"); ?>
