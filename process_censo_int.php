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

require_once ("globals.php");
require_once ("db.php");

require_once ("models/internacao.php");
require_once ("dao/internacaoDao.php");

require_once ("models/gestao.php");
require_once ("dao/gestaoDao.php");

require_once ("models/uti.php");
require_once ("dao/utiDao.php");

require_once ("models/negociacao.php");
require_once ("dao/negociacaoDao.php");

require_once ("models/prorrogacao.php");
require_once ("dao/prorrogacaoDao.php");

require_once ("models/message.php");

require_once ("models/usuario.php");
require_once ("dao/usuarioDao.php");

require_once ("models/capeante.php");
require_once ("dao/capeanteDao.php");

require_once ("models/detalhes.php");
require_once ("dao/detalhesDao.php");

require_once ("models/tuss.php");
require_once ("dao/tussDao.php");

require_once ("models/censo.php");
require_once ("dao/censoDao.php");

require_once ("models/visita.php");
require_once ("dao/visitaDao.php");
require_once ("utils/audit_logger.php");

// $message = new Message($BASE_URL);
$userDao = new UserDAO($conn, $BASE_URL);
$internacaoDao = new InternacaoDAO($conn, $BASE_URL);

$gestaoDao = new gestaoDAO($conn, $BASE_URL);
$utiDao = new utiDAO($conn, $BASE_URL);
$negociacaoDao = new negociacaoDAO($conn, $BASE_URL);
$prorrogacaoDao = new prorrogacaoDAO($conn, $BASE_URL);
$capeanteDao = new capeanteDAO($conn, $BASE_URL);
$detalhesDao = new detalhesDAO($conn, $BASE_URL);
$tussDao = new tussDAO($conn, $BASE_URL);
$censoDao = new censoDAO($conn, $BASE_URL);
$id_censo = isset($_GET['id_censo']) ? $_GET['id_censo'] : null;
$type = isset($_GET['type']) ? $_GET['type'] : null;
$censo = $censoDao->findById($id_censo);
$visitaDao = new visitaDAO($conn, $BASE_URL);


