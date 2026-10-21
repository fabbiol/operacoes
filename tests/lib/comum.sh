# Funções comuns do runner e do preparo dos testes. Carregue com ". tests/lib/comum.sh". POSIX sh.
# Quem carrega define TESTES (a pasta tests/) e RAIZ (a raiz do projeto) antes.

# --- Banco de teste (MariaDB local). Tudo pode ser trocado por variável de ambiente. ---
DB_HOST=${TESTE_DB_HOST:-127.0.0.1}
DB_PORTA=${TESTE_DB_PORTA:-3307}
DB_USUARIO=${TESTE_DB_USUARIO:-operacoes_teste}
# Senha falsa, só deste banco local de teste.
DB_SENHA=${TESTE_DB_SENHA:-teste_local}
export TESTE_DB_HOST="$DB_HOST" TESTE_DB_PORTA="$DB_PORTA" TESTE_DB_USUARIO="$DB_USUARIO" TESTE_DB_SENHA="$DB_SENHA"

# Faixa de portas dos servidores temporários.
PORTA_INICIO=${TESTE_PORTA_INICIO:-8170}
PORTA_FIM=${TESTE_PORTA_FIM:-8199}

TEMP_BASE=${TMPDIR:-/tmp}
PREFIXO_TEMP=op-teste

# Cliente de linha de comando do MySQL/MariaDB.
if command -v mysql >/dev/null 2>&1; then MYSQL_BIN=mysql
elif command -v mariadb >/dev/null 2>&1; then MYSQL_BIN=mariadb
else MYSQL_BIN=""; fi
export TESTE_MYSQL="$MYSQL_BIN"

AMB_DIR=""; AMB_DB=""; AMB_PORTA=""; AMB_PID=""; AMB_ERRO=""

# Roda o cliente com o usuário de teste. O fuso -04:00 é o mesmo que o app usa na conexão dele:
# sem isso o NOW() da linha de comando sairia em UTC, 4 horas na frente do app.
banco_sql() {
    MYSQL_PWD="$DB_SENHA" "$MYSQL_BIN" --protocol=tcp -h "$DB_HOST" -P "$DB_PORTA" -u "$DB_USUARIO" \
        --default-character-set=utf8mb4 --init-command="SET time_zone='-04:00'" "$@"
}

# A porta aceita conexão? (usa o PHP, que os testes exigem de qualquer jeito)
porta_aberta() {
    php -r 'exit(@fsockopen($argv[1], (int) $argv[2], $n, $e, 2) ? 0 : 1);' "$1" "$2" 2>/dev/null
}

# Imprime o motivo se o banco de teste não está utilizável; não imprime nada se está tudo certo.
motivo_sem_banco() {
    if [ -z "$MYSQL_BIN" ]; then
        echo "cliente mysql/mariadb não instalado — rode sh tests/preparar.sh"; return
    fi
    if ! porta_aberta "$DB_HOST" "$DB_PORTA"; then
        echo "MariaDB não responde em $DB_HOST:$DB_PORTA — rode sh tests/preparar.sh"; return
    fi
    if ! banco_sql -N -e 'SELECT 1' >/dev/null 2>&1; then
        echo "o usuário de teste $DB_USUARIO não entra no MariaDB de $DB_HOST:$DB_PORTA — rode sh tests/preparar.sh"
    fi
}

aleatorio() { od -An -N4 -tx1 /dev/urandom | tr -d ' \n'; }

# Imprime a primeira porta livre da faixa; falha se não houver nenhuma.
porta_livre() {
    _p=$PORTA_INICIO
    while [ "$_p" -le "$PORTA_FIM" ]; do
        if php -r 'exit(@stream_socket_server("tcp://127.0.0.1:" . $argv[1]) ? 0 : 1);' "$_p" 2>/dev/null; then
            echo "$_p"; return 0
        fi
        _p=$((_p + 1))
    done
    return 1
}

# Mata o servidor embutido do PHP de uma porta (o mestre leva os processos de trabalho junto).
parar_servidor() {
    if [ -n "$1" ]; then
        kill "$1" 2>/dev/null
        wait "$1" 2>/dev/null
    fi
    if [ -n "$2" ]; then
        # Reserva: se algum processo da nossa porta sobrou, sai também.
        pkill -f "[p]hp -S 127.0.0.1:$2 " 2>/dev/null
    fi
    return 0
}

