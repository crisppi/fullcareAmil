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
$internado = trim((string)(filter_input(INPUT_GET, 'internado') ?? ''));

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);

$where = "i.data_intern_int BETWEEN :data_ini AND :data_fim";
$params = [':data_ini' => $dataIni, ':data_fim' => $dataFim];
if ($hospitalId) {
    $where .= " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = $hospitalId;
}
if ($internado !== '') {
    $where .= " AND i.internado_int = :internado";
    $params[':internado'] = $internado;
}

$sqlBase = "
    FROM tb_internacao i
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_patologia p ON p.id_patologia = i.fk_patologia_int
    LEFT JOIN (
        SELECT fk_internacao_vis,
               COUNT(*) AS total_visitas,
               SUM(CASE WHEN COALESCE(rel_visita_vis,'') <> '' THEN 1 ELSE 0 END) AS visitas_relatorio,
               SUM(CASE WHEN COALESCE(acoes_int_vis,'') <> '' THEN 1 ELSE 0 END) AS visitas_acoes,
               SUM(CASE WHEN COALESCE(programacao_enf,'') <> '' THEN 1 ELSE 0 END) AS visitas_programacao,
               MAX(data_visita_vis) AS ultima_visita
        FROM tb_visita
        GROUP BY fk_internacao_vis
    ) v ON v.fk_internacao_vis = i.id_internacao
    WHERE {$where}
";

