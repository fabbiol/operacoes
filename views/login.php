<section class="entrada">
    <p class="olho">Pátio</p>
    <h1>Entrar</h1>
    <p class="data"><?= e(data_por_extenso()) ?></p>
    <form method="post" action="<?= e(url('login')) ?>" class="formulario">
        <?= csrf_field() ?>
        <label class="campo">
            <span>Usuário</span>
            <input type="text" name="usuario" maxlength="40" autocomplete="username" autocapitalize="words" required autofocus
                   value="<?= e((string) ($usuario ?? '')) ?>" placeholder="Nome">
        </label>
        <label class="campo">
            <span>Senha</span>
            <input type="password" name="senha" autocomplete="current-password" required>
        </label>
        <button type="submit" class="btn primario">Entrar</button>
    </form>
</section>
