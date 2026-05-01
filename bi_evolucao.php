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

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);
$anos = $conn->query("SELECT DISTINCT YEAR(data_visita_vis) AS ano FROM tb_visita WHERE data_visita_vis IS NOT NULL ORDER BY ano DESC")
    ->fetchAll(PDO::FETCH_COLUMN);
$pacientes = $conn->query("SELECT id_paciente, nome_pac FROM tb_paciente ORDER BY nome_pac")
    ->fetchAll(PDO::FETCH_ASSOC);

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
            'hospital' => $row['hospital'] ?? 'Sem informacoes',
            'data_internacao' => $row['data_intern_int'] ?? '',
            'acomodacao' => $row['acomodacao_int'] ?? 'Sem informacoes',
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
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Evolucao</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Historico de visitas e relatorios por internacao.</div>
        </div>
        <div class="bi-header-actions">
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegacao BI">
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
                    <option value="n" <?= $internado === 'n' ? 'selected' : '' ?>>Nao</option>
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
                <label>Mes</label>
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
                        <option value="<?= (int)$p['id_paciente'] ?>" <?= $pacienteId == $p['id_paciente'] ? 'selected' : '' ?>>
                            <?= e($p['nome_pac']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="bi-actions">
                <button class="bi-btn" type="submit">Aplicar filtros</button>
            </div>
        </div>
    </form>

    <div class="bi-panel">
        <h3>Relatorio de visitas</h3>
        <?php if (!$internacoes): ?>
            <div class="bi-empty" style="padding:16px;">Sem informacoes com os filtros atuais.</div>
        <?php else: ?>
            <?php foreach ($internacoes as $internacao): ?>
                <div class="bi-panel" style="margin-bottom:16px;">
                    <div style="font-weight:600; margin-bottom:8px;">
                        Paciente: <?= e($internacao['paciente']) ?> |
                        Data Internacao: <?= $internacao['data_internacao'] ? e(date('d/m/Y', strtotime($internacao['data_internacao']))) : '-' ?> |
                        Acomodacao: <?= e($internacao['acomodacao']) ?> |
                        Hospital: <?= e($internacao['hospital']) ?>
                    </div>
                    <?php foreach ($internacao['visitas'] as $visita): ?>
                        <div style="border-top:1px solid rgba(255,255,255,0.15); padding-top:10px; margin-top:10px;">
                            <div style="font-weight:600;">Data Visita: <?= $visita['data'] ? e(date('d/m/Y', strtotime($visita['data']))) : '-' ?></div>
                            <div><strong>Relatorio:</strong> <?= e($visita['relatorio']) ?></div>
                            <div><strong>Acoes Auditoria:</strong> <?= e($visita['acoes']) ?></div>
                            <div><strong>Programacao Terapeutica:</strong> <?= e($visita['programacao']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
