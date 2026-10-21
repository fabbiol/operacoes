// Leitura da nota fiscal na tela "Nova ordem": XML e PDF (DANFE) sintéticos de tests/notas/ (CNPJ e clientes fictícios).
// Confere os campos preenchidos, os avisos, a nota repetida, material fora do cadastro, m³, edição e arquivos que não são nota.
const T = require('../lib/teste');
const path = require('path');
const { ok, sql, espera, BASE } = T;
const NOTAS = T.NOTAS;
async function ler(p, arquivo) {
  await p.goto(BASE + '/ordens/nova');
  await p.setInputFiles('form.ler-nota input[type=file]', path.join(NOTAS, arquivo));
  await espera(1300);
  await Promise.all([p.waitForNavigation(), p.click('form.ler-nota button[type=submit]')]);
}
const campo = (p, nome) => p.inputValue(`form[action$="/ordens/nova"] [name="${nome}"]`);
const avisos = async p => (await p.locator('.leitura-nota li').allInnerTexts()).join(' | ');
T.executar(async () => {
  if (!T.temComando('pdftotext')) T.pular('pdftotext (poppler-utils) não instalado — rode sh tests/preparar.sh');
  const b = await T.abrir();
  const p = await T.entrar(b, 'Fabio', 'senha-forte-123', { largura: 900, altura: 1200 });

  // 1. XML.
  await ler(p, 'nota1.xml');
  ok(/Nota 12345 lida do XML/.test(await p.textContent('.leitura-nota .aviso')), 'XML: mensagem de nota lida', await p.textContent('.leitura-nota .aviso'));
  ok(await campo(p, 'cliente') === 'CONSTRUTORA BOA OBRA LTDA', 'XML: cliente', await campo(p, 'cliente'));
  ok(await campo(p, 'pedido') === 'PV-4521', 'XML: pedido', await campo(p, 'pedido'));
  ok(await campo(p, 'nf') === '12345', 'XML: número da nota', await campo(p, 'nf'));
  ok(await p.inputValue('select[name=material_id]') === sql("SELECT id FROM materiais WHERE material='Brita 1'"), 'XML: material Brita 1 escolhido no cadastro');
  ok(await campo(p, 'quantidade_autorizada') === '32,50', 'XML: quantidade', await campo(p, 'quantidade_autorizada'));
  ok(await p.inputValue('select[name=unidade]') === 't', 'XML: unidade t');
  ok(await campo(p, 'nf_emitida_em') === '2026-10-15T08:12:40', 'XML: hora da emissão (no horário de Cuiabá)', await campo(p, 'nf_emitida_em'));
  ok(await avisos(p) === '', 'XML: sem avisos', await avisos(p));
  await T.capturar(p, 'nota_lida_xml', { fullPage: true });
  await espera(1300);
  await Promise.all([p.waitForNavigation(), p.click('form[action$="/ordens/nova"] button[type=submit]')]);
  const id1 = sql("SELECT id FROM fila_carregamentos WHERE nf='12345'");
  ok(id1 !== '' && sql(`SELECT CONCAT(cliente,'|',pedido,'|',nf_emitida_em,'|',nf_chave) FROM fila_carregamentos WHERE id=${id1}`) === 'CONSTRUTORA BOA OBRA LTDA|PV-4521|2026-10-15 08:12:40|51261012345678000190550010000123451123456780', 'lançada com a hora e a chave da nota', sql(`SELECT CONCAT(cliente,'|',pedido,'|',IFNULL(nf_emitida_em,'-'),'|',IFNULL(nf_chave,'-')) FROM fila_carregamentos WHERE nf='12345'`));
  ok(/emitida em 15\/10\/2026 08:12/.test(await p.textContent('main')) && /Nota fiscal emitida/.test(await p.textContent('main')), 'ficha mostra a hora da nota');

  // 2. Mesma nota de novo (agora pelo PDF): avisa e não deixa lançar.
  await ler(p, 'nota1.pdf');
  ok(/Nota 12345 lida do PDF/.test(await p.textContent('.leitura-nota .aviso')), 'PDF: mensagem de nota lida');
  ok(/Já existe o carregamento CAR-\d+ com esta nota/.test(await avisos(p)), 'PDF da mesma nota: avisa que já foi lançada', await avisos(p));
  ok(await campo(p, 'pedido') === '4521' && await campo(p, 'nf_emitida_em') === '2026-10-15T08:12:55', 'PDF: pedido e hora (do protocolo) lidos', (await campo(p, 'pedido')) + ' ' + (await campo(p, 'nf_emitida_em')));
  await espera(1300);
  await p.click('form[action$="/ordens/nova"] button[type=submit]');
  await p.waitForLoadState();
  ok(/já foi lançada no carregamento CAR-/.test(await p.textContent('main')) && sql("SELECT COUNT(*) FROM fila_carregamentos WHERE nf_chave='51261012345678000190550010000123451123456780'") === '1', 'a mesma nota não é lançada duas vezes');

  // 3. Material que não está no cadastro, várias linhas do mesmo material, série 2.
  await ler(p, 'nota3.pdf');
  ok(/Nota 98 \(série 2\) lida do PDF/.test(await p.textContent('.leitura-nota .aviso')), 'PDF série 2', await p.textContent('.leitura-nota .aviso'));
  ok(/"BRITA 0 \(PEDRISCO\)", que não está no cadastro/.test(await avisos(p)), 'material fora do cadastro: pede para escolher', await avisos(p));
  ok(/pedido/.test(await avisos(p)), 'pedido não achado: pede para preencher');
  ok(await campo(p, 'quantidade_autorizada') === '15,50', 'soma as linhas do mesmo material', await campo(p, 'quantidade_autorizada'));

  // 4. m³ e pedido no texto.
  await ler(p, 'nota2.xml');
  ok(await p.inputValue('select[name=unidade]') === 'm3' && await campo(p, 'pedido') === '7788', 'XML: m³ e pedido nas informações complementares');
  ok(await p.inputValue('select[name=material_id]') === sql("SELECT id FROM materiais WHERE material='Pó de pedra'"), 'PO DE PEDRA encontra "Pó de pedra" no cadastro');
  await espera(1300);
  await p.check('input[name=liberar]');
  await Promise.all([p.waitForNavigation(), p.click('form[action$="/ordens/nova"] button[type=submit]')]);
  const id2 = sql("SELECT id FROM fila_carregamentos WHERE nf='12346'");
  ok(sql(`SELECT status FROM fila_carregamentos WHERE id=${id2}`) === 'liberado', 'nota 2 lançada e liberada');

  // 5. Editar a ordem mantém a chave e a hora.
  await p.goto(BASE + '/ordens/' + id2);
  await espera(1300);
  await p.fill('textarea[name=observacoes]', 'Conferido');
  await Promise.all([p.waitForNavigation(), p.click('form[action$="/ordens/' + id2 + '"] button[type=submit]')]);
  ok(sql(`SELECT CONCAT(IFNULL(nf_chave,'-'),'|',IFNULL(nf_emitida_em,'-'),'|',IFNULL(observacoes,'')) FROM fila_carregamentos WHERE id=${id2}`) === '51261012345678000190550010000123461123456787|2026-10-15 08:12:40|Conferido', 'editar mantém a chave e a hora da nota', sql(`SELECT CONCAT(IFNULL(nf_chave,'-'),'|',IFNULL(nf_emitida_em,'-'),'|',IFNULL(observacoes,'')) FROM fila_carregamentos WHERE id=${id2}`));

  // 6. Arquivos que não são nota.
  await ler(p, 'texto.txt');
  ok(/não é o PDF nem o XML/.test(await p.textContent('main')), 'arquivo qualquer: avisa');
  await ler(p, 'escaneada.pdf');
  ok(/não tem texto para ler/.test(await p.textContent('main')), 'PDF escaneado (sem texto): pede o XML', (await p.locator('.aviso.erro').allInnerTexts()).join(' | '));

  // 7. Lançar à mão continua igual (sem nota).
  await p.goto(BASE + '/ordens/nova');
  await p.fill('form[action$="/ordens/nova"] [name=cliente]', 'Cliente balcão');
  await p.fill('form[action$="/ordens/nova"] [name=pedido]', 'B-1');
  await p.fill('form[action$="/ordens/nova"] [name=nf]', '555');
  await p.selectOption('select[name=material_id]', { label: 'Brita 1' });
  await p.fill('form[action$="/ordens/nova"] [name=quantidade_autorizada]', '10');
  await espera(1300);
  await Promise.all([p.waitForNavigation(), p.click('form[action$="/ordens/nova"] button[type=submit]')]);
  ok(sql("SELECT CONCAT(IFNULL(nf_emitida_em,'-'),'|',IFNULL(nf_chave,'-')) FROM fila_carregamentos WHERE nf='555'") === '-|-', 'lançar à mão, sem nota, continua funcionando');

  T.resumo();
});
