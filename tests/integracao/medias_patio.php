<?php
// Relatório do pátio (Operacoes::resumo): fila e carga que atravessam a meia-noite ficam fora das médias.
require __DIR__ . '/../lib/cli.php';

$ontem = date('Y-m-d', time() - 86400);
$anteontem = date('Y-m-d', time() - 2 * 86400);
$pdo->exec('DELETE FROM fila_carregamentos');
$ins = $pdo->prepare("INSERT INTO fila_carregamentos (ordem, data, cliente, pedido, nf, material_id, quantidade_autorizada, unidade, status, quantidade_carregada, operador, pa_carregadeira_id, autorizado_em, inicio_carregamento, fim_carregamento)
    VALUES (?, ?, 'Cliente', 'P', 'NF', 1, 10, 't', 'concluido', 10, 'Maria', 1, ?, ?, ?)");
// liberada 08:00, carregada 08:10-08:20: fila 10, carga 10, ciclo 20
$ins->execute(['CAR-A', "$ontem 08:00:00", "$ontem 08:00:00", "$ontem 08:10:00", "$ontem 08:20:00"]);
// liberada anteontem 17:00 e carregada ontem 07:00-07:10: a fila foi a noite toda
$ins->execute(['CAR-B', "$anteontem 17:00:00", "$anteontem 17:00:00", "$ontem 07:00:00", "$ontem 07:10:00"]);

$r = Operacoes::resumo($ontem);
$m = $r['maquina']['medias'];
ok($m['fila'] === 600 && $m['ciclo'] === 1200, 'fila que passou a noite fica fora da fila e do ciclo médio', json_encode($m));
ok($m['carga'] === 600 && $r['maquina']['total'] === 2, 'mas a carga dela conta e a ordem continua no total', json_encode($r['maquina']));
fim();
