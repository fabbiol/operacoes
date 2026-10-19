<section class="cabeca">
    <div>
        <p class="olho"><?= e(Auth::usuario()) ?></p>
        <h1>Meus trabalhos</h1>
    </div>
</section>
<p class="nota">Últimos 30 dias. Entram os carregamentos finalizados, as cargas da escavadeira e as viagens da lavra concluídas em que o seu nome foi registrado.</p>

<div class="tempos">
    <div>
        <span>Trabalhos</span>
        <strong><?= count($itens) ?></strong>
    </div>
    <div>
        <span>Pátio</span>
        <strong><?= (int) $patio ?></strong>
    </div>
    <div>
        <span>Lavra</span>
        <strong><?= (int) $lavra ?></strong>
    </div>
</div>

<?php if (($turnos ?? []) !== []): ?>
    <h2>Horas de motor</h2>
    <div class="lista">
        <?php foreach ($turnos as $turno): ?>
            <article class="cartao">
                <header>
                    <strong><?= e((string) $turno['nome']) ?></strong>
                    <span class="selo status-em_viagem"><?= e(Horimetro::texto((float) $turno['horas'])) ?></span>
                </header>
                <p class="meta">Entrou com <?= e(Horimetro::texto((float) $turno['entrada'])) ?> · saiu com <?= e(Horimetro::texto((float) $turno['saida'])) ?></p>
                <p class="meta"><?= e(quando((string) ($turno['iniciado_em'] ?: $turno['encerrado_em']))) ?> → <?= e(hora_curta((string) $turno['encerrado_em'])) ?></p>
                <?php if (($turno['maquina_ok'] ?? null) !== null): ?>
                    <p class="meta"><?= (int) $turno['maquina_ok'] === 1 ? 'Entregou tudo certo' : 'Avisou problema na máquina' ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($itens === []): ?>
    <p class="vazio">Nenhum trabalho seu neste período.</p>
<?php else: ?>
    <div class="lista">
        <?php foreach ($itens as $item): ?>
            <?php if ($item['tipo'] === 'patio'): ?>
                <?php $ordem = $item['ordem']; ?>
                <article class="cartao">
                    <header>
                        <?php
                        $fotos = [url_foto_pa($ordem)];
                        $legenda = (string) $ordem['ordem'];
                        $destaque = true;
                        require __DIR__ . '/trecho_quem.php';
                        ?>
                        <span class="selo status-liberado">Pátio</span>
                    </header>
                    <p class="cliente"><?= e($ordem['cliente']) ?></p>
                    <p class="carga"><?= e(formatar_quantidade((string) ($ordem['quantidade_carregada'] ?? $ordem['quantidade_autorizada']))) ?> <?= e(unidade_rotulo($ordem['unidade'])) ?> · <?= e($ordem['material_nome']) ?></p>
                    <p class="meta"><?= e($ordem['pa_nome'] ?: 'Sem pá') ?> · <?= e(quando($ordem['fim_carregamento'])) ?></p>
                    <dl class="tempos tempos-ciclo">
                        <div><span>Ciclo</span><strong><?= e(texto_duracao($ordem['tempos']['ciclo'])) ?></strong></div>
                        <div><span>Fila</span><strong><?= e(texto_duracao($ordem['tempos']['fila'])) ?></strong></div>
                        <div><span>Carga</span><strong><?= e(texto_duracao($ordem['tempos']['carga'])) ?></strong></div>
                    </dl>
                </article>
            <?php elseif ($item['tipo'] === 'carga'): ?>
                <?php $ciclo = $item['ciclo']; ?>
                <article class="cartao">
                    <header>
                        <?php
                        $fotos = [url_foto_retro($ciclo) ?? url_foto_caminhao($ciclo)];
                        $legenda = (string) $ciclo['placa'];
                        $destaque = true;
                        require __DIR__ . '/trecho_quem.php';
                        ?>
                        <span class="selo status-carregando">Carga</span>
                    </header>
                    <p class="cliente">Carga na lavra</p>
                    <p class="carga">Carga <?= e(texto_duracao($ciclo['tempos']['carga'])) ?></p>
                    <p class="meta"><?= e((string) ($ciclo['retro_nome'] ?: 'Escavadeira hidráulica')) ?> · <?= e(quando($ciclo['fim_carga'])) ?></p>
                </article>
            <?php else: ?>
                <?php $ciclo = $item['ciclo']; ?>
                <article class="cartao">
                    <header>
                        <?php
                        $fotos = [url_foto_caminhao($ciclo)];
                        $legenda = (string) $ciclo['placa'];
                        $destaque = true;
                        require __DIR__ . '/trecho_quem.php';
                        ?>
                        <?php $fecho = Lavra::fecho($ciclo); ?>
                        <span class="selo <?= e($fecho['classe'] !== '' ? $fecho['classe'] : 'status-em_viagem') ?>"><?= e($fecho['selo'] !== '' ? $fecho['selo'] : 'Lavra') ?></span>
                    </header>
                    <?php
                    $mostrarTitulo = true;
                    $mostrarCiclo = true;
                    $quandoFecho = quando($ciclo['inicio_fila']) . ' → ' . hora_curta($ciclo['fim_ciclo']);
                    require __DIR__ . '/trecho_fecho_lavra.php';
                    ?>
                </article>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
