<section class="resumo">
    <p class="olho">Ida ao britador</p>
    <h1><?= e($ciclo['placa']) ?></h1>
    <p class="meta">Desde <?= e(hora_curta(Lavra::desde($ciclo))) ?></p>
</section>

<?php if ($ciclo['status'] !== 'em_viagem'): ?>
    <p class="aviso erro">O encerramento sem retorno só vale na ida ao britador.</p>
    <p><a class="btn secundario" href="<?= e(url('lavra')) ?>">Voltar</a></p>
<?php else: ?>
    <?php if (!empty($erro)): ?><p class="aviso erro"><?= e($erro) ?></p><?php endif; ?>
    <p class="nota">A carga já foi para o britador. A viagem entra na produção e o retorno fica em branco.</p>
    <form method="post" action="<?= e(url('lavra/ciclos/' . $ciclo['id'] . '/encerrar')) ?>" class="formulario">
        <?= csrf_field() ?>
        <fieldset class="maquinas">
            <legend>Motivo</legend>
            <?php foreach (Lavra::motivosEncerramento() as $codigo => $rotulo): ?>
                <label class="escolha">
                    <input type="radio" name="motivo" value="<?= e($codigo) ?>" required <?= $motivo === $codigo ? 'checked' : '' ?>>
                    <span><?= e($rotulo) ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <label class="campo">
            <span>Outro</span>
            <input type="text" name="outro" maxlength="120" value="<?= e($outro) ?>" placeholder="Obrigatório quando o motivo é outro">
        </label>
        <div class="botoes">
            <button type="submit" class="btn encerrar-viagem">Encerrar viagem</button>
            <a class="btn secundario" href="<?= e(url('lavra')) ?>">Voltar</a>
        </div>
    </form>
<?php endif; ?>
