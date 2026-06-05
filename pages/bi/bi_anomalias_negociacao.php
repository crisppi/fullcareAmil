<?php
$pageTitle = 'Padrao de Negociacao';
$pageSlug = 'bi/anomalias-negociacao';
require_once("bi_rede_bootstrap.php");

if (!function_exists('fmtMoney')) {
    function fmtMoney($value): string
    {
        return 'R$ ' . number_format((float)$value, 2, ',', '.');
    }
}

if (!function_exists('fmtInt')) {
    function fmtInt($value): string
    {
        return number_format((int)$value, 0, ',', '.');
    }
}

if (!function_exists('fmtFloat')) {
    function fmtFloat($value, int $dec = 1): string
    {
        return number_format((float)$value, $dec, ',', '.');
    }
}

$internFilters = biRedeBuildWhere($filterValues, 'i.data_intern_int', 'i', true);
$internWhere = $internFilters['where'];
$internParams = $internFilters['params'];
$internJoins = $internFilters['joins'];
$savingExpr = "COALESCE(
    NULLIF(ng.saving, 0),
    CASE
        WHEN ng.id_negociacao IS NULL THEN NULL
        WHEN UPPER(COALESCE(ng.tipo_negociacao, '')) LIKE 'TROCA%' THEN
            GREATEST(COALESCE(aco_de.valor_aco, 0) - COALESCE(aco_para.valor_aco, 0), 0) * COALESCE(NULLIF(ng.qtd, 0), 1)
        WHEN UPPER(COALESCE(ng.tipo_negociacao, '')) = 'ALTA TARDIA APTO' THEN
            COALESCE(NULLIF(aco_para.valor_aco, 0), COALESCE(aco_de.valor_aco, 0)) * COALESCE(NULLIF(ng.qtd, 0), 1)
        WHEN UPPER(COALESCE(ng.tipo_negociacao, '')) LIKE '%1/2 DIARIA%' THEN
            (COALESCE(aco_de.valor_aco, 0) / 2) * COALESCE(NULLIF(ng.qtd, 0), 1)
        ELSE
            COALESCE(aco_de.valor_aco, 0) * COALESCE(NULLIF(ng.qtd, 0), 1)
    END,
    0
)";

$summaryBaseStmt = $conn->prepare("\n    SELECT\n        COUNT(DISTINCT i.id_internacao) AS internacoes,\n        COUNT(DISTINCT pr.id_prorrogacao) AS prorrogacoes\n    FROM tb_internacao i\n    LEFT JOIN tb_prorrogacao pr ON pr.fk_internacao_pror = i.id_internacao\n    {$internJoins}\n    WHERE {$internWhere}\n");
biBindParams($summaryBaseStmt, $internParams);
$summaryBaseStmt->execute();
$summaryBase = $summaryBaseStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$summaryNegStmt = $conn->prepare("\n    SELECT\n        COUNT(*) AS negociacoes,\n        SUM(saving_calc) AS saving_total\n    FROM (\n        SELECT\n            ng.id_negociacao,\n            MAX({$savingExpr}) AS saving_calc\n        FROM tb_internacao i\n        LEFT JOIN tb_negociacao ng ON ng.fk_id_int = i.id_internacao\n        LEFT JOIN tb_acomodacao aco_de ON aco_de.fk_hospital = i.fk_hospital_int\n            AND LOWER(TRIM(aco_de.acomodacao_aco)) = LOWER(TRIM(IF(LOCATE('-', ng.troca_de) > 0, SUBSTRING_INDEX(ng.troca_de, '-', -1), ng.troca_de)))\n        LEFT JOIN tb_acomodacao aco_para ON aco_para.fk_hospital = i.fk_hospital_int\n            AND LOWER(TRIM(aco_para.acomodacao_aco)) = LOWER(TRIM(IF(LOCATE('-', ng.troca_para) > 0, SUBSTRING_INDEX(ng.troca_para, '-', -1), ng.troca_para)))\n        {$internJoins}\n        WHERE {$internWhere}\n          AND ng.id_negociacao IS NOT NULL\n        GROUP BY ng.id_negociacao\n    ) neg\n");
biBindParams($summaryNegStmt, $internParams);
$summaryNegStmt->execute();
$summaryNeg = $summaryNegStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$internacoes = (int)($summaryBase['internacoes'] ?? 0);
$negociacoes = (int)($summaryNeg['negociacoes'] ?? 0);
$savingTotal = (float)($summaryNeg['saving_total'] ?? 0);
$prorrogacoes = (int)($summaryBase['prorrogacoes'] ?? 0);

