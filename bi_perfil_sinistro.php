<?php
include_once("check_logado.php");
require_once("templates/header.php");

if (!isset($conn) || !($conn instanceof PDO)) {
    die("Conexão inválida.");
}

require_once __DIR__ . '/app/bi_cid_options.php';

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$dateExpr = "COALESCE(NULLIF(ca.data_inicial_capeante,'0000-00-00'), NULLIF(ca.data_digit_capeante,'0000-00-00'), NULLIF(ca.data_fech_capeante,'0000-00-00'))";

$anoInput = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
$ano = ($anoInput !== null && $anoInput !== false) ? (int)$anoInput : null;
$mes = (int)(filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT) ?: 0);
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$patologiaId = filter_input(INPUT_GET, 'patologia_id', FILTER_VALIDATE_INT) ?: null;
$grupoPatologia = trim((string)(filter_input(INPUT_GET, 'grupo_patologia') ?? ''));
$modoInternação = trim((string)(filter_input(INPUT_GET, 'modo_internacao') ?? ''));
$internado = trim((string)(filter_input(INPUT_GET, 'internado') ?? ''));
$sexo = trim((string)(filter_input(INPUT_GET, 'sexo') ?? ''));
$uti = trim((string)(filter_input(INPUT_GET, 'uti') ?? ''));

