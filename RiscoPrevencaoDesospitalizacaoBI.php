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

function fmt_date(?string $value): string
{
    if (!$value) {
        return '-';
    }
    $ts = strtotime($value);
    return $ts ? date('d/m/Y', $ts) : '-';
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
$where = "i.data_intern_int BETWEEN :ini AND :fim AND al.data_alta_alt IS NOT NULL";
if ($hospitalId) {
    $where .= " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = $hospitalId;
}

$sqlAvg = "
    SELECT i.fk_patologia_int AS pat_id,
           AVG(GREATEST(DATEDIFF(al.data_alta_alt, i.data_intern_int), 0)) AS avg_los
    FROM tb_internacao i
    JOIN tb_alta al ON al.fk_id_int_alt = i.id_internacao
    WHERE {$where}
    GROUP BY pat_id
";
$stmt = $conn->prepare($sqlAvg);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$avgRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$avgMap = [];
foreach ($avgRows as $row) {
    $avgMap[(int)($row['pat_id'] ?? 0)] = (float)($row['avg_los'] ?? 0);
}

$sqlCases = "
    SELECT
        i.id_internacao,
        i.fk_patologia_int,
        p.nome_pac,
        h.nome_hosp,
        pat.patologia_pat,
        i.data_intern_int,
        al.data_alta_alt,
        GREATEST(DATEDIFF(al.data_alta_alt, i.data_intern_int), 0) AS dias
    FROM tb_internacao i
    JOIN tb_alta al ON al.fk_id_int_alt = i.id_internacao
    LEFT JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_patologia pat ON pat.id_patologia = i.fk_patologia_int
    WHERE {$where}
";
$stmt = $conn->prepare($sqlCases);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$cases = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$totalAltas = count($cases);
$precoce = [];
$hospAgg = [];

foreach ($cases as $row) {
    $patId = (int)($row['fk_patologia_int'] ?? 0);
    $avg = $avgMap[$patId] ?? 0;
    $dias = (float)($row['dias'] ?? 0);
    if ($avg >= 2 && $dias <= ($avg - 2)) {
        $row['avg_los'] = $avg;
        $row['delta'] = $avg - $dias;
        $precoce[] = $row;
        $hosp = $row['nome_hosp'] ?? 'Sem informações';
        $hospAgg[$hosp] = ($hospAgg[$hosp] ?? 0) + 1;
    }
}

arsort($hospAgg);
$hospLabels = array_keys($hospAgg);
$hospVals = array_values($hospAgg);

$precoceCount = count($precoce);
$pctPrecoce = $totalAltas > 0 ? ($precoceCount / $totalAltas) * 100 : 0;
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260516-rounded-bars"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Desospitalização precoce</h1>
        <div class="bi-header-actions">
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

    <div class="bi-kpis kpi-compact kpi-slim kpi-tight">
        <div class="bi-kpi kpi-amber kpi-compact">
            <small>Altas analisadas</small>
            <strong><?= number_format($totalAltas, 0, ',', '.') ?></strong>
        </div>
        <div class="bi-kpi kpi-rose kpi-compact">
            <small>Altas precoces</small>
            <strong><?= number_format($precoceCount, 0, ',', '.') ?></strong>
        </div>
        <div class="bi-kpi kpi-teal kpi-compact">
            <small>Percentual precoce</small>
            <strong><?= fmt_pct($pctPrecoce) ?></strong>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Altas precoces por hospital</h3>
        <div class="bi-chart compact"><canvas id="chartHospPrecoce"></canvas></div>
    </div>

    <div class="bi-panel">
        <h3>Casos identificados</h3>
        <div class="table-responsive">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th>Internação</th>
                        <th>Paciente</th>
                        <th>Hospital</th>
                        <th>Patologia</th>
                        <th>Data internação</th>
                        <th>Data alta</th>
                        <th>LOS</th>
                        <th>Média</th>
                        <th>Delta</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$precoce): ?>
                        <tr><td colspan="9">Sem informações</td></tr>
                    <?php else: ?>
                        <?php foreach ($precoce as $row): ?>
                            <tr>
                                <td>#<?= (int)$row['id_internacao'] ?></td>
                                <td><?= e($row['nome_pac'] ?? '-') ?></td>
                                <td><?= e($row['nome_hosp'] ?? '-') ?></td>
                                <td><?= e($row['patologia_pat'] ?? '-') ?></td>
                                <td><?= fmt_date($row['data_intern_int']) ?></td>
                                <td><?= fmt_date($row['data_alta_alt']) ?></td>
                                <td><?= number_format((float)$row['dias'], 1, ',', '.') ?></td>
                                <td><?= number_format((float)$row['avg_los'], 1, ',', '.') ?></td>
                                <td><?= number_format((float)$row['delta'], 1, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const hospLabels = <?= json_encode($hospLabels) ?>;
const hospVals = <?= json_encode($hospVals) ?>;
new Chart(document.getElementById('chartHospPrecoce'), {
  type: 'bar',
  data: {
    labels: hospLabels,
    datasets: [{ data: hospVals, backgroundColor: 'rgba(126, 149, 255, 0.85)', borderRadius: 8 }]
  },
  options: {
    legend: { display: false },
    scales: biChartScales()
  }
});
</script>

<?php require_once("templates/footer.php"); ?>
