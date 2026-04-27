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
require_once("models/prorrogacao.php");
require_once("models/message.php");
require_once("dao/usuarioDao.php");
require_once("dao/prorrogacaoDao.php");
require_once("utils/audit_logger.php");

$message = new Message($BASE_URL);
$userDao = new UserDAO($conn, $BASE_URL);
$prorrogacaoDao = new prorrogacaoDAO($conn, $BASE_URL);

// Resgata o tipo do formulário
$type = filter_input(INPUT_POST, "type");

// Resgata dados do usuário

if ($type === "create-pror") {

    // Receber os dados dos inputs
    $fk_internacao_pror = filter_input(INPUT_POST, "fk_internacao_pror");
    $acomod1_pror = filter_input(INPUT_POST, "acomod1_pror");
    $isol_1_pror = filter_input(INPUT_POST, "isol_1_pror");
    $prorrog1_fim_pror = filter_input(INPUT_POST, "prorrog1_fim_pror") ?: null;
    $prorrog1_ini_pror = filter_input(INPUT_POST, "prorrog1_ini_pror") ?: null;
    $fk_usuario_pror = filter_input(INPUT_POST, "fk_usuario_pror");

    $prorrogacao = new prorrogacao();

    // Validação mínima de dados
    if (!empty($acomod1_pror)) {

        $prorrogacao->fk_internacao_pror = $fk_internacao_pror;
        $prorrogacao->acomod1_pror = $acomod1_pror;
        $prorrogacao->isol_1_pror = $isol_1_pror;
        $prorrogacao->prorrog1_fim_pror = $prorrog1_fim_pror;
        $prorrogacao->prorrog1_ini_pror = $prorrog1_ini_pror;
    
        $prorrogacao->fk_usuario_pror = $fk_usuario_pror;

        $prorrogacaoDao->create($prorrogacao);
        $novoIdProrrogacao = (int)$conn->lastInsertId();
        $prorrogacaoCriada = $novoIdProrrogacao > 0 ? $prorrogacaoDao->findById($novoIdProrrogacao) : null;
        fullcareAuditLog($conn, [
            'action' => 'create',
            'entity_type' => 'prorrogacao',
            'entity_id' => $novoIdProrrogacao > 0 ? $novoIdProrrogacao : null,
            'summary' => 'Prorrogação criada.',
            'after' => $prorrogacaoCriada ?: $prorrogacao,
            'trace_id' => isset($__flowCtxAuto) ? ($__flowCtxAuto['trace_id'] ?? null) : null,
            'source' => 'process_prorrogacao.php',
        ], $BASE_URL);
    } else {

        $message->setMessage("Você precisa adicionar pelo menos: prorrogacao!", "error", "back");
    }
}
if ($type === "create-vis") {

    // Receber os dados dos inputs
    $fk_internacao_pror = filter_input(INPUT_POST, "fk_internacao_pror");
    $acomod1_pror = filter_input(INPUT_POST, "acomod1_pror");
    $isol_1_pror = filter_input(INPUT_POST, "isol_1_pror");
    $prorrog1_fim_pror = filter_input(INPUT_POST, "prorrog1_fim_pror") ?: null;
    $prorrog1_ini_pror = filter_input(INPUT_POST, "prorrog1_ini_pror") ?: null;
    $acomod2_pror = filter_input(INPUT_POST, "acomod2_pror");
    $isol_2_pror = filter_input(INPUT_POST, "isol_2_pror");
    $prorrog2_fim_pror = filter_input(INPUT_POST, "prorrog2_fim_pror") ?: null;
    $prorrog2_ini_pror = filter_input(INPUT_POST, "prorrog2_ini_pror") ?: null;
    $acomod3_pror = filter_input(INPUT_POST, "acomod3_pror");
    $isol_3_pror = filter_input(INPUT_POST, "isol_3_pror");
    $prorrog3_fim_pror = filter_input(INPUT_POST, "prorrog3_fim_pror") ?: null;
    $prorrog3_ini_pror = filter_input(INPUT_POST, "prorrog3_ini_pror") ?: null;

    $prorrogacao = new prorrogacao();

    // Validação mínima de dados
    if (!empty($acomod1_pror)) {

        $prorrogacao->fk_internacao_pror = $fk_internacao_pror;
        $prorrogacao->acomod1_pror = $acomod1_pror;
        $prorrogacao->isol_1_pror = $isol_1_pror;
        $prorrogacao->prorrog1_fim_pror = $prorrog1_fim_pror;
        $prorrogacao->prorrog1_ini_pror = $prorrog1_ini_pror;
        

        $prorrogacaoDao->create($prorrogacao);
        $novoIdProrrogacao = (int)$conn->lastInsertId();
        $prorrogacaoCriada = $novoIdProrrogacao > 0 ? $prorrogacaoDao->findById($novoIdProrrogacao) : null;
        fullcareAuditLog($conn, [
            'action' => 'create',
            'entity_type' => 'prorrogacao',
            'entity_id' => $novoIdProrrogacao > 0 ? $novoIdProrrogacao : null,
            'summary' => 'Prorrogação criada.',
            'after' => $prorrogacaoCriada ?: $prorrogacao,
            'trace_id' => isset($__flowCtxAuto) ? ($__flowCtxAuto['trace_id'] ?? null) : null,
            'source' => 'process_prorrogacao.php',
        ], $BASE_URL);
    } else {

        $message->setMessage("Você precisa adicionar pelo menos: prorrogacao!", "error", "back");
    }
}
