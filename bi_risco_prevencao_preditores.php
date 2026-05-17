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

$readmBase = "
    SELECT
        i.id_internacao,
        i.fk_paciente_int,
        al.data_alta_alt,
        COALESCE(NULLIF(pa.idade_pac, 0), TIMESTAMPDIFF(YEAR, pa.data_nasc_pac, CURDATE())) AS idade,
        pat.patologia_pat,
        CASE WHEN EXISTS (
            SELECT 1
            FROM tb_internacao i2
            WHERE i2.fk_paciente_int = i.fk_paciente_int
              AND i2.data_intern_int > al.data_alta_alt
              AND i2.data_intern_int <= DATE_ADD(al.data_alta_alt, INTERVAL 30 DAY)
        ) THEN 1 ELSE 0 END AS readm30
    FROM tb_alta al
    JOIN tb_internacao i ON i.id_internacao = al.fk_id_int_alt
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_patologia pat ON pat.id_patologia = i.fk_patologia_int
    WHERE {$whereAlta}
";

$sqlAge = "
    SELECT
        faixa,
        COUNT(*) AS altas,
        SUM(readm30) AS readm30
    FROM (
        SELECT
            CASE
                WHEN idade < 18 THEN '0-17'
                WHEN idade < 40 THEN '18-39'
                WHEN idade < 65 THEN '40-64'
                WHEN idade >= 65 THEN '65+'
                ELSE 'Sem idade'
            END AS faixa,
            readm30
        FROM ({$readmBase}) base
    ) faixas
    GROUP BY faixa
    ORDER BY faixa
";
$stmt = $conn->prepare($sqlAge);
$stmt->execute($paramsAlta);
$ageRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sqlPat = "
    SELECT
        COALESCE(NULLIF(patologia_pat, ''), 'Sem informações') AS patologia,
        COUNT(*) AS altas,
        SUM(readm30) AS readm30
    FROM ({$readmBase}) base
    GROUP BY patologia
    HAVING COUNT(*) >= 5
    ORDER BY (SUM(readm30) / COUNT(*)) DESC, altas DESC
    LIMIT 12
";
$stmt = $conn->prepare($sqlPat);
$stmt->execute($paramsAlta);
$patRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$whereIntern = "i.data_intern_int BETWEEN :data_ini AND :data_fim";
$paramsIntern = [
    ':data_ini' => $dataIni,
    ':data_fim' => $dataFim,
];
if ($hospitalId) {
    $whereIntern .= " AND i.fk_hospital_int = :hospital_id";
    $paramsIntern[':hospital_id'] = $hospitalId;
}
if ($seguradoraId) {
    $whereIntern .= " AND pa.fk_seguradora_pac = :seguradora_id";
    $paramsIntern[':seguradora_id'] = $seguradoraId;
}

$sqlComp = "
    SELECT
        COALESCE(NULLIF(pat.patologia_pat, ''), 'Sem informações') AS patologia,
        COUNT(DISTINCT i.id_internacao) AS casos,
        COUNT(DISTINCT CASE WHEN g.evento_adverso_ges = 's' THEN i.id_internacao END) AS eventos
    FROM tb_internacao i
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_patologia pat ON pat.id_patologia = i.fk_patologia_int
    LEFT JOIN tb_gestao g ON g.fk_internacao_ges = i.id_internacao
    WHERE {$whereIntern}
    GROUP BY patologia
    HAVING COUNT(DISTINCT i.id_internacao) >= 5
    ORDER BY (COUNT(DISTINCT CASE WHEN g.evento_adverso_ges = 's' THEN i.id_internacao END) / COUNT(DISTINCT i.id_internacao)) DESC
    LIMIT 12
";
$stmt = $conn->prepare($sqlComp);
$stmt->execute($paramsIntern);
$compRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260516-rounded-bars"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Preditores de Readmissão e Complicações</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Leitura por perfil, patologia e eventos adversos no período.</div>
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
        <h3>Readmissão 30d por faixa etaria</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Faixa etária</th>
                    <th>Altas</th>
                    <th>Readmissão 30d</th>
                    <th>Taxa</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$ageRows): ?>
                    <tr>
                        <td colspan="4" class="bi-empty">Sem dados com os filtros atuais.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($ageRows as $row): ?>
                        <?php
                        $altas = (int)($row['altas'] ?? 0);
                        $readm = (int)($row['readm30'] ?? 0);
                        $rate = $altas > 0 ? ($readm / $altas) * 100 : 0;
                        ?>
                        <tr>
                            <td><?= e($row['faixa'] ?? 'Sem idade') ?></td>
                            <td><?= fmtInt($altas) ?></td>
                            <td><?= fmtInt($readm) ?></td>
                            <td><?= fmtPct($rate, 1) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="bi-panel">
        <h3>Patologias com maior taxa de readmissao</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Patologia</th>
                    <th>Altas</th>
                    <th>Readmissão 30d</th>
                    <th>Taxa</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$patRows): ?>
                    <tr>
                        <td colspan="4" class="bi-empty">Sem dados com os filtros atuais.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($patRows as $row): ?>
                        <?php
                        $altas = (int)($row['altas'] ?? 0);
                        $readm = (int)($row['readm30'] ?? 0);
                        $rate = $altas > 0 ? ($readm / $altas) * 100 : 0;
                        ?>
                        <tr>
                            <td><?= e($row['patologia'] ?? 'Sem informações') ?></td>
                            <td><?= fmtInt($altas) ?></td>
                            <td><?= fmtInt($readm) ?></td>
                            <td><?= fmtPct($rate, 1) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="bi-panel">
        <h3>Complicações (eventos adversos) por patologia</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Patologia</th>
                    <th>Casos</th>
                    <th>Eventos adversos</th>
                    <th>Taxa</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$compRows): ?>
                    <tr>
                        <td colspan="4" class="bi-empty">Sem dados com os filtros atuais.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($compRows as $row): ?>
                        <?php
                        $casos = (int)($row['casos'] ?? 0);
                        $eventos = (int)($row['eventos'] ?? 0);
                        $rate = $casos > 0 ? ($eventos / $casos) * 100 : 0;
                        ?>
                        <tr>
                            <td><?= e($row['patologia'] ?? 'Sem informações') ?></td>
                            <td><?= fmtInt($casos) ?></td>
                            <td><?= fmtInt($eventos) ?></td>
                            <td><?= fmtPct($rate, 1) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
