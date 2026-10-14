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
        $tmp = tempnam(sys_get_temp_dir(), 'opfoto');
        if ($tmp === false || !move_uploaded_file($origem, $tmp)) {
            throw new InvalidArgumentException('Não foi possível receber a foto. Tente outra imagem.');
        }

        if (!self::temGd()) {
            // Sem a extensão de imagem no servidor, a foto fica como veio.
            return ['tmp' => $tmp, 'ext' => $extensoes[$mime]];
        }
        $reduzida = self::reduzir($tmp, $mime, $lado, 'image/jpeg');
        unlink($tmp);
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
        if (!self::temGd()) {
            return;
        }
        // Já está no tamanho: não há o que reduzir, e a conta não se repete a cada abertura.
        $info = @getimagesize($caminho);
        if ($info !== false && max($info[0], $info[1]) <= $lado) {
            return;
        }
        $nova = self::reduzir($caminho, $tipo, $lado, $tipo, dirname($caminho));
        if ($nova === null) {
            return;
        }
        // Só troca se ficou menor. A troca por rename é atômica: quem abrir junto vê a antiga ou a nova inteira.
        if ((int) filesize($nova) >= (int) filesize($caminho) || !@rename($nova, $caminho)) {
            @unlink($nova);
        }
    }

    private static function temGd(): bool
    {
        return function_exists('imagecreatefromjpeg') && function_exists('imagejpeg');
    }

    /** Devolve o caminho de um arquivo temporário com a imagem reduzida, ou null se não deu para ler. */
    private static function reduzir(string $origem, string $mime, int $lado, string $saida, ?string $pasta = null): ?string
    {
        $info = @getimagesize($origem);
        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            return null;
        }
        // Uma foto de 12 megapixels ocupa uns 50 MB aberta. Sobe o limite só para esta conta.
        $precisa = (int) ($info[0] * $info[1] * 5) + 32 * 1024 * 1024;
        if (self::limiteMemoria() < $precisa) {
            @ini_set('memory_limit', (string) $precisa);
        }
        $imagem = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($origem),
            'image/png' => @imagecreatefrompng($origem),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($origem) : false,
            default => false,
        };
        if ($imagem === false) {
            return null;
        }
        if ($mime === 'image/jpeg') {
            $imagem = self::endireitar($imagem, $origem);
        }

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

        return $tmp;
    }

    /** A câmera grava a foto deitada e anota o giro no EXIF. A foto reduzida já sai de pé. */
    private static function endireitar(GdImage $imagem, string $origem): GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $imagem;
        }
        $exif = @exif_read_data($origem);
        $orientacao = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
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
