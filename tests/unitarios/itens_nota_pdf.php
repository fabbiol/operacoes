<?php
// @sem-ambiente
// Itens do DANFE em PDF (texto do pdftotext -layout): leiaute antigo e os leiautes com IBS/CBS, de 11/2026. Dados fictícios.
require __DIR__ . '/../lib/ok.php';
require dirname(__DIR__, 2) . '/src/helpers.php';
require dirname(__DIR__, 2) . '/src/NotaFiscal.php';

$itens = (fn (array $linhas) => self::itensDoPdf($linhas))->bindTo(null, NotaFiscal::class);

$antigo = $itens([
    '  CÓDIGO   DESCRIÇÃO DO PRODUT O/SERVIÇO   NCMSH   CST  CFOP  UN  QT D.  VLR. UNIT',
    '2000000000203   PO DE PEDRA COMERCIAL        25171000   020   5101   M3   10,000   88,80   888,00',
    'DADOS ADICIONAIS',
]);
ok($antigo === [['material' => 'PO DE PEDRA COMERCIAL', 'quantidade' => '10.000', 'unidade' => 'M3', 'pedido' => '']], 'leiaute antigo continua lido', json_encode($antigo));

$novo = $itens([
    '         DESCRIÇÃO DO PRODUT O / SERVIÇO                    CST / CFOP    QT D / UN     VLR UNIT        VLR T OT AL',
    '2000000000202 - BRITA 01 COMERCIAL                          CST 020             7,000          158,80       1111,60 ICMS 196,20',
    '                                                            CFOP 5101             M3                                IBS / CBS',
    '                                                                                                                    IS',
    '[NCM 25171000]                                                                                                      IPI 0,00',
    '2000000000210 - PO DE PEDRA                                 CST 020         1.250,500           88,80     111044,40 ICMS 0,00',
    '                                                            CFOP 5101             TON                               IBS / CBS',
    'CALCULO DO ISSQN',
]);
ok(count($novo) === 2, 'leiaute IBS/CBS: um item por produto', json_encode($novo));
ok(($novo[0] ?? null) === ['material' => 'BRITA 01 COMERCIAL', 'quantidade' => '7.000', 'unidade' => 'M3', 'pedido' => ''], 'material, quantidade e unidade da linha do CFOP', json_encode($novo[0] ?? null));
ok(($novo[1]['quantidade'] ?? '') === '1250.500' && ($novo[1]['unidade'] ?? '') === 'TON', 'quantidade com milhar', json_encode($novo[1] ?? null));

// O mesmo leiaute, saído em escada e com pontilhado (nota 7302), e o T separado do resto da palavra.
$escada = $itens([
    '      DESCRIÇÃO DO PRODUT O / SERVIÇO                     CST / CFOP          QT D / UN          VLR UNIT',
    '2000000000202 - BRIT A 01 COMERCIAL        CST . . . 020',
    '                                                     . . . . . . . .13,000',
    '                                                                     . . . . . . . . .158,80',
    '                                                                                       . . . . . .2064,40',
    '                                                                                                 . . . . . ICMS',
    '                            [NCM 25171000] CFOP . 5101',
    '                                                   . . . . . . . . . . [M3]',
    '                                                                        . . . . . . . . . IBS',
    'DADOS ADICIONAIS',
]);
ok($escada === [['material' => 'BRITA 01 COMERCIAL', 'quantidade' => '13.000', 'unidade' => 'M3', 'pedido' => '']], 'leiaute em escada com pontilhado', json_encode($escada));

fim();
