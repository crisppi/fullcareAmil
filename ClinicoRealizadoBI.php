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

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);

$params = [':start' => $startStr, ':end' => $endStr];
$whereHosp = '';
if ($hospitalId) {
    $whereHosp = " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = (int)$hospitalId;
}

// Permanência média por patologia
$sqlPermanencia = "
    SELECT COALESCE(p.patologia_pat, 'Sem informações') AS label,
           AVG(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)) AS media_dias
    FROM tb_internacao i
    LEFT JOIN (
        SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
        FROM tb_alta
        GROUP BY fk_id_int_alt
    ) al ON al.fk_id_int_alt = i.id_internacao
    LEFT JOIN tb_patologia p ON p.id_patologia = i.fk_patologia_int
    WHERE i.data_intern_int BETWEEN :start AND :end
      {$whereHosp}
    GROUP BY p.id_patologia
    ORDER BY media_dias DESC
    LIMIT 12
";
$stmt = $conn->prepare($sqlPermanencia);
$stmt->execute($params);
$permRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$permLabels = array_map(fn($r) => $r['label'], $permRows);
$permVals = array_map(fn($r) => round((float)$r['media_dias'], 1), $permRows);

// Readmissão 30d por hospital
$sqlReadmHosp = "
    SELECT ho.nome_hosp AS label,
           COUNT(*) AS total_altas,
           SUM(
             CASE WHEN EXISTS (
                SELECT 1
                FROM tb_internacao i2
                WHERE i2.fk_paciente_int = i.fk_paciente_int
                  AND i2.data_intern_int > al.data_alta_alt
                  AND i2.data_intern_int <= DATE_ADD(al.data_alta_alt, INTERVAL 30 DAY)
             ) THEN 1 ELSE 0 END
           ) AS readm
    FROM tb_alta al
    JOIN tb_internacao i ON i.id_internacao = al.fk_id_int_alt
    JOIN tb_hospital ho ON ho.id_hospital = i.fk_hospital_int
    WHERE al.data_alta_alt BETWEEN :start AND :end
      {$whereHosp}
    GROUP BY ho.id_hospital
    ORDER BY readm DESC
    LIMIT 12
";
$stmt = $conn->prepare($sqlReadmHosp);
$stmt->execute($params);
$readmHospRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$readmHospLabels = [];
$readmHospRates = [];
foreach ($readmHospRows as $row) {
    $total = (int)($row['total_altas'] ?? 0);
    $readm = (int)($row['readm'] ?? 0);
    $readmHospLabels[] = $row['label'];
    $readmHospRates[] = $total > 0 ? round(($readm / $total) * 100, 2) : 0;
}

// Readmissão 30d por seguradora
$sqlReadmSeg = "
    SELECT COALESCE(s.seguradora_seg, 'Sem informações') AS label,
           COUNT(*) AS total_altas,
           SUM(
             CASE WHEN EXISTS (
                SELECT 1
                FROM tb_internacao i2
                WHERE i2.fk_paciente_int = i.fk_paciente_int
                  AND i2.data_intern_int > al.data_alta_alt
                  AND i2.data_intern_int <= DATE_ADD(al.data_alta_alt, INTERVAL 30 DAY)
             ) THEN 1 ELSE 0 END
           ) AS readm
    FROM tb_alta al
    JOIN tb_internacao i ON i.id_internacao = al.fk_id_int_alt
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_seguradora s ON s.id_seguradora = pa.fk_seguradora_pac
    WHERE al.data_alta_alt BETWEEN :start AND :end
      {$whereHosp}
    GROUP BY s.id_seguradora
    ORDER BY readm DESC
    LIMIT 12
";
$stmt = $conn->prepare($sqlReadmSeg);
$stmt->execute($params);
$readmSegRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$readmSegLabels = [];
$readmSegRates = [];
foreach ($readmSegRows as $row) {
    $total = (int)($row['total_altas'] ?? 0);
    $readm = (int)($row['readm'] ?? 0);
    $readmSegLabels[] = $row['label'];
    $readmSegRates[] = $total > 0 ? round(($readm / $total) * 100, 2) : 0;
}

