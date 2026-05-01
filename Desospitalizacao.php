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
$dataIni = filter_input(INPUT_GET, 'data_ini') ?: date('Y-m-d', strtotime('-120 days'));
$dataFim = filter_input(INPUT_GET, 'data_fim') ?: $hoje;
$internado = trim((string)(filter_input(INPUT_GET, 'internado') ?? ''));
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$tipoInternação = trim((string)(filter_input(INPUT_GET, 'tipo_internacao') ?? ''));
$modoAdmissão = trim((string)(filter_input(INPUT_GET, 'modo_admissao') ?? ''));
$uti = trim((string)(filter_input(INPUT_GET, 'uti') ?? ''));

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);
$tiposInt = $conn->query("SELECT DISTINCT tipo_admissao_int FROM tb_internacao WHERE tipo_admissao_int IS NOT NULL AND tipo_admissao_int <> '' ORDER BY tipo_admissao_int")
    ->fetchAll(PDO::FETCH_COLUMN);
$modosAdm = $conn->query("SELECT DISTINCT modo_internacao_int FROM tb_internacao WHERE modo_internacao_int IS NOT NULL AND modo_internacao_int <> '' ORDER BY modo_internacao_int")
    ->fetchAll(PDO::FETCH_COLUMN);

$where = "i.data_intern_int BETWEEN :data_ini AND :data_fim";
$params = [
    ':data_ini' => $dataIni,
    ':data_fim' => $dataFim,
];
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

$utiJoin = "LEFT JOIN (SELECT DISTINCT fk_internacao_uti FROM tb_uti) ut ON ut.fk_internacao_uti = i.id_internacao";
if ($uti === 's') {
    $where .= " AND ut.fk_internacao_uti IS NOT NULL";
}
if ($uti === 'n') {
    $where .= " AND ut.fk_internacao_uti IS NULL";
}

$sqlBase = "
    FROM tb_internacao i
    {$utiJoin}
    JOIN tb_gestao g ON g.fk_internacao_ges = i.id_internacao AND g.desospitalizacao_ges = 's'
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN (
        SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
        FROM tb_alta
        GROUP BY fk_id_int_alt
    ) al ON al.fk_id_int_alt = i.id_internacao
    WHERE {$where}
";

$sqlStats = "
    SELECT
        COUNT(DISTINCT i.id_internacao) AS total_internacoes,
        SUM(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)) AS total_diarias,
        MAX(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)) AS maior_permanencia,
        ROUND(AVG(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)), 1) AS mp,
        COUNT(DISTINCT g.id_gestao) AS total_eventos
    {$sqlBase}
";
$stmt = $conn->prepare($sqlStats);
$stmt->execute($params);
$stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$totalInternações = (int)($stats['total_internacoes'] ?? 0);
$totalDiárias = (int)($stats['total_diarias'] ?? 0);
$maiorPermanencia = (int)($stats['maior_permanencia'] ?? 0);
$mp = (float)($stats['mp'] ?? 0);
$totalEventos = (int)($stats['total_eventos'] ?? 0);

$sqlHosp = "
    SELECT h.nome_hosp AS label, COUNT(*) AS total
    {$sqlBase}
    GROUP BY h.id_hospital
    ORDER BY total DESC
    LIMIT 12
";
$stmtHosp = $conn->prepare($sqlHosp);
$stmtHosp->execute($params);
$hospRows = $stmtHosp->fetchAll(PDO::FETCH_ASSOC) ?: [];

$tipoRows = [
    ['label' => 'Desospitalizacao', 'total' => $totalEventos],
];

$sqlTable = "
    SELECT
        COALESCE(NULLIF(pa.nome_pac,''), 'Sem informacoes') AS paciente,
        COALESCE(NULLIF(h.nome_hosp,''), 'Sem informacoes') AS hospital,
        COALESCE(NULLIF(g.rel_desospitalizacao_ges,''), '-') AS relatorio
    {$sqlBase}
    ORDER BY i.data_intern_int DESC
    LIMIT 60
";
$stmtTable = $conn->prepare($sqlTable);
$stmtTable->execute($params);
$rowsTable = $stmtTable->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Dashboard Desospitalizacao</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted"></div>
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegacao">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
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
            <label>Tipo Internação</label>
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
            <label>Modo Admissão</label>
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
            <label>Data Internação</label>
            <input type="date" name="data_ini" value="<?= e($dataIni) ?>">
        </div>
        <div class="bi-filter">
            <label>Data Final</label>
            <input type="date" name="data_fim" value="<?= e($dataFim) ?>">
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="bi-panel" style="margin-top:16px;">
        <div class="bi-kpis kpi-compact">
            <div class="bi-kpi kpi-berry kpi-compact"><small>Internações</small><strong><?= $totalInternações ?></strong></div>
            <div class="bi-kpi kpi-teal kpi-compact"><small>Diárias</small><strong><?= $totalDiárias ?></strong></div>
            <div class="bi-kpi kpi-indigo kpi-compact"><small>MP</small><strong><?= number_format($mp, 1, ',', '.') ?></strong></div>
            <div class="bi-kpi kpi-rose kpi-compact"><small>Maior permanencia</small><strong><?= $maiorPermanencia ?></strong></div>
        </div>
    </div>

    <div class="bi-grid fixed-3" style="margin-top:16px;">
        <div class="bi-panel">
            <h3>Hospitais</h3>
            <div class="bi-list">
                <?php if (!$hospRows): ?>
                    <div class="bi-list-item"><span>Sem informacoes</span><span>0</span></div>
                <?php endif; ?>
                <?php foreach ($hospRows as $row): ?>
                    <div class="bi-list-item">
                        <span><?= e($row['label'] ?? 'Sem informacoes') ?></span>
                        <span><?= (int)($row['total'] ?? 0) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="bi-panel">
            <h3>Tipo do evento</h3>
            <div class="bi-list">
                <?php foreach ($tipoRows as $row): ?>
                    <div class="bi-list-item">
                        <span><?= e($row['label'] ?? 'Desospitalizacao') ?></span>
                        <span><?= (int)($row['total'] ?? 0) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="bi-panel bi-panel-compact">
            <div class="bi-kpi kpi-white">
                <small>No. de Desospitalizacao</small>
                <strong class="bi-kpi-big"><?= $totalEventos ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel" style="margin-top:16px;">
        <h3>Relatorios de Desospitalizacao</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Hospital</th>
                    <th>Tipo do evento</th>
                    <th>Relatorio</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rowsTable): ?>
                    <tr>
                        <td colspan="4">Sem informacoes para o filtro selecionado.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($rowsTable as $row): ?>
                    <tr>
                        <td><?= e($row['paciente'] ?? '-') ?></td>
                        <td><?= e($row['hospital'] ?? '-') ?></td>
                        <td>Desospitalizacao</td>
                        <td><?= e($row['relatorio'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
