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

$sqlSummary = "
    SELECT
        COUNT(DISTINCT i.id_internacao) AS total_internacoes,
        COUNT(DISTINCT CASE WHEN pr.id_prorrogacao IS NOT NULL OR ges.evento_prorrogar_ges = 's' THEN i.id_internacao END) AS total_indicacoes,
        COUNT(DISTINCT CASE WHEN alt.data_alta_alt IS NOT NULL THEN i.id_internacao END) AS total_altas,
        COUNT(DISTINCT CASE
            WHEN alt.data_alta_alt IS NOT NULL
             AND COALESCE(NULLIF(p.dias_pato, 0), NULLIF(s.longa_permanencia_seg, 0)) IS NOT NULL
             AND (DATEDIFF(alt.data_alta_alt, i.data_intern_int) + 1) <= COALESCE(NULLIF(p.dias_pato, 0), NULLIF(s.longa_permanencia_seg, 0))
            THEN i.id_internacao END
        ) AS altas_dentro_prazo,
        COUNT(DISTINCT CASE
            WHEN alt.data_alta_alt IS NOT NULL
             AND COALESCE(NULLIF(p.dias_pato, 0), NULLIF(s.longa_permanencia_seg, 0)) IS NOT NULL
             AND (DATEDIFF(alt.data_alta_alt, i.data_intern_int) + 1) > COALESCE(NULLIF(p.dias_pato, 0), NULLIF(s.longa_permanencia_seg, 0))
            THEN i.id_internacao END
        ) AS altas_fora_prazo
    FROM tb_internacao i
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_seguradora s ON s.id_seguradora = pa.fk_seguradora_pac
    LEFT JOIN tb_patologia p ON p.id_patologia = i.fk_patologia_int
    LEFT JOIN tb_alta alt ON alt.fk_id_int_alt = i.id_internacao
    LEFT JOIN tb_prorrogacao pr ON pr.fk_internacao_pror = i.id_internacao
    LEFT JOIN tb_gestao ges ON ges.fk_internacao_ges = i.id_internacao
    WHERE {$where}
";
$stmt = $conn->prepare($sqlSummary);
$stmt->execute($params);
$summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$sqlByConvenio = "
    SELECT
        COALESCE(s.seguradora_seg, 'Sem operadora') AS convenio,
        COUNT(DISTINCT i.id_internacao) AS total_internacoes,
        COUNT(DISTINCT CASE WHEN pr.id_prorrogacao IS NOT NULL OR ges.evento_prorrogar_ges = 's' THEN i.id_internacao END) AS total_indicacoes,
        COUNT(DISTINCT CASE
            WHEN alt.data_alta_alt IS NOT NULL
             AND COALESCE(NULLIF(p.dias_pato, 0), NULLIF(s.longa_permanencia_seg, 0)) IS NOT NULL
             AND (DATEDIFF(alt.data_alta_alt, i.data_intern_int) + 1) <= COALESCE(NULLIF(p.dias_pato, 0), NULLIF(s.longa_permanencia_seg, 0))
            THEN i.id_internacao END
        ) AS altas_dentro_prazo,
        COUNT(DISTINCT CASE
            WHEN alt.data_alta_alt IS NOT NULL
             AND COALESCE(NULLIF(p.dias_pato, 0), NULLIF(s.longa_permanencia_seg, 0)) IS NOT NULL
             AND (DATEDIFF(alt.data_alta_alt, i.data_intern_int) + 1) > COALESCE(NULLIF(p.dias_pato, 0), NULLIF(s.longa_permanencia_seg, 0))
            THEN i.id_internacao END
        ) AS altas_fora_prazo
    FROM tb_internacao i
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_seguradora s ON s.id_seguradora = pa.fk_seguradora_pac
    LEFT JOIN tb_patologia p ON p.id_patologia = i.fk_patologia_int
    LEFT JOIN tb_alta alt ON alt.fk_id_int_alt = i.id_internacao
    LEFT JOIN tb_prorrogacao pr ON pr.fk_internacao_pror = i.id_internacao
    LEFT JOIN tb_gestao ges ON ges.fk_internacao_ges = i.id_internacao
    WHERE {$where}
    GROUP BY convenio
    ORDER BY total_indicacoes DESC, total_internacoes DESC
