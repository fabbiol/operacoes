<?php
$situacaoDoGrupo = static function (array $grupo): array {
    $principal = $grupo['principal'];
    $pendente = $grupo['pendente'];
    if ($principal['status'] === 'cancelado') {
        return ['Cancelada', 'cancelado'];
    }
    if ($pendente !== null) {
        return match ((string) $pendente['status']) {
            'saldo' => ['Saldo a buscar', 'saldo'],
            'liberado' => ['Na fila do pátio', 'liberado'],
            'carregando' => ['Carregando', 'carregando'],
            default => ['Aguardando autorização', 'aguardando_autorizacao'],
        };
    }
    if ($grupo['encerrado']) {
        return ['Saldo encerrado', 'cancelado'];
    }

    return ['Entregue', 'concluido'];
};
$quantidade = static fn (float $valor, string $unidade): string => formatar_quantidade($valor) . ' ' . unidade_rotulo($unidade);
?>
<section class="cabeca">
    <div>
        <p class="olho">Administrativo</p>
        <h1>Controle de carga</h1>
    </div>
    <form class="busca" method="get" action="<?= e(url('controle')) ?>">
        <a class="btn secundario" href="<?= e(url('controle?dia=' . $anterior)) ?>" aria-label="Dia anterior">‹</a>
        <input type="date" name="dia" value="<?= e($dia) ?>" aria-label="Dia">
        <button type="submit" class="btn secundario">Ver</button>
        <a class="btn secundario" href="<?= e(url('controle?dia=' . $seguinte)) ?>" aria-label="Dia seguinte">›</a>
    </form>
</section>

<section class="vendas-dia">
    <div class="venda">
        <span>Vendido no dia</span>
        <strong>R$ <?= e(formatar_quantidade($vendas['valor'])) ?></strong>
        <small><?= (int) $vendas['notas'] ?> <?= (int) $vendas['notas'] === 1 ? 'nota emitida' : 'notas emitidas' ?><?= $vendas['sem_valor'] > 0 ? ' · ' . (int) $vendas['sem_valor'] . ' sem valor: preencha em Notas do dia' : '' ?></small>
    </div>
    <div class="venda">
        <span>Carregado no dia</span>
        <strong><?php
            $partes = [];
            foreach (['t', 'm3', 'un'] as $unidade) {
                if ($carregado[$unidade] > 0) {
                    $partes[] = $quantidade($carregado[$unidade], $unidade);
                }
            }
            echo e($partes === [] ? '—' : implode(' · ', $partes));
        ?></strong>
        <small><?= (int) $carregado['cargas'] ?> <?= (int) $carregado['cargas'] === 1 ? 'carga' : 'cargas' ?> (cada viagem do fracionado conta)</small>
    </div>
    <div class="venda">
        <span>Saldo a buscar</span>
        <strong><?= count($saldos) ?></strong>
        <small><?= count($saldos) === 1 ? 'nota fracionada esperando o cliente' : 'notas fracionadas esperando o cliente' ?></small>
    </div>
</section>

