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

function fmtMoney($value): string
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

$anoInput = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
$ano = $anoInput ? (int)$anoInput : null;
if ($ano === null && !filter_has_var(INPUT_GET, 'ano')) {
    $ano = (int)date('Y');
}
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);
$anos = $conn->query("SELECT DISTINCT YEAR(COALESCE(data_inicial_capeante, data_digit_capeante, data_fech_capeante, data_final_capeante)) AS ano FROM tb_capeante WHERE COALESCE(data_inicial_capeante, data_digit_capeante, data_fech_capeante, data_final_capeante) IS NOT NULL ORDER BY ano DESC")
    ->fetchAll(PDO::FETCH_COLUMN);

$dateExpr = "COALESCE(NULLIF(ca.data_inicial_capeante,'0000-00-00'), NULLIF(ca.data_digit_capeante,'0000-00-00'), NULLIF(ca.data_fech_capeante,'0000-00-00'), NULLIF(ca.data_final_capeante,'0000-00-00'), NULLIF(i.data_intern_int,'0000-00-00'))";
$where = "{$dateExpr} IS NOT NULL";
$params = [];
if ($ano) {
    $where .= " AND YEAR({$dateExpr}) = :ano";
    $params[':ano'] = $ano;
}
if ($hospitalId) {
    $where .= " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = $hospitalId;
}

$sql = "
    SELECT
        MONTH({$dateExpr}) AS mes,
        SUM(COALESCE(ca.valor_apresentado_capeante,0)) AS valor_apresentado,
        SUM(COALESCE(ca.valor_glosa_total,0)) AS valor_glosa,
        SUM(COALESCE(ca.valor_final_capeante,0)) AS valor_final
    FROM tb_internacao i
    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao
    WHERE {$where}
    GROUP BY mes
    ORDER BY mes ASC
";
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$byMonth = [];
$labels = [];
$valApresentado = [];
$valGlosa = [];
$valFinal = [];
foreach ($rows as $row) {
    $m = (int)($row['mes'] ?? 0);
    $byMonth[$m] = $row;
}
for ($m = 1; $m <= 12; $m++) {
    $row = $byMonth[$m] ?? ['valor_apresentado' => 0, 'valor_glosa' => 0, 'valor_final' => 0];
    $labels[] = (string)$m;
    $valApresentado[] = (float)$row['valor_apresentado'];
    $valGlosa[] = (float)$row['valor_glosa'];
    $valFinal[] = (float)$row['valor_final'];
}
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="diversos/CoolAdmin-master/vendor/chartjs/Chart.bundle.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260516-rounded-bars"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Dashboard Custo Medio Diarias</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Resumo mensal de valores apresentados, glosa e finais.</div>
        </div>
        <div class="bi-header-actions">
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação BI">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <form method="get">
        <div class="bi-panel bi-filters bi-filters-wrap">
            <div class="bi-filter">
                <label>Ano</label>
                <select name="ano">
                    <option value="">Todos</option>
                    <?php foreach ($anos as $anoOpt): ?>
                        <option value="<?= (int)$anoOpt ?>" <?= $ano == $anoOpt ? 'selected' : '' ?>>
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
            <div class="bi-actions">
                <button class="bi-btn" type="submit">Aplicar filtros</button>
            </div>
        </div>
    </form>

    <div class="bi-panel">
        <h3>Valor Apresentado</h3>
        <div class="bi-chart"><canvas id="chartApresentado"></canvas></div>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Mês</th>
                    <th>Valor</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <?php $row = $byMonth[$m] ?? ['valor_apresentado' => 0, 'valor_glosa' => 0, 'valor_final' => 0]; ?>
                    <tr>
                        <td><?= $m ?></td>
                        <td><?= fmtMoney((float)$row['valor_apresentado']) ?></td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <div class="bi-panel">
        <h3>Total Glosa</h3>
        <div class="bi-chart"><canvas id="chartGlosa"></canvas></div>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Mês</th>
                    <th>Valor</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <?php $row = $byMonth[$m] ?? ['valor_apresentado' => 0, 'valor_glosa' => 0, 'valor_final' => 0]; ?>
                    <tr>
                        <td><?= $m ?></td>
                        <td><?= fmtMoney((float)$row['valor_glosa']) ?></td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <div class="bi-panel">
        <h3>Valor Final</h3>
        <div class="bi-chart"><canvas id="chartFinal"></canvas></div>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Mês</th>
                    <th>Valor</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <?php $row = $byMonth[$m] ?? ['valor_apresentado' => 0, 'valor_glosa' => 0, 'valor_final' => 0]; ?>
                    <tr>
                        <td><?= $m ?></td>
                        <td><?= fmtMoney((float)$row['valor_final']) ?></td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const cmLabels = <?= json_encode($labels) ?>;
const cmApresentado = <?= json_encode($valApresentado) ?>;
const cmGlosa = <?= json_encode($valGlosa) ?>;
const cmFinal = <?= json_encode($valFinal) ?>;

function buildBarChart(canvasId, labels, values) {
    const el = document.getElementById(canvasId);
    if (!el || !window.Chart) return;
    new Chart(el, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: 'rgba(126,150,255,0.82)',
                borderRadius: 10,
                maxBarThickness: 48
            }]
        },
        options: {
            legend: { display: false },
            scales: window.biChartScales ? window.biChartScales() : undefined,
            tooltips: {
                callbacks: {
                    label: function(tooltipItem) {
                        const v = tooltipItem.yLabel || tooltipItem.value || 0;
                        return window.biMoneyTick ? window.biMoneyTick(v) : v;
                    }
                }
            }
        }
    });
}

buildBarChart('chartApresentado', cmLabels, cmApresentado);
buildBarChart('chartGlosa', cmLabels, cmGlosa);
buildBarChart('chartFinal', cmLabels, cmFinal);
</script>

<?php require_once("templates/footer.php"); ?>
