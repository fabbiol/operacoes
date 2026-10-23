<?php

declare(strict_types=1);

/**
 * Peças de cada máquina (função, marca, código, quantidade e foto) e os pedidos ao Compras pelo WhatsApp.
 * O pedido guarda uma cópia de cada item e um código secreto para a página pública das fotos.
 */
final class Pecas
{
    public const TIPOS = ['pa', 'retro', 'caminhao'];

    private const NOME_MAQUINA = "COALESCE(pa.maquina, r.maquina, c.placa, 'Máquina')";
    private const JUNTA_MAQUINA = "LEFT JOIN pas_carregadeiras pa ON p.tipo = 'pa' AND pa.id = p.maquina_id
        LEFT JOIN retroescavadeiras r ON p.tipo = 'retro' AND r.id = p.maquina_id
        LEFT JOIN caminhoes c ON p.tipo = 'caminhao' AND c.id = p.maquina_id";

    public static function daMaquina(string $tipo, int $maquinaId): array
    {
        $busca = Db::pdo()->prepare(
            'SELECT id, funcao, marca, codigo, quantidade, foto, ativo FROM maquina_pecas
             WHERE tipo = ? AND maquina_id = ? ORDER BY ativo DESC, funcao ASC, id ASC'
        );
        $busca->execute([$tipo, $maquinaId]);
        $pecas = $busca->fetchAll();
        $trocas = self::ultimasTrocas($tipo, $maquinaId);
        foreach ($pecas as &$peca) {
            $peca['ultima_troca'] = $trocas[(int) $peca['id']] ?? null;
        }
        unset($peca);

        return $pecas;
    }

    public static function buscar(int $id): ?array
    {
        $busca = Db::pdo()->prepare('SELECT * FROM maquina_pecas WHERE id = ? LIMIT 1');
        $busca->execute([$id]);
        $linha = $busca->fetch();

        return $linha ?: null;
    }

