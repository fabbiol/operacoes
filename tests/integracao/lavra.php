<?php
// Relatório da lavra (Lavra::resumo / doDia): tempos médios, garagem, oficina e desconto da limpeza da escavadeira.
require __DIR__ . '/../lib/cli.php';

$zerar = function () { (function () { self::$limpezas = null; self::$cargasDaRetro = null; })->bindTo(null, Lavra::class)(); };
$t = fn (string $hm, int $dias = 0) => date('Y-m-d', time() - $dias * 86400) . ' ' . $hm . ':00';
$pdo->exec('DELETE FROM ciclos_lavra; DELETE FROM lavra_limpezas');
$ins = $pdo->prepare("INSERT INTO ciclos_lavra (caminhao_id, status, inicio_fila, inicio_carga, fim_carga, chegada_britador, fim_ciclo, operador, retroescavadeira_id, operador_carga, capacidade_t, encerramento, garagem) VALUES (?, 'concluido', ?, ?, ?, ?, ?, 'Joao', 1, 'Maria', 30, ?, ?)");
// 3 viagens normais de 06:00-07:00: fila 5, carga 5, ida 20, volta 20 = ciclo 50
foreach ([['06:00', '06:05', '06:10', '06:30', '06:50'], ['07:00', '07:05', '07:10', '07:30', '07:50'], ['08:00', '08:05', '08:10', '08:30', '08:50']] as [$f, $c, $fc, $b, $fim]) {
    $ins->execute([1, $t($f), $t($c), $t($fc), $t($b), $t($fim), null, 0]);
}
$base = Lavra::resumo(date('Y-m-d'))['caminhao']['medias'];
ok($base['fila'] === 300 && $base['ciclo'] === 3000, 'base: fila 5 min, ciclo 50 min', json_encode($base));
// encerrada na estrada 2 h depois (oficina): não entra em estrada/volta/ciclo
$ins->execute([2, $t('09:00'), $t('09:05'), $t('09:10'), null, $t('11:10'), 'Oficina', 0]);
$m = Lavra::resumo(date('Y-m-d'))['caminhao']['medias'];
ok($m['ida'] === 1200 && $m['ciclo'] === 3000 && $m['retorno'] === 1200, 'encerrada sem chegar no britador fica fora de estrada, volta e ciclo', json_encode($m));
ok(Lavra::resumo(date('Y-m-d'))['caminhao']['viagens'] === 4, 'mas continua contando como viagem (produção)');
// garagem: chegou no britador e foi 1 min depois para a garagem
$ins->execute([3, $t('10:00'), $t('10:05'), $t('10:10'), $t('10:30'), $t('10:31'), null, 1]);
$m = Lavra::resumo(date('Y-m-d'))['caminhao']['medias'];
ok($m['retorno'] === 1200 && $m['ciclo'] === 3000 && $m['ida'] === 1200, 'garagem: entra a estrada, fica fora a volta e o ciclo', json_encode($m));
// fila que atravessou a noite
if (date('H') >= '07') {
    $ins->execute([3, date('Y-m-d', time() - 86400) . ' 17:40:00', $t('07:00'), $t('07:05'), $t('07:25'), $t('07:45'), null, 0]);
    $m = Lavra::resumo(date('Y-m-d'))['caminhao']['medias'];
    ok($m['fila'] === 300 && $m['ciclo'] === 3000, 'fila que passou a noite fica fora da fila e do ciclo médio', json_encode($m));
} else {
    pular_parte('fila que passou a noite: só roda depois das 07h (horário de Cuiabá), quando as viagens de hoje já aconteceram');
}
// limpeza das 12:00 às 13:00 com carga no meio
$pdo->exec('DELETE FROM ciclos_lavra');
$pdo->prepare("INSERT INTO lavra_limpezas (retroescavadeira_id, operador, inicio, fim) VALUES (1, 'Maria', ?, ?)")->execute([$t('12:00', 1), $t('13:00', 1)]);
$ins2 = $pdo->prepare("INSERT INTO ciclos_lavra (caminhao_id, status, inicio_fila, inicio_carga, fim_carga, chegada_britador, fim_ciclo, operador, retroescavadeira_id, operador_carga, capacidade_t) VALUES (?, 'concluido', ?, ?, ?, ?, ?, 'Joao', 1, 'Maria', 30)");
// caminhão A chega 12:05 e é carregado 12:15-12:20 (a escavadeira interrompe a limpeza)
$ins2->execute([1, $t('12:05', 1), $t('12:15', 1), $t('12:20', 1), $t('12:40', 1), $t('13:00', 1)]);
// caminhão B chega 12:10 e espera até 12:45 (a escavadeira voltou a limpar depois de carregar A)
$ins2->execute([2, $t('12:10', 1), $t('12:45', 1), $t('12:50', 1), $t('13:10', 1), $t('13:30', 1)]);
$dia = date('Y-m-d', time() - 86400);
$zerar();
$linhas = Lavra::doDia($dia)['linhas'];
$porCaminhao = [];
foreach ($linhas as $l) { $porCaminhao[(int) $l['caminhao_id']] = $l['tempos']; }
ok($porCaminhao[1]['limpeza'] === 600 && $porCaminhao[1]['fila'] === 0, 'A: 10 min de espera, todos durante a limpeza', json_encode($porCaminhao[1]));
ok($porCaminhao[2]['limpeza'] === 1800 && $porCaminhao[2]['fila'] === 300, 'B: 35 min de espera, 5 min carregando o A não são limpeza -> limpeza 30, fila 5', json_encode($porCaminhao[2]));
ok(Lavra::limpezaNoDia($dia) === 3000, 'limpeza do dia sem o tempo das duas cargas: 60 - 10 = 50 min', (string) Lavra::limpezaNoDia($dia));
// limpeza antiga (60 dias) continua valendo no relatório daquele dia
$pdo->exec('DELETE FROM ciclos_lavra; DELETE FROM lavra_limpezas');
$pdo->prepare("INSERT INTO lavra_limpezas (retroescavadeira_id, operador, inicio, fim) VALUES (1, 'Maria', ?, ?)")->execute([$t('12:00', 60), $t('12:40', 60)]);
$ins2->execute([1, $t('12:00', 60), $t('12:50', 60), $t('12:55', 60), $t('13:15', 60), $t('13:35', 60)]);
$zerar();
$m = Lavra::resumo(date('Y-m-d', time() - 60 * 86400))['caminhao']['medias'];
ok($m['fila'] === 600, 'dia de 60 dias atrás mantém o desconto da limpeza', json_encode($m));
// limpeza esquecida de ontem fecha no fim do dia
$pdo->exec('DELETE FROM lavra_limpezas');
$pdo->prepare("INSERT INTO lavra_limpezas (retroescavadeira_id, operador, inicio) VALUES (1, 'Maria', ?)")->execute([$t('16:00', 1)]);
ok(Lavra::limpezasAbertas() === [], 'limpeza esquecida ontem não aparece mais como aberta hoje');
ok($pdo->query('SELECT fim FROM lavra_limpezas')->fetchColumn() === date('Y-m-d', time() - 86400) . ' 23:59:59', 'e fecha às 23:59:59 do dia em que começou');
fim();
