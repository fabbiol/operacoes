<?php require __DIR__ . '/resumo_ordem.php'; ?>

<?php if ($ordem['status'] !== 'liberado'): ?>
    <p class="aviso erro">Essa ordem não está liberada. O início só vale com status liberado.</p>
    <p><a class="btn secundario" href="<?= e(url('patio')) ?>">Voltar ao pátio</a></p>
<?php elseif ($pas === []): ?>
    <p class="aviso erro">Nenhuma pá carregadeira ativa. O administrativo precisa cadastrar a máquina.</p>
    <p><a class="btn secundario" href="<?= e(url('patio')) ?>">Voltar ao pátio</a></p>
<?php else: ?>
    <form method="post" action="<?= e(url('carregamentos/' . $ordem['id'] . '/iniciar')) ?>" class="formulario">
        <?= csrf_field() ?>
        <fieldset class="maquinas">
            <legend>Pá carregadeira</legend>
            <?php foreach ($pas as $pa): ?>
                <label class="escolha">
                    <input type="radio" name="pa_carregadeira_id" value="<?= (int) $pa['id'] ?>" required <?= (int) $paId === (int) $pa['id'] ? 'checked' : '' ?>>
                    <span>
                        <?= e($pa['nome']) ?>
                        <?php if (isset($emUso[(int) $pa['id']])): ?>
                            <small>Em uso na <?= e($emUso[(int) $pa['id']]) ?></small>
                        <?php endif; ?>
                    </span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <label class="campo">
            <span>Operador</span>
            <input type="text" name="operador" maxlength="120" required value="<?= e($operador) ?>" autocomplete="name" placeholder="Quem está na pá">
        </label>
        <div class="botoes">
            <button type="submit" class="btn primario">Iniciar carregamento</button>
            <a class="btn secundario" href="<?= e(url('patio')) ?>">Voltar</a>
        </div>
    </form>
<?php endif; ?>
