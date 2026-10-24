<?php

declare(strict_types=1);

final class Db
{
    /**
     * Versão das mudanças que só este arquivo sabe fazer (as funções garantir*()).
     * TROQUE este valor sempre que criar ou mudar uma garantir*(): é ele que faz a atualização rodar de novo.
     */
    private const MIGRACOES = '2026-10-finpro-documento';

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

        // A marca fica no próprio banco (tabela schema_versao), e não num arquivo do servidor: banco novo, zerado
        // ou trocado não tem a marca e é montado de novo, mesmo que a pasta storage tenha sobrado de antes.
        // A marca leva também a versão das mudanças deste arquivo. Na publicação o schema.sql sobe antes do Db.php,
        // e o código antigo (inclusive o que ainda está no cache do PHP por alguns segundos depois do envio)
        // grava a marca da versão dele, e não a do Db.php novo: assim o código novo sempre roda as próprias mudanças.
        $hash = hash('sha256', $sql . self::MIGRACOES);
        if (self::marcaGravada($pdo) === $hash) {
            return;
        }

        // Um pedido de cada vez: dois pedidos juntos tentariam criar a mesma coluna.
        $trava = $pdo->query('SELECT GET_LOCK(CONCAT(DATABASE(), \':migrar\'), 30)')->fetchColumn();
        try {
            if (self::marcaGravada($pdo) === $hash) {
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
            self::garantirEscavadeira($pdo);
            self::garantirNotaFiscal($pdo);
            self::garantirFracionado($pdo);
            self::garantirFicha($pdo);
            self::garantirRetro($pdo);
            self::garantirEstrada($pdo);
            self::garantirHorimetro($pdo);
            self::garantirToneladasDaViagem($pdo);
            self::garantirMaquinaOk($pdo);
            self::garantirRevisao($pdo);
            self::garantirEstoque($pdo);
            self::garantirBritadorEPagamento($pdo);
            self::garantirFinPro($pdo);
            // A planilha do estoque entra uma vez, só num estoque ainda vazio.
            Estoque::importarPlanilha($pdo);

            $pdo->prepare('REPLACE INTO schema_versao (id, hash, atualizado_em) VALUES (1, ?, NOW())')->execute([$hash]);
        } finally {
            if ((int) $trava === 1) {
                $pdo->query('SELECT RELEASE_LOCK(CONCAT(DATABASE(), \':migrar\'))');
            }
        }
    }

