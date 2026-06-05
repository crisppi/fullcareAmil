<?php

if (!defined("FLOW_LOGGER_AUTO_V1")) {
    define("FLOW_LOGGER_AUTO_V1", 1);
    @require_once(__DIR__ . "/../../utils/flow_logger.php");
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
require_once("app/passwordPolicy.php");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$token = filter_input(INPUT_POST, 'token', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
$code = preg_replace('/\D+/', '', (string)($_POST['codigo'] ?? ''));
$senha = (string)($_POST['senha'] ?? '');
$senha2 = (string)($_POST['senha2'] ?? '');

if (!$token) {
    $_SESSION['recuperacao_msg'] = 'Token inválido. Solicite um novo código.';
    $_SESSION['recuperacao_tipo'] = 'error';
    header('Location: ' . app_url('esqueci_senha.php'), true, 303);
    exit;
}

if ($senha === '' || $senha2 === '') {
    $_SESSION['recuperacao_msg'] = 'Preencha a nova senha e a confirmação.';
    $_SESSION['recuperacao_tipo'] = 'error';
    header('Location: ' . app_url('redefinir_senha.php') . '?token=' . urlencode($token), true, 303);
    exit;
}

if ($senha !== $senha2) {
    $_SESSION['recuperacao_msg'] = 'As senhas não conferem.';
    $_SESSION['recuperacao_tipo'] = 'error';
    header('Location: ' . app_url('redefinir_senha.php') . '?token=' . urlencode($token), true, 303);
    exit;
}

if ($policyErrors = password_policy_errors($senha)) {
    $_SESSION['recuperacao_msg'] = $policyErrors[0];
    $_SESSION['recuperacao_tipo'] = 'error';
    header('Location: ' . app_url('redefinir_senha.php') . '?token=' . urlencode($token), true, 303);
    exit;
}

try {
    $tokenHash = hash('sha256', $token);
    $stmt = $conn->prepare("
        SELECT id, user_id, code_hash, expires_at, used_at
          FROM tb_user_password_reset
         WHERE token_hash = :th
         LIMIT 1
    ");
    $stmt->bindValue(':th', $tokenHash);
    $stmt->execute();
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset || !empty($reset['used_at']) || strtotime($reset['expires_at']) < time()) {
        $_SESSION['recuperacao_msg'] = 'Link inválido ou expirado. Solicite um novo código.';
        $_SESSION['recuperacao_tipo'] = 'error';
        header('Location: ' . app_url('esqueci_senha.php'), true, 303);
        exit;
    }

    if (!$code || hash('sha256', $code) !== $reset['code_hash']) {
        $_SESSION['recuperacao_msg'] = 'Código inválido.';
        $_SESSION['recuperacao_tipo'] = 'error';
        header('Location: ' . app_url('redefinir_senha.php') . '?token=' . urlencode($token), true, 303);
        exit;
    }

    $hash = password_hash($senha, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("
        UPDATE tb_user
           SET senha_user = :senha,
               senha_default_user = 'n'
         WHERE id_usuario = :uid
    ");
    $stmt->bindValue(':senha', $hash);
    $stmt->bindValue(':uid', (int)$reset['user_id'], PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $conn->prepare("
        UPDATE tb_user_password_reset
           SET used_at = NOW()
         WHERE id = :id
    ");
    $stmt->bindValue(':id', (int)$reset['id'], PDO::PARAM_INT);
    $stmt->execute();

    $_SESSION['mensagem'] = 'Senha atualizada. Faça login novamente.';
    $_SESSION['mensagem_tipo'] = 'success';
    header('Location: ' . app_url('index.php'), true, 303);
    exit;
} catch (Throwable $e) {
    error_log('[REDEFINIR_SENHA] ' . $e->getMessage());
    $_SESSION['recuperacao_msg'] = 'Erro ao redefinir a senha. Tente novamente.';
    $_SESSION['recuperacao_tipo'] = 'error';
    header('Location: ' . app_url('redefinir_senha.php') . '?token=' . urlencode($token), true, 303);
    exit;
}
