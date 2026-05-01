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

function fmt_date(?string $value): string
{
    if (!$value || $value === '0000-00-00') {
        return '-';
    }
    $ts = strtotime($value);
    return $ts ? date('d/m/Y', $ts) : '-';
}

$anoInput = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
$mesInput = filter_input(INPUT_GET, 'mes', FILTER_VALIDATE_INT);
$ano = ($anoInput !== null && $anoInput !== false) ? (int)$anoInput : null;
$mes = ($mesInput !== null && $mesInput !== false) ? (int)$mesInput : 0;
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$seguradoraId = filter_input(INPUT_GET, 'seguradora_id', FILTER_VALIDATE_INT) ?: null;
$limiarInput = filter_input(INPUT_GET, 'limiar', FILTER_VALIDATE_INT);
$limiar = ($limiarInput !== null && $limiarInput !== false && $limiarInput > 0) ? (int)$limiarInput : 30;

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);
$seguradoras = $conn->query("SELECT id_seguradora, seguradora_seg FROM tb_seguradora ORDER BY seguradora_seg")
    ->fetchAll(PDO::FETCH_ASSOC);
$anos = $conn->query("SELECT DISTINCT YEAR(data_intern_int) AS ano FROM tb_internacao WHERE data_intern_int IS NOT NULL AND data_intern_int <> '0000-00-00' ORDER BY ano DESC")
    ->fetchAll(PDO::FETCH_COLUMN);

if ($ano === null && !filter_has_var(INPUT_GET, 'ano')) {
    $ano = !empty($anos) ? (int)$anos[0] : (int)date('Y');
}

$whereIntern = "YEAR(ac.data_intern_int) = :ano";
$whereReadm = "YEAR(i.data_intern_int) = :ano";
$paramsIntern = [':ano' => (int)$ano, ':limiar' => $limiar];
$paramsReadm = [':ano' => (int)$ano];
if (!empty($mes)) {
    $whereIntern .= " AND MONTH(ac.data_intern_int) = :mes";
    $whereReadm .= " AND MONTH(i.data_intern_int) = :mes";
    $paramsIntern[':mes'] = (int)$mes;
    $paramsReadm[':mes'] = (int)$mes;
}
if (!empty($hospitalId)) {
    $whereIntern .= " AND ac.fk_hospital_int = :hospital_id";
    $whereReadm .= " AND i.fk_hospital_int = :hospital_id";
    $paramsIntern[':hospital_id'] = (int)$hospitalId;
    $paramsReadm[':hospital_id'] = (int)$hospitalId;
}
if (!empty($seguradoraId)) {
    $whereIntern .= " AND pa.fk_seguradora_pac = :seguradora_id";
    $whereReadm .= " AND pa.fk_seguradora_pac = :seguradora_id";
    $paramsIntern[':seguradora_id'] = (int)$seguradoraId;
    $paramsReadm[':seguradora_id'] = (int)$seguradoraId;
}

$sqlLong = "
    SELECT
        COUNT(*) AS casos,
        SUM(total_custo) AS total_custo,
        AVG(diarias) AS media_diarias
    FROM (
        SELECT
            ac.id_internacao,
            SUM(ca.valor_final_capeante) AS total_custo,
            GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), ac.data_intern_int) + 1) AS diarias
        FROM tb_internacao ac
        LEFT JOIN tb_paciente pa ON pa.id_paciente = ac.fk_paciente_int
        LEFT JOIN (
            SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
            FROM tb_alta
            GROUP BY fk_id_int_alt
        ) al ON al.fk_id_int_alt = ac.id_internacao
        LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = ac.id_internacao
        WHERE ac.data_intern_int IS NOT NULL
          AND {$whereIntern}
        GROUP BY ac.id_internacao
    ) t
    WHERE t.diarias >= :limiar
