<section class="cabeca">
    <div>
        <p class="olho">Ciclo de carregamento</p>
        <h1>Tempos do dia</h1>
    </div>
    <a class="btn secundario" href="<?= e(url('patio')) ?>">Voltar ao pátio</a>
</section>

<form class="busca" method="get" action="<?= e(url('patio/resumo')) ?>">
    <input type="hidden" name="visao" value="<?= e($visao) ?>">
    <label class="campo" style="flex:1">
        <span>Dia</span>
        <input type="date" name="dia" value="<?= e($dia) ?>" required>
    </label>
    <button type="submit" class="btn secundario">Ver</button>
</form>

<nav class="filtros" aria-label="Resumo">
    <a class="<?= $visao === 'maquina' ? 'ativo' : '' ?>" href="<?= e(url('patio/resumo?dia=' . rawurlencode($dia) . '&visao=maquina')) ?>">Por máquina</a>
    <a class="<?= $visao === 'operador' ? 'ativo' : '' ?>" href="<?= e(url('patio/resumo?dia=' . rawurlencode($dia) . '&visao=operador')) ?>">Por operador</a>
</nav>

<p class="nota">A fila vai da liberação até o início da carga. A carga vai até a finalização. O ciclo é a soma das duas.</p>

<div class="tempos">
    <div>
        <span>Carregamentos</span>
        <strong><?= (int) $total ?></strong>
    </div>
    <div>
        <span>Ciclo médio</span>
        <strong><?= e(texto_duracao($medias['ciclo'])) ?></strong>
    </div>
    <div>
        <span>Fila</span>
        <strong><?= e(texto_duracao($medias['fila'])) ?></strong>
    </div>
    <div>
        <span>Carga</span>
        <strong><?= e(texto_duracao($medias['carga'])) ?></strong>
    </div>
</div>

<?php if ($grupos === []): ?>
    <p class="vazio"><?= $visao === 'operador' ? 'Nenhum carregamento de operador neste dia.' : 'Nenhum carregamento concluído neste dia.' ?></p>
<?php endif; ?>

<?php foreach ($grupos as $grupo): ?>
    <section>
        <h2><?= e($grupo['titulo']) ?> · <?= (int) $grupo['total'] ?> <?= $grupo['total'] === 1 ? 'carregamento' : 'carregamentos' ?></h2>
        <div class="tempos">
            <div>
                <span>Ciclo médio</span>
                <strong><?= e(texto_duracao($grupo['medias']['ciclo'])) ?></strong>
            </div>
            <div>
                <span>Fila</span>
                <strong><?= e(texto_duracao($grupo['medias']['fila'])) ?></strong>
            </div>
            <div>
                <span>Carga</span>
                <strong><?= e(texto_duracao($grupo['medias']['carga'])) ?></strong>
            </div>
        </div>
        <div class="lista">
            <?php foreach ($grupo['linhas'] as $ordem): ?>
                <?php $quem = $visao === 'operador' ? ($ordem['pa_nome'] ?: 'Sem pá') : ($ordem['operador'] ?: 'Sem identificação'); ?>
                <article class="cartao">
                    <header>
                        <strong><?= e($quem) ?></strong>
                        <span class="selo status-concluido"><?= e(texto_duracao($ordem['tempos']['ciclo'])) ?></span>
                    </header>
                    <p class="meta"><?= e($ordem['ordem']) ?> · <?= e($ordem['cliente']) ?></p>
                    <p class="meta"><?= e(hora_curta($ordem['autorizado_em'] ?: $ordem['inicio_carregamento'])) ?> → <?= e(hora_curta($ordem['fim_carregamento'])) ?></p>
                    <dl class="tempos tempos-ciclo">
                        <div><span>Fila</span><strong><?= e(texto_duracao($ordem['tempos']['fila'])) ?></strong></div>
                        <div><span>Carga</span><strong><?= e(texto_duracao($ordem['tempos']['carga'])) ?></strong></div>
                    </dl>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>
