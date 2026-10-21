// App do tablet no Chrome: manifesto e instalação, tela sempre acesa quando há máquina, tela "Sem conexão" quando cai o sinal
// ou o servidor, e volta sozinha. Derruba e religa o servidor do ambiente (o runner passa TESTE_SERVIDOR_PID e TESTE_SERVIR).
const T = require('../lib/teste');
const { spawn } = require('child_process');
const { ok, espera, BASE } = T;

async function contexto(browser, extra = {}) {
  const ctx = await browser.newContext({ viewport: { width: 600, height: 960 }, locale: 'pt-BR', ...extra });
  // Tela acesa: o Chromium sem tela recusa o pedido; um registro no lugar mostra se o pedido foi feito.
  await ctx.addInitScript(() => {
    window.__travas = 0;
    Object.defineProperty(navigator, 'wakeLock', { configurable: true, value: { request: () => { window.__travas++; const s = new EventTarget(); s.release = () => Promise.resolve(); return Promise.resolve(s); } } });
  });
  return ctx;
}
async function pagina(ctx, nome) {
  const p = T.vigiar(await ctx.newPage(), nome + ' PAGEERROR');
  p.on('console', m => { if (m.type() === 'error' && !/ERR_INTERNET_DISCONNECTED|Failed to load resource|ERR_CONNECTION_REFUSED/.test(m.text())) T.erros.push(nome + ' CONSOLE ' + m.text()); });
  return p;
}
async function entrar(p, usuario, senha) {
  await p.goto(BASE + '/login');
  await p.fill('input[name=usuario]', usuario);
  await p.fill('input[name=senha]', senha);
  await espera(1300);
  await Promise.all([p.waitForNavigation(), p.click('form.formulario button[type=submit]')]);
}


