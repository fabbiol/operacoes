<section class="cabeca">
    <div>
        <p class="olho">Máquinas</p>
        <h1>Manutenção</h1>
    </div>
    <a class="btn secundario" href="<?= e(url('pedidos-pecas')) ?>">Pedidos de peças</a>
</section>
<p class="nota">O prazo usa a última leitura do horímetro. Próxima significa que faltam <?= (int) Horimetro::PROXIMA_H ?> h ou menos. Sem leitura, o prazo não aparece. Os problemas são os que os operadores avisaram ao soltar a máquina: marque como resolvido na ficha.</p>

<?php foreach ($grupos as $grupo): ?>
    <h2><?= e($grupo['titulo']) ?></h2>
    <?php if ($grupo['itens'] === []): ?>
        <p class="vazio">Nenhuma máquina ativa.</p>
    <?php else: ?>
        <div class="lista">
            <?php foreach ($grupo['itens'] as $item): ?>
                <article class="cartao">
                    <header>
                        <strong><?= e($item['nome']) ?></strong>
                        <?php $situacao = ['estado' => $item['estado'], 'rotulo' => $item['rotulo'], 'falta' => null, 'proxima' => null]; require __DIR__ . '/trecho_situacao.php'; ?>
                    </header>
                    <p class="meta">Horímetro <?= e(Horimetro::texto($item['atual'])) ?><?php if (!empty($item['posse'])): ?> · com <?= e((string) $item['posse']['usuario']) ?> desde <?= e(quando((string) $item['posse']['iniciado_em'])) ?><?php endif; ?></p>
                    <?php if (($item['problemas'] ?? []) !== []): ?>
                        <ul class="problemas-lista">
                            <?php foreach ($item['problemas'] as $problema): ?>
                                <li><span class="selo status-problema">Problema</span> <?= e((string) $problema['descricao']) ?> <small><?= e((string) $problema['usuario']) ?>, <?= e(quando((string) $problema['relatado_em'])) ?></small></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if ($item['planos'] === []): ?>
                        <p class="meta">Nenhum serviço ativo.</p>
                    <?php else: ?>
                        <ul class="cadastro">
                            <?php foreach ($item['planos'] as $plano): ?>
                                <li>
                                    <span class="cadastro-texto">
                                        <strong><?= e($plano['nome']) ?></strong>
                                        <small>A cada <?= e(Horimetro::texto($plano['intervalo_h'])) ?></small>
                                    </span>
                                    <?php $situacao = $plano['situacao']; require __DIR__ . '/trecho_situacao.php'; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <a class="btn secundario" href="<?= e(url('cadastros/' . $item['chave'] . '/' . $item['id'])) ?>">Abrir ficha</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endforeach; ?>
