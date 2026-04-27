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
require_once("models/patologia.php");
require_once("models/message.php");
require_once("dao/usuarioDao.php");
require_once("dao/patologiaDao.php");
require_once("utils/audit_logger.php");

$message = new Message($BASE_URL);
$userDao = new UserDAO($conn, $BASE_URL);
$patologiaDao = new patologiaDAO($conn, $BASE_URL);

// Resgata o tipo do formulário
$type = filter_input(INPUT_POST, "type");

// Resgata dados do usuário

if ($type === "create") {

    // Receber os dados dos inputs
    $patologia_pat = filter_input(INPUT_POST, "patologia_pat", FILTER_SANITIZE_SPECIAL_CHARS);
    $patologia_pat = ucwords(strtoupper($patologia_pat));

    $dias_pato = filter_input(INPUT_POST, "dias_pato", FILTER_SANITIZE_SPECIAL_CHARS);

    $fk_usuario_pat = filter_input(INPUT_POST, "fk_usuario_pat");
    $usuario_create_pat = filter_input(INPUT_POST, "usuario_create_pat");
    $data_create_pat = filter_input(INPUT_POST, "data_create_pat");

    $cid = filter_input(INPUT_POST, "cid_pat") ?? null;

    $patologia = new patologia();

    // Validação mínima de dados
    if (!empty($patologia_pat)) {

        $patologia->patologia_pat = $patologia_pat;
        $patologia->dias_pato = $dias_pato;
        $patologia->fk_cid_10_pat = $cid;

        $patologia->fk_usuario_pat = $fk_usuario_pat;
        $patologia->usuario_create_pat = $usuario_create_pat;
        $patologia->data_create_pat = $data_create_pat;

        $patologiaDao->create($patologia);
        $novoIdPatologia = (int)$conn->lastInsertId();
        $patologiaCriada = $novoIdPatologia > 0 ? $patologiaDao->findById($novoIdPatologia) : null;
        fullcareAuditLog($conn, [
            'action' => 'create',
            'entity_type' => 'patologia',
            'entity_id' => $novoIdPatologia > 0 ? $novoIdPatologia : null,
            'summary' => 'Patologia criada.',
            'after' => $patologiaCriada ?: $patologia,
            'trace_id' => isset($__flowCtxAuto) ? ($__flowCtxAuto['trace_id'] ?? null) : null,
            'source' => 'process_patologia.php',
        ], $BASE_URL);
        header('location:list_patologia.php');
    } else {

        $message->setMessage("Você precisa adicionar pelo menos: patologiaNome do patologia!", "error", "back");
    }
} else if ($type === "update") {

    $patologiaDao = new patologiaDAO($conn, $BASE_URL);

    // Receber os dados dos inputs
    $id_patologia = filter_input(INPUT_POST, "id_patologia");
    $patologia_pat = filter_input(INPUT_POST, "patologia_pat", FILTER_SANITIZE_SPECIAL_CHARS);
    $patologia_pat = strtoupper($patologia_pat);

    $dias_pato = filter_input(INPUT_POST, "dias_pato", FILTER_SANITIZE_SPECIAL_CHARS);

    $cid = filter_input(INPUT_POST, "cid_pat") ?? null;

    $fk_usuario_pat = filter_input(INPUT_POST, "fk_usuario_pat");
    $usuario_create_pat = filter_input(INPUT_POST, "usuario_create_pat");
    $data_create_pat = filter_input(INPUT_POST, "data_create_pat");

    $patologiaData = $patologiaDao->findById($id_patologia);
    $patologiaAntes = $patologiaData ? clone $patologiaData : null;

    $patologiaData->id_patologia = $id_patologia;
    $patologiaData->patologia_pat = $patologia_pat;
    $patologiaData->dias_pato = $dias_pato;
    $patologiaData->fk_cid_10_pat = $cid;

    $patologiaData->fk_usuario_pat = $fk_usuario_pat;
    $patologiaData->usuario_create_pat = $usuario_create_pat;
    $patologiaData->data_create_pat = $data_create_pat;

    $patologiaDao->update($patologiaData);
    $patologiaDepois = $patologiaDao->findById((int)$id_patologia);
    fullcareAuditLog($conn, [
        'action' => 'update',
        'entity_type' => 'patologia',
        'entity_id' => (int)$id_patologia,
        'summary' => 'Patologia atualizada.',
        'before' => $patologiaAntes,
        'after' => $patologiaDepois,
        'trace_id' => isset($__flowCtxAuto) ? ($__flowCtxAuto['trace_id'] ?? null) : null,
        'source' => 'process_patologia.php',
    ], $BASE_URL);

    include_once('list_patologia.php');
}

if ($type === "delete") {

    // Recebe os dados do form
    $id_patologia = filter_input(INPUT_POST, "id_patologia");

    $patologiaDao = new patologiaDAO($conn, $BASE_URL);

    $patologia = $patologiaDao->findById($id_patologia);

    if (3 < 4) {
        $patologiaAntesDelete = clone $patologia;
        $patologiaDao->destroy($id_patologia);
        fullcareAuditLog($conn, [
            'action' => 'delete',
            'entity_type' => 'patologia',
            'entity_id' => (int)$id_patologia,
            'summary' => 'Patologia excluída.',
            'before' => $patologiaAntesDelete,
            'trace_id' => isset($__flowCtxAuto) ? ($__flowCtxAuto['trace_id'] ?? null) : null,
            'source' => 'process_patologia.php',
        ], $BASE_URL);

        header('location:list_patologia.php');
    } else {

        $message->setMessage("Informações inválidas!", "error", "index.php");
    }
}
