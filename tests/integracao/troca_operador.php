<?php
// Troca registrada pelo operador (Horimetro::registrarTroca) e os avisos de 100, 50, 25 e 8 h (Horimetro::avisos).
require __DIR__ . '/../lib/cli.php';

$ler = $pdo->prepare("INSERT INTO horimetros (tipo, maquina_id, leitura, lido_em, usuario) VALUES ('pa', 1, ?, NOW(), 'Maria')");
$ler->execute([1000]);
$planos = fn () => (int) $pdo->query("SELECT COUNT(*) FROM manutencao_planos WHERE tipo = 'pa' AND maquina_id = 1")->fetchColumn();

// Primeira vez: o operador diz o nome e de quantas em quantas horas; o horímetro é o atual.
$aviso = Horimetro::registrarTroca('pa', 1, ['servico' => 'novo', 'nome' => 'Troca de óleo do motor', 'intervalo' => '250'], 'Maria');
ok($aviso === 'Troca de óleo do motor registrada com 1.000,0 h. A próxima é com 1.250,0 h.', 'serviço novo registrado com o horímetro atual e o prazo salvo', $aviso);
$planoId = (int) $pdo->query("SELECT id FROM manutencao_planos WHERE nome = 'Troca de óleo do motor'")->fetchColumn();
ok($pdo->query("SELECT CONCAT(intervalo_h, '|', (SELECT CONCAT(horimetro, '|', usuario) FROM manutencoes WHERE plano_id = $planoId)) FROM manutencao_planos WHERE id = $planoId")->fetchColumn() === '250.0|1000.0|Maria', 'prazo 250 h e troca com 1.000 h por Maria');
ok(str_contains(erro(fn () => Horimetro::registrarTroca('pa', 1, ['servico' => 'novo', 'nome' => 'troca de ÓLEO do motor', 'intervalo' => '300'], 'Maria')), 'já está na lista') && $planos() === 1, 'o mesmo serviço não é criado de novo');
ok(erro(fn () => Horimetro::registrarTroca('pa', 1, [], 'Maria')) === 'Escolha o serviço que foi feito.', 'sem escolher o serviço: recusa');
ok(str_contains(erro(fn () => Horimetro::registrarTroca('pa', 1, ['servico' => 'novo', 'nome' => 'Filtro de ar', 'intervalo' => ''], 'Maria')), 'Intervalo') && $planos() === 1, 'serviço novo sem prazo: recusa e não cria');
ok(Horimetro::avisos('pa', 1) === [], 'faltam 250 h: sem aviso');

// Os avisos: a faixa é a menor das 100, 50, 25 e 8 h que ainda cobre o que falta.
foreach ([[1160, 100.0], [1210, 50.0], [1230, 25.0], [1245, 8.0]] as [$leitura, $faixa]) {
    $ler->execute([$leitura]);
    $avisos = Horimetro::avisos('pa', 1);
    ok(count($avisos) === 1 && $avisos[0]['situacao']['faixa'] === $faixa, 'horímetro ' . $leitura . ': aviso na faixa de ' . $faixa . ' h', json_encode($avisos[0]['situacao'] ?? null));
}
$ler->execute([1260]);
$avisos = Horimetro::avisos('pa', 1);
ok($avisos[0]['situacao']['estado'] === 'vencida' && $avisos[0]['situacao']['falta'] === -10.0, 'passou do prazo: vencida');
$todas = Horimetro::avisosDeTodas();
ok(count($todas) === 1 && $todas[0]['maquina'] === 'Pá 1' && $todas[0]['chave'] === 'pas' && $todas[0]['nome'] === 'Troca de óleo do motor', 'o Painel recebe o aviso com a máquina', json_encode($todas, JSON_UNESCAPED_UNICODE));

// Nas próximas vezes é só escolher na lista: o prazo salvo vale de novo.
$aviso = Horimetro::registrarTroca('pa', 1, ['servico' => (string) $planoId], 'Maria');
ok($aviso === 'Troca de óleo do motor registrada com 1.260,0 h. A próxima é com 1.510,0 h.' && Horimetro::avisos('pa', 1) === [], 'troca registrada: o aviso some e a próxima é com mais 250 h', $aviso);

// Serviço de outra máquina ou máquina sem horímetro: recusa, sem criar nada.
Horimetro::criarPlano('pa', 2, 'Graxa', '100');
$daPa2 = (int) $pdo->query("SELECT id FROM manutencao_planos WHERE tipo = 'pa' AND maquina_id = 2")->fetchColumn();
ok(erro(fn () => Horimetro::registrarTroca('pa', 1, ['servico' => (string) $daPa2], 'Maria')) === 'Esse serviço não é desta máquina.', 'serviço de outra máquina: recusa');
ok(str_contains(erro(fn () => Horimetro::registrarTroca('pa', 2, ['servico' => 'novo', 'nome' => 'Filtro', 'intervalo' => '500'], 'Pedro')), 'Lance o horímetro')
    && (int) $pdo->query("SELECT COUNT(*) FROM manutencao_planos WHERE tipo = 'pa' AND maquina_id = 2")->fetchColumn() === 1, 'máquina sem horímetro: recusa e o serviço novo não fica criado');
fim();
