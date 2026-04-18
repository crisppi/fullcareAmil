<?php
// ======================================================================
// process_visita.php  (refatorado, sem alterar métodos existentes)
// ======================================================================

// Debug local opcional (somente quando APP_DEBUG=1 no ambiente)
$__DEBUG = in_array(strtolower((string)getenv('APP_DEBUG')), ['1', 'true', 'on', 'yes'], true);
if ($__DEBUG) {
    error_reporting(E_ALL);
}
function dbg(...$args)
{
    global $__DEBUG;
    if (!$__DEBUG) return;
    echo "<pre style='background:#111;color:#0f0;padding:10px;border-radius:6px;line-height:1.25;'>"
        . htmlspecialchars(print_r(count($args) === 1 ? $args[0] : $args, true), ENT_QUOTES, 'UTF-8')
        . "</pre>";
}

function fullcareUserExists(PDO $conn, ?int $userId): bool
{
    if ($userId === null || $userId <= 0) {
        return false;
    }

    static $cache = [];
    if (array_key_exists($userId, $cache)) {
        return $cache[$userId];
    }

    $stmt = $conn->prepare("SELECT 1 FROM tb_user WHERE id_usuario = :id LIMIT 1");
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    return $cache[$userId] = (bool)$stmt->fetchColumn();
}

function fullcareResolveUserId(PDO $conn, ?int ...$candidates): ?int
{
    foreach ($candidates as $candidate) {
        $candidate = ($candidate !== null && $candidate > 0) ? (int)$candidate : null;
        if ($candidate !== null && fullcareUserExists($conn, $candidate)) {
            return $candidate;
        }
    }

    $sessionId = isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : 0;
    if ($sessionId > 0 && fullcareUserExists($conn, $sessionId)) {
        return $sessionId;
    }

    return null;
}
function fullcareNormalizeNegotiationAcomodacao(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    if (strpos($value, '-') !== false) {
        [, $value] = array_pad(explode('-', $value, 2), 2, '');
    }
    return mb_strtolower(trim($value), 'UTF-8');
}

function fullcareInternacaoHospitalId(PDO $conn, int $internacaoId): int
{
    static $cache = [];
    if ($internacaoId <= 0) {
        return 0;
    }
    if (isset($cache[$internacaoId])) {
        return $cache[$internacaoId];
    }

    $stmt = $conn->prepare("SELECT fk_hospital_int FROM tb_internacao WHERE id_internacao = :id LIMIT 1");
    $stmt->bindValue(':id', $internacaoId, PDO::PARAM_INT);
    $stmt->execute();

    return $cache[$internacaoId] = (int)($stmt->fetchColumn() ?: 0);
}

function fullcareCalcNegotiationSaving(PDO $conn, int $internacaoId, ?string $tipo, ?string $trocaDe, ?string $trocaPara, int $qtd): float
{
    static $cache = [];
    $hospitalId = fullcareInternacaoHospitalId($conn, $internacaoId);
    if ($hospitalId <= 0 || $qtd <= 0) {
        return 0.0;
    }
    if (!isset($cache[$hospitalId])) {
        $stmt = $conn->prepare("SELECT acomodacao_aco, valor_aco FROM tb_acomodacao WHERE fk_hospital = :id");
        $stmt->bindValue(':id', $hospitalId, PDO::PARAM_INT);
        $stmt->execute();
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $map[fullcareNormalizeNegotiationAcomodacao($row['acomodacao_aco'] ?? '')] = (float)($row['valor_aco'] ?? 0);
        }
        $cache[$hospitalId] = $map;
    }

    $map = $cache[$hospitalId];
    $de = (float)($map[fullcareNormalizeNegotiationAcomodacao($trocaDe)] ?? 0);
    $para = (float)($map[fullcareNormalizeNegotiationAcomodacao($trocaPara)] ?? 0);
    $tipoNorm = mb_strtoupper(trim((string)$tipo), 'UTF-8');

    if (strpos($tipoNorm, 'TROCA') === 0) {
        return ($de - $para) * $qtd;
    }
    if (strpos($tipoNorm, '1/2 DIARIA') !== false) {
        return ($de / 2) * $qtd;
    }
    return $de * $qtd;
}

// ======================================================================
// Includes / DAOs / Models
// ======================================================================
require_once("globals.php");
require_once("db.php");

require_once("models/internacao.php");
require_once("dao/internacaoDao.php");

require_once("models/gestao.php");
require_once("dao/gestaoDao.php");

require_once("models/tuss.php");
require_once("dao/tussDao.php");

require_once("models/uti.php");
require_once("dao/utiDao.php");

require_once("models/negociacao.php");
require_once("dao/negociacaoDao.php");

require_once("models/visita.php");
require_once("dao/visitaDao.php");

require_once("models/prorrogacao.php");
require_once("dao/prorrogacaoDao.php");

require_once("models/internacao_antecedente.php");
require_once("dao/internacaoAntecedenteDao.php");

require_once("models/usuario.php");
require_once("dao/usuarioDao.php");

require_once("models/message.php");
require_once("utils/flow_logger.php");
require_once(__DIR__ . "/app/cuidadoContinuado.php");
require_once(__DIR__ . "/app/prorrog_alta_helper.php");

$message                = new Message($BASE_URL);
$userDao                = new UserDAO($conn, $BASE_URL);
$internacaoDao          = new internacaoDAO($conn, $BASE_URL);
$gestaoDao              = new gestaoDAO($conn, $BASE_URL);
$utiDao                 = new utiDAO($conn, $BASE_URL);
$negociacaoDao          = new negociacaoDAO($conn, $BASE_URL);
$tussDao                = new tussDAO($conn, $BASE_URL);
$prorrogacaoDao         = new prorrogacaoDAO($conn, $BASE_URL);
$visitaDao              = new visitaDAO($conn, $BASE_URL);
$internAntecedenteDao   = new InternacaoAntecedenteDAO($conn, $BASE_URL);

