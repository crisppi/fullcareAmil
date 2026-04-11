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
include_once("dao/usuarioDao.php");

include_once("models/internacao.php");
include_once("dao/internacaoDao.php");

include_once("models/alta.php");
include_once("dao/altaDao.php");

include_once("models/uti.php");
include_once("dao/utiDao.php");

if (!function_exists('altaDebugLog')) {
    function altaDebugLog(string $message, array $context = []): void
    {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
        if ($context) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        @file_put_contents(__DIR__ . '/logs/process_alta.debug.log', $line . PHP_EOL, FILE_APPEND);
    }
}

$message = new Message($BASE_URL);
$userDao = new UserDAO($conn, $BASE_URL);

$internacaoDao = new internacaoDAO($conn, $BASE_URL);
$utiDao = new utiDao($conn, $BASE_URL);
$altaDao = new altaDAO($conn, $BASE_URL);

Gate::enforceAction($conn, $BASE_URL, 'discharge', 'Você não tem permissão para dar alta.');

// Resgata o tipo do formulário
$type = filter_input(INPUT_POST, "type");

$alta = new alta();

// Receber os dados dos inputs
$id_internacao = filter_input(INPUT_POST, "id_internacao", FILTER_VALIDATE_INT);
$internado_int = "n";
$internado_alt = "n";
$data_alta_alt = filter_input(INPUT_POST, "data_alta_alt");
$hora_alta_alt = filter_input(INPUT_POST, "hora_alta_alt");
$tipo_alta_alt = filter_input(INPUT_POST, "tipo_alta_alt");
$data_create_alt = filter_input(INPUT_POST, "data_create_alt") ?: date('Y-m-d');
$usuario_alt = filter_input(INPUT_POST, "usuario_alt") ?: ($_SESSION['email_user'] ?? 'sistema');
$fk_usuario_alt = filter_input(INPUT_POST, "fk_usuario_alt", FILTER_VALIDATE_INT);
if ($fk_usuario_alt === false || $fk_usuario_alt === null || $fk_usuario_alt <= 0) {
    $fk_usuario_alt = (int)($_SESSION['id_usuario'] ?? 0) ?: null;
}
altaDebugLog('payload', [
    'id_internacao' => $id_internacao,
    'data_alta_alt' => $data_alta_alt,
    'hora_alta_alt' => $hora_alta_alt,
    'tipo_alta_alt' => $tipo_alta_alt,
    'fk_usuario_alt' => $fk_usuario_alt,
]);

if (isset($__flowCtxAuto) && function_exists('flowLog')) {
    flowLog($__flowCtxAuto, 'alta.payload', 'INFO', [
        'id_internacao' => $id_internacao,
        'data_alta_alt' => $data_alta_alt,
        'hora_alta_alt' => $hora_alta_alt,
        'tipo_alta_alt' => $tipo_alta_alt,
        'fk_usuario_alt' => $fk_usuario_alt,
        'alta_uti' => $alta_uti ?? null,
    ]);
}

if (!$id_internacao || !$data_alta_alt || !$tipo_alta_alt) {
    $message->setMessage("Preencha os campos obrigatórios da alta.", "error", "back");
}

$alta->data_alta_alt = $data_alta_alt;
$alta->hora_alta_alt = $hora_alta_alt;
$alta->tipo_alta_alt = $tipo_alta_alt;
$alta->usuario_alt = $usuario_alt;
$alta->data_create_alt = $data_create_alt;
$alta->fk_id_int_alt = $id_internacao;
$alta->internado_alt = $internado_alt;
$alta->fk_usuario_alt = $fk_usuario_alt;

$id_uti = filter_input(INPUT_POST, "id_uti");
$alta_uti = filter_input(INPUT_POST, "alta_uti");
$data_alta_uti = filter_input(INPUT_POST, "data_alta_uti");

$conn->beginTransaction();

try {
    $altaDao->create($alta);
    altaDebugLog('alta.create.ok', ['id_internacao' => $id_internacao]);
    if (isset($__flowCtxAuto) && function_exists('flowLog')) {
        flowLog($__flowCtxAuto, 'alta.insert', 'INFO', [
            'fk_id_int_alt' => $id_internacao,
            'status' => 'ok'
        ]);
    }

    $internacaoData = new Internacao();
    $internacaoData->id_internacao = $id_internacao;
    $internacaoData->internado_int = $internado_int;

    $internacaoDao->updateAlta($internacaoData);
    $stmtForceAlta = $conn->prepare("UPDATE tb_internacao SET internado_int = 'n' WHERE id_internacao = :id");
    $stmtForceAlta->bindValue(':id', $id_internacao, PDO::PARAM_INT);
    $stmtForceAlta->execute();

    $stmtVerifyAlta = $conn->prepare("SELECT internado_int FROM tb_internacao WHERE id_internacao = :id");
    $stmtVerifyAlta->bindValue(':id', $id_internacao, PDO::PARAM_INT);
    $stmtVerifyAlta->execute();
    $statusInternacao = (string)($stmtVerifyAlta->fetchColumn() ?? '');
    if ($statusInternacao !== 'n') {
        throw new RuntimeException('internacao_nao_foi_encerrada');
    }

    altaDebugLog('internacao.updateAlta.ok', [
        'id_internacao' => $id_internacao,
        'internado_int' => $statusInternacao
    ]);
    if (isset($__flowCtxAuto) && function_exists('flowLog')) {
        flowLog($__flowCtxAuto, 'internacao.update_alta', 'INFO', [
            'id_internacao' => $id_internacao,
            'internado_int' => $internado_int,
            'status' => 'ok'
        ]);
    }

    if ($alta_uti == "alta_uti") {
        // Receber os dados dos inputs
        $internado_uti = filter_input(INPUT_POST, "internado_uti");
        $fk_internacao_uti = filter_input(INPUT_POST, "fk_internacao_uti");

        $UTIData = $utiDao->findById($id_uti);

        $UTIData->data_alta_uti = $data_alta_uti;
        $UTIData->fk_internacao_uti = $fk_internacao_uti;
        $UTIData->internado_uti = $internado_uti;
        $UTIData->id_uti = $id_uti;

        $utiDao->findAltaUpdate($UTIData);
    }

    $conn->commit();
    altaDebugLog('transaction.commit', ['id_internacao' => $id_internacao]);
    if (isset($__flowCtxAuto) && function_exists('flowLog')) {
        flowLog($__flowCtxAuto, 'transaction.commit', 'INFO', [
            'id_internacao' => $id_internacao,
            'status' => 'ok'
        ]);
    }
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    altaDebugLog('transaction.fail', [
        'id_internacao' => $id_internacao,
        'message' => $e->getMessage()
    ]);
    if (isset($__flowCtxAuto) && function_exists('flowLog')) {
        flowLog($__flowCtxAuto, 'transaction.fail', 'ERROR', [
            'id_internacao' => $id_internacao,
            'message' => $e->getMessage()
        ]);
    }
    error_log('[process_alta] ' . $e->getMessage());
    $message->setMessage("Não foi possível registrar a alta.", "error", "back");
}

header('Location: ' . rtrim($BASE_URL, '/') . '/internacoes/lista');
exit;
