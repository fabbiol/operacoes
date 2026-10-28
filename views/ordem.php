<section class="cabeca">
    <div>
        <p class="olho"><?= e(quando($ordem['data'])) ?></p>
        <h1><?= e($ordem['ordem']) ?></h1>
    </div>
    <span class="selo status-<?= e($ordem['status']) ?>"><?= e(status_rotulo($ordem['status'])) ?></span>
</section>

<dl class="ficha">
    <div><dt>Cliente</dt><dd><?= e($ordem['cliente']) ?></dd></div>
    <?php if (!empty($documento)): ?>
        <div><dt>CNPJ/CPF do cliente</dt><dd><?= e($documento) ?></dd></div>
    <?php endif; ?>
    <div><dt>Pedido</dt><dd><?= e($ordem['pedido']) ?></dd></div>
    <div><dt>Nota fiscal</dt><dd><?= e($ordem['nf']) ?><?php if (!empty($ordem['nf_emitida_em'])): ?> · emitida em <?= e(quando((string) $ordem['nf_emitida_em'])) ?><?php elseif (!empty($ordem['nf_emitida_data'])): ?> · emitida em <?= e(date('d/m/Y', (int) strtotime((string) $ordem['nf_emitida_data']))) ?><?php endif; ?><?php if (!empty($ordem['nf_arquivo'])): ?> · <a href="<?= e(url('ordens/' . $ordem['id'] . '/nota')) ?>" target="_blank" rel="noopener">PDF da nota</a><?php endif; ?></dd></div>
    <div><dt>Material</dt><dd><?php
        $fracionado = (int) ($ordem['fracionado'] ?? 0) === 1;
        $rotuloQuantidade = $fracionado && (int) ($ordem['ordem_principal_id'] ?? 0) > 0 ? 'saldo ' : '';
        echo e($rotuloQuantidade . formatar_quantidade((string) $ordem['quantidade_autorizada']) . ' ' . unidade_rotulo($ordem['unidade']) . ' · ' . $ordem['material_nome']
            . (function_exists('outros_materiais_texto') ? outros_materiais_texto($ordem) : ''));
        if ($ordem['quantidade_carregada'] !== null) {
            echo e(' · carregado nesta viagem: ' . formatar_quantidade((string) $ordem['quantidade_carregada']) . ' ' . unidade_rotulo($ordem['unidade']));
        }
    ?></dd></div>
    <?php if ($ordem['nf_valor'] !== null): ?>
        <div><dt>Valor da nota</dt><dd>R$ <?= e(formatar_quantidade((string) $ordem['nf_valor'])) ?></dd></div>
    <?php endif; ?>
    <?php if (!empty($ordem['nf_pagamento'])): ?>
        <div><dt>Forma de pagamento</dt><dd><?= e((function_exists('formas_pagamento') ? formas_pagamento() : [])[$ordem['nf_pagamento']] ?? (string) $ordem['nf_pagamento']) ?></dd></div>
    <?php endif; ?>
    <div><dt><?= (int) ($ordem['retroescavadeira_id'] ?? 0) > 0 ? 'Escavadeira hidráulica' : 'Pá carregadeira' ?></dt><dd><?= e($ordem['pa_nome'] ?: 'Escolhida ao iniciar') ?></dd></div>
    <div><dt>Operador</dt><dd><?= e($ordem['operador'] ?: '—') ?></dd></div>
</dl>

<ol class="linha-tempo">
    <?php if (!empty($ordem['nf_emitida_em'])): ?>
        <li class="feito">
            <strong>Nota fiscal emitida</strong>
            <span><?= e(quando((string) $ordem['nf_emitida_em'])) ?></span>
        </li>
    <?php endif; ?>
    <li class="<?= $ordem['autorizado_em'] ? 'feito' : '' ?>">
        <strong>Autorização</strong>
        <span><?= $ordem['autorizado_em'] ? e(quando($ordem['autorizado_em']) . ' · ' . $ordem['autorizado_por']) : 'Ainda não liberada' ?></span>
    </li>
    <li class="<?= $ordem['inicio_carregamento'] ? 'feito' : '' ?>">
        <strong>Início do carregamento</strong>
        <span><?= $ordem['inicio_carregamento'] ? e(quando($ordem['inicio_carregamento'])) : 'Aguardando o pátio' ?><?php
            if (!empty($ordem['nf_emitida_em']) && $ordem['inicio_carregamento']) {
                $espera = strtotime((string) $ordem['inicio_carregamento']) - strtotime((string) $ordem['nf_emitida_em']);
                if ($espera >= 0) {
                    echo e(' · ' . texto_duracao($espera) . ' depois da nota');
                }
            }
        ?></span>
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

