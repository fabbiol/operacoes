<section class="cabeca">
    <div>
        <p class="olho">Cadastros</p>
        <h1><?= e($tipo['titulo']) ?></h1>
    </div>
    <a class="btn primario" href="<?= e(url('cadastros/' . $tipo['chave'] . '/novo')) ?>">Novo</a>
</section>

<?php if ($itens === []): ?>
    <p class="vazio"><?= e($tipo['vazio'] ?? ('Nenhum ' . $tipo['singular'] . ' cadastrado.')) ?></p>
<?php else: ?>
    <ul class="cadastro">
        <?php foreach ($itens as $item): ?>
            <li class="<?= (int) $item['ativo'] === 1 ? '' : 'inativo' ?>">
                <a href="<?= e(url('cadastros/' . $tipo['chave'] . '/' . $item['id'])) ?>">
                    <?php if (!empty($tipo['ficha']) && !empty($item['foto'])): ?>
                        <img class="miniatura" src="<?= e(url('cadastros/' . $tipo['chave'] . '/' . $item['id'] . '/foto')) ?>" alt="">
                    <?php endif; ?>
                    <span class="cadastro-texto">
                        <strong><?= e($item['nome']) ?></strong>
                        <?php
                        $detalhe = [];
                        foreach (['marca', 'modelo', 'ano'] as $campo) {
                            $valor = trim((string) ($item[$campo] ?? ''));
                            if ($valor !== '') {
                                $detalhe[] = $valor;
                            }
                        }
                        $capacidade = texto_capacidade($item['capacidade_t'] ?? null);
                        if ($capacidade !== '') {
                            $detalhe[] = $capacidade;
                        }
                        ?>
                        <?php if (!empty($tipo['ficha']) && $detalhe !== []): ?>
                            <small><?= e(implode(' · ', $detalhe)) ?></small>
                        <?php endif; ?>
                    </span>
                    <?php if ((int) $item['ativo'] !== 1): ?><span class="selo">Inativo</span><?php endif; ?>
                </a>
                <form method="post" action="<?= e(url('cadastros/' . $tipo['chave'] . '/' . $item['id'] . '/ativo')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn secundario"><?= (int) $item['ativo'] === 1 ? 'Desativar' : 'Ativar' ?></button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<p><a class="btn secundario" href="<?= e(url('cadastros')) ?>">Voltar</a></p>
