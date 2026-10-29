// Integração com o Fin Pro: as notas Pix e Transferência vão sozinhas para um servidor falso que responde como o Fin Pro
// (contrato em docs/superpowers/specs/2026-10-23-integracao-finpro-design.md). Envio na hora com o PDF lido, Dinheiro
// não vai, falha e nova tentativa pela consulta do Monitor, Reenviar, a forma corrigida na ficha e o CNPJ/CPF do cliente.
const T = require('../lib/teste');
const http = require('http');
const fs = require('fs');
const path = require('path');
const { ok, sql, espera, enviar, txt, BASE } = T;
const F = 'form[action$="/ordens/nova"]';

// Servidor falso do Fin Pro: guarda cada pedido e responde com o código da vez.
const recebidos = [];
let resposta = 201;
const falso = http.createServer((req, res) => {
  const partes = [];
  req.on('data', c => partes.push(c));
  req.on('end', () => {
    recebidos.push({ url: req.url, auth: req.headers.authorization, tipo: req.headers['content-type'] || '', corpo: Buffer.concat(partes) });
    res.writeHead(resposta, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify(resposta < 300 ? { ok: true, id: 1, situacao: resposta === 201 ? 'criada' : 'atualizada' } : { ok: false, erro: 'falha simulada' }));
  });
});

// Campos do multipart/form-data; o arquivo vem como Buffer, com o tipo em <campo>_tipo.
function campos(pedido) {
  const limite = Buffer.from('--' + /boundary=(.+)$/.exec(pedido.tipo)[1]);
  const r = {};
  let inicio = pedido.corpo.indexOf(limite);
  while (inicio >= 0) {
    const fim = pedido.corpo.indexOf(limite, inicio + limite.length);
    if (fim < 0) break;
    const parte = pedido.corpo.subarray(inicio + limite.length + 2, fim - 2);
    const corte = parte.indexOf('\r\n\r\n');
    const cab = parte.subarray(0, corte).toString('utf8');
    const nome = /name="([^"]+)"/.exec(cab)[1];
    const valor = parte.subarray(corte + 4);
    if (/filename=/.test(cab)) {
      r[nome] = valor;
      r[nome + '_tipo'] = (/Content-Type: (\S+)/i.exec(cab) || [])[1];
    } else {
      r[nome] = valor.toString('utf8');
    }
    inicio = fim;
  }
  return r;
}
const ultimo = () => campos(recebidos[recebidos.length - 1]);

async function lancarAMao(p, nf, forma, documento = '') {
  await p.goto(BASE + '/ordens/nova');
  await p.fill(F + ' [name=cliente]', 'Cliente ' + nf);
  await p.fill(F + ' [name=nf_documento]', documento);
  await p.fill(F + ' [name=pedido]', 'P-' + nf);
  await p.fill(F + ' [name=nf]', nf);
  await p.fill(F + ' [name=nf_valor]', '1.000,00');
  await p.selectOption(F + ' select[name=material_id]', { label: 'Brita 1' });
  await p.fill(F + ' [name=quantidade_autorizada]', '10');
  await p.selectOption(F + ' [name=nf_pagamento]', forma);
  await enviar(p, F + ' button[type=submit]');
  return sql(`SELECT id FROM fila_carregamentos WHERE nf='${nf}'`);
}

