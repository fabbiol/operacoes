<?php

declare(strict_types=1);

/**
 * Máquina na oficina e a ordem de serviço. Enquanto a estadia está aberta (sem saída), ninguém assume a máquina
 * nem começa trabalho com ela. Ao liberar, a estadia vira a ordem de serviço: o que foi feito, quem fez,
 * o pedido de peças e o custo.
 */
final class Oficina
{
    /** @var array<string, array<string, mixed>>|null chave "tipo:id" */
    private static ?array $abertas = null;

    /** Estadia aberta da máquina, lida na hora (usada dentro da trava de início de trabalho). */
    public static function atual(string $tipo, int $maquinaId): ?array
    {
        $busca = Db::pdo()->prepare('SELECT * FROM maquina_oficina WHERE tipo = ? AND maquina_id = ? AND saida IS NULL ORDER BY id DESC LIMIT 1');
        $busca->execute([$tipo, $maquinaId]);
        $linha = $busca->fetch();

        return $linha ?: null;
    }

    /**
     * Máquinas na oficina agora, com o nome, da que entrou primeiro para a última.
     *
     * @return array<string, array<string, mixed>> chave "tipo:id"
     */
    public static function abertas(): array
    {
        if (self::$abertas === null) {
            self::$abertas = [];
            foreach (Db::pdo()->query('SELECT * FROM maquina_oficina WHERE saida IS NULL ORDER BY entrada ASC, id ASC') as $linha) {
                $linha['nome'] = Posse::nomeDaMaquina((string) $linha['tipo'], (int) $linha['maquina_id']);
                self::$abertas[$linha['tipo'] . ':' . (int) $linha['maquina_id']] = $linha;
            }
        }

        return self::$abertas;
    }