    /** A versão do banco gravada na última atualização; null se o banco ainda não tem a tabela (banco novo ou antigo). */
    private static function marcaGravada(PDO $pdo): ?string
    {
        try {
            $hash = $pdo->query('SELECT hash FROM schema_versao WHERE id = 1')->fetchColumn();
        } catch (PDOException) {
            return null;
        }

        return is_string($hash) ? $hash : null;
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

    /**
     * O operador de escavadeira hidráulica (carga na lavra) passou a ser separado do operador de pá carregadeira (pátio).
     * Quem já era operador fica com as duas funções, para ninguém perder acesso.
     */
    private static function garantirEscavadeira(PDO $pdo): void
    {
        if (in_array('escavadeira', self::colunasDe($pdo, 'usuarios'), true)) {
            return;
        }
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN escavadeira TINYINT(1) NOT NULL DEFAULT 0 AFTER operador');
        $pdo->exec('UPDATE usuarios SET escavadeira = 1 WHERE operador = 1');
    }

    /** A nota fiscal lida no lançamento: a hora em que foi emitida e a chave de acesso (para não lançar a mesma nota duas vezes). */
    private static function garantirNotaFiscal(PDO $pdo): void
    {
        $colunas = self::colunasDe($pdo, 'fila_carregamentos');
        if (!in_array('nf_emitida_em', $colunas, true)) {
            $pdo->exec('ALTER TABLE fila_carregamentos ADD COLUMN nf_emitida_em DATETIME NULL AFTER nf');
        }
        if (!in_array('nf_chave', $colunas, true)) {
            $pdo->exec('ALTER TABLE fila_carregamentos ADD COLUMN nf_chave CHAR(44) NULL AFTER nf_emitida_em');
        }
        $indices = [];
        foreach ($pdo->query('SHOW INDEX FROM fila_carregamentos') as $indice) {
            $indices[(string) ($indice['Key_name'] ?? $indice['key_name'])] = true;
        }
        if (!isset($indices['idx_fila_nf_chave'])) {
            $pdo->exec('ALTER TABLE fila_carregamentos ADD KEY idx_fila_nf_chave (nf_chave)');
        }
    }

    /**
     * Carregamento fracionado: uma nota para o total, buscada em várias viagens. Cada viagem é uma linha da fila,
     * ligada à primeira (ordem_principal_id), com a quantidade que o operador informou ao finalizar.
     * A viagem seguinte espera o cliente voltar na situação "saldo". A nota guarda também o valor (nf_valor).
     */
    private static function garantirFracionado(PDO $pdo): void
    {
        $colunas = self::colunasDe($pdo, 'fila_carregamentos');
        $novas = [
            'nf_valor' => 'DECIMAL(12,2) NULL AFTER nf_chave',
            'fracionado' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER unidade',
            'ordem_principal_id' => 'INT UNSIGNED NULL AFTER fracionado',
            'viagem' => 'SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER ordem_principal_id',
            'quantidade_carregada' => 'DECIMAL(12,2) NULL AFTER viagem',
        ];
        foreach ($novas as $coluna => $tipo) {
            if (!in_array($coluna, $colunas, true)) {
                $pdo->exec('ALTER TABLE fila_carregamentos ADD COLUMN ' . $coluna . ' ' . $tipo);
            }
        }
        $indices = [];
        foreach ($pdo->query('SHOW INDEX FROM fila_carregamentos') as $indice) {
            $indices[(string) ($indice['Key_name'] ?? $indice['key_name'])] = true;
        }
        if (!isset($indices['idx_fila_principal'])) {
            $pdo->exec('ALTER TABLE fila_carregamentos ADD KEY idx_fila_principal (ordem_principal_id)');
        }
        $status = $pdo->query("SHOW COLUMNS FROM fila_carregamentos LIKE 'status'")->fetch();
        if ($status && !str_contains((string) ($status['Type'] ?? $status['type']), "'saldo'")) {
            $pdo->exec(
                "ALTER TABLE fila_carregamentos MODIFY COLUMN status ENUM(
                    'aguardando_autorizacao', 'liberado', 'carregando', 'aguardando_conferencia', 'concluido', 'cancelado', 'saldo'
                ) NOT NULL DEFAULT 'aguardando_autorizacao'"
            );
        }
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

    /**
     * A viagem guarda as toneladas do caminhão na hora em que saiu carregado.
     * Mudar a capacidade no cadastro depois não altera a produção dos dias anteriores.
     */
    private static function garantirToneladasDaViagem(PDO $pdo): void
    {
        if (in_array('capacidade_t', self::colunasDe($pdo, 'ciclos_lavra'), true)) {
            return;
        }
        $pdo->exec('ALTER TABLE ciclos_lavra ADD COLUMN capacidade_t DECIMAL(8,2) NULL AFTER operador_carga');
        // As viagens antigas ficam com a capacidade de hoje, que é a mesma conta que o sistema já fazia.
        $pdo->exec(
            'UPDATE ciclos_lavra c JOIN caminhoes k ON k.id = c.caminhao_id
             SET c.capacidade_t = k.capacidade_t
             WHERE c.fim_carga IS NOT NULL AND c.capacidade_t IS NULL'
        );
    }

    /** O turno guarda como o operador entregou a máquina: 1 tudo certo, 0 relatou problema, vazio quando ninguém perguntou. */
    private static function garantirMaquinaOk(PDO $pdo): void
    {
        if (!in_array('maquina_ok', self::colunasDe($pdo, 'horimetro_turnos'), true)) {
            $pdo->exec('ALTER TABLE horimetro_turnos ADD COLUMN maquina_ok TINYINT(1) NULL AFTER horas');
        }
    }

    /**
     * Colunas da revisão de outubro de 2026:
     * - ciclos_lavra.garagem: a viagem terminou no britador indo para a garagem (fora da média de volta e de ciclo).
     * - fila_carregamentos.nf_emitida_data: o dia em que a nota foi emitida, também quando o PDF não traz a hora
     *   (é o dia em que a nota conta no vendido).
     */
    private static function garantirRevisao(PDO $pdo): void
    {
        if (!in_array('garagem', self::colunasDe($pdo, 'ciclos_lavra'), true)) {
            $pdo->exec('ALTER TABLE ciclos_lavra ADD COLUMN garagem TINYINT(1) NOT NULL DEFAULT 0 AFTER encerramento_ciente_por');
        }
        if (!in_array('nf_emitida_data', self::colunasDe($pdo, 'fila_carregamentos'), true)) {
            $pdo->exec('ALTER TABLE fila_carregamentos ADD COLUMN nf_emitida_data DATE NULL AFTER nf_emitida_em');
            // atualizado_em = atualizado_em: o preenchimento não conta como mudança (notas canceladas antigas não viram "recentes").
            $pdo->exec('UPDATE fila_carregamentos SET nf_emitida_data = DATE(nf_emitida_em), atualizado_em = atualizado_em WHERE nf_emitida_em IS NOT NULL');
        }
    }

    /** Estoque das pilhas: o material marcado para o estoque e o tamanho da concha de cada pá. */
    private static function garantirEstoque(PDO $pdo): void
    {
        if (!in_array('estoque', self::colunasDe($pdo, 'materiais'), true)) {
            $pdo->exec('ALTER TABLE materiais ADD COLUMN estoque TINYINT(1) NOT NULL DEFAULT 0 AFTER observacoes');
        }
        if (!in_array('concha_m3', self::colunasDe($pdo, 'pas_carregadeiras'), true)) {
            $pdo->exec('ALTER TABLE pas_carregadeiras ADD COLUMN concha_m3 DECIMAL(5,2) NULL AFTER ano');
        }
    }

    /**
     * Forma de pagamento da nota (pix, dinheiro, cartao, cheque, boleto, prazo); vazia nas notas antigas.
     * Função "Operador do britador secundário" no cadastro de pessoas.
     */
    private static function garantirBritadorEPagamento(PDO $pdo): void
    {
        if (!in_array('nf_pagamento', self::colunasDe($pdo, 'fila_carregamentos'), true)) {
            $pdo->exec('ALTER TABLE fila_carregamentos ADD COLUMN nf_pagamento VARCHAR(12) NULL AFTER nf_valor');
        }
        if (!in_array('britador', self::colunasDe($pdo, 'usuarios'), true)) {
            $pdo->exec('ALTER TABLE usuarios ADD COLUMN britador TINYINT(1) NOT NULL DEFAULT 0 AFTER escavadeira');
        }
    }

    /**
     * Integração com o Fin Pro: o PDF da nota guardado no lançamento (nf_arquivo), o CNPJ/CPF do cliente
     * (nf_documento, só os números) e espaço para a forma "transferencia", que não cabia nos 12 caracteres.
     */
    private static function garantirFinPro(PDO $pdo): void
    {
        if (!in_array('nf_arquivo', self::colunasDe($pdo, 'fila_carregamentos'), true)) {
            $pdo->exec('ALTER TABLE fila_carregamentos ADD COLUMN nf_arquivo VARCHAR(80) NULL AFTER nf_pagamento');
        }
        if (!in_array('nf_documento', self::colunasDe($pdo, 'fila_carregamentos'), true)) {
            $pdo->exec('ALTER TABLE fila_carregamentos ADD COLUMN nf_documento VARCHAR(14) NULL AFTER nf_arquivo');
        }
        $forma = $pdo->query("SHOW COLUMNS FROM fila_carregamentos LIKE 'nf_pagamento'")->fetch();
        if ($forma && strtolower((string) ($forma['Type'] ?? $forma['type'])) === 'varchar(12)') {
            $pdo->exec('ALTER TABLE fila_carregamentos MODIFY COLUMN nf_pagamento VARCHAR(16) NULL');
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