<section class="bloco">
    <h2>Saldo a buscar</h2>
    <?php if ($saldos === []): ?>
        <p class="vazio">Nenhuma nota com saldo esperando o cliente.</p>
    <?php else: ?>
        <p class="nota">Quando o cliente voltar, toque em <strong>Liberar viagem</strong>: a viagem vai para a fila do pátio, e o operador informa quanto carregou.</p>
        <div class="tabela-rolagem">
            <table class="tabela">
                <thead><tr><th>Cliente</th><th>NF</th><th>Material</th><th>Total</th><th>Carregado</th><th>Saldo</th><th>Viagens</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($saldos as $grupo): ?>
                        <?php $principal = $grupo['principal']; $pendente = $grupo['pendente']; ?>
                        <tr>
                            <td><a href="<?= e(url('ordens/' . $principal['id'])) ?>"><?= e($principal['cliente']) ?></a><small><?= e($principal['ordem']) ?> · <?= e(!empty($principal['nf_emitida_em']) || empty($principal['nf_emitida_data']) ? quando((string) ($principal['nf_emitida_em'] ?? $principal['data'])) : date('d/m/Y', (int) strtotime((string) $principal['nf_emitida_data']))) ?></small></td>
                            <td><?= e($principal['nf']) ?></td>
                            <td><?= e(function_exists('materiais_nomes') ? materiais_nomes($principal) : $principal['material_nome']) ?></td>
                            <td class="numero"><?= e($quantidade($grupo['total'], (string) $principal['unidade'])) ?></td>
                            <td class="numero"><?= e($quantidade($grupo['carregado'], (string) $principal['unidade'])) ?></td>
                            <td class="numero"><strong><?= e($quantidade($grupo['saldo'], (string) $principal['unidade'])) ?></strong></td>
                            <td class="numero"><?= count($grupo['viagens']) - 1 ?></td>
                            <td>
                                <?php if ($pendente !== null && $pendente['status'] === 'saldo'): ?>
                                    <form method="post" action="<?= e(url('ordens/' . $pendente['id'] . '/liberar')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="voltar" value="controle">
                                        <button type="submit" class="btn primario">Liberar viagem</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($pendente !== null && $pendente['status'] !== 'carregando'): ?>
                                    <a class="btn perigo" href="<?= e(url('ordens/' . $pendente['id'] . '/cancelar')) ?>">Encerrar saldo</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="bloco">
    <h2>Notas do dia</h2>
    <?php if ($notas === []): ?>
        <p class="vazio">Nenhuma nota lançada neste dia.</p>
    <?php else: ?>
        <div class="tabela-rolagem">
            <table class="tabela">
                <thead><tr><th>Nota</th><th>Cliente</th><th>Material</th><th>Vendido</th><th>Carregado</th><th>Saldo</th><th>Valor</th><th>Situação</th></tr></thead>
                <tbody>
                    <?php foreach ($notas as $grupo): ?>
                        <?php $principal = $grupo['principal']; [$rotulo, $classe] = $situacaoDoGrupo($grupo); ?>
                        <tr>
                            <td><a href="<?= e(url('ordens/' . $principal['id'])) ?>">NF <?= e($principal['nf']) ?></a><small><?= e($principal['ordem']) ?> · <?= e(hora_curta((string) ($principal['nf_emitida_em'] ?? $principal['data']))) ?><?= (int) $principal['fracionado'] === 1 ? ' · fracionado' : '' ?></small></td>
                            <td><?= e($principal['cliente']) ?><small>Pedido <?= e($principal['pedido']) ?></small></td>
                            <td><?= e(function_exists('materiais_nomes') ? materiais_nomes($principal) : $principal['material_nome']) ?></td>
                            <td class="numero"><?= e($quantidade($grupo['total'], (string) $principal['unidade'])) ?></td>
                            <td class="numero"><?= e($quantidade($grupo['carregado'], (string) $principal['unidade'])) ?></td>
                            <td class="numero"><?= $grupo['saldo'] > 0 && $principal['status'] !== 'cancelado' ? e($quantidade($grupo['saldo'], (string) $principal['unidade'])) : '—' ?></td>
                            <td class="numero">
                                <?php if ($principal['nf_valor'] !== null): ?>
                                    R$ <?= e(formatar_quantidade((string) $principal['nf_valor'])) ?>
                                <?php elseif ($principal['status'] === 'cancelado'): ?>
                                    —
                                <?php else: ?>
                                    <form class="valor-nota" method="post" action="<?= e(url('ordens/' . $principal['id'] . '/valor')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="voltar" value="controle">
                                        <input type="hidden" name="dia" value="<?= e($dia) ?>">
                                        <input type="text" name="nf_valor" inputmode="decimal" maxlength="16" required placeholder="R$ sem valor" aria-label="Valor da NF <?= e($principal['nf']) ?>">
                                        <button type="submit" class="btn secundario">Salvar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td><span class="selo status-<?= e($classe) ?>"><?= e($rotulo) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
