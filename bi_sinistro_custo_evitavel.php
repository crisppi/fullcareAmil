<?php
$pageTitle = 'Custo Evitavel';
$pageSlug = 'bi/sinistro-custo-evitavel';
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

$limiarDias = filter_input(INPUT_GET, 'limiar_dias', FILTER_VALIDATE_INT);
$limiarDias = $limiarDias && $limiarDias > 0 ? (int)$limiarDias : 30;
$janelaReadm = filter_input(INPUT_GET, 'janela_readm', FILTER_VALIDATE_INT);
$janelaReadm = $janelaReadm && $janelaReadm > 0 ? (int)$janelaReadm : 30;

$costExpr = "COALESCE(NULLIF(ca.valor_final_capeante,0), ca.valor_apresentado_capeante, 0)";

$longaStmt = $conn->prepare("
    SELECT
        COUNT(*) AS casos_longa,
        SUM(custo) AS custo_longa,
        MAX(diarias) AS max_diarias,
        ROUND(AVG(diarias), 1) AS mp
    FROM (
        SELECT
            i.id_internacao,
            {$costExpr} AS custo,
            GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1) AS diarias
        FROM tb_internacao i
        LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao
        LEFT JOIN (
            SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
            FROM tb_alta
            GROUP BY fk_id_int_alt
        ) al ON al.fk_id_int_alt = i.id_internacao
        {$internJoins}
        WHERE {$internWhere}
    ) t
    WHERE t.diarias >= :limiar_dias
");
$paramsLonga = $internParams;
$paramsLonga[':limiar_dias'] = $limiarDias;
$longaStmt->execute($paramsLonga);
$longa = $longaStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$casosLonga = (int)($longa['casos_longa'] ?? 0);
$custoLonga = (float)($longa['custo_longa'] ?? 0);
$maxDiarias = (int)($longa['max_diarias'] ?? 0);
$mpLonga = (float)($longa['mp'] ?? 0);

$readmStmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT i.id_internacao) AS casos_readm,
        SUM({$costExpr}) AS custo_readm
    FROM tb_internacao i
    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao
    LEFT JOIN (
        SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
        FROM tb_alta
        GROUP BY fk_id_int_alt
    ) al ON al.fk_id_int_alt = i.id_internacao
    {$internJoins}
    WHERE {$internWhere}
      AND al.data_alta_alt IS NOT NULL
      AND EXISTS (
        SELECT 1
        FROM tb_internacao i2
        WHERE i2.fk_paciente_int = i.fk_paciente_int
          AND i2.data_intern_int > al.data_alta_alt
          AND i2.data_intern_int <= DATE_ADD(al.data_alta_alt, INTERVAL :janela_readm DAY)
      )
");
$paramsReadm = $internParams;
$paramsReadm[':janela_readm'] = $janelaReadm;
$readmStmt->execute($paramsReadm);
$readm = $readmStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$casosReadm = (int)($readm['casos_readm'] ?? 0);
$custoReadm = (float)($readm['custo_readm'] ?? 0);

$custoEvitavel = $custoLonga + $custoReadm;

$tableStmt = $conn->prepare("
    SELECT
        i.id_internacao,
        h2.nome_hosp AS hospital,
        pa.nome_pac AS paciente,
        GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1) AS diarias,
        {$costExpr} AS custo
    FROM tb_internacao i
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_hospital h2 ON h2.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao
    LEFT JOIN (
        SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
        FROM tb_alta
        GROUP BY fk_id_int_alt
    ) al ON al.fk_id_int_alt = i.id_internacao
    {$internJoins}
    WHERE {$internWhere}
      AND GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1) >= :limiar_dias
    ORDER BY diarias DESC
    LIMIT 20
