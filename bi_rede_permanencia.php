<?php
$pageTitle = 'Permanência média por hospital';
$pageSubtitle = 'Variacao entre hospitais e gargalos de eficiencia';
$clearUrl = 'bi/rede-permanencia';
$redeCurrent = 'permanencia';
require_once('bi_rede_bootstrap.php');

$rowsSorted = $rows;
usort($rowsSorted, function ($a, $b) {
    return ($b['permanencia_media'] ?? 0) <=> ($a['permanencia_media'] ?? 0);
});
$chartRows = array_slice($rowsSorted, 0, 10);
$chartLabels = array_map(fn($r) => $r['hospital'] ?: 'Sem hospital', $chartRows);
$chartVals = array_map(fn($r) => round((float)($r['permanencia_media'] ?? 0), 1), $chartRows);
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
                <small>Permanência média</small>
                <strong><?= number_format($network['permanencia_media'], 1, ',', '.') ?> d</strong>
            </div>
            <div class="bi-kpi">
                <small>Internações</small>
                <strong><?= (int)$totals['internacoes'] ?></strong>
            </div>
            <div class="bi-kpi">
                <small>Hospitais avaliados</small>
                <strong><?= count($rows) ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Permanência por hospital</h3>
        <div class="bi-chart">
            <canvas id="chartPermanencia"></canvas>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Detalhe por hospital</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Hospital</th>
                    <th>Permanência média</th>
                    <th>Internações</th>
                    <th>Casos com alta</th>
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
                            <td><?= number_format((float)($row['permanencia_media'] ?? 0), 1, ',', '.') ?> d</td>
                            <td><?= (int)($row['total_internacoes'] ?? 0) ?></td>
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
                yAxes: [{ ticks: { fontColor: '#eaf6ff' }, gridLines: { color: 'rgba(255,255,255,0.1)' } }]
            }
        }
    });
}

const chartLabels = <?= json_encode($chartLabels) ?>;
const chartVals = <?= json_encode($chartVals) ?>;
barChart(document.getElementById('chartPermanencia'), chartLabels, chartVals, 'rgba(64, 181, 255, 0.7)');
</script>

<?php require_once("templates/footer.php"); ?>
