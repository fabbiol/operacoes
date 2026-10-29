<section class="resumo">
    <p class="olho"><?= e($ordem['ordem']) ?></p>
    <h1><?= e($ordem['cliente']) ?></h1>
    <p class="carga"><?= (int) ($ordem['fracionado'] ?? 0) === 1 ? 'Saldo ' : '' ?><?= e(formatar_quantidade((string) $ordem['quantidade_autorizada'])) ?> <?= e(unidade_rotulo($ordem['unidade'])) ?> · <?= e($ordem['material_nome']) ?></p>
    <?php foreach ((function_exists('outros_materiais') ? outros_materiais($ordem) : []) as $outro): ?>
        <p class="carga">+ <?= e(formatar_quantidade($outro['quantidade'])) ?> <?= e(unidade_rotulo($ordem['unidade'])) ?> · <?= e($outro['material']) ?></p>
    <?php endforeach; ?>
    <?php if ((int) ($ordem['fracionado'] ?? 0) === 1): ?>
        <p class="meta"><span class="selo fracionado">Fracionado · viagem <?= (int) ($ordem['viagem'] ?? 1) ?></span> O cliente leva só uma parte: ao finalizar, informe quanto foi carregado.</p>
    <?php endif; ?>
    <p class="meta">Pedido <?= e($ordem['pedido']) ?> · <?= e(function_exists('nf_texto') ? nf_texto($ordem) : 'NF ' . $ordem['nf']) ?><?php if ($ordem['pa_nome']): ?> · <?= e($ordem['pa_nome']) ?><?php endif; ?></p>
    <?php if (trim((string) ($ordem['observacoes'] ?? '')) !== ''): ?>
        <div class="obs-ordem">
            <strong>Observações</strong>
            <p class="texto"><?= e((string) $ordem['observacoes']) ?></p>
        </div>
    <?php endif; ?>
</section>
