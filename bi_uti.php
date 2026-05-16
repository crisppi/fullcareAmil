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
            MIN(i.data_intern_int) AS min_dt,
            MAX(i.data_intern_int) AS max_dt
        FROM tb_internacao i
        INNER JOIN tb_uti u ON u.fk_internacao_uti = i.id_internacao
        WHERE i.data_intern_int IS NOT NULL
          AND i.data_intern_int <> '0000-00-00'
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
$internadoUti = trim((string)(filter_input(INPUT_GET, 'internado_uti') ?? ''));

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
if ($internadoUti !== '') {
    $where .= " AND u.internado_uti = :internado_uti";
    $params[':internado_uti'] = $internadoUti;
}

$utiJoin = "
    INNER JOIN (
        SELECT u1.*
        FROM tb_uti u1
        INNER JOIN (
            SELECT fk_internacao_uti, MAX(id_uti) AS max_id
            FROM tb_uti
            GROUP BY fk_internacao_uti
        ) u2 ON u2.max_id = u1.id_uti
    ) u ON u.fk_internacao_uti = i.id_internacao
";

$sqlBase = "
    FROM tb_internacao i
    {$utiJoin}
    LEFT JOIN (
        SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
        FROM tb_alta
        GROUP BY fk_id_int_alt
    ) al ON al.fk_id_int_alt = i.id_internacao
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    WHERE {$where}
";

function distQuery(PDO $conn, string $labelExpr, string $sqlBase, array $params, int $limit = 12): array
{
    $sql = "
        SELECT {$labelExpr} AS label, COUNT(*) AS total
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

$rowsScore = distQuery($conn, "COALESCE(NULLIF(u.score_uti,''), 'Sem informações')", $sqlBase, $params);
$rowsMotivo = distQuery($conn, "COALESCE(NULLIF(u.motivo_uti,''), 'Sem informações')", $sqlBase, $params);
$rowsJust = distQuery($conn, "COALESCE(NULLIF(u.just_uti,''), 'Sem informações')", $sqlBase, $params, 6);
$rowsUti = distQuery($conn, "CASE WHEN u.internado_uti = 's' THEN 'Sim' WHEN u.internado_uti = 'n' THEN 'Não' ELSE 'Sem informações' END", $sqlBase, $params, 6);
$rowsDva = distQuery($conn, "CASE WHEN u.dva_uti = 's' THEN 'Sim' WHEN u.dva_uti = 'n' THEN 'Não' ELSE 'Sem informações' END", $sqlBase, $params, 6);
$rowsSaps = distQuery($conn, "COALESCE(NULLIF(u.saps_uti,''), 'Sem informações')", $sqlBase, $params, 10);
$rowsSexo = distQuery($conn, "COALESCE(NULLIF(pa.sexo_pac,''), 'Sem informações')", $sqlBase, $params, 6);
$rowsIdade = distQuery($conn, "CASE
        WHEN pa.idade_pac IS NULL THEN 'Sem informações'
        WHEN pa.idade_pac < 20 THEN '0-19'
        WHEN pa.idade_pac < 40 THEN '20-39'
        WHEN pa.idade_pac < 60 THEN '40-59'
        WHEN pa.idade_pac < 80 THEN '60-79'
        ELSE '80+'
    END", $sqlBase, $params, 6);

$sqlKpi = "
    SELECT
        COUNT(DISTINCT i.id_internacao) AS total_internacoes,
        SUM(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)) AS total_diarias,
        MAX(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)) AS maior_permanencia
    {$sqlBase}
";
$stmt = $conn->prepare($sqlKpi);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$kpis = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$totalInternacoes = (int)($kpis['total_internacoes'] ?? 0);
$totalDiarias = (int)($kpis['total_diarias'] ?? 0);
$maiorPermanencia = (int)($kpis['maior_permanencia'] ?? 0);
$mp = $totalInternacoes > 0 ? round($totalDiarias / $totalInternacoes, 1) : 0.0;

function labelsAndValues(array $rows): array
{
    $labels = array_map(fn($r) => $r['label'] ?? 'Sem informações', $rows);
    $values = array_map(fn($r) => (int)($r['total'] ?? 0), $rows);
    return [$labels, $values];
}

