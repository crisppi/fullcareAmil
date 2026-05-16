<?php
ob_start();

require_once("templates/header.php");

function eGraf($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fetchPreparedRows(PDO $conn, string $sql, array $params = []): array
{
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        if (is_int($value)) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function expandMonthlySeries(array $rows, string $valueKey = 'total'): array
{
    if (!$rows) {
        $year = (int)date('Y');
        $start = new DateTime("$year-01-01");
        $end = new DateTime("$year-12-01");
    } else {
        $periods = array_column($rows, 'periodo_ord');
        sort($periods);
        $start = DateTime::createFromFormat('Y-m', $periods[0]) ?: new DateTime();
        $start->setDate((int)$start->format('Y'), 1, 1);
        $lastKey = end($periods);
        $end = DateTime::createFromFormat('Y-m', $lastKey) ?: new DateTime();
        $end->setDate((int)$end->format('Y'), 12, 1);
    }

    $map = [];
    foreach ($rows as $row) {
        $map[$row['periodo_ord']] = $row[$valueKey] ?? 0;
    }

    $series = [];
    $cursor = clone $start;
    while ($cursor <= $end) {
        $key = $cursor->format('Y-m');
        $series[] = [
            'periodo_label' => $cursor->format('m/Y'),
            'value' => (float)($map[$key] ?? 0)
        ];
        $cursor->modify('+1 month');
    }

    return $series;
}

$anoInput = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
$ano = ($anoInput !== null && $anoInput !== false) ? (int)$anoInput : null;
$mes = (int)(filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT) ?: 0);
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$auditorId = filter_input(INPUT_GET, 'auditor_id', FILTER_VALIDATE_INT) ?: null;

if ($ano === null && !filter_has_var(INPUT_GET, 'ano')) {
    $anoRow = fetchPreparedRows(
        $conn,
        "SELECT MAX(YEAR(data_inicio_neg)) AS ano
           FROM tb_negociacao
          WHERE data_inicio_neg IS NOT NULL
            AND data_inicio_neg <> '0000-00-00'"
    );
    $ano = (int)($anoRow[0]['ano'] ?? date('Y'));
}

$hospitais = fetchPreparedRows(
    $conn,
    "SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp"
);

$auditores = fetchPreparedRows(
    $conn,
    "SELECT id_usuario, usuario_user FROM tb_user ORDER BY usuario_user"
);

$baseCondition = "(ng.deletado_neg IS NULL OR ng.deletado_neg != 's')
    AND UPPER(COALESCE(ng.tipo_negociacao, '')) <> 'PRORROGACAO_AUTOMATICA'
    AND ng.data_inicio_neg IS NOT NULL
    AND ng.data_inicio_neg <> '0000-00-00'";

$where = $baseCondition;
$params = [];

if ($ano) {
    $where .= " AND YEAR(ng.data_inicio_neg) = :ano";
    $params[':ano'] = $ano;
}
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

$totals = fetchPreparedRows(
    $conn,
    "SELECT
        SUM(COALESCE(ng.saving, 0)) AS total_saving,
        COUNT(*) AS total_negociacoes,
        AVG(COALESCE(ng.saving, 0)) AS ticket_medio,
        SUM(COALESCE(ng.qtd, 0)) AS total_diarias
     FROM tb_negociacao ng
     LEFT JOIN tb_internacao i ON ng.fk_id_int = i.id_internacao
     WHERE {$where}",
    $params
);

$totalSaving = (float)($totals[0]['total_saving'] ?? 0);
$totalNegociacoes = (int)($totals[0]['total_negociacoes'] ?? 0);
$ticketMedio = (float)($totals[0]['ticket_medio'] ?? 0);
$totalDiarias = (int)($totals[0]['total_diarias'] ?? 0);

$monthlySaving = fetchPreparedRows(
    $conn,
    "SELECT
        DATE_FORMAT(ng.data_inicio_neg, '%Y-%m') AS periodo_ord,
        DATE_FORMAT(ng.data_inicio_neg, '%m/%Y') AS periodo_label,
        SUM(COALESCE(ng.saving, 0)) AS total
     FROM tb_negociacao ng
     LEFT JOIN tb_internacao i ON ng.fk_id_int = i.id_internacao
     WHERE {$where}
     GROUP BY periodo_ord, periodo_label
     ORDER BY periodo_ord",
    $params
);

$monthlyCount = fetchPreparedRows(
    $conn,
    "SELECT
        DATE_FORMAT(ng.data_inicio_neg, '%Y-%m') AS periodo_ord,
        DATE_FORMAT(ng.data_inicio_neg, '%m/%Y') AS periodo_label,
        COUNT(*) AS total
     FROM tb_negociacao ng
     LEFT JOIN tb_internacao i ON ng.fk_id_int = i.id_internacao
     WHERE {$where}
     GROUP BY periodo_ord, periodo_label
     ORDER BY periodo_ord",
    $params
);

$savingByAuditor = fetchPreparedRows(
    $conn,
    "SELECT
        ng.fk_usuario_neg AS auditor_id,
        COALESCE(us.usuario_user, 'Sem responsável') AS auditor,
        SUM(COALESCE(ng.saving, 0)) AS total
     FROM tb_negociacao ng
     LEFT JOIN tb_user us ON ng.fk_usuario_neg = us.id_usuario
     LEFT JOIN tb_internacao i ON ng.fk_id_int = i.id_internacao
     WHERE {$where}
     GROUP BY auditor_id, auditor
     ORDER BY total DESC",
    $params
);

$countByAuditor = fetchPreparedRows(
    $conn,
    "SELECT
        ng.fk_usuario_neg AS auditor_id,
        COALESCE(us.usuario_user, 'Sem responsável') AS auditor,
        COUNT(*) AS total
     FROM tb_negociacao ng
     LEFT JOIN tb_user us ON ng.fk_usuario_neg = us.id_usuario
     LEFT JOIN tb_internacao i ON ng.fk_id_int = i.id_internacao
     WHERE {$where}
     GROUP BY auditor_id, auditor
     ORDER BY total DESC",
    $params
);

$savingByType = fetchPreparedRows(
    $conn,
    "SELECT
        COALESCE(ng.tipo_negociacao, 'Não informado') AS tipo,
        SUM(COALESCE(ng.saving, 0)) AS total
     FROM tb_negociacao ng
     LEFT JOIN tb_internacao i ON ng.fk_id_int = i.id_internacao
     WHERE {$where}
     GROUP BY tipo
     ORDER BY total DESC",
    $params
);

$typeByAuditorRaw = fetchPreparedRows(
    $conn,
    "SELECT
        COALESCE(us.usuario_user, 'Sem responsável') AS auditor,
        COALESCE(ng.tipo_negociacao, 'Não informado') AS tipo,
        COUNT(*) AS total
     FROM tb_negociacao ng
     LEFT JOIN tb_user us ON ng.fk_usuario_neg = us.id_usuario
     LEFT JOIN tb_internacao i ON ng.fk_id_int = i.id_internacao
     WHERE {$where}
     GROUP BY auditor, tipo
     ORDER BY auditor, tipo",
    $params
);

$savingByHospital = fetchPreparedRows(
    $conn,
    "SELECT
        COALESCE(ho.nome_hosp, 'Sem hospital') AS hospital,
        SUM(COALESCE(ng.saving, 0)) AS total,
        COUNT(*) AS total_registros,
        SUM(COALESCE(ng.qtd, 0)) AS total_qtd
     FROM tb_negociacao ng
     LEFT JOIN tb_internacao i ON ng.fk_id_int = i.id_internacao
     LEFT JOIN tb_hospital ho ON i.fk_hospital_int = ho.id_hospital
     WHERE {$where}
     GROUP BY hospital
     ORDER BY total DESC
     LIMIT 10",
    $params
);

$monthlySavingSeries = expandMonthlySeries($monthlySaving, 'total');
$monthlyCountSeries = expandMonthlySeries($monthlyCount, 'total');

$msLabels = array_column($monthlySavingSeries, 'periodo_label');
$msValues = array_map(fn($row) => (float)$row['value'], $monthlySavingSeries);

$mcLabels = array_column($monthlyCountSeries, 'periodo_label');
$mcValues = array_map(fn($row) => (int)$row['value'], $monthlyCountSeries);

$auditorSavingLabels = array_column($savingByAuditor, 'auditor');
$auditorSavingValues = array_map(fn($row) => (float)$row['total'], $savingByAuditor);

$auditorCountLabels = array_column($countByAuditor, 'auditor');
$auditorCountValues = array_map(fn($row) => (int)$row['total'], $countByAuditor);
$auditorCountMap = [];
foreach ($countByAuditor as $row) {
    $auditorCountMap[(string)($row['auditor_id'] ?? 0)] = (int)($row['total'] ?? 0);
}

$typeLabels = array_column($savingByType, 'tipo');
$typeValues = array_map(fn($row) => (float)$row['total'], $savingByType);
$typeCountMap = [];
foreach ($typeByAuditorRaw as $row) {
    $tipoKey = (string)($row['tipo'] ?? 'Não informado');
    if (!isset($typeCountMap[$tipoKey])) {
        $typeCountMap[$tipoKey] = 0;
    }
    $typeCountMap[$tipoKey] += (int)($row['total'] ?? 0);
}

$hospLabels = array_column($savingByHospital, 'hospital');
$hospValues = array_map(fn($row) => (float)$row['total'], $savingByHospital);
$hospCountValues = array_map(fn($row) => (int)($row['total_registros'] ?? 0), $savingByHospital);
$hospDiariaValues = array_map(fn($row) => (int)($row['total_qtd'] ?? 0), $savingByHospital);

$typeAudTypes = array_values(array_unique(array_column($typeByAuditorRaw, 'tipo')));
$typeAudAuditors = array_values(array_unique(array_column($typeByAuditorRaw, 'auditor')));

$typeAudMatrix = [];
foreach ($typeAudAuditors as $aud) {
    $typeAudMatrix[$aud] = array_fill(0, count($typeAudTypes), 0);
}
foreach ($typeByAuditorRaw as $item) {
    $aud = $item['auditor'];
    $tipo = $item['tipo'];
    $idx = array_search($tipo, $typeAudTypes, true);
    if ($idx === false) {
        continue;
    }
    $typeAudMatrix[$aud][$idx] = (int)$item['total'];
}

$palette = ['#5e2363', '#ff6384', '#36a2eb', '#4bc0c0', '#9966ff', '#ff9f40', '#20c997', '#d63384'];
$typeAudDatasets = [];
foreach ($typeAudAuditors as $index => $aud) {
    $typeAudDatasets[] = [
        'label' => $aud,
        'backgroundColor' => $palette[$index % count($palette)],
        'data' => $typeAudMatrix[$aud]
    ];
}
?>

<style>
.summary-card {
    background: linear-gradient(180deg, rgba(94,35,99,.96), rgba(57,16,61,.96));
    color:#fff;
    border-radius:22px;
    border:1px solid rgba(255,255,255,.08);
    box-shadow:0 18px 34px rgba(45,18,70,.18);
    padding:18px 20px;
    min-height:140px;
}
.summary-card small {
    display:block;
    text-transform:uppercase;
    letter-spacing:.08em;
    opacity:.72;
    font-size:.72rem;
    margin-bottom:8px;
}
.summary-card strong {
    display:block;
    font-size:1.65rem;
    line-height:1.1;
    margin-bottom:10px;
}
.summary-card span {
    font-size:.92rem;
    color:rgba(255,255,255,.82);
}
.chart-card {
    background:#fff;
    border-radius:22px;
    border:1px solid #ebe1f5;
    box-shadow:0 12px 28px rgba(45,18,70,.08);
    padding:16px;
    min-height:260px;
    height:100%;
}
.chart-card h5 {
    font-weight:600;
    color:#3a184f;
    margin-bottom:8px;
    font-size:0.95rem;
}
.table-card {
    background:#fff;
    border-radius:22px;
    border:1px solid #ebe1f5;
    box-shadow:0 12px 28px rgba(45,18,70,.08);
    padding:18px;
    height:100%;
}
.table-card h5 {
    font-weight:600;
    color:#3a184f;
    margin-bottom:12px;
}
.neg-table {
    width:100%;
    border-collapse:collapse;
    font-size:.92rem;
}
.neg-table th,
.neg-table td {
    padding:10px 12px;
    border-bottom:1px solid #efe6f6;
}
.neg-table th {
    color:#6b5a7b;
    font-size:.78rem;
    text-transform:uppercase;
    letter-spacing:.06em;
}
.neg-table td.num,
.neg-table th.num {
    text-align:right;
}
.filters-card {
    background:#fff;
    border-radius:22px;
    border:1px solid #ebe1f5;
    box-shadow:0 12px 28px rgba(45,18,70,.08);
    padding:18px;
}
.filters-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));
    gap:14px;
}
.filters-grid label {
    display:block;
    font-size:.8rem;
    font-weight:600;
    color:#655674;
    margin-bottom:6px;
    text-transform:uppercase;
    letter-spacing:.05em;
}
.filters-grid input,
.filters-grid select {
    width:100%;
    border:1px solid #ddd0ea;
    border-radius:12px;
    padding:10px 12px;
    background:#fff;
}
</style>

