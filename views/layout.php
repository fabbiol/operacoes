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
    <link rel="manifest" href="<?= e(url('manifest.json')) ?>">
    <link rel="apple-touch-icon" href="<?= e(url('assets/icone-192.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/app.css')) ?>">
    <?php if (($corpo ?? '') === 'dashboard'): ?>
        <link rel="stylesheet" href="<?= e(asset('assets/dashboard.css')) ?>">
    <?php endif; ?>
</head>
<body class="<?= e((string) $corpo) ?>" data-agora="<?= (int) ($agoraServidor ?? time()) ?>" data-hora="<?= e(date('H:i:s', (int) ($agoraServidor ?? time()))) ?>" data-calma="<?= (int) ($calmaMs ?? 0) ?>">
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
                    <a class="link<?= $caminho === 'dashboard' ? ' ativo' : '' ?>" href="<?= e(url('dashboard')) ?>">Monitor</a>
                    <div class="menu-grupo">
                        <span class="menu-rotulo">Fila</span>
                        <a class="link<?= ($caminho === 'lavra' || str_starts_with($caminho, 'lavra/')) ? ' ativo' : '' ?>" href="<?= e(url('lavra/fila')) ?>">Lavra</a>
                        <a class="link<?= ($caminho === 'fila' || $caminho === 'patio' || str_starts_with($caminho, 'ordens/') || str_starts_with($caminho, 'carregamentos/')) ? ' ativo' : '' ?>" href="<?= e(url('fila')) ?>">Carregamento</a>
                    </div>
                    <a class="link<?= (str_starts_with($caminho, 'usuarios') || str_starts_with($caminho, 'tablets')) ? ' ativo' : '' ?>" href="<?= e(url('usuarios')) ?>">Pessoas</a>
                    <a class="link<?= str_starts_with($caminho, 'cadastros') ? ' ativo' : '' ?>" href="<?= e(url('cadastros')) ?>">Cadastros</a>
                    <a class="link<?= $caminho === 'manutencao' ? ' ativo' : '' ?>" href="<?= e(url('manutencao')) ?>">Manutenção</a>
                <?php else: ?>
                    <?php if (Auth::pode('motorista', 'escavadeira')): ?>
                        <a class="link<?= ($caminho === 'lavra' || str_starts_with($caminho, 'lavra/')) ? ' ativo' : '' ?>" href="<?= e(url('lavra')) ?>">Lavra</a>
                    <?php endif; ?>
                    <?php if (pode_patio()): ?>
                        <a class="link<?= ($caminho === 'patio' || str_starts_with($caminho, 'carregamentos/')) ? ' ativo' : '' ?>" href="<?= e(url('patio')) ?>">Pátio</a>
                    <?php endif; ?>
                    <a class="link<?= $caminho === 'trabalhos' ? ' ativo' : '' ?>" href="<?= e(url('trabalhos')) ?>">Meus</a>
                <?php endif; ?>
                <span class="site-usuario">
                    <?php if (Auth::temFoto()): ?>
                        <img class="avatar" src="<?= e(url('usuarios/' . Auth::id() . '/foto')) ?>" alt="">
                    <?php endif; ?>
                    <span class="site-nome"><?= e(Auth::usuario()) ?><span class="site-papel"> · <?= e(Auth::rotuloPapel()) ?></span></span>
                    <?php foreach (maquinas_do_usuario() as $maquina): ?>
                        <span class="site-maquina">
                            <?php if (!empty($maquina['foto'])): ?>
                                <img src="<?= e((string) $maquina['foto']) ?>" alt="">
                            <?php endif; ?>
                            <?= e((string) $maquina['nome']) ?>
                            <?php if (!Auth::admin()): ?>
                                <a class="link-discreto" href="<?= e(url('maquina')) ?>">Trocar</a>
                            <?php endif; ?>
                        </span>
                    <?php endforeach; ?>
                </span>
                <form method="post" action="<?= e(url('sair')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit">Sair</button>
                </form>
            </nav>
        <?php endif; ?>
    </header>
    <?php if (!empty($fluxoUrl)): ?>
        <div id="conexao" class="conexao" hidden></div>
        <span id="conexao-leitor" class="so-leitor" role="status" aria-live="polite"></span>
    <?php endif; ?>
    <main class="folha">
        <?php if (!empty($flash)): ?>
            <p class="aviso <?= e($flash['tipo']) ?>"><?= e($flash['mensagem']) ?></p>
        <?php endif; ?>
        <?php if (!empty($erro)): ?>
            <p class="aviso erro"><?= e($erro) ?></p>
        <?php endif; ?>
        <?php require $viewFile; ?>
    </main>
    <script src="<?= e(asset('assets/envio.js')) ?>"></script>
    <script src="<?= e(asset('assets/tablet.js')) ?>" data-sw="<?= e(url('sw.js')) ?>" data-escopo="<?= e(url('/')) ?>"></script>
    <?php if (!empty($fluxoUrl)): ?>
        <script src="<?= e(asset('assets/tempo.js')) ?>"></script>
        <script src="<?= e(asset('assets/fluxo.js')) ?>"></script>
    <?php endif; ?>
</body>
</html>
