<?php
$pageTitle = 'Ranking de hospitais';
$pageSubtitle = 'Melhor custo x melhor qualidade';
$clearUrl = 'bi/rede-ranking';
$redeCurrent = 'ranking';
require_once('bi_rede_bootstrap.php');

$rowsSorted = $rows;
usort($rowsSorted, function ($a, $b) {
    return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
});
$topRows = array_slice($rowsSorted, 0, 5);
$chartLabels = array_map(fn($r) => $r['hospital'] ?: 'Sem hospital', $topRows);
$chartVals = array_map(fn($r) => (float)($r['score'] ?? 0), $topRows);
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
        <h3>Top 5 hospitais</h3>
        <div class="bi-chart">
            <canvas id="chartRanking"></canvas>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Ranking completo</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Hospital</th>
                    <th>Score</th>
                    <th>Custo final</th>
                    <th>Glosa</th>
                    <th>Rejeição</th>
                    <th>Permanência</th>
                    <th>Eventos adversos</th>
                    <th>Readmissão</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rowsSorted): ?>
                    <tr>
                        <td colspan="8" class="text-center">Sem dados no período.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rowsSorted as $row): ?>
                        <tr>
                            <td><?= e($row['hospital'] ?: 'Sem hospital') ?></td>
                            <td><?= number_format((float)$row['score'], 1, ',', '.') ?></td>
                            <td><?= number_format((float)$row['custo_final'], 2, ',', '.') ?></td>
                            <td><?= number_format((float)$row['glosa_rate'] * 100, 1, ',', '.') ?>%</td>
                            <td><?= number_format((float)$row['rejeicao_rate'] * 100, 1, ',', '.') ?>%</td>
                            <td><?= number_format((float)($row['permanencia_media'] ?? 0), 1, ',', '.') ?> d</td>
                            <td><?= number_format((float)$row['eventos_rate'] * 100, 1, ',', '.') ?>%</td>
                            <td><?= number_format((float)$row['readm_rate'] * 100, 1, ',', '.') ?>%</td>
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
barChart(document.getElementById('chartRanking'), chartLabels, chartVals, 'rgba(192, 110, 163, 0.7)');
</script>

<?php require_once("templates/footer.php"); ?>