";
$stmt = $conn->prepare($sqlLong);
foreach ($paramsIntern as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$longStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$longCasos = (int)($longStats['casos'] ?? 0);
$longCusto = (float)($longStats['total_custo'] ?? 0);
$longMedia = (float)($longStats['media_diarias'] ?? 0);

$sqlReadm = "
    SELECT
        COUNT(*) AS casos,
        SUM(ca.valor_final_capeante) AS total_custo
    FROM (
        SELECT
            i.id_internacao,
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
        LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
        WHERE i.data_intern_int IS NOT NULL
          AND {$whereReadm}
    ) base
    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = base.id_internacao
    WHERE base.ultima_alta IS NOT NULL
      AND DATEDIFF(base.data_intern_int, base.ultima_alta) BETWEEN 1 AND 30
";
$stmt = $conn->prepare($sqlReadm);
foreach ($paramsReadm as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$readmStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$readmCasos = (int)($readmStats['casos'] ?? 0);
$readmCusto = (float)($readmStats['total_custo'] ?? 0);

$sqlLongDetails = "
    SELECT
        t.id_internacao,
        pa.nome_pac AS paciente,
        h.nome_hosp AS hospital,
        s.seguradora_seg AS seguradora,
        t.data_intern_int,
        t.data_alta,
        t.diarias,
        t.total_custo
    FROM (
        SELECT
            ac.id_internacao,
            ac.fk_paciente_int,
            ac.fk_hospital_int,
            ac.data_intern_int,
            MAX(al.data_alta_alt) AS data_alta,
            SUM(ca.valor_final_capeante) AS total_custo,
            GREATEST(1, DATEDIFF(COALESCE(MAX(al.data_alta_alt), CURDATE()), ac.data_intern_int) + 1) AS diarias
        FROM tb_internacao ac
        LEFT JOIN tb_paciente pa ON pa.id_paciente = ac.fk_paciente_int
        LEFT JOIN tb_alta al ON al.fk_id_int_alt = ac.id_internacao
        LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = ac.id_internacao
        WHERE ac.data_intern_int IS NOT NULL
          AND {$whereIntern}
        GROUP BY ac.id_internacao
    ) t
    LEFT JOIN tb_paciente pa ON pa.id_paciente = t.fk_paciente_int
    LEFT JOIN tb_hospital h ON h.id_hospital = t.fk_hospital_int
    LEFT JOIN tb_seguradora s ON s.id_seguradora = pa.fk_seguradora_pac
    WHERE t.diarias >= :limiar
    ORDER BY t.diarias DESC
    LIMIT 50
";
$stmt = $conn->prepare($sqlLongDetails);
foreach ($paramsIntern as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$longDetails = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sqlReadmDetails = "
    SELECT
        base.id_internacao,
        pa.nome_pac AS paciente,
        h.nome_hosp AS hospital,
        s.seguradora_seg AS seguradora,
        base.data_intern_int,
        base.ultima_alta,
        DATEDIFF(base.data_intern_int, base.ultima_alta) AS dias_entre,
        COALESCE(ca.total_custo, 0) AS total_custo
    FROM (
        SELECT
            i.id_internacao,
            i.fk_paciente_int,
            i.fk_hospital_int,
            i.data_intern_int,
            (
                SELECT MAX(al2.data_alta_alt)
                FROM tb_internacao i2
                LEFT JOIN tb_alta al2 ON al2.fk_id_int_alt = i2.id_internacao
                WHERE i2.fk_paciente_int = i.fk_paciente_int
                  AND al2.data_alta_alt < i.data_intern_int
            ) AS ultima_alta
        FROM tb_internacao i
        LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
        WHERE i.data_intern_int IS NOT NULL
          AND {$whereReadm}
    ) base
    LEFT JOIN (
        SELECT fk_int_capeante, SUM(valor_final_capeante) AS total_custo
        FROM tb_capeante
        GROUP BY fk_int_capeante
    ) ca ON ca.fk_int_capeante = base.id_internacao
    LEFT JOIN tb_paciente pa ON pa.id_paciente = base.fk_paciente_int
    LEFT JOIN tb_hospital h ON h.id_hospital = base.fk_hospital_int
    LEFT JOIN tb_seguradora s ON s.id_seguradora = pa.fk_seguradora_pac
    WHERE base.ultima_alta IS NOT NULL
      AND DATEDIFF(base.data_intern_int, base.ultima_alta) BETWEEN 1 AND 30
    ORDER BY base.data_intern_int DESC
    LIMIT 50
";
$stmt = $conn->prepare($sqlReadmDetails);
foreach ($paramsReadm as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$readmDetails = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));
</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Custo Evitável</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted">Ano <?= e($ano) ?></div>
        </div>
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
            <label>Longa permanência (dias)</label>
            <select name="limiar">
                <?php foreach ([15, 20, 25, 30, 45, 60] as $opt): ?>
                    <option value="<?= $opt ?>" <?= (int)$limiar === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="bi-kpis kpi-compact kpi-tight kpi-slim">
        <div class="bi-kpi kpi-indigo kpi-compact">
            <small>Longa permanência</small>
            <strong><?= number_format($longCasos, 0, ',', '.') ?> casos</strong>
        </div>
        <div class="bi-kpi kpi-rose kpi-compact">
            <small>Custo longa permanência</small>
            <strong>R$ <?= number_format($longCusto, 2, ',', '.') ?></strong>
        </div>
        <div class="bi-kpi kpi-teal kpi-compact">
            <small>Diárias médias</small>
            <strong><?= number_format($longMedia, 1, ',', '.') ?> d</strong>
        </div>
        <div class="bi-kpi kpi-amber kpi-compact">
            <small>Readmissões 30d</small>
            <strong><?= number_format($readmCasos, 0, ',', '.') ?> casos</strong>
        </div>
        <div class="bi-kpi kpi-indigo kpi-compact">
            <small>Custo readmissão</small>
            <strong>R$ <?= number_format($readmCusto, 2, ',', '.') ?></strong>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Como ler</h3>
        <p class="bi-report">
            Longa permanência considera internações com <?= (int)$limiar ?> dias ou mais (data de internação até alta). Readmissão considera nova internação em até 30 dias após a alta anterior.
        </p>
    </div>

    <div class="bi-panel">
        <h3>Detalhamento - Longa Permanência</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Hospital</th>
                    <th>Seguradora</th>
                    <th>Data internação</th>
                    <th>Data alta</th>
                    <th>Diárias</th>
                    <th>Custo</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$longDetails): ?>
                    <tr>
                        <td colspan="7" class="text-center">Sem casos no período.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($longDetails as $row): ?>
                        <tr>
                            <td><?= e($row['paciente'] ?? 'Sem paciente') ?></td>
                            <td><?= e($row['hospital'] ?? 'Sem hospital') ?></td>
                            <td><?= e($row['seguradora'] ?? 'Sem seguradora') ?></td>
                            <td><?= e(fmt_date($row['data_intern_int'] ?? null)) ?></td>
                            <td><?= e(fmt_date($row['data_alta'] ?? null)) ?></td>
                            <td><?= (int)($row['diarias'] ?? 0) ?></td>
                            <td>R$ <?= number_format((float)($row['total_custo'] ?? 0), 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="bi-panel">
        <h3>Detalhamento - Readmissões 30d</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Hospital</th>
                    <th>Seguradora</th>
                    <th>Data internação</th>
                    <th>Última alta</th>
                    <th>Dias entre</th>
                    <th>Custo</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$readmDetails): ?>
                    <tr>
                        <td colspan="7" class="text-center">Sem readmissões no período.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($readmDetails as $row): ?>
                        <tr>
                            <td><?= e($row['paciente'] ?? 'Sem paciente') ?></td>
                            <td><?= e($row['hospital'] ?? 'Sem hospital') ?></td>
                            <td><?= e($row['seguradora'] ?? 'Sem seguradora') ?></td>
                            <td><?= e(fmt_date($row['data_intern_int'] ?? null)) ?></td>
                            <td><?= e(fmt_date($row['ultima_alta'] ?? null)) ?></td>
                            <td><?= (int)($row['dias_entre'] ?? 0) ?></td>
                            <td>R$ <?= number_format((float)($row['total_custo'] ?? 0), 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>