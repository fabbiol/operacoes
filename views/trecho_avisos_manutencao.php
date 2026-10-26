<?php
// Serviços de manutenção chegando (faltam 100 h ou menos) ou vencidos. No Painel ($verTodos), de todas as máquinas,
// com link para a ficha; no tablet, só da máquina de quem está com ela, com o atalho para registrar a troca.
?>
<section class="manutencao-aviso" aria-label="Manutenção chegando">
    <h2>Manutenção chegando <span><?= count($avisosManutencao) ?></span></h2>
    <ul>
        <?php foreach ($avisosManutencao as $aviso): ?>
            <?php
            $situacaoAviso = $aviso['situacao'];
            $vencida = $situacaoAviso['estado'] === 'vencida';
            $faixa = $vencida ? 0 : (int) ($situacaoAviso['faixa'] ?? 100);
            $quanto = $vencida
                ? ((float) $situacaoAviso['falta'] < 0 ? 'Vencida: passou ' . Horimetro::texto(abs((float) $situacaoAviso['falta'])) : 'Vence agora')
                : 'Faltam ' . Horimetro::texto((float) $situacaoAviso['falta']);
            ?>
            <li class="faixa-<?= $faixa ?>">
                <?php if ($verTodos): ?>
                    <a href="<?= e(url('cadastros/' . $aviso['chave'] . '/' . (int) $aviso['id'] . '#manutencao')) ?>">
                        <strong><?= e((string) $aviso['maquina']) ?></strong>
                        <span><?= e((string) $aviso['nome']) ?></span>
                        <small><?= e($quanto) ?></small>
                    </a>
                <?php else: ?>
                    <span class="manutencao-linha">
                        <strong><?= e((string) $aviso['nome']) ?></strong>
                        <small><?= e($quanto) ?> · próxima com <?= e(Horimetro::texto((float) $situacaoAviso['proxima'])) ?></small>
                    </span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if (!$verTodos): ?>
        <p class="nota">Avise o escritório para pedir as peças. Depois da troca, toque em <a href="<?= e(url('troca')) ?>">Registrar troca</a>.</p>
    <?php endif; ?>
</section>