[$labelsScore, $valuesScore] = labelsAndValues($rowsScore);
[$labelsMotivo, $valuesMotivo] = labelsAndValues($rowsMotivo);
[$labelsJust, $valuesJust] = labelsAndValues($rowsJust);
[$labelsUti, $valuesUti] = labelsAndValues($rowsUti);
[$labelsDva, $valuesDva] = labelsAndValues($rowsDva);
[$labelsSaps, $valuesSaps] = labelsAndValues($rowsSaps);
[$labelsSexo, $valuesSexo] = labelsAndValues($rowsSexo);
[$labelsIdade, $valuesIdade] = labelsAndValues($rowsIdade);
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260509-filter-icons"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Dashboard UTI</h1>
        <div class="bi-header-actions">
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
            <select name="internado_uti">
                <option value="">Todos</option>
                <option value="s" <?= $internadoUti === 's' ? 'selected' : '' ?>>Sim</option>
                <option value="n" <?= $internadoUti === 'n' ? 'selected' : '' ?>>Não</option>
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

    <div class="bi-row" style="margin-top:16px;">
        <div class="bi-panel">
            <h3>Score</h3>
            <div class="bi-chart"><canvas id="chartScore"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3>Motivo UTI</h3>
            <div class="bi-chart"><canvas id="chartMotivo"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3>Justificativa</h3>
            <div class="bi-chart"><canvas id="chartJust"></canvas></div>
        </div>
    </div>

    <div class="bi-row" style="margin-top:16px;">
        <div class="bi-panel">
            <h3>UTI Selecionado</h3>
            <div class="bi-chart"><canvas id="chartUti"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3>DVA</h3>
            <div class="bi-chart"><canvas id="chartDva"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3>SAPS</h3>
            <div class="bi-chart"><canvas id="chartSaps"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3>Sexo</h3>
            <div class="bi-chart"><canvas id="chartSexo"></canvas></div>
        </div>
    </div>

    <div class="bi-row" style="margin-top:16px;">
        <div class="bi-panel">
            <h3>Idade</h3>
            <div class="bi-chart"><canvas id="chartIdade"></canvas></div>
        </div>
        <div class="bi-panel">
            <div class="bi-kpis">
                <div class="bi-kpi">
                    <small>Internações</small>
                    <strong><?= $totalInternacoes ?></strong>
                </div>
                <div class="bi-kpi">
                    <small>Diárias</small>
                    <strong><?= $totalDiarias ?></strong>
                </div>
                <div class="bi-kpi">
                    <small>MP</small>
                    <strong><?= number_format($mp, 1, ',', '.') ?></strong>
                </div>
                <div class="bi-kpi">
                    <small>Maior Permanência</small>
                    <strong><?= $maiorPermanencia ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const labelsScore = <?= json_encode($labelsScore) ?>;
const valuesScore = <?= json_encode($valuesScore) ?>;
const labelsMotivo = <?= json_encode($labelsMotivo) ?>;
const valuesMotivo = <?= json_encode($valuesMotivo) ?>;
const labelsJust = <?= json_encode($labelsJust) ?>;
const valuesJust = <?= json_encode($valuesJust) ?>;
const labelsUti = <?= json_encode($labelsUti) ?>;
const valuesUti = <?= json_encode($valuesUti) ?>;
const labelsDva = <?= json_encode($labelsDva) ?>;
const valuesDva = <?= json_encode($valuesDva) ?>;
const labelsSaps = <?= json_encode($labelsSaps) ?>;
const valuesSaps = <?= json_encode($valuesSaps) ?>;
const labelsSexo = <?= json_encode($labelsSexo) ?>;
const valuesSexo = <?= json_encode($valuesSexo) ?>;
const labelsIdade = <?= json_encode($labelsIdade) ?>;
const valuesIdade = <?= json_encode($valuesIdade) ?>;

function barChart(ctx, labels, data, color) {
    return new Chart(ctx, {
        type: 'bar',
        data: { labels, datasets: [{ data, backgroundColor: color }] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: { display: false },
            scales: window.biChartScales ? window.biChartScales() : undefined
        }
    });
}

function pieChart(ctx, labels, data) {
    return new Chart(ctx, {
        type: 'pie',
        data: { labels, datasets: [{ data, backgroundColor: ['#7db8ff','#c17ac3','#4fc1b5','#f2b96b','#8b9fff','#6ac9ff'] }] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: window.biLegendWhite || {}
        }
    });
}

barChart(document.getElementById('chartScore'), labelsScore, valuesScore, 'rgba(141, 208, 255, 0.7)');
barChart(document.getElementById('chartMotivo'), labelsMotivo, valuesMotivo, 'rgba(208, 113, 176, 0.7)');
pieChart(document.getElementById('chartJust'), labelsJust, valuesJust);
pieChart(document.getElementById('chartUti'), labelsUti, valuesUti);
pieChart(document.getElementById('chartDva'), labelsDva, valuesDva);
barChart(document.getElementById('chartSaps'), labelsSaps, valuesSaps, 'rgba(127, 196, 255, 0.7)');
pieChart(document.getElementById('chartSexo'), labelsSexo, valuesSexo);
pieChart(document.getElementById('chartIdade'), labelsIdade, valuesIdade);
</script>

<?php require_once("templates/footer.php"); ?>
