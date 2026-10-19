#!/bin/sh
# Prepara um contêiner novo da nuvem para rodar os testes (sh tests/rodar.sh). Pode rodar quantas vezes quiser:
# o que já está pronto não muda. Faz, nesta ordem:
#   1. instala o que falta com apt-get (só como root; sem root, só diz o que instalar): MariaDB, poppler-utils, curl
#   2. liga o MariaDB em 127.0.0.1:3307 se ele não responder
#   3. cria o usuário de teste (restrito a 127.0.0.1 e aos bancos op_t_*)
#   4. confere o Playwright (não instala navegador: na nuvem o Chromium já vem em /opt/pw-browsers)
# Variáveis: TESTE_DB_PORTA (3307), TESTE_DB_USUARIO (operacoes_teste), TESTE_DB_SENHA (teste_local),
#            TESTE_DB_ADMIN (comando do cliente com acesso de administrador; padrão: "mysql -uroot" pelo socket local),
#            TESTE_SEM_APT=1 (não instala nada, só mostra o que faria).

TESTES=$(cd "$(dirname "$0")" && pwd)
RAIZ=$(dirname "$TESTES")
. "$TESTES/lib/comum.sh"

PENDENCIAS_ESSENCIAIS=""
PENDENCIAS_OPCIONAIS=""
APT_ATUALIZADO=0

