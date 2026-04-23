<?php
$pageTitle = 'Alto Risco de Readmissao';
$pageSlug = 'bi/risco-readmissao';
$clearUrl = 'bi/risco-readmissao';
require_once("bi_rede_bootstrap.php");

$internFilters = biRedeBuildWhere($filterValues, 'i.data_intern_int', 'i', true);
$internWhere = $internFilters['where'];
$internParams = $internFilters['params'];
$internJoins = $internFilters['joins'];

$altaJoin = "LEFT JOIN (\n    SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt\n    FROM tb_alta\n    GROUP BY fk_id_int_alt\n) al ON al.fk_id_int_alt = i.id_internacao";
$stayExpr = "DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int)";

$avgStmt = $conn->prepare("\n    SELECT AVG({$stayExpr}) AS media\n    FROM tb_internacao i\n    {$altaJoin}\n    {$internJoins}\n    WHERE {$internWhere}\n");
biBindParams($avgStmt, $internParams);
$avgStmt->execute();
$avgStay = (float)($avgStmt->fetch(PDO::FETCH_ASSOC)['media'] ?? 0);

$rowsStmt = $conn->prepare("\n    SELECT\n        p.id_paciente,\n        p.nome_pac AS paciente,\n        COALESCE(p.idade_pac, 0) AS idade,\n        COUNT(*) AS internacoes,\n        SUM(\n            CASE WHEN EXISTS (\n                SELECT 1\n                FROM tb_internacao i2\n                WHERE i2.fk_paciente_int = i.fk_paciente_int\n                  AND i2.data_intern_int > al.data_alta_alt\n                  AND i2.data_intern_int <= DATE_ADD(al.data_alta_alt, INTERVAL 30 DAY)\n            ) THEN 1 ELSE 0 END\n        ) AS readm30,\n        AVG({$stayExpr}) AS media_permanencia\n    FROM tb_internacao i\n    LEFT JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int\n    {$altaJoin}\n    {$internJoins}\n    WHERE {$internWhere}\n    GROUP BY p.id_paciente\n    ORDER BY readm30 DESC, internacoes DESC\n    LIMIT 10\n");
biBindParams($rowsStmt, $internParams);
$rowsStmt->execute();
$rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260111">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260111"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Alto Risco de Readmissao</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Pacientes com idade alta, readmissoes e permanencia elevada.</div>
        </div>
        <div class="bi-header-actions">
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegacao BI">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <?php include "bi_rede_filters.php"; ?>

    <div class="bi-panel">
        <h3>Pacientes com maior risco</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Idade</th>
                    <th>Internacoes</th>
                    <th>Readmissao 30d</th>
                    <th>Permanencia media</th>
                    <th>Score risco</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="6" class="bi-empty">Sem dados com os filtros atuais.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $idade = (int)($row['idade'] ?? 0);
                        $internacoes = (int)($row['internacoes'] ?? 0);
                        $readm30 = (int)($row['readm30'] ?? 0);
                        $media = (float)($row['media_permanencia'] ?? 0);
                        $score = $readm30 * 2;
                        if ($idade >= 65) {
                            $score += 1;
                        }
                        if ($media >= $avgStay && $avgStay > 0) {
                            $score += 1;
                        }
                        if ($internacoes >= 3) {
                            $score += 1;
                        }
                        ?>
                        <tr>
                            <td><?= e($row['paciente'] ?? 'Sem informacoes') ?></td>
                            <td><?= fmtInt($idade) ?></td>
                            <td><?= fmtInt($internacoes) ?></td>
                            <td><?= fmtInt($readm30) ?></td>
                            <td><?= fmtFloat($media, 1) ?> dias</td>
                            <td><?= fmtInt($score) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
