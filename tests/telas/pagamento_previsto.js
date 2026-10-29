// Venda paga depois (prefeitura): o administrativo lança a nota em Transferência com "Pagamento previsto para"
// pelo botão +15 dias, a ficha mostra o dia, e na ficha o +30 dias conta a partir do dia da nota.
const T = require('../lib/teste');
const { ok, sql, txt, enviar, BASE } = T;
const F = 'form[action$="/ordens/nova"]';
const somar = (dia, n) => {
  const d = new Date(dia + 'T12:00:00Z');
  d.setUTCDate(d.getUTCDate() + n);
  return d.toISOString().slice(0, 10);
};
const br = (dia) => dia.split('-').reverse().join('/');

T.executar(async () => {
  const b = await T.abrir();
  const adm = await T.entrar(b, 'Fabio', 'senha-forte-123', { largura: 1280, altura: 1100 });
  await adm.goto(BASE + '/ordens/nova');
  await adm.fill(F + ' [name=cliente]', 'Prefeitura Municipal');
  await adm.fill(F + ' [name=pedido]', 'P-15');
  await adm.fill(F + ' [name=nf]', '7070');
  await adm.fill(F + ' [name=nf_valor]', '4.000,00');
  await adm.selectOption(F + ' [name=nf_pagamento]', 'transferencia');
  await adm.selectOption(F + ' select[name=material_id]', { label: 'Brita 1' });
  await adm.fill(F + ' [name=quantidade_autorizada]', '10');
  const dia = (await adm.inputValue(F + ' [name=data]')).slice(0, 10);
  await adm.click(F + ' [data-prazo="15"]');
  const previsto = await adm.inputValue(F + ' [name=nf_previsto]');
  ok(previsto === somar(dia, 15), '+15 dias a partir do dia da ordem (sem a emissão da nota)', previsto + ' ' + dia);
  await T.capturar(adm, 'pagamento_previsto_form', { fullPage: true });
  await enviar(adm, F + ' button[type=submit]');
  const id = sql("SELECT id FROM fila_carregamentos WHERE nf='7070'");
  ok(id !== '' && sql(`SELECT nf_previsto FROM fila_carregamentos WHERE id=${id}`) === previsto, 'grava o dia previsto');
  ok(new RegExp('Pagamento previsto para\s*' + br(previsto)).test(await txt(adm)), 'a ficha mostra o dia previsto', (await txt(adm)).slice(0, 600));

  // Na ficha, depois de a pá carregar: +30 dias a partir do dia da nota.
  sql(`UPDATE fila_carregamentos SET status='aguardando_conferencia', fim_carregamento=NOW(), nf_emitida_data='2026-10-01' WHERE id=${id}`);
  await adm.goto(BASE + '/ordens/' + id);
  const V = `form[action$="/ordens/${id}/valor"]`;
  ok(await adm.inputValue(V + ' [name=nf_previsto]') === previsto, 'o quadro da ficha vem com o dia previsto');
  await adm.click(V + ' [data-prazo="30"]');
  ok(await adm.inputValue(V + ' [name=nf_previsto]') === '2026-10-31', '+30 dias a partir do dia da nota', await adm.inputValue(V + ' [name=nf_previsto]'));
  await enviar(adm, V + ' button[type=submit]');
  ok(sql(`SELECT nf_previsto FROM fila_carregamentos WHERE id=${id}`) === '2026-10-31' && /Pagamento previsto para\s*31\/10\/2026/.test(await txt(adm)), 'corrigir na ficha troca o dia previsto');
  T.resumo();
});
