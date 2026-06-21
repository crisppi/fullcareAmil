<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('fullcare_login_index_url')) {
    function fullcare_login_index_url(): string
    {
        $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        if (preg_match('#^/(fullcareAmil|FullCare|FullConex(?:Aud)?)(/|$)#i', $script, $m)) {
            return '/' . trim($m[1], '/') . '/index.php';
        }
        return '/index.php';
    }
}

require_once(__DIR__ . "/app/security/bi_access.php");
require_once(__DIR__ . "/app/security/inteligencia_access.php");
require_once(__DIR__ . "/app/schemaEnsurer.php");
require_once(__DIR__ . "/app/mfa.php");

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

if (!function_exists('fullcare_mfa_config_url')) {
    function fullcare_mfa_config_url(): string
    {
        $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        if (preg_match('#^/(fullcareAmil|FullCare|FullConex(?:Aud)?)(/|$)#i', $script, $m)) {
            return '/' . trim($m[1], '/') . '/mfa_configuracao.php';
        }
        return '/mfa_configuracao.php';
    }
}

if (!function_exists('fullcare_require_mfa_setup')) {
    function fullcare_require_mfa_setup(PDO $conn): void
    {
        $scriptBase = strtolower(basename((string)($_SERVER['SCRIPT_NAME'] ?? '')));
        $allowed = [
            'mfa_configuracao.php',
            'process_mfa_configuracao.php',
            'process_mfa_verify.php',
            'destroi.php',
            'logout.php',
            'nova_senha.php',
            'process_recuperar_senha.php',
            'process_redefinir_senha.php',
        ];

        if (in_array($scriptBase, $allowed, true)) {
            return;
        }
        if ($scriptBase === 'process_usuario.php' && strtolower((string)($_POST['type'] ?? '')) === 'update-senha') {
            return;
        }

        $userId = (int)($_SESSION['id_usuario'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        ensure_user_mfa_schema($conn);
        $user = fullcare_mfa_fetch_user($conn, $userId);
        if (is_array($user) && fullcare_mfa_user_enabled($user)) {
            return;
        }

        $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
        $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || strpos($accept, 'application/json') !== false
            || strpos(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false;

        if ($isAjax) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'mfa_required',
                'message' => 'Configure o MFA para continuar.',
                'redirect' => fullcare_mfa_config_url(),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Location: ' . fullcare_mfa_config_url(), true, 303);
        exit;
    }
}

require_once(__DIR__ . "/db.php");
fullcare_require_mfa_setup($conn);

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
