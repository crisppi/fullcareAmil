<!DOCTYPE html>
<?php $currentAppVersion = app_latest_version($conn); ?>
<html lang="pt-BR">
<?php $assetBase = rtrim($BASE_URL, '/'); ?>

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>FullCare</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
    /* ===============================
       Base
    =============================== */
    body {
        margin: 0;
        padding: 24px;
        box-sizing: border-box;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        font-family: 'Inter', sans-serif;
        background:
            radial-gradient(circle at 18% 18%, rgba(82, 154, 218, .24), transparent 28%),
            radial-gradient(circle at 88% 20%, rgba(92, 38, 118, .22), transparent 26%),
            linear-gradient(135deg, #edf5fb 0%, #dfe9f3 44%, #f0edf7 100%);
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        background-attachment: fixed;
        position: relative;
        opacity: 0;
        animation: fadeIn .3s ease-in forwards;
    }

    body::before {
        content: "";
        position: fixed;
        inset: 0;
        background: url("<?= htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') ?>/img/17450.jpg") center / cover no-repeat;
        opacity: 0.18;
        z-index: -1;
        pointer-events: none;
    }

    @keyframes fadeIn {
        from {
            opacity: 0
        }

        to {
            opacity: 1
        }
    }

    .login-container {
        display: grid;
        grid-template-columns: 330px minmax(390px, 1fr);
        gap: 34px;
        width: 900px;
        max-width: 95vw;
        min-height: 500px;
        padding: 46px 54px;
        box-sizing: border-box;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, .94);
        border: 1px solid rgba(255, 255, 255, .78);
        border-radius: 22px;
        box-shadow:
            0 34px 76px rgba(37, 49, 72, .24),
            0 12px 28px rgba(37, 49, 72, .12);
        backdrop-filter: none;
        position: relative;
        overflow: hidden;
    }

    .login-container::before {
        content: "";
        position: absolute;
        width: 520px;
        height: 330px;
        left: -210px;
        top: -120px;
        border-radius: 36% 64% 68% 32% / 44% 52% 48% 56%;
        background:
            radial-gradient(circle at 76% 70%, rgba(255, 255, 255, .22), transparent 0 32%, rgba(255, 255, 255, 0) 33%),
            linear-gradient(135deg, rgba(45, 99, 166, .92), rgba(83, 137, 187, .72));
        transform: rotate(-9deg);
        pointer-events: none;
    }

    .login-container::after {
        content: "";
        position: absolute;
        width: 360px;
        height: 460px;
        right: -286px;
        top: 34px;
        border-radius: 64% 36% 28% 72% / 42% 38% 62% 58%;
        background:
            radial-gradient(circle at 18% 24%, rgba(255, 255, 255, .28), transparent 0 20%, rgba(255, 255, 255, 0) 21%),
            linear-gradient(160deg, rgba(94, 38, 112, .78), rgba(66, 24, 73, .58));
        transform: rotate(-4deg);
        box-shadow:
            inset 22px 0 42px rgba(255, 255, 255, .14),
            -18px 24px 46px rgba(66, 24, 73, .16);
        pointer-events: none;
    }

    .side-panel::before {
        content: "";
        position: absolute;
        width: 190px;
        height: 300px;
        right: -132px;
        top: 116px;
        border-radius: 64% 36% 44% 56% / 50% 36% 64% 50%;
        background: linear-gradient(170deg, rgba(94, 38, 112, .28), rgba(66, 24, 73, .08));
        border-left: 1px solid rgba(255, 255, 255, .24);
        transform: rotate(-6deg);
        pointer-events: none;
        z-index: 0;
    }

    .login-form::before {
        content: "";
        position: absolute;
        width: 320px;
        height: 220px;
        left: -138px;
        top: -78px;
        border-radius: 52% 48% 68% 32% / 48% 56% 44% 52%;
        background: rgba(45, 99, 166, .07);
        transform: rotate(-12deg);
        pointer-events: none;
        z-index: -1;
    }

    /* ===============================
       Bloco Azul (formulário)
    =============================== */
    .login-form {
        padding: 42px 36px 38px;
        border-radius: 22px;
        width: auto;
        height: auto;
        min-height: 0;
        background: rgba(255, 255, 255, .97);
        border: 1px solid rgba(232, 236, 244, .96);
        box-shadow:
            0 38px 82px rgba(42, 57, 82, .26),
            0 18px 38px rgba(42, 57, 82, .14),
            0 2px 0 rgba(255, 255, 255, .9) inset;
        backdrop-filter: none;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        position: relative;
        z-index: 2;
        transform: none;
    }

    .login-form::after {
        content: "";
        position: absolute;
        inset: 12px;
        border: 1px solid rgba(45, 99, 166, .045);
        border-radius: 16px;
        pointer-events: none;
    }

    .login-form-logo {
        width: 100%;
        max-width: 172px;
        margin-bottom: 34px;
        display: block;
    }

    .form-content {
        width: min(100%, 268px);
    }

    .input-container {
        position: relative;
        margin: 24px 0;
        width: 100%;
    }

    .input-container + .input-container {
        margin-top: 42px;
    }

    .input-container input {
        width: 100%;
        padding: 10px 0 !important;
        border: 0 !important;
        border-bottom: 1px solid rgba(47, 132, 128, .58) !important;
        background: transparent !important;
        border-radius: 0 !important;
        box-sizing: border-box;
        color: #263241;
        font-size: 13px !important;
        font-weight: 500;
        outline: none;
        box-shadow: none;
        transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
    }

    .input-container input:focus {
        background: transparent !important;
        border-color: #421849 !important;
        box-shadow: 0 6px 0 -5px rgba(66, 24, 73, .7);
    }

    .input-container label {
        position: absolute;
        top: -16px;
        left: 0;
        color: #2f8480;
        pointer-events: none;
        transition: color .2s ease;
        font-size: 11px;
        font-weight: 700;
    }

    .input-container input:focus+label,
    .input-container input:not(:placeholder-shown)+label {
        top: -20px;
        font-size: 11px;
        color: #421849;
    }

    .login-btn {
        width: 100%;
        padding: 12px;
        background: linear-gradient(135deg, #5f2769, #3f174d);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, .22);
        cursor: pointer;
        font-size: 14px;
        font-weight: 700;
        border-radius: 8px;
        margin-top: 18px;
        box-shadow: 0 10px 24px rgba(49, 18, 62, .23);
        transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
    }

    .login-btn:hover {
        box-shadow: 0 13px 28px rgba(49, 18, 62, .28);
        background: linear-gradient(135deg, #6d2e78, #451954);
        transform: translateY(-1px);
    }

    .forgot-password {
        color: #485565;
        text-align: center;
        margin-top: 20px;
    }

    .forgot-password a {
        color: #485565;
        text-decoration: none;
    }

    .login-links {
        width: 100%;
        display: flex;
        justify-content: flex-end;
        margin: -6px 0 6px;
    }

    .login-links a {
        font-size: 12px;
        color: #485565;
        text-decoration: none;
        font-weight: 600;
    }

    .login-links a:hover {
        text-decoration: underline;
    }

    .login-attempts-notice {
        margin: 12px 0 0;
        padding: 9px 11px;
        border-radius: 8px;
        background: #fff8e6;
        border: 1px solid #f1d894;
        border-left: 4px solid #d6a82d;
        color: #705519;
        font-size: 12px;
        line-height: 1.35;
        text-align: left;
        box-shadow: 0 8px 18px rgba(87, 69, 28, .08);
    }

    /* ===============================
       Bloco Lilás (lado direito)
    =============================== */
    .side-panel {
        padding: 0;
        background: transparent;
        color: #421849;
        width: auto;
        max-height: none;
        min-height: 0;
        box-shadow: none;
        display: flex;
        flex-direction: column;
        justify-content: center;
        border-radius: 0;
        margin: 0;
        text-align: center;
        position: relative;
        overflow: visible;
        z-index: 2;
    }

    .side-panel-content {
        margin-top: 0;
        position: relative;
        z-index: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: min(100%, 560px);
        margin-inline: auto;
    }

    .side-panel img.monitor-image {
        width: min(100%, 530px);
        height: auto;
        object-fit: contain;
        margin: 14px auto -8px;
        filter:
            drop-shadow(0 20px 24px rgba(45, 31, 78, .24))
            drop-shadow(0 7px 10px rgba(45, 31, 78, .14));
    }

    .login-insight-row {
        width: min(100%, 430px);
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
        margin: 14px auto 4px;
    }

    .login-insight-card {
        min-height: 58px;
        padding: 10px 11px;
        border-radius: 13px;
        background: linear-gradient(145deg, rgba(255, 255, 255, .86), rgba(246, 249, 255, .72));
        border: 1px solid rgba(117, 143, 174, .15);
        box-shadow: 0 14px 28px rgba(59, 66, 92, .08);
        text-align: left;
    }

    .login-insight-card small {
        display: block;
        color: rgba(66, 24, 73, .58);
        font-size: 9px;
        font-weight: 800;
        letter-spacing: .1em;
        text-transform: uppercase;
        line-height: 1.1;
    }

    .login-insight-card strong {
        display: block;
        margin-top: 7px;
        color: #421849;
        font-size: 12px;
        font-weight: 800;
        line-height: 1.15;
    }

    .login-side-kicker {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        margin-bottom: 10px;
        padding: 6px 11px;
        border-radius: 999px;
        background: rgba(66, 24, 73, .07);
        border: 1px solid rgba(66, 24, 73, .1);
        color: rgba(66, 24, 73, .74);
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
    }

    .login-side-kicker::before {
        content: "";
        width: 7px;
        height: 7px;
        border-radius: 999px;
        background: #76cfc4;
        box-shadow: 0 0 0 4px rgba(118, 207, 196, .16);
    }

    .login-flow-strip {
        width: min(100%, 380px);
        display: grid;
        grid-template-columns: 1fr auto 1fr auto 1fr;
        align-items: center;
        gap: 8px;
        margin: 16px auto -2px;
        color: #ffffff;
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .login-flow-strip span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 28px;
        padding: 0 10px;
        border-radius: 999px;
        background: linear-gradient(135deg, rgba(94, 38, 112, .92), rgba(66, 24, 73, .9));
        border: 1px solid rgba(255, 255, 255, .22);
        box-shadow: 0 10px 20px rgba(66, 24, 73, .14);
        white-space: nowrap;
    }

    .login-flow-strip i {
        width: 20px;
        height: 1px;
        background: linear-gradient(90deg, rgba(94, 38, 112, .12), rgba(94, 38, 112, .42), rgba(94, 38, 112, .12));
    }

    .side-panel h3,
    .side-panel p,
    .side-panel .email-btn {
        margin: 10px 0;
    }

    .side-panel p {
        margin: 8px auto 0;
        max-width: 350px;
        line-height: 1.5;
        color: rgba(55, 46, 78, .68);
        font-size: 12px;
        font-weight: 400;
    }

    .side-panel h3 {
        margin: 0;
        font-size: 20px;
        line-height: 1.2;
        letter-spacing: 0;
        font-weight: 800;
        color: #421849;
    }

    .side-panel .email-btn {
        background: #421849;
        color: #fff;
        padding: 10px 20px;
        border: none;
        cursor: pointer;
        text-decoration: none;
        font-size: 16px;
        border-radius: 5px;
    }

    .side-panel::after {
        content: none;
    }

    /* ===============================
       Mensagem de erro (flutuante)
    =============================== */
    .error-message {
        position: fixed;
        bottom: 26px;
        left: 50%;
        transform: translateX(-50%);
        width: min(88vw, 420px);
        padding: 12px 14px 12px 16px;
        background: rgba(255, 255, 255, .96);
        border: 1px solid rgba(180, 55, 55, .18);
        border-left: 4px solid #b43737;
        border-radius: 12px;
        text-align: left;
        box-shadow: 0 18px 42px rgba(72, 48, 58, .18);
        color: #673030;
        font-size: 13px;
        line-height: 1.4;
        animation: fadeIn .3s ease-in-out;
        z-index: 1000;
    }

    .error-message strong {
        display: block;
        font-size: 11px;
        letter-spacing: .03em;
        margin-bottom: 3px;
        color: #9f2f2f;
        opacity: 1;
        text-transform: uppercase;
    }

    .error-message.hide {
        opacity: 0;
        transform: translate(-50%, 12px);
        transition: opacity .35s ease, transform .35s ease;
    }

    /* ===============================
       Responsivo
    =============================== */
    @media (max-width: 1024px) {
        body {
            padding: 20px 16px;
            height: auto;
        }

        .login-container {
            width: 100%;
            max-width: 860px;
            gap: 24px;
            grid-template-columns: 310px minmax(330px, 1fr);
            padding: 38px 42px;
        }

        .form-content {
            width: min(100%, 268px);
        }
    }

    @media (max-width: 900px) {
        .login-container {
            flex-direction: column;
            display: flex;
            border-radius: 16px;
            overflow: hidden;
            min-height: 0;
            background: rgba(255, 255, 255, .94);
            gap: 26px;
            padding: 34px 24px;
        }

        .login-form,
        .side-panel {
            width: 100%;
            height: auto;
        }

        .login-form {
            padding: 32px 28px 30px;
            min-height: 0;
            width: min(100%, 360px);
            margin: 0 auto;
            transform: none;
        }

        .login-form::before {
            content: none;
        }

        .side-panel {
            min-height: 0;
            margin: 0;
            padding: 0 24px 10px;
        }

        .side-panel::after {
            content: none;
        }

        .side-panel-content {
            margin-top: 0;
        }

        .login-insight-row {
            width: min(100%, 420px);
        }

        .login-flow-strip {
            width: min(100%, 360px);
        }
    }

    @media (max-width: 600px) {
        .side-panel {
            display: none;
        }

        body {
            min-height: 520px;
            align-items: flex-start;
        }

        .login-container {
            align-items: flex-start;
            height: auto;
        }

        .login-form {
            padding: 30px 22px 26px;
            min-height: 0;
            height: auto;
            max-height: none;
            border-radius: 14px;
            transform: none;
        }

        .login-form::before {
            content: none;
        }

        .login-form-logo {
            max-width: 165px;
            margin-bottom: 28px;
        }

        .form-content {
            width: 100%;
        }

        .input-container {
            margin: 24px 0;
        }

        .input-container + .input-container {
            margin-top: 40px;
        }

        .input-container input {
            padding: 11px 13px !important;
            font-size: 13px !important;
        }

        .input-container label {
            font-size: 12px;
        }

        .input-container input:focus+label,
        .input-container input:not(:placeholder-shown)+label {
            top: -20px;
            font-size: 12px;
        }

        .login-btn {
            padding: 12px;
            margin-top: 18px;
            font-size: 14px;
        }
    }

    .login-footer {
        position: fixed;
        bottom: 12px;
        right: 24px;
        color: rgba(255, 255, 255, .85);
        font-size: 0.78rem;
        letter-spacing: 0.04em;
        pointer-events: none;
    }

    @media (max-width: 900px) {
        .login-footer {
            left: 50%;
            right: auto;
            transform: translateX(-50%);
        }
    }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-form">
            <img src="<?= htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') ?>/img/LogoFullCare.png" alt="FullCare Gestao em Saude" class="login-form-logo" />
            <div class="form-content">
                <form action="<?= htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') ?>/check_login.php" method="post" autocomplete="off">
                    <div class="input-container">
                        <input type="email" name="email_login" autocomplete="off" id="email_login" required />
                        <label for="email_login">Email</label>
                    </div>

                    <div class="input-container">
                        <input type="password" id="senha_login" autocomplete="off" name="senha_login" required />
                        <label for="senha_login">Senha</label>
                    </div>

                    <div class="login-links">
                        <a href="<?= htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') ?>/esqueci_senha.php">Esqueci minha senha</a>
                    </div>

                    <?php if (isset($_SESSION['login_attempts_notice']) && $_SESSION['login_attempts_notice'] !== "") { ?>
                    <div class="login-attempts-notice">
                        <?= htmlspecialchars((string)$_SESSION['login_attempts_notice'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <?php unset($_SESSION['login_attempts_notice']); ?>
                    <?php } ?>

                    <input type="submit" value="Login" class="login-btn" />
                </form>

                <!-- Error message -->
                <?php if (isset($_SESSION['login_error']) && $_SESSION['login_error'] !== "") { ?>
                <div class="error-message">
                    <strong>Falha no login</strong>
                    <div><?= htmlspecialchars((string)$_SESSION['login_error'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <?php unset($_SESSION['login_error']); ?>
                <?php } ?>
            </div>
        </div>

        <div class="side-panel">
            <div class="side-panel-content">
                <div class="login-side-kicker">FullCare inteligência</div>
                <h3>Sistema inteligente de auditoria médica</h3>
                <p>BI e IA transformam dados assistenciais em acompanhamento objetivo de internações, contas e indicadores.</p>
                <div class="login-insight-row" aria-label="Indicadores em destaque">
                    <div class="login-insight-card">
                        <small>BI</small>
                        <strong>Dados em tempo real</strong>
                    </div>
                    <div class="login-insight-card">
                        <small>IA</small>
                        <strong>Leitura assistencial</strong>
                    </div>
                    <div class="login-insight-card">
                        <small>Gestão</small>
                        <strong>Decisão mais rápida</strong>
                    </div>
                </div>
                <div class="login-flow-strip" aria-label="Fluxo de auditoria">
                    <span>Internação</span>
                    <i aria-hidden="true"></i>
                    <span>Auditoria</span>
                    <i aria-hidden="true"></i>
                    <span>Resultado</span>
                </div>
                <img src="<?= htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') ?>/img/producao_preview.png?v=20260516-login-preview-pro" alt="Preview do dashboard de producao" class="monitor-image" />
            </div>
        </div>
    </div>

    <div class="login-footer">
        Versão <?= htmlspecialchars($currentAppVersion) ?>
    </div>

    <script>
    // limpar os campos manualmente e evitar autocompletar
    document.addEventListener("DOMContentLoaded", () => {
        const emailInput = document.getElementById("email_login");
        const senhaInput = document.getElementById("senha_login");
        emailInput.value = "";
        senhaInput.value = "";
        setTimeout(() => {
            emailInput.value = "";
            senhaInput.value = "";
        }, 100);
    });

    // resetar campos após o submit
    document.querySelector("form").addEventListener("submit", function() {
        setTimeout(() => {
            this.reset();
        }, 100);
    });

    </script>
</body>

</html>
