<?php
include_once("check_logado.php");

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $query = $_GET;
    $changed = false;

    if (array_key_exists('data_ini', $query)) {
        unset($query['data_ini']);
        $changed = true;
    }
    if (array_key_exists('ie', $query)) {
        unset($query['ie']);
        $changed = true;
    }

    $currentPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
    if (preg_match('#/ControleGastosHospitalBI\.php$#i', $currentPath)) {
        $basePath = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
        $currentPath = ($basePath === '' || $basePath === '.') ? '/bi/gastos-hospital' : $basePath . '/bi/gastos-hospital';
        $changed = true;
    }

    if ($changed) {
        $qs = http_build_query($query);
        header('Location: ' . $currentPath . ($qs !== '' ? '?' . $qs : ''), true, 302);
        exit;
    }
}

require_once("templates/header.php");

if (!isset($conn) || !($conn instanceof PDO)) {
    die("Conexao invalida.");
}

require_once __DIR__ . '/app/bi_cid_options.php';

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$anoInput = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
$mesInput = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT);
$ano = ($anoInput !== null && $anoInput !== false) ? (int)$anoInput : null;
$mes = ($mesInput !== null && $mesInput !== false) ? (int)$mesInput : 0;
$startInput = (string)(filter_input(INPUT_GET, 'data_inicio') ?: filter_input(INPUT_GET, 'data_ini') ?: '');
$endInput = (string)(filter_input(INPUT_GET, 'data_fim') ?: '');
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$seguradoraId = filter_input(INPUT_GET, 'seguradora_id', FILTER_VALIDATE_INT) ?: null;
$patologiaId = filter_input(INPUT_GET, 'patologia_id', FILTER_VALIDATE_INT) ?: null;

$anos = $conn->query("SELECT DISTINCT YEAR(data_intern_int) AS ano FROM tb_internacao WHERE data_intern_int IS NOT NULL AND data_intern_int <> '0000-00-00' ORDER BY ano DESC")
    ->fetchAll(PDO::FETCH_COLUMN);

if ($ano === null && !filter_has_var(INPUT_GET, 'ano')) {
    $ano = !empty($anos) ? (int)$anos[0] : (int)date('Y');
}

$dateExpr = "COALESCE(NULLIF(ca.data_inicial_capeante,'0000-00-00'), NULLIF(ca.data_digit_capeante,'0000-00-00'), NULLIF(ca.data_fech_capeante,'0000-00-00'))";
$whereParts = [
    "ref_date IS NOT NULL",
    "ref_date <> '0000-00-00'",
];
$params = [];

$startDate = DateTime::createFromFormat('Y-m-d', $startInput) ?: null;
$endDate = DateTime::createFromFormat('Y-m-d', $endInput) ?: null;
$hasRange = ($startDate instanceof DateTime) && ($endDate instanceof DateTime);
if ($hasRange) {
    if ($startDate > $endDate) {
        [$startDate, $endDate] = [$endDate, $startDate];
    }
    $rangeStart = $startDate->format('Y-m-d');
    $rangeEnd = $endDate->format('Y-m-d');
    $whereParts[] = "ref_date BETWEEN :data_inicio AND :data_fim";
    $params[':data_inicio'] = $rangeStart;
    $params[':data_fim'] = $rangeEnd;
} else {
    $whereParts[] = "YEAR(ref_date) = :ano";
    $params[':ano'] = (int)$ano;
    if (!empty($mes)) {
        $whereParts[] = "MONTH(ref_date) = :mes";
        $params[':mes'] = (int)$mes;
    }
}

