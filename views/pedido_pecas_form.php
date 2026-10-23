<section class="cabeca">
    <div>
        <p class="olho"><?= e((string) $maquina['nome']) ?></p>
        <h1>Novo pedido de peças</h1>
    </div>
</section>
<?php if ($erro !== null): ?>
    <p class="aviso erro"><?= e($erro) ?></p>
<?php endif; ?>
<?php if ($pecas === []): ?>
    <p class="vazio">Cadastre as peças na ficha da máquina antes de fazer o pedido.</p>
<?php else: ?>
    <form method="post" class="formulario" action="<?= e(url('cadastros/' . $chave . '/' . $maquina['id'] . '/pedidos/novo')) ?>">
        <?= csrf_field() ?>
        <fieldset class="campo">
            <legend>Peças que vão ser trocadas</legend>
            <?php foreach ($pecas as $peca): ?>
                <?php $id = (int) $peca['id']; ?>
                <div class="grade">
                    <label class="check">
                        <input type="checkbox" name="pecas[<?= $id ?>]" value="1" <?= isset($_POST['pecas'][$id]) ? 'checked' : '' ?>>
                        <span><?= e($peca['funcao']) ?> <small><?= e(trim((string) $peca['marca'] . ' ' . $peca['codigo'])) ?></small></span>
                    </label>
                    <label class="campo">
                        <span>Quantidade</span>
                        <input type="number" name="qtd[<?= $id ?>]" min="1" max="99" step="1" value="<?= e((string) ($_POST['qtd'][$id] ?? $peca['quantidade'])) ?>">
                    </label>
                </div>
            <?php endforeach; ?>
        </fieldset>
        <label class="campo">
            <span>Confirmado com</span>
            <select name="confirmado_com">
                <option value="">Ninguém</option>
                <?php foreach ($confirmadores as $nome): ?>
                    <option value="<?= e($nome) ?>" <?= (string) ($_POST['confirmado_com'] ?? '') === $nome ? 'selected' : '' ?>><?= e($nome) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="campo">
            <span>Observação (opcional)</span>
            <input type="text" name="observacoes" maxlength="500" value="<?= e((string) ($_POST['observacoes'] ?? '')) ?>">
        </label>
        <div class="botoes">
            <button type="submit" class="btn primario">Gravar pedido</button>
            <a class="btn secundario" href="<?= e(url('cadastros/' . $chave . '/' . $maquina['id'] . '#pecas')) ?>">Voltar</a>
        </div>
    </form>
<?php endif; ?>
