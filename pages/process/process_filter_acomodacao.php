<?php

if (!defined("FLOW_LOGGER_AUTO_V1")) {
    define("FLOW_LOGGER_AUTO_V1", 1);
    @require_once(__DIR__ . "/../../utils/flow_logger.php");
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

// Review DAO
require_once("globals.php");
require_once("db.php");
require_once("dao/acomodacaoDao.php");
require_once("./models/acomodacao.php");

$hospitalId = $_POST['hospital'];
$acomodacaoDao = new acomodacaoDao($conn, $BASE_URL);

$result = $acomodacaoDao->findGeralByHospital($hospitalId);

// Gera opções para o select de acomodações
$options = '';
$options .= '<option value="0">Selecione</option>';
foreach ($result as $acom) {
    $options .= '<option value="' . $acom['id_acomodacao'] . '">' . $acom['acomodacao_aco'] . '</option>';
}

echo $options;
