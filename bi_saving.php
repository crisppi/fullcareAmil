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
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme bi-ie-page">
    <div class="bi-header">
        <h1 class="bi-title">Saving por Hospital</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted">Ano <?= e($ano) ?><?= $mes > 0 ? ' • Mês ' . e($mes) : '' ?></div>
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
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $mes === $m ? 'selected' : '' ?>><?= $m ?></option>
                <?php endfor; ?>
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
</div>

<script>
const labelsHosp = <?= json_encode($labelsHosp) ?>;
const savingHosp = <?= json_encode($savingHosp) ?>;
const countHosp = <?= json_encode($countHosp) ?>;
const qtdDiariasHosp = <?= json_encode($qtdDiariasHosp) ?>;
const labelsMes = <?= json_encode($labelsMes) ?>;
const savingMensal = <?= json_encode($savingMensal) ?>;

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

barChart(document.getElementById('chartSavingHospital'), labelsHosp, savingHosp, 'rgba(141, 208, 255, 0.7)');
new Chart(document.getElementById('chartQtdeHospital'), {
    type: 'bar',
    data: { labels: labelsHosp, datasets: [{ data: countHosp, backgroundColor: 'rgba(208, 113, 176, 0.7)', isMoney: false }] },
    plugins: [biValueLabelPlugin],
    options: {
        responsive: true,
        maintainAspectRatio: false,
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
</script>

<?php require_once("templates/footer.php"); ?>
