// Abre as telas principais com cada papel e procura erro do PHP (Fatal error, Warning, Notice...) ou resposta 5xx.
const T = require('../lib/teste');
const { espera, ok, BASE, PESSOAS } = T;

// Entra por HTTP e devolve uma função req(método, caminho, corpo) que carrega o cookie da sessão.
async function entrar(usuario, senha) {
  const cookies = {};
  const req = async (metodo, caminho, corpo) => {
    const h = { Cookie: Object.entries(cookies).map(([a, b]) => a + '=' + b).join('; ') };
    let dados;
    if (corpo) { h['Content-Type'] = 'application/x-www-form-urlencoded'; dados = new URLSearchParams(corpo).toString(); }
    const r = await fetch(BASE + caminho, { method: metodo, headers: h, body: dados, redirect: 'manual' });
    for (const c of r.headers.getSetCookie()) { const [kv] = c.split(';'); const i = kv.indexOf('='); cookies[kv.slice(0, i)] = kv.slice(i + 1); }
    return { status: r.status, destino: r.headers.get('location') || '', texto: await r.text() };
  };
  const pagina = await req('GET', '/login');
  const campos = {};
  for (const i of pagina.texto.matchAll(/<input\b([^>]*)>/g)) {
    const nome = (i[1].match(/name="([^"]*)"/) || [])[1];
    const valor = (i[1].match(/value="([^"]*)"/) || [])[1] || '';
    if (nome) campos[nome] = valor;
  }
  await espera(1300);
  const login = await req('POST', '/login', { ...campos, usuario, senha });
  // Entrada aceita: redireciona para fora do /login. Sem isso, todas as telas abaixo só voltariam para o login.
  ok(login.status === 302 && !/\/login/.test(login.destino), usuario + ' entra no sistema', login.status + ' ' + login.destino);
  return req;
}

const TELAS = ['/', '/painel', '/fila', '/fila?situacao=saldo', '/controle', '/controle?dia=' + T.hoje(-3), '/dashboard',
  '/dashboard/dados', '/manutencao', '/cadastros', '/cadastros/pas/1', '/cadastros/caminhoes/1', '/cadastros/retros/1',
  '/ordens/1', '/ordens/nova', '/patio', '/patio/resumo', '/lavra', '/lavra/resumo', '/maquina', '/trabalhos',
  '/pessoas', '/app', '/health'];
const ERRO_PHP = /(Fatal error|Warning:|Notice:|Deprecated:|Uncaught|TypeError)/;

T.executar(async () => {
  for (const papel of ['adm', 'operadora', 'motorista', 'ambos']) {
    const [usuario, senha] = PESSOAS[papel];
    const req = await entrar(usuario, senha);
    const ruins = [];
    let abertas = 0;
    for (const tela of TELAS) {
      const r = await req('GET', tela);
      if (r.status === 200) abertas++;
      if (r.status >= 500 || ERRO_PHP.test(r.texto)) {
        ruins.push(tela + ' ' + r.status + ' ' + ((r.texto.match(/(Fatal error|Warning|Notice|Deprecated|Uncaught|TypeError)[^<]{0,200}/) || [''])[0]));
      }
    }
    ok(ruins.length === 0, usuario + ' abre ' + TELAS.length + ' telas sem erro do PHP nem 5xx', ruins.join(' | '));
    // O administrativo abre quase tudo; quem é do campo, sem máquina, vai para a escolha de máquina (/maquina, /app e /health abrem).
    const minimo = papel === 'adm' ? 15 : 3;
    ok(abertas >= minimo, usuario + ' abriu de fato as telas (' + abertas + ' com resposta 200)', abertas);
  }
  T.resumo();
});
