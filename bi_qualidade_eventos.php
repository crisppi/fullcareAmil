<?php
$pageTitle = 'Eventos Adversos';
$pageSlug = 'bi/qualidade-eventos';
require_once("templates/bi_rede_bootstrap.php");

$internFilters = biRedeBuildWhere($filterValues, 'i.data_intern_int', 'i', true);
$internWhere = $internFilters['where'];
$internParams = $internFilters['params'];
$internJoins = $internFilters['joins'];

$summaryStmt = $conn->prepare("\n    SELECT\n        COUNT(*) AS casos,\n        SUM(CASE WHEN COALESCE(g.evento_adverso_ges,'n') = 's' THEN 1 ELSE 0 END) AS eventos\n    FROM tb_internacao i\n    LEFT JOIN tb_gestao g ON g.fk_internacao_ges = i.id_internacao\n    {$internJoins}\n    WHERE {$internWhere}\n");
biBindParams($summaryStmt, $internParams);
$summaryStmt->execute();
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$casos = (int)($summary['casos'] ?? 0);
$eventos = (int)($summary['eventos'] ?? 0);
$eventosPct = $casos > 0 ? ($eventos / $casos) * 100 : 0.0;

$rowsStmt = $conn->prepare("\n    SELECT\n        h.nome_hosp AS hospital,\n        COUNT(*) AS casos,\n        SUM(CASE WHEN COALESCE(g.evento_adverso_ges,'n') = 's' THEN 1 ELSE 0 END) AS eventos\n    FROM tb_internacao i\n    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int\n    LEFT JOIN tb_gestao g ON g.fk_internacao_ges = i.id_internacao\n    {$internJoins}\n    WHERE {$internWhere}\n    GROUP BY h.id_hospital\n    HAVING h.id_hospital IS NOT NULL\n    ORDER BY eventos DESC\n    LIMIT 10\n");
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
            <h1 class="bi-title">Eventos Adversos</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Ocorrencias registradas por hospital.</div>
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
                <small>Taxa de eventos</small>
                <strong><?= fmtPct($eventosPct, 1) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Eventos registrados</small>
                <strong><?= fmtInt($eventos) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Casos analisados</small>
                <strong><?= fmtInt($casos) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Hospitais no top 10</small>
                <strong><?= fmtInt(count($rows)) ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Hospitais com maior incidência</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Hospital</th>
                    <th>Eventos</th>
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
                        $evt = (int)($row['eventos'] ?? 0);
                        $rate = $total > 0 ? ($evt / $total) * 100 : 0.0;
                        ?>
                        <tr>
                            <td><?= e($row['hospital'] ?? 'Sem informações') ?></td>
                            <td><?= fmtInt($evt) ?></td>
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
