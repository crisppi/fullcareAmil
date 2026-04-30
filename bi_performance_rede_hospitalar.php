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

$hoje = date('Y-m-d');
$dataIni = filter_input(INPUT_GET, 'data_ini');
$dataFim = filter_input(INPUT_GET, 'data_fim');

if (!$dataIni || !$dataFim) {
    $stmtRange = $conn->query("
        SELECT
            MIN(data_intern_int) AS min_dt,
            MAX(data_intern_int) AS max_dt
        FROM tb_internacao
        WHERE data_intern_int IS NOT NULL
          AND data_intern_int <> '0000-00-00'
    ");
    $range = $stmtRange->fetch(PDO::FETCH_ASSOC) ?: [];
    $minDt = $range['min_dt'] ?? null;
    $maxDt = $range['max_dt'] ?? null;
    $dataIni = $dataIni ?: ($minDt ?: date('Y-m-d', strtotime('-180 days')));
    $dataFim = $dataFim ?: ($maxDt ?: $hoje);
}
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$seguradoraId = filter_input(INPUT_GET, 'seguradora_id', FILTER_VALIDATE_INT) ?: null;
$regiao = trim((string)(filter_input(INPUT_GET, 'regiao') ?? ''));
$tipoAdmissao = trim((string)(filter_input(INPUT_GET, 'tipo_admissao') ?? ''));
$modoInternacao = trim((string)(filter_input(INPUT_GET, 'modo_internacao') ?? ''));
$uti = trim((string)(filter_input(INPUT_GET, 'uti') ?? ''));

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);
$seguradoras = $conn->query("SELECT id_seguradora, seguradora_seg FROM tb_seguradora ORDER BY seguradora_seg")
    ->fetchAll(PDO::FETCH_ASSOC);
$regioes = $conn->query("SELECT DISTINCT estado_hosp FROM tb_hospital WHERE estado_hosp IS NOT NULL AND estado_hosp <> '' ORDER BY estado_hosp")
    ->fetchAll(PDO::FETCH_COLUMN);
$tiposAdm = $conn->query("SELECT DISTINCT tipo_admissao_int FROM tb_internacao WHERE tipo_admissao_int IS NOT NULL AND tipo_admissao_int <> '' ORDER BY tipo_admissao_int")
    ->fetchAll(PDO::FETCH_COLUMN);
$modosInt = $conn->query("SELECT DISTINCT modo_internacao_int FROM tb_internacao WHERE modo_internacao_int IS NOT NULL AND modo_internacao_int <> '' ORDER BY modo_internacao_int")
    ->fetchAll(PDO::FETCH_COLUMN);

$where = "i.data_intern_int BETWEEN :data_ini AND :data_fim";
$params = [
    ':data_ini' => $dataIni,
    ':data_fim' => $dataFim,
];
if ($hospitalId) {
    $where .= " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = $hospitalId;
}
if ($seguradoraId) {
    $where .= " AND pa.fk_seguradora_pac = :seguradora_id";
    $params[':seguradora_id'] = $seguradoraId;
}
if ($regiao !== '') {
    $where .= " AND h.estado_hosp = :regiao";
    $params[':regiao'] = $regiao;
}
if ($tipoAdmissao !== '') {
    $where .= " AND i.tipo_admissao_int = :tipo_admissao";
    $params[':tipo_admissao'] = $tipoAdmissao;
}
if ($modoInternacao !== '') {
    $where .= " AND i.modo_internacao_int = :modo_internacao";
    $params[':modo_internacao'] = $modoInternacao;
}

$utiJoin = "LEFT JOIN (SELECT DISTINCT fk_internacao_uti FROM tb_uti) ut ON ut.fk_internacao_uti = i.id_internacao";
if ($uti === 's') {
    $where .= " AND ut.fk_internacao_uti IS NOT NULL";
}
if ($uti === 'n') {
    $where .= " AND ut.fk_internacao_uti IS NULL";
}

