<?php
$detalhe = [];
foreach (['marca', 'modelo', 'ano'] as $campoFicha) {
    $valorFicha = trim((string) ($ficha[$campoFicha] ?? ''));
    if ($valorFicha !== '') {
        $detalhe[] = $valorFicha;
    }
}
$fotoId = (int) ($ficha['caminhao_id'] ?? $ficha['id'] ?? 0);
$placaFicha = (string) ($ficha['placa'] ?? $ficha['nome'] ?? '');
?>
<?php if (!empty($ficha['foto']) && $fotoId > 0): ?>
    <img class="foto-equipamento" src="<?= e(url('cadastros/caminhoes/' . $fotoId . '/foto')) ?>" alt="">
<?php endif; ?>
<header>
    <div class="caminhao-nome">
        <strong><?= e($placaFicha) ?></strong>
        <?php if ($detalhe !== []): ?>
            <p class="meta"><?= e(implode(' · ', $detalhe)) ?></p>
        <?php endif; ?>
        <?php if (!empty($cancelarCiclo)): ?>
            <a class="link-discreto" href="<?= e($cancelarCiclo) ?>">Cancelar ciclo</a>
        <?php endif; ?>
    </div>
    <?php if (!empty($selo)): ?>
        <span class="selo status-<?= e((string) ($ficha['status'] ?? '')) ?>"><?= e($selo) ?></span>
    <?php endif; ?>
</header>
