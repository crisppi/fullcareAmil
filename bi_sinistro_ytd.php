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

$anoBase = (int)(filter_input(INPUT_GET, 'ano_base', FILTER_VALIDATE_INT) ?: date('Y'));
$anoComp = (int)(filter_input(INPUT_GET, 'ano_comp', FILTER_VALIDATE_INT) ?: ($anoBase - 1));
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$tipoAdmissão = trim((string)(filter_input(INPUT_GET, 'tipo_admissao') ?? ''));

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);
$tiposAdm = $conn->query("SELECT DISTINCT tipo_admissao_int FROM tb_internacao WHERE tipo_admissao_int IS NOT NULL AND tipo_admissao_int <> '' ORDER BY tipo_admissao_int")
    ->fetchAll(PDO::FETCH_COLUMN);

function sinistroSeries(PDO $conn, int $ano, ?int $hospitalId, string $tipoAdmissão): array
{
    $dateExpr = "COALESCE(NULLIF(ca.data_inicial_capeante,'0000-00-00'), NULLIF(ca.data_digit_capeante,'0000-00-00'), NULLIF(ca.data_fech_capeante,'0000-00-00'))";
    $where = "ref_date IS NOT NULL AND ref_date <> '0000-00-00' AND YEAR(ref_date) = :ano";
    $params = [':ano' => $ano];
    if ($hospitalId) {
        $where .= " AND fk_hospital_int = :hospital_id";
        $params[':hospital_id'] = $hospitalId;
    }
    if ($tipoAdmissão !== '') {
        $where .= " AND tipo_admissao_int = :tipo";
        $params[':tipo'] = $tipoAdmissão;
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
                ac.tipo_admissao_int
            FROM tb_capeante ca
            INNER JOIN tb_internacao ac ON ac.id_internacao = ca.fk_int_capeante
        ) t
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

    return [
        'valor_apresentado' => array_values($series['valor_apresentado']),
        'valor_glosa' => array_values($series['valor_glosa']),
        'valor_final' => array_values($series['valor_final']),
    ];
}

$seriesBase = sinistroSeries($conn, $anoBase, $hospitalId, $tipoAdmissão);
$seriesComp = sinistroSeries($conn, $anoComp, $hospitalId, $tipoAdmissão);
$labels = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Dashboard Sinistro YTD</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted"><?= e($anoBase) ?> vs <?= e($anoComp) ?></div>
        </div>
    </div>

    <form class="bi-panel bi-filters" method="get">
        <div class="bi-filter">
            <label>Ano Base</label>
            <input type="number" name="ano_base" value="<?= e($anoBase) ?>">
        </div>
        <div class="bi-filter">
            <label>Ano Comparação</label>
            <input type="number" name="ano_comp" value="<?= e($anoComp) ?>">
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
            <label>Tipo admissão</label>
            <select name="tipo_admissao">
                <option value="">Todos</option>
                <?php foreach ($tiposAdm as $tipo): ?>
                    <option value="<?= e($tipo) ?>" <?= $tipoAdmissão === $tipo ? 'selected' : '' ?>>
                        <?= e($tipo) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="bi-panel">
        <h3>Valor apresentado</h3>
        <div class="bi-chart">
            <canvas id="chartApresentado"></canvas>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Total glosa</h3>
        <div class="bi-chart">
            <canvas id="chartGlosa"></canvas>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Valor final</h3>
        <div class="bi-chart">
            <canvas id="chartFinal"></canvas>
        </div>
    </div>
</div>

<script>
const labels = <?= json_encode($labels) ?>;
const base = <?= json_encode($seriesBase) ?>;
const comp = <?= json_encode($seriesComp) ?>;

function lineChart(ctx, title, key) {
    return new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: '<?= e($anoBase) ?>',
                    data: base[key],
                    borderColor: '#8dd0ff',
                    backgroundColor: 'rgba(141, 208, 255, 0.2)',
                    borderWidth: 3,
                    tension: 0.3,
                    pointRadius: 3
                },
                {
                    label: '<?= e($anoComp) ?>',
                    data: comp[key],
                    borderColor: '#d071b0',
                    backgroundColor: 'rgba(208, 113, 176, 0.2)',
                    borderWidth: 3,
                    tension: 0.3,
                    pointRadius: 3
                }
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

lineChart(document.getElementById('chartApresentado'), 'Valor apresentado', 'valor_apresentado');
lineChart(document.getElementById('chartGlosa'), 'Total glosa', 'valor_glosa');
lineChart(document.getElementById('chartFinal'), 'Valor final', 'valor_final');
</script>

<?php require_once("templates/footer.php"); ?>
