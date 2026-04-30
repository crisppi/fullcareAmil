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

$ano = (int)(filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT) ?: date('Y'));
$mes = (int)(filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT) ?: 0);
$internado = trim((string)(filter_input(INPUT_GET, 'internado') ?? ''));
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$tipoInternação = trim((string)(filter_input(INPUT_GET, 'tipo_internacao') ?? ''));
$modoAdmissão = trim((string)(filter_input(INPUT_GET, 'modo_admissao') ?? ''));
$uti = trim((string)(filter_input(INPUT_GET, 'uti') ?? ''));
$seguradoraId = filter_input(INPUT_GET, 'seguradora_id', FILTER_VALIDATE_INT) ?: null;

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);
$seguradoras = $conn->query("SELECT id_seguradora, seguradora_seg FROM tb_seguradora ORDER BY seguradora_seg")
    ->fetchAll(PDO::FETCH_ASSOC);
$tiposInt = $conn->query("SELECT DISTINCT tipo_admissao_int FROM tb_internacao WHERE tipo_admissao_int IS NOT NULL AND tipo_admissao_int <> '' ORDER BY tipo_admissao_int")
    ->fetchAll(PDO::FETCH_COLUMN);
$modosAdm = $conn->query("SELECT DISTINCT modo_internacao_int FROM tb_internacao WHERE modo_internacao_int IS NOT NULL AND modo_internacao_int <> '' ORDER BY modo_internacao_int")
    ->fetchAll(PDO::FETCH_COLUMN);

$where = "YEAR(i.data_intern_int) = :ano";
$params = [':ano' => $ano];
if ($mes > 0) {
    $where .= " AND MONTH(i.data_intern_int) = :mes";
    $params[':mes'] = $mes;
}
if ($internado !== '') {
    $where .= " AND i.internado_int = :internado";
    $params[':internado'] = $internado;
}
if ($hospitalId) {
    $where .= " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = $hospitalId;
}
if ($tipoInternação !== '') {
    $where .= " AND i.tipo_admissao_int = :tipo";
    $params[':tipo'] = $tipoInternação;
}
if ($modoAdmissão !== '') {
    $where .= " AND i.modo_internacao_int = :modo";
    $params[':modo'] = $modoAdmissão;
}
if ($seguradoraId) {
    $where .= " AND pa.fk_seguradora_pac = :seg";
    $params[':seg'] = $seguradoraId;
}

$utiJoin = "LEFT JOIN (SELECT DISTINCT fk_internacao_uti FROM tb_uti) ut ON ut.fk_internacao_uti = i.id_internacao";
if ($uti === 's') {
    $where .= " AND ut.fk_internacao_uti IS NOT NULL";
}
if ($uti === 'n') {
    $where .= " AND ut.fk_internacao_uti IS NULL";
}

$sqlStats = "
    SELECT
        COUNT(*) AS total_internacoes,
        SUM(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)) AS total_diarias,
        MAX(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)) AS maior_permanencia
    FROM tb_internacao i
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN (
        SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
        FROM tb_alta
        GROUP BY fk_id_int_alt
    ) al ON al.fk_id_int_alt = i.id_internacao
    {$utiJoin}
    WHERE {$where}
";
$stmt = $conn->prepare($sqlStats);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$totalInternações = (int)($stats['total_internacoes'] ?? 0);
$totalDiárias = (int)($stats['total_diarias'] ?? 0);
$maiorPermanencia = (int)($stats['maior_permanencia'] ?? 0);
$mp = $totalInternações > 0 ? round($totalDiárias / $totalInternações, 1) : 0;

$dateExpr = "COALESCE(NULLIF(ca.data_inicial_capeante,'0000-00-00'), NULLIF(ca.data_digit_capeante,'0000-00-00'), NULLIF(ca.data_fech_capeante,'0000-00-00'))";
$whereFin = "YEAR(ref_date) = :ano";
$paramsFin = [':ano' => $ano];
if ($mes > 0) {
    $whereFin .= " AND MONTH(ref_date) = :mes";
    $paramsFin[':mes'] = $mes;
}
if ($hospitalId) {
    $whereFin .= " AND fk_hospital_int = :hospital_id";
    $paramsFin[':hospital_id'] = $hospitalId;
}
if ($tipoInternação !== '') {
    $whereFin .= " AND tipo_admissao_int = :tipo";
    $paramsFin[':tipo'] = $tipoInternação;
}
if ($modoAdmissão !== '') {
    $whereFin .= " AND modo_internacao_int = :modo";
    $paramsFin[':modo'] = $modoAdmissão;
}
if ($seguradoraId) {
    $whereFin .= " AND fk_seguradora_pac = :seg";
    $paramsFin[':seg'] = $seguradoraId;
}

$sqlFin = "
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
            ac.tipo_admissao_int,
            ac.modo_internacao_int,
            pa.fk_seguradora_pac
        FROM tb_capeante ca
        INNER JOIN tb_internacao ac ON ac.id_internacao = ca.fk_int_capeante
        LEFT JOIN tb_paciente pa ON pa.id_paciente = ac.fk_paciente_int
    ) t
    WHERE ref_date IS NOT NULL AND ref_date <> '0000-00-00'
      AND {$whereFin}
    GROUP BY mes
    ORDER BY mes ASC
