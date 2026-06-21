<?php

include_once("check_logado.php");
include_once("globals.php");
require_once(__DIR__ . "/app/schemaEnsurer.php");
require_once(__DIR__ . "/app/mfa.php");
require_once(__DIR__ . "/utils/audit_logger.php");

ensure_user_login_security_columns($conn);
ensure_user_mfa_schema($conn);

$redirect = $BASE_URL . 'mfa_configuracao.php';

if (!function_exists('mfa_config_flash')) {
    function mfa_config_flash(string $message, string $type = 'success'): void
    {
        $_SESSION['mfa_flash'] = [
            'message' => $message,
            'type' => $type,
        ];
    }
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}

$csrf = (string)filter_input(INPUT_POST, 'csrf', FILTER_UNSAFE_RAW);
if (!csrf_is_valid($csrf)) {
    mfa_config_flash('Sessão expirada. Recarregue a página e tente novamente.', 'error');
    header('Location: ' . $redirect);
    exit;
}

$action = (string)filter_input(INPUT_POST, 'action', FILTER_UNSAFE_RAW);
$userId = (int)($_SESSION['id_usuario'] ?? 0);
$user = fullcare_mfa_fetch_user($conn, $userId);
if (!is_array($user)) {
    mfa_config_flash('Usuário não encontrado.', 'error');
    header('Location: ' . $redirect);
    exit;
}

try {
    if ($action === 'confirm_setup') {
        if (fullcare_mfa_user_enabled($user)) {
            mfa_config_flash('MFA já está ativo para esta conta.');
            header('Location: ' . $redirect);
            exit;
        }

        $secret = (string)($_SESSION['mfa_setup_secret'] ?? '');
        $code = (string)filter_input(INPUT_POST, 'code', FILTER_UNSAFE_RAW);
        if ($secret === '' || fullcare_mfa_find_valid_step($secret, $code, 2) === null) {
            mfa_config_flash('Código inválido. Confira o horário do celular e tente novamente.', 'error');
            header('Location: ' . $redirect);
            exit;
        }

        $stmt = $conn->prepare("
            UPDATE tb_user
               SET mfa_enabled = 1,
                   mfa_secret = :secret,
                   mfa_confirmed_at = NOW(),
                   mfa_last_used_step = NULL,
                   mfa_recovery_generated_at = NOW()
             WHERE id_usuario = :id
             LIMIT 1
        ");
        $stmt->bindValue(':secret', fullcare_mfa_encrypt_secret($secret), PDO::PARAM_STR);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['mfa_recovery_codes_once'] = fullcare_mfa_generate_recovery_codes($conn, $userId);
        unset($_SESSION['mfa_setup_secret']);

        fullcareAuditLog($conn, [
            'action' => 'mfa.enabled',
            'entity_type' => 'usuario',
            'entity_id' => $userId,
            'summary' => 'MFA ativado pelo usuário.',
            'source' => 'process_mfa_configuracao.php',
        ], $BASE_URL);

        mfa_config_flash('MFA ativado com sucesso.');
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'regenerate_recovery') {
        $code = (string)filter_input(INPUT_POST, 'code', FILTER_UNSAFE_RAW);
        if (!fullcare_mfa_verify_code_for_user($conn, $user, $code, true)) {
            mfa_config_flash('Código inválido. Não foi possível gerar novos códigos.', 'error');
            header('Location: ' . $redirect);
            exit;
        }

        $_SESSION['mfa_recovery_codes_once'] = fullcare_mfa_generate_recovery_codes($conn, $userId);
        $stmt = $conn->prepare("UPDATE tb_user SET mfa_recovery_generated_at = NOW() WHERE id_usuario = :id LIMIT 1");
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        fullcareAuditLog($conn, [
            'action' => 'mfa.recovery_regenerated',
            'entity_type' => 'usuario',
            'entity_id' => $userId,
            'summary' => 'Códigos de recuperação MFA regenerados.',
            'source' => 'process_mfa_configuracao.php',
        ], $BASE_URL);

        mfa_config_flash('Novos códigos de recuperação gerados.');
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'disable') {
        $password = (string)filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);
        $code = (string)filter_input(INPUT_POST, 'code', FILTER_UNSAFE_RAW);
        if (!password_verify($password, (string)($user['senha_user'] ?? ''))) {
            mfa_config_flash('Senha inválida. MFA não foi desativado.', 'error');
            header('Location: ' . $redirect);
            exit;
        }
        if (!fullcare_mfa_verify_code_for_user($conn, $user, $code, true)) {
            mfa_config_flash('Código inválido. MFA não foi desativado.', 'error');
            header('Location: ' . $redirect);
            exit;
        }

        $stmt = $conn->prepare("
            UPDATE tb_user
               SET mfa_enabled = 0,
                   mfa_secret = NULL,
                   mfa_confirmed_at = NULL,
                   mfa_last_used_step = NULL,
                   mfa_recovery_generated_at = NULL
             WHERE id_usuario = :id
             LIMIT 1
        ");
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $delete = $conn->prepare("DELETE FROM tb_user_mfa_recovery_code WHERE user_id = :id");
        $delete->bindValue(':id', $userId, PDO::PARAM_INT);
        $delete->execute();
        unset($_SESSION['mfa_setup_secret']);

        fullcareAuditLog($conn, [
            'action' => 'mfa.disabled',
            'entity_type' => 'usuario',
            'entity_id' => $userId,
            'summary' => 'MFA desativado pelo usuário.',
            'source' => 'process_mfa_configuracao.php',
        ], $BASE_URL);

        mfa_config_flash('MFA desativado para esta conta.');
        header('Location: ' . $redirect);
        exit;
    }
} catch (Throwable $e) {
    error_log('[MFA][CONFIG] ' . $e->getMessage());
    mfa_config_flash('Não foi possível concluir a operação de MFA agora.', 'error');
    header('Location: ' . $redirect);
    exit;
}

mfa_config_flash('Ação inválida.', 'error');
header('Location: ' . $redirect);
exit;
