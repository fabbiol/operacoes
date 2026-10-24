<?php
// @sem-ambiente
// parse_quantidade: o que se digita na quantidade autorizada e na carregada, sem banco nem servidor.
require __DIR__ . '/../lib/ok.php';
require dirname(__DIR__, 2) . '/src/helpers.php';

$le = static function (string $texto): string {
    try {
        return parse_quantidade($texto);
    } catch (InvalidArgumentException) {
        return 'erro';
    }
};

ok($le('1.500') === '1500.00', '"1.500" é mil e quinhentos', $le('1.500'));
ok($le('12.000') === '12000.00' && $le('1.234.567') === '1234567.00', 'milhares com ponto', $le('12.000'));
ok($le('1.500,50') === '1500.50', '"1.500,50" com milhar e vírgula', $le('1.500,50'));
ok($le('2,5') === '2.50' && $le('2.5') === '2.50' && $le('2.25') === '2.25', 'decimal com vírgula ou ponto', $le('2.5'));
ok($le('30') === '30.00', 'número inteiro', $le('30'));
ok($le('0.500') === 'erro' && $le('1.5000') === 'erro' && $le('2,505') === 'erro', 'mais de 2 casas decimais é recusado, sem arredondar escondido', $le('0.500'));
ok($le('') === 'erro' && $le('0') === 'erro' && $le('abc') === 'erro', 'vazio, zero ou texto é recusado');

fim();
