<?php
define('SKIP_HEADER', true);
include_once("check_logado.php");

require_once("globals.php");
require_once("db.php");
require_once("models/usuario.php");
require_once("models/message.php");
require_once("dao/usuarioDao.php");
require_once("utils/audit_logger.php");
?>
<?php
$message = new Message($BASE_URL);
$usuarioDao = new userDAO($conn, $BASE_URL);
Gate::enforceAction($conn, $BASE_URL, 'delete', 'Você não tem permissão para excluir usuário.');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Location: ' . $BASE_URL . 'list_usuario.php', true, 303);
    exit;
}

$csrf = (string)filter_input(INPUT_POST, 'csrf', FILTER_UNSAFE_RAW);
if (!csrf_is_valid($csrf)) {
    http_response_code(400);
    $message->setMessage("CSRF inválido.", "error", "list_usuario.php");
    exit;
}

$id_usuario = filter_input(INPUT_POST, "id_usuario", FILTER_VALIDATE_INT);
$usuario = $usuarioDao->findById_user($id_usuario);

if ($usuario) {
    $usuarioAntesDelete = clone $usuario;
    $usuarioDao->destroy($id_usuario);
    fullcareAuditLog($conn, [
        'action' => 'delete',
        'entity_type' => 'usuario',
        'entity_id' => (int)$id_usuario,
        'summary' => 'Usuário excluído/inativado.',
        'before' => $usuarioAntesDelete,
        'after' => null,
        'source' => 'del_usuario.php',
    ], $BASE_URL);
    header('Location: ' . $BASE_URL . 'list_usuario.php', true, 303);
    exit;
}

$message->setMessage("Informações inválidas!", "error", "index.php");
