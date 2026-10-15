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
})();
