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

require_once("models/capeante.php");
require_once("dao/capeanteDao.php");

require_once("models/message.php");
require_once("dao/usuarioDao.php");
require_once("utils/audit_logger.php");

$message = new Message($BASE_URL);

$capeanteDao = new capeanteDAO($conn, $BASE_URL);

// Resgata o tipo do formulário
$type = filter_input(INPUT_POST, "type");

// Função para limpar os campos de valores e glosas
function limparCampo($valor)
{
    $valor = str_replace('R$', '', $valor);      // Remove o símbolo de moeda
    $valor = str_replace('.', '', $valor);       // Remove o ponto
    $valor = str_replace(',', '.', $valor);      // Converte a vírgula para ponto
    $valor = preg_replace('/\s+/', '', $valor);  // Remove espaços em branco
    return $valor;
}

function flagAtivo($valor): bool
{
    $t = is_string($valor) ? strtolower(trim($valor)) : $valor;
    return in_array($t, ['s', '1', 1, true, 'on', 'true'], true);
}

function valorLancado($valor): bool
{
    if ($valor === null || $valor === '') {
        return false;
    }
    return (float)$valor > 0;
}

if ($type === "create") {
    // Receber os dados dos inputs
    $adm_capeante = filter_input(INPUT_POST, "adm_capeante");
    $aud_enf_capeante = filter_input(INPUT_POST, "aud_enf_capeante");
    $aud_med_capeante = filter_input(INPUT_POST, "aud_med_capeante");

    $fk_int_capeante = filter_input(INPUT_POST, "fk_int_capeante");
    $fk_user_cap = filter_input(INPUT_POST, "fk_user_cap");

    $data_inicial_capeante = filter_input(INPUT_POST, "data_inicial_capeante") ?: null;
    $data_final_capeante = filter_input(INPUT_POST, "data_final_capeante") ?: null;
    $data_fech_capeante = filter_input(INPUT_POST, "data_fech_capeante") ?: null;
    $data_digit_capeante = filter_input(INPUT_POST, "data_digit_capeante") ?: null;
    $timer_cap = filter_input(INPUT_POST, "timer_cap", FILTER_VALIDATE_INT);
    if ($timer_cap === false) {
        $timer_cap = null;
    }
    $diarias_capeante = filter_input(INPUT_POST, "diarias_capeante");
    $lote_cap = filter_input(INPUT_POST, "lote_cap");
    $acomodacao_cap = filter_input(INPUT_POST, "acomodacao_cap");

    $glosa_diaria = limparCampo(filter_input(INPUT_POST, "glosa_diaria"));
    $glosa_honorarios = limparCampo(filter_input(INPUT_POST, "glosa_honorarios"));
    $glosa_matmed = limparCampo(filter_input(INPUT_POST, "glosa_matmed"));
    $glosa_materiais = limparCampo(filter_input(INPUT_POST, "glosa_materiais"));
    $glosa_medicamentos = limparCampo(filter_input(INPUT_POST, "glosa_medicamentos"));
    $glosa_oxig = limparCampo(filter_input(INPUT_POST, "glosa_oxig"));
    $glosa_sadt = limparCampo(filter_input(INPUT_POST, "glosa_sadt"));
    $glosa_taxas = limparCampo(filter_input(INPUT_POST, "glosa_taxas"));
    $glosa_opme = limparCampo(filter_input(INPUT_POST, "glosa_opme"));

    $adm_check = filter_input(INPUT_POST, "adm_check") ?: 'n';
    $med_check = filter_input(INPUT_POST, "med_check") ?: 'n';
    $enfer_check = filter_input(INPUT_POST, "enfer_check") ?: 'n';

    $pacote = filter_input(INPUT_POST, "pacote") ?: "n";
    $parcial_capeante = filter_input(INPUT_POST, "parcial_capeante") ?: "n";
    $parcial_num = filter_input(INPUT_POST, "parcial_num");
    $fk_int_capeante = filter_input(INPUT_POST, "fk_int_capeante");
    $senha_finalizada = filter_input(INPUT_POST, "senha_finalizada") ?: "n";
    $em_auditoria_cap = filter_input(INPUT_POST, "em_auditoria_cap") ?: "n";
    $negociado_desconto_cap = filter_input(INPUT_POST, "negociado_desconto_cap");
    $desconto_valor_cap = filter_input(INPUT_POST, "desconto_valor_cap") ?: NULL;

    $conta_parada_cap = filter_input(INPUT_POST, "conta_parada_cap") ?: NULL;
    $parada_motivo_cap = filter_input(INPUT_POST, "parada_motivo_cap") ?: NULL;

    $valor_apresentado_capeante = limparCampo(filter_input(INPUT_POST, "valor_apresentado_capeante"));
    $valor_final_capeante = limparCampo(filter_input(INPUT_POST, "valor_final_capeante"));
    $valor_diarias = limparCampo(filter_input(INPUT_POST, "valor_diarias"));
    $valor_matmed = limparCampo(filter_input(INPUT_POST, "valor_matmed"));
    $valor_materiais = limparCampo(filter_input(INPUT_POST, "valor_materiais"));
    $valor_medicamentos = limparCampo(filter_input(INPUT_POST, "valor_medicamentos"));
    $valor_oxig = limparCampo(filter_input(INPUT_POST, "valor_oxig"));
    $valor_sadt = limparCampo(filter_input(INPUT_POST, "valor_sadt"));
    $valor_taxa = limparCampo(filter_input(INPUT_POST, "valor_taxa"));
    $valor_honorarios = limparCampo(filter_input(INPUT_POST, "valor_honorarios"));
    $valor_opme = limparCampo(filter_input(INPUT_POST, "valor_opme"));

    $valor_glosa_enf = limparCampo(filter_input(INPUT_POST, "valor_glosa_enf"));
    $valor_glosa_med = limparCampo(filter_input(INPUT_POST, "valor_glosa_med"));
    $valor_glosa_total = limparCampo(filter_input(INPUT_POST, "valor_glosa_total"));

    $fk_user_cap = filter_input(INPUT_POST, "fk_user_cap");
    $usuario_create_cap = filter_input(INPUT_POST, "usuario_create_cap");
    $data_create_cap = filter_input(INPUT_POST, "data_create_cap");
    $sessionUserId = $_SESSION['id_usuario'] ?? null;
    $sessionUserName = $_SESSION['usuario_user'] ?? $_SESSION['login_user'] ?? $_SESSION['email_user'] ?? null;
    $sessionUserId = $_SESSION['id_usuario'] ?? null;
    $sessionUserName = $_SESSION['usuario_user'] ?? $_SESSION['login_user'] ?? $_SESSION['email_user'] ?? null;

    $hasLancamento = false;
    foreach ([
        $valor_diarias,
        $valor_matmed,
        $valor_materiais,
        $valor_medicamentos,
        $valor_oxig,
        $valor_sadt,
        $valor_taxa,
        $valor_honorarios,
        $valor_opme,
        $valor_apresentado_capeante,
        $valor_final_capeante,
    ] as $valorItem) {
        if (valorLancado($valorItem)) {
            $hasLancamento = true;
            break;
        }
    }

    $now = date('Y-m-d H:i:s');
    $timer_start_cap = null;
    $timer_end_cap = null;
    $existing_timer_cap = null;
    if (!empty($id_capeante)) {
        $stmtTimer = $conn->prepare("SELECT timer_start_cap, timer_end_cap, timer_cap FROM tb_capeante WHERE id_capeante = :id");
        $stmtTimer->bindValue(':id', (int)$id_capeante, PDO::PARAM_INT);
        $stmtTimer->execute();
        $timerRow = $stmtTimer->fetch(PDO::FETCH_ASSOC) ?: [];
        $timer_start_cap = $timerRow['timer_start_cap'] ?? null;
        $timer_end_cap = $timerRow['timer_end_cap'] ?? null;
        $existing_timer_cap = $timerRow['timer_cap'] ?? null;
    }
    if (empty($timer_start_cap) && $hasLancamento) {
        $timer_start_cap = $now;
    }
    $finalizando = flagAtivo($encerrado_cap) || flagAtivo($senha_finalizada);
    if ($finalizando && $existing_timer_cap === null && $timer_start_cap) {
        $timer_end_cap = $now;
        $timer_cap = max(0, strtotime($timer_end_cap) - strtotime($timer_start_cap));
    } else {
        $timer_cap = $existing_timer_cap;
    }
    if ($hasLancamento && $sessionUserId) {
        $fk_user_cap = (int)$sessionUserId;
    }
    if ($hasLancamento && $sessionUserName) {
        $usuario_create_cap = $sessionUserName;
    }

    $hasLancamento = false;
    foreach ([
        $valor_diarias,
        $valor_matmed,
        $valor_materiais,
        $valor_medicamentos,
        $valor_oxig,
        $valor_sadt,
        $valor_taxa,
        $valor_honorarios,
        $valor_opme,
        $valor_apresentado_capeante,
        $valor_final_capeante,
    ] as $valorItem) {
        if (valorLancado($valorItem)) {
            $hasLancamento = true;
            break;
        }
    }

    $now = date('Y-m-d H:i:s');
    if (empty($data_create_cap)) {
        $data_create_cap = $now;
    }
    $timer_start_cap = $hasLancamento ? ($data_create_cap ?: $now) : null;
    $timer_end_cap = null;
    $finalizando = flagAtivo($encerrado_cap) || flagAtivo($senha_finalizada);
    if ($finalizando && $timer_start_cap) {
        $timer_end_cap = $now;
        $timer_cap = max(0, strtotime($timer_end_cap) - strtotime($timer_start_cap));
    } else {
        $timer_cap = null;
    }
    if ($hasLancamento && $sessionUserId) {
        $fk_user_cap = (int)$sessionUserId;
    }
    if ($hasLancamento && $sessionUserName) {
        $usuario_create_cap = $sessionUserName;
    }

    $fk_id_aud_enf = filter_input(INPUT_POST, "fk_id_aud_enf");
    $fk_id_aud_med = filter_input(INPUT_POST, "fk_id_aud_med");
    $fk_id_aud_adm = filter_input(INPUT_POST, "fk_id_aud_adm");
    $fk_id_aud_hosp = filter_input(INPUT_POST, "fk_id_aud_hosp");

    $checkbox_imprimir = filter_input(INPUT_POST, "checkbox_imprimir");

    $last_cap = 1;

    $capeante = new capeante();

    // Validação mínima de dados
    if (!empty(3 < 4)) {
        if (empty($data_digit_capeante)) {
            $message->setMessage("Data de digitação é obrigatória.", "error", "back");
            exit;
        }

        $capeante->adm_capeante = $adm_capeante;
        $capeante->adm_check = $adm_check;
        $capeante->aud_enf_capeante = $aud_enf_capeante;
        $capeante->aud_med_capeante = $aud_med_capeante;
        $capeante->data_fech_capeante = $data_fech_capeante;
        $capeante->data_digit_capeante = $data_digit_capeante;
        $capeante->data_final_capeante = $data_final_capeante;
        $capeante->data_inicial_capeante = $data_inicial_capeante;
        $capeante->timer_cap = $timer_cap;
        $capeante->diarias_capeante = $diarias_capeante;
        $capeante->lote_cap = $lote_cap;
        $capeante->acomodacao_cap = $acomodacao_cap;
        $capeante->glosa_diaria = $glosa_diaria;
        $capeante->glosa_honorarios = $glosa_honorarios;
        $capeante->glosa_matmed = $glosa_matmed;
        $capeante->glosa_oxig = $glosa_oxig;
        $capeante->glosa_sadt = $glosa_sadt;
        $capeante->glosa_taxas = $glosa_taxas;
        $capeante->glosa_opme = $glosa_opme;
        $capeante->med_check = $med_check;
        $capeante->enfer_check = $enfer_check;
        $capeante->pacote = $pacote;
        $capeante->parcial_capeante = $parcial_capeante;
        $capeante->parcial_num = $parcial_num;
        $capeante->fk_int_capeante = $fk_int_capeante;
        $capeante->fk_user_cap = $fk_user_cap;
        $capeante->valor_apresentado_capeante = $valor_apresentado_capeante;
        $capeante->valor_diarias = $valor_diarias;
        $capeante->valor_final_capeante = $valor_final_capeante;
        $capeante->valor_glosa_enf = $valor_glosa_enf;
        $capeante->valor_glosa_med = $valor_glosa_med;
        $capeante->valor_glosa_total = $valor_glosa_total;
        $capeante->valor_honorarios = $valor_honorarios;
        $capeante->valor_matmed = $valor_matmed;
        $capeante->valor_medicamentos = $valor_medicamentos;
        $capeante->valor_materiais = $valor_materiais;
        $capeante->valor_oxig = $valor_oxig;
        $capeante->valor_sadt = $valor_sadt;
        $capeante->valor_taxa = $valor_taxa;
        $capeante->valor_opme = $valor_opme;
        $capeante->senha_finalizada = $senha_finalizada;
        $capeante->em_auditoria_cap = $em_auditoria_cap;
        $capeante->desconto_valor_cap = $desconto_valor_cap;
        $capeante->negociado_desconto_cap = $negociado_desconto_cap;
        $capeante->conta_parada_cap = $conta_parada_cap;
        $capeante->parada_motivo_cap = $parada_motivo_cap;
        $capeante->encerrado_cap = $encerrado_cap;
        $capeante->aberto_cap = $aberto_cap;

        $capeante->fk_user_cap = $fk_user_cap;
        $capeante->usuario_create_cap = $usuario_create_cap;
        $capeante->data_create_cap = $data_create_cap;
        $capeante->timer_start_cap = $timer_start_cap;
        $capeante->timer_end_cap = $timer_end_cap;
        $capeante->last_cap = $last_cap;

        $capeante->fk_id_aud_enf = $fk_id_aud_enf;
        $capeante->fk_id_aud_med = $fk_id_aud_med;
        $capeante->fk_id_aud_adm = $fk_id_aud_adm;
        $capeante->fk_id_aud_hosp = $fk_id_aud_hosp;

        $capeanteDao->create($capeante);
        $idCapeante = (int)$conn->lastInsertId();
        $after = $idCapeante > 0 ? $capeanteDao->findById($idCapeante) : array_merge(get_object_vars($capeante), ['id_capeante' => null]);
        fullcareAuditLog($conn, [
            'action' => 'create',
            'entity_type' => 'capeante',
            'entity_id' => $idCapeante > 0 ? $idCapeante : null,
            'after' => $after,
            'source' => 'process_capeante.php',
        ], $BASE_URL);
    }
    header('location: list_internacao_cap.php');
}

