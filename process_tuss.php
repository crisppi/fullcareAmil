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
require_once("models/tuss.php");
require_once("models/message.php");
require_once("dao/usuarioDao.php");
require_once("dao/tussDao.php");
require_once("utils/audit_logger.php");

$message = new Message($BASE_URL);
$userDao = new UserDAO($conn, $BASE_URL);
$tussDao = new tussDAO($conn, $BASE_URL);

// Resgata o tipo do formulário
$type = filter_input(INPUT_POST, "type");

// Resgata dados do usuário

if ($type === "create") {
    // echo "<Pre>";
    // print_r($_POST);
    // echo "</pre>";
    // exit();
    // $fk_int_tuss = filter_input(INPUT_POST, "fk_int_tuss");
    // $fk_usuario_tuss = filter_input(INPUT_POST, "fk_usuario_tuss");
    // $tuss_liberado_sn = filter_input(INPUT_POST, "tuss_liberado_sn");
    // $tuss_solicitado = filter_input(INPUT_POST, "tuss_solicitado");
    // $qtd_tuss_solicitado = filter_input(INPUT_POST, "qtd_tuss_solicitado");
    // $qtd_tuss_liberado = filter_input(INPUT_POST, "qtd_tuss_liberado");
    // $data_realizacao_tuss = filter_input(INPUT_POST, "data_realizacao_tuss");

    $tuss = new tuss();

    // lancar dados do input tuss se selecionado - tuss
    $tuss->fk_int_tuss = $fk_int_tuss;
    $tuss->fk_usuario_tuss = $fk_usuario_tuss;
    $tuss->tuss_solicitado = $tuss_solicitado;
    $tuss->data_realizacao_tuss = $data_realizacao_tuss;
    $tuss->qtd_tuss_solicitado = $qtd_tuss_solicitado;
    $tuss->qtd_tuss_liberado = $qtd_tuss_liberado;
    $tuss->tuss_liberado_sn = $tuss_liberado_sn;
    $tussDao->create($tuss);

    header("location:internacoes/lista");
}

if ($type === "create-vis") {

    // Receber os dados dos inputs
    $fk_int_tuss = filter_input(INPUT_POST, "fk_int_tuss");
    $tuss_solicitado = filter_input(INPUT_POST, "tuss_solicitado");
    $data_realizacao_tuss = filter_input(INPUT_POST, "data_realizacao_tuss");
    $qtd_tuss_solicitado = filter_input(INPUT_POST, "qtd_tuss_solicitado") ?: null;
    $qtd_tuss_liberado = filter_input(INPUT_POST, "qtd_tuss_liberado") ?: null;
    $tuss_liberado_sn = filter_input(INPUT_POST, "tuss_liberado_sn");

    $tuss = new tuss();

    // Validação mínima de dados
    if (!empty($tuss_solicitado)) {

        $tuss->fk_int_tuss = $fk_int_tuss;
        $tuss->tuss_solicitado = $tuss_solicitado;
        $tuss->data_realizacao_tuss = $data_realizacao_tuss;
        $tuss->qtd_tuss_solicitado = $qtd_tuss_solicitado;
        $tuss->qtd_tuss_liberado = $qtd_tuss_liberado;
        $tuss->tuss_liberado_sn = $tuss_liberado_sn;


        $tussDao->create($tuss);
        $novoIdTuss = (int)$conn->lastInsertId();
        $tussCriado = $novoIdTuss > 0 ? $tussDao->findById($novoIdTuss) : null;
        fullcareAuditLog($conn, [
            'action' => 'create',
            'entity_type' => 'tuss',
            'entity_id' => $novoIdTuss > 0 ? $novoIdTuss : null,
            'summary' => 'TUSS criado.',
            'after' => $tussCriado ?: $tuss,
            'trace_id' => isset($__flowCtxAuto) ? ($__flowCtxAuto['trace_id'] ?? null) : null,
            'source' => 'process_tuss.php',
        ], $BASE_URL);
    } else {

        $message->setMessage("Você precisa adicionar pelo menos: tuss!", "error", "back");
    }
}
