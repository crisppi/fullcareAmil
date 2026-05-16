<?php
$pageTitle = 'Readmissão Precoce';
$pageSlug = 'bi/anomalias-readmissao';
require_once("templates/bi_rede_bootstrap.php");

$readmFilters = biRedeBuildWhere($filterValues, 'al.data_alta_alt', 'i', true);
$readmWhere = $readmFilters['where'];
$readmParams = $readmFilters['params'];
$readmJoins = $readmFilters['joins'];

$summaryStmt = $conn->prepare("\n    SELECT\n        COUNT(*) AS total_altas,\n        SUM(\n            CASE WHEN EXISTS (\n                SELECT 1\n                FROM tb_internacao i2\n                WHERE i2.fk_paciente_int = i.fk_paciente_int\n                  AND i2.data_intern_int > al.data_alta_alt\n                  AND i2.data_intern_int <= DATE_ADD(al.data_alta_alt, INTERVAL 7 DAY)\n            ) THEN 1 ELSE 0 END\n        ) AS readm7,\n        SUM(\n            CASE WHEN EXISTS (\n                SELECT 1\n                FROM tb_internacao i3\n                WHERE i3.fk_paciente_int = i.fk_paciente_int\n                  AND i3.data_intern_int > al.data_alta_alt\n                  AND i3.data_intern_int <= DATE_ADD(al.data_alta_alt, INTERVAL 3 DAY)\n            ) THEN 1 ELSE 0 END\n        ) AS readm3\n    FROM tb_alta al\n    JOIN tb_internacao i ON i.id_internacao = al.fk_id_int_alt\n    {$readmJoins}\n    WHERE {$readmWhere}\n");
biBindParams($summaryStmt, $readmParams);
$summaryStmt->execute();
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$totalAltas = (int)($summary['total_altas'] ?? 0);
$readm7 = (int)($summary['readm7'] ?? 0);
$readm3 = (int)($summary['readm3'] ?? 0);
$readm7Pct = $totalAltas > 0 ? ($readm7 / $totalAltas) * 100 : 0.0;
$readm3Pct = $totalAltas > 0 ? ($readm3 / $totalAltas) * 100 : 0.0;

$rowsStmt = $conn->prepare("\n    SELECT\n        h.nome_hosp AS hospital,\n        COUNT(*) AS total_altas,\n        SUM(\n            CASE WHEN EXISTS (\n                SELECT 1\n                FROM tb_internacao i2\n                WHERE i2.fk_paciente_int = i.fk_paciente_int\n                  AND i2.data_intern_int > al.data_alta_alt\n                  AND i2.data_intern_int <= DATE_ADD(al.data_alta_alt, INTERVAL 7 DAY)\n            ) THEN 1 ELSE 0 END\n        ) AS readm7\n    FROM tb_alta al\n    JOIN tb_internacao i ON i.id_internacao = al.fk_id_int_alt\n    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int\n    {$readmJoins}\n    WHERE {$readmWhere}\n    GROUP BY h.id_hospital\n    HAVING h.id_hospital IS NOT NULL\n    ORDER BY readm7 DESC\n    LIMIT 10\n");
biBindParams($rowsStmt, $readmParams);
$rowsStmt->execute();
$rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260509-filter-icons"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Readmissão Precoce</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Retorno rapido do paciente apos alta.</div>
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
                <small>Readmissão 7d</small>
                <strong><?= fmtPct($readm7Pct, 1) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Readmissão 3d</small>
                <strong><?= fmtPct($readm3Pct, 1) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Altas analisadas</small>
                <strong><?= fmtInt($totalAltas) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Casos 7d</small>
                <strong><?= fmtInt($readm7) ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Hospitais com maior readmissao 7d</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Hospital</th>
                    <th>Readmissão 7d</th>
                    <th>Taxa 7d</th>
                    <th>Altas</th>
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
                        $total = (int)($row['total_altas'] ?? 0);
                        $r7 = (int)($row['readm7'] ?? 0);
                        $rate = $total > 0 ? ($r7 / $total) * 100 : 0.0;
                        ?>
                        <tr>
                            <td><?= e($row['hospital'] ?? 'Sem informações') ?></td>
                            <td><?= fmtInt($r7) ?></td>
                            <td><?= fmtPct($rate, 1) ?></td>
                            <td><?= fmtInt($total) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
