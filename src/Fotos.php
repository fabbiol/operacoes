<?php

declare(strict_types=1);

/**
 * Fotos de pessoas e máquinas. A foto do celular chega com vários megas; o tablet do campo
 * só precisa de uma imagem do tamanho do cartão. Por isso a foto é reduzida ao ser recebida,
 * e as antigas, grandes, são reduzidas na primeira vez que alguém as abre.
 */
final class Fotos
{
    public const LADO_PESSOA = 640;
    public const LADO_MAQUINA = 960;
    private const MAX_ENVIO = 10 * 1024 * 1024;
    // Câmera de celular chega a uns 50 megapixels. Acima disso, a foto não é aberta (ocuparia gigas de memória).
    private const MAX_PIXELS = 50_000_000;
    // Teto para a memória que a redução pode pedir ao PHP.
    private const MAX_MEMORIA = 512 * 1024 * 1024;
    // Foto guardada acima disso é de antes da redução e é reduzida ao ser aberta.
    private const MAX_GUARDADA = 400 * 1024;
    private const QUALIDADE = 82;

    /**
     * Confere e reduz a foto enviada no campo "foto". Devolve null quando nenhum arquivo foi escolhido.
     *
     * @return array{tmp: string, ext: string}|null
     */
    public static function receber(int $lado): ?array
    {
        if (!isset($_FILES['foto']) || !is_array($_FILES['foto'])) {
            return null;
        }
        $erro = (int) ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($erro === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($erro === UPLOAD_ERR_INI_SIZE || $erro === UPLOAD_ERR_FORM_SIZE) {
            throw new InvalidArgumentException('A foto é maior do que o servidor aceita. Tente uma foto menor.');
        }
        if ($erro !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Não foi possível receber a foto. Tente outra imagem.');
        }
        $tamanho = (int) ($_FILES['foto']['size'] ?? 0);
        if ($tamanho < 1 || $tamanho > self::MAX_ENVIO) {
            throw new InvalidArgumentException('A foto pode ter no máximo 10 MB.');
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
        $dados = (string) file_get_contents($origem);
        $info = @getimagesizefromstring($dados);
        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            throw new InvalidArgumentException('Não foi possível ler essa foto. Tente outra imagem.');
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw new InvalidArgumentException('A foto tem resolução grande demais. Tente uma foto menor.');
        }

        if (!self::podeReduzir($mime)) {
            // Sem as extensões de imagem no servidor, a foto fica como veio (o navegador gira pelo EXIF).
            $tmp = tempnam(sys_get_temp_dir(), 'opfoto');
            if ($tmp === false || !move_uploaded_file($origem, $tmp)) {
                throw new InvalidArgumentException('Não foi possível receber a foto. Tente outra imagem.');
            }

            return ['tmp' => $tmp, 'ext' => $extensoes[$mime]];
        }
        // Reduz direto do arquivo do envio: o PHP o apaga no fim do pedido, mesmo se der erro no meio.
        $reduzida = self::reduzir($dados, $mime, $info, $lado, 'image/jpeg', null);
        if ($reduzida === null) {
            throw new InvalidArgumentException('Não foi possível ler essa foto. Tente outra imagem.');
        }

        return ['tmp' => $reduzida, 'ext' => 'jpg'];
    }

    /**
     * Envia a foto com cache. O navegador guarda por uma hora e depois só confirma se mudou.
     * Uma foto antiga e grande é reduzida antes, no mesmo formato e no mesmo arquivo.
     */
    public static function servir(string $caminho, string $tipo, int $lado): void
    {
        // A foto não mexe na sessão: libera para os outros pedidos do mesmo tablet não esperarem.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        clearstatcache(true, $caminho);
        if ((int) @filesize($caminho) > self::MAX_GUARDADA) {
            self::reduzirGuardada($caminho, $tipo, $lado);
            clearstatcache(true, $caminho);
        }
        $tamanho = (int) @filesize($caminho);
        $mudou = (int) @filemtime($caminho);
        $etag = '"' . dechex($mudou) . '-' . dechex($tamanho) . '"';

        // A sessão manda "Pragma: no-cache" e uma validade vencida; com eles o Chrome pede a foto de novo em toda tela.
        header_remove('Pragma');
        header_remove('Expires');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        header('ETag: ' . $etag);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mudou) . ' GMT');
        $pedido = (string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
        if ($pedido !== '' && in_array($etag, array_map('trim', explode(',', $pedido)), true)) {
            http_response_code(304);
            exit;
        }
        header('Content-Type: ' . $tipo);
        header('Content-Length: ' . (string) $tamanho);
        readfile($caminho);
        exit;
    }

