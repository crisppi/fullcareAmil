<?php $footerVersion = app_latest_version($conn); ?>
<!DOCTYPE html>
<html>

<head>
    <title>Rodapé FullCare</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= $BASE_URL ?>css/footer-with-button-logo.css">
    <style>
        #myFooter {
            background: #ffffff;
            padding: 32px 0 16px;
            border-top: 1px solid #e0e0e0;
            font-family: "Inter", Arial, Helvetica, sans-serif;
            width: 100%;
        }

        #myFooter .footer-content {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 40px;
            flex-wrap: wrap;
            padding: 0 5vw;
            box-sizing: border-box;
        }

        #myFooter .footer-menu {
            display: flex;
            gap: 48px;
            flex-wrap: wrap;
        }

        #myFooter .footer-menu-column {
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 0.85rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        #myFooter .footer-menu-column h5 {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 700;
        }

        #myFooter .footer-menu-column a {
            text-transform: none;
            letter-spacing: 0;
            font-weight: 400;
            color: #222;
            text-decoration: none;
            font-size: 0.85rem;
        }

        #myFooter .footer-menu-column a:hover {
            text-decoration: underline;
        }

        #myFooter .footer-brands img {
            width: 90px;
            filter: saturate(1.1);
        }

        #myFooter .footer-social {
            display: flex;
            flex-direction: column;
            gap: 10px;
            font-size: 0.85rem;
        }

        #myFooter .footer-social a {
            color: #2c2c2c;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        #myFooter .footer-social i {
            font-size: 1.05rem;
        }

        .footer-cta {
            margin-top: 4px;
            border-radius: 999px;
            padding: 8px 26px;
            background: #5e2363;
            color: #fff;
            text-transform: uppercase;
            font-size: 0.78rem;
            letter-spacing: 0.25em;
            border: none;
            cursor: pointer;
            font-weight: 600;
        }

        #myFooter .footer-bottom {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 5vw;
            margin-top: 18px;
            border-top: none;
            font-size: 0.82rem;
            color: #fff;
            flex-wrap: wrap;
            gap: 8px;
            box-sizing: border-box;
            background: #5e2363;
        }

        #myFooter .footer-bottom span {
            color: #fff;
        }

        #myFooter .footer-version {
            font-weight: 600;
            letter-spacing: 0.05em;
            position: relative;
            display: inline-block;
            margin-left: 1rem;
            cursor: pointer;
            text-decoration: underline;
        }

        @media (max-width: 840px) {
            #myFooter .footer-content {
                justify-content: center;
            }

            #myFooter .footer-menu {
                justify-content: center;
            }

            #myFooter .footer-bottom {
                flex-direction: column;
                align-items: center;
            }
        }
        #versionModalOverlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        #versionModalOverlay.active {
            display: flex;
        }

        #versionModal {
            background: #fff;
            border-radius: 0.65rem;
            max-width: 520px;
            width: 100%;
            padding: 1.75rem 1.5rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.35);
            position: relative;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        #versionModal h3 {
            margin: 0 0 0.25rem;
            font-size: 1rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        #versionModal .version-modal-date {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 0.75rem;
        }

        #versionModal ol {
            padding-left: 1.25rem;
            margin: 0;
        }

        #versionModal li {
            margin-bottom: 0.3rem;
        }

        #versionModalClose {
            position: absolute;
            top: 0.6rem;
            right: 0.75rem;
            border: none;
            background: transparent;
            font-size: 1.2rem;
            cursor: pointer;
            color: #333;
        }
    </style>
</head>

