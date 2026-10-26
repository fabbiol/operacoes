<?php

declare(strict_types=1);

final class Pessoas
{
    /** Funções que se marcam no cadastro. "operador" é o operador de pá carregadeira (pátio). */
    public const FUNCOES = ['motorista', 'operador', 'escavadeira', 'britador'];

    public const ROTULO_BRITADOR = 'Operador do britador secundário';

    public static function listar(): array
    {
        return Db::pdo()->query(
            'SELECT id, nome, nome_completo, administrador, motorista, operador, escavadeira, britador, foto, ativo
             FROM usuarios
             ORDER BY ativo DESC, administrador DESC, nome ASC'
        )->fetchAll();
    }

    public static function buscar(int $id): ?array
    {
        $busca = Db::pdo()->prepare(
            'SELECT id, nome, nome_completo, administrador, motorista, operador, escavadeira, britador, foto, ativo
             FROM usuarios WHERE id = ? LIMIT 1'
        );
        $busca->execute([$id]);
        $linha = $busca->fetch();

        return $linha ?: null;
    }

    public static function paraEntrada(): array
    {
        $linhas = Db::pdo()->query(
            'SELECT id, nome, nome_completo, foto, motorista, operador, escavadeira, britador
             FROM usuarios
             WHERE ativo = 1 AND administrador = 0 AND (motorista = 1 OR operador = 1 OR escavadeira = 1 OR britador = 1)
             ORDER BY nome ASC'
        )->fetchAll();

        return array_values(array_filter(
            $linhas,
            static fn (array $pessoa): bool => !Auth::administradorFixo((string) $pessoa['nome'])
        ));
    }

    /**
     * Nomes de quem pode trabalhar com a máquina: 'pa' (pá carregadeira) ou 'retro' (escavadeira hidráulica).
     *
     * @return list<string>
     */
    public static function operadores(string $maquina = 'pa'): array
    {
        $coluna = $maquina === 'retro' ? 'escavadeira' : 'operador';
        $nomes = [];
        foreach (Db::pdo()->query('SELECT nome FROM usuarios WHERE ativo = 1 AND administrador = 0 AND ' . $coluna . ' = 1 ORDER BY nome ASC') as $linha) {
            $nomes[] = (string) $linha['nome'];
        }

        return $nomes;
    }

    public static function elegivelEntrada(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $busca = Db::pdo()->prepare(
            'SELECT id, nome, nome_completo, foto, motorista, operador, escavadeira, britador, administrador, ativo
             FROM usuarios WHERE id = ? LIMIT 1'
        );
        $busca->execute([$id]);
        $pessoa = $busca->fetch();
        if (!$pessoa || (int) $pessoa['ativo'] !== 1 || (int) $pessoa['administrador'] === 1) {
            return null;
        }
        if ((int) $pessoa['motorista'] !== 1 && (int) $pessoa['operador'] !== 1 && (int) $pessoa['escavadeira'] !== 1 && (int) ($pessoa['britador'] ?? 0) !== 1) {
            return null;
        }
        if (Auth::administradorFixo((string) $pessoa['nome'])) {
            return null;
        }

        return $pessoa;
    }

    /** Funções marcadas da pessoa: 'motorista', 'operador' (pá carregadeira) e 'escavadeira' (escavadeira hidráulica). */
    public static function funcoes(array $pessoa): array
    {
        if (isset($pessoa['funcoes']) && is_array($pessoa['funcoes'])) {
            return array_values(array_intersect(self::FUNCOES, $pessoa['funcoes']));
        }
        $lista = [];
        foreach (self::FUNCOES as $funcao) {
            if ((int) ($pessoa[$funcao] ?? 0) === 1) {
                $lista[] = $funcao;
            }
        }

        return $lista;
    }

    public static function rotulo(array $pessoa): string
    {
        if ((int) ($pessoa['administrador'] ?? 0) === 1) {
            return 'Administrativo';
        }
        $funcoes = self::funcoes($pessoa);
        if (in_array('britador', $funcoes, true)) {
            return self::ROTULO_BRITADOR;
        }

        return self::rotuloFuncoes(
            in_array('motorista', $funcoes, true),
            in_array('operador', $funcoes, true),
            in_array('escavadeira', $funcoes, true)
        );
    }

    public static function rotuloFuncoes(bool $motorista, bool $operador, bool $escavadeira): string
    {
        $maquinas = match (true) {
            $operador && $escavadeira => 'pá e escavadeira',
            $operador => $motorista ? 'pá' : 'pá carregadeira',
            $escavadeira => $motorista ? 'escavadeira' : 'escavadeira hidráulica',
            default => '',
        };
        if ($motorista) {
            return $maquinas === '' ? 'Motorista' : 'Motorista e operador de ' . $maquinas;
        }

        return $maquinas === '' ? '' : 'Operador de ' . $maquinas;
    }

