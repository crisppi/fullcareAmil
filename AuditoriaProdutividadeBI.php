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

$startInput = filter_input(INPUT_GET, 'data_inicio') ?: '';
$endInput = filter_input(INPUT_GET, 'data_fim') ?: '';
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;

$defaultStart = date('Y-m-01', strtotime('-11 months'));
$defaultEnd = date('Y-m-d');

$startDate = DateTime::createFromFormat('Y-m-d', $startInput) ?: new DateTime($defaultStart);
$endDate = DateTime::createFromFormat('Y-m-d', $endInput) ?: new DateTime($defaultEnd);
$startDate->modify('first day of this month');
$endDate->modify('last day of this month');

$startStr = $startDate->format('Y-m-d');
$endStr = $endDate->format('Y-m-d');

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);

$params = [':start' => $startStr, ':end' => $endStr];
$whereHosp = '';
if ($hospitalId) {
    $whereHosp = " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = (int)$hospitalId;
}

$auditorExpr = "
    CASE
        WHEN NULLIF(v.visita_auditor_prof_med,'') IS NOT NULL
            THEN CONCAT(COALESCE(u_med.usuario_user, v.visita_auditor_prof_med), ' (Medico)')
        WHEN NULLIF(v.visita_auditor_prof_enf,'') IS NOT NULL
            THEN CONCAT(COALESCE(u_enf.usuario_user, v.visita_auditor_prof_enf), ' (Enfermagem)')
        WHEN u.usuario_user IS NOT NULL
            THEN CONCAT(u.usuario_user, ' (Auditor)')
        ELSE 'Sem informacoes'
    END
";

$sqlProd = "
    SELECT {$auditorExpr} AS auditor,
           COUNT(*) AS total_visitas,
           COUNT(DISTINCT DATE(v.data_visita_vis)) AS dias_ativos,
           ROUND(COUNT(*) / NULLIF(COUNT(DISTINCT DATE(v.data_visita_vis)), 0), 2) AS visitas_dia
    FROM tb_visita v
    LEFT JOIN tb_user u ON u.id_usuario = v.fk_usuario_vis
    LEFT JOIN tb_user u_med ON u_med.id_usuario = CAST(NULLIF(v.visita_auditor_prof_med,'') AS UNSIGNED)
    LEFT JOIN tb_user u_enf ON u_enf.id_usuario = CAST(NULLIF(v.visita_auditor_prof_enf,'') AS UNSIGNED)
    LEFT JOIN tb_internacao i ON i.id_internacao = v.fk_internacao_vis
    WHERE v.data_visita_vis BETWEEN :start AND :end
      {$whereHosp}
    GROUP BY auditor
    ORDER BY visitas_dia DESC
    LIMIT 15
";
$stmt = $conn->prepare($sqlProd);
$stmt->execute($params);
$prodRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$prodLabels = array_map(fn($r) => $r['auditor'], $prodRows);
$prodVals = array_map(fn($r) => (float)$r['visitas_dia'], $prodRows);
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="diversos/chartjs/Chart.min.js"></script>
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Auditoria - Produtividade</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted">Últimos 12 meses</div>
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <form class="bi-panel bi-filters" method="get">
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
            <label>Data inicio</label>
            <input type="date" name="data_inicio" value="<?= e($startStr) ?>">
        </div>
        <div class="bi-filter">
            <label>Data fim</label>
            <input type="date" name="data_fim" value="<?= e($endStr) ?>">
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="bi-panel" style="margin-top:16px;">
        <h3 class="text-center" style="margin-bottom:12px;">Produtividade por auditor (visitas/dia)</h3>
        <div class="bi-chart"><canvas id="chartProd"></canvas></div>
    </div>
</div>

<script>
const prodLabels = <?= json_encode($prodLabels) ?>;
const prodVals = <?= json_encode($prodVals) ?>;

new Chart(document.getElementById('chartProd'), {
    type: 'bar',
    data: {
        labels: prodLabels,
        datasets: [{
            label: 'Visitas/dia',
            data: prodVals,
            backgroundColor: 'rgba(141, 208, 255, 0.7)'
        }]
    },
    options: {
        legend: window.biLegendWhite ? window.biLegendWhite : undefined,
        scales: window.biChartScales ? window.biChartScales() : undefined
    }
});
</script>

<?php require_once("templates/footer.php"); ?>
