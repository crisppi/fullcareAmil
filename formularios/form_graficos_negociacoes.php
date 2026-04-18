<?php
ob_start();

require_once("templates/header.php");

$baseCondition = "(ng.deletado_neg IS NULL OR ng.deletado_neg != 's')
    AND UPPER(COALESCE(ng.tipo_negociacao, '')) <> 'PRORROGACAO_AUTOMATICA'";

function fetchChartRows(PDO $conn, string $sql): array
{
    $stmt = $conn->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function expandMonthlySeries(array $rows, string $valueKey = 'total'): array
{
    if (!$rows) {
        $year = (int)date('Y');
        $start = new DateTime("$year-01-01");
        $end = new DateTime("$year-12-01");
    } else {
        $periods = array_column($rows, 'periodo_ord');
        sort($periods);
        $start = DateTime::createFromFormat('Y-m', $periods[0]) ?: new DateTime();
        $start->setDate((int)$start->format('Y'), 1, 1);
        $lastKey = end($periods);
        $end = DateTime::createFromFormat('Y-m', $lastKey) ?: new DateTime();
        $end->setDate((int)$end->format('Y'), 12, 1);
    }

    $map = [];
    foreach ($rows as $row) {
        $map[$row['periodo_ord']] = $row[$valueKey] ?? 0;
    }

    $series = [];
    $cursor = clone $start;
    while ($cursor <= $end) {
        $key = $cursor->format('Y-m');
        $series[] = [
            'periodo_label' => $cursor->format('m/Y'),
            'value' => (float)($map[$key] ?? 0)
        ];
        $cursor->modify('+1 month');
    }

    return $series;
}

$monthlySaving = fetchChartRows(
    $conn,
    "
        SELECT 
            DATE_FORMAT(COALESCE(ng.data_inicio_neg, ng.data_fim_neg, ng.updated_at), '%Y-%m') AS periodo_ord,
            DATE_FORMAT(COALESCE(ng.data_inicio_neg, ng.data_fim_neg, ng.updated_at), '%m/%Y') AS periodo_label,
            SUM(COALESCE(ng.saving, 0)) AS total
        FROM tb_negociacao ng
        WHERE $baseCondition
        GROUP BY periodo_ord, periodo_label
        ORDER BY periodo_ord
    "
);

$monthlyCount = fetchChartRows(
    $conn,
    "
        SELECT 
            DATE_FORMAT(COALESCE(ng.data_inicio_neg, ng.data_fim_neg, ng.updated_at), '%Y-%m') AS periodo_ord,
            DATE_FORMAT(COALESCE(ng.data_inicio_neg, ng.data_fim_neg, ng.updated_at), '%m/%Y') AS periodo_label,
            COUNT(*) AS total
        FROM tb_negociacao ng
        WHERE $baseCondition
        GROUP BY periodo_ord, periodo_label
        ORDER BY periodo_ord
    "
);

$savingByAuditor = fetchChartRows(
    $conn,
    "
        SELECT 
            COALESCE(us.usuario_user, 'Sem responsável') AS auditor,
            SUM(COALESCE(ng.saving, 0)) AS total
        FROM tb_negociacao ng
        LEFT JOIN tb_user us ON ng.fk_usuario_neg = us.id_usuario
        WHERE $baseCondition
        GROUP BY auditor
        ORDER BY total DESC
    "
);

$countByAuditor = fetchChartRows(
    $conn,
    "
        SELECT 
            COALESCE(us.usuario_user, 'Sem responsável') AS auditor,
            COUNT(*) AS total
        FROM tb_negociacao ng
        LEFT JOIN tb_user us ON ng.fk_usuario_neg = us.id_usuario
        WHERE $baseCondition
        GROUP BY auditor
        ORDER BY total DESC
    "
);

$savingByType = fetchChartRows(
    $conn,
    "
        SELECT 
            COALESCE(ng.tipo_negociacao, 'Não informado') AS tipo,
            SUM(COALESCE(ng.saving, 0)) AS total
        FROM tb_negociacao ng
        WHERE $baseCondition
        GROUP BY tipo
        ORDER BY total DESC
    "
);

$typeByAuditorRaw = fetchChartRows(
    $conn,
    "
        SELECT 
            COALESCE(us.usuario_user, 'Sem responsável') AS auditor,
            COALESCE(ng.tipo_negociacao, 'Não informado') AS tipo,
            COUNT(*) AS total
        FROM tb_negociacao ng
        LEFT JOIN tb_user us ON ng.fk_usuario_neg = us.id_usuario
        WHERE $baseCondition
        GROUP BY auditor, tipo
        ORDER BY auditor, tipo
    "
);

$savingByHospital = fetchChartRows(
    $conn,
    "
        SELECT 
            COALESCE(ho.nome_hosp, 'Sem hospital') AS hospital,
            SUM(COALESCE(ng.saving, 0)) AS total
        FROM tb_negociacao ng
        LEFT JOIN tb_internacao ac ON ng.fk_id_int = ac.id_internacao
        LEFT JOIN tb_hospital ho ON ac.fk_hospital_int = ho.id_hospital
        WHERE $baseCondition
        GROUP BY hospital
        ORDER BY total DESC
        LIMIT 6
    "
);

$monthlySavingSeries = expandMonthlySeries($monthlySaving, 'total');
$monthlyCountSeries = expandMonthlySeries($monthlyCount, 'total');

$msLabels = array_column($monthlySavingSeries, 'periodo_label');
$msValues = array_map(fn($row) => (float)$row['value'], $monthlySavingSeries);

$mcLabels = array_column($monthlyCountSeries, 'periodo_label');
$mcValues = array_map(fn($row) => (int)$row['value'], $monthlyCountSeries);

$auditorSavingLabels = array_column($savingByAuditor, 'auditor');
$auditorSavingValues = array_map(fn($row) => (float)$row['total'], $savingByAuditor);

$auditorCountLabels = array_column($countByAuditor, 'auditor');
$auditorCountValues = array_map(fn($row) => (int)$row['total'], $countByAuditor);

$typeLabels = array_column($savingByType, 'tipo');
$typeValues = array_map(fn($row) => (float)$row['total'], $savingByType);

$hospLabels = array_column($savingByHospital, 'hospital');
$hospValues = array_map(fn($row) => (float)$row['total'], $savingByHospital);

$typeAudTypes = array_values(array_unique(array_column($typeByAuditorRaw, 'tipo')));
$typeAudAuditors = array_values(array_unique(array_column($typeByAuditorRaw, 'auditor')));

$typeAudMatrix = [];
foreach ($typeAudAuditors as $aud) {
    $typeAudMatrix[$aud] = array_fill(0, count($typeAudTypes), 0);
}
foreach ($typeByAuditorRaw as $item) {
    $aud = $item['auditor'];
    $tipo = $item['tipo'];
    $idx = array_search($tipo, $typeAudTypes, true);
    if ($idx === false) {
        continue;
    }
    $typeAudMatrix[$aud][$idx] = (int)$item['total'];
}

$palette = ['#5e2363', '#ff6384', '#36a2eb', '#4bc0c0', '#9966ff', '#ff9f40', '#20c997', '#d63384'];
$typeAudDatasets = [];
foreach ($typeAudAuditors as $index => $aud) {
    $typeAudDatasets[] = [
        'label' => $aud,
        'backgroundColor' => $palette[$index % count($palette)],
        'data' => $typeAudMatrix[$aud]
    ];
}
?>

<style>
.chart-card {
    background:#fff;
    border-radius:22px;
    border:1px solid #ebe1f5;
    box-shadow:0 12px 28px rgba(45,18,70,.08);
    padding:16px;
    min-height:260px;
    height:100%;
}
.chart-card h5 {
    font-weight:600;
    color:#3a184f;
    margin-bottom:8px;
    font-size:0.95rem;
}
</style>

<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h2 class="mb-1" style="color:#5e2363;">Painel de Negociações</h2>
            <p class="text-muted mb-0">Visão consolidada de savings e volumes de negociações registrados.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="<?= $BASE_URL ?>export_negociacoes_graficos.php" class="btn btn-outline-primary">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i>Exportar XLSX
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-4 col-lg-6">
            <div class="chart-card">
                <h5>Saving mensal</h5>
                <canvas id="chartSavingMensal"></canvas>
            </div>
        </div>
        <div class="col-xl-4 col-lg-6">
            <div class="chart-card">
                <h5>Quantidade de negociações por mês</h5>
                <canvas id="chartCountMensal"></canvas>
            </div>
        </div>

        <div class="col-xl-4 col-lg-6">
            <div class="chart-card">
                <h5>Saving por auditor</h5>
                <canvas id="chartSavingAuditor"></canvas>
            </div>
        </div>
        <div class="col-xl-4 col-lg-6">
            <div class="chart-card">
                <h5>Quantidade de negociações por auditor</h5>
                <canvas id="chartCountAuditor"></canvas>
            </div>
        </div>

        <div class="col-xl-4 col-lg-6">
            <div class="chart-card">
                <h5>Saving por tipo de negociação</h5>
                <canvas id="chartSavingTipo"></canvas>
            </div>
        </div>
        <div class="col-xl-4 col-lg-6">
            <div class="chart-card">
                <h5>Tipos de negociação por auditor</h5>
                <canvas id="chartTipoAuditor"></canvas>
            </div>
        </div>

        <div class="col-xl-4 col-lg-6">
            <div class="chart-card">
                <h5>Hospitais com maior saving</h5>
                <canvas id="chartHospitais"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const palette = ['#5e2363','#ff6384','#36a2eb','#4bc0c0','#9966ff','#ff9f40','#20c997','#d63384','#ffc107','#6c757d'];

const savingMensalData = {
    labels: <?= json_encode($msLabels, JSON_UNESCAPED_UNICODE) ?>,
    datasets: [{
        label: 'Saving (R$)',
        data: <?= json_encode($msValues, JSON_UNESCAPED_UNICODE) ?>,
        borderColor: '#5e2363',
        backgroundColor: 'rgba(94,35,99,0.1)',
        tension: .2,
        fill: true
    }]
};

const countMensalData = {
    labels: <?= json_encode($mcLabels, JSON_UNESCAPED_UNICODE) ?>,
    datasets: [{
        label: 'Negociações',
        data: <?= json_encode($mcValues, JSON_UNESCAPED_UNICODE) ?>,
        borderColor: '#20c997',
        backgroundColor: 'rgba(32,201,151,0.15)',
        tension: .2,
        fill: true
    }]
};

const savingAuditorData = {
    labels: <?= json_encode($auditorSavingLabels, JSON_UNESCAPED_UNICODE) ?>,
    datasets: [{
        label: 'Saving (R$)',
        data: <?= json_encode($auditorSavingValues, JSON_UNESCAPED_UNICODE) ?>,
        backgroundColor: palette
    }]
};

const countAuditorData = {
    labels: <?= json_encode($auditorCountLabels, JSON_UNESCAPED_UNICODE) ?>,
    datasets: [{
        label: 'Negociações',
        data: <?= json_encode($auditorCountValues, JSON_UNESCAPED_UNICODE) ?>,
        backgroundColor: palette
    }]
};

const savingTipoData = {
    labels: <?= json_encode($typeLabels, JSON_UNESCAPED_UNICODE) ?>,
    datasets: [{
        label: 'Saving (R$)',
        data: <?= json_encode($typeValues, JSON_UNESCAPED_UNICODE) ?>,
        backgroundColor: palette
    }]
};

const tipoAuditorData = {
    labels: <?= json_encode($typeAudTypes, JSON_UNESCAPED_UNICODE) ?>,
    datasets: <?= json_encode($typeAudDatasets, JSON_UNESCAPED_UNICODE) ?>
};

const hospitaisData = {
    labels: <?= json_encode($hospLabels, JSON_UNESCAPED_UNICODE) ?>,
    datasets: [{
        label: 'Saving (R$)',
        data: <?= json_encode($hospValues, JSON_UNESCAPED_UNICODE) ?>,
        backgroundColor: palette
    }]
};

const moneyFormatter = (value) => 'R$ ' + Number(value).toLocaleString('pt-BR', {minimumFractionDigits: 2});

new Chart(document.getElementById('chartSavingMensal'), {
    type: 'line',
    data: savingMensalData,
    options: {
        plugins: {tooltip: {callbacks: {label: ctx => `${ctx.dataset.label}: ${moneyFormatter(ctx.parsed.y || 0)}`}}},
        scales: {
            y: {
                beginAtZero: true,
                ticks: {callback: value => 'R$ ' + value.toLocaleString('pt-BR')}
            }
        }
    }
});

new Chart(document.getElementById('chartCountMensal'), {
    type: 'line',
    data: countMensalData,
    options: {
        plugins: {tooltip: {callbacks: {label: ctx => `${ctx.dataset.label}: ${ctx.parsed.y}`}}},
        scales: {y: {beginAtZero: true, ticks: {precision: 0}}}
    }
});

new Chart(document.getElementById('chartSavingAuditor'), {
    type: 'bar',
    data: savingAuditorData,
    options: {
        plugins: {tooltip: {callbacks: {label: ctx => moneyFormatter(ctx.parsed.y || 0)}}},
        scales: {y: {beginAtZero: true, ticks: {callback: value => 'R$ ' + value.toLocaleString('pt-BR')}}}
    }
});

new Chart(document.getElementById('chartCountAuditor'), {
    type: 'bar',
    data: countAuditorData,
    options: {
        indexAxis: 'y',
        plugins: {tooltip: {callbacks: {label: ctx => `${ctx.parsed.x || 0} negociações`}}},
        scales: {x: {beginAtZero: true, ticks: {precision: 0}}}
    }
});

new Chart(document.getElementById('chartSavingTipo'), {
    type: 'doughnut',
    data: savingTipoData,
    options: {
        plugins: {tooltip: {callbacks: {label: ctx => `${ctx.label}: ${moneyFormatter(ctx.parsed || 0)}`}}}
    }
});

new Chart(document.getElementById('chartTipoAuditor'), {
    type: 'bar',
    data: tipoAuditorData,
    options: {
        plugins: {tooltip: {callbacks: {label: ctx => `${ctx.dataset.label}: ${ctx.parsed.y || 0}`}}},
        responsive: true,
        scales: {y: {beginAtZero: true, ticks: {precision: 0}}, x: {stacked: false}}
    }
});

new Chart(document.getElementById('chartHospitais'), {
    type: 'bar',
    data: hospitaisData,
    options: {
        plugins: {tooltip: {callbacks: {label: ctx => moneyFormatter(ctx.parsed.y || 0)}}},
        scales: {y: {beginAtZero: true, ticks: {callback: value => 'R$ ' + value.toLocaleString('pt-BR')}}}
    }
});
</script>
