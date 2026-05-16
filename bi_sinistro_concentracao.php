<?php
$pageTitle = 'Concentracao de Risco';
$pageSlug = 'bi/sinistro-concentracao';
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

$summaryStmt = $conn->prepare("
    SELECT
        SUM({$costExpr}) AS custo_total,
        COUNT(DISTINCT h2.id_hospital) AS hospitais
    FROM tb_internacao i
    LEFT JOIN tb_hospital h2 ON h2.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao
    {$internJoins}
    WHERE {$internWhere}
");
$summaryStmt->execute($internParams);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$custoTotal = (float)($summary['custo_total'] ?? 0);
$hospitais = (int)($summary['hospitais'] ?? 0);

$rowsStmt = $conn->prepare("
    SELECT
        h2.nome_hosp AS hospital,
        COUNT(DISTINCT i.id_internacao) AS casos,
        SUM({$costExpr}) AS custo_total
    FROM tb_internacao i
    LEFT JOIN tb_hospital h2 ON h2.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao
    {$internJoins}
    WHERE {$internWhere}
    GROUP BY h2.id_hospital
    HAVING h2.id_hospital IS NOT NULL
    ORDER BY custo_total DESC
    LIMIT 12
");
$rowsStmt->execute($internParams);
$rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$top1Pct = 0.0;
$top3Pct = 0.0;
for ($i = 0; $i < min(3, count($rows)); $i++) {
    $rowTotal = (float)($rows[$i]['custo_total'] ?? 0);
    $top3Pct += $custoTotal > 0 ? ($rowTotal / $custoTotal) * 100 : 0.0;
    if ($i === 0) {
        $top1Pct = $custoTotal > 0 ? ($rowTotal / $custoTotal) * 100 : 0.0;
    }
}
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260509-filter-icons"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Concentracao de Risco</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Hospitais que concentram o gasto.</div>
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
                <strong><?= fmtMoney($custoTotal) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Top 1 hospital</small>
                <strong><?= fmtPct($top1Pct, 1) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Top 3 hospitais</small>
                <strong><?= fmtPct($top3Pct, 1) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Hospitais analisados</small>
                <strong><?= fmtInt($hospitais) ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Hospitais gastadores</h3>
        <div class="bi-split">
            <div class="bi-placeholder">Grafico de concentracao por hospital.</div>
            <div class="bi-list">
                <div class="bi-list-item">
                    <span>Maior concentracao</span>
                    <strong><?= fmtPct($top1Pct, 1) ?></strong>
                </div>
                <div class="bi-list-item">
                    <span>Top 3 concentracao</span>
                    <strong><?= fmtPct($top3Pct, 1) ?></strong>
                </div>
                <div class="bi-list-item">
                    <span>Hospitais no ranking</span>
                    <strong><?= fmtInt(count($rows)) ?></strong>
                </div>
            </div>
        </div>
        <table class="bi-table" style="margin-top: 16px;">
            <thead>
                <tr>
                    <th>Hospital</th>
                    <th>Custo total</th>
                    <th>Casos</th>
                    <th>% do total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="4" class="bi-empty">Sem dados com os filtros atuais.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $rowTotal = (float)($row['custo_total'] ?? 0);
                        $rowPct = $custoTotal > 0 ? ($rowTotal / $custoTotal) * 100 : 0.0;
                        ?>
                        <tr>
                            <td><?= e($row['hospital'] ?? 'Sem informações') ?></td>
                            <td><?= fmtMoney($rowTotal) ?></td>
                            <td><?= fmtInt((int)($row['casos'] ?? 0)) ?></td>
                            <td><?= fmtPct($rowPct, 1) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
