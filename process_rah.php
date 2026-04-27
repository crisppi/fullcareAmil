<?php
/**
 * process_rah.php (refeito)
 * - Regras de negócio das flags calculadas no backend (fonte da verdade)
 * - Persiste FKs/flags (médico, enfermeiro, adm) em tb_capeante via DAO
 * - Tabelas acessórias recebem apenas valores (sem FKs de profissionais)
 */

declare(strict_types=1);

if (!defined("FLOW_LOGGER_AUTO_V1")) {
    define("FLOW_LOGGER_AUTO_V1", 1);
    @require_once(__DIR__ . "/utils/flow_logger.php");
    if (function_exists("flowLogStart") && function_exists("flowLog")) {
        $__flowCtxAuto = flowLogStart(basename(__FILE__, ".php"), [
            "type" => $_POST["type"] ?? $_GET["type"] ?? null,
            "method" => $_SERVER["REQUEST_METHOD"] ?? null,
        ]);
        register_shutdown_function(function () use ($__flowCtxAuto) {
            $err = error_get_last();
            if ($err && in_array(($err["type"] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                flowLog($__flowCtxAuto, "shutdown.fatal", "ERROR", [
                    "message" => $err["message"] ?? null,
                    "file" => $err["file"] ?? null,
                    "line" => $err["line"] ?? null,
                ]);
            }
            flowLog($__flowCtxAuto, "request.finish", "INFO");
        });
    }
}

require_once "globals.php";
require_once "db.php";

require_once "models/capeante.php";
require_once "dao/capeanteDao.php";
require_once "dao/CapValoresDao.php";

require_once "models/message.php";
require_once "dao/usuarioDao.php";
require_once "utils/audit_logger.php";

$message     = new Message($BASE_URL);
$capeanteDao = new capeanteDAO($conn, $BASE_URL);
$capValoresDao = new CapValoresDAO($conn);

$type = filter_input(INPUT_POST, "type") ?: 'update';

$rahDebugFormLog = false;

/* ---------- Log do POST para depuração ---------- */
if ($rahDebugFormLog && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $formPayload = $_POST;
    foreach ($formPayload as $key => $value) {
        if (is_string($value) && strlen($value) > 500) {
            $formPayload[$key] = substr($value, 0, 500) . '... (truncated)';
        }
    }
    $jsonFlags = 0;
    foreach (['JSON_UNESCAPED_UNICODE', 'JSON_UNESCAPED_SLASHES', 'JSON_PARTIAL_OUTPUT_ON_ERROR'] as $constName) {
        if (defined($constName)) $jsonFlags |= constant($constName);
    }
    error_log('[RAH][FORM_DATA] ' . (json_encode($formPayload, $jsonFlags) ?: 'Falha ao converter payload.'));
}

/* ---------- Helpers ---------- */
function limparCampo($valor)
{
    $valor = (string)($valor ?? '');
    $valor = str_replace(['R$', ' '], '', $valor);
    $valor = str_replace('.', '', $valor);
    $valor = str_replace(',', '.', $valor);
    return $valor === '' ? null : $valor;
}
function moneyPOST($name)
{
    $v = limparCampo(filter_input(INPUT_POST, $name));
    return $v === null ? 0.0 : (float)$v;
}
function intPOST($name)
{
    $v = filter_input(INPUT_POST, $name, FILTER_VALIDATE_INT);
    return ($v === false || $v === null) ? null : (int)$v;
}
function strPOST($name)
{
    $v = filter_input(INPUT_POST, $name);
    return $v === null ? null : trim($v);
}
function datePOST($name)
{
    $v = strPOST($name);
    if (!$v || $v === '0000-00-00') return null;
    return $v;
}
/* varchar(20) destino: numérico simples em string (sem R$) */
function to_varchar20($num)
{
    if ($num === null) $num = 0;
    $num = (float)$num;
    return number_format($num, 2, '.', '');
}
if (!function_exists('yn_norm')) {
    function yn_norm($v): string
    {
        $t = is_string($v) ? mb_strtolower(trim($v), 'UTF-8') : $v;
        return ($t === 's' || $t === '1' || $t === 1 || $t === true || $t === 'on' || $t === 'true') ? 's' : 'n';
    }
}

function flagPOST(string $name, string $default = 'n'): string
{
    return yn_norm($_POST[$name] ?? $default);
}

function frontOverrideFlag(string $name): ?string
{
    $raw = strPOST($name);
    if ($raw === null) return null;
    return yn_norm($raw) === 's' ? 's' : null;
}

/* ---------- Identificação básica ---------- */
$id_capeante    = intPOST("id_capeante");
$fk_internacao  = intPOST("fk_int_capeante");
$id_valor       = intPOST("id_valor");
$isCreate       = ($type === 'create');

$pacote         = strPOST("pacote") ?: 'n';
$parcial        = strPOST("parcial_capeante") ?: (filter_input(INPUT_POST, 'nova_parcial') ? 's' : 'n');
$parcial_num    = filter_input(INPUT_POST, 'parcial_num', FILTER_VALIDATE_INT);
$novaParcialFlag = filter_input(INPUT_POST, 'nova_parcial') ? true : false;
$senha_finalizada = flagPOST('senha_finalizada', 'n');
$conta_parada_cap = flagPOST('conta_parada_cap', 'n');
$parada_motivo_cap = strPOST('parada_motivo_cap');

$data_inicial   = datePOST("data_inicial_capeante");
$data_final     = datePOST("data_final_capeante");
$data_fech      = datePOST("data_fech_capeante");
$data_digit     = datePOST("data_digit_capeante");
$timer_cap      = filter_input(INPUT_POST, "timer_cap", FILTER_VALIDATE_INT);
if ($timer_cap === false) {
    $timer_cap = null;
}

if (!$data_digit) {
    $message->setMessage("Data de digitação é obrigatória.", "error", "back");
    exit;
}

/* --------- Validações de período --------- */
if ($data_inicial && $data_final) {
    if (strtotime($data_final) < strtotime($data_inicial)) {
        $message->setMessage("A data final não pode ser anterior à data inicial.", "error", "back");
        exit;
    }
}

if ($parcial === 's' && $fk_internacao && $data_inicial && $data_final && !$novaParcialFlag) {
    $sql = "SELECT id_capeante, parcial_num, data_inicial_capeante, data_final_capeante
            FROM tb_capeante
            WHERE fk_int_capeante = :fk";
    if ($id_capeante) {
        $sql .= " AND id_capeante <> :id";
    }
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':fk', (int)$fk_internacao, PDO::PARAM_INT);
    if ($id_capeante) {
        $stmt->bindValue(':id', (int)$id_capeante, PDO::PARAM_INT);
    }
    $stmt->execute();
    $parciaisExistentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $novoIni = strtotime($data_inicial);
    $novoFim = strtotime($data_final);
    $conflito = null;

    foreach ($parciaisExistentes as $parc) {
        $ini = strtotime($parc['data_inicial_capeante'] ?? '');
        $fim = strtotime($parc['data_final_capeante'] ?? '');
        if (!$ini || !$fim) continue;

        $sobrepoe = ($novoIni <= $fim) && ($novoFim >= $ini);
        if ($sobrepoe) {
            $conflito = $parc;
            break;
        }
    }

    if ($conflito) {
        $perIni = date('d/m/Y', strtotime($conflito['data_inicial_capeante']));
        $perFim = date('d/m/Y', strtotime($conflito['data_final_capeante']));
        $num    = $conflito['parcial_num'] ?? '?';
        $message->setMessage(
            "As datas informadas coincidem com a parcial nº {$num} ({$perIni} a {$perFim}). Ajuste o intervalo.",
            "error",
            "back"
        );
        exit;
    }
}

if ($id_capeante && !$isCreate) {
    if ($id_valor) {
        $capValoresDao->touch($id_valor, (int)$id_capeante);
    } else {
        $id_valor = $capValoresDao->ensureByCapeante((int)$id_capeante);
    }
}

/* ---------- Profissionais (FKs) ---------- */
$fk_med = intPOST('fk_id_aud_med') ?? 0;
$fk_enf = intPOST('fk_id_aud_enf') ?? 0;
$fk_adm = intPOST('fk_id_aud_adm') ?? 0;

/* ---------- Cadastro Equipe (Ativar) ---------- */
$cadastro_central_cap = yn_norm($_POST['cadastro_central_cap'] ?? 'n'); // 's' / 'n'
$cad_ativo = ($cadastro_central_cap === 's');

/* ---------- Profissionais (FKs) ---------- */
$fk_med = intPOST('fk_id_aud_med') ?? 0;
$fk_enf = intPOST('fk_id_aud_enf') ?? 0;
$fk_adm = intPOST('fk_id_aud_adm') ?? 0;

/* ---------- Flags calculadas NO BACKEND ---------- */
// Regra original (se quiser manter para médico/enfermeiro)
$aud_med_capeante = ($cad_ativo && $fk_med > 0) ? 's' : 'n';
$aud_enf_capeante = ($cad_ativo && $fk_enf > 0) ? 's' : 'n';
$aud_adm_capeante = ($cad_ativo && $fk_adm > 0) ? 's' : 'n';

// Checks vindos do front (se vierem 's', mantém; senão espelha as flags)
$med_check_front   = strtolower((string)(strPOST('med_check')   ?? '')) === 's' ? 's' : null;
$enfer_check_front = strtolower((string)(strPOST('enfer_check') ?? '')) === 's' ? 's' : null;
$adm_check_front   = strtolower((string)(strPOST('adm_check')   ?? '')) === 's' ? 's' : null;

$med_check   = $med_check_front   ?? $aud_med_capeante;
$enfer_check = $enfer_check_front ?? $aud_enf_capeante;
$adm_check = $adm_check_front ?? $aud_aud_capeante;

// *** ADM CHECK TAMBÉM SEMPRE 's' ***
$adm_check   = 's';


/* ---------- Log das flags normalizadas ---------- */
error_log('[RAH][FLAGS_NORMALIZED] ' . json_encode([
    'cadastro_central_cap' => $cadastro_central_cap,
    'fk_id_aud_med' => $fk_med,
    'fk_id_aud_enf' => $fk_enf,
    'fk_id_aud_adm' => $fk_adm,
    'aud_med_capeante' => $aud_med_capeante,
    'aud_enf_capeante' => $aud_enf_capeante,
    'aud_adm_capeante' => $aud_adm_capeante,
    'med_check' => $med_check,
    'enfer_check' => $enfer_check,
    'adm_check' => $adm_check,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

/* ============================================================
 * CAPTURA E CÁLCULOS DE DIÁRIAS/APTO/UTI/CC/OUTROS
 * ============================================================ */
function capturarLinha($prefix)
{
    $qtd     = intPOST($prefix . "_qtd") ?? 0;
    $cobrado = moneyPOST($prefix . "_cobrado");
    $glosado = moneyPOST($prefix . "_glosado");
    $obs     = strPOST($prefix . "_obs");
    $lib     = max(0.0, $cobrado - $glosado);
    return [
        'qtd'     => (int)$qtd,
        'cobrado' => (float)$cobrado,
        'glosado' => (float)$glosado,
        'lib'     => (float)$lib,
        'obs'     => $obs
    ];
}
function somaCampo($arr, $chave)
{
    $t = 0.0;
    foreach ($arr as $r) $t += (float)($r[$chave] ?? 0);
    return $t;
}

/* DIÁRIAS */
$diarias_ids = ['ac_quarto', 'ac_dayclinic', 'ac_uti', 'ac_utisemi', 'ac_enfermaria', 'ac_bercario', 'ac_acompanhante', 'ac_isolamento'];
$diarias_rows = [];
foreach ($diarias_ids as $idp) $diarias_rows[$idp] = capturarLinha($idp);
$diarias_cob = somaCampo($diarias_rows, 'cobrado');
$diarias_glo = somaCampo($diarias_rows, 'glosado');
$diarias_lib = somaCampo($diarias_rows, 'lib');

/* APTO */
$ap_form = [
    'terapias' => 'ap_terapias',
    'taxas' => 'ap_taxas',
    'mat_consumo' => 'ap_mat_consumo',
    'medicamentos' => 'ap_medicametos', // legado
    'gases' => 'ap_gases',
    'mat_especial' => 'ap_mat_espec',
    'exames' => 'ap_exames',
    'hemoderivados' => 'ap_hemoderivados',
    'honorarios' => 'ap_honorarios',
];
$ap_calc = [];
foreach ($ap_form as $cat => $pfx) $ap_calc[$cat] = capturarLinha($pfx);

/* UTI */
$uti_form = [
    'terapias' => 'uti_terapias',
    'taxas' => 'uti_taxas',
    'mat_consumo' => 'uti_mat_consumo',
    'medicamentos' => 'uti_medicametos',
    'gases' => 'uti_gases',
    'mat_especial' => 'uti_mat_espec',
    'exames' => 'uti_exames',
    'hemoderivados' => 'uti_hemoderivados',
    'honorarios' => 'uti_honorarios',
];
$uti_calc = [];
foreach ($uti_form as $cat => $pfx) $uti_calc[$cat] = capturarLinha($pfx);

/* CC */
$cc_form = [
    'terapias' => 'cc_terapias',
    'taxas' => 'cc_taxas',
    'mat_consumo' => 'cc_mat_consumo',
    'medicamentos' => 'cc_medicametos',
    'gases' => 'cc_gases',
    'mat_especial' => 'cc_mat_espec',
    'exames' => 'cc_exames',
    'hemoderivados' => 'cc_hemoderivados',
    'honorarios' => 'cc_honorarios',
];
$cc_calc = [];
foreach ($cc_form as $cat => $pfx) $cc_calc[$cat] = capturarLinha($pfx);

/* OUTROS */
$outros_form = ['pacote' => 'outros_pacote', 'remocao' => 'outros_remocao'];
$outros_calc = [];
foreach ($outros_form as $cat => $pfx) $outros_calc[$cat] = capturarLinha($pfx);

/* Totais */
function somarSetor($calc)
{
    $tCob = $tGlo = $tLib = 0.0;
    foreach ($calc as $r) {
        $tCob += (float)$r['cobrado'];
        $tGlo += (float)$r['glosado'];
        $tLib += (float)$r['lib'];
    }
    return [$tCob, $tGlo, $tLib];
}
list($ap_cob, $ap_glo, $ap_lib)             = somarSetor($ap_calc);
list($uti_cob, $uti_glo, $uti_lib)          = somarSetor($uti_calc);
list($cc_cob, $cc_glo, $cc_lib)             = somarSetor($cc_calc);
list($outros_cob, $outros_glo, $outros_lib) = somarSetor($outros_calc);

$total_cobrado  = (float)$diarias_cob + $ap_cob + $uti_cob + $cc_cob + $outros_cob;
$total_glosado  = (float)$diarias_glo + $ap_glo + $uti_glo + $cc_glo + $outros_glo;
$total_liberado = (float)$diarias_lib + $ap_lib + $uti_lib + $cc_lib + $outros_lib;

$hasLancamento = ($total_cobrado > 0) || ($total_glosado > 0) || ($total_liberado > 0);
$sessionUserId = $_SESSION['id_usuario'] ?? null;
$sessionUserName = $_SESSION['usuario_user'] ?? $_SESSION['login_user'] ?? $_SESSION['email_user'] ?? null;
$now = date('Y-m-d H:i:s');
$timer_start_cap = null;
$timer_end_cap = null;
$existing_timer_cap = null;
if ($id_capeante) {
    $stmtTimer = $conn->prepare("SELECT timer_start_cap, timer_end_cap, timer_cap FROM tb_capeante WHERE id_capeante = :id");
    $stmtTimer->bindValue(':id', (int)$id_capeante, PDO::PARAM_INT);
    $stmtTimer->execute();
    $timerRow = $stmtTimer->fetch(PDO::FETCH_ASSOC) ?: [];
    $timer_start_cap = $timerRow['timer_start_cap'] ?? null;
    $timer_end_cap = $timerRow['timer_end_cap'] ?? null;
    $existing_timer_cap = $timerRow['timer_cap'] ?? null;
}
if (empty($timer_start_cap) && $hasLancamento) {
    $timer_start_cap = $now;
}
$finalizando = true;
if ($finalizando && $existing_timer_cap === null && $timer_start_cap) {
    $timer_end_cap = $now;
    $timer_cap = max(0, strtotime($timer_end_cap) - strtotime($timer_start_cap));
} else {
    $timer_cap = $existing_timer_cap;
}

/* Totais por categoria (para capeante) */
$cat_sum = function ($key) use ($ap_calc, $uti_calc, $cc_calc) {
    return (float)($ap_calc[$key]['lib'] + $uti_calc[$key]['lib'] + $cc_calc[$key]['lib']);
};
$cat_glo = function ($key) use ($ap_calc, $uti_calc, $cc_calc) {
    return (float)($ap_calc[$key]['glosado'] + $uti_calc[$key]['glosado'] + $cc_calc[$key]['glosado']);
};

$valor_diarias      = (float)$diarias_lib;
$valor_taxa         = $cat_sum('taxas');
$valor_materiais    = $cat_sum('mat_consumo');
$valor_medicamentos = $cat_sum('medicamentos');
$valor_sadt         = $cat_sum('exames');
$valor_honorarios   = $cat_sum('honorarios');
$valor_opme         = $cat_sum('mat_especial');
$valor_oxig         = $cat_sum('gases');

$glosa_diaria       = (float)$diarias_glo;
$glosa_taxas        = $cat_glo('taxas');
$glosa_matmed       = $cat_glo('mat_consumo');
$glosa_medicamentos = $cat_glo('medicamentos');
$glosa_sadt         = $cat_glo('exames');
$glosa_honorarios   = $cat_glo('honorarios');
$glosa_opme         = $cat_glo('mat_especial');
$glosa_oxig         = $cat_glo('gases');

$valor_apresentado  = (float)$total_cobrado;

/* Desconto absoluto + observações */
$desconto_valor_cap = max(0.0, moneyPOST("desconto_valor_cap"));
$desconto_valor_cap = min($desconto_valor_cap, $total_liberado);
$comentarios_obs    = strPOST('comentarios_obs');
$valor_final        = max(0.0, $total_liberado - $desconto_valor_cap);
$valor_glosa_total  = max(0.0, $valor_apresentado - $valor_final);

/* ============================================================
 * MONTAGEM DO OBJETO capeante E PERSISTÊNCIA VIA DAO
 * ============================================================ */
$beforeCapeante = null;
if (!$isCreate && $id_capeante) {
    $beforeCapeante = $capeanteDao->findById((int)$id_capeante);
}

$cap = new capeante();

if ($type === 'create') {
    $cap->fk_int_capeante       = $fk_internacao;
} else {
    $cap->id_capeante           = $id_capeante;
    $cap->fk_int_capeante       = $fk_internacao;
}

/* Período e datas */
$cap->data_inicial_capeante     = $data_inicial;
$cap->data_final_capeante       = $data_final;
$cap->data_fech_capeante        = $data_fech;
$cap->data_digit_capeante       = $data_digit;
$cap->timer_start_cap           = $timer_start_cap;
$cap->timer_end_cap             = $timer_end_cap;
$cap->timer_cap                 = $timer_cap;

/* Parcelas/pacote */
$cap->pacote                    = $pacote;
$cap->parcial_capeante          = $parcial;
$cap->parcial_num               = $parcial_num;
$cap->senha_finalizada          = $senha_finalizada;
$cap->conta_parada_cap          = $conta_parada_cap;
$cap->parada_motivo_cap         = $conta_parada_cap === 's' ? ($parada_motivo_cap ?: null) : null;

/* Totais */
$cap->valor_apresentado_capeante = $valor_apresentado;
$cap->valor_final_capeante      = $valor_final;

$cap->valor_diarias             = $valor_diarias;
$cap->valor_taxa                = $valor_taxa;
$cap->valor_materiais           = $valor_materiais;
$cap->valor_medicamentos        = $valor_medicamentos;
$cap->valor_sadt                = $valor_sadt;
$cap->valor_honorarios          = $valor_honorarios;
$cap->valor_opme                = $valor_opme;
$cap->valor_oxig                = $valor_oxig;

$cap->glosa_diaria              = $glosa_diaria;
$cap->glosa_taxas               = $glosa_taxas;
$cap->glosa_matmed              = $glosa_matmed;
$cap->glosa_medicamentos        = $glosa_medicamentos;
$cap->glosa_sadt                = $glosa_sadt;
$cap->glosa_honorarios          = $glosa_honorarios;
$cap->glosa_opme                = $glosa_opme;
$cap->glosa_oxig                = $glosa_oxig;
$cap->valor_glosa_total         = $valor_glosa_total;

$cap->desconto_valor_cap        = $desconto_valor_cap;
if ($hasLancamento && $sessionUserId) {
    $cap->fk_user_cap = (int)$sessionUserId;
}
if ($hasLancamento && $sessionUserName) {
    $cap->usuario_create_cap = $sessionUserName;
}

/* ======= PROFISSIONAIS (FKs) ======= */
$cap->fk_id_aud_med             = $fk_med ?: null;
$cap->fk_id_aud_enf             = $fk_enf ?: null;
$cap->fk_id_aud_adm             = $fk_adm ?: null;

/* ======= FLAGS (calculadas) ======= */
$cap->aud_med_capeante          = $aud_med_capeante;  // 's'/'n'
$cap->aud_enf_capeante          = $aud_enf_capeante;  // 's'/'n'
$cap->aud_adm_capeante          = $aud_adm_capeante;  // 's'/'n'

$cap->med_check                 = $med_check;         // 's'/'n'
$cap->enfer_check               = $enfer_check;       // 's'/'n'
$cap->adm_check                 = $adm_check;         // 's'/'n'

// Ao concluir o RAH a conta correspondente é dada como encerrada
$cap->encerrado_cap             = 's';

/* Persistência principal */
if ($type === 'create') {
    $capeanteDao->create($cap);
    $novoId = $cap->id_capeante ?? (int)$conn->lastInsertId();
    $id_capeante = (int)$novoId;
    $id_valor = $capValoresDao->ensureByCapeante((int)$id_capeante);
    $afterCapeante = $capeanteDao->findById((int)$id_capeante);
    fullcareAuditLog($conn, [
        'action' => 'create',
        'entity_type' => 'capeante',
        'entity_id' => (int)$id_capeante,
        'after' => $afterCapeante ?: array_merge(get_object_vars($cap), ['id_capeante' => (int)$id_capeante]),
        'source' => 'process_rah.php',
    ], $BASE_URL);
} else {
    $capeanteDao->update($cap);
    $afterCapeante = $capeanteDao->findById((int)$id_capeante);
    fullcareAuditLog($conn, [
        'action' => 'update',
        'entity_type' => 'capeante',
        'entity_id' => (int)$id_capeante,
        'before' => $beforeCapeante,
        'after' => $afterCapeante ?: $cap,
        'source' => 'process_rah.php',
    ], $BASE_URL);
}

/* Log final de totais */
error_log("[RAH] Capeante ID {$id_capeante} | Cobrado={$total_cobrado} | Glosado={$total_glosado} | Liberado={$total_liberado}");

/* ---------- Preparar sessão e URLs pós-salvar ---------- */
$patientId = null;
$patientName = null;
$hubUrl = null;
if ($fk_internacao) {
    try {
        $stmtPac = $conn->prepare("SELECT fk_paciente_int FROM tb_internacao WHERE id_internacao = :id LIMIT 1");
        $stmtPac->bindValue(':id', (int)$fk_internacao, PDO::PARAM_INT);
        $stmtPac->execute();
        $patientId = (int)$stmtPac->fetchColumn();
        if ($patientId > 0) {
            $hubUrl = rtrim($BASE_URL, '/') . '/hub_paciente/paciente' . $patientId;
            $stmtName = $conn->prepare("SELECT nome_pac FROM tb_paciente WHERE id_paciente = :id LIMIT 1");
            $stmtName->bindValue(':id', $patientId, PDO::PARAM_INT);
            $stmtName->execute();
            $patientName = $stmtName->fetchColumn();
        }
    } catch (Throwable $e) {
        $hubUrl = null;
    }
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if ($patientId) {
    $_SESSION['rah_after_save'] = [
        'patient_id' => $patientId,
        'patient_name' => $patientName ? (string)$patientName : '',
        'accounts_url' => $BASE_URL . 'internacoes/rah?pesquisa_pac=' . rawurlencode($patientName ?? ''),
        'visits_url' => $fk_internacao ? $BASE_URL . 'cad_visita.php?id_internacao=' . $fk_internacao : null,
    ];
}

/* ============================================================
 * Persistência dos grupos AP / UTI / CC / DIÁRIAS / OUTROS
 * - Somente valores (sem FKs de profissionais)
 * ============================================================ */

function rah_upsert_grupo(PDO $conn, string $tabela, int $fk_capeante, array $mapCatToLegacyCols, array $calcPorCat, array $extraCols = [])
{
    // Verifica se já existe registro para fk_capeante
    $stmt = $conn->prepare("SELECT 1 FROM {$tabela} WHERE fk_capeante = ? LIMIT 1");
    $stmt->execute([$fk_capeante]);
    $exists = (bool)$stmt->fetchColumn();

    if ($exists) {
        $sets = [];
        $valsUpd = [];

        foreach ($extraCols as $col => $val) {
            if ($col === 'fk_capeante') continue;
            $sets[]    = "{$col} = ?";
            $valsUpd[] = $val;
        }

        foreach ($mapCatToLegacyCols as $cat => $legacyPrefix) {
            $row = $calcPorCat[$cat] ?? ['qtd' => 0, 'cobrado' => 0, 'glosado' => 0, 'obs' => null];

            $qtd     = (int)($row['qtd'] ?? 0);
            $cobrado = to_varchar20($row['cobrado'] ?? 0);
            $glosado = to_varchar20($row['glosado'] ?? 0);
            $obs     = $row['obs'] ?? null;

            $sets[]    = "{$legacyPrefix}_qtd = ?";
            $sets[]    = "{$legacyPrefix}_cobrado = ?";
            $sets[]    = "{$legacyPrefix}_glosado = ?";
            $sets[]    = "{$legacyPrefix}_obs = ?";

            $valsUpd[] = (string)$qtd;
            $valsUpd[] = $cobrado;
            $valsUpd[] = $glosado;
            $valsUpd[] = $obs;
        }
        $valsUpd[] = $fk_capeante;
        $sql = "UPDATE {$tabela} SET " . implode(', ', $sets) . " WHERE fk_capeante = ?";
        $conn->prepare($sql)->execute($valsUpd);
    } else {
        $cols = ['fk_capeante'];
        $vals = [$fk_capeante];

        foreach ($extraCols as $col => $val) {
            if ($col === 'fk_capeante') continue;
            $cols[] = $col;
            $vals[] = $val;
        }

        foreach ($mapCatToLegacyCols as $cat => $legacyPrefix) {
            $row = $calcPorCat[$cat] ?? ['qtd' => 0, 'cobrado' => 0, 'glosado' => 0, 'obs' => null];

            $qtd     = (int)($row['qtd'] ?? 0);
            $cobrado = to_varchar20($row['cobrado'] ?? 0);
            $glosado = to_varchar20($row['glosado'] ?? 0);
            $obs     = $row['obs'] ?? null;

            $cols[] = "{$legacyPrefix}_qtd";
            $vals[] = (string)$qtd;
            $cols[] = "{$legacyPrefix}_cobrado";
            $vals[] = $cobrado;
            $cols[] = "{$legacyPrefix}_glosado";
            $vals[] = $glosado;
            $cols[] = "{$legacyPrefix}_obs";
            $vals[] = $obs;
        }
        $ph = implode(',', array_fill(0, count($cols), '?'));
        $sql = "INSERT INTO {$tabela} (" . implode(',', $cols) . ") VALUES ({$ph})";
        $conn->prepare($sql)->execute($vals);
    }
}

/* Mapeamentos: categoria → prefixo legado por tabela */
$map_ap = [
    'terapias' => 'ap_terapias',
    'taxas' => 'ap_taxas',
    'mat_consumo' => 'ap_mat_consumo',
    'medicamentos' => 'ap_medicametos',
    'gases' => 'ap_gases',
    'mat_especial' => 'ap_mat_espec',
    'exames' => 'ap_exames',
    'hemoderivados' => 'ap_hemoderivados',
    'honorarios' => 'ap_honorarios',
];
$map_uti = [
    'terapias' => 'uti_terapias',
    'taxas' => 'uti_taxas',
    'mat_consumo' => 'uti_mat_consumo',
    'medicamentos' => 'uti_medicametos',
    'gases' => 'uti_gases',
    'mat_especial' => 'uti_mat_espec',
    'exames' => 'uti_exames',
    'hemoderivados' => 'uti_hemoderivados',
    'honorarios' => 'uti_honorarios',
];
$map_cc = [
    'terapias' => 'cc_terapias',
    'taxas' => 'cc_taxas',
    'mat_consumo' => 'cc_mat_consumo',
    'medicamentos' => 'cc_medicametos',
    'gases' => 'cc_gases',
    'mat_especial' => 'cc_mat_espec',
    'exames' => 'cc_exames',
    'hemoderivados' => 'cc_hemoderivados',
    'honorarios' => 'cc_honorarios',
];
$map_diar = [
    'ac_quarto' => 'ac_quarto',
    'ac_dayclinic' => 'ac_dayclinic',
    'ac_uti' => 'ac_uti',
    'ac_utisemi' => 'ac_utisemi',
    'ac_enfermaria' => 'ac_enfermaria',
    'ac_bercario' => 'ac_bercario',
    'ac_acompanhante' => 'ac_acompanhante',
    'ac_isolamento' => 'ac_isolamento',
];
$map_out = ['pacote' => 'outros_pacote', 'remocao' => 'outros_remocao'];

/* Salva grupos (somente se temos id_capeante válido) */
if (!empty($id_capeante)) {

    // Garante fk_internacao válido para OUTROS
    if (!$fk_internacao) {
        $fk_internacao = (int)$conn
            ->query("SELECT fk_int_capeante FROM tb_capeante WHERE id_capeante = " . (int)$id_capeante)
            ->fetchColumn();
    }

    // >>> Sem FKs de profissionais nas acessórias <<<
    rah_upsert_grupo($conn, 'tb_cap_valores_ap',   (int)$id_capeante, $map_ap,   $ap_calc);
    rah_upsert_grupo($conn, 'tb_cap_valores_uti',  (int)$id_capeante, $map_uti,  $uti_calc);
    rah_upsert_grupo($conn, 'tb_cap_valores_cc',   (int)$id_capeante, $map_cc,   $cc_calc);
    rah_upsert_grupo($conn, 'tb_cap_valores_diar', (int)$id_capeante, $map_diar, $diarias_rows);

    // OUTROS: inclui apenas o fk_internacao por integridade
    rah_upsert_grupo(
        $conn,
        'tb_cap_valores_out',
        (int)$id_capeante,
        $map_out,
        $outros_calc,
        [
            'fk_int_capeante'    => (int)$fk_internacao,
            'outros_desconto_out' => to_varchar20($desconto_valor_cap),
            'comentarios_obs'     => $comentarios_obs
        ]
    );
}

if ($hubUrl) {
    header("Location: " . $hubUrl);
} else {
    header("Location: " . $BASE_URL . "internacoes/rah");
}
exit;