<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h2 class="mb-1" style="color:#5e2363;">Painel de Negociações</h2>
            <p class="text-muted mb-0">Visão consolidada de savings, volumes, hospitais e auditores nas negociações registradas.</p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="<?= $BASE_URL ?>export_negociacoes_graficos.php" class="btn btn-outline-primary">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i>Exportar XLSX
            </a>
        </div>
    </div>

    <form class="filters-card mb-4" method="get">
        <div class="filters-grid">
            <div>
                <label for="ano">Ano</label>
                <input id="ano" type="number" name="ano" value="<?= eGraf($ano) ?>">
            </div>
            <div>
                <label for="mes">Mês</label>
                <select id="mes" name="mes">
                    <option value="0">Todos</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $mes === $m ? 'selected' : '' ?>><?= str_pad((string)$m, 2, '0', STR_PAD_LEFT) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label for="hospital_id">Hospital</label>
                <select id="hospital_id" name="hospital_id">
                    <option value="">Todos</option>
                    <?php foreach ($hospitais as $h): ?>
                        <option value="<?= (int)$h['id_hospital'] ?>" <?= $hospitalId == (int)$h['id_hospital'] ? 'selected' : '' ?>>
                            <?= eGraf($h['nome_hosp']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="auditor_id">Auditor</label>
                <select id="auditor_id" name="auditor_id">
                    <option value="">Todos</option>
                    <?php foreach ($auditores as $a): ?>
                        <option value="<?= (int)$a['id_usuario'] ?>" <?= $auditorId == (int)$a['id_usuario'] ? 'selected' : '' ?>>
                            <?= eGraf($a['usuario_user']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary w-100">Aplicar</button>
            </div>
        </div>
    </form>

    <div class="row g-4">
        <div class="col-xl-3 col-md-6">
            <div class="summary-card">
                <small>Total saving</small>
                <strong>R$ <?= number_format($totalSaving, 2, ',', '.') ?></strong>
                <span>Resultado acumulado do filtro atual.</span>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="summary-card">
                <small>Negociações</small>
                <strong><?= number_format($totalNegociacoes, 0, ',', '.') ?></strong>
                <span>Quantidade total de itens negociados.</span>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="summary-card">
                <small>Ticket médio</small>
                <strong>R$ <?= number_format($ticketMedio, 2, ',', '.') ?></strong>
                <span>Média de saving por negociação.</span>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="summary-card">
                <small>Diárias trocadas</small>
                <strong><?= number_format($totalDiarias, 0, ',', '.') ?></strong>
                <span>Soma das quantidades negociadas.</span>
            </div>
        </div>

        <div class="col-xl-4 col-lg-6">
            <div class="chart-card">
                <h5>Saving mensal</h5>
                <canvas id="chartSavingMensal"></canvas>
            </div>
        </div>
        <div class="col-xl-4 col-lg-6">
            <div class="chart-card">
                <h5>Quantidade de negociações por mês</h5>
                <canvas id="chartCountMensal"></canvas>
            </div>
        </div>

        <div class="col-xl-4 col-lg-6">
            <div class="chart-card">
                <h5>Saving por auditor</h5>
                <canvas id="chartSavingAuditor"></canvas>
            </div>
        </div>

        <div class="col-xl-4 col-lg-6">
            <div class="chart-card">
                <h5>Quantidade de negociações por auditor</h5>
                <canvas id="chartCountAuditor"></canvas>
            </div>
        </div>

        <div class="col-xl-4 col-lg-6">
            <div class="chart-card">
                <h5>Saving por tipo</h5>
                <canvas id="chartSavingTipo"></canvas>
            </div>
        </div>
        <div class="col-xl-4 col-lg-6">
            <div class="chart-card">
                <h5>Tipos de negociação por auditor</h5>
                <canvas id="chartTipoAuditor"></canvas>
            </div>
        </div>

        <div class="col-xl-4 col-lg-6">
            <div class="chart-card">
                <h5>Hospitais com maior saving</h5>
                <canvas id="chartHospitais"></canvas>
            </div>
        </div>
        <div class="col-xl-4 col-lg-6">
            <div class="chart-card">
                <h5>Quantidade de negociações por hospital</h5>
                <canvas id="chartCountHospital"></canvas>
            </div>
        </div>
        <div class="col-xl-4 col-lg-6">
            <div class="chart-card">
                <h5>Diárias negociadas por hospital</h5>
                <canvas id="chartDiariasHospital"></canvas>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="table-card">
                <h5>Resumo por auditor</h5>
                <?php if ($savingByAuditor): ?>
                    <table class="neg-table">
                        <thead>
                            <tr>
                                <th>Auditor</th>
                                <th class="num">Saving</th>
                                <th class="num">Negociações</th>
                                <th class="num">% total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($savingByAuditor as $idx => $row):
                                $valor = (float)($row['total'] ?? 0);
                                $qtd = (int)($auditorCountMap[(string)($row['auditor_id'] ?? 0)] ?? 0);
                                $pct = $totalSaving != 0.0 ? ($valor / $totalSaving) * 100 : 0;
                            ?>
                                <tr>
                                    <td><?= eGraf($row['auditor']) ?></td>
                                    <td class="num">R$ <?= number_format($valor, 2, ',', '.') ?></td>
                                    <td class="num"><?= number_format($qtd, 0, ',', '.') ?></td>
                                    <td class="num"><?= number_format($pct, 2, ',', '.') ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="text-muted">Nenhum registro para o filtro atual.</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="table-card">
                <h5>Resumo por hospital</h5>
                <?php if ($savingByHospital): ?>
                    <table class="neg-table">
                        <thead>
                            <tr>
                                <th>Hospital</th>
                                <th class="num">Saving</th>
                                <th class="num">Negociações</th>
                                <th class="num">Diárias</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($savingByHospital as $row): ?>
                                <tr>
                                    <td><?= eGraf($row['hospital']) ?></td>
                                    <td class="num">R$ <?= number_format((float)($row['total'] ?? 0), 2, ',', '.') ?></td>
                                    <td class="num"><?= number_format((int)($row['total_registros'] ?? 0), 0, ',', '.') ?></td>
                                    <td class="num"><?= number_format((int)($row['total_qtd'] ?? 0), 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="text-muted">Nenhum registro para o filtro atual.</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="table-card">
                <h5>Resumo por tipo de saving</h5>
                <?php if ($savingByType): ?>
                    <table class="neg-table">
                        <thead>
                            <tr>
                                <th>Tipo de saving</th>
                                <th class="num">Saving</th>
                                <th class="num">Negociações</th>
                                <th class="num">% total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($savingByType as $row):
                                $tipo = (string)($row['tipo'] ?? 'Não informado');
                                $valor = (float)($row['total'] ?? 0);
                                $qtd = (int)($typeCountMap[$tipo] ?? 0);
                                $pct = $totalSaving != 0.0 ? ($valor / $totalSaving) * 100 : 0;
                            ?>
                                <tr>
                                    <td><?= eGraf($tipo) ?></td>
                                    <td class="num">R$ <?= number_format($valor, 2, ',', '.') ?></td>
                                    <td class="num"><?= number_format($qtd, 0, ',', '.') ?></td>
                                    <td class="num"><?= number_format($pct, 2, ',', '.') ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="text-muted">Nenhum registro para o filtro atual.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const palette = ['#5e2363','#ff6384','#36a2eb','#4bc0c0','#9966ff','#ff9f40','#20c997','#d63384','#ffc107','#6c757d'];

const savingMensalData = {
    labels: <?= json_encode($msLabels, JSON_UNESCAPED_UNICODE) ?>,
    datasets: [{
        label: 'Saving (R$)',
        data: <?= json_encode($msValues, JSON_UNESCAPED_UNICODE) ?>,
        borderColor: '#5e2363',
        backgroundColor: 'rgba(94,35,99,0.1)',
        tension: .2,
        fill: true
    }]
};

const countMensalData = {
    labels: <?= json_encode($mcLabels, JSON_UNESCAPED_UNICODE) ?>,
    datasets: [{
        label: 'Negociações',
        data: <?= json_encode($mcValues, JSON_UNESCAPED_UNICODE) ?>,
        borderColor: '#20c997',
        backgroundColor: 'rgba(32,201,151,0.15)',
        tension: .2,
        fill: true
    }]
};

const savingAuditorData = {
    labels: <?= json_encode($auditorSavingLabels, JSON_UNESCAPED_UNICODE) ?>,
    datasets: [{
        label: 'Saving (R$)',
        data: <?= json_encode($auditorSavingValues, JSON_UNESCAPED_UNICODE) ?>,
        backgroundColor: palette
    }]
};

const countAuditorData = {
    labels: <?= json_encode($auditorCountLabels, JSON_UNESCAPED_UNICODE) ?>,
    datasets: [{
        label: 'Negociações',
        data: <?= json_encode($auditorCountValues, JSON_UNESCAPED_UNICODE) ?>,
        backgroundColor: palette
    }]
};

const savingTipoData = {
    labels: <?= json_encode($typeLabels, JSON_UNESCAPED_UNICODE) ?>,
    datasets: [{
        label: 'Saving (R$)',
        data: <?= json_encode($typeValues, JSON_UNESCAPED_UNICODE) ?>,
        backgroundColor: palette
    }]
};

const tipoAuditorData = {
    labels: <?= json_encode($typeAudTypes, JSON_UNESCAPED_UNICODE) ?>,
    datasets: <?= json_encode($typeAudDatasets, JSON_UNESCAPED_UNICODE) ?>
};

const hospitaisData = {
    labels: <?= json_encode($hospLabels, JSON_UNESCAPED_UNICODE) ?>,
    datasets: [{
        label: 'Saving (R$)',
        data: <?= json_encode($hospValues, JSON_UNESCAPED_UNICODE) ?>,
        backgroundColor: palette
    }]
};

const hospitaisCountData = {
    labels: <?= json_encode($hospLabels, JSON_UNESCAPED_UNICODE) ?>,
    datasets: [{
        label: 'Negociações',
        data: <?= json_encode($hospCountValues, JSON_UNESCAPED_UNICODE) ?>,
        backgroundColor: palette
    }]
};

const hospitaisDiariasData = {
    labels: <?= json_encode($hospLabels, JSON_UNESCAPED_UNICODE) ?>,
    datasets: [{
        label: 'Diárias',
        data: <?= json_encode($hospDiariaValues, JSON_UNESCAPED_UNICODE) ?>,
        backgroundColor: palette
    }]
};

const moneyFormatter = (value) => 'R$ ' + Number(value).toLocaleString('pt-BR', {minimumFractionDigits: 2});

new Chart(document.getElementById('chartSavingMensal'), {
    type: 'line',
    data: savingMensalData,
    options: {
        plugins: {tooltip: {callbacks: {label: ctx => `${ctx.dataset.label}: ${moneyFormatter(ctx.parsed.y || 0)}`}}},
        scales: {
            y: {
                beginAtZero: true,
                ticks: {callback: value => 'R$ ' + value.toLocaleString('pt-BR')}
            }
        }
    }
});

new Chart(document.getElementById('chartCountMensal'), {
    type: 'line',
    data: countMensalData,
    options: {
        plugins: {tooltip: {callbacks: {label: ctx => `${ctx.dataset.label}: ${ctx.parsed.y}`}}},
        scales: {y: {beginAtZero: true, ticks: {precision: 0}}}
    }
});

new Chart(document.getElementById('chartSavingAuditor'), {
    type: 'bar',
    data: savingAuditorData,
    options: {
        plugins: {tooltip: {callbacks: {label: ctx => moneyFormatter(ctx.parsed.y || 0)}}},
        scales: {y: {beginAtZero: true, ticks: {callback: value => 'R$ ' + value.toLocaleString('pt-BR')}}}
    }
});

new Chart(document.getElementById('chartCountAuditor'), {
    type: 'bar',
    data: countAuditorData,
    options: {
        indexAxis: 'y',
        plugins: {tooltip: {callbacks: {label: ctx => `${ctx.parsed.x || 0} negociações`}}},
        scales: {x: {beginAtZero: true, ticks: {precision: 0}}}
    }
});

new Chart(document.getElementById('chartSavingTipo'), {
    type: 'doughnut',
    data: savingTipoData,
    options: {
        plugins: {tooltip: {callbacks: {label: ctx => `${ctx.label}: ${moneyFormatter(ctx.parsed || 0)}`}}}
    }
});

new Chart(document.getElementById('chartTipoAuditor'), {
    type: 'bar',
    data: tipoAuditorData,
    options: {
        plugins: {tooltip: {callbacks: {label: ctx => `${ctx.dataset.label}: ${ctx.parsed.y || 0}`}}},
        responsive: true,
        scales: {y: {beginAtZero: true, ticks: {precision: 0}}, x: {stacked: false}}
    }
});

new Chart(document.getElementById('chartHospitais'), {
    type: 'bar',
    data: hospitaisData,
    options: {
        plugins: {tooltip: {callbacks: {label: ctx => moneyFormatter(ctx.parsed.y || 0)}}},
        scales: {y: {beginAtZero: true, ticks: {callback: value => 'R$ ' + value.toLocaleString('pt-BR')}}}
    }
});

new Chart(document.getElementById('chartCountHospital'), {
    type: 'bar',
    data: hospitaisCountData,
    options: {
        plugins: {tooltip: {callbacks: {label: ctx => `${ctx.parsed.y || 0} negociações`}}},
        scales: {y: {beginAtZero: true, ticks: {precision: 0}}}
    }
});

new Chart(document.getElementById('chartDiariasHospital'), {
    type: 'bar',
    data: hospitaisDiariasData,
    options: {
        plugins: {tooltip: {callbacks: {label: ctx => `${ctx.parsed.y || 0} diárias`}}},
        scales: {y: {beginAtZero: true, ticks: {precision: 0}}}
    }
});
</script>
