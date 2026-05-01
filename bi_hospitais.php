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

$hoje = date('Y-m-d');
$dataIni = filter_input(INPUT_GET, 'data_ini') ?: date('Y-m-d', strtotime('-180 days'));
$dataFim = filter_input(INPUT_GET, 'data_fim') ?: $hoje;
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

$sqlStats = "
    SELECT
        COUNT(*) AS total_internacoes,
        SUM(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)) AS total_diarias,
        MAX(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)) AS maior_permanencia
    FROM tb_internacao i
    LEFT JOIN (
        SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
        FROM tb_alta
        GROUP BY fk_id_int_alt
    ) al ON al.fk_id_int_alt = i.id_internacao
    {$utiJoin}
    WHERE {$where}
";
$stmt = $conn->prepare($sqlStats);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$totalInternações = (int)($stats['total_internacoes'] ?? 0);
$totalDiárias = (int)($stats['total_diarias'] ?? 0);
$maiorPermanencia = (int)($stats['maior_permanencia'] ?? 0);
$mp = $totalInternações > 0 ? round($totalDiárias / $totalInternações, 1) : 0;

$sqlHosp = "
    SELECT
        h.nome_hosp AS hospital,
        COUNT(*) AS total_internacoes,
        SUM(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)) AS total_diarias,
        AVG(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)) AS mp
    FROM tb_internacao i
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN (
        SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
        FROM tb_alta
        GROUP BY fk_id_int_alt
    ) al ON al.fk_id_int_alt = i.id_internacao
    {$utiJoin}
    WHERE {$where}
    GROUP BY hospital
    ORDER BY total_internacoes DESC
    LIMIT 10
";
$stmt = $conn->prepare($sqlHosp);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$hospRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

