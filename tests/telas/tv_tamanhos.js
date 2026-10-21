// Monitor da TV em tela cheia em vários tamanhos de televisão: nada fica cortado nem exige rolagem.
const T = require('../lib/teste');
const { espera, BASE } = T;

const TAMANHOS = ['960x540', '1280x720', '1366x768', '1920x1080'];

T.executar(async () => {
  const b = await T.abrir();
  for (const t of TAMANHOS) {
    const [w, h] = t.split('x').map(Number);
    const p = await T.entrar(b, 'Fabio', 'senha-forte-123', { largura: w, altura: h });
    await p.goto(BASE + '/dashboard');
    await p.click('#ampliar');
    await espera(1500);
    await T.capturar(p, 'tv_' + t);
    const info = await p.evaluate(() => {
      const tv = document.querySelector('.tv');
      const cortados = [...tv.querySelectorAll('section,article')].filter(el => el.getBoundingClientRect().bottom > window.innerHeight + 1).map(el => el.className);
      return { tvAltura: Math.round(tv.getBoundingClientRect().height), janela: window.innerHeight, rolagem: document.documentElement.scrollHeight, cortados };
    });
    T.ok(info.cortados.length === 0 && info.rolagem <= info.janela + 1, t + ': tudo cabe na tela, sem rolagem', JSON.stringify(info));
  }
  T.resumo();
});
