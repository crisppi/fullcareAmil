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
require_once("models/acomodacao.php");
require_once("models/message.php");
require_once("dao/usuarioDao.php");
require_once("dao/acomodacaoDao.php");
require_once("utils/audit_logger.php");

$message = new Message($BASE_URL);
$userDao = new UserDAO($conn, $BASE_URL);
$acomodacaoDao = new acomodacaoDAO($conn, $BASE_URL);

$redirect_hospital_id = filter_input(INPUT_POST, "redirect_hospital_id", FILTER_VALIDATE_INT);
if (!$redirect_hospital_id) {
    $redirect_hospital_id = filter_input(INPUT_GET, "redirect_hospital_id", FILTER_VALIDATE_INT);
}

function redirectAcomodacao($BASE_URL, $redirect_hospital_id)
{
    if (!empty($redirect_hospital_id)) {
        header("Location: " . rtrim($BASE_URL, '/') . "/hospital_acomodacoes.php?id_hospital=" . (int) $redirect_hospital_id);
    } else {
        header('location:list_acomodacao.php');
    }
    exit;
}

// Resgata o tipo do formulário
$type = filter_input(INPUT_POST, "type");

// Resgata dados do usuário

if ($type === "create") {

    // Receber os dados dos inputs
    $acomodacao_aco = filter_input(INPUT_POST, "acomodacao_aco");

    $valor_aco = filter_input(INPUT_POST, "valor_aco");
    $valor_aco = str_replace('R$', '', $valor_aco);
    $valor_aco = str_replace('.', '', $valor_aco);
    $valor_aco = str_replace(',', '.', $valor_aco);

    $fk_hospital = filter_input(INPUT_POST, "fk_hospital");
    $data_contrato_aco = filter_input(INPUT_POST, "data_contrato_aco");

    $fk_usuario_acomodacao = filter_input(INPUT_POST, "fk_usuario_acomodacao");
    $usuario_create_acomodacao = filter_input(INPUT_POST, "usuario_create_acomodacao");
    $data_create_acomodacao = filter_input(INPUT_POST, "data_create_acomodacao");

    $acomodacao = new acomodacao();

    // Validação mínima de dados
    if (!empty($acomodacao_aco)) {

        $acomodacao->acomodacao_aco = $acomodacao_aco;
        $acomodacao->valor_aco = $valor_aco;
        $acomodacao->fk_hospital = $fk_hospital;
        $acomodacao->data_contrato_aco = $data_contrato_aco;

        $acomodacao->fk_usuario_acomodacao = $fk_usuario_acomodacao;
        $acomodacao->usuario_create_acomodacao = $usuario_create_acomodacao;
        $acomodacao->data_create_acomodacao = $data_create_acomodacao;

        $acomodacaoDao->create($acomodacao);
        $novoIdAcomodacao = (int)$conn->lastInsertId();
        $acomodacaoCriada = $novoIdAcomodacao > 0 ? $acomodacaoDao->findById($novoIdAcomodacao) : null;
        fullcareAuditLog($conn, [
            'action' => 'create',
            'entity_type' => 'acomodacao',
            'entity_id' => $novoIdAcomodacao > 0 ? $novoIdAcomodacao : null,
            'summary' => 'Acomodação criada.',
            'after' => $acomodacaoCriada ?: get_object_vars($acomodacao),
            'trace_id' => isset($__flowCtxAuto) ? ($__flowCtxAuto['trace_id'] ?? null) : null,
            'source' => 'process_acomodacao.php',
        ], $BASE_URL);
        redirectAcomodacao($BASE_URL, $redirect_hospital_id ?: $fk_hospital);
    } else {

        $message->setMessage("Você precisa adicionar pelo menos: acomodacao_aco do acomodacao!", "error", "back");
    }
} else if ($type === "update") {


    $acomodacao = new Acomodacao();
    // Receber os dados dos inputs
    $id_acomodacao = filter_input(INPUT_POST, "id_acomodacao");
    $fk_hospital = filter_input(INPUT_POST, "fk_hospital");
    $acomodacao_aco = filter_input(INPUT_POST, "acomodacao_aco");
    $valor_aco = filter_input(INPUT_POST, "valor_aco");
    $valor_aco = str_replace('R$', '', $valor_aco);
    $valor_aco = str_replace('.', '', $valor_aco);
    $valor_aco = str_replace(',', '.', $valor_aco);
    $data_contrato_aco = filter_input(INPUT_POST, "data_contrato_aco");

    $fk_usuario_acomodacao = filter_input(INPUT_POST, "fk_usuario_acomodacao");
    $usuario_create_acomodacao = filter_input(INPUT_POST, "usuario_create_acomodacao");
    $data_create_acomodacao = filter_input(INPUT_POST, "data_create_acomodacao");

    $acomodacao = $acomodacaoDao->joinAcomodacaoHospitalshow($id_acomodacao);
    $acomodacaoAntes = is_array($acomodacao) ? $acomodacao : [];

    $acomodacao['id_acomodacao'] = $id_acomodacao;
    $acomodacao['fk_hospital'] = $fk_hospital;
    $acomodacao['valor_aco'] = $valor_aco;
    $acomodacao['acomodacao_aco'] = $acomodacao_aco;
    $acomodacao['fk_usuario_acomodacao'] = $fk_usuario_acomodacao;
    $acomodacao['usuario_create_acomodacao'] = $usuario_create_acomodacao;
    $acomodacao['data_create_acomodacao'] = $data_create_acomodacao;
    $acomodacao['data_contrato_aco'] = $data_contrato_aco;
    $acomodacaoDao->update($acomodacao);
    $acomodacaoDepois = $acomodacaoDao->findById((int)$id_acomodacao);
    fullcareAuditLog($conn, [
        'action' => 'update',
        'entity_type' => 'acomodacao',
        'entity_id' => (int)$id_acomodacao,
        'summary' => 'Acomodação atualizada.',
        'before' => $acomodacaoAntes,
        'after' => $acomodacaoDepois,
        'trace_id' => isset($__flowCtxAuto) ? ($__flowCtxAuto['trace_id'] ?? null) : null,
        'source' => 'process_acomodacao.php',
    ], $BASE_URL);

    redirectAcomodacao($BASE_URL, $redirect_hospital_id ?: $fk_hospital);
}

