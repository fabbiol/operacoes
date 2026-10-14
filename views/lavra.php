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
<p class="nota">Na garagem, Chegou na lavra abre a viagem. No britador, o motorista volta para a lavra ou vai para a garagem. Encerrar viagem interrompe o ciclo, e o horímetro final entra no Sair.</p>

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
                            <input type="hidden" name="etapa" value="<?= e($ciclo['status']) ?>">
                            <button type="submit" class="btn primario">Carga concluída</button>
                        </form>
                    <?php endif; ?>
                <?php elseif (in_array($ciclo['status'], ['no_britador', 'retornando'], true)): ?>
                    <form method="post" action="<?= e(url('lavra/ciclos/' . $ciclo['id'] . '/avancar')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="etapa" value="<?= e($ciclo['status']) ?>">
                        <button type="submit" class="btn primario">Chegou na lavra</button>
                    </form>
                    <form method="post" action="<?= e(url('lavra/ciclos/' . $ciclo['id'] . '/garagem')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="etapa" value="<?= e($ciclo['status']) ?>">
                        <button type="submit" class="btn secundario">Garagem</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= e(url('lavra/ciclos/' . $ciclo['id'] . '/avancar')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="etapa" value="<?= e($ciclo['status']) ?>">
                        <button type="submit" class="btn primario"><?= e($etapa['acao']) ?></button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($livres !== []): ?>
    <h2><?= !empty($garagem) ? 'Garagem' : 'Disponíveis' ?></h2>
    <div class="lista">
        <?php foreach ($livres as $caminhao): ?>
            <article class="cartao">
                <?php
                $cancelarCiclo = null;
                $ficha = $caminhao;
                if (!empty($garagem)) {
                    $ficha['status'] = 'garagem';
                }
                $selo = !empty($garagem) ? 'Na garagem' : '';
                require __DIR__ . '/trecho_caminhao.php';
                ?>
                <?php if (!empty($garagem) && !empty($precisaSoltar)): ?>
                    <p class="nota">A viagem encerrou. Lance o horímetro ao sair antes de outra viagem.</p>
                    <form method="post" action="<?= e(url('sair')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn primario">Sair e lançar horímetro</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= e(url('lavra/caminhoes/' . $caminhao['id'] . '/fila')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn primario">Chegou na lavra</button>
                    </form>
                <?php endif; ?>
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