if ($type === "update") {
    // Receber os dados dos inputs
    $id_capeante = filter_input(INPUT_POST, "id_capeante");
    $adm_capeante = filter_input(INPUT_POST, "adm_capeante") ?: null;
    $aud_enf_capeante = filter_input(INPUT_POST, "aud_enf_capeante");
    $aud_med_capeante = filter_input(INPUT_POST, "aud_med_capeante");

    $fk_int_capeante = filter_input(INPUT_POST, "fk_int_capeante");
    $fk_user_cap = filter_input(INPUT_POST, "fk_user_cap");

    $data_inicial_capeante = filter_input(INPUT_POST, "data_inicial_capeante") ?: null;
    $data_fech_capeante = filter_input(INPUT_POST, "data_fech_capeante") ?: null;
    $data_final_capeante = filter_input(INPUT_POST, "data_final_capeante") ?: null;
    $data_digit_capeante = filter_input(INPUT_POST, "data_digit_capeante") ?: null;
    $timer_cap = filter_input(INPUT_POST, "timer_cap", FILTER_VALIDATE_INT);
    if ($timer_cap === false) {
        $timer_cap = null;
    }
    $diarias_capeante = filter_input(INPUT_POST, "diarias_capeante");
    $lote_cap = filter_input(INPUT_POST, "lote_cap");
    $acomodacao_cap = filter_input(INPUT_POST, "acomodacao_cap");

    $glosa_diaria = limparCampo(filter_input(INPUT_POST, "glosa_diaria"));
    $glosa_honorarios = limparCampo(filter_input(INPUT_POST, "glosa_honorarios"));
    $glosa_matmed = limparCampo(filter_input(INPUT_POST, "glosa_matmed"));
    $glosa_medicamentos = limparCampo(filter_input(INPUT_POST, "glosa_medicamentos"));
    $glosa_materiais = limparCampo(filter_input(INPUT_POST, "glosa_materiais"));
    $glosa_oxig = limparCampo(filter_input(INPUT_POST, "glosa_oxig"));
    $glosa_sadt = limparCampo(filter_input(INPUT_POST, "glosa_sadt"));
    $glosa_taxas = limparCampo(filter_input(INPUT_POST, "glosa_taxas"));
    $glosa_opme = limparCampo(filter_input(INPUT_POST, "glosa_opme"));

    $adm_check = filter_input(INPUT_POST, "adm_check") ?: 'n';
    $med_check = filter_input(INPUT_POST, "med_check") ?: 'n';
    $enfer_check = filter_input(INPUT_POST, "enfer_check") ?: 'n';

    $pacote = filter_input(INPUT_POST, "pacote") ?: "n";
    $parcial_capeante = filter_input(INPUT_POST, "parcial_capeante") ?: "n";
    $parcial_num = filter_input(INPUT_POST, "parcial_num");
    $fk_int_capeante = filter_input(INPUT_POST, "fk_int_capeante");
    $negociado_desconto_cap = filter_input(INPUT_POST, "negociado_desconto_cap");
    $desconto_valor_cap = filter_input(INPUT_POST, "desconto_valor_cap") ?: NULL;
    $senha_finalizada = filter_input(INPUT_POST, "senha_finalizada") ?: "n";
    $em_auditoria_cap = filter_input(INPUT_POST, "em_auditoria_cap");
    $encerrado_cap = filter_input(INPUT_POST, "encerrado_cap");
    $aberto_cap = filter_input(INPUT_POST, "aberto_cap");

    $conta_parada_cap = filter_input(INPUT_POST, "conta_parada_cap") ?: NULL;
    $parada_motivo_cap = filter_input(INPUT_POST, "parada_motivo_cap") ?: NULL;

    $valor_apresentado_capeante = limparCampo(filter_input(INPUT_POST, "valor_apresentado_capeante"));
    $valor_final_capeante = limparCampo(filter_input(INPUT_POST, "valor_final_capeante"));
    $valor_diarias = limparCampo(filter_input(INPUT_POST, "valor_diarias"));
    $valor_matmed = limparCampo(filter_input(INPUT_POST, "valor_matmed"));
    $valor_medicamentos = limparCampo(filter_input(INPUT_POST, "valor_medicamentos"));
    $valor_materiais = limparCampo(filter_input(INPUT_POST, "valor_materiais"));
    $valor_oxig = limparCampo(filter_input(INPUT_POST, "valor_oxig"));
    $valor_sadt = limparCampo(filter_input(INPUT_POST, "valor_sadt"));
    $valor_taxa = limparCampo(filter_input(INPUT_POST, "valor_taxa"));
    $valor_honorarios = limparCampo(filter_input(INPUT_POST, "valor_honorarios"));
    $valor_opme = limparCampo(filter_input(INPUT_POST, "valor_opme"));

    $valor_glosa_enf = limparCampo(filter_input(INPUT_POST, "valor_glosa_enf"));
    $valor_glosa_med = limparCampo(filter_input(INPUT_POST, "valor_glosa_med"));
    $valor_glosa_total = limparCampo(filter_input(INPUT_POST, "valor_glosa_total"));

    $fk_user_cap = filter_input(INPUT_POST, "fk_user_cap");
    $usuario_create_cap = filter_input(INPUT_POST, "usuario_create_cap");
    $data_create_cap = filter_input(INPUT_POST, "data_create_cap");

    $fk_id_aud_enf = filter_input(INPUT_POST, "fk_id_aud_enf");
    $fk_id_aud_med = filter_input(INPUT_POST, "fk_id_aud_med");
    $fk_id_aud_adm = filter_input(INPUT_POST, "fk_id_aud_adm");
    $fk_id_aud_hosp = filter_input(INPUT_POST, "fk_id_aud_hosp");
    $checkbox_imprimir = filter_input(INPUT_POST, "checkbox_imprimir");

    $before = $capeanteDao->findById($id_capeante);
    $capeanteUpdate = new capeante();
    if (empty($data_digit_capeante)) {
        $message->setMessage("Data de digitação é obrigatória.", "error", "back");
        exit;
    }

    // Validação mínima de dados
    if (!empty(3 < 4)) {

        $capeanteUpdate->adm_capeante = $adm_capeante;
        $capeanteUpdate->adm_check = $adm_check;
        $capeanteUpdate->aud_enf_capeante = $aud_enf_capeante;
        $capeanteUpdate->aud_med_capeante = $aud_med_capeante;

    $capeanteUpdate->data_fech_capeante = $data_fech_capeante;
    $capeanteUpdate->data_digit_capeante = $data_digit_capeante;
    $capeanteUpdate->data_final_capeante = $data_final_capeante;
        $capeanteUpdate->data_inicial_capeante = $data_inicial_capeante;
        $capeanteUpdate->timer_start_cap = $timer_start_cap;
        $capeanteUpdate->timer_end_cap = $timer_end_cap;
        $capeanteUpdate->timer_cap = $timer_cap;
        $capeanteUpdate->diarias_capeante = $diarias_capeante;
        $capeanteUpdate->lote_cap = $lote_cap;
        $capeanteUpdate->acomodacao_cap = $acomodacao_cap;

        $capeanteUpdate->glosa_diaria = $glosa_diaria;
        $capeanteUpdate->glosa_honorarios = $glosa_honorarios;
        $capeanteUpdate->glosa_matmed = $glosa_matmed;
        $capeanteUpdate->glosa_medicamentos = $glosa_medicamentos;
        $capeanteUpdate->glosa_materiais = $glosa_materiais;
        $capeanteUpdate->glosa_oxig = $glosa_oxig;
        $capeanteUpdate->glosa_sadt = $glosa_sadt;
        $capeanteUpdate->glosa_taxas = $glosa_taxas;
        $capeanteUpdate->glosa_opme = $glosa_opme;

        $capeanteUpdate->med_check = $med_check;
        $capeanteUpdate->enfer_check = $enfer_check;

        $capeanteUpdate->pacote = $pacote;
        $capeanteUpdate->parcial_capeante = $parcial_capeante;
        $capeanteUpdate->parcial_num = $parcial_num;
        $capeanteUpdate->fk_int_capeante = $fk_int_capeante;

        $capeanteUpdate->senha_finalizada = $senha_finalizada;
        $capeanteUpdate->em_auditoria_cap = $em_auditoria_cap;
        $capeanteUpdate->encerrado_cap = $encerrado_cap;
        $capeanteUpdate->aberto_cap = $aberto_cap;

        $capeanteUpdate->valor_apresentado_capeante = $valor_apresentado_capeante;
        $capeanteUpdate->negociado_desconto_cap = $negociado_desconto_cap;
        $capeanteUpdate->desconto_valor_cap = $desconto_valor_cap;

        $capeanteUpdate->conta_parada_cap = $conta_parada_cap;
        $capeanteUpdate->parada_motivo_cap = $parada_motivo_cap;

        $capeanteUpdate->valor_glosa_enf = $valor_glosa_enf;
        $capeanteUpdate->valor_glosa_med = $valor_glosa_med;
        $capeanteUpdate->valor_glosa_total = $valor_glosa_total;
        $capeanteUpdate->valor_final_capeante = $valor_final_capeante;

        $capeanteUpdate->valor_diarias = $valor_diarias;
        $capeanteUpdate->valor_honorarios = $valor_honorarios;
        $capeanteUpdate->valor_matmed = $valor_matmed;
        $capeanteUpdate->valor_medicamentos = $valor_medicamentos;
        $capeanteUpdate->valor_materiais = $valor_materiais;
        $capeanteUpdate->valor_oxig = $valor_oxig;
        $capeanteUpdate->valor_sadt = $valor_sadt;
        $capeanteUpdate->valor_taxa = $valor_taxa;
        $capeanteUpdate->valor_opme = $valor_opme;

        $capeanteUpdate->id_capeante = $id_capeante;

        $capeanteUpdate->fk_user_cap = $fk_user_cap;
        $capeanteUpdate->usuario_create_cap = $usuario_create_cap;
        $capeanteUpdate->data_create_cap = $data_create_cap;
        $capeanteUpdate->timer_start_cap = $timer_start_cap;
        $capeanteUpdate->timer_end_cap = $timer_end_cap;
        $capeanteUpdate->timer_cap = $timer_cap;

        // $capeanteUpdate->impresso_cap = $impresso_cap;
        $capeanteUpdate->fk_id_aud_enf = $fk_id_aud_enf;
        $capeanteUpdate->fk_id_aud_med = $fk_id_aud_med;
        $capeanteUpdate->fk_id_aud_adm = $fk_id_aud_adm;
        $capeanteUpdate->fk_id_aud_hosp = $fk_id_aud_hosp;
        $capeanteDao->update($capeanteUpdate);
        $after = $capeanteDao->findById($id_capeante);
        fullcareAuditLog($conn, [
            'action' => 'update',
            'entity_type' => 'capeante',
            'entity_id' => (int)$id_capeante,
            'before' => $before,
            'after' => $after ?: $capeanteUpdate,
            'source' => 'process_capeante.php',
        ], $BASE_URL);
    }
    if ($checkbox_imprimir == '1') {
        header('location: show_capeantePrt.php?id_capeante=' . $id_capeante);
    } else {
        header('location: list_internacao_cap.php');
    }
}
