<section class="cabeca">
    <div>
        <p class="olho">Administrativo</p>
        <h1>Cadastros</h1>
    </div>
</section>
<div class="lista hub">
    <?php foreach ($cartoes as $cartao): ?>
        <a class="cartao" href="<?= e(url('cadastros/' . $cartao['chave'])) ?>">
            <header>
                <strong><?= e($cartao['titulo']) ?></strong>
                <span class="selo"><?= (int) $cartao['total'] ?></span>
            </header>
            <p class="meta">Toque para incluir ou desativar.</p>
        </a>
    <?php endforeach; ?>
</div>
