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
            // Confere uma tabela do sistema, não só a conexão: banco sem as tabelas não está pronto.
            Db::pdo()->query('SELECT 1 FROM usuarios LIMIT 1');
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

    // Página para baixar o app Android do tablet. Aberta sem entrar: o tablet novo ainda não tem ninguém.
    if ($path === 'app') {
        render('app_android', [
            'titulo' => 'App do tablet',
            'noApp' => str_contains((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 'ProsperaApp'),
        ]);
        return;
    }

    // Página do pedido de peças para o Compras: aberta sem entrar, só com o código secreto do pedido.
    if (str_starts_with($path, 'compras/')) {
        tela_compras($path);
        return;
    }

    Auth::exigir();

    if ($path === 'sair' && $method === 'POST') {
        csrf_check();
        $maquina = Auth::admin() ? null : maquina_escolhida();
        $trabalho = $maquina !== null ? maquina_do_trabalho() : null;
        if ($trabalho !== null) {
            tela_sair_no_trabalho($trabalho);
            return;
        }
        if ($maquina !== null && maquina_do_trabalho() === null) {
            $leitura = trim((string) ($_POST['leitura'] ?? ''));
            if ($leitura === '') {
                tela_soltar($maquina, null, 'sair');
                return;
            }
            try {
                horimetro_soltar($leitura, (string) ($_POST['situacao'] ?? ''), (string) ($_POST['problema'] ?? ''), (string) ($_POST['entrada_certa'] ?? ''));
            } catch (InvalidArgumentException $e) {
                tela_soltar($maquina, $e->getMessage(), 'sair');
                return;
            }
        }
        Auth::sair();
        redirect('/login');
    }

    if ($path === 'sair') {
        redirect('/');
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
        exigir_patio();
        tela_patio();
        return;
    }

    if ($path === 'patio/situacao') {
        exigir_patio();
        fluxo_json(patio_marca());
    }

    if ($path === 'patio/resumo') {
        exigir_patio();
        tela_patio_resumo();
        return;
    }

    if (preg_match('#^carregamentos/(\d+)/iniciar$#', $path, $m)) {
        exigir_patio();
        tela_iniciar((int) $m[1], $method);
        return;
    }

    if (preg_match('#^carregamentos/(\d+)/finalizar$#', $path, $m)) {
        exigir_patio();
        tela_finalizar((int) $m[1], $method);
        return;
    }

    if ($path === 'estoque') {
        exigir_papel('operador');
        tela_estoque($method);
        return;
    }

    if (preg_match('#^estoque/(\d+)$#', $path, $m)) {
        exigir_admin();
        tela_estoque_corrigir((int) $m[1], $method);
        return;
    }

    if (preg_match('#^estoque/(\d+)/apagar$#', $path, $m) && $method === 'POST') {
        exigir_admin();
        csrf_check();
        Estoque::apagar((int) $m[1]);
        flash_set('Lançamento apagado.');
        redirect('/estoque');
    }

    if ($path === 'britador') {
        exigir_papel('britador');
        render('britador', [
            'titulo' => 'Britador secundário',
            'britador' => Britador::doDia(date('Y-m-d')),
        ]);
        return;
    }

    if (preg_match('#^britador/(iniciar|terminar|problemas|problemas/(\d+)/resolver)$#', $path, $m) && $method === 'POST') {
        exigir_papel('britador');
        csrf_check();
        try {
            match ($m[1]) {
                'iniciar' => Britador::iniciar(Auth::usuario()),
                'terminar' => Britador::terminar(Auth::usuario()),
                'problemas' => Britador::avisar(Auth::usuario(), (string) ($_POST['descricao'] ?? ''), ($_POST['parou'] ?? '') === '1'),
                default => Britador::resolver((int) $m[2], Auth::usuario()),
            };
            flash_set(match ($m[1]) {
                'iniciar' => 'Britagem iniciada.',
                'terminar' => 'Britagem terminada.',
                'problemas' => 'Problema avisado. Ele aparece no Monitor até ser resolvido.',
                default => 'Problema resolvido.',
            });
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/britador');
    }

    if ($path === 'lavra') {
        exigir_papel('motorista', 'escavadeira');
        tela_lavra();
        return;
    }

    if ($path === 'lavra/situacao') {
        exigir_papel('motorista', 'escavadeira');
        fluxo_json(lavra_marca());
    }

    if ($path === 'lavra/resumo') {
        exigir_papel('motorista');
        tela_lavra_resumo();
        return;
    }

    if (preg_match('#^lavra/caminhoes/(\d+)/fila$#', $path, $m) && $method === 'POST') {
        exigir_papel('motorista');
        csrf_check();
        recusar_toque_apressado('/lavra');
        try {
            $maquina = maquina_escolhida();
            if (!Auth::admin() && ($maquina === null || $maquina['tipo'] !== 'caminhao' || (int) $maquina['id'] !== (int) $m[1])) {
                throw new InvalidArgumentException('Entre na fila com o caminhão que está no menu.');
            }
            if (!Auth::admin() && horimetro_precisa_soltar()) {
                throw new InvalidArgumentException('Lance o horímetro ao sair antes de outra viagem.');
            }
            Lavra::entrarNaFila((int) $m[1], Auth::usuario(), Auth::motorista());
            flash_set('Caminhão chegou na lavra.');
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/lavra');
    }

    if (preg_match('#^lavra/ciclos/(\d+)/avancar$#', $path, $m) && $method === 'POST') {
        exigir_papel('motorista');
        csrf_check();
        recusar_toque_apressado('/lavra');
        try {
            flash_set(Lavra::avancar((int) $m[1], Auth::motorista() ? Auth::usuario() : null, etapa_da_tela()));
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/lavra');
    }

    if (preg_match('#^lavra/ciclos/(\d+)/garagem$#', $path, $m) && $method === 'POST') {
        exigir_papel('motorista');
        csrf_check();
        recusar_toque_apressado('/lavra');
        try {
            flash_set(Lavra::irParaGaragem((int) $m[1], Auth::motorista() ? Auth::usuario() : null, etapa_da_tela()));
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
        exigir_papel('escavadeira');
        csrf_check();
        $retroId = (int) ($_POST['retroescavadeira_id'] ?? 0);
        $retro = $retroId > 0 ? Cadastro::buscar('retros', $retroId) : null;
        $ativa = $retro !== null && (int) ($retro['ativo'] ?? 0) === 1;
        if (!Auth::admin()) {
            // O operador escolhe a escavadeira na tela da máquina, com o horímetro.
            redirect('/maquina');
        }
        if ($ativa) {
            $_SESSION['retroescavadeira_id'] = $retroId;
        } else {
            flash_set('Selecione a escavadeira hidráulica.', 'erro');
        }
        redirect('/lavra');
    }

    if ($path === 'lavra/retro/trocar' && $method === 'POST') {
        exigir_papel('escavadeira');
        csrf_check();
        if (Auth::admin()) {
            unset($_SESSION['retroescavadeira_id']);
            redirect('/lavra');
        }
        redirect('/maquina');
    }

    if (($path === 'lavra/limpeza/iniciar' || $path === 'lavra/limpeza/terminar') && $method === 'POST') {
        exigir_papel('escavadeira');
        csrf_check();
        recusar_toque_apressado('/lavra');
        $retro = retro_escolhida();
        try {
            if ($path === 'lavra/limpeza/iniciar') {
                Lavra::iniciarLimpeza((int) ($retro['id'] ?? 0), Auth::usuario());
                flash_set('Limpeza da lavra começou. A espera dos caminhões na fila agora não pesa no tempo médio.');
            } else {
                flash_set($retro !== null && Lavra::terminarLimpeza((int) $retro['id']) ? 'Limpeza da lavra terminada.' : 'A limpeza já estava terminada.');
            }
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/lavra');
    }

    if (preg_match('#^lavra/ciclos/(\d+)/carga$#', $path, $m)) {
        exigir_papel('escavadeira');
        tela_lavra_iniciar((int) $m[1], $method);
        return;
    }

    if (preg_match('#^lavra/ciclos/(\d+)/concluir-carga$#', $path, $m) && $method === 'POST') {
        exigir_papel('escavadeira');
        csrf_check();
        recusar_toque_apressado('/lavra');
        try {
            flash_set(Lavra::concluirCarga((int) $m[1], Auth::admin() ? null : Auth::usuario(), etapa_da_tela()));
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/lavra');
    }

    if (preg_match('#^lavra/ciclos/(\d+)/ciente$#', $path, $m) && $method === 'POST') {
        exigir_papel('escavadeira');
        csrf_check();
        recusar_toque_apressado('/lavra');
        try {
            Lavra::cienteSemRetorno((int) $m[1], Auth::usuario());
            flash_set('Aviso recebido.');
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/lavra');
    }

    if ($path === 'horimetro') {
        tela_horimetro($method);
        return;
    }

    if ($path === 'manutencao') {
        exigir_admin();
        tela_manutencao();
        return;
    }

    if (preg_match('#^cadastros/(pas|retros|caminhoes)/(\d+)/horimetro$#', $path, $m) && $method === 'POST') {
        exigir_cadastro($m[1]);
        csrf_check();
        $tipo = Horimetro::tipoDaChave($m[1]);
        try {
            Horimetro::lancar($tipo, (int) $m[2], (string) ($_POST['leitura'] ?? ''), Auth::usuario());
            flash_set('Horímetro lançado.');
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/cadastros/' . $m[1] . '/' . $m[2]);
    }

    if (preg_match('#^cadastros/(pas|retros|caminhoes)/(\d+)/liberar$#', $path, $m) && $method === 'POST') {
        exigir_cadastro($m[1]);
        csrf_check();
        try {
            flash_set(liberar_maquina($m[1], (int) $m[2], (string) ($_POST['leitura'] ?? ''), (string) ($_POST['problema'] ?? '')));
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/cadastros/' . $m[1] . '/' . $m[2]);
    }

    if (preg_match('#^cadastros/(pas|retros|caminhoes)/(\d+)/problemas$#', $path, $m) && $method === 'POST') {
        exigir_cadastro($m[1]);
        csrf_check();
        try {
            $tipo = Horimetro::tipoDaChave($m[1]);
            if (Cadastro::buscar($m[1], (int) $m[2]) === null) {
                throw new InvalidArgumentException('Máquina não encontrada.');
            }
            Problemas::relatar($tipo, (int) $m[2], Auth::usuario(), (string) ($_POST['problema'] ?? ''), Horimetro::atual($tipo, (int) $m[2]));
            flash_set('Problema registrado.');
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/cadastros/' . $m[1] . '/' . $m[2]);
    }

    if (preg_match('#^cadastros/(pas|retros|caminhoes)/(\d+)/problemas/(\d+)/resolver$#', $path, $m) && $method === 'POST') {
        exigir_cadastro($m[1]);
        csrf_check();
        try {
            Problemas::resolver((int) $m[3], Horimetro::tipoDaChave($m[1]), (int) $m[2], Auth::usuario(), (string) ($_POST['resolucao'] ?? ''));
            flash_set('Problema marcado como resolvido.');
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/cadastros/' . $m[1] . '/' . $m[2] . '#problemas');
    }

    if (preg_match('#^cadastros/(pas|retros|caminhoes)/(\d+)/horimetro/(\d+)/anular$#', $path, $m) && $method === 'POST') {
        exigir_cadastro($m[1]);
        csrf_check();
        try {
            flash_set(Horimetro::anular(
                (int) $m[3],
                Horimetro::tipoDaChave($m[1]),
                (int) $m[2],
                (string) ($_POST['motivo'] ?? ''),
                Auth::usuario(),
                (string) ($_POST['correta'] ?? '')
            ));
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/cadastros/' . $m[1] . '/' . $m[2]);
    }

    if (preg_match('#^cadastros/(pas|retros|caminhoes)/(\d+)/planos/(\d+)/feito$#', $path, $m) && $method === 'POST') {
        exigir_cadastro($m[1]);
        csrf_check();
        $tipo = Horimetro::tipoDaChave($m[1]);
        try {
            Horimetro::registrar((int) $m[3], $tipo, (int) $m[2], (string) ($_POST['horimetro'] ?? ''), Auth::usuario(), (string) ($_POST['observacoes'] ?? ''));
            flash_set('Serviço registrado.');
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/cadastros/' . $m[1] . '/' . $m[2]);
    }

    if (preg_match('#^cadastros/(pas|retros|caminhoes)/(\d+)/planos/(\d+)/ativo$#', $path, $m) && $method === 'POST') {
        exigir_cadastro($m[1]);
        csrf_check();
        try {
            Horimetro::alternarPlano((int) $m[3], Horimetro::tipoDaChave($m[1]), (int) $m[2]);
            flash_set('Serviço atualizado.');
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/cadastros/' . $m[1] . '/' . $m[2]);
    }

    if (preg_match('#^cadastros/(pas|retros|caminhoes)/(\d+)/planos$#', $path, $m) && $method === 'POST') {
        exigir_cadastro($m[1]);
        csrf_check();
        try {
            Horimetro::criarPlano(Horimetro::tipoDaChave($m[1]), (int) $m[2], (string) ($_POST['nome'] ?? ''), (string) ($_POST['intervalo'] ?? ''));
            flash_set('Serviço incluído.');
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/cadastros/' . $m[1] . '/' . $m[2]);
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

    if (preg_match('#^cadastros/(pas|retros|caminhoes)/(\d+)/pecas/(\d+)/foto$#', $path, $m)) {
        $peca = Pecas::buscar((int) $m[3]);
        $arquivo = $peca !== null && $peca['tipo'] === Horimetro::tipoDaChave($m[1]) && (int) $peca['maquina_id'] === (int) $m[2]
            ? Pecas::arquivoFoto((int) $m[3]) : null;
        if ($arquivo === null) {
            http_response_code(404);
            exit;
        }
        Fotos::servir($arquivo['caminho'], $arquivo['tipo'], Fotos::LADO_MAQUINA);
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

    if (preg_match('#^cadastros/(pas|retros|caminhoes)/(\d+)/pecas(?:/(\d+)(/ativo)?)?$#', $path, $m) && $method === 'POST') {
        csrf_check();
        $tipo = Horimetro::tipoDaChave($m[1]);
        try {
            if (($m[3] ?? '') === '') {
                Pecas::incluir($tipo, (int) $m[2], $_POST);
                flash_set('Peça incluída.');
            } elseif (($m[4] ?? '') !== '') {
                Pecas::alternarAtivo($tipo, (int) $m[2], (int) $m[3]);
                flash_set('Peça atualizada.');
            } else {
                Pecas::editar($tipo, (int) $m[2], (int) $m[3], $_POST);
                flash_set('Peça salva.');
            }
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/cadastros/' . $m[1] . '/' . $m[2] . '#pecas');
    }

    if (preg_match('#^cadastros/(pas|retros|caminhoes)/(\d+)/oficina(/liberar)?$#', $path, $m) && $method === 'POST') {
        csrf_check();
        $tipo = Horimetro::tipoDaChave($m[1]);
        try {
            if (($m[3] ?? '') === '') {
                Oficina::mandar($tipo, (int) $m[2], (string) ($_POST['motivo'] ?? ''), (int) ($_POST['problema_id'] ?? 0), Auth::usuario());
                flash_set('Máquina na oficina. Ninguém consegue assumi-la até ser liberada.');
            } else {
                Oficina::liberar($tipo, (int) $m[2], $_POST, Auth::usuario());
                flash_set('Máquina liberada da oficina. A ordem de serviço ficou gravada.');
            }
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/cadastros/' . $m[1] . '/' . $m[2] . '#oficina');
    }

    if (preg_match('#^cadastros/(pas|retros|caminhoes)/(\d+)/pedidos/novo$#', $path, $m)) {
        tela_pedido_novo($m[1], (int) $m[2], $method);
        return;
    }

    if ($path === 'pedidos-pecas') {
        render('pedidos_pecas', [
            'titulo' => 'Pedidos de peças',
            'pedidos' => Pecas::pedidosRecentes(),
        ]);
        return;
    }

    if (preg_match('#^pedidos-pecas/(\d+)/(trocado|cancelar)$#', $path, $m) && $method === 'POST') {
        csrf_check();
        try {
            if ($m[2] === 'trocado') {
                Pecas::marcarTrocado((int) $m[1], Auth::usuario());
                flash_set('Pedido marcado como trocado.');
            } else {
                Pecas::cancelar((int) $m[1]);
                flash_set('Pedido cancelado.');
            }
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/pedidos-pecas/' . $m[1]);
    }

    if (preg_match('#^pedidos-pecas/(\d+)$#', $path, $m)) {
        tela_pedido_pecas((int) $m[1]);
        return;
    }

    if (preg_match('#^lavra/limpezas/(\d+)/terminar$#', $path, $m) && $method === 'POST') {
        csrf_check();
        flash_set(Lavra::terminarLimpezaPorId((int) $m[1]) ? 'Limpeza da lavra terminada.' : 'A limpeza já estava terminada.');
        redirect('/painel');
    }

    if ($path === 'usuarios') {
        tela_pessoas();
        return;
    }

    if ($path === 'tablets') {
        tela_tablets($method);
        return;
    }

    if (preg_match('#^tablets/(\d+)/revogar$#', $path, $m) && $method === 'POST') {
        csrf_check();
        try {
            Tablets::revogar((int) $m[1]);
            flash_set('Tablet revogado. Ele não mostra mais as fotos da equipe.');
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/tablets');
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

    if ($path === 'painel/situacao') {
        exigir_admin();
        finpro_tentar_pendentes();
        fluxo_json(painel_marca());
    }

    if ($path === 'dashboard') {
        exigir_admin();
        tela_dashboard();
        return;
    }

    if ($path === 'dashboard/dados') {
        exigir_admin();
        finpro_tentar_pendentes();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(Dashboard::instantaneo(), JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($path === 'fila') {
        tela_fila();
        return;
    }

    if ($path === 'fila/situacao') {
        exigir_admin();
        fluxo_json(fila_marca());
    }

    if ($path === 'lavra/fila') {
        tela_lavra_fila();
        return;
    }

    if ($path === 'controle') {
        tela_controle();
        return;
    }

    if ($path === 'ordens/nova/nota') {
        tela_ler_nota($method);
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
        redirect(($_POST['voltar'] ?? '') === 'controle' ? '/controle' : '/ordens/' . $m[1]);
    }

    if (preg_match('#^ordens/(\d+)/valor$#', $path, $m) && $method === 'POST') {
        csrf_check();
        try {
            Operacoes::definirValor((int) $m[1], (string) ($_POST['nf_valor'] ?? ''));
            if (trim((string) ($_POST['nf_pagamento'] ?? '')) !== '') {
                Operacoes::definirPagamento((int) $m[1], (string) $_POST['nf_pagamento']);
            }
            flash_set('Valor da nota salvo.');
            finpro_enviar((int) $m[1]);
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        $dia = (string) ($_POST['dia'] ?? '');
        redirect(($_POST['voltar'] ?? '') === 'controle'
            ? '/controle' . (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia) ? '?dia=' . $dia : '')
            : '/ordens/' . $m[1]);
    }

    if (preg_match('#^ordens/(\d+)/nota$#', $path, $m)) {
        $arquivo = NotaFiscal::arquivoPdf(Operacoes::buscar((int) $m[1]) ?? []);
        if ($arquivo === null) {
            http_response_code(404);
            exit;
        }
        session_write_close();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="nota-' . $m[1] . '.pdf"');
        header('Content-Length: ' . (string) filesize($arquivo));
        header('Cache-Control: private, no-store');
        readfile($arquivo);
        exit;
    }

    if (preg_match('#^ordens/(\d+)/finpro$#', $path, $m) && $method === 'POST') {
        csrf_check();
        $id = (int) $m[1];
        try {
            if (!FinPro::enfileirar($id)) {
                flash_set('Só vai para o Fin Pro a nota Pix ou Transferência que não foi cancelada.', 'erro');
            } else {
                $enviada = FinPro::enviar($id);
                flash_set((string) FinPro::situacaoDa($id), $enviada ? 'ok' : 'erro');
            }
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/ordens/' . $id);
    }

    if (preg_match('#^ordens/(\d+)/carregado$#', $path, $m) && $method === 'POST') {
        csrf_check();
        try {
            flash_set(Operacoes::corrigirCarregado((int) $m[1], (string) ($_POST['quantidade'] ?? ''), Auth::usuario()));
        } catch (InvalidArgumentException $e) {
            flash_set($e->getMessage(), 'erro');
        }
        redirect('/ordens/' . $m[1]);
    }

    if (preg_match('#^ordens/(\d+)/saldo$#', $path, $m) && $method === 'POST') {
        csrf_check();
        try {
            Operacoes::voltarParaSaldo((int) $m[1]);
            flash_set('A viagem voltou para Saldo a buscar.');
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

    if ($method === 'POST') {
        // Antes de tudo: um envio repetido do mesmo formulário só volta para onde o primeiro foi.
        csrf_check();
    }

    if (Auth::ok()) {
        if ($method === 'POST' && login_de_outra_pessoa()) {
            flash_set(Auth::usuario() . ' ainda está conectado neste aparelho. Toque em Sair antes de entrar com outra pessoa.', 'atencao');
        }
        redirect('/');
    }

    $erro = null;
    $usuario = '';
    // Pessoa escolhida pela foto: a tela pede a senha dela.
    $escolhida = null;
    if ($method === 'POST') {
        if (isset($_POST['pessoa'])) {
            // O toque logo depois do Sair é o segundo toque de um toque duplo, não uma escolha de rosto.
            if (toque_apressado()) {
                redirect('/login');
            }
            $escolhida = Tablets::autorizado() ? Pessoas::elegivelEntrada((int) $_POST['pessoa']) : null;
            if ($escolhida === null) {
                $erro = Tablets::autorizado()
                    ? 'Não foi possível entrar com essa pessoa.'
                    : 'Este aparelho não está autorizado para a entrada por foto.';
            } elseif (!isset($_POST['senha'])) {
                // Toque na foto de uma tela aberta antes desta versão: só abre o pedido de senha.
            } elseif (Auth::entrarPorPessoa((int) $escolhida['id'], (string) $_POST['senha'])) {
                redirect('/');
            } else {
                $erro = Auth::falhaLogin() !== '' ? Auth::falhaLogin() : 'A senha não confere. Tente de novo.';
            }
        } else {
            $usuario = trim((string) ($_POST['usuario'] ?? ''));
            if (str_contains($usuario, '@')) {
                $erro = 'Use o usuário, sem e-mail.';
            } elseif (Auth::tentar($usuario, (string) ($_POST['senha'] ?? ''))) {
                if (Auth::senhaFraca()) {
                    flash_set('A sua senha é fraca. Troque em Pessoas por uma com pelo menos ' . Auth::senhaAdminMinima() . ' caracteres.', 'atencao');
                }
                redirect('/');
            } else {
                $erro = Auth::falhaLogin() !== '' ? Auth::falhaLogin() : 'Usuário ou senha não conferem.';
            }
        }
    }

    $tablet = Tablets::autorizado();
    if ($method !== 'POST' && $tablet && isset($_GET['pessoa'])) {
        $escolhida = Pessoas::elegivelEntrada((int) $_GET['pessoa']);
    }
    render('login', [
        'titulo' => 'Entrar',
        'erro' => $erro,
        'usuario' => $usuario,
        'tablet' => $tablet,
        'escolhida' => $escolhida,
        'pessoas' => $tablet && $escolhida === null ? Pessoas::paraEntrada() : [],
    ]);
}

function login_de_outra_pessoa(): bool
{
    if (isset($_POST['pessoa'])) {
        $pessoa = Pessoas::buscar((int) $_POST['pessoa']);
        $nome = $pessoa !== null ? (string) $pessoa['nome'] : '';
    } else {
        $nome = (string) (Auth::nomeSimples((string) ($_POST['usuario'] ?? '')) ?? '');
    }

    return mb_strtolower($nome, 'UTF-8') !== mb_strtolower(Auth::usuario(), 'UTF-8');
}

function tela_entrada_foto(int $id): void
{
    $pessoa = Tablets::autorizado() ? Pessoas::elegivelEntrada($id) : null;
    if ($pessoa === null || empty($pessoa['foto'])) {
        http_response_code(404);
        exit;
    }
    tela_pessoa_foto($id);
}

function recusar_toque_apressado(string $volta): void
{
    if (!toque_apressado()) {
        return;
    }
    if (empty($_SESSION['flash'])) {
        flash_set('Toque repetido ignorado. Confira a tela antes de tocar de novo.', 'atencao');
    }
    redirect($volta);
}

function etapa_da_tela(): ?string
{
    $etapa = trim((string) ($_POST['etapa'] ?? ''));

    return $etapa !== '' ? $etapa : null;
}

function tela_tablets(string $method): void
{
    $erro = null;
    if ($method === 'POST') {
        csrf_check();
        try {
            Tablets::autorizarEste((string) ($_POST['nome'] ?? ''), Auth::usuario());
            flash_set('Tablet autorizado. Saia para a equipe entrar pela foto.');
            redirect('/tablets');
        } catch (InvalidArgumentException $e) {
            $erro = $e->getMessage();
        }
    }

    render('tablets', [
        'titulo' => 'Tablets',
        'este' => Tablets::atual(),
        'tablets' => Tablets::listar(),
        'nome' => (string) ($_POST['nome'] ?? ''),
        'erro' => $erro,
    ]);
}

function fluxo_json(string $marca): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['marca' => $marca, 'hora' => date('H:i:s'), 'agora' => time()], JSON_UNESCAPED_UNICODE);
    exit;
}

function marca_de(array $partes): string
{
    $json = json_encode($partes, JSON_UNESCAPED_UNICODE);

    return hash('sha256', $json === false ? '' : $json);
}

function lavra_marca(): string
{
    $tipo = maquina_escolhida()['tipo'] ?? '';
    $mostraCiclo = Auth::admin() || ($tipo === 'caminhao');
    $mostraCarga = Auth::admin() || ($tipo === 'retro');
    $partes = ['ciclo' => $mostraCiclo, 'carga' => $mostraCarga];
    $partes['limpezas'] = array_map(
        static fn (array $limpeza): array => [(int) $limpeza['id'], (int) $limpeza['retroescavadeira_id']],
        Lavra::limpezasAbertas()
    );
    if (!$mostraCiclo && !$mostraCarga) {
        $maquina = maquina_escolhida();
        $partes['fora'] = $maquina['tipo'] ?? '';
        $partes['id'] = (int) ($maquina['id'] ?? 0);

        return marca_de($partes);
    }
    if ($mostraCarga) {
        $minha = Lavra::cargaDoOperador(Auth::usuario());
        $retro = retro_escolhida();
        $partes['fila'] = array_map(static fn (array $ciclo): array => [
            (int) $ciclo['id'],
            (string) $ciclo['status'],
            $ciclo['inicio_fila'] ?? null,
            $ciclo['operador'] ?? null,
        ], Lavra::naFila());
        $partes['minha'] = $minha === null ? null : [
            (int) $minha['id'],
            $minha['inicio_carga'] ?? null,
            (int) ($minha['retroescavadeira_id'] ?? 0),
        ];
        $partes['retro'] = (int) ($retro['id'] ?? 0);
    }
    if ($mostraCarga && !$mostraCiclo) {
        $partes['voltando'] = array_map(static fn (array $ciclo): array => [
            (int) $ciclo['id'],
            $ciclo['inicio_retorno'] ?? $ciclo['chegada_britador'] ?? null,
            $ciclo['placa'] ?? null,
        ], Lavra::voltando());
        $partes['chegou'] = array_map(static fn (array $caminhao): array => [
            (int) $caminhao['id'],
            $caminhao['fim_ciclo'] ?? null,
        ], Lavra::chegouHoje());
        $partes['semRetorno'] = array_map(static fn (array $caminhao): array => [
            (int) $caminhao['id'],
            $caminhao['fim_ciclo'] ?? null,
            $caminhao['encerramento'] ?? null,
        ], Lavra::semRetornoHoje());
    }
    if ($mostraCiclo) {
        $motorista = !Auth::admin() && Auth::motorista();
        $painel = $motorista ? Lavra::painelDoMotorista(Auth::usuario()) : Lavra::painel();
        if ($tipo === 'caminhao') {
            $id = (int) (maquina_escolhida()['id'] ?? 0);
            $painel['livres'] = array_values(array_filter(
                $painel['livres'],
                static fn (array $caminhao): bool => (int) $caminhao['id'] === $id
            ));
        }
        $partes['em'] = array_map(static fn (array $ciclo): array => [
            (int) $ciclo['id'],
            (string) $ciclo['status'],
            $ciclo['inicio_carga'] ?? null,
            $ciclo['fim_carga'] ?? null,
            $ciclo['chegada_britador'] ?? null,
            $ciclo['inicio_retorno'] ?? null,
            $ciclo['operador_carga'] ?? null,
            (int) ($ciclo['retroescavadeira_id'] ?? 0),
        ], $painel['emCiclo']);
        $partes['livres'] = array_map(static fn (array $caminhao): int => (int) $caminhao['id'], $painel['livres']);
        $partes['soltar'] = horimetro_precisa_soltar();
    }

    return marca_de($partes);
}

/** O que o operador precisa saber de uma carga. Se mudar, o tablet do pátio avisa "Carga alterada". */
function patio_assinatura(array $ordem): string
{
    return substr(md5(json_encode([
        (string) $ordem['cliente'],
        (string) $ordem['pedido'],
        (string) $ordem['nf'],
        (int) $ordem['material_id'],
        (string) $ordem['quantidade_autorizada'],
        (string) $ordem['unidade'],
        (int) ($ordem['fracionado'] ?? 0),
        trim((string) ($ordem['observacoes'] ?? '')),
    ])), 0, 12);
}

/**
 * Para o tablet do pátio avisar (som, vibração e aviso na tela): as cargas na fila, com a assinatura,
 * e as que foram canceladas depois de liberadas. O tablet compara com o que já tinha visto.
 */
function patio_avisos(): array
{
    $resumo = static fn (array $ordem): array => [
        'id' => (int) $ordem['id'],
        'ordem' => (string) $ordem['ordem'],
        'cliente' => (string) $ordem['cliente'],
        'carga' => formatar_quantidade((string) $ordem['quantidade_autorizada']) . ' ' . unidade_rotulo((string) $ordem['unidade'])
            . ' · ' . (string) ($ordem['material_nome'] ?? ''),
        'obs' => observacao_recente($ordem['observacoes'] ?? null),
        'assinatura' => patio_assinatura($ordem),
    ];

    return [
        'fila' => array_map($resumo, Operacoes::patio()),
        'canceladas' => array_map($resumo, Operacoes::canceladasRecentes()),
        'retiradas' => array_map($resumo, Operacoes::retiradasRecentes()),
    ];
}

/**
 * O recado mais recente das observações (o último parágrafo escrito por alguém), curto, para o tablet do pátio.
 * As linhas que o sistema acrescenta ("[17/10/2026 09:19] Viagem 2 do CAR-006...") ficam de fora; o que a pessoa
 * escreveu logo embaixo delas, sem linha em branco, continua.
 */
function observacao_recente(?string $observacoes): string
{
    $semSistema = preg_replace('/^[ \t]*\[\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}[^\]\n]*\].*$/m', '', str_replace("\r", '', (string) $observacoes)) ?? '';
    $partes = array_values(array_filter(
        array_map('trim', preg_split('/\n\s*\n/', $semSistema) ?: []),
        static fn (string $parte): bool => $parte !== ''
    ));
    $ultima = (string) (end($partes) ?: '');

    return mb_strlen($ultima, 'UTF-8') > 200 ? mb_substr($ultima, 0, 199, 'UTF-8') . '…' : $ultima;
}

function patio_marca(): string
{
    return marca_de([
        'ordens' => array_map(static fn (array $ordem): array => [
            (int) $ordem['id'],
            (string) $ordem['status'],
            $ordem['inicio_carregamento'] ?? null,
            $ordem['operador'] ?? null,
            (int) ($ordem['pa_carregadeira_id'] ?? 0),
            (int) ($ordem['retroescavadeira_id'] ?? 0),
            patio_assinatura($ordem),
        ], Operacoes::patio()),
        'enviadas' => array_map(static fn (array $ordem): array => [
            (int) $ordem['id'],
            (string) $ordem['status'],
            $ordem['fim_carregamento'] ?? null,
        ], Operacoes::enviadasHoje()),
        'ocupado' => Auth::admin() ? null : patio_ocupado(),
    ]);
}

function painel_marca(): string
{
    $lavra = Lavra::painel();
    $dia = date('Y-m-d');
    $cargaDia = Operacoes::resumo($dia);
    $lavraDia = Lavra::resumo($dia);

    return marca_de([
        'contagens' => Operacoes::contagens(),
        'carregando' => array_map(static fn (array $ordem): array => [
            (int) $ordem['id'],
            (string) $ordem['status'],
            $ordem['inicio_carregamento'] ?? null,
            $ordem['operador'] ?? null,
        ], Operacoes::listar('carregando', '')),
        'ciclos' => array_map(static fn (array $ciclo): array => [
            (int) $ciclo['id'],
            (string) $ciclo['status'],
            $ciclo['inicio_carga'] ?? null,
            $ciclo['fim_carga'] ?? null,
            $ciclo['chegada_britador'] ?? null,
            $ciclo['inicio_retorno'] ?? null,
        ], $lavra['emCiclo']),
        'livres' => count($lavra['livres']),
        'patio' => (int) ($cargaDia['maquina']['total'] ?? 0),
        'viagens' => (int) ($lavraDia['caminhao']['viagens'] ?? 0),
        'toneladas' => $lavraDia['caminhao']['producao']['toneladas'] ?? null,
        'problemas' => array_map(static fn (array $problema): int => (int) $problema['id'], Problemas::abertos()),
        'vendas' => Operacoes::vendas($dia),
        'limpezas' => array_map(static fn (array $limpeza): int => (int) $limpeza['id'], Lavra::limpezasAbertas()),
    ]);
}

function fila_consulta(): array
{
    $situacao = (string) ($_GET['situacao'] ?? 'abertas');
    $busca = trim((string) ($_GET['q'] ?? ''));
    if (!in_array($situacao, ['abertas', 'todas', ...array_keys(status_lista())], true)) {
        $situacao = 'abertas';
    }

    return [$situacao, $busca];
}

function fila_marca(): string
{
    [$situacao, $busca] = fila_consulta();

    return marca_de([
        'situacao' => $situacao,
        'q' => $busca,
        'contagens' => Operacoes::contagens(),
        'ordens' => array_map(static fn (array $ordem): array => [
            (int) $ordem['id'],
            (string) $ordem['status'],
            $ordem['atualizado_em'] ?? null,
            $ordem['operador'] ?? null,
            (int) ($ordem['pa_carregadeira_id'] ?? 0),
        ], Operacoes::listar($situacao, $busca)),
    ]);
}

/** O que impede o operador de iniciar outra carga no pátio agora. */
function patio_ocupado(): ?string
{
    $ordem = Operacoes::ordemDoOperador(Auth::usuario());
    if ($ordem !== null) {
        return 'Finalize a ' . $ordem . ' antes de iniciar outra.';
    }
    $carga = Lavra::cargaDoOperador(Auth::usuario());
    if ($carga !== null) {
        return 'Conclua a carga do ' . $carga['placa'] . ' na lavra antes de iniciar no pátio.';
    }

    return null;
}

/** No tablet, com a máquina do turno já escolhida, o toque em "Iniciar carregamento" no pátio já inicia. */
function patio_um_toque(): bool
{
    if (Auth::admin()) {
        return false;
    }
    $maquina = maquina_escolhida();

    return $maquina !== null
        && ($maquina['tipo'] === 'pa' || ($maquina['tipo'] === 'retro' && Cadastro::opcoes('pas') === []));
}

function tela_patio(): void
{
    render('patio', [
        'titulo' => 'Pátio',
        'corpo' => 'patio',
        'ocupado' => Auth::admin() ? null : patio_ocupado(),
        'umToque' => patio_um_toque(),
        'ordens' => Operacoes::patio(),
        'enviadas' => Operacoes::enviadasHoje(),
        'fluxoUrl' => url('patio/situacao'),
        'fluxoMarca' => patio_marca(),
        // Só o tablet do operador avisa; o administrativo não precisa do som.
        'avisosPatio' => Auth::admin() ? null : patio_avisos(),
    ]);
}

function tela_patio_resumo(): void
{
    $dia = trim((string) ($_GET['dia'] ?? date('Y-m-d')));
    $visao = (($_GET['visao'] ?? '') === 'operador') ? 'operador' : 'maquina';
    try {
        $resumo = Operacoes::resumo($dia, Auth::admin() ? null : Auth::usuario());
    } catch (InvalidArgumentException) {
        $dia = date('Y-m-d');
        $resumo = Operacoes::resumo($dia, Auth::admin() ? null : Auth::usuario());
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

function tela_estoque(string $method): void
{
    $erro = null;
    $form = [];
    $paDoTurno = null;
    if (!Auth::admin()) {
        $maquina = maquina_escolhida();
        if (($maquina['tipo'] ?? '') === 'pa') {
            $paDoTurno = $maquina + ['concha_m3' => Cadastro::buscar('pas', (int) $maquina['id'])['concha_m3'] ?? null];
        }
    }
    if ($method === 'POST') {
        csrf_check();
        try {
            Estoque::lancar($_POST, $paDoTurno, Auth::usuario(), Auth::admin());
            flash_set('Lançado no estoque.');
            redirect('/estoque');
        } catch (InvalidArgumentException $e) {
            $erro = $e->getMessage();
            $form = $_POST;
        }
    }

    render('estoque', [
        'titulo' => 'Estoque',
        'erro' => $erro,
        'form' => $form,
        'paDoTurno' => $paDoTurno,
        'saldos' => Estoque::saldos(),
        'materiais' => Estoque::materiais(),
        'pas' => Auth::admin() ? Cadastro::opcoes('pas') : [],
        'lancamentos' => Estoque::recentes(),
    ]);
}

function tela_estoque_corrigir(int $id, string $method): void
{
    $lancamento = Estoque::buscar($id);
    if ($lancamento === null) {
        http_response_code(404);
        render('erro', ['titulo' => 'Não encontrado', 'mensagem' => 'Esse lançamento não existe.']);
        return;
    }
    $erro = null;
    if ($method === 'POST') {
        csrf_check();
        try {
            Estoque::corrigir($id, $_POST);
            flash_set('Lançamento corrigido.');
            redirect('/estoque');
        } catch (InvalidArgumentException $e) {
            $erro = $e->getMessage();
            $lancamento = array_merge($lancamento, array_intersect_key($_POST, array_flip(['material_id', 'conchas', 'colocado_em', 'observacoes'])));
        }
    }

    render('estoque_corrigir', [
        'titulo' => 'Corrigir lançamento',
        'erro' => $erro,
        'lancamento' => $lancamento,
        'materiais' => Estoque::materiais((int) $lancamento['material_id']),
    ]);
}

function tela_iniciar(int $id, string $method): void
{
    $ordem = ordem_ou_404($id);
    $erro = null;
    // Quem não é do administrativo carrega sempre no próprio nome, como na lavra.
    $operador = Auth::admin() ? trim((string) ($_POST['operador'] ?? '')) : Auth::usuario();
    $paId = (int) ($_POST['pa_carregadeira_id'] ?? 0);
    $pas = Cadastro::opcoes('pas');
    $semPa = $pas === [];
    // Sem pá cadastrada o pátio carrega com a escavadeira: a lista é de quem opera escavadeira.
    $operadores = Auth::admin() ? Pessoas::operadores($semPa ? 'retro' : 'pa') : [];
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

    if ($method === 'POST' && Auth::admin() && $operadores !== [] && !in_array($operador, $operadores, true)) {
        csrf_check();
        $erro = 'Escolha um operador da lista.';
    } elseif ($method === 'POST' && ($retroPatio !== null || (Auth::admin() && $semPa))) {
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
        'oficina' => Oficina::abertas(),
        'operador' => $operador,
        'operadores' => $operadores,
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
    // Com duas pás, a carga em andamento de um operador aparece no tablet do outro: só quem iniciou finaliza
    // (o administrativo também pode, para resolver uma carga esquecida).
    $dono = trim((string) ($ordem['operador'] ?? ''));
    if (!Auth::admin() && $ordem['status'] === 'carregando' && $dono !== '' && $dono !== Auth::usuario()) {
        flash_set('Esta carga está com ' . $dono . '. Só quem iniciou finaliza (ou o administrativo).', 'erro');
        redirect('/patio');
    }
    $erro = null;
    $nota = (string) ($_POST['nota'] ?? '');
    $quantidade = (string) ($_POST['quantidade'] ?? '');

    if ($method === 'POST') {
        csrf_check();
        try {
            Operacoes::finalizar($id, $nota, Auth::usuario(), $quantidade, Auth::admin() ? null : Auth::usuario());
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
        'quantidade' => $quantidade,
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
    // O britador secundário não é máquina do cadastro: quem opera ele não assume máquina.
    if (Auth::admin() || Auth::britador()) {
        return;
    }
    $trabalho = maquina_do_trabalho();
    if ($trabalho !== null) {
        maquina_adotar($trabalho['tipo'], $trabalho['id']);

        return;
    }
    $escolhida = maquina_escolhida();
    if ($escolhida === null) {
        redirect('/maquina');
    }
    // A máquina está num trabalho de outra pessoa (registro de antes da posse ir para o banco): a posse não vale.
    if (Cadastro::emTrabalho((string) maquina_chave($escolhida['tipo']), (int) $escolhida['id'])) {
        Posse::fechar(Auth::id());
        redirect('/maquina');
    }
}

function tela_maquina(string $method): void
{
    if (Auth::admin()) {
        redirect('/painel');
    }

    $travada = maquina_do_trabalho();
    if ($travada !== null) {
        maquina_adotar($travada['tipo'], $travada['id']);
    }

    $erro = null;
    $atual = maquina_escolhida();
    $soltar = $travada === null && $atual !== null;
    if ($method === 'POST' && $travada === null) {
        csrf_check();
        // Um toque repetido em Confirmar não pode soltar a máquina que acabou de ser assumida.
        $acao = (string) ($_POST['acao'] ?? '');
        if ($soltar && $acao === 'assumir') {
            redirect(maquina_destino());
        }
        if (!$soltar && $acao === 'soltar') {
            redirect('/maquina');
        }
        if ($soltar) {
            try {
                horimetro_soltar((string) ($_POST['leitura'] ?? ''), (string) ($_POST['situacao'] ?? ''), (string) ($_POST['problema'] ?? ''), (string) ($_POST['entrada_certa'] ?? ''));
                redirect('/maquina');
            } catch (InvalidArgumentException $e) {
                $erro = $e->getMessage();
            }
        } else {
            $bruto = (string) ($_POST['maquina'] ?? '');
            if (preg_match('/^(caminhao|pa|retro):(\d+)$/', $bruto, $partes)) {
                try {
                    horimetro_assumir($partes[1], (int) $partes[2], (string) ($_POST['leitura'] ?? ''));
                    redirect(maquina_destino());
                } catch (InvalidArgumentException $e) {
                    if (maquina_do_trabalho() !== null) {
                        // Um trabalho com outra máquina começou neste instante: a tela volta mostrando essa máquina.
                        flash_set($e->getMessage(), 'erro');
                        redirect('/maquina');
                    }
                    $erro = $e->getMessage();
                }
            } else {
                $erro = 'Escolha uma máquina livre.';
            }
        }
    }

    $escolher = $travada === null && !$soltar;
    render('maquina', [
        'titulo' => 'Máquina',
        'corpo' => 'lavra',
        'maquinas' => $escolher ? maquinas_livres() : [],
        'comOutros' => $escolher ? maquinas_com_outros() : [],
        'atual' => $atual,
        'travada' => $travada !== null,
        'soltar' => $soltar,
        'destino' => 'maquina',
        'erro' => $erro,
        'situacao' => $erro !== null ? (string) ($_POST['situacao'] ?? '') : '',
        'problema' => $erro !== null ? (string) ($_POST['problema'] ?? '') : '',
    ]);
}

function tela_lavra(): void
{
    $tipo = maquina_escolhida()['tipo'] ?? '';
    $mostraCiclo = Auth::admin() || ($tipo === 'caminhao');
    $mostraCarga = Auth::admin() || ($tipo === 'retro');
    $retroDaTela = $mostraCarga ? retro_escolhida() : null;
    $carga = $mostraCarga ? [
        'filaCarga' => Lavra::naFila(),
        'minhaCarga' => Lavra::cargaDoOperador(Auth::usuario()),
        'retroEscolhida' => $retroDaTela,
        'retros' => Cadastro::opcoes('retros'),
        'limpeza' => $retroDaTela !== null ? Lavra::limpezaAberta((int) $retroDaTela['id']) : null,
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
            'mostrarMovimento' => true,
            'voltando' => Lavra::voltando(),
            'chegou' => Lavra::chegouHoje(),
            'semRetorno' => Lavra::semRetornoHoje(),
            'fluxoUrl' => url('lavra/situacao'),
            'fluxoMarca' => lavra_marca(),
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
        'garagem' => $motorista && $tipo === 'caminhao' && $painel['emCiclo'] === [],
        'precisaSoltar' => horimetro_precisa_soltar(),
        'limpezas' => Lavra::limpezasAbertas(),
        'semCadastro' => Cadastro::listar('caminhoes') === [],
        'mostraCarga' => $mostraCarga,
        'fluxoUrl' => url('lavra/situacao'),
        'fluxoMarca' => lavra_marca(),
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
    recusar_toque_apressado('/lavra');
    $operador = (string) ($_POST['operador'] ?? Auth::usuario());
    $retroId = (int) ($_POST['retroescavadeira_id'] ?? 0);
    if (!Auth::admin()) {
        $maquina = maquina_escolhida();
        $retroId = ($maquina !== null && $maquina['tipo'] === 'retro') ? (int) $maquina['id'] : 0;
        $operador = Auth::usuario();
    }
    try {
        Lavra::iniciarCarga($id, $retroId, $operador, etapa_da_tela());
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
        'limpeza' => (int) ($resumo['limpeza'] ?? 0),
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
    $problema = !empty($_POST['problema']);
    if ($method === 'POST') {
        csrf_check();
        try {
            // Oficina, ou "é problema no caminhão" marcado: vira problema da máquina na hora (Painel, Manutenção e TV),
            // sem esperar o motorista soltar o caminhão.
            $descricao = null;
            if ($motivo === 'oficina') {
                $descricao = 'Foi para a oficina' . (trim($outro) !== '' ? ': ' . trim($outro) : '');
            } elseif ($problema) {
                if (trim($outro) === '') {
                    throw new InvalidArgumentException('Escreva o que aconteceu com o caminhão.');
                }
                $descricao = trim($outro);
            }
            if ($descricao !== null) {
                $descricao = Problemas::texto($descricao);
            }
            $aviso = Lavra::encerrar($id, $motivo, $outro, Auth::usuario(), Auth::motorista() ? Auth::usuario() : null);
            if ($descricao !== null) {
                Problemas::relatar('caminhao', (int) $ciclo['caminhao_id'], Auth::usuario(), $descricao, Horimetro::atual('caminhao', (int) $ciclo['caminhao_id']));
                $aviso .= ' O problema foi avisado ao escritório.';
            }
            if (!Auth::admin() && (string) ($ciclo['operador'] ?? '') === Auth::usuario()) {
                horimetro_marcar_soltar();
            }
            flash_set($aviso, 'atencao');
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
        'problema' => $problema,
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
    $turnos = Horimetro::turnosDo($nome, $desde);

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
        'turnos' => $turnos,
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
        'fluxoUrl' => url('painel/situacao'),
        'fluxoMarca' => painel_marca(),
        'autorizar' => (int) ($contagens['aguardando_autorizacao'] ?? 0),
        'saldos' => (int) ($contagens['saldo'] ?? 0),
        'problemas' => Problemas::abertos(),
        'limpezas' => Lavra::limpezasAbertas(),
        'vendas' => Operacoes::vendas($dia),
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
    [$situacao, $busca] = fila_consulta();

    render('fila', [
        'titulo' => 'Fila',
        'corpo' => 'fila',
        'ordens' => Operacoes::listar($situacao, $busca),
        'contagens' => Operacoes::contagens(),
        'situacao' => $situacao,
        'busca' => $busca,
        'fluxoUrl' => url('fila/situacao?' . http_build_query(['situacao' => $situacao, 'q' => $busca])),
        'fluxoMarca' => fila_marca(),
    ]);
}

function tela_lavra_fila(): void
{
    $situacao = (string) ($_GET['situacao'] ?? 'abertas');
    $busca = trim((string) ($_GET['q'] ?? ''));
    $validas = ['abertas', 'todas', 'na_fila', 'carregando', 'em_viagem', 'no_britador', 'retornando', 'concluido', 'cancelado'];
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
            finpro_enviar($id);
            redirect('/ordens/' . $id);
        } catch (InvalidArgumentException $e) {
            $ordem = ordem_ou_404($id);
            render('ordem_form', dados_formulario($ordem, $_POST, $e->getMessage()));
            return;
        }
    }

    $ordem = ordem_ou_404($id);
    $editavel = in_array($ordem['status'], ['aguardando_autorizacao', 'liberado', 'carregando', 'aguardando_conferencia', 'saldo'], true);
    $finpro = FinPro::situacaoDa($id);
    render('ordem', [
        'titulo' => $ordem['ordem'],
        'ordem' => $ordem,
        'grupo' => (int) ($ordem['fracionado'] ?? 0) === 1 ? Operacoes::viagensDe(Operacoes::grupo($ordem)) : null,
        'editavel' => $editavel,
        'formulario' => $editavel ? dados_formulario($ordem, null, null) : null,
        'finpro' => $finpro !== null ? ['texto' => $finpro, 'reenviar' => FinPro::elegivel($ordem)] : null,
    ]);
}

/** Manda a nota ao Fin Pro na hora, se for Pix ou Transferência; se falhar, ela fica na fila. Nunca impede a tela de seguir. */
function finpro_enviar(int $ordemId): void
{
    try {
        if (FinPro::enfileirar($ordemId)) {
            FinPro::enviar($ordemId);
        }
    } catch (Throwable $e) {
        error_log('Fin Pro: ' . $e->getMessage());
    }
}

/** As telas que se atualizam sozinhas (Monitor e Painel) aproveitam a consulta para tentar de novo as notas da fila. */
function finpro_tentar_pendentes(): void
{
    // A sessão fica livre enquanto espera o Fin Pro: as outras telas do mesmo aparelho não ficam presas.
    session_write_close();
    try {
        FinPro::tentarPendentes();
    } catch (Throwable $e) {
        error_log('Fin Pro: ' . $e->getMessage());
    }
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
            NotaFiscal::ligarPdf($novo, (string) ($_POST['nota_arquivo'] ?? ''));
            finpro_enviar($novo);
            redirect('/ordens/' . $novo);
        } catch (InvalidArgumentException $e) {
            $erro = $e->getMessage();
        }
    }

    render('ordem_form', dados_formulario(null, $post, $erro));
}

/** Lê a nota fiscal (PDF ou XML) e abre a nova ordem já preenchida, para conferir e lançar. */
function tela_ler_nota(string $method): void
{
    if ($method !== 'POST') {
        redirect('/ordens/nova');
    }
    csrf_check();
    $dados = dados_formulario(null, null, null);
    try {
        $nota = NotaFiscal::receber();
    } catch (InvalidArgumentException $e) {
        $dados['erro'] = $e->getMessage();
        render('ordem_form', $dados);
        return;
    }

    $materialId = NotaFiscal::materialDoCadastro($nota['material'], $dados['materiais']);
    $dados['valor'] = array_merge($dados['valor'], [
        'cliente' => (string) ($nota['cliente'] ?? ''),
        'pedido' => (string) ($nota['pedido'] ?? ''),
        'nf' => (string) ($nota['nf'] ?? ''),
        'material_id' => $materialId ?? 0,
        'quantidade_autorizada' => $nota['quantidade'] !== null ? formatar_quantidade($nota['quantidade']) : '',
        // Unidade que a nota traz e o sistema não conhece: fica em branco, para a pessoa escolher (não vira tonelada sozinha).
        'unidade' => (string) ($nota['unidade'] ?? (($nota['unidade_nota'] ?? '') !== '' ? '' : $dados['valor']['unidade'])),
        'nf_emitida_em' => data_hora_para_input($nota['emitida_em']),
        'nf_emitida_data' => (string) ($nota['data_emissao'] ?? ''),
        'nf_chave' => (string) ($nota['chave'] ?? ''),
        'nf_valor' => $nota['valor'] !== null ? formatar_quantidade($nota['valor']) : '',
        'nf_pagamento' => (string) ($nota['pagamento'] ?? ''),
    ]);
    if ($nota['origem'] === 'pdf') {
        $dados['valor']['nota_arquivo'] = NotaFiscal::guardarPdf() ?? '';
    }
    $dados['leitura'] = leitura_da_nota($nota, $materialId);
    render('ordem_form', $dados);
}

/**
 * O que a tela diz depois de ler a nota: o que foi lido e o que precisa de atenção.
 *
 * @return array{mensagem: string, avisos: list<string>}
 */
function leitura_da_nota(array $nota, ?int $materialId): array
{
    $origem = $nota['origem'] === 'xml' ? 'do XML' : 'do PDF';
    $numero = $nota['nf'] !== null ? 'Nota ' . $nota['nf'] . ($nota['serie'] !== '' && $nota['serie'] !== '1' ? ' (série ' . $nota['serie'] . ')' : '') : 'Nota';
    $avisos = [];
    if ($nota['nf'] === null) {
        $avisos[] = 'Não achei o número da nota. Preencha a nota fiscal.';
    }
    if ($nota['cliente'] === null) {
        $avisos[] = 'Não achei o cliente na nota. Preencha o cliente.';
    }
    if ($nota['pedido'] === null) {
        $avisos[] = 'Não achei o número do pedido na nota. Preencha o pedido.';
    }
    if ($nota['material'] === null) {
        $avisos[] = 'Não achei o material na nota. Escolha o material.';
    } elseif ($materialId === null) {
        $avisos[] = 'O material da nota é "' . $nota['material'] . '", que não está no cadastro com esse nome. Escolha o material.';
    }
    if ($nota['outros_materiais'] !== []) {
        $avisos[] = 'A nota tem outros materiais (' . implode(', ', $nota['outros_materiais']) . '). O carregamento usa só o primeiro.';
    }
    if ($nota['quantidade'] === null) {
        $avisos[] = 'Não achei a quantidade na nota. Preencha a quantidade.';
    }
    if (($nota['outras_unidades'] ?? []) !== []) {
        $avisos[] = 'A nota tem o mesmo material em outra unidade (' . implode(', ', $nota['outras_unidades']) . '). Somei só os itens em "'
            . $nota['unidade_nota'] . '". Confira a quantidade.';
    }
    if ($nota['unidade'] === null) {
        $avisos[] = $nota['unidade_nota'] !== null && $nota['unidade_nota'] !== ''
            ? 'A unidade da nota ("' . $nota['unidade_nota'] . '") não é t, m³ nem un. Confira a unidade.'
            : 'Não achei a unidade na nota. Confira a unidade.';
    }
    if ($nota['valor'] === null) {
        $avisos[] = 'Não achei o valor total da nota. Preencha o valor, para somar no vendido do dia.';
    }
    if ($nota['emitida_em'] === null) {
        $avisos[] = !empty($nota['data_emissao'])
            ? 'O ' . ($nota['origem'] === 'xml' ? 'XML' : 'PDF') . ' desta nota mostra só a data da emissão (' . date('d/m/Y', (int) strtotime((string) $nota['data_emissao'])) . '), sem a hora. A nota conta no vendido desse dia. Para gravar a hora, leia o XML da nota ou preencha "Nota emitida em".'
            : 'Não achei a hora em que a nota foi emitida. Preencha "Nota emitida em", se souber.';
    }
    if (($nota['pagamento'] ?? null) === null) {
        $avisos[] = 'Escolha a forma de pagamento: ' . ($nota['origem'] === 'xml' ? 'o XML' : 'o PDF') . ' da nota não traz essa informação.';
    }
    $repetida = Operacoes::ordemComNota($nota['chave'], $nota['nf']);
    if ($repetida !== null) {
        $avisos[] = 'Já existe o carregamento ' . $repetida['ordem'] . ' com esta nota. Confira antes de lançar de novo.';
    }

    return [
        'mensagem' => $numero . ' lida ' . $origem . '. Confira os campos e toque em Lançar ordem.',
        'avisos' => $avisos,
    ];
}

function data_hora_para_input(?string $dataHora): string
{
    if ($dataHora === null || $dataHora === '') {
        return '';
    }
    $ts = strtotime($dataHora);

    return $ts ? date('Y-m-d\TH:i:s', $ts) : '';
}

/** Controle de carga do administrativo: vendido, carregado e saldo das notas. */
function tela_controle(): void
{
    $dia = trim((string) ($_GET['dia'] ?? date('Y-m-d')));
    $data = DateTimeImmutable::createFromFormat('!Y-m-d', $dia);
    if (!$data || $data->format('Y-m-d') !== $dia) {
        $dia = date('Y-m-d');
        $data = new DateTimeImmutable($dia);
    }
    $controle = Operacoes::controle($dia);

    render('controle', [
        'titulo' => 'Controle de carga',
        'corpo' => 'fila',
        'dia' => $dia,
        'anterior' => $data->modify('-1 day')->format('Y-m-d'),
        'seguinte' => $data->modify('+1 day')->format('Y-m-d'),
        'vendas' => Operacoes::vendas($dia),
        'carregado' => Operacoes::carregadoNoDia($dia),
        'notas' => $controle['notas'],
        'saldos' => $controle['saldos'],
    ]);
}

function tela_cancelar(int $id, string $method): void
{
    $ordem = ordem_ou_404($id);
    $erro = null;
    if ($method === 'POST') {
        csrf_check();
        try {
            Operacoes::cancelar($id, (string) ($_POST['motivo'] ?? ''), Auth::usuario());
            flash_set($ordem['status'] === 'saldo' ? 'Saldo encerrado.' : 'Ordem cancelada.');
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

function tela_sair_no_trabalho(array $trabalho): void
{
    $ciclo = $trabalho['tipo'] === 'caminhao' ? Lavra::abertoDo(Auth::usuario()) : null;
    render('sair_trabalho', [
        'titulo' => 'Sair',
        'corpo' => 'lavra',
        'trabalho' => $trabalho,
        'ciclo' => $ciclo,
        'destino' => match ($trabalho['tipo']) {
            'pa' => 'patio',
            default => 'lavra',
        },
    ]);
}

/**
 * O administrativo libera uma máquina que ficou com alguém (esqueceu de soltar, faltou, sessão caiu).
 * Com a leitura do painel, o turno de quem estava com ela é gravado; sem leitura, o turno fica sem as horas.
 */
function liberar_maquina(string $chave, int $id, string $leitura, string $problema = ''): string
{
    $tipo = Horimetro::tipoDaChave($chave);
    $descricao = trim($problema) !== '' ? Problemas::texto($problema) : null;

    return (string) Db::comTrava(Posse::TRAVA, static function () use ($chave, $tipo, $id, $leitura, $descricao): string {
        $posse = Posse::daMaquina($tipo, $id);
        if ($posse === null) {
            return 'Essa máquina já estava livre.';
        }
        if (Cadastro::emTrabalho($chave, $id)) {
            throw new InvalidArgumentException('Essa máquina está em trabalho agora. Conclua ou cancele o trabalho antes de liberar.');
        }
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $turno = false;
            $saida = null;
            if (trim($leitura) !== '') {
                $saida = Horimetro::lancar($tipo, $id, $leitura, Auth::usuario());
                $entrada = $posse['entrada'] !== null ? (float) $posse['entrada'] : null;
                if ($entrada !== null && round($saida, 1) >= round($entrada, 1)) {
                    Horimetro::registrarTurno($tipo, $id, $entrada, $saida, (string) $posse['iniciado_em'], (string) $posse['usuario'], $descricao !== null ? false : null);
                    $turno = true;
                }
            }
            if ($descricao !== null) {
                Problemas::relatar($tipo, $id, Auth::usuario(), $descricao, $saida ?? Horimetro::atual($tipo, $id));
            }
            if ($tipo === 'retro') {
                Lavra::terminarLimpeza($id);
            }
            Posse::liberar($tipo, $id);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $texto = 'Máquina liberada. Estava com ' . $posse['usuario'];
        if ($turno) {
            $texto .= '; o turno foi gravado';
        }
        if ($descricao !== null) {
            $texto .= $turno ? ' e o problema também' : '; o problema foi registrado';
        }

        return $texto . '.';
    });
}

function tela_soltar(array $maquina, ?string $erro, string $destino): void
{
    render('maquina', [
        'titulo' => 'Soltar máquina',
        'corpo' => 'lavra',
        'maquinas' => [],
        'atual' => $maquina,
        'travada' => false,
        'soltar' => true,
        'destino' => $destino,
        'erro' => $erro,
        'situacao' => $erro !== null ? (string) ($_POST['situacao'] ?? '') : '',
        'problema' => $erro !== null ? (string) ($_POST['problema'] ?? '') : '',
    ]);
}

function tela_horimetro(string $method): void
{
    if (Auth::admin()) {
        redirect('/manutencao');
    }
    redirect('/maquina');
}

function tela_manutencao(): void
{
    render('manutencao', [
        'titulo' => 'Manutenção',
        'grupos' => Horimetro::painel(),
        'oficina' => Oficina::abertas(),
        'disponibilidade' => array_map(Oficina::textoDisponibilidade(...), Oficina::disponibilidades()),
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
                'funcoes' => Pessoas::funcoesDoFormulario($_POST),
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
    Fotos::servir($arquivo['caminho'], $arquivo['tipo'], Fotos::LADO_PESSOA);
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
    Fotos::servir($arquivo['caminho'], $arquivo['tipo'], Fotos::LADO_MAQUINA);
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
                'concha_m3' => (string) ($_POST['concha'] ?? ''),
                'estoque' => isset($_POST['estoque']) ? 1 : 0,
                'foto' => is_array($item) ? ($item['foto'] ?? null) : null,
            ];
        }
    }

    render('cadastro_form', [
        'titulo' => $item === null || $id === null ? (($tipo['artigo'] ?? 'Novo') . ' ' . $tipo['singular']) : (string) $item['nome'],
        'tipo' => $tipo,
        'item' => $item,
        'erro' => $erro,
        'horimetro' => ($id !== null && !empty($tipo['ficha'])) ? Horimetro::ficha(Horimetro::tipoDaChave($chave), $id) : null,
        'pecas' => ($id !== null && !empty($tipo['ficha'])) ? Pecas::daMaquina(Horimetro::tipoDaChave($chave), $id) : null,
        'pedidosPecas' => ($id !== null && !empty($tipo['ficha'])) ? Pecas::pedidosDaMaquina(Horimetro::tipoDaChave($chave), $id) : [],
        'oficina' => ($id !== null && !empty($tipo['ficha'])) ? ficha_oficina(Horimetro::tipoDaChave($chave), $id) : null,
    ]);
}

/** Oficina na ficha da máquina: a estadia aberta, os problemas para ligar, a disponibilidade do mês e as ordens de serviço. */
function ficha_oficina(string $tipo, int $id): array
{
    return [
        'atual' => Oficina::atual($tipo, $id),
        'problemas' => Problemas::abertosDa($tipo, $id),
        'disponibilidade' => Oficina::textoDisponibilidade(Oficina::disponibilidades()[$tipo . ':' . $id] ?? 100.0),
        'historico' => Oficina::historico($tipo, $id),
    ];
}

function tela_pedido_novo(string $chave, int $id, string $method): void
{
    $item = Cadastro::buscar($chave, $id);
    if ($item === null) {
        http_response_code(404);
        render('erro', ['titulo' => 'Não encontrado', 'mensagem' => 'Esse cadastro não existe.']);
        return;
    }
    $tipo = Horimetro::tipoDaChave($chave);
    $erro = null;
    if ($method === 'POST') {
        csrf_check();
        try {
            $pedido = Pecas::criarPedido($tipo, $id, $_POST, Auth::usuario());
            flash_set('Pedido gravado. Toque em Enviar para o Compras.');
            redirect('/pedidos-pecas/' . $pedido);
        } catch (InvalidArgumentException $e) {
            $erro = $e->getMessage();
        }
    }
    render('pedido_pecas_form', [
        'titulo' => 'Novo pedido de peças',
        'chave' => $chave,
        'maquina' => $item,
        'pecas' => array_values(array_filter(Pecas::daMaquina($tipo, $id), static fn (array $p): bool => (int) $p['ativo'] === 1)),
        'confirmadores' => Pecas::confirmadores($tipo),
        'erro' => $erro,
    ]);
}

function tela_pedido_pecas(int $id): void
{
    $pedido = Pecas::pedido($id);
    if ($pedido === null) {
        http_response_code(404);
        render('erro', ['titulo' => 'Não encontrado', 'mensagem' => 'Esse pedido não existe.']);
        return;
    }
    $link = (requisicao_https() ? 'https' : 'http') . '://' . (string) ($_SERVER['HTTP_HOST'] ?? '') . url('compras/' . $pedido['codigo_publico']);
    render('pedido_pecas', [
        'titulo' => 'Pedido de peças',
        'pedido' => $pedido,
        'chave' => Horimetro::chaveDoTipo((string) $pedido['tipo']),
        'mensagem' => Pecas::mensagem($pedido, $link),
    ]);
}

/** Página pública do pedido para o Compras (/compras/<código>) e as fotos das peças dele. */
function tela_compras(string $path): void
{
    $pedido = preg_match('#^compras/([0-9a-f]{32})(?:/fotos/(\d+))?$#', $path, $m) ? Pecas::pedidoPorCodigo($m[1]) : null;
    if ($pedido !== null && ($m[2] ?? '') !== '') {
        $arquivo = null;
        foreach ($pedido['itens'] as $item) {
            if ((int) $item['id'] === (int) $m[2] && $item['peca_id'] !== null) {
                $arquivo = Pecas::arquivoFoto((int) $item['peca_id']);
            }
        }
        if ($arquivo === null) {
            http_response_code(404);
            exit;
        }
        Fotos::servir($arquivo['caminho'], $arquivo['tipo'], Fotos::LADO_MAQUINA);
    }
    if ($pedido === null) {
        http_response_code(404);
        render('erro', ['titulo' => 'Não encontrado', 'mensagem' => 'Esse pedido não foi encontrado.']);
        return;
    }
    render('compras', [
        'titulo' => 'Pedido de peças',
        'pedido' => $pedido,
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
                'nf_emitida_em' => $post !== null
                    ? (string) ($post['nf_emitida_em'] ?? '')
                    : data_hora_para_input(is_array($ordem) ? ($ordem['nf_emitida_em'] ?? null) : null),
                'nf_emitida_data' => (string) ($fonte['nf_emitida_data'] ?? ''),
                'nf_chave' => (string) ($fonte['nf_chave'] ?? ''),
                'nf_valor' => $post !== null
                    ? (string) ($post['nf_valor'] ?? '')
                    : (is_array($ordem) && $ordem['nf_valor'] !== null ? formatar_quantidade((string) $ordem['nf_valor']) : ''),
                'nf_pagamento' => (string) ($fonte['nf_pagamento'] ?? ''),
                'fracionado' => $post !== null ? isset($post['fracionado']) : (is_array($ordem) && (int) ($ordem['fracionado'] ?? 0) === 1),
                'nota_arquivo' => (string) ($post['nota_arquivo'] ?? ''),
            ],
            'materiais' => Cadastro::opcoes('materiais', $materialId > 0 ? $materialId : null),
            'travado' => $ordem !== null && !in_array($ordem['status'], ['aguardando_autorizacao', 'liberado', 'saldo'], true),
        ];
}
