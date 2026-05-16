<?php
$pageTitle = 'Outliers de Permanência';
$pageSlug = 'bi/anomalias-permanencia';
require_once("templates/bi_rede_bootstrap.php");

$internFilters = biRedeBuildWhere($filterValues, 'i.data_intern_int', 'i', true);
$internWhere = $internFilters['where'];
$internParams = $internFilters['params'];
$internJoins = $internFilters['joins'];

$altaJoin = "LEFT JOIN (\n    SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt\n    FROM tb_alta\n    GROUP BY fk_id_int_alt\n) al ON al.fk_id_int_alt = i.id_internacao";
$stayExpr = "DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int)";

$summaryStmt = $conn->prepare("\n    SELECT\n        COUNT(*) AS casos,\n        AVG({$stayExpr}) AS media_dias,\n        MAX({$stayExpr}) AS max_dias\n    FROM tb_internacao i\n    {$altaJoin}\n    {$internJoins}\n    WHERE {$internWhere}\n");
biBindParams($summaryStmt, $internParams);
$summaryStmt->execute();
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$casos = (int)($summary['casos'] ?? 0);
$mediaDias = (float)($summary['media_dias'] ?? 0);
$maxDias = (int)($summary['max_dias'] ?? 0);
$limiar = $mediaDias > 0 ? ($mediaDias * 2) : 0;

$outlierStmt = $conn->prepare("\n    SELECT COUNT(*) AS total\n    FROM tb_internacao i\n    {$altaJoin}\n    {$internJoins}\n    WHERE {$internWhere} AND {$stayExpr} >= :limiar\n");
$outlierParams = $internParams;
$outlierParams[':limiar'] = $limiar;
biBindParams($outlierStmt, $outlierParams);
$outlierStmt->execute();
$outlierTotal = (int)($outlierStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

$rowsStmt = $conn->prepare("\n    SELECT\n        i.id_internacao,\n        p.nome_pac AS paciente,\n        h.nome_hosp AS hospital,\n        {$stayExpr} AS dias\n    FROM tb_internacao i\n    LEFT JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int\n    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int\n    {$altaJoin}\n    {$internJoins}\n    WHERE {$internWhere}\n    ORDER BY dias DESC\n    LIMIT 10\n");
biBindParams($rowsStmt, $internParams);
$rowsStmt->execute();
$rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260509-filter-icons"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Outliers de Permanência</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Casos com tempo de internação acima do padrão.</div>
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
                <small>Media de dias</small>
                <strong><?= fmtFloat($mediaDias, 1) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Maior permanencia</small>
                <strong><?= fmtInt($maxDias) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Outliers (>= 2x media)</small>
                <strong><?= fmtInt($outlierTotal) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Casos analisados</small>
                <strong><?= fmtInt($casos) ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Top 10 permanencias acima da media</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Hospital</th>
                    <th>Dias internado</th>
                    <th>Desvio da media</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="4" class="bi-empty">Sem dados com os filtros atuais.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <?php $dias = (int)($row['dias'] ?? 0); ?>
                        <tr>
                            <td><?= e($row['paciente'] ?? 'Sem informações') ?></td>
                            <td><?= e($row['hospital'] ?? 'Sem informações') ?></td>
                            <td><?= fmtInt($dias) ?></td>
                            <td><?= fmtInt((int)max(0, $dias - $mediaDias)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