");
$tableStmt->execute($paramsLonga);
$tableRows = $tableStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260509-filter-icons"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Custo Evitavel</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Casos com permanencia longa e readmissoes.</div>
        </div>
        <div class="bi-header-actions">
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação BI">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <form method="get">
        <div class="bi-panel bi-filters bi-filters-wrap">
            <div class="bi-filter">
                <label for="data_ini">Data inicial</label>
                <input type="date" id="data_ini" name="data_ini" value="<?= e($filterValues['data_ini']) ?>">
            </div>
            <div class="bi-filter">
                <label for="data_fim">Data final</label>
                <input type="date" id="data_fim" name="data_fim" value="<?= e($filterValues['data_fim']) ?>">
            </div>
            <div class="bi-filter">
                <label for="hospital_id">Hospital</label>
                <select id="hospital_id" name="hospital_id">
                    <option value="">Todos</option>
                    <?php foreach ($filterOptions['hospitais'] as $opt): ?>
                        <option value="<?= e($opt['value']) ?>" <?= (string)$filterValues['hospital_id'] === (string)$opt['value'] ? 'selected' : '' ?>>
                            <?= e($opt['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="bi-filter">
                <label for="seguradora_id">Seguradora</label>
                <select id="seguradora_id" name="seguradora_id">
                    <option value="">Todas</option>
                    <?php foreach ($filterOptions['seguradoras'] as $opt): ?>
                        <option value="<?= e($opt['value']) ?>" <?= (string)$filterValues['seguradora_id'] === (string)$opt['value'] ? 'selected' : '' ?>>
                            <?= e($opt['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="bi-filter">
                <label for="limiar_dias">Limiar longa permanencia</label>
                <select id="limiar_dias" name="limiar_dias">
                    <?php foreach ([15, 20, 30, 45, 60, 90] as $dias): ?>
                        <option value="<?= $dias ?>" <?= $limiarDias === $dias ? 'selected' : '' ?>><?= $dias ?> dias</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="bi-filter">
                <label for="janela_readm">Janela readmissao</label>
                <select id="janela_readm" name="janela_readm">
                    <?php foreach ([15, 30, 45, 60] as $dias): ?>
                        <option value="<?= $dias ?>" <?= $janelaReadm === $dias ? 'selected' : '' ?>><?= $dias ?> dias</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="bi-actions">
                <button class="bi-btn" type="submit">Aplicar filtros</button>
            </div>
        </div>
    </form>

    <div class="bi-panel">
        <h3>Indicadores-chave</h3>
        <div class="bi-kpis kpi-grid-4">
            <div class="bi-kpi kpi-compact">
                <small>Casos longa permanencia</small>
                <strong><?= fmtInt($casosLonga) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Readmissoes</small>
                <strong><?= fmtInt($casosReadm) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Custo evitavel</small>
                <strong><?= fmtMoney($custoEvitavel) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Maior permanencia</small>
                <strong><?= fmtInt($maxDiarias) ?> dias</strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Casos com permanencia longa</h3>
        <div class="bi-split">
            <div class="bi-placeholder">Distribuição de permanencia e readmissoes.</div>
            <div class="bi-list">
                <div class="bi-list-item">
                    <span>MP longa permanencia</span>
                    <strong><?= fmtFloat($mpLonga, 1) ?> dias</strong>
                </div>
                <div class="bi-list-item">
                    <span>Custo longa permanencia</span>
                    <strong><?= fmtMoney($custoLonga) ?></strong>
                </div>
                <div class="bi-list-item">
                    <span>Custo readmissoes</span>
                    <strong><?= fmtMoney($custoReadm) ?></strong>
                </div>
            </div>
        </div>
        <table class="bi-table" style="margin-top: 16px;">
            <thead>
                <tr>
                    <th>Hospital</th>
                    <th>Paciente</th>
                    <th>Diarias</th>
                    <th>Custo</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$tableRows): ?>
                    <tr>
                        <td colspan="4" class="bi-empty">Sem dados com os filtros atuais.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tableRows as $row): ?>
                        <tr>
                            <td><?= e($row['hospital'] ?? 'Sem informações') ?></td>
                            <td><?= e($row['paciente'] ?? 'Paciente') ?></td>
                            <td><?= fmtInt((int)($row['diarias'] ?? 0)) ?></td>
                            <td><?= fmtMoney((float)($row['custo'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