$rowsStmt = $conn->prepare("\n    SELECT\n        hospital_id,\n        hospital,\n        COUNT(DISTINCT id_internacao) AS internacoes,\n        COUNT(id_negociacao) AS negociacoes,\n        SUM(saving_calc) AS saving_total\n    FROM (\n        SELECT\n            i.id_internacao,\n            h.id_hospital AS hospital_id,\n            h.nome_hosp AS hospital,\n            ng.id_negociacao,\n            MAX({$savingExpr}) AS saving_calc\n        FROM tb_internacao i\n        LEFT JOIN tb_negociacao ng ON ng.fk_id_int = i.id_internacao\n        LEFT JOIN tb_acomodacao aco_de ON aco_de.fk_hospital = i.fk_hospital_int\n            AND LOWER(TRIM(aco_de.acomodacao_aco)) = LOWER(TRIM(IF(LOCATE('-', ng.troca_de) > 0, SUBSTRING_INDEX(ng.troca_de, '-', -1), ng.troca_de)))\n        LEFT JOIN tb_acomodacao aco_para ON aco_para.fk_hospital = i.fk_hospital_int\n            AND LOWER(TRIM(aco_para.acomodacao_aco)) = LOWER(TRIM(IF(LOCATE('-', ng.troca_para) > 0, SUBSTRING_INDEX(ng.troca_para, '-', -1), ng.troca_para)))\n        {$internJoins}\n        WHERE {$internWhere}\n        GROUP BY i.id_internacao, h.id_hospital, h.nome_hosp, ng.id_negociacao\n    ) neg\n    WHERE hospital_id IS NOT NULL\n    GROUP BY hospital_id, hospital\n    ORDER BY negociacoes DESC, saving_total DESC\n    LIMIT 10\n");
biBindParams($rowsStmt, $internParams);
$rowsStmt->execute();
$rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$prorRowsStmt = $conn->prepare("\n    SELECT\n        h.id_hospital AS hospital_id,\n        COUNT(DISTINCT pr.id_prorrogacao) AS prorrogacoes\n    FROM tb_internacao i\n    LEFT JOIN tb_prorrogacao pr ON pr.fk_internacao_pror = i.id_internacao\n    {$internJoins}\n    WHERE {$internWhere}\n    GROUP BY h.id_hospital\n");
biBindParams($prorRowsStmt, $internParams);
$prorRowsStmt->execute();
$prorMap = [];
foreach (($prorRowsStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $prorRow) {
    $prorMap[(int)($prorRow['hospital_id'] ?? 0)] = (int)($prorRow['prorrogacoes'] ?? 0);
}

foreach ($rows as &$row) {
    $row['prorrogacoes'] = $prorMap[(int)($row['hospital_id'] ?? 0)] ?? 0;
}
unset($row);
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260516-rounded-bars"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Padrao de Negociacao</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Hospitais com excesso de negociacoes ou prorrogações.</div>
        </div>
        <div class="bi-header-actions">
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação BI">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <?php include "bi_rede_filters.php"; ?>

    <div class="bi-panel">
        <h3>Indicadores-chave</h3>
        <div class="bi-kpis kpi-grid-4">
            <div class="bi-kpi kpi-compact">
                <small>Negociacoes</small>
                <strong><?= fmtInt($negociacoes) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Prorrogacoes</small>
                <strong><?= fmtInt($prorrogacoes) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Saving total</small>
                <strong><?= fmtMoney($savingTotal) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Internações analisadas</small>
                <strong><?= fmtInt($internacoes) ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Top 10 hospitais com maior intensidade de negociacao</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Hospital</th>
                    <th>Negociacoes</th>
                    <th>Prorrogacoes</th>
                    <th>Saving total</th>
                    <th>Índice por internacao</th>
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
                        $ints = (int)($row['internacoes'] ?? 0);
                        $neg = (int)($row['negociacoes'] ?? 0);
                        $pr = (int)($row['prorrogacoes'] ?? 0);
                        $indice = $ints > 0 ? (($neg + $pr) / $ints) : 0.0;
                        ?>
                        <tr>
                            <td><?= e($row['hospital'] ?? 'Sem informações') ?></td>
                            <td><?= fmtInt($neg) ?></td>
                            <td><?= fmtInt($pr) ?></td>
                            <td><?= fmtMoney((float)($row['saving_total'] ?? 0)) ?></td>
                            <td><?= fmtFloat($indice, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
