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

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);
$anos = $conn->query("SELECT DISTINCT YEAR(data_alta_alt) AS ano FROM tb_alta WHERE data_alta_alt IS NOT NULL AND data_alta_alt <> '0000-00-00' AND data_alta_alt <= CURDATE() ORDER BY ano DESC")
    ->fetchAll(PDO::FETCH_COLUMN);
if ($ano === null && !filter_has_var(INPUT_GET, 'ano')) {
    $ano = !empty($anos) ? (int)$anos[0] : (int)date('Y');
}

$where = "al.data_alta_alt IS NOT NULL
    AND al.data_alta_alt <> '0000-00-00'
    AND al.data_alta_alt <= CURDATE()
    AND i.data_intern_int IS NOT NULL
    AND i.data_intern_int <> '0000-00-00'
    AND DATEDIFF(al.data_alta_alt, i.data_intern_int) >= 0";
$params = [];
if (!empty($ano)) {
    $where .= " AND YEAR(al.data_alta_alt) = :ano";
    $params[':ano'] = (int)$ano;
}
if (!empty($mes)) {
    $where .= " AND MONTH(al.data_alta_alt) = :mes";
    $params[':mes'] = (int)$mes;
}

$costExpr = "COALESCE(NULLIF(ca.valor_final_capeante, 0), ca.valor_apresentado_capeante, 0)";

$sql = "
    SELECT
        nome_hosp,
        COUNT(*) AS internacoes,
        SUM(custo_internacao) AS total_custo,
        SUM(diarias) AS total_diarias,
        CASE
            WHEN COUNT(*) > 0
            THEN SUM(custo_internacao) / COUNT(*)
            ELSE 0
        END AS custo_medio,
        CASE
            WHEN SUM(diarias) > 0
            THEN SUM(custo_internacao) / SUM(diarias)
            ELSE 0
        END AS custo_medio_diaria
    FROM (
        SELECT
            i.id_internacao,
            h.id_hospital,
            h.nome_hosp,
            SUM({$costExpr}) AS custo_internacao,
            GREATEST(1, DATEDIFF(al.data_alta_alt, i.data_intern_int) + 1) AS diarias
        FROM tb_capeante ca
        INNER JOIN tb_internacao i ON i.id_internacao = ca.fk_int_capeante
        LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
        LEFT JOIN (
            SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
            FROM tb_alta
            GROUP BY fk_id_int_alt
        ) al ON al.fk_id_int_alt = i.id_internacao
        WHERE {$where}
        GROUP BY i.id_internacao, h.id_hospital, h.nome_hosp, al.data_alta_alt, i.data_intern_int
        HAVING custo_internacao > 0
    ) base
    GROUP BY id_hospital, nome_hosp
    ORDER BY total_custo DESC
";
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260509-filter-icons"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));
</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Volume vs Custo por Hospital</h1>
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
        <h3>Peso e custo por hospital</h3>
        <div class="table-responsive">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th>Hospital</th>
                        <th>Internações</th>
                        <th>Custo total</th>
                        <th>Custo médio</th>
                        <th>Custo médio diária</th>
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
                                <td><?= e($row['nome_hosp'] ?? '-') ?></td>
                                <td><?= number_format((int)($row['internacoes'] ?? 0), 0, ',', '.') ?></td>
                                <td>R$ <?= number_format((float)($row['total_custo'] ?? 0), 2, ',', '.') ?></td>
                                <td>R$ <?= number_format((float)($row['custo_medio'] ?? 0), 2, ',', '.') ?></td>
                                <td>R$ <?= number_format((float)($row['custo_medio_diaria'] ?? 0), 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
