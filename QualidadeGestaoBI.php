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

$startInput = filter_input(INPUT_GET, 'data_inicio') ?: '';
$endInput = filter_input(INPUT_GET, 'data_fim') ?: '';
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;

$defaultStart = date('Y-m-01', strtotime('-11 months'));
$defaultEnd = date('Y-m-d');

$startDate = DateTime::createFromFormat('Y-m-d', $startInput) ?: new DateTime($defaultStart);
$endDate = DateTime::createFromFormat('Y-m-d', $endInput) ?: new DateTime($defaultEnd);
$startDate->modify('first day of this month');
$endDate->modify('last day of this month');

$startStr = $startDate->format('Y-m-d');
$endStr = $endDate->format('Y-m-d');

$mesMap = [
    '01' => 'Jan', '02' => 'Fev', '03' => 'Mar', '04' => 'Abr',
    '05' => 'Mai', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago',
    '09' => 'Set', '10' => 'Out', '11' => 'Nov', '12' => 'Dez'
];

$monthKeys = [];
$monthLabels = [];
$cursor = clone $startDate;
$limitEnd = (clone $endDate)->modify('first day of next month');
while ($cursor < $limitEnd) {
    $key = $cursor->format('Y-m');
    $monthKeys[] = $key;
    $monthLabels[] = $mesMap[$cursor->format('m')] . '/' . $cursor->format('Y');
    $cursor->modify('+1 month');
}

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);

$whereHosp = '';
$params = [':start' => $startStr, ':end' => $endStr];
if ($hospitalId) {
    $whereHosp = " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = (int)$hospitalId;
}

// Alto custo por hospital
$sqlAltoHosp = "
    SELECT ho.nome_hosp AS label, COUNT(DISTINCT i.id_internacao) AS total
    FROM tb_gestao g
    JOIN tb_internacao i ON i.id_internacao = g.fk_internacao_ges
    JOIN tb_hospital ho ON ho.id_hospital = i.fk_hospital_int
    WHERE g.alto_custo_ges = 's'
      AND i.data_intern_int BETWEEN :start AND :end
      {$whereHosp}
    GROUP BY ho.id_hospital
    ORDER BY total DESC
    LIMIT 10
";
$stmt = $conn->prepare($sqlAltoHosp);
$stmt->execute($params);
$altoHospRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$altoHospLabels = array_map(fn($r) => $r['label'], $altoHospRows);
$altoHospVals = array_map(fn($r) => (int)$r['total'], $altoHospRows);

// Alto custo por patologia
$sqlAltoPat = "
    SELECT COALESCE(p.patologia_pat, 'Sem informacoes') AS label, COUNT(DISTINCT i.id_internacao) AS total
    FROM tb_gestao g
    JOIN tb_internacao i ON i.id_internacao = g.fk_internacao_ges
    LEFT JOIN tb_patologia p ON p.id_patologia = i.fk_patologia_int
    WHERE g.alto_custo_ges = 's'
      AND i.data_intern_int BETWEEN :start AND :end
      {$whereHosp}
    GROUP BY p.id_patologia
    ORDER BY total DESC
    LIMIT 10
";
$stmt = $conn->prepare($sqlAltoPat);
$stmt->execute($params);
$altoPatRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$altoPatLabels = array_map(fn($r) => $r['label'], $altoPatRows);
$altoPatVals = array_map(fn($r) => (int)$r['total'], $altoPatRows);

// Eventos adversos por 1000 internações (mensal)
$sqlEventos = "
    SELECT ym, SUM(evento_flag) AS eventos, COUNT(*) AS internacoes
    FROM (
        SELECT i.id_internacao,
               DATE_FORMAT(i.data_intern_int, '%Y-%m') AS ym,
               MAX(CASE WHEN g.evento_adverso_ges = 's' THEN 1 ELSE 0 END) AS evento_flag
        FROM tb_internacao i
        LEFT JOIN tb_gestao g ON g.fk_internacao_ges = i.id_internacao
        WHERE i.data_intern_int BETWEEN :start AND :end
          {$whereHosp}
        GROUP BY i.id_internacao, ym
    ) t
    GROUP BY ym
    ORDER BY ym ASC
";
$stmt = $conn->prepare($sqlEventos);
$stmt->execute($params);
$eventRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$eventRate = array_fill_keys($monthKeys, 0.0);
foreach ($eventRows as $row) {
    $ym = $row['ym'];
    if (!isset($eventRate[$ym])) {
        continue;
    }
    $intern = (int)($row['internacoes'] ?? 0);
    $event = (int)($row['eventos'] ?? 0);
    $eventRate[$ym] = $intern > 0 ? round(($event * 1000) / $intern, 2) : 0.0;
}

