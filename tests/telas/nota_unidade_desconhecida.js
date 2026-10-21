// Nota com unidade que o sistema não conhece (SC) e outra unidade (M3): o formulário não escolhe unidade sozinho e avisa as duas.
// Também confere a data de um XML de leiaute antigo, que traz só a data da emissão.
const T = require('../lib/teste');
const path = require('path');
const { espera, BASE } = T;

T.executar(async () => {
  const b = await T.abrir();
  const p = await T.entrar(b, 'Fabio', 'senha-forte-123');
  await p.goto(BASE + '/ordens/nova');
  await p.setInputFiles('form.ler-nota input[type=file]', path.join(T.NOTAS, 'nota_unidade_desconhecida.xml'));
  await espera(1300);
  await Promise.all([p.waitForNavigation(), p.click('form.ler-nota button[type=submit]')]);
  const sel = await p.inputValue('form[action$="/ordens/nova"] select[name=unidade]');
  const avisos = (await p.locator('.leitura-nota').innerText()).replace(/\s+/g, ' ');
  T.ok(sel === '', 'unidade desconhecida: select em "Escolha"', sel);
  T.ok(/outra unidade \(M3\)/.test(avisos) && /"SC"/.test(avisos), 'avisos da unidade desconhecida e da outra unidade', avisos);
  T.ok((await p.inputValue('[name=nf_emitida_data]')) === '2026-10-14', 'data do leiaute antigo');
  T.resumo();
});
