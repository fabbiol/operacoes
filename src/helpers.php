<?php

declare(strict_types=1);

// Janela em que um toque logo depois de outra ação é tratado como toque repetido.
const TOQUE_REPETIDO_MS = 1200;

function carregar_env(string $arquivo): void
{
    if (!is_file($arquivo)) {
        return;
    }

    $linhas = file($arquivo, FILE_IGNORE_NEW_LINES);
    if ($linhas === false) {
        return;
    }

    foreach ($linhas as $linha) {
        $linha = trim($linha);
        if ($linha === '' || str_starts_with($linha, '#')) {
            continue;
        }
        $partes = explode('=', $linha, 2);
        if (count($partes) !== 2) {
            continue;
        }
        $chave = trim($partes[0]);
        $valor = trim($partes[1]);
        if (
            (str_starts_with($valor, '"') && str_ends_with($valor, '"'))
            || (str_starts_with($valor, "'") && str_ends_with($valor, "'"))
        ) {
            $valor = substr($valor, 1, -1);
        }
        $jaDefinido = getenv($chave);
        if ($jaDefinido !== false && $jaDefinido !== '') {
            $_ENV[$chave] = $jaDefinido;
            continue;
        }
        $_ENV[$chave] = $valor;
        putenv($chave . '=' . $valor);
    }
}

function env_str(string $chave, string $padrao = ''): string
{
    $valor = $_ENV[$chave] ?? getenv($chave);
    if ($valor === false || $valor === null || $valor === '') {
        return $padrao;
    }

    return (string) $valor;
}

