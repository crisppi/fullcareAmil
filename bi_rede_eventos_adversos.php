<?php
$pageTitle = 'Eventos adversos por hospital';
$pageSubtitle = 'Indicador de qualidade assistencial';
$clearUrl = 'bi/rede-eventos-adversos';
$redeCurrent = 'eventos';
require_once('bi_rede_bootstrap.php');

$rowsSorted = $rows;
usort($rowsSorted, function ($a, $b) {
    return ($b['eventos_rate'] ?? 0) <=> ($a['eventos_rate'] ?? 0);
});
$chartRows = array_slice($rowsSorted, 0, 10);
$chartLabels = array_map(fn($r) => $r['hospital'] ?: 'Sem hospital', $chartRows);
$chartVals = array_map(fn($r) => round((float)($r['eventos_rate'] ?? 0) * 100, 1), $chartRows);

$monthKeys = [];
$monthLabels = [];
$monthMap = ['01' => 'Jan', '02' => 'Fev', '03' => 'Mar', '04' => 'Abr', '05' => 'Mai', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', '09' => 'Set', '10' => 'Out', '11' => 'Nov', '12' => 'Dez'];
try {
    $ini = new DateTime((string)$dataIni);
    $fim = new DateTime((string)$dataFim);
    if ($ini > $fim) {
        [$ini, $fim] = [$fim, $ini];
    }
    $cursor = (clone $ini)->modify('first day of this month');
    $limit = (clone $fim)->modify('first day of next month');
    while ($cursor < $limit) {
        $k = $cursor->format('Y-m');
        $monthKeys[] = $k;
        $monthLabels[] = $monthMap[$cursor->format('m')] . '/' . $cursor->format('Y');
        $cursor->modify('+1 month');
    }
} catch (Throwable $e) {
}

$monthlySql = "
    SELECT
        DATE_FORMAT(i.data_intern_int, '%Y-%m') AS ym,
        COUNT(DISTINCT i.id_internacao) AS internacoes,
        COUNT(DISTINCT CASE WHEN ev.fk_internacao_ges IS NOT NULL THEN i.id_internacao END) AS eventos
    FROM tb_internacao i
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_seguradora s ON s.id_seguradora = pa.fk_seguradora_pac
    LEFT JOIN (
        SELECT fk_internacao_ges
        FROM tb_gestao
        WHERE evento_adverso_ges = 's'
        GROUP BY fk_internacao_ges
    ) ev ON ev.fk_internacao_ges = i.id_internacao
    {$utiJoin}
    WHERE {$where}
    GROUP BY ym
    ORDER BY ym
";
$stmt = $conn->prepare($monthlySql);
$stmt->execute($params);
$monthlyRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$eventosMensalMap = array_fill_keys($monthKeys, 0);
$taxaMensalMap = array_fill_keys($monthKeys, 0.0);
foreach ($monthlyRows as $r) {
    $ym = (string)($r['ym'] ?? '');
    if (!isset($eventosMensalMap[$ym])) {
        continue;
    }
    $int = (int)($r['internacoes'] ?? 0);
    $evt = (int)($r['eventos'] ?? 0);
    $eventosMensalMap[$ym] = $evt;
    $taxaMensalMap[$ym] = $int > 0 ? ($evt / $int) * 100 : 0;
}
$eventosMensal = array_values($eventosMensalMap);
$taxaMensal = array_values($taxaMensalMap);
?>

<div class="bi-wrapper bi-theme bi-ie-page">
    <div class="bi-header">
        <h1 class="bi-title"><?= e($pageTitle) ?></h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted"><?= e($pageSubtitle) ?></div>
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <?php include 'bi_rede_filters.php'; ?>

    <div class="bi-panel">
        <h3>Indicadores-chave</h3>
        <div class="bi-kpis kpi-dashboard-v2">
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-1">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-exclamation-triangle"></i></span>
                    <small>Taxa de eventos</small>
                </div>
                <strong><?= number_format($network['eventos_rate'] * 100, 1, ',', '.') ?>%</strong>
                <span class="kpi-trend kpi-trend-down"><i class="bi bi-activity"></i>Eventos por internação</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-2">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-clipboard2-heart"></i></span>
                    <small>Internações com evento</small>
                </div>
                <strong><?= (int)$totals['eventos'] ?></strong>
                <span class="kpi-trend kpi-trend-neutral"><i class="bi bi-list-check"></i>Casos sinalizados</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-3">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-building"></i></span>
                    <small>Total de internações</small>
                </div>
                <strong><?= (int)$totals['internacoes'] ?></strong>
                <span class="kpi-trend kpi-trend-up"><i class="bi bi-hospital"></i>Base assistencial</span>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Eventos adversos por hospital</h3>
        <div class="bi-chart ie-chart-sm">
            <canvas id="chartEventos"></canvas>
        </div>
    </div>
    <div class="bi-panel">
        <h3>Número de eventos mensal e taxa (eventos/internações)</h3>
        <div class="bi-chart ie-chart-md">
            <canvas id="chartEventosMensal"></canvas>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Detalhe por hospital</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Hospital</th>
                    <th>Eventos adversos</th>
                    <th>Internações com evento</th>
                    <th>Total de internacoes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rowsSorted): ?>
                    <tr>
                        <td colspan="4" class="text-center">Sem dados no período.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rowsSorted as $row): ?>
                        <tr>
                            <td><?= e($row['hospital'] ?: 'Sem hospital') ?></td>
                            <td><?= number_format((float)$row['eventos_rate'] * 100, 1, ',', '.') ?>%</td>
                            <td><?= (int)($row['internacoes_evento'] ?? 0) ?></td>
                            <td><?= (int)($row['total_internacoes'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function barChart(ctx, labels, data, color) {
    if (!ctx || !labels.length) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: color,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: { display: false },
            scales: {
                xAxes: [{ ticks: { fontColor: '#eaf6ff' }, gridLines: { color: 'rgba(255,255,255,0.1)' } }],
                yAxes: [{
                    ticks: { fontColor: '#eaf6ff', callback: function (v) { return v + '%'; } },
                    gridLines: { color: 'rgba(255,255,255,0.1)' }
                }]
            }
        }
    });
}

