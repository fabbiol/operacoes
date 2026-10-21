// Limpeza da lavra: a escavadeira marca o tempo em que está limpando; o tempo não entra na fila nem no ciclo médio,
// aparece no Painel e no Monitor da TV, e termina pelo botão, pelo administrativo ou ao soltar a máquina.
const T = require('../lib/teste');
const { ok, sql, espera, txt, BASE } = T;
const entrar = (b, usuario, senha, largura = 800) => T.entrar(b, usuario, senha, { largura });
const enviar = T.enviar;
const assumir = (p, valor, leitura) => T.assumir(p, valor, leitura);
T.executar(async () => {
  const b = await T.abrir();
  const maria = await entrar(b, 'Maria', 'maria1234');
  await assumir(maria, 'retro:1', '50');
  await maria.goto(BASE + '/lavra');
  ok(await maria.locator('button:has-text("Iniciar limpeza da lavra")').count() === 1, 'escavadeira vê "Iniciar limpeza da lavra"');

  const joao = await entrar(b, 'Joao', 'joao1234');
  await assumir(joao, 'caminhao:1', '2000');
  await joao.goto(BASE + '/lavra');
  await enviar(joao, 'form[action$="/lavra/caminhoes/1/fila"] button');
  ok(sql("SELECT status FROM ciclos_lavra") === 'na_fila', 'caminhão do Joao na fila da escavadeira');

  await maria.goto(BASE + '/lavra');
  await enviar(maria, 'button:has-text("Iniciar limpeza da lavra")');
  ok(/Limpeza da lavra começou/.test(await txt(maria)) && await maria.locator('.limpeza-lavra.ativa button:has-text("Terminar limpeza")').count() === 1, 'limpeza começou: aparece o tempo e "Terminar limpeza"');
  ok(sql("SELECT CONCAT(retroescavadeira_id,'|',operador,'|',fim IS NULL) FROM lavra_limpezas") === '1|Maria|1', 'limpeza gravada (Retro 1, Maria, em andamento)');
  await T.capturar(maria, 'limpeza_escavadeira', { fullPage: true });

  await joao.goto(BASE + '/lavra');
  ok(/Retro 1 em limpeza da lavra/.test(await txt(joao)), 'o motorista na fila vê que a escavadeira está em limpeza');

  const adm = await entrar(b, 'Fabio', 'senha-forte-123', 1280);
  await adm.goto(BASE + '/painel');
  ok(/Retro 1 em limpeza da lavra desde \d\d:\d\d · Maria/.test(await txt(adm)), 'Painel mostra a limpeza em andamento', (await txt(adm)).match(/Retro 1 em limpeza[^·]*·[^·]*/)?.[0]);
  await adm.goto(BASE + '/dashboard'); await espera(800);
  ok(/Retro 1 em limpeza da lavra/.test(await adm.locator('#monitor').innerText()), 'Monitor da TV mostra a limpeza');

  // 20 min de limpeza durante 25 min de fila. Entre 00:00 e 00:30 (Cuiabá) isso cairia no dia anterior.
  const [hh, mm] = new Intl.DateTimeFormat('en-GB', { timeZone: 'America/Cuiaba', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(new Date()).split(':').map(Number);
  if (hh === 0 && mm < 30) T.pular('perto da meia-noite (Cuiabá): a limpeza de 20 min cairia no dia anterior; rode depois das 00:30');
  sql("UPDATE lavra_limpezas SET inicio = inicio - INTERVAL 20 MINUTE; UPDATE ciclos_lavra SET inicio_fila = inicio_fila - INTERVAL 25 MINUTE");
  await maria.goto(BASE + '/lavra');
  await enviar(maria, 'form[action*="/lavra/ciclos/"][action$="/carga"] button');
  if (/\/carga$/.test(maria.url())) await enviar(maria, 'form.formulario button[type=submit]');
  ok(sql("SELECT status FROM ciclos_lavra") === 'carregando', 'escavadeira iniciou a carga', maria.url());
  ok(sql("SELECT fim IS NULL FROM lavra_limpezas") === '1', 'iniciar a carga NÃO termina a limpeza (a escavadeira carrega e volta a limpar)');
  ok(/Limpeza da lavra continua marcada/.test(await txt(maria)), 'durante a carga, a tela lembra que a limpeza continua');
  const r = await maria.evaluate(async () => {
    const f = document.querySelector('form[action$="/concluir-carga"]');
    const dados = new URLSearchParams();
    for (const el of f.querySelectorAll('input[type=hidden]')) dados.append(el.name, el.value);
    return (await fetch('/lavra/limpeza/iniciar', { method: 'POST', body: dados })).url;
  });
  ok(sql("SELECT COUNT(*) FROM lavra_limpezas") === '1', 'durante a carga não abre outra limpeza');
  await maria.goto(BASE + '/lavra');
  await enviar(maria, 'form[action$="/concluir-carga"] button');
  ok(sql("SELECT status FROM ciclos_lavra") === 'em_viagem', 'carga concluída');
  // Termina a viagem.
  sql("UPDATE ciclos_lavra SET chegada_britador = fim_carga + INTERVAL 8 MINUTE, inicio_retorno = fim_carga + INTERVAL 10 MINUTE, fim_ciclo = fim_carga + INTERVAL 18 MINUTE, status = 'concluido'");
  sql("UPDATE ciclos_lavra SET inicio_carga = inicio_carga - INTERVAL 0 MINUTE");
  await adm.goto(BASE + '/lavra/resumo');
  const resumo = await txt(adm);
  const fila = resumo.match(/Fila (\d+ min)/)?.[1], limpeza = resumo.match(/Limpeza (\d+ min)/)?.[1];
  ok(fila === '5 min' && limpeza === '20 min', 'tempo médio: fila 5 min (25 min menos 20 de limpeza) e limpeza 20 min à parte', 'fila ' + fila + ' · limpeza ' + limpeza);
  ok(/não entra na fila nem no ciclo médio/.test(resumo), 'resumo explica que a limpeza não entra na média');
  await adm.goto(BASE + '/painel');
  const painel = await txt(adm);
  ok(/Limpeza 2\d min/.test(painel) && /Retro 1 em limpeza da lavra/.test(painel), 'Painel: limpeza do dia (sem o tempo de carga) e a limpeza ainda em andamento', (painel.match(/Limpeza \d+ min/) || [''])[0]);
  // O administrativo termina pelo Painel.
  await enviar(adm, '.limpeza-aviso button:has-text("Terminar")');
  ok(/Limpeza da lavra terminada/.test(await txt(adm)) && sql("SELECT COUNT(*) FROM lavra_limpezas WHERE fim IS NULL") === '0', 'administrativo termina a limpeza pelo Painel');
  await T.capturar(adm, 'limpeza_painel', { fullPage: true });

  // Terminar pelo botão e soltar a escavadeira também terminam.
  await maria.goto(BASE + '/lavra');
  await enviar(maria, 'button:has-text("Iniciar limpeza da lavra")');
  await enviar(maria, '.limpeza-lavra.ativa button:has-text("Terminar limpeza")');
  ok(/Limpeza da lavra terminada/.test(await txt(maria)) && sql("SELECT COUNT(*) FROM lavra_limpezas WHERE fim IS NULL") === '0', 'Terminar limpeza');
  await enviar(maria, 'button:has-text("Iniciar limpeza da lavra")');
  sql('UPDATE horimetros SET lido_em = lido_em - INTERVAL 12 HOUR; UPDATE maquina_posse SET iniciado_em = iniciado_em - INTERVAL 12 HOUR');
  await maria.goto(BASE + '/maquina');
  await maria.fill('input[name=leitura]', '56');
  await maria.check('input[name=situacao][value=ok]', { force: true });
  await enviar(maria, 'form.soltar-maquina button[type=submit]');
  ok(sql("SELECT COUNT(*) FROM lavra_limpezas WHERE fim IS NULL") === '0' && sql("SELECT COUNT(*) FROM maquina_posse WHERE usuario='Maria'") === '0', 'soltar a escavadeira termina a limpeza');

  // Motorista não pode começar limpeza.
  const antes = sql('SELECT COUNT(*) FROM lavra_limpezas');
  await joao.goto(BASE + '/lavra');
  // Envia com o _csrf válido da própria tela: a recusa tem de vir da checagem de papel (403), não do token.
  const status = await joao.evaluate(async () => { const d = new URLSearchParams(); const t = document.querySelector('input[name=_csrf]'); if (t) d.append('_csrf', t.value); const r = await fetch('/lavra/limpeza/iniciar', { method: 'POST', body: d }); return r.status; });
  ok(status === 403 && sql('SELECT COUNT(*) FROM lavra_limpezas') === antes, 'motorista não começa limpeza (acesso recusado)', status);

  T.resumo();
});
