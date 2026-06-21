<?php

include_once("check_logado.php");
include_once("globals.php");
require_once(__DIR__ . "/app/schemaEnsurer.php");
require_once(__DIR__ . "/app/mfa.php");

ensure_user_login_security_columns($conn);
ensure_user_mfa_schema($conn);

if (!function_exists('mfa_page_e')) {
    function mfa_page_e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$userId = (int)($_SESSION['id_usuario'] ?? 0);
$user = fullcare_mfa_fetch_user($conn, $userId);
if (!is_array($user)) {
    $_SESSION['login_error'] = 'Sessão inválida. Entre novamente.';
    header('Location: ' . $BASE_URL . 'destroi.php');
    exit;
}

$mfaEnabled = fullcare_mfa_user_enabled($user);
if (!$mfaEnabled && empty($_SESSION['mfa_setup_secret'])) {
    $_SESSION['mfa_setup_secret'] = fullcare_mfa_generate_secret();
}

$setupSecret = (string)($_SESSION['mfa_setup_secret'] ?? '');
$account = (string)($user['email_user'] ?: $user['usuario_user'] ?: ('usuario-' . $userId));
$provisioningUri = $setupSecret !== ''
    ? fullcare_mfa_provisioning_uri('FullCare Amil', $account, $setupSecret)
    : '';
$qrDataUri = $provisioningUri !== '' ? fullcare_mfa_qr_svg_data_uri($provisioningUri) : '';
$flash = $_SESSION['mfa_flash'] ?? null;
unset($_SESSION['mfa_flash']);
$recoveryCodes = $_SESSION['mfa_recovery_codes_once'] ?? [];
unset($_SESSION['mfa_recovery_codes_once']);

require_once("templates/header.php");
?>

<style>
    .mfa-wrap {
        max-width: 980px;
        margin: 28px auto;
        padding: 0 18px 40px;
    }
    .mfa-panel {
        background: #fff;
        border: 1px solid #e5edf5;
        border-radius: 14px;
        box-shadow: 0 18px 44px rgba(31, 45, 61, .08);
        padding: clamp(20px, 4vw, 34px);
    }
    .mfa-title {
        margin: 0 0 8px;
        color: #1f2d3d;
        font-weight: 800;
    }
    .mfa-lead {
        color: #637083;
        line-height: 1.55;
        margin-bottom: 22px;
    }
    .mfa-grid {
        display: grid;
        grid-template-columns: minmax(220px, 300px) 1fr;
        gap: 24px;
        align-items: start;
    }
    .mfa-qr {
        width: 100%;
        max-width: 260px;
        border: 1px solid #d9e4f0;
        border-radius: 14px;
        background: #fff;
        padding: 12px;
    }
    .mfa-secret,
    .mfa-recovery {
        border: 1px solid #d9e4f0;
        border-radius: 10px;
        background: #f8fbfe;
        color: #1f2d3d;
        padding: 14px;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        overflow-wrap: anywhere;
    }
    .mfa-status {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 7px 12px;
        font-weight: 800;
        font-size: .86rem;
        background: #eaf7ef;
        color: #217647;
        margin-bottom: 18px;
    }
    .mfa-alert {
        border-radius: 10px;
        padding: 13px 15px;
        margin-bottom: 18px;
        background: #eef6ff;
        border: 1px solid #cfe5ff;
        color: #24496e;
    }
    .mfa-alert--error {
        background: #fff0f0;
        border-color: #ffd1d1;
        color: #8a2d2d;
    }
    .mfa-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 18px;
    }
    .mfa-form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 12px;
        margin-top: 14px;
    }
    .mfa-form-row input {
        min-height: 44px;
        border: 1px solid #d9e4f0;
        border-radius: 10px;
        padding: 10px 12px;
    }
    @media (max-width: 760px) {
        .mfa-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<main class="mfa-wrap">
    <section class="mfa-panel">
        <h1 class="mfa-title">Segurança e MFA</h1>
        <p class="mfa-lead">
            Use Google Authenticator, Microsoft Authenticator, 1Password ou outro aplicativo compatível com TOTP para proteger seu acesso ao FullCare Amil.
        </p>

        <?php if (is_array($flash)): ?>
            <div class="mfa-alert <?= ($flash['type'] ?? '') === 'error' ? 'mfa-alert--error' : '' ?>">
                <?= mfa_page_e($flash['message'] ?? '') ?>
            </div>
        <?php endif; ?>

        <?php if (is_array($recoveryCodes) && count($recoveryCodes) > 0): ?>
            <div class="mfa-alert">
                <strong>Guarde estes códigos de recuperação agora.</strong><br>
                Eles não serão exibidos novamente e cada código só pode ser usado uma vez.
            </div>
            <pre class="mfa-recovery"><?= mfa_page_e(implode("\n", $recoveryCodes)) ?></pre>
        <?php endif; ?>

        <?php if ($mfaEnabled): ?>
            <span class="mfa-status">MFA ativo</span>
            <p class="mfa-lead">
                Seu login já exige um código temporário além da senha. Você pode gerar novos códigos de recuperação ou desativar o MFA abaixo.
            </p>

            <form method="post" action="<?= mfa_page_e($BASE_URL) ?>process_mfa_configuracao.php">
                <input type="hidden" name="csrf" value="<?= mfa_page_e(csrf_token()) ?>">
                <input type="hidden" name="action" value="regenerate_recovery">
                <h3>Gerar novos códigos de recuperação</h3>
                <div class="mfa-form-row">
                    <input name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="Código do autenticador" required>
                </div>
                <div class="mfa-actions">
                    <button class="btn btn-primary" type="submit">Gerar códigos</button>
                </div>
            </form>

            <hr>

            <form method="post" action="<?= mfa_page_e($BASE_URL) ?>process_mfa_configuracao.php">
                <input type="hidden" name="csrf" value="<?= mfa_page_e(csrf_token()) ?>">
                <input type="hidden" name="action" value="disable">
                <h3>Desativar MFA</h3>
                <p class="mfa-lead">Informe sua senha atual e um código válido para remover o segundo fator desta conta.</p>
                <div class="mfa-form-row">
                    <input type="password" name="password" autocomplete="current-password" placeholder="Senha atual" required>
                    <input name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="Código do autenticador" required>
                </div>
                <div class="mfa-actions">
                    <button class="btn btn-outline-danger" type="submit">Desativar MFA</button>
                </div>
            </form>
        <?php else: ?>
            <div class="mfa-grid">
                <div>
                    <?php if ($qrDataUri !== ''): ?>
                        <img class="mfa-qr" src="<?= mfa_page_e($qrDataUri) ?>" alt="QR Code para configurar MFA">
                    <?php else: ?>
                        <div class="mfa-secret">QR Code indisponível. Use a chave manual.</div>
                    <?php endif; ?>
                </div>
                <div>
                    <h3>1. Escaneie o QR Code</h3>
                    <p class="mfa-lead">No Google Authenticator, toque em adicionar conta e escaneie o código. Se preferir, use a chave manual abaixo.</p>
                    <div class="mfa-secret"><?= mfa_page_e($setupSecret) ?></div>

                    <h3 style="margin-top: 22px;">2. Confirme o primeiro código</h3>
                    <form method="post" action="<?= mfa_page_e($BASE_URL) ?>process_mfa_configuracao.php">
                        <input type="hidden" name="csrf" value="<?= mfa_page_e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="confirm_setup">
                        <div class="mfa-form-row">
                            <input name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="Código de 6 dígitos" required>
                        </div>
                        <div class="mfa-actions">
                            <button class="btn btn-primary" type="submit">Ativar MFA</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php require_once("templates/footer.php"); ?>