# Cria o ambiente isolado de um teste: pasta temporária com cópia do app, banco novo com o seed e servidor próprio.
# Uso: ambiente_criar <nome-curto>. Define AMB_DIR, AMB_DB, AMB_PORTA, AMB_PID; se falhar, devolve 1 e deixa o motivo em AMB_ERRO.
# Não chame dentro de $(...): as variáveis precisam ficar no shell de quem chamou.
# Quem usa deve chamar ambiente_destruir no fim (e em trap).
ambiente_criar() {
    _nome=$(printf '%s' "$1" | tr 'A-Z' 'a-z' | tr -c 'a-z0-9\n' '_' | cut -c1-20)
    AMB_DB="op_t_${_nome}_$(aleatorio)"
    AMB_DIR=$(mktemp -d "$TEMP_BASE/$PREFIXO_TEMP-XXXXXX") || { AMB_ERRO="não consegui criar a pasta temporária"; return 1; }
    cp -R "$RAIZ/public" "$RAIZ/src" "$RAIZ/views" "$RAIZ/database" "$AMB_DIR/" || { AMB_ERRO="não consegui copiar o app"; return 1; }
    mkdir -p "$AMB_DIR/storage" "$AMB_DIR/tmp"
    if ! _erro=$(banco_sql -e "CREATE DATABASE \`$AMB_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" 2>&1); then
        AMB_DB=""
        AMB_ERRO="não consegui criar o banco de teste: $_erro"; return 1
    fi
    # O servidor sobe por um script na pasta do ambiente: o runner o usa e os testes que precisam derrubar e religar
    # o servidor (TESTE_SERVIR) usam o mesmo, com as mesmas variáveis.
    cat >"$AMB_DIR/servir.sh" <<EOS
cd "$AMB_DIR" || exit 1
export APP_USUARIOS='Fabio:senha-forte-123:admin,Joao:joao1234:motorista,Maria:maria1234:operador,Pedro:pedro1234:ambos,Ana:ana12345:motorista'
export APP_ADMIN=Fabio APP_DEBUG=1 APP_TZ=America/Cuiaba
export DB_HOST='$DB_HOST' DB_PORT='$DB_PORTA' DB_NAME='$AMB_DB' DB_USER='$DB_USUARIO' DB_PASSWORD='$DB_SENHA'
export PHP_CLI_SERVER_WORKERS=4
exec php -S "127.0.0.1:\$1" -t public public/router.php
EOS
    _tent=0
    while [ "$_tent" -lt 5 ]; do
        _tent=$((_tent + 1))
        AMB_PORTA=$(porta_livre) || { AMB_ERRO="sem porta livre entre $PORTA_INICIO e $PORTA_FIM"; return 1; }
        sh "$AMB_DIR/servir.sh" "$AMB_PORTA" >"$AMB_DIR/servidor.log" 2>&1 &
        AMB_PID=$!
        _i=0; _pronto=0
        while [ "$_i" -lt 80 ]; do
            kill -0 "$AMB_PID" 2>/dev/null || break
            if curl -s --max-time 2 "http://127.0.0.1:$AMB_PORTA/health" 2>/dev/null | grep -q true; then
                _pronto=1; break
            fi
            sleep 0.25
            _i=$((_i + 1))
        done
        if [ "$_pronto" = 1 ]; then break; fi
        parar_servidor "$AMB_PID" "$AMB_PORTA"; AMB_PID=""
    done
    if [ -z "$AMB_PID" ]; then
        AMB_ERRO="o servidor de teste não subiu: $(tail -n 3 "$AMB_DIR/servidor.log" 2>/dev/null | tr '\n' ' ')"; return 1
    fi
    # O primeiro acesso cria as pessoas da lista APP_USUARIOS; só depois entra o seed.
    curl -s --max-time 5 "http://127.0.0.1:$AMB_PORTA/login" >/dev/null 2>&1
    if ! _erro=$(banco_sql "$AMB_DB" <"$TESTES/lib/seed.sql" 2>&1); then
        AMB_ERRO="o seed falhou: $_erro"; return 1
    fi
    return 0
}

# Desfaz tudo que ambiente_criar fez; pode ser chamada mais de uma vez e com o ambiente pela metade.
ambiente_destruir() {
    if [ -n "$AMB_PID" ] || [ -n "$AMB_PORTA" ]; then
        parar_servidor "$AMB_PID" "$AMB_PORTA"
    fi
    case "$AMB_DB" in
        op_t_?*) banco_sql -e "DROP DATABASE IF EXISTS \`$AMB_DB\`" >/dev/null 2>&1 ;;
    esac
    case "$AMB_DIR" in
        */$PREFIXO_TEMP-?*) rm -rf "$AMB_DIR" ;;
    esac
    AMB_DIR=""; AMB_DB=""; AMB_PORTA=""; AMB_PID=""
    return 0
}

# Lista os arquivos do projeto: os versionados mais os novos da pasta tests/ que ainda não entraram no git.
# As skills de terceiros (.agents/ e .claude/skills/) ficam de fora: não são código do sistema.
arquivos_do_projeto() {
    if git -C "$RAIZ" rev-parse --git-dir >/dev/null 2>&1; then
        { git -C "$RAIZ" ls-files; git -C "$RAIZ" ls-files --others --exclude-standard -- tests; } | sort -u
    else
        (cd "$RAIZ" && find . -type f -not -path './.git/*' | sed 's|^\./||' | sort)
    fi | grep -v -e '^\.agents/' -e '^\.claude/skills/'
}
