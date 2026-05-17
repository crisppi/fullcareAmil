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

$startInput = filter_input(INPUT_GET, 'data_ini');
$endInput = filter_input(INPUT_GET, 'data_fim');
$startDate = $startInput ? date('Y-m-d', strtotime($startInput)) : date('Y-m-d', strtotime('-30 days'));
$endDate = $endInput ? date('Y-m-d', strtotime($endInput)) : date('Y-m-d');
if ($startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}

$baseSql = "
    SELECT
        i.id_internacao,
        i.fk_patologia_int,
        GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1) AS diarias,
        COALESCE(ca.valor_final, 0) AS valor_final,
        CASE
            WHEN i.internado_uti_int = 's'
              OR i.internacao_uti_int = 's'
              OR ut.internado_uti = 's'
              OR ut.internacao_uti = 's'
            THEN 1 ELSE 0
        END AS uti_flag,
        COALESCE(ut.total_uti_dias, 0) AS uti_dias
    FROM tb_internacao i
    LEFT JOIN (
        SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
        FROM tb_alta
        GROUP BY fk_id_int_alt
    ) al ON al.fk_id_int_alt = i.id_internacao
    LEFT JOIN (
        SELECT fk_int_capeante, SUM(valor_final_capeante) AS valor_final
        FROM tb_capeante
        GROUP BY fk_int_capeante
    ) ca ON ca.fk_int_capeante = i.id_internacao
    LEFT JOIN (
        SELECT
            fk_internacao_uti,
            SUM(GREATEST(DATEDIFF(COALESCE(data_alta_uti, CURDATE()), data_internacao_uti), 0) + 1) AS total_uti_dias,
            MAX(internado_uti) AS internado_uti,
            MAX(internacao_uti) AS internacao_uti
        FROM tb_uti
        GROUP BY fk_internacao_uti
    ) ut ON ut.fk_internacao_uti = i.id_internacao
    WHERE i.data_intern_int BETWEEN :ini AND :fim
";

$sql = "
    SELECT
        COALESCE(NULLIF(p.patologia_pat, ''), 'Sem informações') AS patologia,
        SUM(valor_final) AS sinistro,
        SUM(diarias) AS total_diarias,
        COUNT(*) AS internacoes,
        SUM(CASE WHEN uti_flag = 1 THEN 1 ELSE 0 END) AS internacoes_uti,
        SUM(uti_dias) AS total_uti_dias
    FROM ({$baseSql}) base
    LEFT JOIN tb_patologia p ON p.id_patologia = base.fk_patologia_int
    GROUP BY patologia
    ORDER BY sinistro DESC