$typeDelete = filter_input(INPUT_GET, "type");
if (!$typeDelete) {
    $typeDelete = filter_input(INPUT_POST, "type");
}

if ($typeDelete === "delete") {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        redirectAcomodacao($BASE_URL, $redirect_hospital_id);
    }
    $csrf = (string)filter_input(INPUT_POST, 'csrf', FILTER_UNSAFE_RAW);
    if (!csrf_is_valid($csrf)) {
        http_response_code(400);
        $message->setMessage("CSRF inválido.", "error", "back");
        exit;
    }

    // Recebe os dados do form
    $id_acomodacao = filter_input(INPUT_POST, "id_acomodacao", FILTER_VALIDATE_INT);

    $acomodacaoDao = new acomodacaoDAO($conn, $BASE_URL);

    $acomodacao = $acomodacaoDao->joinAcomodacaoHospitalShow($id_acomodacao);
    if ($acomodacao) {
        $acomodacaoAntesDelete = $acomodacao;
        $acomodacaoDao->destroy($id_acomodacao);
        fullcareAuditLog($conn, [
            'action' => 'delete',
            'entity_type' => 'acomodacao',
            'entity_id' => (int)$id_acomodacao,
            'summary' => 'Acomodação excluída.',
            'before' => $acomodacaoAntesDelete,
            'trace_id' => isset($__flowCtxAuto) ? ($__flowCtxAuto['trace_id'] ?? null) : null,
            'source' => 'process_acomodacao.php',
        ], $BASE_URL);
        $fk_hosp = isset($acomodacao['fk_hospital']) ? (int) $acomodacao['fk_hospital'] : 0;
        redirectAcomodacao($BASE_URL, $redirect_hospital_id ?: $fk_hosp);
    } else {

        $message->setMessage("Informações inválidas!", "error", "index.php");
    }
    redirectAcomodacao($BASE_URL, $redirect_hospital_id);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_hospital'])) {
    $id_hospital = filter_var($_POST['id_hospital'], FILTER_VALIDATE_INT);

    if ($id_hospital) {
        // Condição parametrizada para evitar concatenação de valor em SQL
        $where = 'ho.id_hospital = :id_hospital';
        $whereParams = [':id_hospital' => (int)$id_hospital];

        // Obtenha as acomodações
        $acomodacaoDao = new AcomodacaoDAO($conn, $BASE_URL);
        $acomodacoes = $acomodacaoDao->selectAllacomodacao($where, null, null, $whereParams);

        if ($acomodacoes) {
            echo json_encode(['status' => 'success', 'acomodacoes' => $acomodacoes]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Nenhuma acomodação encontrada.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ID do hospital inválido.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Nenhum hospital foi selecionado.']);
}
exit;
