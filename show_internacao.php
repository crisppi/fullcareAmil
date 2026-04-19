<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Internação - Detalhes</title>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="diversos/CoolAdmin-master/vendor/font-awesome-5/css/fontawesome-all.min.css">

    <script src="js/timeout.js"></script>

    <!-- ✅ Bootstrap JS (NECESSÁRIO para tabs/pills funcionarem direito) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</head>

<?php
include_once("check_logado.php");
include_once("globals.php");
Gate::enforceAction($conn, $BASE_URL, 'view', 'Você não tem permissão para visualizar este registro.');
include_once("templates/header.php");

// Models / DAOs
include_once("models/internacao.php");
require_once("dao/internacaoDao.php");
include_once("models/hospital.php");
include_once("dao/hospitalDao.php");
include_once("models/patologia.php");
include_once("dao/patologiaDao.php");
include_once("dao/pacienteDao.php");

include_once("models/prorrogacao.php");
include_once("dao/prorrogacaoDao.php");

include_once("models/visita.php");
include_once("dao/visitaDao.php");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$normCargoAccess = function ($txt) {
    $txt = mb_strtolower(trim((string)$txt), 'UTF-8');
    $c = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
    $txt = $c !== false ? $c : $txt;
    return preg_replace('/[^a-z]/', '', $txt);
};
$isGestorSeguradora = (strpos($normCargoAccess($_SESSION['cargo'] ?? ''), 'seguradora') !== false);

include_once("models/tuss.php");
include_once("dao/tussDao.php");

// Negociação
if (file_exists(__DIR__ . "/models/negociacao.php")) include_once("models/negociacao.php");
if (file_exists(__DIR__ . "/dao/negociacaoDao.php")) include_once("dao/negociacaoDao.php");

// === Helpers ===
function e($v)
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}
function fmtDate($s)
{
    if (empty($s) || $s === '0000-00-00') return '-';
    $ts = strtotime(substr((string)$s, 0, 10));
    return $ts ? date("d/m/Y", $ts) : '-';
}
if (!function_exists('ymd')) {
    function ymd($s)
    {
        if (!$s) return null;
        $s = trim((string)$s);
        $s = substr($s, 0, 10);
        $ts = strtotime($s);
        return $ts ? date('Y-m-d', $ts) : null;
    }
}
function after_dash($s)
{
    $s = trim((string)$s);
    if ($s === '') return '';
    $pos = mb_strpos($s, '-');
    $out = ($pos === false) ? $s : mb_substr($s, $pos + 1);
    $out = preg_replace('/\s+/', ' ', $out);
    return trim($out);
}
function dateToTs(?string $date): ?int
{
    if (!$date) return null;
    $ts = strtotime(substr((string)$date, 0, 10));
    return $ts ? (int)$ts : null;
}
function daysExclusive(int $startTs, int $endTs): int
{
    if ($endTs <= $startTs) return 0;
    return (int)floor(($endTs - $startTs) / 86400);
}
function computeCoverageAndGaps(array $intervals, int $startTs, int $endTs): array
{
    if (!$intervals) {
        return [0, daysExclusive($startTs, $endTs), [[date('d/m/Y', $startTs), date('d/m/Y', $endTs)]]];
    }
    usort($intervals, fn($a, $b) => $a['s'] <=> $b['s']);
    $coveredDays = 0;
    $gaps = [];
    $curS = $intervals[0]['s'];
    $curE = $intervals[0]['e'];
    foreach ($intervals as $idx => $it) {
        if ($idx === 0) continue;
        if ($it['s'] <= $curE) {
            if ($it['e'] > $curE) $curE = $it['e'];
            continue;
        }
        if ($curS > $startTs) {
            $gapStart = $startTs;
            $gapEnd = $curS;
            if ($gapEnd > $gapStart) {
                $gaps[] = [date('d/m/Y', $gapStart), date('d/m/Y', $gapEnd)];
            }
        }
        $coveredDays += daysExclusive($curS, $curE);
        $curS = $it['s'];
        $curE = $it['e'];
    }
    if ($curS > $startTs) {
        $gapStart = $startTs;
        $gapEnd = $curS;
        if ($gapEnd > $gapStart) {
            $gaps[] = [date('d/m/Y', $gapStart), date('d/m/Y', $gapEnd)];
        }
    }
    $coveredDays += daysExclusive($curS, $curE);
    if ($curE < $endTs) {
        $gapStart = $curE;
        $gapEnd = $endTs;
        if ($gapEnd > $gapStart) {
            $gaps[] = [date('d/m/Y', $gapStart), date('d/m/Y', $gapEnd)];
        }
    }
    $totalDays = daysExclusive($startTs, $endTs);
    $missingDays = max(0, $totalDays - $coveredDays);
    return [$coveredDays, $missingDays, $gaps];
}
if (!function_exists('fmtDateAny')) {
    function fmtDateAny($s)
    {
        $y = ymd($s);
        return $y ? date('d/m/Y', strtotime($y)) : '-';
    }
}
function initials_from_name($name)
{
    $name = trim((string)$name);
    if ($name === '') return 'PA';
    $parts = preg_split('/\s+/', $name);
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last  = mb_substr(($parts[count($parts) - 1] ?? ''), 0, 1);
    return mb_strtoupper($first . $last);
}

// === Entrada ===
$id_internacao = filter_input(INPUT_GET, "id_internacao", FILTER_SANITIZE_NUMBER_INT);
$id_internacao = $id_internacao !== null ? trim((string)$id_internacao) : '';

$internacaoDao = new internacaoDAO($conn, $BASE_URL);

// WHERE por ID
$whereParts = [];
if ($id_internacao !== '' && ctype_digit($id_internacao)) {
    $whereParts[] = 'ac.id_internacao = ' . (int)$id_internacao;
}
$where = implode(' AND ', $whereParts);
$order = null;
$limit = 1;

$internacoes = $internacaoDao->selectAllInternacao($where, $order, $limit);
$data = $internacoes && isset($internacoes[0]) ? $internacoes[0] : null;

if (!$data) {
?>
    <div class="container mt-4">
        <div class="alert alert-warning">Nenhuma internação encontrada para o parâmetro informado.</div>
        <?php include_once("diversos/backbtn_internacao.php"); ?>
    </div>
<?php
    include_once("templates/footer.php");
    exit;
}

if ($isGestorSeguradora) {
    $segUserId = (int)($_SESSION['fk_seguradora_user'] ?? 0);
    $pacienteDao = new pacienteDAO($conn, $BASE_URL);
    $pacienteRow = $pacienteDao->findById((int)($data['fk_paciente_int'] ?? 0));
    $pacienteInfo = $pacienteRow && isset($pacienteRow[0]) ? $pacienteRow[0] : [];
    $segPacId = (int)($pacienteInfo['fk_seguradora_pac'] ?? 0);
    if (!$segUserId || $segUserId !== $segPacId) {
        echo "<div class='container mt-4'><div class='alert alert-danger'>Acesso negado para este paciente.</div></div>";
        include_once("templates/footer.php");
        exit;
    }
}

// Datas / auxiliares
$iniciais = initials_from_name($data['nome_pac'] ?? '');
$data_intern_format = fmtDate($data['data_intern_int'] ?? '');

/* =========================================================
   VISITAS
   ========================================================= */
$visitas = [];
$visitaDAO = new visitaDAO($conn, $BASE_URL);

try {
    if (method_exists($visitaDAO, 'joinVisitaInternacao')) {
        $visitas = $visitaDAO->joinVisitaInternacao((int)$id_internacao) ?: [];
    }
} catch (Throwable $e) {
    $visitas = [];
}

function pick_visit_date($row)
{
    foreach (['data_visita', 'data_visita_vis', 'data', 'data_visita_int', 'created_at'] as $k) {
        if (!empty($row[$k])) {
            $ts = strtotime(substr((string)$row[$k], 0, 19));
            if ($ts) return date('Y-m-d', $ts);
        }
    }
    return null;
}
function pick_visit_time($row)
{
    foreach (['data_visita', 'data_visita_vis', 'data', 'data_visita_int', 'created_at'] as $k) {
        if (!empty($row[$k])) {
            $ts = strtotime(substr((string)$row[$k], 0, 19));
            if ($ts) return date('H:i', $ts);
        }
    }
    return null;
}
function pick_visit_text($row)
{
    foreach (['rel_visita', 'rel_visita_vis', 'rel_vis', 'relatorio', 'observacao', 'obs', 'descricao'] as $k) {
        if (empty($row[$k])) {
            continue;
        }
        $text = trim((string)$row[$k]);
        if (preg_match('/^(Importado do OCR do PDF|Complementado via OCR)/i', $text)) {
            continue;
        }
        return $text;
    }
    return '';
}
function pick_visit_id($row)
{
    foreach (['id_visita', 'id', 'id_vst'] as $k) {
        if (!empty($row[$k])) return (int)$row[$k];
    }
    return crc32(json_encode($row));
}
function pick_visit_acomodacao($row)
{
    $keys = ['acomodacao', 'acomodacao_int', 'acomodacao_vis', 'acomodacao_atual', 'acomod', 'acomod_int'];
    foreach ($keys as $k) {
        if (!empty($row[$k])) return $row[$k];
    }
    return '';
}
function pick_visit_auditor($row)
{
    foreach (['auditor_nome', 'usuario_user', 'nome_usuario', 'usuario_cadastro', 'nome'] as $k) {
        if (!empty($row[$k])) return $row[$k];
    }
    if (!empty($row['usuario_create'])) {
        $parts = explode('@', (string)$row['usuario_create']);
        return ucfirst($parts[0] ?? '');
    }
    return '';
}
function pick_visit_role($row)
{
    $cargo = trim((string)($row['cargo_user'] ?? ''));
    if ($cargo !== '') {
        $cargoNorm = mb_strtolower($cargo, 'UTF-8');
        if (strpos($cargoNorm, 'med') !== false) return 'Médico Auditor';
        if (strpos($cargoNorm, 'enf') !== false) return 'Enfermeiro Auditor';
        return $cargo;
    }

    $flagMed = strtolower(trim((string)($row['visita_med_vis'] ?? '')));
    $flagEnf = strtolower(trim((string)($row['visita_enf_vis'] ?? '')));
    if (in_array($flagMed, ['s', 'sim', '1'], true) || !empty($row['visita_auditor_prof_med'])) {
        return 'Médico Auditor';
    }
    if (in_array($flagEnf, ['s', 'sim', '1'], true) || !empty($row['visita_auditor_prof_enf'])) {
        return 'Enfermeiro Auditor';
    }

    return '-';
}

$visitas_norm = [];
foreach (($visitas ?? []) as $v) {
    $d = pick_visit_date($v);

    $nomeAuditor = pick_visit_auditor($v);
    $cargoAuditor = pick_visit_role($v);
    $registro = $v['auditor_registro'] ?? $v['reg_profissional_user'] ?? '';

    if (!empty($registro) && !empty($nomeAuditor)) $nomeExibicao = $nomeAuditor . ' - ' . $registro;
    else $nomeExibicao = $nomeAuditor;

    $visitas_norm[] = [
        '_id'        => pick_visit_id($v),
        '_date'      => $d ?: date('Y-m-d'),
        '_time'      => pick_visit_time($v),
        '_text'      => pick_visit_text($v),
        'acomodacao' => pick_visit_acomodacao($v),
        '_auditor_nome' => $nomeAuditor,
        '_auditor'   => $nomeExibicao,
        '_cargo'     => $cargoAuditor,
        'retificado' => !empty($v['retificado']) ? 1 : 0,
        '_raw'       => $v,
    ];
}
usort($visitas_norm, fn($a, $b) => strcmp($a['_date'], $b['_date']));

$recentLimitInput = filter_input(INPUT_GET, 'recent_limit', FILTER_VALIDATE_INT);
$recentLimit = ($recentLimitInput && $recentLimitInput > 0) ? min($recentLimitInput, 20) : 5;
$recentOrderInput = filter_input(INPUT_GET, 'recent_order', FILTER_SANITIZE_SPECIAL_CHARS);
$recentOrder = in_array($recentOrderInput, ['asc', 'desc'], true) ? $recentOrderInput : 'desc';

$abaParam = filter_input(INPUT_GET, 'aba', FILTER_SANITIZE_SPECIAL_CHARS);
$abaAtual = $abaParam ?: 'resumo';
$abasValidas = ['resumo', 'visitas', 'prorrog', 'tuss', 'neg'];
if (!in_array($abaAtual, $abasValidas, true)) $abaAtual = 'resumo';
if (!$abaParam && (!empty($_GET['recent_limit']) || !empty($_GET['recent_order']))) $abaAtual = 'visitas';

