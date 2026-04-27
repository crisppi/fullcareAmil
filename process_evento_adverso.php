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
require_once("models/gestao.php");
require_once("models/message.php");
require_once("dao/usuarioDao.php");
require_once("dao/gestaoDao.php");
require_once("utils/audit_logger.php");

$message = new Message($BASE_URL);
$userDao = new UserDAO($conn, $BASE_URL);
$gestaoDao = new gestaoDAO($conn, $BASE_URL);

// Resgata o tipo do formulário
$type = filter_input(INPUT_POST, "type");

// Resgata dados do usuário

if ($type === "create") {
    // Receber os dados dos inputs
    $fk_internacao_ges = filter_input(INPUT_POST, "fk_internacao_ges");
    $fk_visita_ges = filter_input(INPUT_POST, "fk_visita_ges");
    $evento_adverso_ges = filter_input(INPUT_POST, "evento_adverso_ges");
    $rel_evento_adverso_ges = filter_input(INPUT_POST, "rel_evento_adverso_ges");
    $tipo_evento_adverso_gest = filter_input(INPUT_POST, "tipo_evento_adverso_gest");
    $evento_sinalizado_ges = filter_input(INPUT_POST, "evento_sinalizado_ges");
    $evento_discutido_ges = filter_input(INPUT_POST, "evento_discutido_ges");
    $evento_negociado_ges = filter_input(INPUT_POST, "evento_negociado_ges");
    $evento_valor_negoc_ges = filter_input(INPUT_POST, "evento_valor_negoc_ges");
    $evento_prorrogar_ges = filter_input(INPUT_POST, "evento_prorrogar_ges");
    $evento_fech_ges = filter_input(INPUT_POST, "evento_fech_ges");

    $evento_retorno_qual_hosp_ges = filter_input(INPUT_POST, "evento_retorno_qual_hosp_ges");
    $evento_classificado_hospital_ges = filter_input(INPUT_POST, "evento_classificado_hospital_ges");
    $evento_data_ges = filter_input(INPUT_POST, "evento_data_ges");
    $evento_encerrar_ges = filter_input(INPUT_POST, "evento_encerrar_ges");
    $evento_impacto_financ_ges = filter_input(INPUT_POST, "evento_impacto_financ_ges");
    $evento_prolongou_internacao_ges = filter_input(INPUT_POST, "evento_prolongou_internacao_ges");
    $evento_concluido_ges = filter_input(INPUT_POST, "evento_concluido_ges");
    $evento_classificacao_ges = filter_input(INPUT_POST, "evento_classificacao_ges");
    $fk_user_ges = filter_input(INPUT_POST, "fk_user_ges");

    $gestao = new gestao();

    // Validação mínima de dados
    $gestao->fk_internacao_ges = $fk_internacao_ges;
    $gestao->fk_visita_ges = null;
    $gestao->evento_adverso_ges = $evento_adverso_ges;
    $gestao->rel_evento_adverso_ges = $rel_evento_adverso_ges;
    $gestao->tipo_evento_adverso_gest = $tipo_evento_adverso_gest;
    $gestao->evento_sinalizado_ges = $evento_sinalizado_ges;
    $gestao->evento_discutido_ges = $evento_discutido_ges;
    $gestao->evento_negociado_ges = $evento_negociado_ges;
    $gestao->evento_valor_negoc_ges = $evento_valor_negoc_ges;
    $gestao->evento_prorrogar_ges = $evento_prorrogar_ges;
    $gestao->evento_fech_ges = $evento_fech_ges;

    $gestao->evento_retorno_qual_hosp_ges = $evento_retorno_qual_hosp_ges;
    $gestao->evento_classificado_hospital_ges = $evento_classificado_hospital_ges;
    $gestao->evento_data_ges = $evento_data_ges;
    $gestao->evento_encerrar_ges = $evento_encerrar_ges;
    $gestao->evento_impacto_financ_ges = $evento_impacto_financ_ges;
    $gestao->evento_prolongou_internacao_ges = $evento_prolongou_internacao_ges;
    $gestao->evento_concluido_ges = $evento_concluido_ges;
    $gestao->evento_classificacao_ges = $evento_classificacao_ges;

    $gestao->fk_user_ges = $fk_user_ges;

    $gestaoDao->create($gestao);
    $idGestao = (int)$conn->lastInsertId();
    fullcareAuditLog($conn, [
        'action' => 'create',
        'entity_type' => 'gestao',
        'entity_id' => $idGestao > 0 ? $idGestao : null,
        'after' => array_merge(get_object_vars($gestao), ['id_gestao' => $idGestao > 0 ? $idGestao : null]),
        'source' => 'process_evento_adverso.php',
    ], $BASE_URL);
    header("location:internacoes/lista");
} else if ($type === "update") {
    // Receber os dados dos inputs
    $id_gestao = filter_input(INPUT_POST, "id_gestao");
    $fk_internacao_ges = filter_input(INPUT_POST, "fk_internacao_ges");
    $fk_visita_ges = filter_input(INPUT_POST, "fk_visita_ges");
    $alto_custo_ges = filter_input(INPUT_POST, "alto_custo_ges");
    $rel_alto_custo_ges = filter_input(INPUT_POST, "rel_alto_custo_ges");
    $evento_adverso_ges = filter_input(INPUT_POST, "evento_adverso_ges");
    $rel_evento_adverso_ges = filter_input(INPUT_POST, "rel_evento_adverso_ges");
    $tipo_evento_adverso_gest = filter_input(INPUT_POST, "tipo_evento_adverso_gest");
    $evento_sinalizado_ges = filter_input(INPUT_POST, "evento_sinalizado_ges");
    $evento_discutido_ges = filter_input(INPUT_POST, "evento_discutido_ges");
    $evento_negociado_ges = filter_input(INPUT_POST, "evento_negociado_ges");
    $evento_valor_negoc_ges = filter_input(INPUT_POST, "evento_valor_negoc_ges");
    $evento_prorrogar_ges = filter_input(INPUT_POST, "evento_prorrogar_ges");
    $evento_fech_ges = filter_input(INPUT_POST, "evento_fech_ges");

    $evento_retorno_qual_hosp_ges = filter_input(INPUT_POST, "evento_retorno_qual_hosp_ges");
    $evento_classificado_hospital_ges = filter_input(INPUT_POST, "evento_classificado_hospital_ges");
    $evento_data_ges = filter_input(INPUT_POST, "evento_data_ges");
    $evento_encerrar_ges = filter_input(INPUT_POST, "evento_encerrar_ges");
    $evento_impacto_financ_ges = filter_input(INPUT_POST, "evento_impacto_financ_ges");
    $evento_prolongou_internacao_ges = filter_input(INPUT_POST, "evento_prolongou_internacao_ges");
    $evento_concluido_ges = filter_input(INPUT_POST, "evento_concluido_ges");
    $evento_classificacao_ges = filter_input(INPUT_POST, "evento_classificacao_ges");

    $opme_ges = filter_input(INPUT_POST, "opme_ges");
    $rel_opme_ges = filter_input(INPUT_POST, "rel_opme_ges");
    $home_care_ges = filter_input(INPUT_POST, "home_care_ges");
    $rel_home_care_ges = filter_input(INPUT_POST, "rel_home_care_ges");
    $desospitalizacao_ges = filter_input(INPUT_POST, "desospitalizacao_ges");
    $rel_desospitalizacao_ges = filter_input(INPUT_POST, "rel_desospitalizacao_ges");
    $fk_user_ges = filter_input(INPUT_POST, "fk_user_ges");


    $before = $gestaoDao->findById($id_gestao);
    $gestao = new gestao();

    // Validação mínima de dados

    $gestao->id_gestao = $id_gestao;
    $gestao->fk_internacao_ges = $fk_internacao_ges;
    $gestao->fk_visita_ges = null;
    $gestao->evento_adverso_ges = $evento_adverso_ges;
    $gestao->rel_evento_adverso_ges = $rel_evento_adverso_ges;
    $gestao->tipo_evento_adverso_gest = $tipo_evento_adverso_gest;
    $gestao->evento_sinalizado_ges = $evento_sinalizado_ges;
    $gestao->evento_discutido_ges = $evento_discutido_ges;
    $gestao->evento_negociado_ges = $evento_negociado_ges;
    $gestao->evento_valor_negoc_ges = $evento_valor_negoc_ges;
    $gestao->evento_prorrogar_ges = $evento_prorrogar_ges;
    $gestao->evento_fech_ges = $evento_fech_ges;

    $gestao->evento_retorno_qual_hosp_ges = $evento_retorno_qual_hosp_ges;
    $gestao->evento_classificado_hospital_ges = $evento_classificado_hospital_ges;
    $gestao->evento_data_ges = $evento_data_ges;
    $gestao->evento_encerrar_ges = $evento_encerrar_ges;
    $gestao->evento_impacto_financ_ges = $evento_impacto_financ_ges;
    $gestao->evento_prolongou_internacao_ges = $evento_prolongou_internacao_ges;
    $gestao->evento_concluido_ges = $evento_concluido_ges;
    $gestao->evento_classificacao_ges = $evento_classificacao_ges;

    $gestao->fk_user_ges = $fk_user_ges;
    $gestaoDao->update($gestao);
    fullcareAuditLog($conn, [
        'action' => 'update',
        'entity_type' => 'gestao',
        'entity_id' => (int)$id_gestao,
        'before' => $before,
        'after' => $gestao,
        'source' => 'process_evento_adverso.php',
    ], $BASE_URL);

    header("location:internacoes/lista");
}