";
$stmt = $conn->prepare($sqlFin);
foreach ($paramsFin as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$rowsFin = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$series = [
    'valor_apresentado' => array_fill(1, 12, 0.0),
    'valor_glosa' => array_fill(1, 12, 0.0),
    'valor_final' => array_fill(1, 12, 0.0),
];
foreach ($rowsFin as $row) {
    $m = (int)($row['mes'] ?? 0);
    if ($m < 1 || $m > 12) continue;
    $series['valor_apresentado'][$m] = (float)($row['valor_apresentado'] ?? 0);
    $series['valor_glosa'][$m] = (float)($row['valor_glosa'] ?? 0);
    $series['valor_final'][$m] = (float)($row['valor_final'] ?? 0);
}
$labels = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260110">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260110"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>
<style>
    .bi-sinistro-chart-sm {
        min-height: 160px !important;
        height: 160px !important;
        max-height: 160px !important;
    }

    .bi-sinistro-chart-sm canvas {
        height: 160px !important;
        max-height: 160px !important;
    }
</style>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Dashboard Sinistro</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted">Ano <?= e($ano) ?></div>
        </div>
    </div>

    <form class="bi-panel bi-filters" method="get">
        <div class="bi-filter">
            <label>Internado</label>
            <select name="internado">
                <option value="">Todos</option>
                <option value="s" <?= $internado === 's' ? 'selected' : '' ?>>Sim</option>
                <option value="n" <?= $internado === 'n' ? 'selected' : '' ?>>Não</option>
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
            <label>Tipo internação</label>
            <select name="tipo_internacao">
                <option value="">Todos</option>
                <?php foreach ($tiposInt as $tipo): ?>
                    <option value="<?= e($tipo) ?>" <?= $tipoInternação === $tipo ? 'selected' : '' ?>>
                        <?= e($tipo) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Modo admissão</label>
            <select name="modo_admissao">
                <option value="">Todos</option>
                <?php foreach ($modosAdm as $modo): ?>
                    <option value="<?= e($modo) ?>" <?= $modoAdmissão === $modo ? 'selected' : '' ?>>
                        <?= e($modo) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>UTI</label>
            <select name="uti">
                <option value="">Todos</option>
                <option value="s" <?= $uti === 's' ? 'selected' : '' ?>>Sim</option>
                <option value="n" <?= $uti === 'n' ? 'selected' : '' ?>>Não</option>
            </select>
        </div>
        <div class="bi-filter">
            <label>Operadora</label>
            <select name="seguradora_id">
                <option value="">Todas</option>
                <?php foreach ($seguradoras as $s): ?>
                    <option value="<?= (int)$s['id_seguradora'] ?>" <?= $seguradoraId == $s['id_seguradora'] ? 'selected' : '' ?>>
                        <?= e($s['seguradora_seg']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Mês</label>
            <select name="mes">
                <option value="0">Todos</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $mes === $m ? 'selected' : '' ?>><?= $m ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Ano</label>
            <input type="number" name="ano" value="<?= e($ano) ?>">
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="bi-panel">
        <div class="bi-kpis">
            <div class="bi-kpi"><small>Internações</small><strong><?= $totalInternações ?></strong></div>
            <div class="bi-kpi"><small>Diárias</small><strong><?= $totalDiárias ?></strong></div>
            <div class="bi-kpi"><small>MP</small><strong><?= $mp ?></strong></div>
            <div class="bi-kpi"><small>Maior permanência</small><strong><?= $maiorPermanencia ?></strong></div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Valor apresentado</h3>
        <div class="bi-chart bi-sinistro-chart-sm" style="height:160px;max-height:160px;"><canvas id="chartApresentado" height="160" style="height:160px !important;"></canvas></div>
    </div>
    <div class="bi-panel">
        <h3>Total de glosas</h3>
        <div class="bi-chart bi-sinistro-chart-sm" style="height:160px;max-height:160px;"><canvas id="chartGlosa" height="160" style="height:160px !important;"></canvas></div>
    </div>
    <div class="bi-panel">
        <h3>Valor final</h3>
        <div class="bi-chart bi-sinistro-chart-sm" style="height:160px;max-height:160px;"><canvas id="chartFinal" height="160" style="height:160px !important;"></canvas></div>
    </div>
</div>

<script>
const labels = <?= json_encode($labels) ?>;
const series = <?= json_encode([
    'valor_apresentado' => array_values($series['valor_apresentado']),
    'valor_glosa' => array_values($series['valor_glosa']),
    'valor_final' => array_values($series['valor_final']),
]) ?>;

function lineChart(ctx, key, yLabel) {
    return new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: '<?= e($ano) ?>',
                data: series[key],
                borderColor: '#8dd0ff',
                backgroundColor: 'rgba(141, 208, 255, 0.2)',
                borderWidth: 3,
                tension: 0.3,
                pointRadius: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: window.biLegendWhite || {},
            scales: window.biChartScales ? (() => {
                const scales = window.biChartScales();
                if (scales.yAxes && scales.yAxes[0]) {
                    scales.yAxes[0].scaleLabel = {
                        display: true,
                        labelString: yLabel,
                        fontColor: '#eaf6ff'
                    };
                }
                if (scales.y) {
                    scales.y.title = { display: true, text: yLabel, color: '#eaf6ff' };
                }
                return scales;
            })() : {
                yAxes: [{
                    ticks: { fontColor: '#eaf6ff' },
                    gridLines: { color: 'rgba(255,255,255,0.12)' },
                    scaleLabel: { display: true, labelString: yLabel, fontColor: '#eaf6ff' }
                }],
                y: { title: { display: true, text: yLabel, color: '#eaf6ff' } }
            }
        }
    });
}

lineChart(document.getElementById('chartApresentado'), 'valor_apresentado', 'Valor (R$)');
lineChart(document.getElementById('chartGlosa'), 'valor_glosa', 'Valor (R$)');
lineChart(document.getElementById('chartFinal'), 'valor_final', 'Valor (R$)');
</script>

<?php require_once("templates/footer.php"); ?>
