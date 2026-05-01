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

function fmtInt($value): string
{
    return number_format((int)$value, 0, ',', '.');
}

function fmtFloat($value, int $dec = 1): string
{
    return number_format((float)$value, $dec, ',', '.');
}

function shortLabel(string $value, int $limit = 18): string
{
    $clean = trim($value);
    if (mb_strlen($clean, 'UTF-8') <= $limit) {
        return $clean;
    }
    return mb_substr($clean, 0, $limit - 3, 'UTF-8') . '...';
}

function topMetric(array $rows, string $metric, int $limit = 10): array
{
    $sorted = $rows;
    usort($sorted, function ($a, $b) use ($metric) {
        return (float)($b[$metric] ?? 0) <=> (float)($a[$metric] ?? 0);
    });
    $slice = array_slice($sorted, 0, $limit);
    $labels = array_map(fn($r) => shortLabel((string)($r['label'] ?? 'Paciente')), $slice);
    $values = array_map(fn($r) => round((float)($r[$metric] ?? 0), 2), $slice);
    return [$labels, $values];
}

$internado = trim((string)(filter_input(INPUT_GET, 'internado') ?? ''));
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$mesInput = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT);
$anoInput = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
$mes = $mesInput ? (int)$mesInput : null;
$ano = $anoInput ? (int)$anoInput : null;

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);
$anos = $conn->query("SELECT DISTINCT YEAR(data_intern_int) AS ano FROM tb_internacao WHERE data_intern_int IS NOT NULL AND data_intern_int <> '0000-00-00' ORDER BY ano DESC")
    ->fetchAll(PDO::FETCH_COLUMN);

$where = "i.data_intern_int IS NOT NULL";
$params = [];
if ($ano) {
    $where .= " AND YEAR(i.data_intern_int) = :ano";
    $params[':ano'] = $ano;
}
if ($mes) {
    $where .= " AND MONTH(i.data_intern_int) = :mes";
    $params[':mes'] = $mes;
}
if ($internado === 's' || $internado === 'n') {
    $where .= " AND i.internado_int = :internado";
    $params[':internado'] = $internado;
}
if ($hospitalId) {
    $where .= " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = $hospitalId;
}

$sql = "
    SELECT
        COALESCE(pa.nome_pac, 'Paciente') AS label,
        COUNT(DISTINCT i.id_internacao) AS internacoes,
        SUM(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)) AS total_diarias,
        SUM(COALESCE(ca.valor_final, 0)) AS custo_total
    FROM tb_internacao i
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN (
        SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
        FROM tb_alta
        GROUP BY fk_id_int_alt
    ) al ON al.fk_id_int_alt = i.id_internacao
    LEFT JOIN (
        SELECT fk_int_capeante, SUM(COALESCE(valor_final_capeante, valor_apresentado_capeante, 0)) AS valor_final
        FROM tb_capeante
        GROUP BY fk_int_capeante
    ) ca ON ca.fk_int_capeante = i.id_internacao
    WHERE {$where}
    GROUP BY pa.id_paciente
    ORDER BY custo_total DESC
    LIMIT 30
";
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

foreach ($rows as &$row) {
    $internacoesRow = (int)($row['internacoes'] ?? 0);
    $diariasRow = (float)($row['total_diarias'] ?? 0);
    $row['mp'] = $internacoesRow > 0 ? $diariasRow / $internacoesRow : 0;
}
unset($row);

[$labelsInternacoes, $valsInternacoes] = topMetric($rows, 'internacoes');
[$labelsMp, $valsMp] = topMetric($rows, 'mp');
[$labelsCusto, $valsCusto] = topMetric($rows, 'custo_total');
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="diversos/CoolAdmin-master/vendor/chartjs/Chart.bundle.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme bi-ie-page">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Top Pacientes</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Internações, MP e custo por paciente.</div>
        </div>
        <div class="bi-header-actions">
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegacao BI">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <form method="get">
        <div class="bi-panel bi-filters bi-filters-wrap bi-filters-compact">
            <div class="bi-filter">
                <label>Internados</label>
                <select name="internado">
                    <option value="" <?= $internado === '' ? 'selected' : '' ?>>Todos</option>
                    <option value="s" <?= $internado === 's' ? 'selected' : '' ?>>Sim</option>
                    <option value="n" <?= $internado === 'n' ? 'selected' : '' ?>>Não</option>
                </select>
            </div>
            <div class="bi-filter">
                <label>Hospitais</label>
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
                <label>Mês</label>
                <select name="mes">
                    <option value="">Todos</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $mes == $m ? 'selected' : '' ?>><?= $m ?></option>
                    <?php endfor; ?>
                </select>
            </div>
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
            <div class="bi-actions">
                <button class="bi-btn" type="submit">Aplicar</button>
                <a class="bi-btn bi-btn-secondary" href="<?= $BASE_URL ?>bi/tops-pacientes">Limpar</a>
            </div>
        </div>
    </form>

    <div class="bi-panel">
        <h3>Resumo</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Internações</th>
                    <th>MP</th>
                    <th>Custo</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="4" class="bi-empty">Sem dados com os filtros atuais.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['label'] ?? 'Paciente') ?></td>
                            <td><?= fmtInt($row['internacoes'] ?? 0) ?></td>
                            <td><?= fmtFloat($row['mp'] ?? 0) ?></td>
                            <td><?= fmtMoney($row['custo_total'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="bi-grid fixed-3">
        <div class="bi-panel">
            <h3>Internações</h3>
            <div class="bi-chart ie-chart-sm"><canvas id="chartInternacoes"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3>MP</h3>
            <div class="bi-chart ie-chart-sm"><canvas id="chartMp"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3>Custo</h3>
            <div class="bi-chart ie-chart-sm"><canvas id="chartCusto"></canvas></div>
        </div>
    </div>
</div>

<script>
function buildBarChart(canvasId, labels, values, tickFormatter) {
    const el = document.getElementById(canvasId);
    if (!el || !window.Chart) return;
    const scales = window.biChartScales ? window.biChartScales() : undefined;
    if (tickFormatter && scales && scales.yAxes && scales.yAxes[0] && scales.yAxes[0].ticks) {
        scales.yAxes[0].ticks.callback = tickFormatter;
    }
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
            responsive: true,
            maintainAspectRatio: false,
            legend: { display: false },
            scales: scales,
            tooltips: {
                callbacks: {
                    label: function (tooltipItem) {
                        const v = tooltipItem.yLabel || tooltipItem.value || 0;
                        return tickFormatter ? tickFormatter(v) : Number(v || 0).toLocaleString('pt-BR');
                    }
                }
            }
        }
    });
}

buildBarChart('chartInternacoes', <?= json_encode($labelsInternacoes) ?>, <?= json_encode($valsInternacoes) ?>);
buildBarChart('chartMp', <?= json_encode($labelsMp) ?>, <?= json_encode($valsMp) ?>);
buildBarChart('chartCusto', <?= json_encode($labelsCusto) ?>, <?= json_encode($valsCusto) ?>, window.biMoneyTick);
</script>

<?php require_once("templates/footer.php"); ?>
