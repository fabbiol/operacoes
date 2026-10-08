<span class="quem">
    <?php foreach ($fotos as $endereco): ?>
        <?php if ($endereco): ?>
            <img class="mini" src="<?= e($endereco) ?>" alt="">
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if (!empty($destaque)): ?>
        <strong><?= e($legenda) ?></strong>
    <?php else: ?>
        <span><?= e($legenda) ?></span>
    <?php endif; ?>
</span>
