// App Android do tablet: página /app (sem entrar), faixa "Baixar o app" no Chrome do tablet autorizado e,
// dentro do app (que se identifica no navegador), pedido de tela acesa ao Android em vez da trava do Chrome.
const T = require('../lib/teste');
const { ok, espera, BASE } = T;
let b;
async function nova(ctx, nome) { return T.vigiar(await ctx.newPage(), nome); }
async function entrarAdmin(p) {
  await p.goto(BASE + '/login');
  await p.fill('input[name=usuario]', 'Fabio'); await p.fill('input[name=senha]', 'senha-forte-123');
  await espera(1300);
  await Promise.all([p.waitForNavigation(), p.click('form.formulario button[type=submit]')]);
}
async function autorizar(p, nome) {
  await p.goto(BASE + '/tablets');
  await p.fill('input[name=nome]', nome);
  await espera(1300);
  await Promise.all([p.waitForNavigation(), p.locator('form[action$="/tablets"] button[type=submit]').click()]);
}
async function sair(p) {
  await espera(1300);
  await Promise.all([p.waitForNavigation(), p.click('form[action$="/sair"] button')]);
}
async function entrarFoto(p, nome, senha) {
  await p.goto(BASE + '/login');
  await espera(1300);
  await Promise.all([p.waitForNavigation(), p.locator('a.rosto', { hasText: nome }).first().click()]);
  await p.fill('input[name=senha]', senha);
  await espera(1300);
  await Promise.all([p.waitForNavigation(), p.click('.entrada-senha button[type=submit]')]);
}
T.executar(async () => {
  b = await T.abrir();
  // 1. Página do app, sem entrar.
  const ctx0 = await b.newContext({ viewport: { width: 600, height: 960 }, locale: 'pt-BR' });
  const p0 = await nova(ctx0, 'pagina');
  const r0 = await p0.goto(BASE + '/app');
  ok(r0.status() === 200 && /App da Próspera para Android/.test(await p0.textContent('main')), 'página /app abre sem entrar');
  const href = await p0.getAttribute('a.btn.primario', 'href');
  ok(/\/baixar\/prospera\.apk\?v=\d+(\.\d+)+$/.test(href), 'botão aponta para o arquivo do app', href);
  const dl = await p0.request.get(BASE + href);
  ok(dl.status() === 200 && (await dl.body()).length > 10000, 'arquivo do app baixa', dl.status());
  await T.capturar(p0, 'app_pagina', { fullPage: true });

  // 2. Tablet autorizado, no Chrome: faixa leva para /app.
  const ctx1 = await b.newContext({ viewport: { width: 600, height: 960 }, locale: 'pt-BR' });
  const p1 = await nova(ctx1, 'chrome');
  await entrarAdmin(p1); await autorizar(p1, 'Tablet Chrome'); await sair(p1);
  await entrarFoto(p1, 'Maria', 'maria1234');
  ok(await p1.locator('#instalar').isVisible(), 'tablet autorizado no Chrome: faixa aparece');
  ok(await p1.getAttribute('#instalar a.btn.primario', 'href') === '/app', 'faixa: Baixar o app leva para /app');
  await p1.click('[data-depois]');
  await p1.reload();
  ok(await p1.locator('#instalar').isHidden(), '"Agora não" esconde a faixa');

  // 3. Dentro do app (o app se identifica no navegador e oferece ProsperaApp.telaAcesa).
  const ctx2 = await b.newContext({ viewport: { width: 600, height: 960 }, locale: 'pt-BR',
    userAgent: (await b.newBrowserCDPSession().then(s => s.send('Browser.getVersion'))).userAgent + ' ProsperaApp/1.0' });
  await ctx2.addInitScript(() => {
    window.__acesa = [];
    window.ProsperaApp = { telaAcesa: v => window.__acesa.push(v), versao: () => '1.0' };
    window.__travas = 0;
    Object.defineProperty(navigator, 'wakeLock', { configurable: true, value: { request: () => { window.__travas++; return Promise.resolve(new EventTarget()); } } });
  });
  const p2 = await nova(ctx2, 'app');
  await entrarAdmin(p2); await autorizar(p2, 'Tablet App'); await sair(p2);
  ok(await p2.locator('#instalar').count() === 0, 'dentro do app: sem faixa de baixar o app');
  await entrarFoto(p2, 'Pedro', 'pedro1234');
  ok(JSON.stringify(await p2.evaluate(() => window.__acesa)) === '[false]', 'dentro do app, sem máquina: pede tela normal', JSON.stringify(await p2.evaluate(() => window.__acesa)));
  await p2.goto(BASE + '/maquina');
  await p2.locator('label:has(input[value="pa:2"])').click();
  await p2.fill('input[name=leitura]', '200');
  await espera(1300);
  await Promise.all([p2.waitForNavigation(), p2.locator('form[action$="/maquina"] button[type=submit]').first().click()]);
  ok(/\/patio$/.test(p2.url()), 'Pedro assume a Pá 2 dentro do app', p2.url());
  ok(JSON.stringify(await p2.evaluate(() => window.__acesa)) === '[true]', 'com máquina: o app recebe "tela acesa"', JSON.stringify(await p2.evaluate(() => window.__acesa)));
  ok(await p2.evaluate(() => window.__travas) === 0, 'dentro do app não usa a trava do Chrome (quem cuida é o app)');
  const r2 = await p2.goto(BASE + '/app');
  ok(/já está usando o app/.test(await p2.textContent('main')), 'página /app avisa que já está no app');

  T.resumo();
});
