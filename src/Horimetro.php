<?php

declare(strict_types=1);

final class Horimetro
{
    public const PROXIMA_H = 50.0;

    /** @var array<string, string> */
    private const CHAVES = [
        'pas' => 'pa',
        'retros' => 'retro',
        'caminhoes' => 'caminhao',
    ];

    public static function tipoDaChave(string $chave): string
    {
        if (!isset(self::CHAVES[$chave])) {
            throw new InvalidArgumentException('Essa máquina não tem horímetro.');
        }

        return self::CHAVES[$chave];
    }

    public static function chaveDoTipo(string $tipo): string
    {
        $chave = array_search($tipo, self::CHAVES, true);
        if ($chave === false) {
            throw new InvalidArgumentException('Essa máquina não tem horímetro.');
        }

        return $chave;
    }

    public static function atual(string $tipo, int $id): ?float
    {
        self::exigirMaquina($tipo, $id);
        $busca = Db::pdo()->prepare(
            'SELECT leitura FROM horimetros
             WHERE tipo = ? AND maquina_id = ?
             ORDER BY leitura DESC, id DESC
             LIMIT 1'
        );
        $busca->execute([$tipo, $id]);
        $valor = $busca->fetchColumn();

        return $valor === false ? null : (float) $valor;
    }

    public static function lancar(string $tipo, int $id, string $bruto, string $usuario): float
    {
        self::exigirMaquina($tipo, $id);
        $leitura = self::lerHoras($bruto, 'Horímetro', true);
        $atual = self::atual($tipo, $id);
        if ($atual !== null && round($leitura, 1) < round($atual, 1)) {
            throw new InvalidArgumentException('A leitura não pode ser menor que o horímetro atual, ' . self::texto($atual) . '.');
        }
        $comando = Db::pdo()->prepare(
            'INSERT INTO horimetros (tipo, maquina_id, leitura, lido_em, usuario)
             VALUES (?, ?, ?, ?, ?)'
        );
        $comando->execute([$tipo, $id, self::gravar($leitura), agora_sql(), $usuario]);

        return $leitura;
    }

    /** @return list<array<string, mixed>> */
    public static function historico(string $tipo, int $id): array
    {
        self::exigirMaquina($tipo, $id);
        $busca = Db::pdo()->prepare(
            'SELECT leitura, lido_em, usuario FROM horimetros
             WHERE tipo = ? AND maquina_id = ?
             ORDER BY lido_em DESC, id DESC
             LIMIT 8'
        );
        $busca->execute([$tipo, $id]);

        return $busca->fetchAll();
    }

    public static function criarPlano(string $tipo, int $id, string $nome, string $intervalo): void
    {
        self::exigirMaquina($tipo, $id);
        $titulo = limpar_texto($nome, 120, true, 'Serviço');
        $horas = self::lerHoras($intervalo, 'Intervalo', false);
        $comando = Db::pdo()->prepare(
            'INSERT INTO manutencao_planos (tipo, maquina_id, nome, intervalo_h)
             VALUES (?, ?, ?, ?)'
        );
        $comando->execute([$tipo, $id, $titulo, self::gravar($horas)]);
    }

    public static function alternarPlano(int $planoId, string $tipo, int $maquinaId): void
    {
        $plano = self::plano($planoId, $tipo, $maquinaId);
        $comando = Db::pdo()->prepare('UPDATE manutencao_planos SET ativo = ? WHERE id = ?');
        $comando->execute([(int) $plano['ativo'] === 1 ? 0 : 1, $planoId]);
    }

    public static function registrar(int $planoId, string $tipo, int $maquinaId, string $bruto, string $usuario, string $observacoes): void
    {
        $plano = self::plano($planoId, $tipo, $maquinaId);
        if ((int) $plano['ativo'] !== 1) {
            throw new InvalidArgumentException('Esse serviço está desativado.');
        }
        $atual = self::atual($tipo, $maquinaId);
        if ($atual === null) {
            throw new InvalidArgumentException('Lance o horímetro da máquina antes de registrar o serviço.');
        }
        $horas = trim($bruto) === '' ? $atual : self::lerHoras($bruto, 'Horímetro do serviço', true);
        if (round($horas, 1) > round($atual, 1)) {
            throw new InvalidArgumentException('O serviço não pode ficar acima do horímetro atual, ' . self::texto($atual) . '.');
        }
        $ultima = self::ultimaFeita($planoId);
        if ($ultima !== null && round($horas, 1) < round($ultima, 1)) {
            throw new InvalidArgumentException('O serviço não pode ficar abaixo da última vez, ' . self::texto($ultima) . '.');
        }
        $nota = limpar_longo($observacoes, 4000);
        $comando = Db::pdo()->prepare(
            'INSERT INTO manutencoes (plano_id, horimetro, feito_em, usuario, observacoes)
             VALUES (?, ?, ?, ?, ?)'
        );
        $comando->execute([$planoId, self::gravar($horas), agora_sql(), $usuario, $nota]);
    }

