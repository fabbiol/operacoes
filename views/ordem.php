<section class="cabeca">
    <div>
        <p class="olho"><?= e(quando($ordem['data'])) ?></p>
        <h1><?= e($ordem['ordem']) ?></h1>
    </div>
    <span class="selo status-<?= e($ordem['status']) ?>"><?= e(status_rotulo($ordem['status'])) ?></span>
</section>

<dl class="ficha">
    <div><dt>Cliente</dt><dd><?= e($ordem['cliente']) ?></dd></div>
    <div><dt>Pedido</dt><dd><?= e($ordem['pedido']) ?></dd></div>
    <div><dt>Nota fiscal</dt><dd><?= e($ordem['nf']) ?></dd></div>
    <div><dt>Material</dt><dd><?= e(formatar_quantidade((string) $ordem['quantidade_autorizada'])) ?> <?= e(unidade_rotulo($ordem['unidade'])) ?> · <?= e($ordem['material_nome']) ?></dd></div>
    <div><dt>Pá carregadeira</dt><dd><?= e($ordem['pa_nome'] ?: 'Escolhida ao iniciar') ?></dd></div>
    <div><dt>Operador</dt><dd><?= e($ordem['operador'] ?: '—') ?></dd></div>
</dl>

<ol class="linha-tempo">
    <li class="<?= $ordem['autorizado_em'] ? 'feito' : '' ?>">
        <strong>Autorização</strong>
        <span><?= $ordem['autorizado_em'] ? e(quando($ordem['autorizado_em']) . ' · ' . $ordem['autorizado_por']) : 'Ainda não liberada' ?></span>
    </li>
    <li class="<?= $ordem['inicio_carregamento'] ? 'feito' : '' ?>">
        <strong>Início do carregamento</strong>
        <span><?= $ordem['inicio_carregamento'] ? e(quando($ordem['inicio_carregamento'])) : 'Aguardando o pátio' ?></span>
    </li>
    <li class="<?= $ordem['fim_carregamento'] ? 'feito' : '' ?>">
        <strong>Fim do carregamento</strong>
        <span><?= $ordem['fim_carregamento'] ? e(quando($ordem['fim_carregamento'])) : 'Em aberto' ?></span>
    </li>
    <li class="<?= $ordem['status'] === 'concluido' ? 'feito' : '' ?>">
        <strong>Conferência</strong>
        <span><?= $ordem['status'] === 'concluido' ? 'Concluída' : ($ordem['status'] === 'aguardando_conferencia' ? 'Pronta para conferir' : 'Ainda não enviada') ?></span>
    </li>
</ol>

<?php if ($ordem['observacoes']): ?>
    <section class="bloco">
        <h2>Observações</h2>
        <p class="texto"><?= e($ordem['observacoes']) ?></p>
    </section>
<?php endif; ?>

<div class="botoes">
    <?php if ($ordem['status'] === 'aguardando_autorizacao'): ?>
        <form method="post" action="<?= e(url('ordens/' . $ordem['id'] . '/liberar')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn primario">Liberar ordem</button>
        </form>
    <?php endif; ?>
    <?php if ($ordem['status'] === 'aguardando_conferencia'): ?>
        <form method="post" action="<?= e(url('ordens/' . $ordem['id'] . '/concluir')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn concluir">Concluir</button>
        </form>
    <?php endif; ?>
    <?php if (in_array($ordem['status'], ['aguardando_autorizacao', 'liberado', 'carregando', 'aguardando_conferencia'], true)): ?>
        <a class="btn perigo" href="<?= e(url('ordens/' . $ordem['id'] . '/cancelar')) ?>">Cancelar</a>
    <?php endif; ?>
    <a class="btn secundario" href="<?= e(url('fila')) ?>">Voltar à fila</a>
</div>

<?php if (!empty($editavel) && !empty($formulario)): ?>
    <?php $secao = true; ?>
    <?php extract($formulario, EXTR_SKIP); ?>
    <?php require __DIR__ . '/ordem_campos.php'; ?>
<?php endif; ?>
