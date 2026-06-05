# FullCare Mobile - primeira versao

## Objetivo

Entregar um app operacional para usuarios autorizados do FullCare acompanharem e registrarem
informacoes assistenciais e administrativas diretamente pelo celular, usando a mesma base do
sistema web.

## Escopo incluido

- Login com usuario da `tb_user`.
- Aceite e exibicao da Politica de Privacidade.
- Sessao autenticada por token.
- Hub principal com modulos operacionais.
- Lista de internados.
- Detalhe da internacao.
- Lancamento de TUSS.
- Lancamento de prorrogacao.
- Registro de alta.
- Registro e consulta de evolucao.
- Fila de Home Care.
- Fila de Longa Permanencia.
- Registro de Evento Adverso.

## Fora da primeira versao

- Cadastro completo de beneficiarios.
- Cadastro completo de internacoes.
- BI completo no app.
- Administracao de usuarios pelo app.
- Funcionalidades offline.
- Notificacoes push.

## Regras de seguranca da primeira versao

- Acesso somente com usuario ativo no FullCare.
- Token enviado apenas no cabecalho `Authorization`.
- API publica somente em HTTPS.
- Sem senha, token ou dados sensiveis em URL.
- Sem credenciais de teste embutidas no app.
- Politica de Privacidade publica e tambem exibida na tela de login.

## URLs de producao

- Sistema web: `https://sistema.fullcareaudit.com.br/`
- API mobile: `https://sistema.fullcareaudit.com.br/api/mobile/index.php`
- Politica de Privacidade: `https://accertconsult.com.br/politica-privacidade-fullcare-mobile.html`
