<section class="cabeca">
    <div>
        <p class="olho"><?= e($maquina['rotulo']) ?></p>
        <h1>Horímetro</h1>
    </div>
</section>
<p class="nota"><?= e($maquina['nome']) ?>. A leitura é o número do painel. Ela não pode ser menor que a anterior.</p>

<article class="cartao">
    <header>
        <strong><?= e(Horimetro::texto($ficha['atual'])) ?></strong>
        <?php $situacao = ['estado' => $ficha['estado'], 'rotulo' => $ficha['rotulo'], 'falta' => null, 'proxima' => null]; require __DIR__ . '/trecho_situacao.php'; ?>
    </header>
    <?php foreach ($ficha['planos'] as $plano): ?>
        <?php if ((int) $plano['ativo'] !== 1) { continue; } ?>
        <p class="meta"><?= e($plano['nome']) ?> · a cada <?= e(Horimetro::texto($plano['intervalo_h'])) ?>
            <?php $situacao = $plano['situacao']; require __DIR__ . '/trecho_situacao.php'; ?>
        </p>
    <?php endforeach; ?>
</article>

<form method="post" action="<?= e(url('horimetro')) ?>" class="formulario">
    <?= csrf_field() ?>
    <label class="campo">
        <span>Leitura</span>
        <input type="text" name="leitura" inputmode="decimal" maxlength="10" required placeholder="h" autofocus>
    </label>
    <button type="submit" class="btn primario">Lançar leitura</button>
</form>
