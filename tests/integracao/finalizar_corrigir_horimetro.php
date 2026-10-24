<?php
// Regras de Operacoes/Horimetro chamadas direto (sem tela): quem finaliza a carga, correção do carregado e entrada do horímetro.
require __DIR__ . '/../lib/cli.php';

$v = fn (string $q) => (string) $pdo->query($q)->fetchColumn();

// 1. Finalizar: o operador que não iniciou não grava, mesmo chegando direto em Operacoes::finalizar.
$pdo->exec("UPDATE fila_carregamentos SET status='carregando', operador='Maria', pa_carregadeira_id=1, inicio_carregamento=NOW() WHERE id=1");
$msg = erro(fn () => Operacoes::finalizar(1, '', 'Pedro', null, 'Pedro'));
ok(str_contains($msg, 'Esta carga está com Maria') && $v('SELECT status FROM fila_carregamentos WHERE id=1') === 'carregando', 'Pedro não finaliza a carga da Maria', $msg);
ok(erro(fn () => Operacoes::finalizar(1, '', 'Fabio', null, null)) === '' && $v('SELECT status FROM fila_carregamentos WHERE id=1') === 'aguardando_conferencia', 'o administrativo finaliza');

// 2. Correção: a soma das viagens não passa do total da nota.
$pdo->exec("UPDATE fila_carregamentos SET quantidade_autorizada=10, unidade='t', fracionado=1, quantidade_carregada=4 WHERE id=1");
$pdo->exec("INSERT INTO fila_carregamentos (ordem, data, cliente, pedido, nf, material_id, quantidade_autorizada, unidade, fracionado, ordem_principal_id, viagem, status, quantidade_carregada, operador)
            SELECT CONCAT(ordem,'-2'), NOW(), cliente, pedido, nf, material_id, 6, unidade, 1, id, 2, 'aguardando_conferencia', 6, 'Maria' FROM fila_carregamentos WHERE id=1");
$msg = erro(fn () => Operacoes::corrigirCarregado(1, '10', 'Fabio'));
ok(str_contains($msg, 'Nesta viagem cabem no máximo 4,00 t') && $v('SELECT quantidade_carregada FROM fila_carregamentos WHERE id=1') === '4.00', 'viagem 1 não passa de 10 − 6', $msg);
ok(erro(fn () => Operacoes::corrigirCarregado(1, '3', 'Fabio')) === '' && $v('SELECT quantidade_autorizada FROM fila_carregamentos WHERE ordem_principal_id=1 AND status=\'saldo\'') === '1.00', 'corrigir para 3: sobra 1 t em Saldo a buscar');

// 3. Entrada corrigida duas vezes com o mesmo valor: anula uma vez só.
$pdo->exec("INSERT INTO horimetros (tipo, maquina_id, leitura, lido_em, usuario) VALUES ('pa', 2, 500.0, NOW() - INTERVAL 5 HOUR, 'Pedro')");
$entrada = (int) $pdo->lastInsertId();
$uid = (int) $v("SELECT id FROM usuarios WHERE nome='Pedro'");
$pdo->exec("INSERT INTO maquina_posse (tipo, maquina_id, usuario_id, usuario, entrada, entrada_id, iniciado_em) VALUES ('pa', 2, $uid, 'Pedro', 500.0, $entrada, NOW() - INTERVAL 5 HOUR)");
ok(erro(fn () => Horimetro::corrigirEntrada('pa', 2, $entrada, '490', 'Pedro')) === '', 'primeira correção 500 → 490');
$nova = (int) $v("SELECT entrada_id FROM maquina_posse WHERE usuario='Pedro'");
ok(erro(fn () => Horimetro::corrigirEntrada('pa', 2, $nova, '490', 'Pedro')) === '', 'segunda correção com o mesmo valor');
ok($v("SELECT COUNT(*) FROM horimetros WHERE tipo='pa' AND maquina_id=2 AND anulado_em IS NOT NULL") === '1' && $v("SELECT COUNT(*) FROM horimetros WHERE tipo='pa' AND maquina_id=2 AND anulado_em IS NULL") === '1', 'anulou uma vez só', $v("SELECT GROUP_CONCAT(CONCAT(leitura,'/',IFNULL(anulado_em,'-'))) FROM horimetros WHERE tipo='pa' AND maquina_id=2"));

// 4. Cancelar uma viagem do fracionado que já saiu: o que ela levou volta para o saldo (nota de 10: viagem 1 com 3, viagem 2 com 6, saldo 1).
$viagem2 = (int) $v("SELECT id FROM fila_carregamentos WHERE ordem_principal_id=1 AND viagem=2");
$aviso = '';
ok(erro(function () use ($viagem2, &$aviso) { $aviso = Operacoes::cancelar($viagem2, 'finalizou o cartão errado', 'Fabio'); }) === ''
    && $v("SELECT quantidade_autorizada FROM fila_carregamentos WHERE ordem_principal_id=1 AND status='saldo'") === '7.00',
    'cancelar a viagem 2: o saldo a buscar passa de 1 para 7 t', $aviso);
ok(str_contains($aviso, 'ficou com o saldo de 7,00 t') && Operacoes::viagensDe(1)['saldo'] === 7.0, 'aviso e saldo da nota batem', $aviso);
// Sem viagem pendente: o cliente levou o saldo (7), essa viagem é cancelada e o saldo volta como viagem nova.
$pdo->exec("UPDATE fila_carregamentos SET status='aguardando_conferencia', quantidade_carregada=7 WHERE ordem_principal_id=1 AND status='saldo'");
$ultima = (int) $v("SELECT id FROM fila_carregamentos WHERE ordem_principal_id=1 AND status='aguardando_conferencia'");
ok(erro(fn () => Operacoes::cancelar($ultima, '', 'Fabio')) === ''
    && $v("SELECT quantidade_autorizada FROM fila_carregamentos WHERE ordem_principal_id=1 AND status='saldo'") === '7.00',
    'cancelar a última viagem que saiu: os 7 t voltam como viagem em Saldo a buscar');
fim();