    /** @return array<string, mixed> */
    public static function ficha(string $tipo, int $id): array
    {
        $atual = self::atual($tipo, $id);
        $planos = [];
        $pior = $atual === null ? 'sem_leitura' : 'sem_plano';
        $busca = Db::pdo()->prepare(
            'SELECT id, nome, intervalo_h, ativo FROM manutencao_planos
             WHERE tipo = ? AND maquina_id = ?
             ORDER BY ativo DESC, nome ASC'
        );
        $busca->execute([$tipo, $id]);
        foreach ($busca as $plano) {
            $ultima = self::ultimaFeita((int) $plano['id']);
            $situacao = self::situacao($atual, $ultima, (float) $plano['intervalo_h'], (int) $plano['ativo'] === 1);
            $planos[] = [
                'id' => (int) $plano['id'],
                'nome' => (string) $plano['nome'],
                'intervalo_h' => (float) $plano['intervalo_h'],
                'ativo' => (int) $plano['ativo'],
                'ultima' => $ultima,
                'situacao' => $situacao,
            ];
            if ((int) $plano['ativo'] === 1 && self::rank($situacao['estado']) < self::rank($pior)) {
                $pior = $situacao['estado'];
            }
        }
        if ($planos === [] && $atual !== null) {
            $pior = 'sem_plano';
        }

        return [
            'atual' => $atual,
            'planos' => $planos,
            'estado' => $pior,
            'rotulo' => self::rotuloEstado($pior),
            'historico' => self::historico($tipo, $id),
            'turnos' => self::turnosDaMaquina($tipo, $id),
        ];
    }

