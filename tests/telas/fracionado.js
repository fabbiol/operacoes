// Nota fracionada em várias viagens: o operador informa quanto levou em cada viagem, o saldo vira nova viagem esperando o
// cliente, o vendido do dia conta a nota uma vez, o administrativo libera a viagem ou encerra o saldo, e a ordem comum não muda.
//
// A nota é sintética: sai de tests/notas/nota2.xml com 3 m³, valor R$ 512,30 e emissão AGORA (horário de Cuiaba). Assim as
// conferências de "vendido hoje" e "carregado no dia" valem em qualquer dia em que o teste rodar.
const T = require('../lib/teste');
const fs = require('fs');
const path = require('path');
const { ok, sql, espera, BASE } = T;
const F = 'form[action$="/ordens/nova"]';
const entrar = (largura, usuario, senha) => T.entrar(browser, usuario, senha, { largura, altura: 1000 });
const clicar = T.enviar;
let browser;

function notaDeHoje() {
  const emissao = T.cuiabaIso() + '-04:00'; // a nota é emitida agora, no horário de Cuiaba
  const xml = fs.readFileSync(path.join(T.NOTAS, 'nota2.xml'), 'utf8')
    .replace(/<dhEmi>[^<]*<\/dhEmi>/, `<dhEmi>${emissao}</dhEmi>`)
    .replace(/<dhSaiEnt>[^<]*<\/dhSaiEnt>/, `<dhSaiEnt>${emissao}</dhSaiEnt>`)
    .replace(/<dhRecbto>[^<]*<\/dhRecbto>/, `<dhRecbto>${emissao}</dhRecbto>`)
    .replace(/<qCom>12\.0000<\/qCom>/, '<qCom>3.0000</qCom>').replace(/<qTrib>12\.0000<\/qTrib>/, '<qTrib>3.0000</qTrib>')
    .replace(/<vProd>720\.00<\/vProd>/g, '<vProd>512.30</vProd>').replace(/<vNF>720\.00<\/vNF>/, '<vNF>512.30</vNF>').replace(/<vPag>720\.00<\/vPag>/, '<vPag>512.30</vPag>');
  const arquivo = path.join(T.TMP, 'nota_fracionada.xml');
  fs.writeFileSync(arquivo, xml);
  return { arquivo, chave: (xml.match(/Id="NFe(\d{44})"/) || [])[1], numero: (xml.match(/<nNF>(\d+)<\/nNF>/) || [])[1], dia: emissao.slice(0, 10) };
}
const aviso = async p => (await p.locator('.aviso').allInnerTexts()).join(' | ');
T.executar(async () => {
  browser = await T.abrir();
  const nota = notaDeHoje();
  const NF = nota.numero;
  const adm = await entrar(1280, 'Fabio', 'senha-forte-123');
  const op = await entrar(600, 'Maria', 'maria1234');

  // 1. Lança pela nota (XML), fracionado, e libera.
  await adm.goto(BASE + '/ordens/nova');
  await adm.setInputFiles('form.ler-nota input[type=file]', nota.arquivo);
  await clicar(adm, 'form.ler-nota button[type=submit]');
  ok((await adm.inputValue(F + ' [name=nf_emitida_em]')).startsWith(nota.dia), 'a nota lida é emitida hoje (horário de Cuiaba)', await adm.inputValue(F + ' [name=nf_emitida_em]'));
  ok(await adm.inputValue(F + ' [name=nf_valor]') === '512,30', 'valor da nota lido do XML (R$ 512,30)', await adm.inputValue(F + ' [name=nf_valor]'));
  await adm.check(F + ' [name=fracionado]');
  await adm.check(F + ' [name=liberar]');
  await clicar(adm, F + ' button[type=submit]');
  const id1 = sql(`SELECT id FROM fila_carregamentos WHERE nf='${NF}' AND ordem_principal_id IS NULL`);
  const cod1 = sql(`SELECT ordem FROM fila_carregamentos WHERE id=${id1}`);
  ok(sql(`SELECT CONCAT(fracionado,'|',status,'|',quantidade_autorizada,'|',nf_valor,'|',viagem) FROM fila_carregamentos WHERE id=${id1}`) === '1|liberado|3.00|512.30|1', 'nota lançada fracionada, liberada, 3 m³ e R$ 512,30', sql(`SELECT CONCAT(fracionado,'|',status,'|',quantidade_autorizada,'|',IFNULL(nf_valor,'-'),'|',viagem) FROM fila_carregamentos WHERE id=${id1}`));

  // 2. Operadora assume a Pá 1 e faz a 1ª viagem: 0,5 m³.
  await op.goto(BASE + '/maquina');
  await op.locator('label:has(input[value="pa:1"])').click();
  await op.fill('input[name=leitura]', '100');
  await clicar(op, 'form[action$="/maquina"] button[type=submit]');
  await op.goto(BASE + '/patio');
  ok(/Saldo 3,00 m³/.test(await op.textContent('main')) && /Fracionado · viagem 1/.test(await op.textContent('main')), 'pátio mostra "Saldo 3,00 m³" e "Fracionado · viagem 1"');
  await op.goto(BASE + `/carregamentos/${id1}/iniciar`);
  await clicar(op, `form[action$="/carregamentos/${id1}/iniciar"] button[type=submit]`);
  ok(sql(`SELECT status FROM fila_carregamentos WHERE id=${id1}`) === 'carregando', '1ª viagem iniciada');
  await op.goto(BASE + `/carregamentos/${id1}/finalizar`);
  ok(/Quanto foi carregado nesta viagem\? \(m³\)/.test(await op.textContent('main')) && /Saldo da nota: 3,00 m³/.test(await op.textContent('main')), 'finalizar pergunta quanto foi carregado e mostra o saldo');
  await op.fill('input[name=quantidade]', '3,5');
  await clicar(op, `form[action$="/carregamentos/${id1}/finalizar"] button[type=submit]`);
  ok(/O saldo desta nota é 3,00 m³/.test(await aviso(op)) && sql(`SELECT status FROM fila_carregamentos WHERE id=${id1}`) === 'carregando', 'mais que o saldo é recusado', await aviso(op));
  await op.fill('input[name=quantidade]', '0,5');
  await clicar(op, `form[action$="/carregamentos/${id1}/finalizar"] button[type=submit]`);
  ok(sql(`SELECT CONCAT(status,'|',quantidade_carregada) FROM fila_carregamentos WHERE id=${id1}`) === 'aguardando_conferencia|0.50', '1ª viagem finalizada com 0,50 m³');
  const filha = sql(`SELECT CONCAT(id,'|',ordem,'|',status,'|',quantidade_autorizada,'|',viagem,'|',IFNULL(nf_valor,'-'),'|',nf_chave) FROM fila_carregamentos WHERE ordem_principal_id=${id1}`);
  ok(new RegExp('^\\d+\\|' + cod1 + '-2\\|saldo\\|2\\.50\\|2\\|-\\|' + nota.chave + '$').test(filha), 'viagem 2 criada: saldo 2,50 m³, esperando o cliente, sem repetir o valor', filha);
  const id2 = filha.split('|')[0];

  // 3. Vendido do dia conta a nota uma vez.
  await adm.goto(BASE + '/painel');
  ok(/Vendido hoje\s*R\$ 512,30\s*4 notas · 3 sem valor/.test((await adm.textContent('.vendas-dia')).replace(/\s+/g, ' ')), 'painel: vendido hoje R$ 512,30 (a nota fracionada conta uma vez; as 3 ordens de exemplo sem valor são avisadas)', (await adm.textContent('.vendas-dia')).replace(/\s+/g, ' '));
  ok(/Saldo a buscar\s*1/.test((await adm.textContent('.vendas-dia')).replace(/\s+/g, ' ')), 'painel: 1 saldo a buscar');

  // 4. Controle de carga: libera a viagem quando o cliente volta.
  await adm.goto(BASE + '/controle');
  const textoControle = (await adm.textContent('main')).replace(/\s+/g, ' ');
  ok(/Vendido no dia R\$ 512,30/.test(textoControle) && /Carregado no dia 0,50 m³/.test(textoControle), 'controle: vendido R$ 512,30 e carregado 0,50 m³', textoControle.slice(0, 300));
  ok(new RegExp('MUNICIPIO DE VARZEA GRANDE.*' + NF + '.*Pó de pedra.*3,00 m³.*0,50 m³.*2,50 m³').test(textoControle), 'controle: saldo a buscar com total, carregado e saldo');
  await T.capturar(adm, 'fracionado_controle', { fullPage: true });
  await clicar(adm, `form[action$="/ordens/${id2}/liberar"] button[type=submit]`);
  ok(/\/controle$/.test(adm.url()) && sql(`SELECT status FROM fila_carregamentos WHERE id=${id2}`) === 'liberado', 'Liberar viagem: vai para a fila do pátio e volta ao controle', adm.url());

  // 5. Viagem 2 leva o resto (2,5).
  await op.goto(BASE + `/carregamentos/${id2}/iniciar`);
  await clicar(op, `form[action$="/carregamentos/${id2}/iniciar"] button[type=submit]`);
  await op.goto(BASE + `/carregamentos/${id2}/finalizar`);
  await op.fill('input[name=quantidade]', '2,50');
  await clicar(op, `form[action$="/carregamentos/${id2}/finalizar"] button[type=submit]`);
  ok(sql(`SELECT CONCAT(status,'|',quantidade_carregada) FROM fila_carregamentos WHERE id=${id2}`) === 'aguardando_conferencia|2.50', 'viagem 2 finalizada com 2,50 m³');
  ok(sql(`SELECT COUNT(*) FROM fila_carregamentos WHERE ordem_principal_id=${id1}`) === '1', 'sem saldo: não cria outra viagem');
  await adm.goto(BASE + '/ordens/' + id1);
  const ficha = (await adm.innerText('main')).replace(/\s+/g, ' ');
  ok(/Total da nota 3,00 m³ Já carregado 3,00 m³ Saldo 0,00 m³/i.test(ficha), 'ficha: total 3,00, carregado 3,00, saldo 0', ficha.match(/Total da nota.{0,80}/)?.[0]);
  ok(/Valor da nota R\$ 512,30/i.test(ficha), 'ficha mostra o valor da nota');
  await T.capturar(adm, 'fracionado_ficha', { fullPage: true });
  await adm.goto(BASE + '/controle');
  ok(/Entregue/.test(await adm.textContent('main')), 'controle: nota entregue');

  // 6. Encerrar saldo de outra nota fracionada.
  await adm.goto(BASE + '/ordens/nova');
  await adm.fill(F + ' [name=cliente]', 'Cliente Fracionado');
  await adm.fill(F + ' [name=pedido]', 'P-2');
  await adm.fill(F + ' [name=nf]', '8001');
  await adm.fill(F + ' [name=nf_valor]', '1.000,00');
  await adm.selectOption('select[name=material_id]', { label: 'Pó de pedra' });
  await adm.fill(F + ' [name=quantidade_autorizada]', '1,5');
  await adm.selectOption('select[name=unidade]', 'm3');
  await adm.check(F + ' [name=fracionado]');
  await adm.check(F + ' [name=liberar]');
  await clicar(adm, F + ' button[type=submit]');
  const id3 = sql("SELECT id FROM fila_carregamentos WHERE nf='8001'");
  await op.goto(BASE + `/carregamentos/${id3}/iniciar`);
  await clicar(op, `form[action$="/carregamentos/${id3}/iniciar"] button[type=submit]`);
  await op.goto(BASE + `/carregamentos/${id3}/finalizar`);
  await op.fill('input[name=quantidade]', '0,5');
  await clicar(op, `form[action$="/carregamentos/${id3}/finalizar"] button[type=submit]`);
  const id4 = sql(`SELECT id FROM fila_carregamentos WHERE ordem_principal_id=${id3}`);
  await adm.goto(BASE + '/ordens/' + id4);
  ok(/O cliente voltou: liberar viagem/.test(await adm.textContent('main')) && /Encerrar saldo/.test(await adm.textContent('main')), 'viagem pendente tem "liberar viagem" e "Encerrar saldo"');
  await adm.goto(BASE + `/ordens/${id4}/cancelar`);
  await adm.fill('textarea[name=motivo], input[name=motivo]', 'Cliente desistiu do resto');
  await clicar(adm, 'form[action$="/cancelar"] button[type=submit]');
  ok(sql(`SELECT status FROM fila_carregamentos WHERE id=${id4}`) === 'cancelado', 'saldo encerrado');
  await adm.goto(BASE + '/controle');
  ok(/Saldo encerrado/.test(await adm.textContent('main')), 'controle mostra "Saldo encerrado"');
  ok(/Vendido no dia R\$ 1\.512,30/.test((await adm.textContent('main')).replace(/\s+/g, ' ')), 'vendido do dia soma as duas notas (R$ 1.512,30)');

  // 7. Ordem comum (não fracionada) continua igual: finaliza sem perguntar quantidade.
  const id5 = sql("SELECT id FROM fila_carregamentos WHERE ordem='CAR-001'");
  await adm.goto(BASE + '/ordens/' + id5);
  await op.goto(BASE + `/carregamentos/${id5}/iniciar`);
  await clicar(op, `form[action$="/carregamentos/${id5}/iniciar"] button[type=submit]`);
  await op.goto(BASE + `/carregamentos/${id5}/finalizar`);
  ok(await op.locator('input[name=quantidade]').count() === 0, 'ordem comum: finalizar não pergunta quantidade');
  await clicar(op, `form[action$="/carregamentos/${id5}/finalizar"] button[type=submit]`);
  ok(sql(`SELECT CONCAT(status,'|',IFNULL(quantidade_carregada,'-')) FROM fila_carregamentos WHERE id=${id5}`) === 'aguardando_conferencia|-', 'ordem comum finalizada como antes');

  // 8. Fila: aba Saldo a buscar e fora das Abertas.
  await adm.goto(BASE + '/fila?situacao=saldo');
  ok(/Saldo a buscar/.test(await adm.textContent('nav.filtros')), 'fila tem a aba "Saldo a buscar"');

  T.resumo();
});
