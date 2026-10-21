// @sem-ambiente
// Som do aviso do pátio quando o navegador não tem AudioContext: toca o arquivo WAV de 3 bipes (cerca de 1 s) e mostra o quadro.
// Não precisa de servidor nem de banco: carrega public/assets/avisos.js numa página em branco.
const path = require('path');
const T = require('../lib/teste');
const { espera } = T;

T.executar(async () => {
  const b = await T.abrir({ args: ['--autoplay-policy=no-user-gesture-required'] });
  const p = T.vigiar(await (await b.newContext()).newPage());
  await p.setContent('<html><body></body></html>');
  // Sem AudioContext: força o caminho do arquivo WAV.
  await p.evaluate(() => {
    window.AudioContext = undefined; window.webkitAudioContext = undefined;
    window.__tocou = []; const original = HTMLMediaElement.prototype.play;
    HTMLMediaElement.prototype.play = function () { const el = this; return original.call(this).then(() => { window.__tocou.push(el.duration); }); };
  });
  await p.addScriptTag({ path: path.join(T.RAIZ, 'public', 'assets', 'avisos.js') });
  await p.evaluate(() => {
    const s = document.createElement('script'); s.type = 'application/json'; s.setAttribute('data-avisos-patio', '');
    s.textContent = JSON.stringify({ fila: [], canceladas: [] }); document.body.appendChild(s);
    document.dispatchEvent(new CustomEvent('fluxo:trocou'));
    s.textContent = JSON.stringify({ fila: [{ id: 1, ordem: 'CAR-1', cliente: 'X', carga: '1 t', obs: '', assinatura: 'a' }], canceladas: [] });
    document.dispatchEvent(new CustomEvent('fluxo:trocou'));
  });
  await espera(1500);
  const tocou = await p.evaluate(() => window.__tocou);
  T.ok(tocou.length === 1 && tocou[0] > 0.9 && tocou[0] < 1.0, 'WAV de 3 bipes tocou (duração ' + tocou + ' s)');
  T.ok(await p.evaluate(() => /Nova carga liberada/.test(document.body.innerText)), 'quadro apareceu');
  T.resumo();
});
