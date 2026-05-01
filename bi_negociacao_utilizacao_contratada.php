<?php
$pageTitle = 'Taxa de Utilizacao Contratada';
$pageSlug = 'bi/negociacao-utilizacao';
require_once("templates/bi_rede_bootstrap.php");

$internFilters = biRedeBuildWhere($filterValues, 'i.data_intern_int', 'i', true);
$internWhere = $internFilters['where'];
$internParams = $internFilters['params'];
$internJoins = $internFilters['joins'];

$start = new DateTime($filterValues['data_ini']);
$end = new DateTime($filterValues['data_fim']);
$periodDays = max(1, (int)$start->diff($end)->days + 1);

$summaryStmt = $conn->prepare("\n    SELECT COUNT(DISTINCT i.id_internacao) AS internacoes\n    FROM tb_internacao i\n    {$internJoins}\n    WHERE {$internWhere}\n");
biBindParams($summaryStmt, $internParams);
$summaryStmt->execute();
$internacoes = (int)($summaryStmt->fetch(PDO::FETCH_ASSOC)['internacoes'] ?? 0);

$rowsStmt = $conn->prepare("\n    SELECT\n        h.nome_hosp AS hospital,\n        COUNT(DISTINCT i.id_internacao) AS internacoes\n    FROM tb_internacao i\n    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int\n    {$internJoins}\n    WHERE {$internWhere}\n    GROUP BY h.id_hospital\n    HAVING h.id_hospital IS NOT NULL\n    ORDER BY internacoes DESC\n    LIMIT 10\n");
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
            <h1 class="bi-title">Taxa de Utilizacao Contratada</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Uso medio diario de internacoes por hospital.</div>
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
                <small>Internacoes no periodo</small>
                <strong><?= fmtInt($internacoes) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Dias analisados</small>
                <strong><?= fmtInt($periodDays) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Media diaria geral</small>
                <strong><?= fmtFloat($internacoes / $periodDays, 2) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Hospitais no top 10</small>
                <strong><?= fmtInt(count($rows)) ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Uso medio diario por hospital</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Hospital</th>
                    <th>Internacoes</th>
                    <th>Internacoes/dia</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="3" class="bi-empty">Sem dados com os filtros atuais.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <?php $ints = (int)($row['internacoes'] ?? 0); ?>
                        <tr>
                            <td><?= e($row['hospital'] ?? 'Sem informacoes') ?></td>
                            <td><?= fmtInt($ints) ?></td>
                            <td><?= fmtFloat($ints / $periodDays, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
