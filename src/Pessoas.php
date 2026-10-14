<?php

declare(strict_types=1);

final class Pessoas
{
    public static function listar(): array
    {
        return Db::pdo()->query(
            'SELECT id, nome, nome_completo, administrador, motorista, operador, foto, ativo
             FROM usuarios
             ORDER BY ativo DESC, administrador DESC, nome ASC'
        )->fetchAll();
    }

    public static function buscar(int $id): ?array
    {
        $busca = Db::pdo()->prepare(
            'SELECT id, nome, nome_completo, administrador, motorista, operador, foto, ativo
             FROM usuarios WHERE id = ? LIMIT 1'
        );
        $busca->execute([$id]);
        $linha = $busca->fetch();

        return $linha ?: null;
    }

    public static function paraEntrada(): array
    {
        $linhas = Db::pdo()->query(
            'SELECT id, nome, nome_completo, foto, motorista, operador
             FROM usuarios
             WHERE ativo = 1 AND administrador = 0 AND (motorista = 1 OR operador = 1)
             ORDER BY nome ASC'
        )->fetchAll();

        return array_values(array_filter(
            $linhas,
            static fn (array $pessoa): bool => !Auth::administradorFixo((string) $pessoa['nome'])
        ));
    }

    /** @return list<string> nomes de quem pode carregar no pátio */
    public static function operadores(): array
    {
        $nomes = [];
        foreach (Db::pdo()->query('SELECT nome FROM usuarios WHERE ativo = 1 AND administrador = 0 AND operador = 1 ORDER BY nome ASC') as $linha) {
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
            'SELECT id, nome, nome_completo, foto, motorista, operador, administrador, ativo
             FROM usuarios WHERE id = ? LIMIT 1'
        );
        $busca->execute([$id]);
        $pessoa = $busca->fetch();
        if (!$pessoa || (int) $pessoa['ativo'] !== 1 || (int) $pessoa['administrador'] === 1) {
            return null;
        }
        if ((int) $pessoa['motorista'] !== 1 && (int) $pessoa['operador'] !== 1) {
            return null;
        }
        if (Auth::administradorFixo((string) $pessoa['nome'])) {
            return null;
        }

        return $pessoa;
    }

    public static function funcao(array $pessoa): string
    {
        if ((int) ($pessoa['administrador'] ?? 0) === 1) {
            return 'admin';
        }
        if ((int) ($pessoa['motorista'] ?? 0) === 1 && (int) ($pessoa['operador'] ?? 0) === 1) {
            return 'ambos';
        }
        if ((int) ($pessoa['motorista'] ?? 0) === 1) {
            return 'motorista';
        }

        return 'operador';
    }

    public static function rotulo(array $pessoa): string
    {
        return match (self::funcao($pessoa)) {
            'admin' => 'Administrativo',
            'ambos' => 'Motorista e operador',
            'motorista' => 'Motorista',
            default => 'Operador',
        };
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
            $papel = 'admin';
        } else {
            $funcao = (string) ($post['funcao'] ?? '');
            if (!in_array($funcao, ['motorista', 'operador', 'ambos'], true)) {
                throw new InvalidArgumentException('Informe a função.');
            }
            $motorista = $funcao === 'motorista' || $funcao === 'ambos' ? 1 : 0;
            $operador = $funcao === 'operador' || $funcao === 'ambos' ? 1 : 0;
            $papel = $funcao;
        }

        $ativo = $fixo ? 1 : (isset($post['ativo']) ? 1 : 0);
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
                $idSalvo = self::inserir($nome, $completo, $senha, $papel, $motorista, $operador, $ativo);
            } else {
                self::atualizar($id, (string) $atual['nome'], $nome, $completo, $senha, $papel, $motorista, $operador, $ativo, $admin);
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
        $comando = Db::pdo()->prepare('UPDATE usuarios SET ativo = ? WHERE id = ?');
        $comando->execute([(int) $atual['ativo'] === 1 ? 0 : 1, $id]);
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

    private static function inserir(string $nome, string $completo, string $senha, string $papel, int $motorista, int $operador, int $ativo): int
    {
        $comando = Db::pdo()->prepare(
            'INSERT INTO usuarios (nome, nome_completo, senha_hash, administrador, papel, motorista, operador, ativo)
             VALUES (?, ?, ?, 0, ?, ?, ?, ?)'
        );
        try {
            $comando->execute([$nome, $completo, password_hash($senha, PASSWORD_DEFAULT), $papel, $motorista, $operador, $ativo]);
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
        int $ativo,
        bool $admin
    ): void {
        $sql = 'UPDATE usuarios SET nome = ?, nome_completo = ?, papel = ?, motorista = ?, operador = ?, ativo = ?';
        $params = [$nome, $completo, $papel, $motorista, $operador, $ativo];
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
     * viagens, cargas, horímetro, turnos, manutenções, a máquina que a pessoa está usando agora.
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
        ];
        foreach ($colunas as $tabela => $lista) {
            foreach ($lista as $coluna) {
                Db::pdo()->prepare(sprintf('UPDATE %s SET %s = ? WHERE %s = ?', $tabela, $coluna, $coluna))->execute([$novo, $antigo]);
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
