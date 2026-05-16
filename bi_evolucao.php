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

function fmtDateBr($value): string
{
    if (!$value) {
        return '-';
    }
    $timestamp = strtotime((string)$value);
    return $timestamp ? date('d/m/Y', $timestamp) : '-';
}

function visitText($value): string
{
    $text = trim((string)$value);
    return $text !== '' ? $text : 'Sem registro.';
}

function visitTextHtml($value): string
{
    return nl2br(e(visitText($value)));
}

function evolucaoOptionScope(array $filters, array $exclude = []): array
{
    $skip = array_fill_keys($exclude, true);
    $where = ["v.data_visita_vis IS NOT NULL"];
    $params = [];

    if (empty($skip['ano']) && !empty($filters['ano'])) {
        $where[] = "YEAR(v.data_visita_vis) = :opt_ano";
        $params[':opt_ano'] = (int)$filters['ano'];
    }
    if (empty($skip['mes']) && !empty($filters['mes'])) {
        $where[] = "MONTH(v.data_visita_vis) = :opt_mes";
        $params[':opt_mes'] = (int)$filters['mes'];
    }
    if (empty($skip['internado']) && ($filters['internado'] ?? '') !== '') {
        $where[] = "i.internado_int = :opt_internado";
        $params[':opt_internado'] = $filters['internado'];
    }
    if (empty($skip['hospital_id']) && !empty($filters['hospital_id'])) {
        $where[] = "i.fk_hospital_int = :opt_hospital_id";
        $params[':opt_hospital_id'] = (int)$filters['hospital_id'];
    }
    if (empty($skip['paciente_id']) && !empty($filters['paciente_id'])) {
        $where[] = "i.fk_paciente_int = :opt_paciente_id";
        $params[':opt_paciente_id'] = (int)$filters['paciente_id'];
    }

    return [$where, $params];
}

function evolucaoFetchOptions(PDO $conn, string $valueExpr, string $labelExpr, string $joins, array $filters, array $exclude = []): array
{
    [$where, $params] = evolucaoOptionScope($filters, $exclude);
    $sql = "
        SELECT {$valueExpr} AS value, {$labelExpr} AS label, COUNT(DISTINCT v.id_visita) AS total
        FROM tb_visita v
        JOIN tb_internacao i ON i.id_internacao = v.fk_internacao_vis
        {$joins}
        WHERE " . implode(' AND ', $where) . "
          AND {$valueExpr} IS NOT NULL
          AND {$labelExpr} IS NOT NULL
          AND {$labelExpr} <> ''
        GROUP BY value, label
        ORDER BY label
    ";
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$internado = trim((string)(filter_input(INPUT_GET, 'internado') ?? ''));
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$mesInput = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT);
$anoInput = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
$mes = $mesInput ? (int)$mesInput : null;
$ano = $anoInput ? (int)$anoInput : null;
if ($ano === null && !filter_has_var(INPUT_GET, 'ano')) {
    $ano = (int)date('Y');
}
if ($internado === '' && !filter_has_var(INPUT_GET, 'internado')) {
    $internado = 's';
}
$pacienteId = filter_input(INPUT_GET, 'paciente_id', FILTER_VALIDATE_INT) ?: null;

$optionFilters = [
    'internado' => $internado,
    'hospital_id' => $hospitalId,
    'mes' => $mes,
    'ano' => $ano,
    'paciente_id' => $pacienteId,
];

$hospitais = evolucaoFetchOptions($conn, 'h.id_hospital', 'h.nome_hosp', 'JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int', $optionFilters, ['hospital_id']);
$anos = array_column(evolucaoFetchOptions($conn, 'YEAR(v.data_visita_vis)', 'YEAR(v.data_visita_vis)', '', $optionFilters, ['ano']), 'value');
rsort($anos, SORT_NUMERIC);
$pacientes = evolucaoFetchOptions($conn, 'pa.id_paciente', 'pa.nome_pac', 'JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int', $optionFilters, ['paciente_id']);

