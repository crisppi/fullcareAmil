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
$where = "i.data_intern_int BETWEEN :ini AND :fim";
if ($hospitalId) {
    $where .= " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = $hospitalId;
}

$limit = 400;
$sql = "
    SELECT
        i.id_internacao,
        i.fk_paciente_int,
        i.data_intern_int,
        COALESCE(a.data_alta_alt, CURRENT_DATE) AS data_alta,
        p.nome_pac,
        h.nome_hosp
    FROM tb_internacao i
    LEFT JOIN tb_alta a ON a.fk_id_int_alt = i.id_internacao
    LEFT JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    WHERE {$where}
    ORDER BY i.fk_paciente_int, i.data_intern_int
    LIMIT {$limit}
";
$stmt = $conn->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$byPaciente = [];
foreach ($rows as $row) {
    $pid = (int)($row['fk_paciente_int'] ?? 0);
    if ($pid <= 0) {
        continue;
    }
    $byPaciente[$pid][] = $row;
}

$riskService = new ReadmissionRiskService($conn);
$scored = [];
$buckets = [
    'alto' => ['total' => 0, 'readm7' => 0, 'readm30' => 0],
    'moderado' => ['total' => 0, 'readm7' => 0, 'readm30' => 0],
    'baixo' => ['total' => 0, 'readm7' => 0, 'readm30' => 0],
];

foreach ($byPaciente as $list) {
    usort($list, function ($a, $b) {
        return strcmp($a['data_intern_int'], $b['data_intern_int']);
    });
    $count = count($list);
    for ($i = 0; $i < $count; $i++) {
        $current = $list[$i];
        $next = $i + 1 < $count ? $list[$i + 1] : null;
        $readm7 = false;
        $readm30 = false;
        if ($next) {
            $diff = (strtotime($next['data_intern_int']) - strtotime($current['data_alta'])) / 86400;
            if ($diff >= 0 && $diff <= 7) {
                $readm7 = true;
            }
            if ($diff >= 0 && $diff <= 30) {
                $readm30 = true;
            }
        }

        $score = $riskService->scoreInternacao((int)$current['id_internacao']);
        if (empty($score['available'])) {
            continue;
        }
        $level = $score['risk_level'] ?? 'baixo';
        if (!isset($buckets[$level])) {
            $level = 'baixo';
        }
        $buckets[$level]['total']++;
        if ($readm7) {
            $buckets[$level]['readm7']++;
        }
        if ($readm30) {
            $buckets[$level]['readm30']++;
        }

        $scored[] = [
            'internacao' => (int)$current['id_internacao'],
            'paciente' => $current['nome_pac'] ?? '-',
            'hospital' => $current['nome_hosp'] ?? '-',
            'data_internacao' => $current['data_intern_int'],
            'prob' => (float)$score['probability'],
            'nivel' => $level,
            'readm7' => $readm7,
            'readm30' => $readm30,
        ];
    }
}

usort($scored, function ($a, $b) {
    if ($a['prob'] == $b['prob']) {
        return 0;
    }
    return ($a['prob'] < $b['prob']) ? 1 : -1;
});
$topHigh = array_slice($scored, 0, 20);

$labels = ['Baixo', 'Moderado', 'Alto'];
$readm7Rates = [];
$readm30Rates = [];
foreach (['baixo', 'moderado', 'alto'] as $key) {
    $total = $buckets[$key]['total'] ?: 0;
    $readm7Rates[] = $total > 0 ? round(($buckets[$key]['readm7'] / $total) * 100, 1) : 0;
    $readm30Rates[] = $total > 0 ? round(($buckets[$key]['readm30'] / $total) * 100, 1) : 0;
}
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260509-filter-icons"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Matriz de Risco (Readmissão 7/30d)</h1>
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
        <h3>Matriz por nível de risco</h3>
        <div class="bi-chart compact"><canvas id="chartRiskMatrix"></canvas></div>
    </div>

    <div class="bi-panel">
        <h3>Pacientes com alto risco antes do evento</h3>
        <div class="table-responsive">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th>Internação</th>
                        <th>Paciente</th>
                        <th>Hospital</th>
                        <th>Data</th>
                        <th>Probabilidade</th>
                        <th>Nível</th>
                        <th>Readm 7d</th>
                        <th>Readm 30d</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$topHigh): ?>
                        <tr><td colspan="8">Sem informações</td></tr>
                    <?php else: ?>
                        <?php foreach ($topHigh as $row): ?>
                            <tr>
                                <td>#<?= (int)$row['internacao'] ?></td>
                                <td><?= e($row['paciente']) ?></td>
                                <td><?= e($row['hospital']) ?></td>
                                <td><?= fmt_date($row['data_internacao']) ?></td>
                                <td><?= fmt_pct($row['prob'] * 100) ?></td>
                                <td><?= strtoupper(e($row['nivel'])) ?></td>
                                <td><?= $row['readm7'] ? 'Sim' : 'Não' ?></td>
                                <td><?= $row['readm30'] ? 'Sim' : 'Não' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const labels = <?= json_encode($labels) ?>;
const readm7 = <?= json_encode($readm7Rates) ?>;
const readm30 = <?= json_encode($readm30Rates) ?>;

new Chart(document.getElementById('chartRiskMatrix'), {
  type: 'bar',
  data: {
    labels,
    datasets: [
      { label: 'Readmissão 7d (%)', data: readm7, backgroundColor: 'rgba(255, 179, 71, 0.8)', borderRadius: 8 },
      { label: 'Readmissão 30d (%)', data: readm30, backgroundColor: 'rgba(84, 132, 255, 0.8)', borderRadius: 8 }
    ]
  },
  options: {
    legend: { display: true },
    scales: biChartScales(),
    tooltips: {
      callbacks: {
        label: (item) => item.yLabel + '%'
      }
    }
  }
});
</script>

<?php require_once("templates/footer.php"); ?>
