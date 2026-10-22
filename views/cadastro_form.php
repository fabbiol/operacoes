<?php $novo = $item === null || empty($item['id']); ?>
<?php $artigo = $tipo['artigo'] ?? 'Novo'; ?>
<?php $ficha = !empty($tipo['ficha']); ?>
<section class="cabeca">
    <div>
        <p class="olho"><?= e($tipo['titulo']) ?></p>
        <h1><?= $novo ? e($artigo . ' ' . $tipo['singular']) : e((string) $item['nome']) ?></h1>
    </div>
</section>
<form method="post" class="formulario"<?= $ficha ? ' enctype="multipart/form-data"' : '' ?> action="<?= e(url('cadastros/' . $tipo['chave'] . ($novo ? '/novo' : '/' . $item['id']))) ?>">
    <?= csrf_field() ?>
    <label class="campo">
        <span><?= e($tipo['rotulo']) ?></span>
        <input type="text" name="nome" maxlength="120" required value="<?= e((string) ($item['nome'] ?? '')) ?>" autofocus>
    </label>
    <?php if ($ficha): ?>
        <div class="campo">
            <span>Foto</span>
            <?php if (!empty($item['foto']) && !empty($item['id'])): ?>
                <img class="foto-atual" src="<?= e(url('cadastros/' . $tipo['chave'] . '/' . $item['id'] . '/foto') . '?v=' . Fotos::versao((string) $item['foto'])) ?>" alt="">
            <?php endif; ?>
            <input type="file" name="foto" accept="image/jpeg,image/png,image/webp">
            <small class="dica">JPG, PNG ou WebP, até 10 MB. Sem arquivo novo, a foto atual permanece.</small>
        </div>
        <?php if (!empty($item['foto']) && !empty($item['id'])): ?>
            <label class="check">
                <input type="checkbox" name="remover_foto" value="1">
                <span>Remover foto</span>
            </label>
        <?php endif; ?>
        <div class="grade">
            <label class="campo">
                <span>Marca</span>
                <input type="text" name="marca" maxlength="80" value="<?= e((string) ($item['marca'] ?? '')) ?>">
            </label>
            <label class="campo">
                <span>Modelo</span>
                <input type="text" name="modelo" maxlength="80" value="<?= e((string) ($item['modelo'] ?? '')) ?>">
            </label>
        </div>
        <label class="campo">
            <span>Ano</span>
            <input type="text" name="ano" inputmode="numeric" maxlength="4" value="<?= e((string) ($item['ano'] ?? '')) ?>">
        </label>
        <?php if (!empty($tipo['capacidade'])): ?>
            <?php
            $capacidade = (string) ($item['capacidade_t'] ?? '');
            if ($capacidade !== '' && preg_match('/^\d+(\.\d+)?$/', $capacidade)) {
                $capacidade = formatar_quantidade($capacidade);
            }
            ?>
            <label class="campo">
                <span>Capacidade</span>
                <input type="text" name="capacidade" inputmode="decimal" maxlength="8" value="<?= e($capacidade) ?>" placeholder="t">
                <small class="dica">Em toneladas. Na brita, a densidade é 1,45 t/m³: 10 t correspondem a 6,90 m³.</small>
            </label>
        <?php endif; ?>
        <?php if (!empty($tipo['concha'])): ?>
            <?php
            $concha = (string) ($item['concha_m3'] ?? '');
            if ($concha !== '' && preg_match('/^\d+(\.\d+)?$/', $concha)) {
                $concha = formatar_quantidade($concha);
            }
            ?>
            <label class="campo">
                <span>Concha</span>
                <input type="text" name="concha" inputmode="decimal" maxlength="6" value="<?= e($concha) ?>" placeholder="m³">
                <small class="dica">Em m³. É com ela que o estoque conta as conchas colocadas na pilha.</small>
            </label>
        <?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($tipo['estoque'])): ?>
        <label class="check">
            <input type="checkbox" name="estoque" value="1" <?= (int) ($item['estoque'] ?? 0) === 1 ? 'checked' : '' ?>>
            <span>Tem estoque (aparece para o operador da pá lançar na pilha)</span>
        </label>
    <?php endif; ?>
    <label class="campo">
        <span>Observações</span>
        <textarea name="observacoes" rows="4" maxlength="4000"><?= e((string) ($item['observacoes'] ?? '')) ?></textarea>
    </label>
    <label class="check">
        <input type="checkbox" name="ativo" value="1" <?= $item === null || (int) ($item['ativo'] ?? 1) === 1 ? 'checked' : '' ?>>
        <span>Ativo</span>
    </label>
    <div class="botoes">
        <button type="submit" class="btn primario">Salvar</button>
        <a class="btn secundario" href="<?= e(url('cadastros/' . $tipo['chave'])) ?>">Voltar</a>
    </div>
</form>
<?php if (!$novo && $ficha && isset($horimetro)): ?>
    <?php require __DIR__ . '/trecho_horimetro.php'; ?>
<?php endif; ?>
