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
$mes = ($mesInput !== null && $mesInput !== false) ? (int)$mesInput : null;
if ($ano === null && !filter_has_var(INPUT_GET, 'ano')) {
    $stmtAno = $conn->query("
        SELECT MAX(YEAR(data_visita_vis)) AS ano
        FROM tb_visita
        WHERE data_visita_vis IS NOT NULL
          AND data_visita_vis <> '0000-00-00'
    ");
    $anoDb = $stmtAno->fetch(PDO::FETCH_ASSOC) ?: [];
    $ano = (int)($anoDb['ano'] ?? date('Y'));
}

$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$auditorNome = trim((string)(filter_input(INPUT_GET, 'auditor') ?? ''));
$profissional = trim((string)(filter_input(INPUT_GET, 'profissional') ?? ''));

$auditorExpr = "
    CASE
        WHEN NULLIF(v.visita_auditor_prof_med,'') IS NOT NULL
            THEN CONCAT(COALESCE(u_med.usuario_user, v.visita_auditor_prof_med), ' (Médico)')
        WHEN NULLIF(v.visita_auditor_prof_enf,'') IS NOT NULL
            THEN CONCAT(COALESCE(u_enf.usuario_user, v.visita_auditor_prof_enf), ' (Enfermagem)')
        WHEN u.usuario_user IS NOT NULL
            THEN CONCAT(u.usuario_user, ' (Auditor)')
        ELSE 'Sem informações'
    END
";

$auditorIdExpr = "
    CASE
        WHEN NULLIF(v.visita_auditor_prof_med,'') IS NOT NULL
            THEN CAST(NULLIF(v.visita_auditor_prof_med,'') AS UNSIGNED)
        WHEN NULLIF(v.visita_auditor_prof_enf,'') IS NOT NULL
            THEN CAST(NULLIF(v.visita_auditor_prof_enf,'') AS UNSIGNED)
        ELSE COALESCE(v.fk_usuario_vis, 0)
    END
";

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);

