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

$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

if (!function_exists('fullcare_is_local_request')) {
    function fullcare_is_local_request(): bool
    {
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $serverName = strtolower((string)($_SERVER['SERVER_NAME'] ?? ''));
        $remoteAddr = (string)($_SERVER['REMOTE_ADDR'] ?? '');

        return strpos($host, 'localhost') !== false
            || strpos($host, '127.0.0.1') !== false
            || strpos($serverName, 'localhost') !== false
            || in_array($remoteAddr, ['127.0.0.1', '::1'], true);
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
if (!$email) {
    $_SESSION['recuperacao_msg'] = 'Informe um e-mail válido.';
    $_SESSION['recuperacao_tipo'] = 'error';
    header('Location: ' . app_url('esqueci_senha.php'), true, 303);
    exit;
}

$email = trim(strtolower($email));
$genericMsg = 'Se o e-mail estiver cadastrado, você receberá um código de verificação em instantes.';
$rateMsg = 'Aguarde 1 minuto antes de solicitar um novo código.';

try {
    $stmt = $conn->prepare("
        SELECT id_usuario, email_user, ativo_user, usuario_user
          FROM tb_user
         WHERE email_user = :email
         LIMIT 1
    ");
    $stmt->bindValue(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || strtolower((string)($user['ativo_user'] ?? '')) !== 's') {
        $_SESSION['recuperacao_msg'] = $genericMsg;
        $_SESSION['recuperacao_tipo'] = 'info';
        header('Location: ' . app_url('esqueci_senha.php'), true, 303);
        exit;
    }

    $uid = (int)$user['id_usuario'];

    $stmt = $conn->prepare("
        SELECT created_at
          FROM tb_user_password_reset
         WHERE user_id = :uid
         ORDER BY created_at DESC
         LIMIT 1
    ");
    $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
    $stmt->execute();
    $last = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($last && (time() - strtotime($last['created_at'])) < 60) {
        $_SESSION['recuperacao_msg'] = $rateMsg;
        $_SESSION['recuperacao_tipo'] = 'info';
        header('Location: ' . app_url('esqueci_senha.php'), true, 303);
        exit;
    }

    $conn->prepare("
        UPDATE tb_user_password_reset
           SET used_at = NOW()
         WHERE user_id = :uid
           AND used_at IS NULL
    ")->execute([':uid' => $uid]);

    $token = bin2hex(random_bytes(32));
    $code = (string)random_int(100000, 999999);
    $tokenHash = hash('sha256', $token);
    $codeHash = hash('sha256', $code);
    $expiresAt = (new DateTime('+20 minutes'))->format('Y-m-d H:i:s');

    $stmt = $conn->prepare("
        INSERT INTO tb_user_password_reset
            (user_id, email, token_hash, code_hash, expires_at, request_ip, user_agent)
        VALUES
            (:uid, :email, :token_hash, :code_hash, :expires_at, :ip, :ua)
    ");
    $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
    $stmt->bindValue(':email', $email);
    $stmt->bindValue(':token_hash', $tokenHash);
    $stmt->bindValue(':code_hash', $codeHash);
    $stmt->bindValue(':expires_at', $expiresAt);
    $stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR'] ?? null);
    $stmt->bindValue(':ua', substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255));
    $stmt->execute();

    $resetUrl = app_url('redefinir_senha.php') . '?token=' . urlencode($token);
    if (!preg_match('~^https?://~i', $resetUrl)) {
        $resetUrl = 'http://' . ltrim($resetUrl, '/');
    }

    $smtpHost = getenv('SMTP_HOST') ?: 'smtps.uhserver.com';
    $smtpUser = getenv('SMTP_USER') ?: '';
    $smtpPass = getenv('SMTP_PASS') ?: '';
    $smtpFrom = getenv('SMTP_FROM') ?: $smtpUser;
    $smtpName = getenv('SMTP_NAME') ?: 'FullCare';
    $smtpPort = (int)(getenv('SMTP_PORT') ?: 465);
    $smtpSecure = getenv('SMTP_SECURE') ?: 'ssl';
    $isLocalRequest = fullcare_is_local_request();

    if ($isLocalRequest && ($smtpUser === '' || $smtpPass === '')) {
        $debugPayload = [
            'email' => $email,
            'codigo' => $code,
            'link' => $resetUrl,
            'expira_em' => $expiresAt,
        ];
        $_SESSION['recuperacao_debug'] = $debugPayload;
        @file_put_contents(
            '/tmp/fullcare_recuperacao_local.log',
            date('c') . ' ' . json_encode($debugPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND
        );

        $_SESSION['recuperacao_msg'] = 'Modo local: código gerado sem envio por e-mail.';
        $_SESSION['recuperacao_tipo'] = 'info';
        header('Location: ' . app_url('esqueci_senha.php'), true, 303);
        exit;
    }

    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        throw new RuntimeException('Dependencias de e-mail nao instaladas. Execute composer install para habilitar a recuperacao por e-mail.');
    }

    if ($smtpUser === '' || $smtpPass === '') {
        throw new RuntimeException('SMTP_USER/SMTP_PASS nao configurados.');
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUser;
    $mail->Password = $smtpPass;
    $mail->SMTPSecure = $smtpSecure;
    $mail->Port = $smtpPort > 0 ? $smtpPort : 465;

    $mail->setFrom($smtpFrom, $smtpName);
    $mail->addAddress($email, $user['usuario_user'] ?? '');
    $mail->Subject = 'Código de recuperação de senha';
    $mail->isHTML(true);
    $mail->Body = '<div style="background:#f5f5fb;padding:24px 0;font-family:Arial,sans-serif;">
        <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:14px;box-shadow:0 10px 24px rgba(0,0,0,.08);overflow:hidden;">
            <div style="background:#5e2363;color:#fff;padding:18px 24px;font-size:18px;font-weight:700;">
                Recuperação de senha
            </div>
            <div style="padding:22px 24px;color:#333;font-size:14px;line-height:1.5;">
                <p style="margin:0 0 12px;">Olá,</p>
                <p style="margin:0 0 16px;">Seu código de verificação é:</p>
                <div style="display:inline-block;padding:10px 16px;border-radius:10px;background:#f3e9f5;color:#5e2363;font-weight:700;font-size:18px;letter-spacing:2px;">
                    ' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '
                </div>
                <p style="margin:16px 0 10px;">Ou clique no botão abaixo para redefinir sua senha:</p>
                <p style="margin:0 0 18px;">
                    <a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#5e2363;color:#fff;text-decoration:none;padding:12px 18px;border-radius:999px;font-weight:700;">
                        Redefinir senha
                    </a>
                </p>
                <p style="margin:0 0 6px;color:#666;">Esse código expira em 20 minutos.</p>
                <p style="margin:0;color:#999;">Se você não solicitou, ignore este e-mail.</p>
            </div>
        </div>
    </div>';
    $mail->AltBody = "Olá,\r\n\r\nSeu código de verificação é: {$code}\r\n\r\n" .
        "Use o link abaixo para redefinir sua senha:\r\n{$resetUrl}\r\n\r\n" .
        "Esse código expira em 20 minutos.\r\n\r\n" .
        "Se você não solicitou, ignore este e-mail.";
    $mail->send();

    $_SESSION['recuperacao_msg'] = $genericMsg;
    $_SESSION['recuperacao_tipo'] = 'info';
    header('Location: ' . app_url('esqueci_senha.php'), true, 303);
    exit;
} catch (\PHPMailer\PHPMailer\Exception $e) {
    $err = '[RECUPERAR_SENHA][MAIL] ' . $e->getMessage();
    error_log($err);
    @file_put_contents('/tmp/recuperar_senha_mail.log', date('c') . ' ' . $err . PHP_EOL, FILE_APPEND);
    $_SESSION['recuperacao_msg'] = 'Não foi possível enviar o e-mail agora. Tente novamente.';
    $_SESSION['recuperacao_tipo'] = 'error';
    header('Location: ' . app_url('esqueci_senha.php'), true, 303);
    exit;
} catch (Throwable $e) {
    $err = '[RECUPERAR_SENHA] ' . $e->getMessage();
    error_log($err);
    @file_put_contents('/tmp/recuperar_senha_mail.log', date('c') . ' ' . $err . PHP_EOL, FILE_APPEND);
    if (stripos($e->getMessage(), 'composer install') !== false) {
        $_SESSION['recuperacao_msg'] = 'Recuperação indisponível neste ambiente: faltam dependências do Composer (`vendor/autoload.php`). Rode `composer install`.';
    } elseif (stripos($e->getMessage(), 'SMTP_USER/SMTP_PASS') !== false) {
        $_SESSION['recuperacao_msg'] = 'Recuperação indisponível neste ambiente: SMTP não configurado. Preencha as variáveis em `.env`.';
    } else {
        $_SESSION['recuperacao_msg'] = 'Não foi possível enviar o e-mail agora. Tente novamente.';
    }
    $_SESSION['recuperacao_tipo'] = 'error';
    header('Location: ' . app_url('esqueci_senha.php'), true, 303);
    exit;
}

$_SESSION['recuperacao_msg'] = $genericMsg;
$_SESSION['recuperacao_tipo'] = 'info';
header('Location: ' . app_url('esqueci_senha.php'), true, 303);
exit;
