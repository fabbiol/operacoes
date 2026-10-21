// Atualização automática da lavra no tablet: a consulta de situação vai direto ao servidor (não passa pelo serviço do app),
// uma ação normal funciona com o app e, sem sinal, a faixa de conexão aparece e some quando o sinal volta.
const T = require('../lib/teste');
const { ok, espera, BASE } = T;

T.executar(async () => {
  const b = await T.abrir();
  const situacoes = [];
  const p = await T.entrar(b, 'Ana', 'ana12345', { largura: 600, altura: 960 });
  const ctx = p.context();
  p.on('response', r => { if (r.url().includes('/situacao')) situacoes.push({ st: r.status(), sw: r.fromServiceWorker() }); });
  await p.evaluate(() => navigator.serviceWorker.ready);
  await p.goto(BASE + '/maquina');
  await p.locator('label:has(input[value="caminhao:2"])').click();
  await p.fill('input[name=leitura]', '500');
  await espera(1300);
  await Promise.all([p.waitForNavigation(), p.locator('form[action$="/maquina"] button[type=submit]').first().click()]);
  ok(/\/lavra$/.test(p.url()), 'Ana assume o caminhão 2', p.url());
  ok(await p.evaluate(() => !!navigator.serviceWorker.controller), 'tela controlada pelo app');
  await espera(12000);
  ok(situacoes.length > 0 && situacoes.every(s => s.st === 200 && !s.sw), 'atualização automática da lavra segue direto ao servidor', JSON.stringify(situacoes.slice(0, 3)) + ' n=' + situacoes.length);
  // Ação normal com o app: chegar na fila.
  const botao = p.locator('#fluxo form button[type=submit]').first();
  const antes = await botao.innerText();
  await Promise.all([p.waitForNavigation(), botao.click()]);
  ok(!/Sem conexão/.test(await p.textContent('body')), 'ação da lavra funciona com o app: ' + antes.replace(/\s+/g, ' '), (await p.locator('.aviso').allInnerTexts()).join(' | '));
  // Sinal cai com a tela aberta: a faixa de conexão do sistema avisa e o envio é barrado.
  await ctx.setOffline(true);
  await p.waitForFunction(() => { const f = document.getElementById('conexao'); return f && !f.hidden; }, null, { timeout: 30000 }).catch(() => {});
  ok(await p.locator('#conexao').isVisible(), 'sem sinal com a tela aberta: faixa de conexão aparece', await p.locator('#conexao').innerText().catch(() => ''));
  await ctx.setOffline(false);
  await p.waitForFunction(() => { const f = document.getElementById('conexao'); return f && f.hidden; }, null, { timeout: 30000 }).catch(() => {});
  ok(await p.locator('#conexao').isHidden(), 'sinal voltou: faixa some');
  T.resumo();
});
