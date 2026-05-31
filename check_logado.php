<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('fullcare_login_index_url')) {
    function fullcare_login_index_url(): string
    {
        $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        if (preg_match('#^/(FullCare|FullConex(?:Aud)?)(/|$)#i', $script, $m)) {
            return '/' . trim($m[1], '/') . '/index.php';
        }
        return '/index.php';
    }
}

require_once(__DIR__ . "/app/security/bi_access.php");
require_once(__DIR__ . "/app/security/inteligencia_access.php");

if (empty($_SESSION['email_user']) && empty($_SESSION['id_usuario'])) {
    header('Location: ' . fullcare_login_index_url(), true, 303);
    exit;
}

$ativoRaw = (string)($_SESSION['ativo'] ?? '');
$ativoNorm = strtolower(trim($ativoRaw));
$ativoOk = in_array($ativoNorm, ['s', '1', 'true', 'ativo'], true);
if (!$ativoOk) {
    $erro_login = "Usuário inativo";
    $_SESSION['mensagem'] = $erro_login;
    header('Location: ' . fullcare_login_index_url(), true, 303);
    exit;
} else {
};

if (function_exists('fullcare_enforce_bi_access')) {
    fullcare_enforce_bi_access();
}
if (function_exists('fullcare_enforce_inteligencia_access')) {
    fullcare_enforce_inteligencia_access();
}

require_once(__DIR__ . "/utils/flow_logger.php");
if (function_exists('flowLog')) {
    $accessCtx = [
        'flow' => 'page_access',
        'trace_id' => $_SERVER['UNIQUE_ID'] ?? substr(md5((string)microtime(true) . (string)($_SESSION['id_usuario'] ?? '0')), 0, 16),
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'session_user_id' => $_SESSION['id_usuario'] ?? null,
        'session_user_name' => $_SESSION['usuario_user'] ?? ($_SESSION['login_user'] ?? ($_SESSION['email_user'] ?? null)),
        'ts' => date('c')
    ];
    flowLog($accessCtx, 'page.access', 'INFO', [
        'script' => basename((string)($_SERVER['SCRIPT_NAME'] ?? '')),
        'query_string' => $_SERVER['QUERY_STRING'] ?? null
    ]);
}
