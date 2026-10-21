// Troca do nome de uma pessoa pelo administrativo: o histórico (viagens, horímetro, posse) acompanha o nome novo.
const T = require('../lib/teste');
const { parseForms, Cliente, erroTela } = require('../lib/http');
const { ok, sql } = T;
T.executar(async () => {
  const adm = new Cliente('Fabio'); await adm.login('Fabio', 'senha-forte-123');
  let p = await adm.get('/usuarios/novo');
  let f = parseForms(p.text).find(x => /usuarios\/novo$/.test(x.action));
  let r = await adm.post('/usuarios/novo', { ...f.fields, nome: 'Bruno', nome_completo: 'Bruno Souza', funcao: 'motorista', senha: 'bruno1234', ativo: '1' });
  const id = sql("SELECT id FROM usuarios WHERE nome='Bruno'");
  ok(r.status === 302 && id !== '', 'admin cria Bruno', r.status + ' ' + erroTela(r.text));

  const bruno = new Cliente('Bruno'); await bruno.login('Bruno', 'bruno1234');
  r = await bruno.assumir('caminhao:2', '500');
  ok(r.status === 302, 'Bruno assume caminhão 2');
  p = await bruno.get('/lavra');
  f = parseForms(p.text).find(x => /caminhoes\/2\/fila$/.test(x.action));
  r = await bruno.post(f.action, f.fields);
  ok(sql("SELECT COUNT(*) FROM ciclos_lavra WHERE operador='Bruno'") === '1', 'Bruno entra na fila');

  // admin troca o nome
  p = await adm.get('/usuarios/' + id);
  ok(/o histórico passa para o nome novo/.test(p.text), 'dica na tela de edição');
  f = parseForms(p.text).find(x => x.action.endsWith('/usuarios/' + id));
  r = await adm.post('/usuarios/' + id, { ...f.fields, nome: 'Maria', nome_completo: 'Bruno Souza', funcao: 'motorista', ativo: '1' });
  ok(r.status === 200 && /Já existe/.test(erroTela(r.text) || '') && sql("SELECT COUNT(*) FROM ciclos_lavra WHERE operador='Bruno'") === '1', 'nome de outra pessoa é recusado e nada muda', erroTela(r.text));
  p = await adm.get('/usuarios/' + id);
  f = parseForms(p.text).find(x => x.action.endsWith('/usuarios/' + id));
  r = await adm.post('/usuarios/' + id, { ...f.fields, nome: 'Bernardo', nome_completo: 'Bernardo Souza', funcao: 'motorista', ativo: '1' });
  ok(r.status === 302, 'admin troca Bruno para Bernardo', r.status + ' ' + erroTela(r.text));
  const resto = sql("SELECT (SELECT COUNT(*) FROM ciclos_lavra WHERE operador='Bruno') + (SELECT COUNT(*) FROM horimetros WHERE usuario='Bruno') + (SELECT COUNT(*) FROM maquina_posse WHERE usuario='Bruno')");
  ok(resto === '0', 'nada ficou com o nome antigo', resto);
  ok(sql("SELECT COUNT(*) FROM ciclos_lavra WHERE operador='Bernardo'") === '1' && sql("SELECT usuario FROM maquina_posse WHERE usuario_id=" + id) === 'Bernardo', 'viagem e máquina passaram para Bernardo');

  // a sessão do Bruno continua e já usa o nome novo
  p = await bruno.get('/lavra');
  ok(p.status === 200 && /Bernardo/.test(p.text), 'sessão aberta continua, com o nome novo', p.status + ' ' + (p.location || ''));
  ok(/QBK2F41/.test(p.text) && /Desde \d\d:\d\d · Bernardo/.test(p.text), 'Bernardo vê a viagem dele na lavra');
  // Bruno não entra mais com o nome antigo; Bernardo entra
  const velho = new Cliente('x'); p = await velho.get('/login');
  f = parseForms(p.text).find(x => 'usuario' in x.fields || x.botoes.includes('Entrar'));
  r = await velho.post('/login', { ...f.fields, usuario: 'Bruno', senha: 'bruno1234' });
  ok(r.status !== 302, 'nome antigo não entra mais');
  const novo = new Cliente('y'); r = await novo.login('Bernardo', 'bruno1234');
  ok(/caminhão|Caminhão|QBK2F41/.test(r.text), 'Bernardo entra e continua com o caminhão');

  // usuário da configuração do servidor não troca de nome por aqui
  const joaoId = sql("SELECT id FROM usuarios WHERE nome='Joao'");
  p = await adm.get('/usuarios/' + joaoId);
  f = parseForms(p.text).find(x => x.action.endsWith('/usuarios/' + joaoId));
  r = await adm.post('/usuarios/' + joaoId, { ...f.fields, nome: 'Joaquim', nome_completo: 'Joao', funcao: 'motorista', ativo: '1' });
  ok(r.status === 200 && /APP_USUARIOS/.test(erroTela(r.text) || ''), 'usuário de APP_USUARIOS não troca de nome', erroTela(r.text));
  // trocar só a função, sem trocar o nome, continua funcionando
  p = await adm.get('/usuarios/' + joaoId);
  f = parseForms(p.text).find(x => x.action.endsWith('/usuarios/' + joaoId));
  r = await adm.post('/usuarios/' + joaoId, { ...f.fields, nome: 'Joao', nome_completo: 'Joao Silva', funcao: 'motorista', ativo: '1' });
  ok(r.status === 302 && sql("SELECT nome_completo FROM usuarios WHERE id=" + joaoId) === 'Joao Silva', 'editar sem trocar o nome continua', r.status + ' ' + erroTela(r.text));
  T.resumo();
});
