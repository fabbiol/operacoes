<section class="entrada<?= $pessoas !== [] ? ' entrada-equipe' : '' ?>">
    <p class="olho">Pátio</p>
    <h1>Entrar</h1>
    <p class="data"><?= e(data_por_extenso()) ?></p>

    <?php if ($pessoas !== []): ?>
        <div class="rostos">
            <?php foreach ($pessoas as $pessoa): ?>
                <?php
                $partes = preg_split('/\s+/u', (string) $pessoa['nome']) ?: [];
                $iniciais = '';
                foreach (array_slice($partes, 0, 2) as $parte) {
                    $iniciais .= mb_strtoupper(mb_substr($parte, 0, 1), 'UTF-8');
                }
                ?>
                <form method="post" action="<?= e(url('login')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="pessoa" value="<?= (int) $pessoa['id'] ?>">
                    <button type="submit" class="rosto">
                        <?php if (!empty($pessoa['foto'])): ?>
                            <img src="<?= e(url('entrada/' . (int) $pessoa['id'] . '/foto')) ?>" alt="" loading="lazy" decoding="async">
                        <?php else: ?>
                            <span class="rosto-vazio"><?= e($iniciais) ?></span>
                        <?php endif; ?>
                        <span class="rosto-nome"><?= e($pessoa['nome']) ?></span>
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($tablet)): ?>
        <p class="nota">Neste aparelho só entra quem tem usuário e senha. Para a equipe entrar pela foto, o administrativo autoriza o tablet em Pessoas › Tablets.</p>
    <?php endif; ?>

    <div class="entrada-admin">
        <h2><?= empty($tablet) ? 'Usuário e senha' : 'Administrativo' ?></h2>
        <form method="post" action="<?= e(url('login')) ?>" class="formulario">
            <?= csrf_field() ?>
            <label class="campo">
                <span>Usuário</span>
                <input type="text" name="usuario" maxlength="40" autocomplete="username" autocapitalize="words" required<?= $pessoas === [] ? ' autofocus' : '' ?>
                       value="<?= e((string) ($usuario ?? '')) ?>" placeholder="Usuário">
            </label>
            <label class="campo">
                <span>Senha</span>
                <input type="password" name="senha" autocomplete="current-password" required>
            </label>
            <button type="submit" class="btn primario">Entrar</button>
        </form>
    </div>
</section>
