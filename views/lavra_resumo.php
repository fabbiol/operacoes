<section class="cabeca">
    <div>
        <p class="olho">Ciclo de lavra</p>
        <h1>Tempos do dia</h1>
    </div>
    <a class="btn secundario" href="<?= e(url('lavra')) ?>">Voltar aos caminhões</a>
</section>

<form class="busca" method="get" action="<?= e(url('lavra/resumo')) ?>">
    <input type="hidden" name="visao" value="<?= e($visao) ?>">
    <label class="campo" style="flex:1">
        <span>Dia</span>
        <input type="date" name="dia" value="<?= e($dia) ?>" required>
    </label>
    <button type="submit" class="btn secundario">Ver</button>
</form>

<nav class="filtros" aria-label="Resumo">
    <a class="<?= $visao === 'caminhao' ? 'ativo' : '' ?>" href="<?= e(url('lavra/resumo?dia=' . rawurlencode($dia) . '&visao=caminhao')) ?>">Por caminhão</a>
    <a class="<?= $visao === 'motorista' ? 'ativo' : '' ?>" href="<?= e(url('lavra/resumo?dia=' . rawurlencode($dia) . '&visao=motorista')) ?>">Por motorista</a>
</nav>

<div class="tempos">
    <div>
        <span>Viagens</span>
        <strong><?= (int) $viagens ?></strong>
    </div>
    <div>
        <span>Produção</span>
        <strong><?= e(texto_capacidade($producao['toneladas'] ?? null) ?: '—') ?></strong>
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
    <div>
        <span>Estrada</span>
        <strong><?= e(texto_duracao($medias['ida'])) ?></strong>
    </div>
    <div>
        <span>Volta</span>
        <strong><?= e(texto_duracao($medias['retorno'])) ?></strong>
    </div>
</div>
<?php $faltando = $producao['faltando'] ?? []; require __DIR__ . '/trecho_sem_capacidade.php'; ?>

<?php if ($grupos === []): ?>
    <p class="vazio"><?= $visao === 'motorista' ? 'Nenhuma viagem de motorista neste dia.' : 'Nenhuma viagem concluída neste dia.' ?></p>
<?php endif; ?>

<?php foreach ($grupos as $grupo): ?>
    <section>
        <h2><?php
            $amostra = $grupo['linhas'][0] ?? [];
            $fotos = [$visao === 'motorista' ? url_foto_pessoa($amostra) : url_foto_caminhao($amostra)];
            $legenda = $grupo['titulo'] . ' · ' . (int) $grupo['viagens'] . ' ' . ($grupo['viagens'] === 1 ? 'viagem' : 'viagens');
            $producaoGrupo = texto_capacidade($grupo['producao']['toneladas'] ?? null);
            if ($producaoGrupo !== '') {
                $legenda .= ' · ' . $producaoGrupo;
            }
            $destaque = true;
            require __DIR__ . '/trecho_quem.php';
        ?></h2>
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
            <div>
                <span>Estrada</span>
                <strong><?= e(texto_duracao($grupo['medias']['ida'])) ?></strong>
            </div>
            <div>
                <span>Volta</span>
                <strong><?= e(texto_duracao($grupo['medias']['retorno'])) ?></strong>
            </div>
        </div>
        <div class="lista">
            <?php foreach ($grupo['linhas'] as $ciclo): ?>
                <article class="cartao">
                    <header>
                        <strong><?= e($visao === 'motorista' ? $ciclo['placa'] : ((string) $ciclo['operador'] !== '' ? $ciclo['operador'] : 'Sem identificação')) ?></strong>
                        <span class="selo status-concluido"><?= e(texto_duracao($ciclo['tempos']['ciclo'])) ?></span>
                    </header>
                    <p class="meta"><?= e(hora_curta($ciclo['inicio_fila'])) ?> → <?= e(hora_curta($ciclo['fim_ciclo'])) ?></p>
                    <dl class="tempos tempos-ciclo">
                        <div><span>Fila</span><strong><?= e(texto_duracao($ciclo['tempos']['fila'])) ?></strong></div>
                        <div><span>Carga</span><strong><?= e(texto_duracao($ciclo['tempos']['carga'])) ?></strong></div>
                        <div><span>Estrada</span><strong><?= e(texto_duracao($ciclo['tempos']['ida'])) ?></strong></div>
                        <div><span>Volta</span><strong><?= e(texto_duracao($ciclo['tempos']['retorno'])) ?></strong></div>
                    </dl>
                    <?php if (trim((string) ($ciclo['encerramento'] ?? '')) !== ''): ?>
                        <p class="meta"><?= !empty($ciclo['fim_carga']) ? 'Sem retorno' : 'Encerrada antes da carga' ?> · <?= e((string) $ciclo['encerramento']) ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>
