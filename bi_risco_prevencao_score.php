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

$hoje = date('Y-m-d');
$dataIni = filter_input(INPUT_GET, 'data_ini') ?: date('Y-m-d', strtotime('-120 days'));
$dataFim = filter_input(INPUT_GET, 'data_fim') ?: $hoje;
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$seguradoraId = filter_input(INPUT_GET, 'seguradora_id', FILTER_VALIDATE_INT) ?: null;
$internado = trim((string)(filter_input(INPUT_GET, 'internado') ?? ''));

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
if ($internado !== '') {
    $where .= " AND i.internado_int = :internado";
    $params[':internado'] = $internado;
}

$sql = "
    SELECT
        i.id_internacao,
        i.data_intern_int,
        pa.nome_pac,
        h.nome_hosp
    FROM tb_internacao i
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    WHERE {$where}
    ORDER BY i.data_intern_int DESC
    LIMIT 150
";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$riskService = new ReadmissionRiskService($conn);
$scored = [];
$levels = ['alto' => 0, 'moderado' => 0, 'baixo' => 0];
foreach ($rows as $row) {
    $internacaoId = (int)($row['id_internacao'] ?? 0);
    if ($internacaoId <= 0) {
        continue;
    }
    $score = $riskService->scoreInternacao($internacaoId);
    if (empty($score['available'])) {
        continue;
    }
    $level = $score['risk_level'] ?? 'baixo';
    if (!isset($levels[$level])) {
        $levels[$level] = 0;
    }
    $levels[$level]++;
    $scored[] = [
        'id' => $internacaoId,
        'paciente' => $row['nome_pac'] ?? '',
        'hospital' => $row['nome_hosp'] ?? '',
        'data' => $row['data_intern_int'] ?? null,
        'probability' => (float)($score['probability'] ?? 0),
        'risk_score' => (float)($score['risk_score'] ?? 0),
        'risk_level' => $level,
        'eventos' => (int)($score['features']['eventos_adversos'] ?? 0),
        'dias' => (int)($score['features']['dias_internado_atual'] ?? 0),
    ];
}

usort($scored, static function ($a, $b) {
    return $a['probability'] < $b['probability'] ? 1 : -1;
});
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Score de Risco por Internacao</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Score calculado via ReadmissionRiskService.</div>
        </div>
        <div class="bi-header-actions">
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegacao BI">
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
        <div class="bi-filter">
            <label>Internado</label>
            <select name="internado">
                <option value="">Todos</option>
                <option value="s" <?= $internado === 's' ? 'selected' : '' ?>>Sim</option>
                <option value="n" <?= $internado === 'n' ? 'selected' : '' ?>>Nao</option>
            </select>
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
            <button class="bi-btn bi-btn-secondary bi-btn-reset" type="button" onclick="window.location.href=window.location.pathname;">Limpar</button>
        </div>
    </form>

    <div class="bi-panel">
        <h3>Distribuicao por risco</h3>
        <div class="bi-kpis kpi-grid-4">
            <div class="bi-kpi kpi-compact">
                <small>Base analisada</small>
                <strong><?= fmtInt(count($scored)) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Risco alto</small>
                <strong><?= fmtInt($levels['alto'] ?? 0) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Risco moderado</small>
                <strong><?= fmtInt($levels['moderado'] ?? 0) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Risco baixo</small>
                <strong><?= fmtInt($levels['baixo'] ?? 0) ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Internacoes com maior risco</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Hospital</th>
                    <th>Data internacao</th>
                    <th>Probabilidade</th>
                    <th>Score</th>
                    <th>Nivel</th>
                    <th>Dias atuais</th>
                    <th>Eventos</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$scored): ?>
                    <tr>
                        <td colspan="8" class="bi-empty">Sem dados com os filtros atuais.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($scored as $row): ?>
                        <tr>
                            <td><?= e($row['paciente'] ?: 'Sem informacoes') ?></td>
                            <td><?= e($row['hospital'] ?: 'Sem informacoes') ?></td>
                            <td><?= !empty($row['data']) ? e(date('d/m/Y', strtotime($row['data']))) : '-' ?></td>
                            <td><?= fmtPct(((float)$row['probability']) * 100, 1) ?></td>
                            <td><?= fmtFloat($row['risk_score'], 1) ?></td>
                            <td><?= e($row['risk_level']) ?></td>
                            <td><?= fmtInt($row['dias']) ?></td>
                            <td><?= fmtInt($row['eventos']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <div style="margin-top:8px; color: var(--bi-muted); font-size: 0.88rem;">Base limitada a 150 internacoes mais recentes no periodo.</div>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
