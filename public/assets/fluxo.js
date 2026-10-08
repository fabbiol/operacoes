(function () {
    var caixa = document.getElementById('fluxo');
    if (!caixa) return;

    var url = caixa.getAttribute('data-url');
    var marca = caixa.getAttribute('data-marca') || '';
    var segurando = false;
    var dedoAte = 0;
    var enviando = false;
    var buscando = false;

    function ocupado() {
        if (enviando || segurando || Date.now() < dedoAte) return true;
        var ativo = document.activeElement;
        return !!(ativo && caixa.contains(ativo) && ativo.matches('input, textarea, select'));
    }

    document.addEventListener('pointerdown', function () {
        segurando = true;
    }, true);
    document.addEventListener('pointerup', function () {
        segurando = false;
        dedoAte = Date.now() + 800;
    }, true);
    document.addEventListener('pointercancel', function () {
        segurando = false;
        dedoAte = 0;
    }, true);
    document.addEventListener('submit', function () {
        enviando = true;
    }, true);

    function aplicar(html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var novo = doc.getElementById('fluxo');
        if (!novo) return;
        var nova = novo.getAttribute('data-marca') || '';
        if (nova === '' || nova === marca || ocupado()) return;
        caixa.innerHTML = novo.innerHTML;
        caixa.setAttribute('data-marca', nova);
        marca = nova;
    }

    function olhar() {
        if (!url || buscando || ocupado() || document.visibilityState === 'hidden') return;
        buscando = true;
        fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store', credentials: 'same-origin' })
            .then(function (resposta) {
                if (!resposta.ok) return null;
                return resposta.json();
            })
            .then(function (dado) {
                if (!dado || dado.marca === marca || ocupado()) return null;
                return fetch(window.location.href, {
                    headers: { Accept: 'text/html', 'X-Fluxo': '1' },
                    cache: 'no-store',
                    credentials: 'same-origin'
                }).then(function (resposta) {
                    if (!resposta.ok) return null;
                    return resposta.text();
                }).then(function (html) {
                    if (html) aplicar(html);
                });
            })
            .catch(function () {})
            .then(function () {
                buscando = false;
            });
    }

    setInterval(olhar, 4000);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') olhar();
    });
})();
