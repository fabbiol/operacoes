#!/bin/sh
# Monta o app Android do tablet (o arquivo .apk), sem Android Studio.
#
# Precisa de: JDK (javac e keytool), aapt, dx, zipalign, apksigner e o android.jar.
# No Ubuntu:  apt-get install default-jdk android-sdk-platform-23 aapt dalvik-exchange zipalign apksigner
#
# Uso:
#   CHAVE=/caminho/prospera-app.jks CHAVE_SENHA='...' sh android/construir.sh
#
# CHAVE é o arquivo da assinatura do app. Guarde sempre o mesmo: o Android só aceita atualizar o app
# se a versão nova vier com a mesma assinatura. Sem ele, é preciso desinstalar o app antigo antes.
# ENDERECO (opcional) é o endereço do sistema que o app abre. Padrão: https://operacoes.fabbiol.tech/
# Antes de montar uma versão nova, aumente android:versionCode e android:versionName no AndroidManifest.xml
# e VERSAO no Principal.java.
set -eu

AQUI=$(cd "$(dirname "$0")" && pwd)
RAIZ=$(dirname "$AQUI")
ENDERECO=${ENDERECO:-https://operacoes.fabbiol.tech/}
ANDROID_JAR=${ANDROID_JAR:-/usr/lib/android-sdk/platforms/android-23/android.jar}
SAIDA=${SAIDA:-$RAIZ/public/app/prospera.apk}
: "${CHAVE:?Informe CHAVE, o arquivo .jks da assinatura do app}"
: "${CHAVE_SENHA:?Informe CHAVE_SENHA, a senha do arquivo da assinatura}"
export CHAVE_SENHA

case "$ENDERECO" in
    https://*/) ;;
    *) echo "ENDERECO precisa começar com https:// e terminar com /" >&2; exit 1 ;;
esac

DX=$(command -v dalvik-exchange || command -v dx || true)
[ -n "$DX" ] || { echo "Falta o dx (pacote dalvik-exchange)." >&2; exit 1; }
[ -f "$ANDROID_JAR" ] || { echo "Falta o android.jar em $ANDROID_JAR (pacote android-sdk-platform-23)." >&2; exit 1; }

T=$(mktemp -d)
trap 'rm -rf "$T"' EXIT
mkdir -p "$T/gerado" "$T/classes" "$T/src"

# O endereço do sistema vai para dentro do app.
cp -R "$AQUI/src/." "$T/src/"
sed -i "s#\"https://operacoes.fabbiol.tech/\"#\"$ENDERECO\"#" "$T/src/br/com/prospera/operacoes/Principal.java"

# 1. Recursos (ícones, nome, cores) e a classe R.
aapt package -f -m -J "$T/gerado" -M "$AQUI/AndroidManifest.xml" -S "$AQUI/res" -I "$ANDROID_JAR"
# 2. Código Java, compilado contra o Android.
javac -source 8 -target 8 -nowarn -Xlint:-options -encoding UTF-8 -bootclasspath "$ANDROID_JAR" \
    -d "$T/classes" $(find "$T/src" "$T/gerado" -name '*.java')
# 3. Código no formato do Android.
"$DX" --dex --output="$T/classes.dex" "$T/classes"
# 4. O pacote: recursos (o resources.arsc sem compressão, exigência do Android 11 em diante) e o código.
aapt package -f -0 arsc -M "$AQUI/AndroidManifest.xml" -S "$AQUI/res" -I "$ANDROID_JAR" -F "$T/app.apk"
(cd "$T" && zip -q -X app.apk classes.dex)
# 5. Alinhado e assinado.
zipalign -p -f 4 "$T/app.apk" "$T/alinhado.apk"
mkdir -p "$(dirname "$SAIDA")"
apksigner sign --ks "$CHAVE" --ks-pass env:CHAVE_SENHA --key-pass env:CHAVE_SENHA --out "$SAIDA" "$T/alinhado.apk"
apksigner verify "$SAIDA"
rm -f "$SAIDA.idsig"
echo "App pronto: $SAIDA (abre $ENDERECO)"
