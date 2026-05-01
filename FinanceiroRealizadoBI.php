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

$params = [':start' => $startStr, ':end' => $endStr];
$whereHosp = '';
if ($hospitalId) {
    $whereHosp = " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = (int)$hospitalId;
}

$dateExpr = "COALESCE(NULLIF(ca.data_final_capeante,'0000-00-00'), NULLIF(ca.data_fech_capeante,'0000-00-00'), NULLIF(ca.data_create_cap,'0000-00-00'))";
$auditedExpr = "(
    (ca.fk_id_aud_med IS NOT NULL AND ca.fk_id_aud_med > 0)
    OR LOWER(COALESCE(ca.med_check,'')) = 's'
    OR LOWER(COALESCE(ca.aud_med_capeante,'')) = 's'
)";

// Apresentado x pos-auditoria (mensal)
$sqlValores = "
    SELECT DATE_FORMAT(ref_date, '%Y-%m') AS ym,
           SUM(COALESCE(valor_apresentado_capeante,0)) AS valor_apresentado,
           SUM(COALESCE(valor_final_capeante,0)) AS valor_final
    FROM (
        SELECT ca.valor_apresentado_capeante,
               ca.valor_final_capeante,
               {$dateExpr} AS ref_date,
               i.fk_hospital_int
        FROM tb_capeante ca
        JOIN tb_internacao i ON i.id_internacao = ca.fk_int_capeante
    ) t
    WHERE ref_date IS NOT NULL AND ref_date <> '0000-00-00'
      AND ref_date BETWEEN :start AND :end
      {$whereHosp}
    GROUP BY ym
    ORDER BY ym ASC
";
$stmt = $conn->prepare($sqlValores);
$stmt->execute($params);
$valorRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$apresentado = array_fill_keys($monthKeys, 0.0);
$final = array_fill_keys($monthKeys, 0.0);
foreach ($valorRows as $row) {
    $ym = $row['ym'];
    if (!isset($apresentado[$ym])) continue;
    $apresentado[$ym] = (float)($row['valor_apresentado'] ?? 0);
    $final[$ym] = (float)($row['valor_final'] ?? 0);
}

// Evolução mensal de contas auditadas
$sqlContasAuditadas = "
    SELECT DATE_FORMAT({$dateExpr}, '%Y-%m') AS ym,
           COUNT(*) AS total_contas,
           SUM(CASE WHEN {$auditedExpr} THEN 1 ELSE 0 END) AS contas_auditadas
    FROM tb_capeante ca
    JOIN tb_internacao i ON i.id_internacao = ca.fk_int_capeante
    WHERE {$dateExpr} IS NOT NULL AND {$dateExpr} <> '0000-00-00'
      AND {$dateExpr} BETWEEN :start AND :end
      {$whereHosp}
    GROUP BY ym
    ORDER BY ym ASC
";
$stmt = $conn->prepare($sqlContasAuditadas);
$stmt->execute($params);
$contasAuditRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$contasTotaisMes = array_fill_keys($monthKeys, 0);
$contasAuditadasMes = array_fill_keys($monthKeys, 0);
foreach ($contasAuditRows as $row) {
    $ym = $row['ym'] ?? '';
    if (!isset($contasTotaisMes[$ym])) {
        continue;
    }
    $contasTotaisMes[$ym] = (int)($row['total_contas'] ?? 0);
    $contasAuditadasMes[$ym] = (int)($row['contas_auditadas'] ?? 0);
}

$totalContasPeriodo = array_sum($contasTotaisMes);
$totalContasAuditadasPeriodo = array_sum($contasAuditadasMes);
$taxaAuditadaPeriodo = $totalContasPeriodo > 0 ? (($totalContasAuditadasPeriodo / $totalContasPeriodo) * 100) : 0;

// Top seguradoras por valor apresentado
$sqlTopSeg = "
    SELECT s.id_seguradora, COALESCE(s.seguradora_seg, 'Sem informacoes') AS nome,
           SUM(COALESCE(ca.valor_apresentado_capeante,0)) AS total
    FROM tb_capeante ca
    JOIN tb_internacao i ON i.id_internacao = ca.fk_int_capeante
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_seguradora s ON s.id_seguradora = pa.fk_seguradora_pac
    WHERE {$dateExpr} IS NOT NULL AND {$dateExpr} <> '0000-00-00'
      AND {$dateExpr} BETWEEN :start AND :end
      {$whereHosp}
    GROUP BY s.id_seguradora
    ORDER BY total DESC
    LIMIT 5
