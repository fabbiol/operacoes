<?php
$situacoes = ['enviado' => ['Enviado', 'status-liberado'], 'trocado' => ['Trocado', 'status-concluido'], 'cancelado' => ['Cancelado', 'status-cancelado']];
[$rotulo, $classe] = $situacoes[$pedido['situacao']] ?? [$pedido['situacao'], ''];
?>
<section class="cabeca">
    <div>
        <p class="olho">Pedido de peças</p>
        <h1><?= e((string) $pedido['maquina']) ?></h1>
    </div>
    <span class="selo <?= e($classe) ?>"><?= e($rotulo) ?></span>
</section>
<p class="meta">
    <?= e(quando((string) $pedido['criado_em'])) ?> · pedido por <?= e((string) $pedido['pedido_por']) ?>
    · horímetro <?= e(Horimetro::texto($pedido['horimetro'] !== null ? (float) $pedido['horimetro'] : null)) ?>
    · confirmado com <?= e((string) ($pedido['confirmado_com'] ?? '') !== '' ? (string) $pedido['confirmado_com'] : 'ninguém') ?>
</p>
<?php if ($pedido['situacao'] === 'trocado'): ?>
    <p class="meta">Trocado em <?= e(quando((string) $pedido['trocado_em'])) ?> por <?= e((string) $pedido['trocado_por']) ?><?php if ($pedido['trocado_horimetro'] !== null): ?>, horímetro <?= e(Horimetro::texto((float) $pedido['trocado_horimetro'])) ?><?php endif; ?></p>
<?php endif; ?>
<div class="tabela-rolagem">
    <table class="tabela">
        <thead><tr><th>Peça</th><th>Marca e código</th><th>Qtd.</th></tr></thead>
        <tbody>
            <?php foreach ($pedido['itens'] as $item): ?>
                <tr>
                    <td><?= e((string) $item['funcao']) ?></td>
                    <td><?= e(trim((string) $item['marca'] . ' ' . $item['codigo'])) ?></td>
                    <td class="numero"><?= (int) $item['quantidade'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php if ((string) ($pedido['observacoes'] ?? '') !== ''): ?>
    <p class="nota">Obs.: <?= e((string) $pedido['observacoes']) ?></p>
<?php endif; ?>
<section class="bloco">
    <h2>Mensagem para o Compras</h2>
    <pre class="nota"><?= e($mensagem) ?></pre>
    <div class="botoes">
        <a class="btn primario" href="https://wa.me/?text=<?= e(rawurlencode($mensagem)) ?>" target="_blank" rel="noopener">Enviar para o Compras</a>
    </div>
    <?php if ($pedido['situacao'] === 'enviado'): ?>
        <div class="botoes">
            <form method="post" action="<?= e(url('pedidos-pecas/' . $pedido['id'] . '/trocado')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn secundario">Peças trocadas</button>
            </form>
            <form method="post" action="<?= e(url('pedidos-pecas/' . $pedido['id'] . '/cancelar')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn perigo">Cancelar pedido</button>
            </form>
        </div>
    <?php endif; ?>
</section>
<div class="botoes">
    <a class="btn secundario" href="<?= e(url('cadastros/' . $chave . '/' . $pedido['maquina_id'] . '#pecas')) ?>">Ficha da máquina</a>
    <a class="btn secundario" href="<?= e(url('pedidos-pecas')) ?>">Todos os pedidos</a>
</div>
