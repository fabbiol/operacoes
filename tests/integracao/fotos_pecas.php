<?php
// Fotos das peças: a foto única de antes (maquina_pecas.foto) vira a primeira foto da peça (peca_fotos), uma vez só,
// e continua abrindo pelo mesmo arquivo (Pecas::arquivoFoto confere que a foto é da peça).
require __DIR__ . '/../lib/cli.php';

$pdo->exec("INSERT INTO maquina_pecas (tipo, maquina_id, funcao, codigo) VALUES ('pa', 1, 'Filtro de óleo', 'LF3349'), ('pa', 1, 'Filtro de ar', 'P181052')");
[$comFoto, $semFoto] = array_map('intval', $pdo->query('SELECT id FROM maquina_pecas ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
$pdo->exec("UPDATE maquina_pecas SET foto = 'pecas/$comFoto.jpg' WHERE id = $comFoto");
@mkdir(OPERACOES_ROOT . '/storage/fotos/pecas', 0775, true);
file_put_contents(OPERACOES_ROOT . "/storage/fotos/pecas/$comFoto.jpg", 'foto antiga');

$migrar = fn () => (function (PDO $pdo) { self::garantirFotosDasPecas($pdo); })->bindTo(null, Db::class)($pdo);
$migrar();
$migrar();  // roda de novo em toda publicação: não pode copiar duas vezes
$fotos = $pdo->query('SELECT id, peca_id, arquivo FROM peca_fotos')->fetchAll();
ok(count($fotos) === 1 && (int) $fotos[0]['peca_id'] === $comFoto && $fotos[0]['arquivo'] === "pecas/$comFoto.jpg", 'a foto antiga virou a primeira foto da peça, uma vez só', json_encode($fotos));
ok($pdo->query('SELECT COUNT(*) FROM maquina_pecas WHERE foto IS NOT NULL')->fetchColumn() == 0, 'a coluna antiga ficou vazia');

$fotoId = (int) $fotos[0]['id'];
ok((Pecas::arquivoFoto($comFoto, $fotoId)['caminho'] ?? '') === OPERACOES_ROOT . "/storage/fotos/pecas/$comFoto.jpg", 'a foto antiga abre pelo mesmo arquivo');
ok(Pecas::arquivoFoto($semFoto, $fotoId) === null, 'a foto de uma peça não abre pela outra');
$naFicha = array_column(Pecas::daMaquina('pa', 1), 'fotos', 'id');
ok(count($naFicha[$comFoto]) === 1 && $naFicha[$semFoto] === [], 'a ficha recebe a foto na lista de fotos da peça', json_encode($naFicha));
fim();
