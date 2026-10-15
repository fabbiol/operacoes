(function () {
    var raiz = document.getElementById('monitor');
    var bruto = document.getElementById('monitor-dados');
    var dados = JSON.parse(bruto.textContent);
    var endereco = bruto.getAttribute('data-url');

    function esc(valor) {
        return String(valor == null ? '' : valor).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function numero(valor, casas) {
        if (valor == null || valor === '') return '—';
        return Number(valor).toLocaleString('pt-BR', { minimumFractionDigits: casas, maximumFractionDigits: casas });
    }

    function tempo(segundos) {
        if (segundos == null) return '—';
        segundos = Math.max(0, Math.round(segundos));
        var h = Math.floor(segundos / 3600);
        var m = Math.floor((segundos % 3600) / 60);
        if (h > 0) return h + ' h ' + String(m).padStart(2, '0') + ' min';
        if (m > 0) return m + ' min';
        return segundos + ' s';
    }

    function cartao(rotulo, valor, extra) {
        return '<article class="ind"><span>' + esc(rotulo) + '</span><strong>' + valor + '</strong>' +
            (extra ? '<small>' + extra + '</small>' : '') + '</article>';
    }

    function geometria(estrada) {
        var ida = estrada && estrada.ida_km > 0 ? estrada.ida_km : 0;
        var retorno = estrada && estrada.retorno_km > 0 ? estrada.retorno_km : 0;
        var x0 = 170;
        var topo = 80;
        var base = 280;
        var largura = 460;
        var idaFim = x0 + largura;
        var retFim = idaFim;
        if (ida > 0 && retorno > 0) {
            var escala = largura / Math.max(ida, retorno);
            idaFim = x0 + ida * escala;
            retFim = x0 + retorno * escala;
        }
        var direita = Math.max(idaFim, retFim) + 70;
        var esquerda = x0 - 70;
        var caminho = 'M' + x0 + ' ' + topo + ' H' + idaFim +
            ' C' + direita + ' ' + topo + ' ' + direita + ' ' + base + ' ' + retFim + ' ' + base +
            ' H' + x0 +
            ' C' + esquerda + ' ' + base + ' ' + esquerda + ' ' + topo + ' ' + x0 + ' ' + topo + ' Z';
        return {
            caminho: caminho,
            ida: ida,
            retorno: retorno,
            lavraX: (x0 - 28) / 800 * 100,
            britadorX: (direita - 28) / 800 * 100,
            yIda: topo / 360 * 100,
            yVolta: base / 360 * 100,
            xSaida: x0 / 800 * 100,
            xIdaFim: idaFim / 800 * 100,
            xRetFim: retFim / 800 * 100
        };
    }

    function lugar(item, slot, total, geo) {
        var desvio = (slot - (total - 1) / 2) * 7;
        if (item.faixa === 'lavra') {
            return { x: geo.lavraX, y: 50 + desvio };
        }
        if (item.faixa === 'ida') {
            return { x: geo.xSaida + item.progresso * (geo.xIdaFim - geo.xSaida), y: geo.yIda };
        }
        return { x: geo.xRetFim - item.progresso * (geo.xRetFim - geo.xSaida), y: geo.yVolta };
    }

    function caminhoes(lista, geo) {
        var grupos = { lavra: [], ida: [], volta: [] };
        lista.forEach(function (item) { (grupos[item.faixa] || grupos.lavra).push(item); });
        return lista.map(function (item) {
            var grupo = grupos[item.faixa] || [item];
            var ponto = lugar(item, grupo.indexOf(item), grupo.length, geo);
            var cor = item.faixa === 'volta' ? 'vazio' : (item.status === 'na_fila' ? 'espera' : 'carregado');
            return '<button type="button" class="veiculo ' + cor + '" style="left:' + ponto.x + '%;top:' + ponto.y + '%" data-id="' + item.id + '">' +
                '<b>' + esc(item.placa) + '</b><i></i></button>';
        }).join('');
    }

    function detalheLavra(lista, escolhido) {
        if (!lista.length) return '<p class="vazio-monitor">Nenhum caminhão em ciclo.</p>';
        return lista.map(function (item) {
            var cap = item.capacidade == null ? 'Sem capacidade' : numero(item.capacidade, 2) + ' t';
            var tons = item.toneladas == null ? '—' : numero(item.toneladas, 2) + ' t hoje';
            return '<article class="ficha-veiculo' + (escolhido === item.id ? ' escolhido' : '') + '" data-id="' + item.id + '">' +
                '<header><strong>' + esc(item.placa) + '</strong><span>' + esc(item.estado) + '</span></header>' +
                '<p>' + esc(item.motorista || 'Sem motorista') + ' · ' + cap + '</p>' +
                '<p>' + item.viagens + ' viagens · ' + tons + ' · parado ' + esc(item.parado) + ' desde ' + esc(item.desde) + '</p>' +
                (item.km != null ? '<p>Cerca de ' + numero(item.km, 2) + ' km ' + (item.faixa === 'volta' ? 'do britador' : 'da lavra') + '</p>' : '') +
                (item.retro ? '<p>Escavadeira ' + esc(item.retro) + (item.operador_carga ? ' · ' + esc(item.operador_carga) : '') + '</p>' : '') +
                '</article>';
        }).join('');
    }

    function barras(horas, classe) {
        if (!horas.length) return '<p class="vazio-monitor">Sem movimento hoje.</p>';
        var topo = Math.max.apply(null, horas.map(function (h) { return h.toneladas || h.viagens; }));
        topo = topo > 0 ? topo : 1;
        return '<div class="barras">' + horas.map(function (h) {
            var altura = Math.max(8, Math.round(((h.toneladas || h.viagens) / topo) * 100));
            var texto = h.viagens === 1 ? '1 viagem' : h.viagens + ' viagens';
            return '<div class="coluna-hora">' +
                '<p class="marca"><strong>' + numero(h.toneladas, 2) + ' t</strong><small>' + texto + '</small></p>' +
                '<span class="trilho"><i style="height:' + altura + '%" class="' + classe + '"></i></span>' +
                '<small class="hora">' + h.hora + 'h</small>' +
                '</div>';
        }).join('') + '</div>';
    }

    function pilhas(materiais, ativo) {
        if (!materiais.length) return '<p class="vazio-monitor">Nenhum material ativo.</p>';
        return materiais.map(function (item) {
            return '<article class="pilha' + (ativo === item.nome ? ' ativa' : '') + '"><span class="monte"></span><strong>' + esc(item.nome) + '</strong></article>';
        }).join('');
    }

    function maquinas(lista) {
        if (!lista.length) return '<p class="vazio-monitor">Nenhuma pá ativa.</p>';
        return lista.map(function (item) {
            var ordem = item.ordem;
            return '<article class="pa ' + item.tipo + (ordem ? ' ocupada' : '') + '">' +
                '<strong>' + esc(item.nome) + '</strong>' +
                '<small>' + (item.tipo === 'retro' ? 'Escavadeira no pátio' : 'Pá carregadeira') + '</small>' +
                (ordem
                    ? '<p>' + esc(ordem.ordem) + ' · ' + esc(ordem.cliente) + '</p><p>' + esc(ordem.produto) + ' · ' + numero(ordem.quantidade, 2) + ' ' + esc(ordem.unidade) + '</p>'
                    : '<p>Livre</p>') +
                '</article>';
        }).join('');
    }

    function fila(lista, titulo) {
        if (!lista.length) return '';
        return '<h3>' + esc(titulo) + '</h3>' + lista.map(function (item) {
            return '<article class="cliente-fila"><strong>' + esc(item.ordem) + '</strong>' +
                '<p>' + esc(item.cliente) + '</p>' +
                '<p>' + esc(item.produto) + ' · ' + numero(item.quantidade, 2) + ' ' + esc(item.unidade) + '</p>' +
                '<small>Pedido ' + esc(item.pedido) + ' · NF ' + esc(item.nf) + ' · ' + esc(item.chegada) + '</small></article>';
        }).join('');
    }

    function desenhar(estado) {
        var lavra = estado.lavra;
        var patio = estado.patio;
        var ind = lavra.indicadores;
        var indP = patio.indicadores;
        var produtoAtivo = '';
        patio.maquinas.forEach(function (item) {
            if (item.ordem) produtoAtivo = item.ordem.produto;
        });
        var geo = geometria(estado.estrada);
        var medidas = (geo.ida ? 'Ida ' + numero(geo.ida, 2) + ' km' : '') +
            (geo.ida && geo.retorno ? ' · ' : '') +
            (geo.retorno ? 'Retorno ' + numero(geo.retorno, 2) + ' km' : '');
        raiz.innerHTML =
            '<section class="inds">' +
                cartao('Em ciclo', ind.operacao) +
                cartao('Viagens', ind.viagens) +
                cartao('Lavra hoje', ind.toneladas == null ? '—' : numero(ind.toneladas, 2) + ' t', ind.volume == null ? '' : numero(ind.volume, 2) + ' m³') +
                cartao('t/h lavra', ind.por_hora == null ? '—' : numero(ind.por_hora, 1)) +
                cartao('Ciclo médio', tempo(ind.ciclo)) +
                cartao('Na fila', ind.parados) +
                cartao('Aguardando', indP.aguardando) +
                cartao('Sem autorização', indP.sem_autorizacao) +
                cartao('Carregando', indP.carregando) +
                cartao('Expedidas', indP.expedidas) +
                cartao('Pátio hoje', indP.toneladas == null ? '—' : numero(indP.toneladas, 2) + ' t', indP.volume == null ? '' : numero(indP.volume, 2) + ' m³') +
                cartao('Espera média', tempo(indP.espera)) +
            '</section>' +
            '<section class="cena">' +
                '<header><h2>01 — Ciclo de lavra</h2><span>Transporte interno</span></header>' +
                '<div class="pista">' +
                    '<svg viewBox="0 0 800 360" preserveAspectRatio="none" aria-hidden="true"><path class="pista-corpo" d="' + geo.caminho + '"/><path class="pista-eixo" d="' + geo.caminho + '"/></svg>' +
                    '<div class="posto lavra" style="left:' + geo.lavraX + '%">Lavra' + (lavra.retros.length ? '<small>' + lavra.retros.map(function (r) {
                        return esc(r.nome) + ' · ' + esc(r.placa);
                    }).join('<br>') + '</small>' : '') + '</div>' +
                    '<div class="posto britador" style="left:' + geo.britadorX + '%;right:auto">Britador</div>' +
                    '<p class="miolo">Ciclo de transporte<br><small>' + (medidas || 'Lavra → Britador → Retorno') + '</small></p>' +
                    caminhoes(lavra.caminhoes, geo) +
                '</div>' +
                '<p class="legenda"><i class="ponto-carregado"></i> Carregado <i class="ponto-fila"></i> Na fila <i class="ponto-volta"></i> Retornando</p>' +
                '<div class="detalhes">' + detalheLavra(lavra.caminhoes, null) + '</div>' +
            '</section>' +
            '<section class="cena patio">' +
                '<header><h2>02 — Expedição no pátio</h2><span>Fila de clientes</span></header>' +
                '<div class="expedicao">' +
                    '<div class="coluna"><h3>Pilhas</h3>' + pilhas(patio.materiais, produtoAtivo) + '</div>' +
                    '<div class="coluna"><h3>Máquinas</h3>' + maquinas(patio.maquinas) + '</div>' +
                    '<div class="coluna"><h3>Fila</h3>' +
                        (patio.fila.length ? fila(patio.fila, 'Liberados') : '<p class="vazio-monitor">Nenhum cliente na fila.</p>') +
                        fila(patio.sem_autorizacao, 'Aguardando autorização') +
                    '</div>' +
                '</div>' +
            '</section>' +
            '<section class="horarios">' +
                '<article><h2>Lavra por hora</h2>' + barras(lavra.horas, 'barra-lavra') + '</article>' +
                '<article><h2>Pátio por hora</h2>' + barras(patio.horas, 'barra-patio') +
                    (patio.pas.length ? '<ul class="ranking">' + patio.pas.map(function (pa) {
                        return '<li><span>' + esc(pa.nome) + '</span><strong>' + pa.cargas + '</strong></li>';
                    }).join('') + '</ul>' : '') +
                '</article>' +
            '</section>' +
            '<p class="relogio">Atualizado às ' + esc(estado.agora) + ' · próxima atualização em <span class="contagem">' + segundosRestantes() + '</span> s</p>';
    }

    var intervalo = 8000;
    var proxima = Date.now() + intervalo;

    function segundosRestantes() {
        return Math.max(0, Math.ceil((proxima - Date.now()) / 1000));
    }

    function atualizarContagem() {
        var marca = raiz.querySelector('.contagem');
        if (marca) marca.textContent = String(segundosRestantes());
    }

    var ampliar = document.getElementById('ampliar');

    function telaCheia(ligar) {
        document.body.classList.toggle('tela-cheia', ligar);
        if (ampliar) ampliar.textContent = ligar ? 'Reduzir' : 'Ampliar';
    }

    if (ampliar) {
        ampliar.addEventListener('click', function () {
            var ligar = !document.body.classList.contains('tela-cheia');
            telaCheia(ligar);
            if (!ligar) {
                if (document.fullscreenElement) document.exitFullscreen();
                return;
            }
            var pedido = document.documentElement.requestFullscreen;
            if (pedido) pedido.call(document.documentElement).catch(function () {});
        });
        document.addEventListener('fullscreenchange', function () {
            if (!document.fullscreenElement) telaCheia(false);
        });
    }

    desenhar(dados);
    setInterval(atualizarContagem, 250);
    setInterval(function () {
        proxima = Date.now() + intervalo;
        atualizarContagem();
        fetch(endereco, { headers: { 'Accept': 'application/json' } })
            .then(function (resposta) { return resposta.json(); })
            .then(desenhar)
            .catch(function () {});
    }, intervalo);
})();
