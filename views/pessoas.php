<section class="cabeca">
    <div>
        <p class="olho">Acesso</p>
        <h1>Pessoas</h1>
    </div>
    <div class="acoes">
        <a class="btn secundario" href="<?= e(url('tablets')) ?>">Tablets</a>
        <a class="btn primario" href="<?= e(url('usuarios/novo')) ?>">Nova</a>
    </div>
</section>

<?php if ($pessoas === []): ?>
    <p class="vazio">Nenhuma pessoa cadastrada.</p>
<?php else: ?>
    <ul class="cadastro pessoas">
        <?php foreach ($pessoas as $pessoa): ?>
            <li class="<?= (int) $pessoa['ativo'] === 1 ? '' : 'inativo' ?>">
                <a href="<?= e(url('usuarios/' . $pessoa['id'])) ?>">
                    <?php if (!empty($pessoa['foto'])): ?>
                        <img class="pessoa-foto" src="<?= e(url('usuarios/' . $pessoa['id'] . '/foto')) ?>" alt="">
                    <?php endif; ?>
                    <?php
                    $completo = trim((string) ($pessoa['nome_completo'] ?? ''));
                    $usuario = (string) $pessoa['nome'];
                    ?>
                    <span class="cadastro-texto">
                        <strong><?= e($completo !== '' ? $completo : $usuario) ?></strong>
                        <small><?php if ($completo !== '' && $completo !== $usuario): ?><?= e($usuario) ?> · <?php endif; ?><?= e(Pessoas::rotulo($pessoa)) ?></small>
                    </span>
                    <?php if ((int) $pessoa['ativo'] !== 1): ?><span class="selo">Inativo</span><?php endif; ?>
                </a>
                <?php if ((int) $pessoa['administrador'] !== 1): ?>
                    <form method="post" action="<?= e(url('usuarios/' . $pessoa['id'] . '/ativo')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn secundario"><?= (int) $pessoa['ativo'] === 1 ? 'Desativar' : 'Ativar' ?></button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
