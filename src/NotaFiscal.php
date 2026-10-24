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

    /**
     * Guarda o PDF lido até a ordem ser lançada (storage/notas/pendentes) e devolve o nome sorteado,
     * que vai escondido no formulário. Sem conseguir guardar, a leitura segue sem o PDF.
     */
    public static function guardarPdf(string $campo = 'nota'): ?string
    {
        $pasta = OPERACOES_ROOT . '/storage/notas/pendentes';
        if (!is_dir($pasta) && !@mkdir($pasta, 0775, true)) {
            return null;
        }
        // O PDF lido e não lançado fica para trás: some depois de um dia.
        foreach (glob($pasta . '/*.pdf') ?: [] as $velho) {
            if ((int) @filemtime($velho) < time() - 86400) {
                @unlink($velho);
            }
        }
        $nome = bin2hex(random_bytes(16));

        return @move_uploaded_file((string) ($_FILES[$campo]['tmp_name'] ?? ''), $pasta . '/' . $nome . '.pdf') ? $nome : null;
    }

    /** Liga o PDF guardado na leitura à ordem lançada (storage/notas/<id>.pdf). Nome inválido ou vencido: fica sem PDF. */
    public static function ligarPdf(int $ordemId, string $nome): void
    {
        $origem = OPERACOES_ROOT . '/storage/notas/pendentes/' . $nome . '.pdf';
        if (!preg_match('/^[0-9a-f]{32}$/', $nome) || !is_file($origem)) {
            return;
        }
        if (!@rename($origem, OPERACOES_ROOT . '/storage/notas/' . $ordemId . '.pdf')) {
            return;
        }
        Db::pdo()->prepare('UPDATE fila_carregamentos SET nf_arquivo = ? WHERE id = ?')->execute(['notas/' . $ordemId . '.pdf', $ordemId]);
    }

    /** Caminho do PDF da nota da ordem, conferido para não sair da pasta storage/notas; null se não tem. */
    public static function arquivoPdf(array $ordem): ?string
    {
        $relativo = (string) ($ordem['nf_arquivo'] ?? '');
        if (!preg_match('#^notas/(\d+)\.pdf$#', $relativo, $partes) || (int) $partes[1] !== (int) ($ordem['id'] ?? 0)) {
            return null;
        }
        $base = realpath(OPERACOES_ROOT . '/storage/notas');
        $arquivo = realpath(OPERACOES_ROOT . '/storage/' . $relativo);
        if ($base === false || $arquivo === false || !is_file($arquivo)) {
            return null;
        }

        return str_starts_with(str_replace('\\', '/', $arquivo), rtrim(str_replace('\\', '/', $base), '/') . '/') ? $arquivo : null;
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
        $soData = null;
        if ($emissao === '') {
            // Leiaute antigo (2.00): só a data, e a hora de saída à parte.
            $data = self::texto($nota, './*[local-name()="ide"]/*[local-name()="dEmi"]');
            $soData = preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) === 1 ? $data : null;
            $hora = self::texto($nota, './*[local-name()="ide"]/*[local-name()="hSaiEnt"]');
            $emissao = $data !== '' && $hora !== '' ? $data . 'T' . $hora : '';
        }

        $numero = self::texto($nota, './*[local-name()="ide"]/*[local-name()="nNF"]');

        return self::resultado('xml', [
            'chave' => strlen($chave) === 44 ? $chave : null,
            'nf' => $numero !== '' ? ltrim($numero, '0') : null,
            'serie' => self::texto($nota, './*[local-name()="ide"]/*[local-name()="serie"]'),
            'cliente' => self::texto($nota, './*[local-name()="dest"]/*[local-name()="xNome"]'),
            'documento' => self::texto($nota, './*[local-name()="dest"]/*[local-name()="CNPJ" or local-name()="CPF"]'),
            'pedido' => $pedido,
            'emitida_em' => self::horaLocal($emissao),
            'data_emissao' => $soData,
            'valor' => self::texto($nota, './*[local-name()="total"]/*[local-name()="ICMSTot"]/*[local-name()="vNF"]'),
            'pagamento' => self::pagamentoDoXml($nota),
        ], $itens);
    }

    /** Forma de pagamento do grupo <pag> (código tPag da NF-e); sem uma forma conhecida, a pessoa escolhe. */
    private static function pagamentoDoXml(SimpleXMLElement $nota): ?string
    {
        $formas = [
            '01' => 'dinheiro',
            '02' => 'cheque',
            '03' => 'cartao',
            '04' => 'cartao',
            '05' => 'prazo',
            '14' => 'prazo',
            '15' => 'boleto',
            '17' => 'pix',
            '18' => 'transferencia',
        ];
        foreach (self::nos($nota, './*[local-name()="pag"]/*[local-name()="detPag"]/*[local-name()="tPag"]') as $codigo) {
            $forma = $formas[trim((string) $codigo)] ?? null;
            if ($forma !== null) {
                return $forma;
            }
        }

        return null;
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
        $adicionais = self::dadosAdicionais($linhas);
        [$emitida, $dataEmissao] = self::emissaoDoPdf($linhas);

        return self::resultado('pdf', [
            'chave' => $chave,
            'nf' => $numero !== '' ? $numero : null,
            'serie' => $serie,
            'cliente' => $cliente,
            'documento' => self::documentoDoPdf($linhas, $texto),
            'pedido' => self::pedidoNoTexto($adicionais !== '' ? $adicionais : $texto),
            'emitida_em' => $emitida,
            'data_emissao' => $dataEmissao,
            'valor' => self::valorDoPdf($linhas, $texto),
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

    /*
     * Os títulos do DANFE são procurados sem espaços e sem acento: alguns PDFs saem com letras separadas
     * ("PROT OCOLO", "DAT A DE EMISSÃO", "PRODUT O"), e assim eles continuam sendo achados.
     */

    /** O nome do destinatário: o valor embaixo de "NOME / RAZÃO SOCIAL", no quadro do destinatário. */
    private static function clienteDoPdf(array $linhas, string $texto): ?string
    {
        $quadro = self::linhaComTitulo($linhas, ['DESTINATARIO/REMETENTE']);
        if ($quadro !== null) {
            for ($i = $quadro; $i < min(count($linhas), $quadro + 4); $i++) {
                $posicao = self::posicaoTitulo($linhas[$i], 'NOME/RAZAO');
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

    /**
     * O CNPJ/CPF do destinatário: o número embaixo de "CNPJ / CPF", no quadro do destinatário, ou, sem ele,
     * o do canhoto ("DESTINATARIO: NOME - endereço, CNPJ: ..."). Volta como está; resultado() confere os dígitos.
     */
    private static function documentoDoPdf(array $linhas, string $texto): ?string
    {
        $numero = '/(?<!\d)(?:\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}|\d{3}\.?\d{3}\.?\d{3}-?\d{2})(?!\d)/';
        $quadro = self::linhaComTitulo($linhas, ['DESTINATARIO/REMETENTE']);
        if ($quadro !== null) {
            for ($i = $quadro; $i < min(count($linhas), $quadro + 4); $i++) {
                $coluna = self::posicaoTitulo($linhas[$i], 'CNPJ/CPF');
                if ($coluna === null) {
                    continue;
                }
                for ($j = $i + 1; $j < min(count($linhas), $i + 3); $j++) {
                    if (!preg_match_all($numero, $linhas[$j], $achados, PREG_OFFSET_CAPTURE)) {
                        continue;
                    }
                    // O número fica centrado embaixo do título e pode começar antes dele: vale o mais perto da coluna.
                    $melhor = null;
                    $distancia = PHP_INT_MAX;
                    foreach ($achados[0] as [$valor, $byte]) {
                        $longe = abs(mb_strlen(substr($linhas[$j], 0, $byte), 'UTF-8') - $coluna);
                        if ($longe < $distancia) {
                            [$melhor, $distancia] = [$valor, $longe];
                        }
                    }

                    return $melhor;
                }
            }
        }
        if (preg_match('/DESTINAT\S*\s*:.{0,400}?\b(?:CNPJ|CPF)\s*:\s*([\d.\/-]{11,18})/su', $texto, $achado)) {
            return $achado[1];
        }

        return null;
    }

    /** CNPJ (14 números) ou CPF (11) só com os números, se os dígitos verificadores conferem; senão null. */
    public static function documento(string $bruto): ?string
    {
        $numero = self::digitos($bruto);
        $tamanho = strlen($numero);
        if (($tamanho !== 11 && $tamanho !== 14) || preg_match('/^(\d)\1*$/', $numero)) {
            return null;
        }
        for ($dv = $tamanho - 2; $dv < $tamanho; $dv++) {
            $soma = 0;
            for ($i = 0; $i < $dv; $i++) {
                // Pesos da direita para a esquerda: 2, 3, 4... (no CNPJ voltam a 2 depois do 9).
                $peso = $dv - $i + 1;
                $soma += (int) $numero[$i] * ($tamanho === 14 ? ($peso - 2) % 8 + 2 : $peso);
            }
            $resto = $soma % 11;
            if ((int) $numero[$dv] !== ($resto < 2 ? 0 : 11 - $resto)) {
                return null;
            }
        }

        return $numero;
    }

    /** "98765432000198" em "98.765.432/0001-98" e "52998224725" em "529.982.247-25"; outro tamanho volta como está. */
    public static function formatarDocumento(?string $numero): string
    {
        $numero = (string) $numero;

        return match (strlen($numero)) {
            11 => (string) preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $numero),
            14 => (string) preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $numero),
            default => $numero,
        };
    }

    /**
     * Quando a nota foi emitida: a data e hora do protocolo de autorização ou, sem ela, a data da emissão
     * com a hora de saída. Alguns DANFEs não trazem hora nenhuma: aí volta só a data, para a tela avisar.
     *
     * @return array{0: ?string, 1: ?string} [data e hora, só a data (Y-m-d)]
     */
    private static function emissaoDoPdf(array $linhas): array
    {
        $protocolo = self::linhaComTitulo($linhas, ['PROTOCOLODEAUTORIZA']);
        if ($protocolo !== null) {
            $coluna = (int) self::posicaoTitulo($linhas[$protocolo], 'PROTOCOLODEAUTORIZA');
            for ($i = $protocolo; $i < min(count($linhas), $protocolo + 3); $i++) {
                $pedaco = mb_substr($linhas[$i], max(0, $coluna - 2), null, 'UTF-8');
                if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})(?::(\d{2}))?/u', $pedaco, $p)) {
                    $data = self::montarData($p[3], $p[2], $p[1], $p[4], $p[5], $p[6] ?? '00');
                    if ($data !== null) {
                        return [$data, substr($data, 0, 10)];
                    }
                }
            }
        }

        $data = null;
        $hora = null;
        foreach ($linhas as $i => $linha) {
            $compacta = self::compacto($linha);
            if ($data === null && (str_contains($compacta, 'DATADEEMISSAO') || str_contains($compacta, 'DATADAEMISSAO'))) {
                $posicao = self::posicaoTitulo($linha, str_contains($compacta, 'DATADEEMISSAO') ? 'DATADEEMISSAO' : 'DATADAEMISSAO');
                $valor = $posicao !== null ? self::valorNaColuna($linhas, $i, $posicao) : null;
                if ($valor !== null && preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $valor, $d) && checkdate((int) $d[2], (int) $d[1], (int) $d[3])) {
                    $data = $d[3] . '-' . $d[2] . '-' . $d[1];
                }
            }
            $posicaoHora = strpos($compacta, 'HORA');
            if ($hora === null && $posicaoHora !== false && str_contains(substr($compacta, $posicaoHora), 'SAIDA')) {
                $posicao = self::posicaoTitulo($linha, 'HORA');
                $valor = $posicao !== null ? self::valorNaColuna($linhas, $i, $posicao) : null;
                if ($valor !== null && preg_match('/^(\d{2}):(\d{2})(?::(\d{2}))?/', $valor, $h)) {
                    $hora = [$h[1], $h[2], $h[3] ?? '00'];
                }
            }
        }
        if ($data !== null && $hora !== null) {
            [$ano, $mes, $dia] = explode('-', $data);

            return [self::montarData($ano, $mes, $dia, $hora[0], $hora[1], $hora[2]), $data];
        }

        return [null, $data];
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
            $compacta = self::compacto($linha);
            if (str_contains($compacta, 'DESCRICAOD') && str_contains($compacta, 'PRODUT')) {
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
            $compacta = self::compacto($linha);
            if (str_contains($compacta, 'DADOSADICIONAIS') || str_contains($compacta, 'CALCULODOISSQN')) {
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

    /** Valor total da nota: embaixo de "VALOR TOTAL DA NOTA" (ou "V. TOTAL DA NOTA"), ou no canhoto. */
    private static function valorDoPdf(array $linhas, string $texto): ?string
    {
        foreach ($linhas as $i => $linha) {
            foreach (['VALORTOTALDANOTA', 'V.TOTALDANOTA', 'VTOTALDANOTA', 'VLR.TOTALDANOTA'] as $titulo) {
                $posicao = self::posicaoTitulo($linha, $titulo);
                if ($posicao === null) {
                    continue;
                }
                $valor = self::valorNaColuna($linhas, $i, $posicao);
                if ($valor !== null && preg_match('/^(?:R\$\s*)?(\d{1,3}(?:\.\d{3})*,\d{2}|\d+,\d{2})$/u', $valor, $achado)) {
                    return str_replace(',', '.', str_replace('.', '', $achado[1]));
                }
            }
        }
        if (preg_match('/VALOR\s+TOTAL\s*:\s*(?:R\$\s*)?(\d{1,3}(?:\.\d{3})*,\d{2}|\d+,\d{2})/u', $texto, $achado)) {
            return str_replace(',', '.', str_replace('.', '', $achado[1]));
        }

        return null;
    }

    /** O texto dos dados adicionais (informações complementares), onde costuma estar o pedido. */
    private static function dadosAdicionais(array $linhas): string
    {
        $comeco = self::linhaComTitulo($linhas, ['DADOSADICIONAIS', 'INFORMACOESCOMPLEMENTARES']);

        return $comeco === null ? '' : implode("\n", array_slice($linhas, $comeco, 40));
    }

    /** Maiúsculas, sem acento e sem espaço: "Prot ocolo de Autorização" vira "PROTOCOLODEAUTORIZACAO". */
    private static function compacto(string $texto): string
    {
        $texto = strtr(mb_strtoupper($texto, 'UTF-8'), [
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N', 'º' => 'O', 'ª' => 'A', '°' => 'O',
        ]);
        $texto = (string) preg_replace('/\s+/u', '', $texto);

        // Qualquer outro caractere fora do ASCII vira "?": cada caractere ocupa uma posição só.
        return (string) preg_replace('/[^\x20-\x7E]/u', '?', $texto);
    }

    /** Primeira linha que tem um dos títulos (comparados sem espaço e sem acento). */
    private static function linhaComTitulo(array $linhas, array $titulos): ?int
    {
        foreach ($linhas as $i => $linha) {
            $compacta = self::compacto($linha);
            foreach ($titulos as $titulo) {
                if (str_contains($compacta, $titulo)) {
                    return $i;
                }
            }
        }

        return null;
    }

    /** Posição (em caracteres) onde o título começa na linha, mesmo com letras separadas por espaço. */
    private static function posicaoTitulo(string $linha, string $titulo): ?int
    {
        $compacta = '';
        $origem = [];
        foreach (mb_str_split($linha, 1, 'UTF-8') as $posicao => $caractere) {
            $letra = self::compacto($caractere);
            if ($letra === '') {
                continue;
            }
            $compacta .= $letra[0];
            $origem[] = $posicao;
        }
        $achou = strpos($compacta, $titulo);

        return $achou === false ? null : $origem[$achou];
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
     * Junta o que foi lido. Itens com o mesmo material e a mesma unidade têm a quantidade somada (toneladas não se
     * somam com metros cúbicos); o carregamento usa o primeiro material.
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
        $outrasUnidades = [];
        $mesmaUnidade = static fn (string $unidade): string => self::unidade($unidade) ?? self::normalizar($unidade);
        foreach ($itens as $item) {
            if ($item['material'] === '') {
                continue;
            }
            if ($material === null) {
                $material = $item['material'];
                $unidadeNota = $item['unidade'];
                $quantidade = 0.0;
            }
            if (self::normalizar($item['material']) !== self::normalizar($material)) {
                $outros[$item['material']] = true;
            } elseif ($mesmaUnidade($item['unidade']) === $mesmaUnidade((string) $unidadeNota)) {
                $quantidade += (float) $item['quantidade'];
            } else {
                $outrasUnidades[$item['unidade']] = true;
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
            // CNPJ/CPF do destinatário, só os números; null se não veio ou se os dígitos verificadores não conferem.
            'documento' => self::documento((string) ($dados['documento'] ?? '')),
            'pedido' => $pedido !== '' ? mb_substr($pedido, 0, 60, 'UTF-8') : null,
            'material' => $material,
            'quantidade' => $quantidade !== null && $quantidade > 0 ? round($quantidade, 2) : null,
            'unidade' => $unidadeNota !== null ? self::unidade($unidadeNota) : null,
            'unidade_nota' => $unidadeNota,
            'outros_materiais' => array_keys($outros),
            'outras_unidades' => array_map('strval', array_keys($outrasUnidades)),
            'emitida_em' => $dados['emitida_em'] ?? null,
            'valor' => isset($dados['valor']) && is_numeric($dados['valor']) && (float) $dados['valor'] > 0 ? round((float) $dados['valor'], 2) : null,
            'data_emissao' => $dados['data_emissao'] ?? (isset($dados['emitida_em']) ? substr((string) $dados['emitida_em'], 0, 10) : null),
            // O PDF da Próspera não traz a forma de pagamento: só o XML.
            'pagamento' => $dados['pagamento'] ?? null,
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