dizer() { printf '  %-8s %s\n' "$1" "$2"; }
falta_essencial() { PENDENCIAS_ESSENCIAIS="$PENDENCIAS_ESSENCIAIS  - $1
"; }
falta_opcional() { PENDENCIAS_OPCIONAIS="$PENDENCIAS_OPCIONAIS  - $1
"; }

# Instala um pacote apt (só como root). Devolve 0 se instalou.
instalar_pacote() { # $1 = pacote
    if [ "$(id -u)" -ne 0 ] || ! command -v apt-get >/dev/null 2>&1; then
        dizer "FALTA" "$1 — instale com: sudo apt-get install -y $1"; return 1
    fi
    if [ -n "${TESTE_SEM_APT:-}" ]; then
        dizer "SIMULA" "apt-get install -y $1"; return 1
    fi
    if [ "$APT_ATUALIZADO" = 0 ]; then
        APT_ATUALIZADO=1
        DEBIAN_FRONTEND=noninteractive apt-get update -qq >/dev/null 2>&1
    fi
    dizer "INSTALA" "apt-get install -y $1"
    if DEBIAN_FRONTEND=noninteractive apt-get install -y "$1" >"$TEMP_BASE/$PREFIXO_TEMP-apt.log" 2>&1; then
        rm -f "$TEMP_BASE/$PREFIXO_TEMP-apt.log"; return 0
    fi
    dizer "ERRO" "a instalação de $1 falhou; veja $TEMP_BASE/$PREFIXO_TEMP-apt.log"; return 1
}

echo "Preparando o ambiente de testes"

# --- 1. Ferramentas -------------------------------------------------------------------------------
echo
echo "Ferramentas"
for _c in php git; do
    if command -v "$_c" >/dev/null 2>&1; then dizer "OK" "$_c"; else dizer "FALTA" "$_c"; falta_essencial "$_c não está instalado"; fi
done
if command -v node >/dev/null 2>&1; then dizer "OK" "node $(node -v)"; else dizer "FALTA" "node (18 ou mais novo) — só os testes de tela e HTTP precisam"; falta_essencial "instale o Node 18 ou mais novo"; fi
if command -v curl >/dev/null 2>&1; then dizer "OK" "curl"
elif instalar_pacote curl; then dizer "FEITO" "curl instalado"
else falta_essencial "curl (sudo apt-get install -y curl)"; fi

if command -v mariadbd >/dev/null 2>&1 || command -v mysqld >/dev/null 2>&1; then dizer "OK" "servidor MariaDB/MySQL"
elif instalar_pacote mariadb-server; then dizer "FEITO" "mariadb-server instalado"
else falta_essencial "servidor MariaDB (sudo apt-get install -y mariadb-server)"; fi

if command -v mysql >/dev/null 2>&1 || command -v mariadb >/dev/null 2>&1; then dizer "OK" "cliente mysql/mariadb"
elif instalar_pacote mariadb-client; then dizer "FEITO" "mariadb-client instalado"
else falta_essencial "cliente MariaDB (sudo apt-get install -y mariadb-client)"; fi
# O cliente pode ter acabado de entrar no PATH.
if command -v mysql >/dev/null 2>&1; then MYSQL_BIN=mysql; elif command -v mariadb >/dev/null 2>&1; then MYSQL_BIN=mariadb; fi

if command -v pdftotext >/dev/null 2>&1; then dizer "OK" "pdftotext (poppler-utils)"
elif instalar_pacote poppler-utils; then dizer "FEITO" "poppler-utils instalado"
else falta_opcional "pdftotext (sudo apt-get install -y poppler-utils): sem ele o teste que lê o PDF da nota falha"; fi

if php -m 2>/dev/null | grep -qi '^pdo_mysql$'; then dizer "OK" "php pdo_mysql"; else dizer "FALTA" "php pdo_mysql"; falta_essencial "extensão pdo_mysql do PHP (sudo apt-get install -y php-mysql)"; fi
if php -m 2>/dev/null | grep -qi '^gd$'; then dizer "OK" "php gd"; else dizer "AVISO" "php gd ausente: o teste de fotos fica PULADO (sudo apt-get install -y php-gd)"; falta_opcional "extensão gd do PHP (teste de fotos)"; fi

# --- 2. MariaDB ligado ----------------------------------------------------------------------------
echo
echo "MariaDB em $DB_HOST:$DB_PORTA"
if porta_aberta "$DB_HOST" "$DB_PORTA"; then
    dizer "OK" "já responde"
else
    case "$DB_HOST" in
        127.0.0.1|localhost) _local=1 ;;
        *) _local=0 ;;
    esac
    if [ "$_local" = 0 ]; then
        dizer "FALTA" "o banco de $DB_HOST:$DB_PORTA não responde e não é local — não inicio nada"
        falta_essencial "banco $DB_HOST:$DB_PORTA fora do ar"
    elif ! command -v mysqld_safe >/dev/null 2>&1; then
        dizer "FALTA" "mysqld_safe não encontrado; inicie o MariaDB em 127.0.0.1:$DB_PORTA"
        falta_essencial "MariaDB não está rodando em 127.0.0.1:$DB_PORTA"
    elif [ "$(id -u)" -ne 0 ]; then
        dizer "FALTA" "sem root não ligo o banco; rode como root: mysqld_safe --user=mysql --port=$DB_PORTA --bind-address=127.0.0.1 &"
        falta_essencial "MariaDB não está rodando em 127.0.0.1:$DB_PORTA"
    else
        # Contêiner novo: o diretório de dados pode não ter sido criado (a instalação sem systemd não sobe o servidor).
        if [ ! -d /var/lib/mysql/mysql ]; then
            dizer "CRIA" "diretório de dados do MariaDB"
            if command -v mariadb-install-db >/dev/null 2>&1; then mariadb-install-db --user=mysql --datadir=/var/lib/mysql >/dev/null 2>&1
            elif command -v mysql_install_db >/dev/null 2>&1; then mysql_install_db --user=mysql --datadir=/var/lib/mysql >/dev/null 2>&1; fi
        fi
        dizer "LIGA" "mysqld_safe --user=mysql --port=$DB_PORTA --bind-address=127.0.0.1"
        (nohup mysqld_safe --user=mysql --port="$DB_PORTA" --bind-address=127.0.0.1 >"$TEMP_BASE/$PREFIXO_TEMP-mariadb.log" 2>&1 &)
        _i=0
        while [ "$_i" -lt 90 ]; do
            if porta_aberta "$DB_HOST" "$DB_PORTA"; then break; fi
            sleep 1; _i=$((_i + 1))
        done
        if porta_aberta "$DB_HOST" "$DB_PORTA"; then dizer "FEITO" "MariaDB respondendo"
        else
            dizer "ERRO" "o MariaDB não subiu em 90 s; veja $TEMP_BASE/$PREFIXO_TEMP-mariadb.log"
            falta_essencial "MariaDB não subiu em 127.0.0.1:$DB_PORTA"
        fi
    fi
