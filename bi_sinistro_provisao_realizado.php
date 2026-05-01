<?php
$pageTitle = 'Provisao vs Realizado';
$pageSlug = 'bi/sinistro-provisao-realizado';
require_once("templates/bi_rede_bootstrap.php");

function bindParams(PDOStatement $stmt, array $params): void
{
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
}

$capeanteDateExpr = "COALESCE(NULLIF(ca.data_inicial_capeante,'0000-00-00'), NULLIF(ca.data_digit_capeante,'0000-00-00'), NULLIF(ca.data_fech_capeante,'0000-00-00'))";
$capFilters = biRedeBuildWhere($filterValues, $capeanteDateExpr, 'i', true);
$capWhere = $capFilters['where'];
$capParams = $capFilters['params'];
$capJoins = $capFilters['joins'];

$summaryStmt = $conn->prepare("
    SELECT
        SUM(COALESCE(ca.valor_apresentado_capeante,0)) AS provisao,
        SUM(COALESCE(ca.valor_final_capeante,0)) AS realizado,
        COUNT(DISTINCT ca.fk_int_capeante) AS casos
    FROM tb_capeante ca
    JOIN tb_internacao i ON i.id_internacao = ca.fk_int_capeante
    {$capJoins}
    WHERE {$capWhere}
");
$summaryStmt->execute($capParams);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$provisao = (float)($summary['provisao'] ?? 0);
$realizado = (float)($summary['realizado'] ?? 0);
$casos = (int)($summary['casos'] ?? 0);
$delta = $provisao - $realizado;
$deltaPct = $provisao > 0 ? ($delta / $provisao) * 100 : 0.0;

$seriesStmt = $conn->prepare("
    SELECT
        DATE_FORMAT({$capeanteDateExpr}, '%Y-%m-01') AS mes_ref,
        DATE_FORMAT({$capeanteDateExpr}, '%b/%Y') AS etiqueta,
        SUM(COALESCE(ca.valor_apresentado_capeante,0)) AS provisao,
        SUM(COALESCE(ca.valor_final_capeante,0)) AS realizado
    FROM tb_capeante ca
    JOIN tb_internacao i ON i.id_internacao = ca.fk_int_capeante
    {$capJoins}
    WHERE {$capWhere}
    GROUP BY mes_ref, etiqueta
    ORDER BY mes_ref ASC
");
$seriesStmt->execute($capParams);
$rows = $seriesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Provisao vs Realizado</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Comparativo entre previsao de sinistro e realizacao.</div>
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
                <small>Provisao</small>
                <strong><?= fmtMoney($provisao) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Realizado</small>
                <strong><?= fmtMoney($realizado) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Delta</small>
                <strong><?= fmtMoney($delta) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Casos analisados</small>
                <strong><?= fmtInt($casos) ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Serie mensal</h3>
        <div class="bi-split">
            <div class="bi-placeholder">Grafico de provisao vs realizado.</div>
            <div class="bi-list">
                <div class="bi-list-item">
                    <span>Delta %</span>
                    <strong><?= fmtPct($deltaPct, 1) ?></strong>
                </div>
                <div class="bi-list-item">
                    <span>Provisao</span>
                    <strong><?= fmtMoney($provisao) ?></strong>
                </div>
                <div class="bi-list-item">
                    <span>Realizado</span>
                    <strong><?= fmtMoney($realizado) ?></strong>
                </div>
            </div>
        </div>
        <table class="bi-table" style="margin-top: 16px;">
            <thead>
                <tr>
                    <th>Mes</th>
                    <th>Provisao</th>
                    <th>Realizado</th>
                    <th>Delta</th>
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
                        $rowProv = (float)($row['provisao'] ?? 0);
                        $rowReal = (float)($row['realizado'] ?? 0);
                        $rowDelta = $rowProv - $rowReal;
                        ?>
                        <tr>
                            <td><?= e($row['etiqueta'] ?? '-') ?></td>
                            <td><?= fmtMoney($rowProv) ?></td>
                            <td><?= fmtMoney($rowReal) ?></td>
                            <td><?= fmtMoney($rowDelta) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