// Glosas evitadas vs glosadas
$dateExpr = "COALESCE(NULLIF(ca.data_final_capeante,'0000-00-00'), NULLIF(ca.data_fech_capeante,'0000-00-00'), NULLIF(ca.data_create_cap,'0000-00-00'))";
$sqlGlosa = "
    SELECT DATE_FORMAT(ref_date, '%Y-%m') AS ym,
           SUM(COALESCE(valor_glosa_total,0)) AS glosada,
           SUM(GREATEST(COALESCE(valor_apresentado_capeante,0) - COALESCE(valor_glosa_total,0), 0)) AS evitada
    FROM (
        SELECT ca.valor_apresentado_capeante,
               ca.valor_glosa_total,
               {$dateExpr} AS ref_date,
               i.fk_hospital_int
        FROM tb_capeante ca
        JOIN tb_internacao i ON i.id_internacao = ca.fk_int_capeante
    ) t
    WHERE ref_date IS NOT NULL AND ref_date <> '0000-00-00'
      AND ref_date BETWEEN :start AND :end
      " . ($hospitalId ? " AND fk_hospital_int = :hospital_id " : "") . "
    GROUP BY ym
    ORDER BY ym ASC
";
$stmt = $conn->prepare($sqlGlosa);
$stmt->execute($params);
$glosaRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$glosaEvitada = array_fill_keys($monthKeys, 0.0);
$glosaGlosada = array_fill_keys($monthKeys, 0.0);
foreach ($glosaRows as $row) {
    $ym = $row['ym'];
    if (!isset($glosaEvitada[$ym])) {
        continue;
    }
    $glosaEvitada[$ym] = (float)($row['evitada'] ?? 0);
    $glosaGlosada[$ym] = (float)($row['glosada'] ?? 0);
}
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Qualidade e Gestão</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted">Últimos 12 meses</div>
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <form class="bi-panel bi-filters" method="get">
        <div class="bi-filter">
            <label>Hospital</label>
            <select name="hospital_id">
                <option value="">Todos</option>
                <?php foreach ($hospitais as $h): ?>
                    <option value="<?= (int)$h['id_hospital'] ?>" <?= $hospitalId == $h['id_hospital'] ? 'selected' : '' ?>>
                        <?= e($h['nome_hosp']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Data inicio</label>
            <input type="date" name="data_inicio" value="<?= e($startStr) ?>">
        </div>
        <div class="bi-filter">
            <label>Data fim</label>
            <input type="date" name="data_fim" value="<?= e($endStr) ?>">
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="bi-grid fixed-2" style="margin-top:16px;">
        <div class="bi-panel">
            <h3 class="text-center" style="margin-bottom:12px;">Alto custo por hospital</h3>
            <div class="bi-chart"><canvas id="chartAltoHosp"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3 class="text-center" style="margin-bottom:12px;">Alto custo por patologia</h3>
            <div class="bi-chart"><canvas id="chartAltoPat"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3 class="text-center" style="margin-bottom:12px;">Eventos adversos por 1.000 internações</h3>
            <div class="bi-chart"><canvas id="chartEventos"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3 class="text-center" style="margin-bottom:12px;">Glosas evitadas vs glosadas</h3>
            <div class="bi-chart"><canvas id="chartGlosa"></canvas></div>
        </div>
    </div>
</div>

<script>
const chartLabels = <?= json_encode($monthLabels) ?>;
const altoHospLabels = <?= json_encode($altoHospLabels) ?>;
const altoHospVals = <?= json_encode($altoHospVals) ?>;
const altoPatLabels = <?= json_encode($altoPatLabels) ?>;
const altoPatVals = <?= json_encode($altoPatVals) ?>;
const eventosVals = <?= json_encode(array_values($eventRate)) ?>;
const glosaEvitada = <?= json_encode(array_values($glosaEvitada)) ?>;
const glosaGlosada = <?= json_encode(array_values($glosaGlosada)) ?>;

function barChart(ctx, labels, data, color) {
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Total',
                data,
                backgroundColor: color
            }]
        },
        options: {
            legend: window.biLegendWhite ? window.biLegendWhite : undefined,
            scales: window.biChartScales ? window.biChartScales() : undefined
        }
    });
}

function lineChart(ctx, labels, data) {
    return new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Eventos/1000',
                data,
                borderColor: 'rgba(255, 198, 108, 0.9)',
                backgroundColor: 'rgba(255, 198, 108, 0.25)',
                fill: true,
                tension: 0.25
            }]
        },
        options: {
            legend: window.biLegendWhite ? window.biLegendWhite : undefined,
            scales: window.biChartScales ? window.biChartScales() : undefined
        }
    });
}

function stackedBar(ctx, labels, dataA, dataB) {
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Evitadas',
                    data: dataA,
                    backgroundColor: 'rgba(111, 223, 194, 0.7)'
                },
                {
                    label: 'Glosadas',
                    data: dataB,
                    backgroundColor: 'rgba(255, 99, 132, 0.7)'
                }
            ]
        },
        options: {
            legend: window.biLegendWhite ? window.biLegendWhite : undefined,
            scales: {
                xAxes: [{ stacked: true }],
                yAxes: [{ stacked: true }]
            }
        }
    });
}

barChart(document.getElementById('chartAltoHosp'), altoHospLabels, altoHospVals, 'rgba(141, 208, 255, 0.7)');
barChart(document.getElementById('chartAltoPat'), altoPatLabels, altoPatVals, 'rgba(208, 113, 176, 0.7)');
lineChart(document.getElementById('chartEventos'), chartLabels, eventosVals);
stackedBar(document.getElementById('chartGlosa'), chartLabels, glosaEvitada, glosaGlosada);
</script>

<?php require_once("templates/footer.php"); ?>
