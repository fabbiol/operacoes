<?php

declare(strict_types=1);

/**
 * Lê a nota fiscal (NF-e) para lançar o carregamento sem digitar: número, pedido, cliente, material,
 * quantidade e a hora em que a nota foi emitida.
 *
 * O XML é a leitura exata (os campos têm nome). O PDF (DANFE) é lido pelo texto, procurando os títulos
 * que todo DANFE tem; o número e a série vêm da chave de acesso, que é conferida pelo dígito verificador.
 * Em qualquer caso a tela mostra os campos para conferir antes de salvar.
 */
final class NotaFiscal
{
    public const TAMANHO_MAXIMO = 5 * 1024 * 1024;

    /**
     * Lê o arquivo enviado no campo "nota" do formulário.
     *
     * @return array<string, mixed>
     */
    public static function receber(string $campo = 'nota'): array
    {
        $arquivo = $_FILES[$campo] ?? null;
        $erro = is_array($arquivo) ? (int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        if ($erro === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException('Escolha o arquivo da nota (PDF ou XML).');
        }
        if ($erro === UPLOAD_ERR_INI_SIZE || $erro === UPLOAD_ERR_FORM_SIZE) {
            throw new InvalidArgumentException('O arquivo da nota passou do limite de 5 MB.');
        }
        if ($erro !== UPLOAD_ERR_OK || !is_uploaded_file((string) $arquivo['tmp_name'])) {
            throw new InvalidArgumentException('Não foi possível receber o arquivo da nota. Tente de novo.');
        }
        if ((int) $arquivo['size'] > self::TAMANHO_MAXIMO) {
            throw new InvalidArgumentException('O arquivo da nota passou do limite de 5 MB.');
        }
        $conteudo = file_get_contents((string) $arquivo['tmp_name']);
        if ($conteudo === false || $conteudo === '') {
            throw new InvalidArgumentException('O arquivo da nota está vazio.');
        }

        return self::ler($conteudo);
    }

    /** @return array<string, mixed> */
    public static function ler(string $conteudo): array
    {
        $inicio = ltrim(substr($conteudo, 0, 2048), "\xEF\xBB\xBF \t\r\n");
        if (str_starts_with($inicio, '%PDF')) {
            return self::lerPdf($conteudo);
        }
        if (str_starts_with($inicio, '<')) {
            return self::lerXml($conteudo);
        }

        throw new InvalidArgumentException('Esse arquivo não é o PDF nem o XML de uma nota fiscal.');
    }

    // ---------------------------------------------------------------- XML

    /** @return array<string, mixed> */
    public static function lerXml(string $conteudo): array
    {
        $antes = libxml_use_internal_errors(true);
        // Sem acesso à rede e sem entidades externas: o arquivo vem de fora.
        $xml = simplexml_load_string($conteudo, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($antes);
        if ($xml === false) {
            throw new InvalidArgumentException('Não foi possível ler o XML da nota. Confira se é o arquivo .xml da NF-e.');
        }
        $infNFe = self::nos($xml, '//*[local-name()="infNFe"]');
        if ($infNFe === []) {
            throw new InvalidArgumentException('Esse XML não é de uma nota fiscal eletrônica (NF-e).');
        }
        $nota = $infNFe[0];

        $chave = self::digitos((string) ($nota['Id'] ?? ''));
        if (strlen($chave) !== 44) {
            $chave = self::digitos(self::texto($xml, '//*[local-name()="infProt"]/*[local-name()="chNFe"]'));
        }

        $itens = [];
        foreach (self::nos($nota, './*[local-name()="det"]/*[local-name()="prod"]') as $prod) {
            $itens[] = [
                'material' => self::texto($prod, './*[local-name()="xProd"]'),
                'quantidade' => self::texto($prod, './*[local-name()="qCom"]'),
                'unidade' => self::texto($prod, './*[local-name()="uCom"]'),
                'pedido' => self::texto($prod, './*[local-name()="xPed"]'),
            ];
        }

        $pedido = '';
        foreach ($itens as $item) {
            if ($item['pedido'] !== '') {
                $pedido = $item['pedido'];
                break;
            }
        }
        if ($pedido === '') {
            $pedido = self::texto($nota, './*[local-name()="compra"]/*[local-name()="xPed"]');
        }
        if ($pedido === '') {
            $pedido = self::pedidoNoTexto(
                self::texto($nota, './*[local-name()="infAdic"]/*[local-name()="infCpl"]') . ' '
                . self::texto($nota, './*[local-name()="infAdic"]/*[local-name()="infAdFisco"]')
            );
        }

        $emissao = self::texto($nota, './*[local-name()="ide"]/*[local-name()="dhEmi"]');
        if ($emissao === '') {
            // Leiaute antigo (2.00): só a data, e a hora de saída à parte.
            $data = self::texto($nota, './*[local-name()="ide"]/*[local-name()="dEmi"]');
            $hora = self::texto($nota, './*[local-name()="ide"]/*[local-name()="hSaiEnt"]');
            $emissao = $data !== '' && $hora !== '' ? $data . 'T' . $hora : '';
        }

        $numero = self::texto($nota, './*[local-name()="ide"]/*[local-name()="nNF"]');

        return self::resultado('xml', [
            'chave' => strlen($chave) === 44 ? $chave : null,
            'nf' => $numero !== '' ? ltrim($numero, '0') : null,
            'serie' => self::texto($nota, './*[local-name()="ide"]/*[local-name()="serie"]'),
            'cliente' => self::texto($nota, './*[local-name()="dest"]/*[local-name()="xNome"]'),
            'pedido' => $pedido,
            'emitida_em' => self::horaLocal($emissao),
        ], $itens);
    }

    /** @return list<SimpleXMLElement> */
    private static function nos(SimpleXMLElement $base, string $caminho): array
    {
        $achados = $base->xpath($caminho);

        return is_array($achados) ? array_values($achados) : [];
    }

    private static function texto(SimpleXMLElement $base, string $caminho): string
    {
        $achados = self::nos($base, $caminho);

        return $achados === [] ? '' : trim((string) $achados[0]);
    }

    /** "2026-10-15T08:12:40-04:00" no horário do sistema (APP_TZ). */
    private static function horaLocal(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }
        try {
            $data = new DateTimeImmutable($valor);
        } catch (Exception) {
            return null;
        }

        return $data->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
    }

    // ---------------------------------------------------------------- PDF

    /** @return array<string, mixed> */
    public static function lerPdf(string $conteudo): array
    {
        $texto = self::textoDoPdf($conteudo);
        $linhas = preg_split('/\R/u', $texto) ?: [];

        $chave = self::chaveNoTexto($texto);
        $numero = null;
        $serie = '';
        if ($chave !== null) {
            $serie = ltrim(substr($chave, 22, 3), '0');
            $numero = ltrim(substr($chave, 25, 9), '0');
        } elseif (preg_match('/N[º°o]\.?\s*:?\s*([\d.]{1,11})/u', $texto, $achado)) {
            $numero = ltrim(str_replace('.', '', $achado[1]), '0');
        }

        $itens = self::itensDoPdf($linhas);
        $cliente = self::clienteDoPdf($linhas, $texto);
        if ($chave === null && $itens === [] && $cliente === null) {
            throw new InvalidArgumentException('Esse PDF não parece ser o DANFE de uma nota fiscal. Envie o PDF da nota ou o XML.');
        }
        $adicionais = self::trechoDepois($texto, '/DADOS\s+ADICIONAIS|INFORMA\S+\s+COMPLEMENTARES/u');

        return self::resultado('pdf', [
            'chave' => $chave,
            'nf' => $numero !== '' ? $numero : null,
            'serie' => $serie,
            'cliente' => $cliente,
            'pedido' => self::pedidoNoTexto($adicionais !== '' ? $adicionais : $texto),
            'emitida_em' => self::emissaoDoPdf($linhas, $texto),
        ], $itens);
    }

    private static function textoDoPdf(string $conteudo): string
    {
        $programa = self::programaPdf();
        if ($programa === null) {
            throw new InvalidArgumentException('Este servidor não consegue ler o PDF da nota. Envie o XML da nota.');
        }
        $arquivo = tempnam(sys_get_temp_dir(), 'nota');
        if ($arquivo === false || file_put_contents($arquivo, $conteudo) === false) {
            throw new InvalidArgumentException('Não foi possível ler o PDF da nota. Tente de novo.');
        }
        try {
            // Só as duas primeiras páginas: os dados do carregamento ficam na primeira.
            $processo = proc_open(
                [$programa, '-layout', '-enc', 'UTF-8', '-f', '1', '-l', '2', $arquivo, '-'],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $canos
            );
            if (!is_resource($processo)) {
                throw new InvalidArgumentException('Não foi possível ler o PDF da nota. Envie o XML da nota.');
            }
            stream_set_blocking($canos[1], false);
            stream_set_blocking($canos[2], false);
            $saida = '';
            $limite = microtime(true) + 15;
            while (true) {
                $saida .= (string) stream_get_contents($canos[1]);
                stream_get_contents($canos[2]);
                $situacao = proc_get_status($processo);
                if (!$situacao['running']) {
                    $saida .= (string) stream_get_contents($canos[1]);
                    break;
                }
                if (microtime(true) > $limite || strlen($saida) > 2_000_000) {
                    proc_terminate($processo);
                    break;
                }
                usleep(20000);
            }
            fclose($canos[1]);
            fclose($canos[2]);
            proc_close($processo);
        } finally {
            @unlink($arquivo);
        }
        // O pdftotext separa as páginas com um caractere de quebra de página (\f): sem texto, só sobra ele.
        if (trim($saida, " \t\n\r\0\x0B\f") === '') {
            throw new InvalidArgumentException('Esse PDF não tem texto para ler (pode ser uma foto escaneada). Envie o XML da nota.');
        }

        return $saida;
    }

    private static function programaPdf(): ?string
    {
        if (!function_exists('proc_open')) {
            return null;
        }
        foreach (['/usr/bin/pdftotext', '/usr/local/bin/pdftotext', '/opt/homebrew/bin/pdftotext'] as $caminho) {
            if (is_file($caminho) && is_executable($caminho)) {
                return $caminho;
            }
        }

        return null;
    }

    /** A chave de acesso: 44 números (no DANFE, em grupos de 4), com o dígito verificador certo. */
    private static function chaveNoTexto(string $texto): ?string
    {
        preg_match_all('/(?<!\d)\d{4}(?:[ .]?\d{4}){10}(?!\d)/u', $texto, $achados);
        foreach ($achados[0] as $bruto) {
            $chave = self::digitos($bruto);
            if (strlen($chave) === 44 && in_array(substr($chave, 20, 2), ['55', '65'], true) && self::chaveValida($chave)) {
                return $chave;
            }
        }

        return null;
    }

    private static function chaveValida(string $chave): bool
    {
        $soma = 0;
        $peso = 2;
        for ($i = 42; $i >= 0; $i--) {
            $soma += (int) $chave[$i] * $peso;
            $peso = $peso === 9 ? 2 : $peso + 1;
        }
        $resto = $soma % 11;
        $digito = $resto < 2 ? 0 : 11 - $resto;

        return (int) $chave[43] === $digito;
    }

    /** O nome do destinatário: o valor embaixo de "NOME / RAZÃO SOCIAL", no quadro do destinatário. */
    private static function clienteDoPdf(array $linhas, string $texto): ?string
    {
        $quadro = null;
        foreach ($linhas as $i => $linha) {
            if (preg_match('/DESTINAT\S*\s*\/\s*REMETENTE/u', $linha)) {
                $quadro = $i;
                break;
            }
        }
        if ($quadro !== null) {
            for ($i = $quadro; $i < min(count($linhas), $quadro + 4); $i++) {
                $posicao = self::posicao($linhas[$i], '/NOME\s*\/\s*RAZ/u');
                if ($posicao === null) {
                    continue;
                }
                $valor = self::valorNaColuna($linhas, $i, $posicao);
                if ($valor !== null) {
                    return $valor;
                }
            }
        }
        // No canhoto: "DESTINATARIO: NOME - endereço".
        if (preg_match('/DESTINAT\S*\s*:\s*([^\n]+?)\s+-\s/u', $texto, $achado)) {
            $nome = trim($achado[1]);

            return $nome !== '' ? $nome : null;
        }

        return null;
    }

    /** Data da emissão com a hora: a do protocolo de autorização, ou a data da emissão com a hora de saída. */
    private static function emissaoDoPdf(array $linhas, string $texto): ?string
    {
        $protocolo = self::trechoDepois($texto, '/PROTOCOLO\s+DE\s+AUTORIZA/u', 600);
        if ($protocolo !== '' && preg_match('/\d{12,17}\s*[-–]?\s*(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})(?::(\d{2}))?/u', $protocolo, $p)) {
            return self::montarData($p[3], $p[2], $p[1], $p[4], $p[5], $p[6] ?? '00');
        }
        $data = null;
        $hora = null;
        foreach ($linhas as $i => $linha) {
            $posicao = self::posicao($linha, '/DATA\s+(?:DA|DE)\s+EMISS/u');
            if ($data === null && $posicao !== null) {
                $valor = self::valorNaColuna($linhas, $i, $posicao);
                if ($valor !== null && preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $valor, $d)) {
                    $data = [$d[3], $d[2], $d[1]];
                }
            }
            $posicao = self::posicao($linha, '/HORA\s+(?:DA|DE)\s+(?:ENTRADA\s*\/\s*)?SA/u');
            if ($hora === null && $posicao !== null) {
                $valor = self::valorNaColuna($linhas, $i, $posicao);
                if ($valor !== null && preg_match('/(\d{2}):(\d{2})(?::(\d{2}))?/', $valor, $h)) {
                    $hora = [$h[1], $h[2], $h[3] ?? '00'];
                }
            }
        }
        if ($data !== null && $hora !== null) {
            return self::montarData($data[0], $data[1], $data[2], $hora[0], $hora[1], $hora[2]);
        }

        return null;
    }

