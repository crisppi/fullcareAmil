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

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);
$anos = $conn->query("SELECT DISTINCT YEAR(data_intern_int) AS ano FROM tb_internacao WHERE data_intern_int IS NOT NULL AND data_intern_int <> '0000-00-00' ORDER BY ano DESC")
    ->fetchAll(PDO::FETCH_COLUMN);

$anoInput = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
$mesInput = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT);
$ano = ($anoInput !== null && $anoInput !== false) ? (int)$anoInput : null;
$mes = ($mesInput !== null && $mesInput !== false) ? (int)$mesInput : null;
if ($ano === null && !filter_has_var(INPUT_GET, 'ano')) {
    $ano = !empty($anos) ? (int)$anos[0] : (int)date('Y');
}

$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$limiarInput = filter_input(INPUT_GET, 'limiar', FILTER_VALIDATE_INT);
$limiarSelecionado = ($limiarInput !== null && $limiarInput !== false && $limiarInput > 0) ? (int)$limiarInput : null;
$limiarPadrao = 30;
$limiarOpcoes = [15, 20, 25, 30, 45, 60];

$where = "1=1";
$params = [];
if (!empty($ano)) {
    $where .= " AND YEAR(i.data_intern_int) = :ano";
    $params[':ano'] = (int)$ano;
}
if (!empty($mes)) {
    $where .= " AND MONTH(i.data_intern_int) = :mes";
    $params[':mes'] = (int)$mes;
}
if (!empty($hospitalId)) {
    $where .= " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = (int)$hospitalId;
}

$limiarExpr = $limiarSelecionado !== null
    ? ':limiar_selecionado'
    : 'COALESCE(NULLIF(se.longa_permanencia_seg, 0), :limiar_padrao)';

if ($limiarSelecionado !== null) {
    $params[':limiar_selecionado'] = $limiarSelecionado;
} else {
    $params[':limiar_padrao'] = $limiarPadrao;
}

$sqlBase = "
    FROM tb_internacao i
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_seguradora se ON se.id_seguradora = pa.fk_seguradora_pac
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN (
        SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
        FROM tb_alta
        GROUP BY fk_id_int_alt
    ) al ON al.fk_id_int_alt = i.id_internacao
    WHERE {$where}
";

$sqlLonga = "
    SELECT t.*
    FROM (
        SELECT
            i.id_internacao,
            h.nome_hosp,
            i.rel_int,
            i.data_intern_int,
            GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1) AS diarias,
            {$limiarExpr} AS limiar
        {$sqlBase}
    ) t
    WHERE t.diarias >= t.limiar
";

$sqlStats = "
    SELECT
        COUNT(DISTINCT id_internacao) AS total_internacoes,
        SUM(diarias) AS total_diarias,
        MAX(diarias) AS maior_permanencia,
        ROUND(AVG(diarias), 1) AS mp
    FROM ({$sqlLonga}) x
";
$stmt = $conn->prepare($sqlStats);
$stmt->execute($params);
$stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$totalInternações = (int)($stats['total_internacoes'] ?? 0);
$totalDiárias = (int)($stats['total_diarias'] ?? 0);
$maiorPermanencia = (int)($stats['maior_permanencia'] ?? 0);
$mp = (float)($stats['mp'] ?? 0);

$sqlHosp = "
    SELECT nome_hosp AS label, COUNT(*) AS total
    FROM ({$sqlLonga}) x
    GROUP BY nome_hosp
    ORDER BY total DESC
";
$stmtHosp = $conn->prepare($sqlHosp);
$stmtHosp->execute($params);
$hospRows = $stmtHosp->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sqlTable = "
    SELECT diarias, nome_hosp, data_intern_int,
           COALESCE(NULLIF(rel_int,''), 'Sem relatório') AS relatorio
    FROM ({$sqlLonga}) x
    ORDER BY diarias DESC
    LIMIT 200
";
$stmtTable = $conn->prepare($sqlTable);
$stmtTable->execute($params);
$tableRows = $stmtTable->fetchAll(PDO::FETCH_ASSOC) ?: [];

$hospTotals = [];
foreach ($hospRows as $row) {
    $name = $row['label'] ?? 'Sem informações';
    $hospTotals[$name] = (int)($row['total'] ?? 0);
}
$labelsHosp = array_map(fn($r) => $r['nome_hosp'], $hospitais);
$valuesHosp = array_map(fn($r) => $hospTotals[$r['nome_hosp']] ?? 0, $hospitais);
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260111">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260110"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>
<style>
.lp-chart-compact {
    min-height: 140px;
    height: 140px;
}

