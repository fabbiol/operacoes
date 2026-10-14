<?php

declare(strict_types=1);

final class Auth
{
    public const SESSAO_SEGUNDOS = 50400;
    private const MAX_TENTATIVAS = 5;
    private const BLOQUEIO_SEGUNDOS = 60;

    private static ?array $eu = null;
    private static bool $euCarregado = false;
    private static string $falhaLogin = '';

    public static function configurada(): bool
    {
        self::garantirUsuarios();

        return (int) Db::pdo()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0;
    }

    public static function ok(): bool
    {
        return !empty($_SESSION['auth']) && self::usuario() !== '';
    }

    public static function usuario(): string
    {
        return self::nomeSimples((string) ($_SESSION['usuario'] ?? '')) ?? '';
    }

    public static function id(): int
    {
        return (int) (self::eu()['id'] ?? 0);
    }

    public static function papel(): string
    {
        if (self::admin()) {
            return 'admin';
        }
        if (self::motorista() && self::operador()) {
            return 'ambos';
        }
        if (self::motorista()) {
            return 'motorista';
        }
        if (self::operador()) {
            return 'operador';
        }

        return '';
    }

    public static function admin(): bool
    {
        return (int) (self::eu()['administrador'] ?? 0) === 1;
    }

    public static function operador(): bool
    {
        return (int) (self::eu()['operador'] ?? 0) === 1;
    }

    public static function motorista(): bool
    {
        return (int) (self::eu()['motorista'] ?? 0) === 1;
    }

    public static function ambos(): bool
    {
        return self::motorista() && self::operador() && !self::admin();
    }

    public static function temFoto(): bool
    {
        return trim((string) (self::eu()['foto'] ?? '')) !== '';
    }

    public static function administradorFixo(string $nome): bool
    {
        return in_array($nome, self::administradores(), true);
    }

    public static function falhaLogin(): string
    {
        return self::$falhaLogin;
    }

    public static function pode(string ...$papeis): bool
    {
        if (self::admin()) {
            return true;
        }
        foreach ($papeis as $papel) {
            if ($papel === 'motorista' && self::motorista()) {
                return true;
            }
            if ($papel === 'operador' && self::operador()) {
                return true;
            }
        }

        return false;
    }

    public static function podeCadastro(string $chave): bool
    {
        return self::admin();
    }

    public static function inicio(): string
    {
        if (self::admin()) {
            return '/painel';
        }
        if (self::ambos()) {
            return '/trabalhos';
        }
        if (self::motorista()) {
            return '/lavra';
        }
        if (self::operador()) {
            return '/patio';
        }

        return '/login';
    }

    public static function rotuloPapel(): string
    {
        return match (self::papel()) {
            'admin' => 'Administrativo',
            'ambos' => 'Motorista e operador',
            'motorista' => 'Motorista',
            'operador' => 'Operador',
            default => '',
        };
    }

    public static function exigir(): void
    {
        if (!self::configurada()) {
            http_response_code(500);
            render('erro', [
                'titulo' => 'Configuração',
                'mensagem' => 'Cadastre ao menos um usuário em APP_USUARIOS.',
                'topo' => false,
            ]);
            exit;
        }

        if (!self::ok()) {
            redirect('/login');
        }

        $eu = self::eu();
        if ($eu === null || (int) ($eu['ativo'] ?? 0) !== 1) {
            unset($_SESSION['auth'], $_SESSION['usuario'], $_SESSION['visto']);
            self::$eu = null;
            self::$euCarregado = false;
            flash_set('Esse acesso está desativado.', 'erro');
            redirect('/login');
        }
    }

    public static function tentar(string $usuario, string $senha): bool
    {
        self::garantirUsuarios();
        self::$falhaLogin = '';
        $bloqueadoAte = (int) ($_SESSION['login_bloqueado_ate'] ?? 0);
        if ($bloqueadoAte > time()) {
            return false;
        }

        $nome = self::nomeSimples($usuario);
        $linha = null;
        if ($nome !== null) {
            $busca = Db::pdo()->prepare('SELECT id, nome, senha_hash, ativo FROM usuarios WHERE nome = ? LIMIT 1');
            $busca->execute([$nome]);
            $achou = $busca->fetch();
            if ($achou && password_verify($senha, (string) $achou['senha_hash'])) {
                if ((int) ($achou['ativo'] ?? 0) !== 1) {
                    self::$falhaLogin = 'Esse acesso está desativado.';
                    return false;
                }
                $linha = $achou;
            }
        }

        if ($linha) {
            self::abrirSessao((string) $linha['nome']);

            return true;
        }

        $falhas = (int) ($_SESSION['login_falhas'] ?? 0) + 1;
        $_SESSION['login_falhas'] = $falhas;
        if ($falhas >= self::MAX_TENTATIVAS) {
            $_SESSION['login_bloqueado_ate'] = time() + self::BLOQUEIO_SEGUNDOS;
            $_SESSION['login_falhas'] = 0;
        }

        return false;
    }

