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

function buildWhereClause(string $auditorExpr, ?int $ano, ?int $mes, ?int $hospitalId, string $auditorNome, array $monthsIn = []): array
{
    $where = "v.fk_internacao_vis IS NOT NULL";
    $params = [];

    if (!empty($ano)) {
        $where .= " AND YEAR(v.data_visita_vis) = :ano";
        $params[':ano'] = (int)$ano;
    }

    if (!empty($mes)) {
        $where .= " AND MONTH(v.data_visita_vis) = :mes";
        $params[':mes'] = (int)$mes;
    } elseif (!empty($monthsIn)) {
        $placeholders = [];
        foreach (array_values($monthsIn) as $idx => $monthVal) {
            $ph = ':m_in_' . $idx;
            $placeholders[] = $ph;
            $params[$ph] = (int)$monthVal;
        }
        $where .= " AND MONTH(v.data_visita_vis) IN (" . implode(',', $placeholders) . ")";
    }

    if (!empty($hospitalId)) {
        $where .= " AND i.fk_hospital_int = :hospital_id";
        $params[':hospital_id'] = (int)$hospitalId;
    }

    if ($auditorNome !== '') {
        $where .= " AND {$auditorExpr} = :auditor_nome";
        $params[':auditor_nome'] = $auditorNome;
    }

    return [$where, $params];
}

function fetchStats(PDO $conn, string $auditorExpr, string $where, array $params): array
{
    $sqlBaseIntern = "
        SELECT DISTINCT i.id_internacao,
            {$auditorExpr} AS auditor_nome,
            GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int)) AS diarias
        FROM tb_visita v
        LEFT JOIN tb_user u ON u.id_usuario = v.fk_usuario_vis
        LEFT JOIN tb_user u_med ON u_med.id_usuario = CAST(NULLIF(v.visita_auditor_prof_med,'') AS UNSIGNED)
        LEFT JOIN tb_user u_enf ON u_enf.id_usuario = CAST(NULLIF(v.visita_auditor_prof_enf,'') AS UNSIGNED)
        LEFT JOIN tb_internacao i ON i.id_internacao = v.fk_internacao_vis
        LEFT JOIN (
            SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
            FROM tb_alta
            GROUP BY fk_id_int_alt
        ) al ON al.fk_id_int_alt = i.id_internacao
        WHERE {$where}
    ";

    $sqlStats = "
        SELECT
            COUNT(DISTINCT id_internacao) AS total_internacoes,
            SUM(diarias) AS total_diarias,
            MAX(diarias) AS maior_permanencia,
            ROUND(AVG(diarias), 1) AS mp
        FROM ({$sqlBaseIntern}) t
    ";

    $stmt = $conn->prepare($sqlStats);
    $stmt->execute($params);

    return [
        'stats' => ($stmt->fetch(PDO::FETCH_ASSOC) ?: []),
        'sqlBaseIntern' => $sqlBaseIntern,
    ];
}

function labelsAndValues(array $rows): array
{
    $labels = array_map(fn($r) => $r['auditor_nome'] ?? 'Sem informações', $rows);
    $values = array_map(fn($r) => (float)($r['total'] ?? 0), $rows);
    return [$labels, $values];
}

function buildTrend(float $current, ?float $previous, bool $inverse = false): array
{
    if ($previous === null || $previous <= 0) {
        return [
            'previous' => $previous,
            'delta_percent' => null,
            'trend_state' => 'neutral',
            'trend_label' => 'Sem base',
            'trend_icon' => 'bi bi-dash',
        ];
    }

    $deltaPercent = (($current - $previous) / $previous) * 100;
    $rounded = round($deltaPercent, 1);
    $display = number_format(abs($rounded), 1, ',', '.');

    if (abs($rounded) < 0.05) {
        return [
            'previous' => $previous,
            'delta_percent' => 0.0,
            'trend_state' => 'neutral',
            'trend_label' => '0,0%',
            'trend_icon' => 'bi bi-dash',
        ];
    }

    $isPositive = $rounded > 0;
    $semanticUp = $inverse ? !$isPositive : $isPositive;

    return [
        'previous' => $previous,
        'delta_percent' => $rounded,
        'trend_state' => $semanticUp ? 'up' : 'down',
        'trend_label' => ($isPositive ? '+' : '-') . $display . '%',
        'trend_icon' => $isPositive ? 'bi bi-arrow-up-right' : 'bi bi-arrow-down-right',
    ];
}

