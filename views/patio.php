<section class="cabeca">
    <div>
        <p class="olho">Operador do pátio</p>
        <h1>Ordens liberadas</h1>
    </div>
    <a class="btn secundario" href="<?= e(url('patio/resumo')) ?>">Tempos de hoje</a>
</section>
<p class="nota ajuda">A fila começa na liberação e a carga no toque de iniciar. Finalize quando a carga estiver no caminhão.</p>

<div id="fluxo" data-url="<?= e($fluxoUrl) ?>" data-marca="<?= e($fluxoMarca) ?>">
<?php if ($ordens === []): ?>
    <p class="vazio">Nenhuma ordem liberada no momento.</p>
<?php else: ?>
    <div class="lista">
        <?php foreach ($ordens as $ordem): ?>
            <article class="cartao destaque">
                <?php $fotoMaquina = $ordem['status'] === 'carregando' ? url_foto_pa($ordem) : null; ?>
                <?php if ($fotoMaquina): ?>
                    <img class="foto-equipamento" src="<?= e($fotoMaquina) ?>" alt="">
                <?php endif; ?>
                <header>
                    <strong><?= e($ordem['ordem']) ?></strong>
                    <span class="selo status-<?= e($ordem['status']) ?>"><?= e(status_rotulo($ordem['status'])) ?></span>
                </header>
                <p class="cliente"><?= e($ordem['cliente']) ?></p>
                <p class="carga"><?= (int) ($ordem['fracionado'] ?? 0) === 1 ? 'Saldo ' : '' ?><?= e(formatar_quantidade((string) $ordem['quantidade_autorizada'])) ?> <?= e(unidade_rotulo($ordem['unidade'])) ?> · <?= e($ordem['material_nome']) ?></p>
                <?php if ((int) ($ordem['fracionado'] ?? 0) === 1): ?>
                    <p class="meta"><span class="selo fracionado">Fracionado · viagem <?= (int) ($ordem['viagem'] ?? 1) ?></span></p>
                <?php endif; ?>
                <p class="meta">Pedido <?= e($ordem['pedido']) ?> · NF <?= e($ordem['nf']) ?></p>
                <?php $recado = observacao_recente($ordem['observacoes'] ?? null); ?>
                <?php if ($recado !== ''): ?>
                    <p class="obs-patio"><strong>Obs.:</strong> <?= e($recado) ?></p>
                <?php endif; ?>
                <?php
                $desde = $ordem['status'] === 'carregando' ? ($ordem['inicio_carregamento'] ?? null) : ($ordem['autorizado_em'] ?? null);
                $etapa = $ordem['status'] === 'carregando' ? 'Carga' : 'Na fila';
                ?>
                <?php if ($desde): ?>
                    <p class="ciclo"><time data-desde="<?= (int) strtotime((string) $desde) ?>"><?= e(texto_duracao(segundos_entre((string) $desde, agora_sql()))) ?></time></p>
                    <?php if ($ordem['status'] === 'carregando'): ?>
                        <?php
                        $detalhePa = [];
                        foreach (['pa_marca', 'pa_modelo', 'pa_ano'] as $origem) {
                            $valorPa = trim((string) ($ordem[$origem] ?? ''));
                            if ($valorPa !== '') {
                                $detalhePa[] = $valorPa;
                            }
                        }
                        ?>
                        <div class="caminhao-nome">
                            <strong><?= e($ordem['pa_nome'] ?: 'Pá') ?></strong>
                            <?php if ($detalhePa !== []): ?>
                                <p class="meta"><?= e(implode(' · ', $detalhePa)) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <p class="meta"><?php
                        $fotos = [url_foto_pessoa($ordem)];
                        $legenda = $etapa . ' desde ' . hora_curta((string) $desde);
                        if ($ordem['status'] === 'carregando' && $ordem['operador']) {
                            $legenda .= ' · ' . $ordem['operador'];
                        }
                        $destaque = false;
                        require __DIR__ . '/trecho_quem.php';
                    ?></p>
                <?php endif; ?>
                <?php if ($ordem['status'] === 'carregando' && (Auth::admin() || trim((string) $ordem['operador']) === '' || $ordem['operador'] === Auth::usuario())): ?>
                    <a class="btn concluir" href="<?= e(url('carregamentos/' . $ordem['id'] . '/finalizar')) ?>">Finalizar carregamento</a>
                <?php elseif ($ordem['status'] === 'carregando'): ?>
                    <p class="espera-patio">Carregando com <?= e((string) $ordem['operador']) ?>: só quem iniciou finaliza.</p>
                <?php elseif (!empty($ocupado)): ?>
                    <p class="espera-patio"><?= e($ocupado) ?></p>
                <?php else: ?>
                    <a class="btn primario" href="<?= e(url('carregamentos/' . $ordem['id'] . '/iniciar')) ?>">Iniciar carregamento</a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($enviadas !== []): ?>
    <section class="bloco">
        <h2>Enviadas hoje</h2>
        <ul class="enviadas">
            <?php foreach ($enviadas as $ordem): ?>
                <?php
                $fotoPa = url_foto_pa($ordem);
                $saida = [];
                if (trim((string) ($ordem['pa_nome'] ?? '')) !== '') {
                    $saida[] = (string) $ordem['pa_nome'];
                }
                if (trim((string) ($ordem['operador'] ?? '')) !== '') {
                    $saida[] = (string) $ordem['operador'];
                }
                $horaSaida = hora_curta($ordem['fim_carregamento'] ?? null);
                if ($horaSaida !== '') {
                    $saida[] = $horaSaida;
                }
                ?>
                <li>
                    <?php if ($fotoPa): ?>
                        <img class="mini" src="<?= e($fotoPa) ?>" alt="">
                    <?php endif; ?>
                    <div class="enviada">
                        <div class="enviada-topo">
                            <strong><?= e($ordem['ordem']) ?></strong>
                            <span><?= e($ordem['cliente']) ?></span>
                            <span class="selo status-<?= e($ordem['status']) ?>"><?= e(status_rotulo($ordem['status'])) ?></span>
                        </div>
                        <p><?= e(formatar_quantidade((string) ($ordem['quantidade_carregada'] ?? $ordem['quantidade_autorizada']))) ?> <?= e(unidade_rotulo($ordem['unidade'])) ?> · <?= e($ordem['material_nome']) ?></p>
                        <?php if ($saida !== []): ?>
                            <p class="meta"><?= e(implode(' · ', $saida)) ?></p>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
<?php if (!empty($avisosPatio)): ?>
    <script type="application/json" data-avisos-patio><?= json_encode($avisosPatio, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php endif; ?>
</div>
