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
$mesInput = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT);
$ano = ($anoInput !== null && $anoInput !== false) ? (int)$anoInput : null;
$mes = ($mesInput !== null && $mesInput !== false) ? (int)$mesInput : 0;
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$seguradoraId = filter_input(INPUT_GET, 'seguradora_id', FILTER_VALIDATE_INT) ?: null;

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);
$seguradoras = $conn->query("SELECT id_seguradora, seguradora_seg FROM tb_seguradora ORDER BY seguradora_seg")
    ->fetchAll(PDO::FETCH_ASSOC);
$anos = $conn->query("SELECT DISTINCT YEAR(data_intern_int) AS ano FROM tb_internacao WHERE data_intern_int IS NOT NULL AND data_intern_int <> '0000-00-00' ORDER BY ano DESC")
    ->fetchAll(PDO::FETCH_COLUMN);

if ($ano === null && !filter_has_var(INPUT_GET, 'ano')) {
    $ano = !empty($anos) ? (int)$anos[0] : (int)date('Y');
}

$dateExpr = "COALESCE(NULLIF(ca.data_inicial_capeante,'0000-00-00'), NULLIF(ca.data_digit_capeante,'0000-00-00'), NULLIF(ca.data_fech_capeante,'0000-00-00'))";
$where = "YEAR(ref_date) = :ano";
$params = [':ano' => (int)$ano];
if (!empty($mes)) {
    $where .= " AND MONTH(ref_date) = :mes";
    $params[':mes'] = (int)$mes;
}
if (!empty($hospitalId)) {
    $where .= " AND fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = (int)$hospitalId;
}
if (!empty($seguradoraId)) {
    $where .= " AND fk_seguradora_pac = :seguradora_id";
    $params[':seguradora_id'] = (int)$seguradoraId;
}

$sql = "
    SELECT
        COALESCE(NULLIF(p.patologia_pat, ''), 'Sem patologia') AS patologia,
        COUNT(*) AS casos,
        SUM(valor_apresentado_capeante) AS valor_apresentado,
        SUM(valor_glosa_total) AS valor_glosa,
        SUM(valor_final_capeante) AS valor_final
    FROM (
        SELECT
            ca.valor_apresentado_capeante,
            ca.valor_glosa_total,
            ca.valor_final_capeante,
            {$dateExpr} AS ref_date,
            ac.fk_patologia_int,
            ac.fk_hospital_int,
            pa.fk_seguradora_pac
        FROM tb_capeante ca
        INNER JOIN tb_internacao ac ON ac.id_internacao = ca.fk_int_capeante
        LEFT JOIN tb_paciente pa ON pa.id_paciente = ac.fk_paciente_int
    ) t
    LEFT JOIN tb_patologia p ON p.id_patologia = t.fk_patologia_int
    WHERE ref_date IS NOT NULL AND ref_date <> '0000-00-00'
      AND {$where}
    GROUP BY patologia
    ORDER BY valor_final DESC
";
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$totalCasos = 0;
$totalApresentado = 0.0;
$totalGlosa = 0.0;
$totalFinal = 0.0;
foreach ($rows as $row) {
    $totalCasos += (int)($row['casos'] ?? 0);
    $totalApresentado += (float)($row['valor_apresentado'] ?? 0);
    $totalGlosa += (float)($row['valor_glosa'] ?? 0);
    $totalFinal += (float)($row['valor_final'] ?? 0);
}
$mp = $totalCasos > 0 ? $totalFinal / $totalCasos : 0;

$topRows = array_slice($rows, 0, 10);
$labels = array_map(fn($r) => $r['patologia'], $topRows);
$values = array_map(fn($r) => round((float)($r['valor_final'] ?? 0), 2), $topRows);
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));
</script>

<div class="bi-wrapper bi-theme bi-ie-page">
    <div class="bi-header">
        <h1 class="bi-title">Sinistralidade por Patologia</h1>
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
            <label>Mês</label>
            <select name="mes">
                <option value="">Todos</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= (int)$mes === $m ? 'selected' : '' ?>><?= $m ?></option>
                <?php endfor; ?>
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
            <small>Custo final</small>
            <strong>R$ <?= number_format($totalFinal, 2, ',', '.') ?></strong>
        </div>
        <div class="bi-kpi kpi-amber kpi-compact">
            <small>Glosa total</small>
            <strong>R$ <?= number_format($totalGlosa, 2, ',', '.') ?></strong>
        </div>
        <div class="bi-kpi kpi-teal kpi-compact">
            <small>Casos</small>
            <strong><?= number_format($totalCasos, 0, ',', '.') ?></strong>
        </div>
        <div class="bi-kpi kpi-rose kpi-compact">
            <small>Custo médio</small>
            <strong>R$ <?= number_format($mp, 2, ',', '.') ?></strong>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Top patologias (custo final)</h3>
        <div class="bi-chart ie-chart-sm"><canvas id="chartPatologias"></canvas></div>
    </div>

    <div class="bi-panel">
        <h3>Detalhe por patologia</h3>
        <div class="table-responsive">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th>Patologia</th>
                        <th>Casos</th>
                        <th>Custo apresentado</th>
                        <th>Glosa</th>
                        <th>Custo final</th>
                        <th>Custo médio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="6">Sem informações</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $casos = (int)($row['casos'] ?? 0);
                            $final = (float)($row['valor_final'] ?? 0);
                            $media = $casos > 0 ? $final / $casos : 0;
                            ?>
                            <tr>
                                <td><?= e($row['patologia'] ?? 'Sem patologia') ?></td>
                                <td><?= number_format($casos, 0, ',', '.') ?></td>
                                <td>R$ <?= number_format((float)($row['valor_apresentado'] ?? 0), 2, ',', '.') ?></td>
                                <td>R$ <?= number_format((float)($row['valor_glosa'] ?? 0), 2, ',', '.') ?></td>
                                <td>R$ <?= number_format($final, 2, ',', '.') ?></td>
                                <td>R$ <?= number_format($media, 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const patLabels = <?= json_encode($labels) ?>;
    const patValues = <?= json_encode($values) ?>;
    new Chart(document.getElementById('chartPatologias'), {
        type: 'horizontalBar',
        data: {
            labels: patLabels,
            datasets: [{
                label: 'Custo final',
                data: patValues,
                backgroundColor: 'rgba(126,150,255,0.8)',
                borderRadius: 10,
                maxBarThickness: 48
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: {
                display: false
            },
            scales: biChartScales(),
            tooltips: {
                callbacks: {
                    label: (item) => biMoneyTick(item.xLabel)
                }
            }
        }
    });
</script>

<?php require_once("templates/footer.php"); ?>
