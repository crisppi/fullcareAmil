<?php
$pageTitle = 'Performance Comparativa da Rede Hospitalar';
$pageSubtitle = 'Custo, qualidade e eficiencia por hospital';
$clearUrl = 'bi/rede-comparativa';
$redeCurrent = 'comparativa';
require_once('bi_rede_bootstrap.php');

$rowsSorted = $rows;
usort($rowsSorted, function ($a, $b) {
    return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
});

$chartRows = $rows;
usort($chartRows, function ($a, $b) {
    return ($b['permanencia_media'] ?? 0) <=> ($a['permanencia_media'] ?? 0);
});
$chartRows = array_slice($chartRows, 0, 10);
$chartLabels = array_map(fn($r) => $r['hospital'] ?: 'Sem hospital', $chartRows);
$chartVals = array_map(fn($r) => round((float)($r['permanencia_media'] ?? 0), 1), $chartRows);
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
                <div class="kpi-card-v2-head"><span class="kpi-card-v2-icon"><i class="bi bi-currency-dollar"></i></span><small>Custo médio apresentado</small></div>
                <strong>R$ <?= number_format($network['custo_apresentado'], 2, ',', '.') ?></strong>
                <span class="kpi-trend kpi-trend-up"><i class="bi bi-receipt"></i>Valor inicial</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-2">
                <div class="kpi-card-v2-head"><span class="kpi-card-v2-icon"><i class="bi bi-wallet2"></i></span><small>Custo médio final</small></div>
                <strong>R$ <?= number_format($network['custo_final'], 2, ',', '.') ?></strong>
                <span class="kpi-trend kpi-trend-neutral"><i class="bi bi-check2-square"></i>Autorizado</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-3">
                <div class="kpi-card-v2-head"><span class="kpi-card-v2-icon"><i class="bi bi-percent"></i></span><small>Glosa média</small></div>
                <strong><?= number_format($network['glosa_rate'] * 100, 1, ',', '.') ?>%</strong>
                <span class="kpi-trend kpi-trend-down"><i class="bi bi-arrow-down-right"></i>Impacto financeiro</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-4">
                <div class="kpi-card-v2-head"><span class="kpi-card-v2-icon"><i class="bi bi-slash-circle"></i></span><small>Rejeição capeante</small></div>
                <strong><?= number_format($network['rejeicao_rate'] * 100, 1, ',', '.') ?>%</strong>
                <span class="kpi-trend kpi-trend-down"><i class="bi bi-x-octagon"></i>Contas paradas</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-1">
                <div class="kpi-card-v2-head"><span class="kpi-card-v2-icon"><i class="bi bi-clock-history"></i></span><small>Permanência média</small></div>
                <strong><?= number_format($network['permanencia_media'], 1, ',', '.') ?> d</strong>
                <span class="kpi-trend kpi-trend-neutral"><i class="bi bi-hourglass-split"></i>Tempo assistencial</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-2">
                <div class="kpi-card-v2-head"><span class="kpi-card-v2-icon"><i class="bi bi-exclamation-triangle"></i></span><small>Eventos adversos</small></div>
                <strong><?= number_format($network['eventos_rate'] * 100, 1, ',', '.') ?>%</strong>
                <span class="kpi-trend kpi-trend-down"><i class="bi bi-activity"></i>Indicador de qualidade</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-3">
                <div class="kpi-card-v2-head"><span class="kpi-card-v2-icon"><i class="bi bi-arrow-repeat"></i></span><small>Readmissão 30d</small></div>
                <strong><?= number_format($network['readm_rate'] * 100, 1, ',', '.') ?>%</strong>
                <span class="kpi-trend kpi-trend-neutral"><i class="bi bi-diagram-2"></i>Retorno em 30 dias</span>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Permanencia media por hospital</h3>
        <div class="bi-chart ie-chart-sm">
            <canvas id="chartPermanenciaRede"></canvas>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Ranking de hospitais (melhor custo x qualidade)</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Hospital</th>
                    <th>Score</th>
                    <th>Custo por caso (apresentado)</th>
                    <th>Custo por caso (final)</th>
                    <th>Glosa</th>
                    <th>Rejeicao</th>
                    <th>Permanencia</th>
                    <th>Eventos adversos</th>
                    <th>Readmissao</th>
                    <th>Casos</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rowsSorted): ?>
                    <tr>
                        <td colspan="10" class="text-center">Sem dados no periodo.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rowsSorted as $row): ?>
                        <tr>
                            <td><?= e($row['hospital'] ?: 'Sem hospital') ?></td>
                            <td><?= number_format((float)$row['score'], 1, ',', '.') ?></td>
                            <td><?= number_format((float)$row['custo_apresentado'], 2, ',', '.') ?></td>
                            <td><?= number_format((float)$row['custo_final'], 2, ',', '.') ?></td>
                            <td><?= number_format((float)$row['glosa_rate'] * 100, 1, ',', '.') ?>%</td>
                            <td><?= number_format((float)$row['rejeicao_rate'] * 100, 1, ',', '.') ?>%</td>
                            <td><?= number_format((float)($row['permanencia_media'] ?? 0), 1, ',', '.') ?> d</td>
                            <td><?= number_format((float)$row['eventos_rate'] * 100, 1, ',', '.') ?>%</td>
                            <td><?= number_format((float)$row['readm_rate'] * 100, 1, ',', '.') ?>%</td>
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
                        callback: yTickCallback || function (v) { return v; }
                    },
                    gridLines: { color: 'rgba(255,255,255,0.1)' }
                }]
            }
        }
    });
}

const chartLabels = <?= json_encode($chartLabels) ?>;
const chartVals = <?= json_encode($chartVals) ?>;
barChart(document.getElementById('chartPermanenciaRede'), chartLabels, chartVals, 'rgba(64, 181, 255, 0.7)');
</script>

<?php require_once("templates/footer.php"); ?>
