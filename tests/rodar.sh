#!/bin/sh
# Roda os testes automáticos da Próspera Operações.
#
#   sh tests/rodar.sh rapido            sintaxe + testes PHP sem banco (não precisa de MariaDB nem de navegador)
#   sh tests/rodar.sh                   tudo: rápido + integração (banco temporário) + telas (Playwright)
#   sh tests/rodar.sh integracao        só a integração
#   sh tests/rodar.sh telas patio       só as telas cujo nome tem "patio" (qualquer grupo aceita filtros)
#   sh tests/rodar.sh limpar            apaga restos de uma rodada interrompida (bancos op_t_*, servidores, pastas)
#
# Cada teste de integração e de tela roda no seu próprio ambiente: pasta temporária com cópia do app,
# banco op_t_<nome>_<sorteio> novo e servidor php -S numa porta livre de 8170 a 8199. Tudo é apagado no fim.
# Código de saída: 0 se nada falhou (suites puladas aparecem no resumo); 1 se algum teste FALHOU.
# Com TESTE_ESTRITO=1, suite PULADA inteira também vira erro (útil em CI).

TESTES=$(cd "$(dirname "$0")" && pwd)
RAIZ=$(dirname "$TESTES")
. "$TESTES/lib/comum.sh"

MODO=tudo
case "${1:-}" in
    rapido|tudo|integracao|telas|limpar) MODO=$1; shift ;;
    ajuda|-h|--help) sed -n '2,13p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
esac
FILTROS="$*"

TEMPO_MAXIMO=${TESTE_TEMPO_MAXIMO:-420}
N_OK=0; N_FALHA=0; N_PULADO=0; N_PARCIAL=0
RESUMO_FALHAS=""; RESUMO_PULADOS=""; RESUMO_PARCIAIS=""
SAIDAS=""

limpar_tudo() {
    _n=0
    if [ -n "$MYSQL_BIN" ] && [ -z "$(motivo_sem_banco)" ]; then
        for _b in $(banco_sql -N -e "SHOW DATABASES LIKE 'op\\_t\\_%'" 2>/dev/null); do
            case "$_b" in op_t_?*) banco_sql -e "DROP DATABASE IF EXISTS \`$_b\`" && echo "banco apagado: $_b"; _n=$((_n + 1)) ;; esac
        done
    fi
    pkill -f "[p]hp -S 127.0.0.1:81[7-9][0-9] " 2>/dev/null && echo "servidores php -S de 8170 a 8199 encerrados"
    for _d in "$TEMP_BASE"/$PREFIXO_TEMP-*; do
        [ -d "$_d" ] && rm -rf "$_d" && echo "pasta apagada: $_d"
    done
    echo "limpeza concluída"
}

if [ "$MODO" = limpar ]; then limpar_tudo; exit 0; fi

SAIDAS=$(mktemp -d "$TEMP_BASE/$PREFIXO_TEMP-saidas-XXXXXX") || { echo "não consegui criar a pasta temporária"; exit 1; }
encerrar() { ambiente_destruir; rm -rf "$SAIDAS"; }
trap encerrar EXIT
trap 'echo; echo "interrompido"; exit 130' INT TERM

# --- Resultado -----------------------------------------------------------------------------------
registrar() { # $1 = OK|FALHA|PULADO, $2 = nome, $3 = detalhe
    case "$1" in
        OK) N_OK=$((N_OK + 1)) ;;
        FALHA) N_FALHA=$((N_FALHA + 1)); RESUMO_FALHAS="$RESUMO_FALHAS  $2 — $3
" ;;
        PULADO) N_PULADO=$((N_PULADO + 1)); RESUMO_PULADOS="$RESUMO_PULADOS  $2 — $3
" ;;
    esac
    printf '  %-7s %-26s %s\n' "$1" "$2" "$3"
}

passa_no_filtro() { # $1 = grupo/nome
    [ -z "$FILTROS" ] && return 0
    for _f in $FILTROS; do
        case "$1" in *"$_f"*) return 0 ;; esac
    done
    return 1
}

cabecalho() { echo; echo "[$1]"; }

# --- Pré-requisitos (cada um é conferido uma vez só; sem $(...) para o resultado ficar guardado) -------
MOTIVO_BANCO=""; BANCO_CONFERIDO=0
conferir_banco() {
    [ "$BANCO_CONFERIDO" = 1 ] && return 0
    BANCO_CONFERIDO=1
    MOTIVO_BANCO=$(motivo_sem_banco)
    if [ -z "$MOTIVO_BANCO" ] && ! command -v curl >/dev/null 2>&1; then
        MOTIVO_BANCO="curl não instalado — rode sh tests/preparar.sh"
    fi
}
MOTIVO_NODE=""; NODE_CONFERIDO=0
conferir_node() {
    [ "$NODE_CONFERIDO" = 1 ] && return 0
    NODE_CONFERIDO=1
    command -v node >/dev/null 2>&1 || MOTIVO_NODE="node não instalado (precisa do Node 18 ou mais novo)"
}
MOTIVO_PW=""; PW_CONFERIDO=0
conferir_playwright() {
    [ "$PW_CONFERIDO" = 1 ] && return 0
    PW_CONFERIDO=1
    conferir_node
    if [ -n "$MOTIVO_NODE" ]; then MOTIVO_PW=$MOTIVO_NODE; return 0; fi
    MOTIVO_PW=$(node "$TESTES/lib/verificar_playwright.js" 2>&1 >/dev/null | head -n 3 | tr '\n' ' ')
}