    public static function entrarPorPessoa(int $id): bool
    {
        self::garantirUsuarios();
        self::$falhaLogin = '';
        $pessoa = Pessoas::elegivelEntrada($id);
        if ($pessoa === null) {
            return false;
        }
        self::abrirSessao((string) $pessoa['nome']);

        return true;
    }

    public static function renovar(): void
    {
        if (empty($_SESSION['auth'])) {
            return;
        }

        $visto = (int) ($_SESSION['visto'] ?? 0);
        if ($visto > 0 && (time() - $visto) > self::SESSAO_SEGUNDOS) {
            unset($_SESSION['auth'], $_SESSION['usuario'], $_SESSION['visto']);
            flash_set('A sessão expirou. Entre de novo.', 'erro');

            return;
        }

        $_SESSION['visto'] = time();
        self::renovarCookie();
    }

    public static function sair(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
    }

    public static function nomeSimples(string $nome): ?string
    {
        $nome = trim((string) (preg_replace('/\s+/u', ' ', $nome) ?? ''));
        if ($nome === '' || str_contains($nome, '@')) {
            return null;
        }
        if (!preg_match('/^[\p{L}][\p{L}\p{N} ]{0,39}$/u', $nome)) {
            return null;
        }

        return $nome;
    }

    /** O cookie da sessão vale o turno e é renovado enquanto o tablet está em uso. */
    private static function renovarCookie(): void
    {
        if ((int) ($_SESSION['cookie_em'] ?? 0) > time() - 600 || headers_sent()) {
            return;
        }
        $params = session_get_cookie_params();
        setcookie(session_name(), (string) session_id(), [
            'expires' => time() + self::SESSAO_SEGUNDOS,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => (string) ($params['samesite'] ?? 'Strict'),
        ]);
        $_SESSION['cookie_em'] = time();
    }

    private static function garantirUsuarios(): void
    {
        $pdo = Db::pdo();
        $existe = $pdo->prepare('SELECT id FROM usuarios WHERE nome = ? LIMIT 1');
        $inserir = $pdo->prepare(
            'INSERT INTO usuarios (nome, nome_completo, senha_hash, administrador, papel, motorista, operador, ativo)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
        );
        $fixarAdmin = $pdo->prepare(
            "UPDATE usuarios SET administrador = 1, papel = 'admin', motorista = 0, operador = 0 WHERE nome = ?"
        );
        foreach (self::listaConfigurada() as $nome => $item) {
            $admin = self::administradorFixo($nome) || $item['papel'] === 'admin';
            $existe->execute([$nome]);
            if ($existe->fetch()) {
                if (self::administradorFixo($nome)) {
                    $fixarAdmin->execute([$nome]);
                }
                continue;
            }
            $motorista = !$admin && ($item['papel'] === 'motorista' || $item['papel'] === 'ambos') ? 1 : 0;
            $operador = !$admin && ($item['papel'] === 'operador' || $item['papel'] === 'ambos') ? 1 : 0;
            $papel = $admin ? 'admin' : $item['papel'];
            $inserir->execute([
                $nome,
                $nome,
                password_hash($item['senha'], PASSWORD_DEFAULT),
                $admin ? 1 : 0,
                $papel,
                $motorista,
                $operador,
            ]);
        }
    }

    private static function abrirSessao(string $nome): void
    {
        session_regenerate_id(true);
        $_SESSION['auth'] = true;
        $_SESSION['usuario'] = $nome;
        $_SESSION['visto'] = time();
        unset($_SESSION['login_falhas'], $_SESSION['login_bloqueado_ate'], $_SESSION['maquina'], $_SESSION['retroescavadeira_id'], $_SESSION['horimetro_turno'], $_SESSION['cookie_em']);
    }

    private static function eu(): ?array
    {
        if (self::$euCarregado) {
            return self::$eu;
        }
        self::$euCarregado = true;
        if (!self::ok()) {
            return null;
        }
        $busca = Db::pdo()->prepare(
            'SELECT id, nome, administrador, motorista, operador, foto, ativo FROM usuarios WHERE nome = ? LIMIT 1'
        );
        $busca->execute([self::usuario()]);
        $linha = $busca->fetch();
        self::$eu = $linha ?: null;

        return self::$eu;
    }

    private static function administradores(): array
    {
        $nomes = [];
        foreach (explode(',', env_str('APP_ADMIN')) as $nome) {
            $limpo = self::nomeSimples($nome);
            if ($limpo !== null) {
                $nomes[$limpo] = true;
            }
        }

        return array_keys($nomes);
    }

    private static function listaConfigurada(): array
    {
        $lista = [];
        foreach (explode(',', env_str('APP_USUARIOS')) as $par) {
            $par = trim($par);
            if ($par === '' || !str_contains($par, ':')) {
                continue;
            }
            $partes = explode(':', $par, 3);
            $nome = self::nomeSimples($partes[0]);
            $senha = $partes[1] ?? '';
            $papel = $partes[2] ?? '';
            if ($nome === null || $senha === '') {
                continue;
            }
            $definido = in_array($papel, ['admin', 'operador', 'motorista', 'ambos'], true);
            $lista[$nome] = [
                'senha' => $senha,
                'papel' => $definido ? $papel : 'operador',
            ];
        }

        return $lista;
    }
}