    public static function salvar(?int $id, array $post): int
    {
        $atual = null;
        if ($id !== null) {
            $atual = self::buscar($id);
            if ($atual === null) {
                throw new InvalidArgumentException('Pessoa não encontrada.');
            }
        }

        $fixo = $atual !== null && Auth::administradorFixo((string) $atual['nome']);
        $nome = $fixo
            ? (string) $atual['nome']
            : Auth::nomeSimples((string) ($post['nome'] ?? ''));
        if ($nome === null || $nome === '' || preg_match('/\s/u', $nome)) {
            throw new InvalidArgumentException('O usuário é o primeiro nome, sem sobrenome.');
        }
        if ($atual !== null && $nome !== (string) $atual['nome'] && Auth::configuradoNoServidor((string) $atual['nome'])) {
            throw new InvalidArgumentException('Esse usuário vem da configuração do servidor (APP_USUARIOS). Para trocar o nome, troque lá.');
        }
        $completo = self::nomeCompleto((string) ($post['nome_completo'] ?? ''));

        $admin = $atual !== null && (int) $atual['administrador'] === 1;
        if ($admin) {
            $motorista = 0;
            $operador = 0;
            $escavadeira = 0;
            $britador = 0;
            $papel = 'admin';
        } else {
            $funcoes = self::funcoesDoFormulario($post);
            if ($funcoes === []) {
                throw new InvalidArgumentException('Marque pelo menos uma função.');
            }
            $motorista = in_array('motorista', $funcoes, true) ? 1 : 0;
            $operador = in_array('operador', $funcoes, true) ? 1 : 0;
            $escavadeira = in_array('escavadeira', $funcoes, true) ? 1 : 0;
            $britador = in_array('britador', $funcoes, true) ? 1 : 0;
            if ($britador === 1 && count($funcoes) > 1) {
                throw new InvalidArgumentException('O operador do britador secundário tem só essa função. Desmarque as outras.');
            }
            $papel = $britador === 1 ? 'britador' : self::papel($motorista === 1, $operador === 1, $escavadeira === 1);
        }

        $ativo = $fixo ? 1 : (isset($post['ativo']) ? 1 : 0);
        if ($atual !== null && (int) $atual['ativo'] === 1 && $ativo === 0) {
            self::exigirSemMaquina($atual);
        }
        if ($atual !== null) {
            self::exigirFuncaoDaMaquina($atual, $motorista === 1, $operador === 1, $escavadeira === 1);
        }
        $senha = (string) ($post['senha'] ?? '');
        if (trim($senha) !== '') {
            $senha = trim($senha);
            $minimo = $admin ? Auth::senhaAdminMinima() : 4;
            if (mb_strlen($senha, 'UTF-8') < $minimo || strlen($senha) > 72) {
                throw new InvalidArgumentException('A senha precisa ter entre ' . $minimo . ' e 72 caracteres.');
            }
        } elseif ($id === null) {
            throw new InvalidArgumentException('Informe a senha.');
        }

        $pendente = self::prepararFoto();
        try {
            if ($id === null) {
                $idSalvo = self::inserir($nome, $completo, $senha, $papel, $motorista, $operador, $escavadeira, $britador, $ativo);
            } else {
                self::atualizar($id, (string) $atual['nome'], $nome, $completo, $senha, $papel, $motorista, $operador, $escavadeira, $britador, $ativo, $admin);
                $idSalvo = $id;
            }

            if ($pendente !== null) {
                try {
                    self::gravarFoto($idSalvo, $pendente);
                    $pendente = null;
                } catch (Throwable $e) {
                    if ($id === null) {
                        self::excluir($idSalvo);
                    }
                    throw $e;
                }
            } elseif ($id !== null && isset($post['remover_foto'])) {
                self::apagarFoto($id);
            }

            return $idSalvo;
        } finally {
            if ($pendente !== null && is_file($pendente['tmp'])) {
                unlink($pendente['tmp']);
            }
        }
    }

    public static function alternarAtivo(int $id): void
    {
        $atual = self::buscar($id);
        if ($atual === null) {
            throw new InvalidArgumentException('Pessoa não encontrada.');
        }
        if (Auth::administradorFixo((string) $atual['nome']) || (int) $atual['administrador'] === 1) {
            throw new InvalidArgumentException('O acesso administrativo permanece ativo.');
        }
        if ((int) $atual['id'] === Auth::id()) {
            throw new InvalidArgumentException('Você não pode desativar o seu próprio acesso.');
        }
        if ((int) $atual['ativo'] === 1) {
            self::exigirSemMaquina($atual);
        }
        $comando = Db::pdo()->prepare('UPDATE usuarios SET ativo = ? WHERE id = ?');
        $comando->execute([(int) $atual['ativo'] === 1 ? 0 : 1, $id]);
    }

