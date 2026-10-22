// Estoque das pilhas: o administrativo marca o material e a concha da pá; o operador da pá lança as conchas
// com a pá do turno; o total aparece em m³ e toneladas (1,45 t/m³) e o que entrou hoje; só o administrativo
// lança com data passada, corrige e apaga; motorista não vê o estoque.
const T = require('../lib/teste');
const { ok, sql, txt, enviar, BASE } = T;
T.executar(async () => {
  const b = await T.abrir();
  const adm = await T.entrar(b, 'Fabio', 'senha-forte-123', { largura: 1280, altura: 1000 });

  // Sem material marcado, o estoque avisa onde marcar.
  await adm.goto(BASE + '/estoque');
  ok(/Nenhum material marcado com estoque/.test(await txt(adm)), 'sem material marcado, o estoque avisa');

  await adm.goto(BASE + '/cadastros/materiais/1');
  await adm.check('input[name=estoque]');
  await enviar(adm, 'form.formulario button[type=submit]');
  ok(sql('SELECT estoque FROM materiais WHERE id=1') === '1', 'material marcado com "Tem estoque"');

  await adm.goto(BASE + '/cadastros/pas/1');
  await adm.fill('input[name=concha]', 'abc');
  await enviar(adm, 'form.formulario button[type=submit]');
  ok(/A concha precisa ser um número/.test(await txt(adm)), 'concha inválida é recusada');
  await adm.fill('input[name=concha]', '3,3');
  await enviar(adm, 'form.formulario button[type=submit]');
  ok(sql('SELECT concha_m3 FROM pas_carregadeiras WHERE id=1') === '3.30', 'concha da Pá 1 gravada: 3,30 m³');

  // O operador da pá lança 12 conchas com a pá do turno.
  const maria = await T.entrar(b, 'Maria', 'maria1234');
  await T.assumir(maria, 'pa:1', '100', { envelhecer: true });
  await maria.goto(BASE + '/patio');
  await Promise.all([maria.waitForNavigation(), maria.click('.cabeca a:has-text("Estoque")')]);
  ok(/\/estoque$/.test(maria.url()) && /Pá 1 · concha de 3,30 m³/.test(await txt(maria)), 'pátio leva ao estoque, com a pá e a concha do turno', maria.url());
  ok(await maria.locator('input[name=colocado_em], select[name=pa_carregadeira_id]').count() === 0, 'operador não escolhe pá nem data');
  await maria.fill('input[name=conchas]', '12');
  await enviar(maria, 'form.formulario button[type=submit]');
  ok(sql("SELECT CONCAT(material_id,'|',pa_carregadeira_id,'|',conchas,'|',concha_m3,'|',usuario) FROM estoque_entradas") === '1|1|12|3.30|Maria', 'lançamento gravado com a concha da hora');
  const pagina = await txt(maria);
  ok(/Lançado no estoque/.test(pagina) && /39,60 m³/.test(pagina) && /57,42 t/.test(pagina), '12 conchas = 39,60 m³ = 57,42 t', pagina.slice(0, 400));
  ok(await maria.locator('a:has-text("Corrigir")').count() === 0, 'operador não vê Corrigir');
  const idMaria = sql('SELECT id FROM estoque_entradas');
  ok((await maria.goto(BASE + '/estoque/' + idMaria)).status() === 403, 'operador não abre a correção');
  await T.capturar(maria, 'estoque_operador', { fullPage: true });

  // Motorista não tem estoque.
  const joao = await T.entrar(b, 'Joao', 'joao1234');
  await T.assumir(joao, 'caminhao:1', '100', { envelhecer: true });
  ok((await joao.goto(BASE + '/estoque')).status() === 403, 'motorista não abre o estoque');

  // Saldo da planilha: o administrativo lança 640 conchas com data de ontem.
  await adm.goto(BASE + '/estoque');
  await adm.check('input[name=material_id][value="1"]', { force: true });
  await adm.fill('input[name=conchas]', '640');
  await adm.selectOption('select[name=pa_carregadeira_id]', '1');
  await adm.fill('input[name=colocado_em]', T.hoje(-1) + 'T18:00');
  await adm.fill('input[name=observacoes]', 'saldo da planilha');
  await enviar(adm, 'form.formulario button[type=submit]');
  let tela = await txt(adm);
  ok(/2\.151,60 m³/.test(tela) && /3\.119,82 t/.test(tela) && /652 conchas/.test(tela), 'total: 652 conchas = 2.151,60 m³ = 3.119,82 t', tela.slice(0, 400));
  ok(/Hoje\s*39,60 m³/.test(tela), 'hoje continua 39,60 m³ (o saldo foi ontem)', tela.slice(0, 400));

  // Data no futuro é recusada.
  await adm.check('input[name=material_id][value="1"]', { force: true });
  await adm.fill('input[name=conchas]', '5');
  await adm.selectOption('select[name=pa_carregadeira_id]', '1');
  await adm.fill('input[name=colocado_em]', T.hoje(2) + 'T08:00');
  await enviar(adm, 'form.formulario button[type=submit]');
  ok(/não pode ficar no futuro/.test(await txt(adm)) && sql('SELECT COUNT(*) FROM estoque_entradas') === '2', 'data no futuro é recusada');

  // Mudar a concha depois não muda o que já entrou.
  sql('UPDATE pas_carregadeiras SET concha_m3 = 4 WHERE id = 1');
  await adm.goto(BASE + '/estoque');
  ok(/2\.151,60 m³/.test(await txt(adm)), 'concha nova não altera os lançamentos antigos');

  // Correção e exclusão pelo administrativo.
  await adm.goto(BASE + '/estoque/' + idMaria);
  await adm.fill('input[name=conchas]', '10');
  await enviar(adm, 'form.formulario button[type=submit]');
  ok(sql(`SELECT conchas FROM estoque_entradas WHERE id=${idMaria}`) === '10' && /2\.145,00 m³/.test(await txt(adm)), 'correção para 10 conchas: 2.145,00 m³');
  await adm.goto(BASE + '/estoque/' + idMaria);
  await enviar(adm, 'button:has-text("Apagar lançamento")');
  tela = await txt(adm);
  ok(sql('SELECT COUNT(*) FROM estoque_entradas') === '1' && /2\.112,00 m³/.test(tela) && /3\.062,40 t/.test(tela), 'apagado: fica o saldo da planilha, 2.112,00 m³ = 3.062,40 t', tela.slice(0, 400));
  await T.capturar(adm, 'estoque_admin', { fullPage: true });
  T.resumo();
});
