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

$anoInput = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
$ano = ($anoInput !== null && $anoInput !== false) ? (int)$anoInput : (int)date('Y');
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);
$anos = $conn->query("SELECT DISTINCT YEAR(data_intern_int) AS ano FROM tb_internacao WHERE data_intern_int IS NOT NULL AND data_intern_int <> '0000-00-00' ORDER BY ano DESC")
    ->fetchAll(PDO::FETCH_COLUMN);
if ($ano && !in_array($ano, $anos, true)) {
    $anos[] = $ano;
    rsort($anos);
}

$dateExpr = "COALESCE(NULLIF(ca.data_inicial_capeante,'0000-00-00'), NULLIF(ca.data_digit_capeante,'0000-00-00'), NULLIF(ca.data_fech_capeante,'0000-00-00'))";
$where = "YEAR(ref_date) = :ano";
$params = [':ano' => $ano];
if (!empty($hospitalId)) {
    $where .= " AND fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = (int)$hospitalId;
}

$sql = "
    SELECT
        MONTH(ref_date) AS mes,
        SUM(valor_apresentado_capeante) AS valor_apresentado,
        SUM(valor_glosa_total) AS valor_glosa,
        SUM(valor_final_capeante) AS valor_final,
        SUM(diarias) AS total_diarias
    FROM (
        SELECT
            ca.valor_apresentado_capeante,
            ca.valor_glosa_total,
            ca.valor_final_capeante,
            {$dateExpr} AS ref_date,
            ac.fk_hospital_int,
            GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), ac.data_intern_int) + 1) AS diarias
        FROM tb_capeante ca
        INNER JOIN tb_internacao ac ON ac.id_internacao = ca.fk_int_capeante
        LEFT JOIN (
            SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
            FROM tb_alta
            GROUP BY fk_id_int_alt
        ) al ON al.fk_id_int_alt = ac.id_internacao
    ) t
    WHERE ref_date IS NOT NULL AND ref_date <> '0000-00-00'
      AND {$where}
    GROUP BY mes
    ORDER BY mes
";
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$labels = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
$series = [
    'apresentado' => array_fill(1, 12, 0.0),
    'glosa' => array_fill(1, 12, 0.0),
    'final' => array_fill(1, 12, 0.0),
];
foreach ($rows as $row) {
    $m = (int)($row['mes'] ?? 0);
    if ($m < 1 || $m > 12) continue;
    $diarias = (float)($row['total_diarias'] ?? 0);
    $series['apresentado'][$m] = $diarias > 0 ? (float)$row['valor_apresentado'] / $diarias : 0.0;
    $series['glosa'][$m] = $diarias > 0 ? (float)$row['valor_glosa'] / $diarias : 0.0;
    $series['final'][$m] = $diarias > 0 ? (float)$row['valor_final'] / $diarias : 0.0;
}
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260516-rounded-bars"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Dashboard Custo Médio Diárias</h1>
        <div class="bi-header-actions"></div>
    </div>

    <div class="bi-layout">
        <aside class="bi-sidebar bi-stack">
            <div class="bi-filter-card">
                <div class="bi-filter-card-header">Filtros</div>
                <div class="bi-filter-card-body bi-stack">
                    <div class="bi-filter">
                        <label>Ano</label>
                        <select name="ano" form="custo-form">
                            <?php foreach ($anos as $anoOpt): ?>
                                <option value="<?= (int)$anoOpt ?>" <?= (int)$anoOpt === (int)$ano ? 'selected' : '' ?>>
                                    <?= (int)$anoOpt ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="bi-filter">
                        <label>Hospital</label>
                        <select name="hospital_id" form="custo-form">
                            <option value="">Todos</option>
                            <?php foreach ($hospitais as $h): ?>
                                <option value="<?= (int)$h['id_hospital'] ?>" <?= $hospitalId == $h['id_hospital'] ? 'selected' : '' ?>>
                                    <?= e($h['nome_hosp']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <form id="custo-form" method="get">
                        <button class="bi-filter-btn" type="submit">Aplicar</button>
                    </form>
                </div>
            </div>
        </aside>

        <section class="bi-main bi-stack">
            <div class="bi-panel">
                <h3>Valor Apresentado (médio por diária)</h3>
                <div class="bi-chart"><canvas id="chartApresentado"></canvas></div>
            </div>
            <div class="bi-panel">
                <h3>Total Glosa (médio por diária)</h3>
                <div class="bi-chart"><canvas id="chartGlosa"></canvas></div>
            </div>
            <div class="bi-panel">
                <h3>Valor Final (médio por diária)</h3>
                <div class="bi-chart"><canvas id="chartFinal"></canvas></div>
            </div>
        </section>
    </div>
</div>

<script>
const custoLabels = <?= json_encode($labels) ?>;
const serieApresentado = <?= json_encode(array_values($series['apresentado'])) ?>;
const serieGlosa = <?= json_encode(array_values($series['glosa'])) ?>;
const serieFinal = <?= json_encode(array_values($series['final'])) ?>;

function buildBarChart(el, data, color) {
  new Chart(el, {
    type: 'bar',
    data: {
      labels: custoLabels,
      datasets: [{
        data: data,
        backgroundColor: color,
        borderRadius: 8
      }]
    },
    options: {
      legend: { display: false },
      scales: biChartScales(),
      tooltips: {
        callbacks: { label: (item) => biMoneyTick(item.yLabel) }
      }
    }
  });
}

buildBarChart(document.getElementById('chartApresentado'), serieApresentado, 'rgba(126,150,255,0.8)');
buildBarChart(document.getElementById('chartGlosa'), serieGlosa, 'rgba(255, 140, 140, 0.8)');
buildBarChart(document.getElementById('chartFinal'), serieFinal, 'rgba(93, 196, 140, 0.8)');
</script>

<?php require_once("templates/footer.php"); ?>