    /** @return list<string> funções marcadas no formulário (aceita também o campo antigo "funcao", de uma tela aberta antes da atualização) */
    public static function funcoesDoFormulario(array $post): array
    {
        if (isset($post['funcoes']) && is_array($post['funcoes'])) {
            $marcadas = array_map('strval', $post['funcoes']);

            return array_values(array_intersect(self::FUNCOES, $marcadas));
        }

        return match ((string) ($post['funcao'] ?? '')) {
            'motorista' => ['motorista'],
            'operador' => ['operador', 'escavadeira'],
            'ambos' => ['motorista', 'operador', 'escavadeira'],
            default => [],
        };
    }

    /** O papel antigo continua gravado para quem lê a tabela; quem manda são as funções marcadas. */
    private static function papel(bool $motorista, bool $operador, bool $escavadeira): string
    {
        return match (true) {
            $motorista && ($operador || $escavadeira) => 'ambos',
            $motorista => 'motorista',
            $operador => 'operador',
            default => 'escavadeira',
        };
    }

    /** Quem está com uma máquina não é desativado: a máquina ficaria presa a uma pessoa que não entra mais. */
    private static function exigirSemMaquina(array $pessoa): void
    {
        $posse = Posse::doUsuario((int) $pessoa['id'], true);
        if ($posse !== null) {
            throw new InvalidArgumentException(
                $pessoa['nome'] . ' está com ' . Posse::nomeDaMaquina((string) $posse['tipo'], (int) $posse['maquina_id'])
                . '. Libere a máquina na ficha dela antes de desativar.'
            );
        }
    }

    /** A função nova precisa continuar permitindo a máquina que a pessoa está usando, senão o turno dela se perde. */
    private static function exigirFuncaoDaMaquina(array $pessoa, bool $motorista, bool $operador, bool $escavadeira): void
    {
        $posse = Posse::doUsuario((int) $pessoa['id'], true);
        if ($posse === null) {
            return;
        }
        $permite = match ((string) $posse['tipo']) {
            'caminhao' => $motorista,
            'pa' => $operador,
            'retro' => $escavadeira,
            default => true,
        };
        if (!$permite) {
            throw new InvalidArgumentException(
                $pessoa['nome'] . ' está com ' . Posse::nomeDaMaquina((string) $posse['tipo'], (int) $posse['maquina_id'])
                . '. Libere a máquina na ficha dela antes de trocar a função.'
            );
        }
    }

    public static function arquivoFoto(int $id): ?array
    {
        $pessoa = self::buscar($id);
        if ($pessoa === null || empty($pessoa['foto'])) {
            return null;
        }
        $relativo = str_replace('\\', '/', (string) $pessoa['foto']);
        if (!preg_match('#^usuarios/(\d+)\.(jpg|png|webp)$#', $relativo, $partes)) {
            return null;
        }
        if ((int) $partes[1] !== $id) {
            return null;
        }

        $base = realpath(OPERACOES_ROOT . '/storage/fotos');
        $arquivo = realpath(OPERACOES_ROOT . '/storage/fotos/' . $relativo);
        if ($base === false || $arquivo === false || !is_file($arquivo)) {
            return null;
        }
        $baseNormal = rtrim(str_replace('\\', '/', $base), '/');
        $arquivoNormal = str_replace('\\', '/', $arquivo);
        if (!str_starts_with($arquivoNormal, $baseNormal . '/')) {
            return null;
        }

        $tipos = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

        return [
            'caminho' => $arquivo,
            'tipo' => $tipos[$partes[2]],
        ];
    }

    private static function inserir(
        string $nome,
        string $completo,
        string $senha,
        string $papel,
        int $motorista,
        int $operador,
        int $escavadeira,
        int $britador,
        int $ativo
    ): int {
        $comando = Db::pdo()->prepare(
            'INSERT INTO usuarios (nome, nome_completo, senha_hash, administrador, papel, motorista, operador, escavadeira, britador, ativo)
             VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?, ?)'
        );
        try {
            $comando->execute([$nome, $completo, password_hash($senha, PASSWORD_DEFAULT), $papel, $motorista, $operador, $escavadeira, $britador, $ativo]);
        } catch (PDOException $e) {
            self::traduzirDuplicado($e);
        }