if (!function_exists('cc_process_visita_cronicos')) {
    function cc_process_visita_cronicos(PDO $conn, internacaoDAO $internacaoDao, ?int $internacaoId, ?string $reportText, string $source): array
    {
        if (!$internacaoId || trim((string)$reportText) === '') {
            return [];
        }

        $internacao = $internacaoDao->findById($internacaoId);
        $patientId = (int)($internacao->fk_paciente_int ?? 0);
        if ($patientId <= 0) {
            return [];
        }

        return cc_enqueue_chronic_candidates_from_text(
            $conn,
            $patientId,
            $reportText,
            $source,
            [
                'origem_tipo' => 'relatorio_visita',
                'origem_descricao' => $source,
                'fk_internacao' => (int)$internacaoId,
                'resumo_clinico' => (string)$reportText,
            ]
        );
    }
}

if (!function_exists('cc_process_visita_cronicos_from_antecedentes')) {
    function cc_process_visita_cronicos_from_antecedentes(PDO $conn, internacaoDAO $internacaoDao, ?int $internacaoId, string $source): array
    {
        if (!$internacaoId) {
            return [];
        }

        $internacao = $internacaoDao->findById($internacaoId);
        $patientId = (int)($internacao->fk_paciente_int ?? 0);
        if ($patientId <= 0) {
            return [];
        }

        return cc_enqueue_chronic_candidates_from_antecedent_names(
            $conn,
            $patientId,
            cc_fetch_patient_antecedent_names($conn, $patientId),
            $source,
            [
                'origem_tipo' => 'antecedente_paciente',
                'origem_descricao' => $source,
                'fk_internacao' => (int)$internacaoId,
            ]
        );
    }
}

// ======================================================================
// Utilitários simples
// ======================================================================
function toIntOrNull($v)
{
    if ($v === null || $v === '') return null;
    $iv = filter_var($v, FILTER_VALIDATE_INT);
    return ($iv === false) ? null : $iv;
}
function toFloatOrNull($v)
{
    if ($v === null || $v === '') return null;
    $fv = filter_var(str_replace(',', '.', $v), FILTER_VALIDATE_FLOAT);
    return ($fv === false) ? null : $fv;
}
function strOrNull($v)
{
    $v = is_string($v) ? trim($v) : '';
    return $v === '' ? null : $v;
}

function normalizeDateTimeInput($value)
{
    if ($value === null) return null;
    $value = trim((string)$value);
    if ($value === '') return null;
    $formats = [
        ['fmt' => 'Y-m-d\\TH:i:s', 'has_time' => true],
        ['fmt' => 'Y-m-d\\TH:i',   'has_time' => true],
        ['fmt' => 'Y-m-d H:i:s',     'has_time' => true],
        ['fmt' => 'Y-m-d H:i',       'has_time' => true],
        ['fmt' => 'd/m/Y H:i:s',     'has_time' => true],
        ['fmt' => 'd/m/Y H:i',       'has_time' => true],
        ['fmt' => 'Y-m-d',           'has_time' => false],
        ['fmt' => 'd/m/Y',           'has_time' => false],
    ];
    foreach ($formats as $conf) {
        $dt = DateTime::createFromFormat($conf['fmt'], $value);
        if ($dt instanceof DateTime) {
            if (!$conf['has_time']) {
                $dt->setTime(0, 0, 0);
            }
            return $dt->format('Y-m-d H:i:s');
        }
    }
    $ts = strtotime($value);
    return $ts ? date('Y-m-d 00:00:00', $ts) : null;
}

function decodeJsonArray(?string $raw): ?array
{
    if ($raw === null || $raw === '') return null;
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) return null;
    return $data;
}

function buildAutoNegociacoesFromProrrog(
    string $flagProrrog,
    ?string $prorrogJsonRaw,
    ?int $fkUsuarioPadrao
): array {
    if ($flagProrrog !== 's') return [];
    $decoded = decodeJsonArray($prorrogJsonRaw);
    if (!is_array($decoded) || !isset($decoded['prorrogations']) || !is_array($decoded['prorrogations'])) {
        return [];
    }

    $auto = [];
    foreach ($decoded['prorrogations'] as $row) {
        if (!is_array($row)) continue;
        $dataIni = strOrNull($row['prorrog1_ini_pror'] ?? null);
        $dataFim = strOrNull($row['prorrog1_fim_pror'] ?? null);
        $acomodLiberada = strOrNull($row['acomod1_pror'] ?? null);
        if (!$dataIni || !$dataFim || !$acomodLiberada) continue;

        $qtd = toIntOrNull($row['diarias_1'] ?? null);
        if ($qtd === null && $dataIni && $dataFim) {
            $iniTs = strtotime($dataIni);
            $fimTs = strtotime($dataFim);
            if ($iniTs && $fimTs && $fimTs >= $iniTs) {
                $qtd = (int)ceil(($fimTs - $iniTs) / 86400);
            }
        }
        if ($qtd === null || $qtd <= 0) continue;

        $auto[] = [
            'tipo_negociacao' => strOrNull($row['tipo_negociacao_pror'] ?? null) ?: 'PRORROGACAO_AUTOMATICA',
            'data_inicio_negoc' => $dataIni,
            'data_fim_negoc' => $dataFim,
            'troca_de' => strOrNull($row['acomod_solicitada_pror'] ?? null) ?: $acomodLiberada,
            'troca_para' => $acomodLiberada,
            'qtd' => $qtd,
            'saving' => toFloatOrNull($row['saving_estimado_pror'] ?? null) ?? 0.0,
            'fk_usuario_neg' => toIntOrNull($row['fk_usuario_pror'] ?? null) ?? $fkUsuarioPadrao,
        ];
    }
    return $auto;
}