";
$stmt = $conn->prepare($sqlTopSeg);
$stmt->execute($params);
$topSegRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$topSegIds = array_filter(array_map(fn($r) => $r['id_seguradora'], $topSegRows));
$segSeries = [];
foreach ($topSegRows as $row) {
    $segSeries[$row['id_seguradora']] = [
        'nome' => $row['nome'],
        'data' => array_fill_keys($monthKeys, 0.0)
    ];
}

if ($topSegIds) {
    $inPlaceholders = implode(',', array_fill(0, count($topSegIds), '?'));
    $sqlSegSeries = "
        SELECT s.id_seguradora, DATE_FORMAT(ref_date, '%Y-%m') AS ym,
               SUM(COALESCE(valor_apresentado_capeante,0)) AS total
        FROM (
            SELECT ca.valor_apresentado_capeante,
                   {$dateExpr} AS ref_date,
                   i.fk_hospital_int,
                   pa.fk_seguradora_pac
            FROM tb_capeante ca
            JOIN tb_internacao i ON i.id_internacao = ca.fk_int_capeante
            LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
        ) t
        LEFT JOIN tb_seguradora s ON s.id_seguradora = t.fk_seguradora_pac
        WHERE ref_date IS NOT NULL AND ref_date <> '0000-00-00'
          AND ref_date BETWEEN ? AND ?
          " . ($hospitalId ? " AND fk_hospital_int = ? " : "") . "
          AND s.id_seguradora IN ({$inPlaceholders})
        GROUP BY s.id_seguradora, ym
        ORDER BY ym ASC
    ";
    $bind = [$startStr, $endStr];
    if ($hospitalId) {
        $bind[] = (int)$hospitalId;
    }
    $bind = array_merge($bind, $topSegIds);
    $stmt = $conn->prepare($sqlSegSeries);
    $stmt->execute($bind);
    $segRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($segRows as $row) {
        $id = $row['id_seguradora'];
        $ym = $row['ym'];
        if (!isset($segSeries[$id]['data'][$ym])) continue;
        $segSeries[$id]['data'][$ym] = (float)($row['total'] ?? 0);
    }
}

// Top 10 hospitais por valor apresentado
$sqlTopHospitais = "
    SELECT COALESCE(ho.nome_hosp, 'Sem hospital') AS hospital,
           SUM(COALESCE(ca.valor_apresentado_capeante,0)) AS valor
    FROM tb_capeante ca
    JOIN tb_internacao i ON i.id_internacao = ca.fk_int_capeante
    LEFT JOIN tb_hospital ho ON ho.id_hospital = i.fk_hospital_int
    WHERE {$dateExpr} IS NOT NULL AND {$dateExpr} <> '0000-00-00'
      AND {$dateExpr} BETWEEN :start AND :end
      {$whereHosp}
    GROUP BY ho.id_hospital, ho.nome_hosp
    ORDER BY valor DESC
    LIMIT 10
