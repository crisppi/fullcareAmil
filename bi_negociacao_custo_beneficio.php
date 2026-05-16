<?php
$pageTitle = 'Custo-Beneficio da Rede';
$pageSlug = 'bi/negociacao-custo-beneficio';
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

$rowsStmt = $conn->prepare("\n    SELECT\n        h.nome_hosp AS hospital,\n        COUNT(DISTINCT i.id_internacao) AS internacoes,\n        SUM({$costExpr}) AS custo_total\n    FROM tb_internacao i\n    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int\n    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao\n    {$internJoins}\n    WHERE {$internWhere}\n    GROUP BY h.id_hospital\n    HAVING h.id_hospital IS NOT NULL\n    ORDER BY custo_total DESC\n    LIMIT 10\n");
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
            <h1 class="bi-title">Custo-Beneficio da Rede</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Concentracao de custo e volume na rede.</div>
        </div>
        <div class="bi-header-actions">
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação BI">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <?php include "templates/bi_rede_filters.php"; ?>

    <div class="bi-panel">
        <h3>Top 10 hospitais por custo</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Hospital</th>
                    <th>Internações</th>
                    <th>Custo total</th>
                    <th>% volume</th>
                    <th>% custo</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="5" class="bi-empty">Sem dados com os filtros atuais.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $vol = (int)($row['internacoes'] ?? 0);
                        $custo = (float)($row['custo_total'] ?? 0);
                        $pctVol = $internacoes > 0 ? ($vol / $internacoes) * 100 : 0.0;
                        $pctCusto = $custoTotal > 0 ? ($custo / $custoTotal) * 100 : 0.0;
                        ?>
                        <tr>
                            <td><?= e($row['hospital'] ?? 'Sem informações') ?></td>
                            <td><?= fmtInt($vol) ?></td>
                            <td><?= fmtMoney($custo) ?></td>
                            <td><?= fmtPct($pctVol, 1) ?></td>
                            <td><?= fmtPct($pctCusto, 1) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
