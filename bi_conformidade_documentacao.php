<?php
$pageTitle = 'Documentacao Completa';
$pageSlug = 'bi/conformidade-documentacao';
require_once("templates/bi_rede_bootstrap.php");

$internFilters = biRedeBuildWhere($filterValues, 'i.data_intern_int', 'i', true);
$internWhere = $internFilters['where'];
$internParams = $internFilters['params'];
$internJoins = $internFilters['joins'];

$docExpr = "COALESCE(NULLIF(TRIM(i.rel_int),''), NULLIF(TRIM(i.rel_auditoria_int),''), NULLIF(TRIM(i.acoes_int),''))";

$summaryStmt = $conn->prepare("\n    SELECT\n        COUNT(*) AS casos,\n        SUM(CASE WHEN {$docExpr} IS NOT NULL THEN 1 ELSE 0 END) AS completos\n    FROM tb_internacao i\n    {$internJoins}\n    WHERE {$internWhere}\n");
biBindParams($summaryStmt, $internParams);
$summaryStmt->execute();
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$casos = (int)($summary['casos'] ?? 0);
$completos = (int)($summary['completos'] ?? 0);
$completoPct = $casos > 0 ? ($completos / $casos) * 100 : 0.0;

$rowsStmt = $conn->prepare("\n    SELECT\n        h.nome_hosp AS hospital,\n        COUNT(*) AS casos,\n        SUM(CASE WHEN {$docExpr} IS NOT NULL THEN 1 ELSE 0 END) AS completos\n    FROM tb_internacao i\n    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int\n    {$internJoins}\n    WHERE {$internWhere}\n    GROUP BY h.id_hospital\n    HAVING h.id_hospital IS NOT NULL\n    ORDER BY completos DESC\n    LIMIT 10\n");
biBindParams($rowsStmt, $internParams);
$rowsStmt->execute();
$rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260516-rounded-bars"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Documentacao Completa</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Registro minimo de relatorio/acoes por internacao.</div>
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
                <small>Documentacao completa</small>
                <strong><?= fmtPct($completoPct, 1) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Internações completas</small>
                <strong><?= fmtInt($completos) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Internações analisadas</small>
                <strong><?= fmtInt($casos) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Taxa de critica</small>
                <strong><?= fmtPct(100 - $completoPct, 1) ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Documentacao por hospital</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Hospital</th>
                    <th>Completos</th>
                    <th>Casos</th>
                    <th>Taxa</th>
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
                        $total = (int)($row['casos'] ?? 0);
                        $comp = (int)($row['completos'] ?? 0);
                        $rate = $total > 0 ? ($comp / $total) * 100 : 0.0;
                        ?>
                        <tr>
                            <td><?= e($row['hospital'] ?? 'Sem informações') ?></td>
                            <td><?= fmtInt($comp) ?></td>
                            <td><?= fmtInt($total) ?></td>
                            <td><?= fmtPct($rate, 1) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
