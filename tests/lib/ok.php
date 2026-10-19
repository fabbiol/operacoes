<?php
// Ajuda mínima dos testes PHP: ok() imprime OK/FALHA e fim() define o código de saída.

$GLOBALS['falhas'] = 0;

function ok(bool $condicao, string $mensagem, string $extra = ''): void
{
    echo ($condicao ? 'OK   ' : 'FALHA') . " $mensagem" . ($extra !== '' ? " :: $extra" : '') . "\n";
    if (!$condicao) {
        $GLOBALS['falhas']++;
    }
}

function fim(): void
{
    echo $GLOBALS['falhas'] ? $GLOBALS['falhas'] . " FALHA(S)\n" : "TUDO OK\n";
    exit($GLOBALS['falhas'] ? 1 : 0);
}