    private static function montarData(string $ano, string $mes, string $dia, string $hora, string $minuto, string $segundo): ?string
    {
        if (!checkdate((int) $mes, (int) $dia, (int) $ano) || (int) $hora > 23 || (int) $minuto > 59 || (int) $segundo > 59) {
            return null;
        }

        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $ano, $mes, $dia, $hora, $minuto, $segundo);
    }

    /**
     * Os itens do quadro "DADOS DO PRODUTO / SERVIÇO". Cada linha de item tem, nesta ordem:
     * código, descrição, NCM (8 números), CST ou CSOSN, CFOP (4 números), unidade e quantidade.
     *
     * @return list<array{material: string, quantidade: string, unidade: string, pedido: string}>
     */
    private static function itensDoPdf(array $linhas): array
    {
        $comeco = null;
        foreach ($linhas as $i => $linha) {
            if (preg_match('/DESCRI\S*\s+D[OA]S?\s+PRODUTO/u', $linha)) {
                $comeco = $i + 1;
                break;
            }
        }
        if ($comeco === null) {
            return [];
        }
        $itens = [];
        for ($i = $comeco; $i < count($linhas); $i++) {
            $linha = $linhas[$i];
            if (preg_match('/DADOS\s+ADICIONAIS|C[ÁA]LCULO\s+DO\s+ISSQN/u', $linha)) {
                break;
            }
            if (preg_match('/^\s*\S+\s+(.+?)\s+\d{8}\s+\d{2,4}\s+\d{4}\s+(\S{1,6})\s+([\d.]+,\d+)/u', $linha, $item)) {
                $unidade = $item[2];
                // Coluna estreita: a unidade pode continuar na linha de baixo ("TO" + "N").
                $seguinte = trim((string) ($linhas[$i + 1] ?? ''));
                if (self::unidade($unidade) === null && preg_match('/^[A-Za-z³]{1,3}$/u', $seguinte)) {
                    $unidade .= $seguinte;
                }
                $itens[] = [
                    'material' => trim($item[1]),
                    'quantidade' => str_replace(',', '.', str_replace('.', '', $item[3])),
                    'unidade' => $unidade,
                    'pedido' => '',
                ];
            }
        }

        return $itens;
    }

    /** Posição (em caracteres) onde o título começa na linha. */
    private static function posicao(string $linha, string $padrao): ?int
    {
        if (!preg_match($padrao, $linha, $achado, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        return mb_strlen(substr($linha, 0, $achado[0][1]), 'UTF-8');
    }

    /** O valor que fica embaixo do título, na mesma coluna, até o próximo espaço largo. */
    private static function valorNaColuna(array $linhas, int $linhaTitulo, int $coluna): ?string
    {
        for ($i = $linhaTitulo + 1; $i < min(count($linhas), $linhaTitulo + 3); $i++) {
            $linha = $linhas[$i];
            if (trim($linha) === '') {
                continue;
            }
            $inicio = max(0, $coluna - 2);
            $pedaco = ltrim(mb_substr($linha, $inicio, null, 'UTF-8'));
            if ($pedaco === '') {
                return null;
            }
            $partes = preg_split('/\s{3,}/u', $pedaco) ?: [];
            $valor = trim((string) ($partes[0] ?? ''));

            return $valor !== '' ? $valor : null;
        }

        return null;
    }

    private static function trechoDepois(string $texto, string $padrao, int $tamanho = 4000): string
    {
        if (!preg_match($padrao, $texto, $achado, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        return substr($texto, $achado[0][1], $tamanho);
    }

    // ---------------------------------------------------------------- comum

    /** "PEDIDO: 4521", "Pedido n. 7788", "PEDIDO DE COMPRA: OC-2026/115". */
    public static function pedidoNoTexto(string $texto): string
    {
        $padrao = '/\bPED(?:IDO)?\b\s*(?:DE\s+(?:COMPRA|VENDA)\s*)?(?:N[º°O]?\s*\.?\s*)?[:\-.]?\s*([A-Z]{0,4}[\-\/]?\d[A-Z0-9\-\/.]{0,28})/iu';
        if (!preg_match($padrao, $texto, $achado)) {
            return '';
        }

        return rtrim($achado[1], '.,;-/');
    }

    /**
     * Junta o que foi lido. Itens com o mesmo material têm a quantidade somada; o carregamento usa o primeiro material.
     *
     * @param array<string, mixed> $dados
     * @param list<array{material: string, quantidade: string, unidade: string, pedido: string}> $itens
     * @return array<string, mixed>
     */
    private static function resultado(string $origem, array $dados, array $itens): array
    {
        $material = null;
        $quantidade = null;
        $unidadeNota = null;
        $outros = [];
        foreach ($itens as $item) {
            if ($item['material'] === '') {
                continue;
            }
            if ($material === null) {
                $material = $item['material'];
                $unidadeNota = $item['unidade'];
                $quantidade = 0.0;
            }
            if (self::normalizar($item['material']) === self::normalizar($material)) {
                $quantidade += (float) $item['quantidade'];
            } else {
                $outros[$item['material']] = true;
            }
        }

        $cliente = isset($dados['cliente']) ? trim((string) $dados['cliente']) : '';
        $pedido = isset($dados['pedido']) ? trim((string) $dados['pedido']) : '';

        return [
            'origem' => $origem,
            'chave' => $dados['chave'] ?? null,
            'nf' => $dados['nf'] ?? null,
            'serie' => (string) ($dados['serie'] ?? ''),
            'cliente' => $cliente !== '' ? mb_substr($cliente, 0, 160, 'UTF-8') : null,
            'pedido' => $pedido !== '' ? mb_substr($pedido, 0, 60, 'UTF-8') : null,
            'material' => $material,
            'quantidade' => $quantidade !== null && $quantidade > 0 ? round($quantidade, 2) : null,
            'unidade' => $unidadeNota !== null ? self::unidade($unidadeNota) : null,
            'unidade_nota' => $unidadeNota,
            'outros_materiais' => array_keys($outros),
            'emitida_em' => $dados['emitida_em'] ?? null,
        ];
    }

    /** Unidade da nota ("T", "TON", "M3", "UN") na unidade do sistema. */
    public static function unidade(string $bruto): ?string
    {
        $valor = str_replace(['³', ' ', '.'], ['3', '', ''], self::normalizar($bruto));

        return match (true) {
            in_array($valor, ['t', 'ton', 'tn', 'tons', 'tonelada', 'toneladas'], true) => 't',
            in_array($valor, ['m3', 'mt3', 'metro3', 'metrocubico', 'metroscubicos'], true) => 'm3',
            in_array($valor, ['un', 'und', 'unid', 'unidade', 'unidades', 'pc', 'pca'], true) => 'un',
            default => null,
        };
    }

    /**
     * O material do cadastro que corresponde ao da nota: o nome igual, ou o nome do cadastro dentro do da nota
     * ("BRITA 1" em "BRITA 1 GRADUADA"). Entre vários, o de nome mais comprido.
     *
     * @param list<array<string, mixed>> $materiais
     */
    public static function materialDoCadastro(?string $texto, array $materiais): ?int
    {
        if ($texto === null || trim($texto) === '') {
            return null;
        }
        $nota = ' ' . self::normalizar($texto) . ' ';
        $melhor = null;
        $pontos = 0;
        foreach ($materiais as $material) {
            if ((int) ($material['ativo'] ?? 1) !== 1) {
                continue;
            }
            $nome = self::normalizar((string) $material['nome']);
            if ($nome === '') {
                continue;
            }
            $agora = 0;
            if (' ' . $nome . ' ' === $nota) {
                $agora = 10000;
            } elseif (str_contains($nota, ' ' . $nome . ' ')) {
                $agora = 100 + strlen($nome);
            } elseif (str_contains(' ' . $nome . ' ', $nota)) {
                $agora = strlen($nota);
            }
            if ($agora > $pontos) {
                $pontos = $agora;
                $melhor = (int) $material['id'];
            }
        }

        return $melhor;
    }

    /** Minúsculas, sem acento e só letras e números separados por um espaço. */
    public static function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $texto = strtr($texto, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n', 'º' => 'o', 'ª' => 'a', '³' => '3', '²' => '2',
        ]);
        $texto = (string) preg_replace('/[^a-z0-9]+/u', ' ', $texto);

        return trim($texto);
    }

    private static function digitos(string $texto): string
    {
        return (string) preg_replace('/\D+/', '', $texto);
    }
}
