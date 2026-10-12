<?php

declare(strict_types=1);

final class Lavra
{
    /** @var list<string> */
    private const ABERTOS = ['na_fila', 'carregando', 'em_viagem', 'no_britador', 'retornando'];

    public static function etapas(): array
    {
        return [
            'na_fila' => [
                'rotulo' => 'Na lavra',
                'desde' => 'inicio_fila',
                'acao' => 'Iniciar carga',
                'aviso' => 'Carga iniciada.',
            ],
            'carregando' => [
                'rotulo' => 'Carregando',
                'desde' => 'inicio_carga',
                'acao' => 'Saiu para o britador',
                'aviso' => 'Caminhão saiu carregado para o britador.',
            ],
            'em_viagem' => [
                'rotulo' => 'Na estrada',
                'desde' => 'fim_carga',
                'acao' => 'Chegou no Britador',
                'aviso' => 'Caminhão chegou no britador.',
            ],
            'no_britador' => [
                'rotulo' => 'No britador',
                'desde' => 'chegada_britador',
                'acao' => 'Chegou na lavra',
                'aviso' => 'Caminhão chegou na lavra.',
            ],
            'retornando' => [
                'rotulo' => 'Retorno vazio',
                'desde' => 'inicio_retorno',
                'acao' => 'Chegou na lavra',
                'aviso' => 'Ciclo concluído.',
            ],
        ];
    }

    private static function sqlAbertos(): string
    {
        return "'" . implode("', '", self::ABERTOS) . "'";
    }

    public static function painel(): array
    {
        $abertos = [];
        foreach (self::abertos() as $ciclo) {
            $abertos[(int) $ciclo['caminhao_id']] = $ciclo;
        }

        $emCiclo = [];
        $livres = [];
        foreach (Cadastro::listar('caminhoes') as $caminhao) {
            $id = (int) $caminhao['id'];
            if (isset($abertos[$id])) {
                $emCiclo[] = $abertos[$id];
                continue;
            }
            if ((int) $caminhao['ativo'] === 1) {
                $livres[] = $caminhao;
            }
        }

        return ['emCiclo' => $emCiclo, 'livres' => $livres];
    }

    public static function painelDoMotorista(string $operador): array
    {
        $painel = self::painel();
        $aberto = self::cicloAbertoDo($operador);
        if ($aberto === null) {
            return ['emCiclo' => [], 'livres' => $painel['livres']];
        }

        $id = (int) $aberto['id'];
        $emCiclo = array_values(array_filter(
            $painel['emCiclo'],
            static fn (array $ciclo): bool => (int) $ciclo['id'] === $id
        ));

        return ['emCiclo' => $emCiclo !== [] ? $emCiclo : [$aberto], 'livres' => []];
    }

    public static function abertos(): array
    {
        $sql = self::sqlBase() . ' WHERE c.status IN (' . self::sqlAbertos() . ')
            ORDER BY c.inicio_fila ASC, c.id ASC';

        return Db::pdo()->query($sql)->fetchAll();
    }

    public static function contagens(): array
    {
        $totais = array_fill_keys([...self::ABERTOS, 'concluido', 'cancelado'], 0);
        foreach (Db::pdo()->query('SELECT status, COUNT(*) AS total FROM ciclos_lavra GROUP BY status') as $linha) {
            $totais[(string) $linha['status']] = (int) $linha['total'];
        }

        return $totais;
    }

    public static function listar(string $situacao, string $busca): array
    {
        $abertas = self::ABERTOS;
        $sql = self::sqlBase() . ' WHERE 1 = 1';
        $params = [];
        if ($situacao === '' || $situacao === 'abertas') {
            $sql .= ' AND c.status IN (' . self::sqlAbertos() . ')';
        } elseif ($situacao !== 'todas') {
            if (!in_array($situacao, [...$abertas, 'concluido', 'cancelado'], true)) {
                throw new InvalidArgumentException('Situação inválida.');
            }
            $sql .= ' AND c.status = ?';
            $params[] = $situacao;
        }

        $termo = trim($busca);
        if ($termo !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $termo) . '%';
            $sql .= ' AND (k.placa LIKE ? OR c.operador LIKE ?)';
            array_push($params, $like, $like);
        }

        $sql .= " ORDER BY FIELD(c.status, 'na_fila', 'carregando', 'em_viagem', 'no_britador', 'retornando', 'concluido', 'cancelado'), c.inicio_fila DESC, c.id DESC LIMIT 300";
        $comando = Db::pdo()->prepare($sql);
        $comando->execute($params);

