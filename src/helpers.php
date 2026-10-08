<?php

declare(strict_types=1);

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
        $tipos[] = 'retro';
    }

    return $tipos;
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
        'retro' => 'Retroescavadeira',
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

function maquina_limpar(): void
{
    unset($_SESSION['maquina'], $_SESSION['retroescavadeira_id']);
}

function maquina_definir(string $tipo, int $id): void
{
    $_SESSION['maquina'] = ['tipo' => $tipo, 'id' => $id];
    if ($tipo === 'retro') {
        $_SESSION['retroescavadeira_id'] = $id;
    } else {
        unset($_SESSION['retroescavadeira_id']);
    }
}

function maquina_escolhida(): ?array
{
    $bruta = $_SESSION['maquina'] ?? null;
    if (!is_array($bruta)) {
        return null;
    }
    $tipo = (string) ($bruta['tipo'] ?? '');
    $id = (int) ($bruta['id'] ?? 0);
    $chave = maquina_chave($tipo);
    if ($chave === null || $id < 1 || !in_array($tipo, maquina_tipos_do_usuario(), true)) {
        maquina_limpar();

        return null;
    }
    $cadastro = Cadastro::buscar($chave, $id);
    if ($cadastro === null || (int) ($cadastro['ativo'] ?? 0) !== 1) {
        maquina_limpar();

        return null;
    }
    if ($tipo === 'retro') {
        $_SESSION['retroescavadeira_id'] = $id;
    }

    return maquina_item($tipo, $chave, $cadastro);
}

function maquinas_livres(): array
{
    $lista = [];
    $tipos = maquina_tipos_do_usuario();
    if (in_array('caminhao', $tipos, true)) {
        $ocupados = [];
        foreach (Lavra::abertos() as $ciclo) {
            $ocupados[(int) $ciclo['caminhao_id']] = true;
        }
        foreach (Cadastro::opcoes('caminhoes') as $item) {
            if (isset($ocupados[(int) $item['id']])) {
                continue;
            }
            $lista[] = maquina_item('caminhao', 'caminhoes', $item);
        }
    }
    if (in_array('pa', $tipos, true)) {
        $ocupados = Operacoes::pasEmUso();
        foreach (Cadastro::opcoes('pas') as $item) {
            if (isset($ocupados[(int) $item['id']])) {
                continue;
            }
            $lista[] = maquina_item('pa', 'pas', $item);
        }
    }
    if (in_array('retro', $tipos, true)) {
        $ocupados = Lavra::retrosEmUso();
        foreach (Cadastro::opcoes('retros') as $item) {
            if (isset($ocupados[(int) $item['id']])) {
                continue;
            }
            $lista[] = maquina_item('retro', 'retros', $item);
        }
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
    if (Auth::operador()) {
        $pa = Operacoes::paEmOperacao($nome);
        if ($pa !== null) {
            return ['tipo' => (string) ($pa['tipo'] ?? 'pa'), 'id' => (int) $pa['id']];
        }
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
    header('Location: ' . url($caminho));
    exit;
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
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $enviado = (string) ($_POST['_csrf'] ?? '');
    if ($enviado === '' || !hash_equals(csrf_token(), $enviado)) {
        http_response_code(419);
        exit('A sessão expirou. Volte e tente de novo.');
    }
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
    $dados['flash'] = $dados['flash'] ?? flash_get();
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
