(function () {
    // Aviso do tablet do pátio: carga nova liberada, carga alterada ou cancelada pelo administrativo.
    // A tela compara a lista de agora com a que já tinha visto (guardada nesta aba) e avisa o que mudou,
    // com som, vibração e um quadro grande que só some no "Ciente".
    var CHAVE_VISTO = 'operacoes-patio-visto';
    var CHAVE_PENDENTES = 'operacoes-patio-pendentes';
    var REPETIR_A_CADA = 20000;
    var REPETICOES = 3;
    var TITULOS = {
        nova: 'Nova carga liberada',
        alterada: 'Carga alterada',
        cancelada: 'Carga cancelada'
    };
    var visto = null;
    var pendentes = [];
    var quadro = null;
    var repetidas = 0;
    var relogio = null;
    var audio = null;

    function lerGuardado(chave) {
        try {
            return JSON.parse(window.sessionStorage.getItem(chave) || 'null');
        } catch (e) {
            return null;
        }
    }

    function guardar(chave, valor) {
        try {
            window.sessionStorage.setItem(chave, JSON.stringify(valor));
        } catch (e) {
            // Sem como guardar: vale enquanto a tela estiver aberta.
        }
    }

    function dadosDaTela() {
        var bruto = document.querySelector('script[data-avisos-patio]');
        if (!bruto) return null;
        try {
            return JSON.parse(bruto.textContent);
        } catch (e) {
            return null;
        }
    }

    // ---- som e vibração

    // O navegador só deixa tocar som depois de um toque na tela: o primeiro toque destrava.
    function destravarSom() {
        var Contexto = window.AudioContext || window.webkitAudioContext;
        if (!Contexto) return;
        if (!audio) {
            try {
                audio = new Contexto();
            } catch (e) {
                audio = null;
                return;
            }
        }
        if (audio.state === 'suspended') audio.resume().catch(function () {});
    }
    document.addEventListener('pointerdown', destravarSom, true);

    function bipes(tipo) {
        if (!audio || audio.state !== 'running') return;
        // Nova: três bipes subindo. Alterada: dois iguais. Cancelada: dois descendo.
        var notas = tipo === 'nova' ? [660, 880, 1100] : (tipo === 'cancelada' ? [880, 520] : [880, 880]);
        var inicio = audio.currentTime + 0.05;
        notas.forEach(function (frequencia, i) {
            var osc = audio.createOscillator();
            var volume = audio.createGain();
            osc.type = 'square';
            osc.frequency.value = frequencia;
            var t = inicio + i * 0.32;
            volume.gain.setValueAtTime(0.0001, t);
            volume.gain.exponentialRampToValueAtTime(0.35, t + 0.02);
            volume.gain.exponentialRampToValueAtTime(0.0001, t + 0.26);
            osc.connect(volume).connect(audio.destination);
            osc.start(t);
            osc.stop(t + 0.28);
        });
    }

    function tocar() {
        if (pendentes.length === 0) return;
        var tipo = pendentes[pendentes.length - 1].tipo;
        var app = window.ProsperaApp;
        // No app da Próspera, o próprio Android toca o som de notificação e vibra, sem precisar de toque.
        if (app && typeof app.avisar === 'function') {
            try {
                app.avisar(tipo);
                return;
            } catch (e) {
                // App antigo: segue com o som do navegador.
            }
        }
        bipes(tipo);
        if (navigator.vibrate) {
            try {
                navigator.vibrate([400, 200, 400]);
            } catch (e) {
                // Sem vibração neste aparelho.
            }
        }
    }

    // ---- o quadro na tela

    function desenhar() {
        if (pendentes.length === 0) {
            if (quadro) quadro.hidden = true;
            return;
        }
        if (!quadro) {
            quadro = document.createElement('div');
            quadro.className = 'avisos-patio';
            quadro.setAttribute('role', 'alertdialog');
            quadro.setAttribute('aria-live', 'assertive');
            quadro.setAttribute('aria-label', 'Aviso do administrativo');
            document.body.appendChild(quadro);
        }
        quadro.textContent = '';
        var caixa = document.createElement('div');
        caixa.className = 'avisos-caixa';
        pendentes.slice(-4).reverse().forEach(function (aviso) {
            var item = document.createElement('article');
            item.className = 'aviso-patio ' + aviso.tipo;
            var titulo = document.createElement('strong');
            titulo.textContent = TITULOS[aviso.tipo] || 'Aviso';
            var cliente = document.createElement('p');
            cliente.className = 'aviso-cliente';
            cliente.textContent = aviso.cliente;
            var carga = document.createElement('p');
            carga.textContent = aviso.carga + ' · ' + aviso.ordem;
            item.appendChild(titulo);
            item.appendChild(cliente);
            item.appendChild(carga);
            caixa.appendChild(item);
        });
        if (pendentes.length > 4) {
            var resto = document.createElement('p');
            resto.className = 'aviso-resto';
            resto.textContent = '+ ' + (pendentes.length - 4) + ' avisos';
            caixa.appendChild(resto);
        }
        var botao = document.createElement('button');
        botao.type = 'button';
        botao.className = 'btn primario';
        botao.textContent = 'Ciente';
        botao.addEventListener('click', ciente);
        caixa.appendChild(botao);
        quadro.appendChild(caixa);
        quadro.hidden = false;
    }

    function ciente() {
        pendentes = [];
        guardar(CHAVE_PENDENTES, pendentes);
        repetidas = 0;
        if (relogio) {
            clearInterval(relogio);
            relogio = null;
        }
        desenhar();
    }

    // Toca de novo algumas vezes enquanto ninguém tocar em "Ciente": dentro da máquina, um bipe só passa batido.
    function repetir() {
        if (relogio) clearInterval(relogio);
        repetidas = 0;
        relogio = setInterval(function () {
            repetidas++;
            if (pendentes.length === 0 || repetidas > REPETICOES) {
                clearInterval(relogio);
                relogio = null;
                return;
            }
            tocar();
        }, REPETIR_A_CADA);
    }

    // ---- comparar com o que a tela já tinha visto

    function comparar() {
        var dado = dadosDaTela();
        if (!dado) return;
        var agora = { fila: {}, canceladas: {} };
        dado.fila.forEach(function (ordem) { agora.fila[ordem.id] = ordem.assinatura; });
        dado.canceladas.forEach(function (ordem) { agora.canceladas[ordem.id] = 1; });
        var antes = visto || lerGuardado(CHAVE_VISTO);
        var novos = [];
        // Na primeira vez nesta aba, tudo o que já está na tela conta como visto.
        if (antes && antes.fila && antes.canceladas) {
            dado.fila.forEach(function (ordem) {
                if (!(ordem.id in antes.fila)) {
                    if (!(ordem.id in antes.canceladas)) novos.push(aviso('nova', ordem));
                } else if (antes.fila[ordem.id] !== ordem.assinatura) {
                    novos.push(aviso('alterada', ordem));
                }
            });
            dado.canceladas.forEach(function (ordem) {
                if (ordem.id in antes.fila && !(ordem.id in antes.canceladas)) novos.push(aviso('cancelada', ordem));
            });
        }
        visto = agora;
        guardar(CHAVE_VISTO, agora);
        if (novos.length === 0) return;
        // Um aviso por carga: o mais novo vale (alterada e depois cancelada fica só cancelada).
        novos.forEach(function (novo) {
            pendentes = pendentes.filter(function (velho) { return velho.id !== novo.id; });
            pendentes.push(novo);
        });
        guardar(CHAVE_PENDENTES, pendentes);
        desenhar();
        tocar();
        repetir();
    }

    function aviso(tipo, ordem) {
        return { tipo: tipo, id: ordem.id, ordem: ordem.ordem, cliente: ordem.cliente, carga: ordem.carga };
    }

    // Avisos que ficaram sem "Ciente" (a pessoa abriu outra tela) voltam ao abrir o pátio de novo.
    pendentes = lerGuardado(CHAVE_PENDENTES) || [];
    if (!Array.isArray(pendentes)) pendentes = [];
    desenhar();
    comparar();
    if (pendentes.length > 0 && !relogio) {
        tocar();
        repetir();
    }
    document.addEventListener('fluxo:trocou', comparar);
})();
