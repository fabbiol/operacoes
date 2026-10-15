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

    // Dentro do app Android da Próspera quem mantém a tela acesa é o próprio app.
    var app = window.ProsperaApp;
    if (app && app.telaAcesa) {
        try {
            app.telaAcesa(corpo.getAttribute('data-acesa') === '1');
        } catch (e) {
            // App antigo, sem essa função: a tela segue o normal do tablet.
        }
    } else if (corpo.getAttribute('data-acesa') === '1' && navigator.wakeLock) {
        // Tela acesa enquanto a pessoa está com uma máquina (e no monitor da TV). Ao soltar a máquina, a tela volta a apagar sozinha.
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

    // Nos tablets autorizados, fora do app: a faixa que leva para baixar o app.
    var faixa = document.getElementById('instalar');
    if (!faixa || app || (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)) return;
    var CHAVE = 'operacoes-instalar-depois';
    var adiado = false;
    try {
        adiado = Number(window.localStorage.getItem(CHAVE) || 0) > Date.now();
    } catch (e) {
        adiado = false;
    }
    if (!adiado) faixa.hidden = false;
    faixa.querySelector('[data-depois]').addEventListener('click', function () {
        faixa.hidden = true;
        try {
            window.localStorage.setItem(CHAVE, String(Date.now() + 7 * 24 * 3600 * 1000));
        } catch (e) {
            // Sem como guardar: a faixa volta na próxima tela.
        }
    });
})();
