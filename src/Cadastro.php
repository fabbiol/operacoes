<?php

declare(strict_types=1);

final class Cadastro
{
    public static function tipos(): array
    {
        return [
            'materiais' => [
                'tabela' => 'materiais',
                'campo' => 'material',
                'titulo' => 'Materiais',
                'rotulo' => 'Material',
                'singular' => 'material',
            ],
            'pas' => [
                'tabela' => 'pas_carregadeiras',
                'campo' => 'maquina',
                'titulo' => 'Pás carregadeiras',
                'rotulo' => 'Máquina',
                'singular' => 'pá carregadeira',
                'artigo' => 'Nova',
                'ficha' => true,
            ],
            'retros' => [
                'tabela' => 'retroescavadeiras',
                'campo' => 'maquina',
                'titulo' => 'Retroescavadeiras',
                'rotulo' => 'Máquina',
                'singular' => 'retroescavadeira',
                'artigo' => 'Nova',
                'vazio' => 'Nenhuma retroescavadeira cadastrada.',
                'ficha' => true,
            ],
            'caminhoes' => [
                'tabela' => 'caminhoes',
                'campo' => 'placa',
                'titulo' => 'Caminhões',
                'rotulo' => 'Placa',
                'singular' => 'caminhão',
                'ficha' => true,
                'capacidade' => true,
            ],
        ];
    }

    public static function tipo(string $chave): array
    {
        $tipos = self::tipos();
        if (!isset($tipos[$chave])) {
            throw new InvalidArgumentException('Cadastro não encontrado.');
        }

        return $tipos[$chave] + ['chave' => $chave];
    }

    public static function listar(string $chave): array
    {
        $tipo = self::tipo($chave);
        $sql = sprintf(
            'SELECT ' . self::colunas($tipo) . ' FROM %s ORDER BY ativo DESC, nome ASC',
            $tipo['campo'],
            $tipo['tabela']
        );

        return Db::pdo()->query($sql)->fetchAll();
    }

    public static function buscar(string $chave, int $id): ?array
    {
        $tipo = self::tipo($chave);
        $sql = sprintf(
            'SELECT ' . self::colunas($tipo) . ' FROM %s WHERE id = ? LIMIT 1',
            $tipo['campo'],
            $tipo['tabela']
        );
        $busca = Db::pdo()->prepare($sql);
        $busca->execute([$id]);
        $linha = $busca->fetch();

        return $linha ?: null;
    }

    public static function opcoes(string $chave, ?int $incluirId = null): array
    {
        $tipo = self::tipo($chave);
        $extra = !empty($tipo['ficha']) ? ', marca, modelo, ano, foto' : '';
        if (!empty($tipo['capacidade'])) {
            $extra .= ', capacidade_t';
        }
        $sql = sprintf(
            'SELECT id, %s AS nome, ativo' . $extra . ' FROM %s WHERE ativo = 1 OR id = ? ORDER BY nome ASC',
            $tipo['campo'],
            $tipo['tabela']
        );
        $busca = Db::pdo()->prepare($sql);
        $busca->execute([$incluirId ?? 0]);

        return $busca->fetchAll();
    }

