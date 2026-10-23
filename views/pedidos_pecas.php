<?php
$situacoes = ['enviado' => ['Enviado', 'status-liberado'], 'trocado' => ['Trocado', 'status-concluido'], 'cancelado' => ['Cancelado', 'status-cancelado']];
?>
<section class="cabeca">
    <div>
        <p class="olho">Manutenção</p>
        <h1>Pedidos de peças</h1>
    </div>
</section>
<p class="nota">Para fazer um pedido, abra a ficha da máquina e toque em <strong>Novo pedido de peças</strong>.</p>
<?php if ($pedidos === []): ?>
    <p class="vazio">Nenhum pedido de peças ainda.</p>
<?php else: ?>
    <ul class="cadastro">
        <?php foreach ($pedidos as $pedido): ?>
            <?php [$rotulo, $classe] = $situacoes[$pedido['situacao']] ?? [$pedido['situacao'], '']; ?>
            <li>
                <a href="<?= e(url('pedidos-pecas/' . $pedido['id'])) ?>">
                    <span class="cadastro-texto">
                        <strong><?= e((string) $pedido['maquina']) ?> · <?= e(quando((string) $pedido['criado_em'])) ?></strong>
                        <small><?= e((string) $pedido['resumo']) ?></small>
                    </span>
                    <span class="selo <?= e($classe) ?>"><?= e($rotulo) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<p><a class="btn secundario" href="<?= e(url('manutencao')) ?>">Voltar</a></p>
