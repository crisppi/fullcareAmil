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
$dataIniInput = filter_input(INPUT_GET, 'data_ini');
$dataFimInput = filter_input(INPUT_GET, 'data_fim');
$rangeMesesInput = filter_input(INPUT_GET, 'range_meses', FILTER_VALIDATE_INT);
$usarRange = (filter_input(INPUT_GET, 'usar_range') === '1');
$rangeMeses = ($rangeMesesInput !== null && $rangeMesesInput !== false && $rangeMesesInput > 0) ? (int)$rangeMesesInput : 12;

if (!$usarRange && ($dataIniInput || $dataFimInput)) {
    $dataIni = $dataIniInput ?: date('Y-01-01');
    $dataFim = $dataFimInput ?: date('Y-12-31');
} else {
    $currentStart = new DateTime('first day of this month');
    $currentEnd = new DateTime('last day of this month');
    $before = (int)floor(($rangeMeses - 1) / 2);
    $after = ($rangeMeses - 1) - $before;
    $startDefault = (clone $currentStart)->modify("-{$before} months");
    $endDefault = (clone $currentEnd)->modify("+{$after} months");
    $dataIni = $startDefault->format('Y-m-d');
    $dataFim = $endDefault->format('Y-m-d');
}
$internado = trim((string)(filter_input(INPUT_GET, 'internado') ?? ''));
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$tipoInternação = trim((string)(filter_input(INPUT_GET, 'tipo_internacao') ?? ''));
$modoAdmissão = trim((string)(filter_input(INPUT_GET, 'modo_admissao') ?? ''));
$uti = trim((string)(filter_input(INPUT_GET, 'uti') ?? ''));

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);
$tiposInt = $conn->query("SELECT DISTINCT tipo_admissao_int FROM tb_internacao WHERE tipo_admissao_int IS NOT NULL AND tipo_admissao_int <> '' ORDER BY tipo_admissao_int")
    ->fetchAll(PDO::FETCH_COLUMN);
$modosAdm = $conn->query("SELECT DISTINCT modo_internacao_int FROM tb_internacao WHERE modo_internacao_int IS NOT NULL AND modo_internacao_int <> '' ORDER BY modo_internacao_int")
    ->fetchAll(PDO::FETCH_COLUMN);

$where = "i.data_intern_int BETWEEN :data_ini AND :data_fim";
$params = [
    ':data_ini' => $dataIni,
    ':data_fim' => $dataFim,
];
if ($internado !== '') {
    $where .= " AND i.internado_int = :internado";
    $params[':internado'] = $internado;
}
if ($hospitalId) {
    $where .= " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = $hospitalId;
}
if ($tipoInternação !== '') {
    $where .= " AND i.tipo_admissao_int = :tipo";
    $params[':tipo'] = $tipoInternação;
}
if ($modoAdmissão !== '') {
    $where .= " AND i.modo_internacao_int = :modo";
    $params[':modo'] = $modoAdmissão;
}

$utiJoin = "LEFT JOIN (SELECT DISTINCT fk_internacao_uti FROM tb_uti) ut ON ut.fk_internacao_uti = i.id_internacao";
if ($uti === 's') {
    $where .= " AND ut.fk_internacao_uti IS NOT NULL";
}
if ($uti === 'n') {
    $where .= " AND ut.fk_internacao_uti IS NULL";
}

$start = new DateTime($dataIni);
$start->modify('first day of this month');
$end = new DateTime($dataFim);
$end->modify('first day of this month');
$monthKeys = [];
$monthLabels = [];
$monthNames = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
$cursor = clone $start;
while ($cursor <= $end) {
    $ym = $cursor->format('Y-m');
    $monthKeys[] = $ym;
    $monthLabels[] = $monthNames[(int)$cursor->format('n') - 1] . '/' . $cursor->format('Y');
    $cursor->modify('+1 month');
}

$series = [
    'internacoes' => array_fill_keys($monthKeys, 0.0),
    'diarias' => array_fill_keys($monthKeys, 0.0),
    'mp' => array_fill_keys($monthKeys, 0.0),
    'valor_final' => array_fill_keys($monthKeys, 0.0),
];

$sqlMonthly = "
    SELECT
        DATE_FORMAT(i.data_intern_int, '%Y-%m') AS ym,
        COUNT(DISTINCT i.id_internacao) AS total_internacoes,
        SUM(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)) AS total_diarias,
        SUM(COALESCE(ca.valor_final_capeante,0)) AS valor_final
    FROM tb_internacao i
    {$utiJoin}
    LEFT JOIN (
        SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
        FROM tb_alta
        GROUP BY fk_id_int_alt
    ) al ON al.fk_id_int_alt = i.id_internacao
    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao
    WHERE {$where}
    GROUP BY ym
    ORDER BY ym
