<?php
$pageTitle = 'Pacientes Cronicos';
$pageSlug = 'bi/risco-cronicos';
require_once("templates/bi_rede_bootstrap.php");

$internFilters = biRedeBuildWhere($filterValues, 'i.data_intern_int', 'i', true);
$internWhere = $internFilters['where'];
$internParams = $internFilters['params'];
$internJoins = $internFilters['joins'];

$summaryStmt = $conn->prepare("\n    SELECT\n        COUNT(DISTINCT i.fk_paciente_int) AS pacientes,\n        COUNT(*) AS internacoes\n    FROM tb_internacao i\n    {$internJoins}\n    WHERE {$internWhere}\n");
biBindParams($summaryStmt, $internParams);
$summaryStmt->execute();
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$pacientes = (int)($summary['pacientes'] ?? 0);
$internacoes = (int)($summary['internacoes'] ?? 0);

$rowsStmt = $conn->prepare("\n    SELECT\n        p.nome_pac AS paciente,\n        COUNT(*) AS internacoes,\n        MAX(i.data_intern_int) AS ultima_internacao\n    FROM tb_internacao i\n    LEFT JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int\n    {$internJoins}\n    WHERE {$internWhere}\n    GROUP BY i.fk_paciente_int\n    HAVING COUNT(*) >= 3\n    ORDER BY internacoes DESC\n    LIMIT 10\n");
biBindParams($rowsStmt, $internParams);
$rowsStmt->execute();
$rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$cronicos = count($rows);
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260509-filter-icons"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Pacientes Cronicos</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Pacientes com 3 ou mais internacoes no período.</div>
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
                <small>Pacientes no recorte</small>
                <strong><?= fmtInt($pacientes) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Internações no período</small>
                <strong><?= fmtInt($internacoes) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Cronicos no top 10</small>
                <strong><?= fmtInt($cronicos) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Razao internacoes/paciente</small>
                <strong><?= fmtFloat($pacientes > 0 ? ($internacoes / $pacientes) : 0, 1) ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Top 10 pacientes cronicos</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Internações</th>
                    <th>Ultima internacao</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="3" class="bi-empty">Sem dados com os filtros atuais.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['paciente'] ?? 'Sem informações') ?></td>
                            <td><?= fmtInt((int)($row['internacoes'] ?? 0)) ?></td>
                            <td><?= e($row['ultima_internacao'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
