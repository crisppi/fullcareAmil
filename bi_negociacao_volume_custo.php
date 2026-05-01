<?php
$pageTitle = 'Volume vs Custo por Hospital';
$pageSlug = 'bi/negociacao-volume-custo';
require_once("templates/bi_rede_bootstrap.php");

$internFilters = biRedeBuildWhere($filterValues, 'i.data_intern_int', 'i', true);
$internWhere = $internFilters['where'];
$internParams = $internFilters['params'];
$internJoins = $internFilters['joins'];

$costExpr = "COALESCE(NULLIF(ca.valor_final_capeante,0), ca.valor_apresentado_capeante, 0)";

$summaryStmt = $conn->prepare("\n    SELECT\n        COUNT(DISTINCT i.id_internacao) AS internacoes,\n        SUM({$costExpr}) AS custo_total\n    FROM tb_internacao i\n    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao\n    {$internJoins}\n    WHERE {$internWhere}\n");
biBindParams($summaryStmt, $internParams);
$summaryStmt->execute();
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$internacoes = (int)($summary['internacoes'] ?? 0);
$custoTotal = (float)($summary['custo_total'] ?? 0);

$rowsStmt = $conn->prepare("\n    SELECT\n        h.nome_hosp AS hospital,\n        COUNT(DISTINCT i.id_internacao) AS internacoes,\n        SUM({$costExpr}) AS custo_total,\n        AVG({$costExpr}) AS custo_medio\n    FROM tb_internacao i\n    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int\n    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao\n    {$internJoins}\n    WHERE {$internWhere}\n    GROUP BY h.id_hospital\n    HAVING h.id_hospital IS NOT NULL\n    ORDER BY custo_total DESC\n    LIMIT 10\n");
biBindParams($rowsStmt, $internParams);
$rowsStmt->execute();
$rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Volume vs Custo por Hospital</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Peso de volume e impacto financeiro por hospital.</div>
        </div>
        <div class="bi-header-actions">
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegacao BI">
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
                <small>Internacoes</small>
                <strong><?= fmtInt($internacoes) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Custo medio</small>
                <strong><?= fmtMoney($internacoes > 0 ? ($custoTotal / $internacoes) : 0) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Hospitais no top 10</small>
                <strong><?= fmtInt(count($rows)) ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Top 10 hospitais por custo total</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Hospital</th>
                    <th>Internacoes</th>
                    <th>Custo total</th>
                    <th>Custo medio</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="4" class="bi-empty">Sem dados com os filtros atuais.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['hospital'] ?? 'Sem informacoes') ?></td>
                            <td><?= fmtInt((int)($row['internacoes'] ?? 0)) ?></td>
                            <td><?= fmtMoney((float)($row['custo_total'] ?? 0)) ?></td>
                            <td><?= fmtMoney((float)($row['custo_medio'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