$auditores = $conn->query("SELECT DISTINCT {$auditorExpr} AS auditor_nome
    FROM tb_visita v
    LEFT JOIN tb_user u ON u.id_usuario = v.fk_usuario_vis
    LEFT JOIN tb_user u_med ON u_med.id_usuario = CAST(NULLIF(v.visita_auditor_prof_med,'') AS UNSIGNED)
    LEFT JOIN tb_user u_enf ON u_enf.id_usuario = CAST(NULLIF(v.visita_auditor_prof_enf,'') AS UNSIGNED)
    WHERE {$auditorExpr} <> 'Sem informações'
    ORDER BY auditor_nome")
    ->fetchAll(PDO::FETCH_COLUMN);

$where = "v.fk_internacao_vis IS NOT NULL";
$params = [];
if (!empty($ano)) {
    $where .= " AND YEAR(v.data_visita_vis) = :ano";
    $params[':ano'] = (int)$ano;
}
if (!empty($mes)) {
    $where .= " AND MONTH(v.data_visita_vis) = :mes";
    $params[':mes'] = (int)$mes;
}
if (!empty($hospitalId)) {
    $where .= " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = (int)$hospitalId;
}
if (!empty($auditorNome)) {
    $where .= " AND {$auditorExpr} = :auditor_nome";
    $params[':auditor_nome'] = $auditorNome;
}
if ($profissional === 'medico') {
    $where .= " AND (v.visita_med_vis = 's' OR UPPER(v.visita_auditor_prof_med) LIKE 'MED%')";
} elseif ($profissional === 'enfermeiro') {
    $where .= " AND (v.visita_enf_vis = 's' OR UPPER(v.visita_auditor_prof_enf) LIKE 'ENF%')";
}

$sql = "
    SELECT
        {$auditorIdExpr} AS auditor_id,
        {$auditorExpr} AS auditor_nome,
        h.id_hospital,
        h.nome_hosp,
        COUNT(*) AS total
    FROM tb_visita v
    LEFT JOIN tb_user u ON u.id_usuario = v.fk_usuario_vis
    LEFT JOIN tb_user u_med ON u_med.id_usuario = CAST(NULLIF(v.visita_auditor_prof_med,'') AS UNSIGNED)
    LEFT JOIN tb_user u_enf ON u_enf.id_usuario = CAST(NULLIF(v.visita_auditor_prof_enf,'') AS UNSIGNED)
    LEFT JOIN tb_internacao i ON i.id_internacao = v.fk_internacao_vis
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    WHERE {$where}
    GROUP BY auditor_nome, h.id_hospital
    ORDER BY auditor_nome
";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$matrix = [];
$totals = [];
$auditorLabels = [];
foreach ($rows as $row) {
    $aud = $row['auditor_nome'] ?? 'Sem informações';
    $auditorId = (int)($row['auditor_id'] ?? 0);
    $hid = (int)($row['id_hospital'] ?? 0);
    $total = (int)($row['total'] ?? 0);
    if (!isset($matrix[$auditorId])) {
        $matrix[$auditorId] = [];
        $totals[$auditorId] = 0;
    }
    $matrix[$auditorId][$hid] = $total;
    $totals[$auditorId] += $total;
    $auditorLabels[$auditorId] = $aud;
}

$colTotals = [];
$grandTotal = 0;
foreach ($hospitais as $h) {
    $colTotals[$h['id_hospital']] = 0;
}
foreach ($matrix as $auditorId => $data) {
    foreach ($data as $hid => $total) {
        $colTotals[$hid] = ($colTotals[$hid] ?? 0) + $total;
        $grandTotal += $total;
    }
}

// Esconde colunas de hospital sem nenhuma visita no período/filtro atual.
$hospitais = array_values(array_filter($hospitais, static function ($h) use ($colTotals): bool {
    $hid = (int)($h['id_hospital'] ?? 0);
    return (int)($colTotals[$hid] ?? 0) > 0;
}));

$negWhere = "
    COALESCE(ng.fk_usuario_neg, 0) > 0
    AND UPPER(COALESCE(ng.tipo_negociacao, '')) <> 'PRORROGACAO_AUTOMATICA'
";
$negParams = [];
if (!empty($ano)) {
    $negWhere .= " AND YEAR(ng.data_inicio_neg) = :ano";
    $negParams[':ano'] = (int)$ano;
}
if (!empty($mes)) {
    $negWhere .= " AND MONTH(ng.data_inicio_neg) = :mes";
    $negParams[':mes'] = (int)$mes;
}
if (!empty($hospitalId)) {
    $negWhere .= " AND i.fk_hospital_int = :hospital_id";
    $negParams[':hospital_id'] = (int)$hospitalId;
}
if (!empty($auditorNome)) {
    $negWhere .= " AND {$auditorExpr} = :auditor_nome";
    $negParams[':auditor_nome'] = $auditorNome;
}
if ($profissional === 'medico') {
    $negWhere .= " AND (
        COALESCE(NULLIF(u_neg.cargo_user, ''), '') LIKE '%med%'
        OR {$auditorExpr} LIKE '%(Médico)'
    )";
} elseif ($profissional === 'enfermeiro') {
    $negWhere .= " AND (
        COALESCE(NULLIF(u_neg.cargo_user, ''), '') LIKE '%enf%'
        OR {$auditorExpr} LIKE '%(Enfermagem)'
    )";
}

$negSql = "
    SELECT
        COALESCE(ng.fk_usuario_neg, 0) AS auditor_id,
        COALESCE({$auditorExpr}, COALESCE(u_neg.usuario_user, 'Sem informações')) AS auditor_nome,
        h.id_hospital,
        COUNT(DISTINCT ng.id_negociacao) AS total
    FROM tb_negociacao ng
    LEFT JOIN tb_user u_neg ON u_neg.id_usuario = ng.fk_usuario_neg
    LEFT JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_visita v ON v.id_visita = ng.fk_visita_neg
    LEFT JOIN tb_user u ON u.id_usuario = v.fk_usuario_vis
    LEFT JOIN tb_user u_med ON u_med.id_usuario = CAST(NULLIF(v.visita_auditor_prof_med,'') AS UNSIGNED)
    LEFT JOIN tb_user u_enf ON u_enf.id_usuario = CAST(NULLIF(v.visita_auditor_prof_enf,'') AS UNSIGNED)
    WHERE {$negWhere}
    GROUP BY auditor_id, auditor_nome, h.id_hospital
    ORDER BY auditor_nome
