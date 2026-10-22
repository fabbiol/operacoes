// Pátio de ponta a ponta: nota só com a data da emissão, correção do carregado (fracionado e saldo), observações,
// aviso de "carga alterada" no tablet, viagem que volta para Saldo a buscar. Duas operadoras (Maria e Pedro) e o administrativo.
const T = require('../lib/teste');
const fs = require('fs');
const path = require('path');
const { ok, sql, espera, txt, BASE } = T;
const F = 'form[action$="/ordens/nova"]';
const entrar = (b, u, s, w) => T.entrar(b, u, s, { largura: w || 800, altura: 1100 });
const enviar = T.enviar;
const assumir = (p, valor, leitura) => T.assumir(p, valor, leitura, { envelhecer: true });
async function iniciar(p, id) {
  await p.goto(BASE + `/carregamentos/${id}/iniciar`);
  await enviar(p, `form[action$="/carregamentos/${id}/iniciar"] button[type=submit]`);
}
async function finalizar(p, id, qtd) {
  await p.goto(BASE + `/carregamentos/${id}/finalizar`);
  if (qtd !== undefined && await p.locator('input[name=quantidade]').count()) await p.fill('input[name=quantidade]', qtd);
  await enviar(p, `form[action$="/carregamentos/${id}/finalizar"] button[type=submit]`);
}
async function corrigir(adm, id, qtd) {
  await adm.goto(BASE + '/ordens/' + id);
  await adm.click('details.corrigir-carregado summary');
  await adm.fill('form[action$="/carregado"] input[name=quantidade]', qtd);
  await enviar(adm, 'form[action$="/carregado"] button[type=submit]');
  return (await adm.locator('.aviso').allInnerTexts()).join(' | ');
}
async function novaOrdem(adm, nf, qtd, unidade, fracionado, obs) {
  await adm.goto(BASE + '/ordens/nova');
  await adm.fill(F + ' [name=cliente]', 'Cliente ' + nf);
  await adm.fill(F + ' [name=pedido]', 'P-' + nf);
  await adm.fill(F + ' [name=nf]', nf);
  await adm.fill(F + ' [name=nf_valor]', '100,00');
  await adm.selectOption(F + ' select[name=material_id]', { label: 'Brita 1' });
  await adm.fill(F + ' [name=quantidade_autorizada]', qtd);
  await adm.selectOption(F + ' select[name=unidade]', unidade);
  if (fracionado) await adm.check(F + ' [name=fracionado]');
  if (obs) await adm.fill(F + ' [name=observacoes]', obs);
  await adm.selectOption(F + ' [name=nf_pagamento]', 'pix');
  await adm.check(F + ' [name=liberar]');
  await enviar(adm, F + ' button[type=submit]');
  return sql(`SELECT id FROM fila_carregamentos WHERE nf='${nf}' AND ordem_principal_id IS NULL`);
}
T.executar(async () => {
  const b = await T.abrir();
  const adm = await entrar(b, 'Fabio', 'senha-forte-123', 1280);
  sql("UPDATE fila_carregamentos SET status='cancelado'"); // tira as ordens de exemplo da fila

  // 1. Nota só com a data, sem hora (leiaute antigo do XML): a nota conta no dia da emissão, não no dia do lançamento.
  // A data é de 3 dias atrás para nunca coincidir com o dia do lançamento.
  const dataXml = T.hoje(-3);
  const xmlSoData = path.join(T.TMP, 'nota_so_data.xml');
  fs.writeFileSync(xmlSoData, fs.readFileSync(path.join(T.NOTAS, 'nota_so_data.xml'), 'utf8').replace(/<dEmi>[^<]*<\/dEmi>/, '<dEmi>' + dataXml + '</dEmi>'));
  await adm.goto(BASE + '/ordens/nova');
  await adm.setInputFiles('form.ler-nota input[type=file]', xmlSoData);
  await enviar(adm, 'form.ler-nota button[type=submit]');
  const dataLida = await adm.inputValue(F + ' [name=nf_emitida_data]');
  ok(dataLida === dataXml && (await adm.inputValue(F + ' [name=nf_emitida_em]')) === '', 'nota sem hora: a data da nota vai no campo escondido', dataLida);
  ok(/conta no vendido desse dia/.test(await txt(adm)), 'formulário explica que a nota conta no dia da emissão');
  await adm.selectOption(F + ' [name=nf_pagamento]', 'pix');
  await adm.check(F + ' [name=liberar]');
  await enviar(adm, F + ' button[type=submit]');
  const idSoData = sql("SELECT id FROM fila_carregamentos WHERE nf='4410'");
  ok(sql(`SELECT CONCAT(IFNULL(nf_emitida_em,'-'),'|',IFNULL(nf_emitida_data,'-')) FROM fila_carregamentos WHERE id=${idSoData}`) === '-|' + dataXml, 'gravou a data da emissão sem hora', sql(`SELECT CONCAT(IFNULL(nf_emitida_em,'-'),'|',IFNULL(nf_emitida_data,'-')) FROM fila_carregamentos WHERE id=${idSoData}`));
  await adm.goto(BASE + '/controle?dia=' + dataXml);
  ok(/NF 4410/.test(await txt(adm)) && /Vendido no dia R\$ 512,30/.test(await txt(adm)), 'Controle do dia da emissão mostra a nota e o valor', (await txt(adm)).slice(0, 200));
  const hoje = sql("SELECT DATE(NOW())");
  if (hoje !== dataXml) {
    await adm.goto(BASE + '/controle?dia=' + hoje);
    ok(!/NF 4410/.test(await txt(adm)), 'e não no dia do lançamento');
  }
  await adm.goto(BASE + '/ordens/' + idSoData);
  ok(new RegExp('4410 · emitida em ' + T.brData(dataXml)).test(await txt(adm)), 'ficha mostra a data da emissão');
  sql(`UPDATE fila_carregamentos SET status='cancelado' WHERE id=${idSoData}`);

  // 2. Operadores: Maria na Pá 1, Pedro na Pá 2.
  const maria = await entrar(b, 'Maria', 'maria1234');
  await assumir(maria, 'pa:1', '100');
  const pedro = await entrar(b, 'Pedro', 'pedro1234');
  await assumir(pedro, 'pa:2', '200');

  // 3. Ordem normal de 10 t, finalizada com 10; o administrativo corrige para 6: vira fracionada e o saldo de 4 vai para Saldo a buscar.
  const idA = await novaOrdem(adm, '9001', '10', 't', false, 'Cliente quer brita lavada');
  await maria.goto(BASE + '/patio');
  ok(/Obs\.: Cliente quer brita lavada/.test(await txt(maria)), 'cartão do pátio mostra a observação');
  // Um toque no cartão do pátio já inicia, com a pá do turno: sem a segunda tela.
  // A carga chegou depois que a Maria abriu o pátio: antes, o aviso de carga nova pede o Ciente.
  await maria.locator('.avisos-patio:not([hidden]) button:has-text("Ciente")').click({ timeout: 5000 });
  await enviar(maria, `form[action$="/carregamentos/${idA}/iniciar"] button[type=submit]`);
  const iniciada = sql(`SELECT CONCAT(status,'|',pa_carregadeira_id,'|',operador) FROM fila_carregamentos WHERE id=${idA}`);
  ok(/\/patio$/.test(maria.url()) && iniciada === 'carregando|1|Maria', 'um toque em "Iniciar carregamento" no pátio já inicia a carga', maria.url() + ' ' + iniciada);
  // Pedro não finaliza a carga da Maria.
  await pedro.goto(BASE + '/patio');
  ok(/Carregando com Maria: só quem iniciou finaliza/.test(await txt(pedro)) && await pedro.locator(`a[href$="/carregamentos/${idA}/finalizar"]`).count() === 0, 'pátio do Pedro: sem "Finalizar" na carga da Maria');
  await pedro.goto(BASE + `/carregamentos/${idA}/finalizar`);
  ok(/\/patio$/.test(pedro.url()) && /Esta carga está com Maria/.test(await txt(pedro)), 'Pedro abrindo o link de finalizar volta ao pátio com aviso', pedro.url());
  await maria.goto(BASE + `/carregamentos/${idA}/finalizar`);
  ok(/Observações Cliente quer brita lavada/.test(await txt(maria)), 'tela de finalizar mostra as observações');
  await finalizar(maria, idA);
  ok(sql(`SELECT status FROM fila_carregamentos WHERE id=${idA}`) === 'aguardando_conferencia', 'Maria finalizou');
  let msg = await corrigir(adm, idA, '6');
  const filhaA = sql(`SELECT CONCAT(id,'|',status,'|',quantidade_autorizada) FROM fila_carregamentos WHERE ordem_principal_id=${idA}`);
  ok(/virou a viagem/.test(msg) && /^\d+\|saldo\|4\.00$/.test(filhaA) && sql(`SELECT CONCAT(fracionado,'|',quantidade_carregada) FROM fila_carregamentos WHERE id=${idA}`) === '1|6.00', 'corrigir 10→6: vira fracionado e cria o saldo de 4 t', msg + ' / ' + filhaA);
  const idA2 = filhaA.split('|')[0];
  await maria.goto(BASE + '/patio');
  ok(/6,00 t · Brita 1/.test(await maria.textContent('.enviadas')), 'Enviadas hoje mostra o que saiu (6 t), não o autorizado', (await maria.textContent('.enviadas')).replace(/\s+/g, ' ').slice(0, 200));
  msg = await corrigir(adm, idA, '7');
  ok(sql(`SELECT quantidade_autorizada FROM fila_carregamentos WHERE id=${idA2}`) === '3.00', 'corrigir 6→7: o saldo pendente vira 3 t', msg);
  msg = await corrigir(adm, idA, '10');
  ok(sql(`SELECT status FROM fila_carregamentos WHERE id=${idA2}`) === 'cancelado' && /foi encerrada/.test(msg), 'corrigir para 10: a nota fica toda carregada e a viagem de saldo é encerrada', msg);
  msg = await corrigir(adm, idA, '12');
  ok(/Informe no máximo isso/.test(msg) && sql(`SELECT quantidade_carregada FROM fila_carregamentos WHERE id=${idA}`) === '10.00', 'mais que o autorizado é recusado', msg);
  msg = await corrigir(adm, idA, '8');
  const idA3 = sql(`SELECT id FROM fila_carregamentos WHERE ordem_principal_id=${idA} AND status = 'saldo'`);
  ok(/virou a viagem/.test(msg) && idA3 !== '', 'nota que estava toda carregada e é corrigida para menos: o saldo volta', msg);
  await adm.goto(BASE + `/ordens/${idA3}/cancelar`);
  await adm.fill('textarea[name=motivo], input[name=motivo]', 'Cliente não vem mais');
  await enviar(adm, 'form[action$="/cancelar"] button[type=submit]');
  ok(/Saldo encerrado\./.test(await txt(adm)), 'mensagem "Saldo encerrado."');
  msg = await corrigir(adm, idA, '7');
  ok(/já estava encerrado/.test(msg) && sql(`SELECT COUNT(*) FROM fila_carregamentos WHERE ordem_principal_id=${idA} AND status <> 'cancelado'`) === '0', 'saldo encerrado pelo administrativo não reabre sozinho', msg);

  // 4. Fracionado 3 m³: viagem 1 com 0,5; a viagem 2 carregando bloqueia a correção.
  const idB = await novaOrdem(adm, '9002', '3', 'm3', true, '');
  await iniciar(maria, idB);
  await finalizar(maria, idB, '0,5');
  const idB2 = sql(`SELECT id FROM fila_carregamentos WHERE ordem_principal_id=${idB}`);
  await adm.goto(BASE + '/ordens/' + idB);
  ok(await adm.locator(`a[href$="/ordens/${idB2}/cancelar"]`).count() === 1 && !(await adm.locator(`a[href$="/ordens/${idB}/cancelar"]`).count()), 'viagem 1 com saldo aberto: botão "Encerrar saldo" aponta para a viagem 2');
  await adm.goto(BASE + `/ordens/${idB}/cancelar`);
  await adm.fill('textarea[name=motivo], input[name=motivo]', 'teste');
  await enviar(adm, 'form[action$="/cancelar"] button[type=submit]');
  ok(/Para encerrar o saldo, use "Encerrar saldo"/.test(await txt(adm)) && sql(`SELECT status FROM fila_carregamentos WHERE id=${idB}`) === 'aguardando_conferencia', 'cancelar a viagem 1 com saldo aberto é recusado');
  msg = await corrigir(adm, idB, '1');
  ok(sql(`SELECT quantidade_autorizada FROM fila_carregamentos WHERE id=${idB2}`) === '2.00', 'corrigir a viagem 1 de 0,5 para 1: saldo da viagem 2 vira 2 m³', msg);
  // Libera a viagem 2 e o cliente não vem: volta para Saldo a buscar.
  await adm.goto(BASE + '/ordens/' + idB2);
  await enviar(adm, `form[action$="/ordens/${idB2}/liberar"] button[type=submit]`);
  ok(sql(`SELECT status FROM fila_carregamentos WHERE id=${idB2}`) === 'liberado', 'viagem 2 liberada');
  await adm.goto(BASE + '/ordens/' + idB2);
  await enviar(adm, `form[action$="/ordens/${idB2}/saldo"] button[type=submit]`);
  ok(sql(`SELECT CONCAT(status,'|',IFNULL(autorizado_em,'-')) FROM fila_carregamentos WHERE id=${idB2}`) === 'saldo|-' && /voltou para Saldo a buscar/.test(await txt(adm)), 'cliente não veio: viagem volta para Saldo a buscar');
  await adm.goto(BASE + '/controle');
  ok(await adm.locator(`a[href$="/ordens/${idB2}/cancelar"]`).count() === 1, 'Controle de carga: "Encerrar saldo" na linha do saldo');
  await adm.goto(BASE + '/ordens/' + idB2);
  await enviar(adm, `form[action$="/ordens/${idB2}/liberar"] button[type=submit]`);
  await iniciar(maria, idB2);
  msg = await corrigir(adm, idB, '0,8');
  ok(/está carregando agora/.test(msg) && sql(`SELECT quantidade_carregada FROM fila_carregamentos WHERE id=${idB}`) === '1.00', 'com a viagem 2 carregando, a correção é recusada sem mudar nada', msg);
  await finalizar(maria, idB2, '2');

  // 5. A pá começa a carregar com o formulário de correção aberto: a mudança de carga é recusada.
  const idC = await novaOrdem(adm, '9003', '5', 't', false, '');
  await adm.goto(BASE + '/ordens/' + idC);
  await iniciar(maria, idC);
  await adm.fill('form.formulario [name=quantidade_autorizada]', '8');
  await enviar(adm, `form.formulario[action$="/ordens/${idC}"] button[type=submit]`);
  ok(/A pá começou a carregar esta ordem antes de você salvar/.test(await txt(adm)) && sql(`SELECT quantidade_autorizada FROM fila_carregamentos WHERE id=${idC}`) === '5.00', 'mudança de carga depois do início é recusada com aviso', (await txt(adm)).slice(0, 160));
  await adm.goto(BASE + '/ordens/' + idC);
  await adm.fill('form.formulario [name=observacoes]', 'Só observação');
  await enviar(adm, `form.formulario[action$="/ordens/${idC}"] button[type=submit]`);
  ok(sql(`SELECT observacoes FROM fila_carregamentos WHERE id=${idC}`) === 'Só observação', 'só a observação ainda pode mudar');
  await finalizar(maria, idC);

  // 6. Aviso de carga alterada no tablet: diz o que mudou.
  const idD = await novaOrdem(adm, '9004', '4', 't', false, '');
  await maria.goto(BASE + '/patio');
  await maria.evaluate(() => { if (window.sessionStorage) {} });
  await espera(1500);
  await adm.goto(BASE + '/ordens/' + idD);
  await adm.fill('form.formulario [name=quantidade_autorizada]', '6');
  await adm.fill('form.formulario [name=observacoes]', 'Caminhão vermelho');
  await enviar(adm, `form.formulario[action$="/ordens/${idD}"] button[type=submit]`);
  let quadro = '';
  for (let i = 0; i < 20 && !/Carga alterada/.test(quadro); i++) { await espera(1000); quadro = await maria.evaluate(() => { const q = document.querySelector('.avisos-patio:not([hidden])'); return q ? q.innerText : ''; }); }
  quadro = quadro.replace(/\s+/g, ' ');
  ok(/Carga alterada/.test(quadro) && /Antes: 4,00 t · Brita 1/.test(quadro) && /Observação: Caminhão vermelho/.test(quadro), 'aviso de alterada mostra a carga de antes e a observação nova', quadro);
  const ciente = maria.locator('.avisos-caixa .btn');
  ok(await ciente.isVisible(), 'Ciente à vista');
  await ciente.click();
  // Muitos avisos numa tela baixa: o Ciente continua à vista.
  await maria.setViewportSize({ width: 800, height: 480 });
  for (let i = 0; i < 5; i++) await novaOrdem(adm, '95' + i, '1', 't', false, 'recado ' + i);
  quadro = '';
  for (let i = 0; i < 20 && !/\+ 1 avisos/.test(quadro); i++) { await espera(1000); quadro = await maria.evaluate(() => { const q = document.querySelector('.avisos-patio:not([hidden])'); return q ? q.innerText : ''; }); }
  const caixa = await ciente.boundingBox();
  ok(caixa && caixa.y + caixa.height <= 480, 'com 5 avisos numa tela de 480 px, o Ciente fica dentro da tela', JSON.stringify(caixa));
  await T.capturar(maria, 'patio_avisos_baixa');
  await ciente.click();

  // 7. Snapshot antigo (só a assinatura) não quebra a tela.
  await maria.evaluate(id => {
    const visto = JSON.parse(sessionStorage.getItem('operacoes-patio-visto'));
    const fila = {};
    Object.keys(visto.fila).forEach(k => { fila[k] = String(k) === String(id) ? 'velha' : visto.fila[k].a; });
    sessionStorage.setItem('operacoes-patio-visto', JSON.stringify({ fila: fila, canceladas: visto.canceladas }));
  }, idD);
  await maria.reload();
  await espera(1500);
  quadro = await maria.evaluate(() => { const q = document.querySelector('.avisos-patio:not([hidden])'); return q ? q.innerText : ''; });
  ok(/^Carga alterada Cliente 9004/.test(quadro.replace(/\s+/g, ' ')) && !/Nova carga/.test(quadro), 'guardado antigo: só a carga mudada avisa, sem erro', quadro.replace(/\s+/g, ' '));

  // 8. Viagem liberada que volta para Saldo a buscar: o tablet avisa que saiu da fila.
  await maria.evaluate(() => { const b = document.querySelector('.avisos-caixa .btn'); if (b) b.click(); });
  const idE = await novaOrdem(adm, '9600', '3', 't', true, '');
  await iniciar(maria, idE);
  await finalizar(maria, idE, '1');
  const idE2 = sql(`SELECT id FROM fila_carregamentos WHERE ordem_principal_id=${idE}`);
  sql(`UPDATE fila_carregamentos SET observacoes = CONCAT(observacoes, '\nLevar lona') WHERE id=${idE2}`);
  await adm.goto(BASE + '/ordens/' + idE2);
  await enviar(adm, `form[action$="/ordens/${idE2}/liberar"] button[type=submit]`);
  await maria.goto(BASE + '/patio');
  await espera(1500);
  await maria.evaluate(() => { const b = document.querySelector('.avisos-caixa .btn'); if (b) b.click(); });
  ok(new RegExp('Obs\\.: Levar lona').test(await txt(maria)), 'recado escrito logo embaixo da linha do sistema aparece no cartão');
  await adm.goto(BASE + '/ordens/' + idE2);
  await enviar(adm, `form[action$="/ordens/${idE2}/saldo"] button[type=submit]`);
  quadro = '';
  for (let i = 0; i < 20 && !/Carga tirada da fila/.test(quadro); i++) { await espera(1000); quadro = await maria.evaluate(() => { const q = document.querySelector('.avisos-patio:not([hidden])'); return q ? q.innerText : ''; }); }
  ok(/Carga tirada da fila/.test(quadro) && /O cliente não veio/.test(quadro), 'cliente não veio: o tablet avisa que a carga saiu da fila', quadro.replace(/\s+/g, ' '));
  await maria.evaluate(() => { const b = document.querySelector('.avisos-caixa .btn'); if (b) b.click(); });
  await adm.goto(BASE + '/ordens/' + idE2);
  await enviar(adm, `form[action$="/ordens/${idE2}/liberar"] button[type=submit]`);
  quadro = '';
  for (let i = 0; i < 20 && !/Nova carga liberada/.test(quadro); i++) { await espera(1000); quadro = await maria.evaluate(() => { const q = document.querySelector('.avisos-patio:not([hidden])'); return q ? q.innerText : ''; }); }
  ok(/Nova carga liberada/.test(quadro), 'liberada de novo: avisa como carga nova', quadro.replace(/\s+/g, ' '));

  T.resumo();
});