";
$stmt = $conn->prepare($sqlTopHospitais);
$stmt->execute($params);
$topHospitais = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$topLabels = array_map(fn($r) => (string)($r['hospital'] ?? 'Sem hospital'), $topHospitais);
$topValues = array_map(fn($r) => (float)($r['valor'] ?? 0), $topHospitais);
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme bi-ie-page">
    <div class="bi-header">
        <h1 class="bi-title">Contas Auditadas por Hospital</h1>
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

    <div class="bi-kpis kpi-dashboard-v2" style="margin-top:16px;">
        <div class="bi-kpi kpi-card-v2 kpi-card-v2-1">
            <div class="kpi-card-v2-head">
                <span class="kpi-card-v2-icon"><i class="bi bi-journal-check"></i></span>
                <small>Total de contas</small>
            </div>
            <strong><?= number_format($totalContasPeriodo, 0, ',', '.') ?></strong>
            <span class="kpi-trend kpi-trend-up"><i class="bi bi-arrow-up-right"></i>Período filtrado</span>
        </div>
        <div class="bi-kpi kpi-card-v2 kpi-card-v2-2">
            <div class="kpi-card-v2-head">
                <span class="kpi-card-v2-icon"><i class="bi bi-clipboard2-pulse"></i></span>
                <small>Contas auditadas</small>
            </div>
            <strong><?= number_format($totalContasAuditadasPeriodo, 0, ',', '.') ?></strong>
            <span class="kpi-trend kpi-trend-up"><i class="bi bi-check2-circle"></i>Volume auditado</span>
        </div>
        <div class="bi-kpi kpi-card-v2 kpi-card-v2-3">
            <div class="kpi-card-v2-head">
                <span class="kpi-card-v2-icon"><i class="bi bi-percent"></i></span>
                <small>Taxa auditada</small>
            </div>
            <strong><?= number_format($taxaAuditadaPeriodo, 1, ',', '.') ?>%</strong>
            <span class="kpi-trend kpi-trend-neutral"><i class="bi bi-bar-chart-line"></i>Cobertura do período</span>
        </div>
    </div>

    <div class="bi-grid fixed-2" style="margin-top:16px;">
        <div class="bi-panel">
            <h3 class="text-center" style="margin-bottom:12px;">Apresentado x Pós-auditoria</h3>
            <div class="bi-chart ie-chart-md"><canvas id="chartValores"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3 class="text-center" style="margin-bottom:12px;">Variação por seguradora (apresentado)</h3>
            <div class="bi-chart ie-chart-md"><canvas id="chartSeguradora"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3 class="text-center" style="margin-bottom:12px;">Evolução mensal de contas auditadas</h3>
            <div class="bi-chart ie-chart-md"><canvas id="chartContasAuditadas"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3 class="text-center" style="margin-bottom:12px;">Top 10 hospitais por valor apresentado</h3>
            <div class="bi-chart ie-chart-md"><canvas id="chartTopContas"></canvas></div>
        </div>
    </div>
</div>

<script>
const chartLabels = <?= json_encode($monthLabels) ?>;
const valorApresentado = <?= json_encode(array_values($apresentado)) ?>;
const valorFinal = <?= json_encode(array_values($final)) ?>;
const contasTotaisMes = <?= json_encode(array_values($contasTotaisMes)) ?>;
const contasAuditadasMes = <?= json_encode(array_values($contasAuditadasMes)) ?>;
const segSeries = <?= json_encode($segSeries) ?>;
const topLabels = <?= json_encode($topLabels) ?>;
const topValues = <?= json_encode($topValues) ?>;

function moneyFmt(v) {
    if (window.biMoneyTick) return window.biMoneyTick(v);
    return 'R$ ' + Number(v || 0).toLocaleString('pt-BR');
}

function groupedBar(ctx, labels, dataA, dataB) {
    const scales = window.biChartScales ? window.biChartScales() : undefined;
    if (scales && scales.yAxes && scales.yAxes[0] && scales.yAxes[0].ticks) {
        scales.yAxes[0].ticks.callback = function(v) { return moneyFmt(v); };
    }
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { label: 'Apresentado', data: dataA, backgroundColor: 'rgba(127, 196, 255, 0.7)' },
                { label: 'Pós-auditoria', data: dataB, backgroundColor: 'rgba(208, 113, 176, 0.7)' }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: window.biLegendWhite ? window.biLegendWhite : undefined,
            scales: scales,
            tooltips: {
                callbacks: {
                    label: function(item, data) {
                        const ds = data.datasets[item.datasetIndex] || {};
                        return (ds.label ? ds.label + ': ' : '') + moneyFmt(item.yLabel);
                    }
                }
            }
        }
    });
}

