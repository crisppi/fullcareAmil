<?php
define('SKIP_HEADER', true);
include_once("check_logado.php");

require_once("globals.php");
require_once("db.php");
require_once("models/message.php");
require_once("dao/usuarioDao.php");
require_once("dao/hospitalUserDao.php");
require_once("utils/audit_logger.php");

$userDao = new UserDAO($conn, $BASE_URL);
$hospitalUserDao = new hospitalUserDAO($conn, $BASE_URL);
Gate::enforceAction($conn, $BASE_URL, 'delete', 'Você não tem permissão para excluir vínculo hospital-usuário.');

$message = new Message($BASE_URL);
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Location: ' . $BASE_URL . 'list_hospitalUser.php', true, 303);
    exit;
}

$redirectHospitalId = filter_input(INPUT_POST, 'redirect_hospital_id', FILTER_VALIDATE_INT) ?: 0;
$redirectUrl = $redirectHospitalId > 0
    ? rtrim($BASE_URL, '/') . '/hospital_usuarios.php?id_hospital=' . (int) $redirectHospitalId
    : rtrim($BASE_URL, '/') . '/list_hospitalUser.php';

$csrf = (string)filter_input(INPUT_POST, 'csrf', FILTER_UNSAFE_RAW);
if (!csrf_is_valid($csrf)) {
    http_response_code(400);
    $message->setMessage("CSRF inválido.", "error", $redirectHospitalId > 0 ? ('hospital_usuarios.php?id_hospital=' . (int) $redirectHospitalId) : "list_hospitalUser.php");
    header('Location: ' . $redirectUrl, true, 303);
    exit;
}

$id_hospitalUser = filter_input(INPUT_POST, "id_hospitalUser", FILTER_VALIDATE_INT);
if ($id_hospitalUser) {
    $hospitalUserAntesDelete = $hospitalUserDao->findById($id_hospitalUser);
    $hospitalUserDao->destroy($id_hospitalUser);
    fullcareAuditLog($conn, [
        'action' => 'delete',
        'entity_type' => 'hospital_user',
        'entity_id' => (int)$id_hospitalUser,
        'summary' => 'Vínculo hospital-usuário excluído.',
        'before' => $hospitalUserAntesDelete,
        'source' => 'del_hosp_user.php',
    ], $BASE_URL);
}

header('Location: ' . $redirectUrl, true, 303);
exit;