// Derruba o servidor do ambiente e espera a porta fechar; religa pelo mesmo script que o runner usa.
async function derrubarServidor() {
  process.kill(Number(process.env.TESTE_SERVIDOR_PID));
  for (let i = 0; i < 40; i++) {
    try { await fetch(BASE + '/health', { signal: AbortSignal.timeout(1000) }); } catch (e) { return; }
    await espera(250);
  }
}
function religarServidor() {
  spawn('sh', [process.env.TESTE_SERVIR, process.env.TESTE_PORTA], { detached: true, stdio: 'ignore' }).unref();
}
let browser;
T.executar(async () => {
  browser = await T.abrir();

  // 1. Administrativo autoriza este "tablet".
  const ctxTab = await contexto(browser);
  let p = await pagina(ctxTab, 'tablet');
  await entrar(p, 'Fabio', 'senha-forte-123');
  await p.goto(BASE + '/tablets');
  await p.fill('input[name=nome]', 'Tablet teste');
  await espera(1300);
  await Promise.all([p.waitForNavigation(), p.locator('form[action$="/tablets"] button[type=submit]').click()]);
  ok(/autorizado|Tablet teste/i.test(await p.textContent('main')), 'tablet autorizado');

  // 2. Manifesto e instalação.
  const cdp = await ctxTab.newCDPSession(p);
  const man = await cdp.send('Page.getAppManifest');
  ok(man.errors.length === 0 && /"short_name": "Próspera"/.test(man.data), 'manifesto lido sem erro', JSON.stringify(man.errors));
  const sw = await p.evaluate(async () => { const r = await navigator.serviceWorker.ready; return r.scope; });
  ok(sw === BASE + '/', 'serviço do app ativo para o site todo', sw);
  await p.reload();
  const inst = await cdp.send('Page.getInstallabilityErrors');
  ok(inst.installabilityErrors.length === 0, 'Chrome aceita instalar o app', JSON.stringify(inst.installabilityErrors));
  const controlada = await p.evaluate(() => !!navigator.serviceWorker.controller);
  ok(controlada, 'a tela já passa pelo serviço do app');

  // 3. Admin no tablet autorizado. A faixa "Baixar o app" (que levou o lugar do pedido de instalação do Chrome) é conferida
  // em app_android.js; aqui fica a tela acesa: só onde há máquina ou no Monitor da TV.
  await p.reload();
  ok(await p.evaluate(() => window.__travas) === 0 && await p.getAttribute('body', 'data-acesa') === null, 'admin no painel: tela apaga normalmente');
  await p.goto(BASE + '/dashboard');
  await espera(300);
  ok(await p.evaluate(() => window.__travas) >= 1, 'monitor da TV: tela fica acesa', await p.evaluate(() => window.__travas));
  ok(await p.locator('#instalar').count() === 0, 'monitor da TV sem faixa de instalar');
  // Sair (envio normal, sem passar pelo serviço).
  await p.goto(BASE + '/painel');
  await espera(1300);
  await Promise.all([p.waitForNavigation(), p.click('form[action$="/sair"] button')]);
  ok(/\/login/.test(p.url()) || /Entrar/.test(await p.textContent('body')), 'sair funciona com o app', p.url());

  // 4. Operadora entra no tablet autorizado pela foto e assume uma máquina.
  ok(await p.locator('a.rosto').count() > 0, 'tablet autorizado mostra as fotos da equipe');
  await p.goto(BASE + '/login');
  await espera(1300);
  await Promise.all([p.waitForNavigation(), p.locator('a.rosto', { hasText: 'Maria' }).first().click()]);
  ok(await p.locator('input[name=senha]').evaluate(el => document.activeElement === el), 'foto da Maria: o campo da senha já vem selecionado');
  await T.capturar(p, 'app_senha');
  await p.fill('input[name=senha]', 'maria1234');
  await espera(1300);
  await Promise.all([p.waitForNavigation(), p.click('.entrada-senha button[type=submit]')]);
  await p.goto(BASE + '/maquina');
  ok(await p.getAttribute('body', 'data-acesa') === null, 'sem máquina: tela apaga normalmente');
  await p.check('input[name=maquina][value="pa:1"]').catch(async () => { await p.locator('label:has(input[value="pa:1"])').click(); });
  await p.fill('input[name=leitura]', '100');
  await espera(1300);
  await Promise.all([p.waitForNavigation(), p.locator('form[action$="/maquina"] button[type=submit]').first().click()]);
  ok(/\/patio$/.test(p.url()), 'Maria assume a Pá 1 (envio de formulário com o app)', p.url());
  await espera(300);
  ok(await p.getAttribute('body', 'data-acesa') === '1' && await p.evaluate(() => window.__travas) >= 1, 'com máquina: tela fica acesa');

  // 5. Sem sinal: a tela da Próspera no lugar do erro do navegador, e volta sozinha.
  await ctxTab.setOffline(true);
  await p.goto(BASE + '/lavra').catch(() => {});
  await p.goto(BASE + '/patio').catch(e => T.erros.push('goto offline ' + e.message));
  const txt = await p.textContent('body');
  ok(/Sem conexão/.test(txt) && /volta sozinha/.test(txt), 'sem sinal: aparece a tela "Sem conexão" da Próspera');
  ok(/\/patio$/.test(p.url()), 'endereço continua o da tela pedida', p.url());
  await p.click('#tentar');
  await espera(800);
  ok(/Ainda sem sinal/.test(await p.textContent('#situacao')), '"Tentar agora" sem sinal avisa que ainda não voltou', await p.textContent('#situacao'));
  await ctxTab.setOffline(false);
  await p.waitForFunction(() => !document.getElementById('tentar'), null, { timeout: 15000 }).catch(() => {});
  const volta = await p.textContent('body');
  ok(!/Sem conexão/.test(volta) && /Pá 1/.test(volta), 'sinal voltou: o pátio abre sozinho, com a Maria e a Pá 1', p.url());

  // 6. Servidor fora do ar (Wi-Fi ligado, mas o sistema não responde).
  await derrubarServidor();
  await p.goto(BASE + '/maquina').catch(e => T.erros.push('goto server down ' + e.message));
  ok(/Sem conexão/.test(await p.textContent('body')), 'servidor fora: tela "Sem conexão"');
  religarServidor();
  await p.waitForFunction(() => !document.getElementById('tentar'), null, { timeout: 25000 }).catch(() => {});
  ok(/Máquina|Pá 1/.test(await p.textContent('body')) && !/Sem conexão/.test(await p.textContent('body')), 'servidor voltou: a tela abre sozinha', p.url());

  // 7. Aparelho não autorizado: sem faixa de instalar.
  const ctxPc = await contexto(browser);
  const pc = await pagina(ctxPc, 'pc');
  await entrar(pc, 'Joao', 'joao1234');
  ok(await pc.locator('#instalar').count() === 0, 'aparelho não autorizado: sem faixa de instalar');

  T.resumo();
});