# --- Sintaxe -----------------------------------------------------------------------------------------
checar_sintaxe() { # $1 = rótulo, $2 = extensão, $3... = comando que recebe o arquivo
    _rotulo=$1; _ext=$2; shift 2
    _lista="$SAIDAS/lista-$_ext.txt"
    arquivos_do_projeto | grep "\\.$_ext\$" >"$_lista"
    _total=0; _ruins=0; _msg=""
    while IFS= read -r _arq; do
        [ -f "$RAIZ/$_arq" ] || continue
        _total=$((_total + 1))
        if ! _erro=$(cd "$RAIZ" && "$@" "$_arq" 2>&1); then
            _erro=$(printf '%s' "$_erro" | head -n 2 | tr '\n' ' ')
            _ruins=$((_ruins + 1)); _msg="$_msg$_arq: $_erro; "
        fi
    done <"$_lista"
    if [ "$_ruins" -gt 0 ]; then
        registrar FALHA "sintaxe $_rotulo" "$_ruins de $_total arquivo(s): $_msg"
    elif [ "$_total" -eq 0 ]; then
        registrar PULADO "sintaxe $_rotulo" "nenhum arquivo .$_ext encontrado"
    else
        registrar OK "sintaxe $_rotulo" "$_total arquivo(s)"
    fi
}

grupo_sintaxe() {
    passa_no_filtro "sintaxe" || return 0
    cabecalho "sintaxe"
    if command -v php >/dev/null 2>&1; then checar_sintaxe "php -l" php php -l
    else registrar PULADO "sintaxe php -l" "php não instalado"; fi
    conferir_node
    if [ -z "$MOTIVO_NODE" ]; then checar_sintaxe "node --check" js node --check
    else registrar PULADO "sintaxe node --check" "$MOTIVO_NODE"; fi
    checar_sintaxe "sh -n" sh sh -n
}

# --- Execução de um teste ---------------------------------------------------------------------------
# $1 = grupo, $2 = arquivo do teste. Cria o ambiente (se o teste pedir), roda, mostra o resultado e limpa.
rodar_teste() {
    _grupo=$1; _arq=$2
    _base=$(basename "$_arq"); _nome=${_base%.*}; _ext=${_base##*.}
    _id="$_grupo/$_nome"
    passa_no_filtro "$_id" || return 0

    _precisa_amb=1
    head -n 6 "$_arq" | grep -q '@sem-ambiente' && _precisa_amb=0
    _precisa_pw=0
    [ "$_grupo" = telas ] && _precisa_pw=1

    if [ "$_ext" = js ]; then
        conferir_node
        if [ -n "$MOTIVO_NODE" ]; then registrar PULADO "$_id" "$MOTIVO_NODE"; return 0; fi
    fi
    if [ "$_precisa_pw" = 1 ]; then
        conferir_playwright
        if [ -n "$MOTIVO_PW" ]; then registrar PULADO "$_id" "Playwright indisponível: $MOTIVO_PW"; return 0; fi
    fi
    if [ "$_precisa_amb" = 1 ]; then
        conferir_banco
        if [ -n "$MOTIVO_BANCO" ]; then registrar PULADO "$_id" "$MOTIVO_BANCO"; return 0; fi
    fi

    _saida="$SAIDAS/$_grupo-$_nome.log"
    _inicio=$(date +%s)
    if [ "$_precisa_amb" = 1 ]; then
        if ! ambiente_criar "$_nome"; then
            _erro=$AMB_ERRO
            ambiente_destruir
            registrar FALHA "$_id" "não montou o ambiente: $_erro"; return 0
        fi
        export TESTE_BASE="http://127.0.0.1:$AMB_PORTA" TESTE_PORTA="$AMB_PORTA" TESTE_DB="$AMB_DB" \
            TESTE_AMBIENTE="$AMB_DIR" TESTE_SESSOES="$AMB_DIR/storage/sessoes" TESTE_TMP="$AMB_DIR/tmp"
    else
        unset TESTE_BASE TESTE_PORTA TESTE_DB TESTE_AMBIENTE TESTE_SESSOES
        export TESTE_TMP="$SAIDAS"
    fi
    export TESTE_NOTAS="$TESTES/notas"
    case "$_ext" in
        php) _cmd="php" ;;
        js) _cmd="node" ;;
        *) _cmd="sh" ;;
    esac
    if command -v timeout >/dev/null 2>&1; then
        timeout "$TEMPO_MAXIMO" "$_cmd" "$_arq" >"$_saida" 2>&1
    else
        "$_cmd" "$_arq" >"$_saida" 2>&1
    fi
    _cod=$?
    [ "$_precisa_amb" = 1 ] && ambiente_destruir
    _seg=$(( $(date +%s) - _inicio ))

    _parciais=$(grep -c '^PULADO' "$_saida")
    _checks=$(grep -c '^OK ' "$_saida")
    if [ "$_cod" -eq 0 ]; then
        if [ "$_parciais" -gt 0 ]; then
            registrar OK "$_id" "${_checks} verificações, ${_seg}s — com parte PULADA (veja o resumo)"
            N_PARCIAL=$((N_PARCIAL + 1))
            RESUMO_PARCIAIS="$RESUMO_PARCIAIS$(grep '^PULADO' "$_saida" | sed "s|^|  $_id — |")
