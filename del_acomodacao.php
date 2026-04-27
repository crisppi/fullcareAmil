<?php
define('SKIP_HEADER', true);
include_once("check_logado.php");

require_once("globals.php");
require_once("db.php");
require_once("models/acomodacao.php");
require_once("models/message.php");
require_once("dao/usuarioDao.php");
require_once("dao/acomodacaoDao.php");
require_once("utils/audit_logger.php");

//$message = new Message($BASE_URL);
$userDao = new UserDAO($conn, $BASE_URL);
$acomodacaoDao = new acomodacaoDAO($conn, $BASE_URL);
Gate::enforceAction($conn, $BASE_URL, 'delete', 'Você não tem permissão para excluir acomodação.');
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Location: ' . $BASE_URL . 'list_acomodacao.php', true, 303);
    exit;
}

$csrf = (string)filter_input(INPUT_POST, 'csrf', FILTER_UNSAFE_RAW);
if (!csrf_is_valid($csrf)) {
    http_response_code(400);
    header('Location: ' . $BASE_URL . 'list_acomodacao.php', true, 303);
    exit;
}

$id_acomodacao = filter_input(INPUT_POST, "id_acomodacao", FILTER_VALIDATE_INT);
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
        'source' => 'del_acomodacao.php',
    ], $BASE_URL);
}

header('Location: ' . $BASE_URL . 'list_acomodacao.php', true, 303);
exit;
