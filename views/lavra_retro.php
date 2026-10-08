<?php if (($retros ?? []) === []): ?>
    <p class="aviso erro">Nenhuma retroescavadeira ativa. O administrativo precisa cadastrar a máquina.</p>
<?php else: ?>
    <p class="nota">Escolha a retroescavadeira. Depois disso, a fila mostra só o caminhão.</p>
    <form method="post" action="<?= e(url('lavra/retro')) ?>" class="formulario">
        <?= csrf_field() ?>
        <fieldset class="maquinas">
            <legend>Retroescavadeira</legend>
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
                            <img class="foto-equipamento" src="<?= e(url('cadastros/retros/' . $retro['id'] . '/foto')) ?>" alt="">
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
        <button type="submit" class="btn primario">Usar esta retro</button>
    </form>
<?php endif; ?>