T.executar(async () => {
  if (!T.temComando('pdftotext')) T.pular('pdftotext (poppler-utils) não instalado — rode sh tests/preparar.sh');
  await new Promise(r => falso.listen(0, '127.0.0.1', r));
  // O app lê o .env da pasta do ambiente a cada pedido.
  fs.writeFileSync(path.join(T.AMBIENTE, '.env'), `FINPRO_URL=http://127.0.0.1:${falso.address().port}/\nFINPRO_TOKEN=teste-token\n`);
  const b = await T.abrir();
  const p = await T.entrar(b, 'Fabio', 'senha-forte-123', { largura: 1100, altura: 1200 });

  // 1. Nota lida do PDF e lançada com Pix: vai na hora, com o PDF.
  await p.goto(BASE + '/ordens/nova');
  await p.setInputFiles('form.ler-nota input[type=file]', path.join(T.NOTAS, 'nota1.pdf'));
  await enviar(p, 'form.ler-nota button[type=submit]');
  ok(/^[0-9a-f]{32}$/.test(await p.inputValue(F + ' [name=nota_arquivo]')), 'o PDF lido fica guardado até lançar');
  ok(await p.inputValue(F + ' [name=nf_documento]') === '98.765.432/0001-98', 'o CNPJ do cliente lido do PDF preenche o campo', await p.inputValue(F + ' [name=nf_documento]'));
  await p.selectOption(F + ' [name=nf_pagamento]', 'pix');
  await enviar(p, F + ' button[type=submit]');
  const id1 = sql("SELECT id FROM fila_carregamentos WHERE nf='12345'");
  ok(id1 !== '' && sql(`SELECT nf_arquivo FROM fila_carregamentos WHERE id=${id1}`) === `notas/${id1}.pdf`, 'o PDF fica ligado à ordem');
  ok(recebidos.length === 1, 'o Fin Pro recebeu a nota na hora', String(recebidos.length));
  const c1 = ultimo();
  ok(recebidos[0].url === '/api/integracao/operacoes/notas' && recebidos[0].auth === 'Bearer teste-token' && /^multipart\/form-data/.test(recebidos[0].tipo), 'endereço, chave Bearer e multipart do contrato', recebidos[0].url + ' ' + recebidos[0].tipo);
  const doBanco = sql(`SELECT CONCAT(nf,'|',pedido,'|',cliente,'|',IFNULL(nf_valor,''),'|',IFNULL(nf_chave,'')) FROM fila_carregamentos WHERE id=${id1}`);
  ok(c1.origem_id === id1 && [c1.nota, c1.pedido, c1.empresa, c1.valor, c1.chave].join('|') === doBanco && c1.nota === '12345' && c1.empresa === 'CONSTRUTORA BOA OBRA LTDA',
    'campos: origem_id, nota, pedido, empresa, valor com ponto e chave', JSON.stringify({ ...c1, arquivo: undefined }));
  ok(c1.documento === '98765432000198' && sql(`SELECT nf_documento FROM fila_carregamentos WHERE id=${id1}`) === '98765432000198', 'o CNPJ vai e fica gravado só com os números', String(c1.documento));
  ok(/CNPJ\/CPF do cliente\s*98\.765\.432\/0001-98/.test(await txt(p)), 'a ficha mostra o CNPJ formatado');
  ok(c1.forma === 'pix' && c1.material === 'Brita 1' && /^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/.test(c1.emitida_em), 'forma pix, material e data de emissão', c1.forma + ' ' + c1.material + ' ' + c1.emitida_em);
  ok(Buffer.isBuffer(c1.arquivo) && c1.arquivo.subarray(0, 4).toString() === '%PDF' && c1.arquivo_tipo === 'application/pdf', 'o PDF da nota vai junto');
  ok(sql(`SELECT CONCAT(situacao,'|',tentativas) FROM finpro_envios WHERE ordem_id=${id1}`) === 'enviado|1', 'a fila marca enviada');
  ok(/Enviada ao Fin Pro em \d{2}\/\d{2} \d{2}:\d{2}/.test(await txt(p)), 'a ficha mostra quando foi enviada');

  // 2. O PDF abre na ficha.
  const href = await p.locator('a', { hasText: 'PDF da nota' }).getAttribute('href');
  const pdf = await p.request.get(new URL(href, BASE).href);
  ok(pdf.status() === 200 && pdf.headers()['content-type'] === 'application/pdf' && (await pdf.body()).subarray(0, 4).toString() === '%PDF', 'o link "PDF da nota" abre o PDF', String(pdf.status()));

  // 3. Dinheiro não vai.
  const id3 = await lancarAMao(p, '9103', 'dinheiro');
  ok(recebidos.length === 1 && sql(`SELECT COUNT(*) FROM finpro_envios WHERE ordem_id=${id3}`) === '0', 'nota em Dinheiro não vai para o Fin Pro');
  ok(!/Fin Pro/.test(await txt(p)), 'a ficha da nota em Dinheiro não fala do Fin Pro');

  // 4. Fin Pro com erro: fica esperando, com o motivo.
  resposta = 500;
  const id4 = await lancarAMao(p, '9104', 'pix');
  ok(recebidos.length === 2, 'tentou mandar na hora');
  ok(sql(`SELECT CONCAT(situacao,'|',tentativas) FROM finpro_envios WHERE ordem_id=${id4}`) === 'pendente|1', 'a fila fica pendente com 1 tentativa');
  ok(/Esperando envio ao Fin Pro: o Fin Pro respondeu 500 \(falha simulada\)/.test(await txt(p)), 'a ficha mostra a espera e o motivo', (await txt(p)).slice(0, 400));

  // 5. A consulta do Monitor tenta de novo quando chega a hora.
  resposta = 201;
  await p.request.get(BASE + '/dashboard/dados');
  ok(recebidos.length === 2, 'antes da hora, o Monitor não manda de novo');
  sql(`UPDATE finpro_envios SET proxima_tentativa = NOW() - INTERVAL 1 MINUTE WHERE ordem_id=${id4}; UPDATE finpro_rodada SET rodada_em = NOW() - INTERVAL 10 MINUTE`);
  await p.request.get(BASE + '/dashboard/dados');
  ok(recebidos.length === 3 && ultimo().origem_id === id4, 'na hora, a consulta do Monitor manda de novo');
  ok(sql(`SELECT CONCAT(situacao,'|',tentativas) FROM finpro_envios WHERE ordem_id=${id4}`) === 'enviado|2', 'a fila marca enviada na segunda tentativa');

  // 6. Reenviar na ficha manda a mesma ordem de novo (o Fin Pro atualiza, sem duplicar).
  resposta = 200;
  await p.goto(BASE + '/ordens/' + id1);
  await enviar(p, `form[action$="/ordens/${id1}/finpro"] button`);
  ok(recebidos.length === 4 && ultimo().origem_id === id1 && Buffer.isBuffer(ultimo().arquivo), 'Reenviar manda a mesma origem_id, com o PDF');
  ok(/Enviada ao Fin Pro em/.test(await txt(p)), 'depois do Reenviar, a ficha mostra enviada');

  // 7. Forma corrigida de Dinheiro para Transferência no formulário da ficha: vai na hora.
  await p.goto(BASE + '/ordens/' + id3);
  await p.selectOption(`form[action$="/ordens/${id3}"] select[name=nf_pagamento]`, 'transferencia');
  await enviar(p, `form[action$="/ordens/${id3}"] button[type=submit]`);
  ok(sql(`SELECT nf_pagamento FROM fila_carregamentos WHERE id=${id3}`) === 'transferencia', 'a forma Transferência é gravada');
  ok(recebidos.length === 5 && ultimo().origem_id === id3 && ultimo().forma === 'transferencia' && ultimo().arquivo === undefined, 'vai com forma=transferencia (lançada à mão, sem PDF)', JSON.stringify(ultimo()));
  ok(ultimo().documento === '', 'sem CNPJ/CPF, o campo documento vai vazio', JSON.stringify(ultimo().documento));
  ok(/Forma de pagamento\s*Transferência \(TED\/DOC\)/.test(await txt(p)), 'a ficha mostra Transferência (TED/DOC)');

  // 8. Quadro do valor da ficha (nota concluída): corrigir a forma manda de novo.
  sql(`UPDATE fila_carregamentos SET status='concluido' WHERE id=${id3}`);
  await p.goto(BASE + '/ordens/' + id3);
  await p.selectOption('form.valor-nota select[name=nf_pagamento]', 'pix');
  await enviar(p, 'form.valor-nota button[type=submit]');
  ok(recebidos.length === 6 && ultimo().origem_id === id3 && ultimo().forma === 'pix', 'o quadro do valor também manda a forma corrigida');

  // 9. CNPJ/CPF digitado: com dígito errado é recusado; o CPF certo, com pontuação, vai só com os números.
  const id9 = await lancarAMao(p, '9109', 'pix', '98.765.432/0001-10');
  ok(id9 === '' && recebidos.length === 6, 'CNPJ com dígito errado: a ordem não é lançada nem enviada', id9);
  ok(/O CNPJ\/CPF do cliente não confere/.test(await txt(p)), 'avisa que o CNPJ/CPF não confere', (await txt(p)).slice(0, 300));
  await p.fill(F + ' [name=nf_documento]', '529.982.247-25');
  await enviar(p, F + ' button[type=submit]');
  const id9b = sql("SELECT id FROM fila_carregamentos WHERE nf='9109'");
  ok(id9b !== '' && sql(`SELECT nf_documento FROM fila_carregamentos WHERE id=${id9b}`) === '52998224725', 'corrigido, o CPF é gravado só com os números');
  ok(recebidos.length === 7 && ultimo().origem_id === id9b && ultimo().documento === '52998224725', 'o Fin Pro recebe o documento só com os números', JSON.stringify(ultimo().documento));
  await p.goto(BASE + '/ordens/' + id9b);
  ok(/CNPJ\/CPF do cliente\s*529\.982\.247-25/.test(await txt(p)), 'a ficha mostra o CPF formatado');

  falso.close();
  await espera(10);
  T.resumo();
});
