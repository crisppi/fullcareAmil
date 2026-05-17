<?php
$pageTitle = 'Risco de Longa Permanência';
$pageSlug = 'bi/risco-longa-permanencia';
require_once("templates/bi_rede_bootstrap.php");

$limiar = (int)(filter_input(INPUT_GET, 'limiar', FILTER_VALIDATE_INT) ?: 15);
$limiar = max(5, $limiar);

$internFilters = biRedeBuildWhere($filterValues, 'i.data_intern_int', 'i', true);
$internWhere = $internFilters['where'];
$internParams = $internFilters['params'];
$internJoins = $internFilters['joins'];

$altaJoin = "LEFT JOIN (\n    SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt\n    FROM tb_alta\n    GROUP BY fk_id_int_alt\n) al ON al.fk_id_int_alt = i.id_internacao";
$stayExpr = "DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int)";

$summaryStmt = $conn->prepare("\n    SELECT\n        COUNT(*) AS casos,\n        SUM(CASE WHEN {$stayExpr} >= :limiar THEN 1 ELSE 0 END) AS longos\n    FROM tb_internacao i\n    {$altaJoin}\n    {$internJoins}\n    WHERE {$internWhere}\n");
$summaryParams = $internParams;
$summaryParams[':limiar'] = $limiar;
biBindParams($summaryStmt, $summaryParams);
$summaryStmt->execute();
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$casos = (int)($summary['casos'] ?? 0);
$longos = (int)($summary['longos'] ?? 0);
$longosPct = $casos > 0 ? ($longos / $casos) * 100 : 0.0;

$rowsStmt = $conn->prepare("\n    SELECT\n        i.id_internacao,\n        p.nome_pac AS paciente,\n        h.nome_hosp AS hospital,\n        {$stayExpr} AS dias\n    FROM tb_internacao i\n    LEFT JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int\n    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int\n    {$altaJoin}\n    {$internJoins}\n    WHERE {$internWhere} AND {$stayExpr} >= :limiar\n    ORDER BY dias DESC\n    LIMIT 10\n");
biBindParams($rowsStmt, $summaryParams);
$rowsStmt->execute();
$rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260516-rounded-bars"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Risco de Longa Permanência</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Internações acima do limiar de dias.</div>
        </div>
        <div class="bi-header-actions">
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação BI">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <form method="get">
        <?php include "templates/bi_rede_filters.php"; ?>
        <div class="bi-panel bi-filters" style="margin-top: -6px;">
            <div class="bi-filter">
                <label for="limiar">Limiar de dias</label>
                <input type="number" id="limiar" name="limiar" value="<?= e($limiar) ?>" min="5" step="1">
            </div>
            <div class="bi-actions">
                <button class="bi-btn" type="submit">Atualizar</button>
            </div>
        </div>
    </form>

    <div class="bi-panel">
        <h3>Indicadores-chave</h3>
        <div class="bi-kpis kpi-grid-4">
            <div class="bi-kpi kpi-compact">
                <small>Casos acima do limiar</small>
                <strong><?= fmtInt($longos) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Taxa acima do limiar</small>
                <strong><?= fmtPct($longosPct, 1) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Limiar atual</small>
                <strong><?= fmtInt($limiar) ?> dias</strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Casos analisados</small>
                <strong><?= fmtInt($casos) ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Top 10 permanencias acima do limiar</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Hospital</th>
                    <th>Dias internado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="3" class="bi-empty">Sem dados com os filtros atuais.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['paciente'] ?? 'Sem informações') ?></td>
                            <td><?= e($row['hospital'] ?? 'Sem informações') ?></td>
                            <td><?= fmtInt((int)($row['dias'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
