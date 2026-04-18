<?php
include_once("check_logado.php");
require_once("templates/header.php");

if (!isset($conn) || !($conn instanceof PDO)) {
    die("Conexão inválida.");
}

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$anoInput = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
$ano = ($anoInput !== null && $anoInput !== false) ? (int)$anoInput : null;
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$auditorId = filter_input(INPUT_GET, 'auditor_id', FILTER_VALIDATE_INT) ?: null;

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")->fetchAll(PDO::FETCH_ASSOC);
$auditores = $conn->query("SELECT id_usuario, usuario_user FROM tb_user ORDER BY usuario_user")->fetchAll(PDO::FETCH_ASSOC);
$auditorOptions = array_column($auditores, 'usuario_user', 'id_usuario');
$negociacaoRealClause = "UPPER(COALESCE(ng.tipo_negociacao, '')) <> 'PRORROGACAO_AUTOMATICA'";
$savingExpr = "COALESCE(ng.saving, 0)";

if ($ano === null && !filter_has_var(INPUT_GET, 'ano')) {
    $stmtAno = $conn->query("SELECT MAX(YEAR(data_inicio_neg)) AS ano FROM tb_negociacao WHERE data_inicio_neg IS NOT NULL AND data_inicio_neg <> '0000-00-00'");
    $anoDb = $stmtAno->fetch(PDO::FETCH_ASSOC) ?: [];
    $ano = (int)($anoDb['ano'] ?? date('Y'));
}

$where = "ng.data_inicio_neg IS NOT NULL
    AND ng.data_inicio_neg <> '0000-00-00'
    AND ng.saving IS NOT NULL
    AND COALESCE(ng.fk_usuario_neg, 0) > 0
    AND {$negociacaoRealClause}
    AND COALESCE(ng.saving, 0) <> 0
    AND YEAR(ng.data_inicio_neg) = :ano";
$params = [':ano' => $ano];

if ($hospitalId) {
    $where .= " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = $hospitalId;
}
if ($auditorId) {
    $where .= " AND ng.fk_usuario_neg = :auditor_id";
    $params[':auditor_id'] = $auditorId;
}

$sqlTotals = "
    SELECT
        SUM({$savingExpr}) AS total_saving,
        COUNT(DISTINCT ng.id_negociacao) AS total_registros,
        AVG({$savingExpr}) AS avg_saving
    FROM tb_negociacao ng
    INNER JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int
    WHERE {$where}
";

$stmt = $conn->prepare($sqlTotals);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$totals = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$totalSaving = (float)($totals['total_saving'] ?? 0);
$totalRegistros = (int)($totals['total_registros'] ?? 0);
$mediaSaving = (float)($totals['avg_saving'] ?? 0);

$sqlAuditorResumo = "
    SELECT
        ng.fk_usuario_neg AS auditor_id,
        COALESCE(u.usuario_user, 'Sem auditor') AS auditor,
        SUM({$savingExpr}) AS total_saving,
        COUNT(DISTINCT ng.id_negociacao) AS total_registros
    FROM tb_negociacao ng
    INNER JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int
    LEFT JOIN tb_user u ON u.id_usuario = ng.fk_usuario_neg
    WHERE {$where}
    GROUP BY auditor_id, auditor
    ORDER BY total_saving DESC
    LIMIT 20
";

$stmt = $conn->prepare($sqlAuditorResumo);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$auditorResumo = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$selectedAuditorLabel = $auditorId ? ($auditorOptions[$auditorId] ?? 'Auditor selecionado') : 'Todos os auditores';

