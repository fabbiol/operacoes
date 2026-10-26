<?php
$falta = $situacao['falta'];
$texto = $situacao['rotulo'];
if ($falta !== null && $falta > 0) {
    $texto .= ' · faltam ' . Horimetro::texto($falta);
} elseif ($falta !== null && $falta < 0) {
    $texto .= ' · passou ' . Horimetro::texto(abs($falta));
} elseif ($situacao['proxima'] !== null && $falta === 0.0) {
    $texto .= ' · vence agora';
}
?>
<span class="selo selo-<?= e($situacao['estado']) ?><?= isset($situacao['faixa']) ? ' faixa-' . (int) $situacao['faixa'] : '' ?>"><?= e($texto) ?></span>
