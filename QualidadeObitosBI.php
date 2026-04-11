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

$rangeStmt = $conn->query("
    SELECT
        MIN(data_alta_alt) AS min_date,
        MAX(data_alta_alt) AS max_date
    FROM tb_alta
    WHERE data_alta_alt IS NOT NULL
      AND data_alta_alt <> '0000-00-00'
");
$rangeRow = $rangeStmt ? $rangeStmt->fetch(PDO::FETCH_ASSOC) : null;
$minDate = !empty($rangeRow['min_date']) ? $rangeRow['min_date'] : null;
$maxDate = !empty($rangeRow['max_date']) ? $rangeRow['max_date'] : null;

$startDate = $startInput ? date('Y-m-d', strtotime($startInput)) : ($minDate ?: date('Y-m-d', strtotime('-30 days')));
$endDate = $endInput ? date('Y-m-d', strtotime($endInput)) : ($maxDate ?: date('Y-m-d'));
if ($startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
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

$where = "a.data_alta_alt IS NOT NULL AND a.data_alta_alt <> '0000-00-00'";
$params = [':ini' => $startDate, ':fim' => $endDate];
$where .= " AND a.data_alta_alt BETWEEN :ini AND :fim";

if (!empty($hospitalId)) {
    $where .= " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = (int)$hospitalId;
}
if (!empty($seguradoraId)) {
    $where .= " AND p.fk_seguradora_pac = :seguradora_id";
    $params[':seguradora_id'] = (int)$seguradoraId;
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

$utiExpr = "(CASE WHEN i.internado_uti_int = 's'
    OR i.internacao_uti_int = 's'
    OR COALESCE(ut.uti_flag, 0) = 1 THEN 1 ELSE 0 END)";
if ($uti === 's') {
    $where .= " AND {$utiExpr} = 1";
}
if ($uti === 'n') {
    $where .= " AND {$utiExpr} = 0";
}

$sqlBase = "
    FROM tb_alta a
    INNER JOIN tb_internacao i ON i.id_internacao = a.fk_id_int_alt
    LEFT JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_seguradora s ON s.id_seguradora = p.fk_seguradora_pac
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN (
        SELECT
            fk_internacao_uti,
            MAX(CASE WHEN internado_uti = 's' OR internacao_uti = 's' THEN 1 ELSE 0 END) AS uti_flag
        FROM tb_uti
        GROUP BY fk_internacao_uti
    ) ut ON ut.fk_internacao_uti = i.id_internacao
    WHERE {$where}
";

$sqlTotals = "
    SELECT
        COUNT(DISTINCT a.fk_id_int_alt) AS altas_total,
        COUNT(DISTINCT CASE
            WHEN LOWER(COALESCE(a.tipo_alta_alt, '')) LIKE '%obito%' THEN a.fk_id_int_alt
            ELSE NULL
        END) AS obitos_total
    {$sqlBase}
";
$stmt = $conn->prepare($sqlTotals);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$totals = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$altasTotal = (int)($totals['altas_total'] ?? 0);
$obitosTotal = (int)($totals['obitos_total'] ?? 0);
$taxaObito = $altasTotal > 0 ? ($obitosTotal / $altasTotal) * 100 : 0;

$sqlHosp = "
    SELECT
        h.nome_hosp,
        COUNT(DISTINCT a.fk_id_int_alt) AS altas,
        COUNT(DISTINCT CASE
            WHEN LOWER(COALESCE(a.tipo_alta_alt, '')) LIKE '%obito%' THEN a.fk_id_int_alt
            ELSE NULL
        END) AS obitos
    {$sqlBase}
    GROUP BY h.nome_hosp
    ORDER BY obitos DESC
";
$stmt = $conn->prepare($sqlHosp);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$hospRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

foreach ($hospRows as $idx => $row) {
    $altas = (int)($row['altas'] ?? 0);
    $obitos = (int)($row['obitos'] ?? 0);
    $hospRows[$idx]['taxa'] = $altas > 0 ? ($obitos / $altas) * 100 : 0;
}

usort($hospRows, function ($a, $b) {
    return ($b['taxa'] ?? 0) <=> ($a['taxa'] ?? 0);
});
$topHosp = array_slice($hospRows, 0, 10);
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260411d">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260411d"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme bi-ie-page">
    <div class="bi-obitos-hero">
        <div class="bi-obitos-header">
            <div>
                <h1>Taxa de Óbito</h1>
                <p>Óbitos intra-hospitalares registrados na alta.</p>
            </div>
        </div>
        <form class="bi-obitos-filters" method="get">
            <div class="bi-obitos-grid">
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
                    <label>Região</label>
                    <select name="regiao">
                        <option value="">Todas</option>
                        <?php foreach ($regioes as $estado): ?>
                            <option value="<?= e($estado) ?>" <?= $regiao === $estado ? 'selected' : '' ?>><?= e($estado) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bi-filter">
                    <label>Tipo de admissão</label>
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
                    <label>Modo de internação</label>
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
                        <option value="n" <?= $uti === 'n' ? 'selected' : '' ?>>Não</option>
                    </select>
                </div>
                <div class="bi-filter bi-obitos-action">
                    <label>&nbsp;</label>
                    <div class="bi-obitos-action-buttons">
                        <button class="bi-btn bi-btn-primary" type="submit">Aplicar</button>
                        <button class="bi-btn bi-btn-secondary" type="button" onclick="window.location.href=window.location.pathname;">Limpar</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="bi-panel bi-obitos-kpis">
        <div class="bi-panel-header">Indicadores-chave</div>
        <div class="bi-kpis kpi-compact kpi-obitos">
            <div class="bi-kpi kpi-obito kpi-compact">
                <small>Taxa de óbito</small>
                <strong><?= number_format($taxaObito, 1, ',', '.') ?>%</strong>
            </div>
            <div class="bi-kpi kpi-obitos kpi-compact">
                <small>Óbitos</small>
                <strong><?= number_format($obitosTotal, 0, ',', '.') ?></strong>
            </div>
            <div class="bi-kpi kpi-altas kpi-compact">
                <small>Altas analisadas</small>
                <strong><?= number_format($altasTotal, 0, ',', '.') ?></strong>
            </div>
            <div class="bi-kpi kpi-top kpi-compact">
                <small>Hospitais no top 10</small>
                <strong><?= number_format(count($topHosp), 0, ',', '.') ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Hospitais com maior taxa de óbito</h3>
        <div class="table-responsive">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th>Hospital</th>
                        <th>Óbitos</th>
                        <th>Altas</th>
                        <th>Taxa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$topHosp): ?>
                        <tr><td colspan="4">Sem informações</td></tr>
                    <?php else: ?>
                        <?php foreach ($topHosp as $row): ?>
                            <tr>
                                <td><?= e($row['nome_hosp'] ?? '-') ?></td>
                                <td><?= number_format((int)($row['obitos'] ?? 0), 0, ',', '.') ?></td>
                                <td><?= number_format((int)($row['altas'] ?? 0), 0, ',', '.') ?></td>
                                <td><?= number_format((float)($row['taxa'] ?? 0), 1, ',', '.') ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
