<section class="cabeca">
    <div>
        <p class="olho">Ciclo de lavra</p>
        <h1>Caminhões</h1>
    </div>
    <div class="acoes">
        <?php if (Auth::admin()): ?>
            <a class="btn secundario" href="<?= e(url('lavra/fila')) ?>">Fila</a>
        <?php endif; ?>
        <a class="btn secundario" href="<?= e(url('lavra/resumo')) ?>">Tempos de hoje</a>
    </div>
</section>
<p class="nota">Fila na lavra, carga, ida até o britador e retorno vazio. O tempo de cada etapa é o intervalo entre os toques.</p>

<?php if ($emCiclo === [] && $livres === []): ?>
    <?php if (!empty($semCadastro)): ?>
        <p class="vazio">Nenhum caminhão cadastrado.
            <a href="<?= e(url('cadastros/caminhoes/novo')) ?>">Cadastrar o primeiro</a>.
        </p>
    <?php else: ?>
        <p class="vazio">Nenhum caminhão disponível agora.</p>
    <?php endif; ?>
<?php endif; ?>

<?php if ($emCiclo !== []): ?>
    <h2>Em ciclo</h2>
    <div class="lista">
        <?php foreach ($emCiclo as $ciclo): ?>
            <?php $etapa = Lavra::etapas()[$ciclo['status']]; ?>
            <?php $desde = Lavra::desde($ciclo); ?>
            <article class="cartao destaque">
                <header>
                    <strong><?= e($ciclo['placa']) ?></strong>
                    <span class="selo status-<?= e($ciclo['status']) ?>"><?= e($etapa['rotulo']) ?></span>
                </header>
                <p class="carga">
                    <?php if ($desde): ?>
                        <time data-desde="<?= (int) strtotime($desde) ?>"><?= e(texto_duracao(segundos_entre($desde, agora_sql()))) ?></time>
                    <?php endif; ?>
                </p>
                <p class="meta">Desde <?= e(hora_curta($desde)) ?><?php if ($ciclo['operador']): ?> · <?= e($ciclo['operador']) ?><?php endif; ?></p>
                <form method="post" action="<?= e(url('lavra/ciclos/' . $ciclo['id'] . '/avancar')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn primario"><?= e($etapa['acao']) ?></button>
                </form>
                <a class="link-discreto" href="<?= e(url('lavra/ciclos/' . $ciclo['id'] . '/cancelar')) ?>">Cancelar ciclo</a>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($livres !== []): ?>
    <h2>Disponíveis</h2>
    <div class="lista">
        <?php foreach ($livres as $caminhao): ?>
            <article class="cartao">
                <header>
                    <strong><?= e($caminhao['nome']) ?></strong>
                </header>
                <form method="post" action="<?= e(url('lavra/caminhoes/' . $caminhao['id'] . '/fila')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn primario">Entrou na fila</button>
                </form>
            </article>
        <?php endforeach; ?>
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
