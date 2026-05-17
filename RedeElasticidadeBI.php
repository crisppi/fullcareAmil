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

$sql = "
    SELECT
        h.nome_hosp,
        COUNT(DISTINCT i.id_internacao) AS internacoes,
        COUNT(ng.id_negociacao) AS negociacoes,
        SUM(COALESCE(ng.saving, 0)) AS total_saving
    FROM tb_internacao i
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_negociacao ng ON ng.fk_id_int = i.id_internacao
    WHERE {$where}
    GROUP BY h.nome_hosp
    ORDER BY total_saving DESC
";
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260516-rounded-bars"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));
</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Elasticidade de Preço</h1>
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
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="bi-panel">
        <h3>Saving vs volume</h3>
        <div class="table-responsive">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th>Hospital</th>
                        <th>Internações</th>
                        <th>Negociações</th>
                        <th>Saving total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="4">Sem informações</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= e($row['nome_hosp'] ?? '-') ?></td>
                                <td><?= number_format((int)($row['internacoes'] ?? 0), 0, ',', '.') ?></td>
                                <td><?= number_format((int)($row['negociacoes'] ?? 0), 0, ',', '.') ?></td>
                                <td>R$ <?= number_format((float)($row['total_saving'] ?? 0), 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>