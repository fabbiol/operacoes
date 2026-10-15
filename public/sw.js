// App do tablet: se o sinal cai, a tela mostra o aviso da Próspera no lugar da página de erro do navegador,
// e volta sozinha quando o sinal volta.
// As telas do sistema nunca ficam guardadas aqui: sempre vêm do servidor, com a situação de agora.
var VERSAO = 'operacoes-1';
var SEM_SINAL = new URL('offline.html', self.registration.scope).href;

self.addEventListener('install', function (evento) {
    evento.waitUntil(
        caches.open(VERSAO)
            .then(function (cache) { return cache.add(new Request(SEM_SINAL, { cache: 'reload' })); })
            .then(function () { return self.skipWaiting(); })
    );
});

self.addEventListener('activate', function (evento) {
    evento.waitUntil(
        caches.keys()
            .then(function (nomes) {
                return Promise.all(nomes.filter(function (nome) { return nome !== VERSAO; }).map(function (nome) {
                    return caches.delete(nome);
                }));
            })
            .then(function () {
                // A tela é pedida ao servidor enquanto este arquivo ainda está acordando: o tablet não fica mais lento.
                return self.registration.navigationPreload ? self.registration.navigationPreload.enable() : null;
            })
            .then(function () { return self.clients.claim(); })
    );
});

self.addEventListener('fetch', function (evento) {
    var pedido = evento.request;
    // Só a abertura de telas. Envio de formulário, atualização automática e fotos vão direto ao servidor, sem passar por aqui.
    if (pedido.mode !== 'navigate' || pedido.method !== 'GET') {
        return;
    }
    evento.respondWith(
        Promise.resolve(evento.preloadResponse)
            .then(function (adiantada) { return adiantada || fetch(pedido); })
            .catch(function () {
                return caches.match(SEM_SINAL).then(function (pagina) { return pagina || Response.error(); });
            })
    );
});
