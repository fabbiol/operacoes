<?php

declare(strict_types=1);

/**
 * Tablets autorizados pelo administrativo. Só eles mostram as fotos da equipe e aceitam a entrada por foto.
 * O aparelho guarda um código aleatório num cookie; o banco guarda só o hash desse código.
 */
final class Tablets
{
    private const COOKIE = 'operacoes_tablet';
    // O Chrome não aceita cookie com validade acima de 400 dias. A validade é renovada a cada entrada.
    private const VALIDADE_SEGUNDOS = 400 * 86400;

    private static ?array $atual = null;
    private static bool $carregado = false;

    public static function atual(): ?array
    {
        if (self::$carregado) {
            return self::$atual;
        }
        self::$carregado = true;
        $codigo = self::codigoDoCookie();
        if ($codigo === null) {
            return null;
        }
        $busca = Db::pdo()->prepare('SELECT id, nome, ativo FROM tablets WHERE token_hash = ? AND ativo = 1 LIMIT 1');
        $busca->execute([hash('sha256', $codigo)]);
        $linha = $busca->fetch();
        self::$atual = $linha ?: null;

        return self::$atual;
    }

    public static function autorizado(): bool
    {
        return self::atual() !== null;
    }

    public static function ativo(int $id): bool
    {
        $busca = Db::pdo()->prepare('SELECT 1 FROM tablets WHERE id = ? AND ativo = 1 LIMIT 1');
        $busca->execute([$id]);

        return (bool) $busca->fetchColumn();
    }

    public static function listar(): array
    {
        return Db::pdo()->query(
            'SELECT id, nome, ativo, autorizado_por, criado_em, ultimo_uso, revogado_em
             FROM tablets
             ORDER BY ativo DESC, nome ASC, id ASC'
        )->fetchAll();
    }

    public static function autorizarEste(string $nome, string $responsavel): void
    {
        $nome = limpar_texto($nome, 60, true, 'o nome do tablet');
        $atual = self::atual();
        if ($atual !== null) {
            throw new InvalidArgumentException('Este aparelho já está autorizado como ' . $atual['nome'] . '.');
        }
        $codigo = bin2hex(random_bytes(32));
        $comando = Db::pdo()->prepare(
            'INSERT INTO tablets (nome, token_hash, ativo, autorizado_por, criado_em) VALUES (?, ?, 1, ?, ?)'
        );
        $comando->execute([$nome, hash('sha256', $codigo), $responsavel, agora_sql()]);
        self::gravarCookie($codigo);
        self::$atual = ['id' => (int) Db::pdo()->lastInsertId(), 'nome' => $nome, 'ativo' => 1];
        self::$carregado = true;
    }

    public static function revogar(int $id): void
    {
        $comando = Db::pdo()->prepare('UPDATE tablets SET ativo = 0, revogado_em = ? WHERE id = ? AND ativo = 1');
        $comando->execute([agora_sql(), $id]);
        if ($comando->rowCount() < 1) {
            throw new InvalidArgumentException('Esse tablet já estava revogado.');
        }
        if (self::$atual !== null && (int) self::$atual['id'] === $id) {
            self::$atual = null;
        }
    }

    public static function registrarUso(): void
    {
        $atual = self::atual();
        $codigo = self::codigoDoCookie();
        if ($atual === null || $codigo === null) {
            return;
        }
        Db::pdo()->prepare('UPDATE tablets SET ultimo_uso = ? WHERE id = ?')->execute([agora_sql(), (int) $atual['id']]);
        self::gravarCookie($codigo);
    }

    private static function codigoDoCookie(): ?string
    {
        $codigo = $_COOKIE[self::COOKIE] ?? '';

        return is_string($codigo) && preg_match('/^[0-9a-f]{64}$/', $codigo) ? $codigo : null;
    }

    private static function gravarCookie(string $codigo): void
    {
        if (headers_sent()) {
            return;
        }
        setcookie(self::COOKIE, $codigo, [
            'expires' => time() + self::VALIDADE_SEGUNDOS,
            'path' => '/',
            'secure' => requisicao_https(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }
}
