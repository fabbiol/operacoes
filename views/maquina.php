<section class="cabeca">
    <div>
        <p class="olho">Depois do login</p>
        <h1><?= !empty($soltar) ? 'Soltar máquina' : 'Máquina' ?></h1>
    </div>
</section>

<?php if (!empty($erro)): ?>
    <p class="aviso erro"><?= e($erro) ?></p>
<?php endif; ?>

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
<?php elseif (!empty($soltar) && !empty($atual)): ?>
    <?php $entrada = horimetro_entrada(); ?>
    <p class="nota">Informe o horímetro do painel para soltar <?= e((string) $atual['nome']) ?>.</p>
    <form method="post" action="<?= e(url(($destino ?? 'maquina') === 'sair' ? 'sair' : 'maquina')) ?>" class="formulario">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="soltar">
        <p class="meta">Horímetro atual <?= e(Horimetro::texto(Horimetro::atual($atual['tipo'], (int) $atual['id']))) ?><?php if ($entrada !== null): ?> · entrou com <?= e(Horimetro::texto($entrada)) ?><?php endif; ?></p>
        <label class="campo">
            <span>Horímetro ao soltar</span>
            <input type="text" name="leitura" inputmode="decimal" maxlength="10" required placeholder="h" autofocus>
        </label>
        <button type="submit" class="btn primario"><?= ($destino ?? '') === 'sair' ? 'Soltar e sair' : 'Soltar' ?></button>
    </form>
<?php elseif ($maquinas === []): ?>
    <p class="vazio">Nenhuma máquina livre agora.</p>
<?php else: ?>
    <?php if (count($maquinas) === 1): ?>
        <p class="nota">Só esta está livre. Informe o horímetro do painel para assumir.</p>
    <?php else: ?>
        <p class="nota">Escolha a máquina deste turno e informe o horímetro do painel.</p>
    <?php endif; ?>
    <form method="post" action="<?= e(url('maquina')) ?>" class="formulario">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="assumir">
        <fieldset class="maquinas maquinas-duas">
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
                            <img class="foto-equipamento" src="<?= e((string) $maquina['foto']) ?>" alt="" loading="lazy" decoding="async">
                        <?php endif; ?>
                        <span class="escolha-texto">
                            <?= e((string) $maquina['nome']) ?>
                            <small><?= e((string) $maquina['rotulo']) ?><?php if (($maquina['detalhe'] ?? '') !== ''): ?> · <?= e((string) $maquina['detalhe']) ?><?php endif; ?> · <?= e(Horimetro::texto(Horimetro::atual($maquina['tipo'], (int) $maquina['id']))) ?></small>
                        </span>
                    </span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <label class="campo">
            <span>Horímetro agora</span>
            <input type="text" name="leitura" inputmode="decimal" maxlength="10" required placeholder="h">
        </label>
        <button type="submit" class="btn primario">Confirmar</button>
    </form>
<?php endif; ?>

<?php if (($comOutros ?? []) !== []): ?>
    <section class="bloco com-outros">
        <h2>Com outras pessoas</h2>
        <ul class="cadastro">
            <?php foreach ($comOutros as $outra): ?>
                <li>
                    <span class="cadastro-texto">
                        <strong><?= e((string) $outra['nome']) ?></strong>
                        <small>Com <?= e((string) $outra['usuario']) ?> desde <?= e(quando((string) $outra['desde'])) ?></small>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="nota">Se a pessoa já foi embora sem soltar, o administrativo libera a máquina na ficha dela.</p>
    </section>
<?php endif; ?>
