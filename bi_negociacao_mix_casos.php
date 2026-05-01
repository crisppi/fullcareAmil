<?php
$pageTitle = 'Mix de Casos por Hospital';
$pageSlug = 'bi/negociacao-mix-casos';
require_once("templates/bi_rede_bootstrap.php");

$internFilters = biRedeBuildWhere($filterValues, 'i.data_intern_int', 'i', true);
$internWhere = $internFilters['where'];
$internParams = $internFilters['params'];
$internJoins = $internFilters['joins'];
$internWhere2 = preg_replace('/:(\w+)/', ':w2_$1', $internWhere);
$internParams2 = [];
foreach ($internParams as $key => $value) {
    $internParams2[':w2_' . ltrim($key, ':')] = $value;
}

$labelPat = "COALESCE(NULLIF(i.grupo_patologia_int,''), p.patologia_pat, 'Sem informacoes')";

$baseSql = "\n    SELECT\n        h.id_hospital AS hospital_id,\n        h.nome_hosp AS hospital,\n        {$labelPat} AS grupo,\n        COUNT(*) AS casos\n    FROM tb_internacao i\n    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int\n    LEFT JOIN tb_patologia p ON p.id_patologia = i.fk_patologia_int\n    {$internJoins}\n    WHERE {$internWhere}\n    GROUP BY h.id_hospital, grupo\n";
$baseSql2 = "\n    SELECT\n        h.id_hospital AS hospital_id,\n        h.nome_hosp AS hospital,\n        {$labelPat} AS grupo,\n        COUNT(*) AS casos\n    FROM tb_internacao i\n    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int\n    LEFT JOIN tb_patologia p ON p.id_patologia = i.fk_patologia_int\n    {$internJoins}\n    WHERE {$internWhere2}\n    GROUP BY h.id_hospital, grupo\n";

$rowsStmt = $conn->prepare("\n    SELECT t.hospital, t.grupo, t.casos\n    FROM ({$baseSql}) t\n    JOIN (\n        SELECT hospital_id, MAX(casos) AS max_casos\n        FROM ({$baseSql2}) x\n        GROUP BY hospital_id\n    ) m ON m.hospital_id = t.hospital_id AND m.max_casos = t.casos\n    ORDER BY t.casos DESC\n    LIMIT 10\n");
$rowsParams = array_merge($internParams, $internParams2);
biBindParams($rowsStmt, $rowsParams);
$rowsStmt->execute();
$rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Mix de Casos por Hospital</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Principal grupo de casos atendido por hospital.</div>
        </div>
        <div class="bi-header-actions">
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegacao BI">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <?php include "templates/bi_rede_filters.php"; ?>

    <div class="bi-panel">
        <h3>Hospitais e principal grupo de casos</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Hospital</th>
                    <th>Grupo principal</th>
                    <th>Casos</th>
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
                            <td><?= e($row['hospital'] ?? 'Sem informacoes') ?></td>
                            <td><?= e($row['grupo'] ?? 'Sem informacoes') ?></td>
                            <td><?= fmtInt((int)($row['casos'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