";
$stmt = $conn->prepare($sqlByConvenio);
$stmt->execute($params);
$rowsConvenio = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalIntern = (int)($summary['total_internacoes'] ?? 0);
$totalIndicacoes = (int)($summary['total_indicacoes'] ?? 0);
$totalAltas = (int)($summary['total_altas'] ?? 0);
$altasDentro = (int)($summary['altas_dentro_prazo'] ?? 0);
$altasFora = (int)($summary['altas_fora_prazo'] ?? 0);
$altasComPrazo = $altasDentro + $altasFora;
?>

<style>
.report-wrapper {
    width: 100%;
    max-width: none;
    margin: 8px 0 44px;
    padding: 0 24px;
}
.report-header {
    background: linear-gradient(120deg, #f4faff, #e8f4fb);
    border-radius: 18px;
    padding: 18px 22px;
    border: 1px solid rgba(76, 142, 187, .12);
    margin-bottom: 16px;
}
.report-header h1 {
    margin: 0 0 4px;
    font-weight: 700;
    color: #24384f;
    font-size: 1.06rem;
}
.report-card {
    background: #fff;
    border-radius: 16px;
    padding: 14px 18px;
    border: 1px solid rgba(76, 142, 187, .08);
    box-shadow: 0 10px 24px rgba(35, 102, 147, .08);
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
.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 12px;
}
.summary-card {
    background: #f4faff;
    border-radius: 14px;
    padding: 11px 14px;
    border: 1px solid rgba(76, 142, 187, .08);
}
.summary-card h6 {
    margin: 0 0 4px;
    color: #24384f;
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .08em;
}
.summary-card div {
    font-size: 1.08rem;
    font-weight: 700;
    color: #2f6f9f;
}
.table thead th {
    background: #2f6f9f;
    color: #fff;
    font-size: .7rem;
    padding-top: .65rem;
    padding-bottom: .65rem;
}
.report-wrapper .table-striped > tbody > tr:nth-of-type(odd) > * {
    --bs-table-accent-bg: #f4faff;
}
.report-wrapper .table-striped > tbody > tr:nth-of-type(even) > * {
    --bs-table-accent-bg: #fff;
}
.report-wrapper .table-hover > tbody > tr:hover > * {
    --bs-table-accent-bg: #e8f4fb;
}
</style>

<div class="report-wrapper">
    <div class="report-header">
        <h1>Indicação de permanência vs. alta no prazo</h1>
        <div class="text-muted">Indicação clara considera prorrogação registrada ou marcação de gestão, apoiando decisão de pagamento.</div>
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
        <div class="summary-grid">
            <div class="summary-card">
                <h6>Internações</h6>
                <div><?= $totalIntern ?></div>
            </div>
            <div class="summary-card">
                <h6>Indicações de permanência</h6>
                <div><?= $totalIndicacoes ?></div>
            </div>
            <div class="summary-card">
                <h6>Altas</h6>
                <div><?= $totalAltas ?></div>
            </div>
            <div class="summary-card">
                <h6>Altas dentro do prazo</h6>
                <div><?= $altasDentro ?><?= $altasComPrazo ? ' (' . round(($altasDentro / $altasComPrazo) * 100, 1) . '%)' : '' ?></div>
            </div>
            <div class="summary-card">
                <h6>Altas fora do prazo</h6>
                <div><?= $altasFora ?><?= $altasComPrazo ? ' (' . round(($altasFora / $altasComPrazo) * 100, 1) . '%)' : '' ?></div>
            </div>
        </div>
    </div>

    <div class="report-card">
        <h5 class="mb-3">Detalhe por Operadora</h5>
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead>
                    <tr>
                        <th>Operadora</th>
                        <th class="text-end">Internações</th>
                        <th class="text-end">Indicações</th>
                        <th class="text-end">Altas dentro</th>
                        <th class="text-end">Altas fora</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rowsConvenio): ?>
                        <tr><td colspan="5" class="text-muted">Nenhum dado encontrado.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rowsConvenio as $row): ?>
                        <tr>
                            <td><?= e($row['convenio']) ?></td>
                            <td class="text-end"><?= (int)$row['total_internacoes'] ?></td>
                            <td class="text-end"><?= (int)$row['total_indicacoes'] ?></td>
                            <td class="text-end"><?= (int)$row['altas_dentro_prazo'] ?></td>
                            <td class="text-end"><?= (int)$row['altas_fora_prazo'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="text-muted small">Prazo calculado por patologia (dias) ou operadora (longa permanência).</div>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
