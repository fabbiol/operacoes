<?php

declare(strict_types=1);

final class Dashboard
{
    /** Segundos de descarga no britador; depois disso o caminhão aparece voltando vazio no Monitor. */
    private const DESCARGA = 180;

    public static function instantaneo(): array
    {
        $dia = date('Y-m-d');
        $lavra = Lavra::resumo($dia);
        $patio = Operacoes::resumo($dia);
        $abertos = Lavra::abertos();
        $medias = $lavra['caminhao']['medias'];
        $viagensHoje = self::viagensPorPlaca($lavra);
        $estrada = self::estrada();

        return [
            'agora' => date('H:i'),
            'agora_ts' => time(),
            'fuso' => date_default_timezone_get(),
            'dia' => data_por_extenso(),
            'vendas' => Operacoes::vendas($dia),
            'estrada' => $estrada,
            'lavra' => [
                'caminhoes' => array_map(static fn (array $ciclo): array => self::caminhao($ciclo, $medias, $viagensHoje, $estrada), $abertos),
                'retros' => self::retrosNaLavra($abertos),
                'indicadores' => self::indicadoresLavra($abertos, $lavra),
                'horas' => self::horasLavra($dia),
                'limpezas' => array_map(static fn (array $limpeza): array => [
                    'retro' => (string) ($limpeza['retro_nome'] ?? 'Escavadeira'),
                    'operador' => (string) $limpeza['operador'],
                    'desde_ts' => (int) strtotime((string) $limpeza['inicio']),
                ], Lavra::limpezasAbertas()),
            ],
            'patio' => self::patio($dia, $patio),
            // Problemas avisados pelos operadores ao soltar a máquina, até alguém marcar como resolvido.
            // A pá aparece na coluna do pátio; escavadeira e caminhão, na da lavra.
            'problemas' => array_map(static fn (array $problema): array => [
                'tipo' => (string) $problema['tipo'],
                'area' => (string) $problema['tipo'] === 'pa' ? 'patio' : 'lavra',
                'maquina' => (string) $problema['maquina'],
                'descricao' => (string) $problema['descricao'],
                'usuario' => (string) $problema['usuario'],
                'desde_ts' => (int) strtotime((string) $problema['relatado_em']),
            ], Problemas::abertos()),
            'em_uso' => self::emUso($abertos),
            'britador' => self::britador($dia),
            'melhor_hora' => [
                'patio' => self::melhorHora(self::horasPatio($dia)),
                'lavra' => self::melhorHora(self::horasLavra($dia)),
            ],
        ];
    }

    /**
     * Cada máquina que está com alguém agora, com a pessoa e o que ela está fazendo.
     *
     * @return list<array{tipo: string, nome: string, pessoa: string, faz: string, ativo: bool}>
     */
    private static function emUso(array $abertos): array
    {
        $ciclos = [];
        $carregandoRetro = [];
        foreach ($abertos as $ciclo) {
            $ciclos[(int) $ciclo['caminhao_id']] = $ciclo;
            if ((string) $ciclo['status'] === 'carregando' && (int) ($ciclo['retroescavadeira_id'] ?? 0) > 0) {
                $carregandoRetro[(int) $ciclo['retroescavadeira_id']] = (string) $ciclo['placa'];
            }
        }
        $limpando = [];
        foreach (Lavra::limpezasAbertas() as $limpeza) {
            $limpando[(int) $limpeza['retroescavadeira_id']] = true;
        }
        $noPatio = [];
        foreach (Operacoes::patio() as $ordem) {
            if ((string) $ordem['status'] === 'carregando' && (int) ($ordem['retroescavadeira_id'] ?? 0) > 0) {
                $noPatio[(int) $ordem['retroescavadeira_id']] = true;
            }
        }

        $lista = [];
        foreach (Posse::todas() as $posse) {
            $tipo = (string) $posse['tipo'];
            $id = (int) $posse['maquina_id'];
            // As pás ficam só na coluna do pátio.
            if ($tipo === 'pa') {
                continue;
            }
            // "ativo": a máquina está sendo usada agora (fica verde no Monitor).
            [$faz, $ativo] = match ($tipo) {
                'retro' => match (true) {
                    isset($carregandoRetro[$id]) => ['carregando ' . $carregandoRetro[$id], true],
                    isset($limpando[$id]) => ['limpando a lavra', true],
                    isset($noPatio[$id]) => ['carregando no pátio', true],
                    default => ['na lavra', false],
                },
                default => isset($ciclos[$id])
                    ? [mb_strtolower(self::estadoLavra((string) $ciclos[$id]['status']), 'UTF-8'), true]
                    : ['sem viagem agora', false],
            };
            $lista[] = [
                'tipo' => $tipo,
                'nome' => Posse::nomeDaMaquina($tipo, $id),
                'pessoa' => (string) $posse['usuario'],
                'faz' => $faz,
                'ativo' => $ativo,
            ];
        }
        // Escavadeiras primeiro: são poucas e explicam a fila dos caminhões.
        usort($lista, static fn (array $a, array $b): int => [$a['tipo'] !== 'retro', $a['nome']] <=> [$b['tipo'] !== 'retro', $b['nome']]);

        return $lista;
    }

