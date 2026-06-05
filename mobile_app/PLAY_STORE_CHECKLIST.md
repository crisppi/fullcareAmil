# FullCare Mobile - Google Play checklist

## Identidade do app

- Nome: `FullCare Mobile`
- Package ID: `br.com.fullcare.mobile`
- Dominio de producao: `https://sistema.fullcareaudit.com.br/`
- Politica de Privacidade: `https://accertconsult.com.br/politica-privacidade-fullcare-mobile.html`
- API mobile: `https://sistema.fullcareaudit.com.br/api/mobile/index.php`

## Requisitos tecnicos

- Usar somente API HTTPS em producao.
- Gerar release com `FULLCARE_API_BASE_URL` apontando para a URL publica HTTPS da API.
- Configurar assinatura release com `android/key.properties`.
- Nao publicar `android/key.properties`, `.jks` ou `.keystore`.

## Criar keystore

Execute dentro de `mobile_app`:

```bash
keytool -genkey -v -keystore upload-keystore.jks -keyalg RSA -keysize 2048 -validity 10000 -alias fullcare-mobile
```

Depois crie `android/key.properties` usando `android/key.properties.example` como modelo.

## Gerar App Bundle

```bash
flutter build appbundle --release --dart-define=FULLCARE_API_BASE_URL=https://sistema.fullcareaudit.com.br/api/mobile/index.php
```

O arquivo gerado fica em:

```text
build/app/outputs/bundle/release/app-release.aab
```

## Play Console

Preencher:

- App access: informar usuario e senha de teste validos.
- Privacy Policy: informar a URL publica da politica.
- Data safety: declarar dados pessoais, credenciais, dados assistenciais/saude, finalidade, compartilhamento e seguranca.
- Health apps declaration: marcar que o app trata funcionalidades/dados relacionados a saude.
- Content rating: preencher questionario de classificacao.
- Target audience: publico profissional/operacional, nao infantil.

## Conta de teste para revisao

Criar um usuario real em `tb_user` apenas para revisao do Google Play:

- ativo_user = `s`
- sem 2FA externa
- com permissao suficiente para navegar pelas telas principais
- sem dados sensiveis reais, usando base de demonstracao quando possivel

No campo App access do Play Console, escrever instrucoes claras em ingles e portugues.
