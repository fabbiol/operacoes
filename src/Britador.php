<?php

declare(strict_types=1);

/**
 * Britador secundário: o operador dele marca no tablet quando começa e quando termina a britagem
 * e avisa os problemas (com ou sem parada do britador), que ficam abertos até ele marcar Resolvido.
 * Pode haver mais de uma britagem no dia (parada do almoço, por exemplo).
 */
final class Britador
{
    public static function aberta(): ?array
    {
        $linha = Db::pdo()->query('SELECT * FROM britagens WHERE fim IS NULL ORDER BY inicio DESC, id DESC LIMIT 1')->fetch();

        return $linha ?: null;
    }

    public static function iniciar(string $usuario): void
    {
        Db::comTrava('britagem', static function () use ($usuario): void {
            if (self::aberta() !== null) {
                throw new InvalidArgumentException('A britagem já está em andamento.');
            }
            Db::pdo()->prepare('INSERT INTO britagens (usuario, inicio) VALUES (?, ?)')->execute([$usuario, agora_sql()]);
        });
    }

    public static function terminar(string $usuario): void
    {
        $comando = Db::pdo()->prepare('UPDATE britagens SET fim = ?, terminado_por = ? WHERE fim IS NULL');
        $comando->execute([agora_sql(), $usuario]);
        if ($comando->rowCount() === 0) {
            throw new InvalidArgumentException('Não há britagem em andamento.');
        }
    }

    public static function avisar(string $usuario, string $descricao, bool $parou): void
    {
        Db::pdo()->prepare('INSERT INTO britagem_problemas (usuario, descricao, parou, inicio) VALUES (?, ?, ?, ?)')
            ->execute([$usuario, Problemas::texto($descricao), $parou ? 1 : 0, agora_sql()]);
    }

    public static function resolver(int $id, string $usuario): void
    {
        $comando = Db::pdo()->prepare('UPDATE britagem_problemas SET resolvido_em = ?, resolvido_por = ? WHERE id = ? AND resolvido_em IS NULL');
        $comando->execute([agora_sql(), $usuario, $id]);
        if ($comando->rowCount() !== 1) {
            throw new InvalidArgumentException('Esse problema já estava resolvido.');
        }
    }

    /**
     * O dia do britador: primeiro início, último fim (null se ainda britando), se está parado por problema
     * e os problemas do dia (os abertos de dias anteriores também, até alguém resolver).
     *
     * @return array{inicio: ?string, fim: ?string, aberta: bool, aberta_desde: ?string, parado: bool, problemas: list<array<string, mixed>>}
     */
    public static function doDia(string $dia): array
    {
        $comando = Db::pdo()->prepare(
            'SELECT MIN(inicio) AS inicio, MAX(fim) AS fim, SUM(fim IS NULL) AS abertas
             FROM britagens WHERE inicio >= ? AND inicio <= ?'
        );
        $comando->execute([$dia . ' 00:00:00', $dia . ' 23:59:59']);
        $linha = $comando->fetch() ?: [];
        $aberta = self::aberta();

        $problemas = Db::pdo()->prepare(
            'SELECT * FROM britagem_problemas
             WHERE resolvido_em IS NULL OR inicio >= ?
             ORDER BY resolvido_em IS NULL DESC, inicio DESC, id DESC'
        );
        $problemas->execute([$dia . ' 00:00:00']);
        $lista = $problemas->fetchAll();
        $parado = false;
        foreach ($lista as $problema) {
            if ($problema['resolvido_em'] === null && (int) $problema['parou'] === 1) {
                $parado = true;
            }
        }

        return [
            'inicio' => $linha['inicio'] ?? null,
            // Ainda britando: não há fim do dia para mostrar.
            'fim' => $aberta === null ? ($linha['fim'] ?? null) : null,
            'aberta' => $aberta !== null,
            'aberta_desde' => $aberta['inicio'] ?? null,
            'parado' => $parado,
            'problemas' => $lista,
        ];
    }
}
