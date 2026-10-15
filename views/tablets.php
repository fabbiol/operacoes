<section class="cabeca">
    <div>
        <p class="olho">Acesso</p>
        <h1>Tablets</h1>
    </div>
    <a class="btn secundario" href="<?= e(url('usuarios')) ?>">Pessoas</a>
</section>
<p class="nota">Só os tablets autorizados mostram as fotos da equipe na entrada e deixam entrar com um toque. Em qualquer outro aparelho, só entra quem tem usuário e senha.</p>

<?php if (!empty($este)): ?>
    <p class="aviso">Este aparelho está autorizado como <?= e((string) $este['nome']) ?>.</p>
<?php else: ?>
    <section class="bloco">
        <h2>Autorizar este aparelho</h2>
        <p class="dica">Abra esta tela no próprio tablet, entrando com o seu usuário e senha. Depois de autorizar, toque em Sair: a entrada passa a mostrar as fotos da equipe.</p>
        <form method="post" action="<?= e(url('tablets')) ?>" class="formulario">
            <?= csrf_field() ?>
            <label class="campo">
                <span>Nome deste tablet</span>
                <input type="text" name="nome" maxlength="60" required value="<?= e($nome ?? '') ?>" placeholder="Ex.: Tablet da escavadeira 1">
            </label>
            <button type="submit" class="btn primario">Autorizar este aparelho</button>
        </form>
    </section>
<?php endif; ?>

<section class="bloco">
    <h2>Autorizados</h2>
    <?php if (($tablets ?? []) === []): ?>
        <p class="vazio">Nenhum tablet autorizado ainda.</p>
    <?php else: ?>
        <ul class="cadastro">
            <?php foreach ($tablets as $item): ?>
                <li class="<?= (int) $item['ativo'] === 1 ? '' : 'inativo' ?>">
                    <span class="cadastro-texto">
                        <strong><?= e((string) $item['nome']) ?><?= !empty($este) && (int) $este['id'] === (int) $item['id'] ? ' · este aparelho' : '' ?></strong>
                        <small>
                            Autorizado por <?= e((string) $item['autorizado_por']) ?> em <?= e(quando((string) $item['criado_em'])) ?>
                            · <?= !empty($item['ultimo_uso']) ? 'última entrada ' . e(quando((string) $item['ultimo_uso'])) : 'ainda sem entrada' ?>
                            <?php if ((int) $item['ativo'] !== 1): ?>
                                · revogado em <?= e(quando((string) $item['revogado_em'])) ?>
                            <?php endif; ?>
                        </small>
                    </span>
                    <?php if ((int) $item['ativo'] === 1): ?>
                        <form method="post" action="<?= e(url('tablets/' . (int) $item['id'] . '/revogar')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn perigo">Revogar</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
