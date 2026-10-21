'use strict';
// Confere se o Playwright e o Chromium estão utilizáveis. Sai com 1 e explica na saída de erro se não estiverem.
const T = require('./teste');

(async () => {
  let b;
  try {
    const { chromium } = T.carregarPlaywright();
    b = await chromium.launch();
    await b.close();
  } catch (e) {
    const msg = String((e && e.message) || e).split('\n')[0];
    const dica = /Executable doesn't exist|browserType\.launch/.test(msg)
      ? 'Chromium não encontrado: aponte PLAYWRIGHT_BROWSERS_PATH para a pasta dos navegadores (na nuvem é /opt/pw-browsers) '
        + 'ou rode "npx playwright install chromium"'
      : msg;
    console.error(dica);
    process.exit(1);
  }
})();