        return (int) Db::pdo()->lastInsertId();
    }

    private static function atualizar(
        int $id,
        string $nomeAntigo,
        string $nome,
        string $completo,
        string $senha,
        string $papel,
        int $motorista,
        int $operador,
        int $escavadeira,
        int $britador,
        int $ativo,
        bool $admin
    ): void {
        $sql = 'UPDATE usuarios SET nome = ?, nome_completo = ?, papel = ?, motorista = ?, operador = ?, escavadeira = ?, britador = ?, ativo = ?';
        $params = [$nome, $completo, $papel, $motorista, $operador, $escavadeira, $britador, $ativo];
        if ($senha !== '') {
            $sql .= ', senha_hash = ?';
            $params[] = password_hash($senha, PASSWORD_DEFAULT);
        }
        if ($admin) {
            $sql .= ', administrador = 1';
        }
        $sql .= ' WHERE id = ?';
        $params[] = $id;
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            try {
                $pdo->prepare($sql)->execute($params);
            } catch (PDOException $e) {
                self::traduzirDuplicado($e);
            }
            if ($nome !== $nomeAntigo) {
                self::levarHistorico($nomeAntigo, $nome);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * O trabalho fica registrado com o nome de quem fez. Ao trocar o nome, o histórico vai junto:
     * viagens, cargas, horímetro, turnos, manutenções, problemas das máquinas, estoque, britagens, pedidos de peças, oficina
     * e a máquina que a pessoa está usando agora.
     * O texto livre das observações fica como foi escrito.
     */
    private static function levarHistorico(string $antigo, string $novo): void
    {
        $colunas = [
            'fila_carregamentos' => ['operador', 'autorizado_por'],
            'ciclos_lavra' => ['operador', 'operador_carga', 'encerramento_ciente_por'],
            'horimetros' => ['usuario', 'anulado_por'],
            'horimetro_turnos' => ['usuario'],
            'manutencoes' => ['usuario'],
            'tablets' => ['autorizado_por'],
            'maquina_posse' => ['usuario'],
            'maquina_problemas' => ['usuario', 'resolvido_por'],
            'lavra_limpezas' => ['operador'],
            'britagens' => ['usuario', 'terminado_por'],
            'britagem_problemas' => ['usuario', 'resolvido_por'],
            'estoque_entradas' => ['usuario'],
            'pecas_pedidos' => ['pedido_por', 'confirmado_com', 'trocado_por'],
            'maquina_oficina' => ['entrada_por', 'saida_por'],
        ];
        foreach ($colunas as $tabela => $lista) {
            // Trocar o nome não é mexer na viagem/ordem: atualizado_em fica como estava (a lavra mede tempo por ele).
            $manter = in_array($tabela, ['fila_carregamentos', 'ciclos_lavra'], true) ? ', atualizado_em = atualizado_em' : '';
            foreach ($lista as $coluna) {
                Db::pdo()->prepare(sprintf('UPDATE %s SET %s = ?%s WHERE %s = ?', $tabela, $coluna, $manter, $coluna))->execute([$novo, $antigo]);
            }
        }
    }

    private static function excluir(int $id): void
    {
        self::limparArquivos($id);
        Db::pdo()->prepare('DELETE FROM usuarios WHERE id = ? AND administrador = 0')->execute([$id]);
    }

    /** @return array{tmp: string, ext: string}|null */
    private static function prepararFoto(): ?array
    {
        return Fotos::receber(Fotos::LADO_PESSOA);
    }

    private static function gravarFoto(int $id, array $pendente): void
    {
        self::limparArquivos($id);
        $dir = OPERACOES_ROOT . '/storage/fotos/usuarios';
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
        Db::pdo()->prepare('UPDATE usuarios SET foto = ? WHERE id = ?')->execute(['usuarios/' . $id . '.' . $pendente['ext'], $id]);
    }

    private static function apagarFoto(int $id): void
    {
        self::limparArquivos($id);
        Db::pdo()->prepare('UPDATE usuarios SET foto = NULL WHERE id = ?')->execute([$id]);
    }

    private static function limparArquivos(int $id): void
    {
        if ($id < 1) {
            return;
        }
        $dir = OPERACOES_ROOT . '/storage/fotos/usuarios';
        foreach (['jpg', 'png', 'webp'] as $ext) {
            $arquivo = $dir . DIRECTORY_SEPARATOR . $id . '.' . $ext;
            if (is_file($arquivo)) {
                unlink($arquivo);
            }
        }
    }

    private static function nomeCompleto(string $valor): string
    {
        $valor = trim((string) (preg_replace('/\s+/u', ' ', $valor) ?? ''));
        if ($valor === '' || str_contains($valor, '@') || !preg_match('/^[\p{L}][\p{L}\p{N} ]{0,119}$/u', $valor)) {
            throw new InvalidArgumentException('Informe o nome completo da pessoa, sem e-mail.');
        }

        return $valor;
    }

    private static function traduzirDuplicado(PDOException $e): void
    {
        if (isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1062) {
            throw new InvalidArgumentException('Já existe uma pessoa com esse usuário.');
        }
        throw $e;
    }
}