function e(string|int|float|null $valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

function url(string $caminho = ''): string
{
    $base = rtrim(env_str('APP_BASE'), '/');
    if ($caminho === '' || $caminho === '/') {
        return $base === '' ? '/' : $base . '/';
    }

    return $base . '/' . ltrim($caminho, '/');
}

function url_foto_pessoa(array $linha): ?string
{
    if (empty($linha['pessoa_foto']) || (int) ($linha['pessoa_id'] ?? 0) < 1) {
        return null;
    }

    return url('usuarios/' . (int) $linha['pessoa_id'] . '/foto');
}

function url_foto_caminhao(array $linha): ?string
{
    $id = (int) ($linha['caminhao_id'] ?? 0);
    if (empty($linha['foto']) || $id < 1) {
        return null;
    }

    return url('cadastros/caminhoes/' . $id . '/foto');
}

function url_foto_pa(array $linha): ?string
{
    $id = (int) ($linha['pa_carregadeira_id'] ?? 0);
    if (!empty($linha['pa_foto']) && $id > 0) {
        return url('cadastros/pas/' . $id . '/foto');
    }
    $retroId = (int) ($linha['retroescavadeira_id'] ?? 0);
    if (!empty($linha['pa_foto']) && $retroId > 0) {
        return url('cadastros/retros/' . $retroId . '/foto');
    }

    return null;
}

function url_foto_retro(array $linha): ?string
{
    $id = (int) ($linha['retroescavadeira_id'] ?? 0);
    if (empty($linha['retro_foto']) || $id < 1) {
        return null;
    }

    return url('cadastros/retros/' . $id . '/foto');
}

function maquina_chave(string $tipo): ?string
{
    return match ($tipo) {
        'caminhao' => 'caminhoes',
        'pa' => 'pas',
        'retro' => 'retros',
        default => null,
    };
}

function maquina_tipos_do_usuario(): array
{
    if (!Auth::ok() || Auth::admin()) {
        return [];
    }
    $tipos = [];
    if (Auth::motorista()) {
        $tipos[] = 'caminhao';
    }
    if (Auth::operador()) {
        $tipos[] = 'pa';
    }
    if (Auth::escavadeira()) {
        $tipos[] = 'retro';
    }

    return $tipos;
}

/**
 * O pátio é do operador de pá carregadeira. Sem nenhuma pá cadastrada, o pátio é carregado
 * com a escavadeira hidráulica, e aí o operador de escavadeira também entra.
 */
function pode_patio(): bool
{
    if (!Auth::ok()) {
        return false;
    }
    if (Auth::admin() || Auth::operador()) {
        return true;
    }

    if (!Auth::escavadeira()) {
        return false;
    }

    // Quem já começou a carregar no pátio com a escavadeira termina, mesmo que uma pá tenha sido cadastrada no meio.
    return Cadastro::opcoes('pas') === [] || Operacoes::paEmOperacao(Auth::usuario()) !== null;
}

function exigir_patio(): void
{
    if (pode_patio()) {
        return;
    }
    http_response_code(403);
    render('erro', [
        'titulo' => 'Acesso',
        'mensagem' => 'Essa tela não faz parte do seu acesso.',
    ]);
    exit;
}

function maquina_item(string $tipo, string $chave, array $cadastro): array
{
    $id = (int) $cadastro['id'];
    $detalhe = [];
    foreach (['marca', 'modelo', 'ano'] as $campo) {
        $valor = trim((string) ($cadastro[$campo] ?? ''));
        if ($valor !== '') {
            $detalhe[] = $valor;
        }
    }
    $rotulos = [
        'caminhao' => 'Caminhão',
        'pa' => 'Pá carregadeira',
        'retro' => 'Escavadeira hidráulica',
    ];

    return [
        'tipo' => $tipo,
        'id' => $id,
        'nome' => (string) $cadastro['nome'],
        'detalhe' => implode(' · ', $detalhe),
        'foto' => !empty($cadastro['foto']) ? url('cadastros/' . $chave . '/' . $id . '/foto') : null,
        'rotulo' => $rotulos[$tipo] ?? 'Máquina',
    ];
}

/**
 * Posse da máquina de quem está usando o sistema (null para o administrativo ou sem máquina).
 * Fica no banco: vale em qualquer tablet e não some quando a sessão cai.
 */
function posse_atual(): ?array
{
    if (!Auth::ok() || Auth::admin()) {
        return null;
    }
    $usuarioId = Auth::id();
    if ($usuarioId < 1) {
        return null;
    }
    posse_da_sessao_antiga($usuarioId);

    return Posse::doUsuario($usuarioId);
}

/**
 * Antes desta versão a máquina e o horímetro de entrada ficavam só na sessão.
 * Quem já estava com uma máquina na hora da atualização continua com ela, agora gravada no banco.
 */
function posse_da_sessao_antiga(int $usuarioId): void
{
    if (!isset($_SESSION['maquina']) && !isset($_SESSION['horimetro_turno'])) {
        return;
    }
    $bruta = $_SESSION['maquina'] ?? null;
    $turno = $_SESSION['horimetro_turno'] ?? null;
    unset($_SESSION['maquina'], $_SESSION['horimetro_turno']);
    if (!is_array($bruta)) {
        return;
    }
    $tipo = (string) ($bruta['tipo'] ?? '');
    $id = (int) ($bruta['id'] ?? 0);
    if (maquina_chave($tipo) === null || $id < 1 || !in_array($tipo, maquina_tipos_do_usuario(), true)) {
        return;
    }
    Db::comTrava(Posse::TRAVA, static function () use ($tipo, $id, $turno, $usuarioId): void {
        if (Posse::doUsuario($usuarioId, true) !== null || Posse::daMaquina($tipo, $id) !== null) {
            return;
        }
        $mesma = is_array($turno) && ($turno['tipo'] ?? '') === $tipo && (int) ($turno['id'] ?? 0) === $id;
        $iniciado = $mesma ? (string) ($turno['iniciado_em'] ?? '') : '';
        Posse::abrir(
            $tipo,
            $id,
            $usuarioId,
            Auth::usuario(),
            $mesma && isset($turno['entrada']) ? (float) $turno['entrada'] : null,
            null,
            $iniciado !== '' ? $iniciado : null,
            $mesma && !empty($turno['precisa_soltar'])
        );
    });
}

function maquina_escolhida(): ?array
{
    $posse = posse_atual();
    if ($posse === null) {
        return null;
    }
    $tipo = (string) $posse['tipo'];
    $id = (int) $posse['maquina_id'];
    $chave = maquina_chave($tipo);
    $cadastro = $chave !== null ? Cadastro::buscar($chave, $id) : null;
    if ($cadastro === null || (int) ($cadastro['ativo'] ?? 0) !== 1 || !in_array($tipo, maquina_tipos_do_usuario(), true)) {
        // Máquina desativada ou função trocada: essa posse não vale mais.
        Posse::fechar(Auth::id());

        return null;
    }

    return maquina_item($tipo, (string) $chave, $cadastro);
}

/** O trabalho em andamento manda: a máquina dele fica com quem está trabalhando. */
function maquina_adotar(string $tipo, int $id): void
{
    $posse = posse_atual();
    if ($posse !== null && (string) $posse['tipo'] === $tipo && (int) $posse['maquina_id'] === $id) {
        return;
    }
    $usuarioId = Auth::id();
    Db::comTrava(Posse::TRAVA, static function () use ($tipo, $id, $usuarioId): void {
        $minha = Posse::doUsuario($usuarioId, true);
        if ($minha !== null && (string) $minha['tipo'] === $tipo && (int) $minha['maquina_id'] === $id) {
            return;
        }
        $dono = Posse::daMaquina($tipo, $id);
        if ($dono !== null) {
            Posse::liberar($tipo, $id);
        }
        if ($minha !== null) {
            Posse::trocarMaquina($usuarioId, $tipo, $id);
        } else {
            Posse::abrir($tipo, $id, $usuarioId, Auth::usuario(), null, null);
        }
    });
}

/** Máquinas que a pessoa pode usar: sem trabalho em andamento e sem estar com outra pessoa. */
function maquinas_livres(): array
{
    $lista = [];
    $tipos = maquina_tipos_do_usuario();
    $tomadas = array_filter(Posse::todas(), static fn (array $posse): bool => (int) $posse['usuario_id'] !== Auth::id());
    if (in_array('caminhao', $tipos, true)) {
        $ocupados = [];
        foreach (Lavra::abertos() as $ciclo) {
            $ocupados[(int) $ciclo['caminhao_id']] = true;
        }
        foreach (Cadastro::opcoes('caminhoes') as $item) {
            if (isset($ocupados[(int) $item['id']]) || isset($tomadas['caminhao:' . (int) $item['id']])) {
                continue;
            }
            $lista[] = maquina_item('caminhao', 'caminhoes', $item);
        }
    }
    if (in_array('pa', $tipos, true)) {
        $ocupados = Operacoes::pasEmUso();
        foreach (Cadastro::opcoes('pas') as $item) {
            if (isset($ocupados[(int) $item['id']]) || isset($tomadas['pa:' . (int) $item['id']])) {
                continue;
            }
            $lista[] = maquina_item('pa', 'pas', $item);
        }
    }
    if (in_array('retro', $tipos, true)) {
        $ocupados = Lavra::retrosEmUso();
        foreach (Cadastro::opcoes('retros') as $item) {
            if (isset($ocupados[(int) $item['id']]) || isset($tomadas['retro:' . (int) $item['id']])) {
                continue;
            }
            $lista[] = maquina_item('retro', 'retros', $item);
        }
    }

    return $lista;
}

/** Máquinas do tipo da pessoa que estão com outra pessoa, para ela saber com quem falar. */
function maquinas_com_outros(): array
{
    $tipos = maquina_tipos_do_usuario();
    $lista = [];
    foreach (Posse::todas() as $posse) {
        $tipo = (string) $posse['tipo'];
        if (!in_array($tipo, $tipos, true) || (int) $posse['usuario_id'] === Auth::id()) {
            continue;
        }
        $lista[] = [
            'nome' => Posse::nomeDaMaquina($tipo, (int) $posse['maquina_id']),
            'usuario' => (string) $posse['usuario'],
            'desde' => (string) $posse['iniciado_em'],
        ];
    }

    return $lista;
}

function maquina_esta_livre(string $tipo, int $id): bool
{
    foreach (maquinas_livres() as $item) {
        if ($item['tipo'] === $tipo && (int) $item['id'] === $id) {
            return true;
        }
    }

    return false;
}

function maquina_do_trabalho(): ?array
{
    if (!Auth::ok() || Auth::admin()) {
        return null;
    }
    $nome = Auth::usuario();
    if (Auth::operador() || Auth::escavadeira()) {
        // Carregamento no pátio: com a pá, ou com a escavadeira quando não há pá cadastrada.
        $pa = Operacoes::paEmOperacao($nome);
        if ($pa !== null) {
            return ['tipo' => (string) ($pa['tipo'] ?? 'pa'), 'id' => (int) $pa['id']];
        }
    }
    if (Auth::escavadeira()) {
        $carga = Lavra::cargaDoOperador($nome);
        $retroId = (int) ($carga['retroescavadeira_id'] ?? 0);
        if ($carga !== null && $retroId > 0) {
            return ['tipo' => 'retro', 'id' => $retroId];
        }
    }
    if (Auth::motorista()) {
        $caminhao = Lavra::caminhaoDoMotorista($nome);
        if ($caminhao !== null) {
            return ['tipo' => 'caminhao', 'id' => (int) $caminhao['id']];
        }
    }

    return null;
}

function maquina_destino(): string
{
    return match (maquina_escolhida()['tipo'] ?? '') {
        'pa' => '/patio',
        'caminhao', 'retro' => '/lavra',
        default => Auth::inicio(),
    };
}

function horimetro_entrada(): ?float
{
    $posse = posse_atual();

    return $posse !== null && $posse['entrada'] !== null ? (float) $posse['entrada'] : null;
}

/** Assume a máquina com a leitura do horímetro. A leitura e a posse gravam juntas ou nenhuma grava. */
function horimetro_assumir(string $tipo, int $id, string $leitura): void
{
    $usuarioId = Auth::id();
    Db::comTrava(Posse::TRAVA, static function () use ($tipo, $id, $leitura, $usuarioId): void {
        $minha = Posse::doUsuario($usuarioId, true);
        if ($minha !== null) {
            // Toque repetido em Confirmar: a máquina já é desta pessoa.
            if ((string) $minha['tipo'] === $tipo && (int) $minha['maquina_id'] === $id) {
                return;
            }
            throw new InvalidArgumentException('Você já está com ' . Posse::nomeDaMaquina((string) $minha['tipo'], (int) $minha['maquina_id']) . '. Solte essa máquina antes.');
        }
        // O administrativo pode ter acabado de iniciar um trabalho desta pessoa com outra máquina.
        $trabalho = maquina_do_trabalho();
        if ($trabalho !== null) {
            throw new InvalidArgumentException(
                'O administrativo acabou de iniciar um trabalho seu com ' . Posse::nomeDaMaquina($trabalho['tipo'], (int) $trabalho['id']) . '. Use essa máquina.'
            );
        }
        if (!maquina_esta_livre($tipo, $id)) {
            $dono = Posse::daMaquina($tipo, $id);
            throw new InvalidArgumentException(
                $dono !== null
                    ? 'Essa máquina acabou de ser assumida por ' . $dono['usuario'] . '. Escolha outra.'
                    : 'Essa máquina não está livre agora. Escolha outra.'
            );
        }
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $lida = Horimetro::lancarComId($tipo, $id, $leitura, Auth::usuario());
            Posse::abrir($tipo, $id, $usuarioId, Auth::usuario(), $lida['leitura'], $lida['id']);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    });
}

function horimetro_marcar_soltar(): void
{
    if (posse_atual() !== null) {
        Posse::marcarSoltar(Auth::id());
    }
}

function horimetro_precisa_soltar(): bool
{
    return !empty(posse_atual()['precisa_soltar']);
}

/**
 * Solta a máquina com a leitura de saída e grava o turno. Um segundo toque não grava de novo.
 * $situacao é a resposta de "Como ficou a máquina?": 'ok' (tudo certo) ou 'problema', com o texto em $problema.
 * Sem $situacao (chamada antiga), não pergunta.
 */
function horimetro_soltar(string $leitura, ?string $situacao = null, string $problema = ''): void
{
    if (maquina_escolhida() === null) {
        return;
    }
    if (maquina_do_trabalho() !== null) {
        throw new InvalidArgumentException('Conclua o trabalho desta máquina antes de soltar.');
    }
    $maquinaOk = null;
    $descricao = null;
    if ($situacao !== null) {
        if (!in_array($situacao, ['ok', 'problema'], true)) {
            throw new InvalidArgumentException('Responda como ficou a máquina: Tudo certo ou Teve problema.');
        }
        $maquinaOk = $situacao === 'ok';
        // Com "Tudo certo", o texto que tenha ficado no campo escondido não vale.
        $descricao = $maquinaOk ? null : Problemas::texto($problema);
    }
    $usuarioId = Auth::id();
    Db::comTrava(Posse::TRAVA, static function () use ($leitura, $usuarioId, $maquinaOk, $descricao): void {
        $posse = Posse::doUsuario($usuarioId, true);
        if ($posse === null) {
            return;
        }
        $tipo = (string) $posse['tipo'];
        $id = (int) $posse['maquina_id'];
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $saida = Horimetro::lancar($tipo, $id, $leitura, Auth::usuario());
            $entrada = $posse['entrada'] !== null ? (float) $posse['entrada'] : null;
            if ($entrada !== null && round($saida, 1) >= round($entrada, 1)) {
                Horimetro::registrarTurno($tipo, $id, $entrada, $saida, (string) $posse['iniciado_em'], Auth::usuario(), $maquinaOk);
            }
            if ($descricao !== null) {
                Problemas::relatar($tipo, $id, Auth::usuario(), $descricao, $saida);
            }
            Posse::fechar($usuarioId);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    });
}

/**
 * Só para a publicação: no instante entre o envio deste arquivo e o do index.php novo,
 * o index.php antigo ainda chama estas duas funções.
 */
function maquina_definir(string $tipo, int $id): void
{
    maquina_adotar($tipo, $id);
}

function maquina_limpar(): void
{
}

function maquinas_do_usuario(): array
{
    $maquina = maquina_escolhida();
    if ($maquina === null) {
        return [];
    }

    return [$maquina];
}

function url_foto_operador_carga(array $linha): ?string
{
    if (empty($linha['carga_pessoa_foto']) || (int) ($linha['carga_pessoa_id'] ?? 0) < 1) {
        return null;
    }

    return url('usuarios/' . (int) $linha['carga_pessoa_id'] . '/foto');
}

function asset(string $caminho): string
{
    $arquivo = OPERACOES_ROOT . '/public/' . ltrim($caminho, '/');
    $versao = is_file($arquivo) ? (string) filemtime($arquivo) : '1';

    return url($caminho) . '?v=' . $versao;
}

function route_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $uri = rawurldecode((string) $uri);
    $base = rtrim(env_str('APP_BASE'), '/');
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }
    $uri = trim($uri, '/');
    if ($uri === 'index.php') {
        return '';
    }

    return $uri;
}