$anoInput = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
$mesInput = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT);
$ano = ($anoInput !== null && $anoInput !== false) ? (int)$anoInput : null;
$mes = ($mesInput !== null && $mesInput !== false) ? (int)$mesInput : null;
if ($ano === null && !filter_has_var(INPUT_GET, 'ano')) {
    $stmtAno = $conn->query("
        SELECT MAX(YEAR(data_visita_vis)) AS ano
        FROM tb_visita
        WHERE data_visita_vis IS NOT NULL
          AND data_visita_vis <> '0000-00-00'
    ");
    $anoDb = $stmtAno->fetch(PDO::FETCH_ASSOC) ?: [];
    $ano = (int)($anoDb['ano'] ?? date('Y'));
}

$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$auditorNome = trim((string)(filter_input(INPUT_GET, 'auditor') ?? ''));

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);
$auditorExpr = "
    CASE
        WHEN NULLIF(v.visita_auditor_prof_med,'') IS NOT NULL
            THEN CONCAT(COALESCE(u_med.usuario_user, v.visita_auditor_prof_med), ' (Médico)')
        WHEN NULLIF(v.visita_auditor_prof_enf,'') IS NOT NULL
            THEN CONCAT(COALESCE(u_enf.usuario_user, v.visita_auditor_prof_enf), ' (Enfermagem)')
        WHEN u.usuario_user IS NOT NULL
            THEN CONCAT(u.usuario_user, ' (Auditor)')
        ELSE 'Sem informações'
    END
";

$auditorListSql = "
    SELECT DISTINCT {$auditorExpr} AS auditor_nome
    FROM tb_visita v
    LEFT JOIN tb_user u ON u.id_usuario = v.fk_usuario_vis
    LEFT JOIN tb_user u_med ON u_med.id_usuario = CAST(NULLIF(v.visita_auditor_prof_med,'') AS UNSIGNED)
    LEFT JOIN tb_user u_enf ON u_enf.id_usuario = CAST(NULLIF(v.visita_auditor_prof_enf,'') AS UNSIGNED)
    WHERE {$auditorExpr} <> 'Sem informações'
    ORDER BY auditor_nome
";
$auditores = $conn->query($auditorListSql)->fetchAll(PDO::FETCH_COLUMN);

[$where, $params] = buildWhereClause($auditorExpr, $ano, $mes, $hospitalId, $auditorNome);
$current = fetchStats($conn, $auditorExpr, $where, $params);
$stats = $current['stats'];
$sqlBaseIntern = $current['sqlBaseIntern'];

$totalInternacoes = (int)($stats['total_internacoes'] ?? 0);
$totalDiarias = (int)($stats['total_diarias'] ?? 0);
$maiorPermanencia = (int)($stats['maior_permanencia'] ?? 0);
$mp = (float)($stats['mp'] ?? 0);

$prevStats = null;

if (!empty($mes)) {
    $prevMonth = $mes - 1;
    $prevYear = (int)$ano;
    if ($prevMonth <= 0) {
        $prevMonth = 12;
        $prevYear--;
    }

    [$prevWhere, $prevParams] = buildWhereClause($auditorExpr, $prevYear, $prevMonth, $hospitalId, $auditorNome);
    $prev = fetchStats($conn, $auditorExpr, $prevWhere, $prevParams);
    $prevStats = $prev['stats'];
} else {
    [$monthWhere, $monthParams] = buildWhereClause($auditorExpr, $ano, null, $hospitalId, $auditorNome);
    $sqlMonths = "
        SELECT DISTINCT MONTH(v.data_visita_vis) AS mes_ref
        FROM tb_visita v
        LEFT JOIN tb_user u ON u.id_usuario = v.fk_usuario_vis
        LEFT JOIN tb_user u_med ON u_med.id_usuario = CAST(NULLIF(v.visita_auditor_prof_med,'') AS UNSIGNED)
        LEFT JOIN tb_user u_enf ON u_enf.id_usuario = CAST(NULLIF(v.visita_auditor_prof_enf,'') AS UNSIGNED)
        LEFT JOIN tb_internacao i ON i.id_internacao = v.fk_internacao_vis
        WHERE {$monthWhere}
        ORDER BY mes_ref
    ";

    $stmtMonths = $conn->prepare($sqlMonths);
    $stmtMonths->execute($monthParams);
    $availableMonths = array_map('intval', $stmtMonths->fetchAll(PDO::FETCH_COLUMN));

    $windowSize = intdiv(count($availableMonths), 2);
    if ($windowSize > 0) {
        $previousMonths = array_slice($availableMonths, -($windowSize * 2), $windowSize);

        if (count($previousMonths) === $windowSize) {
            [$prevWhere, $prevParams] = buildWhereClause($auditorExpr, $ano, null, $hospitalId, $auditorNome, $previousMonths);
            $prev = fetchStats($conn, $auditorExpr, $prevWhere, $prevParams);
            $prevStats = $prev['stats'];
        }
    }
}

