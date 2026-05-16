<?php
$pageTitle = 'Tendencia de Custo';
$pageSlug = 'bi/sinistro-tendencia';
require_once("templates/bi_rede_bootstrap.php");

function bindParams(PDOStatement $stmt, array $params): void
{
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
}

$internFilters = biRedeBuildWhere($filterValues, 'i.data_intern_int', 'i', true);
$internWhere = $internFilters['where'];
$internParams = $internFilters['params'];
$internJoins = $internFilters['joins'];

$costExpr = "COALESCE(NULLIF(ca.valor_final_capeante,0), ca.valor_apresentado_capeante, 0)";

$seriesStmt = $conn->prepare("
    SELECT
        DATE_FORMAT(i.data_intern_int, '%Y-%m-01') AS mes_ref,
        DATE_FORMAT(i.data_intern_int, '%b/%Y') AS etiqueta,
        SUM({$costExpr}) AS total
    FROM tb_internacao i
    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao
    {$internJoins}
    WHERE {$internWhere}
    GROUP BY mes_ref, etiqueta
    ORDER BY mes_ref ASC
");
$seriesStmt->execute($internParams);
$rows = $seriesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$total = 0.0;
foreach ($rows as $row) {
    $total += (float)($row['total'] ?? 0);
}
$firstVal = $rows ? (float)($rows[0]['total'] ?? 0) : 0.0;
$lastVal = $rows ? (float)($rows[count($rows) - 1]['total'] ?? 0) : 0.0;
$varPct = $firstVal > 0 ? (($lastVal - $firstVal) / $firstVal) * 100 : 0.0;
$meses = count($rows);

$labels = array_map(fn($r) => $r['etiqueta'] ?? '-', $rows);
$values = array_map(fn($r) => (float)($r['total'] ?? 0), $rows);
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260509-filter-icons"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Tendencia de Custo</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Evolução mensal do custo assistencial.</div>
        </div>
        <div class="bi-header-actions">
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação BI">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <?php include "templates/bi_rede_filters.php"; ?>

    <div class="bi-panel">
        <h3>Indicadores-chave</h3>
        <div class="bi-kpis kpi-grid-4">
            <div class="bi-kpi kpi-compact">
                <small>Custo total</small>
                <strong><?= fmtMoney($total) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Variacao no período</small>
                <strong><?= fmtPct($varPct, 1) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Ultimo mes</small>
                <strong><?= fmtMoney($lastVal) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Meses analisados</small>
                <strong><?= fmtInt($meses) ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Serie mensal</h3>
        <div class="bi-split">
            <div class="bi-placeholder">Grafico de tendencia mensal.</div>
            <div class="bi-list">
                <div class="bi-list-item">
                    <span>Primeiro mes</span>
                    <strong><?= $rows ? e($rows[0]['etiqueta'] ?? '-') : '-' ?></strong>
                </div>
                <div class="bi-list-item">
                    <span>Ultimo mes</span>
                    <strong><?= $rows ? e($rows[count($rows) - 1]['etiqueta'] ?? '-') : '-' ?></strong>
                </div>
                <div class="bi-list-item">
                    <span>Variacao</span>
                    <strong><?= fmtPct($varPct, 1) ?></strong>
                </div>
            </div>
        </div>
        <table class="bi-table" style="margin-top: 16px;">
            <thead>
                <tr>
                    <th>Mês</th>
                    <th>Custo total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="2" class="bi-empty">Sem dados com os filtros atuais.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['etiqueta'] ?? '-') ?></td>
                            <td><?= fmtMoney((float)($row['total'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const trendLabels = <?= json_encode($labels) ?>;
const trendValues = <?= json_encode($values) ?>;
</script>

<?php require_once("templates/footer.php"); ?>
