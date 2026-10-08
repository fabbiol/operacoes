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
            self::atualizar($tipo, $id, $nome, $observacoes, $ativo, $ficha);
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
        $sql = sprintf('UPDATE %s SET ativo = ? WHERE id = ?', $tipo['tabela']);
        $comando = Db::pdo()->prepare($sql);
        $comando->execute([(int) $atual['ativo'] === 1 ? 0 : 1, $id]);
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
        if (!isset($_FILES['foto']) || !is_array($_FILES['foto'])) {
            return null;
        }
        $erro = (int) ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($erro === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($erro !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Não foi possível receber a foto. Tente outra imagem.');
        }
        $tamanho = (int) ($_FILES['foto']['size'] ?? 0);
        if ($tamanho < 1 || $tamanho > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('A foto pode ter no máximo 5 MB.');
        }
        $origem = (string) ($_FILES['foto']['tmp_name'] ?? '');
        if ($origem === '' || !is_uploaded_file($origem)) {
            throw new InvalidArgumentException('Não foi possível receber a foto. Tente outra imagem.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($origem);
        $extensoes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!is_string($mime) || !isset($extensoes[$mime])) {
            throw new InvalidArgumentException('A foto precisa ser JPG, PNG ou WebP.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'opfoto');
        if ($tmp === false || !move_uploaded_file($origem, $tmp)) {
            throw new InvalidArgumentException('Não foi possível receber a foto. Tente outra imagem.');
        }

        return ['tmp' => $tmp, 'ext' => $extensoes[$mime]];
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