$stmtKpi = $conn->prepare("
    SELECT COUNT(DISTINCT i.id_internacao) AS internacoes,
           COALESCE(SUM(v.total_visitas),0) AS visitas,
           SUM(CASE WHEN COALESCE(i.rel_int,'') <> '' OR COALESCE(v.visitas_relatorio,0) > 0 THEN 1 ELSE 0 END) AS com_relatorio,
           SUM(CASE WHEN COALESCE(i.acoes_int,'') <> '' OR COALESCE(v.visitas_acoes,0) > 0 THEN 1 ELSE 0 END) AS com_acoes
    {$sqlBase}
");
$stmtKpi->execute($params);
$kpi = $stmtKpi->fetch(PDO::FETCH_ASSOC) ?: [];

function runRows(PDO $conn, string $sql, array $params): array
{
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$rowsHosp = runRows($conn, "SELECT COALESCE(NULLIF(h.nome_hosp,''),'Sem informações') AS label, COUNT(DISTINCT i.id_internacao) AS total {$sqlBase} GROUP BY label ORDER BY total DESC LIMIT 12", $params);
$rowsPat = runRows($conn, "SELECT COALESCE(NULLIF(p.patologia_pat,''),'Sem informações') AS label, COUNT(DISTINCT i.id_internacao) AS total {$sqlBase} GROUP BY label ORDER BY total DESC LIMIT 12", $params);
$programacaoRow = runRows($conn, "SELECT SUM(CASE WHEN COALESCE(v.visitas_programacao,0) > 0 THEN 1 ELSE 0 END) AS total {$sqlBase}", $params);
$rowsCob = [
    ['label' => 'Relatório detalhado', 'total' => (int)($kpi['com_relatorio'] ?? 0)],
    ['label' => 'Ações auditoria', 'total' => (int)($kpi['com_acoes'] ?? 0)],
    ['label' => 'Programação terapêutica', 'total' => (int)($programacaoRow[0]['total'] ?? 0)],
];
$rowsTable = runRows($conn, "
    SELECT COALESCE(NULLIF(pa.nome_pac,''), 'Sem informações') AS paciente,
           COALESCE(NULLIF(h.nome_hosp,''), 'Sem informações') AS hospital,
           COALESCE(NULLIF(p.patologia_pat,''), 'Sem informações') AS patologia,
           COALESCE(v.total_visitas,0) AS visitas,
           COALESCE(NULLIF(i.rel_int,''), '-') AS relatorio,
           COALESCE(NULLIF(i.acoes_int,''), '-') AS acoes
    {$sqlBase}
    ORDER BY i.data_intern_int DESC
    LIMIT 80
", $params);

function labelsValues(array $rows): array
{
    return [
        array_map(fn($r) => $r['label'] ?? 'Sem informações', $rows),
        array_map(fn($r) => (float)($r['total'] ?? 0), $rows),
    ];
}
[$labelsHosp, $valuesHosp] = labelsValues($rowsHosp);
[$labelsPat, $valuesPat] = labelsValues($rowsPat);
[$labelsCob, $valuesCob] = labelsValues($rowsCob);
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260509-filter-icons"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Detalhes Clínicos</h1>
        <div class="bi-header-actions"><a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação"><i class="bi bi-grid-3x3-gap"></i></a></div>
    </div>
    <form class="bi-panel bi-filters" method="get">
        <div class="bi-filter"><label>Internado</label><select name="internado"><option value="">Todos</option><option value="s" <?= $internado === 's' ? 'selected' : '' ?>>Sim</option><option value="n" <?= $internado === 'n' ? 'selected' : '' ?>>Não</option></select></div>
        <div class="bi-filter"><label>Hospitais</label><select name="hospital_id"><option value="">Todos</option><?php foreach ($hospitais as $h): ?><option value="<?= (int)$h['id_hospital'] ?>" <?= $hospitalId == $h['id_hospital'] ? 'selected' : '' ?>><?= e($h['nome_hosp']) ?></option><?php endforeach; ?></select></div>
        <div class="bi-filter"><label>Data Internação</label><input type="date" name="data_ini" value="<?= e($dataIni) ?>"></div>
        <div class="bi-filter"><label>Data Final</label><input type="date" name="data_fim" value="<?= e($dataFim) ?>"></div>
        <div class="bi-actions"><button class="bi-btn" type="submit">Aplicar</button></div>
    </form>
    <div class="bi-panel" style="margin-top:16px;"><div class="bi-kpis kpi-compact">
        <div class="bi-kpi kpi-indigo kpi-compact"><small>Internações</small><strong><?= number_format((int)($kpi['internacoes'] ?? 0), 0, ',', '.') ?></strong></div>
        <div class="bi-kpi kpi-teal kpi-compact"><small>Visitas</small><strong><?= number_format((int)($kpi['visitas'] ?? 0), 0, ',', '.') ?></strong></div>
        <div class="bi-kpi kpi-amber kpi-compact"><small>Com relatório</small><strong><?= number_format((int)($kpi['com_relatorio'] ?? 0), 0, ',', '.') ?></strong></div>
        <div class="bi-kpi kpi-rose kpi-compact"><small>Com ações</small><strong><?= number_format((int)($kpi['com_acoes'] ?? 0), 0, ',', '.') ?></strong></div>
    </div></div>
    <div class="bi-grid fixed-3" style="margin-top:16px;">
        <div class="bi-panel"><h3>Hospitais</h3><div class="bi-chart"><canvas id="chartHosp"></canvas></div></div>
        <div class="bi-panel"><h3>Patologias</h3><div class="bi-chart"><canvas id="chartPat"></canvas></div></div>
        <div class="bi-panel"><h3>Cobertura clínica</h3><div class="bi-chart"><canvas id="chartCob"></canvas></div></div>
    </div>
    <div class="bi-panel" style="margin-top:16px;">
        <h3>Relatório detalhado</h3>
        <table class="bi-table"><thead><tr><th>Paciente</th><th>Hospital</th><th>Patologia</th><th>Visitas</th><th>Relatório</th><th>Ações</th></tr></thead><tbody>
        <?php if (!$rowsTable): ?><tr><td colspan="6">Sem informações para o filtro selecionado.</td></tr><?php endif; ?>
        <?php foreach ($rowsTable as $row): ?><tr><td><?= e($row['paciente']) ?></td><td><?= e($row['hospital']) ?></td><td><?= e($row['patologia']) ?></td><td><?= (int)$row['visitas'] ?></td><td><?= e($row['relatorio']) ?></td><td><?= e($row['acoes']) ?></td></tr><?php endforeach; ?>
        </tbody></table>
    </div>
</div>

<script>
const labelsHosp = <?= json_encode($labelsHosp) ?>, valuesHosp = <?= json_encode($valuesHosp) ?>;
const labelsPat = <?= json_encode($labelsPat) ?>, valuesPat = <?= json_encode($valuesPat) ?>;
const labelsCob = <?= json_encode($labelsCob) ?>, valuesCob = <?= json_encode($valuesCob) ?>;
function bar(id, labels, data, color) { return new Chart(document.getElementById(id), {type:'bar', data:{labels, datasets:[{data, backgroundColor:color}]}, options:{responsive:true, maintainAspectRatio:false, legend:{display:false}, scales: window.biChartScales ? window.biChartScales() : undefined}}); }
bar('chartHosp', labelsHosp, valuesHosp, 'rgba(141, 208, 255, 0.7)');
bar('chartPat', labelsPat, valuesPat, 'rgba(111, 223, 194, 0.7)');
bar('chartCob', labelsCob, valuesCob, 'rgba(255, 198, 108, 0.7)');
</script>

<?php require_once("templates/footer.php"); ?>
