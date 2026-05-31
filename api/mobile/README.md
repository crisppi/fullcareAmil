# FullCare Mobile API

API para o app mobile nativo, usando o mesmo banco de dados do FullCare web.

## Endpoints iniciais

- `POST index.php?action=login`
- `GET index.php?action=me`
- `GET index.php?action=patients&query=...`
- `GET index.php?action=admissions&query=...`
- `GET index.php?action=admission&id=...`
- `GET index.php?action=tuss-catalog&query=...`
- `POST index.php?action=admission-tuss`
- `POST index.php?action=admission-extension`

## Autenticacao

Use exclusivamente o cabecalho `Authorization: Bearer <token>`.

Nao envie token por query string, porque URLs podem ficar gravadas em historico,
logs de proxy, analytics e rastreamentos de erro.

## Observacao

Para producao:

- defina `MOBILE_API_SECRET` no ambiente para substituir o segredo padrao de desenvolvimento;
- publique a API somente em HTTPS;
- mantenha `MOBILE_API_DEBUG` desativado para nao expor detalhes internos em respostas de erro;
- configure o app Flutter com `--dart-define=FULLCARE_API_BASE_URL=https://sistema.fullcareaudit.com.br/api/mobile/index.php`.