$sqlHosp = "
    SELECT
        h.id_hospital,
        h.nome_hosp AS hospital,
        h.estado_hosp AS regiao,
        COUNT(DISTINCT i.id_internacao) AS total_internacoes,
        AVG(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)) AS permanencia_media,
        SUM(COALESCE(ca.valor_apresentado_capeante, 0)) AS valor_apresentado,
        SUM(COALESCE(ca.valor_final_capeante, 0)) AS valor_final,
        SUM(CASE WHEN ca.conta_parada_cap = 's' THEN 1 ELSE 0 END) AS contas_rejeitadas,
        COUNT(DISTINCT ca.id_capeante) AS total_contas,
        COUNT(DISTINCT CASE WHEN ev.fk_internacao_ges IS NOT NULL THEN i.id_internacao END) AS internacoes_evento
    FROM tb_internacao i
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_seguradora s ON s.id_seguradora = pa.fk_seguradora_pac
    LEFT JOIN (
        SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
        FROM tb_alta
        GROUP BY fk_id_int_alt
    ) al ON al.fk_id_int_alt = i.id_internacao
    LEFT JOIN (
        SELECT ca1.*
        FROM tb_capeante ca1
        INNER JOIN (
            SELECT fk_int_capeante, MAX(id_capeante) AS max_id
            FROM tb_capeante
            GROUP BY fk_int_capeante
        ) ca2 ON ca2.max_id = ca1.id_capeante
    ) ca ON ca.fk_int_capeante = i.id_internacao
    LEFT JOIN (
        SELECT fk_internacao_ges
        FROM tb_gestao
        WHERE evento_adverso_ges = 's'
        GROUP BY fk_internacao_ges
    ) ev ON ev.fk_internacao_ges = i.id_internacao
    {$utiJoin}
    WHERE {$where}
    GROUP BY h.id_hospital
    ORDER BY total_internacoes DESC
";
$stmt = $conn->prepare($sqlHosp);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$whereAlta = "al.data_alta_alt BETWEEN :data_ini AND :data_fim";
if ($hospitalId) {
    $whereAlta .= " AND i.fk_hospital_int = :hospital_id";
}
if ($seguradoraId) {
    $whereAlta .= " AND pa.fk_seguradora_pac = :seguradora_id";
}
if ($regiao !== '') {
    $whereAlta .= " AND h.estado_hosp = :regiao";
}
if ($tipoAdmissao !== '') {
    $whereAlta .= " AND i.tipo_admissao_int = :tipo_admissao";
}
if ($modoInternacao !== '') {
    $whereAlta .= " AND i.modo_internacao_int = :modo_internacao";
}
if ($uti === 's') {
    $whereAlta .= " AND ut.fk_internacao_uti IS NOT NULL";
}
if ($uti === 'n') {
    $whereAlta .= " AND ut.fk_internacao_uti IS NULL";
}

$sqlReadm = "
    SELECT
        h.id_hospital,
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
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_seguradora s ON s.id_seguradora = pa.fk_seguradora_pac
    {$utiJoin}
    WHERE {$whereAlta}
    GROUP BY h.id_hospital
";
$stmt = $conn->prepare($sqlReadm);
$stmt->execute($params);
$readmRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$readmMap = [];
foreach ($readmRows as $row) {
    $readmMap[(int)$row['id_hospital']] = [
        'total_altas' => (int)$row['total_altas'],
        'readm' => (int)$row['readm'],
    ];
}

$totals = [
    'internacoes' => 0,
    'contas' => 0,
    'valor_apresentado' => 0.0,
    'valor_final' => 0.0,
    'rejeitadas' => 0,
    'eventos' => 0,
    'readm' => 0,
    'altas' => 0,
    'permanencia_num' => 0.0,
];

foreach ($rows as &$row) {
    $totalInternacoes = (int)($row['total_internacoes'] ?? 0);
    $totalContas = (int)($row['total_contas'] ?? 0);
    $valorApresentado = (float)($row['valor_apresentado'] ?? 0);
    $valorFinal = (float)($row['valor_final'] ?? 0);
    $rejeitadas = (int)($row['contas_rejeitadas'] ?? 0);
    $eventos = (int)($row['internacoes_evento'] ?? 0);
    $permanencia = (float)($row['permanencia_media'] ?? 0);

    $readmData = $readmMap[(int)$row['id_hospital']] ?? ['total_altas' => 0, 'readm' => 0];
    $totalAltas = (int)$readmData['total_altas'];
    $readm = (int)$readmData['readm'];

    $row['custo_apresentado'] = $totalContas > 0 ? $valorApresentado / $totalContas : 0;
    $row['custo_final'] = $totalContas > 0 ? $valorFinal / $totalContas : 0;
    $row['glosa_rate'] = $valorApresentado > 0 ? ($valorApresentado - $valorFinal) / $valorApresentado : 0;
    $row['rejeicao_rate'] = $totalContas > 0 ? $rejeitadas / $totalContas : 0;
    $row['eventos_rate'] = $totalInternacoes > 0 ? $eventos / $totalInternacoes : 0;
    $row['readm_rate'] = $totalAltas > 0 ? $readm / $totalAltas : 0;

    $totals['internacoes'] += $totalInternacoes;
    $totals['contas'] += $totalContas;
    $totals['valor_apresentado'] += $valorApresentado;
    $totals['valor_final'] += $valorFinal;
    $totals['rejeitadas'] += $rejeitadas;
    $totals['eventos'] += $eventos;
    $totals['readm'] += $readm;
    $totals['altas'] += $totalAltas;
    $totals['permanencia_num'] += $permanencia * $totalInternacoes;
}
unset($row);

