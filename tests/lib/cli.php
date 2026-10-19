<?php
// Liga o app do ambiente temporário (pasta e banco criados por tests/rodar.sh) para testes PHP de integração.
// Uso no teste: require __DIR__ . '/../lib/cli.php';  (depois use $pdo, ok(), erro() e fim())

require __DIR__ . '/ok.php';

$ambiente = getenv('TESTE_AMBIENTE');
$banco = getenv('TESTE_DB');
if (!$ambiente || !$banco) {
    fwrite(STDERR, "Este teste precisa do ambiente do runner: rode sh tests/rodar.sh integracao\n");
    exit(2);
}
if (!str_starts_with($banco, 'op_t_')) {
    fwrite(STDERR, "Banco recusado ($banco): os testes só mexem em bancos op_t_*.\n");
    exit(2);
}

putenv('DB_HOST=' . (getenv('TESTE_DB_HOST') ?: '127.0.0.1'));
putenv('DB_PORT=' . (getenv('TESTE_DB_PORTA') ?: '3307'));
putenv('DB_NAME=' . $banco);
putenv('DB_USER=' . (getenv('TESTE_DB_USUARIO') ?: 'operacoes_teste'));
putenv('DB_PASSWORD=' . (getenv('TESTE_DB_SENHA') ?: 'teste_local'));
putenv('APP_TZ=America/Cuiaba');

require $ambiente . '/src/bootstrap.php';

$pdo = Db::pdo();

/** Roda a ação e devolve a mensagem da InvalidArgumentException (texto vazio se não houve erro). */
function erro(callable $acao): string
{
    try {
        $acao();

        return '';
    } catch (InvalidArgumentException $e) {
        return $e->getMessage();
    }
}