$trendInternacoes = buildTrend((float)$totalInternacoes, isset($prevStats['total_internacoes']) ? (float)$prevStats['total_internacoes'] : null);
$trendDiarias = buildTrend((float)$totalDiarias, isset($prevStats['total_diarias']) ? (float)$prevStats['total_diarias'] : null);
$trendMp = buildTrend((float)$mp, isset($prevStats['mp']) ? (float)$prevStats['mp'] : null);
$trendPermanencia = buildTrend((float)$maiorPermanencia, isset($prevStats['maior_permanencia']) ? (float)$prevStats['maior_permanencia'] : null);

$sqlContas = "
    SELECT auditor_nome, SUM(COALESCE(ca.valor_apresentado_capeante,0)) AS total
    FROM ({$sqlBaseIntern}) t
    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = t.id_internacao
    GROUP BY auditor_nome
    ORDER BY total DESC
    LIMIT 12
";
$stmt = $conn->prepare($sqlContas);
$stmt->execute($params);
$contasRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sqlGlosa = "
    SELECT auditor_nome, SUM(COALESCE(ca.valor_glosa_total,0)) AS total
    FROM ({$sqlBaseIntern}) t
    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = t.id_internacao
    GROUP BY auditor_nome
    ORDER BY total DESC
    LIMIT 12
";
$stmt = $conn->prepare($sqlGlosa);
$stmt->execute($params);
$glosaRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sqlAuditadas = "
    SELECT auditor_nome, COUNT(DISTINCT id_internacao) AS total
    FROM ({$sqlBaseIntern}) t
    GROUP BY auditor_nome
    ORDER BY total DESC
    LIMIT 12
";
$stmt = $conn->prepare($sqlAuditadas);
$stmt->execute($params);
$auditadasRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sqlVisitas = "
    SELECT {$auditorExpr} AS auditor_nome, COUNT(*) AS total
    FROM tb_visita v
    LEFT JOIN tb_user u ON u.id_usuario = v.fk_usuario_vis
    LEFT JOIN tb_user u_med ON u_med.id_usuario = CAST(NULLIF(v.visita_auditor_prof_med,'') AS UNSIGNED)
    LEFT JOIN tb_user u_enf ON u_enf.id_usuario = CAST(NULLIF(v.visita_auditor_prof_enf,'') AS UNSIGNED)
    LEFT JOIN tb_internacao i ON i.id_internacao = v.fk_internacao_vis
    WHERE {$where}
    GROUP BY auditor_nome
    ORDER BY total DESC
    LIMIT 12
";
$stmt = $conn->prepare($sqlVisitas);
$stmt->execute($params);
$visitasRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

[$contasLabels, $contasValues] = labelsAndValues($contasRows);
[$glosaLabels, $glosaValues] = labelsAndValues($glosaRows);
[$auditadasLabels, $auditadasValues] = labelsAndValues($auditadasRows);
[$visitasLabels, $visitasValues] = labelsAndValues($visitasRows);

