<?php

declare(strict_types=1);

/**
 * Problemas das máquinas: o operador conta ao soltar a máquina ("quebrou o ar-condicionado"),
 * ou o administrativo registra na ficha. O problema fica aberto até alguém marcar como resolvido,
 * e aparece no Painel, na Manutenção, na ficha da máquina e para quem for pegar a máquina depois.
 */
final class Problemas
{
    public const TAMANHO = 500;

    /** @var list<array<string, mixed>>|null */
    private static ?array $abertos = null;

    /** Texto do problema numa linha, sem espaços sobrando. */
    public static function texto(string $bruto): string
    {
        $texto = trim((string) preg_replace('/\s+/u', ' ', $bruto));
        if ($texto === '') {
            throw new InvalidArgumentException('Escreva qual foi o problema da máquina.');
        }
        if (mb_strlen($texto, 'UTF-8') > self::TAMANHO) {
            throw new InvalidArgumentException('Escreva o problema em até ' . self::TAMANHO . ' letras.');
        }

        return $texto;
    }

    /** Grava o problema. Quem chama confere o texto antes com texto(). */
    public static function relatar(string $tipo, int $maquinaId, string $usuario, string $descricao, ?float $horimetro): void
    {
        Db::pdo()->prepare(
            'INSERT INTO maquina_problemas (tipo, maquina_id, usuario, descricao, horimetro, relatado_em)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $tipo,
            $maquinaId,
            $usuario,
            self::texto($descricao),
            $horimetro !== null ? number_format($horimetro, 1, '.', '') : null,
            agora_sql(),
        ]);
        self::$abertos = null;
    }

    public static function resolver(int $id, string $tipo, int $maquinaId, string $usuario, string $resolucao): void
    {
        $resolucao = trim((string) preg_replace('/\s+/u', ' ', $resolucao));
        if (mb_strlen($resolucao, 'UTF-8') > self::TAMANHO) {
            throw new InvalidArgumentException('Escreva o que foi feito em até ' . self::TAMANHO . ' letras.');
        }
        $comando = Db::pdo()->prepare(
            'UPDATE maquina_problemas
             SET resolvido_em = ?, resolvido_por = ?, resolucao = ?
             WHERE id = ? AND tipo = ? AND maquina_id = ? AND resolvido_em IS NULL'
        );
        $comando->execute([agora_sql(), $usuario, $resolucao !== '' ? $resolucao : null, $id, $tipo, $maquinaId]);
        if ($comando->rowCount() !== 1) {
            throw new InvalidArgumentException('Esse problema já estava resolvido.');
        }
        self::$abertos = null;
    }

    /**
     * Todos os problemas abertos, do mais novo para o mais antigo, com o nome da máquina.
     *
     * @return list<array<string, mixed>>
     */
    public static function abertos(): array
    {
        if (self::$abertos === null) {
            $linhas = Db::pdo()->query(
                'SELECT * FROM maquina_problemas WHERE resolvido_em IS NULL ORDER BY relatado_em DESC, id DESC'
            )->fetchAll();
            self::$abertos = array_map(self::comMaquina(...), $linhas);
        }

        return self::$abertos;
    }

    /** @return list<array<string, mixed>> */
    public static function abertosDa(string $tipo, int $maquinaId): array
    {
        return array_values(array_filter(
            self::abertos(),
            static fn (array $problema): bool => $problema['tipo'] === $tipo && (int) $problema['maquina_id'] === $maquinaId
        ));
    }

    /** @return list<array<string, mixed>> os últimos resolvidos desta máquina */
    public static function resolvidosDa(string $tipo, int $maquinaId, int $limite = 5): array
    {
        $busca = Db::pdo()->prepare(
            'SELECT * FROM maquina_problemas
             WHERE tipo = ? AND maquina_id = ? AND resolvido_em IS NOT NULL
             ORDER BY resolvido_em DESC, id DESC
             LIMIT ' . max(1, $limite)
        );
        $busca->execute([$tipo, $maquinaId]);

        return $busca->fetchAll();
    }

    /** @param array<string, mixed> $problema
     *  @return array<string, mixed>
     */
    private static function comMaquina(array $problema): array
    {
        $chave = maquina_chave((string) $problema['tipo']);
        $problema['chave'] = $chave;
        $problema['maquina'] = Posse::nomeDaMaquina((string) $problema['tipo'], (int) $problema['maquina_id']);

        return $problema;
    }
}