fi

# --- 3. Usuário de teste --------------------------------------------------------------------------
echo
echo "Usuário de teste $DB_USUARIO@127.0.0.1 (só bancos op_t_*)"
usuario_pronto() {
    [ -n "$MYSQL_BIN" ] && porta_aberta "$DB_HOST" "$DB_PORTA" || return 1
    banco_sql -r -N -e 'SHOW GRANTS FOR CURRENT_USER()' 2>/dev/null | grep -q 'op\\_t\\_%'
}
if usuario_pronto; then
    dizer "OK" "já existe e entra"
elif [ -z "$MYSQL_BIN" ] || ! porta_aberta "$DB_HOST" "$DB_PORTA"; then
    dizer "PULA" "sem MariaDB para criar o usuário"
else
    # Administrador: o root pelo socket local (padrão da nuvem) ou o comando de TESTE_DB_ADMIN.
    ADMIN=${TESTE_DB_ADMIN:-"$MYSQL_BIN -uroot"}
    if $ADMIN -e 'SELECT 1' >/dev/null 2>&1; then
        $ADMIN <<EOF
CREATE USER IF NOT EXISTS '$DB_USUARIO'@'127.0.0.1' IDENTIFIED BY '$DB_SENHA';
ALTER USER '$DB_USUARIO'@'127.0.0.1' IDENTIFIED BY '$DB_SENHA';
GRANT ALL PRIVILEGES ON \`op\\_t\\_%\`.* TO '$DB_USUARIO'@'127.0.0.1';
FLUSH PRIVILEGES;
EOF
        if usuario_pronto; then dizer "FEITO" "usuário criado e conferido"
        else dizer "ERRO" "criei o usuário, mas ele não entra por $DB_HOST:$DB_PORTA"; falta_essencial "usuário de teste não entra no banco"; fi
    else
        dizer "FALTA" "não consegui entrar como administrador ($ADMIN). Crie o usuário à mão (veja tests/LEIAME.md)"
        falta_essencial "usuário de teste $DB_USUARIO (defina TESTE_DB_ADMIN com um comando de cliente que seja administrador)"
    fi
fi

# --- 4. Playwright --------------------------------------------------------------------------------
echo
echo "Playwright (testes de tela)"
if command -v node >/dev/null 2>&1; then
    if _msg=$(node "$TESTES/lib/verificar_playwright.js" 2>&1); then
        dizer "OK" "Playwright e Chromium funcionam"
    else
        dizer "FALTA" "$_msg"
        dizer "" "Não instalo navegador aqui. Na nuvem o Chromium fica em /opt/pw-browsers e o pacote em /opt/node-tools/node_modules/playwright;"
        dizer "" "fora dela: npm install -g playwright && npx playwright install chromium (e NODE_PATH apontando para o node_modules global)."
        falta_opcional "Playwright/Chromium (os testes de tela ficam PULADOS)"
    fi
fi

echo
if [ -z "$PENDENCIAS_ESSENCIAIS" ] && [ -z "$PENDENCIAS_OPCIONAIS" ]; then
    echo "Tudo pronto. Rode: sh tests/rodar.sh"
    exit 0
fi
if [ -n "$PENDENCIAS_ESSENCIAIS" ]; then echo "Pendências que impedem os testes de integração:"; printf '%s' "$PENDENCIAS_ESSENCIAIS"; fi
if [ -n "$PENDENCIAS_OPCIONAIS" ]; then echo "Pendências opcionais (alguns testes ficam PULADOS):"; printf '%s' "$PENDENCIAS_OPCIONAIS"; fi
[ -n "$PENDENCIAS_ESSENCIAIS" ] && exit 1
exit 0