// Comorbidades (top 10)
$sqlComorb = "
    SELECT a.antecedente_ant AS label, COUNT(*) AS total
    FROM tb_intern_antec ia
    JOIN tb_antecedente a ON a.id_antecedente = ia.intern_antec_ant_int
    JOIN tb_internacao i ON i.id_internacao = ia.fK_internacao_ant_int
    WHERE i.data_intern_int BETWEEN :start AND :end
      {$whereHosp}
    GROUP BY a.id_antecedente
    ORDER BY total DESC
    LIMIT 10
";
$stmt = $conn->prepare($sqlComorb);
$stmt->execute($params);
$comorbRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$comorbLabels = array_map(fn($r) => $r['label'], $comorbRows);
$comorbValues = array_map(fn($r) => (int)$r['total'], $comorbRows);
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260516-rounded-bars"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));
</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Clínico Realizado</h1>
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
            <label>Data início</label>
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

    <div class="bi-grid fixed-2" style="margin-top:16px;">
        <div class="bi-panel">
            <h3 class="text-center" style="margin-bottom:12px;">Permanência média por patologia</h3>
            <div class="bi-chart"><canvas id="chartPermanencia"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3 class="text-center" style="margin-bottom:12px;">Readmissão 30d por hospital</h3>
            <div class="bi-chart"><canvas id="chartReadmHosp"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3 class="text-center" style="margin-bottom:12px;">Readmissão 30d por seguradora</h3>
            <div class="bi-chart"><canvas id="chartReadmSeg"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3 class="text-center" style="margin-bottom:12px;">Distribuição de comorbidades</h3>
            <div class="bi-chart"><canvas id="chartComorb"></canvas></div>
        </div>
    </div>
</div>

<script>
    const permLabels = <?= json_encode($permLabels) ?>;
    const permVals = <?= json_encode($permVals) ?>;
    const readmHospLabels = <?= json_encode($readmHospLabels) ?>;
    const readmHospVals = <?= json_encode($readmHospRates) ?>;
    const readmSegLabels = <?= json_encode($readmSegLabels) ?>;
    const readmSegVals = <?= json_encode($readmSegRates) ?>;
    const comorbLabels = <?= json_encode($comorbLabels) ?>;
    const comorbVals = <?= json_encode($comorbValues) ?>;

    function barChart(ctx, labels, data, color) {
        return new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Dias',
                    data,
                    backgroundColor: color
                }]
            },
            options: {
                legend: window.biLegendWhite ? window.biLegendWhite : undefined,
                scales: window.biChartScales ? window.biChartScales() : undefined
            }
        });
    }

    function barPercent(ctx, labels, data, color) {
        return new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: '% Readmissão',
                    data,
                    backgroundColor: color
                }]
            },
            options: {
                legend: window.biLegendWhite ? window.biLegendWhite : undefined,
                scales: window.biChartScales ? window.biChartScales() : undefined
            }
        });
    }

    function pieChart(ctx, labels, data) {
        return new Chart(ctx, {
            type: 'pie',
            data: {
                labels,
                datasets: [{
                    data,
                    backgroundColor: [
                        '#00b894',
                        '#ffeaa7',
                        '#6c5ce7',
                        '#fd79a8',
                        '#0984e3',
                        '#fab1a0',
                        '#2ecc71',
                        '#e17055',
                        '#fdcb6e',
                        '#74b9ff'
                    ]
                }]
            },
            options: {
                legend: window.biLegendWhite ? window.biLegendWhite : undefined
            }
        });
    }

    barChart(document.getElementById('chartPermanencia'), permLabels, permVals, 'rgba(92, 214, 165, 0.75)');
    barPercent(document.getElementById('chartReadmHosp'), readmHospLabels, readmHospVals, 'rgba(244, 177, 131, 0.75)');
    barPercent(document.getElementById('chartReadmSeg'), readmSegLabels, readmSegVals, 'rgba(231, 120, 170, 0.75)');
    pieChart(document.getElementById('chartComorb'), comorbLabels, comorbVals);
</script>

<?php require_once("templates/footer.php"); ?>