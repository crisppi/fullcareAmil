<?php
include_once("check_logado.php");
require_once("templates/header.php");
require_once __DIR__ . "/app/bi_cid_options.php";

if (!isset($conn) || !($conn instanceof PDO)) {
    die("Conexao invalida.");
}

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$hoje = date('Y-m-d');
$dataIni = filter_input(INPUT_GET, 'data_ini') ?: date('Y-m-d', strtotime('-120 days'));
$dataFim = filter_input(INPUT_GET, 'data_fim') ?: $hoje;
$internado = trim((string)(filter_input(INPUT_GET, 'internado') ?? ''));
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;

$filterScope = [
    'internado' => $internado,
    'hospital_id' => $hospitalId,
    'data_inicio' => $dataIni,
    'data_fim' => $dataFim,
];

$hospitais = bi_fetch_filter_options($conn, 'hospital', $filterScope, ['date_expr' => 'i.data_intern_int']);

$where = "i.data_intern_int BETWEEN :data_ini AND :data_fim";
$params = [':data_ini' => $dataIni, ':data_fim' => $dataFim];
if ($internado !== '') {
    $where .= " AND i.internado_int = :internado";
    $params[':internado'] = $internado;
}
if ($hospitalId) {
    $where .= " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = $hospitalId;
}

$sqlBase = "
    FROM tb_internacao i
    LEFT JOIN (
        SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
        FROM tb_alta
        GROUP BY fk_id_int_alt
    ) al ON al.fk_id_int_alt = i.id_internacao
    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao
    WHERE {$where}
";

