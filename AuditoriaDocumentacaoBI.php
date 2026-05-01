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

$whereIntern = "i.data_intern_int IS NOT NULL";
$whereVis = "v.data_visita_vis IS NOT NULL";
$params = [];
if (!empty($ano)) {
    $whereIntern .= " AND YEAR(i.data_intern_int) = :ano";
    $whereVis .= " AND YEAR(v.data_visita_vis) = :ano";
    $params[':ano'] = (int)$ano;
}
if (!empty($mes)) {
    $whereIntern .= " AND MONTH(i.data_intern_int) = :mes";
    $whereVis .= " AND MONTH(v.data_visita_vis) = :mes";
    $params[':mes'] = (int)$mes;
}
if (!empty($hospitalId)) {
    $whereIntern .= " AND i.fk_hospital_int = :hospital_id";
    $whereVis .= " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = (int)$hospitalId;
}

$sqlVis = "
    SELECT
        h.nome_hosp,
        COUNT(*) AS total,
        SUM(CASE WHEN COALESCE(v.rel_visita_vis,'') <> ''
                  AND COALESCE(v.acoes_int_vis,'') <> ''
                  AND COALESCE(v.programacao_enf,'') <> ''
            THEN 1 ELSE 0 END) AS completos
    FROM tb_visita v
    INNER JOIN tb_internacao i ON i.id_internacao = v.fk_internacao_vis
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    WHERE {$whereVis}
    GROUP BY h.nome_hosp
    ORDER BY completos DESC
";
$stmt = $conn->prepare($sqlVis);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$visRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sqlInt = "
    SELECT
        h.nome_hosp,
        COUNT(*) AS total,
        SUM(CASE WHEN COALESCE(i.rel_int,'') <> ''
                  AND COALESCE(i.acoes_int,'') <> ''
                  AND COALESCE(i.programacao_int,'') <> ''
            THEN 1 ELSE 0 END) AS completos
    FROM tb_internacao i
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    WHERE {$whereIntern}
    GROUP BY h.nome_hosp
    ORDER BY completos DESC
";
$stmt = $conn->prepare($sqlInt);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$intRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));
</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Documentação Completa</h1>
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
        <h3>Visitas com documentação completa</h3>
        <div class="table-responsive">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th>Hospital</th>
                        <th>Completas</th>
                        <th>Total</th>
                        <th>Taxa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$visRows): ?>
                        <tr>
                            <td colspan="4">Sem informações</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($visRows as $row): ?>
                            <?php
                            $total = (int)($row['total'] ?? 0);
                            $completos = (int)($row['completos'] ?? 0);
                            $taxa = $total > 0 ? ($completos / $total) * 100 : 0;
                            ?>
                            <tr>
                                <td><?= e($row['nome_hosp'] ?? '-') ?></td>
                                <td><?= number_format($completos, 0, ',', '.') ?></td>
                                <td><?= number_format($total, 0, ',', '.') ?></td>
                                <td><?= number_format($taxa, 1, ',', '.') ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Internações com documentação completa</h3>
        <div class="table-responsive">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th>Hospital</th>
                        <th>Completas</th>
                        <th>Total</th>
                        <th>Taxa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$intRows): ?>
                        <tr>
                            <td colspan="4">Sem informações</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($intRows as $row): ?>
                            <?php
                            $total = (int)($row['total'] ?? 0);
                            $completos = (int)($row['completos'] ?? 0);
                            $taxa = $total > 0 ? ($completos / $total) * 100 : 0;
                            ?>
                            <tr>
                                <td><?= e($row['nome_hosp'] ?? '-') ?></td>
                                <td><?= number_format($completos, 0, ',', '.') ?></td>
                                <td><?= number_format($total, 0, ',', '.') ?></td>
                                <td><?= number_format($taxa, 1, ',', '.') ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>