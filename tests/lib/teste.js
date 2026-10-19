'use strict';
// Ajudantes comuns dos testes em Node (HTTP e navegador). O runner (tests/rodar.sh) monta o ambiente e passa
// tudo por variáveis TESTE_*; nada aqui aponta para uma pasta, porta ou banco fixos.
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const RAIZ = path.resolve(__dirname, '..', '..');
const BASE = process.env.TESTE_BASE || '';
const DB = process.env.TESTE_DB || '';
const AMBIENTE = process.env.TESTE_AMBIENTE || '';
const SESSOES = process.env.TESTE_SESSOES || '';
const NOTAS = process.env.TESTE_NOTAS || path.join(__dirname, '..', 'notas');
const TMP = process.env.TESTE_TMP || require('os').tmpdir();

// Pessoas falsas do ambiente de teste (as mesmas do APP_USUARIOS que o runner passa ao servidor).
const PESSOAS = {
  adm: ['Fabio', 'senha-forte-123'],
  motorista: ['Joao', 'joao1234'],
  operadora: ['Maria', 'maria1234'],
  ambos: ['Pedro', 'pedro1234'],
  motorista2: ['Ana', 'ana12345'],
};

let falhas = 0;
const erros = [];
const navegadores = [];

function ok(cond, msg, extra) {
  console.log((cond ? 'OK   ' : 'FALHA') + ' ' + msg + (extra !== undefined && extra !== '' ? ' :: ' + extra : ''));
  if (!cond) falhas++;
}

function pular(motivo) {
  console.log('PULADO ' + motivo);
  process.exit(77);
}

// --- Banco: o mesmo banco do servidor do teste, com o fuso do app (-04:00) ---
function argsMysql(extra) {
  return ['--protocol=tcp', '-h', process.env.TESTE_DB_HOST || '127.0.0.1', '-P', process.env.TESTE_DB_PORTA || '3307',
    '-u', process.env.TESTE_DB_USUARIO || 'operacoes_teste', '--default-character-set=utf8mb4',
    "--init-command=SET time_zone='-04:00'", ...extra];
}
function mysql(extra) {
  return execFileSync(process.env.TESTE_MYSQL || 'mysql', argsMysql(extra), {
    encoding: 'utf8', env: { ...process.env, MYSQL_PWD: process.env.TESTE_DB_SENHA || 'teste_local' },
  }).trim();
}
function sql(q) { return mysql(['-N', '-B', DB, '-e', q]); }
function sqlLinhas(q) {
  const saida = mysql(['-B', DB, '-e', q]);
  if (!saida) return [];
  const [cab, ...linhas] = saida.split('\n');
  const cols = cab.split('\t');
  return linhas.map(l => { const v = l.split('\t'); const o = {}; cols.forEach((c, i) => { o[c] = v[i]; }); return o; });
}

const espera = ms => new Promise(r => setTimeout(r, ms));

// --- Playwright: tenta o pacote local/global e depois o caminho das imagens da nuvem ---
function carregarPlaywright() {
  const candidatos = [process.env.TESTE_PLAYWRIGHT, 'playwright', '/opt/node-tools/node_modules/playwright'].filter(Boolean);
  for (const c of candidatos) {
    try { return require(c); } catch (e) { /* tenta o próximo */ }
  }
  throw new Error('pacote "playwright" não encontrado (tentei: ' + candidatos.join(', ') + '). '
    + 'Instale com "npm install -g playwright" e o Chromium com "npx playwright install chromium", '
    + 'ou aponte TESTE_PLAYWRIGHT para a pasta do pacote.');
}

async function abrir(opcoes) {
  const { chromium } = carregarPlaywright();
  const b = await chromium.launch(opcoes);
  navegadores.push(b);
  return b;
}

// Registra os erros de JavaScript da página: o teste falha no resumo se houver algum.
function vigiar(p, rotulo) {
  p.on('pageerror', e => erros.push((rotulo ? rotulo + ' ' : '') + e.message));
  return p;
}

