<?php
// Venda com empenho (prefeitura): a carga sai sem a nota (nota_depois), a nota é lançada depois (Operacoes::lancarNota)
// na viagem 1 e nas outras viagens do fracionado; só então vai ao Fin Pro. O Painel lista as que esperam a nota.
require __DIR__ . '/../lib/cli.php';

$hoje = date('Y-m-d');
$base = ['data' => date('Y-m-d H:i'), 'cliente' => 'Prefeitura Municipal', 'pedido' => 'EMP-2026/15', 'nf' => '', 'material_id' => '1',
    'quantidade_autorizada' => '10', 'unidade' => 't', 'nf_pagamento' => 'transferencia'];

ok(str_contains(erro(fn () => Operacoes::criar($base, 'Fabio')), 'nota fiscal'), 'sem a nota e sem "nota sai depois": recusa');
$id = Operacoes::criar($base + ['nota_depois' => '1', 'liberar' => '1'], 'Fabio');
$ordem = Operacoes::buscar($id);
ok($ordem['nf'] === '' && $ordem['status'] === 'liberado', 'com empenho: lança sem a nota e libera para o pátio', json_encode([$ordem['nf'], $ordem['status']]));
ok(nf_texto($ordem) === 'NF a emitir (empenho)', 'a fila mostra "NF a emitir"');
ok(!FinPro::elegivel($ordem), 'sem a nota não vai ao Fin Pro');
ok(in_array($id, array_map('intval', array_column(Operacoes::aguardandoNota(), 'id')), true), 'o Painel lista a ordem que espera a nota');
$outra = Operacoes::criar(['pedido' => 'EMP-2026/16', 'nota_depois' => '1'] + $base, 'Fabio');
ok(erro(fn () => Operacoes::liberar($outra, 'Fabio')) === '', 'liberar depois também aceita a ordem sem a nota');

// Fracionado: a viagem seguinte nasce sem a nota e recebe a nota junto com a viagem 1.
$pdo->exec("UPDATE fila_carregamentos SET fracionado = 1 WHERE id = $id");
$pdo->exec("INSERT INTO fila_carregamentos (ordem, data, cliente, pedido, nf, material_id, quantidade_autorizada, unidade, fracionado, ordem_principal_id, viagem, status)
            VALUES ('CAR-900', NOW(), 'Prefeitura Municipal', 'EMP-2026/15', '', 1, 4, 't', 1, $id, 2, 'saldo')");
$viagem2 = (int) $pdo->lastInsertId();

$nota = ['nf' => '555', 'nf_emitida_em' => $hoje . 'T09:30', 'nf_valor' => '4.000,00', 'nf_previsto' => date('Y-m-d', strtotime($hoje . ' +30 days'))];
ok(str_contains(erro(fn () => Operacoes::lancarNota($id, ['nf' => ''] + $nota)), 'número da nota'), 'sem o número: recusa');
ok(str_contains(erro(fn () => Operacoes::lancarNota($id, ['nf_emitida_em' => ''] + $nota)), 'quando a nota foi emitida'), 'sem a data: recusa');
ok(str_contains(erro(fn () => Operacoes::lancarNota($id, ['nf_valor' => ''] + $nota)), 'valor da nota'), 'sem o valor: recusa');
ok(str_contains(erro(fn () => Operacoes::lancarNota($id, ['nf_previsto' => date('Y-m-d', strtotime($hoje . ' -1 day'))] + $nota)), 'antes da data da nota'), 'previsto antes da nota: recusa');
ok(Operacoes::buscar($id)['nf'] === '', 'nada foi gravado nas recusas');

ok(Operacoes::lancarNota($viagem2, $nota) === $id, 'lançar pela viagem 2 grava na viagem 1');
$ordem = Operacoes::buscar($id);
ok($ordem['nf'] === '555' && $ordem['nf_valor'] === '4000.00' && $ordem['nf_emitida_data'] === $hoje && $ordem['nf_previsto'] === $nota['nf_previsto'],
    'grava número, valor, dia da nota e pagamento previsto', json_encode([$ordem['nf'], $ordem['nf_valor'], $ordem['nf_emitida_data'], $ordem['nf_previsto']]));
ok(Operacoes::buscar($viagem2)['nf'] === '555' && Operacoes::buscar($viagem2)['nf_valor'] === null, 'a viagem 2 fica com o número; o valor só na viagem 1');
ok(!in_array($id, array_map('intval', array_column(Operacoes::aguardandoNota(), 'id')), true), 'sai da lista do Painel');
$campos = (fn (array $o) => self::campos($o))->bindTo(null, FinPro::class);
ok(FinPro::elegivel($ordem) && $campos($ordem)['nota'] === '555' && $campos($ordem)['previsto'] === $nota['nf_previsto'], 'com a nota, vai ao Fin Pro como a prazo');
ok(Operacoes::vendas($hoje)['valor'] === 4000.0, 'o vendido conta no dia da nota', json_encode(Operacoes::vendas($hoje)['valor']));

ok(str_contains(erro(fn () => Operacoes::lancarNota($id, $nota)), 'já tem nota fiscal'), 'lançar de novo: recusa');
ok(str_contains(erro(fn () => Operacoes::lancarNota($outra, $nota)), 'CAR-'), 'a mesma nota em outra ordem: recusa');
fim();
