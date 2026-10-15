(function () {
    var script = document.currentScript;
    var corpo = document.body;

    // App do tablet: o serviço que mostra a tela de "Sem conexão" quando o sinal cai.
    if (script && 'serviceWorker' in navigator && window.isSecureContext) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(script.getAttribute('data-sw'), { scope: script.getAttribute('data-escopo') })
                .catch(function () {});
        });
    }

    // Tela acesa enquanto a pessoa está com uma máquina (e no monitor da TV). Ao soltar a máquina, a tela volta a apagar sozinha.
    if (corpo.getAttribute('data-acesa') === '1' && navigator.wakeLock) {
        var trava = null;
        var pedindo = false;
        var acender = function () {
            if (trava !== null || pedindo || document.visibilityState !== 'visible') return;
            pedindo = true;
            navigator.wakeLock.request('screen').then(function (nova) {
                pedindo = false;
                trava = nova;
                nova.addEventListener('release', function () { trava = null; });
            }, function () {
                pedindo = false;
            });
        };
        acender();
        // O Android solta a trava quando o tablet bloqueia ou outra tela fica na frente: pede de novo ao voltar.
        document.addEventListener('visibilitychange', acender);
    }

    // Botão "Instalar" nos tablets autorizados, enquanto o app ainda não está instalado.
    var faixa = document.getElementById('instalar');
    if (!faixa) return;
    var CHAVE = 'operacoes-instalar-depois';
    var adiado = function () {
        try {
            return Number(window.localStorage.getItem(CHAVE) || 0) > Date.now();
        } catch (e) {
            return false;
        }
    };
    var pedido = null;
    window.addEventListener('beforeinstallprompt', function (evento) {
        evento.preventDefault();
        pedido = evento;
        if (!adiado()) faixa.hidden = false;
    });
    window.addEventListener('appinstalled', function () {
        pedido = null;
        faixa.hidden = true;
    });
    faixa.querySelector('[data-instalar]').addEventListener('click', function () {
        if (!pedido) return;
        var atual = pedido;
        pedido = null;
        faixa.hidden = true;
        atual.prompt();
    });
    faixa.querySelector('[data-depois]').addEventListener('click', function () {
        faixa.hidden = true;
        try {
            window.localStorage.setItem(CHAVE, String(Date.now() + 7 * 24 * 3600 * 1000));
        } catch (e) {
            // Sem como guardar: a faixa volta na próxima tela.
        }
    });
})();
