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

$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Strict',
    'secure' => $https,
]);
session_name('operacoes_prospera');
session_start();
Auth::renovar();
