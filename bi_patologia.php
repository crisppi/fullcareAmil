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
$dataIni = filter_input(INPUT_GET, 'data_ini');
$dataFim = filter_input(INPUT_GET, 'data_fim');

if (!$dataIni || !$dataFim) {
    $stmtRange = $conn->query("
        SELECT
            MIN(data_intern_int) AS min_dt,
            MAX(data_intern_int) AS max_dt
        FROM tb_internacao
        WHERE data_intern_int IS NOT NULL
          AND data_intern_int <> '0000-00-00'
    ");
    $range = $stmtRange->fetch(PDO::FETCH_ASSOC) ?: [];
    $minDt = $range['min_dt'] ?? null;
    $maxDt = $range['max_dt'] ?? null;
    $dataIni = $dataIni ?: ($minDt ?: date('Y-m-d', strtotime('-120 days')));
    $dataFim = $dataFim ?: ($maxDt ?: $hoje);
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

$sqlBase = "
    FROM tb_internacao i
    {$utiJoin}
    LEFT JOIN (
        SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
        FROM tb_alta
        GROUP BY fk_id_int_alt
    ) al ON al.fk_id_int_alt = i.id_internacao
    LEFT JOIN tb_patologia p ON p.id_patologia = i.fk_patologia_int
    LEFT JOIN tb_cid c ON c.id_cid = COALESCE(NULLIF(i.fk_cid_int, 0), NULLIF(p.fk_cid_10_pat, 0))
    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao
    WHERE {$where}
";

function distQuery(PDO $conn, string $labelExpr, string $sqlBase, array $params, string $metric, int $limit = 12): array
{
    $sql = "
        SELECT {$labelExpr} AS label, {$metric} AS total
        {$sqlBase}
        GROUP BY label
        ORDER BY total DESC
        LIMIT {$limit}
    ";
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$labelPat = "COALESCE(NULLIF(CONCAT(c.cat, ' - ', c.descricao), ' - '), NULLIF(c.cat,''), p.patologia_pat, 'Sem informações')";
$labelAcom = "COALESCE(NULLIF(i.acomodacao_int,''), 'Sem informações')";

$rowsAcom = distQuery($conn, $labelAcom, $sqlBase, $params, "COUNT(DISTINCT i.id_internacao)", 8);

// Remover o LIMIT para mostrar todas as patologias
$rowsCusto = distQuery($conn, $labelPat, $sqlBase, $params, "SUM(COALESCE(ca.valor_final_capeante,0))", 10);
$rowsIntern = distQuery($conn, $labelPat, $sqlBase, $params, "COUNT(DISTINCT i.id_internacao)", 10);
$rowsDiárias = distQuery($conn, $labelPat, $sqlBase, $params, "SUM(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1))", 10);
$rowsMp = distQuery($conn, $labelPat, $sqlBase, $params, "ROUND(AVG(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)), 1)", 10);

$rowsCustoMedio = [];
foreach ($rowsCusto as $row) {
    $label = $row['label'] ?? 'Sem informações';
    $valor = (float)($row['total'] ?? 0);
    $count = 0;
    foreach ($rowsIntern as $rInt) {
        if (($rInt['label'] ?? '') === $label) {
            $count = (int)($rInt['total'] ?? 0);
            break;
        }
    }
    $rowsCustoMedio[] = [
        'label' => $label,
        'total' => $count > 0 ? round($valor / $count, 2) : 0,
    ];
}

function labelsAndValues(array $rows, bool $formatMoney = false): array
{
    $labels = array_map(fn($r) => $r['label'] ?? 'Sem informações', $rows);
    $values = array_map(fn($r) => (float)($r['total'] ?? 0), $rows);
    return [$labels, $values];
}

[$labelsAcom, $valuesAcom] = labelsAndValues($rowsAcom);
[$labelsCusto, $valuesCusto] = labelsAndValues($rowsCusto);
[$labelsIntern, $valuesIntern] = labelsAndValues($rowsIntern);
[$labelsDiárias, $valuesDiárias] = labelsAndValues($rowsDiárias);
[$labelsMp, $valuesMp] = labelsAndValues($rowsMp);
[$labelsCustoMedio, $valuesCustoMedio] = labelsAndValues($rowsCustoMedio);
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));
</script>
<style>
    .bi-patologia-chart-sm {
        min-height: 200px;
        height: 200px;
    }

    .bi-patologia-chart-sm canvas {
        height: 200px !important;
    }
</style>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Dashboard Patologia</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted small">
                <?= isset($fonte_conexao) ? 'Fonte: ' . e($fonte_conexao) : '' ?>
            </div>
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <form class="bi-panel bi-filters" method="get">
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
            <input type="date" name="data_ini" value="<?= e($dataIni) ?>">
        </div>
        <div class="bi-filter">
            <label>Data Final</label>
            <input type="date" name="data_fim" value="<?= e($dataFim) ?>">
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="bi-panel" style="margin-top:16px;">
        <h3>Acomodação</h3>
        <div class="bi-chart bi-patologia-chart-sm"><canvas id="chartAcom"></canvas></div>
    </div>

    <div class="bi-grid fixed-2" style="margin-top:16px;">
        <div class="bi-panel">
            <h3>Custo por patologia</h3>
            <div class="bi-chart bi-patologia-chart-sm"><canvas id="chartCusto"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3>Custo médio internação por patologia</h3>
            <div class="bi-chart bi-patologia-chart-sm"><canvas id="chartCustoMedio"></canvas></div>
        </div>
    </div>

    <div class="bi-grid fixed-2" style="margin-top:16px;">
        <div class="bi-panel">
            <h3>Internações por patologia</h3>
            <div class="bi-chart bi-patologia-chart-sm"><canvas id="chartIntern"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3>MP por patologia</h3>
            <div class="bi-chart bi-patologia-chart-sm"><canvas id="chartMp"></canvas></div>
        </div>
    </div>

    <div class="bi-panel" style="margin-top:16px;">
        <h3>Diárias por patologia</h3>
        <div class="bi-chart bi-patologia-chart-sm"><canvas id="chartDiárias"></canvas></div>
    </div>
</div>

<script>
    const labelsAcom = <?= json_encode($labelsAcom) ?>;
    const valuesAcom = <?= json_encode($valuesAcom) ?>;
    const labelsCusto = <?= json_encode($labelsCusto) ?>;
    const valuesCusto = <?= json_encode($valuesCusto) ?>;
    const labelsCustoMedio = <?= json_encode($labelsCustoMedio) ?>;
    const valuesCustoMedio = <?= json_encode($valuesCustoMedio) ?>;
    const labelsIntern = <?= json_encode($labelsIntern) ?>;
    const valuesIntern = <?= json_encode($valuesIntern) ?>;
    const labelsMp = <?= json_encode($labelsMp) ?>;
    const valuesMp = <?= json_encode($valuesMp) ?>;
    const labelsDiárias = <?= json_encode($labelsDiárias) ?>;
    const valuesDiárias = <?= json_encode($valuesDiárias) ?>;

    function buildScales(yTickCallback) {
        const scales = window.biChartScales ? window.biChartScales() : {
            xAxes: [{
                ticks: {}
            }],
            yAxes: [{
                ticks: {}
            }]
        };
        if (!scales.xAxes || !scales.xAxes[0]) {
            scales.xAxes = [{
                ticks: {}
            }];
        }
        if (!scales.yAxes || !scales.yAxes[0]) {
            scales.yAxes = [{
                ticks: {}
            }];
        }
        scales.xAxes[0].ticks = Object.assign({
            display: true,
            padding: 6,
            fontColor: '#eaf6ff',
            autoSkip: false,
            maxRotation: 90,
            minRotation: 45
        }, scales.xAxes[0].ticks || {});
        scales.yAxes[0].ticks = Object.assign({
            display: true,
            beginAtZero: true,
            padding: 8,
            fontColor: '#eaf6ff',
            autoSkip: false,
            maxRotation: 0,
            minRotation: 0,
            mirror: false
        }, scales.yAxes[0].ticks || {});
        if (yTickCallback) {
            scales.yAxes[0].ticks.callback = yTickCallback;
        }
        return scales;
    }

    function barChart(ctx, labels, data, color, yTickCallback) {
        const scales = buildScales(yTickCallback);
        return new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    data,
                    backgroundColor: color
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    display: false
                },
                layout: {
                    padding: {
                        left: 18,
                        right: 8,
                        top: 6,
                        bottom: 6
                    }
                },
                scales
            }
        });
    }

    function horizontalBar(ctx, labels, data, color) {
        const scales = buildScales();
        return new Chart(ctx, {
            type: 'horizontalBar',
            data: {
                labels,
                datasets: [{
                    data,
                    backgroundColor: color
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    display: false
                },
                layout: {
                    padding: {
                        left: 18,
                        right: 8,
                        top: 6,
                        bottom: 6
                    }
                },
                scales
            }
        });
    }

    horizontalBar(document.getElementById('chartAcom'), labelsAcom, valuesAcom, 'rgba(127, 196, 255, 0.7)');
    barChart(document.getElementById('chartCusto'), labelsCusto, valuesCusto, 'rgba(141, 208, 255, 0.7)', window.biMoneyTick);
    barChart(document.getElementById('chartCustoMedio'), labelsCustoMedio, valuesCustoMedio, 'rgba(208, 113, 176, 0.7)', window.biMoneyTick);
    barChart(document.getElementById('chartIntern'), labelsIntern, valuesIntern, 'rgba(121, 199, 255, 0.7)');
    barChart(document.getElementById('chartMp'), labelsMp, valuesMp, 'rgba(111, 223, 194, 0.7)');
    barChart(document.getElementById('chartDiárias'), labelsDiárias, valuesDiárias, 'rgba(255, 198, 108, 0.7)');
</script>

<?php require_once("templates/footer.php"); ?>