$visitas_recent = $visitas_norm;
usort($visitas_recent, function ($a, $b) use ($recentOrder) {
    $ta = strtotime(($a['_date'] ?? '1970-01-01') . ' ' . ($a['_time'] ?? '00:00')) ?: 0;
    $tb = strtotime(($b['_date'] ?? '1970-01-01') . ' ' . ($b['_time'] ?? '00:00')) ?: 0;
    return $recentOrder === 'asc' ? ($ta <=> $tb) : ($tb <=> $ta);
});
// Busca com folga para manter a quantidade desejada mesmo removendo a visita ativa.
$visitas_recent = array_slice($visitas_recent, 0, $recentLimit + 1);

$minD = $visitas_norm ? $visitas_norm[0]['_date'] : null;
$maxD = $visitas_norm ? $visitas_norm[count($visitas_norm) - 1]['_date'] : null;
$spanDays = ($minD && $maxD) ? max(1, (new DateTime($minD))->diff(new DateTime($maxD))->days) : 1;
$minLabel = $minD ? date('d/m/Y', strtotime($minD)) : '';
$maxLabel = $maxD ? date('d/m/Y', strtotime($maxD)) : '';
$countVis = count($visitas_norm);

// Visita ativa
$vid_req = filter_input(INPUT_GET, 'vid', FILTER_SANITIZE_NUMBER_INT);
if (!$vid_req) $vid_req = filter_input(INPUT_GET, 'id_visita', FILTER_SANITIZE_NUMBER_INT);

$activeVisit = null;
if ($vid_req) {
    foreach ($visitas_norm as $vn) {
        if ($vn['_id'] === (int)$vid_req) {
            $activeVisit = $vn;
            break;
        }
    }
}
if (!$activeVisit && $visitas_norm) $activeVisit = $visitas_norm[count($visitas_norm) - 1];

$activeVisitRet = $activeVisit && !empty($activeVisit['retificado']);

$initDateLabel = '—';
$initTime = '';
$initText = '—';
$initId   = null;
$initAuditor = '';
$initCargo = '-';

if ($activeVisit) {
    $initDateLabel = date('d/m/Y', strtotime($activeVisit['_date']));
    $initTime      = $activeVisit['_time'] ?: '';
    $initText      = trim($activeVisit['_text']) !== '' ? $activeVisit['_text'] : '—';
    $initId        = (int)$activeVisit['_id'];
    $initAuditor   = $activeVisit['_auditor'];
    $initCargo     = $activeVisit['_cargo'] ?? '-';
}

$visitaBtnClass = $initId ? 'btn-success' : 'btn-outline-secondary';
$visualizarInternacaoUrl = rtrim($BASE_URL, '/') . '/internacoes/visualizar/' . (int)$id_internacao;
$visitaPdfBase = $BASE_URL . 'process_visita_pdf.php?id_internacao=' . urlencode((string)$id_internacao) . '&id_visita=';
$visitaPdfHref = $initId ? $visitaPdfBase . urlencode((string)$initId) : '#';
$visitaRangePdfBase = $BASE_URL . 'process_visita_pdf.php?range=1&id_internacao=' . urlencode((string)$id_internacao);
$visitaEditBase = $BASE_URL . 'cad_visita.php?id_internacao=' . urlencode((string)$id_internacao) . '&edit_visita=';

// Evita duplicar no layout a visita já destacada no card principal.
$visitas_recent_exibicao = $visitas_recent;
if ($initId) {
    $visitas_recent_exibicao = array_values(array_filter($visitas_recent_exibicao, function ($item) use ($initId) {
        return (int)($item['_id'] ?? 0) !== (int)$initId;
    }));
}
$visitas_recent_exibicao = array_slice($visitas_recent_exibicao, 0, $recentLimit);
$visitas_recent_exibicao_count = count($visitas_recent_exibicao);

/* =========================================================
   PRORROGAÇÕES
   ========================================================= */
