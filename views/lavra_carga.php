<section class="cabeca">
    <div>
        <p class="olho">Operador da lavra</p>
        <h1>Carga na lavra</h1>
    </div>
</section>
<?php if (!empty($foraDaMaquina)): ?>
    <p class="nota">A máquina do menu é a <?= e((string) $foraDaMaquina['nome']) ?>. Troque para uma retroescavadeira para carregar na lavra.</p>
    <p><a class="btn secundario" href="<?= e(url('maquina')) ?>">Trocar</a></p>
<?php else: ?>
    <div id="fluxo" data-url="<?= e($fluxoUrl) ?>" data-marca="<?= e($fluxoMarca) ?>">
    <?php if (!empty($minhaCarga) || !empty($retroEscolhida)): ?>
        <p class="nota">Os caminhões na fila. Conclua quando o caminhão sair para o britador.</p>
    <?php endif; ?>
    <?php require __DIR__ . '/trecho_carga_lavra.php'; ?>
    </div>
<?php endif; ?>

<script>
(function () {
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
        var agora = Date.now();
        document.querySelectorAll('[data-desde]').forEach(function (el) {
            var inicio = Number(el.getAttribute('data-desde')) * 1000;
            el.textContent = texto(Math.floor((agora - inicio) / 1000));
        });
    }
    tick();
    setInterval(tick, 1000);
})();
</script>
