<?php

declare(strict_types=1);

/**
 * Quem está com cada máquina agora, com o horímetro de entrada do turno.
 * Fica no banco, e não na sessão do tablet: a máquina continua com a pessoa se a sessão cair
 * ou o tablet trocar, e duas pessoas não conseguem pegar a mesma máquina.
 */
final class Posse
{
    // Mesma trava do início de carga: assumir uma máquina e começar a carregar com ela não podem se cruzar.
    public const TRAVA = 'inicio_carga';

    /** @var array<int, array<string, mixed>|null> */
    private static array $doUsuario = [];

    /** @return array<string, mixed>|null */
    public static function doUsuario(int $usuarioId, bool $fresco = false): ?array
    {
        if ($usuarioId < 1) {
            return null;
        }
        if (!$fresco && array_key_exists($usuarioId, self::$doUsuario)) {
            return self::$doUsuario[$usuarioId];
        }
        $busca = Db::pdo()->prepare('SELECT * FROM maquina_posse WHERE usuario_id = ? LIMIT 1');
        $busca->execute([$usuarioId]);
        $linha = $busca->fetch();
        self::$doUsuario[$usuarioId] = $linha ?: null;

        return self::$doUsuario[$usuarioId];
    }

    /** @return array<string, mixed>|null */
    public static function daMaquina(string $tipo, int $maquinaId): ?array
    {
        $busca = Db::pdo()->prepare('SELECT * FROM maquina_posse WHERE tipo = ? AND maquina_id = ? LIMIT 1');
        $busca->execute([$tipo, $maquinaId]);
        $linha = $busca->fetch();

        return $linha ?: null;
    }

    /** @return array<string, array<string, mixed>> chave "tipo:id" */
    public static function todas(): array
    {
        $mapa = [];
        foreach (Db::pdo()->query('SELECT * FROM maquina_posse ORDER BY iniciado_em ASC, id ASC') as $linha) {
            $mapa[$linha['tipo'] . ':' . (int) $linha['maquina_id']] = $linha;
        }

        return $mapa;
    }

    public static function abrir(
        string $tipo,
        int $maquinaId,
        int $usuarioId,
        string $usuario,
        ?float $entrada,
        ?int $entradaId,
        ?string $iniciadoEm = null,
        bool $precisaSoltar = false
    ): void {
        self::$doUsuario = [];
        try {
            Db::pdo()->prepare(
                'INSERT INTO maquina_posse (tipo, maquina_id, usuario_id, usuario, entrada, entrada_id, iniciado_em, precisa_soltar)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $tipo,
                $maquinaId,
                $usuarioId,
                $usuario,
                $entrada === null ? null : number_format($entrada, 1, '.', ''),
                $entradaId,
                $iniciadoEm ?? agora_sql(),
                $precisaSoltar ? 1 : 0,
            ]);
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                throw new InvalidArgumentException('Essa máquina acabou de ser assumida por outra pessoa. Escolha outra.');
            }
            throw $e;
        }
    }

    /** O trabalho em andamento passou para outra máquina: a posse acompanha, sem horímetro de entrada. */
    public static function trocarMaquina(int $usuarioId, string $tipo, int $maquinaId): void
    {
        self::$doUsuario = [];
        Db::pdo()->prepare(
            'UPDATE maquina_posse
             SET tipo = ?, maquina_id = ?, entrada = NULL, entrada_id = NULL, iniciado_em = ?, precisa_soltar = 0
             WHERE usuario_id = ?'
        )->execute([$tipo, $maquinaId, agora_sql(), $usuarioId]);
    }

    public static function fechar(int $usuarioId): void
    {
        self::$doUsuario = [];
        Db::pdo()->prepare('DELETE FROM maquina_posse WHERE usuario_id = ?')->execute([$usuarioId]);
    }

    public static function liberar(string $tipo, int $maquinaId): void
    {
        self::$doUsuario = [];
        Db::pdo()->prepare('DELETE FROM maquina_posse WHERE tipo = ? AND maquina_id = ?')->execute([$tipo, $maquinaId]);
    }

    public static function marcarSoltar(int $usuarioId): void
    {
        self::$doUsuario = [];
        Db::pdo()->prepare('UPDATE maquina_posse SET precisa_soltar = 1 WHERE usuario_id = ?')->execute([$usuarioId]);
    }

    /** A leitura de entrada foi anulada e o administrativo informou a certa: ela passa a ser a entrada do turno. */
    public static function trocarEntrada(int $leituraAnulada, int $leituraCerta, float $valor): void
    {
        self::$doUsuario = [];
        Db::pdo()->prepare('UPDATE maquina_posse SET entrada = ?, entrada_id = ? WHERE entrada_id = ?')
            ->execute([number_format($valor, 1, '.', ''), $leituraCerta, $leituraAnulada]);
    }

    /** A leitura de entrada foi anulada: o turno fica sem entrada e não é somado ao soltar. */
    public static function esquecerEntrada(int $leituraId): void
    {
        self::$doUsuario = [];
        Db::pdo()->prepare('UPDATE maquina_posse SET entrada = NULL, entrada_id = NULL WHERE entrada_id = ?')->execute([$leituraId]);
    }

    /**
     * Antes de começar um trabalho com a máquina: ela não pode estar na oficina nem com outra pessoa,
     * e quem vai trabalhar não pode estar com outra máquina.
     */
    public static function exigirCompativel(string $tipo, int $maquinaId, string $operador): void
    {
        $oficina = Oficina::atual($tipo, $maquinaId);
        if ($oficina !== null) {
            throw new InvalidArgumentException(
                self::nomeDaMaquina($tipo, $maquinaId) . ' está na oficina desde ' . quando((string) $oficina['entrada'])
                . '. O administrativo libera da oficina na ficha dela.'
            );
        }
        $dono = self::daMaquina($tipo, $maquinaId);
        if ($dono !== null && (string) $dono['usuario'] !== $operador) {
            throw new InvalidArgumentException(
                self::nomeDaMaquina($tipo, $maquinaId) . ' está com ' . $dono['usuario'] . ' desde ' . quando((string) $dono['iniciado_em'])
                . '. É preciso soltar a máquina ou o administrativo liberar na ficha dela.'
            );
        }
        $busca = Db::pdo()->prepare('SELECT * FROM maquina_posse WHERE usuario = ? LIMIT 1');
        $busca->execute([$operador]);
        $outra = $busca->fetch();
        if ($outra && ((string) $outra['tipo'] !== $tipo || (int) $outra['maquina_id'] !== $maquinaId)) {
            $nome = self::nomeDaMaquina((string) $outra['tipo'], (int) $outra['maquina_id']);
            throw new InvalidArgumentException(
                $operador . ' está com ' . $nome . '. Use essa máquina ou solte ' . $nome . ' antes.'
            );
        }
    }

    public static function nomeDaMaquina(string $tipo, int $maquinaId): string
    {
        $chave = maquina_chave($tipo);
        $item = $chave !== null ? Cadastro::buscar($chave, $maquinaId) : null;

        return $item !== null ? (string) ($item['nome'] ?? 'A máquina') : 'A máquina';
    }
}
