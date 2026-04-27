<?php

include_once("globals.php");
require_once(__DIR__ . "/utils/flow_logger.php");
require_once(__DIR__ . "/utils/audit_logger.php");
require_once(__DIR__ . '/app/schemaEnsurer.php');

ensure_user_login_security_columns($conn);

if (!function_exists('fullcare_post_login_target')) {
    function fullcare_post_login_target(string $baseUrl, array $user): string
    {
        $nivel = (int)($user['nivel_user'] ?? 0);
        $cargo = trim((string)($user['cargo_user'] ?? ''));
        $cargo = mb_strtolower($cargo, 'UTF-8');
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $cargo);
        $cargo = $ascii !== false ? $ascii : $cargo;
        $cargo = preg_replace('/[^a-z]/', '', $cargo);

        $isDiretoria = in_array($cargo, ['diretoria', 'diretor', 'administrador', 'admin', 'board'], true)
            || strpos($cargo, 'diretor') !== false
            || strpos($cargo, 'diretoria') !== false
            || $nivel === -1;

        if ($nivel === -1) {
            return $baseUrl . 'list_internacao_cap_fin.php';
        }

        return $isDiretoria
            ? $baseUrl . 'dashboard'
            : $baseUrl . 'menu_app.php';
    }
}

if (!function_exists('fullcare_normalize_login_identifier')) {
    function fullcare_normalize_login_identifier(string $value): string
    {
        $value = trim(mb_strtolower($value, 'UTF-8'));
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = $ascii !== false ? $ascii : $value;

        return preg_replace('/[^a-z0-9]/', '', $value);
    }
}

$redirectLogin = $BASE_URL . 'index.php';
$maxTentativasLogin = 6;
$lockMinutes = 15;
$hasLoginSecurityColumns = function_exists('schema_columns_exist')
    && schema_columns_exist($conn, 'tb_user', ['login_fail_count', 'login_locked_until', 'login_last_fail_at']);

$failLogin = static function (string $mensagem, string $attemptNotice = '') use ($redirectLogin): void {
    $_SESSION['login_error'] = $mensagem;
    if ($attemptNotice !== '') {
        $_SESSION['login_attempts_notice'] = $attemptNotice;
    } else {
        unset($_SESSION['login_attempts_notice']);
    }

    // Limpa dados de sessão de autenticação para não permitir entrada parcial.
    unset(
        $_SESSION['id_usuario'],
        $_SESSION['foto_usuario'],
        $_SESSION['email_user'],
        $_SESSION['senha_user'],
        $_SESSION['login_user'],
        $_SESSION['usuario_user'],
        $_SESSION['ativo'],
        $_SESSION['nivel'],
        $_SESSION['cargo'],
        $_SESSION['fk_seguradora_user']
    );

    header('Location: ' . $redirectLogin);
    exit;
};

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . $redirectLogin);
    exit;
}

$email_login = trim((string)filter_input(INPUT_POST, 'email_login', FILTER_SANITIZE_EMAIL));
$senha_login = (string)filter_input(INPUT_POST, 'senha_login');

if ($email_login === '' || $senha_login === '') {
    unset($_SESSION['login_error']);
    unset($_SESSION['login_attempts_notice']);
    header('Location: ' . $redirectLogin);
    exit;
}

