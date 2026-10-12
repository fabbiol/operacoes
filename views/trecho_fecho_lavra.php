<?php
$fecho = $fecho ?? Lavra::fecho($ciclo);
$temposCiclo = $ciclo['tempos'] ?? Lavra::tempos($ciclo);
?>
<?php if (!empty($mostrarTitulo)): ?>
    <p class="cliente"><?= e($fecho['titulo']) ?></p>
<?php endif; ?>
<?php if (!empty($mostrarCiclo) && $fecho['ciclo']): ?>
    <p class="carga">Ciclo <?= e(texto_duracao($temposCiclo['ciclo'])) ?></p>
<?php endif; ?>
<?php if (!empty($quandoFecho)): ?>
    <p class="meta"><?= e($quandoFecho) ?></p>
<?php endif; ?>
<?php if ($fecho['caixas'] !== []): ?>
    <dl class="tempos tempos-ciclo">
        <?php foreach ($fecho['caixas'] as $caixa): ?>
            <div><span><?= e($caixa['rotulo']) ?></span><strong><?= e($caixa['valor']) ?></strong></div>
        <?php endforeach; ?>
    </dl>
<?php endif; ?>
<?php if ($fecho['frase'] !== ''): ?>
    <p class="fecho"><?= e($fecho['frase']) ?></p>
<?php endif; ?>