$where = "v.data_visita_vis IS NOT NULL";
$params = [];
if ($ano) {
    $where .= " AND YEAR(v.data_visita_vis) = :ano";
    $params[':ano'] = $ano;
}
if ($mes) {
    $where .= " AND MONTH(v.data_visita_vis) = :mes";
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
if ($pacienteId) {
    $where .= " AND i.fk_paciente_int = :paciente_id";
    $params[':paciente_id'] = $pacienteId;
}

$sql = "
    SELECT
        i.id_internacao,
        pa.nome_pac AS paciente,
        h.nome_hosp AS hospital,
        i.data_intern_int,
        i.acomodacao_int,
        v.data_visita_vis,
        v.rel_visita_vis,
        v.acoes_int_vis,
        v.programacao_enf
    FROM tb_visita v
    JOIN tb_internacao i ON i.id_internacao = v.fk_internacao_vis
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    WHERE {$where}
    ORDER BY i.id_internacao DESC, v.data_visita_vis DESC
";
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$internacoes = [];
foreach ($rows as $row) {
    $id = (int)($row['id_internacao'] ?? 0);
    if (!isset($internacoes[$id])) {
        $internacoes[$id] = [
            'paciente' => $row['paciente'] ?? 'Paciente',
            'hospital' => $row['hospital'] ?? 'Sem informações',
            'data_internacao' => $row['data_intern_int'] ?? '',
            'acomodacao' => $row['acomodacao_int'] ?? 'Sem informações',
            'visitas' => [],
        ];
    }
    $internacoes[$id]['visitas'][] = [
        'data' => $row['data_visita_vis'] ?? '',
        'relatorio' => $row['rel_visita_vis'] ?? '',
        'acoes' => $row['acoes_int_vis'] ?? '',
        'programacao' => $row['programacao_enf'] ?? '',
    ];
}
$totalVisitas = count($rows);
$totalInternacoes = count($internacoes);
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-evolucao-layout-4">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260509-filter-icons"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Evolução</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Histórico de visitas e relatórios por internação.</div>
        </div>
        <div class="bi-header-actions">
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação BI">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <form method="get">
        <div class="bi-panel bi-filters bi-filters-wrap">
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
                        <option value="<?= (int)$h['value'] ?>" <?= $hospitalId == $h['value'] ? 'selected' : '' ?>>
                            <?= e($h['label']) ?>
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
            <div class="bi-filter" style="min-width: 260px;">
                <label>Nome do paciente</label>
                <select name="paciente_id">
                    <option value="">Todos</option>
                    <?php foreach ($pacientes as $p): ?>
                        <option value="<?= (int)$p['value'] ?>" <?= $pacienteId == $p['value'] ? 'selected' : '' ?>>
                            <?= e($p['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="bi-actions">
                <button class="bi-btn" type="submit">Aplicar filtros</button>
                <a class="bi-btn bi-btn-reset" href="<?= $BASE_URL ?>bi/evolucao">Limpar filtros</a>
            </div>
        </div>
    </form>

    <section class="bi-evolution-summary">
        <div class="bi-evolution-stat">
            <span class="bi-evolution-stat-icon"><i class="bi bi-clipboard2-pulse"></i></span>
            <div>
                <small>Internações</small>
                <strong><?= number_format($totalInternacoes, 0, ',', '.') ?></strong>
            </div>
        </div>
        <div class="bi-evolution-stat">
            <span class="bi-evolution-stat-icon"><i class="bi bi-journal-medical"></i></span>
            <div>
                <small>Visitas registradas</small>
                <strong><?= number_format($totalVisitas, 0, ',', '.') ?></strong>
            </div>
        </div>
        <div class="bi-evolution-stat">
            <span class="bi-evolution-stat-icon"><i class="bi bi-calendar3"></i></span>
            <div>
                <small>Período</small>
                <strong><?= $mes ? str_pad((string)$mes, 2, '0', STR_PAD_LEFT) . '/' : '' ?><?= $ano ?: 'Todos' ?></strong>
            </div>
        </div>
    </section>

    <div class="bi-panel bi-evolution-panel">
        <div class="bi-evolution-panel-head">
            <div>
                <h3>Relatório de visitas</h3>
                <p>Histórico agrupado por internação, com evolução assistencial e programação registrada.</p>
            </div>
        </div>
        <?php if (!$internacoes): ?>
            <div class="bi-empty" style="padding:16px;">Sem informações com os filtros atuais.</div>
        <?php else: ?>
            <div class="bi-evolution-list">
                <?php foreach ($internacoes as $internacao): ?>
                    <article class="bi-evolution-card">
                        <header class="bi-evolution-card-head">
                            <div class="bi-evolution-patient-title">
                                <span class="bi-evolution-eyebrow">Paciente</span>
                                <strong><?= e($internacao['paciente']) ?></strong>
                            </div>
                            <span class="bi-evolution-count"><?= count($internacao['visitas']) ?> visita<?= count($internacao['visitas']) === 1 ? '' : 's' ?></span>
                        </header>
                        <div class="bi-evolution-meta">
                            <span><i class="bi bi-calendar-event"></i> Internação: <?= e(fmtDateBr($internacao['data_internacao'])) ?></span>
                            <span><i class="bi bi-door-open"></i> Acomodação: <?= e(visitText($internacao['acomodacao'])) ?></span>
                            <span><i class="bi bi-hospital"></i> <?= e($internacao['hospital']) ?></span>
                        </div>
                        <div class="bi-evolution-visits">
                            <?php foreach ($internacao['visitas'] as $visita): ?>
                                <section class="bi-evolution-visit">
                                    <div class="bi-evolution-visit-date">
                                        <i class="bi bi-calendar-check"></i>
                                        <span><?= e(fmtDateBr($visita['data'])) ?></span>
                                    </div>
                                    <div class="bi-evolution-visit-content">
                                        <div class="bi-evolution-field bi-evolution-field-main">
                                            <small>Relatório</small>
                                            <p><?= visitTextHtml($visita['relatorio']) ?></p>
                                        </div>
                                        <div class="bi-evolution-field-grid">
                                            <div class="bi-evolution-field">
                                                <small>Ações de auditoria</small>
                                                <p><?= visitTextHtml($visita['acoes']) ?></p>
                                            </div>
                                            <div class="bi-evolution-field">
                                                <small>Programação terapêutica</small>
                                                <p><?= visitTextHtml($visita['programacao']) ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
