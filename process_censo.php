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

require_once("models/censo.php");
require_once("dao/censoDao.php");
require_once("utils/audit_logger.php");

require_once("models/internacao.php");
require_once("dao/internacaoDao.php");


$censoDao = new CensoDAO($conn, $BASE_URL);
$internacaoDao = new InternacaoDAO($conn, $BASE_URL);

// Resgata o tipo do formulário
$type = filter_input(INPUT_POST, "type");

// Resgata dados do usuário
if ($type === "create") {
    
    // Receber os dados dos inputs
    $fk_hospital_censo = filter_input(INPUT_POST, "fk_hospital_censo");
    $fk_paciente_censo = filter_input(INPUT_POST, "fk_paciente_censo");
    $data_censo = filter_input(INPUT_POST, "data_censo");
    $senha_censo = filter_input(INPUT_POST, "senha_censo");
    $acomodacao_censo = filter_input(INPUT_POST, "acomodacao_censo");
    $tipo_admissao_censo = filter_input(INPUT_POST, "tipo_admissao_censo");
    $modo_internacao_censo = filter_input(INPUT_POST, "modo_internacao_censo");
    $usuario_create_censo = filter_input(INPUT_POST, "usuario_create_censo");
    $data_create_censo = filter_input(INPUT_POST, "data_create_censo");
    $titular_censo = filter_input(INPUT_POST, "titular_censo");

    $censo = new censo();


    // Validação mínima de dados
    if (3 < 4) {

        $censo->fk_hospital_censo = $fk_hospital_censo;
        $censo->fk_paciente_censo = $fk_paciente_censo;
        $censo->data_censo = $data_censo;
        $censo->senha_censo = $senha_censo;
        $censo->acomodacao_censo = $acomodacao_censo;
        $censo->tipo_admissao_censo = $tipo_admissao_censo;
        $censo->modo_internacao_censo = $modo_internacao_censo;
        $censo->usuario_create_censo = $usuario_create_censo;
        $censo->data_create_censo = $data_create_censo;
        $censo->titular_censo = $titular_censo;
        if ($internacaoDao->checkInternAtiva($censo->fk_paciente_censo) > 0) {
            echo '0';
        }else {
            $censoDao->create($censo);
            $novoIdCenso = (int)$conn->lastInsertId();
            $censoCriado = $novoIdCenso > 0 ? $censoDao->findById($novoIdCenso) : null;
            fullcareAuditLog($conn, [
                'action' => 'create',
                'entity_type' => 'censo',
                'entity_id' => $novoIdCenso > 0 ? $novoIdCenso : null,
                'summary' => 'Censo criado.',
                'after' => $censoCriado ?: $censo,
                'trace_id' => isset($__flowCtxAuto) ? ($__flowCtxAuto['trace_id'] ?? null) : null,
                'source' => 'process_censo.php',
            ], $BASE_URL);
            echo '1';
        }
    };
}

if ($type == "update") {
    // Receber os dados dos inputs
    $id_censo = filter_input(INPUT_POST, "id_censo");
    $fk_hospital_censo = filter_input(INPUT_POST, "fk_hospital_censo");
    $fk_paciente_censo = filter_input(INPUT_POST, "fk_paciente_censo");
    $data_censo = filter_input(INPUT_POST, "data_censo");
    $senha_censo = filter_input(INPUT_POST, "senha_censo");
    $acomodacao_censo = filter_input(INPUT_POST, "acomodacao_censo");
    $tipo_admissao_censo = filter_input(INPUT_POST, "tipo_admissao_censo");
    $modo_internacao_censo = filter_input(INPUT_POST, "modo_internacao_censo");
    $usuario_create_censo = filter_input(INPUT_POST, "usuario_create_censo");
    $data_create_censo = filter_input(INPUT_POST, "data_create_censo");
    $titular_censo = filter_input(INPUT_POST, "titular_censo");

    $censo = new censo();

    if (3 < 4) {

        $censo->fk_hospital_censo = $fk_hospital_censo;
        $censo->fk_paciente_censo = $fk_paciente_censo;
        $censo->data_censo = $data_censo;
        $censo->senha_censo = $senha_censo;
        $censo->acomodacao_censo = $acomodacao_censo;
        $censo->tipo_admissao_censo = $tipo_admissao_censo;
        $censo->modo_internacao_censo = $modo_internacao_censo;
        $censo->usuario_create_censo = $usuario_create_censo;
        $censo->data_create_censo = $data_create_censo;
        $censo->id_censo = $id_censo;
        $censo->titular_censo = $titular_censo;
        $censoAntes = $censoDao->findById((int)$id_censo);
        $censoDao->update($censo);
        $censoDepois = $censoDao->findById((int)$id_censo);
        fullcareAuditLog($conn, [
            'action' => 'update',
            'entity_type' => 'censo',
            'entity_id' => (int)$id_censo,
            'summary' => 'Censo atualizado.',
            'before' => $censoAntes,
            'after' => $censoDepois,
            'trace_id' => isset($__flowCtxAuto) ? ($__flowCtxAuto['trace_id'] ?? null) : null,
            'source' => 'process_censo.php',
        ], $BASE_URL);

        // header("location:censo/lista");
    };

    // header("location:censo/lista");
};

// header("location:censo/lista");
