<?php require __DIR__ . '/resumo_ordem.php'; ?>

<?php if ($ordem['status'] !== 'carregando'): ?>
    <p class="aviso erro">Essa ordem não está em carregamento.</p>
    <p><a class="btn secundario" href="<?= e(url('patio')) ?>">Voltar ao pátio</a></p>
<?php else: ?>
    <form method="post" action="<?= e(url('carregamentos/' . $ordem['id'] . '/finalizar')) ?>" class="formulario">
        <?= csrf_field() ?>
        <label class="campo">
            <span>Ocorrência, se houver</span>
            <textarea name="nota" rows="3" maxlength="1000" placeholder="Opcional"><?= e($nota) ?></textarea>
        </label>
        <div class="botoes">
            <button type="submit" class="btn concluir">Finalizar carregamento</button>
            <a class="btn secundario" href="<?= e(url('patio')) ?>">Voltar</a>
        </div>
    </form>
<?php endif; ?>
