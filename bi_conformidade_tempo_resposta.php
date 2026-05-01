<?php
$pageTitle = 'Tempo de Resposta';
$pageSlug = 'bi/conformidade-tempo-resposta';
require_once("templates/bi_rede_bootstrap.php");

$internFilters = biRedeBuildWhere($filterValues, 'i.data_intern_int', 'i', true);
$internWhere = $internFilters['where'];
$internParams = $internFilters['params'];
$internJoins = $internFilters['joins'];

$altaJoin = "LEFT JOIN (\n    SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt\n    FROM tb_alta\n    GROUP BY fk_id_int_alt\n) al ON al.fk_id_int_alt = i.id_internacao";

$visitaExpr = "DATEDIFF(COALESCE(i.data_visita_int, i.data_intern_int), i.data_intern_int)";
$altaExpr = "DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int)";

$summaryStmt = $conn->prepare("\n    SELECT\n        AVG({$visitaExpr}) AS tempo_visita,\n        AVG({$altaExpr}) AS tempo_alta,\n        COUNT(*) AS casos\n    FROM tb_internacao i\n    {$altaJoin}\n    {$internJoins}\n    WHERE {$internWhere}\n");
biBindParams($summaryStmt, $internParams);
$summaryStmt->execute();
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$tempoVisita = (float)($summary['tempo_visita'] ?? 0);
$tempoAlta = (float)($summary['tempo_alta'] ?? 0);
$casos = (int)($summary['casos'] ?? 0);

$rowsStmt = $conn->prepare("\n    SELECT\n        h.nome_hosp AS hospital,\n        AVG({$visitaExpr}) AS tempo_visita,\n        AVG({$altaExpr}) AS tempo_alta,\n        COUNT(*) AS casos\n    FROM tb_internacao i\n    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int\n    {$altaJoin}\n    {$internJoins}\n    WHERE {$internWhere}\n    GROUP BY h.id_hospital\n    HAVING h.id_hospital IS NOT NULL\n    ORDER BY tempo_visita DESC\n    LIMIT 10\n");
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
            <h1 class="bi-title">Tempo de Resposta</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Tempo entre internacao, visita e alta.</div>
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
                <small>Tempo medio para visita</small>
                <strong><?= fmtFloat($tempoVisita, 1) ?> dias</strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Tempo medio para alta</small>
                <strong><?= fmtFloat($tempoAlta, 1) ?> dias</strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Internacoes analisadas</small>
                <strong><?= fmtInt($casos) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Hospitais no recorte</small>
                <strong><?= fmtInt(count($rows)) ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Hospitais com maior tempo de resposta</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Hospital</th>
                    <th>Tempo visita (dias)</th>
                    <th>Tempo alta (dias)</th>
                    <th>Casos</th>
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
                            <td><?= fmtFloat((float)($row['tempo_visita'] ?? 0), 1) ?></td>
                            <td><?= fmtFloat((float)($row['tempo_alta'] ?? 0), 1) ?></td>
                            <td><?= fmtInt((int)($row['casos'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