";
$negStmt = $conn->prepare($negSql);
$negStmt->execute($negParams);
$negRows = $negStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$negMatrix = [];
$negTotals = [];
$negColTotals = [];
$negGrandTotal = 0;
foreach ($hospitais as $h) {
    $negColTotals[(int)$h['id_hospital']] = 0;
}
foreach ($negRows as $row) {
    $auditorId = (int)($row['auditor_id'] ?? 0);
    $hid = (int)($row['id_hospital'] ?? 0);
    $total = (int)($row['total'] ?? 0);
    if (!isset($negMatrix[$auditorId])) {
        $negMatrix[$auditorId] = [];
        $negTotals[$auditorId] = 0;
    }
    $negMatrix[$auditorId][$hid] = $total;
    $negTotals[$auditorId] += $total;
    if (empty($auditorLabels[$auditorId])) {
        $auditorLabels[$auditorId] = (string)($row['auditor_nome'] ?? 'Sem informações');
    }
    $negColTotals[$hid] = ($negColTotals[$hid] ?? 0) + $total;
    $negGrandTotal += $total;
}

$allAuditorIds = array_values(array_unique(array_merge(array_keys($matrix), array_keys($negMatrix))));
usort($allAuditorIds, static function ($a, $b) use ($auditorLabels): int {
    return strcasecmp((string)($auditorLabels[$a] ?? ''), (string)($auditorLabels[$b] ?? ''));
});

