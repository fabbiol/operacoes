<section class="bloco horimetro">
    <h2>Horímetro</h2>
    <p class="meta">Atual: <?= e(Horimetro::texto($horimetro['atual'])) ?></p>
    <?php $posse = $horimetro['posse'] ?? null; ?>
    <?php if ($posse !== null): ?>
        <div class="posse">
            <p class="meta">Com <strong><?= e((string) $posse['usuario']) ?></strong> desde <?= e(quando((string) $posse['iniciado_em'])) ?><?php if ($posse['entrada'] !== null): ?> · entrou com <?= e(Horimetro::texto((float) $posse['entrada'])) ?><?php else: ?> · sem leitura de entrada<?php endif; ?></p>
            <details class="anular">
                <summary>Liberar máquina</summary>
                <form method="post" action="<?= e(url('cadastros/' . $tipo['chave'] . '/' . $item['id'] . '/liberar')) ?>" class="formulario">
                    <?= csrf_field() ?>
                    <p class="nota">Use quando a pessoa foi embora sem soltar. Com a leitura do painel, o turno dela é gravado.</p>
                    <label class="campo">
                        <span>Horímetro agora (opcional)</span>
                        <input type="text" name="leitura" inputmode="decimal" maxlength="10" placeholder="h">
                    </label>
                    <button type="submit" class="btn perigo">Liberar a máquina</button>
                </form>
            </details>
        </div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('cadastros/' . $tipo['chave'] . '/' . $item['id'] . '/horimetro')) ?>" class="formulario">
        <?= csrf_field() ?>
        <label class="campo">
            <span>Nova leitura</span>
            <input type="text" name="leitura" inputmode="decimal" maxlength="10" required placeholder="h">
        </label>
        <button type="submit" class="btn primario">Lançar leitura</button>
    </form>
    <?php if ($horimetro['historico'] !== []): ?>
        <ul class="cadastro">
            <?php foreach ($horimetro['historico'] as $leitura): ?>
                <?php $anulada = !empty($leitura['anulado_em']); ?>
                <li class="<?= $anulada ? 'inativo' : '' ?>">
                    <span class="cadastro-texto">
                        <strong><?= $anulada ? '<s>' . e(Horimetro::texto((float) $leitura['leitura'])) . '</s> anulada' : e(Horimetro::texto((float) $leitura['leitura'])) ?></strong>
                        <small><?= e(quando((string) $leitura['lido_em'])) ?> · <?= e((string) $leitura['usuario']) ?><?php if ($anulada): ?>
                            · anulada por <?= e((string) $leitura['anulado_por']) ?> em <?= e(quando((string) $leitura['anulado_em'])) ?>: <?= e((string) $leitura['anulado_motivo']) ?>
                        <?php endif; ?></small>
                    </span>
                    <?php if (!$anulada && isset($leitura['id'])): ?>
                        <details class="anular">
                            <summary>Anular</summary>
                            <form method="post" action="<?= e(url('cadastros/' . $tipo['chave'] . '/' . $item['id'] . '/horimetro/' . (int) $leitura['id'] . '/anular')) ?>" class="formulario">
                                <?= csrf_field() ?>
                                <label class="campo">
                                    <span>Motivo</span>
                                    <input type="text" name="motivo" maxlength="200" required placeholder="Ex.: digitado errado">
                                </label>
                                <?php if ($posse !== null && (int) ($posse['entrada_id'] ?? 0) === (int) $leitura['id']): ?>
                                    <p class="nota">Esta é a entrada de <?= e((string) $posse['usuario']) ?>, que está com a máquina. Sem a leitura certa, o turno dela não é somado.</p>
                                    <label class="campo">
                                        <span>Leitura certa da entrada (opcional)</span>
                                        <input type="text" name="correta" inputmode="decimal" maxlength="10" placeholder="h">
                                    </label>
                                <?php endif; ?>
                                <button type="submit" class="btn perigo">Anular esta leitura</button>
                            </form>
                        </details>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if (($horimetro['turnos'] ?? []) !== []): ?>
        <h3>Turnos</h3>
        <ul class="cadastro">
            <?php foreach ($horimetro['turnos'] as $turno): ?>
                <li>
                    <span class="cadastro-texto">
                        <strong><?= e(Horimetro::texto((float) $turno['horas'])) ?></strong>
                        <small><?= e((string) $turno['usuario']) ?> · entrou <?= e(Horimetro::texto((float) $turno['entrada'])) ?> · saiu <?= e(Horimetro::texto((float) $turno['saida'])) ?> · <?= e(quando((string) $turno['encerrado_em'])) ?></small>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="bloco horimetro">
    <h2>Manutenção</h2>
    <?php if ($horimetro['planos'] === []): ?>
        <p class="vazio">Nenhum serviço nesta máquina.</p>
    <?php else: ?>
        <div class="lista">
            <?php foreach ($horimetro['planos'] as $plano): ?>
                <article class="cartao">
                    <header>
                        <strong><?= e($plano['nome']) ?></strong>
                        <?php $situacao = $plano['situacao']; require __DIR__ . '/trecho_situacao.php'; ?>
                    </header>
                    <p class="meta">A cada <?= e(Horimetro::texto($plano['intervalo_h'])) ?>
                        <?php if ($plano['ultima'] !== null): ?> · última em <?= e(Horimetro::texto($plano['ultima'])) ?><?php endif; ?>
                    </p>
                    <?php if ((int) $plano['ativo'] === 1 && $horimetro['atual'] !== null): ?>
                        <form method="post" action="<?= e(url('cadastros/' . $tipo['chave'] . '/' . $item['id'] . '/planos/' . $plano['id'] . '/feito')) ?>" class="formulario">
                            <?= csrf_field() ?>
                            <label class="campo">
                                <span>Horímetro do serviço</span>
                                <input type="text" name="horimetro" inputmode="decimal" maxlength="10" value="<?= e(number_format((float) $horimetro['atual'], 1, ',', '')) ?>">
                            </label>
                            <label class="campo">
                                <span>Observação</span>
                                <input type="text" name="observacoes" maxlength="4000">
                            </label>
                            <button type="submit" class="btn primario">Serviço feito</button>
                        </form>
                    <?php elseif ((int) $plano['ativo'] === 1): ?>
                        <p class="nota">Lance o horímetro antes de registrar o serviço.</p>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('cadastros/' . $tipo['chave'] . '/' . $item['id'] . '/planos/' . $plano['id'] . '/ativo')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn secundario"><?= (int) $plano['ativo'] === 1 ? 'Desativar' : 'Ativar' ?></button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('cadastros/' . $tipo['chave'] . '/' . $item['id'] . '/planos')) ?>" class="formulario">
        <?= csrf_field() ?>
        <label class="campo">
            <span>Novo serviço</span>
            <input type="text" name="nome" maxlength="120" required placeholder="Troca de óleo">
        </label>
        <label class="campo">
            <span>Intervalo</span>
            <input type="text" name="intervalo" inputmode="decimal" maxlength="10" required placeholder="h">
        </label>
        <button type="submit" class="btn primario">Incluir serviço</button>
    </form>
</section>
