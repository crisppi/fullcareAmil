<?php
$pageTitle = 'Sinistralidade por Hospital';
$pageSlug = 'bi/sinistro-hospital';
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

$labelPat = "COALESCE(NULLIF(CONCAT_WS(' - ', NULLIF(c.cat, ''), NULLIF(c.descricao, '')), ''), 'Sem informações')";
$costExpr = "COALESCE(NULLIF(ca.valor_final_capeante,0), ca.valor_apresentado_capeante, 0)";

$patologiaFiltro = filter_input(INPUT_GET, 'patologia', FILTER_VALIDATE_INT) ?: null;
$extraWhere = '';
$params = $internParams;
if ($patologiaFiltro) {
    $extraWhere = " AND i.fk_cid_int = :patologia";
    $params[':patologia'] = (int)$patologiaFiltro;
}

$optStmt = $conn->prepare("
    SELECT DISTINCT
        c.id_cid AS value,
        {$labelPat} AS label
    FROM tb_internacao i
    INNER JOIN tb_cid c ON c.id_cid = i.fk_cid_int
    {$internJoins}
    WHERE {$internWhere}
      AND i.fk_cid_int IS NOT NULL
      AND i.fk_cid_int <> 0
    ORDER BY label
");
$optStmt->execute($internParams);
$patologiaOptions = $optStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$summaryStmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT i.id_internacao) AS casos,
        SUM({$costExpr}) AS custo_total,
        COUNT(DISTINCT h2.id_hospital) AS hospitais
    FROM tb_internacao i
    LEFT JOIN tb_hospital h2 ON h2.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_patologia p ON p.id_patologia = i.fk_patologia_int
    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao
    {$internJoins}
    WHERE {$internWhere}{$extraWhere}
");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$casos = (int)($summary['casos'] ?? 0);
$custoTotal = (float)($summary['custo_total'] ?? 0);
$hospitais = (int)($summary['hospitais'] ?? 0);
$custoMedio = $casos > 0 ? ($custoTotal / $casos) : 0.0;

$rowsStmt = $conn->prepare("
    SELECT
        h2.nome_hosp AS hospital,
        COUNT(DISTINCT i.id_internacao) AS casos,
        SUM({$costExpr}) AS custo_total
    FROM tb_internacao i
    LEFT JOIN tb_hospital h2 ON h2.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_patologia p ON p.id_patologia = i.fk_patologia_int
    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao
    {$internJoins}
    WHERE {$internWhere}{$extraWhere}
    GROUP BY h2.id_hospital
    HAVING h2.id_hospital IS NOT NULL
    ORDER BY custo_total DESC
    LIMIT 12
");
$rowsStmt->execute($params);
$rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$topHospital = $rows[0]['hospital'] ?? '-';
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260516-rounded-bars"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Sinistralidade por Hospital</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Qual hospital gasta mais em cada diagnostico.</div>
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
                <label for="patologia">CID</label>
                <select id="patologia" name="patologia">
                    <option value="">Todas</option>
                    <?php foreach ($patologiaOptions as $opt): ?>
                        <option value="<?= (int)$opt['value'] ?>" <?= (int)$patologiaFiltro === (int)$opt['value'] ? 'selected' : '' ?>>
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
                <label for="regiao">Região</label>
                <select id="regiao" name="regiao">
                    <option value="">Todas</option>
                    <?php foreach ($filterOptions['regioes'] as $opt): ?>
                        <option value="<?= e($opt['value']) ?>" <?= (string)$filterValues['regiao'] === (string)$opt['value'] ? 'selected' : '' ?>>
                            <?= e($opt['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="bi-filter">
                <label for="tipo_admissao">Tipo de admissão</label>
                <select id="tipo_admissao" name="tipo_admissao">
                    <option value="">Todos</option>
                    <?php foreach ($filterOptions['tipos_admissao'] as $opt): ?>
                        <option value="<?= e($opt['value']) ?>" <?= (string)$filterValues['tipo_admissao'] === (string)$opt['value'] ? 'selected' : '' ?>>
                            <?= e($opt['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="bi-filter">
                <label for="modo_internacao">Modo de internação</label>
                <select id="modo_internacao" name="modo_internacao">
                    <option value="">Todos</option>
                    <?php foreach ($filterOptions['modos_internacao'] as $opt): ?>
                        <option value="<?= e($opt['value']) ?>" <?= (string)$filterValues['modo_internacao'] === (string)$opt['value'] ? 'selected' : '' ?>>
                            <?= e($opt['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="bi-filter">
                <label for="uti">UTI</label>
                <select id="uti" name="uti">
                    <option value="">Todos</option>
                    <option value="s" <?= $filterValues['uti'] === 's' ? 'selected' : '' ?>>Sim</option>
                    <option value="n" <?= $filterValues['uti'] === 'n' ? 'selected' : '' ?>>Não</option>
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
                <small>Custo total</small>
                <strong><?= fmtMoney($custoTotal) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Hospital com maior custo</small>
                <strong><?= e($topHospital) ?></strong>
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
        <h3>Top hospitais por custo</h3>
        <div class="bi-split">
            <div class="bi-placeholder">Grafico de custo por hospital.</div>
            <div class="bi-list">
                <div class="bi-list-item">
                    <span>Hospitais no recorte</span>
                    <strong><?= fmtInt($hospitais) ?></strong>
                </div>
                <div class="bi-list-item">
                    <span>Patologia filtrada</span>
                    <strong><?= $patologiaFiltro !== '' ? e($patologiaFiltro) : 'Todas' ?></strong>
                </div>
                <div class="bi-list-item">
                    <span>Custo total</span>
                    <strong><?= fmtMoney($custoTotal) ?></strong>
                </div>
            </div>
        </div>
        <table class="bi-table" style="margin-top: 16px;">
            <thead>
                <tr>
                    <th>Hospital</th>
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
                            <td><?= e($row['hospital'] ?? 'Sem informações') ?></td>
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
