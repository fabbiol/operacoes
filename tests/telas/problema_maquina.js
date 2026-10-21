// Problema nas máquinas: "Como ficou a máquina?" ao soltar, aviso no Painel e na Manutenção, ficha da máquina
// (resolver, registrar, liberar com problema) e acesso negado ao operador.
const T = require('../lib/teste');
const { ok, sql, espera, txt, BASE } = T;
const entrar = (b, usuario, senha, largura = 800) => T.entrar(b, usuario, senha, { largura });
const enviar = T.enviar;
// O horímetro recusa horas que não cabem no tempo passado: a leitura de entrada fica 12 h para trás.
const assumir = (p, valor, leitura) => T.assumir(p, valor, leitura, { envelhecer: true });
T.executar(async () => {
  const b = await T.abrir();

  // 1. Maria (pá): soltar pede "Como ficou a máquina?".
  const maria = await entrar(b, 'Maria', 'maria1234');
  await assumir(maria, 'pa:1', '100');
  ok(sql("SELECT CONCAT(tipo,':',maquina_id,'|',usuario) FROM maquina_posse") === 'pa:1|Maria', 'Maria assumiu a Pá 1');
  await maria.goto(BASE + '/maquina');
  ok(/Como ficou a máquina\?/.test(await txt(maria)), 'soltar mostra "Como ficou a máquina?"');
  ok(!(await maria.isVisible('textarea[name=problema]')), 'campo do problema escondido até marcar "Teve problema"');
  await maria.fill('input[name=leitura]', '108');
  await maria.click('form.soltar-maquina button[type=submit]'); await espera(500);
  ok(maria.url().endsWith('/maquina') && sql('SELECT COUNT(*) FROM maquina_posse') === '1', 'sem responder, o navegador não deixa soltar');
  await maria.check('input[name=situacao][value=problema]', { force: true });
  ok(await maria.isVisible('textarea[name=problema]'), 'marcou "Teve problema": aparece "Qual foi o problema?"');
  await T.capturar(maria, 'problema_soltar', { fullPage: true });
  await enviar(maria, 'form.soltar-maquina button[type=submit]');
  ok(/Escreva qual foi o problema da máquina/.test(await txt(maria)) && sql('SELECT COUNT(*) FROM maquina_posse') === '1', 'problema em branco: pede para escrever e não solta');
  ok(await maria.inputValue('input[name=leitura]') === '108' && await maria.isChecked('input[name=situacao][value=problema]'), 'a tela guarda o horímetro e a escolha');
  await maria.fill('textarea[name=problema]', '  Escavadeira  quebrou o\nar-condicionado ');
  await enviar(maria, 'form.soltar-maquina button[type=submit]');
  ok(sql('SELECT COUNT(*) FROM maquina_posse') === '0', 'soltou a Pá 1', maria.url() + ' ' + (await txt(maria)).slice(0, 300));
  ok(sql("SELECT CONCAT(tipo,':',maquina_id,'|',usuario,'|',descricao,'|',horimetro) FROM maquina_problemas") === 'pa:1|Maria|Escavadeira quebrou o ar-condicionado|108.0', 'problema gravado com quem, a máquina e o horímetro');
  ok(sql("SELECT CONCAT(horas,'|',maquina_ok) FROM horimetro_turnos") === '8.0|0', 'turno de 8 h marcado com problema');

  // 2. Pedro pega a Pá 1: vê o problema na escolha e no soltar; entrega "Tudo certo".
  const pedro = await entrar(b, 'Pedro', 'pedro1234');
  await pedro.goto(BASE + '/maquina');
  const escolha = (await pedro.locator('label.escolha-maquina', { hasText: 'Pá 1' }).innerText()).replace(/\s+/g, ' ');
  ok(/Problema: Escavadeira quebrou o ar-condicionado/.test(escolha), 'quem vai pegar a Pá 1 vê o problema aberto', escolha);
  await assumir(pedro, 'pa:1', '108');
  await pedro.goto(BASE + '/maquina');
  ok(/Já avisado e ainda não resolvido/.test(await txt(pedro)) && /Escavadeira quebrou o ar-condicionado/.test(await txt(pedro)), 'no soltar, mostra o que já foi avisado');
  await pedro.fill('input[name=leitura]', '110');
  await pedro.check('input[name=situacao][value=problema]', { force: true });
  await pedro.fill('textarea[name=problema]', 'texto que vai ficar escondido');
  await pedro.check('input[name=situacao][value=ok]', { force: true });
  ok(!(await pedro.isVisible('textarea[name=problema]')), '"Tudo certo" esconde o campo de novo');
  await enviar(pedro, 'form.soltar-maquina button[type=submit]');
  ok(sql('SELECT COUNT(*) FROM maquina_problemas') === '1' && sql("SELECT maquina_ok FROM horimetro_turnos WHERE usuario='Pedro'") === '1', '"Tudo certo": turno marcado ok e nenhum problema novo (texto escondido não vale)');

  // 3. Escavadeira (Maria) pelo Sair do topo: soltar e sair com problema.
  await assumir(maria, 'retro:1', '50');
  ok(sql("SELECT CONCAT(tipo,':',maquina_id) FROM maquina_posse WHERE usuario='Maria'") === 'retro:1', 'Maria assumiu a Retro 1');
  await enviar(maria, 'header form[action$="/sair"] button');
  ok(/Como ficou a máquina\?/.test(await txt(maria)) && /Soltar e sair/.test(await txt(maria)), 'Sair pede o horímetro e como ficou a máquina');
  await maria.fill('input[name=leitura]', '55');
  await maria.check('input[name=situacao][value=problema]', { force: true });
  await maria.fill('textarea[name=problema]', 'Vazamento de óleo no braço');
  await enviar(maria, 'form.soltar-maquina button[type=submit]');
  ok(/\/login/.test(maria.url()) && sql("SELECT COUNT(*) FROM maquina_posse WHERE usuario='Maria'") === '0', 'soltou e saiu');
  ok(sql("SELECT CONCAT(tipo,':',maquina_id,'|',descricao,'|',horimetro) FROM maquina_problemas WHERE usuario='Maria' AND tipo='retro'") === 'retro:1|Vazamento de óleo no braço|55.0', 'problema da escavadeira gravado');

  // 4. Caminhão (Joao).
  const joao = await entrar(b, 'Joao', 'joao1234');
  await assumir(joao, 'caminhao:1', '2000');
  await joao.goto(BASE + '/maquina');
  await joao.fill('input[name=leitura]', '2006');
  await joao.check('input[name=situacao][value=problema]', { force: true });
  await joao.fill('textarea[name=problema]', 'Pneu traseiro murchando');
  await enviar(joao, 'form.soltar-maquina button[type=submit]');
  ok(sql("SELECT CONCAT(tipo,':',maquina_id,'|',descricao) FROM maquina_problemas WHERE usuario='Joao'") === 'caminhao:1|Pneu traseiro murchando', 'problema do caminhão gravado');

  // 5. Formulário antigo (sem a pergunta) não solta.
  await assumir(joao, 'caminhao:1', '2006');
  await joao.goto(BASE + '/maquina');
  const r = await joao.evaluate(async () => {
    const f = document.querySelector('form.soltar-maquina') || document.createElement('form');
    const dados = new URLSearchParams();
    for (const el of f.querySelectorAll('input[type=hidden]')) dados.append(el.name, el.value);
    dados.append('leitura', '2007');
    const resp = await fetch('/maquina', { method: 'POST', body: dados, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
    return resp.text();
  });
  ok(/Responda como ficou a máquina/.test(r) && sql("SELECT COUNT(*) FROM maquina_posse WHERE usuario='Joao'") === '1', 'sem a resposta (tela antiga), não solta e pede a resposta');

  // 6. Administrativo: Painel, Manutenção, ficha, resolver, registrar e liberar com problema.
  const adm = await entrar(b, 'Fabio', 'senha-forte-123', 1280);
  await adm.goto(BASE + '/painel');
  const painel = (await adm.textContent('.problemas-painel')).replace(/\s+/g, ' ');
  ok(/Problemas nas máquinas 3/.test(painel) && /Pá 1 Escavadeira quebrou o ar-condicionado/.test(painel) && /Retro 1 Vazamento de óleo/.test(painel) && /ABC1D23 Pneu traseiro/.test(painel), 'Painel mostra os 3 problemas com a máquina', painel);
  await T.capturar(adm, 'problema_painel');
  await adm.goto(BASE + '/manutencao');
  ok(/Problema Escavadeira quebrou o ar-condicionado Maria/.test(await txt(adm)), 'Manutenção mostra o problema na Pá 1');
  await adm.goto(BASE + '/painel');
  await Promise.all([adm.waitForNavigation(), adm.click('.problemas-painel a:has-text("Pá 1")')]);
  ok(/\/cadastros\/pas\/1#problemas$/.test(adm.url()), 'o problema no Painel abre a ficha da máquina', adm.url());
  const ficha = await txt(adm);
  ok(/Problemas Escavadeira quebrou o ar-condicionado Maria/.test(ficha) && /horímetro 108,0 h/.test(ficha), 'ficha mostra o problema aberto');
  ok(/Maria · entrou 100,0 h · saiu 108,0 h .* avisou problema/.test(ficha) && /Pedro · entrou 108,0 h · saiu 110,0 h .* entregou tudo certo/.test(ficha), 'turnos mostram tudo certo / avisou problema');
  await adm.click('li.problema-aberto summary:has-text("Marcar resolvido")');
  await adm.fill('li.problema-aberto input[name=resolucao]', 'Trocado o compressor do ar');
  await enviar(adm, 'li.problema-aberto button[type=submit]');
  const resolvida = await txt(adm);
  ok(/Problema marcado como resolvido/.test(resolvida) && /Nenhum problema aberto nesta máquina/.test(resolvida) && /resolvido por Fabio .*: Trocado o compressor do ar/.test(resolvida), 'resolvido vai para a lista de resolvidos');
  ok(sql("SELECT CONCAT(resolvido_por,'|',resolucao) FROM maquina_problemas WHERE tipo='pa'") === 'Fabio|Trocado o compressor do ar', 'resolução gravada');
  await adm.click('summary:has-text("Registrar problema")');
  await adm.fill('#problemas textarea[name=problema]', 'Farol dianteiro queimado');
  await enviar(adm, '#problemas form[action$="/problemas"] button');
  ok(sql("SELECT CONCAT(usuario,'|',descricao) FROM maquina_problemas WHERE tipo='pa' AND resolvido_em IS NULL") === 'Fabio|Farol dianteiro queimado', 'administrativo registra problema na ficha');
  // Liberar a máquina que ficou com o Joao, com problema.
  await adm.goto(BASE + '/cadastros/caminhoes/1');
  await adm.click('summary:has-text("Liberar máquina")');
  await adm.fill('form[action$="/liberar"] input[name=leitura]', '2010');
  await adm.fill('form[action$="/liberar"] textarea[name=problema]', 'Retrovisor quebrado');
  await enviar(adm, 'form[action$="/liberar"] button');
  ok(/Máquina liberada. Estava com Joao; o turno foi gravado e o problema também./.test(await txt(adm)), 'liberar com problema: mensagem', (await txt(adm)).slice(0, 160));
  ok(sql("SELECT CONCAT(usuario,'|',descricao,'|',horimetro) FROM maquina_problemas WHERE descricao='Retrovisor quebrado'") === 'Fabio|Retrovisor quebrado|2010.0' && sql("SELECT maquina_ok FROM horimetro_turnos WHERE tipo='caminhao' ORDER BY id DESC LIMIT 1") === '0', 'liberar grava o problema e marca o turno');
  await T.capturar(adm, 'problema_ficha', { fullPage: true });
  // Resolver duas vezes.
  const pid = sql("SELECT id FROM maquina_problemas WHERE descricao='Retrovisor quebrado'");
  sql(`UPDATE maquina_problemas SET resolvido_em=NOW(), resolvido_por='Fabio' WHERE id=${pid}`);
  await adm.goto(BASE + '/cadastros/caminhoes/1');
  const resp = await adm.evaluate(async (pid) => {
    const f = document.querySelector('form[action$="/problemas"]');
    const dados = new URLSearchParams();
    for (const el of f.querySelectorAll('input[type=hidden]')) dados.append(el.name, el.value);
    const r = await fetch('/cadastros/caminhoes/1/problemas/' + pid + '/resolver', { method: 'POST', body: dados, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
    return r.text();
  }, pid);
  ok(/já estava resolvido/.test(resp), 'resolver de novo avisa que já estava resolvido');
  // Operador não acessa as rotas do administrativo.
  const joao2 = await entrar(b, 'Ana', 'ana12345');
  // Com caminhão assumido: sem máquina, o sistema manda para a escolha de máquina antes de chegar na checagem de acesso.
  await assumir(joao2, 'caminhao:3', '300');
  const antes = sql('SELECT COUNT(*) FROM maquina_problemas');
  // Envia com o _csrf válido da própria tela: a recusa tem de vir da checagem de acesso (403), não do token.
  const st = await joao2.evaluate(async () => {
    const dados = new URLSearchParams({ problema: 'teste' });
    const t = document.querySelector('input[name=_csrf]');
    if (t) dados.append('_csrf', t.value);
    const r = await fetch('/cadastros/pas/1/problemas', { method: 'POST', body: dados, redirect: 'manual' });
    return r.type === 'opaqueredirect' ? 'redireciona' : String(r.status);
  });
  ok(st === '403' && sql('SELECT COUNT(*) FROM maquina_problemas') === antes, 'motorista não registra problema pela ficha do administrativo (acesso recusado)', st);

  T.resumo();
});