    public static function registrarTurno(string $tipo, int $id, float $entrada, float $saida, ?string $iniciadoEm, string $usuario): void
    {
        self::exigirMaquina($tipo, $id);
        $horas = round($saida - $entrada, 1);
        if ($horas < 0) {
            throw new InvalidArgumentException('A leitura de saída ficou menor que a de entrada.');
        }
        $comando = Db::pdo()->prepare(
            'INSERT INTO horimetro_turnos (tipo, maquina_id, usuario, entrada, saida, horas, iniciado_em, encerrado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $comando->execute([
            $tipo,
            $id,
            $usuario,
            self::gravar($entrada),
            self::gravar($saida),
            self::gravar($horas),
            $iniciadoEm,
            agora_sql(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public static function turnosDo(string $usuario, string $desde): array
    {
        $comando = Db::pdo()->prepare(
            'SELECT * FROM horimetro_turnos
             WHERE usuario = ? AND encerrado_em >= ?
             ORDER BY encerrado_em DESC, id DESC'
        );
        $comando->execute([$usuario, $desde]);

        return array_map(self::turnoComNome(...), $comando->fetchAll());
    }

    /** @return list<array<string, mixed>> */
    public static function turnosDaMaquina(string $tipo, int $id): array
    {
        self::exigirMaquina($tipo, $id);
        $comando = Db::pdo()->prepare(
            'SELECT * FROM horimetro_turnos
             WHERE tipo = ? AND maquina_id = ?
             ORDER BY encerrado_em DESC, id DESC
             LIMIT 8'
        );
        $comando->execute([$tipo, $id]);

        return array_map(self::turnoComNome(...), $comando->fetchAll());
    }

    /** @param array<string, mixed> $turno
     *  @return array<string, mixed>
     */
    private static function turnoComNome(array $turno): array
    {
        $chave = self::chaveDoTipo((string) $turno['tipo']);
        $item = Cadastro::buscar($chave, (int) $turno['maquina_id']);
        $turno['nome'] = $item !== null ? (string) ($item['nome'] ?? 'Máquina') : 'Máquina';

        return $turno;
    }

    /** @return list<array{titulo: string, itens: list<array<string, mixed>>}> */
    public static function painel(): array
    {
        $grupos = [
            'pas' => 'Pás carregadeiras',
            'retros' => 'Retroescavadeiras',
            'caminhoes' => 'Caminhões',
        ];
        $saida = [];
        foreach ($grupos as $chave => $titulo) {
            $itens = [];
            foreach (Cadastro::listar($chave) as $item) {
                if ((int) $item['ativo'] !== 1) {
                    continue;
                }
                $tipo = self::tipoDaChave($chave);
                $ficha = self::ficha($tipo, (int) $item['id']);
                $itens[] = [
                    'id' => (int) $item['id'],
                    'chave' => $chave,
                    'nome' => (string) $item['nome'],
                    'atual' => $ficha['atual'],
                    'estado' => $ficha['estado'],
                    'rotulo' => $ficha['rotulo'],
                    'planos' => array_values(array_filter(
                        $ficha['planos'],
                        static fn (array $plano): bool => (int) $plano['ativo'] === 1
                    )),
                ];
            }
            usort($itens, static function (array $a, array $b): int {
                $ordem = self::rank($a['estado']) <=> self::rank($b['estado']);

                return $ordem !== 0 ? $ordem : strcmp($a['nome'], $b['nome']);
            });
            $saida[] = ['titulo' => $titulo, 'itens' => $itens];
        }

        return $saida;
    }

    public static function texto(?float $horas): string
    {
        if ($horas === null) {
            return 'Sem leitura';
        }

        return number_format($horas, 1, ',', '.') . ' h';
    }

    /** @return array{estado: string, rotulo: string, falta: ?float, proxima: ?float} */
    private static function situacao(?float $atual, ?float $ultima, float $intervalo, bool $ativo): array
    {
        if (!$ativo) {
            return ['estado' => 'inativo', 'rotulo' => 'Desativado', 'falta' => null, 'proxima' => null];
        }
        if ($atual === null) {
            return ['estado' => 'sem_leitura', 'rotulo' => 'Sem leitura', 'falta' => null, 'proxima' => null];
        }
        if ($ultima === null) {
            return ['estado' => 'sem_servico', 'rotulo' => 'Ainda sem serviço', 'falta' => null, 'proxima' => null];
        }
        $proxima = round($ultima + $intervalo, 1);
        $falta = round($proxima - $atual, 1);
        if ($falta <= 0) {
            return ['estado' => 'vencida', 'rotulo' => 'Vencida', 'falta' => $falta, 'proxima' => $proxima];
        }
        if ($falta <= self::PROXIMA_H) {
            return ['estado' => 'proxima', 'rotulo' => 'Próxima', 'falta' => $falta, 'proxima' => $proxima];
        }

        return ['estado' => 'em_dia', 'rotulo' => 'Em dia', 'falta' => $falta, 'proxima' => $proxima];
    }

    private static function rotuloEstado(string $estado): string
    {
        return match ($estado) {
            'vencida' => 'Vencida',
            'proxima' => 'Próxima',
            'em_dia' => 'Em dia',
            'sem_servico' => 'Ainda sem serviço',
            'sem_plano' => 'Sem plano',
            default => 'Sem leitura',
        };
    }

    private static function rank(string $estado): int
    {
        return match ($estado) {
            'vencida' => 0,
            'proxima' => 1,
            'sem_servico' => 2,
            'em_dia' => 3,
            'sem_leitura' => 4,
            default => 5,
        };
    }

    private static function ultimaFeita(int $planoId): ?float
    {
        $busca = Db::pdo()->prepare(
            'SELECT horimetro FROM manutencoes
             WHERE plano_id = ?
             ORDER BY horimetro DESC, id DESC
             LIMIT 1'
        );
        $busca->execute([$planoId]);
        $valor = $busca->fetchColumn();

        return $valor === false ? null : (float) $valor;
    }

    /** @return array<string, mixed> */
    private static function plano(int $planoId, string $tipo, int $maquinaId): array
    {
        $busca = Db::pdo()->prepare(
            'SELECT id, ativo FROM manutencao_planos
             WHERE id = ? AND tipo = ? AND maquina_id = ?
             LIMIT 1'
        );
        $busca->execute([$planoId, $tipo, $maquinaId]);
        $plano = $busca->fetch();
        if (!$plano) {
            throw new InvalidArgumentException('Esse serviço não é desta máquina.');
        }

        return $plano;
    }

    private static function exigirMaquina(string $tipo, int $id): void
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Máquina não encontrada.');
        }
        $item = Cadastro::buscar(self::chaveDoTipo($tipo), $id);
        if ($item === null) {
            throw new InvalidArgumentException('Máquina não encontrada.');
        }
    }

    private static function lerHoras(string $bruto, string $rotulo, bool $permiteZero): float
    {
        $bruto = trim($bruto);
        if (str_contains($bruto, ',') && str_contains($bruto, '.')) {
            $bruto = str_replace('.', '', $bruto);
        }
        $bruto = str_replace(',', '.', $bruto);
        if (!preg_match('/^\d+(\.\d+)?$/', $bruto)) {
            throw new InvalidArgumentException($rotulo . ' precisa ser um número de horas.');
        }
        $numero = round((float) $bruto, 1);
        if ($numero > 999999.9 || ($permiteZero ? $numero < 0 : $numero <= 0)) {
            throw new InvalidArgumentException($permiteZero
                ? $rotulo . ' não pode ser negativo.'
                : $rotulo . ' precisa ser maior que zero.');
        }

        return $numero;
    }

    private static function gravar(float $horas): string
    {
        return number_format($horas, 1, '.', '');
    }
}