$network = [
    'custo_apresentado' => $totals['contas'] > 0 ? $totals['valor_apresentado'] / $totals['contas'] : 0,
    'custo_final' => $totals['contas'] > 0 ? $totals['valor_final'] / $totals['contas'] : 0,
    'glosa_rate' => $totals['valor_apresentado'] > 0 ? ($totals['valor_apresentado'] - $totals['valor_final']) / $totals['valor_apresentado'] : 0,
    'rejeicao_rate' => $totals['contas'] > 0 ? $totals['rejeitadas'] / $totals['contas'] : 0,
    'eventos_rate' => $totals['internacoes'] > 0 ? $totals['eventos'] / $totals['internacoes'] : 0,
    'readm_rate' => $totals['altas'] > 0 ? $totals['readm'] / $totals['altas'] : 0,
    'permanencia_media' => $totals['internacoes'] > 0 ? $totals['permanencia_num'] / $totals['internacoes'] : 0,
];

$metrics = [
    'custo_final' => [],
    'glosa_rate' => [],
    'rejeicao_rate' => [],
    'permanencia_media' => [],
    'eventos_rate' => [],
    'readm_rate' => [],
];
foreach ($rows as $row) {
    $metrics['custo_final'][] = $row['custo_final'];
    $metrics['glosa_rate'][] = $row['glosa_rate'];
    $metrics['rejeicao_rate'][] = $row['rejeicao_rate'];
    $metrics['permanencia_media'][] = (float)($row['permanencia_media'] ?? 0);
    $metrics['eventos_rate'][] = $row['eventos_rate'];
    $metrics['readm_rate'][] = $row['readm_rate'];
}

$bounds = [];
foreach ($metrics as $key => $values) {
    $bounds[$key] = [
        'min' => $values ? min($values) : 0,
        'max' => $values ? max($values) : 0,
    ];
}

foreach ($rows as &$row) {
    $scoreParts = [];
    foreach ($bounds as $key => $range) {
        $min = $range['min'];
        $max = $range['max'];
        $val = $key === 'permanencia_media' ? (float)($row['permanencia_media'] ?? 0) : (float)($row[$key] ?? 0);
        $norm = ($max > $min) ? ($val - $min) / ($max - $min) : 0;
        $scoreParts[] = 1 - $norm;
    }
    $row['score'] = $scoreParts ? round((array_sum($scoreParts) / count($scoreParts)) * 100, 1) : 0;
}
unset($row);

usort($rows, function ($a, $b) {
    return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
});

$chartLabels = array_map(fn($r) => $r['hospital'] ?: 'Sem hospital', array_slice($rows, 0, 10));
$chartVals = array_map(fn($r) => round((float)($r['permanencia_media'] ?? 0), 1), array_slice($rows, 0, 10));
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260110">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260110"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>
<style>
.bi-performance-rede .bi-chart {
    min-height: 160px;
    height: 160px;
}

@media (max-width: 900px) {
    .bi-performance-rede .bi-chart {
        min-height: 165px;
        height: 165px;
    }
}
</style>

