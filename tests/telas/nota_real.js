// Nota fiscal REAL de cliente (nunca vai para o repositório): confere se o PDF (DANFE) e o XML da mesma nota são lidos
// com os mesmos dados, se o PDF sem hora conta a nota no dia da emissão e se a mesma nota não entra duas vezes.
// Só roda com NOTA_REAL_DIR apontando para uma pasta com nota.pdf e real.xml (a nota do mesmo cliente).
// Nenhum dado da nota está escrito aqui: tudo o que se espera sai do próprio XML. As mensagens também não repetem esses dados.
const fs = require('fs');
const path = require('path');
const T = require('../lib/teste');
const { ok, sql, espera, txt, BASE } = T;
const F = 'form[action$="/ordens/nova"]';

T.executar(async () => {
  const dir = process.env.NOTA_REAL_DIR;
  if (!dir) T.pular('nota real: defina NOTA_REAL_DIR com a pasta que tem nota.pdf e real.xml (nota de cliente, fora do repositório)');
  const arqPdf = path.join(dir, 'nota.pdf'), arqXml = path.join(dir, 'real.xml');
  if (!fs.existsSync(arqPdf) || !fs.existsSync(arqXml)) T.pular('nota real: faltam nota.pdf e real.xml em ' + dir);
  if (!T.temComando('pdftotext')) T.pular('pdftotext (poppler-utils) não instalado — rode sh tests/preparar.sh');

  // O que se espera, tirado do XML.
  const xml = fs.readFileSync(arqXml, 'utf8');
  const tag = (nome) => (xml.match(new RegExp('<' + nome + '>([^<]*)</' + nome + '>')) || [])[1];
  const esperado = {
    nf: tag('nNF'),
    valor: T.reais(xml.match(/<vNF>([\d.]+)<\/vNF>/)[1]),
    emitidaEm: T.cuiabaIso(tag('dhEmi')),
    chave: (xml.match(/Id="NFe(\d{44})"/) || [])[1],
  };
  esperado.dia = esperado.emitidaEm.slice(0, 10);
  esperado.hora = esperado.emitidaEm.slice(11, 16);
  ok(!!esperado.nf && !!esperado.chave, 'o real.xml tem número e chave de acesso');

  const b = await T.abrir();
  const p = await T.entrar(b, 'Fabio', 'senha-forte-123', { largura: 600, altura: 1300 });
  // O material da nota precisa existir no cadastro para a leitura achá-lo.
  sql("INSERT IGNORE INTO materiais (material) VALUES ('Pedrisco')");

  const campo = async (n) => {
    const sel = `${F} [name="${n}"]`;
    return (await p.locator(sel).count()) ? p.inputValue(sel) : '';
  };
  async function ler(arquivo) {
    await p.goto(BASE + '/ordens/nova');
    await p.setInputFiles('form.ler-nota input[type=file]', arquivo);
    await espera(1300);
    await Promise.all([p.waitForNavigation(), p.click('form.ler-nota button[type=submit]')]);
    return {
      nf: await campo('nf'), pedido: await campo('pedido'), cliente: await campo('cliente'), material: await p.inputValue('select[name=material_id]'),
      quantidade: await campo('quantidade_autorizada'), unidade: await p.inputValue('select[name=unidade]'), valor: await campo('nf_valor'),
      emitidaEm: await campo('nf_emitida_em'), emitidaData: await campo('nf_emitida_data'),
      avisos: (await p.locator('.leitura-nota li').allInnerTexts()).join(' | '),
    };
  }

  // 1. XML: é a leitura exata, sem nada a conferir.
  const x = await ler(arqXml);
  ok(x.nf === esperado.nf && x.valor === esperado.valor, 'XML real: número e valor da nota');
  ok(x.emitidaEm === esperado.emitidaEm, 'XML real: hora da emissão no horário de Cuiaba');
  ok(x.material !== '', 'XML real: o material foi achado no cadastro');
  ok(x.avisos === '', 'XML real: nada a conferir', x.avisos === '' ? '' : x.avisos.length + ' aviso(s)');

  // 2. PDF da mesma nota: mesmos dados, mas sem a hora (só a data da emissão).
  const d = await ler(arqPdf);
  for (const k of ['nf', 'pedido', 'material', 'quantidade', 'unidade', 'valor']) {
    ok(d[k] === x[k], `PDF real e XML real dão o mesmo ${k}`);
  }
  ok(d.cliente.length >= 20 && x.cliente.startsWith(d.cliente), 'PDF real: o cliente é o começo do nome do XML (o PDF corta nomes compridos)');
  ok(d.emitidaEm === '' && d.emitidaData === esperado.dia, 'PDF real sem hora: a data da emissão vai no campo escondido');
  ok(new RegExp('mostra só a data da emissão \\(' + T.brData(esperado.dia).replace(/\//g, '\\/') + '\\), sem a hora').test(d.avisos), 'PDF real: avisa que o PDF não tem a hora');
  ok(/conta no vendido desse dia/.test(await txt(p)), 'formulário explica que a nota conta no dia da emissão');
  await T.capturar(p, 'nota_real_pdf', { fullPage: true });
  await p.selectOption(`${F} [name=nf_pagamento]`, 'pix');
  await p.check(`${F} [name=liberar]`);
  await T.enviar(p, `${F} button[type=submit]`);
  const idPdf = sql(`SELECT id FROM fila_carregamentos WHERE nf='${esperado.nf}'`);
  const gravado = sql(`SELECT CONCAT(IFNULL(nf_emitida_em,'-'),'|',IFNULL(nf_emitida_data,'-')) FROM fila_carregamentos WHERE id=${idPdf}`);
  ok(gravado === '-|' + esperado.dia, 'lançada pelo PDF: grava a data da emissão sem hora');
  await p.goto(BASE + '/controle?dia=' + esperado.dia);
  ok(new RegExp('NF ' + esperado.nf).test(await txt(p)) && (await txt(p)).includes('Vendido no dia R$ ' + esperado.valor), 'Controle do dia da emissão mostra a nota e o valor');
  await p.goto(BASE + '/ordens/' + idPdf);
  ok(new RegExp(esperado.nf + ' · emitida em ' + T.brData(esperado.dia)).test(await txt(p)), 'ficha mostra a data da emissão');

  // 3. A mesma nota de novo (XML): avisa que já foi lançada. Depois a ordem do PDF sai e o XML é lançado com a hora.
  const repetida = await ler(arqXml);
  ok(/Já existe o carregamento CAR-\d+ com esta nota/.test(repetida.avisos), 'XML da mesma nota: avisa que já foi lançada');
  sql(`DELETE FROM fila_carregamentos WHERE id=${idPdf}`);
  await ler(arqXml);
  await p.selectOption(`${F} [name=nf_pagamento]`, 'pix');
  await p.check(`${F} [name=liberar]`);
  await T.enviar(p, `${F} button[type=submit]`);
  const linha = sql(`SELECT CONCAT(nf,'|',nf_emitida_em,'|',nf_chave,'|',status) FROM fila_carregamentos WHERE nf_chave='${esperado.chave}'`);
  ok(linha === `${esperado.nf}|${esperado.emitidaEm.replace('T', ' ')}|${esperado.chave}|liberado`, 'XML real lançado e liberado, com a hora e a chave da nota');
  ok(new RegExp('emitida em ' + T.brData(esperado.dia).replace(/\//g, '\\/') + ' ' + esperado.hora).test(await txt(p)), 'ficha mostra a hora da nota');
  await p.goto(BASE + '/patio');
  ok(new RegExp(x.quantidade).test(await txt(p)), 'pátio mostra a quantidade da nota');
  const outraVez = await ler(arqPdf);
  ok(/Já existe o carregamento CAR-\d+ com esta nota/.test(outraVez.avisos), 'PDF da mesma nota: avisa que já foi lançada');
  T.resumo();
});
