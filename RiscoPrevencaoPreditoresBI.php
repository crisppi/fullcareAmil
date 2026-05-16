<?php
include_once("check_logado.php");
require_once("templates/header.php");
require_once __DIR__ . "/app/services/ReadmissionRiskService.php";

if (!isset($conn) || !($conn instanceof PDO)) {
    die("Conexao invalida.");
}

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function fmt_pct($value, int $decimals = 1): string
{
    return number_format((float)$value, $decimals, ',', '.') . '%';
}

$endDate = filter_input(INPUT_GET, 'data_fim') ?: date('Y-m-d');
$startDate = filter_input(INPUT_GET, 'data_ini') ?: date('Y-m-d', strtotime('-180 days'));
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);

$params = [':ini' => $startDate, ':fim' => $endDate];
$where = "i.data_intern_int BETWEEN :ini AND :fim";
if ($hospitalId) {
    $where .= " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = $hospitalId;
}

$limit = 350;
$sql = "
    SELECT
        i.id_internacao,
        i.fk_paciente_int,
        i.data_intern_int,
        p.nome_pac,
        h.nome_hosp
    FROM tb_internacao i
    LEFT JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    WHERE {$where}
    ORDER BY i.data_intern_int DESC
    LIMIT {$limit}
";
$stmt = $conn->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$riskService = new ReadmissionRiskService($conn);
$highRisk = [];
$featureCounts = [
    'Idoso' => 0,
    'Evento adverso' => 0,
    'Longa permanência' => 0,
    'Internações prévias >= 2' => 0,
    'MP prévia >= 10d' => 0,
];

foreach ($rows as $row) {
    $score = $riskService->scoreInternacao((int)$row['id_internacao']);
    if (empty($score['available'])) {
        continue;
    }
    $prob = (float)$score['probability'];
    if ($prob < 0.55) {
        continue;
    }
    $features = $score['features'] ?? [];
    $highRisk[] = [
        'internacao' => (int)$row['id_internacao'],
        'paciente' => $row['nome_pac'] ?? '-',
        'hospital' => $row['nome_hosp'] ?? '-',
        'prob' => $prob,
        'nivel' => $score['risk_level'] ?? 'alto',
    ];

    if (($features['faixa_etaria'] ?? '') === 'idoso') {
        $featureCounts['Idoso']++;
    }
    if ((int)($features['eventos_adversos'] ?? 0) > 0) {
        $featureCounts['Evento adverso']++;
    }
    if (!empty($features['longa_permanencia'])) {
        $featureCounts['Longa permanência']++;
    }
    if ((int)($features['internacoes_previas'] ?? 0) >= 2) {
        $featureCounts['Internações prévias >= 2']++;
    }
    if ((float)($features['mp_previas'] ?? 0) >= 10) {
        $featureCounts['MP prévia >= 10d']++;
    }
}

usort($highRisk, function ($a, $b) {
    if ($a['prob'] == $b['prob']) {
        return 0;
    }
    return ($a['prob'] < $b['prob']) ? 1 : -1;
});
$highRisk = array_slice($highRisk, 0, 30);

$totalHigh = count($highRisk);
$labels = array_keys($featureCounts);
$values = [];
foreach ($featureCounts as $count) {
    $values[] = $totalHigh > 0 ? round(($count / $totalHigh) * 100, 1) : 0;
}
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260509-filter-icons"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Preditores de Readmissão/Complicações</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted">Amostra: até <?= (int)$limit ?> internações</div>
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <form class="bi-panel bi-filters bi-filters-wrap" method="get">
        <div class="bi-filter">
            <label>Data inicial</label>
            <input type="date" name="data_ini" value="<?= e($startDate) ?>">
        </div>
        <div class="bi-filter">
            <label>Data final</label>
            <input type="date" name="data_fim" value="<?= e($endDate) ?>">
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
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="bi-panel">
        <h3>Preditores mais frequentes (alto risco)</h3>
        <div class="bi-chart compact"><canvas id="chartPreditores"></canvas></div>
    </div>

    <div class="bi-panel">
        <h3>Pacientes com maior probabilidade</h3>
        <div class="table-responsive">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th>Internação</th>
                        <th>Paciente</th>
                        <th>Hospital</th>
                        <th>Probabilidade</th>
                        <th>Nível</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$highRisk): ?>
                        <tr><td colspan="5">Sem informações</td></tr>
                    <?php else: ?>
                        <?php foreach ($highRisk as $row): ?>
                            <tr>
                                <td>#<?= (int)$row['internacao'] ?></td>
                                <td><?= e($row['paciente']) ?></td>
                                <td><?= e($row['hospital']) ?></td>
                                <td><?= fmt_pct($row['prob'] * 100) ?></td>
                                <td><?= strtoupper(e($row['nivel'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const predLabels = <?= json_encode($labels) ?>;
const predValues = <?= json_encode($values) ?>;
new Chart(document.getElementById('chartPreditores'), {
  type: 'bar',
  data: {
    labels: predLabels,
    datasets: [{ data: predValues, backgroundColor: 'rgba(88, 196, 182, 0.85)', borderRadius: 8 }]
  },
  options: {
    legend: { display: false },
    scales: biChartScales(),
    tooltips: { callbacks: { label: (item) => item.yLabel + '%' } }
  }
});
</script>

<?php require_once("templates/footer.php"); ?>
