<?php if (empty($minhaCarga) && empty($retroEscolhida)): ?>
    <?php require __DIR__ . '/lavra_retro.php'; ?>
<?php elseif (!empty($minhaCarga)): ?>
    <?php $ciclo = $minhaCarga; ?>
    <div class="lista">
        <article class="cartao destaque">
            <?php $cancelarCiclo = null; $ficha = $ciclo; $selo = 'Carga'; require __DIR__ . '/trecho_caminhao.php'; ?>
            <?php if (!empty($ciclo['inicio_carga'])): ?>
                <p class="ciclo"><time data-desde="<?= (int) strtotime((string) $ciclo['inicio_carga']) ?>"><?= e(texto_duracao(segundos_entre((string) $ciclo['inicio_carga'], agora_sql()))) ?></time></p>
            <?php endif; ?>
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
            <form method="post" action="<?= e(url('lavra/ciclos/' . $ciclo['id'] . '/concluir-carga')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn primario">Carga concluída</button>
            </form>
        </article>
    </div>
    <?php if (($filaCarga ?? []) !== []): ?>
        <p class="nota">Conclua esta carga antes de pegar outro caminhão.</p>
    <?php endif; ?>
<?php else: ?>
    <?php if (Auth::admin() && !empty($retroEscolhida)): ?>
        <div class="retro-escolhida">
            <span><?= e((string) $retroEscolhida['nome']) ?></span>
            <form method="post" action="<?= e(url('lavra/retro/trocar')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="link-discreto">Trocar</button>
            </form>
        </div>
    <?php endif; ?>
    <?php if (($filaCarga ?? []) === []): ?>
        <p class="vazio">Nenhum caminhão na fila.</p>
    <?php else: ?>
    <div class="lista">
        <?php foreach ($filaCarga as $ciclo): ?>
            <article class="cartao destaque">
                <?php $cancelarCiclo = null; $ficha = $ciclo; $selo = 'Na fila'; require __DIR__ . '/trecho_caminhao.php'; ?>
                <p class="cliente"><?= e($ciclo['operador'] ?: 'Sem motorista') ?></p>
                <?php if (!empty($ciclo['inicio_fila'])): ?>
                    <p class="ciclo"><time data-desde="<?= (int) strtotime((string) $ciclo['inicio_fila']) ?>"><?= e(texto_duracao(segundos_entre((string) $ciclo['inicio_fila'], agora_sql()))) ?></time></p>
                    <p class="meta">Na fila desde <?= e(hora_curta((string) $ciclo['inicio_fila'])) ?></p>
                <?php endif; ?>
                <form method="post" action="<?= e(url('lavra/ciclos/' . $ciclo['id'] . '/carga')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="retroescavadeira_id" value="<?= (int) ($retroEscolhida['id'] ?? 0) ?>">
                    <input type="hidden" name="operador" value="<?= e(Auth::usuario()) ?>">
                    <button type="submit" class="btn primario">Iniciar carga</button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>
<?php if (!empty($mostrarMovimento)): ?>
    <?php if (($voltando ?? []) !== []): ?>
        <h2>Voltando</h2>
        <div class="lista">
            <?php foreach ($voltando as $ciclo): ?>
                <article class="cartao">
                    <?php $cancelarCiclo = null; $ficha = $ciclo; $selo = 'Voltando'; require __DIR__ . '/trecho_caminhao.php'; ?>
                    <p class="cliente"><?= e($ciclo['operador'] ?: 'Sem motorista') ?></p>
                    <?php if (!empty($ciclo['inicio_retorno'])): ?>
                        <p class="ciclo"><time data-desde="<?= (int) strtotime((string) $ciclo['inicio_retorno']) ?>"><?= e(texto_duracao(segundos_entre((string) $ciclo['inicio_retorno'], agora_sql()))) ?></time></p>
                        <p class="meta">Retorno desde <?= e(hora_curta((string) $ciclo['inicio_retorno'])) ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if (($semRetorno ?? []) !== []): ?>
        <h2>Não volta</h2>
        <div class="lista">
            <?php foreach ($semRetorno as $caminhao): ?>
                <article class="cartao">
                    <?php
                    $cancelarCiclo = null;
                    $ficha = $caminhao;
                    $ficha['status'] = 'sem_retorno';
                    $selo = 'Não volta';
                    require __DIR__ . '/trecho_caminhao.php';
                    ?>
                    <p class="cliente"><?= e($caminhao['operador'] ?: 'Sem motorista') ?></p>
                    <p class="fecho"><?= !empty($caminhao['fim_carga']) ? 'Encerrou sem voltar. Entra na produção.' : 'Não carregou. Não entra na produção.' ?></p>
                    <p class="meta"><?php
                        $aviso = trim((string) ($caminhao['encerramento'] ?? ''));
                        if (!empty($caminhao['fim_ciclo'])) {
                            $hora = hora_curta((string) $caminhao['fim_ciclo']);
                            $aviso = $aviso !== '' ? $aviso . ' · às ' . $hora : 'Às ' . $hora;
                        }
                        echo e($aviso);
                    ?></p>
                    <form method="post" action="<?= e(url('lavra/ciclos/' . (int) $caminhao['ciclo_id'] . '/ciente')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn primario">Ok</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if (($chegou ?? []) !== []): ?>
        <h2>Chegou</h2>
        <div class="lista">
            <?php foreach ($chegou as $caminhao): ?>
                <article class="cartao">
                    <?php
                    $cancelarCiclo = null;
                    $ficha = $caminhao;
                    $ficha['status'] = 'concluido';
                    $selo = 'Chegou';
                    require __DIR__ . '/trecho_caminhao.php';
                    ?>
                    <?php if (!empty($caminhao['fim_ciclo'])): ?>
                        <p class="meta">Chegou às <?= e(hora_curta((string) $caminhao['fim_ciclo'])) ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