"
        else
            registrar OK "$_id" "${_checks} verificações, ${_seg}s"
        fi
        [ -n "${TESTE_VERBOSO:-}" ] && cat "$_saida"
    elif [ "$_cod" -eq 77 ]; then
        registrar PULADO "$_id" "$(grep '^PULADO' "$_saida" | head -n 1 | sed 's/^PULADO *//')"
    else
        if [ "$_cod" -eq 124 ]; then _motivo="passou de ${TEMPO_MAXIMO}s e foi interrompido"
        else _motivo="$(grep -c '^FALHA' "$_saida") verificação(ões) falharam (código $_cod), ${_seg}s"; fi
        registrar FALHA "$_id" "$_motivo"
        # Mostra o que deu errado: as linhas FALHA e o final da saída.
        sed -n '/^FALHA/p' "$_saida" | head -n 20 | sed 's/^/            /'
        tail -n 8 "$_saida" | sed 's/^/            | /'
    fi
    return 0
}

grupo_unitarios() {
    cabecalho "unitarios (PHP, sem banco)"
    if ! command -v php >/dev/null 2>&1; then registrar PULADO "unitarios" "php não instalado"; return; fi
    for _a in "$TESTES"/unitarios/*.php; do [ -f "$_a" ] && rodar_teste unitarios "$_a"; done
}

grupo_integracao() {
    cabecalho "integracao (banco temporario)"
    for _a in "$TESTES"/integracao/*.php "$TESTES"/integracao/*.js; do [ -f "$_a" ] && rodar_teste integracao "$_a"; done
}

grupo_telas() {
    cabecalho "telas (navegador, Playwright)"
    for _a in "$TESTES"/telas/*.js; do [ -f "$_a" ] && rodar_teste telas "$_a"; done
}

# --- Rodada ------------------------------------------------------------------------------------------
INICIO_GERAL=$(date +%s)
echo "Testes da Próspera Operações — modo: $MODO${FILTROS:+ — filtro: $FILTROS}"
case "$MODO" in
    rapido) grupo_sintaxe; grupo_unitarios ;;
    integracao) grupo_integracao ;;
    telas) grupo_telas ;;
    tudo) grupo_sintaxe; grupo_unitarios; grupo_integracao; grupo_telas ;;
esac

TOTAL_SEG=$(( $(date +%s) - INICIO_GERAL ))
echo
echo "=== Resumo (${TOTAL_SEG}s) ==="
echo "  OK: $N_OK   FALHA: $N_FALHA   PULADO: $N_PULADO   (OK com parte pulada: $N_PARCIAL)"
if [ -n "$RESUMO_FALHAS" ]; then echo; echo "FALHARAM:"; printf '%s' "$RESUMO_FALHAS"; fi
if [ -n "$RESUMO_PULADOS" ]; then echo; echo "PULADOS (não foram executados — não conta como passou):"; printf '%s' "$RESUMO_PULADOS"; fi
if [ -n "$RESUMO_PARCIAIS" ]; then echo; echo "PARTES PULADAS dentro de testes que passaram:"; printf '%s' "$RESUMO_PARCIAIS"; fi

if [ "$N_FALHA" -gt 0 ]; then echo; echo "RESULTADO: FALHOU"; exit 1; fi
if [ "${TESTE_ESTRITO:-0}" = 1 ] && [ "$N_PULADO" -gt 0 ]; then echo; echo "RESULTADO: FALHOU (TESTE_ESTRITO=1 e há suites puladas)"; exit 1; fi
if [ "$N_PULADO" -gt 0 ]; then echo; echo "RESULTADO: passou, mas com suites PULADAS"; exit 0; fi
echo; echo "RESULTADO: passou"
exit 0
