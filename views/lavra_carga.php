<section class="cabeca">
    <div>
        <p class="olho">Operador da lavra</p>
        <h1>Carga na lavra</h1>
    </div>
</section>
<?php if (!empty($foraDaMaquina)): ?>
    <p class="nota">A máquina do menu é a <?= e((string) $foraDaMaquina['nome']) ?>. Troque para uma retroescavadeira para carregar na lavra.</p>
    <p><a class="btn secundario" href="<?= e(url('maquina')) ?>">Trocar</a></p>
<?php else: ?>
    <div id="fluxo" data-url="<?= e($fluxoUrl) ?>" data-marca="<?= e($fluxoMarca) ?>">
    <?php if (!empty($minhaCarga) || !empty($retroEscolhida)): ?>
        <p class="nota">Os caminhões na fila. Conclua quando o caminhão sair para o britador.</p>
    <?php endif; ?>
    <?php require __DIR__ . '/trecho_carga_lavra.php'; ?>
    </div>
<?php endif; ?>
