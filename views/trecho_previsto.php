<?php
// Venda paga depois (prefeitura): o dia previsto do pagamento, com os botões +15/+30 dias. Recebe $previstoPagamento e
// $basePrevisto (o dia da nota, para os botões; vazio: o formulário tem os campos da nota).
?>
<div class="grade previsto-pagamento" data-base="<?= e((string) ($basePrevisto ?? '')) ?>">
    <label class="campo">
        <span>Pagamento previsto para</span>
        <input type="date" name="nf_previsto" value="<?= e((string) ($previstoPagamento ?? '')) ?>">
        <small class="dica">Só para venda paga depois (ex.: prefeitura). Vazio: pago na hora.</small>
    </label>
    <div class="atalhos-prazo">
        <button type="button" class="btn secundario" data-prazo="15">+15 dias</button>
        <button type="button" class="btn secundario" data-prazo="30">+30 dias</button>
    </div>
</div>
