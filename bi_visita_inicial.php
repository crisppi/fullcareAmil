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
$anos = $conn->query("SELECT DISTINCT YEAR(data_intern_int) AS ano FROM tb_internacao WHERE data_intern_int IS NOT NULL AND data_intern_int <> '0000-00-00' ORDER BY ano DESC")
    ->fetchAll(PDO::FETCH_COLUMN);
$pacientes = $conn->query("SELECT id_paciente, nome_pac FROM tb_paciente ORDER BY nome_pac")
    ->fetchAll(PDO::FETCH_ASSOC);

$where = "i.data_intern_int IS NOT NULL";
$params = [];
if ($ano) {
    $where .= " AND YEAR(i.data_intern_int) = :ano";
    $params[':ano'] = $ano;
}
if ($mes) {
    $where .= " AND MONTH(i.data_intern_int) = :mes";
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
        p.patologia_pat AS patologia,
        i.tipo_admissao_int,
        i.modo_internacao_int,
        i.especialidade_int,
        i.senha_int,
        v.data_visita_vis,
        v.rel_visita_vis,
        v.acoes_int_vis,
        v.programacao_enf
    FROM tb_internacao i
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_patologia p ON p.id_patologia = i.fk_patologia_int
    LEFT JOIN (
        SELECT v1.*
        FROM tb_visita v1
        INNER JOIN (
            SELECT fk_internacao_vis, MIN(data_visita_vis) AS primeira_visita
            FROM tb_visita
            WHERE data_visita_vis IS NOT NULL
            GROUP BY fk_internacao_vis
        ) v2 ON v2.fk_internacao_vis = v1.fk_internacao_vis AND v1.data_visita_vis = v2.primeira_visita
    ) v ON v.fk_internacao_vis = i.id_internacao
    WHERE {$where}
    ORDER BY i.data_intern_int DESC
    LIMIT 1
";
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260509-filter-icons"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Visita Inicial</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Resumo da primeira visita registrada.</div>
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
                        <option value="<?= (int)$h['id_hospital'] ?>" <?= $hospitalId == $h['id_hospital'] ? 'selected' : '' ?>>
                            <?= e($h['nome_hosp']) ?>
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
        <h3>Detalhes da internacao</h3>
        <?php if (!$row): ?>
            <div class="bi-empty" style="padding:16px;">Sem informações com os filtros atuais.</div>
        <?php else: ?>
            <div class="bi-kpis kpi-grid-4">
                <div class="bi-kpi kpi-compact">
                    <small>Hospital</small>
                    <strong><?= e($row['hospital'] ?? '-') ?></strong>
                </div>
                <div class="bi-kpi kpi-compact">
                    <small>Data Internacao</small>
                    <strong><?= !empty($row['data_intern_int']) ? e(date('d/m/Y', strtotime($row['data_intern_int']))) : '-' ?></strong>
                </div>
                <div class="bi-kpi kpi-compact">
                    <small>Acomodacao</small>
                    <strong><?= e($row['acomodacao_int'] ?? '-') ?></strong>
                </div>
                <div class="bi-kpi kpi-compact">
                    <small>Patologia</small>
                    <strong><?= e($row['patologia'] ?? '-') ?></strong>
                </div>
            </div>
            <div class="bi-kpis kpi-grid-4" style="margin-top:10px;">
                <div class="bi-kpi kpi-compact">
                    <small>Tipo Admissao</small>
                    <strong><?= e($row['tipo_admissao_int'] ?? '-') ?></strong>
                </div>
                <div class="bi-kpi kpi-compact">
                    <small>Modo Internacao</small>
                    <strong><?= e($row['modo_internacao_int'] ?? '-') ?></strong>
                </div>
                <div class="bi-kpi kpi-compact">
                    <small>Especialidade</small>
                    <strong><?= e($row['especialidade_int'] ?? '-') ?></strong>
                </div>
                <div class="bi-kpi kpi-compact">
                    <small>Senha</small>
                    <strong><?= e($row['senha_int'] ?? '-') ?></strong>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="bi-panel">
        <h3>Relatorio de visita</h3>
        <?php if (!$row): ?>
            <div class="bi-empty" style="padding:16px;">Sem informações.</div>
        <?php else: ?>
            <div><strong>Data Visita:</strong> <?= !empty($row['data_visita_vis']) ? e(date('d/m/Y', strtotime($row['data_visita_vis']))) : '-' ?></div>
            <div style="margin-top:8px;"><strong>Relatorio:</strong> <?= e($row['rel_visita_vis'] ?? '') ?></div>
            <div style="margin-top:8px;"><strong>Acoes Auditoria:</strong> <?= e($row['acoes_int_vis'] ?? '') ?></div>
            <div style="margin-top:8px;"><strong>Programacao Terapeutica:</strong> <?= e($row['programacao_enf'] ?? '') ?></div>
        <?php endif; ?>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
