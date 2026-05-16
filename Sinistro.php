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
$rangeMeses = ($rangeMesesInput !== null && $rangeMesesInput !== false && $rangeMesesInput > 0) ? (int)$rangeMesesInput : 12;
$usarRange = (filter_input(INPUT_GET, 'usar_range') === '1');

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

$monthLabels = [];
$monthKeys = [];
$monthNames = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
$cursor = clone $start;
while ($cursor <= $end) {
    $ym = $cursor->format('Y-m');
    $monthKeys[] = $ym;
    $monthLabels[] = $monthNames[(int)$cursor->format('n') - 1] . '/' . $cursor->format('Y');
    $cursor->modify('+1 month');
}

$series = [
    'valor_apresentado' => array_fill_keys($monthKeys, 0.0),
    'valor_glosa' => array_fill_keys($monthKeys, 0.0),
    'valor_final' => array_fill_keys($monthKeys, 0.0),
    'internacoes' => array_fill_keys($monthKeys, 0.0),
];

$dateExpr = "COALESCE(NULLIF(ca.data_inicial_capeante,'0000-00-00'), NULLIF(ca.data_digit_capeante,'0000-00-00'), NULLIF(ca.data_fech_capeante,'0000-00-00'))";
$whereFin = "ref_date BETWEEN :data_ini AND :data_fim";
$paramsFin = [
    ':data_ini' => $dataIni,
    ':data_fim' => $dataFim,
];
if ($hospitalId) {
    $whereFin .= " AND ac.fk_hospital_int = :hospital_id";
    $paramsFin[':hospital_id'] = $hospitalId;
}
if ($tipoInternação !== '') {
    $whereFin .= " AND ac.tipo_admissao_int = :tipo";
    $paramsFin[':tipo'] = $tipoInternação;
}
if ($modoAdmissão !== '') {
    $whereFin .= " AND ac.modo_internacao_int = :modo";
    $paramsFin[':modo'] = $modoAdmissão;
}
if ($internado !== '') {
    $whereFin .= " AND ac.internado_int = :internado";
    $paramsFin[':internado'] = $internado;
}
if ($uti === 's') {
    $whereFin .= " AND ut.fk_internacao_uti IS NOT NULL";
}
if ($uti === 'n') {
    $whereFin .= " AND ut.fk_internacao_uti IS NULL";
}

$sqlFin = "
    SELECT
        DATE_FORMAT(ref_date, '%Y-%m') AS ym,
        SUM(valor_apresentado_capeante) AS valor_apresentado,
        SUM(valor_glosa_total) AS valor_glosa,
        SUM(valor_final_capeante) AS valor_final
    FROM (
        SELECT
            ca.valor_apresentado_capeante,
            ca.valor_glosa_total,
            ca.valor_final_capeante,
            {$dateExpr} AS ref_date,
            ac.fk_hospital_int,
            ac.tipo_admissao_int,
            ac.modo_internacao_int,
            ac.internado_int
        FROM tb_capeante ca
        INNER JOIN tb_internacao ac ON ac.id_internacao = ca.fk_int_capeante
        LEFT JOIN (SELECT DISTINCT fk_internacao_uti FROM tb_uti) ut ON ut.fk_internacao_uti = ac.id_internacao
    ) t
    WHERE ref_date IS NOT NULL AND ref_date <> '0000-00-00'
      AND {$whereFin}
    GROUP BY ym
";
$stmt = $conn->prepare($sqlFin);
foreach ($paramsFin as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$rowsFin = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
foreach ($rowsFin as $row) {
    $ym = $row['ym'] ?? '';
    if (!isset($series['valor_apresentado'][$ym])) {
        continue;
    }
    $series['valor_apresentado'][$ym] = (float)($row['valor_apresentado'] ?? 0);
    $series['valor_glosa'][$ym] = (float)($row['valor_glosa'] ?? 0);
    $series['valor_final'][$ym] = (float)($row['valor_final'] ?? 0);
}

$sqlIntern = "
    SELECT DATE_FORMAT(i.data_intern_int, '%Y-%m') AS ym, COUNT(DISTINCT i.id_internacao) AS total
    FROM tb_internacao i
    {$utiJoin}
    WHERE {$where}
    GROUP BY ym
";
$stmt = $conn->prepare($sqlIntern);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$rowsIntern = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
foreach ($rowsIntern as $row) {
    $ym = $row['ym'] ?? '';
    if (!isset($series['internacoes'][$ym])) {
        continue;
    }
    $series['internacoes'][$ym] = (float)($row['total'] ?? 0);
}
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260509-filter-icons"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>
<style>
    .bi-sinistro-main-chart {
        min-height: 160px !important;
        height: 160px !important;
        max-height: 160px !important;
    }

    .bi-sinistro-main-chart canvas {
        height: 160px !important;
        max-height: 160px !important;
    }
</style>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Dashboard Sinistro</h1>
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
        <h3>Valor apresentado</h3>
        <div class="bi-chart bi-sinistro-main-chart" style="height:160px;max-height:160px;"><canvas id="chartApresentado" height="160" style="height:160px !important;"></canvas></div>
    </div>
    <div class="bi-panel">
        <h3>Glosa total</h3>
        <div class="bi-chart bi-sinistro-main-chart" style="height:160px;max-height:160px;"><canvas id="chartGlosa" height="160" style="height:160px !important;"></canvas></div>
    </div>
    <div class="bi-panel">
        <h3>Valor final</h3>
        <div class="bi-chart bi-sinistro-main-chart" style="height:160px;max-height:160px;"><canvas id="chartFinal" height="160" style="height:160px !important;"></canvas></div>
    </div>
    <div class="bi-panel">
        <h3>Internações</h3>
        <div class="bi-chart bi-sinistro-main-chart" style="height:160px;max-height:160px;"><canvas id="chartIntern" height="160" style="height:160px !important;"></canvas></div>
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
    'valor_apresentado' => array_values($series['valor_apresentado']),
    'valor_glosa' => array_values($series['valor_glosa']),
    'valor_final' => array_values($series['valor_final']),
    'internacoes' => array_values($series['internacoes']),
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

lineChart(document.getElementById('chartApresentado'), 'valor_apresentado', 'Valor (R$)', 'rgba(141, 208, 255, 1)', window.biMoneyTick);
lineChart(document.getElementById('chartGlosa'), 'valor_glosa', 'Valor (R$)', 'rgba(208, 113, 176, 1)', window.biMoneyTick);
lineChart(document.getElementById('chartFinal'), 'valor_final', 'Valor (R$)', 'rgba(111, 223, 194, 1)', window.biMoneyTick);
lineChart(document.getElementById('chartIntern'), 'internacoes', 'Quantidade', 'rgba(255, 198, 108, 1)');
</script>

<?php require_once("templates/footer.php"); ?>
