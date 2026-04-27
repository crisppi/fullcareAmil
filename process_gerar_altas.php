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

include_once("globals.php");
include_once("db.php");

include_once("models/message.php");
include_once("models/internacao.php");
include_once("models/alta.php");
include_once("models/uti.php");

include_once("dao/internacaoDao.php");
include_once("dao/altaDao.php");
include_once("dao/utiDao.php");
include_once("utils/audit_logger.php");

$message = new Message($BASE_URL);
$redirectPage = "list_internacao_gerar_alta.php";

if (!isset($_SESSION["email_user"])) {
    $message->setMessage("Sessão expirada, faça login novamente.", "error", "index.php");
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $message->setMessage("Método inválido.", "error", $redirectPage);
    header("Location: {$redirectPage}");
    exit;
}

$type = filter_input(INPUT_POST, "type");
if ($type !== "gerar_altas") {
    $message->setMessage("Requisição inválida.", "error", $redirectPage);
    header("Location: {$redirectPage}");
    exit;
}

$selecionadas = filter_input(INPUT_POST, "gerar", FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
if (empty($selecionadas)) {
    $message->setMessage("Selecione ao menos uma internação para gerar a alta.", "error", $redirectPage);
    header("Location: {$redirectPage}");
    exit;
}

$altaDao = new altaDAO($conn, $BASE_URL);
$internacaoDao = new internacaoDAO($conn, $BASE_URL);
$utiDao = new utiDAO($conn, $BASE_URL);

$usuarioAlt = $_SESSION["email_user"] ?? "sistema";
$fkUsuarioAlt = $_SESSION["id_usuario"] ?? null;
$dataCreate = date("Y-m-d");

$erros = [];
$sucesso = 0;

foreach ($selecionadas as $rawId) {
    $idInternacao = (int) $rawId;
    if ($idInternacao <= 0) {
        continue;
    }

    $prefix = "alta_{$idInternacao}_";
    $dataAlta = trim((string) ($_POST[$prefix . "data"] ?? ""));
    $horaAlta = trim((string) ($_POST[$prefix . "hora"] ?? ""));
    $motivoAlta = trim((string) ($_POST[$prefix . "motivo"] ?? ""));

    if ($dataAlta === "" || $motivoAlta === "") {
        $erros[] = "ID {$idInternacao}: informe data e motivo da alta.";
        continue;
    }

    $utiFlag = $_POST[$prefix . "uti_flag"] ?? 'n';
    $utiId = (int)($_POST[$prefix . "uti_id"] ?? 0);
    $utiData = trim((string)($_POST[$prefix . "uti_data"] ?? ""));
    $utiFk = (int)($_POST[$prefix . "uti_fk"] ?? $idInternacao);

    if ($utiFlag === 's') {
        if ($utiId <= 0) {
            $erros[] = "ID {$idInternacao}: registro de UTI não localizado.";
            continue;
        }
        if ($utiData === "") {
            $erros[] = "ID {$idInternacao}: informe a data de alta da UTI.";
            continue;
        }
    }

    $alta = new alta();
    $alta->fk_id_int_alt = $idInternacao;
    $alta->tipo_alta_alt = $motivoAlta;
    $alta->data_alta_alt = $dataAlta;
    $alta->hora_alta_alt = $horaAlta !== "" ? $horaAlta : null;
    $alta->internado_alt = "n";
    $alta->usuario_alt = $usuarioAlt;
    $alta->data_create_alt = $dataCreate;
    $alta->fk_usuario_alt = $fkUsuarioAlt;

    try {
        $beforeInternacao = $internacaoDao->findById($idInternacao);
        $altaDao->create($alta);
        $idAlta = (int)$conn->lastInsertId();
        fullcareAuditLog($conn, [
            'action' => 'create',
            'entity_type' => 'alta',
            'entity_id' => $idAlta > 0 ? $idAlta : null,
            'after' => array_merge(get_object_vars($alta), ['id_alta' => $idAlta > 0 ? $idAlta : null]),
            'source' => 'process_gerar_altas.php',
        ], $BASE_URL);

        $internacao = new Internacao();
        $internacao->id_internacao = $idInternacao;
        $internacao->internado_int = "n";
        $internacaoDao->updateAlta($internacao);
        $afterInternacao = $internacaoDao->findById($idInternacao);
        fullcareAuditLog($conn, [
            'action' => 'update',
            'entity_type' => 'internacao',
            'entity_id' => (int)$idInternacao,
            'before' => $beforeInternacao,
            'after' => $afterInternacao ?: $internacao,
            'summary' => 'Internação atualizada para alta em lote.',
            'source' => 'process_gerar_altas.php',
        ], $BASE_URL);

        if ($utiFlag === 's') {
            $UTIData = $utiDao->findById($utiId);
            if (!$UTIData) {
                $erros[] = "ID {$idInternacao}: UTI não encontrada para atualização.";
            } else {
                $beforeUti = clone $UTIData;
                $UTIData->data_alta_uti = $utiData;
                $UTIData->fk_internacao_uti = $utiFk ?: $idInternacao;
                $UTIData->internado_uti = "n";
                $UTIData->id_uti = $utiId;
                $utiDao->findAltaUpdate($UTIData);
                fullcareAuditLog($conn, [
                    'action' => 'update',
                    'entity_type' => 'uti',
                    'entity_id' => (int)$utiId,
                    'before' => $beforeUti,
                    'after' => $UTIData,
                    'summary' => 'Alta de UTI gerada em lote.',
                    'source' => 'process_gerar_altas.php',
                ], $BASE_URL);
            }
        }

        $sucesso++;
    } catch (Throwable $th) {
        $erros[] = "ID {$idInternacao}: " . $th->getMessage();
    }
}

if ($sucesso > 0) {
    $texto = "{$sucesso} alta(s) gerada(s) com sucesso.";
    if ($erros) {
        $texto .= " Alguns registros não foram processados: " . implode(" | ", $erros);
        $message->setMessage($texto, "warning", $redirectPage);
    } else {
        $message->setMessage($texto, "success", $redirectPage);
    }
} else {
    $textoErro = $erros ? implode(" | ", $erros) : "Não foi possível gerar as altas selecionadas.";
    $message->setMessage($textoErro, "error", $redirectPage);
}

header("Location: {$redirectPage}");
exit;