        return $comando->fetchAll();
    }

    public static function concluidosDo(string $operador, string $desde): array
    {
        $comando = Db::pdo()->prepare(
            self::sqlBase() . " WHERE c.operador = ?
                AND c.status = 'concluido'
                AND c.fim_ciclo >= ?
             ORDER BY c.fim_ciclo DESC, c.id DESC"
        );
        $comando->execute([$operador, $desde]);
        $linhas = [];
        foreach ($comando->fetchAll() as $ciclo) {
            $linhas[] = $ciclo + ['tempos' => self::tempos($ciclo)];
        }

        return $linhas;
    }

    public static function buscar(int $id): ?array
    {
        $comando = Db::pdo()->prepare(self::sqlBase() . ' WHERE c.id = ? LIMIT 1');
        $comando->execute([$id]);
        $linha = $comando->fetch();

        return $linha ?: null;
    }

    public static function exigir(int $id): array
    {
        $ciclo = self::buscar($id);
        if ($ciclo === null) {
            throw new InvalidArgumentException('Ciclo não encontrado.');
        }

        return $ciclo;
    }

    public static function entrarNaFila(int $caminhaoId, string $operador, bool $somenteOSeu = false): int
    {
        if ($caminhaoId < 1) {
            throw new InvalidArgumentException('Selecione o caminhão.');
        }
        if ($somenteOSeu && self::cicloAbertoDo($operador) !== null) {
            throw new InvalidArgumentException('Conclua a viagem deste caminhão antes de entrar com outro.');
        }
        $caminhao = Db::pdo()->prepare('SELECT id FROM caminhoes WHERE id = ? AND ativo = 1');
        $caminhao->execute([$caminhaoId]);
        if (!$caminhao->fetch()) {
            throw new InvalidArgumentException('Esse caminhão não está disponível.');
        }

        $aberto = Db::pdo()->prepare(
            "SELECT id FROM ciclos_lavra
             WHERE caminhao_id = ? AND status IN (" . self::sqlAbertos() . ")
             LIMIT 1"
        );
        $aberto->execute([$caminhaoId]);
        if ($aberto->fetch()) {
            throw new InvalidArgumentException('Esse caminhão já está em um ciclo.');
        }

        $comando = Db::pdo()->prepare(
            "INSERT INTO ciclos_lavra (caminhao_id, status, inicio_fila, operador)
             VALUES (?, 'na_fila', ?, ?)"
        );
        $comando->execute([$caminhaoId, agora_sql(), $operador]);

        return (int) Db::pdo()->lastInsertId();
    }

    public static function avancar(int $id, ?string $somenteOperador = null): string
    {
        $ciclo = self::exigir($id);
        self::garantirDono($ciclo, $somenteOperador);
        if (in_array($ciclo['status'], ['na_fila', 'carregando'], true)) {
            throw new InvalidArgumentException('A carga começa e termina na retroescavadeira.');
        }
        $etapa = self::etapas()[$ciclo['status']] ?? null;
        if ($etapa === null) {
            throw new InvalidArgumentException('Esse ciclo já foi encerrado.');
        }

        if ($ciclo['status'] === 'em_viagem') {
            $comando = Db::pdo()->prepare(
                "UPDATE ciclos_lavra SET chegada_britador = ?, status = 'no_britador' WHERE id = ? AND status = 'em_viagem'"
            );
            $comando->execute([agora_sql(), $id]);
            if ($comando->rowCount() < 1) {
                throw new InvalidArgumentException('Esse ciclo mudou de etapa. Atualize a tela.');
            }

            return $etapa['aviso'];
        }

        if (!in_array($ciclo['status'], ['no_britador', 'retornando'], true)) {
            throw new InvalidArgumentException('Esse ciclo já foi encerrado.');
        }

        $comando = Db::pdo()->prepare(
            "UPDATE ciclos_lavra SET fim_ciclo = ?, status = 'concluido' WHERE id = ? AND status = ?"
        );
        $comando->execute([agora_sql(), $id, $ciclo['status']]);
        if ($comando->rowCount() < 1) {
            throw new InvalidArgumentException('Esse ciclo mudou de etapa. Atualize a tela.');
        }

        return $etapa['aviso'];
    }

    public static function cancelar(int $id, string $motivo, string $responsavel, ?string $somenteOperador = null): void
    {
        $ciclo = self::exigir($id);
        self::garantirDono($ciclo, $somenteOperador);
        if (!in_array($ciclo['status'], ['na_fila', 'carregando'], true)) {
            throw new InvalidArgumentException('Depois da saída para o britador, a viagem não se cancela.');
        }
        $motivo = limpar_texto($motivo, 300, false, 'o motivo');
        $linha = '[' . date('d/m/Y H:i') . ' · ' . $responsavel . '] Ciclo cancelado.';
        if ($motivo !== null) {
            $linha .= ' ' . $motivo;
        }
        $observacoes = trim((string) $ciclo['observacoes'] . "\n\n" . $linha);
        $comando = Db::pdo()->prepare(
            "UPDATE ciclos_lavra SET status = 'cancelado', observacoes = ? WHERE id = ? AND status = ?"
        );
        $comando->execute([$observacoes, $id, $ciclo['status']]);
        if ($comando->rowCount() < 1) {
            throw new InvalidArgumentException('Esse ciclo mudou de etapa. Atualize a tela.');
        }
    }

    public static function motivosEncerramento(): array
    {
        return [
            'turno' => 'Turno encerrado',
            'pausa' => 'Pausa',
            'oficina' => 'Oficina',
            'outro' => 'Outro',
        ];
    }

    public static function encerrar(int $id, string $motivo, string $outro, string $responsavel, ?string $somenteOperador = null): void
    {
        $ciclo = self::exigir($id);
        self::garantirDono($ciclo, $somenteOperador);
        if (!in_array($ciclo['status'], self::ABERTOS, true)) {
            throw new InvalidArgumentException('Essa viagem já foi encerrada.');
        }
        $rotulos = self::motivosEncerramento();
        if (!isset($rotulos[$motivo])) {
            throw new InvalidArgumentException('Escolha o motivo.');
        }
        $texto = $rotulos[$motivo];
        if ($motivo === 'outro') {
            $nota = limpar_texto($outro, 120, true, 'o motivo');
            $texto .= ' · ' . $nota;
        }
        $fato = self::saiuCarregado($ciclo)
            ? 'Viagem encerrada sem retorno.'
            : 'Viagem encerrada antes da carga.';
        $linha = '[' . date('d/m/Y H:i') . ' · ' . $responsavel . '] ' . $fato . ' ' . $texto;
        $observacoes = trim((string) $ciclo['observacoes'] . "\n\n" . $linha);
        $comando = Db::pdo()->prepare(
            "UPDATE ciclos_lavra
             SET status = 'concluido', fim_ciclo = ?, encerramento = ?, observacoes = ?
             WHERE id = ? AND status = ?"
        );
        $comando->execute([agora_sql(), $texto, $observacoes, $id, $ciclo['status']]);
        if ($comando->rowCount() < 1) {
            throw new InvalidArgumentException('Esse ciclo mudou de etapa. Atualize a tela.');
        }
    }

    public static function doDia(string $dia, ?string $operador = null): array
    {
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', $dia);
        $erros = DateTimeImmutable::getLastErrors();
        if (!$data || ($erros['warning_count'] ?? 0) > 0 || ($erros['error_count'] ?? 0) > 0) {
            throw new InvalidArgumentException('Data inválida.');
        }

        $sql = self::sqlBase() . " WHERE c.status = 'concluido' AND c.fim_ciclo >= ? AND c.fim_ciclo <= ?";
        $params = [$dia . ' 00:00:00', $dia . ' 23:59:59'];
        if ($operador !== null && $operador !== '') {
            $sql .= ' AND c.operador = ?';
            $params[] = $operador;
        }
        $comando = Db::pdo()->prepare($sql . ' ORDER BY c.fim_ciclo DESC, c.id DESC');
        $comando->execute($params);
        $ciclos = $comando->fetchAll();

        $porCaminhao = [];
        $linhas = [];
        foreach ($ciclos as $ciclo) {
            $tempos = self::tempos($ciclo);
            $linhas[] = $ciclo + ['tempos' => $tempos];
            if (!self::saiuCarregado($ciclo)) {
                continue;
            }
            $placa = (string) $ciclo['placa'];
            $porCaminhao[$placa] = ($porCaminhao[$placa] ?? 0) + 1;
        }

        $entregas = array_values(array_filter(
            $linhas,
            static fn (array $ciclo): bool => self::saiuCarregado($ciclo)
        ));
        $medias = self::mediasDe($entregas);
        ksort($porCaminhao);

        return [
            'dia' => $dia,
            'linhas' => $linhas,
            'medias' => $medias,
            'viagens' => count($entregas),
            'producao' => self::producao($entregas),
            'porCaminhao' => $porCaminhao,
        ];
    }

    public static function resumo(string $dia, ?string $somenteOperador = null): array
    {
        $tudo = self::doDia($dia, $somenteOperador);
        $nomes = array_flip(self::nomesMotoristas());
        $dosMotoristas = array_values(array_filter(
            $tudo['linhas'],
            static fn (array $ciclo): bool => isset($nomes[(string) ($ciclo['operador'] ?? '')])
        ));
        $entregasMotorista = array_values(array_filter(
            $dosMotoristas,
            static fn (array $ciclo): bool => self::saiuCarregado($ciclo)
        ));

        return [
            'dia' => $tudo['dia'],
            'caminhao' => [
                'medias' => $tudo['medias'],
                'viagens' => $tudo['viagens'],
                'producao' => $tudo['producao'],
                'grupos' => self::agrupar($tudo['linhas'], 'placa'),
            ],
            'motorista' => [
                'medias' => self::mediasDe($entregasMotorista),
                'viagens' => count($entregasMotorista),
                'producao' => self::producao($entregasMotorista),
                'grupos' => self::agrupar($dosMotoristas, 'operador'),
            ],
        ];
    }

    public static function tempos(array $ciclo): array
    {
        return [
            'fila' => segundos_entre($ciclo['inicio_fila'] ?? null, $ciclo['inicio_carga'] ?? null),
            'carga' => segundos_entre($ciclo['inicio_carga'] ?? null, $ciclo['fim_carga'] ?? null),
            'ida' => segundos_entre($ciclo['fim_carga'] ?? null, self::fimDaIda($ciclo)),
            'retorno' => segundos_entre(self::inicioDoRetorno($ciclo), $ciclo['fim_ciclo'] ?? null),
            'ciclo' => segundos_entre($ciclo['inicio_fila'] ?? null, $ciclo['fim_ciclo'] ?? null),
        ];
    }

    public static function desde(array $ciclo): ?string
    {
        $etapa = self::etapas()[$ciclo['status']] ?? null;
        if ($etapa === null) {
            return null;
        }

        $valor = $ciclo[$etapa['desde']] ?? null;

        return $valor !== null && $valor !== '' ? (string) $valor : null;
    }

    public static function naFila(): array
    {
        $sql = self::sqlBase() . " WHERE c.status = 'na_fila' ORDER BY c.inicio_fila ASC, c.id ASC";

        return Db::pdo()->query($sql)->fetchAll();
    }

    public static function voltando(): array
    {
        $sql = self::sqlBase() . " WHERE c.status = 'retornando' ORDER BY c.inicio_retorno ASC, c.id ASC";

        return Db::pdo()->query($sql)->fetchAll();
    }

    public static function chegouHoje(): array
    {
        $sql = "SELECT k.id, k.placa AS nome, k.placa, k.ativo, k.marca, k.modelo, k.ano, k.foto, k.capacidade_t, c.fim_ciclo
            FROM caminhoes k
            JOIN ciclos_lavra c ON c.caminhao_id = k.id
                AND c.status = 'concluido'
                AND (c.encerramento IS NULL OR c.encerramento = '')
                AND c.fim_ciclo >= CURDATE()
            WHERE k.ativo = 1
              AND c.id = (
                    SELECT c2.id FROM ciclos_lavra c2
                    WHERE c2.caminhao_id = k.id AND c2.status = 'concluido'
                    ORDER BY c2.fim_ciclo DESC, c2.id DESC
                    LIMIT 1
              )
              AND NOT EXISTS (
                    SELECT 1 FROM ciclos_lavra aberto
                    WHERE aberto.caminhao_id = k.id
                      AND aberto.status IN (" . self::sqlAbertos() . ")
              )
            ORDER BY c.fim_ciclo DESC, k.placa ASC";

        return Db::pdo()->query($sql)->fetchAll();
    }

    public static function semRetornoHoje(): array
    {
        $sql = "SELECT k.id, k.placa AS nome, k.placa, k.ativo, k.marca, k.modelo, k.ano, k.foto, k.capacidade_t,
                c.id AS ciclo_id, c.fim_ciclo, c.fim_carga, c.encerramento, c.operador
            FROM caminhoes k
            JOIN ciclos_lavra c ON c.caminhao_id = k.id
                AND c.status = 'concluido'
                AND c.inicio_retorno IS NULL
                AND c.encerramento IS NOT NULL
                AND c.encerramento <> ''
                AND c.encerramento_ciente_em IS NULL
                AND c.fim_ciclo >= CURDATE()
            WHERE k.ativo = 1
              AND c.id = (
                    SELECT c2.id FROM ciclos_lavra c2
                    WHERE c2.caminhao_id = k.id AND c2.status = 'concluido'
                    ORDER BY c2.fim_ciclo DESC, c2.id DESC
                    LIMIT 1
              )
              AND NOT EXISTS (
                    SELECT 1 FROM ciclos_lavra aberto
                    WHERE aberto.caminhao_id = k.id
                      AND aberto.status IN (" . self::sqlAbertos() . ")
              )
            ORDER BY c.fim_ciclo DESC, k.placa ASC";

        return Db::pdo()->query($sql)->fetchAll();
    }

    public static function cienteSemRetorno(int $id, string $operador): void
    {
        $ciclo = self::exigir($id);
        $encerramento = trim((string) ($ciclo['encerramento'] ?? ''));
        if ($ciclo['status'] !== 'concluido' || $encerramento === '' || !empty($ciclo['inicio_retorno'])) {
            throw new InvalidArgumentException('Esse aviso já não está na tela.');
        }
        if (!empty($ciclo['encerramento_ciente_em'])) {
            return;
        }
        $comando = Db::pdo()->prepare(
            'UPDATE ciclos_lavra
             SET encerramento_ciente_em = ?, encerramento_ciente_por = ?
             WHERE id = ? AND encerramento_ciente_em IS NULL'
        );
        $comando->execute([agora_sql(), $operador, $id]);
    }

    public static function cargaDoOperador(string $operador): ?array
    {
        $comando = Db::pdo()->prepare(
            self::sqlBase() . " WHERE c.status = 'carregando' AND c.operador_carga = ?
             ORDER BY c.inicio_carga ASC, c.id ASC LIMIT 1"
        );
        $comando->execute([$operador]);
        $linha = $comando->fetch();

        return $linha ?: null;
    }

    public static function operadorEmCarga(string $operador): bool
    {
        return self::cargaDoOperador($operador) !== null;
    }

    public static function retrosEmUso(int $excetoCicloId = 0): array
    {
        $comando = Db::pdo()->prepare(
            "SELECT c.retroescavadeira_id, k.placa
             FROM ciclos_lavra c
             JOIN caminhoes k ON k.id = c.caminhao_id
             WHERE c.status = 'carregando' AND c.retroescavadeira_id IS NOT NULL AND c.id <> ?"
        );
        $comando->execute([$excetoCicloId]);
        $mapa = [];
        foreach ($comando->fetchAll() as $linha) {
            $mapa[(int) $linha['retroescavadeira_id']] = (string) $linha['placa'];
        }
        $patio = Db::pdo()->query(
            "SELECT retroescavadeira_id, ordem FROM fila_carregamentos
             WHERE status = 'carregando' AND retroescavadeira_id IS NOT NULL"
        );
        foreach ($patio as $linha) {
            $mapa[(int) $linha['retroescavadeira_id']] = (string) $linha['ordem'];
        }

        return $mapa;
    }

    public static function iniciarCarga(int $id, int $retroId, string $operador): void
    {
        $ciclo = self::exigir($id);
        if ($ciclo['status'] !== 'na_fila') {
            throw new InvalidArgumentException('Só dá para iniciar a carga de um caminhão que está na fila.');
        }
        if ($retroId < 1) {
            throw new InvalidArgumentException('Selecione a retroescavadeira.');
        }
        $operador = limpar_texto($operador, 120, true, 'o nome do operador');
        if (Operacoes::operadorEmCarga($operador)) {
            throw new InvalidArgumentException('Esse operador já está carregando um cliente no pátio.');
        }
        if (self::operadorEmCarga($operador)) {
            throw new InvalidArgumentException('Esse operador já está carregando outro caminhão.');
        }
        $retro = Db::pdo()->prepare('SELECT id FROM retroescavadeiras WHERE id = ? AND ativo = 1');
        $retro->execute([$retroId]);
        if (!$retro->fetch()) {
            throw new InvalidArgumentException('Essa retroescavadeira não está disponível.');
        }
        $emUso = self::retrosEmUso($id);
        if (isset($emUso[$retroId])) {
            throw new InvalidArgumentException('Essa retroescavadeira já está carregando o ' . $emUso[$retroId] . '.');
        }

        $comando = Db::pdo()->prepare(
            "UPDATE ciclos_lavra
             SET status = 'carregando', inicio_carga = ?, retroescavadeira_id = ?, operador_carga = ?
             WHERE id = ? AND status = 'na_fila'"
        );
        $comando->execute([agora_sql(), $retroId, $operador, $id]);
        if ($comando->rowCount() < 1) {
            throw new InvalidArgumentException('Esse ciclo mudou de etapa. Atualize a tela.');
        }
    }

    public static function concluirCarga(int $id, ?string $somenteOperador): string
    {
        $ciclo = self::exigir($id);
        if ($ciclo['status'] !== 'carregando') {
            throw new InvalidArgumentException('Esse caminhão não está em carga.');
        }
        if ($somenteOperador !== null && (string) ($ciclo['operador_carga'] ?? '') !== $somenteOperador) {
            throw new InvalidArgumentException('Essa carga é de outro operador.');
        }

        $comando = Db::pdo()->prepare(
            "UPDATE ciclos_lavra SET fim_carga = ?, status = 'em_viagem' WHERE id = ? AND status = 'carregando'"
        );
        $comando->execute([agora_sql(), $id]);
        if ($comando->rowCount() < 1) {
            throw new InvalidArgumentException('Esse ciclo mudou de etapa. Atualize a tela.');
        }

        return 'Caminhão saiu carregado para o britador.';
    }

    public static function cargasConcluidasDo(string $operador, string $desde): array
    {
        $comando = Db::pdo()->prepare(
            self::sqlBase() . " WHERE c.operador_carga = ?
                AND c.status = 'concluido'
                AND c.fim_ciclo >= ?
                AND (c.operador IS NULL OR c.operador <> c.operador_carga)
             ORDER BY c.fim_ciclo DESC, c.id DESC"
        );
        $comando->execute([$operador, $desde]);
        $linhas = [];
        foreach ($comando->fetchAll() as $ciclo) {
            $linhas[] = $ciclo + ['tempos' => self::tempos($ciclo)];
        }

        return $linhas;
    }

    public static function producao(array $linhas): array
    {
        $toneladas = 0.0;
        $comCapacidade = 0;
        $faltando = [];
        foreach ($linhas as $linha) {
            if (!self::saiuCarregado($linha)) {
                continue;
            }
            $capacidade = $linha['capacidade_t'] ?? null;
            if ($capacidade === null || $capacidade === '') {
                $id = (int) ($linha['caminhao_id'] ?? 0);
                $placa = trim((string) ($linha['placa'] ?? ''));
                if ($placa === '') {
                    $placa = 'Sem placa';
                }
                $chave = $id > 0 ? (string) $id : $placa;
                if (!isset($faltando[$chave])) {
                    $faltando[$chave] = ['id' => $id, 'placa' => $placa, 'viagens' => 0];
                }
                $faltando[$chave]['viagens']++;
                continue;
            }
            $toneladas += (float) $capacidade;
            $comCapacidade++;
        }
        $toneladas = round($toneladas, 2);
        usort($faltando, static fn (array $a, array $b): int => strcmp($a['placa'], $b['placa']));

        return [
            'toneladas' => $comCapacidade > 0 ? $toneladas : null,
            'volume' => $comCapacidade > 0 ? round($toneladas / densidade_brita(), 2) : null,
            'sem_capacidade' => array_sum(array_column($faltando, 'viagens')),
            'faltando' => array_values($faltando),
        ];
    }

    private static function mediasDe(array $linhas): array
    {
        $somas = ['fila' => 0, 'carga' => 0, 'ida' => 0, 'retorno' => 0, 'ciclo' => 0];
        $quantidades = $somas;
        foreach ($linhas as $ciclo) {
            if (!self::saiuCarregado($ciclo)) {
                continue;
            }
            $tempos = $ciclo['tempos'] ?? self::tempos($ciclo);
            foreach ($somas as $chave => $valor) {
                if ($tempos[$chave] === null) {
                    continue;
                }
                $somas[$chave] += $tempos[$chave];
                $quantidades[$chave]++;
            }
        }

        $medias = [];
        foreach ($somas as $chave => $soma) {
            $medias[$chave] = $quantidades[$chave] > 0 ? (int) round($soma / $quantidades[$chave]) : null;
        }

        return $medias;
    }

    private static function agrupar(array $linhas, string $campo): array
    {
        $baldes = [];
        foreach ($linhas as $ciclo) {
            $chave = trim((string) ($ciclo[$campo] ?? ''));
            if ($chave === '') {
                $chave = 'Sem identificação';
            }
            $baldes[$chave][] = $ciclo;
        }
        ksort($baldes);

        $grupos = [];
        foreach ($baldes as $titulo => $itens) {
            $grupos[] = [
                'titulo' => $titulo,
                'viagens' => count(array_filter(
                    $itens,
                    static fn (array $ciclo): bool => self::saiuCarregado($ciclo)
                )),
                'producao' => self::producao($itens),
                'medias' => self::mediasDe($itens),
                'linhas' => $itens,
            ];
        }

        return $grupos;
    }

    private static function nomesMotoristas(): array
    {
        $nomes = [];
        foreach (Db::pdo()->query('SELECT nome FROM usuarios WHERE administrador = 0 AND motorista = 1 ORDER BY nome') as $linha) {
            $nomes[] = (string) $linha['nome'];
        }

        return $nomes;
    }

    public static function caminhaoDoMotorista(string $operador): ?array
    {
        $ciclo = self::cicloAbertoDo($operador);
        if ($ciclo === null) {
            return null;
        }

        return [
            'id' => (int) $ciclo['caminhao_id'],
            'nome' => (string) $ciclo['placa'],
            'foto' => url_foto_caminhao($ciclo),
        ];
    }

    private static function cicloAbertoDo(string $operador): ?array
    {
        $comando = Db::pdo()->prepare(
            self::sqlBase() . " WHERE c.operador = ?
                AND c.status IN (" . self::sqlAbertos() . ")
             ORDER BY c.inicio_fila DESC, c.id DESC LIMIT 1"
        );
        $comando->execute([$operador]);
        $linha = $comando->fetch();

        return $linha ?: null;
    }

    private static function garantirDono(array $ciclo, ?string $somenteOperador): void
    {
        if ($somenteOperador === null) {
            return;
        }
        if ((string) ($ciclo['operador'] ?? '') !== $somenteOperador) {
            throw new InvalidArgumentException('Esse ciclo é de outro motorista.');
        }
    }

    private static function saiuCarregado(array $ciclo): bool
    {
        $fim = $ciclo['fim_carga'] ?? null;

        return $fim !== null && $fim !== '';
    }

    private static function fimDaIda(array $ciclo): ?string
    {
        $britador = $ciclo['chegada_britador'] ?? null;
        if ($britador !== null && $britador !== '') {
            return (string) $britador;
        }
        $retorno = $ciclo['inicio_retorno'] ?? null;
        if ($retorno !== null && $retorno !== '') {
            return (string) $retorno;
        }
        $fim = $ciclo['fim_ciclo'] ?? null;

        return $fim !== null && $fim !== '' ? (string) $fim : null;
    }

    private static function inicioDoRetorno(array $ciclo): ?string
    {
        $britador = $ciclo['chegada_britador'] ?? null;
        if ($britador !== null && $britador !== '') {
            return (string) $britador;
        }
        $retorno = $ciclo['inicio_retorno'] ?? null;

        return $retorno !== null && $retorno !== '' ? (string) $retorno : null;
    }

    private static function sqlBase(): string
    {
        return 'SELECT c.*, k.placa, k.marca, k.modelo, k.ano, k.capacidade_t, k.foto,
                u.id AS pessoa_id, u.foto AS pessoa_foto,
                r.maquina AS retro_nome, r.marca AS retro_marca, r.modelo AS retro_modelo, r.ano AS retro_ano, r.foto AS retro_foto,
                uc.id AS carga_pessoa_id, uc.foto AS carga_pessoa_foto
            FROM ciclos_lavra c
            JOIN caminhoes k ON k.id = c.caminhao_id
            LEFT JOIN usuarios u ON u.nome = c.operador
            LEFT JOIN retroescavadeiras r ON r.id = c.retroescavadeira_id
            LEFT JOIN usuarios uc ON uc.nome = c.operador_carga';
    }
}