    public static function incluir(string $tipo, int $maquinaId, array $post): int
    {
        self::exigirMaquina($tipo, $maquinaId);
        $dados = self::ler($post);
        $pendente = Fotos::receber(Fotos::LADO_MAQUINA);
        try {
            Db::pdo()->prepare(
                'INSERT INTO maquina_pecas (tipo, maquina_id, funcao, marca, codigo, quantidade) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$tipo, $maquinaId, $dados['funcao'], $dados['marca'], $dados['codigo'], $dados['quantidade']]);
            $id = (int) Db::pdo()->lastInsertId();
            if ($pendente !== null) {
                try {
                    self::gravarFoto($id, $pendente);
                    $pendente = null;
                } catch (Throwable $e) {
                    Db::pdo()->prepare('DELETE FROM maquina_pecas WHERE id = ?')->execute([$id]);
                    throw $e;
                }
            }

            return $id;
        } finally {
            if ($pendente !== null && is_file($pendente['tmp'])) {
                unlink($pendente['tmp']);
            }
        }
    }

    public static function editar(string $tipo, int $maquinaId, int $id, array $post): void
    {
        self::daPropriaMaquina($tipo, $maquinaId, $id);
        $dados = self::ler($post);
        $pendente = Fotos::receber(Fotos::LADO_MAQUINA);
        try {
            Db::pdo()->prepare('UPDATE maquina_pecas SET funcao = ?, marca = ?, codigo = ?, quantidade = ? WHERE id = ?')
                ->execute([$dados['funcao'], $dados['marca'], $dados['codigo'], $dados['quantidade'], $id]);
            if ($pendente !== null) {
                self::gravarFoto($id, $pendente);
                $pendente = null;
            } elseif (isset($post['remover_foto'])) {
                self::limparArquivos($id);
                Db::pdo()->prepare('UPDATE maquina_pecas SET foto = NULL WHERE id = ?')->execute([$id]);
            }
        } finally {
            if ($pendente !== null && is_file($pendente['tmp'])) {
                unlink($pendente['tmp']);
            }
        }
    }

    public static function alternarAtivo(string $tipo, int $maquinaId, int $id): void
    {
        self::daPropriaMaquina($tipo, $maquinaId, $id);
        Db::pdo()->prepare('UPDATE maquina_pecas SET ativo = 1 - ativo WHERE id = ?')->execute([$id]);
    }

    /** @return array{caminho: string, tipo: string}|null */
    public static function arquivoFoto(int $id): ?array
    {
        $peca = self::buscar($id);
        $relativo = str_replace('\\', '/', (string) ($peca['foto'] ?? ''));
        if (!preg_match('#^pecas/(\d+)\.(jpg|png|webp)$#', $relativo, $partes) || (int) $partes[1] !== $id) {
            return null;
        }
        $arquivo = OPERACOES_ROOT . '/storage/fotos/' . $relativo;
        if (!is_file($arquivo)) {
            return null;
        }
        $tipos = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

        return ['caminho' => $arquivo, 'tipo' => $tipos[$partes[2]]];
    }

    /**
     * Nomes de quem trabalha com esse tipo de máquina, para o "Confirmado com" do pedido.
     *
     * @return list<string>
     */
    public static function confirmadores(string $tipo): array
    {
        if ($tipo !== 'caminhao') {
            return Pessoas::operadores($tipo);
        }
        $nomes = [];
        foreach (Db::pdo()->query('SELECT nome FROM usuarios WHERE ativo = 1 AND administrador = 0 AND motorista = 1 ORDER BY nome ASC') as $linha) {
            $nomes[] = (string) $linha['nome'];
        }

        return $nomes;
    }

    /** Grava o pedido com a cópia das peças marcadas e devolve o id. */
    public static function criarPedido(string $tipo, int $maquinaId, array $post, string $usuario): int
    {
        self::exigirMaquina($tipo, $maquinaId);
        $marcadas = is_array($post['pecas'] ?? null) ? $post['pecas'] : [];
        $quantidades = is_array($post['qtd'] ?? null) ? $post['qtd'] : [];
        $itens = [];
        foreach (self::daMaquina($tipo, $maquinaId) as $peca) {
            if ((int) $peca['ativo'] !== 1 || !isset($marcadas[$peca['id']])) {
                continue;
            }
            $peca['quantidade'] = self::lerQuantidade((string) ($quantidades[$peca['id']] ?? $peca['quantidade']));
            $itens[] = $peca;
        }
        if ($itens === []) {
            throw new InvalidArgumentException('Marque ao menos uma peça.');
        }
        $confirmado = limpar_texto((string) ($post['confirmado_com'] ?? ''), 40, false, 'Confirmado com');
        if ($confirmado !== null && !in_array($confirmado, self::confirmadores($tipo), true)) {
            throw new InvalidArgumentException('Escolha o operador da lista.');
        }
        $observacoes = limpar_texto((string) ($post['observacoes'] ?? ''), 500, false, 'A observação');
        $horimetro = Horimetro::atual($tipo, $maquinaId);

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO pecas_pedidos (tipo, maquina_id, codigo_publico, horimetro, pedido_por, confirmado_com, observacoes, criado_em)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $tipo,
                $maquinaId,
                bin2hex(random_bytes(16)),
                $horimetro !== null ? number_format($horimetro, 1, '.', '') : null,
                $usuario,
                $confirmado,
                $observacoes,
                agora_sql(),
            ]);
            $pedidoId = (int) $pdo->lastInsertId();
            $item = $pdo->prepare(
                'INSERT INTO pecas_pedido_itens (pedido_id, peca_id, funcao, marca, codigo, quantidade) VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach ($itens as $peca) {
                $item->execute([$pedidoId, $peca['id'], $peca['funcao'], $peca['marca'], $peca['codigo'], $peca['quantidade']]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $pedidoId;
    }

    public static function marcarTrocado(int $pedidoId, string $usuario): void
    {
        $pedido = self::pedido($pedidoId);
        if ($pedido === null) {
            throw new InvalidArgumentException('Pedido não encontrado.');
        }
        $horimetro = Horimetro::atual((string) $pedido['tipo'], (int) $pedido['maquina_id']);
        self::mudarSituacao($pedidoId, 'trocado', [agora_sql(), $horimetro !== null ? number_format($horimetro, 1, '.', '') : null, $usuario]);
    }

    public static function cancelar(int $pedidoId): void
    {
        self::mudarSituacao($pedidoId, 'cancelado', [null, null, null]);
    }

    /** Pedido com o nome da máquina e os itens. */
    public static function pedido(int $id): ?array
    {
        return self::pedidoPor('p.id = ?', $id);
    }

    public static function pedidoPorCodigo(string $codigo): ?array
    {
        if (!preg_match('/^[0-9a-f]{32}$/', $codigo)) {
            return null;
        }

        return self::pedidoPor('p.codigo_publico = ?', $codigo);
    }

    public static function pedidosDaMaquina(string $tipo, int $maquinaId): array
    {
        return self::listarPedidos('WHERE p.tipo = ? AND p.maquina_id = ?', [$tipo, $maquinaId]);
    }

    public static function pedidosRecentes(): array
    {
        return self::listarPedidos('', []);
    }

    /** Texto do WhatsApp para o Compras. */
    public static function mensagem(array $pedido, string $link): string
    {
        $linhas = [
            '*Pedido de peças — ' . $pedido['maquina'] . '*',
            'Horímetro: ' . Horimetro::texto($pedido['horimetro'] !== null ? (float) $pedido['horimetro'] : null),
            'Confirmado com: ' . ((string) ($pedido['confirmado_com'] ?? '') !== '' ? $pedido['confirmado_com'] : 'Ninguém'),
            '',
        ];
        foreach ($pedido['itens'] as $n => $item) {
            $peca = trim(((string) ($item['marca'] ?? '')) . ' ' . $item['codigo']);
            $linhas[] = ($n + 1) . '. ' . $item['funcao'] . ' — ' . $peca . ' (' . (int) $item['quantidade'] . ' un)';
        }
        if ((string) ($pedido['observacoes'] ?? '') !== '') {
            $linhas[] = 'Obs.: ' . $pedido['observacoes'];
        }
        $linhas[] = 'Fotos: ' . $link;

        return implode("\n", $linhas);
    }

    private static function pedidoPor(string $condicao, int|string $valor): ?array
    {
        $busca = Db::pdo()->prepare('SELECT p.*, ' . self::NOME_MAQUINA . ' AS maquina FROM pecas_pedidos p ' . self::JUNTA_MAQUINA . ' WHERE ' . $condicao . ' LIMIT 1');
        $busca->execute([$valor]);
        $pedido = $busca->fetch();
        if (!$pedido) {
            return null;
        }
        $itens = Db::pdo()->prepare(
            'SELECT i.*, mp.foto FROM pecas_pedido_itens i LEFT JOIN maquina_pecas mp ON mp.id = i.peca_id WHERE i.pedido_id = ? ORDER BY i.id ASC'
        );
        $itens->execute([$pedido['id']]);
        $pedido['itens'] = $itens->fetchAll();

        return $pedido;
    }

    private static function listarPedidos(string $onde, array $params): array
    {
        $busca = Db::pdo()->prepare(
            'SELECT p.id, p.tipo, p.maquina_id, p.criado_em, p.pedido_por, p.confirmado_com, p.situacao, p.trocado_em, p.horimetro, '
            . self::NOME_MAQUINA . ' AS maquina,
               (SELECT GROUP_CONCAT(i.funcao ORDER BY i.id SEPARATOR \', \') FROM pecas_pedido_itens i WHERE i.pedido_id = p.id) AS resumo
             FROM pecas_pedidos p ' . self::JUNTA_MAQUINA . ' ' . $onde . ' ORDER BY p.criado_em DESC, p.id DESC LIMIT 50'
        );
        $busca->execute($params);

        return $busca->fetchAll();
    }

    /** @return array<int, array{trocado_em: string, trocado_horimetro: ?string}> */
    private static function ultimasTrocas(string $tipo, int $maquinaId): array
    {
        $busca = Db::pdo()->prepare(
            "SELECT i.peca_id, p.trocado_em, p.trocado_horimetro FROM pecas_pedido_itens i
             JOIN pecas_pedidos p ON p.id = i.pedido_id
             WHERE p.tipo = ? AND p.maquina_id = ? AND p.situacao = 'trocado' AND i.peca_id IS NOT NULL
             ORDER BY p.trocado_em DESC, p.id DESC"
        );
        $busca->execute([$tipo, $maquinaId]);
        $trocas = [];
        foreach ($busca->fetchAll() as $linha) {
            $trocas[(int) $linha['peca_id']] ??= $linha;
        }

        return $trocas;
    }

    private static function mudarSituacao(int $pedidoId, string $situacao, array $troca): void
    {
        $comando = Db::pdo()->prepare(
            "UPDATE pecas_pedidos SET situacao = ?, trocado_em = ?, trocado_horimetro = ?, trocado_por = ?
             WHERE id = ? AND situacao = 'enviado'"
        );
        $comando->execute([$situacao, ...$troca, $pedidoId]);
        if ($comando->rowCount() !== 1) {
            throw new InvalidArgumentException('Esse pedido já foi marcado como trocado ou cancelado.');
        }
    }

    /** @return array{funcao: string, marca: ?string, codigo: string, quantidade: int} */
    private static function ler(array $post): array
    {
        return [
            'funcao' => (string) limpar_texto((string) ($post['funcao'] ?? ''), 120, true, 'a função da peça'),
            'marca' => limpar_texto((string) ($post['marca'] ?? ''), 80, false, 'A marca'),
            'codigo' => (string) limpar_texto((string) ($post['codigo'] ?? ''), 80, true, 'o código da peça'),
            'quantidade' => self::lerQuantidade((string) ($post['quantidade'] ?? '1')),
        ];
    }

    private static function lerQuantidade(string $bruto): int
    {
        $bruto = trim($bruto);
        if (!preg_match('/^\d{1,2}$/', $bruto) || (int) $bruto < 1) {
            throw new InvalidArgumentException('A quantidade precisa ser um número inteiro de 1 a 99.');
        }

        return (int) $bruto;
    }

    private static function exigirMaquina(string $tipo, int $maquinaId): void
    {
        if (!in_array($tipo, self::TIPOS, true) || Cadastro::buscar(Horimetro::chaveDoTipo($tipo), $maquinaId) === null) {
            throw new InvalidArgumentException('Máquina não encontrada.');
        }
    }

    private static function daPropriaMaquina(string $tipo, int $maquinaId, int $id): void
    {
        $peca = self::buscar($id);
        if ($peca === null || $peca['tipo'] !== $tipo || (int) $peca['maquina_id'] !== $maquinaId) {
            throw new InvalidArgumentException('Peça não encontrada.');
        }
    }

    private static function gravarFoto(int $id, array $pendente): void
    {
        self::limparArquivos($id);
        $dir = OPERACOES_ROOT . '/storage/fotos/pecas';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new InvalidArgumentException('Não foi possível guardar a foto.');
        }
        $final = $dir . DIRECTORY_SEPARATOR . $id . '.' . $pendente['ext'];
        if (!@rename($pendente['tmp'], $final)) {
            if (!@copy($pendente['tmp'], $final)) {
                throw new InvalidArgumentException('Não foi possível guardar a foto.');
            }
            unlink($pendente['tmp']);
        }
        Db::pdo()->prepare('UPDATE maquina_pecas SET foto = ? WHERE id = ?')->execute(['pecas/' . $id . '.' . $pendente['ext'], $id]);
    }

    private static function limparArquivos(int $id): void
    {
        foreach (['jpg', 'png', 'webp'] as $ext) {
            $arquivo = OPERACOES_ROOT . '/storage/fotos/pecas/' . $id . '.' . $ext;
            if (is_file($arquivo)) {
                unlink($arquivo);
            }
        }
    }
}
