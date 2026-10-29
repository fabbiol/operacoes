// Venda com empenho (prefeitura): o administrativo lança a ordem sem a nota, com o PDF do empenho; a forma vem como
// Transferência; a ficha e o Painel mostram "Aguardando nota fiscal"; depois a nota é lida do XML na ficha e lançada.
const path = require('path');
const T = require('../lib/teste');
const { ok, sql, txt, enviar, BASE } = T;
const F = 'form[action$="/ordens/nova"]';

T.executar(async () => {
  const b = await T.abrir();
  const adm = await T.entrar(b, 'Fabio', 'senha-forte-123', { largura: 1280, altura: 1100 });
  await adm.goto(BASE + '/ordens/nova');
  await adm.fill(F + ' [name=cliente]', 'Prefeitura Municipal');
  await adm.fill(F + ' [name=pedido]', 'EMP-2026/15');
  await adm.check(F + ' [name=nota_depois]');
  ok(await adm.inputValue(F + ' [name=nf_pagamento]') === 'transferencia', 'marcar "Nota fiscal sai depois" já escolhe Transferência');
  ok(!(await adm.$eval(F + ' [name=nf]', (c) => c.required)), 'a nota deixa de ser obrigatória');
  await adm.setInputFiles(F + ' [name=empenho]', { name: 'empenho.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n%%EOF\n') });
  await adm.selectOption(F + ' select[name=material_id]', { label: 'Brita 1' });
  await adm.fill(F + ' [name=quantidade_autorizada]', '10');
  await adm.check(F + ' [name=liberar]');
  await T.capturar(adm, 'empenho_form', { fullPage: true });
  await enviar(adm, F + ' button[type=submit]');
  const id = sql("SELECT id FROM fila_carregamentos WHERE pedido='EMP-2026/15'");
  ok(id !== '' && sql(`SELECT CONCAT(nf, '|', status, '|', empenho_arquivo) FROM fila_carregamentos WHERE id=${id}`) === `|liberado|empenhos/${id}.pdf`, 'lança liberada, sem a nota e com o empenho');
  ok(/Aguardando nota fiscal/.test(await txt(adm)) && /Ver o empenho/.test(await txt(adm)), 'a ficha mostra que espera a nota e o empenho');
  const empenho = await adm.request.get(BASE + '/ordens/' + id + '/empenho');
  ok(empenho.status() === 200 && empenho.headers()['content-type'] === 'application/pdf', 'o empenho abre');

  await adm.goto(BASE + '/fila');
  ok(/NF a emitir \(empenho\)/.test(await txt(adm)), 'a fila mostra "NF a emitir"');
  await adm.goto(BASE + '/painel');
  ok(/Aguardando nota fiscal\s*1/.test(await txt(adm)) && /Pedido\/empenho EMP-2026\/15/.test(await txt(adm)), 'o Painel lista a venda que espera a nota');

  // A nota chega: lida do XML na ficha, conferida e lançada com o pagamento em 30 dias.
  await adm.goto(BASE + '/ordens/' + id);
  await adm.setInputFiles('#nota-fiscal form.ler-nota input[type=file]', path.join(T.NOTAS, 'nota2.xml'));
  await enviar(adm, '#nota-fiscal form.ler-nota button[type=submit]');
  const L = '#nota-fiscal form.lancar-nota';
  ok(await adm.inputValue(L + ' [name=nf]') === '12346' && await adm.inputValue(L + ' [name=nf_valor]') === '720,00', 'a leitura preenche número e valor', await adm.inputValue(L + ' [name=nf]'));
  await adm.click(L + ' [data-prazo="30"]');
  ok(await adm.inputValue(L + ' [name=nf_previsto]') === '2026-11-14', '+30 dias contados do dia da nota', await adm.inputValue(L + ' [name=nf_previsto]'));
  await T.capturar(adm, 'empenho_lancar', { fullPage: true });
  await enviar(adm, L + ' button[type=submit]');
  ok(sql(`SELECT CONCAT(nf, '|', nf_valor, '|', nf_previsto) FROM fila_carregamentos WHERE id=${id}`) === '12346|720.00|2026-11-14', 'grava a nota e o pagamento previsto');
  ok(/Nota fiscal\s*12346/.test(await txt(adm)) && !/Aguardando nota fiscal/.test(await txt(adm)), 'a ficha passa a mostrar a nota', (await txt(adm)).slice(0, 500));
  await adm.goto(BASE + '/painel');
  ok(!/Aguardando nota fiscal/.test(await txt(adm)), 'sai do Painel');
  T.resumo();
});