.lp-chart-compact canvas {
    height: 140px !important;
}
</style>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Longa Permanência</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted"></div>
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <div class="bi-layout">
        <aside class="bi-sidebar bi-stack">
            <div class="bi-filter-card">
                <div class="bi-filter-card-header">Filtros</div>
                <div class="bi-filter-card-body bi-stack">
                    <div class="bi-filter">
                        <label>Hospital</label>
                        <select name="hospital_id" form="lp-form">
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
                        <select name="mes" form="lp-form">
                            <option value="">Todos</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $mes == $m ? 'selected' : '' ?>><?= $m ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="bi-filter">
                        <label>Ano</label>
                        <select name="ano" form="lp-form">
                            <option value="">Todos</option>
                            <?php foreach ($anos as $anoOpt): ?>
                                <option value="<?= (int)$anoOpt ?>" <?= $ano == $anoOpt ? 'selected' : '' ?>>
                                    <?= (int)$anoOpt ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="bi-filter">
                        <label>Longa permanência</label>
                        <div class="bi-filter-list" id="lp-limiar-list">
                            <button type="button" class="bi-filter-pill <?= $limiarSelecionado === null ? 'active' : '' ?>" data-limiar="">
                                Seguradora
                            </button>
                            <?php foreach ($limiarOpcoes as $opt): ?>
                                <button type="button" class="bi-filter-pill <?= $limiarSelecionado === $opt ? 'active' : '' ?>" data-limiar="<?= (int)$opt ?>">
                                    <?= (int)$opt ?> dias
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <form id="lp-form" method="get">
                        <input type="hidden" name="limiar" value="<?= $limiarSelecionado !== null ? (int)$limiarSelecionado : '' ?>">
                        <button class="bi-filter-btn" type="submit">Aplicar</button>
                    </form>
                </div>
            </div>
        </aside>

        <section class="bi-main bi-stack">
            <div class="bi-kpis kpi-compact">
                <div class="bi-kpi kpi-indigo kpi-compact">
                    <small>Internações</small>
                    <strong><?= number_format($totalInternações, 0, ',', '.') ?></strong>
                </div>
                <div class="bi-kpi kpi-amber kpi-compact">
                    <small>Diárias</small>
                    <strong><?= number_format($totalDiárias, 0, ',', '.') ?></strong>
                </div>
                <div class="bi-kpi kpi-teal kpi-compact">
                    <small>MP</small>
                    <strong><?= number_format($mp, 1, ',', '.') ?></strong>
                </div>
                <div class="bi-kpi kpi-rose kpi-compact">
                    <small>Maior Permanência</small>
                    <strong><?= number_format($maiorPermanencia, 0, ',', '.') ?></strong>
                </div>
            </div>

            <div class="bi-panel">
                <h3>Hospital</h3>
                <div class="bi-chart lp-chart-compact"><canvas id="chartLongaHosp"></canvas></div>
            </div>

            <div class="bi-panel">
                <h3>Diárias</h3>
                <div class="table-responsive">
                    <table class="bi-table">
                        <thead>
                            <tr>
                                <th>Diárias</th>
                                <th>Hospital</th>
                                <th>Data Internação</th>
                                <th>Relatório</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$tableRows): ?>
                                <tr>
                                    <td colspan="4">Sem informações</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($tableRows as $row): ?>
                                    <tr>
                                        <td><?= number_format((int)$row['diarias'], 0, ',', '.') ?></td>
                                        <td><?= e($row['nome_hosp'] ?? 'Sem informações') ?></td>
                                        <td>
                                            <?php if (!empty($row['data_intern_int'])): ?>
                                                <?= e(date('d/m/Y', strtotime($row['data_intern_int']))) ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e($row['relatorio'] ?? 'Sem relatório') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>

<script>
const limiarList = document.getElementById('lp-limiar-list');
const limiarInput = document.querySelector('#lp-form input[name="limiar"]');
if (limiarList && limiarInput) {
  limiarList.addEventListener('click', (event) => {
    const btn = event.target.closest('button[data-limiar]');
    if (!btn) return;
    event.preventDefault();
    const value = btn.getAttribute('data-limiar') || '';
    limiarInput.value = value;
    limiarList.querySelectorAll('.bi-filter-pill').forEach((el) => el.classList.remove('active'));
    btn.classList.add('active');
  });
}
</script>

<script>
const lpLabels = <?= json_encode($labelsHosp) ?>;
const lpValues = <?= json_encode($valuesHosp) ?>;
new Chart(document.getElementById('chartLongaHosp'), {
  type: 'horizontalBar',
  data: {
    labels: lpLabels,
    datasets: [{
      label: 'Internações',
      data: lpValues,
      backgroundColor: 'rgba(126, 150, 255, 0.8)',
      borderRadius: 10,
      maxBarThickness: 34
    }]
  },
  options: {
    maintainAspectRatio: false,
    legend: { display: false },
    scales: {
      xAxes: [{
        ticks: { fontColor: '#e8f1ff' },
        gridLines: { display: false }
      }],
      yAxes: [{
        ticks: { fontColor: '#e8f1ff', autoSkip: false },
        gridLines: { color: 'rgba(255,255,255,0.1)' }
      }]
    }
  }
});
</script>

<?php require_once("templates/footer.php"); ?>
