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

require_once("globals.php");
require_once("db.php");
require_once("models/internacao.php");
// require_once("models/message.php");
// require_once("dao/usuarioDao.php");
require_once("dao/internacaoDao.php");

require_once("models/uti.php");
require_once("dao/utiDao.php");
require_once("utils/audit_logger.php");

// $userDao = new UserDAO($conn, $BASE_URL);
$internacaoDao = new internacaoDAO($conn, $BASE_URL);
$utiDao = new utiDAO($conn, $BASE_URL);

$id_internacao = filter_input(INPUT_POST, "id_internacao");

// Resgata o tipo do formulário
$type = filter_input(INPUT_POST, "type");

// Resgata dados do usuário
if ($type === "update") {
    // exit;
    // Receber os dados dos inputs
    $internado_uti = filter_input(INPUT_POST, "internado_uti");
    $fk_internacao_uti = filter_input(INPUT_POST, "fk_internacao_uti");
    $data_alta_uti = filter_input(INPUT_POST, "data_alta_uti");
    $internado_uti = filter_input(INPUT_POST, "internado_uti");
    $id_uti = filter_input(INPUT_POST, "id_uti");
    $UTIData = $utiDao->findById($id_uti);
    $before = $utiDao->findById($id_uti);

    $UTIData->data_alta_uti = $data_alta_uti;
    $UTIData->fk_internacao_uti = $fk_internacao_uti;
    $UTIData->internado_uti = $internado_uti;
    $UTIData->id_uti = $id_uti;

    $utiDao->findAltaUpdate($UTIData);
    fullcareAuditLog($conn, [
        'action' => 'update',
        'entity_type' => 'uti',
        'entity_id' => (int)$id_uti,
        'before' => $before,
        'after' => $UTIData,
        'source' => 'process_alta_UTI.php',
    ], $BASE_URL);

    include_once('list_internacao_uti.php');
}
