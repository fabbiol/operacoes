<section class="cabeca">
    <div>
        <p class="olho">Depois do login</p>
        <h1>Máquina</h1>
    </div>
</section>

<?php if (!empty($travada) && !empty($atual)): ?>
    <p class="nota">Conclua o trabalho desta máquina antes de trocar.</p>
    <article class="cartao">
        <?php if (!empty($atual['foto'])): ?>
            <img class="foto-equipamento" src="<?= e((string) $atual['foto']) ?>" alt="">
        <?php endif; ?>
        <header>
            <strong><?= e((string) $atual['nome']) ?></strong>
        </header>
        <?php if (($atual['detalhe'] ?? '') !== ''): ?>
            <p class="meta"><?= e((string) $atual['detalhe']) ?></p>
        <?php endif; ?>
        <p class="meta"><?= e((string) $atual['rotulo']) ?></p>
    </article>
<?php elseif ($maquinas === []): ?>
    <p class="vazio">Nenhuma máquina livre agora.</p>
<?php else: ?>
    <?php if (count($maquinas) === 1): ?>
        <p class="nota">Só esta está livre.</p>
    <?php else: ?>
        <p class="nota">Escolha a máquina deste turno. Ela fica no menu, com a opção de trocar.</p>
    <?php endif; ?>
    <form method="post" action="<?= e(url('maquina')) ?>" class="formulario">
        <?= csrf_field() ?>
        <fieldset class="maquinas">
            <legend>Máquina</legend>
            <?php foreach ($maquinas as $maquina): ?>
                <?php
                $valor = $maquina['tipo'] . ':' . (int) $maquina['id'];
                $marcada = (!empty($atual) && $atual['tipo'] === $maquina['tipo'] && (int) $atual['id'] === (int) $maquina['id'])
                    || count($maquinas) === 1;
                ?>
                <label class="escolha escolha-maquina">
                    <input type="radio" name="maquina" value="<?= e($valor) ?>" required <?= $marcada ? 'checked' : '' ?>>
                    <span>
                        <?php if (!empty($maquina['foto'])): ?>
                            <img class="foto-equipamento" src="<?= e((string) $maquina['foto']) ?>" alt="">
                        <?php endif; ?>
                        <span class="escolha-texto">
                            <?= e((string) $maquina['nome']) ?>
                            <small><?= e((string) $maquina['rotulo']) ?><?php if (($maquina['detalhe'] ?? '') !== ''): ?> · <?= e((string) $maquina['detalhe']) ?><?php endif; ?></small>
                        </span>
                    </span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <button type="submit" class="btn primario">Confirmar</button>
    </form>
<?php endif; ?>
