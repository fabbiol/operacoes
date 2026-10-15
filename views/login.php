<?php
$escolhida = $escolhida ?? null;
$iniciais = static function (string $nome): string {
    $partes = preg_split('/\s+/u', $nome) ?: [];
    $letras = '';
    foreach (array_slice($partes, 0, 2) as $parte) {
        $letras .= mb_strtoupper(mb_substr($parte, 0, 1), 'UTF-8');
    }

    return $letras;
};
?>
<section class="entrada<?= $pessoas !== [] ? ' entrada-equipe' : '' ?>">
    <p class="olho">Pátio</p>
    <h1>Entrar</h1>
    <p class="data"><?= e(data_por_extenso()) ?></p>

    <?php if (is_array($escolhida)): ?>
        <div class="entrada-senha">
            <div class="rosto rosto-escolhido">
                <?php if (!empty($escolhida['foto'])): ?>
                    <img src="<?= e(url('entrada/' . (int) $escolhida['id'] . '/foto')) ?>" alt="" decoding="async">
                <?php else: ?>
                    <span class="rosto-vazio"><?= e($iniciais((string) $escolhida['nome'])) ?></span>
                <?php endif; ?>
                <span class="rosto-nome"><?= e((string) $escolhida['nome']) ?></span>
            </div>
            <form method="post" action="<?= e(url('login')) ?>" class="formulario">
                <?= csrf_field() ?>
                <input type="hidden" name="pessoa" value="<?= (int) $escolhida['id'] ?>">
                <?php // O usuário fica no formulário, escondido, para o Chrome não misturar a senha de uma pessoa com a de outra. ?>
                <input type="text" name="usuario" value="<?= e((string) $escolhida['nome']) ?>" autocomplete="username" readonly hidden>
                <label class="campo">
                    <span>Senha de <?= e((string) $escolhida['nome']) ?></span>
                    <input type="password" name="senha" maxlength="72" autocomplete="current-password" required autofocus>
                </label>
                <button type="submit" class="btn primario">Entrar</button>
            </form>
            <a class="btn secundario" href="<?= e(url('login')) ?>">Voltar · não sou <?= e((string) $escolhida['nome']) ?></a>
        </div>
    <?php else: ?>
        <?php if ($pessoas !== []): ?>
            <p class="nota">Toque na sua foto e digite a sua senha.</p>
            <div class="rostos">
                <?php foreach ($pessoas as $pessoa): ?>
                    <a class="rosto" href="<?= e(url('login') . '?pessoa=' . (int) $pessoa['id']) ?>">
                        <?php if (!empty($pessoa['foto'])): ?>
                            <img src="<?= e(url('entrada/' . (int) $pessoa['id'] . '/foto')) ?>" alt="" loading="lazy" decoding="async">
                        <?php else: ?>
                            <span class="rosto-vazio"><?= e($iniciais((string) $pessoa['nome'])) ?></span>
                        <?php endif; ?>
                        <span class="rosto-nome"><?= e($pessoa['nome']) ?></span>
                    </a>
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
    <?php endif; ?>
</section>
