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

$where = "i.data_intern_int BETWEEN :data_ini AND :data_fim";
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
        COUNT(*) AS total_prorrogacoes,
        SUM(CASE WHEN g.alto_custo_ges = 's' THEN 1 ELSE 0 END) AS alto_custo,
        SUM(CASE WHEN g.opme_ges = 's' THEN 1 ELSE 0 END) AS opme,
        SUM(CASE WHEN g.home_care_ges = 's' THEN 1 ELSE 0 END) AS home_care,
        SUM(CASE WHEN g.desospitalizacao_ges = 's' THEN 1 ELSE 0 END) AS desospitalizacao,
        SUM(CASE WHEN g.evento_adverso_ges = 's' THEN 1 ELSE 0 END) AS evento_adverso
    FROM tb_gestao g
    JOIN tb_internacao i ON i.id_internacao = g.fk_internacao_ges
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    WHERE g.evento_prorrogar_ges = 's'
      AND {$where}
";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$total = (int)($row['total_prorrogacoes'] ?? 0);
$motivos = [
    'Alto custo' => (int)($row['alto_custo'] ?? 0),
    'OPME' => (int)($row['opme'] ?? 0),
    'Home care' => (int)($row['home_care'] ?? 0),
    'Desospitalização' => (int)($row['desospitalizacao'] ?? 0),
    'Evento adverso' => (int)($row['evento_adverso'] ?? 0),
];
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
        <h1>Motivos de permanência hospitalar mais frequentes</h1>
        <div class="text-muted">Baseado nos registros de gestão com indicação clara de permanência.</div>
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
        <h5 class="mb-3">Resumo dos motivos de permanência</h5>
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead>
                    <tr>
                        <th>Motivo</th>
                        <th class="text-end">Quantidade</th>
                        <th class="text-end">Participação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($total === 0): ?>
                        <tr><td colspan="3" class="text-muted">Nenhum dado encontrado.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($motivos as $motivo => $qtd): ?>
                        <tr>
                            <td><?= e($motivo) ?></td>
                            <td class="text-end"><?= $qtd ?></td>
                            <td class="text-end"><?= $total ? round(($qtd / $total) * 100, 1) . '%' : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="text-muted small">Uma prorrogação pode ter mais de um motivo marcado.</div>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