function distQuery(PDO $conn, string $field, string $where, array $params, string $label = 'label'): array
{
    $sql = "
        SELECT
            COALESCE(NULLIF({$field}, ''), 'Sem informação') AS {$label},
            COUNT(*) AS total
        FROM tb_internacao i
        LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
        LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
        LEFT JOIN (
            SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
            FROM tb_alta
            GROUP BY fk_id_int_alt
        ) al ON al.fk_id_int_alt = i.id_internacao
        LEFT JOIN (SELECT DISTINCT fk_internacao_uti FROM tb_uti) ut ON ut.fk_internacao_uti = i.id_internacao
        WHERE {$where}
        GROUP BY {$label}
        ORDER BY total DESC
        LIMIT 8
    ";
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$distTipo = distQuery($conn, 'i.tipo_admissao_int', $where, $params, 'tipo');
$distEsp = distQuery($conn, 'i.especialidade_int', $where, $params, 'especialidade');
$distAcom = distQuery($conn, 'i.acomodacao_int', $where, $params, 'acomodacao');
$distSexo = distQuery($conn, 'pa.sexo_pac', $where, $params, 'sexo');
$distModo = distQuery($conn, 'i.modo_internacao_int', $where, $params, 'modo');

$sqlIdade = "
    SELECT
        CASE
            WHEN pa.idade_pac < 20 THEN '0-19'
            WHEN pa.idade_pac < 40 THEN '20-39'
            WHEN pa.idade_pac < 60 THEN '40-59'
            WHEN pa.idade_pac < 80 THEN '60-79'
            WHEN pa.idade_pac IS NULL THEN 'Sem informação'
            ELSE '80+'
        END AS faixa,
        COUNT(*) AS total
    FROM tb_internacao i
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    {$utiJoin}
    WHERE {$where}
    GROUP BY faixa
    ORDER BY total DESC
";
$stmt = $conn->prepare($sqlIdade);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$distIdade = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$labelsHosp = array_map(fn($r) => $r['hospital'] ?: 'Sem hospital', $hospRows);
$valsIntern = array_map(fn($r) => (int)$r['total_internacoes'], $hospRows);
$valsDiárias = array_map(fn($r) => (int)$r['total_diarias'], $hospRows);
$valsMp = array_map(fn($r) => round((float)$r['mp'], 1), $hospRows);

function distToChart(array $rows, string $labelKey): array
{
    return [
        'labels' => array_map(fn($r) => $r[$labelKey], $rows),
        'values' => array_map(fn($r) => (int)$r['total'], $rows),
    ];
}

$tipoChart = distToChart($distTipo, 'tipo');
$espChart = distToChart($distEsp, 'especialidade');
$acomChart = distToChart($distAcom, 'acomodacao');
$sexoChart = distToChart($distSexo, 'sexo');
$modoChart = distToChart($distModo, 'modo');
$idadeChart = distToChart($distIdade, 'faixa');
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Hospitais</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted">Visão de produção e perfil</div>
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
            <label>Tipo internação</label>
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
            <label>Modo admissão</label>
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
            <label>Data inicial</label>
            <input type="date" name="data_ini" value="<?= e($dataIni) ?>">
        </div>
        <div class="bi-filter">
            <label>Data final</label>
            <input type="date" name="data_fim" value="<?= e($dataFim) ?>">
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="bi-panel">
        <div class="bi-kpis">
            <div class="bi-kpi"><small>Diárias</small><strong><?= $totalDiárias ?></strong></div>
            <div class="bi-kpi"><small>Internações</small><strong><?= $totalInternações ?></strong></div>
            <div class="bi-kpi"><small>Maior permanência</small><strong><?= $maiorPermanencia ?></strong></div>
            <div class="bi-kpi"><small>MP</small><strong><?= $mp ?></strong></div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Hospitais - MP / Internações / Diárias</h3>
        <div class="bi-chart">
            <canvas id="chartHosp"></canvas>
        </div>
    </div>

    <div class="bi-grid fixed-3">
        <div class="bi-panel">
            <h4>Tipo admissão</h4>
            <div class="bi-chart"><canvas id="chartTipo"></canvas></div>
        </div>
        <div class="bi-panel">
            <h4>Especialidade</h4>
            <div class="bi-chart"><canvas id="chartEsp"></canvas></div>
        </div>
        <div class="bi-panel">
            <h4>Acomodação</h4>
            <div class="bi-chart"><canvas id="chartAcom"></canvas></div>
        </div>
        <div class="bi-panel">
            <h4>Sexo</h4>
            <div class="bi-chart"><canvas id="chartSexo"></canvas></div>
        </div>
        <div class="bi-panel">
            <h4>Idade</h4>
            <div class="bi-chart"><canvas id="chartIdade"></canvas></div>
        </div>
        <div class="bi-panel">
            <h4>Modo admissão</h4>
            <div class="bi-chart"><canvas id="chartModo"></canvas></div>
        </div>
    </div>
</div>

<script>
const labelsHosp = <?= json_encode($labelsHosp) ?>;
const valsIntern = <?= json_encode($valsIntern) ?>;
const valsDiárias = <?= json_encode($valsDiárias) ?>;
const valsMp = <?= json_encode($valsMp) ?>;

function barMulti(ctx) {
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labelsHosp,
            datasets: [
                { label: 'Internações', data: valsIntern, backgroundColor: 'rgba(141, 208, 255, 0.6)' },
                { label: 'Diárias', data: valsDiárias, backgroundColor: 'rgba(208, 113, 176, 0.6)' },
                { label: 'MP', data: valsMp, backgroundColor: 'rgba(111, 223, 194, 0.6)' }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: window.biLegendWhite || {},
            scales: window.biChartScales ? window.biChartScales() : undefined
        }
    });
}

function pieChart(ctx, data) {
    return new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.values,
                backgroundColor: ['#7cc4ff','#c06ea3','#5fd3b5','#ffc56c','#a2b5ff','#ff8fb1','#8aa1ff','#66d7ff']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: window.biLegendWhite || {}
        }
    });
}

barMulti(document.getElementById('chartHosp'));
pieChart(document.getElementById('chartTipo'), <?= json_encode($tipoChart) ?>);
pieChart(document.getElementById('chartEsp'), <?= json_encode($espChart) ?>);
pieChart(document.getElementById('chartAcom'), <?= json_encode($acomChart) ?>);
pieChart(document.getElementById('chartSexo'), <?= json_encode($sexoChart) ?>);
pieChart(document.getElementById('chartIdade'), <?= json_encode($idadeChart) ?>);
pieChart(document.getElementById('chartModo'), <?= json_encode($modoChart) ?>);
</script>

<?php require_once("templates/footer.php"); ?>