    /** Número que muda quando a foto muda, para a tela de edição não mostrar a foto antiga guardada no navegador. */
    public static function versao(string $relativo): string
    {
        $arquivo = OPERACOES_ROOT . '/storage/fotos/' . str_replace('\\', '/', $relativo);

        return is_file($arquivo) ? dechex((int) filemtime($arquivo)) : '0';
    }

    private static function reduzirGuardada(string $caminho, string $tipo, int $lado): void
    {
        if (!self::podeReduzir($tipo)) {
            return;
        }
        $antes = @stat($caminho);
        if ($antes === false) {
            return;
        }
        // Uma tentativa que não deixou a foto menor fica anotada, para não se repetir a cada abertura.
        $marca = $caminho . '.ok';
        $assinatura = $antes['ino'] . '-' . $antes['size'] . '-' . $antes['mtime'];
        if (@file_get_contents($marca) === $assinatura) {
            return;
        }
        // Os bytes são lidos uma vez só: medida, imagem e giro vêm do mesmo arquivo, mesmo que outro pedido o troque.
        $dados = @file_get_contents($caminho);
        $info = $dados === false ? false : @getimagesizefromstring($dados);
        if ($info === false || max($info[0], $info[1]) <= $lado || $info[0] * $info[1] > self::MAX_PIXELS) {
            return;
        }
        $nova = self::reduzir($dados, $tipo, $info, $lado, $tipo, dirname($caminho));
        if ($nova === null) {
            return;
        }
        // Só troca se a foto no disco ainda é a mesma (outro pedido pode ter reduzido, ou o administrativo enviado
        // uma nova) e se a reduzida ficou menor. O rename é atômico: quem abrir junto vê a antiga ou a nova inteira.
        clearstatcache(true, $caminho);
        $agora = @stat($caminho);
        $mesma = $agora !== false && $agora['ino'] === $antes['ino'] && $agora['size'] === $antes['size'] && $agora['mtime'] === $antes['mtime'];
        $menor = (int) filesize($nova) < (int) $antes['size'];
        if (!$mesma || !$menor || !@rename($nova, $caminho)) {
            @unlink($nova);
            if ($mesma && !$menor) {
                @file_put_contents($marca, $assinatura);
            }
        }
    }

