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
require_once("dao/usuarioDao.php");
require_once("utils/audit_logger.php");

try {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    header('Content-Type: application/json; charset=utf-8');

    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
        exit;
    }

    $idSessao = (int)($_SESSION['id_usuario'] ?? 0);
    $ativo = strtolower((string)($_SESSION['ativo'] ?? ''));
    $cargo = (string)($_SESSION['cargo'] ?? '');
    $nivel = (string)($_SESSION['nivel'] ?? '');

    $norm = static function ($txt): string {
        $txt = mb_strtolower(trim((string)$txt), 'UTF-8');
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
        $txt = $ascii !== false ? $ascii : $txt;
        return preg_replace('/[^a-z]/', '', $txt);
    };

    $isDiretoria = in_array($norm($cargo), ['diretoria', 'diretor', 'administrador', 'admin', 'board'], true)
        || in_array($norm($nivel), ['diretoria', 'diretor', 'administrador', 'admin', 'board'], true)
        || ((int)$nivel === -1);

    if ($idSessao <= 0 || $ativo !== 's' || !$isDiretoria) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
        exit;
    }

    $csrf = (string)filter_input(INPUT_POST, 'csrf', FILTER_UNSAFE_RAW);
    if ($csrf === '' || empty($_SESSION['csrf']) || !hash_equals((string)$_SESSION['csrf'], $csrf)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'CSRF inválido.']);
        exit;
    }

    // Inicializa o DAO de usuário
    $usuarioDAO = new UserDAO($conn, $BASE_URL);

    // Obtém o ID do usuário via POST
    $id_user = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);

    if (!$id_user) {
        throw new Exception("ID do usuário inválido ou não fornecido.");
    }

    // Gera senha temporária aleatória forte (não previsível)
    $tempPassword = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(12))), 0, 12);
    $senha_user = password_hash($tempPassword, PASSWORD_DEFAULT);

    // Busca o usuário pelo ID
    $usuario = $usuarioDAO->findById_user($id_user);
    $before = $usuario ? clone $usuario : null;

    if (!$usuario) {
        throw new Exception("Usuário não encontrado para o ID fornecido: $id_user.");
    }

    // Atualiza os dados do usuário
    $usuario->senha_default_user = 's';
    $usuario->senha_user = $senha_user;

    $usuarioDAO->update($usuario);
    fullcareAuditLog($conn, [
        'action' => 'update.password',
        'entity_type' => 'usuario',
        'entity_id' => (int)$id_user,
        'before' => $before,
        'after' => $usuario,
        'summary' => 'Senha resetada por administrador.',
        'source' => 'process_reset_senha.php',
    ], $BASE_URL);

    echo json_encode([
        'success' => true,
        'message' => 'Senha resetada com sucesso.',
        'temporary_password' => $tempPassword,
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    // Log de erro
    error_log("Erro ao processar a requisição: " . $e->getMessage());

    // Retorno de erro para o cliente
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Ocorreu um erro ao processar a requisição."
    ]);
}
