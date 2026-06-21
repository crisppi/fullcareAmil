<?php

include_once("globals.php");

if (!function_exists('mfa_verify_e')) {
    function mfa_verify_e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!empty($_SESSION['id_usuario'])) {
    header('Location: ' . $BASE_URL . 'inicio');
    exit;
}

$pendingUserId = (int)($_SESSION['mfa_pending_user_id'] ?? 0);
if ($pendingUserId <= 0) {
    header('Location: ' . $BASE_URL . 'index.php');
    exit;
}

if (empty($_SESSION['mfa_pending_token'])) {
    $_SESSION['mfa_pending_token'] = bin2hex(random_bytes(32));
}

$email = (string)($_SESSION['mfa_pending_email'] ?? '');
$mfaToken = (string)$_SESSION['mfa_pending_token'];
$flash = $_SESSION['mfa_verify_error'] ?? '';
unset($_SESSION['mfa_verify_error']);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificação MFA - FullCare Amil</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
            color: #1f2d3d;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #2d63a6, #92bee2 52%, #5e2363);
            padding: 20px;
        }
        .card {
            width: min(440px, 100%);
            background: rgba(255, 255, 255, .96);
            border-radius: 24px;
            box-shadow: 0 22px 60px rgba(17, 24, 39, .22);
            padding: 28px;
        }
        h1 {
            margin: 0 0 8px;
            font-size: 1.75rem;
        }
        p {
            color: #5b6878;
            line-height: 1.55;
        }
        label {
            display: block;
            margin: 18px 0 8px;
            font-weight: 800;
        }
        input {
            width: 100%;
            min-height: 52px;
            border: 1px solid #d9e4f0;
            border-radius: 14px;
            padding: 12px 14px;
            font-size: 1.12rem;
            letter-spacing: .08em;
        }
        button,
        a.button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            border-radius: 12px;
            padding: 0 18px;
            border: 0;
            text-decoration: none;
            font-weight: 800;
            cursor: pointer;
        }
        button {
            background: #5e2363;
            color: #fff;
        }
        a.button {
            color: #2d63a6;
            background: #eef5ff;
        }
        .actions {
            display: flex;
            gap: 12px;
            margin-top: 18px;
        }
        .alert {
            border: 1px solid #ffd1d1;
            border-radius: 12px;
            background: #fff0f0;
            color: #8a2d2d;
            padding: 12px;
            margin-top: 16px;
        }
    </style>
</head>
<body>
    <main class="card">
        <h1>Verificação em duas etapas</h1>
        <p>Digite o código de 6 dígitos do seu aplicativo autenticador para concluir o acesso ao FullCare Amil.</p>
        <?php if ($email !== ''): ?>
            <p><strong><?= mfa_verify_e($email) ?></strong></p>
        <?php endif; ?>
        <?php if ($flash !== ''): ?>
            <div class="alert"><?= mfa_verify_e($flash) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= mfa_verify_e($BASE_URL) ?>process_mfa_verify.php">
            <input type="hidden" name="mfa_token" value="<?= mfa_verify_e($mfaToken) ?>">
            <label for="code">Código MFA</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus required>
            <div class="actions">
                <button type="submit">Verificar</button>
                <a class="button" href="<?= mfa_verify_e($BASE_URL) ?>destroi.php">Cancelar</a>
            </div>
        </form>
    </main>
</body>
</html>
