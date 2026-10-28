<?php
// Carga com mais de um material (Operacoes::criar/atualizar com "outros"): grava até 5, recusa repetido, fracionado
// e linha incompleta; soma no total e no carregado do dia; manda os materiais juntos ao Fin Pro.
require __DIR__ . '/../lib/cli.php';

$pdo->exec("INSERT INTO materiais (material) VALUES ('Areia'), ('Pedrisco'), ('Brita 0'), ('Rachão')");
$id = fn (string $nome) => (int) $pdo->query("SELECT id FROM materiais WHERE material = " . $pdo->quote($nome))->fetchColumn();
$base = ['data' => date('Y-m-d H:i'), 'cliente' => 'Obra Delta', 'pedido' => 'P-50', 'nf' => '5050', 'material_id' => $id('Brita 1'),
    'quantidade_autorizada' => '10', 'unidade' => 't', 'tem_outros' => '1', 'tem_fracionado' => '1'];
$com = fn (array $outros, array $extra = []) => $base + ['outros' => $outros] + $extra;

ok(str_contains(erro(fn () => Operacoes::criar($com([['material_id' => $id('Brita 1'), 'quantidade' => '5']]), 'Fabio')), 'duas vezes'), 'o mesmo material duas vezes: recusa');
ok(str_contains(erro(fn () => Operacoes::criar($com([['material_id' => $id('Areia'), 'quantidade' => '']]), 'Fabio')), 'Informe a quantidade'), 'material sem quantidade: recusa');
ok(str_contains(erro(fn () => Operacoes::criar($com([['material_id' => '', 'quantidade' => '3']]), 'Fabio')), 'Escolha o material'), 'quantidade sem material: recusa');
ok(str_contains(erro(fn () => Operacoes::criar($com([['material_id' => $id('Areia'), 'quantidade' => '3']], ['fracionado' => '1']), 'Fabio')), 'sai inteira'), 'mais de um material e fracionado: recusa');
$cinco = array_map(fn (string $nome) => ['material_id' => $id($nome), 'quantidade' => '1'], ['Pó de pedra', 'Areia', 'Pedrisco', 'Brita 0', 'Rachão']);
ok(str_contains(erro(fn () => Operacoes::criar($com($cinco), 'Fabio')), 'no máximo 5'), 'seis materiais: recusa');
ok((int) $pdo->query("SELECT COUNT(*) FROM fila_carregamentos WHERE nf = '5050'")->fetchColumn() === 0, 'nada foi gravado nas recusas');

$ordemId = Operacoes::criar($com([['material_id' => $id('Pó de pedra'), 'quantidade' => '5'], ['material_id' => '', 'quantidade' => ''], ['material_id' => $id('Areia'), 'quantidade' => '2,5']]), 'Fabio');
$ordem = Operacoes::buscar($ordemId);
ok(outros_materiais_texto($ordem) === ' + 5,00 t · Pó de pedra + 2,50 t · Areia' && materiais_nomes($ordem) === 'Brita 1 + Pó de pedra + Areia', 'grava os outros materiais na ordem, sem a linha vazia', outros_materiais_texto($ordem));
ok(Operacoes::viagensDe($ordemId)['total'] === 17.5, 'o total da nota soma os três materiais');

// Corrigir dados: trocar os outros; formulário sem as linhas mantém o que tinha.
$msg = erro(fn () => Operacoes::atualizar($ordemId, $com([['material_id' => $id('Pedrisco'), 'quantidade' => '4']])));
ok($msg === '' && outros_materiais_texto(Operacoes::buscar($ordemId)) === ' + 4,00 t · Pedrisco', 'corrigir troca os outros materiais', $msg);
$semLinhas = $base;
unset($semLinhas['tem_outros']);
$msg = erro(fn () => Operacoes::atualizar($ordemId, $semLinhas));
ok($msg === '' && outros_materiais_texto(Operacoes::buscar($ordemId)) === ' + 4,00 t · Pedrisco', 'formulário sem as linhas mantém os outros materiais', $msg);

// Carregado no dia e correção do carregado.
$pdo->exec("UPDATE fila_carregamentos SET status = 'concluido', fim_carregamento = NOW() WHERE id = $ordemId");
ok(Operacoes::carregadoNoDia(date('Y-m-d'))['t'] === 14.0, 'o carregado do dia soma os materiais da carga', json_encode(Operacoes::carregadoNoDia(date('Y-m-d'))));
ok(str_contains(erro(fn () => Operacoes::corrigirCarregado($ordemId, '8', 'Fabio')), 'mais de um material'), 'corrigir o carregado: só na carga de um material');

// Fin Pro: os materiais juntos, com as quantidades.
$campos = (fn (array $o) => self::campos($o))->bindTo(null, FinPro::class);
ok($campos(Operacoes::buscar($ordemId))['material'] === 'Brita 1 (10,00 t) + Pedrisco (4,00 t)', 'o Fin Pro recebe os materiais juntos', $campos(Operacoes::buscar($ordemId))['material']);
ok($campos(Operacoes::buscar(1))['material'] === 'Brita 1', 'carga de um material: igual a antes');
fim();
