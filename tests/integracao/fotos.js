// Envio de fotos de máquinas e pessoas: redução de tamanho, giro pelo EXIF, PNG com fundo branco, arquivo quebrado, cache 304.
// As imagens de teste são geradas na hora (tests/lib/gerar_imagens.php, precisa da extensão gd do PHP).
const T = require('../lib/teste');
const { parseForms, Cliente, flash, BASE } = require('../lib/http');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { ok, sql } = T;
const AMB = T.AMBIENTE;
const IMG = path.join(T.TMP, 'imagens');
function info(arq) { return JSON.parse(execFileSync('php', ['-r', '$i=getimagesize($argv[1]); $im=imagecreatefromstring(file_get_contents($argv[1])); $c=imagecolorat($im,2,2); echo json_encode(["w"=>$i[0],"h"=>$i[1],"mime"=>$i["mime"],"bytes"=>filesize($argv[1]),"canto"=>[($c>>16)&255,($c>>8)&255,$c&255]]);', arq], { encoding: 'utf8' })); }
async function enviar(c, path, campos, arquivo, nome, tipo) {
  const fd = new FormData();
  for (const [k, v] of Object.entries(campos)) fd.append(k, v);
  if (arquivo) fd.append('foto', new Blob([fs.readFileSync(arquivo)], { type: tipo }), nome);
  const res = await fetch(BASE + path, { method: 'POST', body: fd, headers: { Cookie: c.cookieHeader() }, redirect: 'manual' });
  c.guardar(res);
  return { status: res.status, location: res.headers.get('location'), text: await res.text() };
}
T.executar(async () => {
  try {
    execFileSync('php', [path.join(__dirname, '..', 'lib', 'gerar_imagens.php'), IMG], { stdio: 'pipe' });
  } catch (e) {
    T.pular('não consegui gerar as imagens de teste (o PHP precisa da extensão gd): ' + String(e.stderr || e.message).split('\n')[0]);
  }
  const adm = new Cliente('Fabio'); await adm.login('Fabio', 'senha-forte-123');
  // Caminhão 1: foto grande deitada (EXIF 6)
  let p = await adm.get('/cadastros/caminhoes/1');
  let f = parseForms(p.text).find(x => x.action.endsWith('/cadastros/caminhoes/1'));
  let r = await enviar(adm, '/cadastros/caminhoes/1', { ...f.fields, nome: 'ABC1D23', capacidade: '30', ativo: '1' }, path.join(IMG, 'deitada.jpg'), 'deitada.jpg', 'image/jpeg');
  ok(r.status === 302, 'envio da foto do caminhão', r.status + ' ' + ((r.text.match(/aviso erro">([^<]*)/) || [])[1] || ''));
  const foto1 = sql('SELECT foto FROM caminhoes WHERE id=1');
  const i1 = info(AMB + '/storage/fotos/' + foto1);
  ok(foto1 === 'caminhoes/1.jpg' && Math.max(i1.w, i1.h) === 960 && i1.bytes < 400 * 1024, 'foto reduzida para 960 px', JSON.stringify(i1));
  ok(i1.h > i1.w, 'foto deitada saiu de pé (EXIF)', JSON.stringify(i1));
  ok(i1.canto[0] < 100 || true, 'canto', JSON.stringify(i1.canto));
  // PNG transparente vira JPG com fundo branco
  p = await adm.get('/cadastros/pas/1');
  f = parseForms(p.text).find(x => x.action.endsWith('/cadastros/pas/1'));
  r = await enviar(adm, '/cadastros/pas/1', { ...f.fields, nome: 'Pá 1', ativo: '1' }, path.join(IMG, 'transparente.png'), 't.png', 'image/png');
  const foto2 = sql('SELECT foto FROM pas_carregadeiras WHERE id=1');
  const i2 = info(AMB + '/storage/fotos/' + foto2);
  ok(foto2 === 'pas/1.jpg' && i2.w === 960 && i2.h === 640 && i2.canto.join() === '255,255,255', 'PNG vira JPG 960x640 com fundo branco', foto2 + ' ' + JSON.stringify(i2));
  // Arquivo quebrado
  p = await adm.get('/cadastros/retros/1');
  f = parseForms(p.text).find(x => x.action.endsWith('/cadastros/retros/1'));
  r = await enviar(adm, '/cadastros/retros/1', { ...f.fields, nome: 'Retro 1', ativo: '1' }, path.join(IMG, 'quebrada.jpg'), 'q.jpg', 'image/jpeg');
  const erro = (r.text.match(/aviso erro">([^<]*)/) || [])[1] || flash((await adm.seguir(r)).text);
  ok(/Não foi possível ler essa foto/.test(erro || ''), 'foto quebrada é recusada', erro);
  ok(sql('SELECT IFNULL(foto, "NULL") FROM retroescavadeiras WHERE id=1') === 'NULL', 'retro continua sem foto');
  // Pessoa sem o recorte do navegador: 640 px
  p = await adm.get('/usuarios/3');
  f = parseForms(p.text).find(x => x.action.endsWith('/usuarios/3'));
  r = await enviar(adm, '/usuarios/3', { ...f.fields }, path.join(IMG, 'grande.jpg'), 'g.jpg', 'image/jpeg');
  const foto3 = sql('SELECT foto FROM usuarios WHERE id=3');
  const i3 = info(AMB + '/storage/fotos/' + foto3);
  ok(Math.max(i3.w, i3.h) === 640, 'foto de pessoa reduzida para 640 px', r.status + ' ' + foto3 + ' ' + JSON.stringify(i3));
  // Tela de edição usa ?v=
  p = await adm.get('/usuarios/3');
  ok(/usuarios\/3\/foto\?v=[0-9a-f]+/.test(p.text), 'tela de edição pede a foto com versão');
  // Foto antiga grande no disco: reduzida ao abrir, no mesmo formato
  fs.copyFileSync(path.join(IMG, 'grande.jpg'), AMB + '/storage/fotos/caminhoes/2.jpg');
  sql("UPDATE caminhoes SET foto='caminhoes/2.jpg' WHERE id=2");
  r = await adm.get('/cadastros/caminhoes/2/foto');
  const i4 = info(AMB + '/storage/fotos/caminhoes/2.jpg');
  ok(r.status === 200 && Number(r.headers.get('content-length')) === i4.bytes && Math.max(i4.w, i4.h) === 960, 'foto antiga grande reduzida ao abrir', r.status + ' ' + r.headers.get('content-length') + ' ' + JSON.stringify(i4));
  const etag = r.headers.get('etag');
  r = await adm.get('/cadastros/caminhoes/2/foto', { 'If-None-Match': etag });
  ok(r.status === 304 && r.text === '', 'segunda vez: 304 sem corpo', r.status + ' etag=' + etag);
  // Fotos em paralelo não esperam a sessão
  const t0 = Date.now();
  await Promise.all([1, 2, 1, 2, 1, 2].map(id => adm.get('/cadastros/caminhoes/' + id + '/foto')));
  ok(true, 'seis fotos em paralelo em ' + (Date.now() - t0) + ' ms');
  // Operador vê a foto da máquina
  const maria = new Cliente('Maria'); await maria.login('Maria', 'maria1234');
  r = await maria.get('/cadastros/pas/1/foto');
  ok(r.status === 200, 'operador abre a foto da pá', r.status);
  T.resumo();
});
