<?php
$pageTitle = 'Analise de Alto Custo';
$pageSlug = 'bi/sinistro-alto-custo';
require_once("templates/bi_rede_bootstrap.php");

function bindParams(PDOStatement $stmt, array $params): void
{
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
}

$internFilters = biRedeBuildWhere($filterValues, 'i.data_intern_int', 'i', true);
$internWhere = $internFilters['where'];
$internParams = $internFilters['params'];
$internJoins = $internFilters['joins'];

$costExpr = "COALESCE(NULLIF(ca.valor_final_capeante,0), ca.valor_apresentado_capeante, 0)";

$totStmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT pa.id_paciente) AS total_pacientes,
        SUM({$costExpr}) AS total_gasto
    FROM tb_internacao i
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao
    {$internJoins}
    WHERE {$internWhere}
");
$totStmt->execute($internParams);
$totRow = $totStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$totalPacientes = (int)($totRow['total_pacientes'] ?? 0);
$totalGasto = (float)($totRow['total_gasto'] ?? 0);
$topCount = $totalPacientes > 0 ? max(1, (int)ceil($totalPacientes * 0.05)) : 0;

$topGasto = 0.0;
if ($topCount > 0) {
    $topStmt = $conn->prepare("
        SELECT SUM(total_cost) AS top_gasto
        FROM (
            SELECT pa.id_paciente, SUM({$costExpr}) AS total_cost
            FROM tb_internacao i
            LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
            LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao
            {$internJoins}
            WHERE {$internWhere}
            GROUP BY pa.id_paciente
            ORDER BY total_cost DESC
            LIMIT {$topCount}
        ) t
    ");
    $topStmt->execute($internParams);
    $topRow = $topStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $topGasto = (float)($topRow['top_gasto'] ?? 0);
}
$topPct = $totalGasto > 0 ? ($topGasto / $totalGasto) * 100 : 0.0;

$listStmt = $conn->prepare("
    SELECT
        pa.nome_pac AS paciente,
        COUNT(DISTINCT i.id_internacao) AS casos,
        SUM({$costExpr}) AS total_cost
    FROM tb_internacao i
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao
    {$internJoins}
    WHERE {$internWhere}
    GROUP BY pa.id_paciente
    ORDER BY total_cost DESC
    LIMIT 12
");
$listStmt->execute($internParams);
$rows = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Analise de Alto Custo</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Concentracao de gasto nos pacientes de maior custo.</div>
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
                <small>Total gasto</small>
                <strong><?= fmtMoney($totalGasto) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Pacientes analisados</small>
                <strong><?= fmtInt($totalPacientes) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Top 5% pacientes</small>
                <strong><?= fmtInt($topCount) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Concentracao do gasto</small>
                <strong><?= fmtPct($topPct, 1) ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Pacientes com maior custo</h3>
        <div class="bi-split">
            <div class="bi-placeholder">Grafico de concentracao (Top 5%).</div>
            <div class="bi-list">
                <div class="bi-list-item">
                    <span>Gasto Top 5%</span>
                    <strong><?= fmtMoney($topGasto) ?></strong>
                </div>
                <div class="bi-list-item">
                    <span>% do total</span>
                    <strong><?= fmtPct($topPct, 1) ?></strong>
                </div>
                <div class="bi-list-item">
                    <span>Gasto medio (Top 5%)</span>
                    <strong><?= $topCount > 0 ? fmtMoney($topGasto / $topCount) : '-' ?></strong>
                </div>
            </div>
        </div>
        <table class="bi-table" style="margin-top: 16px;">
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Custo total</th>
                    <th>Casos</th>
                    <th>% do total</th>
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
                        $rowTotal = (float)($row['total_cost'] ?? 0);
                        $rowPct = $totalGasto > 0 ? ($rowTotal / $totalGasto) * 100 : 0.0;
                        ?>
                        <tr>
                            <td><?= e($row['paciente'] ?? 'Paciente') ?></td>
                            <td><?= fmtMoney($rowTotal) ?></td>
                            <td><?= fmtInt((int)($row['casos'] ?? 0)) ?></td>
                            <td><?= fmtPct($rowPct, 1) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