function processTussEntries(
    string $flag,
    ?string $jsonRaw,
    int $visitaId,
    int $fkInternacao,
    tussDAO $tussDao,
    ?int $fkUsuarioVis
): void {
    if ($visitaId <= 0) return;
    $tussDao->deleteByVisita($visitaId);
    if ($flag !== 's') return;
    $decoded = decodeJsonArray($jsonRaw);
    if (!is_array($decoded) || !isset($decoded['tussEntries']) || !is_array($decoded['tussEntries'])) return;
    foreach ($decoded['tussEntries'] as $row) {
        if (!is_array($row)) continue;
        $descricao = strOrNull($row['tuss_solicitado'] ?? null);
        if (!$descricao) continue;
        $tuss = new tuss();
        $tuss->fk_int_tuss = $fkInternacao;
        $tuss->fk_vis_tuss = $visitaId;
        $tuss->tuss_solicitado = $descricao;
        $tuss->tuss_liberado_sn = strOrNull($row['tuss_liberado_sn'] ?? null);
        $tuss->qtd_tuss_solicitado = toIntOrNull($row['qtd_tuss_solicitado'] ?? null);
        $tuss->qtd_tuss_liberado = toIntOrNull($row['qtd_tuss_liberado'] ?? null);
        $tuss->data_realizacao_tuss = strOrNull($row['data_realizacao_tuss'] ?? null);
        $tuss->fk_usuario_tuss = toIntOrNull($row['fk_usuario_tuss'] ?? $fkUsuarioVis);
        $tuss->data_create_tuss = date('Y-m-d H:i:s');
        $tussDao->create($tuss);
    }
}

function processNegociacoesEntries(
    string $flag,
    ?string $jsonRaw,
    int $visitaId,
    int $fkInternacao,
    negociacaoDAO $negociacaoDao,
    PDO $conn,
    ?int $fkUsuarioNeg,
    array $autoNegociacoes = []
): void {
    if ($visitaId <= 0) return;
    $negociacaoDao->deleteByVisita($visitaId);
    if ($flag !== 's') return;
    $decoded = decodeJsonArray($jsonRaw);
    if (!is_array($decoded)) $decoded = [];
    $rows = array_merge($decoded, $autoNegociacoes);
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $tipo = strOrNull($row['tipo_negociacao'] ?? null);
        if (!$tipo) continue;
        $trocaDe = strOrNull($row['troca_de'] ?? null);
        $trocaPara = strOrNull($row['troca_para'] ?? null);
        $qtd = toIntOrNull($row['qtd'] ?? null);
        $auditorId = fullcareResolveUserId(
            $conn,
            toIntOrNull($row['fk_usuario_neg'] ?? null),
            $fkUsuarioNeg
        );
        $saving = fullcareCalcNegotiationSaving($conn, $fkInternacao, $tipo, $trocaDe, $trocaPara, (int)$qtd);
        if (!$trocaDe || !$trocaPara || !$qtd || $qtd <= 0 || !$auditorId || $saving <= 0) {
            continue;
        }
        $negociacao = new negociacao();
        $negociacao->fk_id_int = $fkInternacao;
        $negociacao->fk_visita_neg = $visitaId;
        $negociacao->tipo_negociacao = $tipo;
        $negociacao->data_inicio_neg = strOrNull($row['data_inicio_negoc'] ?? null);
        $negociacao->data_fim_neg = strOrNull($row['data_fim_negoc'] ?? null);
        $negociacao->troca_de = $trocaDe;
        $negociacao->troca_para = $trocaPara;
        $negociacao->qtd = $qtd;
        $negociacao->saving = $saving;
        $negociacao->fk_usuario_neg = $auditorId;
        $negociacaoDao->create($negociacao);
    }
}

function collectGestaoPostData(): array
{
    return [
        'alto_custo_ges' => strOrNull($_POST['alto_custo_ges'] ?? null),
        'rel_alto_custo_ges' => strOrNull($_POST['rel_alto_custo_ges'] ?? null),
        'opme_ges' => strOrNull($_POST['opme_ges'] ?? null),
        'rel_opme_ges' => strOrNull($_POST['rel_opme_ges'] ?? null),
        'home_care_ges' => strOrNull($_POST['home_care_ges'] ?? null),
        'rel_home_care_ges' => strOrNull($_POST['rel_home_care_ges'] ?? null),
        'desospitalizacao_ges' => strOrNull($_POST['desospitalizacao_ges'] ?? null),
        'rel_desospitalizacao_ges' => strOrNull($_POST['rel_desospitalizacao_ges'] ?? null),
        'evento_adverso_ges' => strOrNull($_POST['evento_adverso_ges'] ?? null),
        'rel_evento_adverso_ges' => strOrNull($_POST['rel_evento_adverso_ges'] ?? null),
        'tipo_evento_adverso_gest' => strOrNull($_POST['tipo_evento_adverso_gest'] ?? null),
        'evento_retorno_qual_hosp_ges' => strOrNull($_POST['evento_retorno_qual_hosp_ges'] ?? null),
        'evento_classificado_hospital_ges' => strOrNull($_POST['evento_classificado_hospital_ges'] ?? null),
        'evento_data_ges' => strOrNull($_POST['evento_data_ges'] ?? null),
        'evento_encerrar_ges' => strOrNull($_POST['evento_encerrar_ges'] ?? null),
        'evento_impacto_financ_ges' => strOrNull($_POST['evento_impacto_financ_ges'] ?? null),
        'evento_prolongou_internacao_ges' => strOrNull($_POST['evento_prolongou_internacao_ges'] ?? null),
        'evento_concluido_ges' => strOrNull($_POST['evento_concluido_ges'] ?? null),
        'evento_classificacao_ges' => strOrNull($_POST['evento_classificacao_ges'] ?? null),
        'evento_fech_ges' => strOrNull($_POST['evento_fech_ges'] ?? null),
        'fk_user_ges' => toIntOrNull($_POST['fk_user_ges'] ?? null),
        'evento_valor_negoc_ges' => strOrNull($_POST['evento_valor_negoc_ges'] ?? null),
        'evento_negociado_ges' => strOrNull($_POST['evento_negociado_ges'] ?? null),
        'evento_discutido_ges' => strOrNull($_POST['evento_discutido_ges'] ?? null),
        'evento_sinalizado_ges' => strOrNull($_POST['evento_sinalizado_ges'] ?? null),
        'evento_prorrogar_ges' => strOrNull($_POST['evento_prorrogar_ges'] ?? null)
    ];
}

