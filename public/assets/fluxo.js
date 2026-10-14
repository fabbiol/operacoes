(function () {
    var caixa = document.getElementById('fluxo');
    if (!caixa) return;

    var url = caixa.getAttribute('data-url');
    var marca = caixa.getAttribute('data-marca') || '';
    var faixa = document.getElementById('conexao');
    // A faixa aparece e some; o leitor de tela anuncia pelo nó que fica sempre na página.
    var leitor = document.getElementById('conexao-leitor');
    var PRAZO = 8000;
    var segurando = false;
    var dedoAte = 0;
    var enviando = false;
    var buscando = false;
    var encerrada = false;
    var falhas = 0;
    var horaOk = document.body.getAttribute('data-hora') || '';
    // O envio.js consulta este objeto para não mandar ação com a tela sem conexão.
    var conexao = window.operacoesConexao = { bloqueio: '' };

    function agora() {
        return performance.now();
    }

    function ocupado() {
        if (enviando || segurando || agora() < dedoAte) return true;
        var ativo = document.activeElement;
        return !!(ativo && caixa.contains(ativo) && ativo.matches('input, textarea, select'));
    }

    document.addEventListener('pointerdown', function () {
        segurando = true;
    }, true);
    document.addEventListener('pointerup', function () {
        segurando = false;
        dedoAte = agora() + 800;
    }, true);
    document.addEventListener('pointercancel', function () {
        segurando = false;
        dedoAte = 0;
    }, true);
    document.addEventListener('envio:saindo', function () {
        enviando = true;
    });
    // O envio ficou sem resposta e o botão foi liberado: a tela volta a se atualizar.
    document.addEventListener('envio:liberado', function () {
        enviando = false;
        olhar();
    });
    document.addEventListener('envio:barrado', function () {
        // Confere a conexão assim que o dedo sai da tela.
        setTimeout(olhar, 900);
    });
    window.addEventListener('pageshow', function (evento) {
        if (evento.persisted) {
            enviando = false;
            buscando = false;
            olhar();
        }
    });
    window.addEventListener('online', function () {
        olhar();
    });
    window.addEventListener('offline', function () {
        semConexao();
    });

    function mostrar(texto, destino) {
        if (leitor && leitor.textContent !== texto) leitor.textContent = texto;
        if (!faixa) return;
        faixa.textContent = '';
        if (destino) {
            var link = document.createElement('a');
            link.href = destino;
            link.textContent = texto;
            faixa.appendChild(link);
        } else {
            faixa.textContent = texto;
        }
        faixa.hidden = false;
    }

    function respondeu(dado) {
        if (dado && dado.agora && window.operacoesRelogio) window.operacoesRelogio.acertar(dado.agora);
    }

    // Só conta como em dia quando a tela mostra o que o servidor tem agora.
    function emDia(dado) {
        falhas = 0;
        if (dado && dado.hora) horaOk = dado.hora;
        if (encerrada) return;
        conexao.bloqueio = '';
        if (faixa) faixa.hidden = true;
        if (leitor) leitor.textContent = '';
        document.dispatchEvent(new CustomEvent('conexao:ok'));
    }

    function semConexao(doServidor) {
        if (encerrada) return;
        if (doServidor) {
            conexao.bloqueio = 'O servidor não respondeu. A ação NÃO foi enviada. Tente de novo em instantes.';
            mostrar('O SERVIDOR NÃO RESPONDE' + (horaOk ? ' desde ' + horaOk : '') + '. A tela pode estar desatualizada.');
            return;
        }
        conexao.bloqueio = 'Sem sinal. A ação NÃO foi enviada. Tente de novo quando o sinal voltar.';
        mostrar('SEM CONEXÃO' + (horaOk ? ' desde ' + horaOk : '') + '. A tela pode estar desatualizada.');
    }

    // doServidor: a rede funcionou, mas o servidor respondeu com erro.
    function falhou(doServidor) {
        falhas++;
        if (falhas >= 2 || navigator.onLine === false) {
            semConexao(doServidor === true);
        } else {
            // Confere de novo logo, sem esperar a próxima rodada.
            setTimeout(olhar, 1000);
        }
    }

    function telaMudou(destino) {
        encerrada = true;
        var login = /\/login(\?|$)/.test(destino);
        conexao.bloqueio = login
            ? 'A sessão terminou. A ação NÃO foi enviada. Toque no aviso no topo para entrar de novo.'
            : 'A tela mudou. A ação NÃO foi enviada. Toque no aviso no topo para atualizar.';
        mostrar(login ? 'A sessão terminou. Toque aqui para entrar de novo.' : 'A tela mudou. Toque aqui para atualizar.', destino);
    }

    function pedir(endereco, cabecalhos) {
        var controle = typeof AbortController === 'function' ? new AbortController() : null;
        var prazo = setTimeout(function () {
            if (controle) controle.abort();
        }, PRAZO);
        return fetch(endereco, {
            headers: cabecalhos,
            cache: 'no-store',
            credentials: 'same-origin',
            signal: controle ? controle.signal : undefined
        }).then(function (resposta) {
            return resposta.text().then(function (texto) {
                return { resposta: resposta, texto: texto };
            });
        }).then(function (resultado) {
            clearTimeout(prazo);
            return resultado;
        }, function (erro) {
            clearTimeout(prazo);
            throw erro;
        });
    }

    // Devolve 'ok', 'ocupado' (tenta de novo depois) ou 'fora' (a tela nova não tem esta caixa).
    function aplicar(html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var novo = doc.getElementById('fluxo');
        var nova = novo ? novo.getAttribute('data-marca') || '' : '';
        if (nova === '') return 'fora';
        if (nova === marca) return 'ok';
        if (ocupado()) return 'ocupado';
        caixa.innerHTML = novo.innerHTML;
        caixa.setAttribute('data-marca', nova);
        marca = nova;
        // Os cartões mudaram de lugar: o envio.js ignora toques logo depois da troca.
        document.dispatchEvent(new CustomEvent('fluxo:trocou'));
        if (window.operacoesRelogio) window.operacoesRelogio.tick();

        return 'ok';
    }

    function olhar() {
        if (!url || buscando || encerrada || ocupado() || document.visibilityState === 'hidden') return;
        buscando = true;
        // O X-Fluxo evita que uma ida ao login durante a consulta gaste o aviso que explica a saída.
        pedir(url, { Accept: 'application/json', 'X-Fluxo': '1' })
            .then(function (r) {
                var tipo = r.resposta.headers.get('Content-Type') || '';
                if (r.resposta.ok && (r.resposta.redirected || tipo.indexOf('application/json') === -1)) {
                    telaMudou(r.resposta.url);
                    return null;
                }
                if (r.resposta.status >= 400 && r.resposta.status < 500) {
                    // Sem acesso a esta tela agora (função ou máquina mudou): recarregar mostra o motivo.
                    telaMudou(window.location.href);
                    return null;
                }
                if (!r.resposta.ok) {
                    falhou(true);
                    return null;
                }
                var dado = JSON.parse(r.texto);
                respondeu(dado);
                if (dado.marca === marca) {
                    emDia(dado);
                    return null;
                }
                if (ocupado()) return null;
                return pedir(window.location.href, { Accept: 'text/html', 'X-Fluxo': '1' }).then(function (p) {
                    if (p.resposta.status >= 400 && p.resposta.status < 500) {
                        telaMudou(window.location.href);
                        return;
                    }
                    if (!p.resposta.ok) {
                        falhou(true);
                        return;
                    }
                    if (p.resposta.redirected) {
                        telaMudou(p.resposta.url);
                        return;
                    }
                    var resultado = aplicar(p.texto);
                    if (resultado === 'fora') {
                        telaMudou(window.location.href);
                    } else if (resultado === 'ok') {
                        emDia(dado);
                    }
                });
            })
            .catch(function () {
                falhou();
            })
            .then(function () {
                buscando = false;
            });
    }

    setInterval(olhar, 4000);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') olhar();
    });
})();
