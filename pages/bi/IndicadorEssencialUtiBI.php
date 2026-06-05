<?php
include_once("check_logado.php");

$modo = trim((string)(filter_input(INPUT_GET, 'modo') ?: 'custo'));
if (!in_array($modo, ['custo', 'percentual'], true)) {
    $modo = 'custo';
}

if (empty($_GET['ie'])) {
    $_GET['ie'] = $modo === 'percentual' ? 'percentual-internacao-uti' : 'custo-uti';
}

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

$startInput = (string)(filter_input(INPUT_GET, 'data_inicio') ?: filter_input(INPUT_GET, 'data_ini') ?: date('Y-m-01', strtotime('-5 months')));
$endInput = (string)(filter_input(INPUT_GET, 'data_fim') ?: date('Y-m-d'));

$startDate = DateTime::createFromFormat('Y-m-d', $startInput) ?: new DateTime(date('Y-m-01', strtotime('-5 months')));
$endDate = DateTime::createFromFormat('Y-m-d', $endInput) ?: new DateTime(date('Y-m-d'));
if ($startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}
$start = $startDate->format('Y-m-d');
$end = $endDate->format('Y-m-d');

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

$utiJoin = "
    LEFT JOIN (
        SELECT DISTINCT fk_internacao_uti
        FROM tb_uti
        WHERE fk_internacao_uti IS NOT NULL
    ) ut ON ut.fk_internacao_uti = i.id_internacao
";

$utiExpr = "(
    CASE
        WHEN i.internado_uti_int = 's' OR i.internacao_uti_int = 's' OR ut.fk_internacao_uti IS NOT NULL THEN 1
        ELSE 0
    END
)";

$sqlKpi = "
    SELECT
        COUNT(DISTINCT i.id_internacao) AS internacoes,
        SUM({$utiExpr}) AS internacoes_uti,
        SUM(COALESCE(ca.valor_final_capeante, 0)) AS custo_total,
        SUM(CASE WHEN {$utiExpr} = 1 THEN COALESCE(ca.valor_final_capeante, 0) ELSE 0 END) AS custo_uti
    FROM tb_internacao i
    {$latestCapeanteJoin}
    {$utiJoin}
    WHERE i.data_intern_int BETWEEN :start AND :end
";
$stmt = $conn->prepare($sqlKpi);
$stmt->bindValue(':start', $start);
$stmt->bindValue(':end', $end);
$stmt->execute();
$kpi = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$internacoes = (int)($kpi['internacoes'] ?? 0);
$internacoesUti = (int)($kpi['internacoes_uti'] ?? 0);
$custoTotal = (float)($kpi['custo_total'] ?? 0);
$custoUti = (float)($kpi['custo_uti'] ?? 0);
$percUti = $internacoes > 0 ? ($internacoesUti / $internacoes) * 100 : 0;
$shareCustoUti = $custoTotal > 0 ? ($custoUti / $custoTotal) * 100 : 0;

$sqlMonthly = "
    SELECT
        DATE_FORMAT(i.data_intern_int, '%Y-%m') AS ym,
        COUNT(DISTINCT i.id_internacao) AS internacoes,
        SUM({$utiExpr}) AS internacoes_uti,
        SUM(CASE WHEN {$utiExpr} = 1 THEN COALESCE(ca.valor_final_capeante, 0) ELSE 0 END) AS custo_uti
    FROM tb_internacao i
    {$latestCapeanteJoin}
    {$utiJoin}
    WHERE i.data_intern_int BETWEEN :start AND :end
    GROUP BY ym
    ORDER BY ym
