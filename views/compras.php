<?php // Página pública do pedido (sem login): só o que o Compras precisa para comprar as peças. ?>
<section class="cabeca">
    <div>
        <p class="olho">Pedido de peças</p>
        <h1><?= e((string) $pedido['maquina']) ?></h1>
    </div>
</section>
<p class="meta"><?= e(quando((string) $pedido['criado_em'])) ?> · horímetro <?= e(Horimetro::texto($pedido['horimetro'] !== null ? (float) $pedido['horimetro'] : null)) ?></p>
<?php if ((string) ($pedido['observacoes'] ?? '') !== ''): ?>
    <p class="nota">Obs.: <?= e((string) $pedido['observacoes']) ?></p>
<?php endif; ?>
<div class="lista">
    <?php foreach ($pedido['itens'] as $n => $item): ?>
        <article class="cartao">
            <header>
                <strong><?= $n + 1 ?>. <?= e((string) $item['funcao']) ?></strong>
            </header>
            <p class="meta"><?= e(trim((string) $item['marca'] . ' ' . $item['codigo'])) ?> · <?= (int) $item['quantidade'] ?> un</p>
            <?php if (!empty($item['foto'])): ?>
                <img class="foto-atual" src="<?= e(url('compras/' . $pedido['codigo_publico'] . '/fotos/' . $item['id'])) ?>" alt="Foto: <?= e((string) $item['funcao']) ?>" loading="lazy" decoding="async">
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
