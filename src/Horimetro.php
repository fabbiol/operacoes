<?php

declare(strict_types=1);

final class Horimetro
{
    public const PROXIMA_H = 100.0;
    // Avisos ao operador e ao administrativo quando faltam estas horas para o serviço (o mais forte por último).
    public const AVISOS_H = [100.0, 50.0, 25.0, 8.0];
    // Folga sobre o tempo decorrido: a máquina não roda mais horas do que o relógio.
    private const FOLGA_H = 1.0;

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
        $ultima = self::ultima($tipo, $id);

        return $ultima === null ? null : (float) $ultima['leitura'];
    }

    /** Maior leitura válida (não anulada) da máquina, com a hora em que foi lida. */
    private static function ultima(string $tipo, int $id): ?array
    {
        self::exigirMaquina($tipo, $id);
        $busca = Db::pdo()->prepare(
            'SELECT id, leitura, lido_em FROM horimetros
             WHERE tipo = ? AND maquina_id = ? AND anulado_em IS NULL
             ORDER BY leitura DESC, id DESC
             LIMIT 1'
        );
        $busca->execute([$tipo, $id]);
        $linha = $busca->fetch();

        return $linha ?: null;
    }

    /** Grava a leitura e devolve o valor lido. Use lancarComId() quando precisar do id do registro. */
    public static function lancar(string $tipo, int $id, string $bruto, string $usuario): float
    {
        return self::lancarComId($tipo, $id, $bruto, $usuario)['leitura'];
    }

    /**
     * @param string|null $lidoEm hora da leitura, quando ela é de antes (a entrada certa no lugar de uma anulada)
     * @return array{id: int, leitura: float}
     */
    public static function lancarComId(string $tipo, int $id, string $bruto, string $usuario, ?string $lidoEm = null): array
    {
        self::exigirMaquina($tipo, $id);
        $leitura = self::lerHoras($bruto, 'Horímetro', true);
        $ultima = self::ultima($tipo, $id);
        if ($ultima !== null) {
            $atual = (float) $ultima['leitura'];
            if (round($leitura, 1) < round($atual, 1)) {
                throw new InvalidArgumentException('A leitura não pode ser menor que o horímetro atual, ' . self::texto($atual) . '.');
            }
            // Um número digitado errado (12345 em vez de 1234,5) travaria a máquina: a leitura não passa do tempo real.
            $quando = $lidoEm !== null ? (int) strtotime($lidoEm) : time();
            $passou = max(0.0, ($quando - (int) strtotime((string) $ultima['lido_em'])) / 3600);
            if (round($leitura, 1) > round($atual + $passou + self::FOLGA_H, 1)) {
                throw new InvalidArgumentException(
                    'A leitura passa do possível: a última foi ' . self::texto($atual) . ' em ' . quando((string) $ultima['lido_em'])
                    . ' e desde então passaram ' . self::texto(round($passou, 1)) . '. Confira o número.'
                );
            }
        }
        $comando = Db::pdo()->prepare(
            'INSERT INTO horimetros (tipo, maquina_id, leitura, lido_em, usuario)
             VALUES (?, ?, ?, ?, ?)'
        );
        $comando->execute([$tipo, $id, self::gravar($leitura), $lidoEm ?? agora_sql(), $usuario]);

        return ['id' => (int) Db::pdo()->lastInsertId(), 'leitura' => $leitura];
    }

    /**
     * O administrativo anula uma leitura errada. O registro fica, marcado, e deixa de valer.
     * Se a leitura é a entrada de quem está com a máquina, a leitura certa (opcional) passa a ser a entrada,
     * gravada com a hora da anulada; sem ela, o turno dessa pessoa fica sem entrada e não é somado.
     * Devolve o aviso para a tela.
     */
    public static function anular(int $leituraId, string $tipo, int $maquinaId, string $motivo, string $usuario, string $correta = ''): string
    {
        self::exigirMaquina($tipo, $maquinaId);
        $motivo = limpar_texto($motivo, 200, true, 'o motivo');
        $busca = Db::pdo()->prepare('SELECT lido_em FROM horimetros WHERE id = ? AND tipo = ? AND maquina_id = ? AND anulado_em IS NULL');
        $busca->execute([$leituraId, $tipo, $maquinaId]);
        $lidoEm = $busca->fetchColumn();
        if ($lidoEm === false) {
            throw new InvalidArgumentException('Essa leitura não é desta máquina ou já foi anulada.');
        }
        $posse = Posse::daMaquina($tipo, $maquinaId);
        $entrada = $posse !== null && (int) ($posse['entrada_id'] ?? 0) === $leituraId;
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $comando = $pdo->prepare(
                'UPDATE horimetros
                 SET anulado_em = ?, anulado_por = ?, anulado_motivo = ?
                 WHERE id = ? AND tipo = ? AND maquina_id = ? AND anulado_em IS NULL'
            );
            $comando->execute([agora_sql(), $usuario, $motivo, $leituraId, $tipo, $maquinaId]);
            if ($comando->rowCount() < 1) {
                throw new InvalidArgumentException('Essa leitura não é desta máquina ou já foi anulada.');
            }
            $aviso = 'Leitura anulada. O horímetro atual passa a ser a maior leitura que sobrou.';
            if ($entrada && trim($correta) !== '') {
                $certa = self::lancarComId($tipo, $maquinaId, $correta, $usuario, (string) $lidoEm);
                Posse::trocarEntrada($leituraId, $certa['id'], $certa['leitura']);
                $aviso = 'Leitura anulada. A entrada de ' . $posse['usuario'] . ' passa a ser ' . self::texto($certa['leitura']) . '.';
            } elseif ($entrada) {
                Posse::esquecerEntrada($leituraId);
                $aviso = 'Leitura anulada. O turno de ' . $posse['usuario'] . ' ficou sem a entrada e não será somado.'
                    . ' Para somar, anule informando a leitura certa da entrada, ou peça para a pessoa soltar e assumir de novo.';
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $aviso;
    }

    /**
     * O operador corrige a entrada do próprio turno (digitou errado ao assumir). Só vale se ninguém lançou
     * outra leitura depois dela; senão, quem corrige é o administrativo, na ficha da máquina.
     */
    public static function corrigirEntrada(string $tipo, int $maquinaId, int $entradaId, string $correta, string $usuario): void
    {
        if ($entradaId < 1) {
            throw new InvalidArgumentException('Este turno não tem entrada para corrigir. Chame o administrativo.');
        }
        $depois = Db::pdo()->prepare(
            'SELECT COUNT(*) FROM horimetros WHERE tipo = ? AND maquina_id = ? AND id > ? AND anulado_em IS NULL'
        );
        $depois->execute([$tipo, $maquinaId, $entradaId]);
        if ((int) $depois->fetchColumn() > 0) {
            throw new InvalidArgumentException('Já houve outra leitura depois da sua entrada. Chame o administrativo para corrigir na ficha da máquina.');
        }
        // A entrada já tem esse valor (corrigida numa tentativa anterior, em que a saída foi recusada e o formulário
        // voltou preenchido): não anula e lança de novo.
        $atual = Db::pdo()->prepare('SELECT leitura FROM horimetros WHERE id = ? AND anulado_em IS NULL');
        $atual->execute([$entradaId]);
        $valor = $atual->fetchColumn();
        if ($valor !== false && abs((float) $valor - self::lerHoras($correta, 'Horímetro', true)) < 0.05) {
            return;
        }
        self::anular($entradaId, $tipo, $maquinaId, 'Entrada corrigida por ' . $usuario . ' ao soltar', $usuario, $correta);
    }

    /** @return list<array<string, mixed>> */
    public static function historico(string $tipo, int $id): array
    {
        self::exigirMaquina($tipo, $id);
        $busca = Db::pdo()->prepare(
            'SELECT id, leitura, lido_em, usuario, anulado_em, anulado_por, anulado_motivo FROM horimetros
             WHERE tipo = ? AND maquina_id = ?
             ORDER BY lido_em DESC, id DESC
             LIMIT 8'
        );
        $busca->execute([$tipo, $id]);

        return $busca->fetchAll();
    }

    public static function criarPlano(string $tipo, int $id, string $nome, string $intervalo): int
    {
        self::exigirMaquina($tipo, $id);
        $titulo = limpar_texto($nome, 120, true, 'Serviço');
        $horas = self::lerHoras($intervalo, 'Intervalo', false);
        $comando = Db::pdo()->prepare(
            'INSERT INTO manutencao_planos (tipo, maquina_id, nome, intervalo_h)
             VALUES (?, ?, ?, ?)'
        );
        $comando->execute([$tipo, $id, $titulo, self::gravar($horas)]);

        return (int) Db::pdo()->lastInsertId();
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
        // Serviço gravado sobre uma leitura que depois foi anulada fica acima do horímetro atual:
        // nesse caso o registro novo, com o horímetro certo, substitui aquele.
        if ($ultima !== null && round($ultima, 1) <= round($atual, 1) && round($horas, 1) < round($ultima, 1)) {
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
        $planos = self::planosDe($tipo, $id, $atual, self::mediaPorDia($tipo, $id));
        $pior = $atual === null ? 'sem_leitura' : 'sem_plano';
        foreach ($planos as $plano) {
            if ($plano['ativo'] === 1 && self::rank($plano['situacao']['estado']) < self::rank($pior)) {
                $pior = $plano['situacao']['estado'];
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
            'posse' => Posse::daMaquina($tipo, $id),
            'problemas' => Problemas::abertosDa($tipo, $id),
            'resolvidos' => Problemas::resolvidosDa($tipo, $id),
        ];
    }

    /** @param bool|null $maquinaOk como o operador entregou a máquina; null quando ninguém perguntou */
    /**
     * Serviços ativos da máquina que estão no aviso (faltam 100 h ou menos) ou vencidos, o mais urgente primeiro.
     *
     * @return list<array{id: int, nome: string, intervalo_h: float, ultima: ?float, situacao: array<string, mixed>}>
     */
    public static function avisos(string $tipo, int $id): array
    {
        $atual = self::atual($tipo, $id);
        if ($atual === null) {
            return [];
        }
        $avisos = array_values(array_filter(
            self::planosDe($tipo, $id, $atual, null),
            static fn (array $plano): bool => $plano['ativo'] === 1 && in_array($plano['situacao']['estado'], ['vencida', 'proxima'], true)
        ));
        usort($avisos, static fn (array $a, array $b): int => $a['situacao']['falta'] <=> $b['situacao']['falta']);

        return $avisos;
    }

    /**
     * Os avisos de todas as máquinas ativas, para o Painel.
     *
     * @return list<array{chave: string, id: int, maquina: string, nome: string, situacao: array<string, mixed>}>
     */
    public static function avisosDeTodas(): array
    {
        $saida = [];
        foreach (self::CHAVES as $chave => $tipo) {
            foreach (Cadastro::listar($chave) as $item) {
                if ((int) $item['ativo'] !== 1) {
                    continue;
                }
                foreach (self::avisos($tipo, (int) $item['id']) as $plano) {
                    $saida[] = ['chave' => $chave, 'id' => (int) $item['id'], 'maquina' => (string) $item['nome'],
                        'nome' => $plano['nome'], 'situacao' => $plano['situacao']];
                }
            }
        }
        usort($saida, static fn (array $a, array $b): int => $a['situacao']['falta'] <=> $b['situacao']['falta']);

        return $saida;
    }

    /**
     * Troca registrada pelo operador no tablet, na máquina que está com ele: um serviço da lista (com o prazo
     * já salvo) ou um serviço novo, com o prazo dito na primeira vez. O horímetro é o atual da máquina.
     *
     * @return string o aviso para a tela
     */
    public static function registrarTroca(string $tipo, int $id, array $post, string $usuario): string
    {
        $escolha = (string) ($post['servico'] ?? '');
        if ($escolha === '') {
            throw new InvalidArgumentException('Escolha o serviço que foi feito.');
        }
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            if ($escolha === 'novo') {
                $nome = (string) limpar_texto((string) ($post['nome'] ?? ''), 120, true, 'o nome do serviço');
                foreach (self::planosDe($tipo, $id, null, null) as $plano) {
                    if (mb_strtolower($plano['nome']) === mb_strtolower($nome)) {
                        throw new InvalidArgumentException('Esse serviço já está na lista desta máquina. Escolha ele na lista.');
                    }
                }
                $planoId = self::criarPlano($tipo, $id, $nome, (string) ($post['intervalo'] ?? ''));
            } else {
                $planoId = (int) $escolha;
            }
            self::registrar($planoId, $tipo, $id, '', $usuario, '');
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        foreach (self::planosDe($tipo, $id, self::atual($tipo, $id), null) as $plano) {
            if ($plano['id'] === $planoId) {
                return $plano['nome'] . ' registrada com ' . self::texto($plano['ultima'])
                    . '. A próxima é com ' . self::texto($plano['situacao']['proxima']) . '.';
            }
        }

        return 'Troca registrada.';
    }

    public static function registrarTurno(string $tipo, int $id, float $entrada, float $saida, ?string $iniciadoEm, string $usuario, ?bool $maquinaOk = null): void
    {
        self::exigirMaquina($tipo, $id);
        $horas = round($saida - $entrada, 1);
        if ($horas < 0) {
            throw new InvalidArgumentException('A leitura de saída ficou menor que a de entrada.');
        }
        $comando = Db::pdo()->prepare(
            'INSERT INTO horimetro_turnos (tipo, maquina_id, usuario, entrada, saida, horas, maquina_ok, iniciado_em, encerrado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $comando->execute([
            $tipo,
            $id,
            $usuario,
            self::gravar($entrada),
            self::gravar($saida),
            self::gravar($horas),
            $maquinaOk === null ? null : ($maquinaOk ? 1 : 0),
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
            'retros' => 'Escavadeiras hidráulicas',
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
                    'posse' => $ficha['posse'],
                    'estado' => $ficha['estado'],
                    'rotulo' => $ficha['rotulo'],
                    'problemas' => $ficha['problemas'],
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

    /** Horas de uso por dia com turno, nos últimos 14 dias. Sem turno no período: null. */
    public static function mediaPorDia(string $tipo, int $id): ?float
    {
        $busca = Db::pdo()->prepare(
            'SELECT SUM(horas), COUNT(DISTINCT DATE(encerrado_em)) FROM horimetro_turnos
             WHERE tipo = ? AND maquina_id = ? AND encerrado_em >= ?'
        );
        $busca->execute([$tipo, $id, date('Y-m-d H:i:s', time() - 14 * 86400)]);
        [$horas, $dias] = $busca->fetch(PDO::FETCH_NUM);

        return (int) $dias > 0 && (float) $horas > 0 ? (float) $horas / (int) $dias : null;
    }

    /** "vence por volta de quinta, 30/10": o dia em que as horas que faltam acabam, na média de uso. */
    public static function previsao(?float $falta, ?float $media, ?DateTimeImmutable $hoje = null): ?string
    {
        if ($falta === null || $falta <= 0 || $media === null || $media <= 0) {
            return null;
        }
        $dias = (int) round($falta / $media);
        if ($dias === 0) {
            return 'vence por volta de hoje';
        }
        $dia = ($hoje ?? new DateTimeImmutable('today'))->modify('+' . $dias . ' days');
        $semana = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];

        return 'vence por volta de ' . $semana[(int) $dia->format('w')] . ', ' . $dia->format('d/m');
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
        // O serviço não pode ter sido feito acima do horímetro real (leitura anulada depois do registro).
        $proxima = round(min($ultima, $atual) + $intervalo, 1);
        $falta = round($proxima - $atual, 1);
        if ($falta <= 0) {
            return ['estado' => 'vencida', 'rotulo' => 'Vencida', 'falta' => $falta, 'proxima' => $proxima];
        }
        if ($falta <= self::PROXIMA_H) {
            // A faixa do aviso: a menor das AVISOS_H que ainda cobre o que falta (faltam 40 h: faixa 50).
            $faixa = self::PROXIMA_H;
            foreach (self::AVISOS_H as $limite) {
                if ($falta <= $limite) {
                    $faixa = $limite;
                }
            }

            return ['estado' => 'proxima', 'rotulo' => 'Próxima', 'falta' => $falta, 'proxima' => $proxima, 'faixa' => $faixa];
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

    /**
     * Serviços da máquina com a situação de cada um (ativos primeiro, por nome).
     *
     * @return list<array{id: int, nome: string, intervalo_h: float, ativo: int, ultima: ?float, situacao: array<string, mixed>}>
     */
    private static function planosDe(string $tipo, int $id, ?float $atual, ?float $media): array
    {
        $busca = Db::pdo()->prepare(
            'SELECT id, nome, intervalo_h, ativo FROM manutencao_planos
             WHERE tipo = ? AND maquina_id = ?
             ORDER BY ativo DESC, nome ASC'
        );
        $busca->execute([$tipo, $id]);
        $planos = [];
        foreach ($busca as $plano) {
            $ultima = self::ultimaFeita((int) $plano['id']);
            $situacao = self::situacao($atual, $ultima, (float) $plano['intervalo_h'], (int) $plano['ativo'] === 1);
            $situacao['previsao'] = self::previsao($situacao['falta'], $media);
            $planos[] = [
                'id' => (int) $plano['id'],
                'nome' => (string) $plano['nome'],
                'intervalo_h' => (float) $plano['intervalo_h'],
                'ativo' => (int) $plano['ativo'],
                'ultima' => $ultima,
                'situacao' => $situacao,
            ];
        }

        return $planos;
    }

    private static function ultimaFeita(int $planoId): ?float
    {
        $busca = Db::pdo()->prepare(
            'SELECT horimetro FROM manutencoes
             WHERE plano_id = ?
             ORDER BY feito_em DESC, id DESC
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
        $bruto = str_replace(' ', '', trim($bruto));
        if (str_contains($bruto, ',') && str_contains($bruto, '.')) {
            $bruto = str_replace('.', '', $bruto);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $bruto)) {
            // "12.350" ou "1.234.567": ponto de milhar, igual ao que a tela mostra ("12.345,0 h"). A casa decimal é a vírgula.
            $bruto = str_replace('.', '', $bruto);
        }
        $bruto = str_replace(',', '.', $bruto);
        if (!preg_match('/^\d+(\.\d+)?$/', $bruto)) {
            throw new InvalidArgumentException($rotulo . ' precisa ser um número de horas.');
        }
        $numero = round((float) $bruto, 1);
        if ($numero > 999999.9) {
            throw new InvalidArgumentException($rotulo . ' pode ter no máximo 999.999,9 h. Confira o número.');
        }
        if ($permiteZero ? $numero < 0 : $numero <= 0) {
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