";
$stmt = $conn->prepare($sqlMonthly);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$rowsMonthly = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
foreach ($rowsMonthly as $row) {
    $ym = $row['ym'] ?? '';
    if (!isset($series['internacoes'][$ym])) {
        continue;
    }
    $totalIntern = (int)($row['total_internacoes'] ?? 0);
    $totalDiarias = (int)($row['total_diarias'] ?? 0);
    $series['internacoes'][$ym] = (float)$totalIntern;
    $series['diarias'][$ym] = (float)$totalDiarias;
    $series['mp'][$ym] = $totalIntern > 0 ? round($totalDiarias / $totalIntern, 1) : 0.0;
    $series['valor_final'][$ym] = (float)($row['valor_final'] ?? 0);
}
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260509-filter-icons"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Dashboard Producao</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted"></div>
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegacao">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <form class="bi-panel bi-filters" method="get">
        <input type="hidden" name="usar_range" id="usar_range" value="<?= $usarRange ? '1' : '0' ?>">
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
                    <option value="<?= (int)$h['id_hospital'] ?>" <?= $hospitalId == $h['id_hospital'] ? 'selected' : '' ?>>
                        <?= e($h['nome_hosp']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Tipo Internação</label>
            <select name="tipo_internacao">
                <option value="">Todos</option>
                <?php foreach ($tiposInt as $tipo): ?>
                    <option value="<?= e($tipo) ?>" <?= $tipoInternação === $tipo ? 'selected' : '' ?>>
                        <?= e($tipo) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Modo Admissão</label>
            <select name="modo_admissao">
                <option value="">Todos</option>
                <?php foreach ($modosAdm as $modo): ?>
                    <option value="<?= e($modo) ?>" <?= $modoAdmissão === $modo ? 'selected' : '' ?>>
                        <?= e($modo) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>UTI</label>
            <select name="uti">
                <option value="">Todos</option>
                <option value="s" <?= $uti === 's' ? 'selected' : '' ?>>Sim</option>
                <option value="n" <?= $uti === 'n' ? 'selected' : '' ?>>Não</option>
            </select>
        </div>
        <div class="bi-filter">
            <label>Data Internação</label>
            <input type="date" name="data_ini" id="data_ini" value="<?= e($dataIni) ?>">
        </div>
        <div class="bi-filter">
            <label>Data Final</label>
            <input type="date" name="data_fim" id="data_fim" value="<?= e($dataFim) ?>">
        </div>
        <div class="bi-filter">
            <label>Intervalo (meses)</label>
            <select name="range_meses" id="range_meses">
                <?php foreach ([3, 6, 12, 18, 24] as $opt): ?>
                    <option value="<?= $opt ?>" <?= $rangeMeses === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="bi-panel" style="margin-top:16px;">
        <h3>Internações</h3>
        <div class="bi-chart"><canvas id="chartIntern"></canvas></div>
    </div>
    <div class="bi-panel">
        <h3>Total de diárias</h3>
        <div class="bi-chart"><canvas id="chartDiárias"></canvas></div>
    </div>
    <div class="bi-panel">
        <h3>MP</h3>
        <div class="bi-chart"><canvas id="chartMp"></canvas></div>
    </div>
    <div class="bi-panel">
        <h3>Valor final</h3>
        <div class="bi-chart"><canvas id="chartCusto"></canvas></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const rangeSelect = document.getElementById('range_meses');
    const usarRange = document.getElementById('usar_range');
    const dataIni = document.getElementById('data_ini');
    const dataFim = document.getElementById('data_fim');

    if (rangeSelect && usarRange) {
        rangeSelect.addEventListener('change', () => {
            usarRange.value = '1';
            if (rangeSelect.form) {
                rangeSelect.form.submit();
            }
        });
    }
    if (dataIni && dataFim && usarRange) {
        [dataIni, dataFim].forEach((el) => {
            el.addEventListener('change', () => {
                usarRange.value = '0';
            });
        });
    }
});

const labels = <?= json_encode($monthLabels) ?>;
const series = <?= json_encode([
    'internacoes' => array_values($series['internacoes']),
    'diarias' => array_values($series['diarias']),
    'mp' => array_values($series['mp']),
    'valor_final' => array_values($series['valor_final']),
]) ?>;

function lineChart(ctx, key, yLabel, color, yTickCallback) {
    const scales = window.biChartScales ? window.biChartScales() : {};
    if (!scales.xAxes) {
        scales.xAxes = [{ ticks: { fontColor: '#e8f1ff' }, gridLines: { display: false } }];
    }
    if (!scales.yAxes) {
        scales.yAxes = [{
            ticks: { fontColor: '#e8f1ff' },
            gridLines: { color: 'rgba(255,255,255,0.1)' }
        }];
    }
    if (scales.yAxes[0]) {
        scales.yAxes[0].ticks = scales.yAxes[0].ticks || {};
        scales.yAxes[0].ticks.fontColor = '#e8f1ff';
        if (yTickCallback) {
            scales.yAxes[0].ticks.callback = yTickCallback;
        }
        if (yLabel) {
            scales.yAxes[0].scaleLabel = {
                display: true,
                labelString: yLabel,
                fontColor: '#e8f1ff'
            };
        }
    }
    scales.x = scales.x || { ticks: { color: '#e8f1ff' }, grid: { display: false } };
    scales.y = scales.y || { ticks: { color: '#e8f1ff' }, grid: { color: 'rgba(255,255,255,0.1)' } };
    if (scales.y.ticks) {
        scales.y.ticks.color = '#e8f1ff';
        if (yTickCallback) {
            scales.y.ticks.callback = yTickCallback;
        }
    }
    if (yLabel) {
        scales.y.title = { display: true, text: yLabel, color: '#e8f1ff' };
    }
    return new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: yLabel,
                data: series[key],
                borderColor: color,
                backgroundColor: color.replace('1)', '0.18)'),
                fill: true,
                borderWidth: 3,
                tension: 0.45,
                cubicInterpolationMode: 'monotone',
                pointRadius: 3,
                pointHoverRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: { display: false },
            plugins: { legend: { display: false } },
            scales
        }
    });
}

lineChart(document.getElementById('chartIntern'), 'internacoes', 'Internações', 'rgba(121, 199, 255, 1)');
lineChart(document.getElementById('chartDiárias'), 'diarias', 'Diárias', 'rgba(255, 198, 108, 1)');
lineChart(document.getElementById('chartMp'), 'mp', 'MP', 'rgba(111, 223, 194, 1)');
lineChart(document.getElementById('chartCusto'), 'valor_final', 'Valor final (R$)', 'rgba(141, 208, 255, 1)', window.biMoneyTick);
</script>

<?php require_once("templates/footer.php"); ?>
