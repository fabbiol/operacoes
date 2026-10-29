<?php
// Venda paga depois (prefeitura: transferência em 15 ou 30 dias): o dia previsto do pagamento (nf_previsto), as recusas,
// a correção na ficha e o envio ao Fin Pro.
require __DIR__ . '/../lib/cli.php';

$hoje = date('Y-m-d');
$daqui = fn (string $quanto): string => date('Y-m-d', strtotime($hoje . ' ' . $quanto));
$base = ['data' => date('Y-m-d H:i'), 'cliente' => 'Prefeitura Municipal', 'pedido' => 'P-15', 'nf' => '7070', 'material_id' => '1',
    'quantidade_autorizada' => '10', 'unidade' => 't', 'nf_valor' => '4.000,00', 'nf_pagamento' => 'transferencia'];

ok(str_contains(erro(fn () => Operacoes::criar(array_merge($base, ['nf_previsto' => '13/11/2026']), 'Fabio')), 'dia previsto'), 'data em outro formato: recusa');
ok(str_contains(erro(fn () => Operacoes::criar(array_merge($base, ['nf_previsto' => $daqui('-1 day')]), 'Fabio')), 'antes da data da nota'), 'antes da nota: recusa');
ok(str_contains(erro(fn () => Operacoes::criar(array_merge($base, ['nf_previsto' => $daqui('+13 months')]), 'Fabio')), 'um ano'), 'mais de um ano depois: recusa');
ok((int) $pdo->query("SELECT COUNT(*) FROM fila_carregamentos WHERE nf = '7070'")->fetchColumn() === 0, 'nada foi gravado nas recusas');

$id = Operacoes::criar(array_merge($base, ['nf_previsto' => $daqui('+15 days')]), 'Fabio');
ok(Operacoes::buscar($id)['nf_previsto'] === $daqui('+15 days'), 'grava o dia previsto', (string) Operacoes::buscar($id)['nf_previsto']);
$campos = (fn (array $o) => self::campos($o))->bindTo(null, FinPro::class);
ok($campos(Operacoes::buscar($id))['previsto'] === $daqui('+15 days'), 'vai ao Fin Pro com o dia previsto');
$avista = Operacoes::criar(array_merge($base, ['nf' => '7071', 'nf_pagamento' => 'pix']), 'Fabio');
ok(Operacoes::buscar($avista)['nf_previsto'] === null && $campos(Operacoes::buscar($avista))['previsto'] === '', 'sem o campo: pago na hora');

// Corrigir na ficha: troca, mantém quando o formulário não traz o campo, e tira.
$corrigir = fn (array $troca) => erro(fn () => Operacoes::corrigirPagamento($id, array_merge(['nf_valor' => '4.000,00', 'nf_pagamento' => 'transferencia', 'tem_pagamentos' => '1'], $troca)));
$msg = $corrigir(['nf_previsto' => $daqui('+30 days')]);
ok($msg === '' && Operacoes::buscar($id)['nf_previsto'] === $daqui('+30 days'), 'corrigir troca o dia previsto', $msg);
$msg = $corrigir([]);
ok($msg === '' && Operacoes::buscar($id)['nf_previsto'] === $daqui('+30 days'), 'formulário sem o campo mantém o dia', $msg);
$msg = $corrigir(['nf_previsto' => '']);
ok($msg === '' && Operacoes::buscar($id)['nf_previsto'] === null, 'campo vazio: volta a ser pago na hora', $msg);
fim();
