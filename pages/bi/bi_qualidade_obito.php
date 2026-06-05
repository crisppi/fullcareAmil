<?php
$pageTitle = 'Taxa de Obito';
$pageSlug = 'bi/qualidade-obito';
require_once("templates/bi_rede_bootstrap.php");

$altaFilters = biRedeBuildWhere($filterValues, 'al.data_alta_alt', 'i', true);
$altaWhere = $altaFilters['where'];
$altaParams = $altaFilters['params'];
$altaJoins = $altaFilters['joins'];

$summaryStmt = $conn->prepare("\n    SELECT\n        COUNT(DISTINCT al.id_alta) AS altas,\n        COUNT(DISTINCT CASE WHEN LOWER(COALESCE(al.tipo_alta_alt,'')) LIKE '%obito%' THEN al.id_alta END) AS obitos\n    FROM tb_alta al\n    JOIN tb_internacao i ON i.id_internacao = al.fk_id_int_alt\n    {$altaJoins}\n    WHERE {$altaWhere}\n");
biBindParams($summaryStmt, $altaParams);
$summaryStmt->execute();
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$altas = (int)($summary['altas'] ?? 0);
$obitos = (int)($summary['obitos'] ?? 0);
$obitoPct = $altas > 0 ? ($obitos / $altas) * 100 : 0.0;

$rowsStmt = $conn->prepare("\n    SELECT\n        h.nome_hosp AS hospital,\n        COUNT(DISTINCT al.id_alta) AS altas,\n        COUNT(DISTINCT CASE WHEN LOWER(COALESCE(al.tipo_alta_alt,'')) LIKE '%obito%' THEN al.id_alta END) AS obitos\n    FROM tb_alta al\n    JOIN tb_internacao i ON i.id_internacao = al.fk_id_int_alt\n    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int\n    {$altaJoins}\n    WHERE {$altaWhere}\n    GROUP BY h.id_hospital\n    HAVING h.id_hospital IS NOT NULL\n    ORDER BY obitos DESC\n    LIMIT 10\n");
biBindParams($rowsStmt, $altaParams);
$rowsStmt->execute();
$rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260516-rounded-bars"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Taxa de Obito</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Óbitos intra-hospitalares registrados na alta.</div>
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
                <small>Taxa de obito</small>
                <strong><?= fmtPct($obitoPct, 1) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Óbitos</small>
                <strong><?= fmtInt($obitos) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Altas analisadas</small>
                <strong><?= fmtInt($altas) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Hospitais no top 10</small>
                <strong><?= fmtInt(count($rows)) ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Hospitais com maior taxa de obito</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Hospital</th>
                    <th>Óbitos</th>
                    <th>Altas</th>
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
                        $total = (int)($row['altas'] ?? 0);
                        $obi = (int)($row['obitos'] ?? 0);
                        $rate = $total > 0 ? ($obi / $total) * 100 : 0.0;
                        ?>
                        <tr>
                            <td><?= e($row['hospital'] ?? 'Sem informações') ?></td>
                            <td><?= fmtInt($obi) ?></td>
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
