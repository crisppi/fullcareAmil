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

// require_once("models/acomodacao.php");
require_once("dao/pacienteDao.php");

// require_once("models/message.php");

// $message = new Message($BASE_URL);
// $userDao = new UserDAO($conn, $BASE_URL);
$pacienteDao = new pacienteDao($conn, $BASE_URL);

$matricula = filter_input(INPUT_POST, "matricula");

$result = $pacienteDao->validarMatriculaExistente($matricula);
$exists = !empty($result);

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

if (isset($__flowCtxAuto) && function_exists('flowLog')) {
    flowLog($__flowCtxAuto, 'matricula.checked', 'INFO', [
        'matricula' => (string) $matricula,
        'exists' => $exists ? 1 : 0,
        'matches' => is_array($result) ? count($result) : 0,
    ]);
}

echo json_encode([
    'success' => true,
    'exists' => $exists,
    'matches' => $result,
], JSON_UNESCAPED_UNICODE);
