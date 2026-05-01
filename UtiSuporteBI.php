<?php
include_once("check_logado.php");
require_once("templates/header.php");

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
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$internadoUti = trim((string)(filter_input(INPUT_GET, 'internado_uti') ?? ''));

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);

$where = "i.data_intern_int BETWEEN :data_ini AND :data_fim";
$params = [':data_ini' => $dataIni, ':data_fim' => $dataFim];
if ($hospitalId) {
    $where .= " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = $hospitalId;
}
if ($internadoUti !== '') {
    $where .= " AND u.internado_uti = :internado_uti";
    $params[':internado_uti'] = $internadoUti;
}

$sqlBase = "
    FROM tb_uti u
    INNER JOIN tb_internacao i ON i.id_internacao = u.fk_internacao_uti
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    WHERE {$where}
";

function rows(PDO $conn, string $labelExpr, string $sqlBase, array $params, int $limit = 10): array
{
    $stmt = $conn->prepare("SELECT {$labelExpr} AS label, COUNT(*) AS total {$sqlBase} GROUP BY label ORDER BY total DESC LIMIT {$limit}");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$stmtKpi = $conn->prepare("
    SELECT COUNT(*) AS registros,
           COUNT(DISTINCT i.id_internacao) AS internacoes,
           SUM(CASE WHEN u.vm_uti = 's' THEN 1 ELSE 0 END) AS vm,
           SUM(CASE WHEN u.dva_uti = 's' THEN 1 ELSE 0 END) AS dva,
           SUM(CASE WHEN u.suporte_vent_uti = 's' THEN 1 ELSE 0 END) AS suporte_vent
    {$sqlBase}
");
$stmtKpi->execute($params);
$kpi = $stmtKpi->fetch(PDO::FETCH_ASSOC) ?: [];

$rowsVm = rows($conn, "CASE WHEN u.vm_uti = 's' THEN 'Sim' WHEN u.vm_uti = 'n' THEN 'Nao' ELSE 'Sem informacoes' END", $sqlBase, $params, 6);
$rowsDva = rows($conn, "CASE WHEN u.dva_uti = 's' THEN 'Sim' WHEN u.dva_uti = 'n' THEN 'Nao' ELSE 'Sem informacoes' END", $sqlBase, $params, 6);
$rowsSuporte = rows($conn, "CASE WHEN u.suporte_vent_uti = 's' THEN 'Sim' WHEN u.suporte_vent_uti = 'n' THEN 'Nao' ELSE 'Sem informacoes' END", $sqlBase, $params, 6);
$rowsGlasgow = rows($conn, "COALESCE(NULLIF(u.glasgow_uti,''), 'Sem informacoes')", $sqlBase, $params, 10);
$rowsCriterios = rows($conn, "COALESCE(NULLIF(u.criterios_uti,''), 'Sem informacoes')", $sqlBase, $params, 10);
$rowsHosp = rows($conn, "COALESCE(NULLIF(h.nome_hosp,''), 'Sem informacoes')", $sqlBase, $params, 12);

function lv(array $rows): array
{
    return [array_map(fn($r) => $r['label'] ?? 'Sem informacoes', $rows), array_map(fn($r) => (float)($r['total'] ?? 0), $rows)];
}
[$labelsVm, $valuesVm] = lv($rowsVm);
[$labelsDva, $valuesDva] = lv($rowsDva);
[$labelsSuporte, $valuesSuporte] = lv($rowsSuporte);
[$labelsGlasgow, $valuesGlasgow] = lv($rowsGlasgow);
[$labelsCriterios, $valuesCriterios] = lv($rowsCriterios);
[$labelsHosp, $valuesHosp] = lv($rowsHosp);
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">UTI Suporte</h1>
        <div class="bi-header-actions"><a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação"><i class="bi bi-grid-3x3-gap"></i></a></div>
    </div>
    <form class="bi-panel bi-filters" method="get">
        <div class="bi-filter"><label>Hospitais</label><select name="hospital_id"><option value="">Todos</option><?php foreach ($hospitais as $h): ?><option value="<?= (int)$h['id_hospital'] ?>" <?= $hospitalId == $h['id_hospital'] ? 'selected' : '' ?>><?= e($h['nome_hosp']) ?></option><?php endforeach; ?></select></div>
        <div class="bi-filter"><label>Internado UTI</label><select name="internado_uti"><option value="">Todos</option><option value="s" <?= $internadoUti === 's' ? 'selected' : '' ?>>Sim</option><option value="n" <?= $internadoUti === 'n' ? 'selected' : '' ?>>Não</option></select></div>
        <div class="bi-filter"><label>Data Internação</label><input type="date" name="data_ini" value="<?= e($dataIni) ?>"></div>
        <div class="bi-filter"><label>Data Final</label><input type="date" name="data_fim" value="<?= e($dataFim) ?>"></div>
        <div class="bi-actions"><button class="bi-btn" type="submit">Aplicar</button></div>
    </form>
    <div class="bi-panel" style="margin-top:16px;"><div class="bi-kpis kpi-compact">
        <div class="bi-kpi kpi-indigo kpi-compact"><small>Registros UTI</small><strong><?= number_format((int)($kpi['registros'] ?? 0), 0, ',', '.') ?></strong></div>
        <div class="bi-kpi kpi-teal kpi-compact"><small>Internações</small><strong><?= number_format((int)($kpi['internacoes'] ?? 0), 0, ',', '.') ?></strong></div>
        <div class="bi-kpi kpi-amber kpi-compact"><small>VM</small><strong><?= number_format((int)($kpi['vm'] ?? 0), 0, ',', '.') ?></strong></div>
        <div class="bi-kpi kpi-rose kpi-compact"><small>DVA</small><strong><?= number_format((int)($kpi['dva'] ?? 0), 0, ',', '.') ?></strong></div>
    </div></div>
    <div class="bi-grid fixed-3" style="margin-top:16px;">
        <div class="bi-panel"><h3>Ventilação mecânica</h3><div class="bi-chart"><canvas id="chartVm"></canvas></div></div>
        <div class="bi-panel"><h3>DVA</h3><div class="bi-chart"><canvas id="chartDva"></canvas></div></div>
        <div class="bi-panel"><h3>Suporte ventilatório</h3><div class="bi-chart"><canvas id="chartSuporte"></canvas></div></div>
    </div>
    <div class="bi-grid fixed-3" style="margin-top:16px;">
        <div class="bi-panel"><h3>Glasgow</h3><div class="bi-chart"><canvas id="chartGlasgow"></canvas></div></div>
        <div class="bi-panel"><h3>Critérios</h3><div class="bi-chart"><canvas id="chartCriterios"></canvas></div></div>
        <div class="bi-panel"><h3>Hospitais</h3><div class="bi-chart"><canvas id="chartHosp"></canvas></div></div>
    </div>
</div>

<script>
const chartData = {
    chartVm: [<?= json_encode($labelsVm) ?>, <?= json_encode($valuesVm) ?>, 'rgba(141, 208, 255, 0.7)'],
    chartDva: [<?= json_encode($labelsDva) ?>, <?= json_encode($valuesDva) ?>, 'rgba(208, 113, 176, 0.7)'],
    chartSuporte: [<?= json_encode($labelsSuporte) ?>, <?= json_encode($valuesSuporte) ?>, 'rgba(111, 223, 194, 0.7)'],
    chartGlasgow: [<?= json_encode($labelsGlasgow) ?>, <?= json_encode($valuesGlasgow) ?>, 'rgba(255, 198, 108, 0.7)'],
    chartCriterios: [<?= json_encode($labelsCriterios) ?>, <?= json_encode($valuesCriterios) ?>, 'rgba(139, 159, 255, 0.7)'],
    chartHosp: [<?= json_encode($labelsHosp) ?>, <?= json_encode($valuesHosp) ?>, 'rgba(106, 201, 255, 0.7)']
};
Object.keys(chartData).forEach((id) => {
    const [labels, data, color] = chartData[id];
    new Chart(document.getElementById(id), {type:'bar', data:{labels, datasets:[{data, backgroundColor:color}]}, options:{responsive:true, maintainAspectRatio:false, legend:{display:false}, scales: window.biChartScales ? window.biChartScales() : undefined}});
});
</script>

<?php require_once("templates/footer.php"); ?>
