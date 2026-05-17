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

$anoInput = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
$mesInput = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT);
$ano = ($anoInput !== null && $anoInput !== false) ? (int)$anoInput : null;
$mes = ($mesInput !== null && $mesInput !== false) ? (int)$mesInput : 0;
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);
$anos = $conn->query("SELECT DISTINCT YEAR(data_intern_int) AS ano FROM tb_internacao WHERE data_intern_int IS NOT NULL AND data_intern_int <> '0000-00-00' ORDER BY ano DESC")
    ->fetchAll(PDO::FETCH_COLUMN);
if ($ano === null && !filter_has_var(INPUT_GET, 'ano')) {
    $ano = !empty($anos) ? (int)$anos[0] : (int)date('Y');
}

$where = "i.data_intern_int IS NOT NULL AND i.data_intern_int <> '0000-00-00'";
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

$summarySql = "
    SELECT
        COUNT(DISTINCT i.id_internacao) AS internacoes,
        COUNT(DISTINCT CASE WHEN g.evento_adverso_ges = 's' THEN i.id_internacao END) AS eventos_adversos,
        COUNT(DISTINCT CASE WHEN LOWER(COALESCE(g.tipo_evento_adverso_gest, '')) LIKE '%infec%' THEN i.id_internacao END) AS infeccoes,
        COUNT(DISTINCT CASE WHEN LOWER(COALESCE(a.tipo_alta_alt, '')) LIKE '%obito%' THEN i.id_internacao END) AS obitos,
        COUNT(DISTINCT CASE WHEN a.data_alta_alt IS NOT NULL AND a.data_alta_alt <> '0000-00-00' THEN i.id_internacao END) AS altas
    FROM tb_internacao i
    LEFT JOIN tb_gestao g ON g.fk_internacao_ges = i.id_internacao
    LEFT JOIN (
        SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt, MAX(tipo_alta_alt) AS tipo_alta_alt
        FROM tb_alta
        GROUP BY fk_id_int_alt
    ) a ON a.fk_id_int_alt = i.id_internacao
    WHERE {$where}