$ratioRows = [];
foreach ($allAuditorIds as $auditorId) {
    $visitasTotal = (int)($totals[$auditorId] ?? 0);
    $negociacoesTotal = (int)($negTotals[$auditorId] ?? 0);
    $ratioRows[$auditorId] = $visitasTotal > 0 ? (($negociacoesTotal / $visitasTotal) * 100) : 0.0;
}
$ratioByHospital = [];
foreach ($allAuditorIds as $auditorId) {
    $ratioByHospital[$auditorId] = [];
    foreach ($hospitais as $h) {
        $hid = (int)($h['id_hospital'] ?? 0);
        $visitasHospital = (int)($matrix[$auditorId][$hid] ?? 0);
        $negociacoesHospital = (int)($negMatrix[$auditorId][$hid] ?? 0);
        $ratioByHospital[$auditorId][$hid] = $visitasHospital > 0 ? (($negociacoesHospital / $visitasHospital) * 100) : 0.0;
    }
}
$ratioColTotals = [];
foreach ($hospitais as $h) {
    $hid = (int)($h['id_hospital'] ?? 0);
    $visitasHospitalTotal = (int)($colTotals[$hid] ?? 0);
    $negociacoesHospitalTotal = (int)($negColTotals[$hid] ?? 0);
    $ratioColTotals[$hid] = $visitasHospitalTotal > 0 ? (($negociacoesHospitalTotal / $visitasHospitalTotal) * 100) : 0.0;
}
$ratioGrand = $grandTotal > 0 ? (($negGrandTotal / $grandTotal) * 100) : 0.0;
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260509-filter-icons"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Auditor Visitas</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted"></div>
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <form class="bi-panel bi-filters" method="get">
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
            <input type="number" name="ano" value="<?= e($ano) ?>" min="2000" max="2100">
        </div>
        <div class="bi-filter">
            <label>Nome Auditor</label>
            <select name="auditor">
                <option value="">Todos</option>
                <?php foreach ($auditores as $a): ?>
                    <option value="<?= e($a) ?>" <?= $auditorNome === $a ? 'selected' : '' ?>>
                        <?= e($a) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Profissional Auditor</label>
            <select name="profissional">
                <option value="">Todos</option>
                <option value="medico" <?= $profissional === 'medico' ? 'selected' : '' ?>>Médico</option>
                <option value="enfermeiro" <?= $profissional === 'enfermeiro' ? 'selected' : '' ?>>Enfermeiro</option>
            </select>
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="bi-panel" style="margin-top:16px;">
        <h3 class="text-center" style="margin-bottom:12px;">Quantidade de Visitas</h3>
        <div class="table-responsive">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th>Auditor</th>
                        <?php foreach ($hospitais as $h): ?>
                            <th><?= e($h['nome_hosp']) ?></th>
                        <?php endforeach; ?>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$matrix): ?>
                        <tr>
                            <td colspan="<?= count($hospitais) + 2 ?>">Sem informações</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($allAuditorIds as $auditorId): ?>
                            <?php $data = $matrix[$auditorId] ?? []; ?>
                            <tr>
                                <td><?= e($auditorLabels[$auditorId] ?? 'Sem informações') ?></td>
                                <?php foreach ($hospitais as $h): ?>
                                    <?php $val = $data[$h['id_hospital']] ?? 0; ?>
                                    <td><?= (int)$val ?></td>
                                <?php endforeach; ?>
                                <td><?= (int)($totals[$auditorId] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td>Total</td>
                            <?php foreach ($hospitais as $h): ?>
                                <td><?= (int)($colTotals[$h['id_hospital']] ?? 0) ?></td>
                            <?php endforeach; ?>
                            <td><?= (int)$grandTotal ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bi-panel" style="margin-top:16px;">
        <h3 class="text-center" style="margin-bottom:12px;">Quantidade de Negociações por Usuário</h3>
        <div class="table-responsive">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <?php foreach ($hospitais as $h): ?>
                            <th><?= e($h['nome_hosp']) ?></th>
                        <?php endforeach; ?>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$allAuditorIds): ?>
                        <tr>
                            <td colspan="<?= count($hospitais) + 2 ?>">Sem informações</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($allAuditorIds as $auditorId): ?>
                            <?php $data = $negMatrix[$auditorId] ?? []; ?>
                            <tr>
                                <td><?= e($auditorLabels[$auditorId] ?? 'Sem informações') ?></td>
                                <?php foreach ($hospitais as $h): ?>
                                    <td><?= (int)($data[$h['id_hospital']] ?? 0) ?></td>
                                <?php endforeach; ?>
                                <td><?= (int)($negTotals[$auditorId] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td>Total</td>
                            <?php foreach ($hospitais as $h): ?>
                                <td><?= (int)($negColTotals[$h['id_hospital']] ?? 0) ?></td>
                            <?php endforeach; ?>
                            <td><?= (int)$negGrandTotal ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bi-panel" style="margin-top:16px;">
        <h3 class="text-center" style="margin-bottom:12px;">Percentual de Negociações sobre Visitas</h3>
        <div class="table-responsive">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <?php foreach ($hospitais as $h): ?>
                            <th><?= e($h['nome_hosp']) ?></th>
                        <?php endforeach; ?>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$allAuditorIds): ?>
                        <tr>
                            <td colspan="<?= count($hospitais) + 2 ?>">Sem informações</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($allAuditorIds as $auditorId): ?>
                            <tr>
                                <td><?= e($auditorLabels[$auditorId] ?? 'Sem informações') ?></td>
                                <?php foreach ($hospitais as $h): ?>
                                    <td><?= number_format((float)($ratioByHospital[$auditorId][$h['id_hospital']] ?? 0), 1, ',', '.') ?>%</td>
                                <?php endforeach; ?>
                                <td><?= number_format((float)($ratioRows[$auditorId] ?? 0), 1, ',', '.') ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td>Total</td>
                            <?php foreach ($hospitais as $h): ?>
                                <td><?= number_format((float)($ratioColTotals[$h['id_hospital']] ?? 0), 1, ',', '.') ?>%</td>
                            <?php endforeach; ?>
                            <td><?= number_format($ratioGrand, 1, ',', '.') ?>%</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
