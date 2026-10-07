<section class="cabeca">
    <div>
        <p class="olho">Administrativo</p>
        <h1>Painel do dia</h1>
    </div>
    <p class="data"><?= e(data_por_extenso()) ?></p>
</section>

<?php if ($autorizar > 0 || $conferir > 0): ?>
    <nav class="parado" aria-label="Esperando o administrativo">
        <?php if ($autorizar > 0): ?>
            <a href="<?= e(url('fila?situacao=aguardando_autorizacao')) ?>">Autorizar <strong><?= (int) $autorizar ?></strong></a>
        <?php endif; ?>
        <?php if ($conferir > 0): ?>
            <a href="<?= e(url('fila?situacao=aguardando_conferencia')) ?>">Conferir <strong><?= (int) $conferir ?></strong></a>
        <?php endif; ?>
    </nav>
<?php endif; ?>

<div class="painel-agora">
    <section class="painel-bloco">
        <h2><a href="<?= e(url('fila')) ?>">Carregamento</a></h2>
        <div class="tempos">
            <div>
                <span>Liberadas</span>
                <strong><?= (int) $liberadas ?></strong>
            </div>
            <div>
                <span>Na pá</span>
                <strong><?= count($carregando) ?></strong>
            </div>
        </div>
        <?php if ($carregando === []): ?>
            <p class="vazio">Nenhuma carga em andamento.</p>
        <?php else: ?>
            <div class="lista">
                <?php foreach ($carregando as $ordem): ?>
                    <article class="cartao">
                        <header>
                            <strong><?= e($ordem['ordem']) ?></strong>
                            <span class="selo status-carregando">Carregando</span>
                        </header>
                        <p class="cliente"><?= e($ordem['cliente']) ?></p>
                        <?php if ($ordem['inicio_carregamento']): ?>
                            <p class="ciclo"><time data-desde="<?= (int) strtotime((string) $ordem['inicio_carregamento']) ?>"><?= e(texto_duracao(segundos_entre((string) $ordem['inicio_carregamento'], agora_sql()))) ?></time></p>
                        <?php endif; ?>
                        <p class="meta"><?= e($ordem['pa_nome'] ?: 'Sem pá') ?> · <?= e($ordem['operador'] ?: 'Sem operador') ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="painel-bloco">
        <h2><a href="<?= e(url('lavra/fila')) ?>">Lavra</a></h2>
        <div class="tempos">
            <div>
                <span>Em ciclo</span>
                <strong><?= count($ciclos) ?></strong>
            </div>
            <div>
                <span>Livres</span>
                <strong><?= (int) $livres ?></strong>
            </div>
        </div>
        <?php if ($ciclos === []): ?>
            <p class="vazio">Nenhum caminhão em ciclo.</p>
        <?php else: ?>
            <div class="lista">
                <?php foreach ($ciclos as $ciclo): ?>
                    <?php
                    $etapa = Lavra::etapas()[$ciclo['status']] ?? null;
                    $desde = Lavra::desde($ciclo);
                    ?>
                    <article class="cartao">
                        <header>
                            <strong><?= e($ciclo['placa']) ?></strong>
                            <span class="selo status-<?= e($ciclo['status']) ?>"><?= e($etapa['rotulo'] ?? '') ?></span>
                        </header>
                        <p class="cliente"><?= e($ciclo['operador'] ?: 'Sem motorista') ?></p>
                        <?php if ($desde): ?>
                            <p class="ciclo"><time data-desde="<?= (int) strtotime($desde) ?>"><?= e(texto_duracao(segundos_entre($desde, agora_sql()))) ?></time></p>
                        <?php endif; ?>
                        <p class="meta">Desde <?= e(hora_curta($desde)) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<h2>Hoje</h2>
<div class="painel-agora">
    <section class="painel-bloco">
        <h2><a href="<?= e(url('patio/resumo')) ?>">Carregamento</a></h2>
        <div class="tempos">
            <div>
                <span>Concluídos</span>
                <strong><?= (int) $cargaDia['maquina']['total'] ?></strong>
            </div>
            <div>
                <span>Ciclo médio</span>
                <strong><?= e(texto_duracao($cargaDia['maquina']['medias']['ciclo'])) ?></strong>
            </div>
            <div>
                <span>Fila</span>
                <strong><?= e(texto_duracao($cargaDia['maquina']['medias']['fila'])) ?></strong>
            </div>
            <div>
                <span>Carga</span>
                <strong><?= e(texto_duracao($cargaDia['maquina']['medias']['carga'])) ?></strong>
            </div>
        </div>
        <?php if ($cargaDia['maquina']['grupos'] !== []): ?>
            <p class="recortes">
                <?php foreach ($cargaDia['maquina']['grupos'] as $grupo): ?>
                    <span><?= e($grupo['titulo']) ?> · <?= (int) $grupo['total'] ?></span>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
        <?php if ($cargaDia['operador']['grupos'] !== []): ?>
            <p class="recortes">
                <?php foreach ($cargaDia['operador']['grupos'] as $grupo): ?>
                    <span><?= e($grupo['titulo']) ?> · <?= (int) $grupo['total'] ?></span>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
    </section>

    <section class="painel-bloco">
        <h2><a href="<?= e(url('lavra/resumo')) ?>">Lavra</a></h2>
        <div class="tempos">
            <div>
                <span>Viagens</span>
                <strong><?= (int) $lavraDia['caminhao']['viagens'] ?></strong>
            </div>
            <div>
                <span>Ciclo médio</span>
                <strong><?= e(texto_duracao($lavraDia['caminhao']['medias']['ciclo'])) ?></strong>
            </div>
            <div>
                <span>Fila</span>
                <strong><?= e(texto_duracao($lavraDia['caminhao']['medias']['fila'])) ?></strong>
            </div>
            <div>
                <span>Carga</span>
                <strong><?= e(texto_duracao($lavraDia['caminhao']['medias']['carga'])) ?></strong>
            </div>
            <div>
                <span>Ida</span>
                <strong><?= e(texto_duracao($lavraDia['caminhao']['medias']['ida'])) ?></strong>
            </div>
            <div>
                <span>Retorno</span>
                <strong><?= e(texto_duracao($lavraDia['caminhao']['medias']['retorno'])) ?></strong>
            </div>
        </div>
        <?php if ($lavraDia['caminhao']['grupos'] !== []): ?>
            <p class="recortes">
                <?php foreach ($lavraDia['caminhao']['grupos'] as $grupo): ?>
                    <span><?= e($grupo['titulo']) ?> · <?= (int) $grupo['viagens'] ?></span>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
        <?php if ($lavraDia['motorista']['grupos'] !== []): ?>
            <p class="recortes">
                <?php foreach ($lavraDia['motorista']['grupos'] as $grupo): ?>
                    <span><?= e($grupo['titulo']) ?> · <?= (int) $grupo['viagens'] ?></span>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
    </section>
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
