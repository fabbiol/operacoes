<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#f5f3ed">
    <?php if (!empty($atualizar)): ?>
        <meta http-equiv="refresh" content="<?= (int) $atualizar ?>">
    <?php endif; ?>
    <title><?= e($titulo) ?> · Próspera</title>
    <link rel="icon" href="<?= e(url('favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('assets/app.css')) ?>">
</head>
<body class="<?= e((string) $corpo) ?>">
    <header class="site">
        <a class="brand" href="<?= e(url('/')) ?>">
            <img class="brand-logo" src="<?= e(url('assets/logo.png')) ?>" alt="">
            <span class="brand-name">
                <strong>Próspera</strong>
                <small>Pátio e lavra</small>
            </span>
        </a>
        <?php if (Auth::ok()): ?>
            <nav class="menu">
                <?php $caminho = route_path(); ?>
                <?php if (Auth::admin()): ?>
                    <a class="link<?= $caminho === 'painel' ? ' ativo' : '' ?>" href="<?= e(url('painel')) ?>">Painel</a>
                    <div class="menu-grupo">
                        <span class="menu-rotulo">Fila</span>
                        <a class="link<?= ($caminho === 'lavra' || str_starts_with($caminho, 'lavra/')) ? ' ativo' : '' ?>" href="<?= e(url('lavra/fila')) ?>">Lavra</a>
                        <a class="link<?= ($caminho === 'fila' || $caminho === 'patio' || str_starts_with($caminho, 'ordens/') || str_starts_with($caminho, 'carregamentos/')) ? ' ativo' : '' ?>" href="<?= e(url('fila')) ?>">Carregamento</a>
                    </div>
                    <a class="link<?= str_starts_with($caminho, 'usuarios') ? ' ativo' : '' ?>" href="<?= e(url('usuarios')) ?>">Pessoas</a>
                    <a class="link<?= str_starts_with($caminho, 'cadastros') ? ' ativo' : '' ?>" href="<?= e(url('cadastros')) ?>">Cadastros</a>
                <?php elseif (Auth::ambos()): ?>
                    <a class="link<?= ($caminho === 'lavra' || str_starts_with($caminho, 'lavra/')) ? ' ativo' : '' ?>" href="<?= e(url('lavra')) ?>">Lavra</a>
                    <a class="link<?= ($caminho === 'patio' || str_starts_with($caminho, 'carregamentos/')) ? ' ativo' : '' ?>" href="<?= e(url('patio')) ?>">Pátio</a>
                    <a class="link<?= $caminho === 'trabalhos' ? ' ativo' : '' ?>" href="<?= e(url('trabalhos')) ?>">Meus</a>
                <?php elseif (Auth::motorista()): ?>
                    <a class="link<?= ($caminho === 'lavra' || str_starts_with($caminho, 'lavra/')) ? ' ativo' : '' ?>" href="<?= e(url('lavra')) ?>">Lavra</a>
                    <a class="link<?= $caminho === 'trabalhos' ? ' ativo' : '' ?>" href="<?= e(url('trabalhos')) ?>">Meus</a>
                <?php elseif (Auth::operador()): ?>
                    <a class="link<?= ($caminho === 'patio' || str_starts_with($caminho, 'carregamentos/')) ? ' ativo' : '' ?>" href="<?= e(url('patio')) ?>">Pátio</a>
                    <a class="link<?= $caminho === 'trabalhos' ? ' ativo' : '' ?>" href="<?= e(url('trabalhos')) ?>">Meus</a>
                <?php endif; ?>
                <span class="site-usuario">
                    <?php if (Auth::temFoto()): ?>
                        <img class="avatar" src="<?= e(url('usuarios/' . Auth::id() . '/foto')) ?>" alt="">
                    <?php endif; ?>
                    <?= e(Auth::usuario()) ?> · <?= e(Auth::rotuloPapel()) ?>
                </span>
                <form method="post" action="<?= e(url('sair')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit">Sair</button>
                </form>
            </nav>
        <?php endif; ?>
    </header>
    <main class="folha">
        <?php if (!empty($flash)): ?>
            <p class="aviso <?= e($flash['tipo']) ?>"><?= e($flash['mensagem']) ?></p>
        <?php endif; ?>
        <?php if (!empty($erro)): ?>
            <p class="aviso erro"><?= e($erro) ?></p>
        <?php endif; ?>
        <?php require $viewFile; ?>
    </main>
</body>
</html>
