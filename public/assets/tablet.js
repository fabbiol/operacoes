(function () {
    var script = document.currentScript;

    // App do tablet: o serviço que mostra a tela de "Sem conexão" quando o sinal cai.
    if (script && 'serviceWorker' in navigator && window.isSecureContext) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(script.getAttribute('data-sw'), { scope: script.getAttribute('data-escopo') })
                .catch(function () {});
        });
    }
})();
