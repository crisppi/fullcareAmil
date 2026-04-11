<?php
$pageTitle = 'Valor por caso por hospital';
$pageSubtitle = 'Apresentado x final autorizado';
$clearUrl = 'bi/rede-custo';
$redeCurrent = 'custo';
require_once('bi_rede_bootstrap.php');

$rowsSorted = $rows;
usort($rowsSorted, function ($a, $b) {
    return ($b['custo_final'] ?? 0) <=> ($a['custo_final'] ?? 0);
});
$chartRows = array_slice($rowsSorted, 0, 10);
$chartLabels = array_map(fn($r) => $r['hospital'] ?: 'Sem hospital', $chartRows);
$chartVals = array_map(fn($r) => round((float)($r['custo_final'] ?? 0), 0), $chartRows);
?>

<div class="bi-wrapper bi-theme bi-ie-page">
    <div class="bi-header">
        <h1 class="bi-title"><?= e($pageTitle) ?></h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted"><?= e($pageSubtitle) ?></div>
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegacao">
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
                    <span class="kpi-card-v2-icon"><i class="bi bi-currency-dollar"></i></span>
                    <small>Valor médio apresentado</small>
                </div>
                <strong>R$ <?= number_format($network['custo_apresentado'], 2, ',', '.') ?></strong>
                <span class="kpi-trend kpi-trend-up"><i class="bi bi-arrow-up-right"></i>Base da conta</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-2">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-wallet2"></i></span>
                    <small>Valor médio final</small>
                </div>
                <strong>R$ <?= number_format($network['custo_final'], 2, ',', '.') ?></strong>
                <span class="kpi-trend kpi-trend-neutral"><i class="bi bi-cash-stack"></i>Valor autorizado</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-3">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-percent"></i></span>
                    <small>Glosa média</small>
                </div>
                <strong><?= number_format($network['glosa_rate'] * 100, 1, ',', '.') ?>%</strong>
                <span class="kpi-trend kpi-trend-down"><i class="bi bi-arrow-down-right"></i>Diferença apresentada x final</span>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Valor final por hospital</h3>
        <div class="bi-chart ie-chart-sm">
            <canvas id="chartCustoFinal"></canvas>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Detalhe por hospital</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Hospital</th>
                    <th>Valor apresentado</th>
                    <th>Valor final</th>
                    <th>Glosa</th>
                    <th>Casos</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rowsSorted): ?>
                    <tr>
                        <td colspan="5" class="text-center">Sem dados no periodo.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rowsSorted as $row): ?>
                        <tr>
                            <td><?= e($row['hospital'] ?: 'Sem hospital') ?></td>
                            <td><?= number_format((float)$row['custo_apresentado'], 2, ',', '.') ?></td>
                            <td><?= number_format((float)$row['custo_final'], 2, ',', '.') ?></td>
                            <td><?= number_format((float)$row['glosa_rate'] * 100, 1, ',', '.') ?>%</td>
                            <td><?= (int)($row['total_internacoes'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function barChart(ctx, labels, data, color, yTickCallback) {
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
                    ticks: {
                        fontColor: '#eaf6ff',
                        callback: yTickCallback || window.biMoneyTick
                    },
                    gridLines: { color: 'rgba(255,255,255,0.1)' }
                }]
            }
        }
    });
}

const chartLabels = <?= json_encode($chartLabels) ?>;
const chartVals = <?= json_encode($chartVals) ?>;
barChart(document.getElementById('chartCustoFinal'), chartLabels, chartVals, 'rgba(141, 208, 255, 0.7)', window.biMoneyTick);
</script>

<?php require_once("templates/footer.php"); ?>
