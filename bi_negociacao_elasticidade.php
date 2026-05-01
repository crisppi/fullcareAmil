<?php
$pageTitle = 'Elasticidade de Preco';
$pageSlug = 'bi/negociacao-elasticidade';
require_once("templates/bi_rede_bootstrap.php");

$internFilters = biRedeBuildWhere($filterValues, 'i.data_intern_int', 'i', true);
$internWhere = $internFilters['where'];
$internParams = $internFilters['params'];
$internJoins = $internFilters['joins'];

$costExpr = "COALESCE(NULLIF(ca.valor_final_capeante,0), ca.valor_apresentado_capeante, 0)";

$rowsStmt = $conn->prepare("\n    SELECT\n        h.nome_hosp AS hospital,\n        COUNT(DISTINCT i.id_internacao) AS internacoes,\n        AVG({$costExpr}) AS custo_medio\n    FROM tb_internacao i\n    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int\n    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao\n    {$internJoins}\n    WHERE {$internWhere}\n    GROUP BY h.id_hospital\n    HAVING h.id_hospital IS NOT NULL\n    ORDER BY internacoes DESC\n    LIMIT 10\n");
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
            <h1 class="bi-title">Elasticidade de Preco</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Relacao entre custo medio e volume captado.</div>
        </div>
        <div class="bi-header-actions">
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegacao BI">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <?php include "templates/bi_rede_filters.php"; ?>

    <div class="bi-panel">
        <h3>Elasticidade por hospital</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Hospital</th>
                    <th>Internacoes</th>
                    <th>Custo medio</th>
                    <th>Indice elasticidade</th>
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
                        $vol = (int)($row['internacoes'] ?? 0);
                        $custo = (float)($row['custo_medio'] ?? 0);
                        $indice = $custo > 0 ? ($vol / $custo) * 1000 : 0.0;
                        ?>
                        <tr>
                            <td><?= e($row['hospital'] ?? 'Sem informacoes') ?></td>
                            <td><?= fmtInt($vol) ?></td>
                            <td><?= fmtMoney($custo) ?></td>
                            <td><?= fmtFloat($indice, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="bi-note" style="margin-top: 10px;">Indice = volume / custo medio (escala 1000).</div>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
