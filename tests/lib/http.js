'use strict';
// Cliente HTTP simples (sem navegador) para os testes que conversam direto com o servidor do ambiente:
// guarda cookies, lê formulários da página e segue redirecionamentos.
const T = require('./teste');

const BASE = T.BASE;

function decode(s) {
  return s.replace(/&quot;/g, '"').replace(/&#039;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
}

function parseForms(html) {
  const forms = [];
  const re = /<form\b([^>]*)>([\s\S]*?)<\/form>/gi;
  let m;
  while ((m = re.exec(html))) {
    const attrs = m[1];
    const body = m[2];
    const action = decode((attrs.match(/action="([^"]*)"/) || [])[1] || '');
    const method = ((attrs.match(/method="([^"]*)"/) || [])[1] || 'get').toLowerCase();
    const fields = {};
    const ire = /<input\b([^>]*)>/gi;
    let i;
    while ((i = ire.exec(body))) {
      const a = i[1];
      const name = (a.match(/name="([^"]*)"/) || [])[1];
      const type = ((a.match(/type="([^"]*)"/) || [])[1] || 'text').toLowerCase();
      const value = decode((a.match(/value="([^"]*)"/) || [])[1] || '');
      if (!name) continue;
      if ((type === 'radio' || type === 'checkbox') && !/\bchecked\b/.test(a)) continue;
      fields[name] = value;
    }
    const botoes = [];
    const bre = /<button\b[^>]*>([\s\S]*?)<\/button>/gi;
    let b;
    while ((b = bre.exec(body))) botoes.push(decode(b[1].replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim()));
    forms.push({ action, method, fields, botoes, texto: botoes.join(' | ') });
  }
  return forms;
}

class Cliente {
  constructor(nome) { this.nome = nome; this.cookies = {}; }
  cookieHeader() { return Object.entries(this.cookies).map(([k, v]) => k + '=' + v).join('; '); }
  guardar(res) {
    const sc = res.headers.getSetCookie ? res.headers.getSetCookie() : [];
    for (const c of sc) {
      const [par] = c.split(';');
      const idx = par.indexOf('=');
      const k = par.slice(0, idx).trim(), v = par.slice(idx + 1).trim();
      if (v === '' || v === 'deleted' || /expires=Thu, 01 Jan 1970/i.test(c) || /Max-Age=0/i.test(c)) delete this.cookies[k]; else this.cookies[k] = v;
    }
  }
  async req(method, path, body, extra = {}) {
    const headers = { Cookie: this.cookieHeader(), ...extra };
    let payload;
    if (body) {
      headers['Content-Type'] = 'application/x-www-form-urlencoded';
      payload = typeof body === 'string' ? body : new URLSearchParams(body).toString();
    }
    const url = path.startsWith('http') ? path : BASE + path;
    const res = await fetch(url, { method, headers, body: payload, redirect: 'manual' });
    this.guardar(res);
    const text = await res.text();
    return { status: res.status, location: res.headers.get('location'), text, headers: res.headers };
  }
  get(path, extra) { return this.req('GET', path, null, extra); }
  async post(path, body, extra) {
    // Ritmo de gente: as ações da lavra recusam um toque menos de 1,2 s depois da ação anterior.
    if (/\/lavra\//.test(path) && !process.env.SEM_PAUSA) await T.espera(1300);
    return this.req('POST', path, body, extra);
  }
  async seguir(r, max = 5) {
    while (r.location && max-- > 0) r = await this.get(r.location);
    return r;
  }
  async login(usuario, senha) {
    const p = await this.get('/login');
    const f = parseForms(p.text).find(x => 'usuario' in x.fields || x.botoes.includes('Entrar'));
    const r = await this.post('/login', { ...f.fields, usuario, senha });
    if (r.status !== 302) throw new Error('login falhou ' + usuario + ' ' + r.status);
    return this.seguir(r);
  }
  async assumir(maquina, leitura) {
    const p = await this.get('/maquina');
    const f = parseForms(p.text).find(x => x.action.endsWith('/maquina') && x.fields.acao === 'assumir');
    if (!f) throw new Error('sem form assumir: ' + p.status + ' ' + p.text.slice(0, 300));
    return this.post('/maquina', { ...f.fields, maquina, leitura: String(leitura) });
  }
}

function flash(html) {
  const m = html.match(/<p class="aviso ([a-z]+)">([\s\S]*?)<\/p>/);
  return m ? m[1] + ': ' + decode(m[2].trim()) : null;
}

const erroTela = t => ((t.match(/aviso erro">([^<]*)/) || [])[1] || '').trim();

module.exports = { BASE, decode, parseForms, Cliente, flash, erroTela };