<?php if (!empty($grupo)): ?>
    <section class="bloco viagens">
        <h2>Fracionado: viagens desta nota</h2>
        <?php $unidade = unidade_rotulo((string) $ordem['unidade']); ?>
        <dl class="ficha">
            <div><dt>Total da nota</dt><dd><?= e(formatar_quantidade($grupo['total']) . ' ' . $unidade) ?></dd></div>
            <div><dt>Já carregado</dt><dd><?= e(formatar_quantidade($grupo['carregado']) . ' ' . $unidade) ?></dd></div>
            <div><dt>Saldo</dt><dd><?= e(formatar_quantidade($grupo['saldo']) . ' ' . $unidade) ?><?= $grupo['encerrado'] ? ' (encerrado)' : '' ?></dd></div>
        </dl>
        <div class="tabela-rolagem">
            <table class="tabela">
                <thead><tr><th>Viagem</th><th>Ordem</th><th>Situação</th><th>Carregado</th><th>Fim</th><th>Operador</th></tr></thead>
                <tbody>
                    <?php foreach ($grupo['viagens'] as $viagem): ?>
                        <tr class="<?= (int) $viagem['id'] === (int) $ordem['id'] ? 'atual' : '' ?>">
                            <td><?= (int) $viagem['viagem'] ?></td>
                            <td><a href="<?= e(url('ordens/' . $viagem['id'])) ?>"><?= e($viagem['ordem']) ?></a></td>
                            <td><span class="selo status-<?= e($viagem['status']) ?>"><?= e(status_rotulo($viagem['status'])) ?></span></td>
                            <td><?= $viagem['quantidade_carregada'] !== null ? e(formatar_quantidade((string) $viagem['quantidade_carregada']) . ' ' . $unidade) : '—' ?></td>
                            <td><?= $viagem['fim_carregamento'] ? e(quando((string) $viagem['fim_carregamento'])) : '—' ?></td>
                            <td><?= e($viagem['operador'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if ($ordem['observacoes']): ?>
    <section class="bloco">
        <h2>Observações</h2>
        <p class="texto"><?= e($ordem['observacoes']) ?></p>
    </section>
<?php endif; ?>

<?php
// Viagem 1 de um fracionado com outra viagem ainda aberta: o saldo se encerra na viagem que falta, não aqui.
$pendenteOutra = null;
if (!empty($grupo) && (int) ($ordem['ordem_principal_id'] ?? 0) === 0 && $grupo['pendente'] !== null
    && (int) $grupo['pendente']['id'] !== (int) $ordem['id']) {
    $pendenteOutra = $grupo['pendente'];
}
?>
<div class="botoes">
    <?php if ($ordem['status'] === 'aguardando_autorizacao' || $ordem['status'] === 'saldo'): ?>
        <form method="post" action="<?= e(url('ordens/' . $ordem['id'] . '/liberar')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn primario"><?= $ordem['status'] === 'saldo' ? 'O cliente voltou: liberar viagem' : 'Liberar ordem' ?></button>
        </form>
    <?php endif; ?>
    <?php if ($ordem['status'] === 'aguardando_conferencia'): ?>
        <form method="post" action="<?= e(url('ordens/' . $ordem['id'] . '/concluir')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn concluir">Concluir</button>
        </form>
    <?php endif; ?>
    <?php if ($ordem['status'] === 'liberado' && (int) ($ordem['ordem_principal_id'] ?? 0) > 0): ?>
        <form method="post" action="<?= e(url('ordens/' . $ordem['id'] . '/saldo')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn secundario">O cliente não veio: voltar para Saldo a buscar</button>
        </form>
    <?php endif; ?>
    <?php if ($pendenteOutra !== null): ?>
        <?php if ($pendenteOutra['status'] !== 'carregando'): ?>
            <a class="btn perigo" href="<?= e(url('ordens/' . $pendenteOutra['id'] . '/cancelar')) ?>">Encerrar saldo (viagem <?= e($pendenteOutra['ordem']) ?>)</a>
        <?php endif; ?>
    <?php elseif (in_array($ordem['status'], ['aguardando_autorizacao', 'liberado', 'carregando', 'aguardando_conferencia', 'saldo'], true)): ?>
        <a class="btn perigo" href="<?= e(url('ordens/' . $ordem['id'] . '/cancelar')) ?>"><?= $ordem['status'] === 'saldo' ? 'Encerrar saldo' : 'Cancelar' ?></a>
    <?php endif; ?>
    <a class="btn secundario" href="<?= e(url('fila')) ?>">Voltar à fila</a>
</div>

<?php if ($ordem['status'] !== 'cancelado' && (int) ($ordem['ordem_principal_id'] ?? 0) === 0 && (empty($editavel) || !empty($formulario['travado']))): ?>
    <form class="valor-nota bloco" method="post" action="<?= e(url('ordens/' . $ordem['id'] . '/valor')) ?>">
        <?= csrf_field() ?>
        <label class="campo">
            <span>Valor da nota (R$)</span>
            <input type="text" name="nf_valor" inputmode="decimal" maxlength="16" required placeholder="Ex.: 476,40" value="<?= $ordem['nf_valor'] !== null ? e(formatar_quantidade((string) $ordem['nf_valor'])) : '' ?>">
        </label>
        <label class="campo">
            <span>Forma de pagamento</span>
            <select name="nf_pagamento">
                <option value="">Sem forma</option>
                <?php foreach ((function_exists('formas_pagamento') ? formas_pagamento() : []) as $codigo => $rotulo): ?>
                    <option value="<?= e($codigo) ?>" <?= ($ordem['nf_pagamento'] ?? '') === $codigo ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="btn secundario"><?= $ordem['nf_valor'] !== null ? 'Corrigir valor' : 'Salvar valor' ?></button>
        <p class="nota"><?= $ordem['nf_valor'] !== null ? 'Se o valor estiver errado, corrija aqui.' : 'Esta nota está sem valor e não entra no vendido do dia. Preencha o valor total da nota.' ?></p>
    </form>
<?php endif; ?>

<?php if (!empty($finpro)): ?>
    <section class="bloco finpro">
        <p class="nota"><?= e($finpro['texto']) ?></p>
        <?php if ($finpro['reenviar']): ?>
            <form method="post" action="<?= e(url('ordens/' . $ordem['id'] . '/finpro')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn secundario">Reenviar ao Fin Pro</button>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if (in_array($ordem['status'], ['aguardando_conferencia', 'concluido'], true) && (!function_exists('outros_materiais') || outros_materiais($ordem) === [])): ?>
    <details class="corrigir-carregado bloco">
        <summary>Corrigir a quantidade carregada</summary>
        <form method="post" action="<?= e(url('ordens/' . $ordem['id'] . '/carregado')) ?>" class="formulario">
            <?= csrf_field() ?>
            <label class="campo">
                <span>Quanto saiu nesta viagem (<?= e(unidade_rotulo((string) $ordem['unidade'])) ?>)</span>
                <input type="text" name="quantidade" inputmode="decimal" maxlength="16" required placeholder="0,00" value="<?= e(formatar_quantidade((string) ($ordem['quantidade_carregada'] ?? $ordem['quantidade_autorizada']))) ?>">
            </label>
            <p class="nota">Use quando o operador digitou errado ou o cliente levou só uma parte. O saldo da nota é refeito: se faltar material, a próxima viagem fica em Saldo a buscar.</p>
            <button type="submit" class="btn secundario">Salvar quantidade</button>
        </form>
    </details>
<?php endif; ?>

<?php if (!empty($editavel) && !empty($formulario)): ?>
    <?php $secao = true; ?>
    <?php extract($formulario, EXTR_SKIP); ?>
    <?php require __DIR__ . '/ordem_campos.php'; ?>
<?php endif; ?>