if ($ano === null && !filter_has_var(INPUT_GET, 'ano')) {
    $stmtAno = $conn->query("
        SELECT MAX(YEAR(ref_date)) AS ano
        FROM (
            SELECT {$dateExpr} AS ref_date
            FROM tb_capeante ca
            WHERE {$dateExpr} IS NOT NULL AND {$dateExpr} <> '0000-00-00'
        ) t
    ");
    $anoDb = $stmtAno->fetch(PDO::FETCH_ASSOC) ?: [];
    $ano = (int)($anoDb['ano'] ?? date('Y'));
}

$filterScope = [
    'ano' => $ano,
    'mes' => $mes,
    'hospital_id' => $hospitalId,
    'modo_internacao' => $modoInternação,
    'grupo_patologia' => $grupoPatologia,
    'internado' => $internado,
    'sexo' => $sexo,
    'uti' => $uti,
];

$hospitais = array_map(fn($r) => ['id_hospital' => $r['value'], 'nome_hosp' => $r['label']], bi_fetch_filter_options($conn, 'hospital', $filterScope, [
    'date_expr' => $dateExpr,
]));
$grupos = array_column(bi_fetch_filter_options($conn, 'grupo_patologia', $filterScope, [
    'date_expr' => $dateExpr,
]), 'label');
$modos = array_column(bi_fetch_filter_options($conn, 'modo_internacao', $filterScope, [
    'date_expr' => $dateExpr,
]), 'label');
$patologias = bi_fetch_cid_options($conn, $filterScope, [
    'date_expr' => $dateExpr,
    'join_capeante' => true,
]);

$where = "ref_date IS NOT NULL AND ref_date <> '0000-00-00' AND YEAR(ref_date) = :ano";
$params = [':ano' => $ano];
if ($mes > 0) {
    $where .= " AND MONTH(ref_date) = :mes";
    $params[':mes'] = $mes;
}
if ($hospitalId) {
    $where .= " AND fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = $hospitalId;
}
if ($patologiaId) {
    $where .= " AND fk_cid_int = :patologia_id";
    $params[':patologia_id'] = $patologiaId;
}
if ($grupoPatologia !== '') {
    $where .= " AND grupo_patologia_int = :grupo";
    $params[':grupo'] = $grupoPatologia;
}
if ($modoInternação !== '') {
    $where .= " AND modo_internacao_int = :modo";
    $params[':modo'] = $modoInternação;
}
if ($internado !== '') {
    $where .= " AND internado_int = :internado";
    $params[':internado'] = $internado;
}
if ($sexo !== '') {
    $where .= " AND sexo_pac = :sexo";
    $params[':sexo'] = $sexo;
}

$utiJoin = "LEFT JOIN (SELECT DISTINCT fk_internacao_uti FROM tb_uti) ut ON ut.fk_internacao_uti = fk_int_capeante";
if ($uti === 's') {
    $where .= " AND ut.fk_internacao_uti IS NOT NULL";
}
if ($uti === 'n') {
    $where .= " AND ut.fk_internacao_uti IS NULL";
}

$sql = "
    SELECT
        SUM(COALESCE(valor_apresentado_capeante,0)) AS valor_apresentado,
        SUM(COALESCE(valor_final_capeante,0)) AS valor_final,
        SUM(COALESCE(valor_glosa_total,0)) AS glosa_total,
        SUM(COALESCE(valor_glosa_med,0)) AS glosa_med,
        SUM(COALESCE(valor_glosa_enf,0)) AS glosa_enf,
        SUM(COALESCE(valor_diarias,0)) AS valor_diarias,
        SUM(COALESCE(valor_matmed,0)) AS valor_matmed,
        SUM(COALESCE(valor_honorarios,0)) AS valor_honorarios,
        SUM(COALESCE(valor_oxig,0)) AS valor_oxig,
        SUM(COALESCE(valor_sadt,0)) AS valor_sadt,
        SUM(COALESCE(valor_taxa,0)) AS valor_taxas,
        SUM(COALESCE(glosa_diaria,0)) AS glosa_diaria,
        SUM(COALESCE(glosa_matmed,0)) AS glosa_matmed,
        SUM(COALESCE(glosa_honorarios,0)) AS glosa_honorarios,
        SUM(COALESCE(glosa_oxig,0)) AS glosa_oxig,
        SUM(COALESCE(glosa_sadt,0)) AS glosa_sadt,
        SUM(COALESCE(glosa_taxas,0)) AS glosa_taxas
    FROM (
        SELECT
            ca.*,
            {$dateExpr} AS ref_date,
            ac.fk_hospital_int,
            ac.fk_cid_int,
            ac.fk_patologia_int,
            ac.grupo_patologia_int,
            ac.modo_internacao_int,
            ac.internado_int,
            pa.sexo_pac
        FROM tb_capeante ca
        INNER JOIN tb_internacao ac ON ac.id_internacao = ca.fk_int_capeante
        LEFT JOIN tb_paciente pa ON pa.id_paciente = ac.fk_paciente_int
    ) t
    {$utiJoin}
    WHERE {$where}
";
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$allocLabels = ['Diárias', 'Mat/Med', 'Honorários', 'Oxigênio', 'Taxas', 'SADT'];
$allocValues = [
    (float)($row['valor_diarias'] ?? 0),
    (float)($row['valor_matmed'] ?? 0),
    (float)($row['valor_honorarios'] ?? 0),
    (float)($row['valor_oxig'] ?? 0),
    (float)($row['valor_taxas'] ?? 0),
    (float)($row['valor_sadt'] ?? 0),
];

$glosaLabels = ['Glosa Honorários', 'Glosa Diárias', 'Glosa Mat/Med', 'Glosa Oxigênio', 'Glosa SADT', 'Glosa Taxas'];
$glosaValues = [
    (float)($row['glosa_honorarios'] ?? 0),
    (float)($row['glosa_diaria'] ?? 0),
    (float)($row['glosa_matmed'] ?? 0),
    (float)($row['glosa_oxig'] ?? 0),
    (float)($row['glosa_sadt'] ?? 0),
    (float)($row['glosa_taxas'] ?? 0),
];

$barLabels = ['Valor apresentado', 'Valor glosa total', 'Valor final'];
$barValues = [
    (float)($row['valor_apresentado'] ?? 0),
    (float)($row['glosa_total'] ?? 0),
    (float)($row['valor_final'] ?? 0),
];
$perfilColors = ['#7cc4ff', '#c06ea3', '#5fd3b5', '#ffc56c', '#a2b5ff', '#ff8fb1'];
$allocTotal = array_sum($allocValues);
$glosaTotalBreakdown = array_sum($glosaValues);
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260508-perfil-sinistro">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260509-filter-icons"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>
<div class="bi-wrapper bi-theme bi-perfil-sinistro-page">
    <div class="bi-header">
        <h1 class="bi-title">Dashboard Perfil Sinistro</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted">Ano <?= e($ano) ?></div>
        </div>
    </div>

    <form class="bi-panel bi-filters bi-filters-wrap bi-filters-compact bi-perfil-sinistro-filters" method="get">
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
            <label>Internação</label>
            <select name="modo_internacao">
                <option value="">Todos</option>
                <?php foreach ($modos as $m): ?>
                    <option value="<?= e($m) ?>" <?= $modoInternação === $m ? 'selected' : '' ?>>
                        <?= e($m) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>CID</label>
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
            <label>Grupo patologia</label>
            <select name="grupo_patologia">
                <option value="">Todos</option>
                <?php foreach ($grupos as $g): ?>
                    <option value="<?= e($g) ?>" <?= $grupoPatologia === $g ? 'selected' : '' ?>>
                        <?= e($g) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Internado</label>
            <select name="internado">
                <option value="">Todos</option>
                <option value="s" <?= $internado === 's' ? 'selected' : '' ?>>Sim</option>
                <option value="n" <?= $internado === 'n' ? 'selected' : '' ?>>Não</option>
            </select>
        </div>
        <div class="bi-filter">
            <label>Sexo</label>
            <select name="sexo">
                <option value="">Todos</option>
                <option value="M" <?= $sexo === 'M' ? 'selected' : '' ?>>Masculino</option>
                <option value="F" <?= $sexo === 'F' ? 'selected' : '' ?>>Feminino</option>
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
            <label>Ano</label>
            <input type="number" name="ano" value="<?= e($ano) ?>">
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
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <section class="bi-perfil-sinistro-grid">
        <div class="bi-panel bi-perfil-chart-panel">
            <h3>Alocação dos Custos</h3>
            <div class="bi-perfil-chart-body">
                <div class="bi-chart bi-perfil-sinistro-chart"><canvas id="chartAlloc"></canvas></div>
                <div class="bi-perfil-chart-values">
                    <?php foreach ($allocLabels as $idx => $label): ?>
                        <?php
                            $value = (float)($allocValues[$idx] ?? 0);
                            $pct = $allocTotal > 0 ? ($value / $allocTotal * 100) : 0;
                        ?>
                        <div class="bi-perfil-chart-value">
                            <span class="bi-perfil-chart-dot" style="background: <?= e($perfilColors[$idx] ?? '#7cc4ff') ?>"></span>
                            <span class="bi-perfil-chart-label"><?= e($label) ?></span>
                            <strong>R$ <?= number_format($value, 2, ',', '.') ?></strong>
                            <em><?= number_format($pct, 1, ',', '.') ?>%</em>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="bi-panel bi-perfil-chart-panel">
            <h3>Análise da Glosa</h3>
            <div class="bi-perfil-chart-body">
                <div class="bi-chart bi-perfil-sinistro-chart"><canvas id="chartGlosa"></canvas></div>
                <div class="bi-perfil-chart-values">
                    <?php foreach ($glosaLabels as $idx => $label): ?>
                        <?php
                            $value = (float)($glosaValues[$idx] ?? 0);
                            $pct = $glosaTotalBreakdown > 0 ? ($value / $glosaTotalBreakdown * 100) : 0;
                        ?>
                        <div class="bi-perfil-chart-value">
                            <span class="bi-perfil-chart-dot" style="background: <?= e($perfilColors[$idx] ?? '#7cc4ff') ?>"></span>
                            <span class="bi-perfil-chart-label"><?= e($label) ?></span>
                            <strong>R$ <?= number_format($value, 2, ',', '.') ?></strong>
                            <em><?= number_format($pct, 1, ',', '.') ?>%</em>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="bi-panel bi-perfil-chart-panel bi-perfil-values-panel">
            <h3>Valores Consolidados</h3>
            <div class="bi-chart bi-perfil-values-chart"><canvas id="chartValores"></canvas></div>
        </div>

        <div class="bi-panel bi-perfil-kpi-panel">
            <div class="bi-kpis bi-perfil-kpis">
                <div class="bi-kpi kpi-finance kpi-finance-primary"><small>Valor apresentado</small><strong>R$ <?= number_format((float)($row['valor_apresentado'] ?? 0), 2, ',', '.') ?></strong></div>
                <div class="bi-kpi kpi-finance"><small>Glosa med total</small><strong>R$ <?= number_format((float)($row['glosa_med'] ?? 0), 2, ',', '.') ?></strong></div>
                <div class="bi-kpi kpi-finance"><small>Glosa enf total</small><strong>R$ <?= number_format((float)($row['glosa_enf'] ?? 0), 2, ',', '.') ?></strong></div>
                <div class="bi-kpi kpi-finance"><small>Glosa total</small><strong>R$ <?= number_format((float)($row['glosa_total'] ?? 0), 2, ',', '.') ?></strong></div>
                <div class="bi-kpi kpi-finance"><small>Valor final</small><strong>R$ <?= number_format((float)($row['valor_final'] ?? 0), 2, ',', '.') ?></strong></div>
            </div>
        </div>
    </section>
</div>

<script>
const allocLabels = <?= json_encode($allocLabels) ?>;
const allocValues = <?= json_encode($allocValues) ?>;
const glosaLabels = <?= json_encode($glosaLabels) ?>;
const glosaValues = <?= json_encode($glosaValues) ?>;
const barLabels = <?= json_encode($barLabels) ?>;
const barValues = <?= json_encode($barValues) ?>;
const perfilColors = <?= json_encode($perfilColors) ?>;
const perfilTextColor = '#eef7ff';
const perfilGridColor = 'rgba(235, 246, 255, 0.16)';
function doughnut(ctx, labels, data) {
    return new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor: perfilColors,
                borderColor: 'rgba(7, 32, 52, 0.18)',
                borderWidth: 1,
                hoverBorderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutoutPercentage: 68,
            legend: { display: false },
            layout: { padding: { left: 12, right: 12, top: 8, bottom: 0 } },
            biValueLabels: false
        }
    });
}

function bar(ctx, labels, data) {
    return new Chart(ctx, {
        type: 'bar',
        data: { labels, datasets: [{ data, backgroundColor: ['#7cc4ff','#ff8fb1','#5fd3b5'] }] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: { display: false },
            layout: { padding: { left: 10, right: 18, top: 8, bottom: 0 } },
            scales: {
                xAxes: [{
                    ticks: { fontColor: perfilTextColor, maxRotation: 0, autoSkip: false },
                    gridLines: { display: false }
                }],
                yAxes: [{
                    ticks: {
                        fontColor: perfilTextColor,
                        beginAtZero: true,
                        callback: (value) => window.biMoneyTick ? window.biMoneyTick(value) : value
                    },
                    gridLines: { color: perfilGridColor, zeroLineColor: perfilGridColor }
                }]
            },
            tooltips: { mode: 'index', intersect: false }
        }
    });
}

doughnut(document.getElementById('chartAlloc'), allocLabels, allocValues);
doughnut(document.getElementById('chartGlosa'), glosaLabels, glosaValues);
bar(document.getElementById('chartValores'), barLabels, barValues);

</script>

<?php require_once("templates/footer.php"); ?>
