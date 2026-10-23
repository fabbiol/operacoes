// Previsão de vencimento: com a média de uso dos turnos da Pá 1 (8 h por dia), a Manutenção e a ficha mostram o dia
// em que a troca de óleo vence (faltam 40 h = 5 dias); a Pá 2, sem turno, fica sem previsão.
const T = require('../lib/teste');
const { ok, sql, txt, BASE } = T;

T.executar(async () => {
  sql("INSERT INTO horimetro_turnos (tipo, maquina_id, usuario, entrada, saida, horas, encerrado_em) VALUES ('pa', 1, 'Maria', 1186, 1194, 8, NOW() - INTERVAL 3 DAY), ('pa', 1, 'Maria', 1194, 1202, 8, NOW() - INTERVAL 2 DAY), ('pa', 1, 'Maria', 1202, 1210, 8, NOW() - INTERVAL 1 DAY)");
  sql("INSERT INTO horimetros (tipo, maquina_id, leitura, lido_em, usuario) VALUES ('pa', 1, 1210, NOW(), 'Fabio'), ('pa', 2, 1210, NOW(), 'Fabio')");
  sql("INSERT INTO manutencao_planos (tipo, maquina_id, nome, intervalo_h) VALUES ('pa', 1, 'Troca de óleo', 250), ('pa', 2, 'Troca de óleo', 250)");
  sql("INSERT INTO manutencoes (plano_id, horimetro, feito_em, usuario) SELECT id, 1000, NOW(), 'Fabio' FROM manutencao_planos");
  const [ddmm, dia] = sql("SELECT CONCAT(DATE_FORMAT(CURDATE() + INTERVAL 5 DAY, '%d/%m'), '|', DAYOFWEEK(CURDATE() + INTERVAL 5 DAY))").split('|');
  const esperado = 'vence por volta de ' + ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'][Number(dia) - 1] + ', ' + ddmm;

  const b = await T.abrir();
  const adm = await T.entrar(b, 'Fabio', 'senha-forte-123', { largura: 1280, altura: 1000 });
  await adm.goto(BASE + '/manutencao');
  const tela = await txt(adm);
  ok(new RegExp('Pá 1 .*Troca de óleo A cada 250,0 h ' + esperado).test(tela), 'Manutenção mostra ' + esperado + ' na Pá 1', tela.slice(0, 600));
  ok((tela.match(/vence por volta de/g) || []).length === 1, 'só a Pá 1 (com turnos) tem previsão');
  await T.capturar(adm, 'previsao_manutencao', { fullPage: true });
  await adm.goto(BASE + '/cadastros/pas/1');
  ok(new RegExp('A cada 250,0 h · última em 1\.000,0 h · ' + esperado).test(await txt(adm)), 'ficha mostra a previsão no serviço');
  T.resumo();
});