    private static function britador(string $dia): array
    {
        $britador = Britador::doDia($dia);
        $hora = static fn (?string $quando): ?string => $quando !== null ? hora_curta($quando) : null;

        return [
            'inicio' => $hora($britador['inicio']),
            'fim' => $hora($britador['fim']),
            'aberta' => $britador['aberta'],
            'aberta_desde_ts' => $britador['aberta_desde'] !== null ? (int) strtotime((string) $britador['aberta_desde']) : null,
            'parado' => $britador['parado'],
            'problemas' => array_map(static fn (array $problema): array => [
                'descricao' => (string) $problema['descricao'],
                'parou' => (int) $problema['parou'] === 1,
                'desde' => hora_curta((string) $problema['inicio']),
                'desde_ts' => (int) strtotime((string) $problema['inicio']),
                'ate' => $hora($problema['resolvido_em']),
                'resolvido' => $problema['resolvido_em'] !== null,
            ], $britador['problemas']),
        ];
    }

    /** A hora com mais cargas (ou viagens) no dia; null antes da primeira. */
    private static function melhorHora(array $horas): ?array
    {
        $melhor = null;
        foreach ($horas as $hora) {
            if ($hora['viagens'] > 0 && ($melhor === null || $hora['viagens'] > $melhor['viagens'])) {
                $melhor = $hora;
            }
        }

        return $melhor !== null ? ['hora' => $melhor['hora'], 'quantidade' => $melhor['viagens']] : null;
    }

    public static function estrada(): array
    {
        $linha = Db::pdo()->query('SELECT ida_km, retorno_km FROM estrada WHERE id = 1')->fetch();
        if (!$linha) {
            Db::pdo()->exec('INSERT INTO estrada (id, ida_km, retorno_km) VALUES (1, NULL, NULL)');

            return ['ida_km' => null, 'retorno_km' => null];
        }

        return [
            'ida_km' => $linha['ida_km'] !== null ? (float) $linha['ida_km'] : null,
            'retorno_km' => $linha['retorno_km'] !== null ? (float) $linha['retorno_km'] : null,
        ];
    }

