<?php

declare(strict_types=1);

define('OPERACOES_ROOT', dirname(__DIR__));

require OPERACOES_ROOT . '/src/helpers.php';
require OPERACOES_ROOT . '/src/Db.php';
require OPERACOES_ROOT . '/src/Auth.php';
require OPERACOES_ROOT . '/src/Pessoas.php';
require OPERACOES_ROOT . '/src/Cadastro.php';
require OPERACOES_ROOT . '/src/Operacoes.php';
require OPERACOES_ROOT . '/src/Lavra.php';
require OPERACOES_ROOT . '/src/Dashboard.php';
require OPERACOES_ROOT . '/src/Horimetro.php';
require OPERACOES_ROOT . '/src/Posse.php';
require OPERACOES_ROOT . '/src/Fotos.php';
require OPERACOES_ROOT . '/src/Tablets.php';
require OPERACOES_ROOT . '/src/NotaFiscal.php';
require OPERACOES_ROOT . '/src/Problemas.php';
require OPERACOES_ROOT . '/src/Estoque.php';
require OPERACOES_ROOT . '/src/Britador.php';
require OPERACOES_ROOT . '/src/Pecas.php';

carregar_env(OPERACOES_ROOT . '/.env');

date_default_timezone_set(env_str('APP_TZ', 'America/Cuiaba'));

if (!is_dir(OPERACOES_ROOT . '/storage')) {
    mkdir(OPERACOES_ROOT . '/storage', 0775, true);
}

$debug = env_str('APP_DEBUG') === '1';
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', OPERACOES_ROOT . '/storage/php-error.log');
error_reporting(E_ALL);

if (PHP_SAPI === 'cli') {
    return;
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Content-Type: text/html; charset=utf-8');

// As sessões ficam numa pasta própria para durar o turno inteiro.
// Na pasta padrão do servidor, a limpeza de outros sites apaga a sessão depois de 24 minutos parada.
$sessoes = OPERACOES_ROOT . '/storage/sessoes';
if (!is_dir($sessoes)) {
    mkdir($sessoes, 0700, true);
}
foreach ([OPERACOES_ROOT . '/storage', $sessoes] as $pasta) {
    if (!is_file($pasta . '/.htaccess')) {
        file_put_contents(
            $pasta . '/.htaccess',
            "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n"
        );
    }
}
session_save_path($sessoes);
ini_set('session.gc_maxlifetime', (string) Auth::SESSAO_SEGUNDOS);
ini_set('session.gc_probability', '1');
ini_set('session.gc_divisor', '100');
ini_set('session.use_strict_mode', '1');

session_set_cookie_params([
    'lifetime' => Auth::SESSAO_SEGUNDOS,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Strict',
    'secure' => requisicao_https(),
]);
session_name('operacoes_prospera');
session_start();
Auth::renovar();
