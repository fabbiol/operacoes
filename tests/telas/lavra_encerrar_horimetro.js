// Máquinas e horímetro: encerrar viagem com "Outro" e "é problema", Oficina vira problema sozinha,
// horímetro com ponto de milhar e entrada digitada errada corrigida ao soltar.
const T = require('../lib/teste');
const { ok, sql, espera, txt, BASE } = T;
const entrar = (b, usuario, senha) => T.entrar(b, usuario, senha);
const enviar = T.enviar;
// Além da posse e do horímetro, o problema avisado volta 1 minuto (o app recusa dois problemas no mesmo instante).
const envelhecer = () => T.envelhecer('UPDATE maquina_problemas SET relatado_em = relatado_em - INTERVAL 1 MINUTE');
async function assumir(p, valor, leitura) {
  await T.assumir(p, valor, leitura);
  envelhecer();
}
T.executar(async () => {
  const b = await T.abrir();
  // 1. Joao: Encerrar viagem com "Outro: pneu furou" e "é problema".
  const joao = await entrar(b, 'Joao', 'joao1234');
  await assumir(joao, 'caminhao:1', '2000');
  await joao.goto(BASE + '/lavra');
  await enviar(joao, 'form[action$="/lavra/caminhoes/1/fila"] button');
  const ciclo = sql("SELECT id FROM ciclos_lavra WHERE caminhao_id = 1 AND status = 'na_fila'");
  await joao.goto(BASE + '/lavra/ciclos/' + ciclo + '/encerrar');
  await joao.check('input[name=motivo][value=outro]', { force: true });
  await joao.check('input[name=problema]');
  await enviar(joao, 'form.formulario button[type=submit]');
  ok(/Escreva o que aconteceu com o caminhão/.test(await txt(joao)), 'marcou é problema sem escrever: pede o texto');
  ok(sql('SELECT COUNT(*) FROM maquina_problemas') === '0' && sql("SELECT status FROM ciclos_lavra WHERE id = " + ciclo) === 'na_fila', 'marcado "é problema" sem escrever: não encerra nem grava');
  await joao.fill('input[name=outro]', 'Pneu furou');
  await enviar(joao, 'form.formulario button[type=submit]');
  ok(/O problema foi avisado ao escritório/.test(await txt(joao)), 'encerrou e avisou o problema');
  ok(sql("SELECT CONCAT(tipo,':',maquina_id,'|',usuario,'|',descricao) FROM maquina_problemas") === 'caminhao:1|Joao|Pneu furou', 'problema gravado na hora, antes de soltar o caminhão');
  envelhecer();
  await joao.goto(BASE + '/maquina');
  ok(await joao.isChecked('input[name=situacao][value=problema]'), 'ao soltar, "Teve problema" já vem marcado');
  await joao.fill('input[name=leitura]', '2006');
  await enviar(joao, 'form.soltar-maquina button[type=submit]');
  ok(sql("SELECT COUNT(*) FROM maquina_posse WHERE usuario='Joao'") === '0' && sql('SELECT COUNT(*) FROM maquina_problemas') === '1' && sql("SELECT maquina_ok FROM horimetro_turnos WHERE usuario='Joao'") === '0', 'soltou sem reescrever o problema; turno "avisou problema" e nenhum problema repetido');

  // 2. Ana: Oficina sem texto vira problema sozinho.
  const ana = await entrar(b, 'Ana', 'ana12345');
  await assumir(ana, 'caminhao:2', '1500');
  await ana.goto(BASE + '/lavra');
  await enviar(ana, 'form[action$="/lavra/caminhoes/2/fila"] button');
  const ciclo2 = sql("SELECT id FROM ciclos_lavra WHERE caminhao_id = 2 AND status = 'na_fila'");
  await ana.goto(BASE + '/lavra/ciclos/' + ciclo2 + '/encerrar');
  await ana.check('input[name=motivo][value=oficina]', { force: true });
  await ana.fill('input[name=outro]', 'barulho no freio');
  await enviar(ana, 'form.formulario button[type=submit]');
  ok(sql("SELECT descricao FROM maquina_problemas WHERE usuario='Ana'") === 'Foi para a oficina: barulho no freio', 'Oficina vira problema sozinha', sql("SELECT descricao FROM maquina_problemas WHERE usuario='Ana'"));
  ok(sql("SELECT encerramento FROM ciclos_lavra WHERE id = " + ciclo2) === 'Oficina · barulho no freio', 'o encerramento guarda o detalhe da oficina');

  // 3. Pedro: horímetro com ponto de milhar.
  const pedro = await entrar(b, 'Pedro', 'pedro1234');
  await assumir(pedro, 'pa:2', '12.350');
  ok(sql("SELECT leitura FROM horimetros WHERE tipo='pa' AND maquina_id=2") === '12350.0', '"12.350" é lido como 12.350 h', sql("SELECT leitura FROM horimetros WHERE tipo='pa' AND maquina_id=2"));
  await pedro.goto(BASE + '/maquina');
  await pedro.fill('input[name=leitura]', '12.358');
  await pedro.check('input[name=situacao][value=ok]', { force: true });
  await enviar(pedro, 'form.soltar-maquina button[type=submit]');
  ok(sql("SELECT CONCAT(entrada,'|',saida,'|',horas) FROM horimetro_turnos WHERE usuario='Pedro'") === '12350.0|12358.0|8.0', 'turno de 8 h com pontos de milhar', sql("SELECT CONCAT(entrada,'|',saida,'|',horas) FROM horimetro_turnos WHERE usuario='Pedro'"));

  // 4. Maria: entrada digitada errada (2010 em vez de 2000) e corrigida por ela mesma.
  sql("INSERT INTO horimetros (tipo, maquina_id, leitura, lido_em, usuario) VALUES ('pa', 1, 2000.0, DATE_SUB(NOW(), INTERVAL 30 HOUR), 'Fabio')");
  const maria = await entrar(b, 'Maria', 'maria1234');
  await assumir(maria, 'pa:1', '2010');
  await maria.goto(BASE + '/maquina');
  await maria.fill('input[name=leitura]', '2006');
  await maria.check('input[name=situacao][value=ok]', { force: true });
  await enviar(maria, 'form.soltar-maquina button[type=submit]');
  const erro = await txt(maria);
  ok(/não pode ser menor/.test(erro) && /A entrada foi digitada errada\?/.test(erro), 'saída recusada explica como corrigir a entrada');
  await maria.click('details.corrigir-entrada summary');
  await maria.fill('input[name=entrada_certa]', '2000');
  await maria.fill('input[name=leitura]', '2006');
  await maria.check('input[name=situacao][value=ok]', { force: true });
  await enviar(maria, 'form.soltar-maquina button[type=submit]');
  ok(sql("SELECT COUNT(*) FROM maquina_posse WHERE usuario='Maria'") === '0', 'com a entrada certa, soltou');
  ok(sql("SELECT CONCAT(entrada,'|',saida,'|',horas) FROM horimetro_turnos WHERE usuario='Maria'") === '2000.0|2006.0|6.0', 'turno somado com a entrada certa', sql("SELECT CONCAT(entrada,'|',saida,'|',horas) FROM horimetro_turnos WHERE usuario='Maria'"));
  ok(sql("SELECT CONCAT(leitura,'|',anulado_motivo) FROM horimetros WHERE anulado_em IS NOT NULL") === '2010.0|Entrada corrigida por Maria ao soltar', 'a entrada errada fica anulada, com registro');
  T.resumo();
});
