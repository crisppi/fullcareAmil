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
$ano = ($anoInput !== null && $anoInput !== false) ? (int)$anoInput : null;
$mes = (int)(filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT) ?: 0);
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$auditorId = filter_input(INPUT_GET, 'auditor_id', FILTER_VALIDATE_INT) ?: null;

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")->fetchAll(PDO::FETCH_ASSOC);
$auditores = $conn->query("SELECT id_usuario, usuario_user FROM tb_user ORDER BY usuario_user")->fetchAll(PDO::FETCH_ASSOC);
$negociacaoRealClause = "UPPER(COALESCE(ng.tipo_negociacao, '')) <> 'PRORROGACAO_AUTOMATICA'";

$savingExpr = "COALESCE(ng.saving, 0)";

if ($ano === null && !filter_has_var(INPUT_GET, 'ano')) {
    $stmtAno = $conn->query("SELECT MAX(YEAR(data_inicio_neg)) AS ano FROM tb_negociacao WHERE data_inicio_neg IS NOT NULL AND data_inicio_neg <> '0000-00-00'");
    $anoDb = $stmtAno->fetch(PDO::FETCH_ASSOC) ?: [];
    $ano = (int)($anoDb['ano'] ?? date('Y'));
}

$where = "ng.data_inicio_neg IS NOT NULL
    AND ng.data_inicio_neg <> '0000-00-00'
    AND ng.saving IS NOT NULL
    AND COALESCE(ng.fk_usuario_neg, 0) > 0
    AND {$negociacaoRealClause}
    AND COALESCE(ng.saving, 0) <> 0
    AND YEAR(ng.data_inicio_neg) = :ano";
$params = [':ano' => $ano];

if ($mes > 0) {
    $where .= " AND MONTH(ng.data_inicio_neg) = :mes";
    $params[':mes'] = $mes;
}
if ($hospitalId) {
    $where .= " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = $hospitalId;
}
if ($auditorId) {
    $where .= " AND ng.fk_usuario_neg = :auditor_id";
    $params[':auditor_id'] = $auditorId;
}

$sqlTotals = "
    SELECT
        COUNT(DISTINCT ng.id_negociacao) AS total_registros,
        SUM({$savingExpr}) AS total_saving
    FROM tb_negociacao ng
    INNER JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int
    WHERE {$where}
";
$stmt = $conn->prepare($sqlTotals);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$totals = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$totalRegistros = (int)($totals['total_registros'] ?? 0);
$totalSaving = (float)($totals['total_saving'] ?? 0);

$sqlRows = "
    SELECT
        COALESCE(h.nome_hosp, 'Sem hospital') AS hospital,
        COALESCE(NULLIF(p.nome_social_pac, ''), NULLIF(p.nome_pac, ''), 'Sem paciente') AS paciente,
        COALESCE(NULLIF(i.senha_int, ''), '-') AS senha,
        COALESCE(NULLIF(ng.tipo_negociacao, ''), 'Não informado') AS tipo_negociacao,
        MONTH(ng.data_inicio_neg) AS mes_numero,
        DATE_FORMAT(STR_TO_DATE(ng.data_inicio_neg, '%Y-%m-%d'), '%m/%Y') AS mes_label,
        {$savingExpr} AS saving_calculado,
        ng.data_inicio_neg AS data_inicio_neg
    FROM tb_negociacao ng
    INNER JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
    WHERE {$where}
    ORDER BY MONTH(ng.data_inicio_neg) DESC, ng.data_inicio_neg DESC, h.nome_hosp ASC, paciente ASC
";
$stmt = $conn->prepare($sqlRows);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260509-filter-icons"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Negociações Detalhadas</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted">Auditoria</div>
            <a class="bi-btn bi-btn-secondary" href="<?= $BASE_URL ?>bi/analise-negociacoes" title="Análise das Negociações">Análise</a>
            <a class="bi-btn bi-btn-secondary" href="<?= $BASE_URL ?>bi/saving-por-auditor" title="Saving por Auditor">Saving por Auditor</a>
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
            <label>Auditor</label>
            <select name="auditor_id">
                <option value="">Todos</option>
                <?php foreach ($auditores as $a): ?>
                    <option value="<?= (int)$a['id_usuario'] ?>" <?= $auditorId == $a['id_usuario'] ? 'selected' : '' ?>>
                        <?= e($a['usuario_user']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Mês</label>
            <select name="mes">
                <option value="0">Todos</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $mes === $m ? 'selected' : '' ?>><?= $m ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Ano</label>
            <input type="number" name="ano" value="<?= e($ano) ?>">
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="bi-panel">
        <div class="bi-kpis">
            <div class="bi-kpi">
                <small>Quantidade de negociações</small>
                <strong><?= $totalRegistros ?></strong>
            </div>
            <div class="bi-kpi">
                <small>Total saving</small>
                <strong>R$ <?= number_format($totalSaving, 2, ',', '.') ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel" style="margin-top:16px;">
        <h3>Tabela detalhada</h3>
        <div class="table-responsive">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th>Hospital</th>
                        <th>Paciente</th>
                        <th>Senha</th>
                        <th>Tipo negociação</th>
                        <th>Mês</th>
                        <th>Saving</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="6">Nenhuma negociação encontrada para os filtros selecionados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= e($row['hospital']) ?></td>
                                <td><?= e($row['paciente']) ?></td>
                                <td><?= e($row['senha']) ?></td>
                                <td><?= e($row['tipo_negociacao']) ?></td>
                                <td><?= e($row['mes_label'] ?: str_pad((string)($row['mes_numero'] ?? 0), 2, '0', STR_PAD_LEFT) . '/' . e($ano)) ?></td>
                                <td>R$ <?= number_format((float)($row['saving_calculado'] ?? 0), 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
