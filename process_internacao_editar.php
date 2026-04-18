<?php

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

/*──────────────────────────────────────────────────────
  process_internacao_editar.php – fluxo UPDATE / CREATE
────────────────────────────────────────────────────────*/
require_once 'globals.php';
require_once 'db.php';
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* Models & DAOs */
require_once 'models/internacao.php';
require_once 'dao/internacaoDao.php';
require_once 'models/detalhes.php';
require_once 'dao/detalhesDao.php';
require_once 'models/uti.php';
require_once 'dao/utiDao.php';
require_once 'models/negociacao.php';
require_once 'dao/negociacaoDao.php';
require_once 'models/acomodacao.php';
require_once 'dao/acomodacaoDao.php';
require_once 'models/prorrogacao.php';
require_once 'dao/prorrogacaoDao.php';
require_once 'models/tuss.php';
require_once 'dao/tussDao.php';
require_once 'models/gestao.php';
require_once 'dao/gestaoDao.php';

/*──────── helpers ────────*/
if (!function_exists('decodeArray')) {
    function decodeArray(?string $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') return [];
        $arr = json_decode($raw, true);
        return is_array($arr) ? $arr : [];
    }
}
if (!function_exists('postArrayValues')) {
    function postArrayValues(string $key): array
    {
        $val = $_POST[$key] ?? [];
        if (is_array($val)) {
            return $val;
        }
        if ($val === null || $val === '') {
            return [];
        }
        return [$val];
    }
}
function limpa(?string $t, int $lim = 5000): string
{
    $t = htmlspecialchars($t ?? '', ENT_QUOTES, 'UTF-8');
    return substr($t, 0, $lim);
}
if (!function_exists('normalizeDateTimeInput')) {
    function normalizeDateTimeInput($value)
    {
        if ($value === null) return null;
        $value = trim((string)$value);
        if ($value === '') return null;
        $formats = [
            ['fmt' => 'Y-m-d\\TH:i:s', 'has_time' => true],
            ['fmt' => 'Y-m-d\\TH:i',   'has_time' => true],
            ['fmt' => 'Y-m-d H:i:s',   'has_time' => true],
            ['fmt' => 'Y-m-d H:i',     'has_time' => true],
            ['fmt' => 'd/m/Y H:i:s',   'has_time' => true],
            ['fmt' => 'd/m/Y H:i',     'has_time' => true],
            ['fmt' => 'Y-m-d',         'has_time' => false],
            ['fmt' => 'd/m/Y',         'has_time' => false],
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
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
}

if (!function_exists('internacaoUserExists')) {
    function internacaoUserExists(PDO $conn, ?int $userId): bool
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
}

if (!function_exists('resolveInternacaoEditUserId')) {
    function resolveInternacaoEditUserId(PDO $conn, ?int $postedUserId, ?int $currentUserId, ?int $sessionUserId): ?int
    {
        $candidates = [
            'post' => $postedUserId,
            'current' => $currentUserId,
            'session' => $sessionUserId,
        ];

        foreach ($candidates as $source => $candidate) {
            $candidate = ($candidate !== null && $candidate > 0) ? (int)$candidate : null;
            if ($candidate !== null && internacaoUserExists($conn, $candidate)) {
                if ($source !== 'post' && $postedUserId !== null && $postedUserId > 0 && $postedUserId !== $candidate) {
                    internacaoEditarDebugLog(
                        'FK_USUARIO fallback source=' . $source
                        . ' posted=' . (int)$postedUserId
                        . ' resolved=' . $candidate
                    );
                }
                return $candidate;
            }
        }

        if ($postedUserId !== null && $postedUserId > 0) {
            internacaoEditarDebugLog('FK_USUARIO invalid posted=' . (int)$postedUserId . ' resolved=NULL');
        }

        return null;
    }
}

if (!function_exists('internacaoEditarDebugLog')) {
    function internacaoEditarDebugLog(string $message): void
    {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents(__DIR__ . '/logs/process_internacao_editar.debug.log', $line, FILE_APPEND);
    }
}
if (!function_exists('normalizeAcomodacaoNegociacao')) {
    function normalizeAcomodacaoNegociacao(?string $value): string
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
}
if (!function_exists('calcNegotiationSavingValue')) {
    function calcNegotiationSavingValue(PDO $conn, int $hospitalId, ?string $tipo, ?string $trocaDe, ?string $trocaPara, int $qtd): float
    {
        static $cache = [];
        if ($hospitalId <= 0 || $qtd <= 0) {
            return 0.0;
        }
        if (!isset($cache[$hospitalId])) {
            $stmt = $conn->prepare("SELECT acomodacao_aco, valor_aco FROM tb_acomodacao WHERE fk_hospital = :id");
            $stmt->bindValue(':id', $hospitalId, PDO::PARAM_INT);
            $stmt->execute();
            $map = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $map[normalizeAcomodacaoNegociacao($row['acomodacao_aco'] ?? '')] = (float)($row['valor_aco'] ?? 0);
            }
            $cache[$hospitalId] = $map;
        }
        $map = $cache[$hospitalId];
        $de = (float)($map[normalizeAcomodacaoNegociacao($trocaDe)] ?? 0);
        $para = (float)($map[normalizeAcomodacaoNegociacao($trocaPara)] ?? 0);
        $tipoNorm = mb_strtoupper(trim((string)$tipo), 'UTF-8');

        if (strpos($tipoNorm, 'TROCA') === 0) {
            return ($de - $para) * $qtd;
        }
        if (strpos($tipoNorm, '1/2 DIARIA') !== false) {
            return ($de / 2) * $qtd;
        }
        return $de * $qtd;
    }
}

/*──────── session/inputs mínimos ────────*/
$idInternacao = (int) ($_POST['id_internacao'] ?? 0);
if (!$idInternacao) {
    die("ID de internação ausente");
}
$type = filter_input(INPUT_POST, 'type');
$idInt = filter_input(INPUT_POST, 'id_internacao', FILTER_VALIDATE_INT);

/* Instância de DAOs */
$internacaoDao = new InternacaoDAO($conn, $BASE_URL);
$detalhesDao   = new detalhesDAO($conn, $BASE_URL);
$utiDao        = new utiDAO($conn, $BASE_URL);
$negDao        = new negociacaoDAO($conn, $BASE_URL);
$prorrogDao    = new prorrogacaoDAO($conn, $BASE_URL);
$tussDao       = new tussDAO($conn, $BASE_URL);
$gestaoDao     = new gestaoDAO($conn, $BASE_URL);

internacaoEditarDebugLog(
    'START id_int=' . (int)$idInternacao
    . ' type=' . (string)($type ?? '')
    . ' select_gestao=' . (string)($_POST['select_gestao'] ?? '')
    . ' evento_adverso_ges=' . (string)($_POST['evento_adverso_ges'] ?? '')
    . ' id_gestao=' . (string)($_POST['id_gestao'] ?? '')
);

try {
    $conn->beginTransaction();

    /*──────── INTERNACAO ────────*/
    $currentIntern = $internacaoDao->findById($idInt);
    if (!$currentIntern) {
        throw new RuntimeException('Internação não encontrada.');
    }

    $int = $currentIntern;
    $int->id_internacao = $idInt;

    $fkHospital = filter_input(INPUT_POST, 'fk_hospital_int', FILTER_VALIDATE_INT);
    if ($fkHospital !== null && $fkHospital !== false) {
        $int->fk_hospital_int = $fkHospital;
    }

    $fkPaciente = filter_input(INPUT_POST, 'fk_paciente_int', FILTER_VALIDATE_INT);
    if ($fkPaciente !== null && $fkPaciente !== false) {
        $int->fk_paciente_int = $fkPaciente;
    }

    $fkPatologia2 = filter_input(INPUT_POST, 'fk_patologia2', FILTER_VALIDATE_INT);
    if ($fkPatologia2 !== null && $fkPatologia2 !== false) {
        $int->fk_patologia2 = $fkPatologia2 ?: $int->fk_patologia2;
    }

    $fkPatologia = filter_input(INPUT_POST, 'fk_patologia_int', FILTER_VALIDATE_INT);
    if ($fkPatologia !== null && $fkPatologia !== false) {
        $int->fk_patologia_int = $fkPatologia ?: $int->fk_patologia_int;
    }

    $fkCid = filter_input(INPUT_POST, 'fk_cid_int', FILTER_VALIDATE_INT);
    if ($fkCid !== null && $fkCid !== false) {
        $int->fk_cid_int = $fkCid ?: $int->fk_cid_int;
    }

    $int->internado_int       = filter_input(INPUT_POST, 'internado_int')       ?? $int->internado_int;
    $int->modo_internacao_int = filter_input(INPUT_POST, 'modo_internacao_int') ?? $int->modo_internacao_int;
    $int->tipo_admissao_int   = filter_input(INPUT_POST, 'tipo_admissao_int')   ?? $int->tipo_admissao_int;
    $int->grupo_patologia_int = filter_input(INPUT_POST, 'grupo_patologia_int') ?? $int->grupo_patologia_int;

    $dataVisita = filter_input(INPUT_POST, 'data_visita_int');
    if ($dataVisita !== null) {
        $int->data_visita_int = ($dataVisita === '') ? null : $dataVisita;
    }

    $dataIntern = filter_input(INPUT_POST, 'data_intern_int');
    if ($dataIntern !== null) {
        $int->data_intern_int = ($dataIntern === '') ? null : $dataIntern;
    }

    $int->especialidade_int = filter_input(INPUT_POST, 'especialidade_int') ?? $int->especialidade_int;
    $int->titular_int       = filter_input(INPUT_POST, 'titular_int')       ?? $int->titular_int;
    $int->crm_int           = filter_input(INPUT_POST, 'crm_int')           ?? $int->crm_int;
    $int->acomodacao_int    = filter_input(INPUT_POST, 'acomodacao_int')    ?? $int->acomodacao_int;

    $senha = filter_input(INPUT_POST, 'senha_int', FILTER_UNSAFE_RAW);
    if ($senha !== null) {
        $int->senha_int = $senha;
    }

    $int->rel_int         = limpa(filter_input(INPUT_POST, 'rel_int'));
    $int->acoes_int       = limpa(filter_input(INPUT_POST, 'acoes_int'));
    $int->programacao_int = limpa(filter_input(INPUT_POST, 'programacao_int'));

    $int->primeira_vis_int = filter_input(INPUT_POST, 'primeira_vis_int') ?? $int->primeira_vis_int;
    $int->visita_no_int    = filter_input(INPUT_POST, 'visita_no_int')    ?? $int->visita_no_int;
    $int->visita_enf_int   = filter_input(INPUT_POST, 'visita_enf_int')   ?? $int->visita_enf_int;
    $int->visita_med_int   = filter_input(INPUT_POST, 'visita_med_int')   ?? $int->visita_med_int;

    $int->visita_auditor_prof_med = filter_input(INPUT_POST, 'visita_auditor_prof_med') ?? $int->visita_auditor_prof_med;
    $int->visita_auditor_prof_enf = filter_input(INPUT_POST, 'visita_auditor_prof_enf') ?? $int->visita_auditor_prof_enf;

    $fkUsuario = filter_input(INPUT_POST, 'fk_usuario_int', FILTER_VALIDATE_INT);
    $resolvedFkUsuario = resolveInternacaoEditUserId(
        $conn,
        ($fkUsuario !== false ? $fkUsuario : null),
        isset($currentIntern->fk_usuario_int) ? (int)$currentIntern->fk_usuario_int : null,
        isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : null
    );
    $int->fk_usuario_int = $resolvedFkUsuario;

    $int->censo_int             = filter_input(INPUT_POST, 'censo_int')             ?? $int->censo_int;
    $int->origem_int            = filter_input(INPUT_POST, 'origem_int')            ?? $int->origem_int;
    $int->int_pertinente_int    = filter_input(INPUT_POST, 'int_pertinente_int')    ?? $int->int_pertinente_int;
    $int->rel_pertinente_int    = limpa(filter_input(INPUT_POST, 'rel_pertinente_int'));
    $int->hora_intern_int       = filter_input(INPUT_POST, 'hora_intern_int')       ?? $int->hora_intern_int;

    $numAtendimento = filter_input(INPUT_POST, 'num_atendimento_int', FILTER_VALIDATE_INT);
    if ($numAtendimento !== null && $numAtendimento !== false) {
        $int->num_atendimento_int = $numAtendimento;
    }

    $timerRaw = filter_input(INPUT_POST, 'timer_int', FILTER_VALIDATE_INT);
    if ($timerRaw !== null && $timerRaw !== false) {
        $int->timer_int = max(0, $timerRaw);
    }

    $internacaoDao->update($int);

    /*──────── DETALHES ────────*/
    if (filter_input(INPUT_POST, 'select_detalhes') === 's') {
        $fkVis      = filter_input(INPUT_POST, 'fk_vis_det', FILTER_VALIDATE_INT);
        $idDetalhes = filter_input(INPUT_POST, 'id_detalhes', FILTER_VALIDATE_INT);

        $d = new detalhes();
        $d->id_detalhes               = $idDetalhes;
        $d->fk_int_det                = $idInt;
        $d->fk_vis_det                = $fkVis;
        $d->curativo_det              = filter_input(INPUT_POST, 'curativo_det');
        $d->dieta_det                 = filter_input(INPUT_POST, 'dieta_det');
        $d->nivel_consc_det           = filter_input(INPUT_POST, 'nivel_consc_det');
        $d->oxig_det                  = filter_input(INPUT_POST, 'oxig_det');
        $d->oxig_uso_det              = filter_input(INPUT_POST, 'oxig_uso_det');
        $d->qt_det                    = filter_input(INPUT_POST, 'qt_det');
        $d->dispositivo_det           = filter_input(INPUT_POST, 'dispositivo_det');
        $d->atb_det                   = filter_input(INPUT_POST, 'atb_det');
        $d->atb_uso_det               = filter_input(INPUT_POST, 'atb_uso_det');
        $d->acamado_det               = filter_input(INPUT_POST, 'acamado_det');
        $d->exames_det                = limpa(filter_input(INPUT_POST, 'exames_det'));
        $d->oportunidades_det         = limpa(filter_input(INPUT_POST, 'oportunidades_det'));
        $d->tqt_det                   = filter_input(INPUT_POST, 'tqt_det');
        $d->svd_det                   = filter_input(INPUT_POST, 'svd_det');
        $d->gtt_det                   = filter_input(INPUT_POST, 'gtt_det');
        $d->dreno_det                 = filter_input(INPUT_POST, 'dreno_det');
        $d->rt_det                    = filter_input(INPUT_POST, 'rt_det');
        $d->lesoes_pele_det           = filter_input(INPUT_POST, 'lesoes_pele_det');
        $d->medic_alto_custo_det      = filter_input(INPUT_POST, 'medic_alto_custo_det');
        $d->qual_medicamento_det      = filter_input(INPUT_POST, 'qual_medicamento_det');
        $d->hemoderivados_det         = filter_input(INPUT_POST, 'hemoderivados_det');
        $d->dialise_det               = filter_input(INPUT_POST, 'dialise_det');
        $d->oxigenio_hiperbarica_det  = filter_input(INPUT_POST, 'oxigenio_hiperbarica_det');
        $d->paliativos_det            = filter_input(INPUT_POST, 'paliativos_det');
        $d->braden_det                = filter_input(INPUT_POST, 'braden_det');
        $d->liminar_det               = filter_input(INPUT_POST, 'liminar_det');
        $d->parto_det                 = filter_input(INPUT_POST, 'parto_det');

        if (!$idDetalhes) {
            unset($d->id_detalhes);
            $detalhesDao->create($d);
        } else {
            $detalhesDao->update($d);
        }
    }

    /*──────── UTI (CREATE/UPDATE) ────────*/
    if (filter_input(INPUT_POST, 'select_uti') === 's') {
        $u = new uti();
        $u->id_uti              = filter_input(INPUT_POST, 'id_uti', FILTER_VALIDATE_INT);
        $u->fk_internacao_uti   = filter_input(INPUT_POST, 'fk_internacao_uti', FILTER_VALIDATE_INT);
        $u->hora_internacao_uti = filter_input(INPUT_POST, 'hora_internacao_uti');
        $u->data_internacao_uti = filter_input(INPUT_POST, 'data_internacao_uti');
        $u->vm_uti              = filter_input(INPUT_POST, 'vm_uti');
        $u->dva_uti             = filter_input(INPUT_POST, 'dva_uti');
        $u->motivo_uti          = filter_input(INPUT_POST, 'motivo_uti');
        $u->rel_uti             = limpa(filter_input(INPUT_POST, 'rel_uti'));
        $u->just_uti            = filter_input(INPUT_POST, 'just_uti');
        $u->saps_uti            = filter_input(INPUT_POST, 'saps_uti');
        $u->score_uti           = filter_input(INPUT_POST, 'score_uti');
        $u->criterios_uti       = filter_input(INPUT_POST, 'criterio_uti');
        $u->internado_uti       = filter_input(INPUT_POST, 'internado_uti');

        if (!empty($u->id_uti)) {
            $utiDao->update($u);
        } else {
            $utiDao->create($u);
        }
    }

    /*──────── GESTAO (CREATE/UPDATE) ────────*/
    $selectGestaoPost = (string)($_POST['select_gestao'] ?? '');
    $eventoAdversoPost = strtolower(trim((string)($_POST['evento_adverso_ges'] ?? 'n')));
    $tipoEventoAdversoPost = trim((string)($_POST['tipo_evento_adverso_gest'] ?? ''));
    $relEventoAdversoPost = trim((string)($_POST['rel_evento_adverso_ges'] ?? ''));
    $deveSalvarGestao = (
        $selectGestaoPost === 's'
        || $eventoAdversoPost === 's'
        || $tipoEventoAdversoPost !== ''
        || $relEventoAdversoPost !== ''
    );

    if ($deveSalvarGestao) {
        $idGestao = filter_input(INPUT_POST, 'id_gestao', FILTER_VALIDATE_INT);

        $gestao = new gestao();
        if ($idGestao) $gestao->id_gestao = $idGestao;

        $gestao->fk_internacao_ges              = $idInt;
        $gestao->fk_visita_ges                  = filter_input(INPUT_POST, 'fk_visita_ges', FILTER_VALIDATE_INT);
        $gestao->alto_custo_ges                 = filter_input(INPUT_POST, 'alto_custo_ges');
        $gestao->rel_alto_custo_ges             = limpa(filter_input(INPUT_POST, 'rel_alto_custo_ges'));
        $gestao->opme_ges                       = filter_input(INPUT_POST, 'opme_ges');
        $gestao->rel_opme_ges                   = limpa(filter_input(INPUT_POST, 'rel_opme_ges'));
        $gestao->home_care_ges                  = filter_input(INPUT_POST, 'home_care_ges');
        $gestao->rel_home_care_ges              = limpa(filter_input(INPUT_POST, 'rel_home_care_ges'));
        $gestao->desospitalizacao_ges           = filter_input(INPUT_POST, 'desospitalizacao_ges');
        $gestao->rel_desospitalizacao_ges       = limpa(filter_input(INPUT_POST, 'rel_desospitalizacao_ges'));
        $gestao->evento_adverso_ges             = $eventoAdversoPost;
        $gestao->rel_evento_adverso_ges         = limpa($relEventoAdversoPost);
        $gestao->tipo_evento_adverso_gest       = $tipoEventoAdversoPost;
        $gestao->evento_sinalizado_ges          = filter_input(INPUT_POST, 'evento_sinalizado_ges');
        $gestao->evento_discutido_ges           = filter_input(INPUT_POST, 'evento_discutido_ges');
        $gestao->evento_negociado_ges           = filter_input(INPUT_POST, 'evento_negociado_ges');
        $gestao->evento_prorrogar_ges           = filter_input(INPUT_POST, 'evento_prorrogar_ges');
        $gestao->evento_fech_ges                = filter_input(INPUT_POST, 'evento_fech_ges');
        $gestao->evento_valor_negoc_ges         = filter_input(INPUT_POST, 'evento_valor_negoc_ges');
        $gestao->evento_retorno_qual_hosp_ges   = filter_input(INPUT_POST, 'evento_retorno_qual_hosp_ges');
        $gestao->evento_classificado_hospital_ges = filter_input(INPUT_POST, 'evento_classificado_hospital_ges');
        $gestao->evento_data_ges                = filter_input(INPUT_POST, 'evento_data_ges') ?: null;
        $gestao->evento_encerrar_ges            = filter_input(INPUT_POST, 'evento_encerrar_ges');
        $gestao->evento_impacto_financ_ges      = filter_input(INPUT_POST, 'evento_impacto_financ_ges');
        $gestao->evento_prolongou_internacao_ges = filter_input(INPUT_POST, 'evento_prolongou_internacao_ges');
        $gestao->evento_concluido_ges           = filter_input(INPUT_POST, 'evento_concluido_ges');
        $gestao->evento_classificacao_ges       = filter_input(INPUT_POST, 'evento_classificacao_ges');
        $gestao->fk_user_ges                    = filter_input(INPUT_POST, 'fk_user_ges', FILTER_VALIDATE_INT)
            ?? ($_SESSION['id_usuario'] ?? null);

        if ($gestao->evento_encerrar_ges === 's' || $gestao->evento_fech_ges === 's') {
            Gate::enforceAction($conn, $BASE_URL, 'close_management', 'Você não tem permissão para fechar gestão.');
        }

        if ($idGestao) {
            $gestaoDao->update($gestao);
            internacaoEditarDebugLog('GESTAO update ok id_int=' . (int)$idInt . ' id_gestao=' . (int)$idGestao . ' evento=' . (string)$gestao->evento_adverso_ges);
        } else {
            $gestaoDao->create($gestao);
            internacaoEditarDebugLog('GESTAO create ok id_int=' . (int)$idInt . ' evento=' . (string)$gestao->evento_adverso_ges);
        }
    } else {
        internacaoEditarDebugLog('GESTAO skip id_int=' . (int)$idInt . ' select_gestao=' . (string)$selectGestaoPost . ' evento=' . (string)$eventoAdversoPost);
    }

    /*──────── NEGOCIAÇÕES (UPDATE/CREATE/DELETE) ────────*/
    if (filter_input(INPUT_POST, 'select_negoc') === 's') {
        $existing    = $negDao->findByInternacao($idInt);
        $existingIds = array_map(fn(array $r) => (int) $r['id_negociacao'], $existing);

        $negArray    = decodeArray($_POST['negociacoes_json'] ?? null);  // sempre array
        if (!$negArray) {
            $ids = postArrayValues('neg_id');
            $tipos = postArrayValues('tipo_negociacao');
            $inicios = postArrayValues('data_inicio_neg');
            $fins = postArrayValues('data_fim_neg');
            $trocasDe = postArrayValues('troca_de');
            $trocasPara = postArrayValues('troca_para');
            $qtds = postArrayValues('qtd');
            $savings = postArrayValues('saving');
            $rows = max(
                count($ids),
                count($tipos),
                count($inicios),
                count($fins),
                count($trocasDe),
                count($trocasPara),
                count($qtds),
                count($savings)
            );
            for ($i = 0; $i < $rows; $i++) {
                $tipoLinha = trim((string)($tipos[$i] ?? ''));
                $deLinha = trim((string)($trocasDe[$i] ?? ''));
                $paraLinha = trim((string)($trocasPara[$i] ?? ''));
                $qtdLinha = (int)($qtds[$i] ?? 0);
                if ($tipoLinha === '' && $deLinha === '' && $paraLinha === '' && $qtdLinha <= 0) {
                    continue;
                }
                $negArray[] = [
                    'id' => (int)($ids[$i] ?? 0),
                    'tipo_negociacao' => $tipoLinha,
                    'data_inicio_neg' => $inicios[$i] ?? null,
                    'data_fim_neg' => $fins[$i] ?? null,
                    'troca_de' => $deLinha,
                    'troca_para' => $paraLinha,
                    'qtd' => $qtdLinha,
                    'saving' => (float)($savings[$i] ?? 0),
                ];
            }
            internacaoEditarDebugLog('NEGOC fallback post rows=' . count($negArray) . ' id_int=' . (int)$idInt);
        } else {
            internacaoEditarDebugLog('NEGOC json rows=' . count($negArray) . ' id_int=' . (int)$idInt);
        }

        $deleteIds = [];
        $deleteArray = decodeArray($_POST['negociacoes_delete_ids'] ?? null);
        foreach ($deleteArray as $deleteId) {
            $deleteId = (int)$deleteId;
            if ($deleteId > 0) {
                $deleteIds[$deleteId] = $deleteId;
            }
        }
        foreach ((array)($_POST['neg_delete_ids'] ?? []) as $deleteId) {
            $deleteId = (int)$deleteId;
            if ($deleteId > 0) {
                $deleteIds[$deleteId] = $deleteId;
            }
        }

        $postedIds = [];
        foreach ($negArray as $n) {
            if (!empty($n['id'])) $postedIds[] = (int) $n['id'];
        }

        $toDelete = array_unique(array_merge(
            array_diff($existingIds, $postedIds),
            array_values($deleteIds)
        ));
        foreach ($toDelete as $delId) {
            $negDao->destroy($delId);
            error_log("[NEGOCIAÇÃO] Deletada ID $delId");
        }

        foreach ($negArray as $nData) {
            $negIdAtual = !empty($nData['id']) ? (int)$nData['id'] : 0;
            if ($negIdAtual > 0 && isset($deleteIds[$negIdAtual])) {
                continue;
            }

            $neg = new negociacao();
            if ($negIdAtual > 0) $neg->id_negociacao = $negIdAtual;

            $neg->fk_id_int       = $idInt;
            $neg->fk_usuario_neg  = resolveInternacaoEditUserId(
                $conn,
                isset($nData['fk_usuario_neg']) ? (int)$nData['fk_usuario_neg'] : null,
                isset($currentIntern->fk_usuario_int) ? (int)$currentIntern->fk_usuario_int : null,
                isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : null
            );
            $neg->troca_de        = $nData['troca_de']        ?? '';
            $neg->troca_para      = $nData['troca_para']      ?? '';
            $neg->qtd             = (int)($nData['qtd']       ?? 0);
            $neg->saving          = calcNegotiationSavingValue(
                $conn,
                (int)($currentIntern->fk_hospital_int ?? 0),
                $nData['tipo_negociacao'] ?? '',
                $nData['troca_de'] ?? '',
                $nData['troca_para'] ?? '',
                (int)($nData['qtd'] ?? 0)
            );
            $neg->data_inicio_neg = $nData['data_inicio_neg'] ?? null;
            $neg->data_fim_neg    = $nData['data_fim_neg']    ?? null;
            $neg->tipo_negociacao = $nData['tipo_negociacao'] ?? '';

            if (!empty($neg->id_negociacao)) {
                $negDao->update($neg);
            } else {
                $negDao->create($neg);
            }
        }
    }

    /*──────── PRORROGAÇÕES (UPDATE/CREATE/DELETE) ────────*/
    if (filter_input(INPUT_POST, 'select_prorrog') === 's') {
        $existing    = $prorrogDao->selectInternacaoProrrog($idInt);
        $existingIds = array_map(fn($r) => (int) $r['id_prorrogacao'], $existing);

        $prArray     = decodeArray($_POST['prorrogacoes_json'] ?? null); // sempre array
        internacaoEditarDebugLog('PRORROG input id_int=' . (int)$idInt . ' rows=' . count($prArray));

        $postedIds = [];
        foreach ($prArray as $p) {
            if (!empty($p['id_prorrogacao'])) $postedIds[] = (int) $p['id_prorrogacao'];
        }

        $toDelete = array_diff($existingIds, $postedIds);
        foreach ($toDelete as $delId) {
            $prorrogDao->destroy($delId);
            error_log("[PRORROGAÇÃO] Deletada ID $delId");
        }

        foreach ($prArray as $p) {
            $pr = new prorrogacao();
            if (!empty($p['id_prorrogacao'])) $pr->id_prorrogacao = (int) $p['id_prorrogacao'];

            $pr->fk_internacao_pror  = $idInt;
            $pr->fk_usuario_pror     = resolveInternacaoEditUserId(
                $conn,
                isset($p['fk_usuario_pror']) ? (int)$p['fk_usuario_pror'] : null,
                isset($currentIntern->fk_usuario_int) ? (int)$currentIntern->fk_usuario_int : null,
                isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : null
            );
            $pr->fk_visita_pror      = !empty($p['fk_visita_pror']) ? (int)$p['fk_visita_pror'] : null;
            $pr->acomod1_pror        = $p['acomod']     ?? '';
            $pr->isol_1_pror         = $p['isolamento'] ?? 'n';
            $pr->prorrog1_ini_pror   = $p['ini']        ?: null;
            $pr->prorrog1_fim_pror   = $p['fim']        ?: null;
            $pr->diarias_1           = (int)($p['diarias'] ?? 0);

            if (!empty($pr->id_prorrogacao)) {
                $prorrogDao->update($pr);
                internacaoEditarDebugLog('PRORROG update ok id_int=' . (int)$idInt . ' id_pror=' . (int)$pr->id_prorrogacao);
            } else {
                $prorrogDao->create($pr);
                internacaoEditarDebugLog('PRORROG create ok id_int=' . (int)$idInt . ' ini=' . (string)$pr->prorrog1_ini_pror . ' fim=' . (string)$pr->prorrog1_fim_pror);
            }

            $dataIni = $p['ini'] ?? null;
            $dataFim = $p['fim'] ?? null;
            $acomod = $p['acomod'] ?? '';
            $diarias = (int)($p['diarias'] ?? 0);
            if (!empty($dataIni) && !empty($dataFim) && !empty($acomod) && $diarias > 0) {
                $acomodSolicitada = $p['acomod_solicitada'] ?? $acomod;
                $trocaDeNorm = normalizeAcomodacaoNegociacao($acomodSolicitada);
                $trocaParaNorm = normalizeAcomodacaoNegociacao($acomod);

                if ($trocaDeNorm === $trocaParaNorm) {
                    foreach ($negDao->findByInternacao($idInt) as $negExist) {
                        $tipoExist = (string)($negExist['tipo_negociacao'] ?? '');
                        if ($tipoExist !== 'PRORROGACAO_AUTOMATICA') {
                            continue;
                        }
                        $iniExist = (string)($negExist['data_inicio_neg'] ?? '');
                        $fimExist = (string)($negExist['data_fim_neg'] ?? '');
                        $deExist = normalizeAcomodacaoNegociacao($negExist['troca_de'] ?? '');
                        $paraExist = normalizeAcomodacaoNegociacao($negExist['troca_para'] ?? '');
                        if ($iniExist === (string)$dataIni && $fimExist === (string)$dataFim && $deExist === $paraExist) {
                            $negDao->destroy((int)($negExist['id_negociacao'] ?? 0));
                        }
                    }
                    internacaoEditarDebugLog('PRORROG skip neg_auto sem troca id_int=' . (int)$idInt . ' ini=' . (string)$dataIni . ' fim=' . (string)$dataFim);
                    continue;
                }

                $negAuto = new negociacao();
                $negAuto->fk_id_int = $idInt;
                $negAuto->tipo_negociacao = 'PRORROGACAO_AUTOMATICA';
                $negAuto->data_inicio_neg = $dataIni;
                $negAuto->data_fim_neg = $dataFim;
                $negAuto->troca_de = $acomodSolicitada;
                $negAuto->troca_para = $acomod;
                $negAuto->qtd = $diarias;
                $negAuto->saving = (float)($p['saving_estimado'] ?? 0);
                $negAuto->fk_usuario_neg = resolveInternacaoEditUserId(
                    $conn,
                    isset($p['fk_usuario_pror']) ? (int)$p['fk_usuario_pror'] : null,
                    isset($currentIntern->fk_usuario_int) ? (int)$currentIntern->fk_usuario_int : null,
                    isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : null
                );
                if (!$negDao->existeNegociacao($negAuto)) {
                    $negDao->create($negAuto);
                }
            }
        }
    }

    /*──────── TUSS (CREATE / UPDATE / DELETE) ────────*/
    if (filter_input(INPUT_POST, 'select_tuss') === 's') {
        $tussJson    = decodeArray($_POST['tuss_json'] ?? null); // sempre array
        $idUsuario   = (int) ($_SESSION['id_usuario'] ?? 0);

        // existentes
        $existentes  = $tussDao->findByIdIntern($idInternacao);
        $existIds    = array_map(fn($r) => (int) ($r['id_tuss'] ?? $r->id_tuss), $existentes);

        // ids vindos no form
        $incomingIds = [];
        foreach ($tussJson as $item) {
            if (!empty($item['id_tuss'])) $incomingIds[] = (int) $item['id_tuss'];
        }

        // deletar os que sumiram
        $toDelete = array_diff($existIds, $incomingIds);
        foreach ($toDelete as $delId) {
            $tussDao->destroy($delId);
        }

        // create/update
        foreach ($tussJson as $item) {
            if (empty($item['tuss_solicitado'])) continue;

            $tuss = new tuss();
            $tuss->id_tuss               = !empty($item['id_tuss']) ? (int) $item['id_tuss'] : null;
            $tuss->fk_int_tuss           = !empty($item['fk_int_tuss']) ? (int) $item['fk_int_tuss'] : $idInternacao;
            $tuss->tuss_solicitado       = $item['tuss_solicitado']       ?? '';
            $tuss->tuss_liberado_sn      = $item['tuss_liberado_sn']      ?? '';
            $tuss->qtd_tuss_solicitado   = $item['qtd_tuss_solicitado']   ?? '';
            $tuss->qtd_tuss_liberado     = $item['qtd_tuss_liberado']     ?? '';
            $tuss->data_realizacao_tuss  = $item['data_realizacao_tuss']  ?? null;
            $tuss->fk_vis_tuss           = $item['fk_vis_tuss']           ?? null;
            $tuss->fk_usuario_tuss       = $idUsuario;
            $tuss->data_create_tuss      = date('Y-m-d H:i:s');
            $tuss->glosa_tuss            = null;

            if (!empty($tuss->id_tuss)) {
                $tussDao->update($tuss);
            } else {
                $tussDao->create($tuss);
            }
        }
    }

    $conn->commit();
    internacaoEditarDebugLog('COMMIT ok id_int=' . (int)$idInternacao);

    // redirect único após todo o processamento
    header('Location: internacoes/lista');
    exit;
} catch (Throwable $e) {
    $conn->rollBack();
    internacaoEditarDebugLog('ERROR id_int=' . (int)$idInternacao . ' msg=' . $e->getMessage());
    error_log('[process_internacao_editar][ERROR] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo "Erro ao processar atualização. Detalhes no log.";
    exit;
}
