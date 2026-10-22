<?php

declare(strict_types=1);

final class Auth
{
    public const SESSAO_SEGUNDOS = 50400;
    private const MAX_TENTATIVAS = 5;
    // Os tablets da pedreira saem pelo mesmo endereço: o limite por endereço só pega abuso grosso.
    private const MAX_TENTATIVAS_IP = 100;
    private const JANELA_MINUTOS = 15;
    private const SENHA_ADMIN_MINIMA = 8;

    private static ?array $eu = null;
    private static bool $euCarregado = false;
    private static string $falhaLogin = '';
    private static bool $senhaFraca = false;

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
        if (self::motorista() && (self::operador() || self::escavadeira())) {
            return 'ambos';
        }
        if (self::motorista()) {
            return 'motorista';
        }
        if (self::operador()) {
            return 'operador';
        }
        if (self::escavadeira()) {
            return 'escavadeira';
        }
        if (self::britador()) {
            return 'britador';
        }

        return '';
    }

    public static function admin(): bool
    {
        return (int) (self::eu()['administrador'] ?? 0) === 1;
    }

    /** Operador de pá carregadeira (pátio). */
    public static function operador(): bool
    {
        return (int) (self::eu()['operador'] ?? 0) === 1;
    }

    /** Operador de escavadeira hidráulica (carga na lavra). */
    public static function escavadeira(): bool
    {
        return (int) (self::eu()['escavadeira'] ?? 0) === 1;
    }

    /** Operador do britador secundário: não usa máquina do cadastro, só marca a britagem. */
    public static function britador(): bool
    {
        return (int) (self::eu()['britador'] ?? 0) === 1;
    }

    public static function motorista(): bool
    {
        return (int) (self::eu()['motorista'] ?? 0) === 1;
    }

    /** Motorista e também operador (de pá ou de escavadeira). */
    public static function ambos(): bool
    {
        return self::motorista() && (self::operador() || self::escavadeira()) && !self::admin();
    }

    public static function temFoto(): bool
    {
        return trim((string) (self::eu()['foto'] ?? '')) !== '';
    }

    public static function administradorFixo(string $nome): bool
    {
        return in_array($nome, self::administradores(), true);
    }

    /** Pessoa criada pela lista APP_USUARIOS do servidor: se o nome mudar aqui, o servidor cria a antiga de novo. */
    public static function configuradoNoServidor(string $nome): bool
    {
        foreach (array_keys(self::listaConfigurada()) as $configurado) {
            if (mb_strtolower((string) $configurado, 'UTF-8') === mb_strtolower($nome, 'UTF-8')) {
                return true;
            }
        }

        return false;
    }

    public static function falhaLogin(): string
    {
        return self::$falhaLogin;
    }

    public static function senhaFraca(): bool
    {
        return self::$senhaFraca;
    }

    public static function senhaAdminMinima(): int
    {
        return self::SENHA_ADMIN_MINIMA;
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
            if ($papel === 'escavadeira' && self::escavadeira()) {
                return true;
            }
            if ($papel === 'britador' && self::britador()) {
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
        if (self::escavadeira()) {
            return '/lavra';
        }
        if (self::britador()) {
            return '/britador';
        }

        return '/login';
    }

    public static function rotuloPapel(): string
    {
        if (self::admin()) {
            return 'Administrativo';
        }

        return self::britador()
            ? Pessoas::ROTULO_BRITADOR
            : Pessoas::rotuloFuncoes(self::motorista(), self::operador(), self::escavadeira());
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
            unset($_SESSION['auth'], $_SESSION['usuario'], $_SESSION['usuario_id'], $_SESSION['visto']);
            self::$eu = null;
            self::$euCarregado = false;
            flash_set('Esse acesso está desativado.', 'erro');
            redirect('/login');
        }

        // Quem entrou pela foto sai junto quando o tablet é revogado.
        $tablet = (int) ($_SESSION['tablet_id'] ?? 0);
        if ($tablet > 0 && !Tablets::ativo($tablet)) {
            unset($_SESSION['auth'], $_SESSION['usuario'], $_SESSION['usuario_id'], $_SESSION['visto'], $_SESSION['tablet_id']);
            self::$eu = null;
            self::$euCarregado = false;
            flash_set('Este tablet foi revogado pelo administrativo.', 'erro');
            redirect('/login');
        }
    }

    public static function tentar(string $usuario, string $senha): bool
    {
        self::garantirUsuarios();
        self::$falhaLogin = '';
        self::$senhaFraca = false;
        $nome = self::nomeSimples($usuario);
        // A contagem usa o nome já limpo: espaços extras ou especiais não abrem um contador novo.
        $chave = mb_substr(mb_strtolower($nome ?? trim($usuario), 'UTF-8'), 0, 40, 'UTF-8');
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $bloqueio = 'Muitas tentativas erradas. Espere ' . self::JANELA_MINUTOS . ' minutos e tente de novo.';
        if (self::bloqueado($chave, $ip)) {
            self::$falhaLogin = $bloqueio;

            return false;
        }
        // A tentativa entra na conta antes de conferir a senha, para pedidos simultâneos não passarem do limite.
        self::registrarFalha($chave, $ip);
        if (self::bloqueado($chave, $ip, true)) {
            self::$falhaLogin = $bloqueio;

            return false;
        }

        $linha = null;
        if ($nome !== null) {
            $busca = Db::pdo()->prepare('SELECT id, nome, senha_hash, ativo, administrador FROM usuarios WHERE nome = ? LIMIT 1');
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
            Db::pdo()->prepare('DELETE FROM login_falhas WHERE usuario = ? AND ip = ?')->execute([$chave, $ip]);
            self::$senhaFraca = (int) $linha['administrador'] === 1
                && (mb_strlen($senha, 'UTF-8') < self::SENHA_ADMIN_MINIMA || $senha === 'troque-esta-senha');
            self::abrirSessao((int) $linha['id'], (string) $linha['nome']);

            return true;
        }

        return false;
    }

    /**
     * Entrada pela foto, no tablet autorizado: a pessoa escolhe a foto e digita a senha dela.
     * Vale o mesmo limite de tentativas erradas da entrada por usuário e senha.
     */
    public static function entrarPorPessoa(int $id, ?string $senha = null): bool
    {
        self::garantirUsuarios();
        self::$falhaLogin = '';
        if (!Tablets::autorizado()) {
            self::$falhaLogin = 'Este aparelho não está autorizado para a entrada por foto.';

            return false;
        }
        $pessoa = Pessoas::elegivelEntrada($id);
        if ($pessoa === null) {
            return false;
        }
        // Sem senha não entra (inclusive o pedido de uma tela antiga, de antes da senha na foto).
        if ($senha === null || $senha === '') {
            self::$falhaLogin = 'Digite a senha.';

            return false;
        }
        if (!self::tentar((string) $pessoa['nome'], $senha)) {
            return false;
        }
        $_SESSION['tablet_id'] = (int) (Tablets::atual()['id'] ?? 0);
        Tablets::registrarUso();

        return true;
    }

    public static function renovar(): void
    {
        if (empty($_SESSION['auth'])) {
            return;
        }

        $visto = (int) ($_SESSION['visto'] ?? 0);
        if ($visto > 0 && (time() - $visto) > self::SESSAO_SEGUNDOS) {
            unset($_SESSION['auth'], $_SESSION['usuario'], $_SESSION['usuario_id'], $_SESSION['visto']);
            flash_set('A sessão expirou. Entre de novo.', 'erro');

            return;
        }

        $_SESSION['visto'] = time();
        self::renovarCookie();
    }

    /**
     * Esvazia a sessão e troca o código dela. A sessão nova continua aberta para guardar o envio do Sair:
     * assim um toque duplo em "Soltar e sair" não cai num rosto da tela de entrada.
     * O código antigo fica gravado vazio: um segundo pedido que já esperava por ele não repete o Sair.
     */
    public static function sair(): void
    {
        $_SESSION = [];
        session_regenerate_id(false);
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

    /** As tentativas erradas ficam no banco: apagar o cookie não zera a contagem. */
    /** Com $contandoEsta, a tentativa atual já está gravada e entra no limite. */
    private static function bloqueado(string $usuario, string $ip, bool $contandoEsta = false): bool
    {
        $folga = $contandoEsta ? 1 : 0;
        $desde = date('Y-m-d H:i:s', time() - self::JANELA_MINUTOS * 60);
        $porUsuario = Db::pdo()->prepare('SELECT COUNT(*) FROM login_falhas WHERE usuario = ? AND ip = ? AND criado_em >= ?');
        $porUsuario->execute([$usuario, $ip, $desde]);
        if ((int) $porUsuario->fetchColumn() > self::MAX_TENTATIVAS - 1 + $folga) {
            return true;
        }
        $porIp = Db::pdo()->prepare('SELECT COUNT(*) FROM login_falhas WHERE ip = ? AND criado_em >= ?');
        $porIp->execute([$ip, $desde]);

        return (int) $porIp->fetchColumn() > self::MAX_TENTATIVAS_IP - 1 + $folga;
    }

    private static function registrarFalha(string $usuario, string $ip): void
    {
        $pdo = Db::pdo();
        $pdo->prepare('DELETE FROM login_falhas WHERE criado_em < ?')->execute([date('Y-m-d H:i:s', time() - 86400)]);
        $pdo->prepare('INSERT INTO login_falhas (usuario, ip, criado_em) VALUES (?, ?, ?)')->execute([$usuario, $ip, agora_sql()]);
    }

    private static function garantirUsuarios(): void
    {
        $pdo = Db::pdo();
        $existe = $pdo->prepare('SELECT id FROM usuarios WHERE nome = ? LIMIT 1');
        $inserir = $pdo->prepare(
            'INSERT INTO usuarios (nome, nome_completo, senha_hash, administrador, papel, motorista, operador, escavadeira, ativo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)'
        );
        $fixarAdmin = $pdo->prepare(
            "UPDATE usuarios SET administrador = 1, papel = 'admin', motorista = 0, operador = 0, escavadeira = 0 WHERE nome = ?"
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
            // "operador" na configuração continua valendo para as duas máquinas, como antes.
            $escavadeira = !$admin && in_array($item['papel'], ['operador', 'ambos', 'escavadeira'], true) ? 1 : 0;
            $papel = $admin ? 'admin' : $item['papel'];
            $inserir->execute([
                $nome,
                $nome,
                password_hash($item['senha'], PASSWORD_DEFAULT),
                $admin ? 1 : 0,
                $papel,
                $motorista,
                $operador,
                $escavadeira,
            ]);
        }
    }

    private static function abrirSessao(int $id, string $nome): void
    {
        session_regenerate_id(true);
        $_SESSION['auth'] = true;
        $_SESSION['usuario'] = $nome;
        // A pessoa é reconhecida pelo número: se o administrativo trocar o nome, a sessão continua valendo.
        $_SESSION['usuario_id'] = $id;
        $_SESSION['visto'] = time();
        unset($_SESSION['maquina'], $_SESSION['retroescavadeira_id'], $_SESSION['horimetro_turno'], $_SESSION['cookie_em'], $_SESSION['tablet_id']);
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
        $sql = 'SELECT id, nome, administrador, motorista, operador, escavadeira, britador, foto, ativo FROM usuarios';
        $id = (int) ($_SESSION['usuario_id'] ?? 0);
        if ($id > 0) {
            $busca = Db::pdo()->prepare($sql . ' WHERE id = ? LIMIT 1');
            $busca->execute([$id]);
        } else {
            // Sessão aberta antes desta versão, que guardava só o nome.
            $busca = Db::pdo()->prepare($sql . ' WHERE nome = ? LIMIT 1');
            $busca->execute([self::usuario()]);
        }
        $linha = $busca->fetch();
        self::$eu = $linha ?: null;
        if (self::$eu !== null) {
            $_SESSION['usuario_id'] = (int) self::$eu['id'];
            // Com o nome trocado pelo administrativo, a sessão aberta passa a usar o nome novo.
            $_SESSION['usuario'] = (string) self::$eu['nome'];
        }

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
            $definido = in_array($papel, ['admin', 'operador', 'motorista', 'ambos', 'escavadeira'], true);
            $lista[$nome] = [
                'senha' => $senha,
                'papel' => $definido ? $papel : 'operador',
            ];
        }

        return $lista;
    }
}
