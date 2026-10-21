<?php
// Gera as imagens de teste das fotos na pasta recebida: JPG grande com ruído (grande.jpg), o mesmo com EXIF de giro 6 (deitada.jpg),
// PNG com transparência (transparente.png) e um arquivo quebrado (quebrada.jpg). Precisa da extensão gd.
// Uso: php tests/lib/gerar_imagens.php <pasta>
if (!function_exists('imagecreatetruecolor')) {
    fwrite(STDERR, "extensão gd do PHP ausente\n");
    exit(1);
}
$pasta = $argv[1] ?? '';
if ($pasta === '') {
    fwrite(STDERR, "Uso: php gerar_imagens.php <pasta>\n");
    exit(1);
}
if (!is_dir($pasta) && !mkdir($pasta, 0775, true)) {
    fwrite(STDERR, "não consegui criar $pasta\n");
    exit(1);
}

function ruido(int $w, int $h) {
    mt_srand(42); // sempre as mesmas imagens
    $im = imagecreatetruecolor($w, $h);
    for ($y = 0; $y < $h; $y += 8) {
        for ($x = 0; $x < $w; $x += 8) {
            $c = imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            imagefilledrectangle($im, $x, $y, $x + 7, $y + 7, $c);
        }
    }
    // marca vermelha no canto de cima à esquerda, para conferir o giro
    imagefilledrectangle($im, 0, 0, (int) ($w / 5), (int) ($h / 5), imagecolorallocate($im, 255, 0, 0));

    return $im;
}

imagejpeg(ruido(3264, 2448), "$pasta/grande.jpg", 88);
// insere EXIF Orientation = 6 logo depois do SOI
$jpg = file_get_contents("$pasta/grande.jpg");
$tiff = "MM\x00\x2a\x00\x00\x00\x08" . "\x00\x01" . "\x01\x12\x00\x03\x00\x00\x00\x01\x00\x06\x00\x00" . "\x00\x00\x00\x00";
$app1 = "Exif\x00\x00" . $tiff;
$seg = "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1;
file_put_contents("$pasta/deitada.jpg", substr($jpg, 0, 2) . $seg . substr($jpg, 2));

$png = imagecreatetruecolor(1200, 800);
imagesavealpha($png, true);
imagealphablending($png, false);
imagefill($png, 0, 0, imagecolorallocatealpha($png, 0, 0, 0, 127));
imagefilledellipse($png, 600, 400, 600, 400, imagecolorallocate($png, 0, 0, 255));
imagepng($png, "$pasta/transparente.png");

file_put_contents("$pasta/quebrada.jpg", "\xFF\xD8\xFF\xE0" . str_repeat('x', 5000));
