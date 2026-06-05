<?php
include_once("check_logado.php");
require_once("templates/header.php");
require_once("app/services/ReadmissionRiskService.php");

if (!isset($conn) || !($conn instanceof PDO)) {
    die("Conexao invalida.");
}

if (!function_exists('e')) {
    function e($v)
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('fmtInt')) {
    function fmtInt($value): string
    {
        return number_format((float)$value, 0, ',', '.');
    }
}

if (!function_exists('fmtFloat')) {
    function fmtFloat($value, int $dec = 1): string
    {
        return number_format((float)$value, $dec, ',', '.');
    }
}

if (!function_exists('fmtPct')) {
    function fmtPct($value, int $dec = 1): string
    {
        return number_format((float)$value, $dec, ',', '.') . '%';
    }
}

if (!function_exists('fmtCurrency')) {
    function fmtCurrency($value): string
    {
        return 'R$ ' . number_format((float)$value, 2, ',', '.');
    }
}

$hoje = date('Y-m-d');
$dataIni = filter_input(INPUT_GET, 'data_ini') ?: date('Y-m-d', strtotime('-120 days'));
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
    $where .= " AND p.fk_seguradora_pac = :seguradora_id";
    $params[':seguradora_id'] = $seguradoraId;
}

$sql = "
    SELECT
        i.id_internacao,
        i.data_intern_int,
        p.nome_pac,
        h.nome_hosp,
        ca.valor_final_capeante,
        ca.valor_apresentado_capeante
    FROM tb_internacao i
    LEFT JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN (
        SELECT ca1.*
        FROM tb_capeante ca1
        INNER JOIN (
            SELECT fk_int_capeante, MAX(id_capeante) AS max_id
            FROM tb_capeante
            GROUP BY fk_int_capeante
        ) ca2 ON ca2.max_id = ca1.id_capeante
    ) ca ON ca.fk_int_capeante = i.id_internacao
    WHERE {$where}
    ORDER BY i.data_intern_int DESC
    LIMIT 300
";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$riskService = new ReadmissionRiskService($conn);
$items = [];
foreach ($rows as $row) {
    $internacaoId = (int)($row['id_internacao'] ?? 0);
    if ($internacaoId <= 0) {
        continue;
    }
    $score = $riskService->scoreInternacao($internacaoId);
    if (empty($score['available'])) {
        continue;
    }
    $cost = (float)($row['valor_final_capeante'] ?? 0);
    if ($cost <= 0) {
        $cost = (float)($row['valor_apresentado_capeante'] ?? 0);
    }
    $items[] = [
        'id' => $internacaoId,
        'paciente' => $row['nome_pac'] ?? '',
        'hospital' => $row['nome_hosp'] ?? '',
        'data' => $row['data_intern_int'] ?? null,
        'probability' => (float)($score['probability'] ?? 0),
        'risk_level' => $score['risk_level'] ?? 'baixo',
        'risk_score' => (float)($score['risk_score'] ?? 0),
        'cost' => $cost,
    ];
}

$costs = array_map(static function ($item) {
    return (float)($item['cost'] ?? 0);
}, $items);
$avgCost = $costs ? array_sum($costs) / count($costs) : 0.0;
$probThreshold = 0.55;

$matrix = [
    'alto_alto' => 0,
    'alto_baixo' => 0,
    'baixo_alto' => 0,
    'baixo_baixo' => 0,
];
$highHigh = [];
foreach ($items as $item) {
    $probHigh = (float)$item['probability'] >= $probThreshold;
    $costHigh = (float)$item['cost'] >= $avgCost && $avgCost > 0;
    if ($probHigh && $costHigh) {
        $matrix['alto_alto']++;
        $highHigh[] = $item;
    } elseif ($probHigh && !$costHigh) {
        $matrix['alto_baixo']++;
    } elseif (!$probHigh && $costHigh) {
        $matrix['baixo_alto']++;
    } else {
        $matrix['baixo_baixo']++;
    }
}

