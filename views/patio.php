<section class="cabeca">
    <div>
        <p class="olho">Operador do pátio</p>
        <h1>Ordens liberadas</h1>
    </div>
    <a class="btn secundario" href="<?= e(url('patio/resumo')) ?>">Tempos de hoje</a>
</section>
<p class="nota">A fila começa na liberação e a carga no toque de iniciar. Finalize quando a carga estiver no caminhão.</p>

<?php if ($ordens === []): ?>
    <p class="vazio">Nenhuma ordem liberada no momento.</p>
<?php else: ?>
    <div class="lista">
        <?php foreach ($ordens as $ordem): ?>
            <article class="cartao destaque">
                <header>
                    <strong><?= e($ordem['ordem']) ?></strong>
                    <span class="selo status-<?= e($ordem['status']) ?>"><?= e(status_rotulo($ordem['status'])) ?></span>
                </header>
                <p class="cliente"><?= e($ordem['cliente']) ?></p>
                <p class="carga"><?= e(formatar_quantidade((string) $ordem['quantidade_autorizada'])) ?> <?= e(unidade_rotulo($ordem['unidade'])) ?> · <?= e($ordem['material_nome']) ?></p>
                <p class="meta">Pedido <?= e($ordem['pedido']) ?> · NF <?= e($ordem['nf']) ?></p>
                <?php
                $desde = $ordem['status'] === 'carregando' ? ($ordem['inicio_carregamento'] ?? null) : ($ordem['autorizado_em'] ?? null);
                $etapa = $ordem['status'] === 'carregando' ? 'Carga' : 'Na fila';
                ?>
                <?php if ($desde): ?>
                    <p class="ciclo"><time data-desde="<?= (int) strtotime((string) $desde) ?>"><?= e(texto_duracao(segundos_entre((string) $desde, agora_sql()))) ?></time></p>
                    <p class="meta"><?= e($etapa) ?> desde <?= e(hora_curta((string) $desde)) ?><?php if ($ordem['status'] === 'carregando'): ?> · <?= e($ordem['operador'] ?: 'operador') ?> · <?= e($ordem['pa_nome'] ?: 'pá') ?><?php endif; ?></p>
                <?php endif; ?>
                <?php if ($ordem['status'] === 'carregando'): ?>
                    <a class="btn concluir" href="<?= e(url('carregamentos/' . $ordem['id'] . '/finalizar')) ?>">Finalizar carregamento</a>
                <?php else: ?>
                    <a class="btn primario" href="<?= e(url('carregamentos/' . $ordem['id'] . '/iniciar')) ?>">Iniciar carregamento</a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($enviadas !== []): ?>
    <section class="bloco">
        <h2>Enviadas hoje</h2>
        <ul class="enviadas">
            <?php foreach ($enviadas as $ordem): ?>
                <li>
                    <strong><?= e($ordem['ordem']) ?></strong>
                    <?= e($ordem['cliente']) ?>
                    <span class="selo status-<?= e($ordem['status']) ?>"><?= e(status_rotulo($ordem['status'])) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
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