$timelineAuditores = [];
$seen = [];
if ($auditorId) {
    $timelineAuditores[] = [
        'id' => $auditorId,
        'label' => $selectedAuditorLabel,
    ];
    $seen[$auditorId] = true;
}
foreach ($auditorResumo as $row) {
    if (count($timelineAuditores) >= 4) {
        break;
    }
    $auditorKey = (int)($row['auditor_id'] ?? 0);
    if ($auditorKey <= 0 || isset($seen[$auditorKey])) {
        continue;
    }
    $timelineAuditores[] = [
        'id' => $auditorKey,
        'label' => $row['auditor'] ?: ($auditorOptions[$auditorKey] ?? 'Sem auditor'),
    ];
    $seen[$auditorKey] = true;
}

$sqlMonthly = "
    SELECT
        MONTH(ng.data_inicio_neg) AS mes,
        ng.fk_usuario_neg AS auditor_id,
        SUM({$savingExpr}) AS total_saving
    FROM tb_negociacao ng
    INNER JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int
    WHERE {$where}
    GROUP BY mes, auditor_id
    ORDER BY mes, auditor_id
";

$stmt = $conn->prepare($sqlMonthly);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$monthlyRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$monthNames = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
$lineMonthlyByAuditor = [];
foreach ($timelineAuditores as $auditor) {
    $lineMonthlyByAuditor[$auditor['id']] = array_fill(1, 12, 0.0);
}
$totalMonthly = array_fill(1, 12, 0.0);
foreach ($monthlyRows as $row) {
    $mes = (int)($row['mes'] ?? 0);
    if ($mes < 1 || $mes > 12) {
        continue;
    }
    $auditorKey = (int)($row['auditor_id'] ?? 0);
    $valor = (float)($row['total_saving'] ?? 0);
    $totalMonthly[$mes] += $valor;
    if (isset($lineMonthlyByAuditor[$auditorKey])) {
        $lineMonthlyByAuditor[$auditorKey][$mes] = $valor;
    }
}

$lineDatasets = [[
    'label' => 'Total saving',
    'data' => array_values($totalMonthly),
    'borderColor' => 'rgba(18, 65, 134, 0.85)',
    'backgroundColor' => 'rgba(18, 65, 134, 0.08)',
    'borderWidth' => 2,
    'tension' => 0.35,
    'pointRadius' => 2,
    'fill' => true,
]];

