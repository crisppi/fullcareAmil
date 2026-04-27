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

ob_start();
require_once("globals.php");
require_once("db.php");
require_once("models/antecedente.php");
require_once("models/message.php");
require_once("dao/usuarioDao.php");
require_once("dao/antecedenteDao.php");
require_once("utils/audit_logger.php");

$message = new Message($BASE_URL);
$userDao = new UserDAO($conn, $BASE_URL);
$antecedenteDao = new antecedenteDAO($conn, $BASE_URL);

// Resgata o tipo do formulário
$type = filter_input(INPUT_POST, "type");

// Resgata dados do usuário

if ($type === "create-ant") {

    // Receber os dados dos inputs
    $antecedente_ant = filter_input(INPUT_POST, "antecedente_ant", FILTER_SANITIZE_SPECIAL_CHARS);
    $antecedente_ant = strtoupper($antecedente_ant);
    $cid = filter_input(INPUT_POST, "cid_ant") ?? null;

    $fk_usuario_ant = filter_input(INPUT_POST, "fk_usuario_ant");
    $usuario_create_ant = filter_input(INPUT_POST, "usuario_create_ant");
    $data_create_ant = filter_input(INPUT_POST, "data_create_ant");

    $antecedente = new antecedente();

    // Validação mínima de dados
    if (!empty($antecedente_ant)) {

        $antecedente->antecedente_ant = $antecedente_ant;

        $antecedente->fk_usuario_ant = $fk_usuario_ant;
        $antecedente->usuario_create_ant = $usuario_create_ant;
        $antecedente->data_create_ant = $data_create_ant;
        $antecedente->fk_cid_10_ant = $cid;

        $antecedenteDao->create($antecedente);
        $novoIdAntecedente = (int)$conn->lastInsertId();
        $antecedenteCriado = $novoIdAntecedente > 0 ? $antecedenteDao->findById($novoIdAntecedente) : null;
        fullcareAuditLog($conn, [
            'action' => 'create',
            'entity_type' => 'antecedente',
            'entity_id' => $novoIdAntecedente > 0 ? $novoIdAntecedente : null,
            'summary' => 'Antecedente criado.',
            'after' => $antecedenteCriado ?: $antecedente,
            'trace_id' => isset($__flowCtxAuto) ? ($__flowCtxAuto['trace_id'] ?? null) : null,
            'source' => 'process_antecedente.php',
        ], $BASE_URL);
        header('location:list_antecedente.php');
    } else {
        $message->setMessage("Você precisa adicionar pelo menos: Antecedente!", "error", "internacoes/nova");
    }
} else if ($type === "update-ant") {

    $antecedenteDao = new antecedenteDAO($conn, $BASE_URL);

    // Receber os dados dos inputs
    $id_antecedente = filter_input(INPUT_POST, "id_antecedente");
    $antecedente_ant = filter_input(INPUT_POST, "antecedente_ant", FILTER_SANITIZE_SPECIAL_CHARS);
    $antecedente_ant = ucwords(strtoupper($antecedente_ant));
    $cid = filter_input(INPUT_POST, "cid_ant") ?? null;

    $usuario_create_ant = filter_input(INPUT_POST, "usuario_create_ant");
    $data_create_ant = filter_input(INPUT_POST, "data_create_ant");
    $fk_usuario_ant = filter_input(INPUT_POST, "fk_usuario_ant");

    $antecedenteData = $antecedenteDao->findById($id_antecedente);
    $antecedenteAntes = $antecedenteData ? clone $antecedenteData : null;

    $antecedenteData->id_antecedente = $id_antecedente;
    $antecedenteData->antecedente_ant = $antecedente_ant;
    $antecedenteData->fk_cid_10_ant = $cid;

    $antecedenteData->usuario_create_ant = $usuario_create_ant;
    $antecedenteData->data_create_ant = $data_create_ant;
    $antecedenteData->fk_usuario_ant = $fk_usuario_ant;

    $antecedenteDao->update($antecedenteData);
    $antecedenteDepois = $antecedenteDao->findById((int)$id_antecedente);
    fullcareAuditLog($conn, [
        'action' => 'update',
        'entity_type' => 'antecedente',
        'entity_id' => (int)$id_antecedente,
        'summary' => 'Antecedente atualizado.',
        'before' => $antecedenteAntes,
        'after' => $antecedenteDepois,
        'trace_id' => isset($__flowCtxAuto) ? ($__flowCtxAuto['trace_id'] ?? null) : null,
        'source' => 'process_antecedente.php',
    ], $BASE_URL);

    header('location:list_antecedente.php');
};
if ($type === "delete") {
    // Recebe os dados do form
    $id_antecedente = filter_input(INPUT_POST, "id_antecedente");

    $antecedenteDao = new antecedenteDAO($conn, $BASE_URL);

    $antecedente = $antecedenteDao->findById($id_antecedente);
    if ($antecedente) {
        $antecedenteAntesDelete = clone $antecedente;
        $antecedenteDao->destroy($id_antecedente);
        fullcareAuditLog($conn, [
            'action' => 'delete',
            'entity_type' => 'antecedente',
            'entity_id' => (int)$id_antecedente,
            'summary' => 'Antecedente excluído.',
            'before' => $antecedenteAntesDelete,
            'trace_id' => isset($__flowCtxAuto) ? ($__flowCtxAuto['trace_id'] ?? null) : null,
            'source' => 'process_antecedente.php',
        ], $BASE_URL);

        header('location:list_antecedente.php');
    } else {

        $message->setMessage("Informações inválidas!", "error", "index.php");
    }
}