$payload = [
    'kpis' => [
        'internacoes' => [
            'value' => number_format($totalInternacoes, 0, ',', '.'),
            'trend_state' => $trendInternacoes['trend_state'] ?? 'neutral',
            'trend_icon' => $trendInternacoes['trend_icon'] ?? 'bi bi-dash',
            'trend_label' => $trendInternacoes['trend_label'] ?? 'Sem base',
        ],
        'diarias' => [
            'value' => number_format($totalDiarias, 0, ',', '.'),
            'trend_state' => $trendDiarias['trend_state'] ?? 'neutral',
            'trend_icon' => $trendDiarias['trend_icon'] ?? 'bi bi-dash',
            'trend_label' => $trendDiarias['trend_label'] ?? 'Sem base',
        ],
        'mp' => [
            'value' => number_format($mp, 1, ',', '.'),
            'trend_state' => $trendMp['trend_state'] ?? 'neutral',
            'trend_icon' => $trendMp['trend_icon'] ?? 'bi bi-dash',
            'trend_label' => $trendMp['trend_label'] ?? 'Sem base',
        ],
        'permanencia' => [
            'value' => number_format($maiorPermanencia, 0, ',', '.'),
            'trend_state' => $trendPermanencia['trend_state'] ?? 'neutral',
            'trend_icon' => $trendPermanencia['trend_icon'] ?? 'bi bi-dash',
            'trend_label' => $trendPermanencia['trend_label'] ?? 'Sem base',
        ],
    ],
    'charts' => [
        'contas' => ['labels' => $contasLabels, 'values' => $contasValues],
        'glosa' => ['labels' => $glosaLabels, 'values' => $glosaValues],
        'auditadas' => ['labels' => $auditadasLabels, 'values' => $auditadasValues],
        'visitas' => ['labels' => $visitasLabels, 'values' => $visitasValues],
    ],
];

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

$ajaxEndpoint = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ($_SERVER['PHP_SELF'] ?? 'AuditorBI.php'));
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260516-rounded-bars"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme bi-auditor-page">
    <div class="bi-header">
        <h1 class="bi-title">Auditor</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted"></div>
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <form class="bi-panel bi-filters bi-filters-wrap bi-filters-compact js-bi-filter-form" method="get">
        <div class="bi-filter">
            <label>Auditor</label>
            <select name="auditor">
                <option value="">Todos</option>
                <?php foreach ($auditores as $a): ?>
                    <option value="<?= e($a) ?>" <?= $auditorNome === $a ? 'selected' : '' ?>>
                        <?= e($a) ?>
                    </option>
                <?php endforeach; ?>
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
            <label>Mês</label>
            <select name="mes">
                <option value="">Todos</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $mes == $m ? 'selected' : '' ?>><?= $m ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Ano</label>
            <input type="number" name="ano" value="<?= e($ano) ?>" min="2000" max="2100">
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
            <a class="bi-btn bi-btn-secondary" href="<?= $BASE_URL ?>bi/auditor">Limpar</a>
        </div>
    </form>

    <section class="bi-stack">
            <div class="bi-kpis kpi-auditor-v2">
                <div class="bi-kpi kpi-card-v2 kpi-card-v2-1">
                    <div class="kpi-card-v2-head">
                        <span class="kpi-card-v2-icon"><i class="bi bi-hospital"></i></span>
                        <small>Internações</small>
                    </div>
                    <strong id="kpi-internacoes-value"><?= number_format($totalInternacoes, 0, ',', '.') ?></strong>
                    <div id="kpi-internacoes-trend" class="kpi-trend kpi-trend-<?= e($trendInternacoes['trend_state']) ?>">
                        <i class="<?= e($trendInternacoes['trend_icon']) ?>"></i>
                        <span><?= e($trendInternacoes['trend_label']) ?></span>
                    </div>
                </div>

                <div class="bi-kpi kpi-card-v2 kpi-card-v2-2">
                    <div class="kpi-card-v2-head">
                        <span class="kpi-card-v2-icon"><i class="bi bi-moon-stars"></i></span>
                        <small>Diárias</small>
                    </div>
                    <strong id="kpi-diarias-value"><?= number_format($totalDiarias, 0, ',', '.') ?></strong>
                    <div id="kpi-diarias-trend" class="kpi-trend kpi-trend-<?= e($trendDiarias['trend_state']) ?>">
                        <i class="<?= e($trendDiarias['trend_icon']) ?>"></i>
                        <span><?= e($trendDiarias['trend_label']) ?></span>
                    </div>
                </div>

                <div class="bi-kpi kpi-card-v2 kpi-card-v2-3">
                    <div class="kpi-card-v2-head">
                        <span class="kpi-card-v2-icon"><i class="bi bi-activity"></i></span>
                        <small>MP</small>
                    </div>
                    <strong id="kpi-mp-value"><?= number_format($mp, 1, ',', '.') ?></strong>
                    <div id="kpi-mp-trend" class="kpi-trend kpi-trend-<?= e($trendMp['trend_state']) ?>">
                        <i class="<?= e($trendMp['trend_icon']) ?>"></i>
                        <span><?= e($trendMp['trend_label']) ?></span>
                    </div>
                </div>

                <div class="bi-kpi kpi-card-v2 kpi-card-v2-4">
                    <div class="kpi-card-v2-head">
                        <span class="kpi-card-v2-icon"><i class="bi bi-stopwatch"></i></span>
                        <small>Maior permanência</small>
                    </div>
                    <strong id="kpi-permanencia-value"><?= number_format($maiorPermanencia, 0, ',', '.') ?></strong>
                    <div id="kpi-permanencia-trend" class="kpi-trend kpi-trend-<?= e($trendPermanencia['trend_state']) ?>">
                        <i class="<?= e($trendPermanencia['trend_icon']) ?>"></i>
                        <span><?= e($trendPermanencia['trend_label']) ?></span>
                    </div>
                </div>
            </div>

            <div class="bi-grid fixed-2">
                <div class="bi-panel">
                    <h3>Contas por Auditor</h3>
                    <div class="bi-chart"><canvas id="chartAuditorContas"></canvas></div>
                </div>
                <div class="bi-panel">
                    <h3>Glosa por Auditor</h3>
                    <div class="bi-chart"><canvas id="chartAuditorGlosa"></canvas></div>
                </div>
            </div>

            <div class="bi-grid fixed-2">
                <div class="bi-panel">
                    <h3>Contas Auditadas</h3>
                    <div class="bi-chart"><canvas id="chartAuditorAuditadas"></canvas></div>
                </div>
                <div class="bi-panel">
                    <h3>Visitas</h3>
                    <div class="bi-chart"><canvas id="chartAuditorVisitas"></canvas></div>
                </div>
            </div>
    </section>
