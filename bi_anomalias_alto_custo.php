<?php
$pageTitle = 'Alto Custo sem Justificativa Clinica';
$pageSlug = 'bi/anomalias-alto-custo';
require_once("templates/bi_rede_bootstrap.php");

$internFilters = biRedeBuildWhere($filterValues, 'i.data_intern_int', 'i', true);
$internWhere = $internFilters['where'];
$internParams = $internFilters['params'];
$internJoins = $internFilters['joins'];

$costExpr = "COALESCE(NULLIF(ca.valor_final_capeante,0), ca.valor_apresentado_capeante, 0)";
$justExpr = "(COALESCE(g.alto_custo_ges,'n') = 's' OR COALESCE(g.opme_ges,'n') = 's' OR COALESCE(g.evento_adverso_ges,'n') = 's')";

$summaryStmt = $conn->prepare("\n    SELECT\n        COUNT(DISTINCT i.id_internacao) AS casos,\n        AVG({$costExpr}) AS custo_medio,\n        MAX({$costExpr}) AS custo_max\n    FROM tb_internacao i\n    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao\n    LEFT JOIN tb_gestao g ON g.fk_internacao_ges = i.id_internacao\n    {$internJoins}\n    WHERE {$internWhere}\n");
biBindParams($summaryStmt, $internParams);
$summaryStmt->execute();
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$casos = (int)($summary['casos'] ?? 0);
$custoMedio = (float)($summary['custo_medio'] ?? 0);
$custoMax = (float)($summary['custo_max'] ?? 0);
$limiar = $custoMedio > 0 ? ($custoMedio * 2) : 0;

$outlierStmt = $conn->prepare("\n    SELECT\n        SUM(CASE WHEN {$justExpr} THEN 0 ELSE 1 END) AS sem_justificativa\n    FROM tb_internacao i\n    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao\n    LEFT JOIN tb_gestao g ON g.fk_internacao_ges = i.id_internacao\n    {$internJoins}\n    WHERE {$internWhere} AND {$costExpr} >= :limiar\n");
$outlierParams = $internParams;
$outlierParams[':limiar'] = $limiar;
biBindParams($outlierStmt, $outlierParams);
$outlierStmt->execute();
$semJustificativa = (int)($outlierStmt->fetch(PDO::FETCH_ASSOC)['sem_justificativa'] ?? 0);

$rowsStmt = $conn->prepare("\n    SELECT\n        i.id_internacao,\n        p.nome_pac AS paciente,\n        h.nome_hosp AS hospital,\n        {$costExpr} AS custo,\n        COALESCE(g.opme_ges,'n') AS opme,\n        COALESCE(g.alto_custo_ges,'n') AS alto_custo,\n        COALESCE(g.evento_adverso_ges,'n') AS evento\n    FROM tb_internacao i\n    LEFT JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int\n    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int\n    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao\n    LEFT JOIN tb_gestao g ON g.fk_internacao_ges = i.id_internacao\n    {$internJoins}\n    WHERE {$internWhere}\n    ORDER BY custo DESC\n    LIMIT 10\n");
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
            <h1 class="bi-title">Alto Custo sem Justificativa Clinica</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Casos com custo elevado e sem sinalizacao clinica clara.</div>
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
                <small>Custo medio</small>
                <strong><?= fmtMoney($custoMedio) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Maior custo</small>
                <strong><?= fmtMoney($custoMax) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Sem justificativa (>= 2x media)</small>
                <strong><?= fmtInt($semJustificativa) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Casos analisados</small>
                <strong><?= fmtInt($casos) ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Top 10 casos por custo</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Hospital</th>
                    <th>Custo</th>
                    <th>OPME</th>
                    <th>Alto custo</th>
                    <th>Evento adverso</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="6" class="bi-empty">Sem dados com os filtros atuais.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['paciente'] ?? 'Sem informacoes') ?></td>
                            <td><?= e($row['hospital'] ?? 'Sem informacoes') ?></td>
                            <td><?= fmtMoney((float)($row['custo'] ?? 0)) ?></td>
                            <td><?= e($row['opme'] === 's' ? 'Sim' : 'Nao') ?></td>
                            <td><?= e($row['alto_custo'] === 's' ? 'Sim' : 'Nao') ?></td>
                            <td><?= e($row['evento'] === 's' ? 'Sim' : 'Nao') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
