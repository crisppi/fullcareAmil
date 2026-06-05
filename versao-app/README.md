# Versao App

Aplicacao enxuta para operacao de internacao, TUSS e prorrogacao, isolada do restante do projeto em `versao-app/`.

## O que entrega na V1

- cadastro de internacoes
- associacao de procedimentos TUSS
- registro de pedidos de prorrogacao
- painel com busca e filtro por status
- persistencia local em JSON para simplificar implantacao inicial

## Estrutura

- `index.php`: interface principal
- `api.php`: endpoints locais da V1
- `lib/storage.php`: leitura e escrita do arquivo JSON
- `assets/`: CSS e JavaScript
- `data/storage.json`: criado automaticamente no primeiro uso

## Como usar

1. Abra `http://localhost/fullcareAmil/versao-app/`
2. Cadastre uma internacao
3. Vincule itens TUSS
4. Registre a prorrogacao
5. Use o painel para acompanhar

## Observacao

Esta V1 usa arquivo JSON como armazenamento para acelerar validacao do fluxo. O passo seguinte natural seria trocar `lib/storage.php` por persistencia em MySQL sem alterar a interface.
