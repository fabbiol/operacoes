<?php

declare(strict_types=1);

final class Operacoes
{
    public static function contagens(): array
    {
        $totais = array_fill_keys(array_keys(status_lista()), 0);
        foreach (Db::pdo()->query('SELECT status, COUNT(*) AS total FROM fila_carregamentos GROUP BY status') as $linha) {
            $totais[(string) $linha['status']] = (int) $linha['total'];
        }

        return $totais;
    }

    public static function listar(string $situacao, string $busca): array
    {
        $sql = self::sqlBase() . ' WHERE 1 = 1';
        $params = [];
        if ($situacao === '' || $situacao === 'abertas') {
            $sql .= " AND f.status NOT IN ('concluido', 'cancelado')";
        } elseif ($situacao !== 'todas') {
            if (!isset(status_lista()[$situacao])) {
                throw new InvalidArgumentException('Situação inválida.');
            }
            $sql .= ' AND f.status = ?';
            $params[] = $situacao;
        }

        $termo = trim($busca);
        if ($termo !== '') {
            $like = '%' . self::escaparLike($termo) . '%';
            $sql .= ' AND (f.ordem LIKE ? OR f.cliente LIKE ? OR f.pedido LIKE ? OR f.nf LIKE ? OR p.maquina LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }

        $sql .= " ORDER BY FIELD(f.status, 'aguardando_conferencia', 'aguardando_autorizacao', 'carregando', 'liberado', 'concluido', 'cancelado'), f.data DESC, f.id DESC LIMIT 300";
        $comando = Db::pdo()->prepare($sql);
        $comando->execute($params);

        return $comando->fetchAll();
    }

    public static function patio(): array
    {
        $sql = self::sqlBase() . " WHERE f.status IN ('liberado', 'carregando')
            ORDER BY FIELD(f.status, 'carregando', 'liberado'), f.data ASC, f.id ASC";

        return Db::pdo()->query($sql)->fetchAll();
    }

    public static function enviadasHoje(): array
    {
        $sql = self::sqlBase() . " WHERE f.status IN ('aguardando_conferencia', 'concluido')
            AND f.fim_carregamento >= ?
            ORDER BY f.fim_carregamento DESC
            LIMIT 20";
        $comando = Db::pdo()->prepare($sql);
        $comando->execute([date('Y-m-d 00:00:00')]);

        return $comando->fetchAll();
    }

    public static function tempos(array $ordem): array
    {
        $fila = segundos_entre($ordem['autorizado_em'] ?? null, $ordem['inicio_carregamento'] ?? null);
        $carga = segundos_entre($ordem['inicio_carregamento'] ?? null, $ordem['fim_carregamento'] ?? null);
        $ciclo = segundos_entre($ordem['autorizado_em'] ?? $ordem['inicio_carregamento'] ?? null, $ordem['fim_carregamento'] ?? null);

        return [
            'fila' => $fila,
            'carga' => $carga,
            'ciclo' => $ciclo,
        ];
    }

    public static function resumo(string $dia, ?string $somenteOperador = null): array
    {
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', $dia);
        $erros = DateTimeImmutable::getLastErrors();
        if (!$data || ($erros['warning_count'] ?? 0) > 0 || ($erros['error_count'] ?? 0) > 0) {
            throw new InvalidArgumentException('Data inválida.');
        }

        $sql = self::sqlBase() . " WHERE f.status IN ('aguardando_conferencia', 'concluido')
            AND f.fim_carregamento >= ? AND f.fim_carregamento <= ?";
        $params = [$dia . ' 00:00:00', $dia . ' 23:59:59'];
        if ($somenteOperador !== null && $somenteOperador !== '') {
            $sql .= ' AND f.operador = ?';
            $params[] = $somenteOperador;
        }
        $comando = Db::pdo()->prepare($sql . ' ORDER BY f.fim_carregamento DESC, f.id DESC');
        $comando->execute($params);

        $linhas = [];
        foreach ($comando->fetchAll() as $ordem) {
            $linhas[] = $ordem + ['tempos' => self::tempos($ordem)];
        }

        return [
            'dia' => $dia,
            'maquina' => [
                'medias' => self::mediasDe($linhas),
                'total' => count($linhas),
                'grupos' => self::agrupar($linhas, 'pa_nome', 'Sem pá'),
            ],
            'operador' => [
                'medias' => self::mediasDe($linhas),
                'total' => count($linhas),
                'grupos' => self::agrupar($linhas, 'operador', 'Sem identificação'),
            ],
        ];
    }

    public static function concluidosDo(string $operador, string $desde): array
    {
        $comando = Db::pdo()->prepare(
            self::sqlBase() . " WHERE f.operador = ?
                AND f.status IN ('aguardando_conferencia', 'concluido')
                AND f.fim_carregamento >= ?
             ORDER BY f.fim_carregamento DESC, f.id DESC"
        );
        $comando->execute([$operador, $desde]);
        $linhas = [];
        foreach ($comando->fetchAll() as $ordem) {
            $linhas[] = $ordem + ['tempos' => self::tempos($ordem)];
        }

        return $linhas;
    }

    public static function buscar(int $id): ?array
    {
        $sql = self::sqlBase() . ' WHERE f.id = ? LIMIT 1';
        $comando = Db::pdo()->prepare($sql);
        $comando->execute([$id]);
        $linha = $comando->fetch();

        return $linha ?: null;
    }

    public static function exigir(int $id): array
    {
        $ordem = self::buscar($id);
        if ($ordem === null) {
            throw new InvalidArgumentException('Ordem não encontrada.');
        }

        return $ordem;
    }

    public static function pasEmUso(int $excetoOrdemId = 0): array
    {
        $comando = Db::pdo()->prepare(
            "SELECT pa_carregadeira_id, ordem FROM fila_carregamentos
             WHERE status = 'carregando' AND pa_carregadeira_id IS NOT NULL AND id <> ?"
        );
        $comando->execute([$excetoOrdemId]);
        $mapa = [];
        foreach ($comando->fetchAll() as $linha) {
            $mapa[(int) $linha['pa_carregadeira_id']] = (string) $linha['ordem'];
        }

        return $mapa;
    }

    public static function operadorEmCarga(string $operador): bool
    {
        return self::paEmOperacao($operador) !== null;
    }

    /** Ordem que o operador está carregando no pátio, se houver. */
    public static function ordemDoOperador(string $operador, int $excetoOrdemId = 0): ?string
    {
        $comando = Db::pdo()->prepare(
            "SELECT ordem FROM fila_carregamentos
             WHERE status = 'carregando' AND operador = ? AND id <> ?
             ORDER BY inicio_carregamento DESC, id DESC
             LIMIT 1"
        );
        $comando->execute([$operador, $excetoOrdemId]);
        $ordem = $comando->fetchColumn();

        return $ordem === false ? null : (string) $ordem;
    }

    public static function paEmOperacao(string $operador): ?array
    {
        $comando = Db::pdo()->prepare(
            "SELECT p.id AS pa_id, p.maquina AS pa_nome, p.foto AS pa_foto,
                    r.id AS retro_id, r.maquina AS retro_nome, r.foto AS retro_foto
             FROM fila_carregamentos f
             LEFT JOIN pas_carregadeiras p ON p.id = f.pa_carregadeira_id
             LEFT JOIN retroescavadeiras r ON r.id = f.retroescavadeira_id
             WHERE f.status = 'carregando' AND f.operador = ?
               AND (f.pa_carregadeira_id IS NOT NULL OR f.retroescavadeira_id IS NOT NULL)
             ORDER BY f.inicio_carregamento DESC, f.id DESC
             LIMIT 1"
        );
        $comando->execute([$operador]);
        $linha = $comando->fetch();
        if (!$linha) {
            return null;
        }
        if ((int) ($linha['pa_id'] ?? 0) > 0) {
            $id = (int) $linha['pa_id'];

            return [
                'tipo' => 'pa',
                'id' => $id,
                'nome' => (string) $linha['pa_nome'],
                'foto' => !empty($linha['pa_foto']) ? url('cadastros/pas/' . $id . '/foto') : null,
            ];
        }

        $id = (int) $linha['retro_id'];

        return [
            'tipo' => 'retro',
            'id' => $id,
            'nome' => (string) $linha['retro_nome'],
            'foto' => !empty($linha['retro_foto']) ? url('cadastros/retros/' . $id . '/foto') : null,
        ];
    }

    public static function criar(array $post, string $responsavel): int
    {
        $dados = self::lerOrdem($post);
        $liberar = isset($post['liberar']);
        $pdo = Db::pdo();

        for ($tentativa = 0; $tentativa < 5; $tentativa++) {
            $pdo->beginTransaction();
            try {
                $ultimo = $pdo->query(
                    "SELECT COALESCE(MAX(CAST(SUBSTRING(ordem, 5) AS UNSIGNED)), 0) FROM fila_carregamentos"
                )->fetchColumn();
                $ordem = sprintf('CAR-%03d', ((int) $ultimo) + 1);
                $comando = $pdo->prepare(
                    'INSERT INTO fila_carregamentos (
                        ordem, data, cliente, pedido, nf, material_id, quantidade_autorizada, unidade,
                        observacoes, status, autorizado_por, autorizado_em
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $status = $liberar ? 'liberado' : 'aguardando_autorizacao';
                $comando->execute([
                    $ordem,
                    $dados['data'],
                    $dados['cliente'],
                    $dados['pedido'],
                    $dados['nf'],
                    $dados['material_id'],
                    $dados['quantidade_autorizada'],
                    $dados['unidade'],
                    $dados['observacoes'],
                    $status,
                    $liberar ? $responsavel : null,
                    $liberar ? agora_sql() : null,
                ]);
                $id = (int) $pdo->lastInsertId();
                $pdo->commit();

                return $id;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if (isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1062 && $tentativa < 4) {
                    continue;
                }
                throw $e;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        }

        throw new RuntimeException('Não foi possível gerar o número da ordem.');
    }

    public static function atualizar(int $id, array $post): void
    {
        $ordem = self::exigir($id);
        if (in_array($ordem['status'], ['concluido', 'cancelado'], true)) {
            throw new InvalidArgumentException('Essa ordem está encerrada e não pode ser alterada.');
        }

        $observacoes = limpar_longo((string) ($post['observacoes'] ?? ''), 4000);
        if (in_array($ordem['status'], ['carregando', 'aguardando_conferencia'], true)) {
            $comando = Db::pdo()->prepare(
                'UPDATE fila_carregamentos SET observacoes = ? WHERE id = ? AND status = ?'
            );
            $comando->execute([$observacoes, $id, $ordem['status']]);
            self::aindaNaSituacao($id, [$ordem['status']]);

            return;
        }

        $dados = self::lerOrdem($post, $ordem);
        $comando = Db::pdo()->prepare(
            "UPDATE fila_carregamentos
             SET data = ?, cliente = ?, pedido = ?, nf = ?, material_id = ?, quantidade_autorizada = ?,
                 unidade = ?, observacoes = ?
             WHERE id = ? AND status IN ('aguardando_autorizacao', 'liberado')"
        );
        $comando->execute([
            $dados['data'],
            $dados['cliente'],
            $dados['pedido'],
            $dados['nf'],
            $dados['material_id'],
            $dados['quantidade_autorizada'],
            $dados['unidade'],
            $observacoes,
            $id,
        ]);
        self::aindaNaSituacao($id, ['aguardando_autorizacao', 'liberado']);
    }

    public static function liberar(int $id, string $responsavel): void
    {
        $ordem = self::exigir($id);
        if ($ordem['status'] !== 'aguardando_autorizacao') {
            throw new InvalidArgumentException('Só dá para liberar uma ordem que ainda aguarda autorização.');
        }
        foreach (['cliente', 'pedido', 'nf', 'material_id', 'quantidade_autorizada'] as $campo) {
            if ($ordem[$campo] === null || $ordem[$campo] === '') {
                throw new InvalidArgumentException('Confira cliente, pedido, nota, material e quantidade antes de liberar.');
            }
        }

        $comando = Db::pdo()->prepare(
            "UPDATE fila_carregamentos
             SET status = 'liberado', autorizado_por = ?, autorizado_em = ?
             WHERE id = ? AND status = 'aguardando_autorizacao'"
        );
        $comando->execute([$responsavel, agora_sql(), $id]);
        self::exigirAlteracao($comando);
    }

    public static function iniciar(int $id, int $paId, string $operador): void
    {
        Db::comTrava('inicio_carga', static fn () => self::iniciarSemCorrida($id, $paId, $operador));
    }

    public static function iniciarComRetro(int $id, int $retroId, string $operador): void
    {
        Db::comTrava('inicio_carga', static fn () => self::iniciarComRetroSemCorrida($id, $retroId, $operador));
    }

    private static function iniciarSemCorrida(int $id, int $paId, string $operador): void
    {
        $ordem = self::exigir($id);
        if ($ordem['status'] !== 'liberado') {
            throw new InvalidArgumentException('Só dá para iniciar uma ordem que está liberada.');
        }
        if ($paId < 1) {
            throw new InvalidArgumentException('Selecione a pá carregadeira.');
        }
        $operador = limpar_texto($operador, 120, true, 'o nome do operador');
        if (Lavra::operadorEmCarga($operador)) {
            throw new InvalidArgumentException('Esse operador já está carregando um caminhão na lavra.');
        }
        self::exigirOperadorLivre($operador, $id);
        $pa = Db::pdo()->prepare('SELECT id FROM pas_carregadeiras WHERE id = ? AND ativo = 1');
        $pa->execute([$paId]);
        if (!$pa->fetch()) {
            throw new InvalidArgumentException('Essa pá carregadeira não está disponível.');
        }
        $emUso = self::pasEmUso($id);
        if (isset($emUso[$paId])) {
            throw new InvalidArgumentException('Essa pá já está carregando a ' . $emUso[$paId] . '. Finalize aquela carga antes.');
        }
        Posse::exigirCompativel('pa', $paId, $operador);

        $comando = Db::pdo()->prepare(
            "UPDATE fila_carregamentos
             SET status = 'carregando', pa_carregadeira_id = ?, retroescavadeira_id = NULL, operador = ?, inicio_carregamento = ?
             WHERE id = ? AND status = 'liberado'"
        );
        $comando->execute([$paId, $operador, agora_sql(), $id]);
        self::exigirAlteracao($comando);
    }

    private static function iniciarComRetroSemCorrida(int $id, int $retroId, string $operador): void
    {
        $ordem = self::exigir($id);
        if ($ordem['status'] !== 'liberado') {
            throw new InvalidArgumentException('Só dá para iniciar uma ordem que está liberada.');
        }
        if ((int) Db::pdo()->query('SELECT COUNT(*) FROM pas_carregadeiras WHERE ativo = 1')->fetchColumn() > 0) {
            throw new InvalidArgumentException('Há uma pá ativa. Use a pá carregadeira para carregar no pátio.');
        }
        if ($retroId < 1) {
            throw new InvalidArgumentException('Selecione a escavadeira hidráulica.');
        }
        $operador = limpar_texto($operador, 120, true, 'o nome do operador');
        if (Lavra::operadorEmCarga($operador)) {
            throw new InvalidArgumentException('Esse operador já está carregando um caminhão na lavra.');
        }
        self::exigirOperadorLivre($operador, $id);
        $retro = Db::pdo()->prepare('SELECT id FROM retroescavadeiras WHERE id = ? AND ativo = 1');
        $retro->execute([$retroId]);
        if (!$retro->fetch()) {
            throw new InvalidArgumentException('Essa escavadeira hidráulica não está disponível.');
        }
        $emUso = Lavra::retrosEmUso();
        if (isset($emUso[$retroId])) {
            throw new InvalidArgumentException('Essa escavadeira já está carregando o ' . $emUso[$retroId] . '.');
        }
        Posse::exigirCompativel('retro', $retroId, $operador);

        $comando = Db::pdo()->prepare(
            "UPDATE fila_carregamentos
             SET status = 'carregando', pa_carregadeira_id = NULL, retroescavadeira_id = ?, operador = ?, inicio_carregamento = ?
             WHERE id = ? AND status = 'liberado'"
        );
        $comando->execute([$retroId, $operador, agora_sql(), $id]);
        self::exigirAlteracao($comando);
    }

    public static function finalizar(int $id, string $nota, string $operador): void
    {
        $ordem = self::exigir($id);
        if ($ordem['status'] !== 'carregando') {
            throw new InvalidArgumentException('Só dá para finalizar uma ordem que está em carregamento.');
        }

        $nota = limpar_longo($nota, 1000);
        $observacoes = $ordem['observacoes'];
        if ($nota !== null) {
            $quem = trim((string) ($ordem['operador'] ?? '')) !== '' ? (string) $ordem['operador'] : $operador;
            $linha = '[' . date('d/m/Y H:i') . ' · ' . $quem . '] ' . $nota;
            $observacoes = trim((string) $observacoes . "\n\n" . $linha);
            if (tamanho($observacoes) > 4000) {
                throw new InvalidArgumentException('As observações passaram do limite. Encurte a ocorrência.');
            }
        }

        $comando = Db::pdo()->prepare(
            "UPDATE fila_carregamentos
             SET status = 'aguardando_conferencia', fim_carregamento = ?, observacoes = ?
             WHERE id = ? AND status = 'carregando'"
        );
        $comando->execute([agora_sql(), $observacoes, $id]);
        self::exigirAlteracao($comando);
    }

    public static function concluir(int $id): void
    {
        $ordem = self::exigir($id);
        if ($ordem['status'] !== 'aguardando_conferencia') {
            throw new InvalidArgumentException('Só dá para concluir uma ordem que aguarda conferência.');
        }
        $comando = Db::pdo()->prepare(
            "UPDATE fila_carregamentos SET status = 'concluido' WHERE id = ? AND status = 'aguardando_conferencia'"
        );
        $comando->execute([$id]);
        self::exigirAlteracao($comando);
    }

    public static function cancelar(int $id, string $motivo, string $responsavel): void
    {
        $ordem = self::exigir($id);
        if (!in_array($ordem['status'], ['aguardando_autorizacao', 'liberado', 'carregando', 'aguardando_conferencia'], true)) {
            throw new InvalidArgumentException('Essa ordem já está encerrada.');
        }
        $motivo = limpar_texto((string) $motivo, 300, false, 'o motivo');
        $linha = '[' . date('d/m/Y H:i') . ' · ' . $responsavel . '] Cancelado.';
        if ($motivo !== null) {
            $linha .= ' ' . $motivo;
        }
        $observacoes = trim((string) $ordem['observacoes'] . "\n\n" . $linha);

        $comando = Db::pdo()->prepare(
            "UPDATE fila_carregamentos
             SET status = 'cancelado', observacoes = ?
             WHERE id = ? AND status = ?"
        );
        $comando->execute([$observacoes, $id, $ordem['status']]);
        self::exigirAlteracao($comando);
    }

    private static function lerOrdem(array $post, ?array $atual = null): array
    {
        $materialId = (int) ($post['material_id'] ?? 0);
        $unidade = (string) ($post['unidade'] ?? '');
        if (!isset(unidades()[$unidade])) {
            throw new InvalidArgumentException('Selecione a unidade.');
        }

        self::exigirVinculo('materiais', $materialId, 'o material', isset($atual['material_id']) ? (int) $atual['material_id'] : null);

        return [
            'data' => parse_data_hora((string) ($post['data'] ?? '')),
            'cliente' => limpar_texto((string) ($post['cliente'] ?? ''), 160, true, 'o cliente'),
            'pedido' => limpar_texto((string) ($post['pedido'] ?? ''), 60, true, 'o pedido'),
            'nf' => limpar_texto((string) ($post['nf'] ?? ''), 60, true, 'a nota fiscal'),
            'material_id' => $materialId,
            'quantidade_autorizada' => parse_quantidade((string) ($post['quantidade_autorizada'] ?? '')),
            'unidade' => $unidade,
            'observacoes' => limpar_longo((string) ($post['observacoes'] ?? ''), 4000),
        ];
    }

    private static function exigirVinculo(string $tabela, int $id, string $rotulo, ?int $atual): void
    {
        if (!in_array($tabela, ['materiais', 'pas_carregadeiras'], true)) {
            throw new RuntimeException('Vínculo inválido.');
        }
        if ($id < 1) {
            throw new InvalidArgumentException('Selecione ' . $rotulo . '.');
        }
        $comando = Db::pdo()->prepare("SELECT ativo FROM {$tabela} WHERE id = ?");
        $comando->execute([$id]);
        $linha = $comando->fetch();
        if (!$linha) {
            throw new InvalidArgumentException('Selecione ' . $rotulo . '.');
        }
        if ((int) $linha['ativo'] !== 1 && $id !== $atual) {
            throw new InvalidArgumentException('O cadastro escolhido para ' . $rotulo . ' está inativo.');
        }
    }

    private static function exigirOperadorLivre(string $operador, int $ordemId): void
    {
        $outra = self::ordemDoOperador($operador, $ordemId);
        if ($outra !== null) {
            throw new InvalidArgumentException('Esse operador já está carregando a ' . $outra . '. Finalize aquela carga antes.');
        }
        if (Lavra::abertoDo($operador) !== null) {
            throw new InvalidArgumentException('Esse operador está com uma viagem aberta na lavra. Encerre a viagem antes.');
        }
    }

    private static function exigirAlteracao(PDOStatement $comando): void
    {
        if ($comando->rowCount() < 1) {
            throw new InvalidArgumentException('Essa ordem mudou de situação. Atualize a tela.');
        }
    }

    private static function aindaNaSituacao(int $id, array $esperados): void
    {
        $atual = self::buscar($id);
        if ($atual === null || !in_array($atual['status'], $esperados, true)) {
            throw new InvalidArgumentException('Essa ordem mudou de situação. Atualize a tela.');
        }
    }

    private static function mediasDe(array $linhas): array
    {
        $somas = ['fila' => 0, 'carga' => 0, 'ciclo' => 0];
        $quantidades = $somas;
        foreach ($linhas as $ordem) {
            foreach ($somas as $chave => $valor) {
                $tempo = $ordem['tempos'][$chave] ?? null;
                if ($tempo === null) {
                    continue;
                }
                $somas[$chave] += $tempo;
                $quantidades[$chave]++;
            }
        }

        $medias = [];
        foreach ($somas as $chave => $soma) {
            $medias[$chave] = $quantidades[$chave] > 0 ? (int) round($soma / $quantidades[$chave]) : null;
        }

        return $medias;
    }

    private static function agrupar(array $linhas, string $campo, string $vazio): array
    {
        $baldes = [];
        foreach ($linhas as $ordem) {
            $chave = trim((string) ($ordem[$campo] ?? ''));
            if ($chave === '') {
                $chave = $vazio;
            }
            $baldes[$chave][] = $ordem;
        }
        ksort($baldes);

        $grupos = [];
        foreach ($baldes as $titulo => $itens) {
            $grupos[] = [
                'titulo' => $titulo,
                'total' => count($itens),
                'medias' => self::mediasDe($itens),
                'linhas' => $itens,
            ];
        }

        return $grupos;
    }

    private static function sqlBase(): string
    {
        return 'SELECT f.*,
                m.material AS material_nome,
                COALESCE(p.maquina, r.maquina) AS pa_nome,
                COALESCE(p.marca, r.marca) AS pa_marca,
                COALESCE(p.modelo, r.modelo) AS pa_modelo,
                COALESCE(p.ano, r.ano) AS pa_ano,
                COALESCE(p.foto, r.foto) AS pa_foto,
                r.foto AS retro_foto,
                u.id AS pessoa_id, u.foto AS pessoa_foto
            FROM fila_carregamentos f
            JOIN materiais m ON m.id = f.material_id
            LEFT JOIN pas_carregadeiras p ON p.id = f.pa_carregadeira_id
            LEFT JOIN retroescavadeiras r ON r.id = f.retroescavadeira_id
            LEFT JOIN usuarios u ON u.nome = f.operador';
    }

    private static function escaparLike(string $termo): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $termo);
    }
}
