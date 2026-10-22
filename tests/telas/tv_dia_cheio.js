// Monitor da TV num dia cheio: pás carregando e parada, caminhões em todas as etapas da pista, máquinas com problema,
// escavadeira limpando, britador secundário parado e vendas por forma. Em TV pequena e grande nada fica cortado
// nem exige rolagem, cada aviso aparece na coluna certa e a máquina em uso fica verde.
const T = require('../lib/teste');
const { ok, sql, espera, BASE } = T;

const TAMANHOS = ['960x540', '1920x1080'];

T.executar(async () => {
  const id = nome => sql(`SELECT id FROM usuarios WHERE nome='${nome}'`);
  // Pátio: a Pá 1 carrega a primeira ordem de exemplo; a Pá 2 está parada com problema.
  sql("UPDATE fila_carregamentos SET status='carregando', pa_carregadeira_id=1, operador='Maria', inicio_carregamento=NOW() - INTERVAL 6 MINUTE ORDER BY id LIMIT 1");
  sql("UPDATE fila_carregamentos SET nf_valor=1500, nf_pagamento='pix' ORDER BY id LIMIT 1");
  sql("UPDATE fila_carregamentos SET nf_valor=800, nf_pagamento='dinheiro' WHERE nf_valor IS NULL ORDER BY id LIMIT 1");
  sql("INSERT INTO maquina_problemas (tipo, maquina_id, usuario, descricao, relatado_em) VALUES ('pa', 2, 'Maria', 'Pneu furado', NOW() - INTERVAL 80 MINUTE)");
  // Lavra: um caminhão carregando, um indo carregado, um voltando com problema.
  sql(`INSERT INTO ciclos_lavra (caminhao_id, status, inicio_fila, inicio_carga, operador, retroescavadeira_id, operador_carga) VALUES (1, 'carregando', NOW() - INTERVAL 8 MINUTE, NOW() - INTERVAL 3 MINUTE, 'Joao', 1, 'Pedro')`);
  sql(`INSERT INTO ciclos_lavra (caminhao_id, status, inicio_fila, inicio_carga, fim_carga, operador, retroescavadeira_id, operador_carga, capacidade_t) VALUES (2, 'em_viagem', NOW() - INTERVAL 20 MINUTE, NOW() - INTERVAL 15 MINUTE, NOW() - INTERVAL 4 MINUTE, 'Ana', 1, 'Pedro', 28)`);
  sql(`INSERT INTO ciclos_lavra (caminhao_id, status, inicio_fila, inicio_carga, fim_carga, chegada_britador, inicio_retorno, operador, retroescavadeira_id, operador_carga, capacidade_t) VALUES (3, 'retornando', NOW() - INTERVAL 40 MINUTE, NOW() - INTERVAL 35 MINUTE, NOW() - INTERVAL 30 MINUTE, NOW() - INTERVAL 15 MINUTE, NOW() - INTERVAL 5 MINUTE, 'Pedro', 1, 'Pedro', 25)`);
  sql("INSERT INTO maquina_problemas (tipo, maquina_id, usuario, descricao, relatado_em) VALUES ('caminhao', 3, 'Pedro', 'Freio fazendo barulho', NOW() - INTERVAL 2 HOUR)");
  sql("INSERT INTO lavra_limpezas (retroescavadeira_id, operador, inicio) VALUES (2, 'Pedro', NOW() - INTERVAL 12 MINUTE)");
  sql(`INSERT INTO maquina_posse (tipo, maquina_id, usuario_id, usuario, iniciado_em) VALUES
    ('pa', 1, ${id('Maria')}, 'Maria', NOW() - INTERVAL 3 HOUR), ('caminhao', 1, ${id('Joao')}, 'Joao', NOW() - INTERVAL 3 HOUR),
    ('caminhao', 2, ${id('Ana')}, 'Ana', NOW() - INTERVAL 3 HOUR), ('retro', 1, ${id('Pedro')}, 'Pedro', NOW() - INTERVAL 3 HOUR)`);
  // Britador secundário: britando desde cedo, parado agora por um problema; outro já resolvido.
  sql("INSERT INTO britagens (usuario, inicio) VALUES ('Bruno', NOW() - INTERVAL 3 HOUR)");
  sql("INSERT INTO britagem_problemas (usuario, descricao, parou, inicio) VALUES ('Bruno', 'Correia transportadora rasgando', 1, NOW() - INTERVAL 20 MINUTE)");
  sql("INSERT INTO britagem_problemas (usuario, descricao, parou, inicio, resolvido_em, resolvido_por) VALUES ('Bruno', 'Peneira travou', 1, NOW() - INTERVAL 2 HOUR, NOW() - INTERVAL 100 MINUTE, 'Bruno')");

  const b = await T.abrir();
  for (const t of TAMANHOS) {
    const [w, h] = t.split('x').map(Number);
    const p = await T.entrar(b, 'Fabio', 'senha-forte-123', { largura: w, altura: h });
    await p.goto(BASE + '/dashboard');
    await p.click('#ampliar');
    await espera(1500);
    await T.capturar(p, 'tv_cheio_' + t);
    const info = await p.evaluate(() => {
      const tv = document.querySelector('.tv');
      const cortados = [...tv.querySelectorAll('section,article')].filter(el => el.getBoundingClientRect().bottom > window.innerHeight + 1).map(el => el.className);
      const texto = s => (document.querySelector(s) || {}).innerText || '';
      return {
        cortados, rolagem: document.documentElement.scrollHeight, janela: window.innerHeight,
        patio: texto('.tv-patio'), lavra: texto('.tv-lavra'), britador: texto('.tv-britador'), vendas: texto('.tv-vendas'),
        caminhoes: document.querySelectorAll('.tv-caminhao').length, problema: document.querySelectorAll('.tv-caminhao.tv-problema').length,
        emUso: texto('.tv-em-uso'), verdes: document.querySelectorAll('.tv-em-uso li.ativo').length,
        paCor: getComputedStyle(document.querySelector('.tv-maquina.ocupada')).borderTopColor,
      };
    });
    ok(info.cortados.length === 0 && info.rolagem <= info.janela + 1, t + ': tudo cabe, sem rolagem', JSON.stringify({ c: info.cortados, r: info.rolagem, j: info.janela }));
    ok(/Pá 1 · carregando/i.test(info.patio) && /Pá 2 · parada/i.test(info.patio) && /Pneu furado/.test(info.patio), t + ': pátio mostra a pá carregando e a parada', info.patio.slice(0, 200));
    ok(/Freio fazendo barulho/.test(info.lavra) && !/Pneu furado/.test(info.lavra) && /Retro 2 em limpeza da lavra/.test(info.lavra), t + ': lavra mostra o problema do caminhão e a limpeza', info.lavra.slice(0, 200));
    ok(info.caminhoes === 3 && info.problema === 1, t + ': três caminhões na pista, um marcado com problema', info.caminhoes + ' / ' + info.problema);
    ok(!/Pá 1/.test(info.emUso) && /Retro 1 · Pedro/.test(info.emUso) && info.verdes === 3, t + ': máquinas em uso sem as pás, as 3 em uso em verde', info.emUso + ' / ' + info.verdes);
    ok(info.paCor === 'rgb(47, 179, 107)', t + ': pá carregando em verde', info.paCor);
    ok(/Parado por problema/.test(info.britador) && /Correia transportadora rasgando/.test(info.britador) && /resolvido/.test(info.britador), t + ': britador parado, com o problema aberto e o resolvido', info.britador.slice(0, 200));
    ok(/Pix/.test(info.vendas) && /Dinheiro/.test(info.vendas), t + ': vendido dividido por forma', info.vendas);
  }
  T.resumo();
});
