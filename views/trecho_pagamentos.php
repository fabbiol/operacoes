<?php
// Nota paga em mais de uma forma (Pix + cartão, dinheiro + Pix...): o valor da forma escolhida em "Forma de pagamento"
// e as outras formas, cada uma com o seu valor. A soma precisa bater com o valor da nota. Recebe $linhasPagamento.
$maxFormas = defined('Operacoes::MAX_FORMAS') ? (int) Operacoes::MAX_FORMAS : 4;
$linhasPagamento = is_array($linhasPagamento ?? null) ? $linhasPagamento : [];
$temOutras = false;
foreach ($linhasPagamento as $n => $linha) {
    if ((int) $n > 0 && is_array($linha) && (trim((string) ($linha['forma'] ?? '')) !== '' || trim((string) ($linha['valor'] ?? '')) !== '')) {
        $temOutras = true;
    }
}
?>
<input type="hidden" name="tem_pagamentos" value="1">
<details class="outras-formas"<?= $temOutras ? ' open' : '' ?>>
    <summary>+ Outra forma de pagamento</summary>
    <p class="dica">Para a nota paga em mais de uma forma (ex.: Pix e cartão): informe quanto foi pago em cada uma. A soma precisa bater com o valor da nota.</p>
    <div class="grade">
        <label class="campo">
            <span>Valor pago na forma escolhida acima (R$)</span>
            <input type="text" name="pagamentos[0][valor]" inputmode="decimal" maxlength="16" placeholder="0,00" value="<?= e((string) ($linhasPagamento[0]['valor'] ?? '')) ?>">
        </label>
    </div>
    <?php for ($n = 1; $n < $maxFormas; $n++): ?>
        <?php $linha = is_array($linhasPagamento[$n] ?? null) ? $linhasPagamento[$n] : []; ?>
        <div class="grade">
            <label class="campo">
                <span>Forma <?= $n + 1 ?></span>
                <select name="pagamentos[<?= $n ?>][forma]">
                    <option value="">Nenhuma</option>
                    <?php foreach (formas_pagamento() as $codigo => $rotulo): ?>
                        <option value="<?= e($codigo) ?>" <?= (string) ($linha['forma'] ?? '') === $codigo ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="campo">
                <span>Valor na forma <?= $n + 1 ?> (R$)</span>
                <input type="text" name="pagamentos[<?= $n ?>][valor]" inputmode="decimal" maxlength="16" placeholder="0,00" value="<?= e((string) ($linha['valor'] ?? '')) ?>">
            </label>
        </div>
    <?php endfor; ?>
</details>
