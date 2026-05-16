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

$anoInput = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
$mesInput = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT);
$ano = ($anoInput !== null && $anoInput !== false) ? (int)$anoInput : null;
$mes = ($mesInput !== null && $mesInput !== false) ? (int)$mesInput : 0;
$internado = trim((string)(filter_input(INPUT_GET, 'internado') ?? ''));
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$pacienteId = filter_input(INPUT_GET, 'paciente_id', FILTER_VALIDATE_INT) ?: null;

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);
$pacientes = $conn->query("SELECT id_paciente, nome_pac FROM tb_paciente ORDER BY nome_pac")
    ->fetchAll(PDO::FETCH_ASSOC);
$anos = $conn->query("SELECT DISTINCT YEAR(data_intern_int) AS ano FROM tb_internacao WHERE data_intern_int IS NOT NULL AND data_intern_int <> '0000-00-00' ORDER BY ano DESC")
    ->fetchAll(PDO::FETCH_COLUMN);

if ($ano === null && !filter_has_var(INPUT_GET, 'ano')) {
    $ano = !empty($anos) ? (int)$anos[0] : (int)date('Y');
}

$where = "i.data_intern_int IS NOT NULL";
$params = [];
if (!empty($ano)) {
    $where .= " AND YEAR(i.data_intern_int) = :ano";
    $params[':ano'] = (int)$ano;
}
if (!empty($mes)) {
    $where .= " AND MONTH(i.data_intern_int) = :mes";
    $params[':mes'] = (int)$mes;
}
if ($internado !== '') {
    $where .= " AND i.internado_int = :internado";
    $params[':internado'] = $internado;
}
if (!empty($hospitalId)) {
    $where .= " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = (int)$hospitalId;
}
if (!empty($pacienteId)) {
    $where .= " AND i.fk_paciente_int = :paciente_id";
    $params[':paciente_id'] = (int)$pacienteId;
}

$sqlIntern = "
    SELECT
        i.*,
        p.nome_pac,
        h.nome_hosp,
        pa.patologia_pat
    FROM tb_internacao i
    LEFT JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_patologia pa ON pa.id_patologia = i.fk_patologia_int
    WHERE {$where}
    ORDER BY i.data_intern_int DESC, i.id_internacao DESC
    LIMIT 1
";
$stmt = $conn->prepare($sqlIntern);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$intern = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

$visita = null;
if ($intern) {
    $stmtVis = $conn->prepare("SELECT * FROM tb_visita WHERE fk_internacao_vis = :id AND (retificado IS NULL OR retificado = 0) ORDER BY data_visita_vis ASC, id_visita ASC LIMIT 1");
    $stmtVis->bindValue(':id', (int)$intern['id_internacao'], PDO::PARAM_INT);
    $stmtVis->execute();
    $visita = $stmtVis->fetch(PDO::FETCH_ASSOC) ?: null;
}
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260509-filter-icons"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Visita Inicial</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted">Ano <?= e($ano) ?></div>
        </div>
    </div>

    <div class="bi-layout">
        <aside class="bi-sidebar bi-stack">
            <div class="bi-filter-card">
                <div class="bi-filter-card-header">Filtros</div>
                <div class="bi-filter-card-body bi-stack">
                    <div class="bi-filter">
                        <label>Internados</label>
                        <select name="internado" form="visita-form">
                            <option value="">Todos</option>
                            <option value="s" <?= $internado === 's' ? 'selected' : '' ?>>Sim</option>
                            <option value="n" <?= $internado === 'n' ? 'selected' : '' ?>>Não</option>
                        </select>
                    </div>
                    <div class="bi-filter">
                        <label>Hospital</label>
                        <select name="hospital_id" form="visita-form">
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
                        <select name="mes" form="visita-form">
                            <option value="">Todos</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= (int)$mes === $m ? 'selected' : '' ?>><?= $m ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="bi-filter">
                        <label>Ano</label>
                        <select name="ano" form="visita-form">
                            <?php foreach ($anos as $anoOpt): ?>
                                <option value="<?= (int)$anoOpt ?>" <?= (int)$anoOpt === (int)$ano ? 'selected' : '' ?>>
                                    <?= (int)$anoOpt ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="bi-filter">
                        <label>Paciente</label>
                        <select name="paciente_id" form="visita-form">
                            <option value="">Selecione</option>
                            <?php foreach ($pacientes as $p): ?>
                                <option value="<?= (int)$p['id_paciente'] ?>" <?= $pacienteId == $p['id_paciente'] ? 'selected' : '' ?>>
                                    <?= e($p['nome_pac']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <form id="visita-form" method="get">
                        <button class="bi-filter-btn" type="submit">Aplicar</button>
                    </form>
                </div>
            </div>
        </aside>

        <section class="bi-main bi-stack">
            <?php if (!$intern): ?>
                <div class="bi-panel">
                    <p class="bi-empty">Selecione um paciente para visualizar a visita inicial.</p>
                </div>
            <?php else: ?>
                <div class="bi-kpis kpi-compact">
                    <div class="bi-kpi kpi-indigo kpi-compact">
                        <small>Hospital</small>
                        <strong><?= e($intern['nome_hosp'] ?? '-') ?></strong>
                    </div>
                    <div class="bi-kpi kpi-amber kpi-compact">
                        <small>Data Internação</small>
                        <strong><?= !empty($intern['data_intern_int']) ? e(date('d/m/Y', strtotime($intern['data_intern_int']))) : '-' ?></strong>
                    </div>
                    <div class="bi-kpi kpi-teal kpi-compact">
                        <small>Acomodação</small>
                        <strong><?= e($intern['acomodacao_int'] ?? '-') ?></strong>
                    </div>
                    <div class="bi-kpi kpi-rose kpi-compact">
                        <small>Patologia</small>
                        <strong><?= e($intern['patologia_pat'] ?? '-') ?></strong>
                    </div>
                    <div class="bi-kpi kpi-indigo kpi-compact">
                        <small>Tipo Admissão</small>
                        <strong><?= e($intern['tipo_admissao_int'] ?? '-') ?></strong>
                    </div>
                    <div class="bi-kpi kpi-amber kpi-compact">
                        <small>Modo Internação</small>
                        <strong><?= e($intern['modo_internacao_int'] ?? '-') ?></strong>
                    </div>
                    <div class="bi-kpi kpi-teal kpi-compact">
                        <small>Especialidade</small>
                        <strong><?= e($intern['especialidade_int'] ?? '-') ?></strong>
                    </div>
                    <div class="bi-kpi kpi-rose kpi-compact">
                        <small>Senha</small>
                        <strong><?= e($intern['senha_int'] ?? '-') ?></strong>
                    </div>
                </div>

                <div class="bi-panel">
                    <div class="bi-section-title">Relatório de Visita</div>
                    <div class="bi-report">
                        <p><strong>Relatório:</strong> <?= e($visita['rel_visita_vis'] ?? $intern['rel_int'] ?? '-') ?></p>
                        <p><strong>Ações da Auditoria:</strong> <?= e($visita['acoes_int_vis'] ?? $intern['acoes_int'] ?? '-') ?></p>
                        <p><strong>Programação Terapêutica:</strong> <?= e($visita['programacao_enf'] ?? $intern['programacao_int'] ?? '-') ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