function chartRows(PDO $conn, string $labelExpr, string $sqlBase, array $params, string $metric, int $limit = 12): array
{
    $sql = "SELECT {$labelExpr} AS label, {$metric} AS total {$sqlBase} GROUP BY label ORDER BY total DESC LIMIT {$limit}";
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function labelsValues(array $rows): array
{
    return [
        array_map(fn($r) => $r['label'] ?? 'Sem informações', $rows),
        array_map(fn($r) => (float)($r['total'] ?? 0), $rows),
    ];
}

$labelTipo = "COALESCE(NULLIF(i.tipo_admissao_int,''), 'Sem informações')";
$labelModo = "COALESCE(NULLIF(i.modo_internacao_int,''), 'Sem informações')";
$rowsTipo = chartRows($conn, $labelTipo, $sqlBase, $params, "COUNT(DISTINCT i.id_internacao)", 12);
$rowsModo = chartRows($conn, $labelModo, $sqlBase, $params, "COUNT(DISTINCT i.id_internacao)", 12);
$rowsTipoMp = chartRows($conn, $labelTipo, $sqlBase, $params, "ROUND(AVG(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)), 1)", 12);
$rowsTipoCusto = chartRows($conn, $labelTipo, $sqlBase, $params, "SUM(COALESCE(ca.valor_final_capeante,0))", 12);

$stmtKpi = $conn->prepare("
    SELECT COUNT(DISTINCT i.id_internacao) AS internacoes,
           SUM(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)) AS diarias,
           SUM(COALESCE(ca.valor_final_capeante,0)) AS custo
    {$sqlBase}
");
$stmtKpi->execute($params);
$kpi = $stmtKpi->fetch(PDO::FETCH_ASSOC) ?: [];
$internacoes = (int)($kpi['internacoes'] ?? 0);
$diarias = (int)($kpi['diarias'] ?? 0);
$mp = $internacoes > 0 ? $diarias / $internacoes : 0;

[$labelsTipo, $valuesTipo] = labelsValues($rowsTipo);
[$labelsModo, $valuesModo] = labelsValues($rowsModo);
[$labelsTipoMp, $valuesTipoMp] = labelsValues($rowsTipoMp);
[$labelsTipoCusto, $valuesTipoCusto] = labelsValues($rowsTipoCusto);
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260516-rounded-bars"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Tipo Internação</h1>
        <div class="bi-header-actions">
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação"><i class="bi bi-grid-3x3-gap"></i></a>
        </div>
    </div>

    <form class="bi-panel bi-filters" method="get">
        <div class="bi-filter">
            <label>Internado</label>
            <select name="internado">
                <option value="">Todos</option>
                <option value="s" <?= $internado === 's' ? 'selected' : '' ?>>Sim</option>
                <option value="n" <?= $internado === 'n' ? 'selected' : '' ?>>Não</option>
            </select>
        </div>
        <div class="bi-filter">
            <label>Hospitais</label>
            <select name="hospital_id">
                <option value="">Todos</option>
                <?php foreach ($hospitais as $h): ?>
                    <option value="<?= (int)$h['value'] ?>" <?= $hospitalId == $h['value'] ? 'selected' : '' ?>><?= e($h['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter"><label>Data Internação</label><input type="date" name="data_ini" value="<?= e($dataIni) ?>"></div>
        <div class="bi-filter"><label>Data Final</label><input type="date" name="data_fim" value="<?= e($dataFim) ?>"></div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
            <a class="bi-btn bi-btn-reset" href="<?= $BASE_URL ?>bi/tipo-internacao">Limpar</a>
        </div>
    </form>

    <div class="bi-panel" style="margin-top:16px;">
        <div class="bi-kpis kpi-dashboard-v2">
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-1">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-hospital"></i></span>
                    <small>Internações</small>
                </div>
                <strong><?= number_format($internacoes, 0, ',', '.') ?></strong>
                <span class="kpi-trend">Casos no período</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-2">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-calendar2-week"></i></span>
                    <small>Diárias</small>
                </div>
                <strong><?= number_format($diarias, 0, ',', '.') ?></strong>
                <span class="kpi-trend">Total acumulado</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-3">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-clock-history"></i></span>
                    <small>MP</small>
                </div>
                <strong><?= number_format($mp, 1, ',', '.') ?></strong>
                <span class="kpi-trend">Média de permanência</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-4">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-currency-dollar"></i></span>
                    <small>Custo</small>
                </div>
                <strong>R$ <?= number_format((float)($kpi['custo'] ?? 0), 2, ',', '.') ?></strong>
                <span class="kpi-trend">Valor final</span>
            </div>
        </div>
    </div>

    <div class="bi-grid fixed-2" style="margin-top:16px;">
        <div class="bi-panel"><h3>Internações por tipo</h3><div class="bi-chart"><canvas id="chartTipo"></canvas></div></div>
        <div class="bi-panel"><h3>Internações por modo</h3><div class="bi-chart"><canvas id="chartModo"></canvas></div></div>
    </div>
    <div class="bi-grid fixed-2" style="margin-top:16px;">
        <div class="bi-panel"><h3>MP por tipo</h3><div class="bi-chart"><canvas id="chartMp"></canvas></div></div>
        <div class="bi-panel"><h3>Custo por tipo</h3><div class="bi-chart"><canvas id="chartCusto"></canvas></div></div>
    </div>
</div>

<script>
const labelsTipo = <?= json_encode($labelsTipo) ?>, valuesTipo = <?= json_encode($valuesTipo) ?>;
const labelsModo = <?= json_encode($labelsModo) ?>, valuesModo = <?= json_encode($valuesModo) ?>;
const labelsTipoMp = <?= json_encode($labelsTipoMp) ?>, valuesTipoMp = <?= json_encode($valuesTipoMp) ?>;
const labelsTipoCusto = <?= json_encode($labelsTipoCusto) ?>, valuesTipoCusto = <?= json_encode($valuesTipoCusto) ?>;
function barChart(id, labels, data, color, tick) {
    const scales = window.biChartScales ? window.biChartScales() : undefined;
    if (scales && tick && scales.yAxes && scales.yAxes[0]) scales.yAxes[0].ticks.callback = tick;
    return new Chart(document.getElementById(id), {type:'bar', data:{labels, datasets:[{data, backgroundColor: color}]}, options:{responsive:true, maintainAspectRatio:false, legend:{display:false}, scales}});
}
barChart('chartTipo', labelsTipo, valuesTipo, 'rgba(141, 208, 255, 0.7)');
barChart('chartModo', labelsModo, valuesModo, 'rgba(111, 223, 194, 0.7)');
barChart('chartMp', labelsTipoMp, valuesTipoMp, 'rgba(255, 198, 108, 0.7)');
barChart('chartCusto', labelsTipoCusto, valuesTipoCusto, 'rgba(208, 113, 176, 0.7)', window.biMoneyTick);
</script>

<?php require_once("templates/footer.php"); ?>
