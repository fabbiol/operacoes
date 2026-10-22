// Britador secundário: o administrativo cadastra o operador (só essa função); ele entra direto na tela do britador,
// inicia, avisa um problema que parou o britador, resolve e termina; o Monitor recebe os horários e os problemas.
// Motorista não abre a tela.
const T = require('../lib/teste');
const { ok, sql, txt, enviar, BASE } = T;
T.executar(async () => {
  const b = await T.abrir();
  const adm = await T.entrar(b, 'Fabio', 'senha-forte-123', { largura: 1280, altura: 1000 });

  await adm.goto(BASE + '/usuarios/novo');
  await adm.fill('input[name=nome]', 'Bruno');
  await adm.fill('input[name=nome_completo]', 'Bruno Britador');
  await adm.fill('input[name=senha]', 'bruno1234');
  await adm.check('input[name="funcoes[]"][value=britador]', { force: true });
  await adm.check('input[name="funcoes[]"][value=motorista]', { force: true });
  await enviar(adm, 'form.formulario button[type=submit]');
  ok(/tem só essa função/.test(await txt(adm)) && sql("SELECT COUNT(*) FROM usuarios WHERE nome='Bruno'") === '0', 'britador com outra função é recusado');
  await adm.uncheck('input[name="funcoes[]"][value=motorista]', { force: true });
  await adm.fill('input[name=senha]', 'bruno1234');
  await enviar(adm, 'form.formulario button[type=submit]');
  ok(sql("SELECT CONCAT(britador,motorista,operador,escavadeira) FROM usuarios WHERE nome='Bruno'") === '1000', 'operador do britador cadastrado');
  await adm.goto(BASE + '/usuarios');
  ok(/Operador do britador secundário/.test(await txt(adm)), 'a lista de pessoas mostra a função');

  const bruno = await T.entrar(b, 'Bruno', 'bruno1234');
  ok(/\/britador$/.test(bruno.url()) && /Sem britagem agora/.test(await txt(bruno)), 'entra direto na tela do britador, sem escolher máquina', bruno.url());

  await enviar(bruno, 'form[action$="/britador/iniciar"] button');
  ok(/Britagem iniciada/.test(await txt(bruno)) && sql('SELECT COUNT(*) FROM britagens WHERE fim IS NULL') === '1', 'inicia a britagem');
  ok(await bruno.locator('form[action$="/britador/iniciar"]').count() === 0, 'em andamento: só aparece Terminar');

  await bruno.fill('textarea[name=descricao]', 'Correia transportadora rasgando');
  await bruno.check('input[name=parou][value="1"]', { force: true });
  await enviar(bruno, 'form[action$="/britador/problemas"] button[type=submit]');
  ok(/Parado por problema/.test(await txt(bruno)) && sql("SELECT CONCAT(parou,'|',usuario) FROM britagem_problemas") === '1|Bruno', 'problema que parou o britador');

  let tv = (await (await adm.request.get(BASE + '/dashboard/dados')).json()).britador;
  ok(tv && tv.aberta === true && tv.parado === true && tv.problemas.length === 1 && !!tv.inicio, 'Monitor recebe a britagem parada e o problema', JSON.stringify(tv));

  await enviar(bruno, 'form[action*="/britador/problemas/"] button');
  ok(sql('SELECT resolvido_por FROM britagem_problemas') === 'Bruno' && /^Britando/.test((await bruno.textContent('h1')).trim()), 'resolvido: volta a "Britando"');

  await enviar(bruno, 'form[action$="/britador/terminar"] button');
  ok(/Britagem terminada/.test(await txt(bruno)) && sql('SELECT COUNT(*) FROM britagens WHERE fim IS NOT NULL') === '1', 'termina a britagem');
  tv = (await (await adm.request.get(BASE + '/dashboard/dados')).json()).britador;
  ok(tv.aberta === false && !!tv.fim && tv.problemas.length === 1 && tv.problemas[0].resolvido === true, 'Monitor recebe o fim e o problema resolvido', JSON.stringify(tv));

  const joao = await T.entrar(b, 'Joao', 'joao1234');
  await T.assumir(joao, 'caminhao:1', '100', { envelhecer: true });
  ok((await joao.goto(BASE + '/britador')).status() === 403, 'motorista não abre a tela do britador');
  T.resumo();
});
