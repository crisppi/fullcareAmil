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

ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once("check_logado.php");          // se seu projeto usa para sessão
require_once("globals.php");               // onde estiver $BASE_URL (ajuste se necessário)
require_once("db.php");                    // onde estiver $conn (ajuste se necessário)

require_once("models/hospitalUser.php");
require_once("dao/hospitalUserDao.php");
require_once("utils/audit_logger.php");

// Instancia o DAO
$hospitalUserDao = new hospitalUserDAO($conn, $BASE_URL);

// Coleta e normaliza os dados do POST
$type             = filter_input(INPUT_POST, 'type');
$id_hospitalUser  = filter_input(INPUT_POST, 'id_hospitalUser', FILTER_VALIDATE_INT);
$fk_usuario_hosp  = filter_input(INPUT_POST, 'fk_usuario_hosp', FILTER_VALIDATE_INT);
$fk_hospital_user = filter_input(INPUT_POST, 'fk_hospital_user', FILTER_VALIDATE_INT);
$redirect_hospital_id = filter_input(INPUT_POST, 'redirect_hospital_id', FILTER_VALIDATE_INT);

// Garantia de inteiros (0 quando null)
$id_hospitalUser  = $id_hospitalUser  ?: 0;
$fk_usuario_hosp  = $fk_usuario_hosp  ?: 0;
$fk_hospital_user = $fk_hospital_user ?: 0;
$redirect_hospital_id = $redirect_hospital_id ?: 0;

$redirectUrl = $redirect_hospital_id > 0
    ? rtrim($BASE_URL, '/') . '/hospital_usuarios.php?id_hospital=' . (int) $redirect_hospital_id
    : rtrim($BASE_URL, '/') . '/list_hospitalUser.php';

// Validação simples
if (!in_array($type, ['create', 'update'], true)) {
    // fallback: se não vier 'type', decide por id > 0
    $type = $id_hospitalUser > 0 ? 'update' : 'create';
}

try {
    if ($type === 'create') {

        // cria o objeto do modelo
        $hu = new hospitalUser();
        $hu->fk_usuario_hosp  = (int)$fk_usuario_hosp;
        $hu->fk_hospital_user = (int)$fk_hospital_user;

        // valida mínimos
        if ($hu->fk_usuario_hosp <= 0 || $hu->fk_hospital_user <= 0) {
            throw new RuntimeException("Selecione um usuário e um hospital válidos.");
        }

        $stmtDupe = $conn->prepare("SELECT id_hospitalUser FROM tb_hospitalUser WHERE fk_usuario_hosp = :u AND fk_hospital_user = :h LIMIT 1");
        $stmtDupe->bindValue(':u', $hu->fk_usuario_hosp, PDO::PARAM_INT);
        $stmtDupe->bindValue(':h', $hu->fk_hospital_user, PDO::PARAM_INT);
        $stmtDupe->execute();
        if ($stmtDupe->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException("Usuário já vinculado a este hospital.");
        }

        // persiste
        $hospitalUserDao->create($hu);
        $novoIdHospitalUser = (int)$conn->lastInsertId();
        $hospitalUserCriado = $novoIdHospitalUser > 0 ? $hospitalUserDao->findById($novoIdHospitalUser) : null;
        fullcareAuditLog($conn, [
            'action' => 'create',
            'entity_type' => 'hospital_user',
            'entity_id' => $novoIdHospitalUser > 0 ? $novoIdHospitalUser : null,
            'summary' => 'Vínculo hospital-usuário criado.',
            'after' => $hospitalUserCriado ?: get_object_vars($hu),
            'trace_id' => isset($__flowCtxAuto) ? ($__flowCtxAuto['trace_id'] ?? null) : null,
            'source' => 'process_hospitalUser.php',
        ], $BASE_URL);
        header('Location: ' . $redirectUrl, true, 303);
        exit;
    } elseif ($type === 'update') {

        // cria o objeto do modelo
        $hu = new hospitalUser();
        $hu->id_hospitalUser  = (int)$id_hospitalUser;
        $hu->fk_usuario_hosp  = (int)$fk_usuario_hosp;
        $hu->fk_hospital_user = (int)$fk_hospital_user;

        if ($hu->id_hospitalUser <= 0) {
            throw new RuntimeException("ID do vínculo inválido para atualizar.");
        }
        if ($hu->fk_usuario_hosp <= 0 || $hu->fk_hospital_user <= 0) {
            throw new RuntimeException("Selecione um usuário e um hospital válidos.");
        }

        $stmtDupe = $conn->prepare("SELECT id_hospitalUser FROM tb_hospitalUser WHERE fk_usuario_hosp = :u AND fk_hospital_user = :h AND id_hospitalUser <> :id LIMIT 1");
        $stmtDupe->bindValue(':u', $hu->fk_usuario_hosp, PDO::PARAM_INT);
        $stmtDupe->bindValue(':h', $hu->fk_hospital_user, PDO::PARAM_INT);
        $stmtDupe->bindValue(':id', $hu->id_hospitalUser, PDO::PARAM_INT);
        $stmtDupe->execute();
        if ($stmtDupe->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException("Usuário já vinculado a este hospital.");
        }

        // persiste
        $hospitalUserAntes = $hospitalUserDao->findById($hu->id_hospitalUser);
        $hospitalUserDao->update($hu);
        $hospitalUserDepois = $hospitalUserDao->findById($hu->id_hospitalUser);
        fullcareAuditLog($conn, [
            'action' => 'update',
            'entity_type' => 'hospital_user',
            'entity_id' => (int)$hu->id_hospitalUser,
            'summary' => 'Vínculo hospital-usuário atualizado.',
            'before' => $hospitalUserAntes,
            'after' => $hospitalUserDepois,
            'trace_id' => isset($__flowCtxAuto) ? ($__flowCtxAuto['trace_id'] ?? null) : null,
            'source' => 'process_hospitalUser.php',
        ], $BASE_URL);
        header('Location: ' . $redirectUrl, true, 303);
        exit;
    }
} catch (Throwable $e) {
    // usa o sistema de mensagens já existente
    $hospitalUserDao->message->setMessage(
        "Erro ao processar: " . $e->getMessage(),
        "error",
        $redirect_hospital_id > 0 ? ('hospital_usuarios.php?id_hospital=' . (int) $redirect_hospital_id) : "list_hospitalUser.php"
    );
    header('Location: ' . $redirectUrl, true, 303);
    exit;
}