usort($highHigh, static function ($a, $b) {
    $scoreA = ((float)$a['probability']) * max((float)$a['cost'], 1);
    $scoreB = ((float)$b['probability']) * max((float)$b['cost'], 1);
    if ($scoreA === $scoreB) {
        return 0;
    }
    return $scoreA < $scoreB ? 1 : -1;
});
$highHigh = array_slice($highHigh, 0, 20);
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260516-rounded-bars"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Matriz de Risco (Readmissão 7/30d x Custo)</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Probabilidade via ReadmissionRiskService e custo medio da internação no período.</div>
        </div>
        <div class="bi-header-actions">
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação BI">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <form class="bi-panel bi-filters" method="get">
        <div class="bi-filter">
            <label>Data inicial</label>
            <input type="date" name="data_ini" value="<?= e($dataIni) ?>">
        </div>
        <div class="bi-filter">
            <label>Data final</label>
            <input type="date" name="data_fim" value="<?= e($dataFim) ?>">
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
            <label>Seguradora</label>
            <select name="seguradora_id">
                <option value="">Todas</option>
                <?php foreach ($seguradoras as $s): ?>
                    <option value="<?= (int)$s['id_seguradora'] ?>" <?= $seguradoraId == $s['id_seguradora'] ? 'selected' : '' ?>>
                        <?= e($s['seguradora_seg']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
            <button class="bi-btn bi-btn-secondary bi-btn-reset" type="button" onclick="window.location.href=window.location.pathname;">Limpar</button>
        </div>
    </form>

    <div class="bi-panel">
        <h3>Resumo da matriz</h3>
        <div class="bi-kpis kpi-grid-4">
            <div class="bi-kpi kpi-compact">
                <small>Base analisada</small>
                <strong><?= fmtInt(count($items)) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Probabilidade alta (>= <?= fmtPct($probThreshold * 100, 0) ?>)</small>
                <strong><?= fmtInt($matrix['alto_alto'] + $matrix['alto_baixo']) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Custo médio referencia</small>
                <strong><?= $avgCost > 0 ? fmtCurrency($avgCost) : 'n/d' ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Quadrante alto/alto</small>
                <strong><?= fmtInt($matrix['alto_alto']) ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Matriz (Probabilidade x Custo)</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th></th>
                    <th>Custo baixo</th>
                    <th>Custo alto</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th>Probabilidade alta</th>
                    <td><?= fmtInt($matrix['alto_baixo']) ?></td>
                    <td><?= fmtInt($matrix['alto_alto']) ?></td>
                </tr>
                <tr>
                    <th>Probabilidade baixa</th>
                    <td><?= fmtInt($matrix['baixo_baixo']) ?></td>
                    <td><?= fmtInt($matrix['baixo_alto']) ?></td>
                </tr>
            </tbody>
        </table>
        <div style="margin-top:8px; color: var(--bi-muted); font-size: 0.88rem;">Custo alto = acima do custo medio do periodo. Base limitada a 300 internacoes mais recentes.</div>
    </div>

    <div class="bi-panel">
        <h3>Casos prioritarios (alto risco + alto custo)</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Hospital</th>
                    <th>Data internação</th>
                    <th>Probabilidade</th>
                    <th>Score</th>
                    <th>Custo</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$highHigh): ?>
                    <tr>
                        <td colspan="6" class="bi-empty">Sem casos com os filtros atuais.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($highHigh as $item): ?>
                        <tr>
                            <td><?= e($item['paciente'] ?: 'Sem informações') ?></td>
                            <td><?= e($item['hospital'] ?: 'Sem informações') ?></td>
                            <td><?= !empty($item['data']) ? e(date('d/m/Y', strtotime($item['data']))) : '-' ?></td>
                            <td><?= fmtPct(((float)$item['probability']) * 100, 1) ?></td>
                            <td><?= fmtFloat($item['risk_score'], 1) ?></td>
                            <td><?= $item['cost'] > 0 ? fmtCurrency($item['cost']) : 'n/d' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
