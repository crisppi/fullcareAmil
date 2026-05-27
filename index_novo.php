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
            radial-gradient(circle at 18% 18%, rgba(82, 154, 218, .10), transparent 28%),
            radial-gradient(circle at 88% 20%, rgba(92, 38, 118, .09), transparent 26%),
            linear-gradient(135deg, #f8fbfd 0%, #eef4f9 44%, #faf5fb 100%);
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
        opacity: 0.055;
        z-index: -1;
        pointer-events: none;
    }

    body::after {
        content: "";
        position: fixed;
        width: min(980px, 72vw);
        height: min(620px, 58vh);
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        border-radius: 999px;
        background: radial-gradient(circle, rgba(255, 255, 255, .18) 0%, rgba(255, 255, 255, .08) 38%, rgba(255, 255, 255, 0) 68%);
        filter: blur(4px);
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
        background:
            radial-gradient(circle at 13% 18%, rgba(48, 160, 210, .42), transparent 0 28%),
            radial-gradient(circle at 90% 62%, rgba(104, 48, 135, .36), transparent 0 31%),
            linear-gradient(112deg, rgba(232, 248, 255, .98) 0%, rgba(255, 255, 255, .99) 43%, rgba(255, 246, 255, .98) 100%);
        border: 1px solid rgba(87, 57, 115, .26);
        border-radius: 22px;
        box-shadow:
            0 46px 104px rgba(30, 37, 58, .34),
            0 22px 46px rgba(77, 40, 104, .18),
            0 0 0 1px rgba(255, 255, 255, .96) inset,
            0 1px 0 rgba(255, 255, 255, .98) inset;
        backdrop-filter: blur(8px) saturate(1.04);
        -webkit-backdrop-filter: blur(8px) saturate(1.04);
        position: relative;
        overflow: hidden;
    }

    .login-container > * {
        position: relative;
        z-index: 2;
    }

    .login-container .side-panel::before {
        z-index: 0;
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
            radial-gradient(circle at 76% 70%, rgba(255, 255, 255, .30), transparent 0 32%, rgba(255, 255, 255, 0) 33%),
            linear-gradient(135deg, rgba(35, 133, 194, .74), rgba(82, 183, 220, .46));
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
            linear-gradient(160deg, rgba(108, 48, 139, .64), rgba(74, 28, 94, .42));
        transform: rotate(-4deg);
        box-shadow:
            inset 22px 0 42px rgba(255, 255, 255, .12),
            -18px 24px 46px rgba(66, 24, 73, .10);
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
        background: linear-gradient(170deg, rgba(108, 48, 139, .44), rgba(66, 24, 73, .16));
        border-left: 1px solid rgba(255, 255, 255, .18);
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
        background: linear-gradient(145deg, rgba(35, 150, 205, .30), rgba(255, 255, 255, .04));
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
        background:
            radial-gradient(circle at 0% 0%, rgba(48, 160, 210, .20), transparent 0 40%),
            linear-gradient(180deg, rgba(255, 255, 255, .99), rgba(244, 251, 255, .98));
        border: 1px solid rgba(88, 166, 205, .38);
        box-shadow:
            0 38px 82px rgba(42, 57, 82, .22),
            0 18px 38px rgba(55, 119, 157, .13),
            0 2px 0 rgba(255, 255, 255, .9) inset;
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
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
        border: 1px solid rgba(78, 155, 196, .10);
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
        border-bottom: 1px solid rgba(54, 139, 154, .58) !important;
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
        border-color: #5a2363 !important;
        box-shadow: 0 6px 0 -5px rgba(90, 35, 99, .72);
    }

    .input-container input:-webkit-autofill,
    .input-container input:-webkit-autofill:hover,
    .input-container input:-webkit-autofill:focus {
        -webkit-text-fill-color: #263241;
        box-shadow: 0 0 0 1000px rgba(236, 246, 255, .78) inset !important;
        border-bottom-color: rgba(54, 139, 154, .72) !important;
        transition: background-color 9999s ease-in-out 0s;
    }

    .input-container label {
        position: absolute;
        top: -16px;
        left: 0;
        color: #327f8a;
        pointer-events: none;
        transition: color .2s ease;
        font-size: 11px;
        font-weight: 700;
    }

    .input-container input:focus+label,
    .input-container input:not(:placeholder-shown)+label {
        top: -20px;
        font-size: 11px;
        color: #5a2363;
    }

    .login-btn {
        width: 100%;
        padding: 12px;
        background: linear-gradient(135deg, #6b2b74, #421849);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, .22);
        cursor: pointer;
        font-size: 14px;
        font-weight: 700;
        border-radius: 8px;
        margin-top: 18px;
        box-shadow: 0 12px 26px rgba(66, 24, 73, .28);
        transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
    }

    .login-btn:hover {
        box-shadow: 0 15px 32px rgba(66, 24, 73, .34);
        background: linear-gradient(135deg, #783484, #4b1c58);
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
        color: #4a1d55;
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
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 58px;
        padding: 10px 11px;
        border-radius: 13px;
        background:
            linear-gradient(180deg, rgba(255, 255, 255, .90), rgba(246, 249, 255, .70)),
            linear-gradient(135deg, rgba(48, 160, 210, .32), rgba(108, 48, 139, .24));
        border: 1px solid rgba(112, 121, 178, .26);
        box-shadow: 0 14px 28px rgba(59, 66, 92, .11);
        text-align: center;
    }

    .login-insight-card small {
        display: block;
        color: rgba(83, 46, 101, .62);
        font-size: 9px;
        font-weight: 800;
        letter-spacing: .1em;
        text-transform: uppercase;
        line-height: 1.1;
    }

    .login-insight-card strong {
        display: block;
        margin-top: 7px;
        color: #4a1d55;
        font-size: 12px;
        font-weight: 800;
        line-height: 1.15;
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
        color: rgba(55, 46, 78, .72);
        font-size: 12px;
        font-weight: 400;
    }

    .side-panel h3 {
        margin: 0;
        font-size: 20px;
        line-height: 1.2;
        letter-spacing: 0;
        font-weight: 800;
        color: #4a1d55;
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
                <h3>Sistema inteligente de auditoria médica</h3>
                <p>BI e IA transformam dados assistenciais em acompanhamento objetivo de internações, contas e indicadores.</p>
                <div class="login-insight-row" aria-label="Indicadores em destaque">
                    <div class="login-insight-card">
                        <small>BI</small>
                        <strong>Dados em tempo real</strong>
                    </div>
                    <div class="login-insight-card">
                        <small>IA</small>
                        <strong>Insights clínicos inteligentes</strong>
                    </div>
                    <div class="login-insight-card">
                        <small>Gestão</small>
                        <strong>Decisão mais rápida</strong>
                    </div>
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
