<?php
$abertas = 0;
foreach (['aguardando_autorizacao', 'liberado', 'carregando', 'aguardando_conferencia'] as $chave) {
    $abertas += (int) ($contagens[$chave] ?? 0);
}
$filtros = [
    'abertas' => ['Abertas', $abertas],
    'aguardando_autorizacao' => ['Autorizar', $contagens['aguardando_autorizacao'] ?? 0],
    'liberado' => ['Liberadas', $contagens['liberado'] ?? 0],
    'carregando' => ['Carregando', $contagens['carregando'] ?? 0],
    'aguardando_conferencia' => ['Conferir', $contagens['aguardando_conferencia'] ?? 0],
    'saldo' => ['Saldo a buscar', $contagens['saldo'] ?? 0],
    'concluido' => ['Concluídas', $contagens['concluido'] ?? 0],
    'cancelado' => ['Canceladas', $contagens['cancelado'] ?? 0],
    'todas' => ['Todas', array_sum($contagens)],
];
?>
<section class="cabeca">
    <div>
        <p class="olho">Administrativo</p>
        <h1>Fila de carregamento</h1>
    </div>
    <div class="acoes">
        <a class="btn secundario" href="<?= e(url('patio')) ?>">Abrir o pátio</a>
        <a class="btn primario" href="<?= e(url('ordens/nova')) ?>">Nova ordem</a>
    </div>
</section>

<form class="busca" method="get" action="<?= e(url('fila')) ?>">
    <input type="hidden" name="situacao" value="<?= e($situacao) ?>">
    <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Ordem, cliente, pedido, NF ou pá" aria-label="Buscar">
    <button type="submit" class="btn secundario">Buscar</button>
</form>

<div id="fluxo" data-url="<?= e($fluxoUrl) ?>" data-marca="<?= e($fluxoMarca) ?>">
<nav class="filtros" aria-label="Situação">
    <?php foreach ($filtros as $chave => [$rotulo, $total]): ?>
        <a class="<?= $situacao === $chave ? 'ativo' : '' ?>" href="<?= e(url('fila?situacao=' . $chave . ($busca !== '' ? '&q=' . rawurlencode($busca) : ''))) ?>">
            <?= e($rotulo) ?> <strong><?= (int) $total ?></strong>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($ordens === []): ?>
    <p class="vazio">Nenhuma ordem nessa visão.</p>
<?php else: ?>
    <div class="lista">
        <?php foreach ($ordens as $ordem): ?>
            <a class="cartao" href="<?= e(url('ordens/' . $ordem['id'])) ?>">
                <header>
                    <?php
                    $fotos = [url_foto_pa($ordem), url_foto_pessoa($ordem)];
                    $legenda = (string) $ordem['ordem'];
                    $destaque = true;
                    require __DIR__ . '/trecho_quem.php';
                    ?>
                    <span class="selo status-<?= e($ordem['status']) ?>"><?= e(status_rotulo($ordem['status'])) ?></span>
                </header>
                <p class="cliente"><?= e($ordem['cliente']) ?></p>
                <p class="carga"><?php
                    $quanto = $ordem['quantidade_carregada'] !== null ? (string) $ordem['quantidade_carregada'] : (string) $ordem['quantidade_autorizada'];
                    echo e(((int) ($ordem['fracionado'] ?? 0) === 1 && $ordem['quantidade_carregada'] === null ? 'Saldo ' : '') . formatar_quantidade($quanto) . ' ' . unidade_rotulo($ordem['unidade']) . ' · ' . $ordem['material_nome']
                        . (function_exists('outros_materiais_texto') ? outros_materiais_texto($ordem) : ''));
                ?></p>
                <?php if ((int) ($ordem['fracionado'] ?? 0) === 1): ?>
                    <p class="meta"><span class="selo fracionado">Fracionado · viagem <?= (int) ($ordem['viagem'] ?? 1) ?></span></p>
                <?php endif; ?>
                <p class="meta">Pedido <?= e($ordem['pedido']) ?> · NF <?= e($ordem['nf']) ?><?php if ($ordem['pa_nome']): ?> · <?= e($ordem['pa_nome']) ?><?php endif; ?> · <?= e(quando($ordem['data'])) ?></p>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
</div>