    private static function caminhao(array $ciclo, array $medias, array $viagensHoje, array $estrada): array
    {
        $status = (string) $ciclo['status'];
        $desde = match ($status) {
            'carregando' => $ciclo['inicio_carga'] ?? $ciclo['inicio_fila'],
            'em_viagem' => $ciclo['fim_carga'] ?? $ciclo['inicio_fila'],
            'no_britador' => $ciclo['chegada_britador'] ?? $ciclo['fim_carga'] ?? $ciclo['inicio_fila'],
            'retornando' => $ciclo['inicio_retorno'] ?? $ciclo['fim_carga'],
            default => $ciclo['inicio_fila'],
        };
        $naEstrada = in_array($status, ['em_viagem', 'no_britador', 'retornando'], true);
        $decorrido = segundos_entre((string) $desde, agora_sql()) ?? 0;
        // O motorista só marca a chegada no britador e a chegada na lavra: passado o tempo de descarga,
        // o caminhão que está "no britador" já está voltando vazio.
        $voltando = $status === 'retornando' || ($status === 'no_britador' && $decorrido >= self::DESCARGA);
        $base = match (true) {
            $status === 'em_viagem' => (int) ($medias['ida'] ?? 0),
            $voltando => (int) ($medias['retorno'] ?? 0),
            default => 0,
        };
        if ($base < 60) {
            $base = 900;
        }
        $faixa = match (true) {
            $voltando => 'volta',
            $status === 'em_viagem', $status === 'no_britador' => 'ida',
            default => 'lavra',
        };
        $progresso = match (true) {
            $voltando => min(1, $decorrido / $base),
            $status === 'no_britador' => 1.0,
            $naEstrada => min(1, $decorrido / $base),
            default => 0.0,
        };
        $distancia = match ($faixa) {
            'ida' => $estrada['ida_km'],
            'volta' => $estrada['retorno_km'],
            default => null,
        };
        $placa = (string) $ciclo['placa'];
        $hoje = $viagensHoje[$placa] ?? ['viagens' => 0, 'toneladas' => null];

        return [
            'id' => (int) $ciclo['id'],
            'placa' => $placa,
            'motorista' => (string) ($ciclo['operador'] ?? ''),
            'capacidade' => $ciclo['capacidade_t'] !== null ? (float) $ciclo['capacidade_t'] : null,
            'status' => $status,
            'estado' => $voltando ? 'Voltando' : self::estadoLavra($status),
            'faixa' => $faixa,
            'progresso' => round($progresso, 3),
            'km' => $distancia !== null && $naEstrada ? round($progresso * $distancia, 2) : null,
            'retro' => (string) ($ciclo['retro_nome'] ?? ''),
            'operador_carga' => (string) ($ciclo['operador_carga'] ?? ''),
            'desde' => hora_curta((string) $desde),
            'desde_ts' => (int) strtotime((string) $desde),
            'parado' => texto_duracao($decorrido),
            'viagens' => (int) $hoje['viagens'],
            'toneladas' => $hoje['toneladas'],
        ];
    }

    private static function estadoLavra(string $status): string
    {
        return match ($status) {
            'na_fila' => 'Na fila',
            'carregando' => 'Carregando',
            'em_viagem' => 'Na estrada',
            'no_britador' => 'No britador',
            'retornando' => 'Retorno vazio',
            default => $status,
        };
    }

