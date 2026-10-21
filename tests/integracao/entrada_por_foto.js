// Entrada pela foto no tablet autorizado: toque na foto + senha, limite de tentativas e tablet revogado.
const T = require('../lib/teste');
const { parseForms, Cliente, erroTela } = require('../lib/http');
const { ok, sql, espera } = T;
const logado = async c => { const r = await c.get('/maquina'); return r.status === 200; };
async function postLogin(c, campos) {
  const p = await c.get('/login');
  const f = parseForms(p.text).find(x => /\/login$/.test(x.action));
  return c.post('/login', { _token: f.fields._token, ...Object.fromEntries(Object.entries(f.fields).filter(([k]) => k.startsWith('_'))), ...campos });
}
T.executar(async () => {
  const tab = new Cliente('tablet');
  await tab.login('Fabio', 'senha-forte-123');
  let p = await tab.get('/tablets');
  let f = parseForms(p.text).find(x => /\/tablets$/.test(x.action));
  await espera(1300);
  let r = await tab.post('/tablets', { ...f.fields, nome: 'Tablet 1' });
  ok(r.status === 302 && sql('SELECT COUNT(*) FROM tablets WHERE ativo=1') === '1', 'tablet autorizado');
  p = await tab.seguir(r);
  f = parseForms(p.text).find(x => /\/sair$/.test(x.action));
  await espera(1300);
  r = await tab.post('/sair', f.fields);
  p = await tab.seguir(r);
  const maria = sql("SELECT id FROM usuarios WHERE nome='Maria'");
  const fabio = sql("SELECT id FROM usuarios WHERE nome='Fabio'");
  ok(new RegExp('href="/login\\?pessoa=' + maria + '"').test(p.text), 'foto da Maria é um link para o pedido de senha');
  ok(!new RegExp('pessoa=' + fabio + '"').test(p.text), 'administrativo não aparece nas fotos');
  ok(/Toque na sua foto e digite a sua senha/.test(p.text), 'aviso de como entrar');
  await espera(1300);
  p = await tab.get('/login?pessoa=' + maria);
  ok(p.status === 200 && /Senha de Maria/.test(p.text) && /não sou Maria/.test(p.text), 'tocar na foto abre a senha da Maria');
  ok(!/class="rostos"/.test(p.text) && !/Administrativo<\/h2>/.test(p.text), 'na tela da senha não aparecem as outras fotos');
  ok(!(await logado(tab)), 'só tocar na foto não entra');
  f = parseForms(p.text).find(x => /\/login$/.test(x.action));
  ok(f && f.fields.pessoa === maria && f.fields.usuario === 'Maria', 'formulário leva a pessoa e o usuário (para o Chrome não misturar senhas)', JSON.stringify(f && f.fields));
  // Pedido antigo (sem senha): só abre a senha.
  { const antigo = { ...f.fields }; delete antigo.senha; delete antigo.usuario; await espera(1300); r = await tab.post('/login', antigo); }
  ok(r.status === 200 && /Senha de Maria/.test(r.text) && !(await logado(tab)), 'toque na foto de tela antiga (sem senha) não entra', r.status);
  // Senha vazia.
  p = await tab.get('/login?pessoa=' + maria); f = parseForms(p.text).find(x => /\/login$/.test(x.action));
  r = await tab.post('/login', { ...f.fields, senha: '' });
  ok(r.status === 200 && !(await logado(tab)), 'senha vazia não entra', erroTela(r.text));
  // Senha errada.
  p = await tab.get('/login?pessoa=' + maria); f = parseForms(p.text).find(x => /\/login$/.test(x.action));
  r = await tab.post('/login', { ...f.fields, senha: 'errada' });
  ok(r.status === 200 && /senha não confere/i.test(erroTela(r.text)) && /Senha de Maria/.test(r.text), 'senha errada: avisa e continua na senha da Maria', erroTela(r.text));
  ok(!(await logado(tab)), 'senha errada não entra');
  // Senha de outra pessoa na foto da Maria.
  p = await tab.get('/login?pessoa=' + maria); f = parseForms(p.text).find(x => /\/login$/.test(x.action));
  r = await tab.post('/login', { ...f.fields, senha: 'pedro1234' });
  ok(r.status === 200 && !(await logado(tab)), 'senha do Pedro na foto da Maria não entra');
  // Trocar o usuário escondido não muda quem entra.
  p = await tab.get('/login?pessoa=' + maria); f = parseForms(p.text).find(x => /\/login$/.test(x.action));
  r = await tab.post('/login', { ...f.fields, usuario: 'Pedro', senha: 'pedro1234' });
  ok(r.status === 200 && !(await logado(tab)), 'mexer no usuário escondido não deixa entrar como outro');
  // Senha certa.
  p = await tab.get('/login?pessoa=' + maria); f = parseForms(p.text).find(x => /\/login$/.test(x.action));
  r = await tab.post('/login', { ...f.fields, senha: 'maria1234' });
  ok(r.status === 302 && await logado(tab), 'senha certa: Maria entra', r.status + ' ' + r.location);
  p = await tab.get('/maquina');
  ok(/Maria/.test(p.text), 'quem entrou foi a Maria');
  f = parseForms(p.text).find(x => /\/sair$/.test(x.action));
  await espera(1300);
  r = await tab.post('/sair', f.fields);
  // Limite de tentativas: 5 erradas bloqueiam.
  sql("DELETE FROM login_falhas");
  let ultimo = '';
  for (let i = 0; i < 6; i++) {
    p = await tab.get('/login?pessoa=' + maria); f = parseForms(p.text).find(x => /\/login$/.test(x.action));
    await espera(1300);
    r = await tab.post('/login', { ...f.fields, senha: 'errada' + i });
    ultimo = erroTela(r.text);
  }
  ok(/Muitas tentativas/.test(ultimo), 'cinco senhas erradas bloqueiam a Maria por 15 minutos', ultimo);
  p = await tab.get('/login?pessoa=' + maria); f = parseForms(p.text).find(x => /\/login$/.test(x.action));
  await espera(1300);
  r = await tab.post('/login', { ...f.fields, senha: 'maria1234' });
  ok(r.status === 200 && /Muitas tentativas/.test(erroTela(r.text)), 'bloqueada, nem a senha certa entra', erroTela(r.text));
  sql("DELETE FROM login_falhas");
  // Pedro entra e é derrubado quando o tablet é revogado.
  const pedro = sql("SELECT id FROM usuarios WHERE nome='Pedro'");
  p = await tab.get('/login?pessoa=' + pedro); f = parseForms(p.text).find(x => /\/login$/.test(x.action));
  await espera(1300);
  r = await tab.post('/login', { ...f.fields, senha: 'pedro1234' });
  ok(r.status === 302 && await logado(tab), 'Pedro entra pela foto com a senha');
  sql("UPDATE tablets SET ativo = 0");
  r = await tab.get('/maquina');
  ok(r.status === 302 && /login/.test(r.location || ''), 'tablet revogado: quem entrou pela foto sai', r.status + ' ' + r.location);
  sql("UPDATE tablets SET ativo = 1");
  // Aparelho não autorizado.
  const pc = new Cliente('pc');
  p = await pc.get('/login?pessoa=' + maria);
  ok(p.status === 200 && !/Senha de Maria/.test(p.text) && /Usuário e senha/.test(p.text), 'aparelho não autorizado: link da foto não abre nada');
  f = parseForms(p.text).find(x => /\/login$/.test(x.action));
  { const campos = { ...f.fields }; delete campos.usuario; r = await pc.post('/login', { ...campos, pessoa: maria, senha: 'maria1234' }); }
  ok(r.status === 200 && /não está autorizado/.test(erroTela(r.text)) && !(await logado(pc)), 'aparelho não autorizado: foto + senha não entra', erroTela(r.text));
  await pc.login('Joao', 'joao1234');
  ok(await logado(pc), 'usuário e senha continuam funcionando');
  // Administrativo pelo link da foto: não.
  const tab2 = new Cliente('tablet2'); tab2.cookies = { ...tab.cookies }; delete tab2.cookies['operacoes_prospera'];
  p = await tab2.get('/login?pessoa=' + fabio);
  ok(!/Senha de Fabio/.test(p.text), 'administrativo não entra pela foto');
  T.resumo();
});
