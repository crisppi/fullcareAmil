<?php
$pageTitle = 'Sinistralidade por Patologia';
$pageSlug = 'bi/sinistro-patologia';
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

$labelPat = "COALESCE(NULLIF(i.grupo_patologia_int,''), p.patologia_pat, 'Sem informações')";
$costExpr = "COALESCE(NULLIF(ca.valor_final_capeante,0), ca.valor_apresentado_capeante, 0)";

$summaryStmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT i.id_internacao) AS casos,
        COUNT(DISTINCT {$labelPat}) AS patologias,
        SUM({$costExpr}) AS custo_total
    FROM tb_internacao i
    LEFT JOIN tb_patologia p ON p.id_patologia = i.fk_patologia_int
    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao
    {$internJoins}
    WHERE {$internWhere}
");
$summaryStmt->execute($internParams);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$casos = (int)($summary['casos'] ?? 0);
$patologias = (int)($summary['patologias'] ?? 0);
$custoTotal = (float)($summary['custo_total'] ?? 0);
$custoMedio = $casos > 0 ? ($custoTotal / $casos) : 0.0;

$rowsStmt = $conn->prepare("
    SELECT
        {$labelPat} AS patologia,
        COUNT(DISTINCT i.id_internacao) AS casos,
        SUM({$costExpr}) AS custo_total
    FROM tb_internacao i
    LEFT JOIN tb_patologia p ON p.id_patologia = i.fk_patologia_int
    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao
    {$internJoins}
    WHERE {$internWhere}
    GROUP BY patologia
    ORDER BY custo_total DESC
    LIMIT 12
");
$rowsStmt->execute($internParams);
$rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$topPatologia = $rows[0]['patologia'] ?? '-';
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260516-rounded-bars"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Sinistralidade por Patologia</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Diagnosticos que concentram o maior custo.</div>
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
                <small>Custo total</small>
                <strong><?= fmtMoney($custoTotal) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Patologia com maior custo</small>
                <strong><?= e($topPatologia) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Custo médio por caso</small>
                <strong><?= fmtMoney($custoMedio) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Casos analisados</small>
                <strong><?= fmtInt($casos) ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Top patologias por custo</h3>
        <div class="bi-split">
            <div class="bi-placeholder">Grafico de custo por patologia.</div>
            <div class="bi-list">
                <div class="bi-list-item">
                    <span>Patologias no recorte</span>
                    <strong><?= fmtInt($patologias) ?></strong>
                </div>
                <div class="bi-list-item">
                    <span>Custo total</span>
                    <strong><?= fmtMoney($custoTotal) ?></strong>
                </div>
                <div class="bi-list-item">
                    <span>Ticket medio</span>
                    <strong><?= fmtMoney($custoMedio) ?></strong>
                </div>
            </div>
        </div>
        <table class="bi-table" style="margin-top: 16px;">
            <thead>
                <tr>
                    <th>Patologia</th>
                    <th>Custo total</th>
                    <th>Custo médio</th>
                    <th>Casos</th>
                    <th>% do total</th>
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
                        $rowCasos = (int)($row['casos'] ?? 0);
                        $rowTotal = (float)($row['custo_total'] ?? 0);
                        $rowMedio = $rowCasos > 0 ? ($rowTotal / $rowCasos) : 0.0;
                        $rowPct = $custoTotal > 0 ? ($rowTotal / $custoTotal) * 100 : 0.0;
                        ?>
                        <tr>
                            <td><?= e($row['patologia'] ?? 'Sem informações') ?></td>
                            <td><?= fmtMoney($rowTotal) ?></td>
                            <td><?= fmtMoney($rowMedio) ?></td>
                            <td><?= fmtInt($rowCasos) ?></td>
                            <td><?= fmtPct($rowPct, 1) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
