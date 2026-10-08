<?php

declare(strict_types=1);

final class Dashboard
{
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
            'estrada' => $estrada,
            'lavra' => [
                'caminhoes' => array_map(static fn (array $ciclo): array => self::caminhao($ciclo, $medias, $viagensHoje, $estrada), $abertos),
                'retros' => self::retrosNaLavra($abertos),
                'indicadores' => self::indicadoresLavra($abertos, $lavra),
                'horas' => self::horasLavra($dia),
            ],
            'patio' => self::patio($dia, $patio),
        ];
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

    public static function salvarEstrada(?float $ida, ?float $retorno): void
    {
        self::estrada();
        Db::pdo()->prepare('UPDATE estrada SET ida_km = ?, retorno_km = ? WHERE id = 1')->execute([
            $ida,
            $retorno,
        ]);
    }

    private static function caminhao(array $ciclo, array $medias, array $viagensHoje, array $estrada): array
    {
        $status = (string) $ciclo['status'];
        $desde = match ($status) {
            'carregando' => $ciclo['inicio_carga'] ?? $ciclo['inicio_fila'],
            'em_viagem' => $ciclo['fim_carga'] ?? $ciclo['inicio_fila'],
            'retornando' => $ciclo['inicio_retorno'] ?? $ciclo['fim_carga'],
            default => $ciclo['inicio_fila'],
        };
        $naEstrada = in_array($status, ['em_viagem', 'retornando'], true);
        $distancia = $status === 'em_viagem' ? $estrada['ida_km'] : ($status === 'retornando' ? $estrada['retorno_km'] : null);
        $base = match ($status) {
            'em_viagem' => (int) ($medias['ida'] ?? 0),
            'retornando' => (int) ($medias['retorno'] ?? 0),
            default => 0,
        };
        if ($base < 60) {
            $base = 900;
        }
        $decorrido = segundos_entre((string) $desde, agora_sql()) ?? 0;
        $progresso = $naEstrada ? min(1, $decorrido / $base) : 0.0;
        $placa = (string) $ciclo['placa'];
        $hoje = $viagensHoje[$placa] ?? ['viagens' => 0, 'toneladas' => null];

        return [
            'id' => (int) $ciclo['id'],
            'placa' => $placa,
            'motorista' => (string) ($ciclo['operador'] ?? ''),
            'capacidade' => $ciclo['capacidade_t'] !== null ? (float) $ciclo['capacidade_t'] : null,
            'status' => $status,
            'estado' => self::estadoLavra($status),
            'faixa' => match ($status) {
                'em_viagem' => 'ida',
                'retornando' => 'volta',
                default => 'lavra',
            },
            'progresso' => round($progresso, 3),
            'km' => $distancia !== null && $naEstrada ? round($progresso * $distancia, 2) : null,
            'retro' => (string) ($ciclo['retro_nome'] ?? ''),
            'operador_carga' => (string) ($ciclo['operador_carga'] ?? ''),
            'desde' => hora_curta((string) $desde),
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
            'em_viagem' => 'Ida ao britador',
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
            'quantidade' => (float) $ordem['quantidade_autorizada'],
            'unidade' => unidade_rotulo((string) $ordem['unidade']),
            'maquina' => (string) ($ordem['pa_nome'] ?? ''),
            'operador' => (string) ($ordem['operador'] ?? ''),
            'chegada' => hora_curta((string) ($ordem['autorizado_em'] ?? $ordem['data'])),
            'inicio' => hora_curta($ordem['inicio_carregamento'] ?? null),
        ];
    }

    private static function expedidas(string $dia): array
    {
        $comando = Db::pdo()->prepare(
            "SELECT unidade, SUM(quantidade_autorizada) AS total, COUNT(*) AS cargas
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
            "SELECT HOUR(c.fim_ciclo) AS hora, COUNT(*) AS viagens, SUM(k.capacidade_t) AS toneladas
             FROM ciclos_lavra c
             JOIN caminhoes k ON k.id = c.caminhao_id
             WHERE c.status = 'concluido' AND c.fim_ciclo >= ? AND c.fim_ciclo <= ?
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
                    SUM(CASE WHEN unidade = 't' THEN quantidade_autorizada ELSE 0 END) AS toneladas
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
             WHERE status = 'concluido' AND fim_ciclo >= CURDATE()"
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
