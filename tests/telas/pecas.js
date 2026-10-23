// Peças da máquina e pedido ao Compras: o administrativo cadastra as peças (uma com foto), faz o pedido confirmado
// com o operador, a mensagem do WhatsApp sai pronta com o link público das fotos, que abre sem login só com o código;
// o operador não abre nada disso; "trocado" grava o horímetro e a última troca aparece na peça; editar a peça
// depois não muda o pedido.
const T = require('../lib/teste');
const { ok, sql, txt, enviar, BASE } = T;
// PNG de 1x1 pixel.
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64');

T.executar(async () => {
  const b = await T.abrir();
  const maria = await T.entrar(b, 'Maria', 'maria1234');
  await T.assumir(maria, 'pa:1', '100', { envelhecer: true });
  const adm = await T.entrar(b, 'Fabio', 'senha-forte-123', { largura: 1280, altura: 1000 });

  async function incluir(funcao, marca, codigo, qtd, foto) {
    await adm.goto(BASE + '/cadastros/pas/1');
    await adm.click('#pecas > details.anular > summary');
    const f = '#pecas > details.anular form';
    await adm.fill(f + ' input[name=funcao]', funcao);
    await adm.fill(f + ' input[name=marca]', marca);
    await adm.fill(f + ' input[name=codigo]', codigo);
    await adm.fill(f + ' input[name=quantidade]', qtd);
    if (foto) await adm.setInputFiles(f + ' input[name=foto]', { name: 'filtro.png', mimeType: 'image/png', buffer: PNG });
    await enviar(adm, f + ' button[type=submit]');
  }
  await incluir('Filtro de óleo do motor', 'Fleetguard', 'LF3349', '1', true);
  await incluir('Filtro de ar', 'Donaldson', 'P181052', '2', false);
  ok(sql("SELECT COUNT(*) FROM maquina_pecas WHERE tipo='pa' AND maquina_id=1") === '2', 'duas peças incluídas na Pá 1');
  const idOleo = sql("SELECT id FROM maquina_pecas WHERE codigo='LF3349'");
  ok(/^pecas\/\d+\.jpg$/.test(sql(`SELECT foto FROM maquina_pecas WHERE id=${idOleo}`)), 'foto da peça guardada em pecas/');
  ok(await adm.locator('#pecas img.miniatura').count() === 1, 'ficha mostra a miniatura da peça');

  // Pedido sem peça marcada é recusado.
  await adm.goto(BASE + '/cadastros/pas/1/pedidos/novo');
  await enviar(adm, 'form.formulario button[type=submit]');
  ok(/Marque ao menos uma peça/.test(await txt(adm)) && sql('SELECT COUNT(*) FROM pecas_pedidos') === '0', 'pedido sem peça é recusado');

  // Pedido com as duas, confirmado com Maria.
  await adm.goto(BASE + '/cadastros/pas/1/pedidos/novo');
  for (const c of await adm.locator('input[name^="pecas["]').all()) await c.check();
  await adm.selectOption('select[name=confirmado_com]', 'Maria');
  await adm.fill('input[name=observacoes]', 'urgente');
  await enviar(adm, 'form.formulario button[type=submit]');
  const idPedido = (adm.url().match(/\/pedidos-pecas\/(\d+)$/) || [])[1];
  ok(!!idPedido, 'pedido gravado e aberto', adm.url());
  ok(sql(`SELECT CONCAT(horimetro,'|',pedido_por,'|',confirmado_com,'|',situacao) FROM pecas_pedidos WHERE id=${idPedido}`) === '100.0|Fabio|Maria|enviado', 'pedido com horímetro, quem pediu e confirmado com');
  const href = await adm.getAttribute('a:has-text("Enviar para o Compras")', 'href');
  const msg = decodeURIComponent((href || '').split('text=')[1] || '');
  const codigo = sql(`SELECT codigo_publico FROM pecas_pedidos WHERE id=${idPedido}`);
  ok(/^https:\/\/wa\.me\/\?text=/.test(href || ''), 'botão abre o WhatsApp', href);
  ok(msg.includes('*Pedido de peças — Pá 1*') && msg.includes('Horímetro: 100,0 h') && msg.includes('Confirmado com: Maria')
    && msg.includes('1. Filtro de ar — Donaldson P181052 (2 un)') && msg.includes('2. Filtro de óleo do motor — Fleetguard LF3349 (1 un)')
    && msg.includes('Obs.: urgente'), 'mensagem no formato do Compras', msg);
  ok(/^[0-9a-f]{32}$/.test(codigo) && msg.includes('Fotos: ' + BASE + '/compras/' + codigo), 'mensagem tem o link público das fotos', msg);
  await T.capturar(adm, 'pecas_pedido', { fullPage: true });

  // Página pública, sem login.
  const ctx = await b.newContext();
  const pub = T.vigiar(await ctx.newPage(), 'publico');
  let r = await pub.goto(BASE + '/compras/' + codigo);
  let tela = await txt(pub);
  ok(r.status() === 200 && /Filtro de óleo do motor/.test(tela) && /LF3349/.test(tela) && /P181052/.test(tela), 'página pública abre sem login com os itens', r.status() + ' ' + tela.slice(0, 300));
  ok(await pub.locator('nav.menu').count() === 0, 'página pública sem menu');
  const src = await pub.getAttribute('main img.foto-atual', 'src');
  const foto = src ? await ctx.request.get(BASE + src) : null;
  ok(foto && foto.status() === 200 && /^image\//.test(foto.headers()['content-type'] || ''), 'foto da peça abre pelo link público', src);
  await T.capturar(pub, 'pecas_compras', { fullPage: true });
  ok((await pub.goto(BASE + '/compras/' + '0'.repeat(32))).status() === 404, 'código inexistente: 404');
  ok((await pub.goto(BASE + '/compras/abc')).status() === 404, 'código fora do formato: 404');
  ok((await ctx.request.get(BASE + '/compras/' + codigo + '/fotos/99999')).status() === 404, 'foto de item de outro pedido: 404');
  r = await ctx.request.get(BASE + '/pedidos-pecas/' + idPedido, { maxRedirects: 0 });
  ok(r.status() === 302, 'sem login, a página interna do pedido manda para a entrada', r.status());

  // O operador não abre as telas do administrativo.
  for (const caminho of ['/pedidos-pecas', '/pedidos-pecas/' + idPedido, '/cadastros/pas/1/pedidos/novo']) {
    ok((await maria.goto(BASE + caminho)).status() === 403, 'operador recebe 403 em ' + caminho);
  }

  // Peças trocadas: grava data, horímetro e quem marcou; a peça mostra a última troca.
  await adm.goto(BASE + '/pedidos-pecas/' + idPedido);
  await enviar(adm, 'button:has-text("Peças trocadas")');
  ok(sql(`SELECT CONCAT(situacao,'|',trocado_horimetro,'|',trocado_por,'|',trocado_em IS NOT NULL) FROM pecas_pedidos WHERE id=${idPedido}`) === 'trocado|100.0|Fabio|1', 'trocado com horímetro e quem marcou');
  await adm.goto(BASE + '/cadastros/pas/1');
  tela = await txt(adm);
  ok(/LF3349 · 1 un · última troca em [\d/]+ [\d:]+, 100,0 h/.test(tela), 'peça mostra a última troca', tela.slice(tela.indexOf('Peças'), tela.indexOf('Peças') + 400));

  // Editar a peça não muda o item do pedido.
  await adm.click('#pecas li:has-text("Filtro de óleo") details.anular > summary');
  await adm.fill('#pecas li:has-text("Filtro de óleo") input[name=codigo]', 'LF9999');
  await enviar(adm, '#pecas li:has-text("Filtro de óleo") button:has-text("Salvar peça")');
  ok(sql(`SELECT codigo FROM maquina_pecas WHERE id=${idOleo}`) === 'LF9999', 'peça editada');
  ok(sql(`SELECT codigo FROM pecas_pedido_itens WHERE peca_id=${idOleo}`) === 'LF3349', 'item do pedido continua com o código antigo');
  await adm.goto(BASE + '/pedidos-pecas/' + idPedido);
  ok(/LF3349/.test(await txt(adm)), 'página do pedido mostra o código da hora do pedido');

  // Lista na Manutenção.
  await adm.goto(BASE + '/manutencao');
  await Promise.all([adm.waitForNavigation(), adm.click('a:has-text("Pedidos de peças")')]);
  ok(/Pá 1/.test(await txt(adm)) && /Trocado/.test(await txt(adm)), 'lista de pedidos a partir da Manutenção');
  T.resumo();
});
