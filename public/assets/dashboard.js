(function () {
    // Monitor da TV: lido de 3 a 6 metros. Poucos números, grandes; cor só para o que pede atenção
    // (espera longa, conexão caída). Os relógios contam sozinhos entre uma atualização e outra.
    var raiz = document.getElementById('monitor');
    var bruto = document.getElementById('monitor-dados');
    var dados = JSON.parse(bruto.textContent);
    var endereco = bruto.getAttribute('data-url');
    var INTERVALO = 8000;
    var ESPERA_ATENCAO = 30 * 60;
    var ESPERA_CRITICA = 60 * 60;
    var SEM_ATUALIZAR_AVISO = 60 * 1000;
    var SEM_ATUALIZAR_VELHO = 3 * 60 * 1000;
    var desvio = 0;
    var ultimoOk = Date.now();
    var horaUltimoOk = '';

    function esc(valor) {
        return String(valor == null ? '' : valor).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function numero(valor, casas) {
        if (valor == null || valor === '') return '—';
        return Number(valor).toLocaleString('pt-BR', { minimumFractionDigits: casas, maximumFractionDigits: casas });
    }

    // Toneladas e m³: sem casas quando é um número cheio grande, uma casa quando é pequeno.
    function quantia(valor) {
        if (valor == null) return '—';
        var v = Number(valor);
        return numero(v, v >= 100 || v === Math.round(v) ? 0 : 1);
    }

    // Valor curto para caber no quadro: R$ 48 mil; o valor exato fica na tela Controle de carga.
    function dinheiro(valor) {
        var v = Number(valor || 0);
        if (v >= 1000000) return '<small>R$ </small>' + numero(v / 1000000, 1) + '<small> mi</small>';
        if (v >= 10000) return '<small>R$ </small>' + numero(Math.round(v / 1000), 0) + '<small> mil</small>';
        return '<small>R$ </small>' + numero(v, 0);
    }

    function duracao(segundos) {
        if (segundos == null || segundos < 0) return '—';
        // Abaixo de 1 minuto mostra os segundos: "0 min" parecia número quebrado.
        if (segundos < 60) return Math.floor(segundos) + ' s';
        var h = Math.floor(segundos / 3600);
        var m = Math.floor((segundos % 3600) / 60);
        if (h > 0) return h + ' h ' + String(m).padStart(2, '0');
        return m + ' min';
    }

    function agoraServidor() {
        return Math.floor((Date.now() + desvio) / 1000);
    }

    function horaMinuto(epoch) {
        try {
            return new Date(epoch * 1000).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', timeZone: dados.fuso || undefined });
        } catch (e) {
            return new Date(epoch * 1000).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        }
    }

    // ---- números do dia

    function tile(rotulo, valor, detalhe, classe) {
        return '<article class="tv-numero' + (classe ? ' ' + classe : '') + '">' +
            '<span class="tv-rotulo">' + esc(rotulo) + '</span>' +
            '<strong class="tv-valor">' + valor + '</strong>' +
            '<span class="tv-detalhe">' + (detalhe || '&nbsp;') + '</span>' +
            '</article>';
    }

    function numeros(estado, mostrarVendas) {
        var p = estado.patio.indicadores;
        var l = estado.lavra.indicadores;
        // Toneladas e m³ não se somam: a maior medida vai grande, a outra no detalhe.
        var principal = '0<small> t</small>';
        var outra = '';
        if (p.toneladas != null) {
            principal = quantia(p.toneladas) + '<small> t</small>';
            if (p.volume != null) outra = '+ ' + quantia(p.volume) + ' m³ · ';
        } else if (p.volume != null) {
            principal = quantia(p.volume) + '<small> m³</small>';
        }
        var maior = 0;
        estado.patio.fila.forEach(function (item) {
            maior = Math.max(maior, agoraServidor() - item.desde_ts);
        });
        var espera = estado.patio.fila.length
            ? 'maior espera <b data-desde="' + (agoraServidor() - maior) + '">' + duracao(maior) + '</b>'
            : 'ninguém esperando';
        var pas = estado.patio.maquinas.filter(function (m) { return m.ordem; }).map(function (m) { return esc(m.nome); });
        var esperando = estado.lavra.caminhoes.filter(function (c) { return c.status === 'na_fila'; }).length;
        var limpando = (estado.lavra.limpezas || []).map(function (l) { return l.retro; });
        var lista = [
            tile('Carregado hoje', principal, outra + p.expedidas + (p.expedidas === 1 ? ' carga' : ' cargas'), 'tv-heroi'),
            tile('Na fila do pátio', String(p.aguardando), espera, maior >= ESPERA_CRITICA ? 'critico' : (maior >= ESPERA_ATENCAO ? 'atencao' : '')),
            tile('Carregando', String(p.carregando), pas.length ? pas.join(' · ') : 'nenhuma pá carregando'),
            tile('Viagens da lavra', String(l.viagens), (l.toneladas != null ? quantia(l.toneladas) + ' t · ' : '') + 'ciclo ' + duracao(l.ciclo)),
            tile('Caminhões na lavra', String(l.operacao),
                limpando.length
                    ? esc(limpando.join(' e ')) + ' em limpeza'
                    : (esperando ? esperando + ' esperando a escavadeira' : 'nenhum esperando'),
                limpando.length ? 'atencao' : '')
        ];
        if (mostrarVendas && estado.vendas) {
            var semValor = estado.vendas.sem_valor || 0;
            lista.push(tile('Vendido hoje', dinheiro(estado.vendas.valor),
                estado.vendas.notas + (estado.vendas.notas === 1 ? ' nota' : ' notas') +
                (semValor ? ' · ' + semValor + ' sem valor' : '')));
        }
        return '<section class="tv-numeros n' + lista.length + '">' + lista.join('') + '</section>';
    }

    // ---- problemas das máquinas (avisados ao soltar a máquina): faixa vermelha enquanto houver algum aberto

    function quando(epoch) {
        var dia = function (t) {
            try {
                return new Date(t * 1000).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', timeZone: dados.fuso || undefined });
            } catch (e) {
                return new Date(t * 1000).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
            }
        };
        return dia(epoch) === dia(agoraServidor()) ? horaMinuto(epoch) : dia(epoch);
    }

    function problemas(estado) {
        var lista = estado.problemas || [];
        if (lista.length === 0) return '';
        var visiveis = lista.slice(0, 4);
        return '<section class="tv-problemas" aria-label="Problemas nas máquinas">' +
            '<h2><i aria-hidden="true">!</i>Problemas nas máquinas<span>' + lista.length + '</span></h2>' +
            '<ul>' + visiveis.map(function (p) {
                return '<li><strong>' + esc(p.maquina) + '</strong> ' + esc(p.descricao) +
                    ' <small>' + esc(p.usuario) + ' · ' + esc(quando(p.desde_ts)) + '</small></li>';
            }).join('') + '</ul>' +
            (lista.length > visiveis.length ? '<p class="tv-problemas-mais">+ ' + (lista.length - visiveis.length) + '</p>' : '') +
            '</section>';
    }

    // ---- pátio

    function espera(item) {
        var segundos = agoraServidor() - item.desde_ts;
        var classe = segundos >= ESPERA_CRITICA ? 'critico' : (segundos >= ESPERA_ATENCAO ? 'atencao' : '');
        return '<span class="tv-tempo ' + classe + '" data-desde="' + item.desde_ts + '" data-limites="1">' +
            '<i aria-hidden="true">!</i><b>' + duracao(segundos) + '</b></span>';
    }

    function carga(item) {
        return esc(item.produto) + ' · ' + quantia(item.quantidade) + ' ' + esc(item.unidade) +
            (item.fracionado ? ' · viagem ' + item.viagem : '');
    }

    function patio(estado) {
        var dadosPatio = estado.patio;
        var maquinas = dadosPatio.maquinas.length
            ? dadosPatio.maquinas.map(function (m) {
                var o = m.ordem;
                if (!o) {
                    return '<article class="tv-maquina livre"><strong>Livre</strong>' +
                        '<p><span><b>' + esc(m.nome) + '</b></span></p></article>';
                }
                var inicio = o.inicio_ts || o.desde_ts;
                return '<article class="tv-maquina ocupada"><strong>' + esc(o.cliente) + '</strong>' +
                    '<p><span><b>' + esc(m.nome) + '</b> · ' + carga(o) + '</span>' +
                    '<span class="tv-tempo" data-desde="' + inicio + '"><b>' + duracao(agoraServidor() - inicio) + '</b></span></p></article>';
            }).join('')
            : '<p class="tv-vazio">Nenhuma pá ativa.</p>';
        // A lista leva a fila toda; o que não couber na tela some e vira "+ N na fila" (ver caber()).
        var fila = dadosPatio.fila.length
            ? '<ol class="tv-fila" data-um="na fila" data-varios="na fila">' + dadosPatio.fila.slice(0, 20).map(function (item) {
                return '<li><strong>' + esc(item.cliente) + '</strong><span>' + carga(item) + '</span>' + espera(item) + '</li>';
            }).join('') + '</ol><p class="tv-resto" data-total="' + dadosPatio.fila.length + '"></p>'
            : '<p class="tv-vazio">Ninguém na fila.</p>';
        var autorizar = dadosPatio.indicadores.sem_autorizacao;
        return '<article class="tv-zona">' +
            '<header><h2>Pátio</h2><span>' + (autorizar ? autorizar + ' aguardando autorização' : 'Expedição de clientes') + '</span></header>' +
            '<div class="tv-maquinas">' + maquinas + '</div>' +
            '<h3>Fila <span>' + dadosPatio.fila.length + '</span></h3>' + fila +
            '</article>';
    }

    // ---- lavra: o circuito com os caminhões e a lista do que cada um está fazendo

    // Circuito em coordenadas de 1000 x 240; as posições saem em % para os nomes das placas ficarem por cima.
    function geometria(estrada) {
        var ida = estrada && estrada.ida_km > 0 ? estrada.ida_km : 0;
        var retorno = estrada && estrada.retorno_km > 0 ? estrada.retorno_km : 0;
        var x0 = 200, topo = 45, base = 195, largura = 600;
        var idaFim = x0 + largura, retFim = idaFim;
        if (ida > 0 && retorno > 0) {
            var escala = largura / Math.max(ida, retorno);
            idaFim = x0 + ida * escala;
            retFim = x0 + retorno * escala;
        }
        var direita = Math.max(idaFim, retFim) + 90;
        var esquerda = x0 - 90;
        return {
            caminho: 'M' + x0 + ' ' + topo + ' H' + idaFim + ' C' + direita + ' ' + topo + ' ' + direita + ' ' + base + ' ' + retFim + ' ' + base +
                ' H' + x0 + ' C' + esquerda + ' ' + base + ' ' + esquerda + ' ' + topo + ' ' + x0 + ' ' + topo + ' Z',
            xLavra: (x0 - 68) / 10,
            lavraRotulo: (x0 + 30) / 10,
            britadorRotulo: 100 - (Math.min(idaFim, retFim) - 30) / 10,
            yIda: topo / 2.4,
            yVolta: base / 2.4,
            xSaida: x0 / 10,
            xIdaFim: idaFim / 10,
            xRetFim: retFim / 10
        };
    }

    function lugar(item, ordem, total, geo) {
        if (item.faixa === 'ida') return { x: geo.xSaida + item.progresso * (geo.xIdaFim - geo.xSaida), y: geo.yIda };
        if (item.faixa === 'volta') return { x: geo.xRetFim - item.progresso * (geo.xRetFim - geo.xSaida), y: geo.yVolta };
        // Na lavra (esperando ou carregando): empilhados na curva da esquerda.
        var passo = Math.min(22, 66 / Math.max(1, total - 1));
        return { x: geo.xLavra, y: 50 + (ordem - (total - 1) / 2) * passo };
    }

    function lavra(estado) {
        var geo = geometria(estado.estrada);
        var lista = estado.lavra.caminhoes;
        var grupos = { lavra: [], ida: [], volta: [] };
        lista.forEach(function (c) { (grupos[c.faixa] || grupos.lavra).push(c); });
        var marcas = lista.map(function (c) {
            var grupo = grupos[c.faixa] || grupos.lavra;
            var ponto = lugar(c, grupo.indexOf(c), grupo.length, geo);
            var tipo = c.faixa === 'volta' ? 'volta' : (c.faixa === 'ida' ? 'carregado' : 'lavra');
            return '<span class="tv-caminhao tv-cor-' + tipo + '" style="left:' + ponto.x + '%;top:' + ponto.y + '%">' + esc(c.placa) + '</span>';
        }).join('');
        var linhas = lista.length
            ? '<ul class="tv-caminhoes" data-um="caminhão" data-varios="caminhões">' + lista.map(function (c) {
                return '<li><strong>' + esc(c.placa) + '</strong><span>' + esc(c.estado) + (c.motorista ? ' · ' + esc(c.motorista) : '') + '</span>' +
                    '<span class="tv-tempo" data-desde="' + c.desde_ts + '"><b>' + duracao(agoraServidor() - c.desde_ts) + '</b></span></li>';
            }).join('') + '</ul><p class="tv-resto" data-total="' + lista.length + '"></p>'
            : '<p class="tv-vazio">Nenhum caminhão em ciclo.</p>';
        // Escavadeira parada para limpar a lavra: explica por que os caminhões esperam na fila.
        var limpezas = (estado.lavra.limpezas || []).map(function (l) {
            return '<p class="tv-limpeza">' + esc(l.retro) + ' em limpeza da lavra' +
                '<span class="tv-tempo" data-desde="' + l.desde_ts + '"><b>' + duracao(agoraServidor() - l.desde_ts) + '</b></span></p>';
        }).join('');
        return '<article class="tv-zona">' +
            '<header><h2>Lavra</h2><span class="tv-legenda">' +
                '<span><i class="tv-cor-carregado"></i>Carregado</span><span><i class="tv-cor-volta"></i>Voltando</span><span><i class="tv-cor-lavra"></i>Na lavra</span>' +
            '</span></header>' +
            limpezas +
            '<div class="tv-circuito">' +
                '<svg viewBox="0 0 1000 240" preserveAspectRatio="none" aria-hidden="true"><path d="' + geo.caminho + '"/></svg>' +
                '<span class="tv-posto" style="left:' + geo.lavraRotulo + '%">Lavra</span>' +
                '<span class="tv-posto direita" style="right:' + geo.britadorRotulo + '%">Britador</span>' +
                marcas +
            '</div>' +
            linhas +
            '</article>';
    }

    // Esconde as linhas que não cabem na altura da área e mostra quantas ficaram de fora.
    function caber() {
        raiz.querySelectorAll('[data-varios]').forEach(function (lista) {
            var itens = lista.children;
            var limite = lista.clientHeight;
            var escondidos = 0;
            for (var i = 0; i < itens.length; i++) {
                itens[i].hidden = false;
            }
            for (var j = 0; j < itens.length; j++) {
                if (itens[j].offsetTop + itens[j].offsetHeight > limite + 1) {
                    itens[j].hidden = true;
                    escondidos++;
                }
            }
            var resto = lista.nextElementSibling;
            if (resto && resto.hasAttribute('data-total')) {
                var fora = Number(resto.getAttribute('data-total')) - (itens.length - escondidos);
                resto.textContent = fora > 0 ? '+ ' + fora + ' ' + lista.getAttribute(fora === 1 ? 'data-um' : 'data-varios') : '';
            }
        });
    }

    // ---- por hora: uma série por gráfico, colunas finas, valor só na hora atual e na maior

    function porHora(titulo, horas, unidade, chave) {
        var horaAtual = Number(horaMinuto(agoraServidor()).slice(0, 2));
        var mapa = {};
        horas.forEach(function (h) { mapa[h.hora] = h; });
        var inicio = 6, fim = Math.max(18, horaAtual);
        horas.forEach(function (h) { inicio = Math.min(inicio, h.hora); fim = Math.max(fim, h.hora); });
        var valores = [];
        for (var h = inicio; h <= fim; h++) valores.push({ hora: h, valor: mapa[h] ? Number(mapa[h][chave] || 0) : 0 });
        var maior = Math.max.apply(null, valores.map(function (v) { return v.valor; }));
        var total = valores.reduce(function (s, v) { return s + v.valor; }, 0);
        var colunas = valores.map(function (v) {
            var altura = maior > 0 ? Math.round(v.valor / maior * 100) : 0;
            var rotulo = v.valor > 0 && (v.valor === maior || v.hora === horaAtual) ? '<b>' + quantia(v.valor) + '</b>' : '';
            return '<div class="tv-coluna' + (v.hora === horaAtual ? ' agora' : '') + (v.hora > horaAtual ? ' futuro' : '') + '" title="' + v.hora + 'h: ' + quantia(v.valor) + ' ' + unidade + '">' +
                rotulo + '<i style="height:' + altura + '%"></i><small>' + v.hora + '</small></div>';
        }).join('');
        return '<article class="tv-hora"><header><h3>' + esc(titulo) + '</h3><span>' + quantia(total) + ' ' + esc(unidade) + ' hoje</span></header>' +
            '<div class="tv-colunas">' + colunas + '</div></article>';
    }

    // ---- montagem

    function desenhar(estado) {
        dados = estado;
        desvio = estado.agora_ts * 1000 - Date.now();
        var mostrarVendas = lerOpcaoVendas();
        raiz.innerHTML =
            '<header class="tv-topo">' +
                '<div class="tv-marca"><strong>Próspera</strong><span>Pátio e lavra</span></div>' +
                '<p class="tv-situacao" role="status"><span data-situacao>Atualizado às ' + esc(horaMinuto(estado.agora_ts)) + '</span></p>' +
                '<div class="tv-relogio"><span>' + esc(estado.dia || '') + '</span><strong data-relogio>' + esc(horaMinuto(agoraServidor())) + '</strong></div>' +
            '</header>' +
            numeros(estado, mostrarVendas) +
            problemas(estado) +
            '<section class="tv-zonas">' + patio(estado) + lavra(estado) + '</section>' +
            '<section class="tv-horas">' +
                porHora('Pátio por hora', estado.patio.horas, 'cargas', 'viagens') +
                porHora('Lavra por hora', estado.lavra.horas, 'viagens', 'viagens') +
            '</section>';
        raiz.classList.toggle('com-problemas', (estado.problemas || []).length > 0);
        caber();
        horaUltimoOk = horaMinuto(estado.agora_ts);
        situacao();
    }

    // Relógios e tempos contam a cada segundo, sem pedir nada ao servidor.
    function contar() {
        var agora = agoraServidor();
        var relogio = raiz.querySelector('[data-relogio]');
        if (relogio) relogio.textContent = horaMinuto(agora);
        raiz.querySelectorAll('[data-desde]').forEach(function (el) {
            var segundos = agora - Number(el.getAttribute('data-desde'));
            var alvo = el.querySelector('b') || el;
            alvo.textContent = duracao(segundos);
            if (el.hasAttribute('data-limites')) {
                el.classList.toggle('atencao', segundos >= ESPERA_ATENCAO && segundos < ESPERA_CRITICA);
                el.classList.toggle('critico', segundos >= ESPERA_CRITICA);
            }
        });
        situacao();
    }

    // Sem atualizar há mais de 1 minuto: aviso. Há mais de 3: os números ficam apagados, para ninguém ler dado velho como atual.
    function situacao() {
        var parado = Date.now() - ultimoOk;
        var marca = raiz.querySelector('[data-situacao]');
        raiz.classList.toggle('sem-dados', parado > SEM_ATUALIZAR_AVISO);
        raiz.classList.toggle('velho', parado > SEM_ATUALIZAR_VELHO);
        if (!marca) return;
        marca.textContent = parado > SEM_ATUALIZAR_AVISO
            ? 'Sem atualização desde ' + horaUltimoOk + ' — confira a internet da TV'
            : 'Atualizado às ' + horaUltimoOk;
    }

    function buscar() {
        fetch(endereco, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
            .then(function (resposta) {
                if (!resposta.ok) throw new Error('HTTP ' + resposta.status);
                return resposta.json();
            })
            .then(function (estado) {
                ultimoOk = Date.now();
                desenhar(estado);
            })
            .catch(function () { situacao(); });
    }

    // ---- opção do vendido (fica guardada neste aparelho)

    var opcaoVendas = document.getElementById('mostrar-vendas');
    function lerOpcaoVendas() {
        try {
            return window.localStorage.getItem('monitor-vendas') === '1';
        } catch (e) {
            return opcaoVendas ? opcaoVendas.checked : false;
        }
    }
    if (opcaoVendas) {
        opcaoVendas.checked = lerOpcaoVendas();
        opcaoVendas.addEventListener('change', function () {
            try {
                window.localStorage.setItem('monitor-vendas', opcaoVendas.checked ? '1' : '0');
            } catch (e) {
                // Sem como guardar: vale só enquanto a página estiver aberta.
            }
            desenhar(dados);
        });
    }

    // ---- tela cheia

    var ampliar = document.getElementById('ampliar');
    function telaCheia(ligar) {
        document.body.classList.toggle('tela-cheia', ligar);
        if (ampliar) ampliar.textContent = ligar ? 'Sair da tela cheia' : 'Tela cheia';
        window.requestAnimationFrame(caber);
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

    // ---- cuidados com uma tela ligada o dia todo

    // A imagem anda 2 pixels de tempos em tempos, para não marcar a tela.
    var passos = [[0, 0], [2, 0], [2, 2], [0, 2]];
    var passo = 0;
    setInterval(function () {
        passo = (passo + 1) % passos.length;
        raiz.style.transform = 'translate(' + passos[passo][0] + 'px,' + passos[passo][1] + 'px)';
    }, 5 * 60 * 1000);

    // Recarrega a página toda madrugada (3h), para pegar versão nova e limpar a memória.
    (function recarregarDeMadrugada() {
        var agora = new Date();
        var alvo = new Date(agora);
        alvo.setHours(3, 0, 0, 0);
        if (alvo <= agora) alvo.setDate(alvo.getDate() + 1);
        setTimeout(function () { window.location.reload(); }, alvo - agora);
    })();

    window.addEventListener('resize', caber);
    desenhar(dados);
    setInterval(contar, 1000);
    setInterval(buscar, INTERVALO);
})();
