<section class="resumo">
    <p class="olho"><?= e($ordem['ordem']) ?></p>
    <h1><?= e($ordem['cliente']) ?></h1>
    <p class="carga"><?= e(formatar_quantidade((string) $ordem['quantidade_autorizada'])) ?> <?= e(unidade_rotulo($ordem['unidade'])) ?> · <?= e($ordem['material_nome']) ?></p>
    <p class="meta">Pedido <?= e($ordem['pedido']) ?> · NF <?= e($ordem['nf']) ?><?php if ($ordem['pa_nome']): ?> · <?= e($ordem['pa_nome']) ?><?php endif; ?></p>
</section>
