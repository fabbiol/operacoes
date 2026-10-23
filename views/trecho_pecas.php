<?php
// Peças da máquina e pedidos ao Compras, na ficha. Recebe $tipo, $item, $pecas e $pedidosPecas do index.php.
$basePecas = 'cadastros/' . $tipo['chave'] . '/' . $item['id'] . '/pecas';
$ativas = array_filter($pecas, static fn (array $p): bool => (int) $p['ativo'] === 1);
$desativadas = array_filter($pecas, static fn (array $p): bool => (int) $p['ativo'] !== 1);
$situacoes = ['enviado' => ['Enviado', 'status-liberado'], 'trocado' => ['Trocado', 'status-concluido'], 'cancelado' => ['Cancelado', 'status-cancelado']];
?>
<section class="bloco horimetro" id="pecas">
    <h2>Peças</h2>
    <?php if ($ativas === []): ?>
        <p class="vazio">Nenhuma peça cadastrada nesta máquina.</p>
    <?php else: ?>
        <ul class="cadastro">
            <?php foreach ($ativas as $peca): ?>
                <li>
                    <?php if (!empty($peca['foto'])): ?>
                        <img class="miniatura" src="<?= e(url($basePecas . '/' . $peca['id'] . '/foto') . '?v=' . Fotos::versao((string) $peca['foto'])) ?>" alt="Foto: <?= e($peca['funcao']) ?>" loading="lazy" decoding="async">
                    <?php endif; ?>
                    <span class="cadastro-texto">
                        <strong><?= e($peca['funcao']) ?></strong>
                        <small><?= e(trim((string) $peca['marca'] . ' ' . $peca['codigo'])) ?> · <?= (int) $peca['quantidade'] ?> un<?php if ($peca['ultima_troca'] !== null): ?>
                            · última troca em <?= e(quando((string) $peca['ultima_troca']['trocado_em'])) ?><?php if ($peca['ultima_troca']['trocado_horimetro'] !== null): ?>, <?= e(Horimetro::texto((float) $peca['ultima_troca']['trocado_horimetro'])) ?><?php endif; ?>
                        <?php endif; ?></small>
                    </span>
                    <details class="anular">
                        <summary>Editar</summary>
                        <form method="post" enctype="multipart/form-data" action="<?= e(url($basePecas . '/' . $peca['id'])) ?>" class="formulario">
                            <?= csrf_field() ?>
                            <?php $valores = $peca; require __DIR__ . '/trecho_peca_campos.php'; ?>
                            <?php if (!empty($peca['foto'])): ?>
                                <label class="check">
                                    <input type="checkbox" name="remover_foto" value="1">
                                    <span>Remover foto</span>
                                </label>
                            <?php endif; ?>
                            <button type="submit" class="btn primario">Salvar peça</button>
                        </form>
                        <form method="post" action="<?= e(url($basePecas . '/' . $peca['id'] . '/ativo')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn secundario">Desativar peça</button>
                        </form>
                    </details>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if ($ativas !== []): ?>
        <p><a class="btn primario" href="<?= e(url('cadastros/' . $tipo['chave'] . '/' . $item['id'] . '/pedidos/novo')) ?>">Novo pedido de peças</a></p>
    <?php endif; ?>
    <details class="anular">
        <summary>Incluir peça</summary>
        <form method="post" enctype="multipart/form-data" action="<?= e(url($basePecas)) ?>" class="formulario">
            <?= csrf_field() ?>
            <?php $valores = ['quantidade' => 1]; require __DIR__ . '/trecho_peca_campos.php'; ?>
            <button type="submit" class="btn primario">Incluir peça</button>
        </form>
    </details>
    <?php if ($desativadas !== []): ?>
        <details>
            <summary>Peças desativadas (<?= count($desativadas) ?>)</summary>
            <ul class="cadastro">
                <?php foreach ($desativadas as $peca): ?>
                    <li class="inativo">
                        <span class="cadastro-texto">
                            <strong><?= e($peca['funcao']) ?></strong>
                            <small><?= e(trim((string) $peca['marca'] . ' ' . $peca['codigo'])) ?></small>
                        </span>
                        <form method="post" action="<?= e(url($basePecas . '/' . $peca['id'] . '/ativo')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn secundario">Ativar</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        </details>
    <?php endif; ?>
    <?php if (($pedidosPecas ?? []) !== []): ?>
        <h3>Pedidos de peças</h3>
        <ul class="cadastro">
            <?php foreach ($pedidosPecas as $pedido): ?>
                <?php [$rotulo, $classe] = $situacoes[$pedido['situacao']] ?? [$pedido['situacao'], '']; ?>
                <li>
                    <a href="<?= e(url('pedidos-pecas/' . $pedido['id'])) ?>">
                        <span class="cadastro-texto">
                            <strong><?= e(quando((string) $pedido['criado_em'])) ?></strong>
                            <small><?= e((string) $pedido['resumo']) ?></small>
                        </span>
                        <span class="selo <?= e($classe) ?>"><?= e($rotulo) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
