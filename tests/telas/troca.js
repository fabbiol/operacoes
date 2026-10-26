// Manutenção pelo operador: Maria, com a Pá 1, registra no tablet a primeira troca de óleo dizendo o prazo (250 h);
// quando faltam 40 h, o aviso aparece no tablet dela e no Painel do administrativo; a troca seguinte é só escolher.
const T = require('../lib/teste');
const { ok, sql, txt, enviar, BASE } = T;

T.executar(async () => {
  const b = await T.abrir();
  const maria = await T.entrar(b, 'Maria', 'maria1234');
  await maria.goto(BASE + '/troca');
  ok(/\/maquina$/.test(maria.url()), 'sem máquina, a troca manda escolher a máquina', maria.url());
  await T.assumir(maria, 'pa:1', '1000', { envelhecer: true });

  ok(await maria.locator('nav.menu a[href$="/troca"]').count() === 1, 'com máquina, o menu tem "Manutenção"');
  await maria.goto(BASE + '/troca');
  let tela = await txt(maria);
  ok(/Pá 1/.test(tela) && /Horímetro atual 1\.000,0 h/.test(tela), 'tela de manutenção da máquina dela', tela.slice(0, 300));
  ok(await maria.isChecked('input[name=servico][value=novo]'), 'sem serviço na lista: "Outro serviço" já vem marcado');
  await maria.fill('input[name=nome]', 'Troca de óleo do motor');
  await maria.fill('input[name=intervalo]', '250');
  await T.capturar(maria, 'troca_primeira', { fullPage: true });
  await enviar(maria, 'form.formulario button[type=submit]');
  tela = await txt(maria);
  ok(/Troca de óleo do motor registrada com 1\.000,0 h\. A próxima é com 1\.250,0 h\./.test(tela), 'troca registrada com o horímetro atual', tela.slice(0, 300));
  ok(sql("SELECT CONCAT(p.intervalo_h, '|', m.horimetro, '|', m.usuario) FROM manutencao_planos p JOIN manutencoes m ON m.plano_id = p.id") === '250.0|1000.0|Maria', 'prazo salvo e troca gravada');

  // Faltam 40 h: o aviso aparece em todas as telas do tablet dela e no Painel.
  sql("INSERT INTO horimetros (tipo, maquina_id, leitura, lido_em, usuario) VALUES ('pa', 1, 1210, NOW() - INTERVAL 1 HOUR, 'Maria')");
  await maria.goto(BASE + '/patio');
  tela = await txt(maria);
  ok(/Manutenção chegando 1 Troca de óleo do motor Faltam 40,0 h · próxima com 1\.250,0 h/.test(tela), 'aviso no tablet da Maria', tela.slice(0, 400));
  ok(await maria.locator('.manutencao-aviso li.faixa-50').count() === 1, 'na faixa de 50 h');
  await T.capturar(maria, 'troca_aviso_tablet', { fullPage: true });

  const adm = await T.entrar(b, 'Fabio', 'senha-forte-123', { largura: 1280, altura: 1000 });
  await adm.goto(BASE + '/painel');
  tela = await txt(adm);
  ok(/Manutenção chegando 1 Pá 1 Troca de óleo do motor Faltam 40,0 h/.test(tela), 'aviso no Painel', tela.slice(0, 500));
  await T.capturar(adm, 'troca_aviso_painel', { fullPage: true });
  await adm.goto(BASE + '/troca');
  ok(/\/manutencao$/.test(adm.url()), 'o administrativo vai para a Manutenção', adm.url());

  // A troca seguinte: só escolher na lista. O aviso some.
  await maria.goto(BASE + '/troca');
  await maria.check('input[name=servico]:not([value=novo])', { force: true });
  await enviar(maria, 'form.formulario button[type=submit]');
  ok(/registrada com 1\.210,0 h\. A próxima é com 1\.460,0 h\./.test(await txt(maria)), 'segunda troca com o prazo salvo');
  await maria.goto(BASE + '/patio');
  ok(await maria.locator('.manutencao-aviso').count() === 0, 'depois da troca, o aviso some');
  T.resumo();
});
