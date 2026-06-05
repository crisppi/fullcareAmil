<?php
define('SKIP_HEADER', true);
include_once("check_logado.php");

require_once("globals.php");
require_once("db.php");
require_once("models/censo.php");
require_once("models/message.php");
require_once("dao/usuarioDao.php");
require_once("dao/censoDao.php");
require_once("utils/audit_logger.php");

$userDao = new UserDAO($conn, $BASE_URL);
$censoDao = new censoDAO($conn, $BASE_URL);
Gate::enforceAction($conn, $BASE_URL, 'delete', 'Você não tem permissão para excluir censo.');

$message = new Message($BASE_URL);
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Location: ' . $BASE_URL . 'censo/lista', true, 303);
    exit;
}

$csrf = (string)filter_input(INPUT_POST, 'csrf', FILTER_UNSAFE_RAW);
if (!csrf_is_valid($csrf)) {
    http_response_code(400);
    $message->setMessage("CSRF inválido.", "error", "censo/lista");
    exit;
}

$id_censo = filter_input(INPUT_POST, "id_censo", FILTER_VALIDATE_INT);
if ($id_censo) {
    $censoAntesDelete = $censoDao->findById($id_censo);
    $censoDao->destroy($id_censo);
    fullcareAuditLog($conn, [
        'action' => 'delete',
        'entity_type' => 'censo',
        'entity_id' => (int)$id_censo,
        'summary' => 'Censo excluído.',
        'before' => $censoAntesDelete,
        'source' => 'del_censo.php',
    ], $BASE_URL);
}

header('Location: ' . $BASE_URL . 'censo/lista', true, 303);
exit;