";
$stmt = $conn->prepare($sql);
$stmt->bindValue(':ini', $startDate);
$stmt->bindValue(':fim', $endDate);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$topRows = array_slice($rows, 0, 10);
$labels = array_map(fn($r) => $r['patologia'], $topRows);
$sinistroVals = array_map(fn($r) => round((float)($r['sinistro'] ?? 0), 2), $topRows);
$diariasVals = array_map(fn($r) => round((float)($r['total_diarias'] ?? 0), 1), $topRows);
$internacoesVals = array_map(fn($r) => (int)($r['internacoes'] ?? 0), $topRows);
$mpVals = array_map(fn($r) => ($r['internacoes'] ?? 0) > 0 ? round($r['total_diarias'] / $r['internacoes'], 1) : 0, $topRows);
$utiVals = array_map(fn($r) => (int)($r['internacoes_uti'] ?? 0), $topRows);
$mpUtiVals = array_map(fn($r) => ($r['internacoes_uti'] ?? 0) > 0 ? round($r['total_uti_dias'] / $r['internacoes_uti'], 1) : 0, $topRows);
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260516-rounded-bars"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));
</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Ranking Patologia</h1>
        <div class="bi-header-actions"></div>
    </div>

    <form class="bi-panel bi-filters" method="get">
        <div class="bi-filter">
            <label>Data Internação (início)</label>
            <input type="date" name="data_ini" value="<?= e($startDate) ?>">
        </div>
        <div class="bi-filter">
            <label>Data Internação (fim)</label>
            <input type="date" name="data_fim" value="<?= e($endDate) ?>">
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="bi-stack">
        <div class="bi-panel">
            <h3>Sinistro</h3>
            <div class="bi-chart compact"><canvas id="chartSinistro"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3>Total Diária</h3>
            <div class="bi-chart compact"><canvas id="chartDiarias"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3>Internações</h3>
            <div class="bi-chart compact"><canvas id="chartInternacoes"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3>MP</h3>
            <div class="bi-chart compact"><canvas id="chartMp"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3>Internações UTI</h3>
            <div class="bi-chart compact"><canvas id="chartUti"></canvas></div>
        </div>
        <div class="bi-panel">
            <h3>MP UTI</h3>
            <div class="bi-chart compact"><canvas id="chartMpUti"></canvas></div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Detalhe por patologia</h3>
        <div class="table-responsive">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th>Patologia</th>
                        <th>Sinistro</th>
                        <th>Total Diárias</th>
                        <th>Internações</th>
                        <th>MP</th>
                        <th>Internações UTI</th>
                        <th>MP UTI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="7">Sem informações</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $internacoes = (int)($row['internacoes'] ?? 0);
                            $internacoesUti = (int)($row['internacoes_uti'] ?? 0);
                            $mp = $internacoes > 0 ? $row['total_diarias'] / $internacoes : 0;
                            $mpUti = $internacoesUti > 0 ? $row['total_uti_dias'] / $internacoesUti : 0;
                            ?>
                            <tr>
                                <td><?= e($row['patologia'] ?? '-') ?></td>
                                <td>R$ <?= number_format((float)($row['sinistro'] ?? 0), 2, ',', '.') ?></td>
                                <td><?= number_format((float)($row['total_diarias'] ?? 0), 1, ',', '.') ?></td>
                                <td><?= number_format($internacoes, 0, ',', '.') ?></td>
                                <td><?= number_format($mp, 1, ',', '.') ?></td>
                                <td><?= number_format($internacoesUti, 0, ',', '.') ?></td>
                                <td><?= number_format($mpUti, 1, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const rankLabels = <?= json_encode($labels) ?>;
    const sinistroVals = <?= json_encode($sinistroVals) ?>;
    const diariasVals = <?= json_encode($diariasVals) ?>;
    const internacoesVals = <?= json_encode($internacoesVals) ?>;
    const mpVals = <?= json_encode($mpVals) ?>;
    const utiVals = <?= json_encode($utiVals) ?>;
    const mpUtiVals = <?= json_encode($mpUtiVals) ?>;

    function buildBar(id, data, color, money) {
        new Chart(document.getElementById(id), {
            type: 'bar',
            data: {
                labels: rankLabels,
                datasets: [{
                    data: data,
                    backgroundColor: color,
                    borderRadius: 8
                }]
            },
            options: {
                legend: {
                    display: false
                },
                scales: biChartScales(),
                tooltips: {
                    callbacks: {
                        label: (item) => money ? biMoneyTick(item.yLabel) : item.yLabel
                    }
                }
            }
        });
    }

    buildBar('chartSinistro', sinistroVals, 'rgba(126,150,255,0.8)', true);
    buildBar('chartDiarias', diariasVals, 'rgba(99, 197, 185, 0.8)', false);
    buildBar('chartInternacoes', internacoesVals, 'rgba(255, 187, 107, 0.8)', false);
    buildBar('chartMp', mpVals, 'rgba(174, 126, 255, 0.8)', false);
    buildBar('chartUti', utiVals, 'rgba(255, 140, 140, 0.8)', false);
    buildBar('chartMpUti', mpUtiVals, 'rgba(140, 209, 120, 0.8)', false);
</script>

<?php require_once("templates/footer.php"); ?>