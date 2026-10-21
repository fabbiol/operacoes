// Lançamento manual de uma ordem em m³: a unidade existe no formulário e aparece certa no banco, na ficha e na fila.
const T = require('../lib/teste');
const { ok, sql, espera, BASE } = T;
const F = 'form[action$="/ordens/nova"]';

T.executar(async () => {
  const b = await T.abrir();
  const p = await T.entrar(b, 'Fabio', 'senha-forte-123', { largura: 600, altura: 1100 });
  await p.goto(BASE + '/ordens/nova');
  const opcoes = await p.locator('select[name=unidade] option').evaluateAll(os => os.map(o => o.value));
  ok(opcoes.includes('m3') && opcoes.includes('t'), 'o formulário oferece t e m³', JSON.stringify(opcoes));
  await p.fill(F + ' [name=cliente]', 'Teste m3');
  await p.fill(F + ' [name=pedido]', 'M3-1');
  await p.fill(F + ' [name=nf]', '9001');
  await p.selectOption('select[name=material_id]', { index: 1 });
  await p.fill(F + ' [name=quantidade_autorizada]', '6');
  await p.selectOption('select[name=unidade]', 'm3');
  await espera(1300);
  await Promise.all([p.waitForNavigation(), p.click(F + ' button[type=submit]')]);
  const aviso = (await p.locator('.aviso').allInnerTexts()).join(' | ');
  ok(/\/ordens\/\d+$/.test(p.url()) && /Ordem lançada/.test(aviso), 'ordem lançada e aberta na ficha', p.url() + ' ' + aviso);
  const linha = sql("SELECT CONCAT(quantidade_autorizada,'|',unidade,'|',status) FROM fila_carregamentos WHERE nf='9001'");
  ok(linha === '6.00|m3|aguardando_autorizacao', 'banco guarda 6,00 m3 aguardando autorização', linha);
  const ficha = (await p.locator('dl.ficha').innerText()).replace(/\s+/g, ' ');
  ok(/6,00 m³ · Brita 1/.test(ficha), 'ficha mostra 6,00 m³ · Brita 1', ficha);
  await p.goto(BASE + '/fila');
  ok(/6,00 m³/.test(await p.textContent('main')), 'a fila mostra 6,00 m³');
  T.resumo();
});