function processGestaoData(
    string $flag,
    array $data,
    int $visitaId,
    int $fkInternacao,
    gestaoDAO $gestaoDao,
    bool $clearExisting = false
): void {
    if ($visitaId <= 0) return;
    if ($clearExisting) {
        if (method_exists($gestaoDao, 'deleteByVisita')) {
            $gestaoDao->deleteByVisita($visitaId);
        }
    }
    if ($flag !== 's') return;

    $gestao = new gestao();
    $gestao->fk_internacao_ges = $fkInternacao;
    $gestao->fk_visita_ges = $visitaId;
    $gestao->alto_custo_ges = $data['alto_custo_ges'];
    $gestao->rel_alto_custo_ges = $data['rel_alto_custo_ges'];
    $gestao->opme_ges = $data['opme_ges'];
    $gestao->rel_opme_ges = $data['rel_opme_ges'];
    $gestao->home_care_ges = $data['home_care_ges'];
    $gestao->rel_home_care_ges = $data['rel_home_care_ges'];
    $gestao->desospitalizacao_ges = $data['desospitalizacao_ges'];
    $gestao->rel_desospitalizacao_ges = $data['rel_desospitalizacao_ges'];
    $gestao->evento_adverso_ges = $data['evento_adverso_ges'];
    $gestao->rel_evento_adverso_ges = $data['rel_evento_adverso_ges'];
    $gestao->tipo_evento_adverso_gest = $data['tipo_evento_adverso_gest'];
    $gestao->evento_retorno_qual_hosp_ges = $data['evento_retorno_qual_hosp_ges'];
    $gestao->evento_classificado_hospital_ges = $data['evento_classificado_hospital_ges'];
    $gestao->evento_data_ges = $data['evento_data_ges'];
    $gestao->evento_encerrar_ges = $data['evento_encerrar_ges'];
    $gestao->evento_impacto_financ_ges = $data['evento_impacto_financ_ges'];
    $gestao->evento_prolongou_internacao_ges = $data['evento_prolongou_internacao_ges'];
    $gestao->evento_concluido_ges = $data['evento_concluido_ges'];
    $gestao->evento_classificacao_ges = $data['evento_classificacao_ges'];
    $gestao->evento_fech_ges = $data['evento_fech_ges'];
    $gestao->fk_user_ges = $data['fk_user_ges'];
    $gestao->evento_valor_negoc_ges = $data['evento_valor_negoc_ges'];
    $gestao->evento_negociado_ges = $data['evento_negociado_ges'] ?? null;
    $gestao->evento_discutido_ges = $data['evento_discutido_ges'] ?? null;
    $gestao->evento_sinalizado_ges = $data['evento_sinalizado_ges'] ?? null;
    $gestao->evento_prorrogar_ges = $data['evento_prorrogar_ges'] ?? null;

    $gestaoDao->create($gestao);
}

function collectUtiPostData(string $usuarioCreate): array
{
    return [
        'internado_uti' => strOrNull($_POST['internado_uti'] ?? null),
        'criterios_uti' => strOrNull($_POST['criterios_uti'] ?? null),
        'data_alta_uti' => strOrNull($_POST['data_alta_uti'] ?? null),
        'data_internacao_uti' => strOrNull($_POST['data_internacao_uti'] ?? null),
        'dva_uti' => strOrNull($_POST['dva_uti'] ?? null),
        'especialidade_uti' => strOrNull($_POST['especialidade_uti'] ?? null),
        'internacao_uti' => strOrNull($_POST['internacao_uti'] ?? null),
        'just_uti' => strOrNull($_POST['just_uti'] ?? null),
        'motivo_uti' => strOrNull($_POST['motivo_uti'] ?? null),
        'rel_uti' => strOrNull($_POST['rel_uti'] ?? null),
        'saps_uti' => strOrNull($_POST['saps_uti'] ?? null),
        'score_uti' => strOrNull($_POST['score_uti'] ?? null),
        'vm_uti' => strOrNull($_POST['vm_uti'] ?? null),
        'id_internacao' => toIntOrNull($_POST['id_internacao'] ?? null),
        'fk_user_uti' => toIntOrNull($_POST['fk_user_uti'] ?? null),
        'glasgow_uti' => strOrNull($_POST['glasgow_uti'] ?? null),
        'suporte_vent_uti' => strOrNull($_POST['suporte_vent_uti'] ?? null),
        'justifique_uti' => strOrNull($_POST['justifique_uti'] ?? null),
        'hora_internacao_uti' => strOrNull($_POST['hora_internacao_uti'] ?? null),
        'dist_met_uti' => strOrNull($_POST['dist_met_uti'] ?? null),
        'usuario_create_uti' => $usuarioCreate
    ];
}

function processUtiData(
    string $flag,
    array $data,
    int $visitaId,
    int $fkInternacao,
    utiDAO $utiDao,
    bool $clearExisting = false
): void {
    if ($visitaId <= 0) return;
    if ($clearExisting && method_exists($utiDao, 'deleteByVisita')) {
        $utiDao->deleteByVisita($visitaId);
    }
    if ($flag !== 's') return;

    $uti = new uti();
    $uti->fk_internacao_uti = $fkInternacao;
    $uti->fk_visita_uti = $visitaId;
    $uti->internado_uti = $data['internado_uti'];
    $uti->criterios_uti = $data['criterios_uti'];
    $uti->data_alta_uti = $data['data_alta_uti'];
    $uti->data_internacao_uti = $data['data_internacao_uti'];
    $uti->dva_uti = $data['dva_uti'];
    $uti->especialidade_uti = $data['especialidade_uti'];
    $uti->internacao_uti = $data['internacao_uti'];
    $uti->just_uti = $data['just_uti'];
    $uti->motivo_uti = $data['motivo_uti'];
    $uti->rel_uti = $data['rel_uti'];
    $uti->saps_uti = $data['saps_uti'];
    $uti->score_uti = $data['score_uti'];
    $uti->vm_uti = $data['vm_uti'];
    $uti->id_internacao = $data['id_internacao'];
    $uti->usuario_create_uti = $data['usuario_create_uti'];
    $uti->fk_user_uti = $data['fk_user_uti'];
    $uti->glasgow_uti = $data['glasgow_uti'];
    $uti->suporte_vent_uti = $data['suporte_vent_uti'];
    $uti->justifique_uti = $data['justifique_uti'];
    $uti->hora_internacao_uti = $data['hora_internacao_uti'];
    $uti->dist_met_uti = $data['dist_met_uti'];

    $utiDao->create($uti);
}

