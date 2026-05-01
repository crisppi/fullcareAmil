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

function producaoSeries(PDO $conn, int $ano, ?int $hospitalId, string $tipoAdmissão): array
{
    $where = "YEAR(i.data_intern_int) = :ano";
    $params = [':ano' => $ano];
    if ($hospitalId) {
        $where .= " AND i.fk_hospital_int = :hospital_id";
        $params[':hospital_id'] = $hospitalId;
    }
    if ($tipoAdmissão !== '') {
        $where .= " AND i.tipo_admissao_int = :tipo";
        $params[':tipo'] = $tipoAdmissão;
    }

    $sql = "
        SELECT
            MONTH(i.data_intern_int) AS mes,
            COUNT(*) AS total_internacoes,
            SUM(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)) AS total_diarias
        FROM tb_internacao i
        LEFT JOIN (
            SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
            FROM tb_alta
            GROUP BY fk_id_int_alt
        ) al ON al.fk_id_int_alt = i.id_internacao
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
        'internacoes' => array_fill(1, 12, 0),
        'diarias' => array_fill(1, 12, 0),
        'mp' => array_fill(1, 12, 0.0),
    ];
    foreach ($rows as $row) {
        $m = (int)($row['mes'] ?? 0);
        if ($m < 1 || $m > 12) continue;
        $internacoes = (int)($row['total_internacoes'] ?? 0);
        $diarias = (int)($row['total_diarias'] ?? 0);
        $series['internacoes'][$m] = $internacoes;
        $series['diarias'][$m] = $diarias;
        $series['mp'][$m] = $internacoes > 0 ? round($diarias / $internacoes, 1) : 0.0;
    }

    return [
        'internacoes' => array_values($series['internacoes']),
        'diarias' => array_values($series['diarias']),
        'mp' => array_values($series['mp']),
    ];
}

$seriesBase = producaoSeries($conn, $anoBase, $hospitalId, $tipoAdmissão);
$seriesComp = producaoSeries($conn, $anoComp, $hospitalId, $tipoAdmissão);
$labels = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Dashboard Produção YTD</h1>
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
        <h3>Total internações</h3>
        <div class="bi-chart">
            <canvas id="chartInternações"></canvas>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Total diárias</h3>
        <div class="bi-chart">
            <canvas id="chartDiárias"></canvas>
        </div>
    </div>

    <div class="bi-panel">
        <h3>MP</h3>
        <div class="bi-chart">
            <canvas id="chartMp"></canvas>
        </div>
    </div>
</div>

<script>
const labels = <?= json_encode($labels) ?>;
const base = <?= json_encode($seriesBase) ?>;
const comp = <?= json_encode($seriesComp) ?>;

function lineChart(ctx, key) {
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

lineChart(document.getElementById('chartInternações'), 'internacoes');
lineChart(document.getElementById('chartDiárias'), 'diarias');
lineChart(document.getElementById('chartMp'), 'mp');
</script>

<?php require_once("templates/footer.php"); ?>
