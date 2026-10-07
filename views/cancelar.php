<?php require __DIR__ . '/resumo_ordem.php'; ?>

<?php if (!in_array($ordem['status'], ['aguardando_autorizacao', 'liberado', 'carregando', 'aguardando_conferencia'], true)): ?>
    <p class="aviso erro">Essa ordem já está encerrada.</p>
    <p><a class="btn secundario" href="<?= e(url('ordens/' . $ordem['id'])) ?>">Voltar</a></p>
<?php else: ?>
    <form method="post" action="<?= e(url('ordens/' . $ordem['id'] . '/cancelar')) ?>" class="formulario">
        <?= csrf_field() ?>
        <label class="campo">
            <span>Motivo</span>
            <input type="text" name="motivo" maxlength="300" value="<?= e($motivo) ?>" placeholder="Opcional">
        </label>
        <div class="botoes">
            <button type="submit" class="btn perigo">Cancelar ordem</button>
            <a class="btn secundario" href="<?= e(url('ordens/' . $ordem['id'])) ?>">Voltar</a>
        </div>
    </form>
<?php endif; ?>