";
$stmt = $conn->prepare($sqlMonthly);
$stmt->bindValue(':start', $start);
$stmt->bindValue(':end', $end);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$seriePerc = array_fill_keys($monthKeys, 0.0);
$serieCusto = array_fill_keys($monthKeys, 0.0);
foreach ($rows as $r) {
    $ym = (string)($r['ym'] ?? '');
    if (!isset($seriePerc[$ym])) continue;
    $int = (int)($r['internacoes'] ?? 0);
    $u = (int)($r['internacoes_uti'] ?? 0);
    $seriePerc[$ym] = $int > 0 ? ($u / $int) * 100 : 0;
    $serieCusto[$ym] = (float)($r['custo_uti'] ?? 0);
}

$today = new DateTime();
$curStart = (new DateTime($today->format('Y-01-01')))->format('Y-m-d');
$curEnd = $today->format('Y-m-d');
$prevStart = (new DateTime($curStart))->modify('-1 year')->format('Y-m-d');
$prevEnd = (new DateTime($curEnd))->modify('-1 year')->format('Y-m-d');

$getYtd = function (string $a, string $b) use ($conn, $latestCapeanteJoin, $utiJoin, $utiExpr) {
    $sql = "
        SELECT
            COUNT(DISTINCT i.id_internacao) AS internacoes,
            SUM({$utiExpr}) AS internacoes_uti,
            SUM(CASE WHEN {$utiExpr} = 1 THEN COALESCE(ca.valor_final_capeante, 0) ELSE 0 END) AS custo_uti
        FROM tb_internacao i
        {$latestCapeanteJoin}
        {$utiJoin}
        WHERE i.data_intern_int BETWEEN :a AND :b
    ";
    $st = $conn->prepare($sql);
    $st->bindValue(':a', $a);
    $st->bindValue(':b', $b);
    $st->execute();
    $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    $i = (int)($r['internacoes'] ?? 0);
    $u = (int)($r['internacoes_uti'] ?? 0);
    return [
        'perc' => $i > 0 ? ($u / $i) * 100 : 0,
        'custo' => (float)($r['custo_uti'] ?? 0),
    ];
};

$ytdCur = $getYtd($curStart, $curEnd);
$ytdPrev = $getYtd($prevStart, $prevEnd);
$deltaPerc = $ytdCur['perc'] - $ytdPrev['perc'];
$deltaCusto = $ytdCur['custo'] - $ytdPrev['custo'];