$prorrogacoes = [];
if (class_exists('prorrogacaoDAO')) {
    $prDAO = new prorrogacaoDAO($conn, $BASE_URL);
    if (method_exists($prDAO, 'selectInternacaoProrrog')) {
        $prorrogacoes = $prDAO->selectInternacaoProrrog((int)$id_internacao) ?: [];
    }
}
if (!$prorrogacoes && class_exists('negociacaoDAO')) {
    $negFallbackStmt = $conn->prepare("
        SELECT
            0 AS id_prorrogacao,
            troca_para AS acomod,
            data_inicio_neg AS ini,
            data_fim_neg AS fim,
            qtd AS diarias,
            'n' AS isolamento
        FROM tb_negociacao
        WHERE fk_id_int = :id
          AND tipo_negociacao = 'PRORROGACAO_AUTOMATICA'
        ORDER BY data_inicio_neg
    ");
    $negFallbackStmt->bindValue(':id', (int)$id_internacao, PDO::PARAM_INT);
    $negFallbackStmt->execute();
    $prorrogacoes = $negFallbackStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
$pr_ini_raw = filter_input(INPUT_GET, 'pr_ini', FILTER_DEFAULT) ?: '';
$pr_fim_raw = filter_input(INPUT_GET, 'pr_fim', FILTER_DEFAULT) ?: '';
$pr_ini = ymd($pr_ini_raw);
$pr_fim = ymd($pr_fim_raw);

$pr_filtered = $prorrogacoes;
if ($pr_ini || $pr_fim) {
    $pr_filtered = array_filter($prorrogacoes, function ($p) use ($pr_ini, $pr_fim) {
        $ini = ymd($p['ini'] ?? null);
        $fim = ymd($p['fim'] ?? ($p['ini'] ?? null));
        if (!$ini && !$fim) return false;
        if ($pr_ini && $pr_fim) return ($fim >= $pr_ini) && ($ini <= $pr_fim);
        if ($pr_ini) return $fim >= $pr_ini;
        if ($pr_fim) return $ini <= $pr_fim;
        return true;
    });
}
usort($pr_filtered, function ($a, $b) {
    $da = strtotime((string)($a['fim'] ?: ($a['ini'] ?? '')));
    $db = strtotime((string)($b['fim'] ?: ($b['ini'] ?? '')));
    return $db <=> $da;
});
$pr_total_diarias = array_reduce($pr_filtered, fn($s, $p) => $s + (int)($p['diarias'] ?? 0), 0);

// Sinalizador de período aberto na prorrogação
$pr_pendente_label = '';
$pr_pendente_gaps = [];
$internStart = ymd($data['data_intern_int'] ?? '');
$internStartTs = $internStart ? dateToTs($internStart) : null;
$altaStmt = $conn->prepare("SELECT MAX(data_alta_alt) AS data_alta_alt FROM tb_alta WHERE fk_id_int_alt = :id");
$altaStmt->bindValue(':id', (int)$id_internacao, PDO::PARAM_INT);
$altaStmt->execute();
$altaRow = $altaStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$altaDate = $altaRow['data_alta_alt'] ?? null;
$internEnd = $altaDate ? ymd($altaDate) : date('Y-m-d');
$internEndTs = $internEnd ? dateToTs($internEnd) : null;

if ($internStartTs && $internEndTs && $internEndTs > $internStartTs) {
    $intervals = [];
    foreach ($prorrogacoes as $p) {
        $ini = ymd($p['ini'] ?? null);
        if (!$ini) continue;
        $fim = ymd($p['fim'] ?? null);
        $iniTs = dateToTs($ini);
        $fimTs = dateToTs($fim) ?: $internEndTs;
        if (!$iniTs || $fimTs <= $internStartTs || $iniTs >= $internEndTs) continue;
        $iniTs = max($iniTs, $internStartTs);
        $fimTs = min($fimTs, $internEndTs);
        $intervals[] = ['s' => $iniTs, 'e' => $fimTs];
    }
    [$coveredDays, $missingDays, $gaps] = computeCoverageAndGaps($intervals, $internStartTs, $internEndTs);
    if ($missingDays > 0) {
        $parts = array_map(fn($g) => $g[0] . ' → ' . $g[1], $gaps);
        $pr_pendente_label = $missingDays . ' dias | ' . implode(' • ', $parts);
    }
}

/* =========================================================
   TUSS
   ========================================================= */
$tussItens = [];
if (class_exists('tussDAO')) {
    $tussDAO = new tussDAO($conn, $BASE_URL);
    if (method_exists($tussDAO, 'selectAllTUSSByIntern')) {
        $tussItens = $tussDAO->selectAllTUSSByIntern((int)$id_internacao) ?: [];
    }
}
$tuss_ini_raw = filter_input(INPUT_GET, 'tuss_ini', FILTER_DEFAULT) ?: '';
$tuss_fim_raw = filter_input(INPUT_GET, 'tuss_fim', FILTER_DEFAULT) ?: '';
$tuss_ini = ymd($tuss_ini_raw);
$tuss_fim = ymd($tuss_fim_raw);

$tuss_filtered = $tussItens;
if ($tuss_ini || $tuss_fim) {
    $tuss_filtered = array_filter($tussItens, function ($t) use ($tuss_ini, $tuss_fim) {
        $dt = ymd($t['data_realizacao_tuss'] ?? null);
        if (!$dt) return false;
        if ($tuss_ini && $tuss_fim) return ($dt >= $tuss_ini) && ($dt <= $tuss_fim);
        if ($tuss_ini) return $dt >= $tuss_ini;
        if ($tuss_fim) return $dt <= $tuss_fim;
        return true;
    });
}
usort($tuss_filtered, function ($a, $b) {
    $da = strtotime((string)($a['data_realizacao_tuss'] ?? ''));
    $db = strtotime((string)($b['data_realizacao_tuss'] ?? ''));
    return $db <=> $da;
});
$tuss_tot_solic = array_reduce($tuss_filtered, fn($s, $r) => $s + (int)($r['qtd_tuss_solicitado'] ?? 0), 0);
$tuss_tot_lib   = array_reduce($tuss_filtered, fn($s, $r) => $s + (int)($r['qtd_tuss_liberado'] ?? 0), 0);

/* =========================================================
   NEGOCIAÇÕES
   ========================================================= */
$negociacoes = [];
if (class_exists('negociacaoDAO')) {
    $negDAO = new negociacaoDAO($conn, $BASE_URL);
    if (method_exists($negDAO, 'findByInternacao')) {
        $negociacoes = $negDAO->findByInternacao((int)$id_internacao) ?: [];
    }
}
if (!function_exists('negociacaoEhTrocaReal')) {
    function negociacaoEhTrocaReal(array $n): bool
    {
        $tipo = (string)($n['tipo_negociacao'] ?? '');
        if ($tipo !== 'PRORROGACAO_AUTOMATICA') {
            return true;
        }
        $de = trim((string)after_dash($n['troca_de'] ?? ''));
        $para = trim((string)after_dash($n['troca_para'] ?? ''));
        if ($de === '' || $para === '') {
            return false;
        }
        return mb_strtolower($de, 'UTF-8') !== mb_strtolower($para, 'UTF-8');
    }
}
$negociacoes = array_values(array_filter($negociacoes, 'negociacaoEhTrocaReal'));
$neg_ini_raw = filter_input(INPUT_GET, 'neg_ini', FILTER_DEFAULT) ?: '';
$neg_fim_raw = filter_input(INPUT_GET, 'neg_fim', FILTER_DEFAULT) ?: '';
$neg_ini = ymd($neg_ini_raw);
$neg_fim = ymd($neg_fim_raw);

$neg_filtered = $negociacoes;
if ($neg_ini || $neg_fim) {
    $neg_filtered = array_filter($negociacoes, function ($n) use ($neg_ini, $neg_fim) {
        $ini = ymd($n['data_inicio_neg'] ?? null);
        $fim = ymd($n['data_fim_neg'] ?? null) ?: $ini;
        if (!$ini && !$fim) return false;
        if ($neg_ini && $neg_fim) return ($fim >= $neg_ini) && ($ini <= $neg_fim);
        if ($neg_ini) return $fim >= $neg_ini;
        if ($neg_fim) return $ini <= $neg_fim;
        return true;
    });
}
usort($neg_filtered, function ($a, $b) {
    $da = strtotime((string)($a['data_fim_neg'] ?? ($a['data_inicio_neg'] ?? '')));
    $db = strtotime((string)($b['data_fim_neg'] ?? ($b['data_inicio_neg'] ?? '')));
    return $db <=> $da;
});

$visitasCount = count($visitas_norm);
$prorrogCount = count($prorrogacoes);
$tussCount = count($tussItens);
$negCount = count($negociacoes);

$internacaoEncerrada = !empty($altaDate) && (string)$altaDate !== '0000-00-00';
$statusInternacao = $internacaoEncerrada ? 'Alta' : 'Internado';
$statusInternacaoDetalhe = $internacaoEncerrada ? ('em ' . fmtDateAny($altaDate)) : 'em curso';

$diasInternado = null;
if ($internStartTs && $internEndTs && $internEndTs >= $internStartTs) {
    $diasInternado = (int)floor(($internEndTs - $internStartTs) / 86400) + 1;
}

$pendencias = [];
if (!empty($pr_pendente_label)) {
    $pendencias[] = 'Período de prorrogação em aberto';
}
if (empty($visitas_norm)) {
    $pendencias[] = 'Nenhuma visita registrada';
}
$pendenciasCount = count($pendencias);
$pendenciasTitle = $pendencias ? implode(' • ', $pendencias) : 'Sem pendências operacionais detectadas';

$priorityLevel = 'Normal';
$priorityIcon = 'fas fa-check-circle';
$priorityClass = 'is-normal';
if (!empty($pr_pendente_label) && empty($visitas_norm)) {
    $priorityLevel = 'Crítico';
    $priorityIcon = 'fas fa-exclamation-triangle';
    $priorityClass = 'is-critical';
} elseif (!empty($pr_pendente_label) || !empty($pendenciasCount)) {
    $priorityLevel = 'Atenção';
    $priorityIcon = 'fas fa-exclamation-circle';
    $priorityClass = 'is-warning';
}

$novaVisitaUrl = $BASE_URL . 'cad_visita.php?id_internacao=' . (int)$id_internacao;
$editarInternacaoUrl = $BASE_URL . 'edit_internacao.php?id_internacao=' . (int)$id_internacao;
$gerarAltaUrl = $BASE_URL . 'edit_alta.php?type=alta&id_internacao=' . (int)$id_internacao;
$editarProrrogUrl = $BASE_URL . 'edit_internacao.php?id_internacao=' . (int)$id_internacao . '&section=prorrog#collapseProrrog';
$editarTussUrl = $BASE_URL . 'edit_internacao.php?id_internacao=' . (int)$id_internacao . '&section=tuss#collapseTuss';
$editarNegocUrl = $BASE_URL . 'edit_internacao.php?id_internacao=' . (int)$id_internacao . '&section=negoc#collapseNegoc';
?>

<div id="main-container" class="container-fluid py-3">
    <div class="v2-max mx-auto">

        <div class="card shadow-sm mb-3 header-card">
            <div class="card-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
                <div class="d-flex gap-3 align-items-center">
                    <div class="v2-avatar"><?= e($iniciais) ?></div>
                    <div>
                        <h4 class="mb-1"><?= e(mb_strtoupper($data['nome_pac'] ?? '-')) ?></h4>
                        <div class="d-flex flex-wrap gap-2 text-secondary small">
                            <span><i class="fas fa-hospital me-1"></i><?= e($data['nome_hosp'] ?? '-') ?></span>
                            <span>•</span>
                            <span><i class="fas fa-procedures me-1"></i>Internação <?= e($data['id_internacao'] ?? '-') ?></span>
                            <span>•</span>
                            <span><i class="far fa-calendar-alt me-1"></i>Data da internação: <?= e($data_intern_format) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-body py-2 px-3">
                <div class="ux-summary-strip">
                    <div class="ux-summary-chip">
                        <span class="ux-chip-label">Status</span>
                        <strong class="ux-chip-value"><?= e($statusInternacao) ?></strong>
                        <span class="ux-chip-sub"><?= e($statusInternacaoDetalhe) ?></span>
                    </div>
                    <div class="ux-summary-chip">
                        <span class="ux-chip-label">Dias internado</span>
                        <strong class="ux-chip-value"><?= $diasInternado !== null ? (int)$diasInternado : '-' ?></strong>
                    </div>
                    <div class="ux-summary-chip">
                        <span class="ux-chip-label">Prorrogação</span>
                        <strong class="ux-chip-value"><?= !empty($pr_pendente_label) ? 'Em aberto' : 'Coberta' ?></strong>
                    </div>
                    <div class="ux-summary-chip ux-priority-chip <?= e($priorityClass) ?>" title="<?= e($pendenciasTitle) ?>">
                        <span class="ux-chip-label">Prioridade</span>
                        <strong class="ux-chip-value"><i class="<?= e($priorityIcon) ?> me-1"></i><?= e($priorityLevel) ?></strong>
                        <span class="ux-chip-sub"><?= (int)$pendenciasCount ?> pendência(s)</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <ul class="nav nav-pills mb-0" id="internTabs" role="tablist"
                        style="--bs-nav-pills-link-active-bg:#5e2363; --bs-nav-pills-link-active-color:#fff; --bs-nav-link-color:#5e2363; --bs-nav-link-hover-color:#5e2363;">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link<?= $abaAtual === 'resumo' ? ' active' : '' ?>"
                                id="resumo-tab"
                                data-bs-toggle="pill"
                                data-bs-target="#resumo"
                                type="button" role="tab" aria-controls="resumo"
                                aria-selected="<?= $abaAtual === 'resumo' ? 'true' : 'false' ?>">
                                <i class="fas fa-bars me-2"></i>Resumo
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link<?= $abaAtual === 'visitas' ? ' active' : '' ?>"
                                id="visitas-tab"
                                data-bs-toggle="pill"
                                data-bs-target="#visitas"
                                type="button" role="tab" aria-controls="visitas"
                                aria-selected="<?= $abaAtual === 'visitas' ? 'true' : 'false' ?>">
                                <i class="fas fa-stethoscope me-2"></i>Visitas
                                <span class="ux-tab-count"><?= (int)$visitasCount ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link<?= $abaAtual === 'prorrog' ? ' active' : '' ?>"
                                id="prorrog-tab"
                                data-bs-toggle="pill"
                                data-bs-target="#prorrog"
                                type="button" role="tab" aria-controls="prorrog"
                                aria-selected="<?= $abaAtual === 'prorrog' ? 'true' : 'false' ?>">
                                <i class="fas fa-history me-2"></i>Prorrogações
                                <span class="ux-tab-count"><?= (int)$prorrogCount ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link<?= $abaAtual === 'tuss' ? ' active' : '' ?>"
                                id="tuss-tab"
                                data-bs-toggle="pill"
                                data-bs-target="#tuss"
                                type="button" role="tab" aria-controls="tuss"
                                aria-selected="<?= $abaAtual === 'tuss' ? 'true' : 'false' ?>">
                                <i class="fas fa-tasks me-2"></i>TUSS
                                <span class="ux-tab-count"><?= (int)$tussCount ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link<?= $abaAtual === 'neg' ? ' active' : '' ?>"
                                id="neg-tab"
                                data-bs-toggle="pill"
                                data-bs-target="#neg"
                                type="button" role="tab" aria-controls="neg"
                                aria-selected="<?= $abaAtual === 'neg' ? 'true' : 'false' ?>">
                                <i class="fas fa-handshake me-2"></i>Negociações
                                <span class="ux-tab-count"><?= (int)$negCount ?></span>
                            </button>
                        </li>
                    </ul>

                </div>

                <div class="ux-actions-sticky mb-3">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <?php if (!$isGestorSeguradora): ?>
                            <a href="<?= e($novaVisitaUrl) ?>" class="btn btn-sm text-white shadow-sm"
                                style="background-color:#5e2363;border-color:#5e2363;">
                                <i class="fas fa-plus me-1"></i>Nova Visita
                            </a>
                            <a href="<?= e($editarInternacaoUrl) ?>" class="btn btn-sm btn-outline-secondary shadow-sm">
                                <i class="fas fa-edit me-1"></i>Editar internação
                            </a>
                            <a href="<?= e($gerarAltaUrl) ?>" class="btn btn-sm btn-outline-danger shadow-sm">
                                <i class="fas fa-file-alt me-1"></i>Gerar alta
                            </a>
                        <?php endif; ?>
                        <a href="<?= !empty($_SERVER['HTTP_REFERER']) ? 'javascript:history.back()' : $BASE_URL . 'list_intenacao.php' ?>"
                            class="btn btn-ghost-brand btn-sm shadow-sm">
                            <i class="fas fa-arrow-left me-1"></i>Voltar
                        </a>
                    </div>
                </div>

                <!-- ✅ Bootstrap vai cuidar do show/hide sem “espaço fantasma” -->
                <div class="tab-content" id="internTabsContent">

                    <!-- ================= RESUMO ================= -->
                    <div class="tab-pane fade<?= $abaAtual === 'resumo' ? ' show active' : '' ?>"
                        id="resumo" role="tabpanel" aria-labelledby="resumo-tab" tabindex="0">
                        <div class="row g-3">
                            <div class="col-12 col-lg-6">
                                <div class="card ov-card ov-int"
                                    style="border-radius:14px;background:#fff;box-shadow:0 8px 24px rgba(0,0,0,.06);background-image:linear-gradient(to right, var(--ov, #5e2363) 6px, #fff 6px);">
                                    <div class="card-body">
                                        <div class="ov-head">
                                            <div class="ov-icon"><i class="fas fa-procedures"></i></div>
                                            <h6 class="ov-title mb-0">Internação</h6>
                                        </div>
                                        <dl class="details-dl">
                                            <dt>Código</dt>
                                            <dd><?= e($data['id_internacao'] ?? '-') ?></dd>
                                            <dt>Senha</dt>
                                            <dd><?= e($data['senha_int'] ?? '-') ?></dd>
                                            <dt>Acomodação</dt>
                                            <dd><?= e($data['acomodacao_int'] ?? '—') ?></dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-lg-6">
                                <div class="card ov-card ov-vis"
                                    style="border-radius:14px;background:#fff;box-shadow:0 8px 24px rgba(0,0,0,.06);background-image:linear-gradient(to right, var(--ov, #0f766e) 6px, #fff 6px);">
                                    <div class="card-body">
                                        <div class="ov-head">
                                            <div class="ov-icon"><i class="fas fa-user-md"></i></div>
                                            <h6 class="ov-title mb-0">Detalhes</h6>
                                        </div>
                                        <dl class="details-dl">
                                            <dt>Tipo admissão</dt>
                                            <dd><?= e($data['tipo_admissao_int'] ?? '-') ?></dd>
                                            <dt>Modo Internação</dt>
                                            <dd><?= e($data['modo_internacao_int'] ?? '-') ?></dd>
                                            <dt>Especialidade</dt>
                                            <dd><?= e($data['especialidade_int'] ?? '-') ?></dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-12">
                                <div class="card ov-card ov-int"
                                    style="border-radius:14px;background:#fff;box-shadow:0 8px 24px rgba(0,0,0,.06);background-image:linear-gradient(to right, var(--ov, #5e2363) 6px, #fff 6px);">
                                    <div class="card-body">
                                        <div class="ov-head">
                                            <h6 class="ov-title mb-0">Relatório Internação</h6>
                                        </div>
                                        <div class="v2-relatorio"><?= nl2br(e($data['rel_int'] ?? '-')) ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ================= VISITAS ================= -->
                    <div class="tab-pane fade<?= $abaAtual === 'visitas' ? ' show active' : '' ?>"
                        id="visitas" role="tabpanel" aria-labelledby="visitas-tab" tabindex="0">

                        <?php if (!$visitas_norm): ?>
                            <div class="ux-empty-state">
                                <div class="ux-empty-title">Nenhuma visita registrada para esta internação</div>
                                <div class="ux-empty-text">Cadastre a primeira visita para iniciar o histórico clínico e liberar relatórios por período.</div>
                                <?php if (!$isGestorSeguradora): ?>
                                    <div class="mt-2">
                                        <a href="<?= e($novaVisitaUrl) ?>" class="btn btn-sm text-white" style="background:#5e2363;border-color:#5e2363;">
                                            <i class="fas fa-plus me-1"></i>Cadastrar visita
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>

                            <div class="card ov-card ov-int"
                                style="border-radius:14px;background:#fff;box-shadow:0 8px 24px rgba(0,0,0,.06);background-image:linear-gradient(to right, var(--ov, #5e2363) 6px, #fff 6px);">
                                <div class="card-body">
                                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center ov-head gap-2">
                                        <div>
                                            <h6 class="ov-title mb-0">Período das visitas</h6>
                                            <?php if ($minLabel && $maxLabel): ?>
                                                <div class="small text-muted" id="vis-periodo-resumo"><?= e($minLabel) ?> — <?= e($maxLabel) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small text-secondary text-nowrap" id="vis-periodo-selecionado" style="display:none;">
                                            <strong>Período selecionado:</strong>
                                            <span id="vis-periodo-range"></span>
                                        </div>
                                    </div>

                                    <?php if ($minD && $maxD && $countVis > 0): ?>
                                        <div class="mb-3">
                                            <form id="formFiltroVisitas" class="row g-2 align-items-end">
                                                <div class="col-sm-4 col-md-3">
                                                    <label class="form-label small text-muted">Data inicial</label>
                                                    <input type="date" id="vis_ini" class="form-control form-control-sm"
                                                        value="<?= e($minD) ?>" data-default="<?= e($minD) ?>">
                                                </div>
                                                <div class="col-sm-4 col-md-3">
                                                    <label class="form-label small text-muted">Data final</label>
                                                    <input type="date" id="vis_fim" class="form-control form-control-sm"
                                                        value="<?= e($maxD) ?>" data-default="<?= e($maxD) ?>">
                                                </div>
                                                <div class="col-auto">
                                                    <button type="button" id="btnAplicarVisitas" class="btn btn-sm btn-primary"
                                                        style="background:#5e2363;border-color:#5e2363;">Aplicar</button>
                                                </div>
                                                <div class="col-auto">
                                                    <button type="button" id="btnLimparVisitas" class="btn btn-sm btn-outline-secondary">Limpar</button>
                                                </div>
                                            </form>
                                            <div class="small text-muted mt-2">As visitas fora do intervalo selecionado são escondidas da tabela.</div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="visitas-table-wrap mb-3">
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle mb-0 visitas-table">
                                                <thead>
                                                    <tr>
                                                        <th>ID visita</th>
                                                        <th>Usuário</th>
                                                        <th>Cargo</th>
                                                        <th>Data da visita</th>
                                                        <th class="visita-actions-head">Ações</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($visitas_norm as $v):
                                                        $isActive  = ($activeVisit && $activeVisit['_id'] === $v['_id']);
                                                        $dataLabel = date('d/m/Y', strtotime($v['_date']));
                                                        $hora      = $v['_time'] ?: '';
                                                        $texto     = trim($v['_text']) !== '' ? $v['_text'] : '—';
                                                        $auditorNome = trim((string)($v['_auditor_nome'] ?? ''));
                                                        $auditorNomeExib = $auditorNome !== '' ? $auditorNome : trim((string)($v['_auditor'] ?? ''));
                                                        $auditorNomeExib = $auditorNomeExib !== '' ? $auditorNomeExib : '—';
                                                        $cargoAuditor = trim((string)($v['_cargo'] ?? '-'));
                                                        $visitaId = (int)$v['_id'];
                                                        $deleteRowDisabled = ($countVis <= 1) || !empty($v['retificado']);
                                                    ?>
                                                        <tr class="js-visita-select<?= $isActive ? ' active' : '' ?>"
                                                            role="button" tabindex="0"
                                                            data-dateraw="<?= e($v['_date']) ?>"
                                                            data-id="<?= $visitaId ?>"
                                                            data-date="<?= e($dataLabel) ?>"
                                                            data-time="<?= e($hora) ?>"
                                                            data-text="<?= e($texto) ?>"
                                                            data-auditor="<?= e($v['_auditor'] ?? '') ?>"
                                                            data-cargo="<?= e($cargoAuditor) ?>"
                                                            data-retificado="<?= !empty($v['retificado']) ? '1' : '0' ?>"
                                                            onclick="window.selectVisitaEntry(this); return false;"
                                                            onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault(); window.selectVisitaEntry(this);}">
                                                            <td class="visita-id-cell">
                                                                <span class="fw-semibold text-dark">#<?= $visitaId ?></span>
                                                            </td>
                                                            <td>
                                                                <div class="fw-semibold text-dark"><?= e($auditorNomeExib) ?></div>
                                                            </td>
                                                            <td>
                                                                <span class="text-dark"><?= e($cargoAuditor !== '' ? $cargoAuditor : '-') ?></span>
                                                            </td>
                                                            <td>
                                                                <div class="fw-semibold"><?= e($dataLabel) ?></div>
                                                            </td>
                                                            <td class="visita-actions-cell">
                                                                <div class="visita-actions">
                                                                    <a class="btn btn-sm btn-outline-success"
                                                                        href="<?= e($visitaPdfBase . urlencode((string)$visitaId)) ?>"
                                                                        target="_blank" rel="noopener"
                                                                        onclick="event.stopPropagation();">
                                                                        PDF
                                                                    </a>
                                                                    <?php if (!$isGestorSeguradora): ?>
                                                                        <a class="btn btn-sm btn-outline-primary"
                                                                            href="<?= e($visitaEditBase . urlencode((string)$visitaId)) ?>"
                                                                            onclick="event.stopPropagation();">
                                                                            Editar
                                                                        </a>
                                                                        <button type="button"
                                                                            class="btn btn-sm btn-outline-danger<?= $deleteRowDisabled ? ' disabled' : '' ?>"
                                                                            onclick="event.stopPropagation(); window.promptDeleteVisitaFromTable(<?= $visitaId ?>, <?= !empty($v['retificado']) ? 'true' : 'false' ?>);"
                                                                            <?= $deleteRowDisabled ? 'disabled aria-disabled="true"' : '' ?>>
                                                                            Excluir
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    <tr id="visitas-empty-row" style="display:none;">
                                                        <td colspan="5" class="text-center text-muted py-4">Nenhuma visita encontrada no período selecionado.</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <div class="d-flex flex-wrap justify-content-end align-items-stretch gap-2 mb-2 btn-visitas-row">
                                            <?php if ($visitas_recent_exibicao_count > 0): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-ultimas-visitas btn-visitas-eq"
                                                    data-bs-toggle="modal" data-bs-target="#modalUltimasVisitas">
                                                    <i class="fas fa-history me-1"></i> Últimas visitas
                                                </button>
                                            <?php endif; ?>

                                            <?php
                                                $rangeEnabled = !empty($minD) && !empty($maxD);
                                                $rangeHref = $rangeEnabled
                                                    ? ($visitaRangePdfBase . '&data_ini=' . urlencode((string)$minD) . '&data_fim=' . urlencode((string)$maxD))
                                                    : '#';
                                            ?>
                                            <a id="btn-visitas-range-pdf"
                                                class="btn btn-sm btn-outline-primary btn-visitas-eq<?= $rangeEnabled ? '' : ' disabled' ?>"
                                                data-base="<?= e($visitaRangePdfBase) ?>" href="<?= e($rangeHref) ?>"
                                                target="_blank" rel="noopener"
                                                aria-disabled="<?= $rangeEnabled ? 'false' : 'true' ?>">
                                                <i class="fas fa-file-pdf me-1"></i> PDF (período)
                                                <span id="btn-visitas-range-info" class="d-block small mt-1 text-start text-muted">
                                                    <?= $rangeEnabled ? ('Período: ' . e($minLabel) . ' — ' . e($maxLabel)) : 'Use o filtro de datas' ?>
                                                </span>
                                            </a>
                                        </div>

                                        <div class="border-top mt-3 mb-3"></div>

                                        <div class="p-3 rounded-4 shadow-sm" style="background:#f9f9fb;border:1px solid #e0e3ea;">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <h6 class="mb-0 text-secondary fw-semibold">Relatório da visita:
                                                    <span id="v-rel-date" class="text-dark"><?= e($initDateLabel) ?></span>
                                                </h6>
                                                <div class="d-flex align-items-center gap-2">
                                                    <button type="button" id="btn-foco-relatorio" class="btn btn-sm btn-outline-secondary"
                                                        data-bs-toggle="modal" data-bs-target="#modalRelatorioFoco">
                                                        <i class="fas fa-expand-arrows-alt me-1"></i>Modo foco
                                                    </button>
                                                    <span id="v-rel-id-wrap" class="badge bg-secondary-subtle text-secondary-emphasis<?= $initId ? '' : ' d-none' ?>">
                                                        ID <span id="v-rel-id"><?= e($initId ?: '') ?></span>
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="mt-3 p-3 rounded bg-white border" style="border-color:#e0e3ea;">
                                                <div class="v2-relatorio" id="v-rel-text" style="white-space:pre-wrap"><?= e($initText) ?></div>
                                            </div>

                                            <div id="v-rel-auditor-wrap"
                                                style="font-size:0.85rem;color:#5e2363;font-weight:600;margin-top:10px;display:<?= !empty($initAuditor) ? 'block' : 'none' ?>;">
                                                <i class="fas fa-user-md" style="margin-right:5px;"></i>
                                                Visita realizada pelo(a) Auditor(a):
                                                <span id="v-rel-auditor"><?= e($initAuditor) ?></span>
                                            </div>

                                            <div id="v-rel-cargo-wrap"
                                                style="font-size:0.85rem;color:#475467;font-weight:600;margin-top:6px;display:<?= !empty($initCargo) && $initCargo !== '-' ? 'block' : 'none' ?>;">
                                                <i class="fas fa-briefcase" style="margin-right:5px;"></i>
                                                Cargo:
                                                <span id="v-rel-cargo"><?= e($initCargo) ?></span>
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center mt-2">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if ($visitas_recent_exibicao_count > 0): ?>
                                <div class="ux-recent-wrap card shadow-sm mt-4" style="border:1px solid #dbe5f1;border-left:5px solid #1d8fe1;border-radius:16px;background:linear-gradient(180deg,#fbfcff 0%,#f4f8ff 100%);box-shadow:0 8px 24px rgba(17,24,39,.06);">
                                    <div class="card-body">
                                    <div class="ux-recent-header">
                                        <h6 class="ux-recent-title mb-0">
                                            <i class="fas fa-layer-group me-2"></i>
                                            Últimas <?= e($visitas_recent_exibicao_count) ?> visitas registradas
                                        </h6>
                                        <form class="ux-recent-controls d-flex flex-wrap align-items-center gap-2" method="get" action="<?= e($_SERVER['PHP_SELF']) ?>#visitas">
                                            <input type="hidden" name="id_internacao" value="<?= (int)$id_internacao ?>">
                                            <input type="hidden" name="aba" value="visitas">
                                            <?php if (!empty($vid_req)): ?>
                                                <input type="hidden" name="vid" value="<?= (int)$vid_req ?>">
                                            <?php endif; ?>

                                            <label class="small text-muted mb-0">Qtd
                                                <select name="recent_limit" class="form-select form-select-sm d-inline-block" style="width:auto;">
                                                    <?php foreach ([3, 5, 10, 15, 20] as $opt): ?>
                                                        <option value="<?= $opt ?>" <?= $recentLimit == $opt ? 'selected' : '' ?>><?= $opt ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>

                                            <label class="small text-muted mb-0">Ordem
                                                <select name="recent_order" class="form-select form-select-sm d-inline-block" style="width:auto;">
                                                    <option value="desc" <?= $recentOrder === 'desc' ? 'selected' : '' ?>>Recente</option>
                                                    <option value="asc" <?= $recentOrder === 'asc' ? 'selected' : '' ?>>Antiga</option>
                                                </select>
                                            </label>

                                            <button class="btn btn-sm btn-outline-secondary" type="submit">Aplicar</button>
                                        </form>
                                    </div>

                                    <div class="d-flex flex-column gap-3 mt-3">
                                        <?php foreach ($visitas_recent_exibicao as $recent):
                                            $recentDate = $recent['_date'] ? date('d/m/Y', strtotime($recent['_date'])) : '—';
                                            $recentText = trim((string)($recent['_text'] ?? ''));
                                            $recentAud  = trim((string)($recent['_auditor'] ?? ''));
                                            $recentId   = $recent['_id'] ?? ($recent['_raw']['id_visita'] ?? null);
                                        ?>
                                            <div class="ux-recent-item" style="border:1px solid #e3e6ee;border-left:4px solid #1d8fe1;border-radius:14px;background:#fff;box-shadow:0 4px 16px rgba(17,24,39,.04);padding:16px 18px;">
                                                <div class="ux-recent-meta">
                                                    <div class="ux-recent-meta-block">
                                                        <span class="ux-recent-meta-label">Data da visita</span>
                                                        <span class="ux-recent-date-chip" style="display:inline-block;width:fit-content;padding:3px 11px;border-radius:999px;background:#e7f3ff;color:#0f4f8e;font-weight:700;"><?= e($recentDate) ?></span>
                                                    </div>
                                                    <div class="ux-recent-meta-block">
                                                        <span class="ux-recent-meta-label">Profissional</span>
                                                        <span class="ux-recent-meta-value"><?= e($recentAud !== '' ? $recentAud : '-') ?></span>
                                                    </div>
                                                    <?php if ($recentId): ?>
                                                        <div class="ux-recent-meta-block">
                                                            <span class="ux-recent-meta-label">ID</span>
                                                            <span class="ux-recent-meta-id" style="display:inline-flex;align-items:center;width:fit-content;padding:2px 10px;border-radius:999px;border:1px solid #d8e5f2;background:#f8fbff;color:#2d4059;font-size:.82rem;font-weight:700;">#<?= e($recentId) ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="ux-recent-evolucao mt-3" style="border:1px solid #e7e9f0;border-radius:10px;background:#fdfefe;padding:12px 14px;line-height:1.55;">
                                                    <div class="small text-muted text-uppercase mb-2">Relatório / Evolução</div>
                                                    <p class="mb-0"><?= nl2br(e($recentText !== '' ? $recentText : '-')) ?></p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                        <?php endif; ?>

                        <?php if (!empty($visitas_norm)): ?>
                            <div class="modal fade" id="modalDeleteVisitaInternacao" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Remover visita</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Deseja realmente deletar esta visita? Essa ação apenas desativa o registro.</p>
                                            <div class="alert alert-danger d-none js-delete-feedback" role="alert"></div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                                            <button type="button" class="btn btn-danger" data-action="confirm-delete">Remover</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($visitas_recent_exibicao_count > 0): ?>
                            <div class="modal fade modal-ultimas-visitas" id="modalUltimasVisitas" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-xl modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header border-0">
                                            <div>
                                                <h5 class="modal-title">Últimas visitas</h5>
                                            </div>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="visita-list">
                                                <?php foreach ($visitas_recent_exibicao as $vis):
                                                    $d = $vis['_date'] ? date('d/m/Y', strtotime($vis['_date'])) : '-';
                                                    $relatorio = trim((string)($vis['_text'] ?? ''));
                                                    $idVis = $vis['_id'] ?? ($vis['_raw']['id_visita'] ?? null);
                                                ?>
                                                    <div class="visita-item rounded-4 shadow-sm mb-3 p-3" style="border:1px solid #e0e3ea;border-left:4px solid #1d8fe1;background:#f9f9fb;">
                                                        <div class="ux-recent-meta">
                                                            <div class="ux-recent-meta-block">
                                                                <span class="ux-recent-meta-label">Data da visita</span>
                                                                <span class="ux-recent-date-chip" style="display:inline-block;width:fit-content;padding:3px 11px;border-radius:999px;background:#e7f3ff;color:#0f4f8e;font-weight:700;"><?= e($d) ?></span>
                                                            </div>
                                                            <div class="ux-recent-meta-block">
                                                                <span class="ux-recent-meta-label">Profissional</span>
                                                                <span class="ux-recent-meta-value"><?= e($vis['_auditor'] ?: '-') ?></span>
                                                            </div>
                                                            <?php if ($idVis): ?>
                                                                <div class="ux-recent-meta-block">
                                                                    <span class="ux-recent-meta-label">ID</span>
                                                                    <span class="ux-recent-meta-id" style="display:inline-flex;align-items:center;width:fit-content;padding:2px 10px;border-radius:999px;border:1px solid #d8e5f2;background:#f8fbff;color:#2d4059;font-size:.82rem;font-weight:700;">#<?= e($idVis) ?></span>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="mt-3 p-3 rounded bg-white border" style="border-color:#e0e3ea;">
                                                            <span class="small text-muted d-block mb-2 text-uppercase">Relatório / Evolução</span>
                                                            <p class="mb-0"><?= nl2br(e($relatorio !== '' ? $relatorio : '-')) ?></p>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-0">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($visitas_norm)): ?>
                            <div class="modal fade" id="modalRelatorioFoco" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Relatório da visita em foco</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="small text-muted mb-2">
                                                Data: <strong id="foco-rel-date"><?= e($initDateLabel) ?></strong>
                                                <span id="foco-rel-auditor-wrap"<?= !empty($initAuditor) ? '' : ' style="display:none;"' ?>>
                                                    • Auditor: <strong id="foco-rel-auditor"><?= e($initAuditor) ?></strong>
                                                </span>
                                                <span id="foco-rel-cargo-wrap"<?= (!empty($initCargo) && $initCargo !== '-') ? '' : ' style="display:none;"' ?>>
                                                    • Cargo: <strong id="foco-rel-cargo"><?= e($initCargo) ?></strong>
                                                </span>
                                            </div>
                                            <div class="ux-focus-text" id="foco-rel-text"><?= e($initText) ?></div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div><!-- /#visitas -->

                    <!-- ================= PRORROG ================= -->
                    <div class="tab-pane fade<?= $abaAtual === 'prorrog' ? ' show active' : '' ?>"
                        id="prorrog" role="tabpanel" aria-labelledby="prorrog-tab" tabindex="0">

                        <div class="card ov-card ov-int"
                            style="border-radius:14px;background:#fff;box-shadow:0 8px 24px rgba(0,0,0,.06);background-image:linear-gradient(to right, var(--ov, #5e2363) 6px, #fff 6px);">
                            <div class="card-body">
                                <div class="ov-head ov-head-space">
                                    <h6 class="ov-title mb-0">Prorrogações</h6>
                                    <?php if (!empty($pr_pendente_label)): ?>
                                        <a class="prorrog-pendente-badge"
                                            style="margin-left:auto !important;background:#ffe7ef !important;color:#b42346 !important;border:1px solid #e55353 !important;border-radius:999px !important;padding:6px 14px !important;display:inline-flex !important;align-items:center !important;text-decoration:none !important;box-shadow:0 1px 4px rgba(181,35,70,.12) !important;"
                                            href="<?= e($BASE_URL) ?>edit_internacao.php?id_internacao=<?= (int)$id_internacao ?>&section=prorrog#collapseProrrog">
                                            Período em aberto: <?= e($pr_pendente_label) ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <form method="get" action="<?= e($_SERVER['PHP_SELF']) ?>#prorrog" class="row g-2 align-items-end mb-3 js-period-form" data-start-field="pr_ini" data-end-field="pr_fim">
                                    <input type="hidden" name="id_internacao" value="<?= e($id_internacao) ?>">
                                    <input type="hidden" name="aba" value="prorrog">
                                    <div class="col-sm-4 col-md-3">
                                        <label class="form-label small text-muted">Início</label>
                                        <input type="date" name="pr_ini" value="<?= e($pr_ini ?? $pr_ini_raw) ?>" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-sm-4 col-md-3">
                                        <label class="form-label small text-muted">Fim</label>
                                        <input type="date" name="pr_fim" value="<?= e($pr_fim ?? $pr_fim_raw) ?>" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-12 col-md-auto">
                                        <div class="btn-group btn-group-sm" role="group" aria-label="Presets de período">
                                            <button type="button" class="btn btn-outline-secondary" data-period="today">Hoje</button>
                                            <button type="button" class="btn btn-outline-secondary" data-period="7d">7 dias</button>
                                            <button type="button" class="btn btn-outline-secondary" data-period="30d">30 dias</button>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <button class="btn btn-sm btn-primary" style="background:#5e2363;border-color:#5e2363;">Filtrar</button>
                                    </div>
                                    <div class="col-auto">
                                        <a class="btn btn-sm btn-outline-secondary" href="<?= e($visualizarInternacaoUrl . '?aba=prorrog') ?>#prorrog">Limpar</a>
                                    </div>
                                </form>
                                <div class="mb-2">
                                    <input type="search" class="form-control form-control-sm js-local-filter" data-target-table="table-prorrog"
                                        placeholder="Buscar nesta aba (acomodação, período, isolamento...)">
                                </div>

                                <?php if (!empty($pr_filtered)): ?>
                                    <div class="table-responsive ux-table-wrap">
                                        <table id="table-prorrog" class="table table-sm align-middle mb-2 ux-data-table">
                                            <tbody>
                                                <tr class="table-light text-uppercase small fw-semibold">
                                                    <td>Acomodação</td>
                                                    <td>Período</td>
                                                    <td class="text-center">Diárias</td>
                                                    <td class="text-center">Isolamento</td>
                                                </tr>
                                                <?php foreach ($pr_filtered as $p):
                                                    $acom = e(after_dash($p['acomod'] ?? '-'));
                                                    $ini  = fmtDate($p['ini'] ?? '');
                                                    $fim  = fmtDate($p['fim'] ?? '');
                                                    $periodo = ($ini !== '-' || $fim !== '-') ? ($ini . ' — ' . $fim) : '-';
                                                    $dias = (int)($p['diarias'] ?? 0);
                                                    $isoRaw = strtolower((string)($p['isolamento'] ?? $p['isol_1_pror'] ?? ''));
                                                    $iso = ($isoRaw === 's' || $isoRaw === 'sim' || $isoRaw === '1') ? 'Sim' : 'Não';
                                                ?>
                                                    <tr>
                                                        <td><?= $acom ?></td>
                                                        <td><?= $periodo ?></td>
                                                        <td class="text-center"><?= $dias ?></td>
                                                        <td class="text-center">
                                                            <?= $iso === 'Sim'
                                                                ? '<span class="badge rounded-pill text-bg-danger">Sim</span>'
                                                                : '<span class="badge rounded-pill text-bg-secondary">Não</span>' ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-end fw-semibold">Total de diárias <?= (int)$pr_total_diarias ?></div>
                                <?php else: ?>
                                    <div class="ux-empty-state">
                                        <div class="ux-empty-title">Nenhuma prorrogação<?= ($pr_ini || $pr_fim) ? ' no período selecionado' : ' registrada para esta internação' ?></div>
                                        <div class="ux-empty-text">Revise o período de filtro ou edite a internação para lançar prorrogações.</div>
                                        <div class="mt-2">
                                            <a class="btn btn-sm btn-outline-secondary" href="<?= e($editarProrrogUrl) ?>">
                                                <i class="fas fa-edit me-1"></i>Editar prorrogações
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ================= TUSS ================= -->
                    <div class="tab-pane fade<?= $abaAtual === 'tuss' ? ' show active' : '' ?>"
                        id="tuss" role="tabpanel" aria-labelledby="tuss-tab" tabindex="0">
                        <div class="card ov-card ov-int"
                            style="border-radius:14px;background:#fff;box-shadow:0 8px 24px rgba(0,0,0,.06);background-image:linear-gradient(to right, var(--ov, #5e2363) 6px, #fff 6px);">
                            <div class="card-body">
                                <div class="ov-head ov-head-space">
                                    <h6 class="ov-title mb-0">TUSS</h6>
                                    <a class="btn btn-sm btn-outline-secondary ms-auto"
                                        href="<?= e($BASE_URL) ?>edit_internacao.php?id_internacao=<?= (int)$id_internacao ?>&section=tuss#collapseTuss">
                                        <i class="fas fa-edit me-1"></i>Editar TUSS
                                    </a>
                                </div>

                                <form method="get" action="<?= e($_SERVER['PHP_SELF']) ?>#tuss" class="row g-2 align-items-end mb-3 js-period-form" data-start-field="tuss_ini" data-end-field="tuss_fim">
                                    <input type="hidden" name="id_internacao" value="<?= e($id_internacao) ?>">
                                    <input type="hidden" name="aba" value="tuss">
                                    <div class="col-sm-4 col-md-3">
                                        <label class="form-label small text-muted">Realização - Início</label>
                                        <input type="date" name="tuss_ini" value="<?= e($tuss_ini ?? $tuss_ini_raw) ?>" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-sm-4 col-md-3">
                                        <label class="form-label small text-muted">Realização - Fim</label>
                                        <input type="date" name="tuss_fim" value="<?= e($tuss_fim ?? $tuss_fim_raw) ?>" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-12 col-md-auto">
                                        <div class="btn-group btn-group-sm" role="group" aria-label="Presets de período">
                                            <button type="button" class="btn btn-outline-secondary" data-period="today">Hoje</button>
                                            <button type="button" class="btn btn-outline-secondary" data-period="7d">7 dias</button>
                                            <button type="button" class="btn btn-outline-secondary" data-period="30d">30 dias</button>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <button class="btn btn-sm btn-primary" style="background:#5e2363;border-color:#5e2363;">Filtrar</button>
                                    </div>
                                    <div class="col-auto">
                                        <a class="btn btn-sm btn-outline-secondary" href="<?= e($visualizarInternacaoUrl . '?aba=tuss') ?>#tuss">Limpar</a>
                                    </div>
                                </form>
                                <div class="mb-2">
                                    <input type="search" class="form-control form-control-sm js-local-filter" data-target-table="table-tuss"
                                        placeholder="Buscar nesta aba (código, terminologia, status...)">
                                </div>

                                <?php if (!empty($tuss_filtered)): ?>
                                    <div class="table-responsive ux-table-wrap">
                                        <table id="table-tuss" class="table table-sm align-middle mb-2 ux-data-table">
                                            <tbody>
                                                <tr class="table-light text-uppercase small fw-semibold">
                                                    <td style="min-width:110px;">Código</td>
                                                    <td>Terminologia</td>
                                                    <td style="min-width:120px;">Realização</td>
                                                    <td class="text-center" style="min-width:120px;">Solicitado</td>
                                                    <td class="text-center" style="min-width:120px;">Liberado</td>
                                                    <td class="text-center" style="min-width:110px;">Status</td>
                                                </tr>
                                                <?php foreach ($tuss_filtered as $t):
                                                    $cod = e($t['tuss_solicitado'] ?? '-');
                                                    $term = e($t['terminologia_tuss'] ?? '-');
                                                    $dt = fmtDateAny($t['data_realizacao_tuss'] ?? '');
                                                    $qsol = (int)($t['qtd_tuss_solicitado'] ?? 0);
                                                    $qlib = (int)($t['qtd_tuss_liberado'] ?? 0);
                                                    $libRaw = strtolower((string)($t['tuss_liberado_sn'] ?? ''));
                                                    $status = ($libRaw === 's' || $libRaw === 'sim' || $libRaw === '1') ? 'Liberado' : 'Pendente';
                                                    $badge = ($status === 'Liberado') ? 'text-bg-success' : 'text-bg-secondary';
                                                ?>
                                                    <tr>
                                                        <td class="fw-semibold"><?= $cod ?></td>
                                                        <td><?= $term ?></td>
                                                        <td><?= $dt ?></td>
                                                        <td class="text-center"><?= $qsol ?></td>
                                                        <td class="text-center"><?= $qlib ?></td>
                                                        <td class="text-center"><span class="badge rounded-pill <?= $badge ?>"><?= $status ?></span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="d-flex justify-content-end gap-3">
                                        <div><span class="text-muted">Total solicitado:</span> <strong><?= (int)$tuss_tot_solic ?></strong></div>
                                        <div><span class="text-muted">Total liberado:</span> <strong><?= (int)$tuss_tot_lib ?></strong></div>
                                    </div>
                                <?php else: ?>
                                    <div class="ux-empty-state">
                                        <div class="ux-empty-title">Nenhum item TUSS<?= ($tuss_ini || $tuss_fim) ? ' no período selecionado' : ' para esta internação' ?></div>
                                        <div class="ux-empty-text">Ajuste o período ou edite a internação para cadastrar itens TUSS.</div>
                                        <div class="mt-2">
                                            <a class="btn btn-sm btn-outline-secondary" href="<?= e($editarTussUrl) ?>">
                                                <i class="fas fa-edit me-1"></i>Editar TUSS
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ================= NEG ================= -->
                    <div class="tab-pane fade<?= $abaAtual === 'neg' ? ' show active' : '' ?>"
                        id="neg" role="tabpanel" aria-labelledby="neg-tab" tabindex="0">

                        <div class="card ov-card ov-int"
                            style="border-radius:14px;background:#fff;box-shadow:0 8px 24px rgba(0,0,0,.06);background-image:linear-gradient(to right, var(--ov, #5e2363) 6px, #fff 6px);">
                            <div class="card-body">
                                <div class="ov-head ov-head-space">
                                    <h6 class="ov-title mb-0">Negociações</h6>
                                    <a class="btn btn-sm btn-outline-secondary ms-auto"
                                        href="<?= e($BASE_URL) ?>edit_internacao.php?id_internacao=<?= (int)$id_internacao ?>&section=negoc#collapseNegoc">
                                        <i class="fas fa-edit me-1"></i>Editar Negociações
                                    </a>
                                </div>

                                <form method="get" action="<?= e($_SERVER['PHP_SELF']) ?>#neg" class="row g-2 align-items-end mb-3 js-period-form" data-start-field="neg_ini" data-end-field="neg_fim">
                                    <input type="hidden" name="id_internacao" value="<?= e($id_internacao) ?>">
                                    <input type="hidden" name="aba" value="neg">
                                    <div class="col-sm-4 col-md-3">
                                        <label class="form-label small text-muted">Início</label>
                                        <input type="date" name="neg_ini" value="<?= e($neg_ini ?? $neg_ini_raw) ?>" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-sm-4 col-md-3">
                                        <label class="form-label small text-muted">Fim</label>
                                        <input type="date" name="neg_fim" value="<?= e($neg_fim ?? $neg_fim_raw) ?>" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-12 col-md-auto">
                                        <div class="btn-group btn-group-sm" role="group" aria-label="Presets de período">
                                            <button type="button" class="btn btn-outline-secondary" data-period="today">Hoje</button>
                                            <button type="button" class="btn btn-outline-secondary" data-period="7d">7 dias</button>
                                            <button type="button" class="btn btn-outline-secondary" data-period="30d">30 dias</button>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <button class="btn btn-sm btn-primary" style="background:#5e2363;border-color:#5e2363;">Filtrar</button>
                                    </div>
                                    <div class="col-auto">
                                        <a class="btn btn-sm btn-outline-secondary" href="<?= e($visualizarInternacaoUrl . '?aba=neg') ?>#neg">Limpar</a>
                                    </div>
                                </form>
                                <div class="mb-2">
                                    <input type="search" class="form-control form-control-sm js-local-filter" data-target-table="table-neg"
                                        placeholder="Buscar nesta aba (tipo, troca, período, saving...)">
                                </div>

                                <?php if (!empty($neg_filtered)): ?>
                                    <div class="table-responsive ux-table-wrap">
                                        <table id="table-neg" class="table table-sm align-middle mb-2 ux-data-table">
                                            <tbody>
                                                <tr class="table-light text-uppercase small fw-semibold">
                                                    <td style="min-width:140px;">Tipo</td>
                                                    <td>Troca</td>
                                                    <td class="text-center" style="min-width:90px;">Qtd</td>
                                                    <td class="text-center" style="min-width:110px;">Saving</td>
                                                    <td style="min-width:190px;">Período</td>
                                                    <td style="min-width:150px;">Atualizado</td>
                                                </tr>
                                                <?php foreach ($neg_filtered as $n):
                                                    $tipo = e($n['tipo_negociacao'] ?? '-');
                                                    $de   = e(after_dash($n['troca_de'] ?? '-'));
                                                    $para = e(after_dash($n['troca_para'] ?? '-'));
                                                    $qtd = e($n['qtd'] ?? '-');
                                                    $saving = e($n['saving'] ?? '-');
                                                    $ini = fmtDateAny($n['data_inicio_neg'] ?? '');
                                                    $fim = fmtDateAny($n['data_fim_neg'] ?? '');
                                                    $periodo = ($ini !== '-' || $fim !== '-') ? ($ini . ' — ' . $fim) : '-';
                                                    $upd = e($n['updated_at'] ?? '');
                                                    $updFmt = ($upd) ? date('d/m/Y H:i', strtotime($upd)) : '-';
                                                ?>
                                                    <tr>
                                                        <td class="fw-semibold"><?= $tipo ?></td>
                                                        <td><?= $de ?> <i class="fas fa-exchange-alt mx-1 text-muted"></i> <?= $para ?></td>
                                                        <td class="text-center"><?= $qtd ?></td>
                                                        <td class="text-center"><?= $saving ?></td>
                                                        <td><?= $periodo ?></td>
                                                        <td><?= $updFmt ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="ux-empty-state">
                                        <div class="ux-empty-title">Nenhuma negociação<?= ($neg_ini || $neg_fim) ? ' no período selecionado' : ' para esta internação' ?></div>
                                        <div class="ux-empty-text">Você pode ajustar o filtro ou lançar uma negociação na edição da internação.</div>
                                        <div class="mt-2">
                                            <a class="btn btn-sm btn-outline-secondary" href="<?= e($editarNegocUrl) ?>">
                                                <i class="fas fa-edit me-1"></i>Editar negociações
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div><!-- /.tab-content -->

            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="small text-muted">Atualizado: <?= e(date('d/m/Y H:i')) ?></div>
        </div>

    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var tabMap = {
            resumo: 'resumo',
            visitas: 'visitas',
            prorrog: 'prorrog',
            tuss: 'tuss',
            neg: 'neg'
        };
        document.querySelectorAll('#internTabs button[data-bs-toggle="pill"]').forEach(function(tabBtn) {
            tabBtn.addEventListener('shown.bs.tab', function(ev) {
                var target = (ev.target.getAttribute('data-bs-target') || '').replace('#', '');
                var aba = tabMap[target] || target;
                if (!aba) return;
                var y = window.scrollY || window.pageYOffset || 0;
                var params = new URLSearchParams(window.location.search);
                params.set('aba', aba);
                var query = params.toString();
                var nextUrl = window.location.pathname + (query ? ('?' + query) : '') + '#' + target;
                history.replaceState(null, '', nextUrl);
                window.scrollTo(0, y);
            });
        });

        function formatYmd(dateObj) {
            var y = dateObj.getFullYear();
            var m = String(dateObj.getMonth() + 1).padStart(2, '0');
            var d = String(dateObj.getDate()).padStart(2, '0');
            return y + '-' + m + '-' + d;
        }

        document.querySelectorAll('.js-period-form').forEach(function(form) {
            form.addEventListener('click', function(ev) {
                var btn = ev.target.closest('button[data-period]');
                if (!btn) return;
                ev.preventDefault();
                var startField = form.getAttribute('data-start-field');
                var endField = form.getAttribute('data-end-field');
                if (!startField || !endField) return;

                var ini = form.querySelector('input[name="' + startField + '"]');
                var fim = form.querySelector('input[name="' + endField + '"]');
                if (!ini || !fim) return;

                var now = new Date();
                now.setHours(0, 0, 0, 0);
                var start = new Date(now.getTime());
                var period = btn.getAttribute('data-period');

                if (period === 'today') {
                    // mantém start = hoje
                } else if (period === '7d') {
                    start.setDate(start.getDate() - 6);
                } else if (period === '30d') {
                    start.setDate(start.getDate() - 29);
                } else {
                    return;
                }

                ini.value = formatYmd(start);
                fim.value = formatYmd(now);
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            });
        });

        document.querySelectorAll('.js-local-filter').forEach(function(input) {
            input.addEventListener('input', function() {
                var tableId = input.getAttribute('data-target-table');
                if (!tableId) return;
                var table = document.getElementById(tableId);
                if (!table) return;
                var q = (input.value || '').toLowerCase().trim();
                table.querySelectorAll('tbody tr:not(.table-light)').forEach(function(row) {
                    var txt = (row.textContent || '').toLowerCase();
                    row.style.display = (!q || txt.indexOf(q) !== -1) ? '' : 'none';
                });
            });
        });
    });