const chartLabels = <?= json_encode($chartLabels) ?>;
const chartVals = <?= json_encode($chartVals) ?>;
barChart(document.getElementById('chartEventos'), chartLabels, chartVals, 'rgba(111, 223, 194, 0.7)');

const mensalLabels = <?= json_encode($monthLabels, JSON_UNESCAPED_UNICODE) ?>;
const eventosMensal = <?= json_encode($eventosMensal) ?>;
const taxaMensal = <?= json_encode($taxaMensal) ?>;
if (mensalLabels.length) {
    new Chart(document.getElementById('chartEventosMensal'), {
        type: 'bar',
        data: {
            labels: mensalLabels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Eventos',
                    data: eventosMensal,
                    backgroundColor: 'rgba(127, 196, 255, 0.68)',
                    yAxisID: 'y-count'
                },
                {
                    type: 'line',
                    label: 'Taxa eventos/internações',
                    data: taxaMensal,
                    borderColor: 'rgba(255, 205, 86, 0.9)',
                    backgroundColor: 'rgba(255,205,86,0.15)',
                    borderWidth: 2,
                    tension: 0.35,
                    pointRadius: 3,
                    fill: false,
                    yAxisID: 'y-rate'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: { display: true, labels: { fontColor: '#eaf6ff' } },
            scales: {
                xAxes: [{ ticks: { fontColor: '#eaf6ff' }, gridLines: { color: 'rgba(255,255,255,0.1)' } }],
                yAxes: [
                    {
                        id: 'y-count',
                        position: 'left',
                        ticks: { fontColor: '#eaf6ff', beginAtZero: true },
                        gridLines: { color: 'rgba(255,255,255,0.1)' }
                    },
                    {
                        id: 'y-rate',
                        position: 'right',
                        ticks: { fontColor: '#eaf6ff', beginAtZero: true, callback: function (v) { return v + '%'; } },
                        gridLines: { drawOnChartArea: false }
                    }
                ]
            },
            tooltips: {
                callbacks: {
                    label: function (item, data) {
                        const ds = data.datasets[item.datasetIndex] || {};
                        if (ds.yAxisID === 'y-rate') {
                            return (ds.label || '') + ': ' + Number(item.yLabel || 0).toLocaleString('pt-BR') + '%';
                        }
                        return (ds.label || '') + ': ' + Number(item.yLabel || 0).toLocaleString('pt-BR');
                    }
                }
            }
        }
    });
}
</script>

<?php require_once("templates/footer.php"); ?>