    /** O servidor tem o que precisa para abrir, reduzir e, no JPG, endireitar a foto. */
    private static function podeReduzir(string $mime): bool
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
            return false;
        }
        // Sem a extensão exif não dá para saber o giro: regravar a foto a deixaria deitada para sempre.
        return $mime !== 'image/jpeg' || function_exists('exif_read_data');
    }

    /**
     * Devolve o caminho de um arquivo temporário com a imagem reduzida, ou null se não deu para ler.
     *
     * @param array{0: int, 1: int} $info medidas lidas de $dados
     */
    private static function reduzir(string $dados, string $mime, array $info, int $lado, string $saida, ?string $pasta): ?string
    {
        [$largura, $altura] = [(int) $info[0], (int) $info[1]];
        // A foto aberta ocupa uns 4 bytes por pixel. O giro é feito depois de reduzir, na imagem pequena.
        $precisa = $largura * $altura * 5 + $lado * $lado * 8 + 32 * 1024 * 1024;
        if (self::limiteMemoria() < $precisa) {
            if ($precisa > self::MAX_MEMORIA) {
                return null;
            }
            @ini_set('memory_limit', (string) $precisa);
            if (self::limiteMemoria() < $precisa) {
                return null;
            }
        }
        if ($mime === 'image/webp' && !function_exists('imagecreatefromwebp')) {
            return null;
        }
        $imagem = @imagecreatefromstring($dados);
        if ($imagem === false) {
            return null;
        }
        $paleta = !imageistruecolor($imagem);
        $largura = imagesx($imagem);
        $altura = imagesy($imagem);
        $fator = min(1.0, $lado / max($largura, $altura));
        $novaLargura = max(1, (int) round($largura * $fator));
        $novaAltura = max(1, (int) round($altura * $fator));
        $destino = imagecreatetruecolor($novaLargura, $novaAltura);
        if ($saida === 'image/jpeg') {
            // JPG não tem transparência: o fundo transparente de um PNG vira branco, e não preto.
            imagefill($destino, 0, 0, (int) imagecolorallocate($destino, 255, 255, 255));
        } else {
            imagealphablending($destino, false);
            imagesavealpha($destino, true);
        }
        imagecopyresampled($destino, $imagem, 0, 0, 0, 0, $novaLargura, $novaAltura, $largura, $altura);
        imagedestroy($imagem);
        if ($mime === 'image/jpeg') {
            $destino = self::endireitar($destino, self::orientacao($dados));
        }
        if ($saida === 'image/png' && $paleta) {
            // Desenho em PNG de poucas cores continua com paleta; em cores cheias ficaria maior que o original.
            imagetruecolortopalette($destino, false, 256);
        }

        $tmp = tempnam($pasta ?? sys_get_temp_dir(), 'opfoto');
        if ($tmp === false) {
            imagedestroy($destino);

            return null;
        }
        $ok = match ($saida) {
            'image/png' => imagepng($destino, $tmp, 9),
            'image/webp' => function_exists('imagewebp') && imagewebp($destino, $tmp, self::QUALIDADE),
            default => imagejpeg($destino, $tmp, self::QUALIDADE),
        };
        imagedestroy($destino);
        if (!$ok) {
            @unlink($tmp);

            return null;
        }
        // O tempnam cria só para o dono; a foto guardada fica com a permissão de antes (leitura para todos).
        @chmod($tmp, 0644);

        return $tmp;
    }

    /** Giro anotado pela câmera no EXIF do JPG (1 = de pé, como está). */
    private static function orientacao(string $dados): int
    {
        $fluxo = fopen('php://memory', 'r+b');
        if ($fluxo === false) {
            return 1;
        }
        fwrite($fluxo, $dados);
        rewind($fluxo);
        $exif = @exif_read_data($fluxo);
        fclose($fluxo);

        return is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
    }

    /** A câmera grava a foto deitada e anota o giro no EXIF. A foto reduzida já sai de pé. */
    private static function endireitar(GdImage $imagem, int $orientacao): GdImage
    {
        $giro = match ($orientacao) {
            3, 4 => 180,
            6, 7 => -90,
            5, 8 => 90,
            default => 0,
        };
        if (in_array($orientacao, [2, 4, 5, 7], true)) {
            imageflip($imagem, IMG_FLIP_HORIZONTAL);
        }
        if ($giro !== 0) {
            $girada = imagerotate($imagem, $giro, 0);
            if ($girada !== false) {
                imagedestroy($imagem);
                $imagem = $girada;
            }
        }

        return $imagem;
    }

    private static function limiteMemoria(): int
    {
        $valor = trim((string) ini_get('memory_limit'));
        if ($valor === '' || $valor === '-1') {
            return PHP_INT_MAX;
        }
        $numero = (int) $valor;

        return match (strtolower(substr($valor, -1))) {
            'g' => $numero * 1024 * 1024 * 1024,
            'm' => $numero * 1024 * 1024,
            'k' => $numero * 1024,
            default => $numero,
        };
    }
}
