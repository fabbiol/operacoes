<section class="resumo">
    <p class="olho">Ciclo de lavra</p>
    <h1><?= e($ciclo['placa']) ?></h1>
    <p class="meta"><?= e(Lavra::etapas()[$ciclo['status']]['rotulo'] ?? $ciclo['status']) ?> desde <?= e(hora_curta(Lavra::desde($ciclo))) ?></p>
</section>

<?php if (!isset(Lavra::etapas()[$ciclo['status']])): ?>
    <p class="aviso erro">Esse ciclo já foi encerrado.</p>
    <p><a class="btn secundario" href="<?= e(url('lavra')) ?>">Voltar</a></p>
<?php else: ?>
    <form method="post" action="<?= e(url('lavra/ciclos/' . $ciclo['id'] . '/cancelar')) ?>" class="formulario">
        <?= csrf_field() ?>
        <label class="campo">
            <span>Motivo</span>
            <input type="text" name="motivo" maxlength="300" value="<?= e($motivo) ?>" placeholder="Opcional">
        </label>
        <a class="btn primario" href="<?= e(url('lavra')) ?>">Voltar</a>
        <button type="submit" class="acao-perigosa">Cancelar ciclo</button>
    </form>
<?php endif; ?>
