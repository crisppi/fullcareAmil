<?php
include_once("check_logado.php");
require_once("templates/header.php");

if (!isset($conn) || !($conn instanceof PDO)) {
    die("Conexao invalida.");
}

if (!function_exists('e')) {
    function e($v)
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

$modo = trim((string)(filter_input(INPUT_GET, 'modo') ?: 'contas'));
if (!in_array($modo, ['contas', 'glosa'], true)) {
    $modo = 'contas';
}

$startInput = (string)(filter_input(INPUT_GET, 'data_inicio') ?: filter_input(INPUT_GET, 'data_ini') ?: date('Y-m-01', strtotime('-5 months')));
$endInput = (string)(filter_input(INPUT_GET, 'data_fim') ?: date('Y-m-d'));
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;

$startDate = DateTime::createFromFormat('Y-m-d', $startInput) ?: new DateTime(date('Y-m-01', strtotime('-5 months')));
$endDate = DateTime::createFromFormat('Y-m-d', $endInput) ?: new DateTime(date('Y-m-d'));
if ($startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}
$start = $startDate->format('Y-m-d');
$end = $endDate->format('Y-m-d');

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")->fetchAll(PDO::FETCH_ASSOC);

$mesMap = ['01' => 'Jan', '02' => 'Fev', '03' => 'Mar', '04' => 'Abr', '05' => 'Mai', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', '09' => 'Set', '10' => 'Out', '11' => 'Nov', '12' => 'Dez'];
$monthKeys = [];
$monthLabels = [];
$cursor = (clone $startDate)->modify('first day of this month');
$limit = (clone $endDate)->modify('first day of next month');
while ($cursor < $limit) {
    $k = $cursor->format('Y-m');
    $monthKeys[] = $k;
    $monthLabels[] = $mesMap[$cursor->format('m')] . '/' . $cursor->format('Y');
    $cursor->modify('+1 month');
}

$auditorExpr = "
    CASE
        WHEN NULLIF(i.visita_auditor_prof_med,'') IS NOT NULL
            THEN CONCAT(COALESCE(u_med.usuario_user, i.visita_auditor_prof_med), ' (Medico)')
        WHEN NULLIF(i.visita_auditor_prof_enf,'') IS NOT NULL
            THEN CONCAT(COALESCE(u_enf.usuario_user, i.visita_auditor_prof_enf), ' (Enfermagem)')
        ELSE 'Sem informacoes'
    END
";

$latestCapeanteJoin = "
    LEFT JOIN (
        SELECT c1.*
        FROM tb_capeante c1
        INNER JOIN (
            SELECT fk_int_capeante, MAX(id_capeante) AS max_id
            FROM tb_capeante
            GROUP BY fk_int_capeante
        ) c2 ON c2.max_id = c1.id_capeante
    ) ca ON ca.fk_int_capeante = i.id_internacao
";

$where = "i.data_intern_int BETWEEN :start AND :end";
$params = [':start' => $start, ':end' => $end];
if ($hospitalId) {
    $where .= " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = (int)$hospitalId;
}

$sqlAuditor = "
    SELECT
        {$auditorExpr} AS auditor,
        COUNT(DISTINCT i.id_internacao) AS contas,
        SUM(COALESCE(ca.valor_glosa_total, 0)) AS glosa
    FROM tb_internacao i
    LEFT JOIN tb_user u_med ON u_med.id_usuario = CAST(NULLIF(i.visita_auditor_prof_med,'') AS UNSIGNED)
    LEFT JOIN tb_user u_enf ON u_enf.id_usuario = CAST(NULLIF(i.visita_auditor_prof_enf,'') AS UNSIGNED)
    {$latestCapeanteJoin}
    WHERE {$where}
      AND ({$auditorExpr}) <> 'Sem informacoes'
    GROUP BY auditor
    ORDER BY " . ($modo === 'glosa' ? "glosa" : "contas") . " DESC
    LIMIT 12
";
$stmt = $conn->prepare($sqlAuditor);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$audRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sqlTotals = "
    SELECT
        COUNT(DISTINCT i.id_internacao) AS contas,
        SUM(COALESCE(ca.valor_glosa_total, 0)) AS glosa
    FROM tb_internacao i
    {$latestCapeanteJoin}
    WHERE {$where}
";
$stmt = $conn->prepare($sqlTotals);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$tot = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$totalContas = (int)($tot['contas'] ?? 0);
$totalGlosa = (float)($tot['glosa'] ?? 0);

$sqlMonthly = "
    SELECT
        DATE_FORMAT(i.data_intern_int, '%Y-%m') AS ym,
        COUNT(DISTINCT i.id_internacao) AS contas,
        SUM(COALESCE(ca.valor_glosa_total, 0)) AS glosa
    FROM tb_internacao i
    {$latestCapeanteJoin}
    WHERE {$where}
    GROUP BY ym
    ORDER BY ym
";
$stmt = $conn->prepare($sqlMonthly);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$monthRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$serieContas = array_fill_keys($monthKeys, 0);
$serieGlosa = array_fill_keys($monthKeys, 0.0);
foreach ($monthRows as $r) {
    $ym = (string)($r['ym'] ?? '');
    if (!isset($serieContas[$ym])) continue;
    $serieContas[$ym] = (int)($r['contas'] ?? 0);
    $serieGlosa[$ym] = (float)($r['glosa'] ?? 0);
}

$today = new DateTime();
$curStart = (new DateTime($today->format('Y-01-01')))->format('Y-m-d');
$curEnd = $today->format('Y-m-d');
$prevStart = (new DateTime($curStart))->modify('-1 year')->format('Y-m-d');
$prevEnd = (new DateTime($curEnd))->modify('-1 year')->format('Y-m-d');

$getYtd = function (string $a, string $b) use ($conn, $latestCapeanteJoin, $hospitalId) {
    $w = "i.data_intern_int BETWEEN :a AND :b";
    $p = [':a' => $a, ':b' => $b];
    if ($hospitalId) {
        $w .= " AND i.fk_hospital_int = :hospital_id";
        $p[':hospital_id'] = (int)$hospitalId;
    }
    $sql = "SELECT COUNT(DISTINCT i.id_internacao) AS contas, SUM(COALESCE(ca.valor_glosa_total,0)) AS glosa FROM tb_internacao i {$latestCapeanteJoin} WHERE {$w}";
    $st = $conn->prepare($sql);
    foreach ($p as $k => $v) {
        $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $st->execute();
    return $st->fetch(PDO::FETCH_ASSOC) ?: ['contas' => 0, 'glosa' => 0];
};
$ytdCur = $getYtd($curStart, $curEnd);
$ytdPrev = $getYtd($prevStart, $prevEnd);

$audLabels = array_map(fn($r) => (string)($r['auditor'] ?? 'Sem informacoes'), $audRows);
$audContas = array_map(fn($r) => (int)($r['contas'] ?? 0), $audRows);
$audGlosa = array_map(fn($r) => (float)($r['glosa'] ?? 0), $audRows);

$pctAuditorDatasets = [];
if ($modo === 'glosa' && !empty($monthKeys) && !empty($audRows)) {
    $topAuditores = array_slice(array_map(fn($r) => (string)($r['auditor'] ?? ''), $audRows), 0, 4);
    $topAuditores = array_values(array_filter($topAuditores, fn($v) => $v !== ''));

    if (!empty($topAuditores)) {
        $audParams = [];
        $audTokens = [];
        foreach ($topAuditores as $idx => $nome) {
            $ph = ':aud_' . $idx;
            $audTokens[] = $ph;
            $audParams[$ph] = $nome;
        }
        $inAud = implode(',', $audTokens);

        $sqlPctMonthly = "
            SELECT
                DATE_FORMAT(i.data_intern_int, '%Y-%m') AS ym,
                {$auditorExpr} AS auditor,
                SUM(COALESCE(ca.valor_glosa_total, 0)) AS glosa,
                SUM(COALESCE(ca.valor_apresentado_capeante, 0)) AS apresentado
            FROM tb_internacao i
            LEFT JOIN tb_user u_med ON u_med.id_usuario = CAST(NULLIF(i.visita_auditor_prof_med,'') AS UNSIGNED)
            LEFT JOIN tb_user u_enf ON u_enf.id_usuario = CAST(NULLIF(i.visita_auditor_prof_enf,'') AS UNSIGNED)
            {$latestCapeanteJoin}
            WHERE {$where}
              AND ({$auditorExpr}) IN ({$inAud})
            GROUP BY ym, auditor
            ORDER BY ym, auditor
        ";
        $stmt = $conn->prepare($sqlPctMonthly);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        foreach ($audParams as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->execute();
        $pctRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $seriesByAud = [];
        foreach ($topAuditores as $a) {
            $seriesByAud[$a] = array_fill_keys($monthKeys, 0.0);
        }
        foreach ($pctRows as $r) {
            $a = (string)($r['auditor'] ?? '');
            $ym = (string)($r['ym'] ?? '');
            if (!isset($seriesByAud[$a][$ym])) {
                continue;
            }
            $glosa = (float)($r['glosa'] ?? 0);
            $apresentado = (float)($r['apresentado'] ?? 0);
            $seriesByAud[$a][$ym] = $apresentado > 0 ? ($glosa / $apresentado) * 100 : 0;
        }

        $palettePct = [
            'rgba(255, 198, 108, 0.95)',
            'rgba(111, 223, 194, 0.95)',
            'rgba(126, 150, 255, 0.95)',
            'rgba(245, 120, 163, 0.95)',
        ];
        $idx = 0;
        foreach ($seriesByAud as $aud => $serie) {
            $pctAuditorDatasets[] = [
                'label' => $aud,
                'data' => array_values($serie),
                'borderColor' => $palettePct[$idx % count($palettePct)],
                'backgroundColor' => 'rgba(0,0,0,0)',
                'borderWidth' => 2,
                'pointRadius' => 3,
                'tension' => 0.3,
                'fill' => false,
            ];
            $idx++;
        }
    }
}

$title = $modo === 'glosa' ? 'Glosa por Auditor' : 'Contas Auditadas por Auditor';
$ieSlug = $modo === 'glosa' ? 'glosa-auditor' : 'contas-auditadas-auditor';
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme bi-ie-page bi-auditor-page">
    <div class="bi-header">
        <h1 class="bi-title"><?= e($title) ?></h1>
        <div class="bi-header-actions">
            <a class="bi-btn bi-btn-secondary" href="<?= $BASE_URL ?>IndicadoresEssenciaisHubBI.php">Indicadores Essenciais</a>
        </div>
    </div>

    <form class="bi-panel bi-filters" method="get">
        <input type="hidden" name="modo" value="<?= e($modo) ?>">
        <input type="hidden" name="ie" value="<?= e($ieSlug) ?>">
        <div class="bi-filter">
            <label>Data inicio</label>
            <input type="date" name="data_inicio" value="<?= e($start) ?>">
        </div>
        <div class="bi-filter">
            <label>Data fim</label>
            <input type="date" name="data_fim" value="<?= e($end) ?>">
        </div>
        <div class="bi-filter">
            <label>Hospital</label>
            <select name="hospital_id">
                <option value="">Todos</option>
                <?php foreach ($hospitais as $h): ?>
                    <option value="<?= (int)$h['id_hospital'] ?>" <?= $hospitalId == $h['id_hospital'] ? 'selected' : '' ?>><?= e($h['nome_hosp']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-actions"><button class="bi-btn" type="submit">Aplicar</button></div>
    </form>

    <div class="bi-panel">
        <div class="bi-kpis kpi-auditor-v2">
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-1">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-clipboard2-check"></i></span>
                    <small>Contas auditadas</small>
                </div>
                <strong><?= number_format($totalContas, 0, ',', '.') ?></strong>
                <span class="kpi-trend kpi-trend-up"><i class="bi bi-arrow-up-right"></i>Volume consolidado</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-2">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-cash-stack"></i></span>
                    <small>Glosa total</small>
                </div>
                <strong>R$ <?= number_format($totalGlosa, 2, ',', '.') ?></strong>
                <span class="kpi-trend kpi-trend-neutral"><i class="bi bi-graph-up-arrow"></i>Período filtrado</span>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3><?= e($title) ?></h3>
        <div class="bi-chart ie-chart-main"><canvas id="chartMain"></canvas></div>
    </div>

    <div class="bi-panel">
        <h3>Evolução mensal</h3>
        <div class="bi-chart ie-chart-monthly"><canvas id="chartMensal"></canvas></div>
    </div>
    <?php if ($modo === 'glosa'): ?>
    <div class="bi-panel">
        <h3>% de glosa mensal por auditor</h3>
        <div class="bi-chart ie-chart-monthly"><canvas id="chartPctAuditorMensal"></canvas></div>
    </div>
    <?php endif; ?>

    <div class="bi-panel">
        <h3>Comparativo YTD (<?= date('Y') ?> x <?= date('Y') - 1 ?>)</h3>
        <table class="bi-table">
            <thead><tr><th>Indicador</th><th class="text-end">Atual</th><th class="text-end">Anterior</th><th class="text-end">Delta</th></tr></thead>
            <tbody>
                <tr>
                    <td>Contas auditadas</td>
                    <td class="text-end"><?= number_format((int)($ytdCur['contas'] ?? 0), 0, ',', '.') ?></td>
                    <td class="text-end"><?= number_format((int)($ytdPrev['contas'] ?? 0), 0, ',', '.') ?></td>
                    <td class="text-end"><?= number_format(((int)($ytdCur['contas'] ?? 0) - (int)($ytdPrev['contas'] ?? 0)), 0, ',', '.') ?></td>
                </tr>
                <tr>
                    <td>Glosa</td>
                    <td class="text-end">R$ <?= number_format((float)($ytdCur['glosa'] ?? 0), 2, ',', '.') ?></td>
                    <td class="text-end">R$ <?= number_format((float)($ytdPrev['glosa'] ?? 0), 2, ',', '.') ?></td>
                    <td class="text-end">R$ <?= number_format(((float)($ytdCur['glosa'] ?? 0) - (float)($ytdPrev['glosa'] ?? 0)), 2, ',', '.') ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<style>
.ie-chart-main { height: 240px; }
.ie-chart-monthly { height: 220px; }
@media (max-width: 900px) {
  .ie-chart-main { height: 220px; }
  .ie-chart-monthly { height: 200px; }
}
</style>

<script>
const modo = <?= json_encode($modo) ?>;
const audLabels = <?= json_encode($audLabels, JSON_UNESCAPED_UNICODE) ?>;
const audContas = <?= json_encode($audContas) ?>;
const audGlosa = <?= json_encode($audGlosa) ?>;
const monthLabels = <?= json_encode(array_values($monthLabels), JSON_UNESCAPED_UNICODE) ?>;
const serieContas = <?= json_encode(array_values($serieContas)) ?>;
const serieGlosa = <?= json_encode(array_values($serieGlosa)) ?>;
const pctAuditorDatasets = <?= json_encode($pctAuditorDatasets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

const barScales = window.biChartScales ? window.biChartScales() : undefined;
if (modo === 'glosa' && barScales && barScales.xAxes && barScales.xAxes[0] && barScales.xAxes[0].ticks) {
  barScales.xAxes[0].ticks.callback = function(v){ return window.biMoneyTick ? window.biMoneyTick(v) : ('R$ ' + Number(v||0).toLocaleString('pt-BR')); };
}

new Chart(document.getElementById('chartMain'), {
  type: 'horizontalBar',
  data: {
    labels: audLabels,
    datasets: [{
      label: modo === 'glosa' ? 'Glosa (R$)' : 'Contas auditadas',
      data: modo === 'glosa' ? audGlosa : audContas,
      backgroundColor: 'rgba(126,150,255,0.78)',
      borderRadius: 8
    }]
  },
  options: {
    legend: { display: false },
    maintainAspectRatio: false,
    scales: barScales,
    tooltips: {
      callbacks: {
        label: function(item, data) {
          const ds = data.datasets[item.datasetIndex] || {};
          if (modo === 'glosa') {
            const v = window.biMoneyTick ? window.biMoneyTick(item.xLabel) : ('R$ ' + Number(item.xLabel || 0).toLocaleString('pt-BR'));
            return (ds.label ? ds.label + ': ' : '') + v;
          }
          return (ds.label ? ds.label + ': ' : '') + Number(item.xLabel || 0).toLocaleString('pt-BR');
        }
      }
    }
  }
});

const lineScales = window.biChartScales ? window.biChartScales() : undefined;
if (modo === 'glosa' && lineScales && lineScales.yAxes && lineScales.yAxes[0] && lineScales.yAxes[0].ticks) {
  lineScales.yAxes[0].ticks.callback = function(v){ return window.biMoneyTick ? window.biMoneyTick(v) : ('R$ ' + Number(v||0).toLocaleString('pt-BR')); };
}

new Chart(document.getElementById('chartMensal'), {
  type: 'line',
  data: {
    labels: monthLabels,
    datasets: [{
      label: modo === 'glosa' ? 'Glosa mensal (R$)' : 'Contas auditadas mensal',
      data: modo === 'glosa' ? serieGlosa : serieContas,
      borderColor: 'rgba(111,223,194,0.9)',
      backgroundColor: 'rgba(111,223,194,0.15)',
      borderWidth: 2,
      tension: 0.35,
      pointRadius: 3,
      fill: true
    }]
  },
  options: {
    legend: { display: false },
    maintainAspectRatio: false,
    scales: lineScales
  }
});

if (modo === 'glosa') {
  const pctScales = window.biChartScales ? window.biChartScales() : undefined;
  if (pctScales && pctScales.yAxes && pctScales.yAxes[0] && pctScales.yAxes[0].ticks) {
    pctScales.yAxes[0].ticks.callback = function(v){ return Number(v || 0).toLocaleString('pt-BR') + '%'; };
  }
  const pctCanvas = document.getElementById('chartPctAuditorMensal');
  if (pctCanvas) {
    new Chart(pctCanvas, {
      type: 'line',
      data: {
        labels: monthLabels,
        datasets: pctAuditorDatasets
      },
      options: {
        legend: window.biLegendWhite ? window.biLegendWhite : undefined,
        maintainAspectRatio: false,
        scales: pctScales,
        tooltips: {
          callbacks: {
            label: function(item, data) {
              const ds = data.datasets[item.datasetIndex] || {};
              return (ds.label ? ds.label + ': ' : '') + Number(item.yLabel || 0).toLocaleString('pt-BR') + '%';
            }
          }
        }
      }
    });
  }
}
</script>

<?php require_once("templates/footer.php"); ?>