$lineColors = [
    'rgba(72, 154, 255, 0.8)',
    'rgba(255, 161, 64, 0.8)',
    'rgba(255, 99, 132, 0.8)',
    'rgba(0, 200, 150, 0.8)',
];
$colorIndex = 0;
foreach ($timelineAuditores as $auditor) {
    $audProducts = $lineMonthlyByAuditor[$auditor['id']] ?? array_fill(1, 12, 0.0);
    $lineDatasets[] = [
        'label' => $auditor['label'],
        'data' => array_values($audProducts),
        'borderColor' => $lineColors[$colorIndex % count($lineColors)],
        'backgroundColor' => 'rgba(0, 0, 0, 0)',
        'borderWidth' => 2,
        'pointRadius' => 3,
        'tension' => 0.3,
        'fill' => false,
    ];
    $colorIndex++;
}
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260411d">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260411d"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme bi-ie-page">
    <div class="bi-header">
        <h1 class="bi-title">Audit Saving</h1>
        <div class="bi-header-actions">
            <span class="text-muted small">Filtro: <?= e($selectedAuditorLabel) ?> <?= e($hospitalId ? '| Hospital ID ' . $hospitalId : '') ?></span>
            <a class="bi-btn bi-btn-secondary" href="<?= $BASE_URL ?>bi/negociacoes-detalhadas" title="Negociações Detalhadas">Negociações</a>
            <a class="bi-btn bi-btn-secondary" href="<?= $BASE_URL ?>bi/saving" title="Dashboard Saving">Saving</a>
        </div>
    </div>

    <form class="bi-panel bi-filters" method="get">
        <div class="bi-filter">
            <label>Ano</label>
            <input type="number" name="ano" value="<?= e($ano) ?>">
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
            <label>Auditor</label>
            <select name="auditor_id">
                <option value="">Todos</option>
                <?php foreach ($auditores as $a): ?>
                    <option value="<?= (int)$a['id_usuario'] ?>" <?= $auditorId == $a['id_usuario'] ? 'selected' : '' ?>>
                        <?= e($a['usuario_user']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="bi-panel">
        <div class="bi-kpis kpi-dashboard-v2">
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-1">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-piggy-bank"></i></span>
                    <small>Total saving</small>
                </div>
                <strong>R$ <?= number_format($totalSaving, 2, ',', '.') ?></strong>
                <span class="kpi-trend kpi-trend-up"><i class="bi bi-cash-stack"></i>Resultado consolidado</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-2">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-list-ol"></i></span>
                    <small>Negociações</small>
                </div>
                <strong><?= $totalRegistros ?></strong>
                <span class="kpi-trend kpi-trend-neutral"><i class="bi bi-file-earmark-bar-graph"></i>Itens no período</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-3">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-calculator"></i></span>
                    <small>Ticket médio</small>
                </div>
                <strong>R$ <?= number_format($mediaSaving, 2, ',', '.') ?></strong>
                <span class="kpi-trend kpi-trend-neutral"><i class="bi bi-graph-up"></i>Média por negociação</span>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Resumo por auditor</h3>
        <?php if (!empty($auditorResumo)): ?>
            <div class="bi-table-wrapper">
                <table class="bi-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Auditor</th>
                            <th class="text-end">Saving (R$)</th>
                            <th class="text-end">Negociações</th>
                            <th class="text-end">% do total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditorResumo as $index => $row):
                            $valor = (float)($row['total_saving'] ?? 0);
                            $quantidade = (int)($row['total_registros'] ?? 0);
                            $participacao = $totalSaving > 0 ? ($valor / $totalSaving) * 100 : 0;
                        ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= e($row['auditor'] ?: 'Sem auditor') ?></td>
                                <td class="text-end">R$ <?= number_format($valor, 2, ',', '.') ?></td>
                                <td class="text-end"><?= $quantidade ?></td>
                                <td class="text-end"><?= number_format($participacao, 2, ',', '.') ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-muted">Nenhum registro encontrado para o filtro aplicado.</div>
        <?php endif; ?>
    </div>

    <div class="bi-panel">
        <h3>Evolução mensal <?= $auditorId ? 'de ' . e($selectedAuditorLabel) : 'do saving' ?></h3>
        <div class="bi-chart ie-chart-md">
            <canvas id="chartSavingTimeline"></canvas>
        </div>
    </div>
</div>

<script>
const timelineMonths = <?= json_encode(array_values($monthNames)) ?>;
const lineChartDatasets = <?= json_encode($lineDatasets, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

function lineChart(ctx, labels, datasets) {
    const scales = window.biChartScales ? window.biChartScales() : undefined;
    if (scales && scales.yAxes && scales.yAxes[0] && scales.yAxes[0].ticks) {
        scales.yAxes[0].ticks.callback = function (value) {
            return window.biMoneyTick ? window.biMoneyTick(value) : ('R$ ' + Number(value || 0).toLocaleString('pt-BR'));
        };
    }
    return new Chart(ctx, {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: scales,
            tooltips: {
                callbacks: {
                    label: function (tooltipItem, data) {
                        const ds = data.datasets[tooltipItem.datasetIndex] || {};
                        const label = ds.label ? ds.label + ': ' : '';
                        const value = window.biMoneyTick ? window.biMoneyTick(tooltipItem.yLabel) : ('R$ ' + Number(tooltipItem.yLabel || 0).toLocaleString('pt-BR'));
                        return label + value;
                    }
                }
            }
        }
    });
}

const timelineCanvas = document.getElementById('chartSavingTimeline');
if (timelineCanvas) {
    lineChart(timelineCanvas, timelineMonths, lineChartDatasets);
}
</script>

<?php require_once("templates/footer.php"); ?>
