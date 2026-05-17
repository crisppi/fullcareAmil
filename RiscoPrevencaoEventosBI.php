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

function fmt_date(?string $value): string
{
    if (!$value) {
        return '-';
    }
    $ts = strtotime($value);
    return $ts ? date('d/m/Y', $ts) : '-';
}

$endDate = filter_input(INPUT_GET, 'data_fim') ?: date('Y-m-d');
$startDate = filter_input(INPUT_GET, 'data_ini') ?: date('Y-m-d', strtotime('-180 days'));
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);

$params = [':ini' => $startDate, ':fim' => $endDate];
$baseWhere = "i.data_intern_int BETWEEN :ini AND :fim";
if ($hospitalId) {
    $baseWhere .= " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = $hospitalId;
}
$where = $baseWhere . " AND LOWER(COALESCE(g.evento_adverso_ges, '')) = 's'";

$sqlSummary = "
    SELECT
        COUNT(DISTINCT i.id_internacao) AS internacoes,
        COUNT(CASE WHEN LOWER(COALESCE(g.evento_adverso_ges, '')) = 's' THEN 1 END) AS eventos,
        COUNT(DISTINCT CASE WHEN LOWER(COALESCE(g.evento_adverso_ges, '')) = 's' THEN g.tipo_evento_adverso_gest END) AS tipos,
        COUNT(DISTINCT CASE WHEN LOWER(COALESCE(g.evento_adverso_ges, '')) = 's' THEN i.fk_hospital_int END) AS hospitais
    FROM tb_internacao i
    LEFT JOIN tb_gestao g ON g.fk_internacao_ges = i.id_internacao
    WHERE {$baseWhere}
";
$stmt = $conn->prepare($sqlSummary);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$internacoesAnalisadas = (int)($summary['internacoes'] ?? 0);
$eventosTotal = (int)($summary['eventos'] ?? 0);
$tiposTotal = (int)($summary['tipos'] ?? 0);
$hospitaisTotal = (int)($summary['hospitais'] ?? 0);
$taxaEvento = $internacoesAnalisadas > 0 ? ($eventosTotal / $internacoesAnalisadas) * 100 : 0;

$sqlTipos = "
    SELECT
        COALESCE(NULLIF(g.tipo_evento_adverso_gest, ''), 'Sem informações') AS tipo,
        COUNT(*) AS total
    FROM tb_gestao g
    JOIN tb_internacao i ON i.id_internacao = g.fk_internacao_ges
    WHERE {$where}
    GROUP BY tipo
    ORDER BY total DESC
    LIMIT 10
";
$stmt = $conn->prepare($sqlTipos);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$tipos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sqlHosp = "
    SELECT
        h.nome_hosp AS hospital,
        COUNT(*) AS total
    FROM tb_gestao g
    JOIN tb_internacao i ON i.id_internacao = g.fk_internacao_ges
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    WHERE {$where}
    GROUP BY hospital
    ORDER BY total DESC
    LIMIT 10
";
$stmt = $conn->prepare($sqlHosp);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$hospRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sqlRecentes = "
    SELECT
        i.id_internacao,
        p.nome_pac,
        h.nome_hosp,
        g.tipo_evento_adverso_gest AS tipo,
        g.rel_evento_adverso_ges AS relato,
        i.data_intern_int
    FROM tb_gestao g
    JOIN tb_internacao i ON i.id_internacao = g.fk_internacao_ges
    LEFT JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    WHERE {$where}
    ORDER BY i.data_intern_int DESC
    LIMIT 40
";
$stmt = $conn->prepare($sqlRecentes);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$recentes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$labelsTipo = array_map(function ($r) {
    return $r['tipo'];
}, $tipos);
$valsTipo = array_map(function ($r) {
    return (int)$r['total'];
}, $tipos);
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260516-rounded-bars"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Eventos Adversos — padrões e causas</h1>
        <div class="bi-header-actions">
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <form class="bi-panel bi-filters bi-filters-wrap" method="get">
        <div class="bi-filter">
            <label>Data inicial</label>
            <input type="date" name="data_ini" value="<?= e($startDate) ?>">
        </div>
        <div class="bi-filter">
            <label>Data final</label>
            <input type="date" name="data_fim" value="<?= e($endDate) ?>">
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
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="bi-panel">
        <h3>Indicadores-chave</h3>
        <div class="bi-kpis kpi-dashboard-v2">
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-1">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-hospital"></i></span>
                    <small>Internações analisadas</small>
                </div>
                <strong><?= number_format($internacoesAnalisadas, 0, ',', '.') ?></strong>
                <span class="kpi-trend kpi-trend-neutral">Período filtrado</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-2">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-exclamation-octagon"></i></span>
                    <small>Total de eventos</small>
                </div>
                <strong><?= number_format($eventosTotal, 0, ',', '.') ?></strong>
                <span class="kpi-trend kpi-trend-neutral"><?= number_format($taxaEvento, 1, ',', '.') ?>% das internações</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-3">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-tags"></i></span>
                    <small>Tipos de evento</small>
                </div>
                <strong><?= number_format($tiposTotal, 0, ',', '.') ?></strong>
                <span class="kpi-trend kpi-trend-neutral">Classificações registradas</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-4">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-building"></i></span>
                    <small>Hospitais com evento</small>
                </div>
                <strong><?= number_format($hospitaisTotal, 0, ',', '.') ?></strong>
                <span class="kpi-trend kpi-trend-neutral">Com registro no recorte</span>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Principais tipos de evento adverso</h3>
        <div class="bi-chart compact"><canvas id="chartEventosTipos"></canvas></div>
    </div>

    <div class="bi-panel">
        <h3>Hospitais com mais eventos adversos</h3>
        <div class="table-responsive">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th>Hospital</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$hospRows): ?>
                        <tr><td colspan="2">Sem informações</td></tr>
                    <?php else: ?>
                        <?php foreach ($hospRows as $row): ?>
                            <tr>
                                <td><?= e($row['hospital'] ?? '-') ?></td>
                                <td><?= number_format((int)$row['total'], 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Eventos recentes</h3>
        <div class="table-responsive">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th>Internação</th>
                        <th>Paciente</th>
                        <th>Hospital</th>
                        <th>Tipo</th>
                        <th>Relato</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$recentes): ?>
                        <tr><td colspan="6">Sem informações</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentes as $row): ?>
                            <tr>
                                <td>#<?= (int)$row['id_internacao'] ?></td>
                                <td><?= e($row['nome_pac'] ?? '-') ?></td>
                                <td><?= e($row['nome_hosp'] ?? '-') ?></td>
                                <td><?= e($row['tipo'] ?? 'Sem informações') ?></td>
                                <td><?= e($row['relato'] ?? '-') ?></td>
                                <td><?= fmt_date($row['data_intern_int'] ?? null) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const labelsTipo = <?= json_encode($labelsTipo) ?>;
const valsTipo = <?= json_encode($valsTipo) ?>;
new Chart(document.getElementById('chartEventosTipos'), {
  type: 'bar',
  data: {
    labels: labelsTipo,
    datasets: [{ data: valsTipo, backgroundColor: 'rgba(255, 149, 102, 0.85)', borderRadius: 8 }]
  },
  options: {
    legend: { display: false },
    scales: biChartScales()
  }
});
</script>

<?php require_once("templates/footer.php"); ?>