function processProrrogacoesEntries(
    string $flag,
    ?string $jsonRaw,
    int $visitaId,
    int $fkInternacao,
    prorrogacaoDAO $prorrogacaoDao,
    PDO $conn,
    ?int $fallbackUserId = null,
    bool $clearExisting = false
): void {
    if ($visitaId <= 0) return;
    if ($clearExisting && method_exists($prorrogacaoDao, 'deleteByVisita')) {
        $prorrogacaoDao->deleteByVisita($visitaId);
    }
    if ($flag !== 's') return;
    $decoded = decodeJsonArray($jsonRaw);
    if (!is_array($decoded) || !isset($decoded['prorrogations']) || !is_array($decoded['prorrogations'])) return;
    foreach ($decoded['prorrogations'] as $row) {
        if (!is_array($row)) continue;
        $pr = new prorrogacao();
        $pr->fk_internacao_pror = $fkInternacao;
        $pr->fk_usuario_pror = fullcareResolveUserId(
            $conn,
            toIntOrNull($row['fk_usuario_pror'] ?? null),
            $fallbackUserId
        );
        $pr->acomod1_pror = strOrNull($row['acomod1_pror'] ?? null);
        $pr->prorrog1_ini_pror = strOrNull($row['prorrog1_ini_pror'] ?? null);
        $pr->prorrog1_fim_pror = strOrNull($row['prorrog1_fim_pror'] ?? null);
        $pr->isol_1_pror = strOrNull($row['isol_1_pror'] ?? null);
        $pr->diarias_1 = toIntOrNull($row['diarias_1'] ?? null);
        $pr->fk_visita_pror = $visitaId;
        $prorrogacaoDao->create($pr);
    }
}

// ======================================================================
// Roteamento por tipo
// ======================================================================
$type = filter_input(INPUT_POST, "type"); // create | update | delete
$flowCtx = flowLogStart('process_visita', [
    'type' => $type,
    'id_visita' => $_POST['id_visita'] ?? null,
    'fk_internacao_vis' => $_POST['fk_internacao_vis'] ?? null,
    'fk_usuario_vis' => $_POST['fk_usuario_vis'] ?? null
]);

if ($type === "delete") {
    flowLog($flowCtx, 'delete.start', 'INFO');
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        exit;
    }
    $csrf = (string)filter_input(INPUT_POST, 'csrf', FILTER_UNSAFE_RAW);
    if (!csrf_is_valid($csrf)) {
        flowLog($flowCtx, 'delete.validation', 'WARN', ['error' => 'csrf_invalido']);
        $isAjaxCsrf = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest' || isset($_POST['ajax']);
        if ($isAjaxCsrf) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'CSRF inválido.']);
        } else {
            http_response_code(400);
            $message->setMessage("CSRF inválido.", "error", "back");
        }
        exit;
    }
    $idVisitaDelete = toIntOrNull($_POST['id_visita'] ?? null);
    $redirectRaw    = strOrNull($_POST['redirect'] ?? '');
    $redirectUrl    = $redirectRaw ?: ($BASE_URL . "internacoes/lista");
    $isAjax         = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest' || isset($_POST['ajax']);

    $respond = function (array $payload, bool $ajax) use ($redirectUrl) {
        if ($ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($payload);
        } else {
            header("Location: " . ($payload['redirect'] ?? $redirectUrl));
        }
        exit;
    };

    if (!$idVisitaDelete) {
        flowLog($flowCtx, 'delete.validation', 'WARN', ['error' => 'id_visita_invalido']);
        $respond(['success' => false, 'message' => 'Visita inválida.'], $isAjax);
    }

    $visitaAtualObj = $visitaDao->findById($idVisitaDelete);
    if (!$visitaAtualObj) {
        flowLog($flowCtx, 'delete.validation', 'WARN', ['error' => 'visita_nao_localizada']);
        $respond(['success' => false, 'message' => 'Visita não localizada.'], $isAjax);
    }
    $visitaAtual = get_object_vars($visitaAtualObj);
    if (!empty($visitaAtual['retificado'])) {
        flowLog($flowCtx, 'delete.validation', 'WARN', ['error' => 'visita_ja_retificada', 'id_visita' => $idVisitaDelete]);
        $respond(['success' => false, 'message' => 'Visita já está desativada.'], $isAjax);
    }

    try {
        $ok = $visitaDao->marcarRetificadoPorId($idVisitaDelete);
        if (!$ok) {
            throw new RuntimeException('Falha ao desativar visita.');
        }
        $novoEstado = $visitaAtual;
        $novoEstado['retificado'] = 1;
        $usuarioId = $_SESSION['id_usuario'] ?? null;
        $usuarioNome = $_SESSION['nome_user'] ?? ($_SESSION['email_user'] ?? null);
        $visitaDao->logAlteracao($visitaAtual, $novoEstado, $usuarioId ? (int)$usuarioId : null, $usuarioNome);

        $_SESSION['mensagem'] = "Visita removida com sucesso.";
        $_SESSION['mensagem_tipo'] = "success";

        $respond([
            'success'  => true,
            'redirect' => $redirectUrl,
            'message'  => 'Visita removida com sucesso.'
        ], $isAjax);
    } catch (Throwable $e) {
        flowLog($flowCtx, 'delete.error', 'ERROR', ['error' => $e->getMessage(), 'id_visita' => $idVisitaDelete]);
        error_log("Erro ao remover visita {$idVisitaDelete}: " . $e->getMessage());
        $respond(['success' => false, 'message' => 'Não foi possível remover a visita.'], $isAjax);
    }
}

