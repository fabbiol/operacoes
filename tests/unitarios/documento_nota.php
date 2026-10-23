<?php
// @sem-ambiente
// CNPJ/CPF do cliente na nota (NotaFiscal::documento e a leitura do XML e do PDF), sem banco nem servidor.
// As notas de tests/notas/ são sintéticas: nota1 com CNPJ válido, nota2 com CPF válido, nota3 com CNPJ de dígito errado.
require __DIR__ . '/../lib/ok.php';
require dirname(__DIR__, 2) . '/src/helpers.php';
require dirname(__DIR__, 2) . '/src/NotaFiscal.php';

$notas = dirname(__DIR__) . '/notas/';

// Dígitos verificadores.
ok(NotaFiscal::documento('98.765.432/0001-98') === '98765432000198', 'CNPJ com pontuação vira só os números');
ok(NotaFiscal::documento('529.982.247-25') === '52998224725', 'CPF com pontuação vira só os números');
ok(NotaFiscal::documento('98765432000110') === null, 'CNPJ com dígito verificador errado fica vazio');
ok(NotaFiscal::documento('52998224724') === null, 'CPF com dígito verificador errado fica vazio');
ok(NotaFiscal::documento('11111111111') === null && NotaFiscal::documento('00000000000000') === null, 'números todos iguais ficam vazios');
ok(NotaFiscal::documento('') === null && NotaFiscal::documento('1234567') === null, 'vazio ou tamanho errado fica vazio');
ok(NotaFiscal::formatarDocumento('98765432000198') === '98.765.432/0001-98' && NotaFiscal::formatarDocumento('52998224725') === '529.982.247-25', 'formatação de CNPJ e CPF');

// XML.
$xml = static fn (string $arquivo) => NotaFiscal::lerXml((string) file_get_contents($notas . $arquivo))['documento'];
ok($xml('nota1.xml') === '98765432000198', 'XML: dest/CNPJ', var_export($xml('nota1.xml'), true));
ok($xml('nota2.xml') === '52998224725', 'XML: dest/CPF', var_export($xml('nota2.xml'), true));
ok($xml('nota3.xml') === null, 'XML: dest/CNPJ com dígito errado fica vazio', var_export($xml('nota3.xml'), true));
ok($xml('nota_so_data.xml') === null, 'XML sem destinatário fica sem documento');

// PDF (DANFE): precisa do pdftotext.
if (trim((string) shell_exec('command -v pdftotext 2>/dev/null')) === '') {
    pular_parte('leitura do CNPJ/CPF no PDF: pdftotext (poppler-utils) não instalado — rode sh tests/preparar.sh');
} else {
    $pdf = static fn (string $arquivo) => NotaFiscal::lerPdf((string) file_get_contents($notas . $arquivo))['documento'];
    ok($pdf('nota1.pdf') === '98765432000198', 'PDF: CNPJ do destinatário', var_export($pdf('nota1.pdf'), true));
    ok($pdf('nota2.pdf') === '52998224725', 'PDF: CPF do destinatário', var_export($pdf('nota2.pdf'), true));
    ok($pdf('nota3.pdf') === null, 'PDF: CNPJ com dígito errado fica vazio', var_export($pdf('nota3.pdf'), true));
}

fim();