// Abre o navegador de uma pessoa, entra com usuário e senha e devolve a página.
// opcoes: largura, altura, app (finge o app Android), contexto (opções extras do contexto).
async function entrar(b, usuario, senha, opcoes = {}) {
  const ctx = await b.newContext({
    viewport: { width: opcoes.largura || 800, height: opcoes.altura || 1280 },
    locale: 'pt-BR', timezoneId: 'America/Cuiaba', ...(opcoes.contexto || {}),
  });
  if (opcoes.app) {
    await ctx.addInitScript(() => {
      window.ProsperaApp = { avisar: t => { window.__avisos = (window.__avisos || []).concat(t); }, telaAcesa() {}, versao() { return '1.1'; } };
    });
  }
  const p = vigiar(await ctx.newPage(), usuario);
  await p.goto(BASE + '/login');
  await p.fill('input[name=usuario]', usuario);
  await p.fill('input[name=senha]', senha);
  await espera(1300); // o app recusa envio rápido demais
  await Promise.all([p.waitForNavigation(), p.click('form.formulario button[type=submit]')]);
  return p;
}

// Clica no botão de envio depois da pausa que o app exige e espera a página seguinte.
async function enviar(p, seletor) {
  await espera(1300);
  await Promise.all([p.waitForNavigation(), p.click(seletor)]);
}

// O horímetro recusa horas que não cabem no tempo passado: a entrada e a posse voltam 12 h.
function envelhecer(extra) {
  sql('UPDATE horimetros SET lido_em = lido_em - INTERVAL 12 HOUR; UPDATE maquina_posse SET iniciado_em = iniciado_em - INTERVAL 12 HOUR' + (extra ? '; ' + extra : ''));
}

// Assume uma máquina pelo formulário da tela /maquina (valor "pa:1", "retro:1", "caminhao:2"...).
async function assumir(p, valor, leitura, opcoes = {}) {
  await p.goto(BASE + '/maquina');
  await p.check(`input[name=maquina][value="${valor}"]`, { force: true });
  await p.fill('input[name=leitura]', leitura);
  await enviar(p, 'form.formulario button[type=submit]');
  if (opcoes.envelhecer) envelhecer(opcoes.extraEnvelhecer);
}

const txt = async p => (await p.textContent('main')).replace(/\s+/g, ' ');

// Guarda a captura de tela só se TESTE_CAPTURAS apontar para uma pasta; senão, não grava nada.
async function capturar(p, nome, opcoes = {}) {
  const pasta = process.env.TESTE_CAPTURAS;
  if (!pasta) return;
  fs.mkdirSync(pasta, { recursive: true });
  await p.screenshot({ path: path.join(pasta, nome + '.png'), ...opcoes });
}

// Dia de hoje (AAAA-MM-DD) no horário de Cuiaba, sem depender do fuso da máquina.
function hoje(deslocamentoDias = 0) {
  const d = new Date(Date.now() + deslocamentoDias * 86400000);
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Cuiaba', year: 'numeric', month: '2-digit', day: '2-digit' }).format(d);
}
const brData = iso => iso.split('-').reverse().join('/');

// Fim do teste: confere os erros de JavaScript do navegador, imprime o resumo e define o código de saída.
function resumo(opcoes = {}) {
  if (opcoes.erros !== false && navegadores.length) ok(erros.length === 0, 'sem erros de JavaScript no navegador', erros.join(' | '));
  console.log(falhas ? falhas + ' FALHA(S)' : 'TUDO OK');
  process.exitCode = falhas ? 1 : 0;
}

// Roda o teste e garante que o navegador fecha; erro inesperado vira código de saída 2.
async function executar(fn) {
  try {
    await fn();
  } catch (e) {
    console.error('FALHA erro inesperado no teste: ' + (e && e.stack ? e.stack : e));
    process.exitCode = 2;
  } finally {
    for (const b of navegadores) { try { await b.close(); } catch (e) { /* já fechado */ } }
  }
  process.exit(process.exitCode || 0);
}

module.exports = {
  RAIZ, BASE, DB, AMBIENTE, SESSOES, NOTAS, TMP, PESSOAS,
  ok, pular, sql, sqlLinhas, espera, carregarPlaywright, abrir, vigiar, entrar, enviar, envelhecer, assumir,
  txt, capturar, hoje, brData, resumo, executar, erros,
  get falhas() { return falhas; },
};
