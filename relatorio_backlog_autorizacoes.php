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

$hoje = date('Y-m-d');
$dataIni = filter_input(INPUT_GET, 'data_ini') ?: date('Y-m-d', strtotime('-90 days'));
$dataFim = filter_input(INPUT_GET, 'data_fim') ?: $hoje;
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$seguradoraId = filter_input(INPUT_GET, 'seguradora_id', FILTER_VALIDATE_INT) ?: null;

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);
$seguradoras = $conn->query("SELECT id_seguradora, seguradora_seg FROM tb_seguradora ORDER BY seguradora_seg")
    ->fetchAll(PDO::FETCH_ASSOC);

$where = "COALESCE(tu.data_create_tuss, tu.data_realizacao_tuss) BETWEEN :data_ini AND :data_fim";
$params = [
    ':data_ini' => $dataIni,
    ':data_fim' => $dataFim,
];
if ($hospitalId) {
    $where .= " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = $hospitalId;
}
if ($seguradoraId) {
    $where .= " AND pa.fk_seguradora_pac = :seguradora_id";
    $params[':seguradora_id'] = $seguradoraId;
}

$sql = "
    SELECT
        COALESCE(s.seguradora_seg, 'Sem operadora') AS convenio,
        COUNT(*) AS pendentes,
        ROUND(AVG(DATEDIFF(CURDATE(), DATE(COALESCE(tu.data_create_tuss, tu.data_realizacao_tuss)))), 1) AS media_dias,
        MAX(DATEDIFF(CURDATE(), DATE(COALESCE(tu.data_create_tuss, tu.data_realizacao_tuss)))) AS max_dias
    FROM tb_tuss tu
    JOIN tb_internacao i ON i.id_internacao = tu.fk_int_tuss
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_seguradora s ON s.id_seguradora = pa.fk_seguradora_pac
    WHERE (tu.tuss_liberado_sn IS NULL OR tu.tuss_liberado_sn = '' OR tu.tuss_liberado_sn = 'n')
      AND {$where}
    GROUP BY convenio
    ORDER BY pendentes DESC, media_dias DESC
";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.report-wrapper {
    width: 100%;
    max-width: none;
    margin: 8px 0 44px;
    padding: 0 24px;
}
.report-header {
    background: linear-gradient(120deg, #fef6ff, #f3e6f9);
    border-radius: 18px;
    padding: 18px 22px;
    border: 1px solid rgba(94, 35, 99, .12);
    margin-bottom: 16px;
}
.report-header h1 {
    margin: 0 0 4px;
    font-weight: 700;
    color: #4b2054;
    font-size: 1.06rem;
}
.report-card {
    background: #fff;
    border-radius: 16px;
    padding: 14px 18px;
    border: 1px solid rgba(94, 35, 99, .08);
    box-shadow: 0 10px 24px rgba(45, 18, 70, .08);
    margin-bottom: 14px;
}
.report-wrapper .text-muted,
.report-wrapper .form-label,
.report-wrapper .form-control,
.report-wrapper .form-select,
.report-wrapper .table,
.report-wrapper small {
    font-size: .78rem;
}
.report-wrapper .row.g-3 {
    --bs-gutter-y: .6rem;
    --bs-gutter-x: .8rem;
}
.table thead th {
    background: #f8f3fb;
    color: #4b2054;
    font-size: .7rem;
    padding-top: .65rem;
    padding-bottom: .65rem;
}
</style>

<div class="report-wrapper">
    <div class="report-header">
        <h1>Backlog de autorizações por operadora</h1>
        <div class="text-muted">Itens TUSS pendentes de liberação no período selecionado.</div>
    </div>

    <form class="report-card" method="get">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Data inicial</label>
                <input type="date" class="form-control" name="data_ini" value="<?= e($dataIni) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Data final</label>
                <input type="date" class="form-control" name="data_fim" value="<?= e($dataFim) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Hospital</label>
                <select class="form-select" name="hospital_id">
                    <option value="">Todos</option>
                    <?php foreach ($hospitais as $h): ?>
                        <option value="<?= (int)$h['id_hospital'] ?>" <?= $hospitalId == $h['id_hospital'] ? 'selected' : '' ?>>
                            <?= e($h['nome_hosp']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Operadora</label>
                <select class="form-select" name="seguradora_id">
                    <option value="">Todos</option>
                    <?php foreach ($seguradoras as $s): ?>
                        <option value="<?= (int)$s['id_seguradora'] ?>" <?= $seguradoraId == $s['id_seguradora'] ? 'selected' : '' ?>>
                            <?= e($s['seguradora_seg']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <button class="btn btn-primary" type="submit">Aplicar filtros</button>
            </div>
        </div>
    </form>

    <div class="report-card">
        <h5 class="mb-3">Pendências por operadora</h5>
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead>
                    <tr>
                        <th>Operadora</th>
                        <th class="text-end">Pendentes</th>
                        <th class="text-end">Média (dias)</th>
                        <th class="text-end">Máximo (dias)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="4" class="text-muted">Nenhum dado encontrado.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['convenio']) ?></td>
                            <td class="text-end"><?= (int)$row['pendentes'] ?></td>
                            <td class="text-end"><?= e($row['media_dias']) ?></td>
                            <td class="text-end"><?= e($row['max_dias'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="text-muted small">Cálculo baseado na data de criação do TUSS, quando disponível.</div>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
