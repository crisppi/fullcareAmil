<?php

if (!function_exists('fullcare_post_login_target')) {
    function fullcare_post_login_target(string $baseUrl, array $user): string
    {
        $nivel = (int)($user['nivel_user'] ?? 0);
        $cargo = trim((string)($user['cargo_user'] ?? ''));
        $cargo = mb_strtolower($cargo, 'UTF-8');
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $cargo);
        $cargo = $ascii !== false ? $ascii : $cargo;
        $cargo = preg_replace('/[^a-z]/', '', $cargo);

        if ($nivel === -1) {
            return $baseUrl . 'list_internacao_cap_fin.php';
        }

        return $baseUrl . 'inicio';
    }
}

if (!function_exists('fullcare_login_session_start')) {
    function fullcare_login_session_start(array $user): void
    {
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

        unset(
            $_SESSION['mfa_pending_user_id'],
            $_SESSION['mfa_pending_issued_at'],
            $_SESSION['mfa_pending_attempts'],
            $_SESSION['mfa_pending_email'],
            $_SESSION['mfa_pending_token'],
            $_SESSION['login_error'],
            $_SESSION['login_attempts_notice']
        );
        $_SESSION['msg'] = '';
    }
}

if (!function_exists('fullcare_login_session_clear')) {
    function fullcare_login_session_clear(): void
    {
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
    }
}
