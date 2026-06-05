<?php
$pageTitle = 'Casos Caros Previsiveis';
$pageSlug = 'bi/risco-casos-previsiveis';
require_once("templates/bi_rede_bootstrap.php");

$internFilters = biRedeBuildWhere($filterValues, 'i.data_intern_int', 'i', true);
$internWhere = $internFilters['where'];
$internParams = $internFilters['params'];
$internJoins = $internFilters['joins'];

$costExpr = "COALESCE(NULLIF(ca.valor_final_capeante,0), ca.valor_apresentado_capeante, 0)";

$topHospStmt = $conn->prepare("\n    SELECT\n        i.fk_hospital_int AS hospital_id,\n        AVG({$costExpr}) AS custo_medio\n    FROM tb_internacao i\n    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao\n    {$internJoins}\n    WHERE {$internWhere} AND i.fk_hospital_int IS NOT NULL\n    GROUP BY i.fk_hospital_int\n    ORDER BY custo_medio DESC\n    LIMIT 5\n");
biBindParams($topHospStmt, $internParams);
$topHospStmt->execute();
$topHosp = $topHospStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$topHospIds = array_values(array_filter(array_map(fn($r) => (int)($r['hospital_id'] ?? 0), $topHosp)));

$topPatStmt = $conn->prepare("\n    SELECT\n        i.fk_patologia_int AS patologia_id,\n        AVG({$costExpr}) AS custo_medio\n    FROM tb_internacao i\n    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao\n    {$internJoins}\n    WHERE {$internWhere} AND i.fk_patologia_int IS NOT NULL\n    GROUP BY i.fk_patologia_int\n    ORDER BY custo_medio DESC\n    LIMIT 5\n");
biBindParams($topPatStmt, $internParams);
$topPatStmt->execute();
$topPat = $topPatStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$topPatIds = array_values(array_filter(array_map(fn($r) => (int)($r['patologia_id'] ?? 0), $topPat)));

$rows = [];
if ($topHospIds && $topPatIds) {
    $hospParams = [];
    foreach ($topHospIds as $idx => $id) {
        $hospParams[":hosp_{$idx}"] = $id;
    }
    $patParams = [];
    foreach ($topPatIds as $idx => $id) {
        $patParams[":pat_{$idx}"] = $id;
    }
    $hospPlace = implode(',', array_keys($hospParams));
    $patPlace = implode(',', array_keys($patParams));

    $rowsStmt = $conn->prepare("\n        SELECT\n            p.nome_pac AS paciente,\n            h.nome_hosp AS hospital,\n            pa.patologia_pat AS patologia,\n            {$costExpr} AS custo\n        FROM tb_internacao i\n        LEFT JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int\n        LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int\n        LEFT JOIN tb_patologia pa ON pa.id_patologia = i.fk_patologia_int\n        LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao\n        {$internJoins}\n        WHERE {$internWhere}\n          AND i.fk_hospital_int IN ({$hospPlace})\n          AND i.fk_patologia_int IN ({$patPlace})\n        ORDER BY custo DESC\n        LIMIT 10\n    ");

    $rowsParams = array_merge($internParams, $hospParams, $patParams);
    biBindParams($rowsStmt, $rowsParams);
    $rowsStmt->execute();
    $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260516-rounded-bars"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Casos Caros Previsiveis</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Combinacao de patologia e hospital com alto custo medio.</div>
        </div>
        <div class="bi-header-actions">
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação BI">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <?php include "templates/bi_rede_filters.php"; ?>

    <div class="bi-panel">
        <h3>Top 10 casos esperados de alto custo</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Hospital</th>
                    <th>Patologia</th>
                    <th>Custo</th>
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
                            <td><?= e($row['paciente'] ?? 'Sem informações') ?></td>
                            <td><?= e($row['hospital'] ?? 'Sem informações') ?></td>
                            <td><?= e($row['patologia'] ?? 'Sem informações') ?></td>
                            <td><?= fmtMoney((float)($row['custo'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
