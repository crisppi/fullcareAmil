<?php
if (defined('FULLCARE_FOOTER_RENDERED')) {
    return;
}
define('FULLCARE_FOOTER_RENDERED', true);
require_once(__DIR__ . '/../app/security/bi_access.php');

$footerVersion = '1.3.6';
$footerYear = date('Y');
$canSeeBiLink = function_exists('fullcare_has_bi_access') ? fullcare_has_bi_access() : false;
?>
<footer id="myFooterSimple" aria-label="Rodapé FullCare">
    <div class="footer-simple-inner">
        <div class="footer-simple-topline"></div>

        <div class="footer-simple-main">
            <a href="https://fullcare.cloud" target="_blank" rel="noopener noreferrer"
                class="footer-simple-brand" aria-label="Abrir fullcare.cloud">
                <img src="<?= $BASE_URL ?>img/full-03.png" alt="FullCare">
                <span class="footer-simple-brand-text">Gestão em Saúde</span>
            </a>

            <nav class="footer-simple-links" aria-label="Links rápidos">
                <a href="<?= $BASE_URL ?>inicio">Início</a>
                <a href="<?= $BASE_URL ?>internacoes/lista">Internações</a>
                <a href="<?= $BASE_URL ?>visitas/lista">Visitas</a>
                <?php if ($canSeeBiLink) { ?>
                    <a href="<?= $BASE_URL ?>bi/navegacao">BI</a>
                <?php } ?>
            </nav>

            <div class="footer-simple-meta">
                <span class="footer-simple-copy">© <?= (int)$footerYear ?> FullCare - Accert Consult.</span>
                <span class="footer-simple-version">Versão <?= htmlspecialchars((string)$footerVersion, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>
    </div>
</footer>

<style>
html,
body {
    min-height: 100%;
}

body {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    padding-bottom: 88px;
}

#myFooterSimple {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 40;
    width: 100%;
    background: linear-gradient(90deg, #f2ecfb 0%, #ebe5f7 52%, #e8f1fb 100%);
    box-shadow: 0 -8px 22px rgba(95, 69, 148, 0.08);
}

#myFooterSimple .footer-simple-inner {
    position: relative;
}

#myFooterSimple .footer-simple-main {
    min-height: 52px;
    padding: 8px 18px;
    display: flex;
    align-items: center;
    gap: 16px;
}

#myFooterSimple .footer-simple-brand {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

#myFooterSimple .footer-simple-brand img {
    width: 64px;
    max-width: 100%;
    height: auto;
}

#myFooterSimple .footer-simple-brand-text {
    color: #6a5b84;
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

#myFooterSimple .footer-simple-links {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    margin: 0 auto;
}

#myFooterSimple .footer-simple-links a {
    color: #6a587f;
    text-decoration: none;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.02em;
}

#myFooterSimple .footer-simple-links a:hover {
    color: #4f3674;
    text-decoration: underline;
    text-decoration-color: rgba(79, 54, 116, 0.32);
}

#myFooterSimple .footer-simple-meta {
    margin-left: auto;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

#myFooterSimple .footer-simple-copy,
#myFooterSimple .footer-simple-version {
    color: #746489;
    font-size: 0.66rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    white-space: nowrap;
}

@media (max-width: 920px) {
    body {
        padding-bottom: 104px;
    }

    #myFooterSimple .footer-simple-main {
        flex-wrap: wrap;
        justify-content: center;
        row-gap: 6px;
    }

    #myFooterSimple .footer-simple-meta {
        margin-left: 0;
    }
}

@media (max-width: 640px) {
    body {
        padding-bottom: 112px;
    }

    #myFooterSimple .footer-simple-main {
        min-height: 58px;
        padding: 8px 10px;
        gap: 8px;
    }

    #myFooterSimple .footer-simple-brand img {
        width: 56px;
    }

    #myFooterSimple .footer-simple-brand-text,
    #myFooterSimple .footer-simple-links a,
    #myFooterSimple .footer-simple-copy {
        font-size: 0.6rem;
    }

    #myFooterSimple .footer-simple-version {
        font-size: 0.58rem;
    }
}
</style>