";
$stmt = $conn->prepare($summarySql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$internacoes = (int)($summary['internacoes'] ?? 0);
$eventosAdversos = (int)($summary['eventos_adversos'] ?? 0);
$infeccoes = (int)($summary['infeccoes'] ?? 0);
$obitos = (int)($summary['obitos'] ?? 0);
$altas = (int)($summary['altas'] ?? 0);
$taxaEventos = $internacoes > 0 ? ($eventosAdversos / $internacoes) * 100 : 0;
$taxaObitos = $altas > 0 ? ($obitos / $altas) * 100 : 0;

$rowsSql = "
    SELECT
        h.nome_hosp AS hospital,
        COUNT(DISTINCT i.id_internacao) AS internacoes,
        COUNT(DISTINCT CASE WHEN g.evento_adverso_ges = 's' THEN i.id_internacao END) AS eventos_adversos,
        COUNT(DISTINCT CASE WHEN LOWER(COALESCE(g.tipo_evento_adverso_gest, '')) LIKE '%infec%' THEN i.id_internacao END) AS infeccoes,
        COUNT(DISTINCT CASE WHEN LOWER(COALESCE(a.tipo_alta_alt, '')) LIKE '%obito%' THEN i.id_internacao END) AS obitos
    FROM tb_internacao i
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_gestao g ON g.fk_internacao_ges = i.id_internacao
    LEFT JOIN (
        SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt, MAX(tipo_alta_alt) AS tipo_alta_alt
        FROM tb_alta
        GROUP BY fk_id_int_alt
    ) a ON a.fk_id_int_alt = i.id_internacao
    WHERE {$where}
    GROUP BY h.id_hospital, h.nome_hosp
    HAVING h.id_hospital IS NOT NULL
    ORDER BY eventos_adversos DESC, obitos DESC, internacoes DESC
    LIMIT 10
";
$stmt = $conn->prepare($rowsSql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260516-rounded-bars"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Qualidade e Desfecho</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Visão consolidada de eventos, infecções e óbitos por internação.</div>
        </div>
        <div class="bi-header-actions">
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação BI">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <form class="bi-panel bi-filters" method="get">
        <div class="bi-filter">
            <label>Ano</label>
            <select name="ano">
                <?php foreach ($anos as $anoOpt): ?>
                    <option value="<?= (int)$anoOpt ?>" <?= (int)$anoOpt === (int)$ano ? 'selected' : '' ?>>
                        <?= (int)$anoOpt ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Mês</label>
            <select name="mes">
                <option value="">Todos</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= (int)$mes === $m ? 'selected' : '' ?>><?= $m ?></option>
                <?php endfor; ?>
            </select>
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

    <div class="bi-kpis kpi-dashboard-v2">
        <div class="bi-kpi kpi-card-v2 kpi-card-v2-1">
            <div class="kpi-card-v2-head">
                <span class="kpi-card-v2-icon"><i class="bi bi-hospital"></i></span>
                <small>Internações</small>
            </div>
            <strong><?= number_format($internacoes, 0, ',', '.') ?></strong>
            <span class="kpi-trend kpi-trend-neutral">Período filtrado</span>
        </div>
        <div class="bi-kpi kpi-card-v2 kpi-card-v2-2">
            <div class="kpi-card-v2-head">
                <span class="kpi-card-v2-icon"><i class="bi bi-exclamation-octagon"></i></span>
                <small>Eventos adversos</small>
            </div>
            <strong><?= number_format($eventosAdversos, 0, ',', '.') ?></strong>
            <span class="kpi-trend kpi-trend-neutral"><?= number_format($taxaEventos, 1, ',', '.') ?>% das internações</span>
        </div>
        <div class="bi-kpi kpi-card-v2 kpi-card-v2-3">
            <div class="kpi-card-v2-head">
                <span class="kpi-card-v2-icon"><i class="bi bi-virus2"></i></span>
                <small>Infecções</small>
            </div>
            <strong><?= number_format($infeccoes, 0, ',', '.') ?></strong>
            <span class="kpi-trend kpi-trend-neutral">Eventos classificados</span>
        </div>
        <div class="bi-kpi kpi-card-v2 kpi-card-v2-4">
            <div class="kpi-card-v2-head">
                <span class="kpi-card-v2-icon"><i class="bi bi-heart-pulse"></i></span>
                <small>Óbitos</small>
            </div>
            <strong><?= number_format($obitos, 0, ',', '.') ?></strong>
            <span class="kpi-trend kpi-trend-neutral"><?= number_format($taxaObitos, 1, ',', '.') ?>% das altas</span>
        </div>
    </div>

    <div class="bi-panel">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h3 class="mb-0">Indicadores por hospital</h3>
            <div class="d-flex gap-2">
                <a class="bi-btn bi-btn-secondary" href="<?= $BASE_URL ?>bi/qualidade-eventos">Eventos adversos</a>
                <a class="bi-btn bi-btn-secondary" href="<?= $BASE_URL ?>bi/qualidade-obitos">Óbitos</a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th>Hospital</th>
                        <th>Internações</th>
                        <th>Eventos adversos</th>
                        <th>Infecções</th>
                        <th>Óbitos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="5">Sem informações</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= e($row['hospital'] ?? 'Sem informações') ?></td>
                                <td><?= number_format((int)($row['internacoes'] ?? 0), 0, ',', '.') ?></td>
                                <td><?= number_format((int)($row['eventos_adversos'] ?? 0), 0, ',', '.') ?></td>
                                <td><?= number_format((int)($row['infeccoes'] ?? 0), 0, ',', '.') ?></td>
                                <td><?= number_format((int)($row['obitos'] ?? 0), 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
