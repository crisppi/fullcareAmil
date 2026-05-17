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

function fmtPct($value, int $dec = 1): string
{
    return number_format((float)$value, $dec, ',', '.') . '%';
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
    $labels = array_map(fn($r) => shortLabel((string)($r['label'] ?? 'Sem informações')), $slice);
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
        h.id_hospital,
        COALESCE(h.nome_hosp, 'Sem informações') AS label,
        COUNT(DISTINCT i.id_internacao) AS internacoes,
        SUM(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)) AS total_diarias,
        SUM(COALESCE(ca.valor_final, 0)) AS custo_total,
        SUM(CASE WHEN ut.fk_internacao_uti IS NOT NULL THEN 1 ELSE 0 END) AS internacoes_uti,
        SUM(
            CASE WHEN al.data_alta_alt IS NOT NULL AND EXISTS (
                SELECT 1
                FROM tb_internacao i2
                WHERE i2.fk_paciente_int = i.fk_paciente_int
                  AND i2.data_intern_int > al.data_alta_alt
                  AND i2.data_intern_int <= DATE_ADD(al.data_alta_alt, INTERVAL 30 DAY)
            ) THEN 1 ELSE 0 END
        ) AS reinternacoes
    FROM tb_internacao i
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
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
    LEFT JOIN (SELECT DISTINCT fk_internacao_uti FROM tb_uti) ut ON ut.fk_internacao_uti = i.id_internacao
    WHERE {$where}
    GROUP BY h.id_hospital
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
    $internacoesUtiRow = (int)($row['internacoes_uti'] ?? 0);
    $custoTotalRow = (float)($row['custo_total'] ?? 0);
    $row['mp'] = $internacoesRow > 0 ? $diariasRow / $internacoesRow : 0;
    $row['pct_uti'] = $internacoesRow > 0 ? ($internacoesUtiRow / $internacoesRow) * 100 : 0;
    $row['custo_diaria'] = $diariasRow > 0 ? $custoTotalRow / $diariasRow : 0;
}
unset($row);

[$labelsInternacoes, $valsInternacoes] = topMetric($rows, 'internacoes');
[$labelsMp, $valsMp] = topMetric($rows, 'mp');
[$labelsPctUti, $valsPctUti] = topMetric($rows, 'pct_uti');
[$labelsCustoTotal, $valsCustoTotal] = topMetric($rows, 'custo_total');
[$labelsCustoDiaria, $valsCustoDiaria] = topMetric($rows, 'custo_diaria');
[$labelsReinternacoes, $valsReinternacoes] = topMetric($rows, 'reinternacoes');
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="diversos/CoolAdmin-master/vendor/chartjs/Chart.bundle.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260516-rounded-bars"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme bi-ie-page">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Top Hospitais</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Internações, MP, % UTI, custo total, custo diária e reinternações.</div>
        </div>
        <div class="bi-header-actions">
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação BI">
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
                <a class="bi-btn bi-btn-secondary" href="<?= $BASE_URL ?>bi/tops-hospitais">Limpar</a>
            </div>
        </div>
    </form>

    <div class="bi-panel">
        <h3>Resumo</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Hospital</th>
                    <th>Internações</th>
                    <th>MP</th>
                    <th>% UTI / Internação</th>
                    <th>Custo total</th>
                    <th>Custo diária</th>
                    <th>Reinternações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" class="bi-empty">Sem dados com os filtros atuais.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['label'] ?? 'Sem informações') ?></td>
                            <td><?= fmtInt($row['internacoes'] ?? 0) ?></td>
                            <td><?= fmtFloat($row['mp'] ?? 0) ?></td>
                            <td><?= fmtPct($row['pct_uti'] ?? 0) ?></td>
                            <td><?= fmtMoney($row['custo_total'] ?? 0) ?></td>
                            <td><?= fmtMoney($row['custo_diaria'] ?? 0) ?></td>
                            <td><?= fmtInt($row['reinternacoes'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="bi-grid fixed-2">
        <div class="bi-panel">
            <h3>Internações</h3>
            <div class="bi-chart ie-chart-sm"><canvas id="chartInternacoes"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3>MP</h3>
            <div class="bi-chart ie-chart-sm"><canvas id="chartMp"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3>% UTI em relação à internação</h3>
            <div class="bi-chart ie-chart-sm"><canvas id="chartPctUti"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3>Custo total</h3>
            <div class="bi-chart ie-chart-sm"><canvas id="chartCustoTotal"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3>Custo diária</h3>
            <div class="bi-chart ie-chart-sm"><canvas id="chartCustoDiaria"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3>Reinternações</h3>
            <div class="bi-chart ie-chart-sm"><canvas id="chartReinternacoes"></canvas></div>
        </div>
    </div>
</div>

<script>
const biBarValueLabelPlugin = {
    afterDatasetsDraw: function(chart) {
        const ctx = chart.ctx;
        ctx.save();

        chart.data.datasets.forEach(function(dataset, datasetIndex) {
            const meta = chart.getDatasetMeta(datasetIndex);
            if (!meta || meta.hidden) return;

            meta.data.forEach(function(element, index) {
                const value = Number(dataset.data[index] || 0);
                if (!Number.isFinite(value) || value === 0) return;

                const labelFormatter = dataset.valueFormatter || function(v) {
                    return Number(v || 0).toLocaleString('pt-BR');
                };

                ctx.font = '600 12px Poppins, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'bottom';
                ctx.fillStyle = '#f5fbff';
                ctx.shadowColor = 'rgba(8, 20, 38, 0.35)';
                ctx.shadowBlur = 6;

                const topY = Math.min(element._model.base, element._model.y);
                ctx.fillText(labelFormatter(value), element._model.x, topY - 8);
            });
        });

        ctx.restore();
    }
};

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
                maxBarThickness: 48,
                valueFormatter: tickFormatter || function(v) {
                    return Number(v || 0).toLocaleString('pt-BR');
                }
            }]
        },
        plugins: [biBarValueLabelPlugin],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: {
                    top: 28
                }
            },
            biValueLabels: false,
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
buildBarChart('chartPctUti', <?= json_encode($labelsPctUti) ?>, <?= json_encode($valsPctUti) ?>, function (v) { return Number(v || 0).toLocaleString('pt-BR') + '%'; });
buildBarChart('chartCustoTotal', <?= json_encode($labelsCustoTotal) ?>, <?= json_encode($valsCustoTotal) ?>, window.biMoneyTick);
buildBarChart('chartCustoDiaria', <?= json_encode($labelsCustoDiaria) ?>, <?= json_encode($valsCustoDiaria) ?>, window.biMoneyTick);
buildBarChart('chartReinternacoes', <?= json_encode($labelsReinternacoes) ?>, <?= json_encode($valsReinternacoes) ?>);
</script>

<?php require_once("templates/footer.php"); ?>
