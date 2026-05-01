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
$ano = ($anoInput !== null && $anoInput !== false) ? (int)$anoInput : null;
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$seguradoraId = filter_input(INPUT_GET, 'seguradora_id', FILTER_VALIDATE_INT) ?: null;
$patologiaId = filter_input(INPUT_GET, 'patologia_id', FILTER_VALIDATE_INT) ?: null;

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);
$seguradoras = $conn->query("SELECT id_seguradora, seguradora_seg FROM tb_seguradora ORDER BY seguradora_seg")
    ->fetchAll(PDO::FETCH_ASSOC);
$patologias = $conn->query("SELECT id_patologia, patologia_pat FROM tb_patologia ORDER BY patologia_pat")
    ->fetchAll(PDO::FETCH_ASSOC);
$anos = $conn->query("SELECT DISTINCT YEAR(data_intern_int) AS ano FROM tb_internacao WHERE data_intern_int IS NOT NULL AND data_intern_int <> '0000-00-00' ORDER BY ano DESC")
    ->fetchAll(PDO::FETCH_COLUMN);

if ($ano === null && !filter_has_var(INPUT_GET, 'ano')) {
    $ano = !empty($anos) ? (int)$anos[0] : (int)date('Y');
}

$dateExpr = "COALESCE(NULLIF(ca.data_inicial_capeante,'0000-00-00'), NULLIF(ca.data_digit_capeante,'0000-00-00'), NULLIF(ca.data_fech_capeante,'0000-00-00'))";
$where = "YEAR(ref_date) = :ano";
$params = [':ano' => (int)$ano];
if (!empty($hospitalId)) {
    $where .= " AND fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = (int)$hospitalId;
}
if (!empty($seguradoraId)) {
    $where .= " AND fk_seguradora_pac = :seguradora_id";
    $params[':seguradora_id'] = (int)$seguradoraId;
}
if (!empty($patologiaId)) {
    $where .= " AND fk_patologia_int = :patologia_id";
    $params[':patologia_id'] = (int)$patologiaId;
}

$sql = "
    SELECT
        MONTH(ref_date) AS mes,
        SUM(valor_apresentado_capeante) AS valor_apresentado,
        SUM(valor_glosa_total) AS valor_glosa,
        SUM(valor_final_capeante) AS valor_final
    FROM (
        SELECT
            ca.valor_apresentado_capeante,
            ca.valor_glosa_total,
            ca.valor_final_capeante,
            {$dateExpr} AS ref_date,
            ac.fk_hospital_int,
            ac.fk_patologia_int,
            pa.fk_seguradora_pac
        FROM tb_capeante ca
        INNER JOIN tb_internacao ac ON ac.id_internacao = ca.fk_int_capeante
        LEFT JOIN tb_paciente pa ON pa.id_paciente = ac.fk_paciente_int
    ) t
    WHERE ref_date IS NOT NULL AND ref_date <> '0000-00-00'
      AND {$where}
    GROUP BY mes
    ORDER BY mes
";
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$series = [
    'valor_apresentado' => array_fill(1, 12, 0.0),
    'valor_glosa' => array_fill(1, 12, 0.0),
    'valor_final' => array_fill(1, 12, 0.0),
];
foreach ($rows as $row) {
    $m = (int)($row['mes'] ?? 0);
    if ($m < 1 || $m > 12) continue;
    $series['valor_apresentado'][$m] = (float)($row['valor_apresentado'] ?? 0);
    $series['valor_glosa'][$m] = (float)($row['valor_glosa'] ?? 0);
    $series['valor_final'][$m] = (float)($row['valor_final'] ?? 0);
}
$labels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
$totalFinal = array_sum($series['valor_final']);
$totalGlosa = array_sum($series['valor_glosa']);
$totalApresentado = array_sum($series['valor_apresentado']);
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));
</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Tendência de Custo</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted">Ano <?= e($ano) ?></div>
        </div>
    </div>

    <form class="bi-panel bi-filters" method="get">
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
            <label>Patologia</label>
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
            <small>Apresentado</small>
            <strong>R$ <?= number_format($totalApresentado, 2, ',', '.') ?></strong>
        </div>
        <div class="bi-kpi kpi-amber kpi-compact">
            <small>Glosa</small>
            <strong>R$ <?= number_format($totalGlosa, 2, ',', '.') ?></strong>
        </div>
        <div class="bi-kpi kpi-teal kpi-compact">
            <small>Final</small>
            <strong>R$ <?= number_format($totalFinal, 2, ',', '.') ?></strong>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Evolução mensal</h3>
        <div class="bi-chart"><canvas id="chartTendencia"></canvas></div>
    </div>
</div>

<script>
    const trendLabels = <?= json_encode($labels) ?>;
    const serieApresentado = <?= json_encode(array_values($series['valor_apresentado'])) ?>;
    const serieGlosa = <?= json_encode(array_values($series['valor_glosa'])) ?>;
    const serieFinal = <?= json_encode(array_values($series['valor_final'])) ?>;
    new Chart(document.getElementById('chartTendencia'), {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                    label: 'Apresentado',
                    data: serieApresentado,
                    borderColor: 'rgba(255, 209, 102, 0.9)',
                    backgroundColor: 'rgba(255, 209, 102, 0.2)',
                    fill: true
                },
                {
                    label: 'Glosa',
                    data: serieGlosa,
                    borderColor: 'rgba(255, 140, 140, 0.9)',
                    backgroundColor: 'rgba(255, 140, 140, 0.2)',
                    fill: true
                },
                {
                    label: 'Final',
                    data: serieFinal,
                    borderColor: 'rgba(126,150,255,0.95)',
                    backgroundColor: 'rgba(126,150,255,0.25)',
                    fill: true
                }
            ]
        },
        options: {
            scales: biChartScales(),
            legend: biLegendWhite,
            tooltips: {
                callbacks: {
                    label: (item) => biMoneyTick(item.yLabel)
                }
            }
        }
    });
</script>

<?php require_once("templates/footer.php"); ?>