<div class="bi-wrapper bi-theme bi-performance-rede">
    <div class="bi-header">
        <h1 class="bi-title">Performance Comparativa da Rede Hospitalar</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted">Custo, qualidade e eficiencia por hospital</div>
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegacao">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <form class="bi-panel bi-filters" method="get">
        <div class="bi-filter">
            <label>Data inicial</label>
            <input type="date" name="data_ini" value="<?= e($dataIni) ?>">
        </div>
        <div class="bi-filter">
            <label>Data final</label>
            <input type="date" name="data_fim" value="<?= e($dataFim) ?>">
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
        <div class="bi-filter">
            <label>Regiao</label>
            <select name="regiao">
                <option value="">Todas</option>
                <?php foreach ($regioes as $reg): ?>
                    <option value="<?= e($reg) ?>" <?= $regiao === $reg ? 'selected' : '' ?>>
                        <?= e($reg) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Tipo de admissao</label>
            <select name="tipo_admissao">
                <option value="">Todos</option>
                <?php foreach ($tiposAdm as $tipo): ?>
                    <option value="<?= e($tipo) ?>" <?= $tipoAdmissao === $tipo ? 'selected' : '' ?>>
                        <?= e($tipo) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Modo de internacao</label>
            <select name="modo_internacao">
                <option value="">Todos</option>
                <?php foreach ($modosInt as $modo): ?>
                    <option value="<?= e($modo) ?>" <?= $modoInternacao === $modo ? 'selected' : '' ?>>
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
                <option value="n" <?= $uti === 'n' ? 'selected' : '' ?>>Nao</option>
            </select>
        </div>
        <div class="bi-filter bi-filter-actions">
            <button class="bi-btn bi-btn-primary" type="submit">Aplicar filtros</button>
            <a class="bi-btn bi-btn-secondary" href="<?= $BASE_URL ?>bi/performance-rede-hospitalar">Limpar</a>
        </div>
    </form>

    <div class="bi-panel">
        <div class="bi-kpi-grid">
            <div class="bi-kpi">
                <small>Custo medio apresentado</small>
                <strong><?= number_format($network['custo_apresentado'], 2, ',', '.') ?></strong>
            </div>
            <div class="bi-kpi">
                <small>Custo medio final</small>
                <strong><?= number_format($network['custo_final'], 2, ',', '.') ?></strong>
            </div>
            <div class="bi-kpi">
                <small>Glosa media</small>
                <strong><?= number_format($network['glosa_rate'] * 100, 1, ',', '.') ?>%</strong>
            </div>
            <div class="bi-kpi">
                <small>Permanencia media</small>
                <strong><?= number_format($network['permanencia_media'], 1, ',', '.') ?> d</strong>
            </div>
            <div class="bi-kpi">
                <small>Eventos adversos</small>
                <strong><?= number_format($network['eventos_rate'] * 100, 1, ',', '.') ?>%</strong>
            </div>
            <div class="bi-kpi">
                <small>Readmissao 30d</small>
                <strong><?= number_format($network['readm_rate'] * 100, 1, ',', '.') ?>%</strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <div class="bi-section-title">Permanencia media por hospital</div>
        <div class="bi-chart">
            <canvas id="chartPermanenciaRede"></canvas>
        </div>
    </div>

    <div class="bi-panel">
        <div class="bi-section-title">Ranking de hospitais (custo x qualidade)</div>
        <div class="bi-table">
            <table>
                <thead>
                    <tr>
                        <th>Hospital</th>
                        <th>Score</th>
                        <th>Custo por caso (apresentado)</th>
                        <th>Custo por caso (final)</th>
                        <th>Glosa</th>
                        <th>Rejeicao capeante</th>
                        <th>Permanencia</th>
                        <th>Eventos adversos</th>
                        <th>Readmissao 30d</th>
                        <th>Casos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="10" class="text-center">Sem dados no periodo.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= e($row['hospital'] ?: 'Sem hospital') ?></td>
                                <td><?= number_format((float)$row['score'], 1, ',', '.') ?></td>
                                <td><?= number_format((float)$row['custo_apresentado'], 2, ',', '.') ?></td>
                                <td><?= number_format((float)$row['custo_final'], 2, ',', '.') ?></td>
                                <td><?= number_format((float)$row['glosa_rate'] * 100, 1, ',', '.') ?>%</td>
                                <td><?= number_format((float)$row['rejeicao_rate'] * 100, 1, ',', '.') ?>%</td>
                                <td><?= number_format((float)($row['permanencia_media'] ?? 0), 1, ',', '.') ?> d</td>
                                <td><?= number_format((float)$row['eventos_rate'] * 100, 1, ',', '.') ?>%</td>
                                <td><?= number_format((float)$row['readm_rate'] * 100, 1, ',', '.') ?>%</td>
                                <td><?= (int)($row['total_internacoes'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const chartLabels = <?= json_encode($chartLabels) ?>;
const chartVals = <?= json_encode($chartVals) ?>;
function barChart(ctx, labels, data, color) {
    const scales = window.biChartScales ? window.biChartScales() : undefined;
    return new Chart(ctx, {
        type: 'bar',
        data: { labels, datasets: [{ data, backgroundColor: color }] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: { display: false },
            scales
        }
    });
}
if (chartLabels.length) {
    barChart(document.getElementById('chartPermanenciaRede'), chartLabels, chartVals, 'rgba(64, 181, 255, 0.7)');
}
</script>

<?php require_once("templates/footer.php"); ?>