// ----------------------------------------------------------------------
// CREATE
// ----------------------------------------------------------------------
if ($type === "create") {
    flowLog($flowCtx, 'create.start', 'INFO');

    // ------------------- Campos principais da visita -------------------
    $fk_internacao_vis           = toIntOrNull($_POST['fk_internacao_vis'] ?? null);
    $usuario_create              = strOrNull($_POST['usuario_create'] ?? null);
    $rel_visita_vis              = strOrNull($_POST['rel_visita_vis'] ?? null);
    $acoes_int_vis               = strOrNull($_POST['acoes_int_vis'] ?? null);
    $data_visita_vis             = strOrNull($_POST['data_visita_vis'] ?? null);
    $visita_no_vis               = toIntOrNull($_POST['visita_no_vis'] ?? null);
    $visita_enf_vis              = strOrNull($_POST['visita_enf_vis'] ?? null);
    $visita_med_vis              = strOrNull($_POST['visita_med_vis'] ?? null);
    $visita_auditor_prof_enf     = strOrNull($_POST['visita_auditor_prof_enf'] ?? null);
    $visita_auditor_prof_med     = strOrNull($_POST['visita_auditor_prof_med'] ?? null);
    $fk_usuario_vis              = toIntOrNull($_POST['fk_usuario_vis'] ?? null);
    $timer_vis_raw               = toIntOrNull($_POST['timer_vis'] ?? null);
    $timer_vis                   = $timer_vis_raw !== null ? max(0, $timer_vis_raw) : null;

    // bloco enfermagem (visita)
    $exames_enf                  = strOrNull($_POST['exames_enf'] ?? null);
    $oportunidades_enf           = strOrNull($_POST['oportunidades_enf'] ?? null);
    $programacao_enf             = strOrNull($_POST['programacao_enf'] ?? null);

    // retificar (ATENÇÃO: precisa ser número de visita, não data)
    $retificou                   = toIntOrNull($_POST['retificou'] ?? null);
    $id_visita_edit              = toIntOrNull($_POST['id_visita_edit'] ?? null);

    // json antecedentes
    $jsonAntecRaw                = $_POST['json-antec'] ?? null;
    $tussJsonRaw                 = $_POST['tuss-json'] ?? '';
    $negociacoesJsonRaw          = $_POST['negociacoes_json'] ?? '';

    // ------------------- Tabelas adicionais (flags) --------------------
    $select_tuss                 = strOrNull($_POST['select_tuss'] ?? null);     // 's'/'n'
    $select_gestao               = strOrNull($_POST['select_gestao'] ?? null);   // 's'/'n'
    $select_uti                  = strOrNull($_POST['select_uti'] ?? null);      // 's'/'n'
    $select_prorrog              = strOrNull($_POST['select_prorrog'] ?? null);  // 's'/'n'
    $select_negoc                = strOrNull($_POST['select_negoc'] ?? null);    // 's'/'n'

    // ------------------- Dados auxiliares para módulos ----------------
    $gestaoPostData              = collectGestaoPostData();
    $utiPostData                 = collectUtiPostData($usuario_create ?? '');
    $prorrogacoesJsonRaw         = $_POST['prorrogacoes-json'] ?? '[]';

    // ------------------- IDs auxiliares usados por você ----------------
    $fk_int_visita               = toIntOrNull($_POST['fk_int_visita'] ?? null); // você já envia "próximo id" no form
    $fk_usuario_neg_form         = toIntOrNull($_POST['fk_usuario_neg'] ?? null);
    $resolvedUsuarioVis          = fullcareResolveUserId($conn, $fk_usuario_vis);
    $resolvedUsuarioNeg          = fullcareResolveUserId($conn, $fk_usuario_neg_form, $resolvedUsuarioVis);

    // ------------------- Sanidade mínima ------------------------------
    if (!$fk_internacao_vis) {
        flowLog($flowCtx, 'create.validation', 'WARN', ['error' => 'fk_internacao_vis_invalido']);
        if ($__DEBUG) {
            dbg("ERRO: fk_internacao_vis ausente ou inválido");
            exit;
        }
        $message->setMessage("Informações inválidas da internação.", "error", "back");
        exit;
    }
    if (!$data_visita_vis) {
        flowLog($flowCtx, 'create.validation', 'WARN', ['error' => 'data_visita_vis_vazia']);
        if ($__DEBUG) {
            dbg("ERRO: data_visita_vis vazia");
            exit;
        }
        $message->setMessage("Data da visita é obrigatória.", "error", "back");
        exit;
    }

    // ------------------- Determina se é edição completa ----------------
    $visitaEmEdicao = null;
    if ($id_visita_edit) {
        $visitaObj = $visitaDao->findById((int)$id_visita_edit);
        if ($visitaObj) {
            $visitaEmEdicao = get_object_vars($visitaObj);
        }
    } elseif (is_int($retificou) && $retificou > 0 && $fk_internacao_vis) {
        $visitaOriginal = $visitaDao->findByInternacaoNumero((int)$fk_internacao_vis, (int)$retificou);
        if ($visitaOriginal && isset($visitaOriginal['id_visita'])) {
            $id_visita_edit = (int)$visitaOriginal['id_visita'];
            $visitaEmEdicao = $visitaOriginal;
        }
    }
    $isEditMode = $visitaEmEdicao && $id_visita_edit;

    $data_lancamento_vis = $isEditMode
        ? ($visitaEmEdicao['data_lancamento_vis'] ?? date('Y-m-d H:i:s'))
        : date('Y-m-d H:i:s');

    if ($isEditMode) {
        $dadosAtualizados = [
            'id_visita'               => $id_visita_edit,
            'rel_visita_vis'          => $rel_visita_vis,
            'acoes_int_vis'           => $acoes_int_vis,
            'usuario_create'          => $usuario_create,
            'visita_auditor_prof_med' => $visita_auditor_prof_med,
            'visita_auditor_prof_enf' => $visita_auditor_prof_enf,
            'visita_med_vis'          => $visita_med_vis,
            'visita_enf_vis'          => $visita_enf_vis,
            'visita_no_vis'           => $visitaEmEdicao['visita_no_vis'] ?? $visita_no_vis,
            'fk_usuario_vis'          => $fk_usuario_vis,
            'data_visita_vis'         => $data_visita_vis ?: ($visitaEmEdicao['data_visita_vis'] ?? null),
            'data_lancamento_vis'     => $data_lancamento_vis,
            'data_faturamento_vis'    => $visitaEmEdicao['data_faturamento_vis'] ?? null,
            'faturado_vis'            => $visitaEmEdicao['faturado_vis'] ?? 'n',
            'exames_enf'              => $exames_enf,
            'oportunidades_enf'       => $oportunidades_enf,
            'programacao_enf'         => $programacao_enf,
            'timer_vis'               => $visitaEmEdicao['timer_vis'] ?? $timer_vis
        ];
        try {
            if (!$visitaDao->updateDirect($dadosAtualizados)) {
                throw new RuntimeException('Falha ao atualizar visita.');
            }
            $novoRegistro = array_merge($visitaEmEdicao, $dadosAtualizados);
            $usuarioNomeLog = $_SESSION['nome_user'] ?? ($_SESSION['email_user'] ?? null);
            $visitaDao->logAlteracao($visitaEmEdicao, $novoRegistro, $fk_usuario_vis, $usuarioNomeLog);
            processTussEntries($select_tuss ?? '', $tussJsonRaw, $id_visita_edit, $fk_internacao_vis, $tussDao, $resolvedUsuarioVis);
            processProrrogacoesEntries($select_prorrog ?? '', $prorrogacoesJsonRaw, $id_visita_edit, $fk_internacao_vis, $prorrogacaoDao, $conn, $resolvedUsuarioVis, true);
            $prorrogAltaPayload = fullcare_prorrog_alta_payload_from_post(
                $_POST,
                'normalizeDateTimeInput',
                $resolvedUsuarioVis,
                $_SESSION['email_user'] ?? $usuario_create ?? null
            );
            if (($select_prorrog ?? '') === 's' && $prorrogAltaPayload) {
                fullcare_upsert_prorrog_alta($conn, (int)$fk_internacao_vis, $prorrogAltaPayload);
            }
            $autoNegociacoesProrrog = buildAutoNegociacoesFromProrrog($select_prorrog ?? '', $prorrogacoesJsonRaw, $resolvedUsuarioNeg ?? $resolvedUsuarioVis);
            $processaNegociacoes = (($select_negoc ?? '') === 's' || !empty($autoNegociacoesProrrog)) ? 's' : 'n';
            processNegociacoesEntries($processaNegociacoes, $negociacoesJsonRaw, $id_visita_edit, $fk_internacao_vis, $negociacaoDao, $conn, $resolvedUsuarioNeg ?? $resolvedUsuarioVis, $autoNegociacoesProrrog);
            processGestaoData($select_gestao ?? '', $gestaoPostData, $id_visita_edit, $fk_internacao_vis, $gestaoDao, true);
            processUtiData($select_uti ?? '', $utiPostData, $id_visita_edit, $fk_internacao_vis, $utiDao, true);
            $cronicosAtualizados = cc_process_visita_cronicos($conn, $internacaoDao, $fk_internacao_vis, $rel_visita_vis, 'edição do relatório da visita');
            $cronicosAntecedentes = cc_process_visita_cronicos_from_antecedentes($conn, $internacaoDao, $fk_internacao_vis, 'antecedentes já cadastrados do paciente');
            flowLog($flowCtx, 'create.edit_mode.finish', 'INFO', [
                'id_visita' => $id_visita_edit,
                'fk_internacao_vis' => $fk_internacao_vis,
                'condicoes_cronicas' => $cronicosAtualizados,
                'condicoes_antecedentes' => $cronicosAntecedentes
            ]);
        } catch (Throwable $e) {
            flowLog($flowCtx, 'create.edit_mode.error', 'ERROR', ['error' => $e->getMessage(), 'id_visita' => $id_visita_edit]);
            error_log("Erro ao atualizar visita: " . $e->getMessage());
            if ($__DEBUG) {
                dbg("ERRO update visita", $e->getMessage());
                exit;
            }
            $message->setMessage("Erro ao atualizar visita.", "error", "back");
            exit;
        }

        if ($__DEBUG) {
            dbg("VISITA editada", $dadosAtualizados);
            exit;
        }
        header("Location: internacoes/lista");
        exit;
    }

    // ------------------- Monta objeto VISITA (novo registro) ----------
    $visita                           = new visita();
    $visita->fk_internacao_vis        = $fk_internacao_vis;
    $visita->usuario_create           = $usuario_create;
    $visita->rel_visita_vis           = $rel_visita_vis;
    $visita->acoes_int_vis            = $acoes_int_vis;
    $visita->data_visita_vis          = $data_visita_vis;
    $visita->data_lancamento_vis      = $data_lancamento_vis;
    $visita->data_faturamento_vis     = null;
    $visita->visita_no_vis            = $visita_no_vis;
    $visita->visita_enf_vis           = $visita_enf_vis;
    $visita->visita_med_vis           = $visita_med_vis;
    $visita->visita_auditor_prof_enf  = $visita_auditor_prof_enf;
    $visita->visita_auditor_prof_med  = $visita_auditor_prof_med;
    $visita->fk_usuario_vis           = $resolvedUsuarioVis;
    $visita->faturado_vis             = 'n';

    // enfermagem (texto)
    $visita->exames_enf               = $exames_enf;
    $visita->oportunidades_enf        = $oportunidades_enf;
    $visita->programacao_enf          = $programacao_enf;
    $visita->timer_vis                = $timer_vis;

    // ------------------- Persistência VISITA --------------------------
    try {
        $novoIdVisita = $visitaDao->create($visita);
        processTussEntries($select_tuss ?? '', $tussJsonRaw, $novoIdVisita, $fk_internacao_vis, $tussDao, $resolvedUsuarioVis);
        processProrrogacoesEntries($select_prorrog ?? '', $prorrogacoesJsonRaw, $novoIdVisita, $fk_internacao_vis, $prorrogacaoDao, $conn, $resolvedUsuarioVis);
        $prorrogAltaPayload = fullcare_prorrog_alta_payload_from_post(
            $_POST,
            'normalizeDateTimeInput',
            $resolvedUsuarioVis,
            $_SESSION['email_user'] ?? $usuario_create ?? null
        );
        if (($select_prorrog ?? '') === 's' && $prorrogAltaPayload) {
            fullcare_upsert_prorrog_alta($conn, (int)$fk_internacao_vis, $prorrogAltaPayload);
        }
        $autoNegociacoesProrrog = buildAutoNegociacoesFromProrrog($select_prorrog ?? '', $prorrogacoesJsonRaw, $resolvedUsuarioNeg ?? $resolvedUsuarioVis);
        $processaNegociacoes = (($select_negoc ?? '') === 's' || !empty($autoNegociacoesProrrog)) ? 's' : 'n';
        processNegociacoesEntries($processaNegociacoes, $negociacoesJsonRaw, $novoIdVisita, $fk_internacao_vis, $negociacaoDao, $conn, $resolvedUsuarioNeg ?? $resolvedUsuarioVis, $autoNegociacoesProrrog);
        processGestaoData($select_gestao ?? '', $gestaoPostData, $novoIdVisita, $fk_internacao_vis, $gestaoDao);
        processUtiData($select_uti ?? '', $utiPostData, $novoIdVisita, $fk_internacao_vis, $utiDao);
        $cronicosCriados = cc_process_visita_cronicos($conn, $internacaoDao, $fk_internacao_vis, $rel_visita_vis, 'relatório da visita');
        $cronicosAntecedentes = cc_process_visita_cronicos_from_antecedentes($conn, $internacaoDao, $fk_internacao_vis, 'antecedentes já cadastrados do paciente');
        flowLog($flowCtx, 'create.finish', 'INFO', [
            'id_visita' => $novoIdVisita,
            'fk_internacao_vis' => $fk_internacao_vis,
            'condicoes_cronicas' => $cronicosCriados,
            'condicoes_antecedentes' => $cronicosAntecedentes
        ]);
        if ($__DEBUG) dbg("VISITA criada", $visita);
    } catch (Throwable $e) {
        flowLog($flowCtx, 'create.error', 'ERROR', ['error' => $e->getMessage(), 'fk_internacao_vis' => $fk_internacao_vis]);
        error_log("Erro ao criar visita: " . $e->getMessage());
        if ($__DEBUG) {
            dbg("ERRO create visita", $e->getMessage());
            exit;
        }
        $message->setMessage("Erro ao salvar visita.", "error", "back");
        exit;
    }

    // ------------------- Antecedentes (JSON) --------------------------
    if ($jsonAntecRaw) {
        $antecArr = json_decode($jsonAntecRaw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Antecedentes JSON inválido: " . json_last_error_msg());
            if ($__DEBUG) dbg("JSON Antec inválido", json_last_error_msg(), $jsonAntecRaw);
        } elseif (is_array($antecArr)) {
            foreach ($antecArr as $row) {
                try {
                    // O seu DAO possui buildintern_antec($data)
                    $intern_antec = $internAntecedenteDao->buildintern_antec($row);
                    $internAntecedenteDao->create($intern_antec);
                } catch (Throwable $e) {
                    error_log("Erro ao salvar antecedente: " . $e->getMessage());
                    if ($__DEBUG) dbg("Antecedente erro", $e->getMessage(), $row);
                }
            }
        }
    }

    // ------------------- FIM CREATE ---------------------
    if ($__DEBUG) {
        dbg("CREATE concluído. Redirecionamento suprimido no debug.");
        exit;
    }
    header("Location: internacoes/lista");
    exit;
}