function redirect(string $caminho): never
{
    $envio = envio_atual();
    if ($envio !== null && session_status() === PHP_SESSION_ACTIVE) {
        $feitos = is_array($_SESSION['envios'] ?? null) ? $_SESSION['envios'] : [];
        unset($feitos[$envio]);
        $feitos[$envio] = $caminho;
        $_SESSION['envios'] = array_slice($feitos, -40, null, true);
        $_SESSION['envio_em'] = (int) round(microtime(true) * 1000);
    }
    header('Location: ' . url($caminho));
    exit;
}

function requisicao_https(): bool
{
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

function caminho_de_volta(): string
{
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $partes = $referer !== '' ? parse_url($referer) : false;
    if (!is_array($partes) || ($partes['host'] ?? '') === '' || ($partes['host'] ?? '') !== parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
        return '/';
    }
    $caminho = (string) ($partes['path'] ?? '/');
    if (preg_match('/[\\\\\x00-\x1f]/', $caminho)) {
        return '/';
    }
    $base = rtrim(env_str('APP_BASE'), '/');
    if ($base !== '' && str_starts_with($caminho, $base)) {
        $caminho = substr($caminho, strlen($base));
    }
    $caminho = '/' . ltrim($caminho, '/');
    if (isset($partes['query']) && $partes['query'] !== '') {
        $caminho .= '?' . $partes['query'];
    }

    return $caminho;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'
        . '<input type="hidden" name="_envio" value="' . bin2hex(random_bytes(12)) . '">'
        . '<input type="hidden" name="_tela" value="' . (int) round(microtime(true) * 1000) . '">';
}

/**
 * Toque que chegou logo depois de outra ação, vindo da tela que essa ação abriu. No tablet, o segundo toque
 * de um toque duplo cai no botão da tela nova, que aparece no mesmo lugar em menos de um segundo.
 */
function toque_apressado(): bool
{
    $tela = (int) ($_POST['_tela'] ?? 0);
    $anterior = (int) ($_SESSION['envio_em'] ?? 0);
    if ($tela <= 0 || $anterior <= 0 || $tela < $anterior) {
        return false;
    }
    $passou = (int) round(microtime(true) * 1000) - $anterior;

    return $passou >= 0 && $passou < TOQUE_REPETIDO_MS;
}

/** Quanto falta da janela de toque repetido para a tela aberta pelo redirect de uma ação. */
function calma_restante_ms(): int
{
    $anterior = (int) ($_SESSION['envio_em'] ?? 0);
    if ($anterior <= 0 || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return 0;
    }

    return max(0, $anterior + TOQUE_REPETIDO_MS - (int) round(microtime(true) * 1000));
}

function csrf_check(): void
{
    $enviado = (string) ($_POST['_csrf'] ?? '');
    if ($enviado === '' || !hash_equals(csrf_token(), $enviado)) {
        flash_set('A tela estava desatualizada. Confira e tente de novo.', 'atencao');
        redirect(caminho_de_volta());
    }
    envio_conferir();
}

/**
 * Cada formulário leva um código de envio. O primeiro envio que termina em redirect guarda o destino;
 * um toque repetido ou um reenvio do mesmo formulário só volta para esse destino, sem repetir a ação.
 */
function envio_conferir(): void
{
    $envio = (string) ($_POST['_envio'] ?? '');
    if (!preg_match('/^[0-9a-f]{24}$/', $envio)) {
        return;
    }
    $feitos = $_SESSION['envios'] ?? null;
    if (is_array($feitos) && isset($feitos[$envio])) {
        header('Location: ' . url((string) $feitos[$envio]));
        exit;
    }
    envio_atual($envio);
}

function envio_atual(?string $envio = null): ?string
{
    static $atual = null;
    if ($envio !== null) {
        $atual = $envio;
    }

    return $atual;
}

function flash_set(string $mensagem, string $tipo = 'ok'): void
{
    $_SESSION['flash'] = ['mensagem' => $mensagem, 'tipo' => $tipo];
}

function flash_get(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function data_por_extenso(?int $timestamp = null): string
{
    $timestamp ??= time();
    $dias = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
    $meses = [
        1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
        'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
    ];

    return $dias[(int) date('w', $timestamp)]
        . ', ' . date('j', $timestamp)
        . ' de ' . $meses[(int) date('n', $timestamp)]
        . ' de ' . date('Y', $timestamp);
}

function quando(?string $dataHora): string
{
    if ($dataHora === null || $dataHora === '') {
        return '';
    }
    $ts = strtotime($dataHora);

    return $ts ? date('d/m/Y H:i', $ts) : $dataHora;
}

function hora_curta(?string $dataHora): string
{
    if ($dataHora === null || $dataHora === '') {
        return '';
    }
    $ts = strtotime($dataHora);

    return $ts ? date('H:i', $ts) : $dataHora;
}

function segundos_entre(?string $inicio, ?string $fim): ?int
{
    if ($inicio === null || $inicio === '' || $fim === null || $fim === '') {
        return null;
    }
    $a = strtotime($inicio);
    $b = strtotime($fim);
    if ($a === false || $b === false || $b < $a) {
        return null;
    }

    return $b - $a;
}

function texto_duracao(?int $segundos): string
{
    if ($segundos === null) {
        return '—';
    }
    $segundos = max(0, $segundos);
    $horas = intdiv($segundos, 3600);
    $minutos = intdiv($segundos % 3600, 60);
    $resto = $segundos % 60;
    if ($horas > 0) {
        return $horas . ' h ' . str_pad((string) $minutos, 2, '0', STR_PAD_LEFT) . ' min';
    }
    if ($minutos > 0) {
        return $minutos . ' min ' . str_pad((string) $resto, 2, '0', STR_PAD_LEFT) . ' s';
    }

    return $resto . ' s';
}

function tamanho(string $texto): int
{
    return function_exists('mb_strlen') ? mb_strlen($texto) : strlen($texto);
}

function limpar_texto(string $texto, int $maximo, bool $obrigatorio, string $rotulo): ?string
{
    $texto = trim((string) (preg_replace('/\s+/u', ' ', $texto) ?? ''));
    if ($texto === '') {
        if ($obrigatorio) {
            throw new InvalidArgumentException('Informe ' . $rotulo . '.');
        }

        return null;
    }
    if (tamanho($texto) > $maximo) {
        throw new InvalidArgumentException($rotulo . ' pode ter no máximo ' . $maximo . ' caracteres.');
    }

    return $texto;
}

function limpar_longo(string $texto, int $maximo): ?string
{
    $texto = str_replace(["\0", "\r\n", "\r"], ["", "\n", "\n"], $texto);
    $texto = trim($texto);
    if ($texto === '') {
        return null;
    }
    if (tamanho($texto) > $maximo) {
        throw new InvalidArgumentException('As observações podem ter no máximo ' . $maximo . ' caracteres.');
    }

    return $texto;
}

function parse_quantidade(string $bruto): string
{
    $bruto = trim(str_replace([' ', "\xc2\xa0"], '', $bruto));
    if ($bruto === '') {
        throw new InvalidArgumentException('Informe a quantidade autorizada.');
    }
    if (str_contains($bruto, ',') && str_contains($bruto, '.')) {
        $bruto = str_replace('.', '', $bruto);
    }
    $bruto = str_replace(',', '.', $bruto);
    if (!preg_match('/^\d+(\.\d+)?$/', $bruto)) {
        throw new InvalidArgumentException('A quantidade autorizada precisa ser um número.');
    }
    $numero = round((float) $bruto, 2);
    if ($numero <= 0 || $numero > 99999999.99) {
        throw new InvalidArgumentException('A quantidade autorizada precisa ser maior que zero.');
    }

    return number_format($numero, 2, '.', '');
}

function formatar_quantidade(string|float|int $valor): string
{
    return number_format((float) $valor, 2, ',', '.');
}

function parse_data_hora(string $bruto): string
{
    $bruto = trim(str_replace('T', ' ', $bruto));
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $bruto)) {
        $bruto .= ':00';
    }
    $data = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $bruto);
    $erros = DateTimeImmutable::getLastErrors();
    if (!$data || ($erros['warning_count'] ?? 0) > 0 || ($erros['error_count'] ?? 0) > 0) {
        throw new InvalidArgumentException('Informe a data e o horário.');
    }

    return $data->format('Y-m-d H:i:s');
}

