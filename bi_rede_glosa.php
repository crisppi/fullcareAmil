<?php
$pageTitle = 'Taxa de glosa por hospital';
$pageSubtitle = 'Conformidade e diferenca apresentada vs autorizada';
$clearUrl = 'bi/rede-glosa';
$redeCurrent = 'glosa';
require_once('bi_rede_bootstrap.php');

$rowsSorted = $rows;
usort($rowsSorted, function ($a, $b) {
    return ($b['glosa_rate'] ?? 0) <=> ($a['glosa_rate'] ?? 0);
});
$chartRows = array_slice($rowsSorted, 0, 10);
$chartLabels = array_map(fn($r) => $r['hospital'] ?: 'Sem hospital', $chartRows);
$chartVals = array_map(fn($r) => round((float)($r['glosa_rate'] ?? 0) * 100, 1), $chartRows);
?>

<style>
.bi-glosa-page .bi-header {
    padding-left: 26px;
}

.bi-glosa-kpis {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}

.bi-glosa-kpi {
    min-height: 132px;
    padding: 14px 16px;
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.10), 0 10px 22px rgba(6, 23, 44, 0.26);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 8px;
}

.bi-glosa-kpi-head {
    display: flex;
    align-items: center;
    gap: 10px;
}

.bi-glosa-kpi-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.12);
    color: #7be3ff;
    border: 1px solid rgba(255, 255, 255, 0.24);
    font-size: 1.05rem;
}

.bi-glosa-kpi small {
    margin: 0;
    font-size: 0.72rem;
    color: rgba(228, 241, 255, 0.82);
    letter-spacing: 0.1em;
    text-transform: uppercase;
}

.bi-glosa-kpi strong {
    margin-top: 0;
    font-size: clamp(1.6rem, 2.5vw, 2.1rem);
    line-height: 1.05;
    color: #ffffff;
    letter-spacing: 0.01em;
}

.bi-glosa-kpi-note {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-weight: 700;
    font-size: 0.92rem;
    color: #c9d6e8;
}

.bi-glosa-kpi-note i {
    font-size: 0.9rem;
}

.bi-glosa-kpi-1 {
    background: linear-gradient(140deg, rgba(7, 45, 92, 0.96), rgba(18, 110, 185, 0.9));
    border-color: rgba(121, 214, 255, 0.48);
}

.bi-glosa-kpi-2 {
    background: linear-gradient(140deg, rgba(14, 81, 106, 0.96), rgba(28, 142, 141, 0.9));
    border-color: rgba(132, 255, 243, 0.45);
}

.bi-glosa-kpi-3 {
    background: linear-gradient(140deg, rgba(86, 21, 85, 0.96), rgba(181, 58, 138, 0.9));
    border-color: rgba(255, 171, 221, 0.45);
}

#chartGlosaWrap {
    height: 220px !important;
    min-height: 220px !important;
}

#chartGlosaWrap canvas {
    width: 100% !important;
    height: 100% !important;
}

@media (max-width: 900px) {
    .bi-glosa-page .bi-header {
        padding-left: 0;
    }

    .bi-glosa-kpis {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="bi-wrapper bi-theme bi-glosa-page">
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
        <div class="bi-glosa-kpis">
            <div class="bi-glosa-kpi bi-glosa-kpi-1">
                <div class="bi-glosa-kpi-head">
                    <span class="bi-glosa-kpi-icon"><i class="bi bi-percent"></i></span>
                    <small>Glosa média</small>
                </div>
                <strong><?= number_format($network['glosa_rate'] * 100, 1, ',', '.') ?>%</strong>
                <span class="bi-glosa-kpi-note"><i class="bi bi-dash"></i>Taxa consolidada</span>
            </div>
            <div class="bi-glosa-kpi bi-glosa-kpi-2">
                <div class="bi-glosa-kpi-head">
                    <span class="bi-glosa-kpi-icon"><i class="bi bi-cash-stack"></i></span>
                    <small>Valor apresentado</small>
                </div>
                <strong>R$ <?= number_format($totals['valor_apresentado'], 2, ',', '.') ?></strong>
                <span class="bi-glosa-kpi-note"><i class="bi bi-dash"></i>Total apresentado</span>
            </div>
            <div class="bi-glosa-kpi bi-glosa-kpi-3">
                <div class="bi-glosa-kpi-head">
                    <span class="bi-glosa-kpi-icon"><i class="bi bi-wallet2"></i></span>
                    <small>Valor final</small>
                </div>
                <strong>R$ <?= number_format($totals['valor_final'], 2, ',', '.') ?></strong>
                <span class="bi-glosa-kpi-note"><i class="bi bi-dash"></i>Total autorizado</span>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Glosa por hospital</h3>
        <div class="bi-chart compact" id="chartGlosaWrap">
            <canvas id="chartGlosa" height="220"></canvas>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Detalhe por hospital</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Hospital</th>
                    <th>Glosa</th>
                    <th>Valor apresentado</th>
                    <th>Valor final</th>
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
                            <td><?= number_format((float)$row['glosa_rate'] * 100, 1, ',', '.') ?>%</td>
                            <td><?= number_format((float)$row['valor_apresentado'], 2, ',', '.') ?></td>
                            <td><?= number_format((float)$row['valor_final'], 2, ',', '.') ?></td>
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
                        callback: yTickCallback || function (v) { return v + '%'; }
                    },
                    gridLines: { color: 'rgba(255,255,255,0.1)' }
                }]
            }
        }
    });
}

const chartLabels = <?= json_encode($chartLabels) ?>;
const chartVals = <?= json_encode($chartVals) ?>;
barChart(document.getElementById('chartGlosa'), chartLabels, chartVals, 'rgba(208, 113, 176, 0.7)', function (v) { return v + '%'; });
</script>

<?php require_once("templates/footer.php"); ?>
