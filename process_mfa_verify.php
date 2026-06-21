<?php

include_once("globals.php");
require_once(__DIR__ . "/app/auth_session.php");
require_once(__DIR__ . "/app/schemaEnsurer.php");
require_once(__DIR__ . "/app/mfa.php");
require_once(__DIR__ . "/utils/audit_logger.php");
require_once(__DIR__ . "/utils/flow_logger.php");

ensure_user_login_security_columns($conn);
ensure_user_mfa_schema($conn);

$loginUrl = $BASE_URL . 'index.php';
$verifyUrl = $BASE_URL . 'mfa_verify.php';

if (!function_exists('mfa_verify_debug_log')) {
    function mfa_verify_debug_log(string $message): void
    {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents(__DIR__ . '/logs/mfa_debug.log', $line, FILE_APPEND);
        error_log('[MFA] ' . $message);
    }
}

if (!function_exists('mfa_verify_abort_login')) {
    function mfa_verify_abort_login(string $message, string $loginUrl): void
    {
        mfa_verify_debug_log('[VERIFY_ABORT] ' . $message . ' pending_user_id=' . (int)($_SESSION['mfa_pending_user_id'] ?? 0));
        unset(
            $_SESSION['mfa_pending_user_id'],
            $_SESSION['mfa_pending_issued_at'],
            $_SESSION['mfa_pending_attempts'],
            $_SESSION['mfa_pending_email'],
            $_SESSION['mfa_pending_token']
        );
        fullcare_login_session_clear();
        $_SESSION['login_error'] = $message;
        header('Location: ' . $loginUrl);
        exit;
    }
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . $loginUrl);
    exit;
}

$pendingUserId = (int)($_SESSION['mfa_pending_user_id'] ?? 0);
$issuedAt = (int)($_SESSION['mfa_pending_issued_at'] ?? 0);
if ($pendingUserId <= 0 || $issuedAt <= 0 || $issuedAt < (time() - 600)) {
    mfa_verify_abort_login('A verificação MFA expirou. Faça login novamente.', $loginUrl);
}

$mfaToken = (string)filter_input(INPUT_POST, 'mfa_token', FILTER_UNSAFE_RAW);
$sessionMfaToken = (string)($_SESSION['mfa_pending_token'] ?? '');
if ($mfaToken === '' || $sessionMfaToken === '' || !hash_equals($sessionMfaToken, $mfaToken)) {
    mfa_verify_debug_log('[VERIFY_FAIL] token_invalid pending_user_id=' . $pendingUserId . ' post_len=' . strlen($mfaToken) . ' session_len=' . strlen($sessionMfaToken));
    $_SESSION['mfa_pending_token'] = bin2hex(random_bytes(32));
    $_SESSION['mfa_verify_error'] = 'A tela de verificação expirou. Digite o código novamente.';
    header('Location: ' . $verifyUrl);
    exit;
}

$attempts = (int)($_SESSION['mfa_pending_attempts'] ?? 0);
if ($attempts >= 6) {
    mfa_verify_abort_login('Muitas tentativas de MFA. Faça login novamente.', $loginUrl);
}

$user = fullcare_mfa_fetch_user($conn, $pendingUserId);
if (!is_array($user) || ($user['ativo_user'] ?? 'n') !== 's' || !fullcare_mfa_user_enabled($user)) {
    mfa_verify_abort_login('Não foi possível concluir a verificação MFA.', $loginUrl);
}

$code = (string)filter_input(INPUT_POST, 'code', FILTER_UNSAFE_RAW);
if (!fullcare_mfa_verify_code_for_user($conn, $user, $code, true)) {
    mfa_verify_debug_log('[VERIFY_FAIL] invalid_code user_id=' . $pendingUserId . ' attempts=' . ($attempts + 1) . ' code_len=' . strlen(preg_replace('/\D+/', '', $code)));
    $_SESSION['mfa_pending_attempts'] = $attempts + 1;
    $_SESSION['mfa_verify_error'] = 'Código inválido. Tente novamente.';
    header('Location: ' . $verifyUrl);
    exit;
}

session_regenerate_id(true);
fullcare_login_session_start($user);
mfa_verify_debug_log('[VERIFY_SUCCESS] user_id=' . (int)($_SESSION['id_usuario'] ?? 0));

$target = fullcare_post_login_target($BASE_URL, $user);
if (function_exists('flowLogStart') && function_exists('flowLog')) {
    $loginCtx = flowLogStart('auth_login_mfa', [
        'session_user_id' => (int)($_SESSION['id_usuario'] ?? 0),
        'session_user_name' => (string)($_SESSION['usuario_user'] ?? ''),
        'email_user' => (string)($_SESSION['email_user'] ?? ''),
        'nivel' => (int)($_SESSION['nivel'] ?? 0),
        'cargo' => (string)($_SESSION['cargo'] ?? ''),
    ]);
    flowLog($loginCtx, 'login.mfa_success', 'INFO', [
        'target' => str_replace($BASE_URL, '', $target),
    ]);
    fullcareAuditLog($conn, [
        'action' => 'login.mfa_success',
        'entity_type' => 'login',
        'entity_id' => (int)($_SESSION['id_usuario'] ?? 0),
        'summary' => 'Login concluído com MFA.',
        'after' => $user,
        'context' => [
            'target' => str_replace($BASE_URL, '', $target),
            'nivel' => (int)($_SESSION['nivel'] ?? 0),
            'cargo' => (string)($_SESSION['cargo'] ?? ''),
        ],
        'trace_id' => $loginCtx['trace_id'] ?? null,
        'source' => 'process_mfa_verify.php',
    ], $BASE_URL);
}

if (($user['senha_default_user'] ?? 'n') === 's') {
    header('Location: ' . $BASE_URL . 'nova_senha.php');
    exit;
}

header('Location: ' . $target);
exit;
