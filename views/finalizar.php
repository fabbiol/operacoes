<?php require __DIR__ . '/resumo_ordem.php'; ?>

<?php if ($ordem['status'] !== 'carregando'): ?>
    <p class="aviso erro">Essa ordem não está em carregamento.</p>
    <p><a class="btn secundario" href="<?= e(url('patio')) ?>">Voltar ao pátio</a></p>
<?php else: ?>
    <form method="post" action="<?= e(url('carregamentos/' . $ordem['id'] . '/finalizar')) ?>" class="formulario">
        <?= csrf_field() ?>
        <?php if ((int) ($ordem['fracionado'] ?? 0) === 1): ?>
            <label class="campo">
                <span>Quanto foi carregado nesta viagem? (<?= e(unidade_rotulo((string) $ordem['unidade'])) ?>)</span>
                <input type="text" name="quantidade" inputmode="decimal" maxlength="12" required value="<?= e($quantidade ?? '') ?>" placeholder="0,00" autofocus>
                <small class="dica">Saldo da nota: <?= e(formatar_quantidade((string) $ordem['quantidade_autorizada'])) ?> <?= e(unidade_rotulo((string) $ordem['unidade'])) ?>. Se sobrar, o resto fica para a próxima viagem.</small>
            </label>
        <?php endif; ?>
        <label class="campo">
            <span>Ocorrência, se houver</span>
            <textarea name="nota" rows="3" maxlength="1000" placeholder="Opcional"><?= e($nota) ?></textarea>
        </label>
        <div class="botoes">
            <button type="submit" class="btn concluir">Finalizar carregamento</button>
            <a class="btn secundario" href="<?= e(url('patio')) ?>">Voltar</a>
        </div>
    </form>
<?php endif; ?>
