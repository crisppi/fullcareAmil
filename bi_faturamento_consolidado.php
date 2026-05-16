<?php
include_once("check_logado.php");
require_once("templates/header.php");

if (!isset($conn) || !($conn instanceof PDO)) {
    die("Conexão inválida.");
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$seguradoraId = filter_input(INPUT_GET, 'seguradora_id', FILTER_VALIDATE_INT) ?: null;
$auditorId = filter_input(INPUT_GET, 'auditor_id', FILTER_VALIDATE_INT) ?: null;
$dtIniRaw = trim((string)filter_input(INPUT_GET, 'dt_ini', FILTER_SANITIZE_SPECIAL_CHARS));
$dtFimRaw = trim((string)filter_input(INPUT_GET, 'dt_fim', FILTER_SANITIZE_SPECIAL_CHARS));
$faturado = strtolower(trim((string)(filter_input(INPUT_GET, 'faturado', FILTER_SANITIZE_SPECIAL_CHARS) ?? '')));
if (!in_array($faturado, ['s', 'n', ''], true)) {
    $faturado = '';
}

$today = new DateTime();
if ($dtFimRaw === '') {
    $dtFimRaw = $today->format('Y-m-d');
}
if ($dtIniRaw === '') {
    $dtIniTmp = clone $today;
    $dtIniTmp->modify('-30 days');
    $dtIniRaw = $dtIniTmp->format('Y-m-d');
}
if ($dtIniRaw !== '' && $dtFimRaw !== '' && $dtIniRaw > $dtFimRaw) {
    [$dtIniRaw, $dtFimRaw] = [$dtFimRaw, $dtIniRaw];
}

$hospitais = $conn->query('SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp')->fetchAll(PDO::FETCH_ASSOC);
$rawSeguradoras = $conn->query('SELECT id_seguradora, seguradora_seg FROM tb_seguradora ORDER BY seguradora_seg')->fetchAll(PDO::FETCH_ASSOC);
$seguradoras = [];
$seenSeg = [];
foreach ($rawSeguradoras as $seg) {
    $label = trim($seg['seguradora_seg'] ?? '');
    $key = mb_strtolower($label, 'UTF-8');
    if ($key === '') {
        continue;
    }
    if (isset($seenSeg[$key])) {
        continue;
    }
    $seenSeg[$key] = true;
    $seguradoras[] = $seg;
}
$auditores = $conn->query('SELECT id_usuario, usuario_user FROM tb_user WHERE usuario_user <> "" ORDER BY usuario_user')->fetchAll(PDO::FETCH_ASSOC);

$parsedDateExpr = <<<'SQL'
COALESCE(
    STR_TO_DATE(NULLIF(v1.data_visita_vis,''), '%Y-%m-%d %H:%i:%s'),
    STR_TO_DATE(NULLIF(v1.data_visita_vis,''), '%Y-%m-%dT%H:%i:%s'),
    STR_TO_DATE(NULLIF(v1.data_visita_vis,''), '%Y-%m-%d'),
    STR_TO_DATE(NULLIF(v1.data_visita_vis,''), '%d/%m/%Y %H:%i:%s'),
    STR_TO_DATE(NULLIF(v1.data_visita_vis,''), '%d/%m/%Y %H:%i'),
    STR_TO_DATE(NULLIF(v1.data_visita_vis,''), '%d/%m/%Y'),
    STR_TO_DATE(NULLIF(v1.data_visita_vis,''), '%d-%m-%Y %H:%i:%s'),
    STR_TO_DATE(NULLIF(v1.data_visita_vis,''), '%d-%m-%Y')
)
SQL;
$validLancExpr = "NULLIF(NULLIF(v1.data_lancamento_vis,''), '0000-00-00 00:00:00')";
$coalescedDateExpr = "COALESCE($validLancExpr, $parsedDateExpr)";

$where = ['COALESCE(i.deletado_int, \'\') <> "s"', '(v1.retificado IS NULL OR v1.retificado IN (0,\'0\',\'\',\'n\',\'N\'))', "$coalescedDateExpr IS NOT NULL"];
$params = [];
if ($hospitalId) {
    $where[] = 'i.fk_hospital_int = :hospital_id';
    $params[':hospital_id'] = $hospitalId;
}
if ($seguradoraId) {
    $where[] = 'pa.fk_seguradora_pac = :seguradora_id';
    $params[':seguradora_id'] = $seguradoraId;
}
if ($auditorId) {
    $where[] = 'v1.fk_usuario_vis = :auditor_id';
    $params[':auditor_id'] = $auditorId;
}
if ($dtIniRaw) {
    $where[] = "DATE($coalescedDateExpr) >= :dt_ini";
    $params[':dt_ini'] = $dtIniRaw;
}
if ($dtFimRaw) {
    $where[] = "DATE($coalescedDateExpr) <= :dt_fim";
    $params[':dt_fim'] = $dtFimRaw;
}
if ($faturado) {
    $where[] = "IFNULL(NULLIF(v1.faturado_vis,''),'n') = :faturado";
    $params[':faturado'] = $faturado;
}
$whereClause = implode(' AND ', $where);

$joinClause = <<<SQL
FROM tb_visita v1
LEFT JOIN tb_internacao i ON i.id_internacao = v1.fk_internacao_vis
LEFT JOIN tb_hospital ho ON ho.id_hospital = i.fk_hospital_int
LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
LEFT JOIN tb_seguradora se ON se.id_seguradora = pa.fk_seguradora_pac
LEFT JOIN tb_user um ON um.id_usuario = CAST(NULLIF(v1.visita_auditor_prof_med,'') AS UNSIGNED)
LEFT JOIN tb_user u ON u.id_usuario = v1.fk_usuario_vis
SQL;

    $auditorMedText = "NULLIF(TRIM(v1.visita_auditor_prof_med), '')";
    $auditorLabelExpr = "COALESCE(NULLIF(um.usuario_user,''), NULLIF($auditorMedText,''), NULLIF(u.usuario_user,''), 'Sem auditor')";
$seguradoraLabelExpr = "COALESCE(NULLIF(TRIM(se.seguradora_seg), ''), 'Sem seguradora')";

$summarySql = <<<SQL
SELECT
    COUNT(*) AS total_visitas,
    SUM(IFNULL(NULLIF(v1.faturado_vis,''), 'n') = 's') AS total_faturado,
    SUM(IFNULL(NULLIF(v1.faturado_vis,''), 'n') = 'n') AS total_nao_faturado,
    COUNT(DISTINCT i.fk_hospital_int) AS hospitais_distintos
$joinClause
WHERE $whereClause
SQL;

$stmt = $conn->prepare($summarySql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

function fetchRows(PDO $conn, string $sql, array $params): array
{
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$hospitalRows = fetchRows($conn, <<<SQL
SELECT COALESCE(ho.nome_hosp, 'Sem hospital') AS label, COUNT(*) AS value
$joinClause
WHERE $whereClause
GROUP BY ho.id_hospital, ho.nome_hosp
ORDER BY value DESC
LIMIT 10
SQL
, $params);

$seguradoraRows = fetchRows($conn, <<<SQL
SELECT $seguradoraLabelExpr AS label, COUNT(*) AS value
$joinClause
WHERE $whereClause
GROUP BY label
ORDER BY value DESC
LIMIT 10
SQL
, $params);

$auditorRows = fetchRows($conn, <<<SQL
SELECT $auditorLabelExpr AS label, COUNT(*) AS value
$joinClause
WHERE $whereClause
GROUP BY $auditorLabelExpr
ORDER BY value DESC
LIMIT 12
SQL
, $params);

    $trendRows = fetchRows($conn, <<<SQL
    SELECT
        DATE_FORMAT($coalescedDateExpr, '%Y-%m') AS month,
        COUNT(*) AS total,
        SUM(IFNULL(NULLIF(v1.faturado_vis,''), 'n') = 's') AS faturado
    $joinClause
    WHERE $whereClause
    GROUP BY month
    ORDER BY month
    SQL
    , $params);

    $cycleRows = fetchRows($conn, <<<SQL
    SELECT
        DATE($coalescedDateExpr) AS day,
        COUNT(*) AS total
    $joinClause
    WHERE $whereClause
    GROUP BY day
    ORDER BY day
    SQL
    , $params);

$trendLabels = [];
$trendTotal = [];
$trendFaturado = [];
foreach ($trendRows as $row) {
    if (empty($row['month'])) {
        continue;
    }
    $date = DateTime::createFromFormat('Y-m', $row['month']);
    if (!$date) {
        continue;
    }
    $trendLabels[] = $date->format('M/Y');
    $trendTotal[] = (int)$row['total'];
    $trendFaturado[] = (int)$row['faturado'];
}

$cycleLabels = [];
$cycleValues = [];
foreach ($cycleRows as $row) {
    if (empty($row['day'])) {
        continue;
    }
    $date = DateTime::createFromFormat('Y-m-d', $row['day']);
    if (!$date) {
        continue;
    }
    $cycleLabels[] = $date->format('d/m');
    $cycleValues[] = (int)$row['total'];
}

?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260509-filter-icons"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>
<style>
.bi-faturamento-consolidado .bi-chart {
    min-height: 172px;
    height: 172px;
}

.bi-faturamento-consolidado .bi-chart.bi-chart-line {
    min-height: 192px;
    height: 192px;
}

@media (max-width: 900px) {
    .bi-faturamento-consolidado .bi-chart,
    .bi-faturamento-consolidado .bi-chart.bi-chart-line {
        min-height: 182px;
        height: 182px;
    }
}
</style>

<div class="bi-wrapper bi-theme bi-faturamento-consolidado">
    <div class="bi-header">
        <h1 class="bi-title">Faturamento consolidado</h1>
        <div class="bi-header-actions">
            <span class="text-muted small"><?= e('período filtrado: ' . $dtIniRaw . ' até ' . $dtFimRaw) ?></span>
        </div>
    </div>

    <form class="bi-panel bi-filters" method="get">
        <div class="bi-filter">
            <label>Hospital</label>
            <select name="hospital_id">
                <option value="">Todos</option>
                <?php foreach ($hospitais as $hospital): ?>
                    <option value="<?= (int)$hospital['id_hospital'] ?>" <?= $hospitalId === (int)$hospital['id_hospital'] ? 'selected' : '' ?>>
                        <?= e($hospital['nome_hosp']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Seguradora</label>
            <select name="seguradora_id">
                <option value="">Todas</option>
                <?php foreach ($seguradoras as $seg): ?>
                    <option value="<?= (int)$seg['id_seguradora'] ?>" <?= $seguradoraId === (int)$seg['id_seguradora'] ? 'selected' : '' ?>>
                        <?= e($seg['seguradora_seg']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Auditor</label>
            <select name="auditor_id">
                <option value="">Todos</option>
                <?php foreach ($auditores as $aud): ?>
                    <option value="<?= (int)$aud['id_usuario'] ?>" <?= $auditorId === (int)$aud['id_usuario'] ? 'selected' : '' ?>>
                        <?= e($aud['usuario_user']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Início lançamento</label>
            <input type="date" name="dt_ini" value="<?= e($dtIniRaw) ?>">
        </div>
        <div class="bi-filter">
            <label>Fim lançamento</label>
            <input type="date" name="dt_fim" value="<?= e($dtFimRaw) ?>">
        </div>
        <div class="bi-filter">
            <label>Faturado</label>
            <select name="faturado">
                <option value="" <?= $faturado === '' ? 'selected' : '' ?>>Todos</option>
                <option value="s" <?= $faturado === 's' ? 'selected' : '' ?>>Sim</option>
                <option value="n" <?= $faturado === 'n' ? 'selected' : '' ?>>Não</option>
            </select>
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="bi-panel">
        <div class="bi-kpis">
            <div class="bi-kpi">
                <small>Total visitas</small>
                <strong><?= number_format((int)$summary['total_visitas'] ?? 0, 0, ',', '.') ?></strong>
            </div>
            <div class="bi-kpi">
                <small>Faturado</small>
                <strong><?= number_format((int)$summary['total_faturado'] ?? 0, 0, ',', '.') ?></strong>
            </div>
            <div class="bi-kpi">
                <small>Não faturado</small>
                <strong><?= number_format((int)$summary['total_nao_faturado'] ?? 0, 0, ',', '.') ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Distribuição por hospital</h3>
        <div class="bi-chart"><canvas id="chartHospitais"></canvas></div>
    </div>
    <div class="bi-panel">
        <h3>Distribuição por seguradora</h3>
        <div class="bi-chart"><canvas id="chartSeguradoras"></canvas></div>
    </div>
    <div class="bi-panel">
        <h3>Volumetria por auditor</h3>
        <div class="bi-chart"><canvas id="chartAuditores"></canvas></div>
    </div>
    <div class="bi-panel">
        <h3>Evolução por ciclo (30 dias)</h3>
        <div class="bi-chart bi-chart-line"><canvas id="chartCycle"></canvas></div>
    </div>
    <div class="bi-panel">
        <h3>Evolução mensal de faturamento</h3>
        <div class="bi-chart bi-chart-line"><canvas id="chartTrend"></canvas></div>
    </div>
</div>

<script>
const hospitalLabels = <?= json_encode(array_map(fn($row) => $row['label'], $hospitalRows)) ?>;
const hospitalValues = <?= json_encode(array_map(fn($row) => (int)$row['value'], $hospitalRows)) ?>;
const seguradoraLabels = <?= json_encode(array_map(fn($row) => $row['label'], $seguradoraRows)) ?>;
const seguradoraValues = <?= json_encode(array_map(fn($row) => (int)$row['value'], $seguradoraRows)) ?>;
const auditorLabels = <?= json_encode(array_map(fn($row) => $row['label'], $auditorRows)) ?>;
const auditorValues = <?= json_encode(array_map(fn($row) => (int)$row['value'], $auditorRows)) ?>;
const trendLabels = <?= json_encode($trendLabels) ?>;
const trendTotal = <?= json_encode($trendTotal) ?>;
const trendFaturado = <?= json_encode($trendFaturado) ?>;
const cycleLabels = <?= json_encode($cycleLabels) ?>;
const cycleValues = <?= json_encode($cycleValues) ?>;

function barChart(ctx, labels, data, color) {
    return new Chart(ctx, {
        type: 'bar',
        data: { labels, datasets: [{ data, backgroundColor: color }] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: window.biChartScales ? window.biChartScales() : undefined,
            legend: { display: false },
            plugins: { legend: { display: false } }
        }
    });
}

function stackedLine(ctx, labels, datasets) {
    return new Chart(ctx, {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: window.biChartScales ? window.biChartScales() : undefined,
            plugins: {
                legend: { display: false }
            }
        }
    });
}

barChart(document.getElementById('chartHospitais'), hospitalLabels, hospitalValues, 'rgba(99, 102, 241, 0.9)');
barChart(document.getElementById('chartSeguradoras'), seguradoraLabels, seguradoraValues, 'rgba(229, 62, 122, 0.75)');
barChart(document.getElementById('chartAuditores'), auditorLabels, auditorValues, 'rgba(16, 185, 129, 0.75)');
stackedLine(document.getElementById('chartTrend'), trendLabels, [
    {
        label: 'Total visitas',
        data: trendTotal,
        borderColor: 'rgba(59, 130, 246, 0.8)',
        backgroundColor: 'rgba(59, 130, 246, 0.1)',
        tension: 0.3,
        fill: true
    },
    {
        label: 'Faturado',
        data: trendFaturado,
        borderColor: 'rgba(16, 185, 129, 0.8)',
        backgroundColor: 'rgba(16, 185, 129, 0.15)',
        tension: 0.3,
        fill: true
    }
]);

if (cycleLabels.length && cycleValues.length) {
    stackedLine(document.getElementById('chartCycle'), cycleLabels, [
        {
            label: 'Visitas',
            data: cycleValues,
            borderColor: 'rgba(249, 115, 22, 0.85)',
            backgroundColor: 'rgba(249, 115, 22, 0.2)',
            tension: 0.3,
            fill: true
        }
    ]);
}
</script>

<?php require_once("templates/footer.php"); ?>
