(function () {
    // Os cronômetros contam pelo relógio do servidor. O tablet sem chip pode estar com a hora errada,
    // e o Android pode acertar a hora com a tela aberta. Por isso a conta usa performance.now(),
    // que não salta junto com o relógio do aparelho.
    var servidorMs = (Number(document.body.getAttribute('data-agora')) || 0) * 1000;
    var base = performance.now();

    function agora() {
        return servidorMs > 0 ? servidorMs + (performance.now() - base) : Date.now();
    }

    function texto(segundos) {
        segundos = Math.max(0, segundos);
        var horas = Math.floor(segundos / 3600);
        var minutos = Math.floor((segundos % 3600) / 60);
        var resto = segundos % 60;
        function dois(n) { return (n < 10 ? '0' : '') + n; }
        if (horas > 0) return horas + ' h ' + dois(minutos) + ' min';
        if (minutos > 0) return minutos + ' min ' + dois(resto) + ' s';
        return resto + ' s';
    }

    function tick() {
        var momento = agora();
        document.querySelectorAll('[data-desde]').forEach(function (el) {
            var inicio = Number(el.getAttribute('data-desde')) * 1000;
            el.textContent = texto(Math.floor((momento - inicio) / 1000));
        });
    }

    window.operacoesRelogio = {
        // Cada consulta que dá certo traz a hora do servidor e acerta a base.
        // Só anda para trás numa diferença grande: a demora variável da rede não pode fazer o contador recuar.
        acertar: function (segundosDoServidor) {
            if (!(segundosDoServidor > 0)) return;
            var novo = segundosDoServidor * 1000;
            var atual = agora();
            if (servidorMs === 0 || novo > atual || atual - novo > 5000) {
                servidorMs = novo;
                base = performance.now();
            }
        },
        tick: tick
    };

    tick();
    setInterval(tick, 1000);
})();