$filterScope = [
    'ano' => $hasRange ? null : $ano,
    'mes' => $hasRange ? null : $mes,
    'data_inicio' => $hasRange ? $rangeStart : null,
    'data_fim' => $hasRange ? $rangeEnd : null,
    'hospital_id' => $hospitalId,
    'seguradora_id' => $seguradoraId,
];
$hospitais = array_map(fn($r) => ['id_hospital' => $r['value'], 'nome_hosp' => $r['label']], bi_fetch_filter_options($conn, 'hospital', $filterScope, [
    'date_expr' => $dateExpr,
]));
$seguradoras = array_map(fn($r) => ['id_seguradora' => $r['value'], 'seguradora_seg' => $r['label']], bi_fetch_filter_options($conn, 'seguradora', $filterScope, [
    'date_expr' => $dateExpr,
]));
$patologias = bi_fetch_cid_options($conn, $filterScope, [
    'date_expr' => $dateExpr,
    'join_capeante' => true,
]);
if (!empty($hospitalId)) {
    $whereParts[] = "fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = (int)$hospitalId;
}
if (!empty($seguradoraId)) {
    $whereParts[] = "fk_seguradora_pac = :seguradora_id";
    $params[':seguradora_id'] = (int)$seguradoraId;
}
if (!empty($patologiaId)) {
    $whereParts[] = "fk_cid_int = :patologia_id";
    $params[':patologia_id'] = (int)$patologiaId;
}
$where = implode(" AND ", $whereParts);

$baseSql = "
    SELECT
        ca.valor_final_capeante,
        {$dateExpr} AS ref_date,
        ac.fk_hospital_int,
        ac.fk_cid_int,
        ac.fk_patologia_int,
        pa.fk_seguradora_pac
    FROM tb_capeante ca
    INNER JOIN tb_internacao ac ON ac.id_internacao = ca.fk_int_capeante
    LEFT JOIN tb_paciente pa ON pa.id_paciente = ac.fk_paciente_int
";

$sqlHosp = "
    SELECT
        h.id_hospital AS hospital_id,
        h.nome_hosp AS hospital,
        COUNT(*) AS casos,
        SUM(valor_final_capeante) AS valor_final
    FROM ({$baseSql}) t
    LEFT JOIN tb_hospital h ON h.id_hospital = t.fk_hospital_int
    WHERE {$where}
    GROUP BY hospital
    ORDER BY valor_final DESC
";
$stmt = $conn->prepare($sqlHosp);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$rowsHosp = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sqlCombo = "
    SELECT
        h.nome_hosp AS hospital,
        COALESCE(NULLIF(CONCAT_WS(' - ', NULLIF(c.cat, ''), NULLIF(c.descricao, '')), ''), 'Sem CID') AS patologia,
        COUNT(*) AS casos,
        SUM(valor_final_capeante) AS valor_final
    FROM ({$baseSql}) t
    LEFT JOIN tb_hospital h ON h.id_hospital = t.fk_hospital_int
    LEFT JOIN tb_cid c ON c.id_cid = t.fk_cid_int
    WHERE {$where}
    GROUP BY hospital, patologia
    ORDER BY valor_final DESC
    LIMIT 80
";
$stmt = $conn->prepare($sqlCombo);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$rowsCombo = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$topRows = array_slice($rowsHosp, 0, 10);
$labels = array_map(fn($r) => $r['hospital'], $topRows);
$values = array_map(fn($r) => round((float)($r['valor_final'] ?? 0), 2), $topRows);

$totalFinal = 0.0;
$totalCasos = 0;
foreach ($rowsHosp as $row) {
    $totalFinal += (float)($row['valor_final'] ?? 0);
    $totalCasos += (int)($row['casos'] ?? 0);
}
$media = $totalCasos > 0 ? $totalFinal / $totalCasos : 0;

$monthNames = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
$monthKeys = [];
$monthLabels = [];
if ($hasRange) {
    $cursor = (clone $startDate)->modify('first day of this month');
    $limitEnd = (clone $endDate)->modify('first day of next month');
    while ($cursor < $limitEnd) {
        $key = $cursor->format('Y-m');
        $monthKeys[] = $key;
        $monthLabels[] = ($monthNames[(int)$cursor->format('m') - 1] ?? $cursor->format('m')) . '/' . $cursor->format('Y');
        $cursor->modify('+1 month');
    }
} else {
    for ($m = 1; $m <= 12; $m++) {
        $monthKeys[] = sprintf('%04d-%02d', (int)$ano, $m);
        $monthLabels[] = $monthNames[$m - 1] . '/' . $ano;
    }
}

