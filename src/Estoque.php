<?php

declare(strict_types=1);

/**
 * Estoque das pilhas (Brita 01, Pedrisco...). Só entra material: a pá conta as conchas que colocou na pilha.
 * Os caminhões dos clientes carregam direto da bica, então nada sai daqui.
 */
final class Estoque
{
    private const CONCHAS_MAXIMO = 999;

    /** Cada material da pilha com o total e o que entrou hoje, em conchas, m³ e toneladas. */
    public static function saldos(): array
    {
        $comando = Db::pdo()->prepare(
            'SELECT m.id, m.material AS nome,
                    COALESCE(SUM(e.conchas), 0) AS conchas,
                    COALESCE(SUM(e.conchas * e.concha_m3), 0) AS m3,
                    COALESCE(SUM(CASE WHEN e.colocado_em >= ? THEN e.conchas END), 0) AS conchas_hoje,
                    COALESCE(SUM(CASE WHEN e.colocado_em >= ? THEN e.conchas * e.concha_m3 END), 0) AS m3_hoje
             FROM materiais m
             LEFT JOIN estoque_entradas e ON e.material_id = m.id
             WHERE (m.estoque = 1 AND m.ativo = 1) OR e.id IS NOT NULL
             GROUP BY m.id, m.material
             ORDER BY m.material'
        );
        $hoje = date('Y-m-d 00:00:00');
        $comando->execute([$hoje, $hoje]);
        $saldos = [];
        foreach ($comando->fetchAll() as $linha) {
            $linha['t'] = self::toneladas($linha['m3']);
            $linha['t_hoje'] = self::toneladas($linha['m3_hoje']);
            $saldos[] = $linha;
        }

        return $saldos;
    }

    /** Materiais que o operador pode escolher ao lançar. */
    public static function materiais(?int $incluirId = null): array
    {
        $comando = Db::pdo()->prepare(
            'SELECT id, material AS nome FROM materiais WHERE (estoque = 1 AND ativo = 1) OR id = ? ORDER BY material'
        );
        $comando->execute([$incluirId ?? 0]);

        return $comando->fetchAll();
    }

    public static function recentes(int $limite = 40): array
    {
        $comando = Db::pdo()->prepare(
            self::sqlBase() . ' ORDER BY e.colocado_em DESC, e.id DESC LIMIT ' . max(1, $limite)
        );
        $comando->execute();

        return array_map([self::class, 'comVolume'], $comando->fetchAll());
    }

    public static function buscar(int $id): ?array
    {
        $comando = Db::pdo()->prepare(self::sqlBase() . ' WHERE e.id = ? LIMIT 1');
        $comando->execute([$id]);
        $linha = $comando->fetch();

        return $linha ? self::comVolume($linha) : null;
    }

    /**
     * Lança as conchas na pilha. O operador lança com a pá do turno e na hora;
     * o administrativo escolhe a pá e pode lançar com data passada (o saldo da planilha, por exemplo).
     */
    public static function lancar(array $post, ?array $paDoTurno, string $usuario, bool $admin): int
    {
        $materialId = self::lerMaterial($post, null);
        $conchas = self::lerConchas($post);
        $paId = $admin ? (int) ($post['pa_carregadeira_id'] ?? 0) : (int) ($paDoTurno['id'] ?? 0);
        if ($paId <= 0) {
            throw new InvalidArgumentException($admin ? 'Escolha a pá carregadeira.' : 'Escolha a pá carregadeira do turno antes de lançar no estoque.');
        }
        $pa = self::pa($paId);
        $quando = $admin ? self::lerQuando($post) : agora_sql();
        $observacoes = limpar_texto((string) ($post['observacoes'] ?? ''), 200, false, 'A observação');

        Db::pdo()->prepare(
            'INSERT INTO estoque_entradas (material_id, pa_carregadeira_id, conchas, concha_m3, colocado_em, usuario, observacoes)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$materialId, $paId, $conchas, $pa['concha_m3'], $quando, $usuario, $observacoes]);

        return (int) Db::pdo()->lastInsertId();
    }

    /** Correção do administrativo. A concha gravada no lançamento continua a mesma. */
    public static function corrigir(int $id, array $post): void
    {
        $atual = self::buscar($id);
        if ($atual === null) {
            throw new InvalidArgumentException('Lançamento não encontrado.');
        }
        Db::pdo()->prepare(
            'UPDATE estoque_entradas SET material_id = ?, conchas = ?, colocado_em = ?, observacoes = ? WHERE id = ?'
        )->execute([
            self::lerMaterial($post, (int) $atual['material_id']),
            self::lerConchas($post),
            self::lerQuando($post),
            limpar_texto((string) ($post['observacoes'] ?? ''), 200, false, 'A observação'),
            $id,
        ]);
    }

    public static function apagar(int $id): void
    {
        Db::pdo()->prepare('DELETE FROM estoque_entradas WHERE id = ?')->execute([$id]);
    }

    public static function toneladas(string|float|int $m3): float
    {
        return round((float) $m3 * densidade_brita(), 2);
    }

    private static function sqlBase(): string
    {
        return 'SELECT e.*, m.material AS material_nome, p.maquina AS pa_nome
                FROM estoque_entradas e
                JOIN materiais m ON m.id = e.material_id
                JOIN pas_carregadeiras p ON p.id = e.pa_carregadeira_id';
    }

    private static function comVolume(array $linha): array
    {
        $linha['m3'] = round((int) $linha['conchas'] * (float) $linha['concha_m3'], 2);
        $linha['t'] = self::toneladas($linha['m3']);

        return $linha;
    }

    private static function lerMaterial(array $post, ?int $atual): int
    {
        $id = (int) ($post['material_id'] ?? 0);
        foreach (self::materiais($atual) as $material) {
            if ((int) $material['id'] === $id) {
                return $id;
            }
        }
        throw new InvalidArgumentException('Escolha o material da pilha.');
    }

    private static function lerConchas(array $post): int
    {
        $bruto = trim((string) ($post['conchas'] ?? ''));
        if (!preg_match('/^\d{1,4}$/', $bruto) || (int) $bruto < 1 || (int) $bruto > self::CONCHAS_MAXIMO) {
            throw new InvalidArgumentException('Informe quantas conchas foram colocadas, de 1 a ' . self::CONCHAS_MAXIMO . '.');
        }

        return (int) $bruto;
    }

    private static function pa(int $id): array
    {
        $pa = Cadastro::buscar('pas', $id);
        if ($pa === null || (int) $pa['ativo'] !== 1) {
            throw new InvalidArgumentException('Escolha uma pá carregadeira ativa.');
        }
        if ($pa['concha_m3'] === null || (float) $pa['concha_m3'] <= 0) {
            throw new InvalidArgumentException('A concha da ' . $pa['nome'] . ' não está cadastrada. Avise o administrativo.');
        }

        return $pa;
    }

    private static function lerQuando(array $post): string
    {
        $quando = parse_data_hora((string) ($post['colocado_em'] ?? ''));
        // Uma folga de alguns minutos para o relógio do tablet adiantado.
        if (strtotime($quando) > time() + 300) {
            throw new InvalidArgumentException('A data do lançamento não pode ficar no futuro.');
        }

        return $quando;
    }
}