</div>

<script>
const biPayload = <?= json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

function barOptionsMoney() {
  return {
    legend: { display: false },
    plugins: { legend: { display: false } },
    scales: {
      x: { ticks: { color: '#e8f1ff' }, grid: { display: false } },
      y: {
        ticks: {
          color: '#e8f1ff',
          callback: (value) => window.biMoneyTick ? window.biMoneyTick(value) : value
        },
        grid: { color: 'rgba(255,255,255,0.1)' },
        title: { display: true, text: 'Valor (R$)', color: '#e8f1ff' }
      },
      xAxes: [{ ticks: { fontColor: '#e8f1ff' }, gridLines: { display: false } }],
      yAxes: [{
        ticks: {
          fontColor: '#e8f1ff',
          callback: (value) => window.biMoneyTick ? window.biMoneyTick(value) : value
        },
        gridLines: { color: 'rgba(255,255,255,0.1)' },
        scaleLabel: { display: true, labelString: 'Valor (R$)', fontColor: '#e8f1ff' }
      }]
    }
  };
}

function barOptions() {
  return {
    legend: { display: false },
    plugins: { legend: { display: false } },
    scales: {
      x: { ticks: { color: '#e8f1ff' }, grid: { display: false } },
      y: {
        ticks: { color: '#e8f1ff' },
        grid: { color: 'rgba(255,255,255,0.1)' },
        title: { display: true, text: 'Quantidade', color: '#e8f1ff' }
      },
      xAxes: [{ ticks: { fontColor: '#e8f1ff' }, gridLines: { display: false } }],
      yAxes: [{
        ticks: { fontColor: '#e8f1ff' },
        gridLines: { color: 'rgba(255,255,255,0.1)' },
        scaleLabel: { display: true, labelString: 'Quantidade', fontColor: '#e8f1ff' }
      }]
    }
  };
}

const chartAuditorContas = new Chart(document.getElementById('chartAuditorContas'), {
  type: 'bar',
  data: { labels: biPayload.charts.contas.labels, datasets: [{ label: '', data: biPayload.charts.contas.values, backgroundColor: 'rgba(126,150,255,0.82)', borderRadius: 10 }] },
  options: barOptionsMoney()
});

const chartAuditorGlosa = new Chart(document.getElementById('chartAuditorGlosa'), {
  type: 'bar',
  data: { labels: biPayload.charts.glosa.labels, datasets: [{ label: '', data: biPayload.charts.glosa.values, backgroundColor: 'rgba(126,150,255,0.82)', borderRadius: 10 }] },
  options: barOptionsMoney()
});

const chartAuditorAuditadas = new Chart(document.getElementById('chartAuditorAuditadas'), {
  type: 'bar',
  data: { labels: biPayload.charts.auditadas.labels, datasets: [{ label: '', data: biPayload.charts.auditadas.values, backgroundColor: 'rgba(126,150,255,0.82)', borderRadius: 10 }] },
  options: barOptions()
});

