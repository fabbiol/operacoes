// Máquina na oficina e ordem de serviço: não manda para a oficina a máquina que está com alguém; a Pá 2 vai para a
// oficina ligada a um problema aberto, some da escolha de máquina, o servidor recusa assumi-la e iniciar carga com ela,
// o Monitor e a Manutenção mostram; liberar exige o que foi feito, grava a ordem de serviço com o custo e resolve o
// problema; a disponibilidade do mês desconta o tempo na oficina; o operador não usa as rotas novas.
const T = require('../lib/teste');
const { ok, sql, txt, enviar, BASE } = T;

T.executar(async () => {
  const b = await T.abrir();
  const maria = await T.entrar(b, 'Maria', 'maria1234');
  await T.assumir(maria, 'pa:1', '100', { envelhecer: true });
  const adm = await T.entrar(b, 'Fabio', 'senha-forte-123', { largura: 1280, altura: 1000 });

  async function mandar(chave, motivo, problema) {
    await adm.goto(BASE + '/cadastros/' + chave + '#oficina');
    await adm.click('#oficina details.anular > summary');
    await adm.fill('#oficina textarea[name=motivo]', motivo);
    if (problema) await adm.selectOption('#oficina select[name=problema_id]', problema);
    await enviar(adm, '#oficina button:has-text("Mandar para a oficina")');
  }

  // A Pá 1 está com a Maria: precisa ser liberada antes.
  await mandar('pas/1', 'Barulho no motor');
  ok(/está com Maria\. Libere a máquina antes/.test(await txt(adm)) && sql('SELECT COUNT(*) FROM maquina_oficina') === '0', 'Pá 1 com a Maria não vai para a oficina', (await txt(adm)).slice(0, 200));

  // A Pá 2 vai, ligada ao problema aberto.
  sql("INSERT INTO maquina_problemas (tipo, maquina_id, usuario, descricao, relatado_em) VALUES ('pa', 2, 'Maria', 'Vazamento no hidráulico', NOW() - INTERVAL 1 HOUR)");
  const idProblema = sql("SELECT id FROM maquina_problemas WHERE descricao='Vazamento no hidráulico'");
  await mandar('pas/2', 'Vazamento no hidráulico do braço', idProblema);
  ok(sql("SELECT CONCAT(problema_id,'|',entrada_por,'|',saida IS NULL) FROM maquina_oficina WHERE tipo='pa' AND maquina_id=2") === idProblema + '|Fabio|1', 'Pá 2 na oficina, ligada ao problema');
  let tela = await txt(adm);
  ok(/Na oficina desde [\d/]+ [\d:]+/.test(tela) && /Liberar da oficina/.test(tela), 'ficha mostra o selo e o formulário de liberar', tela.slice(0, 300));
  const csrf = await adm.getAttribute('#oficina input[name=_csrf]', 'value');
  await adm.request.post(BASE + '/cadastros/pas/2/oficina', { form: { _csrf: csrf, motivo: 'De novo' }, maxRedirects: 0 });
  await adm.reload();
  ok(/Essa máquina já está na oficina/.test(await txt(adm)) && sql("SELECT COUNT(*) FROM maquina_oficina WHERE tipo='pa' AND maquina_id=2") === '1', 'não abre segunda estadia');

  // Pedro (opera pá) não vê a Pá 2 para assumir, e o servidor recusa.
  const pedro = await T.entrar(b, 'Pedro', 'pedro1234');
  await pedro.goto(BASE + '/maquina');
  tela = await txt(pedro);
  ok(await pedro.locator('input[name=maquina][value="pa:2"]').count() === 0 && /Pá 2 Na oficina desde/.test(tela), 'Pá 2 some da escolha e aparece como na oficina', tela.slice(0, 400));
  await pedro.evaluate(() => { const r = document.querySelector('input[name=maquina]'); r.value = 'pa:2'; r.checked = true; });
  await pedro.fill('input[name=leitura]', '200');
  await enviar(pedro, 'form.formulario button[type=submit]');
  ok(/Essa máquina está na oficina/.test(await txt(pedro)) && sql("SELECT COUNT(*) FROM maquina_posse WHERE tipo='pa' AND maquina_id=2") === '0', 'servidor recusa assumir a Pá 2', (await txt(pedro)).slice(0, 200));

  // Pátio: a Pá 2 aparece desabilitada e o servidor recusa iniciar carga com ela.
  await adm.goto(BASE + '/carregamentos/1/iniciar');
  ok(await adm.locator('input[name=pa_carregadeira_id][value="2"]').isDisabled() && /Pá 2 Volvo · L60 · 2018 Na oficina/.test(await txt(adm)), 'pátio mostra a Pá 2 desabilitada, na oficina', (await txt(adm)).slice(0, 400));
  await adm.evaluate(() => { const r = document.querySelector('input[name=pa_carregadeira_id][value="2"]'); r.disabled = false; r.checked = true; });
  await adm.selectOption('select[name=operador]', 'Pedro');
  await enviar(adm, 'form.formulario button[type=submit]');
  ok(/Pá 2 está na oficina desde/.test(await txt(adm)) && sql('SELECT status FROM fila_carregamentos WHERE id=1') === 'liberado', 'servidor recusa iniciar carga com a Pá 2', (await txt(adm)).slice(0, 300));

  // Monitor e Manutenção.
  const dados = await (await adm.request.get(BASE + '/dashboard/dados')).json();
  const naTv = (dados.oficina || []).find(o => o.nome === 'Pá 2');
  ok(naTv && naTv.tipo === 'pa' && naTv.motivo === 'Vazamento no hidráulico do braço' && naTv.desde_ts > 0, 'Monitor lista a Pá 2 na oficina', JSON.stringify(dados.oficina));
  await adm.goto(BASE + '/manutencao');
  tela = await txt(adm);
  ok(/Pá 2 .*Na oficina desde [\d/]+ [\d:]+ · Vazamento no hidráulico do braço/.test(tela), 'Manutenção mostra o selo na Pá 2', tela.slice(0, 600));

  // O operador não usa as rotas novas.
  for (const caminho of ['/cadastros/pas/2/oficina', '/cadastros/pas/2/oficina/liberar']) {
    ok((await maria.request.post(BASE + caminho, { form: { motivo: 'x', servico: 'x' }, maxRedirects: 0 })).status() === 403, 'operador recebe 403 em ' + caminho);
  }

  // Liberar sem o que foi feito é recusado.
  await adm.goto(BASE + '/cadastros/pas/2#oficina');
  await adm.evaluate(() => document.querySelector('textarea[name=servico]').removeAttribute('required'));
  await enviar(adm, '#oficina button:has-text("Liberar da oficina")');
  ok(/Informe o que foi feito/.test(await txt(adm)) && sql("SELECT COUNT(*) FROM maquina_oficina WHERE saida IS NULL") === '1', 'liberar sem o que foi feito é recusado');

  // Liberar com serviço, quem fez e custo grava a ordem de serviço e resolve o problema.
  await adm.fill('#oficina textarea[name=servico]', 'Trocada a mangueira do braço');
  await adm.fill('#oficina input[name=executor]', 'Oficina do Zé');
  await adm.fill('#oficina input[name=custo]', '1.250,50');
  await enviar(adm, '#oficina button:has-text("Liberar da oficina")');
  ok(sql("SELECT CONCAT(saida IS NOT NULL,'|',saida_por,'|',servico,'|',executor,'|',custo) FROM maquina_oficina WHERE tipo='pa' AND maquina_id=2") === '1|Fabio|Trocada a mangueira do braço|Oficina do Zé|1250.50', 'ordem de serviço gravada com saída e custo');
  ok(sql(`SELECT CONCAT(resolvido_em IS NOT NULL,'|',resolvido_por,'|',resolucao) FROM maquina_problemas WHERE id=${idProblema}`) === '1|Fabio|Trocada a mangueira do braço', 'problema ligado ficou resolvido');
  tela = await txt(adm);
  ok(/Ordens de serviço/.test(tela) && /Vazamento no hidráulico do braço/.test(tela) && /por Oficina do Zé · R\$ 1\.250,50/.test(tela) && /Mandar para a oficina/.test(tela), 'ficha mostra a ordem de serviço e volta a oferecer mandar', tela.slice(tela.indexOf('Oficina'), tela.indexOf('Oficina') + 500));
  await pedro.goto(BASE + '/maquina');
  ok(await pedro.locator('input[name=maquina][value="pa:2"]').count() === 1, 'Pá 2 volta para a escolha do Pedro');
  await T.capturar(adm, 'oficina_ficha', { fullPage: true });

  // Disponibilidade: uma estadia de 1 hora que terminou agora na Retro 1, cortada no começo do mês.
  sql("INSERT INTO maquina_oficina (tipo, maquina_id, motivo, entrada, entrada_por, saida, saida_por, servico) VALUES ('retro', 1, 'Revisão', NOW() - INTERVAL 1 HOUR, 'Fabio', NOW(), 'Fabio', 'Revisão feita')");
  const total = Number(sql("SELECT UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(DATE_FORMAT(NOW(), '%Y-%m-01'))"));
  const esperado = 100 * (1 - Math.min(3600, total) / total);
  await adm.goto(BASE + '/cadastros/retros/1');
  const mostrado = (await adm.textContent('#oficina .disponibilidade')) || '';
  const numero = Number(mostrado.replace('%', '').replace(',', '.'));
  ok(Math.abs(numero - esperado) <= 0.15, 'disponibilidade do mês desconta a hora na oficina', mostrado + ' esperado ' + esperado.toFixed(2));
  await adm.goto(BASE + '/cadastros/caminhoes/1');
  ok(((await adm.textContent('#oficina .disponibilidade')) || '') === '100%', 'máquina sem oficina no mês: 100%');
  T.resumo();
});
