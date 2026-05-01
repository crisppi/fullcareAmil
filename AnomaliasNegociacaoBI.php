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
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
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
if (!empty($hospitalId)) {
    $where .= " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = (int)$hospitalId;
}

$sqlNeg = "
    SELECT
        h.nome_hosp,
        COUNT(ng.id_negociacao) AS total_negociacoes,
        SUM(COALESCE(ng.saving, 0)) AS total_saving
    FROM tb_negociacao ng
    INNER JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    WHERE {$where}
    GROUP BY h.nome_hosp
    ORDER BY total_negociacoes DESC
";
$stmt = $conn->prepare($sqlNeg);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$negRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sqlPror = "
    SELECT
        h.nome_hosp,
        COUNT(p.id_prorrogacao) AS total_prorrogacoes,
        SUM(COALESCE(p.diarias_1, 0)) AS total_diarias
    FROM tb_prorrogacao p
    INNER JOIN tb_internacao i ON i.id_internacao = p.fk_internacao_pror
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    WHERE {$where}
    GROUP BY h.nome_hosp
    ORDER BY total_prorrogacoes DESC
";
$stmt = $conn->prepare($sqlPror);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$prorRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));
</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Negociações Suspeitas</h1>
        <div class="bi-header-actions"></div>
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

    <div class="bi-panel">
        <h3>Negociações e descontos (saving)</h3>
        <div class="table-responsive">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th>Hospital</th>
                        <th>Negociações</th>
                        <th>Saving total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$negRows): ?>
                        <tr>
                            <td colspan="3">Sem informações</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($negRows as $row): ?>
                            <tr>
                                <td><?= e($row['nome_hosp'] ?? '-') ?></td>
                                <td><?= number_format((int)($row['total_negociacoes'] ?? 0), 0, ',', '.') ?></td>
                                <td>R$ <?= number_format((float)($row['total_saving'] ?? 0), 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Prorrogações registradas</h3>
        <div class="table-responsive">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th>Hospital</th>
                        <th>Prorrogações</th>
                        <th>Diárias prorrogadas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$prorRows): ?>
                        <tr>
                            <td colspan="3">Sem informações</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($prorRows as $row): ?>
                            <tr>
                                <td><?= e($row['nome_hosp'] ?? '-') ?></td>
                                <td><?= number_format((int)($row['total_prorrogacoes'] ?? 0), 0, ',', '.') ?></td>
                                <td><?= number_format((float)($row['total_diarias'] ?? 0), 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>