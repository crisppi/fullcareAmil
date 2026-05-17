<?php
include_once("check_logado.php");
require_once("templates/header.php");

if (!isset($conn) || !($conn instanceof PDO)) {
    die("Conexão inválida.");
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
    $where .= " AND pa.fk_seguradora_pac = :seguradora_id";
    $params[':seguradora_id'] = $seguradoraId;
}

$sqlTotals = "
    SELECT
        COUNT(DISTINCT i.id_internacao) AS total_internacoes,
        COUNT(DISTINCT CASE WHEN g.evento_adverso_ges = 's' THEN i.id_internacao END) AS internacoes_evento,
        COUNT(DISTINCT g.id_gestao) AS total_eventos
    FROM tb_internacao i
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_gestao g ON g.fk_internacao_ges = i.id_internacao AND g.evento_adverso_ges = 's'
    WHERE {$where}
";
$stmt = $conn->prepare($sqlTotals);
$stmt->execute($params);
$totals = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$totalInternacoes = (int)($totals['total_internacoes'] ?? 0);
$internacoesEvento = (int)($totals['internacoes_evento'] ?? 0);
$totalEventos = (int)($totals['total_eventos'] ?? 0);
$eventoPct = $totalInternacoes > 0 ? ($internacoesEvento / $totalInternacoes) * 100 : 0.0;

$sqlTipos = "
    SELECT
        COALESCE(NULLIF(g.tipo_evento_adverso_gest, ''), 'Sem informações') AS tipo,
        COUNT(*) AS total
    FROM tb_gestao g
    JOIN tb_internacao i ON i.id_internacao = g.fk_internacao_ges
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    WHERE {$where}
      AND g.evento_adverso_ges = 's'
    GROUP BY tipo
    ORDER BY total DESC
    LIMIT 12
";
$stmt = $conn->prepare($sqlTipos);
$stmt->execute($params);
$tipoRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sqlHosp = "
    SELECT
        h.nome_hosp AS hospital,
        COUNT(*) AS total
    FROM tb_gestao g
    JOIN tb_internacao i ON i.id_internacao = g.fk_internacao_ges
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    WHERE {$where}
      AND g.evento_adverso_ges = 's'
    GROUP BY h.id_hospital
    ORDER BY total DESC
    LIMIT 12
";
$stmt = $conn->prepare($sqlHosp);
$stmt->execute($params);
$hospRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sqlRel = "
    SELECT
        COALESCE(NULLIF(g.rel_evento_adverso_ges, ''), 'Sem informações') AS relato,
        COUNT(*) AS total
    FROM tb_gestao g
    JOIN tb_internacao i ON i.id_internacao = g.fk_internacao_ges
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    WHERE {$where}
      AND g.evento_adverso_ges = 's'
    GROUP BY relato
    ORDER BY total DESC
    LIMIT 12
";
$stmt = $conn->prepare($sqlRel);
$stmt->execute($params);
$relRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$sqlTable = "
    SELECT
        COALESCE(NULLIF(pa.nome_pac, ''), 'Sem informações') AS paciente,
        COALESCE(NULLIF(h.nome_hosp, ''), 'Sem informações') AS hospital,
        COALESCE(NULLIF(g.tipo_evento_adverso_gest, ''), 'Sem informações') AS tipo,
        COALESCE(NULLIF(g.rel_evento_adverso_ges, ''), '-') AS relato
    FROM tb_gestao g
    JOIN tb_internacao i ON i.id_internacao = g.fk_internacao_ges
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    WHERE {$where}
      AND g.evento_adverso_ges = 's'
    ORDER BY i.data_intern_int DESC
    LIMIT 40
";
$stmt = $conn->prepare($sqlTable);
$stmt->execute($params);
$tableRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260516-rounded-bars"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <div>
            <h1 class="bi-title">Analise de Eventos Adversos</h1>
            <div style="color: var(--bi-muted); font-size: 0.95rem;">Padroes por tipo, hospital e relatos registrados.</div>
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
        <div class="bi-kpis kpi-dashboard-v2">
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-1">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-hospital"></i></span>
                    <small>Internações analisadas</small>
                </div>
                <strong><?= fmtInt($totalInternacoes) ?></strong>
                <span class="kpi-trend kpi-trend-neutral">Período filtrado</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-2">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-exclamation-octagon"></i></span>
                    <small>Internações com evento</small>
                </div>
                <strong><?= fmtInt($internacoesEvento) ?></strong>
                <span class="kpi-trend kpi-trend-neutral">Com evento adverso</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-3">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-percent"></i></span>
                    <small>Taxa de evento</small>
                </div>
                <strong><?= fmtPct($eventoPct, 1) ?></strong>
                <span class="kpi-trend kpi-trend-neutral">Sobre internações</span>
            </div>
            <div class="bi-kpi kpi-card-v2 kpi-card-v2-4">
                <div class="kpi-card-v2-head">
                    <span class="kpi-card-v2-icon"><i class="bi bi-clipboard2-pulse"></i></span>
                    <small>Total de eventos</small>
                </div>
                <strong><?= fmtInt($totalEventos) ?></strong>
                <span class="kpi-trend kpi-trend-neutral">Registros no recorte</span>
            </div>
        </div>
    </div>

    <div class="bi-panel">
        <h3>Eventos por tipo</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Eventos</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$tipoRows): ?>
                    <tr>
                        <td colspan="2" class="bi-empty">Sem dados com os filtros atuais.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tipoRows as $row): ?>
                        <tr>
                            <td><?= e($row['tipo'] ?? 'Sem informações') ?></td>
                            <td><?= fmtInt($row['total'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="bi-panel">
        <h3>Eventos por hospital</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Hospital</th>
                    <th>Eventos</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$hospRows): ?>
                    <tr>
                        <td colspan="2" class="bi-empty">Sem dados com os filtros atuais.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($hospRows as $row): ?>
                        <tr>
                            <td><?= e($row['hospital'] ?? 'Sem informações') ?></td>
                            <td><?= fmtInt($row['total'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="bi-panel">
        <h3>Relatos recorrentes (causas)</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Relato</th>
                    <th>Ocorrencias</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$relRows): ?>
                    <tr>
                        <td colspan="2" class="bi-empty">Sem dados com os filtros atuais.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($relRows as $row): ?>
                        <tr>
                            <td><?= e($row['relato'] ?? 'Sem informações') ?></td>
                            <td><?= fmtInt($row['total'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="bi-panel">
        <h3>Casos recentes</h3>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Hospital</th>
                    <th>Tipo</th>
                    <th>Relato</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$tableRows): ?>
                    <tr>
                        <td colspan="4" class="bi-empty">Sem dados com os filtros atuais.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tableRows as $row): ?>
                        <tr>
                            <td><?= e($row['paciente'] ?? 'Sem informações') ?></td>
                            <td><?= e($row['hospital'] ?? 'Sem informações') ?></td>
                            <td><?= e($row['tipo'] ?? 'Sem informações') ?></td>
                            <td><?= e($row['relato'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