$title = $modo === 'percentual' ? '% de Internação UTI' : 'Custo por UTI';
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260516-rounded-bars"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme bi-ie-page">
    <div class="bi-header">
        <h1 class="bi-title"><?= e($title) ?></h1>
        <div class="bi-header-actions">
            <a class="bi-btn bi-btn-secondary" href="<?= $BASE_URL ?>IndicadoresEssenciaisHubBI.php">Indicadores Essenciais</a>
        </div>
    </div>

    <form class="bi-panel bi-filters" method="get">
        <input type="hidden" name="modo" value="<?= e($modo) ?>">
        <input type="hidden" name="ie" value="<?= e($modo === 'percentual' ? 'percentual-internacao-uti' : 'custo-uti') ?>">
        <div class="bi-filter">
            <label>Data início</label>
            <input type="date" name="data_inicio" value="<?= e($start) ?>">
        </div>
        <div class="bi-filter">
            <label>Data fim</label>
            <input type="date" name="data_fim" value="<?= e($end) ?>">
        </div>
        <div class="bi-actions"><button class="bi-btn" type="submit">Aplicar</button></div>
    </form>

    <div class="bi-panel">
        <div class="bi-kpis kpi-dashboard-v2">
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-1">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-hospital"></i></span>
                    <small>Internações UTI</small>
                </div>
                <strong><?= number_format($internacoesUti, 0, ',', '.') ?></strong>
                <span class="kpi-trend kpi-trend-up"><i class="bi bi-arrow-up-right"></i>Casos com UTI</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-2">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-percent"></i></span>
                    <small>% Internação UTI</small>
                </div>
                <strong><?= number_format($percUti, 2, ',', '.') ?>%</strong>
                <span class="kpi-trend kpi-trend-neutral"><i class="bi bi-bar-chart-line"></i>Share assistencial</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-3">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-cash-coin"></i></span>
                    <small>Custo UTI</small>
                </div>
                <strong>R$ <?= number_format($custoUti, 2, ',', '.') ?></strong>
                <span class="kpi-trend kpi-trend-up"><i class="bi bi-currency-dollar"></i>Total acumulado</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-4">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-pie-chart"></i></span>
                    <small>Share custo UTI</small>
                </div>
                <strong><?= number_format($shareCustoUti, 2, ',', '.') ?>%</strong>
                <span class="kpi-trend kpi-trend-neutral"><i class="bi bi-diagram-3"></i>Peso no custo total</span>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Evolução mensal</h3>
        <div class="bi-chart ie-chart-md"><canvas id="chartUtiMain"></canvas></div>
    </div>

    <div class="bi-panel">
        <h3>Comparativo YTD (<?= date('Y') ?> x <?= date('Y') - 1 ?>)</h3>
        <table class="bi-table">
            <thead><tr><th>Indicador</th><th class="text-end">Atual</th><th class="text-end">Anterior</th><th class="text-end">Delta</th></tr></thead>
            <tbody>
                <tr>
                    <td>% Internação UTI</td>
                    <td class="text-end"><?= number_format($ytdCur['perc'], 2, ',', '.') ?>%</td>
                    <td class="text-end"><?= number_format($ytdPrev['perc'], 2, ',', '.') ?>%</td>
                    <td class="text-end"><?= number_format($deltaPerc, 2, ',', '.') ?> p.p.</td>
                </tr>
                <tr>
                    <td>Custo UTI</td>
                    <td class="text-end">R$ <?= number_format($ytdCur['custo'], 2, ',', '.') ?></td>
                    <td class="text-end">R$ <?= number_format($ytdPrev['custo'], 2, ',', '.') ?></td>
                    <td class="text-end">R$ <?= number_format($deltaCusto, 2, ',', '.') ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
const labels = <?= json_encode(array_values($monthLabels), JSON_UNESCAPED_UNICODE) ?>;
const seriePerc = <?= json_encode(array_values($seriePerc)) ?>;
const serieCusto = <?= json_encode(array_values($serieCusto)) ?>;
const modo = <?= json_encode($modo) ?>;

const scales = window.biChartScales ? window.biChartScales() : undefined;
if (modo === 'custo' && scales && scales.yAxes && scales.yAxes[0] && scales.yAxes[0].ticks) {
    scales.yAxes[0].ticks.callback = function (v) { return window.biMoneyTick ? window.biMoneyTick(v) : ('R$ ' + Number(v || 0).toLocaleString('pt-BR')); };
}
if (modo === 'percentual' && scales && scales.yAxes && scales.yAxes[0] && scales.yAxes[0].ticks) {
    scales.yAxes[0].ticks.callback = function (v) { return Number(v || 0).toLocaleString('pt-BR') + '%'; };
}

new Chart(document.getElementById('chartUtiMain'), {
    type: 'line',
    data: {
        labels,
        datasets: [{
            label: modo === 'custo' ? 'Custo UTI' : '% Internação UTI',
            data: modo === 'custo' ? serieCusto : seriePerc,
            borderColor: 'rgba(126,150,255,0.92)',
            backgroundColor: 'rgba(126,150,255,0.12)',
            borderWidth: 2,
            pointRadius: 3,
            tension: 0.35,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales,
        tooltips: {
            callbacks: {
                label: function (item) {
                    if (modo === 'custo') {
                        return window.biMoneyTick ? window.biMoneyTick(item.yLabel) : ('R$ ' + Number(item.yLabel || 0).toLocaleString('pt-BR'));
                    }
                    return Number(item.yLabel || 0).toLocaleString('pt-BR') + '%';
                }
            }
        }
    }
});
</script>

<?php require_once("templates/footer.php"); ?>