function data_para_input(?string $dataHora): string
{
    if ($dataHora === null || $dataHora === '') {
        return date('Y-m-d\TH:i');
    }
    $ts = strtotime($dataHora);

    return $ts ? date('Y-m-d\TH:i', $ts) : '';
}

function status_lista(): array
{
    return [
        'aguardando_autorizacao' => 'Aguardando autorização',
        'liberado' => 'Liberado',
        'carregando' => 'Carregando',
        'aguardando_conferencia' => 'Aguardando conferência',
        'concluido' => 'Concluído',
        'cancelado' => 'Cancelado',
        'saldo' => 'Saldo a buscar',
    ];
}

function status_rotulo(string $status): string
{
    return status_lista()[$status] ?? $status;
}

function unidades(): array
{
    return [
        't' => 't',
        'm3' => 'm³',
        'un' => 'un',
    ];
}

function unidade_rotulo(string $unidade): string
{
    return unidades()[$unidade] ?? $unidade;
}

function densidade_brita(): float
{
    return 1.45;
}

function texto_capacidade(string|float|int|null $toneladas): string
{
    if ($toneladas === null || $toneladas === '') {
        return '';
    }
    $tonelada = round((float) $toneladas, 2);
    $volume = round($tonelada / densidade_brita(), 2);

    return formatar_quantidade($tonelada) . ' t · ' . formatar_quantidade($volume) . ' m³';
}