    public static function mandar(string $tipo, int $maquinaId, string $motivo, int $problemaId, string $usuario): void
    {
        $chave = Horimetro::chaveDoTipo($tipo);
        if (Cadastro::buscar($chave, $maquinaId) === null) {
            throw new InvalidArgumentException('Máquina não encontrada.');
        }
        $motivo = (string) limpar_texto($motivo, 500, true, 'o motivo');
        if ($problemaId > 0 && !in_array($problemaId, array_map(
            static fn (array $p): int => (int) $p['id'],
            Problemas::abertosDa($tipo, $maquinaId)
        ), true)) {
            throw new InvalidArgumentException('Escolha um problema aberto desta máquina.');
        }
        // Mesma trava de assumir a máquina e começar trabalho: ninguém pega a máquina no meio do envio.
        Db::comTrava(Posse::TRAVA, static function () use ($tipo, $chave, $maquinaId, $motivo, $problemaId, $usuario): void {
            if (self::atual($tipo, $maquinaId) !== null) {
                throw new InvalidArgumentException('Essa máquina já está na oficina.');
            }
            if (Cadastro::emTrabalho($chave, $maquinaId)) {
                throw new InvalidArgumentException('Essa máquina está em trabalho agora. Encerre a viagem ou a carga antes de mandar para a oficina.');
            }
            $posse = Posse::daMaquina($tipo, $maquinaId);
            if ($posse !== null) {
                throw new InvalidArgumentException('Essa máquina está com ' . $posse['usuario'] . '. Libere a máquina antes de mandar para a oficina.');
            }
            Db::pdo()->prepare(
                'INSERT INTO maquina_oficina (tipo, maquina_id, motivo, problema_id, entrada, entrada_por) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$tipo, $maquinaId, $motivo, $problemaId > 0 ? $problemaId : null, agora_sql(), $usuario]);
        });
        self::$abertas = null;
    }

    /** Libera da oficina e grava a ordem de serviço. O problema ligado fica resolvido com o que foi feito. */
    public static function liberar(string $tipo, int $maquinaId, array $post, string $usuario): void
    {
        $aberta = self::atual($tipo, $maquinaId);
        if ($aberta === null) {
            throw new InvalidArgumentException('Essa máquina não está na oficina.');
        }
        $servico = (string) limpar_texto((string) ($post['servico'] ?? ''), 2000, true, 'o que foi feito');
        $executor = limpar_texto((string) ($post['executor'] ?? ''), 120, false, 'Quem fez');
        $pedidoId = (int) ($post['pedido_id'] ?? 0);
        if ($pedidoId > 0) {
            $pedido = Pecas::pedido($pedidoId);
            if ($pedido === null || $pedido['tipo'] !== $tipo || (int) $pedido['maquina_id'] !== $maquinaId) {
                throw new InvalidArgumentException('Escolha um pedido de peças desta máquina.');
            }
        }
        $custo = self::custo((string) ($post['custo'] ?? ''));

        $comando = Db::pdo()->prepare(
            'UPDATE maquina_oficina SET saida = ?, saida_por = ?, servico = ?, executor = ?, pedido_id = ?, custo = ?
             WHERE id = ? AND saida IS NULL'
        );
        $comando->execute([agora_sql(), $usuario, $servico, $executor, $pedidoId > 0 ? $pedidoId : null, $custo, $aberta['id']]);
        if ($comando->rowCount() !== 1) {
            throw new InvalidArgumentException('Essa máquina já foi liberada da oficina.');
        }
        self::$abertas = null;
        if ($aberta['problema_id'] !== null) {
            try {
                Problemas::resolver((int) $aberta['problema_id'], $tipo, $maquinaId, $usuario, mb_substr($servico, 0, Problemas::TAMANHO, 'UTF-8'));
            } catch (InvalidArgumentException) {
                // Alguém já marcou o problema como resolvido na ficha: a ordem de serviço vale do mesmo jeito.
            }
        }
    }

    /** @return list<array<string, mixed>> ordens de serviço (estadias encerradas), da mais nova para a mais antiga */
    public static function historico(string $tipo, int $maquinaId): array
    {
        $busca = Db::pdo()->prepare(
            'SELECT * FROM maquina_oficina WHERE tipo = ? AND maquina_id = ? AND saida IS NOT NULL ORDER BY entrada DESC, id DESC LIMIT 30'
        );
        $busca->execute([$tipo, $maquinaId]);

        return $busca->fetchAll();
    }

    /**
     * Disponibilidade no mês corrente até agora: 100% menos o tempo na oficina dentro do mês.
     * Só as máquinas que passaram pela oficina no mês aparecem; as outras estão com 100%.
     *
     * @return array<string, float> chave "tipo:id"
     */
    public static function disponibilidades(): array
    {
        $inicio = strtotime(date('Y-m-01 00:00:00'));
        $agora = time();
        $busca = Db::pdo()->prepare('SELECT tipo, maquina_id, entrada, saida FROM maquina_oficina WHERE entrada < ? AND (saida IS NULL OR saida > ?)');
        $busca->execute([date('Y-m-d H:i:s', $agora), date('Y-m-d H:i:s', $inicio)]);
        $parada = [];
        foreach ($busca->fetchAll() as $linha) {
            // Só o pedaço da estadia que cai dentro do mês até agora.
            $de = max((int) strtotime((string) $linha['entrada']), $inicio);
            $ate = min($linha['saida'] !== null ? (int) strtotime((string) $linha['saida']) : $agora, $agora);
            $chave = $linha['tipo'] . ':' . (int) $linha['maquina_id'];
            $parada[$chave] = ($parada[$chave] ?? 0) + max(0, $ate - $de);
        }
        $total = max(1, $agora - $inicio);

        return array_map(static fn (int $segundos): float => max(0.0, 100 * (1 - $segundos / $total)), $parada);
    }

    /** 97.5 -> "97,5%"; 100 -> "100%". */
    public static function textoDisponibilidade(float $porcentagem): string
    {
        return rtrim(rtrim(number_format($porcentagem, 1, ',', '.'), '0'), ',') . '%';
    }

    /** Custo em reais (opcional), lido como o valor da nota: "1.250,50", "1250,50" ou "1250.50". */
    private static function custo(string $bruto): ?string
    {
        $bruto = trim(str_replace(['R$', ' ', "\xc2\xa0"], '', $bruto));
        if ($bruto === '') {
            return null;
        }
        if (str_contains($bruto, ',') && str_contains($bruto, '.')) {
            $bruto = str_replace('.', '', $bruto);
        }
        $bruto = str_replace(',', '.', $bruto);
        if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $bruto)) {
            throw new InvalidArgumentException('O custo precisa ser um número, como 1.250,50.');
        }

        return number_format((float) $bruto, 2, '.', '');
    }
}