</script>

<?php if (!empty($_GET['recent_limit']) || !empty($_GET['recent_order'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // garante que ao aplicar filtros você caia na aba certa
            location.hash = 'visitas';
        });
    </script>
<?php endif; ?>

<?php if (!empty($visitas_norm)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var totalVisitas = <?= (int)$countVis ?>;
            var deleteBtn = document.getElementById('btn-visita-delete-main');
            var modal = document.getElementById('modalDeleteVisitaInternacao');
            var confirmBtn = modal ? modal.querySelector('[data-action="confirm-delete"]') : null;
            var feedback = modal ? modal.querySelector('.js-delete-feedback') : null;
            var currentId = <?= $initId ? (int)$initId : 'null' ?>;
            var currentRet = <?= $activeVisitRet ? 'true' : 'false' ?>;
            var redirectUrl = <?= json_encode($visualizarInternacaoUrl . '#visitas') ?>;
            var rangeBtn = document.getElementById('btn-visitas-range-pdf');
            var rangeInfo = document.getElementById('btn-visitas-range-info');
            var rangeSel = document.getElementById('vis-periodo-selecionado');
            var rangeSpan = document.getElementById('vis-periodo-range');
            var visIni = document.getElementById('vis_ini');
            var visFim = document.getElementById('vis_fim');
            var visitRows = Array.prototype.slice.call(document.querySelectorAll('#visitas .js-visita-select'));
            var emptyRow = document.getElementById('visitas-empty-row');

            function updateDeleteBtn() {
                var disabled = !currentId || totalVisitas <= 1 || currentRet;
                if (!deleteBtn) return disabled;
                deleteBtn.disabled = disabled;
                deleteBtn.classList.toggle('disabled', disabled);
                deleteBtn.setAttribute('aria-disabled', disabled ? 'true' : 'false');
                if (currentId) deleteBtn.setAttribute('data-delete-visita', currentId);
                return disabled;
            }

            window.updateVisitaDeleteTarget = function(id, retFlag) {
                currentId = id ? parseInt(id, 10) : null;
                currentRet = (retFlag === true || retFlag === '1' || retFlag === 1);
                updateDeleteBtn();
            };

            window.promptDeleteVisitaFromTable = function(id, retFlag) {
                if (!id) return;
                currentId = parseInt(id, 10);
                currentRet = (retFlag === true || retFlag === '1' || retFlag === 1);
                updateDeleteBtn();
                if (deleteBtn && deleteBtn.disabled) return;
                var targetRow = document.querySelector('#visitas .js-visita-select[data-id="' + currentId + '"]');
                if (targetRow) {
                    window.selectVisitaEntry(targetRow);
                }
                if (modal && window.bootstrap && window.bootstrap.Modal) {
                    var instance = bootstrap.Modal.getInstance(modal) || new bootstrap.Modal(modal);
                    instance.show();
                }
            };

            updateDeleteBtn();

            function formatDateBr(value) {
                if (!value) return '';
                var parts = value.split('-');
                if (parts.length === 3) {
                    return parts[2] + '/' + parts[1] + '/' + parts[0];
                }
                return value;
            }

            function updateVisitPdfButton(id, dateLabel) {
                var pdfBtn = document.getElementById('btn-visita-pdf');
                if (!pdfBtn) return;
                var base = pdfBtn.getAttribute('data-pdf-base') || '';
                if (id && base) {
                    pdfBtn.href = base + encodeURIComponent(id);
                    pdfBtn.classList.remove('disabled');
                    pdfBtn.classList.remove('btn-outline-secondary');
                    pdfBtn.classList.add('btn-success');
                    pdfBtn.setAttribute('aria-disabled', 'false');
                } else {
                    pdfBtn.href = '#';
                    pdfBtn.classList.add('disabled');
                    pdfBtn.classList.remove('btn-success');
                    pdfBtn.classList.add('btn-outline-secondary');
                    pdfBtn.setAttribute('aria-disabled', 'true');
                }

                var pdfDate = document.getElementById('btn-visita-date');
                if (!pdfDate) return;
                if (id && dateLabel) {
                    pdfDate.textContent = 'Data: ' + dateLabel;
                    pdfDate.classList.remove('text-muted');
                } else {
                    pdfDate.textContent = 'Selecione uma visita';
                    pdfDate.classList.add('text-muted');
                }
            }

            function setVisitDetails(visit) {
                var dateLabel = visit && visit.date ? visit.date : '—';
                var textValue = visit && visit.text ? visit.text : '—';
                var idValue = visit && visit.id ? visit.id : '';
                var auditorValue = visit && visit.auditor ? visit.auditor : '';
                var cargoValue = visit && visit.cargo ? visit.cargo : '';

                var dEl = document.getElementById('v-rel-date');
                var xEl = document.getElementById('v-rel-text');
                var iWrap = document.getElementById('v-rel-id-wrap');
                var iEl = document.getElementById('v-rel-id');
                var audEl = document.getElementById('v-rel-auditor');
                var audWrap = document.getElementById('v-rel-auditor-wrap');
                var cargoEl = document.getElementById('v-rel-cargo');
                var cargoWrap = document.getElementById('v-rel-cargo-wrap');
                var focoDate = document.getElementById('foco-rel-date');
                var focoText = document.getElementById('foco-rel-text');
                var focoAud = document.getElementById('foco-rel-auditor');
                var focoAudWrap = document.getElementById('foco-rel-auditor-wrap');
                var focoCargo = document.getElementById('foco-rel-cargo');
                var focoCargoWrap = document.getElementById('foco-rel-cargo-wrap');

                if (dEl) dEl.textContent = dateLabel;
                if (xEl) xEl.textContent = textValue;
                if (iEl) iEl.textContent = idValue || '';
                if (iWrap) {
                    if (idValue) iWrap.classList.remove('d-none');
                    else iWrap.classList.add('d-none');
                }
                if (audEl) audEl.textContent = auditorValue;
                if (audWrap) audWrap.style.display = auditorValue ? 'block' : 'none';
                if (cargoEl) cargoEl.textContent = cargoValue;
                if (cargoWrap) cargoWrap.style.display = (cargoValue && cargoValue !== '-') ? 'block' : 'none';
                if (focoDate) focoDate.textContent = dateLabel;
                if (focoText) focoText.textContent = textValue;
                if (focoAud) focoAud.textContent = auditorValue;
                if (focoAudWrap) focoAudWrap.style.display = auditorValue ? '' : 'none';
                if (focoCargo) focoCargo.textContent = cargoValue;
                if (focoCargoWrap) focoCargoWrap.style.display = (cargoValue && cargoValue !== '-') ? '' : 'none';

                updateVisitPdfButton(idValue, dateLabel);
            }

            function clearVisitSelection() {
                visitRows.forEach(function(row) {
                    row.classList.remove('active');
                });
                currentId = null;
                currentRet = false;
                if (window.updateVisitaDeleteTarget) window.updateVisitaDeleteTarget(null, false);
                setVisitDetails({
                    date: 'Nenhuma visita no período',
                    text: 'Nenhuma visita encontrada para o período selecionado.',
                    id: '',
                    auditor: '',
                    cargo: ''
                });
            }

            window.selectVisitaEntry = function(row) {
                if (!row) return false;

                visitRows.forEach(function(item) {
                    item.classList.remove('active');
                });
                row.classList.add('active');

                var visit = {
                    id: row.dataset.id || '',
                    date: row.dataset.date || '—',
                    text: row.dataset.text || '—',
                    auditor: row.dataset.auditor || '',
                    cargo: row.dataset.cargo || '',
                    retificado: row.dataset.retificado || '0'
                };

                currentId = visit.id ? parseInt(visit.id, 10) : null;
                currentRet = (visit.retificado === '1' || visit.retificado === 1 || visit.retificado === true);
                if (window.updateVisitaDeleteTarget) window.updateVisitaDeleteTarget(visit.id, visit.retificado);
                setVisitDetails(visit);
                return false;
            };

            function filterVisitRows() {
                if (!visitRows.length) return;

                var iniVal = visIni ? visIni.value : '';
                var fimVal = visFim ? visFim.value : '';
                var visibleRows = [];

                visitRows.forEach(function(row) {
                    var dateRaw = row.dataset.dateraw || '';
                    var visible = true;
                    if (iniVal && dateRaw < iniVal) visible = false;
                    if (fimVal && dateRaw > fimVal) visible = false;
                    row.style.display = visible ? '' : 'none';
                    if (visible) visibleRows.push(row);
                });

                if (emptyRow) {
                    emptyRow.style.display = visibleRows.length ? 'none' : '';
                }

                var activeVisible = visitRows.find(function(row) {
                    return row.classList.contains('active') && row.style.display !== 'none';
                });

                if (activeVisible) {
                    setVisitDetails({
                        id: activeVisible.dataset.id || '',
                        date: activeVisible.dataset.date || '—',
                        text: activeVisible.dataset.text || '—',
                        auditor: activeVisible.dataset.auditor || '',
                        cargo: activeVisible.dataset.cargo || ''
                    });
                    return;
                }

                if (visibleRows.length) {
                    window.selectVisitaEntry(visibleRows[visibleRows.length - 1]);
                } else {
                    clearVisitSelection();
                }
            }

            function updateRangeBtn() {
                if (!rangeBtn || !visIni || !visFim) return;
                var iniVal = visIni.value;
                var fimVal = visFim.value;
                var hasRange = !!(iniVal && fimVal);
                var base = rangeBtn.getAttribute('data-base') || '';

                if (hasRange) {
                    rangeBtn.classList.remove('disabled');
                    rangeBtn.setAttribute('aria-disabled', 'false');
                    rangeBtn.href = base + '&data_ini=' + encodeURIComponent(iniVal) + '&data_fim=' + encodeURIComponent(fimVal);
                    if (rangeInfo) {
                        rangeInfo.textContent = 'Período: ' + formatDateBr(iniVal) + ' — ' + formatDateBr(fimVal);
                        rangeInfo.classList.remove('text-muted');
                    }
                    if (rangeSel && rangeSpan) {
                        rangeSpan.textContent = formatDateBr(iniVal) + ' — ' + formatDateBr(fimVal);
                        rangeSel.style.display = 'block';
                    }
                } else {
                    rangeBtn.classList.add('disabled');
                    rangeBtn.setAttribute('aria-disabled', 'true');
                    rangeBtn.href = '#';
                    if (rangeInfo) {
                        rangeInfo.textContent = 'Use o filtro de datas';
                        rangeInfo.classList.add('text-muted');
                    }
                    if (rangeSel) {
                        rangeSel.style.display = 'none';
                    }
                }

                filterVisitRows();
            }

            if (rangeBtn) {
                rangeBtn.addEventListener('click', function(evt) {
                    if (rangeBtn.classList.contains('disabled')) {
                        evt.preventDefault();
                    }
                });
            }
            if (visIni) {
                visIni.addEventListener('change', updateRangeBtn);
                visIni.addEventListener('input', updateRangeBtn);
            }
            if (visFim) {
                visFim.addEventListener('change', updateRangeBtn);
                visFim.addEventListener('input', updateRangeBtn);
            }
            var btnAplicar = document.getElementById('btnAplicarVisitas');
            var btnLimpar = document.getElementById('btnLimparVisitas');
            if (btnAplicar) btnAplicar.addEventListener('click', updateRangeBtn);
            if (btnLimpar) {
                btnLimpar.addEventListener('click', function() {
                    if (visIni) visIni.value = visIni.getAttribute('data-default') || '';
                    if (visFim) visFim.value = visFim.getAttribute('data-default') || '';
                    updateRangeBtn();
                });
            }
            updateRangeBtn();

            if (!modal || !confirmBtn) return;

            modal.addEventListener('show.bs.modal', function(event) {
                if (updateDeleteBtn()) {
                    event.preventDefault();
                    return;
                }
                if (feedback) {
                    feedback.classList.add('d-none');
                    feedback.textContent = '';
                }
            });

            confirmBtn.addEventListener('click', function() {
                if (!currentId) return;
                confirmBtn.disabled = true;

                if (feedback) {
                    feedback.classList.add('d-none');
                    feedback.textContent = '';
                }

                var formData = new FormData();
                formData.append('type', 'delete');
                formData.append('id_visita', currentId);
                formData.append('redirect', redirectUrl);
                formData.append('ajax', '1');

                fetch('process_visita.php', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(function(resp) {
                        return resp.json();
                    })
                    .then(function(res) {
                        if (res && res.success) {
                            var target = res.redirect || redirectUrl || window.location.href;
                            try {
                                var absolute = new URL(target, window.location.origin).href;
                                if (absolute === window.location.href) window.location.reload();
                                else window.location.href = target;
                            } catch (err) {
                                window.location.reload();
                            }
                            return;
                        }
                        var msg = (res && res.message) ? res.message : 'Não foi possível remover a visita.';
                        if (feedback) {
                            feedback.textContent = msg;
                            feedback.classList.remove('d-none');
                        } else {
                            alert(msg);
                        }
                    })
                    .catch(function() {
                        if (feedback) {
                            feedback.textContent = 'Falha inesperada ao remover a visita.';
                            feedback.classList.remove('d-none');
                        } else {
                            alert('Falha inesperada ao remover a visita.');
                        }
                    })
                    .finally(function() {
                        confirmBtn.disabled = false;
                    });
            });
        });
    </script>
<?php endif; ?>

<style>
    :root {
        --brand: #5e2363;
        --brand-700: #4b1c50;
        --brand-800: #431945;
        --brand-100: #f2e8f7;
        --brand-050: #f9f3fc;
        --teal: #0f766e;
        --teal-100: #d1fae5;
        --padX: 56px;
    }

    .v2-avatar {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: #ecd5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        color: #5e2363
    }

    .ux-summary-strip {
        display: grid;
        grid-template-columns: repeat(4, minmax(180px, 1fr));
        gap: 10px;
    }

    .ux-summary-chip {
        border: 1px solid #eadcf3;
        background: #fbf7fe;
        border-radius: 12px;
        padding: 8px 10px;
        display: flex;
        align-items: baseline;
        gap: 8px;
        flex-wrap: wrap;
    }

    .ux-chip-label {
        font-size: .75rem;
        letter-spacing: .02em;
        color: #6b7280;
        text-transform: uppercase;
    }

    .ux-chip-value {
        color: #5e2363;
        font-size: .95rem;
        font-weight: 700;
    }

    .ux-chip-sub {
        color: #6b7280;
        font-size: .8rem;
    }

    .ux-priority-chip .ux-chip-value {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .ux-priority-chip.is-normal {
        background: #ecfdf3;
        border-color: #b7ebcd;
    }
    .ux-priority-chip.is-normal .ux-chip-value {
        color: #0f7a3e;
    }

    .ux-priority-chip.is-warning {
        background: #fff8e8;
        border-color: #f2d8a3;
    }
    .ux-priority-chip.is-warning .ux-chip-value {
        color: #9a5a00;
    }

    .ux-priority-chip.is-critical {
        background: #ffecef;
        border-color: #f0b9c2;
    }
    .ux-priority-chip.is-critical .ux-chip-value {
        color: #b42346;
    }

    .ux-tab-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 22px;
        height: 20px;
        border-radius: 999px;
        padding: 0 6px;
        margin-left: 8px;
        font-size: 0.72rem;
        font-weight: 700;
        border: 1px solid #ddcfe8;
        background: #f5edf9;
        color: #5e2363;
        vertical-align: middle;
    }

    .nav-link.active .ux-tab-count {
        background: rgba(255, 255, 255, 0.22);
        border-color: rgba(255, 255, 255, 0.4);
        color: #fff;
    }

    .ux-actions-sticky {
        position: sticky;
        top: 68px;
        z-index: 10;
        background: #fff;
        border: 1px solid #ece7f1;
        border-radius: 12px;
        padding: 10px;
    }

    .ov-card .ov-head {
        display: flex;
        align-items: center;
        gap: .5rem;
        margin-bottom: .5rem
    }

    #main-container .ov-card {
        border: 1px solid #ede7f3;
        border-radius: 14px;
    }

    #main-container .ov-card .card-body {
        padding: 1rem 1.1rem;
    }

    #main-container .ov-title {
        font-size: 1rem;
        font-weight: 700;
        color: #2b2f36;
    }

    #main-container .btn-sm {
        min-height: 34px;
        padding: 0.33rem 0.78rem;
        font-weight: 600;
        line-height: 1.2;
    }

    #main-container .form-label.small {
        font-size: .78rem;
        font-weight: 600;
        color: #667085 !important;
        margin-bottom: 0.25rem;
    }

    .ux-table-wrap {
        border: 1px solid #ece7f1;
        border-radius: 12px;
        overflow: auto;
        background: #fff;
    }

    .ux-data-table {
        margin-bottom: 0 !important;
    }

    .ux-data-table tbody tr.table-light td {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #f6f2fa;
        border-bottom: 1px solid #e7dff0;
    }

    .ux-data-table tbody tr:not(.table-light):nth-child(odd) td {
        background: #fcfbfe;
    }

    .ux-data-table tbody tr:not(.table-light):hover td {
        background: #f4effa;
    }

    .ux-data-table td {
        padding-top: 0.58rem;
        padding-bottom: 0.58rem;
        vertical-align: middle;
    }

    .ux-empty-state {
        border: 1px dashed #d9cbe7;
        background: #faf7fd;
        border-radius: 12px;
        padding: 14px 16px;
    }

    .ux-empty-title {
        font-weight: 700;
        color: #43395b;
        margin-bottom: 2px;
    }

    .ux-empty-text {
        color: #6f6790;
        font-size: .92rem;
    }

    .ux-focus-text {
        border: 1px solid #e8e1f0;
        background: #fcfbfe;
        border-radius: 12px;
        padding: 14px;
        white-space: pre-wrap;
        max-height: 60vh;
        overflow: auto;
        line-height: 1.45;
    }
    .ov-head-space {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
    }

    .prorrog-pendente-badge {
        margin-left: auto;
        background: #ffe7ef !important;
        color: #b42346 !important;
        border: 1px solid #e55353 !important;
        border-radius: 999px;
        padding: 6px 14px;
        font-weight: 600;
        font-size: 0.85rem;
        white-space: nowrap;
        text-decoration: none !important;
        display: inline-flex !important;
        align-items: center;
        box-shadow: 0 1px 4px rgba(181, 35, 70, 0.12);
    }
    a.prorrog-pendente-badge,
    a.prorrog-pendente-badge:visited,
    a.prorrog-pendente-badge:active,
    a.prorrog-pendente-badge:focus {
        color: #b42346 !important;
        text-decoration: none !important;
    }
    .prorrog-pendente-badge:hover {
        background: #ffd9e6 !important;
        color: #8f1f3b !important;
    }
    @media (max-width: 991.98px) {
        .ov-head-space {
            flex-wrap: wrap;
        }
        .prorrog-pendente-badge {
            margin-left: 0;
            margin-top: 8px;
        }
    }

    .ov-card .ov-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--ov-accent-100, var(--brand-100));
        color: var(--ov-accent, var(--brand))
    }

    .ov-card.ov-int {
        --ov-accent: var(--brand);
        --ov-accent-100: var(--brand-100)
    }

    .ov-card.ov-vis {
        --ov-accent: var(--teal);
        --ov-accent-100: var(--teal-100)
    }

    .btn-ghost-brand {
        color: var(--brand);
        background: var(--brand-050);
        border: 1px solid #eadcf3
    }

    .btn-ghost-brand:hover {
        background: var(--brand-100);
        color: var(--brand-800)
    }

    .ux-recent-wrap {
        border: 1px solid #dbe5f1;
        border-left: 5px solid #1d8fe1;
        border-radius: 16px;
        background: linear-gradient(180deg, #fbfcff 0%, #f4f8ff 100%);
        box-shadow: 0 8px 24px rgba(17, 24, 39, 0.06);
        padding: 14px 16px;
    }

    .ux-recent-header {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 14px;
    }

    .ux-recent-title {
        text-transform: uppercase;
        font-size: .86rem;
        font-weight: 700;
        letter-spacing: .04em;
        color: #64707d;
    }

    .ux-recent-item {
        border: 1px solid #e3e6ee;
        border-left: 4px solid #1d8fe1;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 4px 16px rgba(17, 24, 39, 0.04);
        padding: 16px 18px;
    }

    .ux-recent-meta {
        display: grid;
        grid-template-columns: repeat(3, minmax(140px, 1fr));
        gap: 8px 12px;
        align-items: start;
    }

    .ux-recent-meta-block {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    }

    .ux-recent-meta-label {
        font-size: .72rem;
        line-height: 1;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #8a94a6;
        font-weight: 700;
    }

    .ux-recent-meta-value {
        color: #3f4756;
        font-weight: 600;
        line-height: 1.25;
    }

    .ux-recent-meta-id {
        display: inline-flex;
        align-items: center;
        width: fit-content;
        padding: 2px 10px;
        border-radius: 999px;
        border: 1px solid #e5e7eb;
        background: #f8fafc;
        color: #4b5563;
        font-size: .82rem;
        font-weight: 700;
    }

    .ux-recent-date-chip {
        display: inline-block;
        width: fit-content;
        padding: 3px 11px;
        border-radius: 999px;
        background: #f2e8f7;
        color: #4b1c50;
        font-weight: 700;
    }

    .ux-recent-evolucao {
        border: 1px solid #e7e9f0;
        border-radius: 10px;
        background: #fdfefe;
        padding: 12px 14px;
        line-height: 1.55;
    }

    .btn-ultimas-visitas {
        border: 2px solid #c62828;
        color: #c62828;
        background-color: #fff;
        font-weight: 600
    }

    .btn-ultimas-visitas:hover,
    .btn-ultimas-visitas:focus {
        background-color: #ffeceb;
        color: #a11212
    }

    .btn-visitas-row .btn {
        min-width: 170px;
        height: 52px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 0.4rem 0.9rem;
        white-space: nowrap;
    }

    .btn-visitas-row .btn span {
        line-height: 1.1;
    }

    .btn-visitas-row .btn.btn-danger {
        background-color: #dc3545;
        border-color: #dc3545;
        color: #fff;
        font-weight: 600;
    }

    .btn-visitas-row .btn.btn-danger:hover,
    .btn-visitas-row .btn.btn-danger:focus {
        background-color: #c82333;
        border-color: #bd2130;
        color: #fff;
    }

    .modal-ultimas-visitas .modal-dialog {
        max-width: 95vw
    }

    .modal-ultimas-visitas .modal-content {
        border-radius: 18px
    }

    .modal-ultimas-visitas .modal-body {
        max-height: 75vh;
        overflow-y: auto;
        padding: 1.5rem 1.75rem
    }

    .visita-list {
        display: flex;
        flex-direction: column;
        gap: 1rem
    }

    .visita-item {
        border: 1px solid #eee;
        border-radius: 14px;
        padding: 1rem 1.25rem;
        box-shadow: 0 4px 18px rgba(0, 0, 0, 0.05);
        background: #fff
    }

    @media (max-width: 992px) {
        .ux-summary-strip {
            grid-template-columns: repeat(2, minmax(140px, 1fr));
        }
        .ux-recent-meta {
            grid-template-columns: repeat(2, minmax(140px, 1fr));
        }
    }

    @media (max-width: 576px) {
        .ux-summary-strip {
            grid-template-columns: 1fr;
        }
        .ux-recent-meta {
            grid-template-columns: 1fr;
        }
        .ux-actions-sticky {
            top: 58px;
        }
        .ux-recent-wrap {
            padding: 12px;
        }
        .ux-recent-controls {
            width: 100%;
        }
    }

    .visitas-table-wrap {
        border: 1px solid #ece7f1;
        border-radius: 14px;
        overflow: hidden;
        background: #fff;
    }

    .visitas-table {
        margin-bottom: 0;
    }

    .visitas-table thead th {
        background: #e7d9f2;
        color: #4b1c50;
        font-size: .8rem;
        font-weight: 700;
        letter-spacing: .01em;
        text-transform: uppercase;
        border-bottom: 1px solid #c9afd9;
        white-space: nowrap;
        padding: .82rem 1rem;
        vertical-align: middle;
    }

    .visitas-table tbody tr {
        cursor: pointer;
        transition: background-color .15s ease, box-shadow .15s ease;
    }

    .visitas-table tbody tr td {
        padding-top: .62rem;
        padding-bottom: .62rem;
        padding-left: 1rem;
        padding-right: 1rem;
        border-color: #f0eaf5;
        border-right: 0;
        border-left: 0;
        vertical-align: middle;
    }

    .visitas-table tbody tr:hover td,
    .visitas-table tbody tr:focus td {
        background: #faf6fd;
    }

    .visitas-table tbody tr.active td {
        background: #f3ebf9;
        border-top-color: #e4d6ef;
        border-bottom-color: #e4d6ef;
    }

    .visitas-table tbody tr.active td:first-child {
        box-shadow: inset 4px 0 0 #5e2363;
    }

    .visitas-table th:first-child,
    .visitas-table td:first-child {
        width: 140px;
    }

    .visitas-table th:nth-child(2),
    .visitas-table td:nth-child(2) {
        width: 30%;
    }

    .visitas-table th:nth-child(3),
    .visitas-table td:nth-child(3) {
        width: 22%;
    }

    .visitas-table th:nth-child(4),
    .visitas-table td:nth-child(4) {
        width: 18%;
    }

    .visitas-table th:nth-child(5),
    .visitas-table td:nth-child(5) {
        width: 250px;
    }

    .visita-id-cell {
        white-space: nowrap;
    }

    .visita-actions-cell {
        white-space: nowrap;
        text-align: center;
    }

    .visita-actions {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .4rem;
        flex-wrap: wrap;
    }

    .visita-actions .btn {
        min-height: 30px;
        padding: .28rem .6rem;
        line-height: 1.1;
    }

    .visita-actions-head {
        text-align: center;
    }
</style>

<?php require_once("templates/footer.php"); ?>

</html>
