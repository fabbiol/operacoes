<?php
// Previsão de vencimento (Horimetro::mediaPorDia, previsao, ficha): média de uso dos turnos dos últimos 14 dias,
// dia previsto do serviço preventivo, sem previsão para máquina sem turno e para serviço vencido.
require __DIR__ . '/../lib/cli.php';

$turno = $pdo->prepare("INSERT INTO horimetro_turnos (tipo, maquina_id, usuario, entrada, saida, horas, encerrado_em) VALUES ('pa', 1, 'Maria', 0, 0, ?, NOW() - INTERVAL ? DAY)");
// 3 dias com turno: um com dois turnos de 4 h, dois com 8 h -> 24 h em 3 dias = 8 h/dia. O de 20 dias atrás não conta.
foreach ([[4, 1], [4, 1], [8, 2], [8, 3], [100, 20]] as $t) {
    $turno->execute($t);
}
ok(Horimetro::mediaPorDia('pa', 1) === 8.0, 'média de 8 h por dia, sem o turno de 20 dias atrás', var_export(Horimetro::mediaPorDia('pa', 1), true));
ok(Horimetro::mediaPorDia('pa', 2) === null, 'máquina sem turno: sem média');

// Horímetro 1.210 h; troca de óleo a cada 250 h feita com 1.000 h -> vence com 1.250 h, faltam 40 h = 5 dias.
$pdo->exec("INSERT INTO horimetros (tipo, maquina_id, leitura, lido_em, usuario) VALUES ('pa', 1, 1210, NOW(), 'Fabio'), ('pa', 2, 1210, NOW(), 'Fabio')");
foreach ([1, 2] as $id) {
    Horimetro::criarPlano('pa', $id, 'Troca de óleo', '250');
    Horimetro::criarPlano('pa', $id, 'Graxa', '100');
}
$pdo->exec("INSERT INTO manutencoes (plano_id, horimetro, feito_em, usuario) SELECT id, 1000, NOW(), 'Fabio' FROM manutencao_planos");
$planos = fn (int $id) => array_column(Horimetro::ficha('pa', $id)['planos'], 'situacao', 'nome');

$dia = (new DateTimeImmutable('today', new DateTimeZone('-04:00')))->modify('+5 days');
$semana = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'][(int) $dia->format('w')];
$esperado = 'vence por volta de ' . $semana . ', ' . $dia->format('d/m');
$pa1 = $planos(1);
ok($pa1['Troca de óleo']['falta'] === 40.0 && $pa1['Troca de óleo']['previsao'] === $esperado, 'troca de óleo: ' . $esperado, json_encode($pa1['Troca de óleo'], JSON_UNESCAPED_UNICODE));
ok($pa1['Graxa']['estado'] === 'vencida' && $pa1['Graxa']['previsao'] === null, 'serviço vencido: sem previsão, fica o selo de vencido', json_encode($pa1['Graxa'], JSON_UNESCAPED_UNICODE));
ok($planos(2)['Troca de óleo']['previsao'] === null, 'máquina sem turno: sem previsão');

$noPainel = array_values(array_filter(Horimetro::painel()[0]['itens'], fn (array $i): bool => $i['nome'] === 'Pá 1'))[0];
ok(in_array($esperado, array_column(array_column($noPainel['planos'], 'situacao'), 'previsao'), true), 'o painel da Manutenção traz a previsão pronta');

// A conta pura: arredonda para o dia, e menos de meio dia é hoje.
$quarta = new DateTimeImmutable('2026-10-28');
ok(Horimetro::previsao(10.0, 8.0, $quarta) === 'vence por volta de quinta, 29/10', '10 h a 8 h/dia: amanhã', (string) Horimetro::previsao(10.0, 8.0, $quarta));
ok(Horimetro::previsao(3.9, 8.0, $quarta) === 'vence por volta de hoje', 'menos de meio dia: hoje');
ok(Horimetro::previsao(-2.0, 8.0, $quarta) === null && Horimetro::previsao(40.0, null, $quarta) === null, 'vencido ou sem média: sem previsão');
fim();