// Resgata dados do usuário
if ($type === "create") {

    $fk_hospital_int = $censo->fk_hospital_censo;
    $fk_paciente_int = $censo->fk_paciente_censo;
    $fk_patologia_int = filter_input(INPUT_POST, "fk_patologia_int") ?: 1;
    $fk_patologia2 = filter_input(INPUT_POST, "fk_patologia2") ?: 1;
    $internado_int = 's';
    $modo_internacao_int = filter_input(INPUT_POST, "modo_internacao_int");
    $tipo_admissao_int = filter_input(INPUT_POST, "tipo_admissao_int");
    $data_visita_int = filter_input(INPUT_POST, "data_visita_int") ?: null;
    $data_intern_int = $censo->data_censo ?: null;
    $especialidade_int = filter_input(INPUT_POST, "especialidade_int");
    $titular_int = $censo->titular_censo;
    $acomodacao_int = $censo->acomodacao_censo;
    ;
    $acoes_int = filter_input(INPUT_POST, "acoes_int");
    $rel_int = filter_input(INPUT_POST, "rel_int") ?: null;
    $senha_int = $censo->senha_censo;
    $usuario_create_int = $censo->usuario_create_censo;
    $data_create_int = $censo->data_create_censo ?: null;
    $grupo_patologia_int = filter_input(INPUT_POST, "grupo_patologia_int");
    $primeira_vis_int = filter_input(INPUT_POST, "primeira_vis_int");
    $visita_med_int = filter_input(INPUT_POST, "visita_med_int");
    $visita_enf_int = filter_input(INPUT_POST, "visita_enf_int");
    $visita_no_int = filter_input(INPUT_POST, "visita_no_int");
    $visita_auditor_prof_med = filter_input(INPUT_POST, "visita_auditor_prof_med");
    $visita_auditor_prof_enf = filter_input(INPUT_POST, "visita_auditor_prof_enf");
    $fk_usuario_int = filter_input(INPUT_POST, "fk_usuario_int");
    $censo_int = 's';
    $programacao_int = filter_input(INPUT_POST, "programacao_int");

    //inputs da visita detalhes
    $select_detalhes = filter_input(INPUT_POST, "select_detalhes");
    $fk_vis_det = filter_input(INPUT_POST, "fk_vis_det");
    $fk_int_det = filter_input(INPUT_POST, "fk_int_det");
    $curativo_det = filter_input(INPUT_POST, "curativo_det");
    $dieta_det = filter_input(INPUT_POST, "dieta_det");
    $nivel_consc_det = filter_input(INPUT_POST, "nivel_consc_det");
    $oxig_det = filter_input(INPUT_POST, "oxig_det");
    $oxig_uso_det = filter_input(INPUT_POST, "oxig_uso_det");
    $qt_det = filter_input(INPUT_POST, "qt_det");
    $atb_det = filter_input(INPUT_POST, "atb_det");
    $atb_uso_det = filter_input(INPUT_POST, "atb_uso_det");
    $acamado_det = filter_input(INPUT_POST, "acamado_det");
    $exames_det = filter_input(INPUT_POST, "exames_det");
    $oportunidades_det = filter_input(INPUT_POST, "oportunidades_det");

    // pegar dados input da gestao
    $select_gestao = filter_input(INPUT_POST, "select_gestao");
    $fk_internacao_ges = filter_input(INPUT_POST, "fk_internacao_ges");
    $fk_visita_ges = filter_input(INPUT_POST, "fk_visita_ges");
    $alto_custo_ges = filter_input(INPUT_POST, "alto_custo_ges");
    $rel_alto_custo_ges = filter_input(INPUT_POST, "rel_alto_custo_ges");
    $evento_adverso_ges = filter_input(INPUT_POST, "evento_adverso_ges");
    $rel_evento_adverso_ges = filter_input(INPUT_POST, "rel_evento_adverso_ges");
    $tipo_evento_adverso_gest = filter_input(INPUT_POST, "tipo_evento_adverso_gest");
    $opme_ges = filter_input(INPUT_POST, "opme_ges");
    $rel_opme_ges = filter_input(INPUT_POST, "rel_opme_ges");
    $home_care_ges = filter_input(INPUT_POST, "home_care_ges");
    $rel_home_care_ges = filter_input(INPUT_POST, "rel_home_care_ges");
    $desospitalizacao_ges = filter_input(INPUT_POST, "desospitalizacao_ges");
    $rel_desospitalizacao_ges = filter_input(INPUT_POST, "rel_desospitalizacao_ges");
    $fk_usuario_ges = filter_input(INPUT_POST, "fk_usuario_ges");

    // Receber os dados dos inputs UTI
    $select_uti = filter_input(INPUT_POST, "select_uti");
    $fk_internacao_uti = filter_input(INPUT_POST, "fk_internacao_uti");
    $rel_uti = filter_input(INPUT_POST, "rel_uti") ?: null;
    $fk_paciente_int = $censo->fk_paciente_censo;
    $internado_uti = filter_input(INPUT_POST, "internado_uti");
    $criterios_uti = filter_input(INPUT_POST, "criterios_uti");
    $data_alta_uti = filter_input(INPUT_POST, "data_alta_uti");
    $data_internacao_uti = filter_input(INPUT_POST, "data_internacao_uti");
    $dva_uti = filter_input(INPUT_POST, "dva_uti");
    $especialidade_uti = filter_input(INPUT_POST, "especialidade_uti");
    $internacao_uti = filter_input(INPUT_POST, "internacao_uti");
    $just_uti = filter_input(INPUT_POST, "just_uti");
    $motivo_uti = filter_input(INPUT_POST, "motivo_uti");
    $saps_uti = filter_input(INPUT_POST, "saps_uti");
    $score_uti = filter_input(INPUT_POST, "score_uti");
    $vm_uti = filter_input(INPUT_POST, "vm_uti");
    $id_internacao = filter_input(INPUT_POST, "id_internacao");
    $data_create_uti = filter_input(INPUT_POST, "data_create_uti") ?: null;
    $fk_user_uti = filter_input(INPUT_POST, "fk_user_uti");

    // Receber os dados dos inputs prorrogacao
    $select_prorrog = filter_input(INPUT_POST, "select_prorrog");
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
    $fk_usuario_pror = filter_input(INPUT_POST, "fk_usuario_pror");

    // Receber os dados dos inputs neggoc
    $select_negoc = filter_input(INPUT_POST, "select_negoc");

    // Receber os dados dos inputs TUSS - bloco 1
    $select_tuss = filter_input(INPUT_POST, "select_tuss");
    $fk_int_tuss = filter_input(INPUT_POST, "fk_int_tuss");
    $tuss_liberado_sn = filter_input(INPUT_POST, "tuss_liberado_sn");
    $tuss_solicitado = filter_input(INPUT_POST, "tuss_solicitado");
    $qtd_tuss_solicitado = filter_input(INPUT_POST, "qtd_tuss_solicitado");
    $qtd_tuss_liberado = filter_input(INPUT_POST, "qtd_tuss_liberado");
    $data_realizacao_tuss = filter_input(INPUT_POST, "data_realizacao_tuss");

    // Receber os dados dos inputs TUSS - bloco 2
    $select_tuss2 = filter_input(INPUT_POST, "select_tuss2");
    $tuss_solicitado2 = filter_input(INPUT_POST, "tuss_solicitado2");
    $data_realizacao_tuss2 = filter_input(INPUT_POST, "data_realizacao_tuss2");
    $qtd_tuss_solicitado2 = filter_input(INPUT_POST, "qtd_tuss_solicitado2");
    $qtd_tuss_liberado2 = filter_input(INPUT_POST, "qtd_tuss_liberado2");
    $tuss_liberado_sn2 = filter_input(INPUT_POST, "tuss_liberado_sn2");
    $bloco2 = filter_input(INPUT_POST, "bloco2");

    // Receber os dados dos inputs TUSS - bloco 3
    $select_tuss3 = filter_input(INPUT_POST, "select_tuss3");
    $bloco3 = filter_input(INPUT_POST, "bloco3");
    $tuss_solicitado3 = filter_input(INPUT_POST, "tuss_solicitado3");
    $data_realizacao_tuss3 = filter_input(INPUT_POST, "data_realizacao_tuss3");
    $qtd_tuss_solicitado3 = filter_input(INPUT_POST, "qtd_tuss_solicitado3");
    $qtd_tuss_liberado3 = filter_input(INPUT_POST, "qtd_tuss_liberado3");
    $tuss_liberado_sn3 = filter_input(INPUT_POST, "tuss_liberado_sn3");

    // Receber os dados dos inputs TUSS - bloco 4
    $select_tuss4 = filter_input(INPUT_POST, "select_tuss4");
    $bloco4 = filter_input(INPUT_POST, "bloco4");
    $tuss_solicitado4 = filter_input(INPUT_POST, "tuss_solicitado4");
    $data_realizacao_tuss4 = filter_input(INPUT_POST, "data_realizacao_tuss4");
    $qtd_tuss_solicitado4 = filter_input(INPUT_POST, "qtd_tuss_solicitado4");
    $qtd_tuss_liberado4 = filter_input(INPUT_POST, "qtd_tuss_liberado4");
    $tuss_liberado_sn4 = filter_input(INPUT_POST, "tuss_liberado_sn4");

    // Receber os dados dos inputs TUSS - bloco 5
    $select_tuss5 = filter_input(INPUT_POST, "select_tuss5");
    $bloco5 = filter_input(INPUT_POST, "bloco5");
    $tuss_solicitado5 = filter_input(INPUT_POST, "tuss_solicitado5");
    $data_realizacao_tuss5 = filter_input(INPUT_POST, "data_realizacao_tuss5");
    $qtd_tuss_solicitado5 = filter_input(INPUT_POST, "qtd_tuss_solicitado5");
    $qtd_tuss_liberado5 = filter_input(INPUT_POST, "qtd_tuss_liberado5");
    $tuss_liberado_sn5 = filter_input(INPUT_POST, "tuss_liberado_sn5");

    // Receber os dados dos inputs TUSS - bloco 6
    $select_tuss6 = filter_input(INPUT_POST, "select_tuss6");
    $bloco6 = filter_input(INPUT_POST, "bloco6");
    $tuss_solicitado6 = filter_input(INPUT_POST, "tuss_solicitado6");
    $data_realizacao_tuss6 = filter_input(INPUT_POST, "data_realizacao_tuss6");
    $qtd_tuss_solicitado6 = filter_input(INPUT_POST, "qtd_tuss_solicitado6");
    $qtd_tuss_liberado6 = filter_input(INPUT_POST, "qtd_tuss_liberado6");
    $tuss_liberado_sn6 = filter_input(INPUT_POST, "tuss_liberado_sn6");

    $internacao = new internacao();


    // Validação mínima de dados
    if (3 < 4) {


        if ($internacaoDao->checkInternAtiva($internacao->fk_paciente_int) > 0) {
            echo "0";
        } else {
            $internacao->fk_paciente_int = $fk_paciente_int;
            $internacao->$fk_hospital_int = $fk_hospital_int;
            $internacao->fk_patologia_int = $fk_patologia_int;
            $internacao->fk_patologia2 = $fk_patologia2;
            $internacao->internado_int = $internado_int;
            $internacao->modo_internacao_int = $modo_internacao_int;
            $internacao->tipo_admissao_int = $tipo_admissao_int;
            $internacao->grupo_patologia_int = $grupo_patologia_int;
            $internacao->data_visita_int = $data_intern_int;
            $internacao->data_intern_int = $data_intern_int;
            $internacao->especialidade_int = $especialidade_int;
            $internacao->titular_int = $titular_int;
            $internacao->acomodacao_int = $acomodacao_int;
            $internacao->rel_int = $rel_int;
            $internacao->acoes_int = $acoes_int;
            $internacao->senha_int = $senha_int;
            $internacao->usuario_create_int = $usuario_create_int;
            $internacao->data_create_int = $data_create_int;
            $internacao->grupo_patologia_int = $grupo_patologia_int;
            $internacao->primeira_vis_int = 'n';
            $internacao->visita_med_int = $visita_med_int;
            $internacao->visita_enf_int = $visita_enf_int;
            $internacao->visita_no_int = 1;
            $internacao->visita_auditor_prof_med = $visita_auditor_prof_med;
            $internacao->visita_auditor_prof_enf = $visita_auditor_prof_enf;
            $internacao->fk_usuario_int = $_SESSION["id_usuario"];
            $internacao->censo_int = $censo_int;
            

            $internacao->programacao_int = $programacao_int;
            $internacao->fk_hospital_int = $censo->fk_hospital_censo;
            $internacaoDao->create($internacao);
            $censoDao->updateCenso($censo);
            $lastId = $internacaoDao->findLastId()['0']['id_intern'];
            fullcareAuditLog($conn, [
                'action' => 'create',
                'entity_type' => 'internacao',
                'entity_id' => (int)$lastId,
                'after' => array_merge(get_object_vars($internacao), ['id_internacao' => (int)$lastId]),
                'context' => ['origin' => 'censo_int', 'id_censo' => $id_censo],
                'source' => 'process_censo_int.php',
            ], $BASE_URL);
            $visita = new visita;
            $visita->fk_internacao_vis = $lastId;
            $visita->data_visita_vis = $data_intern_int;
            $visita->data_create = $data_intern_int;
            $visitaDao->create($visita);
            $idVisita = method_exists($visitaDao, 'findLastId') ? (int)$visitaDao->findLastId() : (int)$conn->lastInsertId();
            fullcareAuditLog($conn, [
                'action' => 'create',
                'entity_type' => 'visita',
                'entity_id' => $idVisita > 0 ? $idVisita : null,
                'after' => array_merge(get_object_vars($visita), ['id_visita' => $idVisita > 0 ? $idVisita : null]),
                'context' => ['origin' => 'censo_int', 'id_censo' => $id_censo],
                'source' => 'process_censo_int.php',
            ], $BASE_URL);

            // lancar dados detalhes 
            if ($select_detalhes == "s") {
                $detalhes = new detalhes();
                // lancar dados do input detalhes se selecionado
                $detalhes->fk_int_det = $fk_int_det;
                // $detalhes->fk_vis_det = $fk_vis_det;
                $detalhes->curativo_det = $curativo_det;
                $detalhes->dieta_det = $dieta_det;
                $detalhes->nivel_consc_det = $nivel_consc_det;
                $detalhes->oxig_det = $oxig_det;
                $detalhes->oxig_uso_det = $oxig_uso_det;
                $detalhes->qt_det = $qt_det;
                $detalhes->dispositivo_det = $dispositivo_det;
                $detalhes->atb_det = $atb_det;
                $detalhes->atb_uso_det = $atb_uso_det;
                $detalhes->acamado_det = $acamado_det;
                $detalhes->exames_det = $exames_det;
                $detalhes->oportunidades_det = $oportunidades_det;

                $detalhesDao->create($detalhes);
                $idDetalhes = (int)$conn->lastInsertId();
                fullcareAuditLog($conn, [
                    'action' => 'create',
                    'entity_type' => 'detalhes',
                    'entity_id' => $idDetalhes > 0 ? $idDetalhes : null,
                    'after' => array_merge(get_object_vars($detalhes), ['id_detalhes' => $idDetalhes > 0 ? $idDetalhes : null, 'fk_int_det' => $lastId]),
                    'context' => ['origin' => 'censo_int', 'id_censo' => $id_censo],
                    'source' => 'process_censo_int.php',
                ], $BASE_URL);
            }
            ;
            // lancar dados gestao 
            if ($select_gestao == "s") {

                $gestao = new gestao();

                // lancar dados do input gestao se selecionado

                $gestao->fk_internacao_ges = $fk_internacao_ges;
                $gestao->alto_custo_ges = $alto_custo_ges;
                $gestao->fk_internacao_ges = $fk_internacao_ges;
                $gestao->fk_visita_ges = $fk_visita_ges;
                $gestao->alto_custo_ges = $alto_custo_ges;
                $gestao->rel_alto_custo_ges = $rel_alto_custo_ges;
                $gestao->evento_adverso_ges = $evento_adverso_ges;
                $gestao->rel_evento_adverso_ges = $rel_evento_adverso_ges;
                $gestao->tipo_evento_adverso_gest = $tipo_evento_adverso_gest;
                $gestao->opme_ges = $opme_ges;
                $gestao->rel_opme_ges = $rel_opme_ges;
                $gestao->home_care_ges = $home_care_ges;
                $gestao->rel_home_care_ges = $rel_home_care_ges;
                $gestao->desospitalizacao_ges = $desospitalizacao_ges;
                $gestao->rel_desospitalizacao_ges = $rel_desospitalizacao_ges;
                $gestao->fk_usuario_ges = $fk_usuario_ges;

                $gestaoDao->create($gestao);
                $idGestao = (int)$conn->lastInsertId();
                fullcareAuditLog($conn, [
                    'action' => 'create',
                    'entity_type' => 'gestao',
                    'entity_id' => $idGestao > 0 ? $idGestao : null,
                    'after' => array_merge(get_object_vars($gestao), ['id_gestao' => $idGestao > 0 ? $idGestao : null, 'fk_internacao_ges' => $lastId]),
                    'context' => ['origin' => 'censo_int', 'id_censo' => $id_censo],
                    'source' => 'process_censo_int.php',
                ], $BASE_URL);
            }
            ;
            // lancar dados UTI 
            if ($select_uti == "s") {

                $uti = new uti();

                // lancar dados do input uti se selecionado
                $uti->fk_internacao_uti = $fk_internacao_uti;
                $uti->internado_uti = $internado_uti;
                $uti->criterios_uti = $criterios_uti;
                $uti->data_alta_uti = $data_alta_uti;
                $uti->data_internacao_uti = $data_internacao_uti;
                $uti->dva_uti = $dva_uti;
                $uti->especialidade_uti = $especialidade_uti;
                $uti->internacao_uti = $internacao_uti;
                $uti->just_uti = $just_uti;
                $uti->motivo_uti = $motivo_uti;
                $uti->rel_uti = $rel_uti;
                $uti->saps_uti = $saps_uti;
                $uti->score_uti = $score_uti;
                $uti->vm_uti = $vm_uti;
                $uti->id_internacao = $id_internacao;
                $uti->usuario_create_uti = $usuario_create_int;
                $uti->data_create_uti = $data_create_int;
                $uti->fk_user_uti = $fk_user_uti;

                $utiDao->create($uti);
                $idUti = (int)$conn->lastInsertId();
                fullcareAuditLog($conn, [
                    'action' => 'create',
                    'entity_type' => 'uti',
                    'entity_id' => $idUti > 0 ? $idUti : null,
                    'after' => array_merge(get_object_vars($uti), ['id_uti' => $idUti > 0 ? $idUti : null, 'fk_internacao_uti' => $lastId]),
                    'context' => ['origin' => 'censo_int', 'id_censo' => $id_censo],
                    'source' => 'process_censo_int.php',
                ], $BASE_URL);
            }
            ;
            // lancar dados negociacao 
            if ($select_negoc == "s") {
                // Receber os dados dos inputs negociacao
                $select_negoc = filter_input(INPUT_POST, "select_negoc");
                $troca_de_1 = filter_input(INPUT_POST, "troca_de_1");
                $troca_para_1 = filter_input(INPUT_POST, "troca_para_1");
                $fk_id_int = filter_input(INPUT_POST, "fk_id_int");
                $qtd_1 = filter_input(INPUT_POST, "qtd_1");

                $troca_de_2 = filter_input(INPUT_POST, "troca_de_2");
                $troca_para_2 = filter_input(INPUT_POST, "troca_para_2");
                $qtd_2 = filter_input(INPUT_POST, "qtd_2");

                $troca_de_3 = filter_input(INPUT_POST, "troca_de_3");
                $troca_para_3 = filter_input(INPUT_POST, "troca_para_3");
                $qtd_3 = filter_input(INPUT_POST, "qtd_3");

                $fk_usuario_neg = filter_input(INPUT_POST, "fk_usuario_neg");

                foreach ($niveis as $query) {

                    if ($troca_de_1 === $query['acomodacao_aco']) {
                        $valor_de_1 = $query['valor_aco'];
                    }
                }
                ;
                foreach ($niveis as $query) {

                    if ($troca_para_1 === $query['acomodacao_aco']) {
                        $valor_para_1 = $query['valor_aco'];
                    }
                }
                ;

                foreach ($niveis as $query) {

                    if ($troca_de_2 === $query['acomodacao_aco']) {
                        $valor_de_2 = $query['valor_aco'];
                    }
                }
                ;
                foreach ($niveis as $query) {

                    if ($troca_para_2 === $query['acomodacao_aco']) {
                        $valor_para_2 = $query['valor_aco'];
                    }
                }
                ;
                foreach ($niveis as $query) {

                    if ($troca_de_3 === $query['acomodacao_aco']) {
                        $valor_de_3 = $query['valor_aco'];
                    }
                }
                ;
                foreach ($niveis as $query) {

                    if ($troca_para_3 === $query['acomodacao_aco']) {
                        $valor_para_3 = $query['valor_aco'];
                    }
                }
                ;
                // valorizacao das diarias
                $dif_aco_1 = $valor_de_1 - $valor_para_1;
                $dif_1 = $dif_aco_1 * $qtd_1;
                $dif_aco_2 = $valor_de_2 - $valor_para_2;
                $dif_2 = $dif_aco_2 * $qtd_2;
                $dif_aco_3 = $valor_de_3 - $valor_para_3;
                $dif_3 = $dif_aco_3 * $qtd_3;
                $negociacao = new negociacao();

                // lancar dados do input negoc se selecionado
                $negociacao->troca_de_1 = $troca_de_1;
                $negociacao->troca_para_1 = $troca_para_1;
                $negociacao->fk_id_int = $fk_id_int;
                $negociacao->valor_de_1 = $valor_de_1;
                $negociacao->valor_para_1 = $valor_para_1;
                $negociacao->dif_1 = $dif_1;
                $negociacao->qtd_1 = $qtd_1;

                $negociacao->troca_de_2 = $troca_de_2;
                $negociacao->troca_para_2 = $troca_para_2;
                $negociacao->valor_de_2 = $valor_de_2;
                $negociacao->valor_para_2 = $valor_para_2;
                $negociacao->dif_2 = $dif_2;
                $negociacao->qtd_2 = $qtd_2;

                $negociacao->troca_de_3 = $troca_de_3;
                $negociacao->troca_para_3 = $troca_para_3;
                $negociacao->valor_de_3 = $valor_de_3;
                $negociacao->valor_para_3 = $valor_para_3;
                $negociacao->dif_3 = $dif_3;
                $negociacao->qtd_3 = $qtd_3;

                $negociacao->fk_usuario_neg = $fk_usuario_neg;

                $negociacaoDao->create($negociacao);
                $idNegociacao = (int)$conn->lastInsertId();
                fullcareAuditLog($conn, [
                    'action' => 'create',
                    'entity_type' => 'negociacao',
                    'entity_id' => $idNegociacao > 0 ? $idNegociacao : null,
                    'after' => array_merge(get_object_vars($negociacao), ['id_negociacao' => $idNegociacao > 0 ? $idNegociacao : null, 'fk_id_int' => $lastId]),
                    'context' => ['origin' => 'censo_int', 'id_censo' => $id_censo],
                    'source' => 'process_censo_int.php',
                ], $BASE_URL);
            }
            ;
            // lancar dados prorrogacao 
            if ($select_prorrog == "s") {

                $prorrogacao = new prorrogacao();

                // lancar dados do input prorrogacao se selecionado
                $prorrogacao->fk_internacao_pror = $fk_internacao_pror;
                $prorrogacao->acomod1_pror = $acomod1_pror;
                $prorrogacao->isol_1_pror = $isol_1_pror;
                $prorrogacao->prorrog1_fim_pror = $prorrog1_fim_pror;
                $prorrogacao->prorrog1_ini_pror = $prorrog1_ini_pror;
                $prorrogacao->acomod2_pror = $acomod2_pror;
                $prorrogacao->isol_2_pror = $isol_2_pror;
                $prorrogacao->prorrog2_fim_pror = $prorrog2_fim_pror;
                $prorrogacao->prorrog2_ini_pror = $prorrog2_ini_pror;
                $prorrogacao->acomod3_pror = $acomod3_pror;
                $prorrogacao->isol_3_pror = $isol_3_pror;
                $prorrogacao->prorrog3_fim_pror = $prorrog3_fim_pror;
                $prorrogacao->fk_usuario_pror = $fk_usuario_pror;
                $prorrogacao->prorrog3_ini_pror = $prorrog3_ini_pror;

                $prorrogacaoDao->create($prorrogacao);
                $idProrrogacao = (int)$conn->lastInsertId();
                fullcareAuditLog($conn, [
                    'action' => 'create',
                    'entity_type' => 'prorrogacao',
                    'entity_id' => $idProrrogacao > 0 ? $idProrrogacao : null,
                    'after' => array_merge(get_object_vars($prorrogacao), ['id_prorrogacao' => $idProrrogacao > 0 ? $idProrrogacao : null, 'fk_internacao_pror' => $lastId]),
                    'context' => ['origin' => 'censo_int', 'id_censo' => $id_censo],
                    'source' => 'process_censo_int.php',
                ], $BASE_URL);
            }
            ;
            // lancar dados tuss 
            if ($select_tuss == "s") {

                $tuss = new tuss();

                // lancar dados do input tuss se selecionado
                $tuss->fk_int_tuss = $fk_int_tuss;
                $tuss->tuss_solicitado = $tuss_solicitado;
                $tuss->data_realizacao_tuss = $data_realizacao_tuss;
                $tuss->qtd_tuss_solicitado = $qtd_tuss_solicitado;
                $tuss->qtd_tuss_liberado = $qtd_tuss_liberado;
                $tuss->tuss_liberado_sn = $tuss_liberado_sn;

                $tussDao->create($tuss);
                $idTuss = (int)$conn->lastInsertId();
                fullcareAuditLog($conn, [
                    'action' => 'create',
                    'entity_type' => 'tuss',
                    'entity_id' => $idTuss > 0 ? $idTuss : null,
                    'after' => array_merge(get_object_vars($tuss), ['id_tuss' => $idTuss > 0 ? $idTuss : null, 'fk_int_tuss' => $lastId]),
                    'context' => ['origin' => 'censo_int', 'id_censo' => $id_censo, 'bloco' => 1],
                    'source' => 'process_censo_int.php',
                ], $BASE_URL);

                if (($tuss_liberado_sn == "s") || ($tuss_liberado_sn == "n")) {

                    $tuss = new tuss();
                    // lancar dados do input tuss se selecionado
                    $tuss->fk_int_tuss = $fk_int_tuss2;
                    $tuss->tuss_liberado_sn = $tuss_liberado_sn2;
                    $tuss->tuss_solicitado = $tuss_solicitado2;
                    $tuss->data_realizacao_tuss = $data_realizacao_tuss2;
                    $tuss->qtd_tuss_solicitado = $qtd_tuss_solicitado2;
                    $tuss->qtd_tuss_liberado = $qtd_tuss_liberado2;
                    $tuss->bloco2 = $bloco2;

                    $tussDao->create($tuss);
                    $idTuss = (int)$conn->lastInsertId();
                    fullcareAuditLog($conn, [
                        'action' => 'create',
                        'entity_type' => 'tuss',
                        'entity_id' => $idTuss > 0 ? $idTuss : null,
                        'after' => array_merge(get_object_vars($tuss), ['id_tuss' => $idTuss > 0 ? $idTuss : null, 'fk_int_tuss' => $lastId]),
                        'context' => ['origin' => 'censo_int', 'id_censo' => $id_censo, 'bloco' => 2],
                        'source' => 'process_censo_int.php',
                    ], $BASE_URL);
                }

                if (($tuss_liberado_sn2 == "s") || ($tuss_liberado_sn2 == "n")) {

                    $tuss = new tuss();
                    // lancar dados do input tuss se selecionado
                    $tuss->fk_int_tuss = $fk_int_tuss3;
                    $tuss->tuss_liberado_sn = $tuss_liberado_sn3;
                    $tuss->tuss_solicitado = $tuss_solicitado3;
                    $tuss->data_realizacao_tuss = $data_realizacao_tuss3;
                    $tuss->qtd_tuss_solicitado = $qtd_tuss_solicitado3;
                    $tuss->qtd_tuss_liberado = $qtd_tuss_liberado3;
                    $tuss->bloco3 = $bloco3;

                    $tussDao->create($tuss);
                    $idTuss = (int)$conn->lastInsertId();
                    fullcareAuditLog($conn, [
                        'action' => 'create',
                        'entity_type' => 'tuss',
                        'entity_id' => $idTuss > 0 ? $idTuss : null,
                        'after' => array_merge(get_object_vars($tuss), ['id_tuss' => $idTuss > 0 ? $idTuss : null, 'fk_int_tuss' => $lastId]),
                        'context' => ['origin' => 'censo_int', 'id_censo' => $id_censo, 'bloco' => 3],
                        'source' => 'process_censo_int.php',
                    ], $BASE_URL);
                }
                if (($tuss_liberado_sn3 == "s") || ($tuss_liberado_sn3 == "n")) {

                    $tuss = new tuss();
                    // lancar dados do input tuss se selecionado
                    $tuss->tuss_solicitado = $tuss_solicitado4;
                    $tuss->bloco4 = $bloco4;
                    $tuss->data_realizacao_tuss = $data_realizacao_tuss4;
                    $tuss->qtd_tuss_solicitado = $qtd_tuss_solicitado4;
                    $tuss->qtd_tuss_liberado = $qtd_tuss_liberado4;
                    $tuss->tuss_liberado_sn = $tuss_liberado_sn4;

                    $tussDao->create($tuss);
                    $idTuss = (int)$conn->lastInsertId();
                    fullcareAuditLog($conn, [
                        'action' => 'create',
                        'entity_type' => 'tuss',
                        'entity_id' => $idTuss > 0 ? $idTuss : null,
                        'after' => array_merge(get_object_vars($tuss), ['id_tuss' => $idTuss > 0 ? $idTuss : null, 'fk_int_tuss' => $lastId]),
                        'context' => ['origin' => 'censo_int', 'id_censo' => $id_censo, 'bloco' => 4],
                        'source' => 'process_censo_int.php',
                    ], $BASE_URL);
                }

                if (($tuss_liberado_sn4 == "s") || ($tuss_liberado_sn4 == "n")) {

                    $tuss = new tuss();
                    // lancar dados do input tuss se selecionado
                    $tuss->tuss_liberado_sn = $tuss_liberado_sn5;
                    $tuss->tuss_solicitado = $tuss_solicitado5;
                    $tuss->data_realizacao_tuss = $data_realizacao_tuss5;
                    $tuss->qtd_tuss_solicitado = $qtd_tuss_solicitado5;
                    $tuss->qtd_tuss_liberado = $qtd_tuss_liberado5;
                    $tuss->bloco5 = $bloco5;

                    $tussDao->create($tuss);
                    $idTuss = (int)$conn->lastInsertId();
                    fullcareAuditLog($conn, [
                        'action' => 'create',
                        'entity_type' => 'tuss',
                        'entity_id' => $idTuss > 0 ? $idTuss : null,
                        'after' => array_merge(get_object_vars($tuss), ['id_tuss' => $idTuss > 0 ? $idTuss : null, 'fk_int_tuss' => $lastId]),
                        'context' => ['origin' => 'censo_int', 'id_censo' => $id_censo, 'bloco' => 5],
                        'source' => 'process_censo_int.php',
                    ], $BASE_URL);
                }

                if (($tuss_liberado_sn5 == "s") || ($tuss_liberado_sn5 == "n")) {

                    $tuss = new tuss();
                    // lancar dados do input tuss se selecionado
                    $tuss->fk_int_tuss = $fk_int_tuss6;
                    $tuss->tuss_solicitado = $tuss_solicitado6;
                    $tuss->bloco6 = $bloco6;
                    $tuss->data_realizacao_tuss = $data_realizacao_tuss6;
                    $tuss->qtd_tuss_solicitado = $qtd_tuss_solicitado6;
                    $tuss->qtd_tuss_liberado = $qtd_tuss_liberado6;
                    $tuss->tuss_liberado_sn = $tuss_liberado_sn6;

                    $tussDao->create($tuss);
                    $idTuss = (int)$conn->lastInsertId();
                    fullcareAuditLog($conn, [
                        'action' => 'create',
                        'entity_type' => 'tuss',
                        'entity_id' => $idTuss > 0 ? $idTuss : null,
                        'after' => array_merge(get_object_vars($tuss), ['id_tuss' => $idTuss > 0 ? $idTuss : null, 'fk_int_tuss' => $lastId]),
                        'context' => ['origin' => 'censo_int', 'id_censo' => $id_censo, 'bloco' => 6],
                        'source' => 'process_censo_int.php',
                    ], $BASE_URL);
                }
            }
            ;

            $capeante = new capeante;
            $lastId = $internacaoDao->findLastId()['0']['id_intern'];
            $fk_int_capeante = $lastId;
            $encerrado_cap = filter_input(INPUT_POST, "encerrado_cap");
            $aberto_cap = filter_input(INPUT_POST, "aberto_cap");
            $em_auditoria_cap = filter_input(INPUT_POST, "em_auditoria_cap");
            $senha_finalizada = filter_input(INPUT_POST, "senha_finalizada");

            $fk_user_cap = filter_input(INPUT_POST, "fk_usuario_int");
            $usuario_create_cap = filter_input(INPUT_POST, "usuario_create_int");
            $data_create_cap = filter_input(INPUT_POST, "data_create_int");
            $capeante->fk_int_capeante = $fk_int_capeante;
            $capeante->encerrado_cap = $encerrado_cap;
            $capeante->aberto_cap = $aberto_cap;
            $capeante->em_auditoria_cap = $em_auditoria_cap;
            $capeante->senha_finalizada = $senha_finalizada;
            $capeante->med_check = 'n';
            $capeante->enfer_check = 'n';
            $capeante->adm_check = 'n';
            $capeante->last_cap = 1;

            $capeante->fk_user_cap = $fk_user_cap;
            $capeante->usuario_create_cap = $usuario_create_cap;
            $capeante->data_create_cap = $data_create_cap;

            $capeanteDao->create($capeante);
            $idCapeante = (int)$conn->lastInsertId();
            fullcareAuditLog($conn, [
                'action' => 'create',
                'entity_type' => 'capeante',
                'entity_id' => $idCapeante > 0 ? $idCapeante : null,
                'after' => array_merge(get_object_vars($capeante), ['id_capeante' => $idCapeante > 0 ? $idCapeante : null]),
                'context' => ['origin' => 'censo_int', 'id_censo' => $id_censo, 'fk_int_capeante' => $lastId],
                'source' => 'process_censo_int.php',
            ], $BASE_URL);
        }
        ;
    }
}
