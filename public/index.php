<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

$path = route_path();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    despachar($path, $method);
} catch (InvalidArgumentException $e) {
    flash_set($e->getMessage(), 'erro');
    redirect('/');
} catch (Throwable $e) {
    error_log($e::class . ': ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    $mensagem = $e instanceof PDOException
        ? 'Não foi possível falar com o banco.'
        : 'Não foi possível concluir a operação.';
    if (env_str('APP_DEBUG') === '1') {
        $mensagem .= ' ' . $e->getMessage();
    }
    render('erro', [
        'titulo' => 'Falha',
        'mensagem' => $mensagem,
    ]);
}

function despachar(string $path, string $method): void
{
    if ($path === 'health') {
        header('Content-Type: application/json; charset=utf-8');
        try {
            Db::pdo()->query('SELECT 1');
            echo '{"ok":true}';
        } catch (Throwable $e) {
            http_response_code(503);
            echo '{"ok":false}';
        }
        exit;
    }

    if (preg_match('#^entrada/(\d+)/foto$#', $path, $m) && $method === 'GET') {
        tela_entrada_foto((int) $m[1]);
        return;
    }

    if ($path === 'login') {
        tela_login($method);
        return;
    }

    Auth::exigir();

    if ($path === 'sair' && $method === 'POST') {
        csrf_check();
        Auth::sair();
        redirect('/login');
    }

    if ($path === 'maquina') {
        tela_maquina($method);
        return;
    }

    exigir_maquina();

    if ($path === '' || $path === 'inicio') {
        redirect(Auth::inicio());
    }

    if (preg_match('#^usuarios/(\d+)/foto$#', $path, $m)) {
        tela_pessoa_foto((int) $m[1]);
        return;
    }

    if ($path === 'patio') {
        exigir_papel('operador');
        tela_patio();
        return;
    }

    if ($path === 'patio/resumo') {
        exigir_papel('operador');
        tela_patio_resumo();
        return;
    }

    if (preg_match('#^carregamentos/(\d+)/iniciar$#', $path, $m)) {
        exigir_papel('operador');
        tela_iniciar((int) $m[1], $method);
        return;
    }

    if (preg_match('#^carregamentos/(\d+)/finalizar$#', $path, $m)) {
        exigir_papel('operador');
        tela_finalizar((int) $m[1], $method);
        return;
    }

    if ($path === 'lavra') {
        exigir_papel('motorista', 'operador');
        tela_lavra();
        return;
    }

    if ($path === 'lavra/resumo') {
        exigir_papel('motorista');
        tela_lavra_resumo();
        return;
    }

    if (preg_match('#^lavra/caminhoes/(\d+)/fila$#', $path, $m) && $method === 'POST') {
        exigir_papel('motorista');
        csrf_check();
        try {
            $maquina = maquina_escolhida();
            if (!Auth::admin() && ($maquina === null || $maquina['tipo'] !== 'caminhao' || (int) $maquina['id'] !== (int) $m[1])) {
                throw new InvalidArgumentException('Entre na fila com o caminhão que está no menu.');
            }
            Lavra::entrarNaFila((int) $m[1], Auth::usuario(), Auth::motorista());
            flash_set('Caminhão entrou na fila.');
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/lavra');
    }

    if (preg_match('#^lavra/ciclos/(\d+)/avancar$#', $path, $m) && $method === 'POST') {
        exigir_papel('motorista');
        csrf_check();
        try {
            flash_set(Lavra::avancar((int) $m[1], Auth::motorista() ? Auth::usuario() : null));
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/lavra');
    }

    if (preg_match('#^lavra/ciclos/(\d+)/cancelar$#', $path, $m)) {
        exigir_papel('motorista');
        tela_lavra_cancelar((int) $m[1], $method);
        return;
    }

    if (preg_match('#^lavra/ciclos/(\d+)/encerrar$#', $path, $m)) {
        exigir_papel('motorista');
        tela_lavra_encerrar((int) $m[1], $method);
        return;
    }

    if ($path === 'lavra/retro' && $method === 'POST') {
        exigir_papel('operador');
        csrf_check();
        $retroId = (int) ($_POST['retroescavadeira_id'] ?? 0);
        $retro = $retroId > 0 ? Cadastro::buscar('retros', $retroId) : null;
        $ativa = $retro !== null && (int) ($retro['ativo'] ?? 0) === 1;
        if (Auth::admin() && $ativa) {
            $_SESSION['retroescavadeira_id'] = $retroId;
        } elseif ($ativa && maquina_esta_livre('retro', $retroId)) {
            maquina_definir('retro', $retroId);
        } else {
            flash_set('Selecione a retroescavadeira.', 'erro');
        }
        redirect('/lavra');
    }

    if ($path === 'lavra/retro/trocar' && $method === 'POST') {
        exigir_papel('operador');
        csrf_check();
        if (Auth::admin()) {
            unset($_SESSION['retroescavadeira_id']);
            redirect('/lavra');
        }
        redirect('/maquina');
    }

    if (preg_match('#^lavra/ciclos/(\d+)/carga$#', $path, $m)) {
        exigir_papel('operador');
        tela_lavra_iniciar((int) $m[1], $method);
        return;
    }

    if (preg_match('#^lavra/ciclos/(\d+)/concluir-carga$#', $path, $m) && $method === 'POST') {
        exigir_papel('operador');
        csrf_check();
        try {
            flash_set(Lavra::concluirCarga((int) $m[1], Auth::admin() ? null : Auth::usuario()));
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/lavra');
    }

    if ($path === 'cadastros') {
        tela_hub();
        return;
    }

    if (preg_match('#^cadastros/([a-z]+)$#', $path, $m)) {
        exigir_cadastro($m[1]);
        tela_cadastro_lista($m[1]);
        return;
    }

    if (preg_match('#^cadastros/([a-z]+)/novo$#', $path, $m)) {
        exigir_cadastro($m[1]);
        tela_cadastro_form($m[1], null, $method);
        return;
    }

    if (preg_match('#^cadastros/([a-z]+)/(\d+)/foto$#', $path, $m)) {
        tela_cadastro_foto($m[1], (int) $m[2]);
        return;
    }

    if (preg_match('#^cadastros/([a-z]+)/(\d+)/ativo$#', $path, $m) && $method === 'POST') {
        exigir_cadastro($m[1]);
        csrf_check();
        try {
            Cadastro::alternarAtivo($m[1], (int) $m[2]);
            flash_set('Cadastro atualizado.');
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/cadastros/' . $m[1]);
    }

    if (preg_match('#^cadastros/([a-z]+)/(\d+)$#', $path, $m)) {
        exigir_cadastro($m[1]);
        tela_cadastro_form($m[1], (int) $m[2], $method);
        return;
    }

    if ($path === 'trabalhos') {
        if (Auth::admin()) {
            redirect('/painel');
        }
        tela_trabalhos();
        return;
    }

    exigir_admin();

    if ($path === 'usuarios') {
        tela_pessoas();
        return;
    }

    if ($path === 'usuarios/novo') {
        tela_pessoa_form(null, $method);
        return;
    }

    if (preg_match('#^usuarios/(\d+)/ativo$#', $path, $m) && $method === 'POST') {
        csrf_check();
        try {
            Pessoas::alternarAtivo((int) $m[1]);
            flash_set('Pessoa atualizada.');
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/usuarios');
    }

    if (preg_match('#^usuarios/(\d+)$#', $path, $m)) {
        tela_pessoa_form((int) $m[1], $method);
        return;
    }

    if ($path === 'painel') {
        tela_painel();
        return;
    }

    if ($path === 'dashboard') {
        exigir_admin();
        tela_dashboard();
        return;
    }

    if ($path === 'dashboard/dados') {
        exigir_admin();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(Dashboard::instantaneo(), JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($path === 'fila') {
        tela_fila();
        return;
    }

    if ($path === 'lavra/fila') {
        tela_lavra_fila();
        return;
    }

    if ($path === 'ordens/nova') {
        tela_ordem_form(null, $method);
        return;
    }

    if (preg_match('#^ordens/(\d+)$#', $path, $m)) {
        tela_ordem((int) $m[1], $method);
        return;
    }

    if (preg_match('#^ordens/(\d+)/liberar$#', $path, $m) && $method === 'POST') {
        csrf_check();
        try {
            Operacoes::liberar((int) $m[1], Auth::usuario());
            flash_set('Ordem liberada para o pátio.');
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/ordens/' . $m[1]);
    }

    if (preg_match('#^ordens/(\d+)/concluir$#', $path, $m) && $method === 'POST') {
        csrf_check();
        try {
            Operacoes::concluir((int) $m[1]);
            flash_set('Carregamento conferido e concluído.');
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/ordens/' . $m[1]);
    }

    if (preg_match('#^ordens/(\d+)/cancelar$#', $path, $m)) {
        tela_cancelar((int) $m[1], $method);
        return;
    }

    http_response_code(404);
    render('erro', [
        'titulo' => 'Não encontrado',
        'mensagem' => 'Essa página não existe.',
    ]);
}

function tela_login(string $method): void
{
    if (!Auth::configurada()) {
        http_response_code(500);
        render('erro', [
            'titulo' => 'Configuração',
            'mensagem' => 'Cadastre ao menos um usuário em APP_USUARIOS.',
        ]);
        return;
    }

    if (Auth::ok()) {
        redirect('/');
    }

    $erro = null;
    $usuario = '';
    if ($method === 'POST') {
        csrf_check();
        if (isset($_POST['pessoa'])) {
            if (Auth::entrarPorPessoa((int) $_POST['pessoa'])) {
                redirect('/');
            }
            $erro = 'Não foi possível entrar com essa pessoa.';
        } else {
            $usuario = trim((string) ($_POST['usuario'] ?? ''));
            if (str_contains($usuario, '@')) {
                $erro = 'Use o usuário, sem e-mail.';
            } elseif (Auth::tentar($usuario, (string) ($_POST['senha'] ?? ''))) {
                redirect('/');
            } else {
                $erro = Auth::falhaLogin() !== '' ? Auth::falhaLogin() : 'Usuário ou senha não conferem.';
            }
        }
    }

    render('login', [
        'titulo' => 'Entrar',
        'erro' => $erro,
        'usuario' => $usuario,
        'pessoas' => Pessoas::paraEntrada(),
    ]);
}

function tela_entrada_foto(int $id): void
{
    $pessoa = Pessoas::elegivelEntrada($id);
    if ($pessoa === null || empty($pessoa['foto'])) {
        http_response_code(404);
        exit;
    }
    tela_pessoa_foto($id);
}

function tela_patio(): void
{
    render('patio', [
        'titulo' => 'Pátio',
        'corpo' => 'patio',
        'atualizar' => 30,
        'ordens' => Operacoes::patio(),
        'enviadas' => Operacoes::enviadasHoje(),
    ]);
}

function tela_patio_resumo(): void
{
    $dia = trim((string) ($_GET['dia'] ?? date('Y-m-d')));
    $visao = (($_GET['visao'] ?? '') === 'operador') ? 'operador' : 'maquina';
    try {
        $resumo = Operacoes::resumo($dia, Auth::operador() ? Auth::usuario() : null);
    } catch (InvalidArgumentException) {
        $dia = date('Y-m-d');
        $resumo = Operacoes::resumo($dia, Auth::operador() ? Auth::usuario() : null);
    }
    $bloco = $resumo[$visao];

    render('patio_resumo', [
        'titulo' => 'Tempos do pátio',
        'corpo' => 'patio',
        'dia' => $resumo['dia'],
        'visao' => $visao,
        'medias' => $bloco['medias'],
        'total' => $bloco['total'],
        'grupos' => $bloco['grupos'],
    ]);
}

function tela_iniciar(int $id, string $method): void
{
    $ordem = ordem_ou_404($id);
    $erro = null;
    $operador = (string) ($_POST['operador'] ?? Auth::usuario());
    $paId = (int) ($_POST['pa_carregadeira_id'] ?? 0);
    $pas = Cadastro::opcoes('pas');
    $semPa = $pas === [];
    $paFixa = null;
    $retroPatio = null;
    if (!Auth::admin()) {
        $maquina = maquina_escolhida();
        if ($maquina !== null && $maquina['tipo'] === 'pa') {
            $paFixa = $maquina;
            $paId = (int) $maquina['id'];
        } elseif ($semPa && $maquina !== null && $maquina['tipo'] === 'retro') {
            $retroPatio = $maquina;
        } else {
            $paFixa = false;
        }
    }

    if ($method === 'POST' && ($retroPatio !== null || (Auth::admin() && $semPa))) {
        csrf_check();
        try {
            $retroId = $retroPatio !== null
                ? (int) $retroPatio['id']
                : (int) ($_POST['retroescavadeira_id'] ?? 0);
            Operacoes::iniciarComRetro($id, $retroId, $operador);
            flash_set('Carregamento iniciado.');
            redirect('/patio');
        } catch (InvalidArgumentException $e) {
            $erro = $e->getMessage();
            $ordem = Operacoes::buscar($id) ?? $ordem;
        }
    } elseif ($method === 'POST' && $paFixa !== false) {
        csrf_check();
        try {
            Operacoes::iniciar($id, $paId, $operador);
            flash_set('Carregamento iniciado.');
            redirect('/patio');
        } catch (InvalidArgumentException $e) {
            $erro = $e->getMessage();
            $ordem = Operacoes::buscar($id) ?? $ordem;
        }
    }

    render('iniciar', [
        'titulo' => 'Iniciar ' . $ordem['ordem'],
        'corpo' => 'patio',
        'ordem' => $ordem,
        'pas' => $pas,
        'retros' => $semPa ? Cadastro::opcoes('retros') : [],
        'emUso' => Operacoes::pasEmUso($id),
        'operador' => $operador,
        'paId' => $paId,
        'paFixa' => $paFixa,
        'retroPatio' => $retroPatio,
        'semPa' => $semPa,
        'erro' => $erro,
    ]);
}

function tela_finalizar(int $id, string $method): void
{
    $ordem = ordem_ou_404($id);
    $erro = null;
    $nota = (string) ($_POST['nota'] ?? '');

    if ($method === 'POST') {
        csrf_check();
        try {
            Operacoes::finalizar($id, $nota, Auth::usuario());
            flash_set('Carregamento enviado para conferência.');
            redirect('/patio');
        } catch (InvalidArgumentException $e) {
            $erro = $e->getMessage();
            $ordem = Operacoes::buscar($id) ?? $ordem;
        }
    }

    render('finalizar', [
        'titulo' => 'Finalizar ' . $ordem['ordem'],
        'corpo' => 'patio',
        'ordem' => $ordem,
        'nota' => $nota,
        'erro' => $erro,
    ]);
}

function retro_escolhida(): ?array
{
    if (Auth::admin()) {
        $id = (int) ($_SESSION['retroescavadeira_id'] ?? 0);
    } else {
        $maquina = maquina_escolhida();
        $id = ($maquina !== null && $maquina['tipo'] === 'retro') ? (int) $maquina['id'] : 0;
    }
    if ($id < 1) {
        return null;
    }
    $retro = Cadastro::buscar('retros', $id);
    if ($retro === null || (int) ($retro['ativo'] ?? 0) !== 1) {
        unset($_SESSION['retroescavadeira_id']);

        return null;
    }

    return $retro;
}

function exigir_maquina(): void
{
    $path = route_path();
    if (preg_match('#^(usuarios/\d+/foto|cadastros/[a-z]+/\d+/foto)$#', $path)) {
        return;
    }
    if (Auth::admin()) {
        return;
    }
    $trabalho = maquina_do_trabalho();
    if ($trabalho !== null) {
        maquina_definir($trabalho['tipo'], $trabalho['id']);

        return;
    }
    $escolhida = maquina_escolhida();
    if ($escolhida !== null && maquina_esta_livre($escolhida['tipo'], (int) $escolhida['id'])) {
        return;
    }
    if ($escolhida !== null) {
        maquina_limpar();
    }
    redirect('/maquina');
}

function tela_maquina(string $method): void
{
    if (Auth::admin()) {
        redirect('/painel');
    }

    $travada = maquina_do_trabalho();
    if ($travada !== null) {
        maquina_definir($travada['tipo'], $travada['id']);
    }

    $erro = null;
    if ($method === 'POST' && $travada === null) {
        csrf_check();
        $bruto = (string) ($_POST['maquina'] ?? '');
        if (preg_match('/^(caminhao|pa|retro):(\d+)$/', $bruto, $partes) && maquina_esta_livre($partes[1], (int) $partes[2])) {
            maquina_definir($partes[1], (int) $partes[2]);
            redirect(maquina_destino());
        }
        $erro = 'Escolha uma máquina livre.';
    }

    render('maquina', [
        'titulo' => 'Máquina',
        'corpo' => 'lavra',
        'maquinas' => $travada === null ? maquinas_livres() : [],
        'atual' => maquina_escolhida(),
        'travada' => $travada !== null,
        'erro' => $erro,
    ]);
}

function tela_lavra(): void
{
    $tipo = maquina_escolhida()['tipo'] ?? '';
    $mostraCiclo = Auth::admin() || ($tipo === 'caminhao');
    $mostraCarga = Auth::admin() || ($tipo === 'retro');
    $carga = $mostraCarga ? [
        'filaCarga' => Lavra::naFila(),
        'minhaCarga' => Lavra::cargaDoOperador(Auth::usuario()),
        'retroEscolhida' => retro_escolhida(),
        'retros' => Cadastro::opcoes('retros'),
    ] : [];
    if (!$mostraCiclo && !$mostraCarga) {
        render('lavra_carga', [
            'titulo' => 'Carga na lavra',
            'corpo' => 'lavra',
            'foraDaMaquina' => maquina_escolhida(),
            'filaCarga' => [],
            'minhaCarga' => null,
            'retroEscolhida' => null,
            'retros' => [],
        ]);
        return;
    }
    if (!$mostraCiclo) {
        render('lavra_carga', [
            'titulo' => 'Carga na lavra',
            'corpo' => 'lavra',
            'foraDaMaquina' => null,
        ] + $carga);
        return;
    }

    $motorista = !Auth::admin() && Auth::motorista();
    $painel = $motorista ? Lavra::painelDoMotorista(Auth::usuario()) : Lavra::painel();
    if ($tipo === 'caminhao') {
        $id = (int) (maquina_escolhida()['id'] ?? 0);
        $painel['livres'] = array_values(array_filter(
            $painel['livres'],
            static fn (array $caminhao): bool => (int) $caminhao['id'] === $id
        ));
    }
    render('lavra', [
        'titulo' => 'Ciclo de lavra',
        'corpo' => 'lavra',
        'emCiclo' => $painel['emCiclo'],
        'livres' => $painel['livres'],
        'semCadastro' => Cadastro::listar('caminhoes') === [],
        'mostraCarga' => $mostraCarga,
    ] + $carga);
}

function tela_lavra_iniciar(int $id, string $method): void
{
    $ciclo = Lavra::buscar($id);
    if ($ciclo === null) {
        http_response_code(404);
        render('erro', [
            'titulo' => 'Não encontrado',
            'mensagem' => 'Esse ciclo não existe.',
        ]);
        return;
    }

    if ($method !== 'POST') {
        redirect('/lavra');
    }

    csrf_check();
    $operador = (string) ($_POST['operador'] ?? Auth::usuario());
    $retroId = (int) ($_POST['retroescavadeira_id'] ?? 0);
    if (!Auth::admin()) {
        $maquina = maquina_escolhida();
        $retroId = ($maquina !== null && $maquina['tipo'] === 'retro') ? (int) $maquina['id'] : 0;
        $operador = Auth::usuario();
    }
    try {
        Lavra::iniciarCarga($id, $retroId, $operador);
        flash_set('Carga iniciada.');
    } catch (InvalidArgumentException $e) {
        flash_set($e->getMessage(), 'erro');
    }
    redirect('/lavra');
}

function tela_lavra_resumo(): void
{
    $dia = trim((string) ($_GET['dia'] ?? date('Y-m-d')));
    $visao = (($_GET['visao'] ?? '') === 'motorista') ? 'motorista' : 'caminhao';
    try {
        $resumo = Lavra::resumo($dia, Auth::motorista() ? Auth::usuario() : null);
    } catch (InvalidArgumentException) {
        $dia = date('Y-m-d');
        $resumo = Lavra::resumo($dia, Auth::motorista() ? Auth::usuario() : null);
    }
    $bloco = $resumo[$visao];

    render('lavra_resumo', [
        'titulo' => 'Tempos da lavra',
        'corpo' => 'lavra',
        'dia' => $resumo['dia'],
        'visao' => $visao,
        'medias' => $bloco['medias'],
        'viagens' => $bloco['viagens'],
        'producao' => $bloco['producao'],
        'grupos' => $bloco['grupos'],
    ]);
}

function tela_lavra_cancelar(int $id, string $method): void
{
    $ciclo = Lavra::buscar($id);
    if ($ciclo === null) {
        http_response_code(404);
        render('erro', [
            'titulo' => 'Não encontrado',
            'mensagem' => 'Esse ciclo não existe.',
        ]);
        return;
    }

    if (Auth::motorista() && (string) ($ciclo['operador'] ?? '') !== Auth::usuario()) {
        http_response_code(403);
        render('erro', [
            'titulo' => 'Acesso',
            'mensagem' => 'Esse ciclo é de outro motorista.',
        ]);
        return;
    }

    $erro = null;
    $motivo = (string) ($_POST['motivo'] ?? '');
    if ($method === 'POST') {
        csrf_check();
        try {
            Lavra::cancelar($id, $motivo, Auth::usuario(), Auth::motorista() ? Auth::usuario() : null);
            flash_set('Ciclo cancelado.');
            redirect('/lavra');
        } catch (InvalidArgumentException $e) {
            $erro = $e->getMessage();
            $ciclo = Lavra::buscar($id) ?? $ciclo;
        }
    }

    render('lavra_cancelar', [
        'titulo' => 'Cancelar ciclo',
        'corpo' => 'lavra',
        'ciclo' => $ciclo,
        'motivo' => $motivo,
        'erro' => $erro,
    ]);
}

function tela_lavra_encerrar(int $id, string $method): void
{
    $ciclo = Lavra::buscar($id);
    if ($ciclo === null) {
        http_response_code(404);
        render('erro', [
            'titulo' => 'Não encontrado',
            'mensagem' => 'Esse ciclo não existe.',
        ]);
        return;
    }

    if (Auth::motorista() && (string) ($ciclo['operador'] ?? '') !== Auth::usuario()) {
        http_response_code(403);
        render('erro', [
            'titulo' => 'Acesso',
            'mensagem' => 'Esse ciclo é de outro motorista.',
        ]);
        return;
    }

    $erro = null;
    $motivo = (string) ($_POST['motivo'] ?? '');
    $outro = (string) ($_POST['outro'] ?? '');
    if ($method === 'POST') {
        csrf_check();
        try {
            Lavra::encerrar($id, $motivo, $outro, Auth::usuario(), Auth::motorista() ? Auth::usuario() : null);
            flash_set('Viagem encerrada.');
            redirect('/lavra');
        } catch (InvalidArgumentException $e) {
            $erro = $e->getMessage();
            $ciclo = Lavra::buscar($id) ?? $ciclo;
        }
    }

    render('lavra_encerrar', [
        'titulo' => 'Encerrar viagem',
        'corpo' => 'lavra',
        'ciclo' => $ciclo,
        'motivo' => $motivo,
        'outro' => $outro,
        'erro' => $erro,
    ]);
}

function tela_trabalhos(): void
{
    $desde = date('Y-m-d H:i:s', strtotime('-30 days'));
    $nome = Auth::usuario();
    $itens = [];
    foreach (Operacoes::concluidosDo($nome, $desde) as $ordem) {
        $itens[] = ['tipo' => 'patio', 'quando' => (string) $ordem['fim_carregamento'], 'ordem' => $ordem];
    }
    foreach (Lavra::concluidosDo($nome, $desde) as $ciclo) {
        $itens[] = ['tipo' => 'lavra', 'quando' => (string) $ciclo['fim_ciclo'], 'ciclo' => $ciclo];
    }
    foreach (Lavra::cargasConcluidasDo($nome, $desde) as $ciclo) {
        $itens[] = ['tipo' => 'carga', 'quando' => (string) $ciclo['fim_ciclo'], 'ciclo' => $ciclo];
    }
    usort($itens, static fn (array $a, array $b): int => strcmp($b['quando'], $a['quando']));

    $patio = 0;
    $lavra = 0;
    foreach ($itens as $item) {
        if ($item['tipo'] === 'patio') {
            $patio++;
        } else {
            $lavra++;
        }
    }

    render('trabalhos', [
        'titulo' => 'Meus trabalhos',
        'corpo' => 'trabalhos',
        'itens' => $itens,
        'patio' => $patio,
        'lavra' => $lavra,
    ]);
}

function tela_dashboard(): void
{
    render('dashboard', [
        'titulo' => 'Monitor',
        'corpo' => 'dashboard',
        'instantaneo' => Dashboard::instantaneo(),
    ]);
}

function tela_painel(): void
{
    $contagens = Operacoes::contagens();
    $lavra = Lavra::painel();
    $dia = date('Y-m-d');

    render('painel', [
        'titulo' => 'Painel',
        'corpo' => 'painel',
        'atualizar' => 30,
        'autorizar' => (int) ($contagens['aguardando_autorizacao'] ?? 0),
        'conferir' => (int) ($contagens['aguardando_conferencia'] ?? 0),
        'liberadas' => (int) ($contagens['liberado'] ?? 0),
        'carregando' => Operacoes::listar('carregando', ''),
        'ciclos' => $lavra['emCiclo'],
        'livres' => count($lavra['livres']),
        'cargaDia' => Operacoes::resumo($dia),
        'lavraDia' => Lavra::resumo($dia),
    ]);
}

function tela_fila(): void
{
    $situacao = (string) ($_GET['situacao'] ?? 'abertas');
    $busca = trim((string) ($_GET['q'] ?? ''));
    if (!in_array($situacao, ['abertas', 'todas', ...array_keys(status_lista())], true)) {
        $situacao = 'abertas';
    }

    render('fila', [
        'titulo' => 'Fila',
        'corpo' => 'fila',
        'ordens' => Operacoes::listar($situacao, $busca),
        'contagens' => Operacoes::contagens(),
        'situacao' => $situacao,
        'busca' => $busca,
    ]);
}

function tela_lavra_fila(): void
{
    $situacao = (string) ($_GET['situacao'] ?? 'abertas');
    $busca = trim((string) ($_GET['q'] ?? ''));
    $validas = ['abertas', 'todas', 'na_fila', 'carregando', 'em_viagem', 'retornando', 'concluido', 'cancelado'];
    if (!in_array($situacao, $validas, true)) {
        $situacao = 'abertas';
    }

    render('lavra_fila', [
        'titulo' => 'Fila da lavra',
        'corpo' => 'fila',
        'ciclos' => Lavra::listar($situacao, $busca),
        'contagens' => Lavra::contagens(),
        'situacao' => $situacao,
        'busca' => $busca,
    ]);
}

function tela_ordem(int $id, string $method): void
{
    if ($method === 'POST') {
        csrf_check();
        try {
            Operacoes::atualizar($id, $_POST);
            flash_set('Ordem atualizada.');
            redirect('/ordens/' . $id);
        } catch (InvalidArgumentException $e) {
            $ordem = ordem_ou_404($id);
            render('ordem_form', dados_formulario($ordem, $_POST, $e->getMessage()));
            return;
        }
    }

    $ordem = ordem_ou_404($id);
    $editavel = in_array($ordem['status'], ['aguardando_autorizacao', 'liberado', 'carregando', 'aguardando_conferencia'], true);
    render('ordem', [
        'titulo' => $ordem['ordem'],
        'ordem' => $ordem,
        'editavel' => $editavel,
        'formulario' => $editavel ? dados_formulario($ordem, null, null) : null,
    ]);
}

function tela_ordem_form(?int $id, string $method): void
{
    $erro = null;
    $post = $method === 'POST' ? $_POST : null;
    if ($method === 'POST') {
        csrf_check();
        try {
            $novo = Operacoes::criar($_POST, Auth::usuario());
            flash_set(isset($_POST['liberar']) ? 'Ordem lançada e liberada para o pátio.' : 'Ordem lançada. Falta liberar para o pátio.');
            redirect('/ordens/' . $novo);
        } catch (InvalidArgumentException $e) {
            $erro = $e->getMessage();
        }
    }

    render('ordem_form', dados_formulario(null, $post, $erro));
}

function tela_cancelar(int $id, string $method): void
{
    $ordem = ordem_ou_404($id);
    $erro = null;
    if ($method === 'POST') {
        csrf_check();
        try {
            Operacoes::cancelar($id, (string) ($_POST['motivo'] ?? ''), Auth::usuario());
            flash_set('Ordem cancelada.');
            redirect('/ordens/' . $id);
        } catch (InvalidArgumentException $e) {
            $erro = $e->getMessage();
            $ordem = Operacoes::buscar($id) ?? $ordem;
        }
    }

    render('cancelar', [
        'titulo' => 'Cancelar ' . $ordem['ordem'],
        'ordem' => $ordem,
        'erro' => $erro,
        'motivo' => (string) ($_POST['motivo'] ?? ''),
    ]);
}

function tela_hub(): void
{
    $cartoes = [];
    foreach (Cadastro::tipos() as $chave => $tipo) {
        if (!Auth::podeCadastro($chave)) {
            continue;
        }
        $cartoes[] = $tipo + [
            'chave' => $chave,
            'total' => count(Cadastro::listar($chave)),
        ];
    }
    if ($cartoes === []) {
        exigir_papel();
    }
    render('hub', [
        'titulo' => 'Cadastros',
        'cartoes' => $cartoes,
    ]);
}

function tela_cadastro_lista(string $chave): void
{
    try {
        $tipo = Cadastro::tipo($chave);
    } catch (InvalidArgumentException) {
        http_response_code(404);
        render('erro', ['titulo' => 'Não encontrado', 'mensagem' => 'Esse cadastro não existe.']);
        return;
    }

    render('cadastros', [
        'titulo' => $tipo['titulo'],
        'tipo' => $tipo,
        'itens' => Cadastro::listar($chave),
    ]);
}

function tela_pessoas(): void
{
    render('pessoas', [
        'titulo' => 'Pessoas',
        'pessoas' => Pessoas::listar(),
    ]);
}

function tela_pessoa_form(?int $id, string $method): void
{
    $pessoa = null;
    if ($id !== null) {
        $pessoa = Pessoas::buscar($id);
        if ($pessoa === null) {
            http_response_code(404);
            render('erro', ['titulo' => 'Não encontrado', 'mensagem' => 'Essa pessoa não existe.']);
            return;
        }
    }

    $erro = null;
    if ($method === 'POST') {
        csrf_check();
        try {
            $salvo = Pessoas::salvar($id, $_POST);
            flash_set('Pessoa salva.');
            redirect('/usuarios/' . $salvo);
        } catch (InvalidArgumentException $e) {
            $erro = $e->getMessage();
            $pessoa = [
                'id' => $id,
                'nome' => (string) ($_POST['nome'] ?? ($pessoa['nome'] ?? '')),
                'nome_completo' => (string) ($_POST['nome_completo'] ?? ($pessoa['nome_completo'] ?? '')),
                'funcao' => (string) ($_POST['funcao'] ?? ''),
                'ativo' => isset($_POST['ativo']) ? 1 : 0,
                'foto' => is_array($pessoa) ? ($pessoa['foto'] ?? null) : null,
                'administrador' => is_array($pessoa) ? ($pessoa['administrador'] ?? 0) : 0,
            ];
            if (is_array($pessoa) && (int) $pessoa['administrador'] === 1 && $id !== null) {
                $original = Pessoas::buscar($id);
                if ($original !== null) {
                    $pessoa['nome'] = $original['nome'];
                    $pessoa['administrador'] = 1;
                }
            }
        }
    }

    render('pessoa_form', [
        'titulo' => $pessoa === null || $id === null ? 'Nova pessoa' : (string) (($pessoa['nome_completo'] ?? '') !== '' ? $pessoa['nome_completo'] : $pessoa['nome']),
        'pessoa' => $pessoa,
        'erro' => $erro,
    ]);
}

function tela_pessoa_foto(int $id): void
{
    $pessoa = Pessoas::buscar($id);
    if ($pessoa === null) {
        http_response_code(404);
        exit;
    }
    $arquivo = Pessoas::arquivoFoto($id);
    if ($arquivo === null) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: ' . $arquivo['tipo']);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=3600');
    header('Content-Length: ' . (string) filesize($arquivo['caminho']));
    readfile($arquivo['caminho']);
    exit;
}

function tela_cadastro_foto(string $chave, int $id): void
{
    try {
        $arquivo = Cadastro::arquivoFoto($chave, $id);
    } catch (InvalidArgumentException) {
        http_response_code(404);
        exit;
    }
    if ($arquivo === null) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: ' . $arquivo['tipo']);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=3600');
    header('Content-Length: ' . (string) filesize($arquivo['caminho']));
    readfile($arquivo['caminho']);
    exit;
}

function tela_cadastro_form(string $chave, ?int $id, string $method): void
{
    try {
        $tipo = Cadastro::tipo($chave);
    } catch (InvalidArgumentException) {
        http_response_code(404);
        render('erro', ['titulo' => 'Não encontrado', 'mensagem' => 'Esse cadastro não existe.']);
        return;
    }

    $item = null;
    if ($id !== null) {
        $item = Cadastro::buscar($chave, $id);
        if ($item === null) {
            http_response_code(404);
            render('erro', ['titulo' => 'Não encontrado', 'mensagem' => 'Esse cadastro não existe.']);
            return;
        }
    }

    $erro = null;
    if ($method === 'POST') {
        csrf_check();
        try {
            $salvo = Cadastro::salvar($chave, $id, $_POST);
            flash_set('Cadastro salvo.');
            redirect('/cadastros/' . $chave . '/' . $salvo);
        } catch (InvalidArgumentException $e) {
            $erro = $e->getMessage();
            $item = [
                'id' => $id,
                'nome' => (string) ($_POST['nome'] ?? ''),
                'observacoes' => (string) ($_POST['observacoes'] ?? ''),
                'ativo' => isset($_POST['ativo']) ? 1 : 0,
                'marca' => (string) ($_POST['marca'] ?? ''),
                'modelo' => (string) ($_POST['modelo'] ?? ''),
                'ano' => (string) ($_POST['ano'] ?? ''),
                'capacidade_t' => (string) ($_POST['capacidade'] ?? ''),
                'foto' => is_array($item) ? ($item['foto'] ?? null) : null,
            ];
        }
    }

    render('cadastro_form', [
        'titulo' => $item === null || $id === null ? (($tipo['artigo'] ?? 'Novo') . ' ' . $tipo['singular']) : (string) $item['nome'],
        'tipo' => $tipo,
        'item' => $item,
        'erro' => $erro,
    ]);
}

function ordem_ou_404(int $id): array
{
    $ordem = Operacoes::buscar($id);
    if ($ordem === null) {
        http_response_code(404);
        render('erro', [
            'titulo' => 'Não encontrada',
            'mensagem' => 'Essa ordem não existe.',
        ]);
        exit;
    }

    return $ordem;
}

function dados_formulario(?array $ordem, ?array $post, ?string $erro): array
{
    $fonte = $post ?? (is_array($ordem) ? $ordem : []);
    $materialId = (int) ($fonte['material_id'] ?? 0);

    return [
            'titulo' => $ordem === null ? 'Nova ordem' : 'Editar ' . $ordem['ordem'],
            'ordem' => $ordem,
            'erro' => $erro,
            'valor' => [
                'data' => $post !== null
                    ? (string) ($post['data'] ?? '')
                    : data_para_input(is_array($ordem) ? (string) ($ordem['data'] ?? '') : null),
                'cliente' => (string) ($fonte['cliente'] ?? ''),
                'pedido' => (string) ($fonte['pedido'] ?? ''),
                'nf' => (string) ($fonte['nf'] ?? ''),
                'material_id' => $materialId,
                'quantidade_autorizada' => $post !== null
                    ? (string) ($post['quantidade_autorizada'] ?? '')
                    : ($ordem === null ? '' : formatar_quantidade((string) $ordem['quantidade_autorizada'])),
                'unidade' => (string) ($fonte['unidade'] ?? 't'),
                'observacoes' => (string) ($fonte['observacoes'] ?? ''),
            ],
            'materiais' => Cadastro::opcoes('materiais', $materialId > 0 ? $materialId : null),
            'travado' => $ordem !== null && !in_array($ordem['status'], ['aguardando_autorizacao', 'liberado'], true),
        ];
}
