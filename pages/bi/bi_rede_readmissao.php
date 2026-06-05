<?php
$pageTitle = 'Readmissão 30 dias por hospital';
$pageSubtitle = 'Indicador de resolucao e qualidade assistencial';
$clearUrl = 'bi/rede-readmissao';
$redeCurrent = 'readmissao';
require_once('bi_rede_bootstrap.php');

$rowsSorted = $rows;
usort($rowsSorted, function ($a, $b) {
    return ($b['readm_rate'] ?? 0) <=> ($a['readm_rate'] ?? 0);
});
$chartRows = array_slice($rowsSorted, 0, 10);
$chartLabels = array_map(fn($r) => $r['hospital'] ?: 'Sem hospital', $chartRows);
$chartVals = array_map(fn($r) => round((float)($r['readm_rate'] ?? 0) * 100, 1), $chartRows);
?>

<div class="bi-wrapper bi-theme">
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
        <div class="bi-kpis kpi-compact">
            <div class="bi-kpi">
                <small>Readmissão media</small>
                <strong><?= number_format($network['readm_rate'] * 100, 1, ',', '.') ?>%</strong>
            </div>
            <div class="bi-kpi">
                <small>Readmissoes</small>
                <strong><?= (int)$totals['readm'] ?></strong>
            </div>
            <div class="bi-kpi">
                <small>Altas analisadas</small>
                <strong><?= (int)$totals['altas'] ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Readmissão por hospital</h3>
        <div class="bi-chart">
            <canvas id="chartReadm"></canvas>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Detalhe por hospital</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Hospital</th>
                    <th>Readmissão</th>
                    <th>Readmissoes</th>
                    <th>Altas analisadas</th>
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
                            <td><?= number_format((float)$row['readm_rate'] * 100, 1, ',', '.') ?>%</td>
                            <td><?= (int)($row['readm'] ?? 0) ?></td>
                            <td><?= (int)($row['total_altas'] ?? 0) ?></td>
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
barChart(document.getElementById('chartReadm'), chartLabels, chartVals, 'rgba(127, 196, 255, 0.7)');
</script>

<?php require_once("templates/footer.php"); ?>
