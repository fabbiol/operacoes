<section class="entrada">
    <h1><?= e($titulo) ?></h1>
    <p><?= e($mensagem) ?></p>
    <?php if (Auth::ok()): ?>
        <p><a class="btn secundario" href="<?= e(url('/')) ?>">Voltar ao início</a></p>
    <?php endif; ?>
</section>
