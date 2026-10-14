<?php

declare(strict_types=1);

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        self::$pdo = self::conectar();
        self::migrar(self::$pdo);

        return self::$pdo;
    }

    /**
     * Executa a ação com uma trava do MySQL. Serve para checagens do tipo "a máquina está livre?"
     * seguidas de gravação, que dois aparelhos podem fazer no mesmo instante.
     */
    public static function comTrava(string $nome, callable $acao): mixed
    {
        $pdo = self::pdo();
        $chave = $pdo->query('SELECT DATABASE()')->fetchColumn() . ':' . $nome;
        $trava = $pdo->prepare('SELECT GET_LOCK(?, 10)');
        $trava->execute([$chave]);
        if ((int) $trava->fetchColumn() !== 1) {
            throw new InvalidArgumentException('O sistema está ocupado. Tente de novo.');
        }
        try {
            return $acao();
        } finally {
            $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$chave]);
        }
    }

    private static function conectar(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            env_str('DB_HOST', '127.0.0.1'),
            env_str('DB_PORT', '3306'),
            env_str('DB_NAME', 'operacoes_prospera')
        );
        $tentativas = env_str('DB_WAIT') === '1' ? 30 : 1;
        $ultimo = null;

        for ($i = 0; $i < $tentativas; $i++) {
            try {
                $pdo = new PDO($dsn, env_str('DB_USER', 'operacoes'), env_str('DB_PASSWORD'), [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '-04:00'");

                return $pdo;
            } catch (PDOException $e) {
                $ultimo = $e;
                if ($i < $tentativas - 1) {
                    sleep(1);
                }
            }
        }

        throw $ultimo ?? new PDOException('Não foi possível conectar ao MySQL.');
    }

    private static function migrar(PDO $pdo): void
    {
        $sql = file_get_contents(OPERACOES_ROOT . '/database/schema.sql');
        if ($sql === false) {
            throw new RuntimeException('Schema do banco não encontrado.');
        }

        $marca = OPERACOES_ROOT . '/storage/.schema-ok';
        $hash = hash('sha256', $sql);
        if (is_file($marca) && trim((string) file_get_contents($marca)) === $hash) {
            return;
        }

        $limpo = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $comandos = array_filter(array_map('trim', explode(';', $limpo)));
        foreach ($comandos as $comando) {
            $pdo->exec($comando);
        }

        self::retirarCaminhaoDaFila($pdo);
        self::garantirPapel($pdo);
        self::garantirPessoa($pdo);
        self::garantirFicha($pdo);
        self::garantirRetro($pdo);
        self::garantirEstrada($pdo);
        self::garantirHorimetro($pdo);

        if (!is_dir(dirname($marca))) {
            mkdir(dirname($marca), 0775, true);
        }
        file_put_contents($marca, $hash);
    }

    private static function retirarCaminhaoDaFila(PDO $pdo): void
    {
        $fila = $pdo->query("SHOW TABLES LIKE 'fila_carregamentos'")->fetch();
        if ($fila) {
            $restricoes = [];
            $busca = $pdo->query(
                "SELECT CONSTRAINT_NAME AS nome
                 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'fila_carregamentos'
                   AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
            );
            foreach ($busca as $linha) {
                $restricoes[] = strtolower((string) $linha['nome']);
            }
            foreach (['fk_fila_caminhao', 'fk_fila_motorista'] as $nome) {
                if (in_array($nome, $restricoes, true)) {
                    $pdo->exec('ALTER TABLE fila_carregamentos DROP FOREIGN KEY ' . $nome);
                }
            }

            $colunas = [];
            foreach ($pdo->query('SHOW COLUMNS FROM fila_carregamentos') as $coluna) {
                $colunas[] = (string) ($coluna['Field'] ?? $coluna['field']);
            }
            if (in_array('motorista_id', $colunas, true)) {
                $pdo->exec('ALTER TABLE fila_carregamentos DROP COLUMN motorista_id');
            }
            if (in_array('caminhao_id', $colunas, true)) {
                $pdo->exec('ALTER TABLE fila_carregamentos DROP COLUMN caminhao_id');
            }
        }
    }

    private static function garantirPapel(PDO $pdo): void
    {
        $tabela = $pdo->query("SHOW TABLES LIKE 'usuarios'")->fetch();
        if (!$tabela) {
            return;
        }

        $colunas = [];
        foreach ($pdo->query('SHOW COLUMNS FROM usuarios') as $coluna) {
            $colunas[] = (string) ($coluna['Field'] ?? $coluna['field']);
        }
        if (in_array('papel', $colunas, true)) {
            return;
        }

        $pdo->exec("ALTER TABLE usuarios ADD COLUMN papel VARCHAR(20) NOT NULL DEFAULT 'operador' AFTER administrador");
        $pdo->exec("UPDATE usuarios SET papel = 'admin' WHERE administrador = 1");
    }

    private static function garantirPessoa(PDO $pdo): void
    {
        $tabela = $pdo->query("SHOW TABLES LIKE 'usuarios'")->fetch();
        if (!$tabela) {
            return;
        }

        $colunas = [];
        foreach ($pdo->query('SHOW COLUMNS FROM usuarios') as $coluna) {
            $colunas[] = (string) ($coluna['Field'] ?? $coluna['field']);
        }
        $novas = [
            'motorista' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'operador' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'foto' => 'VARCHAR(80) NULL',
            'ativo' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'nome_completo' => 'VARCHAR(120) NULL',
        ];
        foreach ($novas as $nome => $tipo) {
            if (!in_array($nome, $colunas, true)) {
                $pdo->exec('ALTER TABLE usuarios ADD COLUMN ' . $nome . ' ' . $tipo);
            }
        }

        $pdo->exec("UPDATE usuarios SET nome_completo = nome WHERE nome_completo IS NULL OR nome_completo = ''");
        self::separarUsuario($pdo);

        $pdo->exec("UPDATE usuarios SET motorista = 1 WHERE papel = 'motorista' AND motorista = 0 AND operador = 0");
        $pdo->exec("UPDATE usuarios SET operador = 1 WHERE papel = 'operador' AND motorista = 0 AND operador = 0");
        $pdo->exec("UPDATE usuarios SET motorista = 1, operador = 1 WHERE papel = 'ambos' AND motorista = 0 AND operador = 0");
        $pdo->exec("UPDATE usuarios SET administrador = 1, papel = 'admin' WHERE papel = 'admin' AND administrador = 0");
    }

    private static function separarUsuario(PDO $pdo): void
    {
        $temEspaco = $pdo->query("SELECT id FROM usuarios WHERE administrador = 0 AND nome LIKE '% %' LIMIT 1")->fetch();
        if (!$temEspaco) {
            return;
        }

        $linhas = $pdo->query('SELECT id, nome, nome_completo FROM usuarios WHERE administrador = 0')->fetchAll();
        $ocupados = [];
        foreach ($linhas as $linha) {
            $ocupados[mb_strtolower((string) $linha['nome'], 'UTF-8')] = (int) $linha['id'];
        }
        $atualizar = $pdo->prepare('UPDATE usuarios SET nome = ?, nome_completo = ? WHERE id = ? AND nome = ?');
        $ciclo = $pdo->prepare('UPDATE ciclos_lavra SET operador = ? WHERE operador = ?');
        $fila = $pdo->prepare('UPDATE fila_carregamentos SET operador = ? WHERE operador = ?');
        foreach ($linhas as $linha) {
            $nome = (string) $linha['nome'];
            if (!str_contains($nome, ' ')) {
                continue;
            }
            $partes = preg_split('/\s+/u', $nome) ?: [];
            $usuario = (string) ($partes[0] ?? '');
            if ($usuario === '' || !preg_match('/^[\p{L}][\p{L}\p{N}]{0,39}$/u', $usuario)) {
                continue;
            }
            $chave = mb_strtolower($usuario, 'UTF-8');
            if (isset($ocupados[$chave]) && $ocupados[$chave] !== (int) $linha['id']) {
                continue;
            }
            $completo = trim((string) ($linha['nome_completo'] ?? ''));
            if ($completo === '') {
                $completo = $nome;
            }
            $pdo->beginTransaction();
            try {
                $ciclo->execute([$usuario, $nome]);
                $fila->execute([$usuario, $nome]);
                $atualizar->execute([$usuario, $completo, (int) $linha['id'], $nome]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
            unset($ocupados[mb_strtolower($nome, 'UTF-8')]);
            $ocupados[$chave] = (int) $linha['id'];
        }
    }

    private static function garantirFicha(PDO $pdo): void
    {
        foreach (['pas_carregadeiras', 'caminhoes', 'retroescavadeiras'] as $tabela) {
            $existe = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($tabela))->fetch();
            if (!$existe) {
                continue;
            }
            $colunas = [];
            foreach ($pdo->query('SHOW COLUMNS FROM ' . $tabela) as $coluna) {
                $colunas[] = (string) ($coluna['Field'] ?? $coluna['field']);
            }
            $novas = [
                'marca' => 'VARCHAR(80) NULL',
                'modelo' => 'VARCHAR(80) NULL',
                'ano' => 'SMALLINT UNSIGNED NULL',
                'foto' => 'VARCHAR(80) NULL',
            ];
            foreach ($novas as $nome => $tipo) {
                if (!in_array($nome, $colunas, true)) {
                    $pdo->exec('ALTER TABLE ' . $tabela . ' ADD COLUMN ' . $nome . ' ' . $tipo);
                }
            }
            if ($tabela === 'caminhoes' && !in_array('capacidade_t', $colunas, true)) {
                $pdo->exec('ALTER TABLE caminhoes ADD COLUMN capacidade_t DECIMAL(8,2) NULL AFTER ano');
            }
        }
    }

    private static function garantirRetro(PDO $pdo): void
    {
        $retro = $pdo->query("SHOW TABLES LIKE 'retroescavadeiras'")->fetch();
        $ciclo = $pdo->query("SHOW TABLES LIKE 'ciclos_lavra'")->fetch();
        if (!$retro || !$ciclo) {
            return;
        }

        $colunas = [];
        foreach ($pdo->query('SHOW COLUMNS FROM ciclos_lavra') as $coluna) {
            $colunas[] = (string) ($coluna['Field'] ?? $coluna['field']);
        }
        if (!in_array('retroescavadeira_id', $colunas, true)) {
            $pdo->exec('ALTER TABLE ciclos_lavra ADD COLUMN retroescavadeira_id INT UNSIGNED NULL AFTER operador');
        }
        if (!in_array('operador_carga', $colunas, true)) {
            $pdo->exec('ALTER TABLE ciclos_lavra ADD COLUMN operador_carga VARCHAR(120) NULL AFTER retroescavadeira_id');
        }
        if (!in_array('encerramento', $colunas, true)) {
            $pdo->exec('ALTER TABLE ciclos_lavra ADD COLUMN encerramento VARCHAR(160) NULL AFTER operador_carga');
        }
        if (!in_array('encerramento_ciente_em', $colunas, true)) {
            $pdo->exec('ALTER TABLE ciclos_lavra ADD COLUMN encerramento_ciente_em DATETIME NULL AFTER encerramento');
        }
        if (!in_array('encerramento_ciente_por', $colunas, true)) {
            $pdo->exec('ALTER TABLE ciclos_lavra ADD COLUMN encerramento_ciente_por VARCHAR(120) NULL AFTER encerramento_ciente_em');
        }
        if (!in_array('chegada_britador', $colunas, true)) {
            $pdo->exec('ALTER TABLE ciclos_lavra ADD COLUMN chegada_britador DATETIME NULL AFTER fim_carga');
        }
        $tipoStatus = '';
        foreach ($pdo->query('SHOW COLUMNS FROM ciclos_lavra') as $coluna) {
            if ((string) ($coluna['Field'] ?? $coluna['field'] ?? '') === 'status') {
                $tipoStatus = (string) ($coluna['Type'] ?? $coluna['type'] ?? '');
            }
        }
        if (!str_contains($tipoStatus, 'no_britador')) {
            $pdo->exec(
                "ALTER TABLE ciclos_lavra MODIFY COLUMN status
                 ENUM('na_fila', 'carregando', 'em_viagem', 'no_britador', 'retornando', 'concluido', 'cancelado')
                 NOT NULL DEFAULT 'na_fila'"
            );
        }

        $restricoes = [];
        $busca = $pdo->query(
            "SELECT CONSTRAINT_NAME AS nome
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'ciclos_lavra'
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
        );
        foreach ($busca as $linha) {
            $restricoes[] = strtolower((string) $linha['nome']);
        }
        if (!in_array('fk_ciclo_retro', $restricoes, true)) {
            $pdo->exec(
                'ALTER TABLE ciclos_lavra
                 ADD CONSTRAINT fk_ciclo_retro
                 FOREIGN KEY (retroescavadeira_id) REFERENCES retroescavadeiras (id)'
            );
        }

        $fila = $pdo->query("SHOW TABLES LIKE 'fila_carregamentos'")->fetch();
        if (!$fila) {
            return;
        }
        $colunasFila = [];
        foreach ($pdo->query('SHOW COLUMNS FROM fila_carregamentos') as $coluna) {
            $colunasFila[] = (string) ($coluna['Field'] ?? $coluna['field']);
        }
        if (!in_array('retroescavadeira_id', $colunasFila, true)) {
            $pdo->exec('ALTER TABLE fila_carregamentos ADD COLUMN retroescavadeira_id INT UNSIGNED NULL AFTER pa_carregadeira_id');
        }
        $restricoesFila = [];
        $buscaFila = $pdo->query(
            "SELECT CONSTRAINT_NAME AS nome
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'fila_carregamentos'
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
        );
        foreach ($buscaFila as $linha) {
            $restricoesFila[] = strtolower((string) $linha['nome']);
        }
        if (!in_array('fk_fila_retro', $restricoesFila, true)) {
            $pdo->exec(
                'ALTER TABLE fila_carregamentos
                 ADD CONSTRAINT fk_fila_retro
                 FOREIGN KEY (retroescavadeira_id) REFERENCES retroescavadeiras (id)'
            );
        }
    }

    /** @return list<string> */
    private static function colunasDe(PDO $pdo, string $tabela): array
    {
        $colunas = [];
        foreach ($pdo->query('SHOW COLUMNS FROM ' . $tabela) as $coluna) {
            $colunas[] = (string) ($coluna['Field'] ?? $coluna['field']);
        }

        return $colunas;
    }

    /** Leitura de horímetro errada é anulada pelo administrativo, com motivo, sem apagar o registro. */
    private static function garantirHorimetro(PDO $pdo): void
    {
        $colunas = self::colunasDe($pdo, 'horimetros');
        $novas = [
            'anulado_em' => 'DATETIME NULL',
            'anulado_por' => 'VARCHAR(40) NULL',
            'anulado_motivo' => 'VARCHAR(200) NULL',
        ];
        foreach ($novas as $nome => $tipo) {
            if (!in_array($nome, $colunas, true)) {
                $pdo->exec('ALTER TABLE horimetros ADD COLUMN ' . $nome . ' ' . $tipo);
            }
        }
    }

    private static function garantirEstrada(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS estrada (
                id TINYINT UNSIGNED NOT NULL,
                ida_km DECIMAL(6,2) NULL,
                retorno_km DECIMAL(6,2) NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