<body>
    <footer id="myFooter">
        <div class="footer-content">
            <div class="footer-brands">
                <a href="<?= $BASE_URL ?>inicio">
                    <img src="<?= $BASE_URL ?>img/full-03.png" alt="FullCare">
                </a>
            </div>
            <div class="footer-menu">
                <div class="footer-menu-column">
                    <h5>Início</h5>
                    <a href="https://accertconsult.com.br/" target="_blank">Home</a>
                    <a href="https://accertconsult.com.br/produtos" target="_blank">Produtos</a>
                    <a href="https://www.accertconsult.com.br/sinistralidade" target="_blank">Sinistralidade</a>
                </div>
                <div class="footer-menu-column">
                    <h5>Sobre nós</h5>
                    <a href="https://accertconsult.com.br/" target="_blank">Informações da Empresa</a>
                    <a href="https://accertconsult.com.br/contato" target="_blank">Contato</a>
                    <a href="https://blog.fullcare.cloud/" target="_blank">Blog</a>
                </div>
                <div class="footer-menu-column">
                    <h5>Suporte</h5>
                    <a href="https://accertconsult.com.br/" target="_blank">FAQ</a>
                    <a href="https://accertconsult.com.br/contato" target="_blank">Telefones</a>
                    <a href="https://accertconsult.com.br/" target="_blank">Área restrita</a>
                </div>
            </div>
            <div class="footer-social">
                <a href="https://www.linkedin.com/in/accertconsult/" target="_blank"><i class="bi bi-linkedin"></i>Linkedin</a>
                <a href="https://accertconsult.com.br/" target="_blank"><i class="bi bi-facebook"></i>Facebook</a>
                <a href="https://www.instagram.com/accert_consult/" target="_blank"><i class="bi bi-instagram"></i>Instagram</a>
                <a href="https://accertconsult.com.br/contato" target="_blank">
                    <button class="footer-cta">Contato</button>
                </a>
            </div>
        </div>
            <div class="footer-bottom">
            <span>© 2022 FullCare – Accert Consult</span>
            <span class="footer-version" id="footerVersionTrigger" role="button" tabindex="0">
                Versão <?= htmlspecialchars($footerVersion) ?>
            </span>
        </div>
        <div id="versionModalOverlay" role="presentation">
            <div id="versionModal" role="dialog" aria-modal="true" aria-labelledby="versionModalTitle">
                <button id="versionModalClose" aria-label="Fechar">×</button>
                <h3 id="versionModalTitle">ATUALIZAÇÕES DA VERSÃO</h3>
                <div class="version-modal-date">DATA ATUALIZAÇÃO – 17/01/2026</div>
                <ol>
                    <li>Painel no Hub pacientes – somente visível se logado com médico, enfermeiro ou diretor</li>
                    <li>Pesquisa por matrícula e nome separadas.</li>
                    <li>Data da visita não obrigatório.</li>
                    <li>No Menu Cadastros - Alterado Pacientes para Lista Pacientes</li>
                    <li>No Menu Produção – retirado listas Internação, Internação UTI.</li>
                    <li>Reposicionado o Botão de Nova Visita na parte superior do Hub Paciente.</li>
                    <li>Acrescentado PS como nova acomodação.</li>
                    <li>Acrescentado Desconto no Hub paciente – visualização da conta.</li>
                    <li>Colocado campo Glosa Total no RAH, com cálculo automático.</li>
                    <li>Alterado Final para Liberado nas contas.</li>
                    <li>Acrescentado campos – Valor apresentado, Seguradora, Data internação e data alta no export excel.</li>
                    <li>Filtro Seguradora na lista de faturamento visitas.</li>
                    <li>Botão Parcial no hub paciente para criar nova parcial dentro da conta.</li>
                </ol>
            </div>
        </div>
    </footer>
    <script>
        (function () {
            var trigger = document.getElementById('footerVersionTrigger');
            var overlay = document.getElementById('versionModalOverlay');
            var closeBtn = document.getElementById('versionModalClose');

            if (!trigger || !overlay || !closeBtn) {
                return;
            }

            function openModal() {
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }

            function closeModal() {
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }

            trigger.addEventListener('click', openModal);
            trigger.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openModal();
                }
            });

            closeBtn.addEventListener('click', closeModal);

            overlay.addEventListener('click', function (event) {
                if (event.target === overlay) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && overlay.classList.contains('active')) {
                    closeModal();
                }
            });
        })();
    </script>
</body>

</html>
