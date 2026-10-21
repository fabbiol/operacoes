// Posse de máquina (quem está com a pá, a retro ou o caminhão) e liberação pelo administrativo, direto por HTTP.
const T = require('../lib/teste');
const { parseForms, Cliente, flash, erroTela } = require('../lib/http');
const fs = require('fs');
const { ok, sql, sqlLinhas: sqlRows } = T;
const posse = () => sqlRows('SELECT tipo, maquina_id, usuario, entrada, entrada_id, precisa_soltar FROM maquina_posse ORDER BY id');
const turnos = () => sqlRows('SELECT tipo, maquina_id, usuario, entrada, saida, horas FROM horimetro_turnos ORDER BY id');
async function form(c, path, pred) { const p = await c.get(path); return [parseForms(p.text).find(pred), p]; }
async function resposta(c, r) { return r.status === 302 ? flash((await c.seguir(r)).text) : erroTela(r.text); }

T.executar(async () => {
  ok(sql("SELECT maquina FROM pas_carregadeiras WHERE id=1") === 'Pá 1', 'nome da pá com acento certo');
  const maria = new Cliente('Maria'); await maria.login('Maria', 'maria1234');
  let r = await maria.assumir('pa:1', '100');
  ok(r.status === 302 && /patio/.test(r.location || ''), 'Maria assume Pá 1 e vai ao pátio', r.status + ' ' + r.location);
  ok(JSON.stringify(posse()) === JSON.stringify([{ tipo: 'pa', maquina_id: '1', usuario: 'Maria', entrada: '100.0', entrada_id: sql('SELECT MAX(id) FROM horimetros'), precisa_soltar: '0' }]), 'posse gravada no banco', JSON.stringify(posse()));

  const pedro = new Cliente('Pedro'); await pedro.login('Pedro', 'pedro1234');
  let p = await pedro.get('/maquina');
  ok(!/value="pa:1"/.test(p.text) && /value="pa:2"/.test(p.text), 'Pá 1 some da lista do Pedro');
  ok(/Com outras pessoas/.test(p.text) && /Com Maria desde/.test(p.text), 'Pedro vê que a Pá 1 está com a Maria');
  r = await pedro.assumir('pa:1', '101');
  ok(r.status === 200 && /assumida por Maria/.test(r.text), 'Pedro não consegue assumir a Pá 1', erroTela(r.text));

  const maria2 = new Cliente('Maria2'); r = await maria2.login('Maria', 'maria1234');
  ok(/Pá 1/.test(r.text) && !/name="acao" value="assumir"/.test(r.text), 'Maria entra em outro tablet e continua com a Pá 1', r.status);

  const adm = new Cliente('Fabio'); await adm.login('Fabio', 'senha-forte-123');
  let pg = await adm.get('/carregamentos/1/iniciar');
  let f = parseForms(pg.text).find(x => /carregamentos\/1\/iniciar$/.test(x.action));
  if (!f) { pg = await adm.get('/patio'); f = parseForms(pg.text).find(x => /carregamentos\/1\/iniciar$/.test(x.action)); }
  ok(!!f, 'admin acha o form de iniciar');
  r = await adm.post('/carregamentos/1/iniciar', { ...f.fields, operador: 'Pedro', pa_carregadeira_id: '1' });
  let txt = await resposta(adm, r);
  ok(/Pá 1 está com Maria/.test(txt || ''), 'admin não inicia carga do Pedro com a Pá 1 da Maria', txt);
  r = await adm.post('/carregamentos/1/iniciar', { ...f.fields, operador: 'Maria', pa_carregadeira_id: '2' });
  txt = await resposta(adm, r);
  ok(/Maria está com Pá 1/.test(txt || ''), 'admin não inicia carga da Maria com a Pá 2', txt);
  r = await adm.post('/carregamentos/1/iniciar', { ...f.fields, operador: 'Maria', pa_carregadeira_id: '1' });
  ok(r.status === 302 && sql("SELECT status FROM fila_carregamentos WHERE id=1") === 'carregando', 'admin inicia carga da Maria com a Pá 1', r.status);

  let [fl] = await form(adm, '/cadastros/pas/1', x => /\/liberar$/.test(x.action));
  ok(!!fl, 'ficha da Pá 1 mostra Liberar');
  r = await adm.post('/cadastros/pas/1/liberar', { ...fl.fields, leitura: '' });
  txt = flash((await adm.seguir(r)).text);
  ok(/em trabalho/.test(txt || '') && posse().length === 1, 'não libera máquina em trabalho', txt);

  [f] = await form(maria, '/carregamentos/1/finalizar', x => /finalizar$/.test(x.action));
  r = await maria.post('/carregamentos/1/finalizar', { ...f.fields, nota: 'NF-1' });
  ok(r.status === 302, 'Maria finaliza a carga', r.status + ' ' + erroTela(r.text));
  sql("UPDATE horimetros SET lido_em = DATE_SUB(lido_em, INTERVAL 6 HOUR) WHERE tipo='pa' AND maquina_id=1");
  [f] = await form(maria, '/maquina', x => x.fields.acao === 'soltar');
  const [f2] = await form(maria2, '/maquina', x => x.fields.acao === 'soltar');
  ok(!!f && !!f2, 'Maria vê o form de soltar nos dois tablets');
  const [s1, s2] = await Promise.all([
    maria.req('POST', '/maquina', { ...f.fields, leitura: '105', situacao: 'ok' }),
    maria2.req('POST', '/maquina', { ...f2.fields, leitura: '105', situacao: 'ok' }),
  ]);
  ok(posse().length === 0, 'posse apagada ao soltar', JSON.stringify(posse()) + ' ' + erroTela(s1.text) + ' / ' + erroTela(s2.text));
  ok(turnos().length === 1 && turnos()[0].horas === '5.0' && turnos()[0].usuario === 'Maria', 'um turno só, de 5 h, com dois toques em paralelo', JSON.stringify(turnos()) + ' ' + s1.status + ' ' + s2.status);

  const ana = new Cliente('Ana'); await ana.login('Ana', 'ana12345');
  r = await ana.assumir('caminhao:3', '300');
  ok(r.status === 302 && posse().some(x => x.usuario === 'Ana' && x.maquina_id === '3'), 'Ana assume caminhão 3');
  sql("UPDATE horimetros SET lido_em = DATE_SUB(lido_em, INTERVAL 3 HOUR) WHERE tipo='caminhao' AND maquina_id=3");
  sql("UPDATE maquina_posse SET iniciado_em = DATE_SUB(iniciado_em, INTERVAL 3 HOUR) WHERE usuario='Ana'");
  p = await adm.get('/manutencao');
  ok(/com Ana desde/.test(p.text), 'manutenção mostra com quem está o caminhão');
  [fl, p] = await form(adm, '/cadastros/caminhoes/3', x => /\/liberar$/.test(x.action));
  ok(/Com <strong>Ana<\/strong> desde/.test(p.text) && /entrou com 300,0 h/.test(p.text), 'ficha mostra posse da Ana com a entrada');
  r = await adm.post('/cadastros/caminhoes/3/liberar', { ...fl.fields, leitura: '302,5' });
  txt = flash((await adm.seguir(r)).text);
  ok(/Estava com Ana; o turno foi gravado/.test(txt || '') && posse().length === 0, 'admin libera o caminhão da Ana com leitura', txt);
  ok(turnos().some(t => t.usuario === 'Ana' && t.horas === '2.5'), 'turno da Ana gravado com 2,5 h', JSON.stringify(turnos()));
  r = await ana.get('/lavra');
  ok(r.status === 302 && /maquina/.test(r.location || ''), 'Ana volta para a escolha de máquina', r.status + ' ' + r.location);
  [fl] = await form(adm, '/cadastros/caminhoes/3', x => /\/horimetro$/.test(x.action));
  r = await adm.post('/cadastros/caminhoes/3/liberar', { ...fl.fields, leitura: '' });
  ok(/já estava livre/.test(flash((await adm.seguir(r)).text) || ''), 'liberar de novo diz que já estava livre');

  r = await pedro.assumir('retro:2', '50');
  ok(r.status === 302 && posse().some(x => x.usuario === 'Pedro'), 'Pedro assume Retro 2');
  [f] = await form(adm, '/cadastros/retros', x => /retros\/2\/ativo$/.test(x.action));
  if (f) {
    r = await adm.post('/cadastros/retros/2/ativo', f.fields);
    txt = flash((await adm.seguir(r)).text);
    ok(/está com Pedro/.test(txt || '') && sql('SELECT ativo FROM retroescavadeiras WHERE id=2') === '1', 'não desativa máquina com posse', txt);
  } else ok(false, 'form ativo da retro');

  const entradaId = sql("SELECT entrada_id FROM maquina_posse WHERE usuario='Pedro'");
  let [fa] = await form(adm, '/cadastros/retros/2', x => x.action.endsWith('/horimetro/' + entradaId + '/anular'));
  r = await adm.post('/cadastros/retros/2/horimetro/' + entradaId + '/anular', { ...fa.fields, motivo: 'errado' });
  ok(sql("SELECT IFNULL(entrada,'NULL') FROM maquina_posse WHERE usuario='Pedro'") === 'NULL', 'anular a entrada limpa a entrada da posse');
  p = await pedro.get('/maquina');
  ok(/Horímetro atual/.test(p.text) && !/entrou com/.test(p.text), 'tela de soltar sem entrada');
  [f] = await form(pedro, '/maquina', x => x.fields.acao === 'soltar');
  r = await pedro.post('/maquina', { ...f.fields, leitura: '51', situacao: 'ok' });
  ok(posse().length === 0 && !turnos().some(t => t.usuario === 'Pedro'), 'soltar sem entrada não grava turno', JSON.stringify(turnos()) + ' ' + erroTela(r.text));

  r = await maria.get('/maquina');
  f = parseForms(r.text).find(x => x.fields.acao === 'assumir');
  r = await maria.post('/lavra/retro', { _envio: f.fields._envio, _tela: f.fields._tela, retroescavadeira_id: '1' });
  ok(r.status === 302 && /maquina/.test(r.location || '') && posse().length === 0, 'rota antiga da retro não dá a máquina sem horímetro', r.status + ' ' + r.location);

  const joao = new Cliente('Joao'); await joao.login('Joao', 'joao1234');
  const sid = joao.cookies['operacoes_prospera'];
  const arq = T.SESSOES + '/sess_' + sid;
  fs.appendFileSync(arq, 'maquina|a:2:{s:4:"tipo";s:8:"caminhao";s:2:"id";i:1;}horimetro_turno|a:5:{s:4:"tipo";s:8:"caminhao";s:2:"id";i:1;s:7:"entrada";d:700.5;s:11:"iniciado_em";s:19:"2026-10-14 06:00:00";s:14:"precisa_soltar";b:0;}');
  r = await joao.get('/lavra');
  ok(r.status === 200 && posse().some(x => x.usuario === 'Joao' && x.tipo === 'caminhao' && x.maquina_id === '1' && x.entrada === '700.5'), 'sessão antiga vira posse no banco', r.status + ' ' + JSON.stringify(posse()));
  ok(!fs.readFileSync(arq, 'utf8').includes('horimetro_turno'), 'chaves antigas saem da sessão');

  r = await adm.get('/lavra');
  const fFila = parseForms(r.text).find(x => /caminhoes\/1\/fila$/.test(x.action));
  if (fFila) {
    r = await adm.post('/lavra/caminhoes/1/fila', fFila.fields);
    txt = flash((await adm.seguir(r)).text);
    ok(/está com Joao/.test(txt || '') && sql('SELECT COUNT(*) FROM ciclos_lavra') === '0', 'admin não põe na fila o caminhão que está com o Joao', txt);
  } else ok(false, 'admin não achou form da fila do caminhão 1');
  r = await joao.get('/lavra');
  const fj = parseForms(r.text).find(x => /caminhoes\/1\/fila$/.test(x.action));
  r = await joao.post('/lavra/caminhoes/1/fila', fj.fields);
  ok(sql("SELECT COUNT(*) FROM ciclos_lavra WHERE operador='Joao'") === '1', 'Joao entra na fila com o caminhão dele', flash((await joao.seguir(r)).text));

  T.resumo();
});
