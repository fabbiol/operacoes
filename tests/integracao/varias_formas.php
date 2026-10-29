<?php
// Nota paga em mais de uma forma (Operacoes::criar/corrigirPagamento com "pagamentos"): o valor de cada forma, a soma
// igual ao valor da nota, o vendido por forma, o envio ao Fin Pro e a correção do valor sozinho bloqueada.
require __DIR__ . '/../lib/cli.php';

$base = ['data' => date('Y-m-d H:i'), 'cliente' => 'Obra Sul', 'pedido' => 'P-90', 'nf' => '9090', 'material_id' => '1',
    'quantidade_autorizada' => '10', 'unidade' => 't', 'nf_valor' => '1.905,60', 'nf_pagamento' => 'pix', 'tem_pagamentos' => '1'];
$com = fn (array $linhas, array $troca = []) => array_merge($base, ['pagamentos' => $linhas], $troca);
$pixCartao = [['valor' => '1.000,00'], ['forma' => 'cartao', 'valor' => '905,60']];

ok(str_contains(erro(fn () => Operacoes::criar($com([['valor' => '1.000,00'], ['forma' => 'cartao', 'valor' => '900,00']]), 'Fabio')), 'não bate'), 'soma diferente do valor da nota: recusa');
ok(str_contains(erro(fn () => Operacoes::criar($com([['valor' => '1.000,00'], ['forma' => 'pix', 'valor' => '905,60']]), 'Fabio')), 'duas vezes'), 'a mesma forma duas vezes: recusa');
ok(str_contains(erro(fn () => Operacoes::criar($com([['valor' => ''], ['forma' => 'cartao', 'valor' => '905,60']]), 'Fabio')), 'quanto foi pago em Pix'), 'sem o valor da primeira forma: recusa');
ok(str_contains(erro(fn () => Operacoes::criar($com([['valor' => '1.000,00'], ['forma' => 'cartao', 'valor' => '']]), 'Fabio')), 'quanto foi pago em Cartão'), 'forma sem valor: recusa');
ok(str_contains(erro(fn () => Operacoes::criar($com($pixCartao, ['nf_valor' => '']), 'Fabio')), 'Preencha o valor da nota'), 'sem o valor da nota: recusa');
ok((int) $pdo->query("SELECT COUNT(*) FROM fila_carregamentos WHERE nf = '9090'")->fetchColumn() === 0, 'nada foi gravado nas recusas');

$id = Operacoes::criar($com($pixCartao), 'Fabio');
$ordem = Operacoes::buscar($id);
ok(pagamentos_texto($ordem) === 'Pix R$ 1.000,00 + Cartão R$ 905,60', 'grava as duas formas com os valores', pagamentos_texto($ordem));
$vendas = Operacoes::vendas(date('Y-m-d'));
ok(($vendas['por_forma']['pix'] ?? 0) === 1000.0 && ($vendas['por_forma']['cartao'] ?? 0) === 905.6 && $vendas['valor'] === 1905.6, 'o vendido conta cada parte na sua forma', json_encode($vendas));

// Fin Pro: vai porque tem parte em Pix, mesmo com o cartão primeiro.
$id2 = Operacoes::criar($com([['valor' => '905,60'], ['forma' => 'pix', 'valor' => '1.000,00']], ['nf' => '9091', 'nf_pagamento' => 'cartao']), 'Fabio');
$campos = (fn (array $o) => self::campos($o))->bindTo(null, FinPro::class);
$enviado = $campos(Operacoes::buscar($id2));
ok(FinPro::elegivel(Operacoes::buscar($id2)) && $enviado['forma'] === 'pix' && json_decode($enviado['pagamentos'], true) === [['forma' => 'cartao', 'valor' => '905.60'], ['forma' => 'pix', 'valor' => '1000.00']],
    'vai ao Fin Pro com a forma Pix e as partes', json_encode($enviado, JSON_UNESCAPED_UNICODE));
$id3 = Operacoes::criar($com([['valor' => '905,60'], ['forma' => 'dinheiro', 'valor' => '1.000,00']], ['nf' => '9092', 'nf_pagamento' => 'cartao']), 'Fabio');
ok(!FinPro::elegivel(Operacoes::buscar($id3)), 'cartão e dinheiro, sem Pix: não vai ao Fin Pro');
ok($campos(Operacoes::buscar(1))['pagamentos'] === '', 'nota de uma forma só: sem as partes');

// Corrigir na ficha: valor sozinho (Controle) bloqueado; o formulário com as formas troca ou tira as partes.
ok(str_contains(erro(fn () => Operacoes::definirValor($id, '2000')), 'mais de uma forma'), 'corrigir só o valor de nota com várias formas: recusa');
$msg = erro(fn () => Operacoes::corrigirPagamento($id, ['nf_valor' => '1905,60', 'nf_pagamento' => 'pix', 'tem_pagamentos' => '1',
    'pagamentos' => [['valor' => '1.500,00'], ['forma' => 'dinheiro', 'valor' => '405,60']]]));
ok($msg === '' && pagamentos_texto(Operacoes::buscar($id)) === 'Pix R$ 1.500,00 + Dinheiro R$ 405,60', 'corrigir troca as formas e os valores', $msg);
$msg = erro(fn () => Operacoes::corrigirPagamento($id, ['nf_valor' => '1905,60', 'nf_pagamento' => 'pix', 'tem_pagamentos' => '1', 'pagamentos' => [['valor' => '']]]));
ok($msg === '' && pagamentos_texto(Operacoes::buscar($id)) === 'Pix' && Operacoes::pagamentosDaOrdem($id) === [], 'sem outra forma: volta a ser nota de uma forma só', $msg);
fim();
