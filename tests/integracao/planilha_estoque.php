<?php
// Planilha do estoque (Estoque::importarPlanilha): entra uma vez, dia por dia, com a pá 656, a Brita 01 e o Pedrisco;
// sem eles no cadastro, ou com o estoque já lançado, não faz nada; depois de importada, não volta nem com o estoque zerado.
require __DIR__ . '/../lib/cli.php';

ok(Estoque::importarPlanilha($pdo) !== 'importada', 'cadastro sem a pá 656 e o Pedrisco: não importa');

$pdo->exec("INSERT INTO pas_carregadeiras (maquina, marca, modelo) VALUES ('Pá 656', 'Volvo', 'L120')");
$pdo->exec("INSERT INTO materiais (material) VALUES ('Pedrisco')");
$pdo->exec("UPDATE materiais SET material = 'Brita 01' WHERE material = 'Brita 1'");
ok(Estoque::importarPlanilha($pdo) === 'importada', 'com a pá 656, a Brita 01 e o Pedrisco: importa');

$saldos = [];
foreach (Estoque::saldos() as $saldo) {
    $saldos[$saldo['nome']] = [(int) $saldo['conchas'], round((float) $saldo['m3'], 2), $saldo['t']];
}
ok($saldos['Brita 01'] === [640, 2112.0, 3062.4], 'Brita 01: 640 conchas = 2.112 m³ = 3.062,40 t', json_encode($saldos));
ok($saldos['Pedrisco'] === [350, 1155.0, 1674.75], 'Pedrisco: 350 conchas = 1.155 m³ = 1.674,75 t', json_encode($saldos));
ok($pdo->query("SELECT COUNT(*) FROM estoque_entradas WHERE colocado_em = '2026-10-21 18:00:00'")->fetchColumn() == 2, 'dia 21/10: as duas linhas da planilha');
ok($pdo->query("SELECT CONCAT(concha_m3, '|', (SELECT SUM(estoque) FROM materiais)) FROM pas_carregadeiras WHERE maquina = 'Pá 656'")->fetchColumn() === '3.30|2', 'concha da 656 em 3,3 m³ e os dois materiais com estoque');

$total = $pdo->query('SELECT COUNT(*) FROM estoque_entradas')->fetchColumn();
ok(Estoque::importarPlanilha($pdo) === 'a planilha já foi tratada' && $pdo->query('SELECT COUNT(*) FROM estoque_entradas')->fetchColumn() === $total, 'de novo: não lança duas vezes');
$pdo->exec('DELETE FROM estoque_entradas');
ok(Estoque::importarPlanilha($pdo) === 'a planilha já foi tratada' && (int) $pdo->query('SELECT COUNT(*) FROM estoque_entradas')->fetchColumn() === 0, 'estoque zerado depois: a planilha não volta');

fim();
