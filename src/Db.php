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
        foreach (['pas_carregadeiras', 'caminhoes'] as $tabela) {
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
        }
    }
}
