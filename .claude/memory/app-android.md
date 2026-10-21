---
name: app-android
description: Versão e assinatura do app Android dos tablets
metadata:
  type: reference
source: android/construir.sh, README.md "App Android: como montar uma versão nova"
evidence: versões 1.0 a 1.2 assinadas com a mesma chave; arquivo da chave entregue ao dono em 15/10/2026
last_verified: 2026-10-19
invalidate_when: a chave for trocada ou o app passar a ser publicado em loja
---

O APK fica em `public/baixar/prospera.apk` e é instalado por cima pelos tablets em `/app`. Toda versão nova precisa da mesma chave de assinatura (`prospera-app.jks` e a senha), que fica só com o dono e nunca vai para o Git. Para montar uma versão nova numa sessão, peça ao dono para enviar o arquivo da chave e a senha, guarde os dois só na pasta temporária da sessão (nunca dentro do repositório) e apague no fim. O `.gitignore` e o `.dockerignore` já recusam `*.jks` e `*.keystore`. Aumente `versionCode`/`versionName` no `android/AndroidManifest.xml`, `VERSAO` no `Principal.java` e `$versaoApp` em `views/app_android.php`. Confira com `apksigner verify --print-certs` que o certificado é o mesmo da versão anterior.