const chartAuditorVisitas = new Chart(document.getElementById('chartAuditorVisitas'), {
  type: 'bar',
  data: { labels: biPayload.charts.visitas.labels, datasets: [{ label: '', data: biPayload.charts.visitas.values, backgroundColor: 'rgba(126,150,255,0.82)', borderRadius: 10 }] },
  options: barOptions()
});

function updateTrend(baseId, trend) {
  const trendWrap = document.getElementById(baseId + '-trend');
  if (!trendWrap) return;
  trendWrap.className = 'kpi-trend kpi-trend-' + (trend.trend_state || 'neutral');
  const icon = trendWrap.querySelector('i');
  const label = trendWrap.querySelector('span');
  if (icon) icon.className = trend.trend_icon || 'bi bi-dash';
  if (label) label.textContent = trend.trend_label || 'Sem base';
}

function updateChart(chart, labels, values) {
  chart.data.labels = labels || [];
  chart.data.datasets[0].data = values || [];
  chart.update();
}

function applyPayload(data) {
  if (!data || !data.kpis || !data.charts) return;
  const setText = (id, value) => {
    const el = document.getElementById(id);
    if (el) el.textContent = value ?? '';
  };

  setText('kpi-internacoes-value', data.kpis.internacoes?.value);
  setText('kpi-diarias-value', data.kpis.diarias?.value);
  setText('kpi-mp-value', data.kpis.mp?.value);
  setText('kpi-permanencia-value', data.kpis.permanencia?.value);

  updateTrend('kpi-internacoes', data.kpis.internacoes || {});
  updateTrend('kpi-diarias', data.kpis.diarias || {});
  updateTrend('kpi-mp', data.kpis.mp || {});
  updateTrend('kpi-permanencia', data.kpis.permanencia || {});

  updateChart(chartAuditorContas, data.charts.contas?.labels, data.charts.contas?.values);
  updateChart(chartAuditorGlosa, data.charts.glosa?.labels, data.charts.glosa?.values);
  updateChart(chartAuditorAuditadas, data.charts.auditadas?.labels, data.charts.auditadas?.values);
  updateChart(chartAuditorVisitas, data.charts.visitas?.labels, data.charts.visitas?.values);
}

async function fetchBiData(form) {
  const fd = new FormData(form);
  fd.set('ajax', '1');
  const qs = new URLSearchParams(fd).toString();
  const endpoint = '<?= e($ajaxEndpoint) ?>';
  const url = endpoint + (endpoint.includes('?') ? '&' : '?') + qs;
  const resp = await fetch(url, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin'
  });
  const text = await resp.text();
  if (!resp.ok) throw new Error('Erro ao carregar dados (' + resp.status + ')');
  let data = null;
  try {
    data = JSON.parse(text);
  } catch (_e) {
    throw new Error('Resposta nao-JSON (possivel redirect/sessao expirada).');
  }
  applyPayload(data);

  fd.delete('ajax');
  const cleanQs = new URLSearchParams(fd).toString();
  const newUrl = endpoint + (cleanQs ? ('?' + cleanQs) : '');
  window.history.replaceState({}, '', newUrl);
}

const filterForm = document.querySelector('.js-bi-filter-form');
if (filterForm) {
  filterForm.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    try {
      await fetchBiData(filterForm);
    } catch (e) {
      console.error(e);
      // Fallback silencioso: recarrega com os filtros atuais sem popup nativo.
      filterForm.submit();
    }
  });

  const autoSubmitFields = filterForm.querySelectorAll('select[name=\"auditor\"], select[name=\"hospital_id\"], select[name=\"mes\"], input[name=\"ano\"]');
  autoSubmitFields.forEach((el) => {
    const fn = async () => {
      try {
        await fetchBiData(filterForm);
      } catch (e) {
        console.error(e);
      }
    };
    el.addEventListener('change', fn);
    if (el.tagName === 'INPUT') {
      el.addEventListener('blur', fn);
      el.addEventListener('keydown', (ev) => {
        if (ev.key === 'Enter') {
          ev.preventDefault();
          fn();
        }
      });
    }
  });
}

window.addEventListener('pageshow', () => {
  const params = new URLSearchParams(window.location.search);
  if (params.get('ajax') === '1') {
    params.delete('ajax');
    const clean = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
    window.history.replaceState({}, '', clean);
  }
});
</script>

<?php require_once("templates/footer.php"); ?>
