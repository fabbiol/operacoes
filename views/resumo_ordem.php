<section class="resumo">
    <p class="olho"><?= e($ordem['ordem']) ?></p>
    <h1><?= e($ordem['cliente']) ?></h1>
    <p class="carga"><?= (int) ($ordem['fracionado'] ?? 0) === 1 ? 'Saldo ' : '' ?><?= e(formatar_quantidade((string) $ordem['quantidade_autorizada'])) ?> <?= e(unidade_rotulo($ordem['unidade'])) ?> · <?= e($ordem['material_nome']) ?></p>
    <?php if ((int) ($ordem['fracionado'] ?? 0) === 1): ?>
        <p class="meta"><span class="selo fracionado">Fracionado · viagem <?= (int) ($ordem['viagem'] ?? 1) ?></span> O cliente leva só uma parte: ao finalizar, informe quanto foi carregado.</p>
    <?php endif; ?>
    <p class="meta">Pedido <?= e($ordem['pedido']) ?> · NF <?= e($ordem['nf']) ?><?php if ($ordem['pa_nome']): ?> · <?= e($ordem['pa_nome']) ?><?php endif; ?></p>
</section>
