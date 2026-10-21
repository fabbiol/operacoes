// Monitor da TV: botão "Ampliar" guarda a escolha de tela cheia, recarregar mantém, um toque pede a tela toda do navegador
// e "Sair da tela cheia" esquece a escolha.
const T = require('../lib/teste');
const { espera, BASE } = T;

T.executar(async () => {
  const b = await T.abrir();
  const p = await T.entrar(b, 'Fabio', 'senha-forte-123', { largura: 1280, altura: 720 });
  await p.goto(BASE + '/dashboard');
  const emTelaCheia = () => p.evaluate(() => document.body.classList.contains('tela-cheia'));
  T.ok(!(await emTelaCheia()), 'sem escolha: normal');
  await p.click('#ampliar');
  T.ok(await p.evaluate(() => localStorage.getItem('monitor-tela-cheia')) === '1', 'clicou: guardou');
  await p.reload(); await espera(800);
  T.ok(await emTelaCheia(), 'recarregou: continua em tela cheia');
  T.ok((await p.textContent('#ampliar')).trim() === 'Sair da tela cheia', 'botão diz Sair');
  await p.mouse.click(300, 300); await espera(500);
  T.ok(await p.evaluate(() => !!document.fullscreenElement), 'toque: pediu a tela toda');
  await p.click('#ampliar');
  T.ok(await p.evaluate(() => localStorage.getItem('monitor-tela-cheia')) === '0' && !(await emTelaCheia()), 'saiu: esqueceu a escolha');
  await p.reload(); await espera(800);
  T.ok(!(await emTelaCheia()), 'recarregou: normal');
  T.resumo();
});
