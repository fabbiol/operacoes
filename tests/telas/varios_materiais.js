// Carga com mais de um material: o administrativo lança a nota com Brita 1 e Pó de pedra (+ Outro material),
// a pá vê os dois materiais no cartão do pátio, carrega e finaliza uma vez só; a ficha e o Controle mostram os dois.
const T = require('../lib/teste');
const { ok, sql, txt, enviar, BASE } = T;
const F = 'form[action$="/ordens/nova"]';

T.executar(async () => {
  const b = await T.abrir();
  const adm = await T.entrar(b, 'Fabio', 'senha-forte-123', { largura: 1280, altura: 1100 });
  await adm.goto(BASE + '/ordens/nova');
  await adm.fill(F + ' [name=cliente]', 'Obra Delta');
  await adm.fill(F + ' [name=pedido]', 'P-77');
  await adm.fill(F + ' [name=nf]', '7777');
  await adm.fill(F + ' [name=nf_valor]', '900,00');
  await adm.selectOption(F + ' select[name=material_id]', { label: 'Brita 1' });
  await adm.fill(F + ' [name=quantidade_autorizada]', '10');
  await adm.selectOption(F + ' select[name=unidade]', 't');
  await adm.click(F + ' details.outros-materiais summary');
  await adm.selectOption(F + ' select[name="outros[0][material_id]"]', { label: 'Pó de pedra' });
  await adm.fill(F + ' [name="outros[0][quantidade]"]', '5');
  await adm.selectOption(F + ' [name=nf_pagamento]', 'pix');
  await adm.check(F + ' [name=liberar]');
  await T.capturar(adm, 'varios_materiais_form', { fullPage: true });
  await enviar(adm, F + ' button[type=submit]');
  const id = sql("SELECT id FROM fila_carregamentos WHERE nf='7777'");
  ok(id !== '' && sql(`SELECT CONCAT(om.quantidade, ' ', m.material) FROM ordem_materiais om JOIN materiais m ON m.id = om.material_id WHERE om.ordem_id = ${id}`) === '5.00 Pó de pedra', 'ordem lançada com o segundo material');
  ok(/10,00 t · Brita 1 \+ 5,00 t · Pó de pedra/.test(await txt(adm)), 'a ficha mostra os dois materiais');

  const maria = await T.entrar(b, 'Maria', 'maria1234');
  await T.assumir(maria, 'pa:1', '100', { envelhecer: true });
  await maria.goto(BASE + '/patio');
  const cartao = (await maria.locator('article.cartao', { hasText: '7777' }).innerText()).replace(/\s+/g, ' ');
  ok(/10,00 t · Brita 1 \+ 5,00 t · Pó de pedra/.test(cartao), 'o cartão do pátio mostra os dois materiais, um embaixo do outro', cartao);
  await T.capturar(maria, 'varios_materiais_patio', { fullPage: true });
  await maria.goto(BASE + `/carregamentos/${id}/iniciar`);
  await enviar(maria, `form[action$="/carregamentos/${id}/iniciar"] button[type=submit]`);
  await maria.goto(BASE + `/carregamentos/${id}/finalizar`);
  ok(await maria.locator('input[name=quantidade]').count() === 0, 'finalizar não pede quantidade: a carga sai inteira');
  await enviar(maria, `form[action$="/carregamentos/${id}/finalizar"] button[type=submit]`);
  ok(sql(`SELECT status FROM fila_carregamentos WHERE id = ${id}`) === 'aguardando_conferencia', 'carga finalizada uma vez só');

  await adm.goto(BASE + '/controle');
  const controle = await txt(adm);
  ok(/NF 7777.*Brita 1 \+ Pó de pedra\s*15,00 t\s*15,00 t/.test(controle), 'o Controle mostra os dois materiais e soma 15 t', controle.slice(controle.indexOf('NF 7777'), controle.indexOf('NF 7777') + 200));
  T.resumo();
});
