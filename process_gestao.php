<?php

require_once("globals.php");
require_once("db.php");
require_once("models/gestao.php");
require_once("models/message.php");
require_once("dao/usuarioDao.php");
require_once("dao/gestaoDao.php");
require_once("utils/flow_logger.php");
require_once("utils/audit_logger.php");

$message = new Message($BASE_URL);
$userDao = new UserDAO($conn, $BASE_URL);
$gestaoDao = new gestaoDAO($conn, $BASE_URL);

// Resgata o tipo do formulário
$type = filter_input(INPUT_POST, "type");
$flowCtx = flowLogStart('process_gestao', [
    'type' => $type,
    'id_gestao' => $_POST['id_gestao'] ?? $_GET['id_gestao'] ?? null,
    'fk_internacao_ges' => $_POST['fk_internacao_ges'] ?? null,
    'fk_visita_ges' => $_POST['fk_visita_ges'] ?? null
]);

// Resgata dados do usuário

if ($type === "create") {
    flowLog($flowCtx, 'create.start', 'INFO');

    // Receber os dados dos inputs
    $fk_internacao_ges = filter_input(INPUT_POST, "fk_internacao_ges");
    $fk_visita_ges = filter_input(INPUT_POST, "fk_visita_ges");

    $alto_custo_ges = filter_input(INPUT_POST, "alto_custo_ges");
    $rel_alto_custo_ges = filter_input(INPUT_POST, "rel_alto_custo_ges");

    // Escapa caracteres especiais para evitar XSS
    $rel_alto_custo_ges = htmlspecialchars($rel_alto_custo_ges, ENT_QUOTES, 'UTF-8');

    $rel_alto_custo_ges = substr($rel_alto_custo_ges, 0, 5000);

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

    $gestao = new gestao();

    // Validação mínima de dados
    if (3 < 4) {

        $gestao->fk_internacao_ges = $fk_internacao_ges;
        $gestao->fk_visita_ges = $fk_visita_ges;

        $gestao->alto_custo_ges = $alto_custo_ges;
        $gestao->rel_alto_custo_ges = $rel_alto_custo_ges;

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
        $gestao->evento_encerrar_ges = $evento_encerrar_ges;
        $gestao->evento_impacto_financ_ges = $evento_impacto_financ_ges;
        $gestao->evento_prolongou_internacao_ges = $evento_prolongou_internacao_ges;
        $gestao->evento_concluido_ges = $evento_concluido_ges;
        $gestao->evento_classificacao_ges = $evento_classificacao_ges;

        $gestao->opme_ges = $opme_ges;
        $gestao->rel_opme_ges = $rel_opme_ges;

        $gestao->home_care_ges = $home_care_ges;
        $gestao->rel_home_care_ges = $rel_home_care_ges;

        $gestao->desospitalizacao_ges = $desospitalizacao_ges;
        $gestao->rel_desospitalizacao_ges = $rel_desospitalizacao_ges;

        $gestao->fk_user_ges = $fk_user_ges;

        $gestaoDao->create($gestao);
        $novoIdGestao = (int)$conn->lastInsertId();
        $gestaoCriada = $novoIdGestao > 0 ? $gestaoDao->findById($novoIdGestao) : null;
        fullcareAuditLog($conn, [
            'action' => 'create',
            'entity_type' => 'gestao',
            'entity_id' => $novoIdGestao > 0 ? $novoIdGestao : null,
            'summary' => 'Gestão criada.',
            'after' => $gestaoCriada ?: $gestao,
            'trace_id' => $flowCtx['trace_id'] ?? null,
            'source' => 'process_gestao.php',
        ], $BASE_URL);
        flowLog($flowCtx, 'create.finish', 'INFO', [
            'fk_internacao_ges' => $fk_internacao_ges,
            'fk_visita_ges' => $fk_visita_ges
        ]);
        header("location:list_gestao.php");
    } else {

        // $message->setMessage("Você precisa adicionar pelo menos: gestao_aco do gestao!", "error", "back");
    }
} else if ($type === "update") {
    flowLog($flowCtx, 'update.start', 'INFO');

    $gestao = new gestao();

    // Receber os dados dos inputs
    $id_gestao = filter_input(INPUT_POST, "id_gestao");
    $fk_hospital = filter_input(INPUT_POST, "fk_hospital");
    $gestao_aco = filter_input(INPUT_POST, "gestao_aco");
    $valor_aco = filter_input(INPUT_POST, "valor_aco");

    $gestao = $gestaoDao->joingestaoHospitalshow($id_gestao);
    $gestaoAntes = is_array($gestao) ? $gestao : [];

    $gestao['id_gestao'] = $id_gestao;
    $gestao['fk_hospital'] = $fk_hospital;
    $gestao['valor_aco'] = $valor_aco;
    $gestao['gestao_aco'] = $gestao_aco;

    $gestaoDao->update($gestao);
    $gestaoDepois = $gestaoDao->findById((int)$id_gestao);
    fullcareAuditLog($conn, [
        'action' => 'update',
        'entity_type' => 'gestao',
        'entity_id' => (int)$id_gestao,
        'summary' => 'Gestão atualizada.',
        'before' => $gestaoAntes,
        'after' => $gestaoDepois,
        'trace_id' => $flowCtx['trace_id'] ?? null,
        'source' => 'process_gestao.php',
    ], $BASE_URL);
    flowLog($flowCtx, 'update.finish', 'INFO', ['id_gestao' => $id_gestao]);

    header("location:list_gestao.php");
}

$type = filter_input(INPUT_GET, "type");

if ($type === "delete") {
    flowLog($flowCtx, 'delete.start', 'INFO');
    // Recebe os dados do form
    $id_gestao = filter_input(INPUT_GET, "id_gestao");

    $gestaoDao = new gestaoDAO($conn, $BASE_URL);

    $gestao = $gestaoDao->joingestaoHospitalShow($id_gestao);
    if ($gestao) {
        $gestaoAntesDelete = $gestao;
        $gestaoDao->destroy($id_gestao);
        fullcareAuditLog($conn, [
            'action' => 'delete',
            'entity_type' => 'gestao',
            'entity_id' => (int)$id_gestao,
            'summary' => 'Gestão excluída.',
            'before' => $gestaoAntesDelete,
            'trace_id' => $flowCtx['trace_id'] ?? null,
            'source' => 'process_gestao.php',
        ], $BASE_URL);
        flowLog($flowCtx, 'delete.finish', 'INFO', ['id_gestao' => $id_gestao]);

        header("location:list_gestao.php");
    } else {

        $message->setMessage("Informações inválidas!", "error", "index.php");
    }
}
