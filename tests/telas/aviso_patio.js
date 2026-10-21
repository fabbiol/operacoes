// Aviso no tablet da pá: carga nova, alterada e cancelada aparecem num quadro grande até tocar em "Ciente";
// no app Android o sistema também pede o som e a vibração. O administrativo no pátio não recebe aviso.
const T = require('../lib/teste');
const { ok, sql, espera, BASE } = T;
const entrar = (b, usuario, senha, comApp) => T.entrar(b, usuario, senha, { app: comApp });
const assumir = (p, valor, leitura) => T.assumir(p, valor, leitura);
const quadro = async p => (await p.locator('.avisos-patio:not([hidden])').count()) ? (await p.locator('.avisos-patio').innerText()).replace(/\s+/g, ' ') : '';
async function esperarQuadro(p, re, ms = 15000) {
  const ate = Date.now() + ms;
  while (Date.now() < ate) { const t = await quadro(p); if (re.test(t)) return t; await espera(500); }
  return await quadro(p);
}
const liberar = (ordem, cliente, q) => sql(`INSERT INTO fila_carregamentos (ordem, data, cliente, pedido, nf, material_id, quantidade_autorizada, unidade, status, autorizado_por, autorizado_em) VALUES ('${ordem}', NOW(), '${cliente}', 'P-1', 'NF-${ordem}', 1, ${q}, 't', 'liberado', 'Fabio', NOW())`);
T.executar(async () => {
  const b = await T.abrir();
  const maria = await entrar(b, 'Maria', 'maria1234', true);
  await assumir(maria, 'pa:1', '100');
  await maria.goto(BASE + '/patio');
  ok(/\/patio$/.test(maria.url()), 'Maria no pátio', maria.url());
  await espera(1500);
  ok(await quadro(maria) === '', 'ao abrir o pátio, o que já estava na fila não avisa');

  liberar('CAR-500', 'Areião São José', 18);
  let t = await esperarQuadro(maria, /Nova carga liberada/);
  ok(/Nova carga liberada Areião São José 18,00 t · Brita 1 · CAR-500/.test(t), 'carga nova: aviso grande com cliente, carga e número', t);
  ok(JSON.stringify(await maria.evaluate(() => window.__avisos)) === '["nova"]', 'no app: pede ao Android o som e a vibração (nova)', JSON.stringify(await maria.evaluate(() => window.__avisos)));
  await T.capturar(maria, 'aviso_nova');
  await maria.click('.avisos-patio button:has-text("Ciente")');
  ok(await quadro(maria) === '', 'Ciente fecha o aviso');

  sql("UPDATE fila_carregamentos SET quantidade_autorizada = 24 WHERE ordem = 'CAR-500'");
  t = await esperarQuadro(maria, /Carga alterada/);
  ok(/Carga alterada Areião São José 24,00 t/.test(t), 'carga alterada pelo administrativo', t);
  await maria.click('.avisos-patio button:has-text("Ciente")');

  sql("UPDATE fila_carregamentos SET status = 'cancelado' WHERE ordem = 'CAR-001'");
  t = await esperarQuadro(maria, /Carga cancelada/);
  ok(/Carga cancelada Construtora Alfa 30,00 t · Brita 1 · CAR-001/.test(t), 'carga cancelada', t);
  ok((await maria.evaluate(() => window.__avisos)).slice(-1)[0] === 'cancelada', 'no app: som de cancelada');
  await T.capturar(maria, 'aviso_cancelada');

  // Sem Ciente, abre outra tela: ao voltar, o aviso continua; e o que mudou longe da tela também avisa.
  await maria.goto(BASE + '/patio/resumo');
  liberar('CAR-501', 'Concreto Forte', 12);
  await maria.goto(BASE + '/patio');
  t = await esperarQuadro(maria, /Concreto Forte/, 4000);
  ok(/Nova carga liberada Concreto Forte/.test(t) && /Carga cancelada Construtora Alfa/.test(t), 'ao voltar ao pátio: o aviso sem Ciente continua e a carga liberada enquanto estava fora avisa', t);
  await maria.click('.avisos-patio button:has-text("Ciente")');

  // O operador iniciar uma carga não é "alterada".
  await maria.reload(); await espera(1500);
  const antes = (await maria.evaluate(() => window.__avisos || [])).length;
  sql("UPDATE fila_carregamentos SET status = 'carregando', inicio_carregamento = NOW(), operador = 'Pedro', pa_carregadeira_id = 2 WHERE ordem = 'CAR-002'");
  await espera(9000);
  ok(await quadro(maria) === '' && (await maria.evaluate(() => window.__avisos || [])).length === antes, 'carga iniciada por outra pá não avisa');

  // Sem o app (Chrome): o aviso aparece na tela do mesmo jeito.
  sql("UPDATE fila_carregamentos SET status = 'concluido', fim_carregamento = NOW() WHERE ordem = 'CAR-002'");
  const pedro = await entrar(b, 'Pedro', 'pedro1234', false);
  await assumir(pedro, 'pa:2', '50');
  await pedro.goto(BASE + '/patio'); await espera(1500);
  liberar('CAR-502', 'Casa & Cia', 6);
  t = await esperarQuadro(pedro, /Casa & Cia/);
  ok(/Nova carga liberada Casa & Cia/.test(t), 'no Chrome, sem o app: o aviso aparece', t);

  // Administrativo no pátio: sem aviso.
  const adm = await entrar(b, 'Fabio', 'senha-forte-123', false);
  await adm.goto(BASE + '/patio');
  ok(await adm.locator('script[src*="avisos.js"]').count() === 0 && await adm.locator('script[data-avisos-patio]').count() === 0, 'administrativo no pátio: sem aviso');

  T.resumo();
});
