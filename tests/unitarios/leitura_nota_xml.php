<?php
// @sem-ambiente
// Leitura do XML da nota fiscal (NotaFiscal::lerXml): só a regra de unidades e datas, sem banco nem servidor.
require __DIR__ . '/../lib/ok.php';
require dirname(__DIR__, 2) . '/src/helpers.php';
require dirname(__DIR__, 2) . '/src/NotaFiscal.php';

/** Monta um XML mínimo de NF-e. $itens = [[produto, quantidade, unidade], ...]; $ide = tags de data da emissão. */
function nota(array $itens, string $ide): string
{
    $det = '';
    foreach ($itens as $i => [$prod, $q, $u]) {
        $det .= "<det nItem=\"" . ($i + 1) . "\"><prod><xProd>$prod</xProd><qCom>$q</qCom><uCom>$u</uCom></prod></det>";
    }

    return "<?xml version=\"1.0\"?><nfeProc xmlns=\"http://www.portalfiscal.inf.br/nfe\"><NFe><infNFe Id=\"NFe00000000000000000000000000000000000000000000\"><ide><nNF>123</nNF><serie>1</serie>$ide</ide><dest><xNome>Cliente</xNome></dest>$det<total><ICMSTot><vNF>100.00</vNF></ICMSTot></total></infNFe></NFe></nfeProc>";
}

$n = NotaFiscal::lerXml(nota([['BRITA 1', '10.0000', 'TON'], ['BRITA 1', '4.0000', 'M3'], ['BRITA 1', '2.5000', 't']], '<dhEmi>2026-10-15T08:00:00-04:00</dhEmi>'));
ok($n['quantidade'] == 12.5 && $n['unidade'] === 't' && $n['outras_unidades'] === ['M3'], 'soma só as toneladas (TON + t) e avisa o M3', json_encode([$n['quantidade'], $n['unidade'], $n['outras_unidades']]));

$n = NotaFiscal::lerXml(nota([['BRITA 1', '10', 't'], ['PO DE PEDRA', '5', 't'], ['BRITA 1', '2', 't'], ['PO DE PEDRA', '1.5', 'TON'], ['PEDRISCO', '3', 'M3']], '<dEmi>2026-10-14</dEmi>'));
ok($n['material'] === 'BRITA 1' && $n['quantidade'] == 12 && $n['outros_materiais'] === [['material' => 'PO DE PEDRA', 'quantidade' => 6.5]] && $n['outras_unidades'] === ['M3'],
    'nota com mais de um material: o primeiro é o da ordem, os outros somados na mesma unidade, o item em m³ fica de fora', json_encode($n, JSON_UNESCAPED_UNICODE));

$n = NotaFiscal::lerXml(nota([['AREIA', '3', 'SC']], '<dEmi>2026-10-14</dEmi>'));
ok($n['unidade'] === null && $n['unidade_nota'] === 'SC', 'unidade desconhecida fica sem unidade do sistema');
ok($n['emitida_em'] === null && $n['data_emissao'] === '2026-10-14', 'leiaute antigo só com a data: guarda a data', json_encode([$n['emitida_em'], $n['data_emissao']]));
ok($n['pagamento'] === null, 'nota sem o grupo de pagamento fica sem forma');

$comPag = static fn (string $codigos): string => str_replace('<total>', '<pag>' . $codigos . '</pag><total>', nota([['BRITA 1', '1', 't']], '<dEmi>2026-10-14</dEmi>'));
ok(NotaFiscal::lerXml($comPag('<detPag><tPag>17</tPag><vPag>100.00</vPag></detPag>'))['pagamento'] === 'pix', 'tPag 17 é Pix');
ok(NotaFiscal::lerXml($comPag('<detPag><tPag>18</tPag></detPag>'))['pagamento'] === 'transferencia', 'tPag 18 (transferência bancária) é Transferência');
ok(NotaFiscal::lerXml($comPag('<detPag><tPag>04</tPag></detPag>'))['pagamento'] === 'cartao', 'tPag 04 (débito) é Cartão');
ok(NotaFiscal::lerXml($comPag('<detPag><tPag>99</tPag></detPag><detPag><tPag>01</tPag></detPag>'))['pagamento'] === 'dinheiro', 'pula o "outros" e pega a forma conhecida');
ok(NotaFiscal::lerXml($comPag('<detPag><tPag>90</tPag></detPag>'))['pagamento'] === null, '"sem pagamento" fica para escolher');
$duas = NotaFiscal::lerXml($comPag('<detPag><tPag>17</tPag><vPag>1000.00</vPag></detPag><detPag><tPag>03</tPag><vPag>500.00</vPag></detPag><detPag><tPag>04</tPag><vPag>405.60</vPag></detPag>'));
ok($duas['pagamento'] === 'pix' && $duas['pagamentos'] === [['forma' => 'pix', 'valor' => 1000.0], ['forma' => 'cartao', 'valor' => 905.6]], 'nota paga em Pix e cartão (crédito + débito somados): as partes com os valores', json_encode($duas['pagamentos']));
ok(NotaFiscal::lerXml($comPag('<detPag><tPag>17</tPag><vPag>100.00</vPag></detPag>'))['pagamentos'] === [], 'uma forma só: sem partes');
// Venda paga depois (prefeitura): "depósito bancário" (tPag 16) é Transferência; o vencimento das duplicatas é o dia previsto.
$prazo = NotaFiscal::lerXml(str_replace('<total>', '<cobr><dup><nDup>001</nDup><dVenc>2026-11-13</dVenc><vDup>500.00</vDup></dup><dup><nDup>002</nDup><dVenc>2026-11-28</dVenc><vDup>500.00</vDup></dup></cobr><pag><detPag><tPag>16</tPag></detPag></pag><total>', nota([['BRITA 1', '1', 't']], '<dEmi>2026-10-14</dEmi>')));
ok($prazo['pagamento'] === 'transferencia' && $prazo['previsto'] === '2026-11-28', 'depósito bancário é Transferência e o previsto é o último vencimento', json_encode([$prazo['pagamento'], $prazo['previsto']]));
ok(NotaFiscal::lerXml($comPag('<detPag><tPag>17</tPag></detPag>'))['previsto'] === null, 'sem duplicata: pago na hora (sem dia previsto)');

fim();
