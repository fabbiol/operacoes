// Valor da nota: nota sem valor é avisada no Monitor e no Controle de carga; o administrativo preenche e corrige o valor
// pelo Controle ou pela ficha; valor inválido é recusado; ordem cancelada não aceita valor.
const T = require('../lib/teste');
const { ok, sql, espera, txt, BASE } = T;
T.executar(async () => {
  // Nota concluída hoje sem valor (como as lançadas antes da atualização), 3 m³.
  sql("INSERT INTO fila_carregamentos (ordem, data, cliente, pedido, nf, material_id, quantidade_autorizada, unidade, status, autorizado_por, autorizado_em, inicio_carregamento, fim_carregamento, pa_carregadeira_id, operador) VALUES ('CAR-900', NOW(), 'Cliente Pedrisco', '5501', '5502', 1, 3, 'm3', 'concluido', 'Fabio', NOW(), NOW(), NOW(), 1, 'Maria')");
  sql("INSERT INTO fila_carregamentos (ordem, data, cliente, pedido, nf, material_id, quantidade_autorizada, unidade, status, autorizado_por, autorizado_em, inicio_carregamento, fim_carregamento, pa_carregadeira_id, operador) VALUES ('CAR-901', NOW(), 'Areião', 'P-1', '7300', 1, 12, 't', 'aguardando_conferencia', 'Fabio', NOW(), NOW(), NOW(), 1, 'Maria')");
  const id = sql("SELECT id FROM fila_carregamentos WHERE ordem='CAR-900'");
  const id2 = sql("SELECT id FROM fila_carregamentos WHERE ordem='CAR-901'");
  sql("UPDATE fila_carregamentos SET nf_valor = NULL");
  const b = await T.abrir();
  const p = await T.entrar(b, 'Fabio', 'senha-forte-123', { largura: 1681, altura: 950 });

  await p.goto(BASE + '/dashboard'); await espera(500);
  const tile = (await p.locator('.tv-numero').last().innerText()).replace(/\s+/g, ' ');
  ok(/Vendido hoje R\$ 0/i.test(tile) && /sem valor/.test(tile), 'Monitor avisa nota sem valor', tile);
  await T.capturar(p, 'valor_monitor');

  await p.goto(BASE + '/controle'); await espera(500);
  ok(/sem valor: preencha em Notas do dia/.test(await txt(p)), 'Controle avisa onde preencher');
  ok(await p.locator('form.valor-nota').count() === 5, 'Controle: campo de valor nas 5 notas sem valor (2 deste teste + 3 de exemplo)', await p.locator('form.valor-nota').count());
  const form = p.locator(`form.valor-nota[action$="/ordens/${id}/valor"]`);
  await form.locator('input[name=nf_valor]').fill('512,30');
  await espera(1300);
  await Promise.all([p.waitForNavigation(), form.locator('button').click()]);
  ok(/\/controle\?dia=\d{4}-\d{2}-\d{2}$/.test(p.url()), 'volta ao Controle no mesmo dia', p.url());
  ok(/Valor da nota salvo/.test(await txt(p)) && /Vendido no dia R\$ 512,30/.test(await txt(p)), 'vendido do dia passa a R$ 512,30', (await txt(p)).slice(0, 260));
  ok(sql(`SELECT nf_valor FROM fila_carregamentos WHERE id=${id}`) === '512.30', 'gravado no banco');
  await T.capturar(p, 'valor_controle', { fullPage: true });

  // Ficha da ordem concluída: corrigir o valor.
  await p.goto(BASE + '/ordens/' + id);
  ok(await p.locator('form.valor-nota button[type=submit]').innerText() === 'Corrigir valor', 'ficha concluída: Corrigir valor');
  await p.fill('form.valor-nota input[name=nf_valor]', 'abc');
  await espera(1300);
  await Promise.all([p.waitForNavigation(), p.click('form.valor-nota button[type=submit]')]);
  ok(/precisa ser um número/.test(await txt(p)) && sql(`SELECT nf_valor FROM fila_carregamentos WHERE id=${id}`) === '512.30', 'valor inválido é recusado');
  await p.fill('form.valor-nota input[name=nf_valor]', 'R$ 1.500,00');
  await espera(1300);
  await Promise.all([p.waitForNavigation(), p.click('form.valor-nota button[type=submit]')]);
  ok(p.url().endsWith('/ordens/' + id) && sql(`SELECT nf_valor FROM fila_carregamentos WHERE id=${id}`) === '1500.00', 'ficha corrige para R$ 1.500,00');
  await T.capturar(p, 'valor_ficha', { fullPage: true });

  // Aguardando conferência (edição travada): o valor vai pelo quadro da ficha, sem campo repetido.
  await p.goto(BASE + '/ordens/' + id2);
  ok(await p.locator('input[name=nf_valor]').count() === 1, 'ordem travada: um campo de valor só', await p.locator('input[name=nf_valor]').count());
  await p.fill('form.valor-nota input[name=nf_valor]', '900');
  await espera(1300);
  await Promise.all([p.waitForNavigation(), p.click('form.valor-nota button[type=submit]')]);
  ok(sql(`SELECT CONCAT(nf_valor,'|',status) FROM fila_carregamentos WHERE id=${id2}`) === '900.00|aguardando_conferencia', 'valor salvo sem mexer na situação');

  // Ordem liberada: o valor continua no formulário de edição, sem o quadro extra.
  sql("INSERT INTO fila_carregamentos (ordem, data, cliente, pedido, nf, material_id, quantidade_autorizada, unidade, status) VALUES ('CAR-902', NOW(), 'Casa', 'P-2', '7301', 1, 6, 'm3', 'liberado')");
  const id3 = sql("SELECT id FROM fila_carregamentos WHERE ordem='CAR-902'");
  await p.goto(BASE + '/ordens/' + id3);
  ok(await p.locator('form.valor-nota').count() === 0 && await p.locator('input[name=nf_valor]').count() === 1, 'ordem liberada: valor no formulário normal');

  // Cancelada: não aceita valor.
  sql(`UPDATE fila_carregamentos SET status='cancelado' WHERE id=${id3}`);
  await p.goto(BASE + '/ordens/' + id3);
  ok(await p.locator('input[name=nf_valor]').count() === 0, 'cancelada: sem campo de valor');

  await p.goto(BASE + '/dashboard'); await espera(500);
  const tile2 = (await p.locator('.tv-numero').last().innerText()).replace(/\s+/g, ' ');
  ok(/R\$ 2\.400/.test(tile2) && /3 sem valor/.test(tile2), 'Monitor: R$ 2.400 e as 3 de exemplo sem valor', tile2);
  T.resumo();
});
