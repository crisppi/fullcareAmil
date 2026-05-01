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
$limiarInput = filter_input(INPUT_GET, 'limiar', FILTER_VALIDATE_INT);
$limiar = ($limiarInput !== null && $limiarInput !== false && $limiarInput > 0) ? (int)$limiarInput : 20;

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

$sqlReadm = "
    SELECT
        h.nome_hosp,
        SUM(CASE WHEN DATEDIFF(i.data_intern_int, ultima_alta) BETWEEN 1 AND 7 THEN 1 ELSE 0 END) AS readm_7,
        SUM(CASE WHEN DATEDIFF(i.data_intern_int, ultima_alta) BETWEEN 1 AND 30 THEN 1 ELSE 0 END) AS readm_30
    FROM (
        SELECT
            i.id_internacao,
            i.fk_hospital_int,
            i.fk_paciente_int,
            i.data_intern_int,
            (
                SELECT MAX(al2.data_alta_alt)
                FROM tb_internacao i2
                LEFT JOIN tb_alta al2 ON al2.fk_id_int_alt = i2.id_internacao
                WHERE i2.fk_paciente_int = i.fk_paciente_int
                  AND al2.data_alta_alt < i.data_intern_int
            ) AS ultima_alta
        FROM tb_internacao i
        WHERE {$where}
    ) i
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    WHERE i.ultima_alta IS NOT NULL
    GROUP BY h.nome_hosp
    ORDER BY readm_30 DESC
";
$stmt = $conn->prepare($sqlReadm);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->execute();
$readmRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sqlLong = "
    SELECT
        p.nome_pac,
        h.nome_hosp,
        i.data_intern_int,
        GREATEST(1, DATEDIFF(CURDATE(), i.data_intern_int) + 1) AS diarias
    FROM tb_internacao i
    LEFT JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    WHERE {$where}
      AND i.internado_int = 's'
      AND GREATEST(1, DATEDIFF(CURDATE(), i.data_intern_int) + 1) >= :limiar
    ORDER BY diarias DESC
    LIMIT 200
";
$stmt = $conn->prepare($sqlLong);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_INT);
}
$stmt->bindValue(':limiar', $limiar, PDO::PARAM_INT);
$stmt->execute();
$longRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Risco de Readmissão</h1>
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
        <div class="bi-filter">
            <label>Longa permanência (dias)</label>
            <select name="limiar">
                <?php foreach ([15, 20, 25, 30, 45, 60] as $opt): ?>
                    <option value="<?= $opt ?>" <?= (int)$limiar === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
            <button class="bi-btn bi-btn-secondary bi-btn-reset" type="button" onclick="window.location.href=window.location.pathname;">Limpar</button>
        </div>
    </form>

    <div class="bi-panel">
        <h3>Readmissão precoce por hospital</h3>
        <div class="table-responsive">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th>Hospital</th>
                        <th>Readmissão 7d</th>
                        <th>Readmissão 30d</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$readmRows): ?>
                        <tr><td colspan="3">Sem informações</td></tr>
                    <?php else: ?>
                        <?php foreach ($readmRows as $row): ?>
                            <tr>
                                <td><?= e($row['nome_hosp'] ?? '-') ?></td>
                                <td><?= number_format((int)($row['readm_7'] ?? 0), 0, ',', '.') ?></td>
                                <td><?= number_format((int)($row['readm_30'] ?? 0), 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Risco de longa permanência (internados)</h3>
        <div class="table-responsive">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th>Paciente</th>
                        <th>Hospital</th>
                        <th>Data Internação</th>
                        <th>Diárias</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$longRows): ?>
                        <tr><td colspan="4">Sem informações</td></tr>
                    <?php else: ?>
                        <?php foreach ($longRows as $row): ?>
                            <tr>
                                <td><?= e($row['nome_pac'] ?? '-') ?></td>
                                <td><?= e($row['nome_hosp'] ?? '-') ?></td>
                                <td><?= !empty($row['data_intern_int']) ? e(date('d/m/Y', strtotime($row['data_intern_int']))) : '-' ?></td>
                                <td><?= number_format((float)($row['diarias'] ?? 0), 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