try {
    $securitySelect = $hasLoginSecurityColumns
        ? ",
            login_fail_count,
            login_locked_until,
            login_last_fail_at"
        : "";

    $userSelect = "
        SELECT
            id_usuario,
            usuario_user,
            email_user,
            senha_user,
            senha_default_user,
            ativo_user,
            nivel_user,
            cargo_user,
            foto_usuario,
            fk_seguradora_user
            {$securitySelect}
        FROM tb_user
    ";

    $loginIdentifier = mb_strtolower($email_login, 'UTF-8');

    $stmt = $conn->prepare($userSelect . "
        WHERE LOWER(TRIM(email_user)) = :email_identifier
           OR LOWER(TRIM(email02_user)) = :email2_identifier
           OR LOWER(TRIM(login_user)) = :login_identifier
           OR LOWER(TRIM(usuario_user)) = :user_identifier
        LIMIT 1
    ");
    $stmt->bindValue(':email_identifier', $loginIdentifier, PDO::PARAM_STR);
    $stmt->bindValue(':email2_identifier', $loginIdentifier, PDO::PARAM_STR);
    $stmt->bindValue(':login_identifier', $loginIdentifier, PDO::PARAM_STR);
    $stmt->bindValue(':user_identifier', $loginIdentifier, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!is_array($user) && strpos($loginIdentifier, '@') !== false) {
        $alias = fullcare_normalize_login_identifier((string)strtok($loginIdentifier, '@'));

        if ($alias !== '') {
            $stmtAlias = $conn->prepare($userSelect . "
                WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(LOWER(TRIM(usuario_user)), ' ', ''), '.', ''), '-', ''), '_', ''), '''', '') = :alias
                LIMIT 2
            ");
            $stmtAlias->bindValue(':alias', $alias, PDO::PARAM_STR);
            $stmtAlias->execute();
            $aliasMatches = $stmtAlias->fetchAll(PDO::FETCH_ASSOC);

            if (count($aliasMatches) === 1) {
                $user = $aliasMatches[0];
            }
        }
    }
} catch (Throwable $e) {
    error_log('[LOGIN] ' . $e->getMessage());
    $failLogin('Não foi possível realizar o login agora. Tente novamente.');
}

if (!is_array($user)) {
    $user = [];
}

if (count($user) === 0) {
    error_log('[LOGIN][FAIL][USER_NOT_FOUND] email=' . $email_login . ' fonte=' . ($fonte_conexao ?? 'n/a'));
    $failLogin('E-mail ou senha inválidos. Verifique os dados e tente novamente.');
}

if (($user['ativo_user'] ?? 'n') !== 's') {
    error_log('[LOGIN][FAIL][INACTIVE] email=' . $email_login . ' user_id=' . (int)($user['id_usuario'] ?? 0) . ' fonte=' . ($fonte_conexao ?? 'n/a'));
    $failLogin('Seu usuário está inativo. Entre em contato com o administrador.');
}

if ($hasLoginSecurityColumns) {
    $lockedUntilRaw = (string)($user['login_locked_until'] ?? '');
    if ($lockedUntilRaw !== '') {
        try {
            $lockedUntil = new DateTimeImmutable($lockedUntilRaw);
            $now = new DateTimeImmutable('now');
            if ($lockedUntil > $now) {
                $diff = $now->diff($lockedUntil);
                $remainingMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s > 0 ? 1 : 0);
                error_log('[LOGIN][LOCKED] email=' . $email_login . ' user_id=' . (int)($user['id_usuario'] ?? 0) . ' locked_until=' . $lockedUntilRaw);
                $failLogin('Usuário temporariamente bloqueado por excesso de tentativas. Tente novamente em ' . max(1, $remainingMinutes) . ' minuto(s).');
            }
        } catch (Throwable $e) {
            error_log('[LOGIN][LOCK_PARSE] ' . $e->getMessage());
        }
    }
}

$senhaUser = (string)($user['senha_user'] ?? '');
$senhaValida = $senhaUser !== '' && (
    password_verify($senha_login, $senhaUser)
);

if (!$senhaValida) {
    if ($hasLoginSecurityColumns) {
        try {
            $tentativas = max(0, (int)($user['login_fail_count'] ?? 0)) + 1;
            $bloqueouAgora = $tentativas >= $maxTentativasLogin;
            $lockedUntil = $bloqueouAgora
                ? (new DateTimeImmutable('now'))->modify('+' . $lockMinutes . ' minutes')->format('Y-m-d H:i:s')
                : null;

            $stmtFail = $conn->prepare("
                UPDATE tb_user
                   SET login_fail_count = :count,
                       login_last_fail_at = NOW(),
                       login_locked_until = :locked_until
                 WHERE id_usuario = :id
                 LIMIT 1
            ");
            $stmtFail->bindValue(':count', $tentativas, PDO::PARAM_INT);
            if ($lockedUntil === null) {
                $stmtFail->bindValue(':locked_until', null, PDO::PARAM_NULL);
            } else {
                $stmtFail->bindValue(':locked_until', $lockedUntil, PDO::PARAM_STR);
            }
            $stmtFail->bindValue(':id', (int)($user['id_usuario'] ?? 0), PDO::PARAM_INT);
            $stmtFail->execute();

            error_log('[LOGIN][FAIL_COUNT] email=' . $email_login . ' user_id=' . (int)($user['id_usuario'] ?? 0) . ' attempts=' . $tentativas . ' locked=' . ($bloqueouAgora ? 's' : 'n'));

            if ($bloqueouAgora) {
                $failLogin('Usuário temporariamente bloqueado após 6 tentativas inválidas. Tente novamente em 15 minutos.');
            }

            $restantes = max(0, $maxTentativasLogin - $tentativas);
            $sufixoTentativas = $restantes === 1 ? ' tentativa restante' : ' tentativas restantes';
            $failLogin(
                'E-mail ou senha inválidos. Verifique os dados e tente novamente.',
                'Restam ' . $restantes . $sufixoTentativas . ' antes do bloqueio temporário.'
            );
        } catch (Throwable $e) {
            error_log('[LOGIN][FAIL_COUNT_UPDATE] ' . $e->getMessage());
        }
    }

    error_log('[LOGIN][FAIL][INVALID_PASSWORD] email=' . $email_login . ' user_id=' . (int)($user['id_usuario'] ?? 0) . ' fonte=' . ($fonte_conexao ?? 'n/a'));
    $failLogin('E-mail ou senha inválidos. Verifique os dados e tente novamente.');
}

if ($hasLoginSecurityColumns && ((int)($user['login_fail_count'] ?? 0) > 0 || !empty($user['login_locked_until']) || !empty($user['login_last_fail_at']))) {
    try {
        $stmtReset = $conn->prepare("
            UPDATE tb_user
               SET login_fail_count = 0,
                   login_locked_until = NULL,
                   login_last_fail_at = NULL
             WHERE id_usuario = :id
             LIMIT 1
        ");
        $stmtReset->bindValue(':id', (int)($user['id_usuario'] ?? 0), PDO::PARAM_INT);
        $stmtReset->execute();
    } catch (Throwable $e) {
        error_log('[LOGIN][FAIL_COUNT_RESET] ' . $e->getMessage());
    }
}

session_regenerate_id(true);

$_SESSION['id_usuario'] = (int)($user['id_usuario'] ?? 0);
$_SESSION['foto_usuario'] = (string)($user['foto_usuario'] ?? '');
$_SESSION['email_user'] = (string)($user['email_user'] ?? '');
$_SESSION['senha_user'] = '';
$_SESSION['login_user'] = (string)($user['email_user'] ?? '');
$_SESSION['usuario_user'] = (string)($user['usuario_user'] ?? '');
$_SESSION['ativo'] = (string)($user['ativo_user'] ?? '');
$_SESSION['nivel'] = (int)($user['nivel_user'] ?? 99);
$_SESSION['cargo'] = (string)($user['cargo_user'] ?? '');
$_SESSION['fk_seguradora_user'] = isset($user['fk_seguradora_user'])
    ? (int)$user['fk_seguradora_user']
    : null;
unset($_SESSION['login_error']);
unset($_SESSION['login_attempts_notice']);
$_SESSION['msg'] = '';

if (function_exists('flowLogStart') && function_exists('flowLog')) {
    $loginCtx = flowLogStart('auth_login', [
        'session_user_id' => (int)($_SESSION['id_usuario'] ?? 0),
        'session_user_name' => (string)($_SESSION['usuario_user'] ?? ''),
        'email_user' => (string)($_SESSION['email_user'] ?? ''),
        'nivel' => (int)($_SESSION['nivel'] ?? 0),
        'cargo' => (string)($_SESSION['cargo'] ?? ''),
    ]);
    $target = fullcare_post_login_target($BASE_URL, $user);
    flowLog($loginCtx, 'login.success', 'INFO', [
        'target' => str_replace($BASE_URL, '', $target),
    ]);
    fullcareAuditLog($conn, [
        'action' => 'login.success',
        'entity_type' => 'login',
        'entity_id' => (int)($_SESSION['id_usuario'] ?? 0),
        'summary' => 'Login realizado com sucesso.',
        'after' => $user,
        'context' => [
            'target' => str_replace($BASE_URL, '', $target),
            'nivel' => (int)($_SESSION['nivel'] ?? 0),
            'cargo' => (string)($_SESSION['cargo'] ?? ''),
        ],
        'trace_id' => $loginCtx['trace_id'] ?? null,
        'source' => 'check_login.php',
    ], $BASE_URL);
}

if (($user['senha_default_user'] ?? 'n') === 's') {
    header('Location: ' . $BASE_URL . 'nova_senha.php');
    exit;
}

header('Location: ' . fullcare_post_login_target($BASE_URL, $user));
exit;