    public static function salvar(string $chave, ?int $id, array $post): int
    {
        $tipo = self::tipo($chave);
        $nome = limpar_texto((string) ($post['nome'] ?? ''), 120, true, $tipo['rotulo']);
        if ($chave === 'caminhoes') {
            $nome = mb_strtoupper((string) $nome, 'UTF-8');
            $nome = str_replace(' ', '', $nome);
            if (!preg_match('/^[0-9A-Z-]{3,12}$/', $nome)) {
                throw new InvalidArgumentException('Use a placa do caminhão, com letras e números.');
            }
        }
        $observacoes = limpar_longo((string) ($post['observacoes'] ?? ''), 4000);
        $ativo = isset($post['ativo']) ? 1 : 0;
        $ficha = !empty($tipo['ficha']) ? self::lerFicha($post, !empty($tipo['capacidade'])) : null;
        $pendente = !empty($tipo['ficha']) ? self::prepararFoto() : null;

        try {
            if ($id === null) {
                $idSalvo = self::inserir($tipo, $nome, $observacoes, $ativo, $ficha);
                if ($pendente !== null) {
                    try {
                        self::gravarFoto($chave, $idSalvo, $pendente);
                        $pendente = null;
                    } catch (Throwable $e) {
                        self::excluir($tipo, $idSalvo);
                        throw $e;
                    }
                }

                return $idSalvo;
            }

            $atual = self::buscar($chave, $id);
            if ($atual === null) {
                throw new InvalidArgumentException('Cadastro não encontrado.');
            }
            // Desmarcar "Ativo" na ficha tem as mesmas regras do botão Desativar da lista.
            if ((int) $atual['ativo'] === 1 && $ativo === 0) {
                self::exigirPodeDesativar($chave, $tipo, $id);
            }
            $pdo = Db::pdo();
            $pdo->beginTransaction();
            try {
                if ($ficha !== null && array_key_exists('capacidade_t', $ficha)) {
                    self::congelarToneladas($id, $atual['capacidade_t'] ?? null, $ficha['capacidade_t']);
                }
                self::atualizar($tipo, $id, $nome, $observacoes, $ativo, $ficha);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            if ($pendente !== null) {
                self::gravarFoto($chave, $id, $pendente);
                $pendente = null;
            } elseif ($ficha !== null && isset($post['remover_foto'])) {
                self::apagarFoto($chave, $id);
            }

            return $id;
        } finally {
            if ($pendente !== null && is_file($pendente['tmp'])) {
                unlink($pendente['tmp']);
            }
        }
    }

    public static function arquivoFoto(string $chave, int $id): ?array
    {
        $item = self::buscar($chave, $id);
        if ($item === null || empty($item['foto'])) {
            return null;
        }
        $relativo = str_replace('\\', '/', (string) $item['foto']);
        if (!preg_match('#^(pas|caminhoes|retros)/(\d+)\.(jpg|png|webp)$#', $relativo, $partes)) {
            return null;
        }
        if ($partes[1] !== $chave || (int) $partes[2] !== $id) {
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
            'tipo' => $tipos[$partes[3]],
        ];
    }

    public static function alternarAtivo(string $chave, int $id): void
    {
        $tipo = self::tipo($chave);
        $atual = self::buscar($chave, $id);
        if ($atual === null) {
            throw new InvalidArgumentException('Cadastro não encontrado.');
        }
        if ((int) $atual['ativo'] === 1) {
            self::exigirPodeDesativar($chave, $tipo, $id);
        }
        $sql = sprintf('UPDATE %s SET ativo = ? WHERE id = ?', $tipo['tabela']);
        $comando = Db::pdo()->prepare($sql);
        $comando->execute([(int) $atual['ativo'] === 1 ? 0 : 1, $id]);
    }

    /** Máquina em trabalho ou com alguém não pode ser desativada: o turno de quem está com ela se perderia. */
    private static function exigirPodeDesativar(string $chave, array $tipo, int $id): void
    {
        if (self::emTrabalho($chave, $id)) {
            throw new InvalidArgumentException('Essa máquina está em trabalho agora. Encerre a viagem ou a carga antes de desativar.');
        }
        $posse = !empty($tipo['ficha']) ? Posse::daMaquina(Horimetro::tipoDaChave($chave), $id) : null;
        if ($posse !== null) {
            throw new InvalidArgumentException('Essa máquina está com ' . $posse['usuario'] . '. Libere a máquina antes de desativar.');
        }
    }

    /**
     * Antes de mudar a capacidade do caminhão, as viagens carregadas que ainda não têm as toneladas gravadas
     * (caminhão que estava sem capacidade) ficam com a capacidade com que já eram contadas.
     * Assim a correção vale só para as próximas viagens.
     */
    private static function congelarToneladas(int $caminhaoId, mixed $anterior, mixed $nova): void
    {
        $valor = $anterior !== null && $anterior !== '' ? $anterior : $nova;
        if ($valor === null || $valor === '') {
            return;
        }
        Db::pdo()->prepare(
            'UPDATE ciclos_lavra SET capacidade_t = ?
             WHERE caminhao_id = ? AND fim_carga IS NOT NULL AND capacidade_t IS NULL'
        )->execute([$valor, $caminhaoId]);
    }

    public static function emTrabalho(string $chave, int $id): bool
    {
        $sql = match ($chave) {
            'caminhoes' => "SELECT 1 FROM ciclos_lavra WHERE caminhao_id = ? AND status IN ('na_fila', 'carregando', 'em_viagem', 'no_britador', 'retornando') LIMIT 1",
            'pas' => "SELECT 1 FROM fila_carregamentos WHERE pa_carregadeira_id = ? AND status = 'carregando' LIMIT 1",
            'retros' => "SELECT 1 FROM ciclos_lavra WHERE retroescavadeira_id = ? AND status = 'carregando'
                         UNION SELECT 1 FROM fila_carregamentos WHERE retroescavadeira_id = ? AND status = 'carregando' LIMIT 1",
            default => null,
        };
        if ($sql === null) {
            return false;
        }
        $busca = Db::pdo()->prepare($sql);
        $busca->execute($chave === 'retros' ? [$id, $id] : [$id]);

        return (bool) $busca->fetchColumn();
    }

    private static function colunas(array $tipo): string
    {
        $sql = 'id, %s AS nome, observacoes, ativo';
        if (!empty($tipo['ficha'])) {
            $sql .= ', marca, modelo, ano, foto';
        }
        if (!empty($tipo['capacidade'])) {
            $sql .= ', capacidade_t';
        }

        return $sql;
    }

    /** @return array{marca: ?string, modelo: ?string, ano: ?int, capacidade_t?: ?string} */
    private static function lerFicha(array $post, bool $capacidade = false): array
    {
        $marca = limpar_texto((string) ($post['marca'] ?? ''), 80, false, 'Marca');
        $modelo = limpar_texto((string) ($post['modelo'] ?? ''), 80, false, 'Modelo');
        $anoBruto = trim((string) ($post['ano'] ?? ''));
        $ano = null;
        if ($anoBruto !== '') {
            if (!preg_match('/^\d{4}$/', $anoBruto)) {
                throw new InvalidArgumentException('Informe o ano com 4 dígitos.');
            }
            $ano = (int) $anoBruto;
            $limite = (int) date('Y') + 1;
            if ($ano < 1970 || $ano > $limite) {
                throw new InvalidArgumentException('Informe um ano entre 1970 e ' . $limite . '.');
            }
        }

        $ficha = [
            'marca' => $marca,
            'modelo' => $modelo,
            'ano' => $ano,
        ];
        if ($capacidade) {
            $ficha['capacidade_t'] = self::lerCapacidade((string) ($post['capacidade'] ?? ''));
        }

        return $ficha;
    }

    private static function lerCapacidade(string $bruto): ?string
    {
        $bruto = trim(str_replace([' ', "\xc2\xa0"], '', $bruto));
        if ($bruto === '') {
            return null;
        }
        if (str_contains($bruto, ',') && str_contains($bruto, '.')) {
            $bruto = str_replace('.', '', $bruto);
        }
        $bruto = str_replace(',', '.', $bruto);
        if (!preg_match('/^\d+(\.\d+)?$/', $bruto)) {
            throw new InvalidArgumentException('A capacidade precisa ser um número, em toneladas.');
        }
        $numero = round((float) $bruto, 2);
        if ($numero <= 0 || $numero > 999.99) {
            throw new InvalidArgumentException('A capacidade precisa ficar entre 0,01 e 999,99 t.');
        }

        return number_format($numero, 2, '.', '');
    }

    /** @return array{tmp: string, ext: string}|null */
    private static function prepararFoto(): ?array
    {
        return Fotos::receber(Fotos::LADO_MAQUINA);
    }

    private static function inserir(array $tipo, string $nome, ?string $observacoes, int $ativo, ?array $ficha): int
    {
        if ($ficha === null) {
            $sql = sprintf(
                'INSERT INTO %s (%s, observacoes, ativo) VALUES (?, ?, ?)',
                $tipo['tabela'],
                $tipo['campo']
            );
            $params = [$nome, $observacoes, $ativo];
        } else {
            $campos = 'marca, modelo, ano';
            $marcas = '?, ?, ?';
            $params = [$nome, $observacoes, $ativo, $ficha['marca'], $ficha['modelo'], $ficha['ano']];
            if (array_key_exists('capacidade_t', $ficha)) {
                $campos .= ', capacidade_t';
                $marcas .= ', ?';
                $params[] = $ficha['capacidade_t'];
            }
            $sql = sprintf(
                'INSERT INTO %s (%s, observacoes, ativo, %s) VALUES (?, ?, ?, %s)',
                $tipo['tabela'],
                $tipo['campo'],
                $campos,
                $marcas
            );
        }
        $comando = Db::pdo()->prepare($sql);
        try {
            $comando->execute($params);
        } catch (PDOException $e) {
            self::traduzirDuplicado($e, $tipo['rotulo']);
        }

        return (int) Db::pdo()->lastInsertId();
    }

    private static function atualizar(array $tipo, int $id, string $nome, ?string $observacoes, int $ativo, ?array $ficha): void
    {
        if ($ficha === null) {
            $sql = sprintf(
                'UPDATE %s SET %s = ?, observacoes = ?, ativo = ? WHERE id = ?',
                $tipo['tabela'],
                $tipo['campo']
            );
            $params = [$nome, $observacoes, $ativo, $id];
        } else {
            $extra = 'marca = ?, modelo = ?, ano = ?';
            $params = [$nome, $observacoes, $ativo, $ficha['marca'], $ficha['modelo'], $ficha['ano']];
            if (array_key_exists('capacidade_t', $ficha)) {
                $extra .= ', capacidade_t = ?';
                $params[] = $ficha['capacidade_t'];
            }
            $params[] = $id;
            $sql = sprintf(
                'UPDATE %s SET %s = ?, observacoes = ?, ativo = ?, %s WHERE id = ?',
                $tipo['tabela'],
                $tipo['campo'],
                $extra
            );
        }
        $comando = Db::pdo()->prepare($sql);
        try {
            $comando->execute($params);
        } catch (PDOException $e) {
            self::traduzirDuplicado($e, $tipo['rotulo']);
        }
    }

    private static function excluir(array $tipo, int $id): void
    {
        $sql = sprintf('DELETE FROM %s WHERE id = ?', $tipo['tabela']);
        Db::pdo()->prepare($sql)->execute([$id]);
        self::limparArquivos((string) ($tipo['chave'] ?? ''), $id);
    }

    private static function gravarFoto(string $chave, int $id, array $pendente): void
    {
        self::limparArquivos($chave, $id);
        $dir = OPERACOES_ROOT . '/storage/fotos/' . $chave;
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
        $tipo = self::tipo($chave);
        $sql = sprintf('UPDATE %s SET foto = ? WHERE id = ?', $tipo['tabela']);
        Db::pdo()->prepare($sql)->execute([$chave . '/' . $id . '.' . $pendente['ext'], $id]);
    }

    private static function apagarFoto(string $chave, int $id): void
    {
        self::limparArquivos($chave, $id);
        $tipo = self::tipo($chave);
        $sql = sprintf('UPDATE %s SET foto = NULL WHERE id = ?', $tipo['tabela']);
        Db::pdo()->prepare($sql)->execute([$id]);
    }

    private static function limparArquivos(string $chave, int $id): void
    {
        if (!in_array($chave, ['pas', 'caminhoes', 'retros'], true) || $id < 1) {
            return;
        }
        $dir = OPERACOES_ROOT . '/storage/fotos/' . $chave;
        foreach (['jpg', 'png', 'webp'] as $ext) {
            $arquivo = $dir . DIRECTORY_SEPARATOR . $id . '.' . $ext;
            if (is_file($arquivo)) {
                unlink($arquivo);
            }
        }
    }

    private static function traduzirDuplicado(PDOException $e, string $rotulo): void
    {
        if (isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 1062) {
            throw new InvalidArgumentException('Já existe um cadastro com esse ' . mb_strtolower($rotulo, 'UTF-8') . '.');
        }
        throw $e;
    }
}
