// Monitor da TV em tela sensível ao toque: com a tela cheia guardada, o primeiro toque ocupa a tela toda
// e tocar em "Sair" volta ao normal.
const T = require('../lib/teste');
const { espera, BASE } = T;

T.executar(async () => {
  const b = await T.abrir();
  const p = await T.entrar(b, 'Fabio', 'senha-forte-123', { largura: 1280, altura: 720, contexto: { hasTouch: true } });
  await p.goto(BASE + '/dashboard');
  await p.evaluate(() => localStorage.setItem('monitor-tela-cheia', '1'));
  await p.reload(); await espera(800);
  T.ok(await p.evaluate(() => document.body.classList.contains('tela-cheia') && !document.fullscreenElement), 'recarregou em tela cheia (sem a tela toda ainda)');
  await p.tap('.tv-zonas'); await espera(600);
  T.ok(await p.evaluate(() => !!document.fullscreenElement), 'um toque na tela: ocupa a tela toda');
  await p.tap('#ampliar'); await espera(600);
  T.ok(await p.evaluate(() => !document.fullscreenElement && !document.body.classList.contains('tela-cheia')), 'tocar em Sair: volta ao normal');
  T.resumo();
});
