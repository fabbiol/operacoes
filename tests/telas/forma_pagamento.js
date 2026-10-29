// Forma de pagamento da nota: obrigatória para liberar (nota em PDF não traz a forma), lida do XML, corrigida na ficha
// e somada por forma no vendido do dia do Monitor. Nota antiga sem forma entra em "sem forma".
const T = require('../lib/teste');
const { ok, sql, txt, enviar, BASE } = T;
const F = 'form[action$="/ordens/nova"]';
T.executar(async () => {
  const b = await T.abrir();
  const p = await T.entrar(b, 'Fabio', 'senha-forte-123', { largura: 1280, altura: 1000 });

  // Lançada à mão e marcada para liberar, sem forma: recusa e mantém o que foi digitado.
  await p.goto(BASE + '/ordens/nova');
  await p.fill(F + ' [name=cliente]', 'Cliente Pix');
  await p.fill(F + ' [name=pedido]', 'P-9');
  await p.fill(F + ' [name=nf]', '9001');
  await p.fill(F + ' [name=nf_valor]', '1.200,00');
  await p.selectOption(F + ' select[name=material_id]', { label: 'Brita 1' });
  await p.fill(F + ' [name=quantidade_autorizada]', '10');
  await p.check(F + ' [name=liberar]');
  await enviar(p, F + ' button[type=submit]');
  ok(/Escolha a forma de pagamento da nota antes de liberar/.test(await txt(p)) && sql("SELECT COUNT(*) FROM fila_carregamentos WHERE nf='9001'") === '0', 'liberar sem forma é recusado');
  ok(await p.inputValue(F + ' [name=cliente]') === 'Cliente Pix', 'o que foi digitado continua na tela');
  await p.selectOption(F + ' [name=nf_pagamento]', 'pix');
  await p.check(F + ' [name=liberar]');
  await enviar(p, F + ' button[type=submit]');
  ok(sql("SELECT CONCAT(status,'|',nf_pagamento) FROM fila_carregamentos WHERE nf='9001'") === 'liberado|pix', 'com Pix, libera e grava a forma');
  ok(/Forma de pagamento\s*Pix/.test(await txt(p)), 'a ficha mostra a forma', (await txt(p)).slice(0, 300));

  // Lançada sem liberar e sem forma: o Liberar da ficha também pede a forma.
  await p.goto(BASE + '/ordens/nova');
  await p.fill(F + ' [name=cliente]', 'Cliente Dinheiro');
  await p.fill(F + ' [name=pedido]', 'P-10');
  await p.fill(F + ' [name=nf]', '9002');
  await p.fill(F + ' [name=nf_valor]', '300,00');
  await p.selectOption(F + ' select[name=material_id]', { label: 'Brita 1' });
  await p.fill(F + ' [name=quantidade_autorizada]', '5');
  await enviar(p, F + ' button[type=submit]');
  const id2 = sql("SELECT id FROM fila_carregamentos WHERE nf='9002'");
  await enviar(p, `form[action$="/ordens/${id2}/liberar"] button[type=submit]`);
  ok(/Escolha a forma de pagamento/.test(await txt(p)) && sql(`SELECT status FROM fila_carregamentos WHERE id=${id2}`) === 'aguardando_autorizacao', 'Liberar na ficha sem forma é recusado');

  // Concluída: a forma é corrigida no quadro do valor da ficha.
  sql(`UPDATE fila_carregamentos SET status='concluido' WHERE id=${id2}`);
  await p.goto(BASE + '/ordens/' + id2);
  await p.selectOption('form.valor-nota select[name=nf_pagamento]', 'dinheiro');
  await enviar(p, 'form.valor-nota button[type=submit]');
  ok(sql(`SELECT CONCAT(nf_valor,'|',nf_pagamento) FROM fila_carregamentos WHERE id=${id2}`) === '300.00|dinheiro', 'ficha corrige a forma para Dinheiro');

  // Vendido por forma: as notas de exemplo, sem forma, ficam em "sem_forma".
  const vendas = (await (await p.request.get(BASE + '/dashboard/dados')).json()).vendas;
  ok(vendas.por_forma.pix === 1200 && vendas.por_forma.dinheiro === 300 && 'sem_forma' in vendas.por_forma, 'vendido do dia dividido por forma', JSON.stringify(vendas));
  T.resumo();
});