    private static function retrosNaLavra(array $abertos): array
    {
        $lista = [];
        foreach ($abertos as $ciclo) {
            if ((string) $ciclo['status'] !== 'carregando') {
                continue;
            }
            $nome = trim((string) ($ciclo['retro_nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $lista[] = [
                'nome' => $nome,
                'operador' => (string) ($ciclo['operador_carga'] ?? ''),
                'placa' => (string) $ciclo['placa'],
            ];
        }

        return $lista;
    }

    private static function viagensPorPlaca(array $lavra): array
    {
        $mapa = [];
        foreach ($lavra['caminhao']['grupos'] as $grupo) {
            $mapa[(string) $grupo['titulo']] = [
                'viagens' => (int) $grupo['viagens'],
                'toneladas' => $grupo['producao']['toneladas'] ?? null,
            ];
        }

        return $mapa;
    }

    private static function indicadoresLavra(array $abertos, array $lavra): array
    {
        $parados = 0;
        foreach ($abertos as $ciclo) {
            if ((string) $ciclo['status'] === 'na_fila') {
                $parados++;
            }
        }
        $toneladas = $lavra['caminhao']['producao']['toneladas'] ?? null;
        $viagens = (int) $lavra['caminhao']['viagens'];

        return [
            'operacao' => count($abertos),
            'viagens' => $viagens,
            'toneladas' => $toneladas,
            'volume' => $lavra['caminhao']['producao']['volume'] ?? null,
            'por_hora' => self::porHora($toneladas, self::horasDesdePrimeiraLavra()),
            'ciclo' => $lavra['caminhao']['medias']['ciclo'] ?? null,
            'parados' => $parados,
        ];
    }

    private static function patio(string $dia, array $resumo): array
    {
        $abertas = Operacoes::patio();
        $espera = Operacoes::listar('aguardando_autorizacao', '');
        $pas = Db::pdo()->query('SELECT id, maquina FROM pas_carregadeiras WHERE ativo = 1 ORDER BY maquina')->fetchAll();
        $retros = Db::pdo()->query('SELECT id, maquina FROM retroescavadeiras WHERE ativo = 1 ORDER BY maquina')->fetchAll();
        $materiais = Db::pdo()->query('SELECT id, material FROM materiais WHERE ativo = 1 ORDER BY material')->fetchAll();
        $porMaquina = [];
        foreach ($abertas as $ordem) {
            if ((string) $ordem['status'] !== 'carregando') {
                continue;
            }
            if ((int) ($ordem['pa_carregadeira_id'] ?? 0) > 0) {
                $porMaquina['pa:' . (int) $ordem['pa_carregadeira_id']] = self::ordem($ordem);
            } elseif ((int) ($ordem['retroescavadeira_id'] ?? 0) > 0) {
                $porMaquina['retro:' . (int) $ordem['retroescavadeira_id']] = self::ordem($ordem);
            }
        }
        $maquinas = [];
        foreach ($pas as $pa) {
            $chave = 'pa:' . (int) $pa['id'];
            $maquinas[] = [
                'tipo' => 'pa',
                'nome' => (string) $pa['maquina'],
                'ordem' => $porMaquina[$chave] ?? null,
            ];
        }
        foreach ($retros as $retro) {
            $chave = 'retro:' . (int) $retro['id'];
            if (!isset($porMaquina[$chave])) {
                continue;
            }
            $maquinas[] = [
                'tipo' => 'retro',
                'nome' => (string) $retro['maquina'],
                'ordem' => $porMaquina[$chave],
            ];
        }
        $fila = [];
        $carregando = 0;
        foreach ($abertas as $ordem) {
            if ((string) $ordem['status'] === 'carregando') {
                $carregando++;
                continue;
            }
            $fila[] = self::ordem($ordem);
        }
        $expedidas = self::expedidas($dia);
        $medias = $resumo['maquina']['medias'];

        return [
            'materiais' => array_map(static fn (array $item): array => [
                'nome' => (string) $item['material'],
            ], $materiais),
            'maquinas' => $maquinas,
            'fila' => $fila,
            'sem_autorizacao' => array_map(static fn (array $ordem): array => self::ordem($ordem), $espera),
            'indicadores' => [
                'aguardando' => count($fila),
                'sem_autorizacao' => count($espera),
                'carregando' => $carregando,
                'expedidas' => (int) $expedidas['cargas'],
                'toneladas' => $expedidas['toneladas'],
                'volume' => $expedidas['volume'],
                'espera' => $medias['fila'] ?? null,
                'carga' => $medias['carga'] ?? null,
            ],
            'horas' => self::horasPatio($dia),
            'pas' => array_map(static fn (array $grupo): array => [
                'nome' => (string) $grupo['titulo'],
                'cargas' => (int) $grupo['total'],
                'espera' => $grupo['medias']['fila'] ?? null,
            ], $resumo['maquina']['grupos']),
        ];
    }

    private static function ordem(array $ordem): array
    {
        return [
            'ordem' => (string) $ordem['ordem'],
            'cliente' => (string) $ordem['cliente'],
            'pedido' => (string) $ordem['pedido'],
            'nf' => (string) $ordem['nf'],
            'produto' => (string) ($ordem['material_nome'] ?? ''),
            'quantidade' => (float) ($ordem['quantidade_carregada'] ?? $ordem['quantidade_autorizada']),
            'unidade' => unidade_rotulo((string) $ordem['unidade']),
            'maquina' => (string) ($ordem['pa_nome'] ?? ''),
            'operador' => (string) ($ordem['operador'] ?? ''),
            'chegada' => hora_curta((string) ($ordem['autorizado_em'] ?? $ordem['data'])),
            'inicio' => hora_curta($ordem['inicio_carregamento'] ?? null),
            // Para os relógios da TV contarem sozinhos entre uma atualização e outra.
            'desde_ts' => (int) strtotime((string) ($ordem['autorizado_em'] ?? $ordem['data'])),
            'inicio_ts' => !empty($ordem['inicio_carregamento']) ? (int) strtotime((string) $ordem['inicio_carregamento']) : null,
            'fracionado' => (int) ($ordem['fracionado'] ?? 0) === 1,
            'viagem' => (int) ($ordem['viagem'] ?? 1),
        ];
    }

    private static function expedidas(string $dia): array
    {
        $comando = Db::pdo()->prepare(
            "SELECT unidade, SUM(COALESCE(quantidade_carregada, quantidade_autorizada)) AS total, COUNT(*) AS cargas
             FROM fila_carregamentos
             WHERE status IN ('aguardando_conferencia', 'concluido')
               AND fim_carregamento >= ? AND fim_carregamento <= ?
             GROUP BY unidade"
        );
        $comando->execute([$dia . ' 00:00:00', $dia . ' 23:59:59']);
        $toneladas = null;
        $volume = null;
        $cargas = 0;
        foreach ($comando->fetchAll() as $linha) {
            $cargas += (int) $linha['cargas'];
            if ((string) $linha['unidade'] === 't') {
                $toneladas = round((float) $linha['total'], 2);
            }
            if ((string) $linha['unidade'] === 'm3') {
                $volume = round((float) $linha['total'], 2);
            }
        }

        return ['cargas' => $cargas, 'toneladas' => $toneladas, 'volume' => $volume];
    }

    private static function horasLavra(string $dia): array
    {
        $comando = Db::pdo()->prepare(
            "SELECT HOUR(c.fim_ciclo) AS hora, COUNT(*) AS viagens, SUM(COALESCE(c.capacidade_t, k.capacidade_t)) AS toneladas
             FROM ciclos_lavra c
             JOIN caminhoes k ON k.id = c.caminhao_id
             WHERE c.status = 'concluido' AND c.fim_carga IS NOT NULL AND c.fim_ciclo >= ? AND c.fim_ciclo <= ?
             GROUP BY HOUR(c.fim_ciclo)
             ORDER BY hora"
        );
        $comando->execute([$dia . ' 00:00:00', $dia . ' 23:59:59']);

        return self::horas($comando->fetchAll());
    }

    private static function horasPatio(string $dia): array
    {
        $comando = Db::pdo()->prepare(
            "SELECT HOUR(fim_carregamento) AS hora, COUNT(*) AS viagens,
                    SUM(CASE WHEN unidade = 't' THEN COALESCE(quantidade_carregada, quantidade_autorizada) ELSE 0 END) AS toneladas
             FROM fila_carregamentos
             WHERE status IN ('aguardando_conferencia', 'concluido')
               AND fim_carregamento >= ? AND fim_carregamento <= ?
             GROUP BY HOUR(fim_carregamento)
             ORDER BY hora"
        );
        $comando->execute([$dia . ' 00:00:00', $dia . ' 23:59:59']);

        return self::horas($comando->fetchAll());
    }

    private static function horas(array $linhas): array
    {
        $lista = [];
        foreach ($linhas as $linha) {
            $lista[] = [
                'hora' => (int) $linha['hora'],
                'viagens' => (int) $linha['viagens'],
                'toneladas' => $linha['toneladas'] !== null ? round((float) $linha['toneladas'], 2) : 0,
            ];
        }

        return $lista;
    }

    private static function horasDesdePrimeiraLavra(): ?float
    {
        $inicio = Db::pdo()->query(
            "SELECT MIN(fim_ciclo) FROM ciclos_lavra
             WHERE status = 'concluido' AND fim_carga IS NOT NULL AND fim_ciclo >= CURDATE()"
        )->fetchColumn();
        if (!$inicio) {
            return null;
        }
        $segundos = segundos_entre((string) $inicio, agora_sql());
        if ($segundos === null) {
            return null;
        }

        return max($segundos / 3600, 1 / 60);
    }

    private static function porHora(mixed $toneladas, ?float $horas): ?float
    {
        if ($toneladas === null || $horas === null || $horas <= 0) {
            return null;
        }

        return round((float) $toneladas / $horas, 1);
    }
}
