<?php
$pageTitle = 'Conformidade de Faturamento';
$pageSlug = 'bi/conformidade-faturamento';
require_once("templates/bi_rede_bootstrap.php");

$capeanteDateExpr = "COALESCE(NULLIF(ca.data_inicial_capeante,'0000-00-00'), NULLIF(ca.data_digit_capeante,'0000-00-00'), NULLIF(ca.data_fech_capeante,'0000-00-00'))";
$capFilters = biRedeBuildWhere($filterValues, $capeanteDateExpr, 'i', true);
$capWhere = $capFilters['where'];
$capParams = $capFilters['params'];
$capJoins = $capFilters['joins'];

$summaryStmt = $conn->prepare("\n    SELECT\n        COUNT(DISTINCT ca.fk_int_capeante) AS casos,\n        SUM(COALESCE(ca.valor_apresentado_capeante,0)) AS valor_apresentado,\n        SUM(COALESCE(ca.valor_glosa_total,0)) AS valor_glosa\n    FROM tb_capeante ca\n    JOIN tb_internacao i ON i.id_internacao = ca.fk_int_capeante\n    {$capJoins}\n    WHERE {$capWhere}\n");
biBindParams($summaryStmt, $capParams);
$summaryStmt->execute();
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$casos = (int)($summary['casos'] ?? 0);
$valorApresentado = (float)($summary['valor_apresentado'] ?? 0);
$valorGlosa = (float)($summary['valor_glosa'] ?? 0);
$glosaPct = $valorApresentado > 0 ? ($valorGlosa / $valorApresentado) * 100 : 0.0;
$conformidade = max(0, 100 - $glosaPct);

$rowsStmt = $conn->prepare("\n    SELECT\n        h.nome_hosp AS hospital,\n        COUNT(DISTINCT ca.fk_int_capeante) AS casos,\n        SUM(COALESCE(ca.valor_apresentado_capeante,0)) AS valor_apresentado,\n        SUM(COALESCE(ca.valor_glosa_total,0)) AS valor_glosa\n    FROM tb_capeante ca\n    JOIN tb_internacao i ON i.id_internacao = ca.fk_int_capeante\n    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int\n    {$capJoins}\n    WHERE {$capWhere}\n    GROUP BY h.id_hospital\n    HAVING h.id_hospital IS NOT NULL\n    ORDER BY valor_glosa DESC\n    LIMIT 10\n");
biBindParams($rowsStmt, $capParams);
$rowsStmt->execute();
$rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260516-rounded-bars"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Conformidade de Faturamento</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Diferenca entre valor apresentado e glosado.</div>
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
                <small>Taxa media de glosa</small>
                <strong><?= fmtPct($glosaPct, 1) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Valor glosado</small>
                <strong><?= fmtMoney($valorGlosa) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Conformidade media</small>
                <strong><?= fmtPct($conformidade, 1) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Casos analisados</small>
                <strong><?= fmtInt($casos) ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Hospitais com maior glosa</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Hospital</th>
                    <th>Valor glosa</th>
                    <th>Taxa glosa</th>
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
                        <?php
                        $ap = (float)($row['valor_apresentado'] ?? 0);
                        $gl = (float)($row['valor_glosa'] ?? 0);
                        $rate = $ap > 0 ? ($gl / $ap) * 100 : 0.0;
                        ?>
                        <tr>
                            <td><?= e($row['hospital'] ?? 'Sem informações') ?></td>
                            <td><?= fmtMoney($gl) ?></td>
                            <td><?= fmtPct($rate, 1) ?></td>
                            <td><?= fmtInt((int)($row['casos'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
