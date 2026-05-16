<?php
$pageTitle = 'Contas Paradas - Rede Hospitalar';
$pageSlug = 'bi/rede-paradas-capeante';
require_once("templates/bi_rede_bootstrap.php");

$capeanteDateExpr = "COALESCE(NULLIF(ca.data_inicial_capeante,'0000-00-00'), NULLIF(ca.data_digit_capeante,'0000-00-00'), NULLIF(ca.data_fech_capeante,'0000-00-00'))";
$capFilters = biRedeBuildWhere($filterValues, $capeanteDateExpr, 'i', true);
$capWhere = $capFilters['where'];
$capParams = $capFilters['params'];
$capJoins = $capFilters['joins'];

$summaryStmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT ca.fk_int_capeante) AS casos,
        SUM(CASE WHEN COALESCE(ca.conta_parada_cap,'n') = 's' THEN 1 ELSE 0 END) AS paradas
    FROM tb_capeante ca
    JOIN tb_internacao i ON i.id_internacao = ca.fk_int_capeante
    {$capJoins}
    WHERE {$capWhere}
");
$summaryStmt->execute($capParams);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$casos = (int)($summary['casos'] ?? 0);
$paradas = (int)($summary['paradas'] ?? 0);
$paradasPct = $casos > 0 ? ($paradas / $casos) * 100 : 0.0;

$rowsStmt = $conn->prepare("
    SELECT
        h.nome_hosp AS hospital,
        COUNT(DISTINCT ca.fk_int_capeante) AS casos,
        SUM(CASE WHEN COALESCE(ca.conta_parada_cap,'n') = 's' THEN 1 ELSE 0 END) AS paradas
    FROM tb_capeante ca
    JOIN tb_internacao i ON i.id_internacao = ca.fk_int_capeante
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    {$capJoins}
    WHERE {$capWhere}
    GROUP BY h.id_hospital
    HAVING h.id_hospital IS NOT NULL
    ORDER BY paradas DESC
    LIMIT 12
");
$rowsStmt->execute($capParams);
$paradasRows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$hospitalMaior = '-';
$hospitalAderente = '-';
$alertas = 0;
foreach ($paradasRows as $row) {
    $rowCasos = (int)($row['casos'] ?? 0);
    $rowParadas = (int)($row['paradas'] ?? 0);
    $rate = $rowCasos > 0 ? ($rowParadas / $rowCasos) * 100 : 0.0;
    if ($hospitalMaior === '-' && $rowParadas > 0) {
        $hospitalMaior = $row['hospital'] ?? '-';
    }
    if ($hospitalAderente === '-') {
        $hospitalAderente = $row['hospital'] ?? '-';
    } elseif ($rate < 5) {
        $hospitalAderente = $row['hospital'] ?? $hospitalAderente;
    }
    if ($rate >= 15) {
        $alertas++;
    }
}
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260509-filter-icons"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Contas Paradas</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Comparativo de contas paradas por hospital.</div>
        </div>
        <div class="bi-header-actions">
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/rede-comparativa" title="Comparativa da rede">
                <i class="bi bi-chevron-left"></i>
            </a>
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
                <small>Paradas na rede</small>
                <strong><?= fmtPct($paradasPct, 1) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Hospitais acima do padrao</small>
                <strong><?= fmtInt($alertas) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Indicador</small>
                <strong>Conta parada</strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Casos analisados</small>
                <strong><?= fmtInt($casos) ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Contas paradas por hospital</h3>
        <div class="bi-split">
            <div class="bi-placeholder">Grafico de contas paradas por hospital.</div>
            <div class="bi-list">
                <div class="bi-list-item">
                    <span>Hospital com mais paradas</span>
                    <strong><?= e($hospitalMaior) ?></strong>
                </div>
                <div class="bi-list-item">
                    <span>Hospital com menos paradas</span>
                    <strong><?= e($hospitalAderente) ?></strong>
                </div>
                <div class="bi-list-item">
                    <span>Alertas ativos</span>
                    <strong><?= fmtInt($alertas) ?></strong>
                </div>
            </div>
        </div>
        <table class="bi-table" style="margin-top: 16px;">
            <thead>
                <tr>
                    <th>Hospital</th>
                    <th>Taxa de paradas</th>
                    <th>Indicador</th>
                    <th>Casos</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$paradasRows): ?>
                    <tr>
                        <td colspan="4" class="bi-empty">Sem dados com os filtros atuais.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($paradasRows as $row): ?>
                        <?php
                        $rowCasos = (int)($row['casos'] ?? 0);
                        $rowParadas = (int)($row['paradas'] ?? 0);
                        $rate = $rowCasos > 0 ? ($rowParadas / $rowCasos) * 100 : 0.0;
                        ?>
                        <tr>
                            <td><?= e($row['hospital'] ?? 'Sem informações') ?></td>
                            <td><?= fmtPct($rate, 1) ?></td>
                            <td>Conta parada</td>
                            <td><?= fmtInt($rowCasos) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