function agora_sql(): string
{
    return date('Y-m-d H:i:s');
}

function render(string $view, array $dados = []): void
{
    $dados['viewFile'] = OPERACOES_ROOT . '/views/' . $view . '.php';
    $parcial = (string) ($_SERVER['HTTP_X_FLUXO'] ?? '') === '1';
    $dados['flash'] = $parcial ? null : ($dados['flash'] ?? flash_get());
    $dados['agoraServidor'] = time();
    $dados['calmaMs'] = calma_restante_ms();
    $dados['titulo'] = $dados['titulo'] ?? 'Pátio';
    $dados['corpo'] = $dados['corpo'] ?? '';
    $dados['atualizar'] = $dados['atualizar'] ?? 0;
    extract($dados, EXTR_SKIP);
    require OPERACOES_ROOT . '/views/layout.php';
}

function exigir_admin(): void
{
    exigir_papel();
}

function exigir_papel(string ...$papeis): void
{
    if ($papeis === []) {
        $liberado = Auth::admin();
    } else {
        $liberado = Auth::pode(...$papeis);
    }
    if ($liberado) {
        return;
    }
    http_response_code(403);
    render('erro', [
        'titulo' => 'Acesso',
        'mensagem' => 'Essa tela não faz parte do seu acesso.',
    ]);
    exit;
}

function exigir_cadastro(string $chave): void
{
    try {
        Cadastro::tipo($chave);
    } catch (InvalidArgumentException) {
        http_response_code(404);
        render('erro', [
            'titulo' => 'Não encontrado',
            'mensagem' => 'Esse cadastro não existe.',
        ]);
        exit;
    }
    if (Auth::podeCadastro($chave)) {
        return;
    }
    exigir_papel();
}
