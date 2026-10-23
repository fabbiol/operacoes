<?php
// Oficina e ordens de serviço, na ficha. Recebe $tipo, $item, $oficina e $pedidosPecas prontos do index.php.
$baseOficina = 'cadastros/' . $tipo['chave'] . '/' . $item['id'] . '/oficina';
$naOficina = $oficina['atual'];
?>
<section class="bloco horimetro" id="oficina">
    <h2>Oficina</h2>
    <p class="meta">Disponibilidade no mês: <strong class="disponibilidade"><?= e((string) $oficina['disponibilidade']) ?></strong> (100% menos o tempo na oficina, desde o dia 1 até agora)</p>
    <?php if ($naOficina !== null): ?>
        <p><span class="selo status-problema">Na oficina desde <?= e(quando((string) $naOficina['entrada'])) ?></span> <?= e((string) $naOficina['motivo']) ?></p>
        <form method="post" action="<?= e(url($baseOficina . '/liberar')) ?>" class="formulario">
            <?= csrf_field() ?>
            <h3>Liberar da oficina</h3>
            <label class="campo">
                <span>O que foi feito</span>
                <textarea name="servico" rows="3" maxlength="2000" required placeholder="Ex.: trocada a mangueira do hidráulico"></textarea>
            </label>
            <label class="campo">
                <span>Quem fez (opcional)</span>
                <input type="text" name="executor" maxlength="120" placeholder="Ex.: mecânico da casa">
            </label>
            <?php if (($pedidosPecas ?? []) !== []): ?>
                <label class="campo">
                    <span>Peças (opcional)</span>
                    <select name="pedido_id">
                        <option value="">Nenhum pedido de peças</option>
                        <?php foreach ($pedidosPecas as $pedido): ?>
                            <option value="<?= (int) $pedido['id'] ?>"><?= e(quando((string) $pedido['criado_em']) . ' — ' . $pedido['resumo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <label class="campo">
                <span>Custo (opcional)</span>
                <input type="text" name="custo" inputmode="decimal" maxlength="16" placeholder="R$">
            </label>
            <button type="submit" class="btn primario">Liberar da oficina</button>
        </form>
    <?php else: ?>
        <details class="anular">
            <summary>Mandar para a oficina</summary>
            <form method="post" action="<?= e(url($baseOficina)) ?>" class="formulario">
                <?= csrf_field() ?>
                <label class="campo">
                    <span>Motivo</span>
                    <textarea name="motivo" rows="2" maxlength="500" required placeholder="Ex.: vazamento no hidráulico"></textarea>
                </label>
                <?php if ($oficina['problemas'] !== []): ?>
                    <label class="campo">
                        <span>Problema aberto (opcional)</span>
                        <select name="problema_id">
                            <option value="">Nenhum</option>
                            <?php foreach ($oficina['problemas'] as $problema): ?>
                                <option value="<?= (int) $problema['id'] ?>"><?= e((string) $problema['descricao']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="dica">Ao liberar da oficina, o problema escolhido fica resolvido.</small>
                    </label>
                <?php endif; ?>
                <p class="nota">Enquanto estiver na oficina, ninguém consegue assumir a máquina.</p>
                <button type="submit" class="btn primario">Mandar para a oficina</button>
            </form>
        </details>
    <?php endif; ?>
    <?php if ($oficina['historico'] !== []): ?>
        <h3>Ordens de serviço</h3>
        <ul class="cadastro">
            <?php foreach ($oficina['historico'] as $os): ?>
                <li>
                    <span class="cadastro-texto">
                        <strong><?= e(texto_duracao(segundos_entre((string) $os['entrada'], (string) $os['saida']))) ?> · <?= e((string) $os['motivo']) ?></strong>
                        <small>Entrou em <?= e(quando((string) $os['entrada'])) ?> · saiu em <?= e(quando((string) $os['saida'])) ?></small>
                        <small>Feito: <?= e((string) $os['servico']) ?><?php if (($os['executor'] ?? '') !== ''): ?> · por <?= e((string) $os['executor']) ?><?php endif; ?><?php if ($os['custo'] !== null): ?> · R$ <?= e(formatar_quantidade((string) $os['custo'])) ?><?php endif; ?></small>
                        <?php if ($os['pedido_id'] !== null): ?>
                            <small><a href="<?= e(url('pedidos-pecas/' . (int) $os['pedido_id'])) ?>">Pedido de peças</a></small>
                        <?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
