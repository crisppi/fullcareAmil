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

$sqlOpme = "
    SELECT
        i.id_internacao,
        p.nome_pac,
        h.nome_hosp,
        ca.valor_opme,
        ca.glosa_opme,
        COALESCE(g.opme_ges, 'n') AS opme_flag,
        COALESCE(g.rel_opme_ges, '') AS rel_opme
    FROM tb_internacao i
    LEFT JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao
    LEFT JOIN tb_gestao g ON g.fk_internacao_ges = i.id_internacao
    WHERE {$where}
      AND COALESCE(ca.valor_opme, 0) > 0
      AND (COALESCE(g.opme_ges, 'n') <> 's' OR COALESCE(g.rel_opme_ges, '') = '')
    ORDER BY ca.valor_opme DESC
    LIMIT 200
";
$stmt = $conn->prepare($sqlOpme);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$opmeRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sqlRate = "
    SELECT
        h.nome_hosp,
        SUM(CASE WHEN g.opme_ges = 's' THEN 1 ELSE 0 END) AS opme_sim,
        COUNT(*) AS total
    FROM tb_gestao g
    INNER JOIN tb_internacao i ON i.id_internacao = g.fk_internacao_ges
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    WHERE {$where}
    GROUP BY h.nome_hosp
    ORDER BY opme_sim DESC
";
$stmt = $conn->prepare($sqlRate);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$rateRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));
</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">OPME sem justificativa</h1>
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
        <h3>Casos com OPME sem justificativa</h3>
        <div class="table-responsive">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th>Paciente</th>
                        <th>Hospital</th>
                        <th>Valor OPME</th>
                        <th>Glosa OPME</th>
                        <th>OPME informado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$opmeRows): ?>
                        <tr>
                            <td colspan="5">Sem informações</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($opmeRows as $row): ?>
                            <tr>
                                <td><?= e($row['nome_pac'] ?? '-') ?></td>
                                <td><?= e($row['nome_hosp'] ?? '-') ?></td>
                                <td>R$ <?= number_format((float)($row['valor_opme'] ?? 0), 2, ',', '.') ?></td>
                                <td>R$ <?= number_format((float)($row['glosa_opme'] ?? 0), 2, ',', '.') ?></td>
                                <td><?= e($row['opme_flag'] ?? 'n') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Variação de uso de OPME por hospital</h3>
        <div class="table-responsive">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th>Hospital</th>
                        <th>OPME (sim)</th>
                        <th>Total</th>
                        <th>Taxa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rateRows): ?>
                        <tr>
                            <td colspan="4">Sem informações</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rateRows as $row): ?>
                            <?php
                            $total = (int)($row['total'] ?? 0);
                            $opmeSim = (int)($row['opme_sim'] ?? 0);
                            $taxa = $total > 0 ? ($opmeSim / $total) * 100 : 0;
                            ?>
                            <tr>
                                <td><?= e($row['nome_hosp'] ?? '-') ?></td>
                                <td><?= number_format($opmeSim, 0, ',', '.') ?></td>
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