// ----------------------------------------------------------------------
// UPDATE (ajuste simples como no seu original)
// ----------------------------------------------------------------------
if ($type === "update") {
    flowLog($flowCtx, 'update.start', 'INFO');

    try {
        $id_visita    = toIntOrNull($_POST['id_visita'] ?? null);
        $fk_hospital  = toIntOrNull($_POST['fk_hospital'] ?? null);
        $valor_diaria = strOrNull($_POST['valor_diaria'] ?? null);

        if (!$id_visita) {
            if ($__DEBUG) {
                dbg("UPDATE: id_visita inválido");
                exit;
            }
            $message->setMessage("Visita inválida.", "error", "back");
            exit;
        }

        $visitaEncontrada = $visitaDao->findById($id_visita);
        if (!$visitaEncontrada) {
            if ($__DEBUG) {
                dbg("UPDATE: visita não encontrada");
                exit;
            }
            $message->setMessage("Visita não encontrada.", "error", "back");
            exit;
        }

        // Mantém o seu padrão de update via array
        $visita = is_array($visitaEncontrada)
            ? $visitaEncontrada
            : get_object_vars($visitaEncontrada);
        $visita['id_visita']    = $id_visita;
        $visita['fk_hospital']  = $fk_hospital;
        $visita['valor_diaria'] = $valor_diaria;

        $visitaDao->update($visita);
        flowLog($flowCtx, 'update.finish', 'INFO', ['id_visita' => $id_visita]);

        if ($__DEBUG) {
            dbg("UPDATE ok", $visita);
            exit;
        }

        include_once('list_visita.php');
        exit;
    } catch (Throwable $e) {
        flowLog($flowCtx, 'update.error', 'ERROR', ['error' => $e->getMessage()]);
        error_log("UPDATE visita erro: " . $e->getMessage());
        if ($__DEBUG) {
            dbg("UPDATE EXCEPTION", $e->getMessage());
            exit;
        }
        $message->setMessage("Erro ao atualizar.", "error", "back");
        exit;
    }
}

// ----------------------------------------------------------------------
// Fallback: tipo desconhecido
// ----------------------------------------------------------------------
if ($__DEBUG) {
    dbg("Nenhuma ação executada. type=", $type);
    exit;
}
$message->setMessage("Ação inválida.", "error", "back");
exit;
