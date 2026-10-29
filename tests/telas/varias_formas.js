// Nota paga em mais de uma forma: o administrativo lança Pix R$ 1.000,00 + Cartão R$ 905,60 ("+ Outra forma de
// pagamento"), a soma errada é recusada sem perder o que foi digitado, a ficha mostra as duas e corrige depois.
const T = require('../lib/teste');
const { ok, sql, txt, enviar, BASE } = T;
const F = 'form[action$="/ordens/nova"]';

T.executar(async () => {
  const b = await T.abrir();
  const adm = await T.entrar(b, 'Fabio', 'senha-forte-123', { largura: 1280, altura: 1100 });
  await adm.goto(BASE + '/ordens/nova');
  await adm.fill(F + ' [name=cliente]', 'Obra Sul');
  await adm.fill(F + ' [name=pedido]', 'P-90');
  await adm.fill(F + ' [name=nf]', '9090');
  await adm.fill(F + ' [name=nf_valor]', '1.905,60');
  await adm.selectOption(F + ' [name=nf_pagamento]', 'pix');
  await adm.selectOption(F + ' select[name=material_id]', { label: 'Brita 1' });
  await adm.fill(F + ' [name=quantidade_autorizada]', '10');
  await adm.click(F + ' details.outras-formas summary');
  await adm.fill(F + ' [name="pagamentos[0][valor]"]', '1.000,00');
  await adm.selectOption(F + ' [name="pagamentos[1][forma]"]', 'cartao');
  await adm.fill(F + ' [name="pagamentos[1][valor]"]', '900,00');
  await enviar(adm, F + ' button[type=submit]');
  ok(/não bate com o valor da nota/.test(await txt(adm)) && sql("SELECT COUNT(*) FROM fila_carregamentos WHERE nf='9090'") === '0', 'soma errada: recusa');
  ok(await adm.inputValue(F + ' [name="pagamentos[1][valor]"]') === '900,00' && await adm.isVisible(F + ' [name="pagamentos[1][valor]"]'), 'o que foi digitado continua na tela');
  await adm.fill(F + ' [name="pagamentos[1][valor]"]', '905,60');
  await T.capturar(adm, 'varias_formas_form', { fullPage: true });
  await enviar(adm, F + ' button[type=submit]');
  const id = sql("SELECT id FROM fila_carregamentos WHERE nf='9090'");
  ok(id !== '' && /Forma de pagamento Pix R\$ 1\.000,00 \+ Cartão R\$ 905,60/.test(await txt(adm)), 'a ficha mostra as duas formas com os valores', (await txt(adm)).slice(0, 600));

  // Corrigir na ficha, depois de a pá carregar (o formulário de "Corrigir dados" fica travado).
  sql(`UPDATE fila_carregamentos SET status='aguardando_conferencia', fim_carregamento=NOW() WHERE id=${id}`);
  await adm.goto(BASE + '/ordens/' + id);
  const V = `form[action$="/ordens/${id}/valor"]`;
  await adm.click(V + ' details.outras-formas summary').catch(() => {});
  await adm.selectOption(V + ' [name="pagamentos[1][forma]"]', 'dinheiro');
  await enviar(adm, V + ' button[type=submit]');
  ok(/Pix R\$ 1\.000,00 \+ Dinheiro R\$ 905,60/.test(await txt(adm)), 'corrigir na ficha troca a forma');
  T.resumo();
});
