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
<p class="nota">O motorista marca a chegada na lavra e no britador. A retro faz a carga. Encerrar viagem vale em qualquer momento.</p>

<div id="fluxo" data-url="<?= e($fluxoUrl) ?>" data-marca="<?= e($fluxoMarca) ?>">
<?php if ($emCiclo === [] && $livres === []): ?>
    <?php if (!empty($semCadastro)): ?>
        <p class="vazio">Nenhum caminhão cadastrado.<?php if (Auth::admin()): ?>
            <a href="<?= e(url('cadastros/caminhoes/novo')) ?>">Cadastrar o primeiro</a>.
        <?php endif; ?></p>
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
                <?php if (isset(Lavra::etapas()[$ciclo['status']])): ?>
                    <a class="btn encerrar-viagem" href="<?= e(url('lavra/ciclos/' . $ciclo['id'] . '/encerrar')) ?>">Encerrar viagem</a>
                <?php endif; ?>
                <?php
                $cancelarCiclo = null;
                $ficha = $ciclo;
                $selo = $etapa['rotulo'];
                require __DIR__ . '/trecho_caminhao.php';
                ?>
                <p class="carga">
                    <?php if ($desde): ?>
                        <time data-desde="<?= (int) strtotime($desde) ?>"><?= e(texto_duracao(segundos_entre($desde, agora_sql()))) ?></time>
                    <?php endif; ?>
                </p>
                <p class="meta"><?php
                    $fotos = [url_foto_pessoa($ciclo)];
                    $legenda = 'Desde ' . hora_curta($desde) . ($ciclo['operador'] ? ' · ' . $ciclo['operador'] : '');
                    $destaque = false;
                    require __DIR__ . '/trecho_quem.php';
                ?></p>
                <?php if ($ciclo['status'] === 'na_fila'): ?>
                    <p class="espera">Aguardando a retro</p>
                <?php elseif ($ciclo['status'] === 'carregando'): ?>
                    <p class="meta"><?php
                        $fotos = [url_foto_retro($ciclo), url_foto_operador_carga($ciclo)];
                        $legenda = trim((string) ($ciclo['retro_nome'] ?? '')) !== '' ? (string) $ciclo['retro_nome'] : 'Retroescavadeira';
                        $quemCarga = trim((string) ($ciclo['operador_carga'] ?? ''));
                        if ($quemCarga !== '') {
                            $legenda .= ' · ' . $quemCarga;
                        }
                        $destaque = false;
                        require __DIR__ . '/trecho_quem.php';
                    ?></p>
                    <p class="nota">A retro está carregando.</p>
                    <?php if (Auth::admin() && (string) ($ciclo['operador_carga'] ?? '') !== Auth::usuario()): ?>
                        <form method="post" action="<?= e(url('lavra/ciclos/' . $ciclo['id'] . '/concluir-carga')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn primario">Carga concluída</button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <form method="post" action="<?= e(url('lavra/ciclos/' . $ciclo['id'] . '/avancar')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn primario"><?= e($etapa['acao']) ?></button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($livres !== []): ?>
    <h2>Disponíveis</h2>
    <div class="lista">
        <?php foreach ($livres as $caminhao): ?>
            <article class="cartao">
                <?php $cancelarCiclo = null; $ficha = $caminhao; $selo = ''; require __DIR__ . '/trecho_caminhao.php'; ?>
                <form method="post" action="<?= e(url('lavra/caminhoes/' . $caminhao['id'] . '/fila')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn primario">Chegou na lavra</button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($mostraCarga)): ?>
    <section class="bloco">
        <h2>Carga na lavra</h2>
        <?php require __DIR__ . '/trecho_carga_lavra.php'; ?>
    </section>
<?php endif; ?>
</div>

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
