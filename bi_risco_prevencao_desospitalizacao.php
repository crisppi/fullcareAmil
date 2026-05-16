<?php
include_once("check_logado.php");
require_once("templates/header.php");

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
$dataIni = filter_input(INPUT_GET, 'data_ini') ?: date('Y-m-d', strtotime('-180 days'));
$dataFim = filter_input(INPUT_GET, 'data_fim') ?: $hoje;
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$seguradoraId = filter_input(INPUT_GET, 'seguradora_id', FILTER_VALIDATE_INT) ?: null;

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);
$seguradoras = $conn->query("SELECT id_seguradora, seguradora_seg FROM tb_seguradora ORDER BY seguradora_seg")
    ->fetchAll(PDO::FETCH_ASSOC);

$whereAlta = "al.data_alta_alt BETWEEN :data_ini AND :data_fim";
$paramsAlta = [
    ':data_ini' => $dataIni,
    ':data_fim' => $dataFim,
];
if ($hospitalId) {
    $whereAlta .= " AND i.fk_hospital_int = :hospital_id";
    $paramsAlta[':hospital_id'] = $hospitalId;
}
if ($seguradoraId) {
    $whereAlta .= " AND pa.fk_seguradora_pac = :seguradora_id";
    $paramsAlta[':seguradora_id'] = $seguradoraId;
}

$whereAltaSub = str_replace(['al.', 'i.', 'pa.'], ['al2.', 'i2.', 'pa2.'], $whereAlta);
$whereAltaSub = str_replace(
    [':data_ini', ':data_fim', ':hospital_id', ':seguradora_id'],
    [':data_ini_sub', ':data_fim_sub', ':hospital_id_sub', ':seguradora_id_sub'],
    $whereAltaSub
);
$paramsAltaSub = [
    ':data_ini_sub' => $dataIni,
    ':data_fim_sub' => $dataFim,
];
if ($hospitalId) {
    $paramsAltaSub[':hospital_id_sub'] = $hospitalId;
}
if ($seguradoraId) {
    $paramsAltaSub[':seguradora_id_sub'] = $seguradoraId;
}

$sqlAvg = "
    SELECT
        i.fk_hospital_int AS hospital_id,
        AVG(GREATEST(1, DATEDIFF(al.data_alta_alt, i.data_intern_int) + 1)) AS media_dias,
        COUNT(*) AS altas
    FROM tb_internacao i
    JOIN tb_alta al ON al.fk_id_int_alt = i.id_internacao
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    WHERE {$whereAlta}
    GROUP BY i.fk_hospital_int
";
$stmt = $conn->prepare($sqlAvg);
$stmt->execute($paramsAlta);
$avgRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$avgMap = [];
foreach ($avgRows as $row) {
    $avgMap[(int)$row['hospital_id']] = [
        'media' => (float)($row['media_dias'] ?? 0),
        'altas' => (int)($row['altas'] ?? 0),
    ];
}

$sqlEarly = "
    SELECT
        i.id_internacao,
        pa.nome_pac,
        h.nome_hosp,
        i.data_intern_int,
        al.data_alta_alt,
        GREATEST(1, DATEDIFF(al.data_alta_alt, i.data_intern_int) + 1) AS dias,
        AVG_DATA.media_dias
    FROM tb_internacao i
    JOIN tb_alta al ON al.fk_id_int_alt = i.id_internacao
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    JOIN (
        SELECT
            i2.fk_hospital_int AS hospital_id,
            AVG(GREATEST(1, DATEDIFF(al2.data_alta_alt, i2.data_intern_int) + 1)) AS media_dias
        FROM tb_internacao i2
        JOIN tb_alta al2 ON al2.fk_id_int_alt = i2.id_internacao
        LEFT JOIN tb_paciente pa2 ON pa2.id_paciente = i2.fk_paciente_int
        WHERE {$whereAltaSub}
        GROUP BY i2.fk_hospital_int
    ) AS AVG_DATA ON AVG_DATA.hospital_id = i.fk_hospital_int
    WHERE {$whereAlta}
      AND GREATEST(1, DATEDIFF(al.data_alta_alt, i.data_intern_int) + 1) <= (AVG_DATA.media_dias - 2)
    ORDER BY (AVG_DATA.media_dias - GREATEST(1, DATEDIFF(al.data_alta_alt, i.data_intern_int) + 1)) DESC
    LIMIT 60
";
$stmt = $conn->prepare($sqlEarly);
$stmt->execute($paramsAlta + $paramsAltaSub);
$earlyRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sqlTotalAltas = "
    SELECT COUNT(*)
    FROM tb_alta al
    JOIN tb_internacao i ON i.id_internacao = al.fk_id_int_alt
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    WHERE {$whereAlta}
";
$stmt = $conn->prepare($sqlTotalAltas);
$stmt->execute($paramsAlta);
$totalAltas = (int)($stmt->fetchColumn() ?: 0);

$totalEarly = count($earlyRows);
$earlyPct = $totalAltas > 0 ? ($totalEarly / $totalAltas) * 100 : 0.0;
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260509-filter-icons"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Desospitalização Precoce</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Regra: alta 2 dias antes da media do hospital no período.</div>
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
        <h3>Indicadores-chave</h3>
        <div class="bi-kpis kpi-grid-4">
            <div class="bi-kpi kpi-compact">
                <small>Total de altas</small>
                <strong><?= fmtInt($totalAltas) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Desospitalizacoes precoces</small>
                <strong><?= fmtInt($totalEarly) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Taxa</small>
                <strong><?= fmtPct($earlyPct, 1) ?></strong>
            </div>
            <div class="bi-kpi kpi-compact">
                <small>Hospitais no recorte</small>
                <strong><?= fmtInt(count($avgMap)) ?></strong>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Casos com alta antecipada</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Hospital</th>
                    <th>Data internação</th>
                    <th>Data alta</th>
                    <th>Dias</th>
                    <th>Media hospital</th>
                    <th>Diferenca</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$earlyRows): ?>
                    <tr>
                        <td colspan="7" class="bi-empty">Sem dados com os filtros atuais.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($earlyRows as $row): ?>
                        <?php
                        $dias = (int)($row['dias'] ?? 0);
                        $media = (float)($row['media_dias'] ?? 0);
                        $diff = $media > 0 ? $media - $dias : 0;
                        ?>
                        <tr>
                            <td><?= e($row['nome_pac'] ?? 'Sem informações') ?></td>
                            <td><?= e($row['nome_hosp'] ?? 'Sem informações') ?></td>
                            <td><?= !empty($row['data_intern_int']) ? e(date('d/m/Y', strtotime($row['data_intern_int']))) : '-' ?></td>
                            <td><?= !empty($row['data_alta_alt']) ? e(date('d/m/Y', strtotime($row['data_alta_alt']))) : '-' ?></td>
                            <td><?= fmtInt($dias) ?></td>
                            <td><?= fmtFloat($media, 1) ?></td>
                            <td><?= fmtFloat($diff, 1) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
