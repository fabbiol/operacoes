(function () {
    // Monitor da TV: lido de 3 a 6 metros. Três números no topo e três colunas (pátio, lavra e britador secundário),
    // cada uma com os seus avisos; cor sempre com palavra (verde = máquina em uso, amarelo = espera longa, vermelho = parado
    // ou problema, azul = carregado ou britando). Os relógios contam sozinhos entre uma atualização e outra.
    var raiz = document.getElementById('monitor');
    var bruto = document.getElementById('monitor-dados');
    var dados = JSON.parse(bruto.textContent);
    var endereco = bruto.getAttribute('data-url');
    var INTERVALO = 8000;
    var ESPERA_ATENCAO = 30 * 60;
    var ESPERA_CRITICA = 60 * 60;
    var SEM_ATUALIZAR_AVISO = 60 * 1000;
    var SEM_ATUALIZAR_VELHO = 3 * 60 * 1000;
    var FORMAS = { pix: 'Pix', dinheiro: 'Dinheiro', cartao: 'Cartão', cheque: 'Cheque', boleto: 'Boleto', prazo: 'A prazo', sem_forma: 'Sem forma' };
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

    function relogio(desde) {
        return '<span class="tv-conta" data-desde="' + desde + '"><b>' + duracao(agoraServidor() - desde) + '</b></span>';
    }

    // ---- números do dia

    function tile(rotulo, valor, detalhe, classe) {
        return '<article class="tv-numero' + (classe ? ' ' + classe : '') + '">' +
            '<span class="tv-rotulo">' + esc(rotulo) + '</span>' +
            '<strong class="tv-valor">' + valor + '</strong>' +
            '<span class="tv-detalhe">' + (detalhe || '&nbsp;') + '</span>' +
            '</article>';
    }

    function vendido(vendas) {
        vendas = vendas || { valor: 0, notas: 0, sem_valor: 0, por_forma: {} };
        var formas = Object.keys(FORMAS).filter(function (chave) {
            return vendas.por_forma && vendas.por_forma[chave] > 0;
        });
        var lista = formas.map(function (chave) {
            return '<li><span>' + esc(FORMAS[chave]) + '</span><b>' + dinheiro(vendas.por_forma[chave]) + '</b></li>';
        }).join('');
        var semValor = vendas.sem_valor || 0;
        return '<article class="tv-numero tv-vendas">' +
            '<header><span class="tv-rotulo">Vendido hoje</span><strong class="tv-valor">' + dinheiro(vendas.valor) + '</strong></header>' +
            (lista ? '<ul>' + lista + '</ul>' : '') +
            '<span class="tv-detalhe">' + vendas.notas + (vendas.notas === 1 ? ' nota' : ' notas') +
                (semValor ? ' · ' + semValor + ' sem valor' : '') + '</span>' +
            '</article>';
    }

    function numeros(estado) {
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
        return '<section class="tv-numeros">' +
            tile('Entregue aos clientes hoje', principal,
                outra + p.expedidas + (p.expedidas === 1 ? ' carga' : ' cargas') + ' · ' + p.aguardando + ' na fila agora', 'tv-heroi') +
            tile('Viagens ao britador primário hoje', String(l.viagens) + '<small> ' + (l.viagens === 1 ? 'viagem' : 'viagens') + '</small>',
                (l.toneladas != null ? quantia(l.toneladas) + ' t levadas · ' : '') + 'viagem completa em ' + duracao(l.ciclo)) +
            vendido(estado.vendas) +
            '</section>';
    }

    // ---- avisos de problema dentro de cada coluna

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

    function avisos(lista) {
        if (!lista.length) return '';
        // No máximo dois, para sobrar altura para a fila e a pista; o resto vira "+ N".
        var fora = lista.length - 2;
        return '<ul class="tv-avisos">' + lista.slice(0, 2).map(function (p) {
            return '<li><strong>' + esc(p.maquina) + ':</strong> ' + esc(p.descricao) +
                ' <small>desde ' + esc(quando(p.desde_ts)) + ' · ' + esc(p.usuario) + '</small></li>';
        }).join('') + (fora > 0 ? '<li class="tv-avisos-mais">+ ' + fora + (fora === 1 ? ' problema' : ' problemas') + '</li>' : '') + '</ul>';
    }

    // ---- pátio

    function espera(item) {
        var segundos = agoraServidor() - item.desde_ts;
        var classe = segundos >= ESPERA_CRITICA ? 'critico' : (segundos >= ESPERA_ATENCAO ? 'atencao' : '');
        return '<span class="tv-tempo ' + classe + '" data-desde="' + item.desde_ts + '" data-limites="1">' +
            '<i aria-hidden="true">!</i>espera <b>' + duracao(segundos) + '</b></span>';
    }

    function carga(item) {
        return esc(item.produto) + ' · ' + quantia(item.quantidade) + ' ' + esc(item.unidade) +
            (item.fracionado ? ' · viagem ' + item.viagem : '');
    }

    function patio(estado) {
        var dadosPatio = estado.patio;
        var problemas = (estado.problemas || []).filter(function (p) { return p.area === 'patio'; });
        var maquinas = dadosPatio.maquinas.length
            ? dadosPatio.maquinas.map(function (m) {
                var o = m.ordem;
                var problema = problemas.filter(function (p) { return p.maquina === m.nome; })[0];
                if (o) {
                    var inicio = o.inicio_ts || o.desde_ts;
                    return '<article class="tv-maquina ocupada"><span class="tv-estado">' + esc(m.nome) + ' · carregando há ' + relogio(inicio) + '</span>' +
                        '<strong>' + esc(o.cliente) + '</strong><p>' + carga(o) + (o.operador ? ' · ' + esc(o.operador) : '') + '</p></article>';
                }
                if (problema) {
                    return '<article class="tv-maquina parada"><span class="tv-estado">' + esc(m.nome) + ' · parada desde ' + esc(quando(problema.desde_ts)) + '</span>' +
                        '<strong>' + esc(problema.descricao) + '</strong></article>';
                }
                return '<article class="tv-maquina livre"><span class="tv-estado">' + esc(m.nome) + ' · livre</span></article>';
            }).join('')
            : '<p class="tv-vazio">Nenhuma pá ativa.</p>';
        // A lista leva a fila toda; o que não couber na tela some e vira "+ N na fila" (ver caber()).
        var fila = dadosPatio.fila.length
            ? '<ol class="tv-fila" data-um="na fila" data-varios="na fila">' + dadosPatio.fila.slice(0, 20).map(function (item, i) {
                return '<li><span class="tv-ordem">' + (i + 1) + '</span><div><strong>' + esc(item.cliente) + '</strong><span>' + carga(item) + '</span></div>' + espera(item) + '</li>';
            }).join('') + '</ol><p class="tv-resto" data-total="' + dadosPatio.fila.length + '"></p>'
            : '<p class="tv-vazio">Ninguém na fila.</p>';
        var autorizar = dadosPatio.indicadores.sem_autorizacao;
        var outrosProblemas = problemas.filter(function (p) {
            return !dadosPatio.maquinas.some(function (m) { return m.nome === p.maquina; });
        });
        return '<article class="tv-zona tv-patio">' +
            '<header><h2>Clientes · Pátio</h2><span>' + (autorizar ? autorizar + ' aguardando autorização' : '') + '</span></header>' +
            avisos(outrosProblemas) +
            '<div class="tv-maquinas">' + maquinas + '</div>' +
            '<h3>Na fila <span>' + dadosPatio.fila.length + '</span></h3>' + fila +
            '</article>';
    }

    // ---- lavra: o circuito com os caminhões e as máquinas em uso

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
        var problemas = (estado.problemas || []).filter(function (p) { return p.area === 'lavra'; });
        var comProblema = {};
        problemas.forEach(function (p) { comProblema[p.maquina] = true; });
        var grupos = { lavra: [], ida: [], volta: [] };
        lista.forEach(function (c) { (grupos[c.faixa] || grupos.lavra).push(c); });
        var pontos = lista.map(function (c) {
            var grupo = grupos[c.faixa] || grupos.lavra;
            return { c: c, p: lugar(c, grupo.indexOf(c), grupo.length, geo) };
        });
        // Caminhões no mesmo trecho e no mesmo ponto (por exemplo, dois no britador) ficam lado a lado, sem um esconder o outro.
        ['ida', 'volta'].forEach(function (faixa) {
            var naFaixa = pontos.filter(function (item) { return item.c.faixa === faixa; })
                .sort(function (a, b) { return a.p.x - b.p.x; });
            for (var k = 1; k < naFaixa.length; k++) {
                if (naFaixa[k].p.x - naFaixa[k - 1].p.x < 10) naFaixa[k].p.x = naFaixa[k - 1].p.x + 10;
            }
            var excesso = naFaixa.length ? naFaixa[naFaixa.length - 1].p.x - 90 : 0;
            if (excesso > 0) naFaixa.forEach(function (item) { item.p.x = Math.max(8, item.p.x - excesso); });
        });
        var marcas = pontos.map(function (item) {
            var c = item.c;
            var tipo = c.faixa === 'volta' ? 'volta' : (c.faixa === 'ida' ? 'carregado' : 'lavra');
            return '<span class="tv-caminhao tv-cor-' + tipo + (comProblema[c.placa] ? ' tv-problema' : '') + '" data-id="' + c.id + '" style="left:' + item.p.x + '%;top:' + item.p.y + '%">' + esc(c.placa) + '</span>';
        }).join('');
        // Escavadeira parada para limpar a lavra: explica por que os caminhões esperam na fila.
        // Uma linha só, mesmo com duas escavadeiras limpando; o tempo é o da limpeza mais antiga.
        var emLimpeza = estado.lavra.limpezas || [];
        var limpezas = '';
        if (emLimpeza.length) {
            var desde = Math.min.apply(null, emLimpeza.map(function (l) { return l.desde_ts; }));
            limpezas = '<p class="tv-limpeza">' + esc(emLimpeza.map(function (l) { return l.retro; }).join(' e ')) + ' em limpeza da lavra' +
                '<span class="tv-tempo" data-desde="' + desde + '"><b>' + duracao(agoraServidor() - desde) + '</b></span></p>';
        }
        var emUso = estado.em_uso || [];
        var maquinas = emUso.length
            ? '<ul class="tv-em-uso" data-um="máquina" data-varios="máquinas">' + emUso.map(function (m) {
                return '<li class="' + (m.ativo ? 'ativo' : '') + '"><strong>' + esc(m.nome) + ' · ' + esc(m.pessoa) + '</strong><span>' + esc(m.faz) + '</span></li>';
            }).join('') + '</ul><p class="tv-resto" data-total="' + emUso.length + '"></p>'
            : '<p class="tv-vazio">Nenhuma máquina com alguém agora.</p>';
        return '<article class="tv-zona tv-lavra">' +
            '<header><h2>Lavra → Britador Primário</h2></header>' +
            avisos(problemas) +
            limpezas +
            '<div class="tv-circuito">' +
                '<svg viewBox="0 0 1000 240" preserveAspectRatio="none" aria-hidden="true"><path d="' + geo.caminho + '"/></svg>' +
                '<span class="tv-posto" style="left:' + geo.lavraRotulo + '%">Lavra</span>' +
                '<span class="tv-posto direita" style="right:' + geo.britadorRotulo + '%">Britador<br>primário</span>' +
                '<span class="tv-sentido ida">indo carregado →</span>' +
                '<span class="tv-sentido volta">← voltando vazio</span>' +
                marcas +
            '</div>' +
            '<p class="tv-legenda"><span><i class="tv-cor-lavra"></i>Na lavra</span><span><i class="tv-cor-carregado"></i>Carregado</span><span><i class="tv-cor-volta"></i>Voltando</span>' +
                (lista.length ? '' : '<span>Nenhum caminhão em ciclo.</span>') + '</p>' +
            '<h3>Máquinas em uso <span>' + emUso.length + '</span></h3>' + maquinas +
            '</article>';
    }

    // ---- britador secundário

    function britador(estado) {
        var b = estado.britador || { aberta: false, problemas: [] };
        var situacao;
        if (b.aberta && b.parado) {
            situacao = '<div class="tv-situacao-britador parado"><strong>Parado por problema</strong></div>';
        } else if (b.aberta) {
            situacao = '<div class="tv-situacao-britador britando"><strong>Britando</strong>' +
                (b.aberta_desde_ts ? '<span>há ' + relogio(b.aberta_desde_ts) + '</span>' : '') + '</div>';
        } else if (b.fim) {
            situacao = '<div class="tv-situacao-britador"><strong>Terminou às ' + esc(b.fim) + '</strong></div>';
        } else {
            situacao = '<div class="tv-situacao-britador"><strong>Sem britagem hoje</strong></div>';
        }
        var problemas = b.problemas.length
            ? '<ul class="tv-britador-problemas" data-um="problema" data-varios="problemas">' + b.problemas.map(function (p) {
                var detalhe = p.resolvido
                    ? (p.parou ? 'parou de ' : 'de ') + esc(p.desde) + ' às ' + esc(p.ate) + ' · resolvido'
                    : 'desde ' + esc(quando(p.desde_ts)) + ' · ' + (p.parou ? 'britador parado' : 'continua britando');
                return '<li class="' + (p.resolvido ? 'resolvido' : 'aberto') + '"><strong>' + esc(p.descricao) + '</strong><span>' + detalhe + '</span></li>';
            }).join('') + '</ul><p class="tv-resto" data-total="' + b.problemas.length + '"></p>'
            : '<p class="tv-vazio">Nenhum problema hoje.</p>';
        return '<article class="tv-zona tv-britador">' +
            '<header><h2>Britador Secundário</h2></header>' +
            situacao +
            '<div class="tv-horarios">' +
                '<div><span>Começou a britagem</span><strong>' + esc(b.inicio || '—') + '</strong></div>' +
                '<div><span>Terminou a britagem</span><strong>' + (b.aberta ? 'ainda britando' : esc(b.fim || '—')) + '</strong></div>' +
            '</div>' +
            '<h3>Problemas hoje <span>' + b.problemas.length + '</span></h3>' + problemas +
            '</article>';
    }

    function rodape(estado) {
        var melhor = estado.melhor_hora || {};
        var partes = [];
        if (melhor.patio) partes.push('Melhor hora do pátio: ' + melhor.patio.hora + 'h, com ' + melhor.patio.quantidade + (melhor.patio.quantidade === 1 ? ' carga' : ' cargas'));
        if (melhor.lavra) partes.push('Melhor hora da lavra: ' + melhor.lavra.hora + 'h, com ' + melhor.lavra.quantidade + (melhor.lavra.quantidade === 1 ? ' viagem' : ' viagens'));
        // A situação fica à esquerda: o canto direito de baixo é do botão de sair da tela cheia.
        return '<footer class="tv-rodape"><p class="tv-situacao" role="status"><span data-situacao>Atualizado às ' + esc(horaMinuto(estado.agora_ts)) + '</span></p>' +
            '<span>' + esc(partes.join(' · ')) + '</span></footer>';
    }

    // Esconde as linhas que não cabem na altura da área e mostra quantas ficaram de fora.
    function caber() {
        raiz.querySelectorAll('[data-varios]').forEach(function (lista) {
            var itens = lista.children;
            var resto = lista.nextElementSibling;
            var temResto = resto && resto.hasAttribute('data-total');
            // O "+ N" ocupa uma linha embaixo da lista: escreve antes de medir, para a lista medir já sem essa altura.
            if (temResto) resto.textContent = '+ ' + resto.getAttribute('data-total') + ' ' + lista.getAttribute('data-varios');
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
            if (temResto) {
                var fora = Number(resto.getAttribute('data-total')) - (itens.length - escondidos);
                resto.textContent = fora > 0 ? '+ ' + fora + ' ' + lista.getAttribute(fora === 1 ? 'data-um' : 'data-varios') : '';
            }
        });
    }

    // ---- montagem

    function desenhar(estado) {
        dados = estado;
        desvio = estado.agora_ts * 1000 - Date.now();
        // Onde cada caminhão estava: ele sai dali e desliza até o lugar novo, em vez de pular.
        var antes = {};
        raiz.querySelectorAll('.tv-caminhao[data-id]').forEach(function (el) {
            antes[el.getAttribute('data-id')] = { left: el.style.left, top: el.style.top };
        });
        raiz.innerHTML =
            '<header class="tv-topo">' +
                '<div class="tv-marca"><strong>Próspera</strong><span>Operação de hoje</span></div>' +
                '<div class="tv-relogio"><span>' + esc(estado.dia || '') + '</span><strong data-relogio>' + esc(horaMinuto(agoraServidor())) + '</strong></div>' +
            '</header>' +
            numeros(estado) +
            '<section class="tv-zonas">' + patio(estado) + lavra(estado) + britador(estado) + '</section>' +
            rodape(estado);
        var mover = [];
        raiz.querySelectorAll('.tv-caminhao[data-id]').forEach(function (el) {
            var velho = antes[el.getAttribute('data-id')];
            if (!velho || (velho.left === el.style.left && velho.top === el.style.top)) return;
            mover.push({ el: el, left: el.style.left, top: el.style.top });
            el.style.transition = 'none';
            el.style.left = velho.left;
            el.style.top = velho.top;
        });
        if (mover.length) {
            void raiz.offsetWidth;
            mover.forEach(function (m) {
                m.el.style.transition = '';
                m.el.style.left = m.left;
                m.el.style.top = m.top;
            });
        }
        caber();
        horaUltimoOk = horaMinuto(estado.agora_ts);
        situacao();
    }

    // Relógios e tempos contam a cada segundo, sem pedir nada ao servidor.
    function contar() {
        var agora = agoraServidor();
        var relogioTopo = raiz.querySelector('[data-relogio]');
        if (relogioTopo) relogioTopo.textContent = horaMinuto(agora);
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

    // ---- tela cheia

    // A escolha fica guardada no aparelho: a página recarrega sozinha às 3h e volta em tela cheia. O navegador só
    // deixa ocupar a tela toda depois de um toque; até lá, a página já fica sem o menu, do tamanho da janela.
    var ampliar = document.getElementById('ampliar');
    function guardarTelaCheia(ligar) {
        try {
            window.localStorage.setItem('monitor-tela-cheia', ligar ? '1' : '0');
        } catch (e) {
            // Sem como guardar: vale só enquanto a página estiver aberta.
        }
    }
    function telaCheiaGuardada() {
        try {
            return window.localStorage.getItem('monitor-tela-cheia') === '1';
        } catch (e) {
            return false;
        }
    }
    function pedirTelaToda() {
        var pedido = document.documentElement.requestFullscreen;
        if (pedido && !document.fullscreenElement) {
            try {
                var promessa = pedido.call(document.documentElement);
                if (promessa && promessa.catch) promessa.catch(function () {});
            } catch (e) {
                // Sem permissão agora: fica do tamanho da janela.
            }
        }
    }
    function telaCheia(ligar) {
        document.body.classList.toggle('tela-cheia', ligar);
        if (ampliar) ampliar.textContent = ligar ? 'Sair da tela cheia' : 'Tela cheia';
        window.requestAnimationFrame(caber);
    }
    if (ampliar) {
        ampliar.addEventListener('click', function () {
            var ligar = !document.body.classList.contains('tela-cheia');
            telaCheia(ligar);
            guardarTelaCheia(ligar);
            if (!ligar) {
                if (document.fullscreenElement) document.exitFullscreen();
                return;
            }
            pedirTelaToda();
        });
        document.addEventListener('fullscreenchange', function () {
            // Saiu da tela toda pelo Esc ou pelo botão do controle: volta ao normal e esquece a escolha.
            if (!document.fullscreenElement && document.body.classList.contains('tela-cheia')) {
                telaCheia(false);
                guardarTelaCheia(false);
            }
        });
        if (telaCheiaGuardada()) {
            telaCheia(true);
            pedirTelaToda();
            // Toque, clique ou tecla do controle na TV: aí o navegador deixa ocupar a tela toda. Tenta a cada toque até
            // conseguir (no toque da tela, só o "click" vale como gesto; o "pointerdown" não).
            var aoTocar = function (evento) {
                if (evento.target === ampliar) return;
                if (document.body.classList.contains('tela-cheia')) pedirTelaToda();
            };
            var pararDeTentar = function () {
                if (!document.fullscreenElement && document.body.classList.contains('tela-cheia')) return;
                document.removeEventListener('click', aoTocar, true);
                document.removeEventListener('keydown', aoTocar, true);
                document.removeEventListener('fullscreenchange', pararDeTentar);
            };
            document.addEventListener('click', aoTocar, true);
            document.addEventListener('keydown', aoTocar, true);
            document.addEventListener('fullscreenchange', pararDeTentar);
        }
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
