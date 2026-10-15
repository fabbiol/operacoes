<?php require __DIR__ . '/resumo_ordem.php'; ?>

<?php if ($ordem['status'] !== 'liberado'): ?>
    <p class="aviso erro">Essa ordem não está liberada. O início só vale com status liberado.</p>
    <p><a class="btn secundario" href="<?= e(url('patio')) ?>">Voltar ao pátio</a></p>
<?php elseif (is_array($retroPatio ?? null)): ?>
    <?php if (!empty($erro)): ?><p class="aviso erro"><?= e($erro) ?></p><?php endif; ?>
    <p class="nota">Nenhuma pá livre. A carga segue com a escavadeira do menu.</p>
    <form method="post" action="<?= e(url('carregamentos/' . $ordem['id'] . '/iniciar')) ?>" class="formulario">
        <?= csrf_field() ?>
        <input type="hidden" name="retroescavadeira_id" value="<?= (int) $retroPatio['id'] ?>">
        <p class="cliente"><?= e((string) $retroPatio['nome']) ?></p>
        <?php if (($retroPatio['detalhe'] ?? '') !== ''): ?>
            <p class="meta"><?= e((string) $retroPatio['detalhe']) ?></p>
        <?php endif; ?>
        <?php require __DIR__ . '/trecho_operador.php'; ?>
        <div class="botoes">
            <button type="submit" class="btn primario">Iniciar carregamento</button>
            <a class="btn secundario" href="<?= e(url('patio')) ?>">Voltar</a>
        </div>
    </form>
<?php elseif ($paFixa === false): ?>
    <p class="aviso erro"><?= !empty($semPa)
        ? 'Nenhuma pá livre. Troque para uma escavadeira hidráulica para carregar no pátio.'
        : 'A máquina do menu não é uma pá. Troque para uma pá carregadeira para carregar no pátio.' ?></p>
    <p><a class="btn secundario" href="<?= e(url('maquina')) ?>">Trocar</a></p>
<?php elseif (is_array($paFixa)): ?>
    <form method="post" action="<?= e(url('carregamentos/' . $ordem['id'] . '/iniciar')) ?>" class="formulario">
        <?= csrf_field() ?>
        <input type="hidden" name="pa_carregadeira_id" value="<?= (int) $paFixa['id'] ?>">
        <p class="nota">Pá carregadeira</p>
        <p class="cliente"><?= e((string) $paFixa['nome']) ?></p>
        <?php if (($paFixa['detalhe'] ?? '') !== ''): ?>
            <p class="meta"><?= e((string) $paFixa['detalhe']) ?></p>
        <?php endif; ?>
        <?php require __DIR__ . '/trecho_operador.php'; ?>
        <div class="botoes">
            <button type="submit" class="btn primario">Iniciar carregamento</button>
            <a class="btn secundario" href="<?= e(url('patio')) ?>">Voltar</a>
        </div>
    </form>
<?php elseif ($pas === [] && ($retros ?? []) !== []): ?>
    <?php if (!empty($erro)): ?><p class="aviso erro"><?= e($erro) ?></p><?php endif; ?>
    <form method="post" action="<?= e(url('carregamentos/' . $ordem['id'] . '/iniciar')) ?>" class="formulario">
        <?= csrf_field() ?>
        <p class="nota">Nenhuma pá livre. A carga segue com a escavadeira hidráulica.</p>
        <fieldset class="maquinas">
            <legend>Escavadeira hidráulica</legend>
            <?php foreach ($retros as $retro): ?>
                <?php
                $detalhe = [];
                foreach (['marca', 'modelo', 'ano'] as $campoFicha) {
                    $valorFicha = trim((string) ($retro[$campoFicha] ?? ''));
                    if ($valorFicha !== '') {
                        $detalhe[] = $valorFicha;
                    }
                }
                ?>
                <label class="escolha escolha-maquina">
                    <input type="radio" name="retroescavadeira_id" value="<?= (int) $retro['id'] ?>" required>
                    <span>
                        <?php if (!empty($retro['foto'])): ?>
                            <img class="foto-equipamento" src="<?= e(url('cadastros/retros/' . $retro['id'] . '/foto')) ?>" alt="" loading="lazy" decoding="async">
                        <?php endif; ?>
                        <span class="escolha-texto">
                            <?= e($retro['nome']) ?>
                            <?php if ($detalhe !== []): ?>
                                <small><?= e(implode(' · ', $detalhe)) ?></small>
                            <?php endif; ?>
                        </span>
                    </span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <?php require __DIR__ . '/trecho_operador.php'; ?>
        <div class="botoes">
            <button type="submit" class="btn primario">Iniciar carregamento</button>
            <a class="btn secundario" href="<?= e(url('patio')) ?>">Voltar</a>
        </div>
    </form>
<?php elseif ($pas === []): ?>
    <p class="aviso erro">Nenhuma pá carregadeira ativa. O administrativo precisa cadastrar a máquina.</p>
    <p><a class="btn secundario" href="<?= e(url('patio')) ?>">Voltar ao pátio</a></p>
<?php else: ?>
    <form method="post" action="<?= e(url('carregamentos/' . $ordem['id'] . '/iniciar')) ?>" class="formulario">
        <?= csrf_field() ?>
        <fieldset class="maquinas">
            <legend>Pá carregadeira</legend>
            <?php foreach ($pas as $pa): ?>
                <?php
                $detalhe = [];
                foreach (['marca', 'modelo', 'ano'] as $campoFicha) {
                    $valorFicha = trim((string) ($pa[$campoFicha] ?? ''));
                    if ($valorFicha !== '') {
                        $detalhe[] = $valorFicha;
                    }
                }
                ?>
                <label class="escolha escolha-maquina">
                    <input type="radio" name="pa_carregadeira_id" value="<?= (int) $pa['id'] ?>" required <?= (int) $paId === (int) $pa['id'] ? 'checked' : '' ?>>
                    <span>
                        <?php if (!empty($pa['foto'])): ?>
                            <img class="foto-equipamento" src="<?= e(url('cadastros/pas/' . $pa['id'] . '/foto')) ?>" alt="" loading="lazy" decoding="async">
                        <?php endif; ?>
                        <span class="escolha-texto">
                            <?= e($pa['nome']) ?>
                            <?php if ($detalhe !== []): ?>
                                <small><?= e(implode(' · ', $detalhe)) ?></small>
                            <?php endif; ?>
                            <?php if (isset($emUso[(int) $pa['id']])): ?>
                                <small>Em uso na <?= e($emUso[(int) $pa['id']]) ?></small>
                            <?php endif; ?>
                        </span>
                    </span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <?php require __DIR__ . '/trecho_operador.php'; ?>
        <div class="botoes">
            <button type="submit" class="btn primario">Iniciar carregamento</button>
            <a class="btn secundario" href="<?= e(url('patio')) ?>">Voltar</a>
        </div>
    </form>
<?php endif; ?>