$topHosp = array_slice($rowsHosp, 0, 5);
$topHospIds = [];
$topHospNames = [];
foreach ($topHosp as $row) {
    $hid = (int)($row['hospital_id'] ?? 0);
    if ($hid > 0) {
        $topHospIds[] = $hid;
        $topHospNames[$hid] = (string)($row['hospital'] ?? ('Hospital ' . $hid));
    }
}

$monthlyDatasets = [];
if ($topHospIds && $monthKeys) {
    $seriesMap = [];
    foreach ($topHospIds as $hid) {
        $seriesMap[$hid] = array_fill_keys($monthKeys, 0.0);
    }

    $whereMonthly = $where;
    $inParams = [];
    $inTokens = [];
    foreach ($topHospIds as $idx => $hid) {
        $ph = ':h' . $idx;
        $inTokens[] = $ph;
        $inParams[$ph] = (int)$hid;
    }
    $in = implode(',', $inTokens);
    $sqlMonthly = "
        SELECT
            fk_hospital_int AS hospital_id,
            DATE_FORMAT(ref_date, '%Y-%m') AS ym,
            SUM(valor_final_capeante) AS valor_final
        FROM ({$baseSql}) t
        WHERE {$whereMonthly}
          AND fk_hospital_int IN ({$in})
        GROUP BY hospital_id, ym
        ORDER BY ym
    ";
    $stmt = $conn->prepare($sqlMonthly);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    foreach ($inParams as $ph => $hid) {
        $stmt->bindValue($ph, $hid, PDO::PARAM_INT);
    }
    $stmt->execute();
    $rowsMonthly = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rowsMonthly as $row) {
        $hid = (int)($row['hospital_id'] ?? 0);
        $ym = (string)($row['ym'] ?? '');
        if (isset($seriesMap[$hid][$ym])) {
            $seriesMap[$hid][$ym] = (float)($row['valor_final'] ?? 0);
        }
    }

    $palette = [
        'rgba(126,150,255,0.92)',
        'rgba(92,205,173,0.92)',
        'rgba(245,168,88,0.92)',
        'rgba(202,126,255,0.92)',
        'rgba(255,127,154,0.92)',
    ];
    $colorIdx = 0;
    foreach ($topHospIds as $hid) {
        $monthlyDatasets[] = [
            'label' => $topHospNames[$hid] ?? ('Hospital ' . $hid),
            'data' => array_values($seriesMap[$hid]),
            'borderColor' => $palette[$colorIdx % count($palette)],
            'backgroundColor' => 'rgba(0,0,0,0)',
            'borderWidth' => 2,
            'pointRadius' => 2,
            'tension' => 0.35,
            'fill' => false,
        ];
        $colorIdx++;
    }
}
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260516-rounded-bars"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));
</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Sinistralidade por Hospital</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted">
                <?php if ($hasRange): ?>
                    <?= e($rangeStart) ?> a <?= e($rangeEnd) ?>
                <?php else: ?>
                    Ano <?= e($ano) ?><?= $mes ? ' • Mês ' . e($mes) : '' ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <form class="bi-panel bi-filters" method="get">
        <div class="bi-filter">
            <label>Data início</label>
            <input type="date" name="data_inicio" value="<?= e($hasRange ? $rangeStart : '') ?>">
        </div>
        <div class="bi-filter">
            <label>Data fim</label>
            <input type="date" name="data_fim" value="<?= e($hasRange ? $rangeEnd : '') ?>">
        </div>
        <div class="bi-filter">
            <label>Ano</label>
            <select name="ano">
                <?php foreach ($anos as $anoOpt): ?>
                    <option value="<?= (int)$anoOpt ?>" <?= (int)$anoOpt === (int)$ano ? 'selected' : '' ?>>
                        <?= (int)$anoOpt ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Mês</label>
            <select name="mes">
                <option value="">Todos</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= (int)$mes === $m ? 'selected' : '' ?>><?= $m ?></option>
                <?php endfor; ?>
            </select>
        </div>
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
            <label>CID</label>
            <select name="patologia_id">
                <option value="">Todas</option>
                <?php foreach ($patologias as $p): ?>
                    <option value="<?= (int)$p['id_patologia'] ?>" <?= $patologiaId == $p['id_patologia'] ? 'selected' : '' ?>>
                        <?= e($p['patologia_pat']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Seguradora</label>
            <select name="seguradora_id">
                <option value="">Todas</option>
                <?php foreach ($seguradoras as $s): ?>
                    <option value="<?= (int)$s['id_seguradora'] ?>" <?= $seguradoraId == $s['id_seguradora'] ? 'selected' : '' ?>>
                        <?= e($s['seguradora_seg']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="bi-kpis kpi-compact kpi-tight kpi-slim">
        <div class="bi-kpi kpi-indigo kpi-compact">
            <small>Custo final</small>
            <strong>R$ <?= number_format($totalFinal, 2, ',', '.') ?></strong>
        </div>
        <div class="bi-kpi kpi-teal kpi-compact">
            <small>Casos</small>
            <strong><?= number_format($totalCasos, 0, ',', '.') ?></strong>
        </div>
        <div class="bi-kpi kpi-amber kpi-compact">
            <small>Custo médio</small>
            <strong>R$ <?= number_format($media, 2, ',', '.') ?></strong>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Top hospitais (custo final)</h3>
        <div class="bi-chart ie-chart-sm"><canvas id="chartHospitais"></canvas></div>
    </div>
    <div class="bi-panel">
        <h3>Evolução mensal de custo por hospital (Top 5)</h3>
        <div class="bi-chart ie-chart-sm"><canvas id="chartMensalHospitais"></canvas></div>
    </div>

    <div class="bi-panel">
        <h3>Hospital x Patologia</h3>
        <div class="table-responsive">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th>Hospital</th>
                        <th>Patologia</th>
                        <th>Casos</th>
                        <th>Custo final</th>
                        <th>Custo médio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rowsCombo): ?>
                        <tr>
                            <td colspan="5">Sem informações</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rowsCombo as $row): ?>
                            <?php
                            $casos = (int)($row['casos'] ?? 0);
                            $final = (float)($row['valor_final'] ?? 0);
                            $media = $casos > 0 ? $final / $casos : 0;
                            ?>
                            <tr>
                                <td><?= e($row['hospital'] ?? 'Sem hospital') ?></td>
                                <td><?= e($row['patologia'] ?? 'Sem patologia') ?></td>
                                <td><?= number_format($casos, 0, ',', '.') ?></td>
                                <td>R$ <?= number_format($final, 2, ',', '.') ?></td>
                                <td>R$ <?= number_format($media, 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const hospLabels = <?= json_encode($labels) ?>;
    const hospValues = <?= json_encode($values) ?>;
    const mensalLabels = <?= json_encode($monthLabels) ?>;
    const mensalDatasets = <?= json_encode($monthlyDatasets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    new Chart(document.getElementById('chartHospitais'), {
        type: 'horizontalBar',
        data: {
            labels: hospLabels,
            datasets: [{
                label: 'Custo final',
                data: hospValues,
                backgroundColor: 'rgba(126,150,255,0.8)',
                borderRadius: 10,
                maxBarThickness: 48
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: {
                display: false
            },
            scales: (function () {
                const s = biChartScales();
                if (s && s.xAxes && s.xAxes[0] && s.xAxes[0].ticks) {
                    s.xAxes[0].ticks.callback = function (v) { return biMoneyTick(v); };
                }
                return s;
            })(),
            tooltips: {
                callbacks: {
                    label: (item) => biMoneyTick(item.xLabel)
                }
            }
        }
    });

    if (mensalDatasets.length) {
        const scales = biChartScales();
        if (scales && scales.yAxes && scales.yAxes[0] && scales.yAxes[0].ticks) {
            scales.yAxes[0].ticks.callback = function (v) { return biMoneyTick(v); };
        }
        new Chart(document.getElementById('chartMensalHospitais'), {
            type: 'line',
            data: { labels: mensalLabels, datasets: mensalDatasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: scales,
                tooltips: {
                    callbacks: {
                        label: function (item, data) {
                            const ds = data.datasets[item.datasetIndex] || {};
                            return (ds.label ? ds.label + ': ' : '') + biMoneyTick(item.yLabel);
                        }
                    }
                }
            }
        });
    }
</script>

<?php require_once("templates/footer.php"); ?>