function multiLine(ctx, labels, series) {
    const palette = [
        'rgba(141, 208, 255, 0.9)',
        'rgba(255, 198, 108, 0.9)',
        'rgba(111, 223, 194, 0.9)',
        'rgba(255, 99, 132, 0.9)',
        'rgba(173, 131, 255, 0.9)'
    ];
    const datasets = Object.values(series).map((s, i) => ({
        label: s.nome,
        data: Object.values(s.data),
        borderColor: palette[i % palette.length],
        backgroundColor: palette[i % palette.length],
        fill: false,
        tension: 0.25
    }));
    const scales = window.biChartScales ? window.biChartScales() : undefined;
    if (scales && scales.yAxes && scales.yAxes[0] && scales.yAxes[0].ticks) {
        scales.yAxes[0].ticks.callback = function(v) { return moneyFmt(v); };
    }
    return new Chart(ctx, {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: window.biLegendWhite ? window.biLegendWhite : undefined,
            scales: scales,
            tooltips: {
                callbacks: {
                    label: function(item, data) {
                        const ds = data.datasets[item.datasetIndex] || {};
                        return (ds.label ? ds.label + ': ' : '') + moneyFmt(item.yLabel);
                    }
                }
            }
        }
    });
}

function horizontalBar(ctx, labels, data) {
    const scales = window.biChartScales ? window.biChartScales() : undefined;
    if (scales && scales.xAxes && scales.xAxes[0] && scales.xAxes[0].ticks) {
        scales.xAxes[0].ticks.callback = function(v) { return moneyFmt(v); };
    }
    return new Chart(ctx, {
        type: 'horizontalBar',
        data: {
            labels,
            datasets: [{
                label: 'Valor apresentado',
                data,
                backgroundColor: 'rgba(121, 199, 255, 0.7)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: window.biLegendWhite ? window.biLegendWhite : undefined,
            scales: scales,
            tooltips: {
                callbacks: {
                    label: function(item, data) {
                        const ds = data.datasets[item.datasetIndex] || {};
                        return (ds.label ? ds.label + ': ' : '') + moneyFmt(item.xLabel);
                    }
                }
            }
        }
    });
}

function mixedAuditChart(ctx, labels, totalData, auditedData) {
    const pctData = totalData.map((total, idx) => {
        const t = Number(total || 0);
        const a = Number(auditedData[idx] || 0);
        return t > 0 ? Math.round((a / t) * 1000) / 10 : 0;
    });

    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Contas auditadas',
                    data: auditedData,
                    backgroundColor: 'rgba(111, 223, 194, 0.78)',
                    yAxisID: 'y'
                },
                {
                    type: 'line',
                    label: 'Taxa auditada (%)',
                    data: pctData,
                    borderColor: 'rgba(255, 198, 108, 0.95)',
                    backgroundColor: 'rgba(255, 198, 108, 0.25)',
                    pointBackgroundColor: 'rgba(255, 198, 108, 0.95)',
                    pointRadius: 3,
                    borderWidth: 2,
                    fill: false,
                    yAxisID: 'yPct',
                    tension: 0.2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: window.biLegendWhite ? window.biLegendWhite : undefined,
            scales: {
                xAxes: [{
                    ticks: { fontColor: '#e8f1ff' },
                    gridLines: { display: false }
                }],
                yAxes: [
                    {
                        id: 'y',
                        position: 'left',
                        ticks: { beginAtZero: true, fontColor: '#e8f1ff' },
                        gridLines: { color: 'rgba(255,255,255,0.1)' },
                        scaleLabel: { display: true, labelString: 'Quantidade', fontColor: '#e8f1ff' }
                    },
                    {
                        id: 'yPct',
                        position: 'right',
                        ticks: {
                            beginAtZero: true,
                            max: 100,
                            fontColor: '#e8f1ff',
                            callback: function(value) { return value + '%'; }
                        },
                        gridLines: { display: false },
                        scaleLabel: { display: true, labelString: 'Taxa (%)', fontColor: '#e8f1ff' }
                    }
                ]
            }
        }
    });
}

groupedBar(document.getElementById('chartValores'), chartLabels, valorApresentado, valorFinal);
multiLine(document.getElementById('chartSeguradora'), chartLabels, segSeries);
mixedAuditChart(document.getElementById('chartContasAuditadas'), chartLabels, contasTotaisMes, contasAuditadasMes);
horizontalBar(document.getElementById('chartTopContas'), topLabels, topValues);
</script>

<?php require_once("templates/footer.php"); ?>
