<?php
$novo = $pessoa === null || empty($pessoa['id']);
$fixo = !$novo && Auth::administradorFixo((string) ($pessoa['nome'] ?? ''));
$admin = !$novo && (int) ($pessoa['administrador'] ?? 0) === 1;
$funcao = is_array($pessoa) ? (string) ($pessoa['funcao'] ?? Pessoas::funcao($pessoa)) : '';
?>
<section class="cabeca">
    <div>
        <p class="olho">Pessoas</p>
        <h1><?= $novo ? 'Nova pessoa' : e((string) $pessoa['nome']) ?></h1>
    </div>
</section>
<form method="post" class="formulario" enctype="multipart/form-data" action="<?= e(url('usuarios' . ($novo ? '/novo' : '/' . $pessoa['id']))) ?>">
    <?= csrf_field() ?>
    <label class="campo">
        <span>Nome</span>
        <input type="text" name="nome" maxlength="40" required value="<?= e((string) ($pessoa['nome'] ?? '')) ?>" <?= $fixo ? 'readonly' : '' ?> autofocus>
    </label>
    <div class="campo">
        <span>Foto</span>
        <?php if (!$novo && !empty($pessoa['foto'])): ?>
            <img class="foto-atual" src="<?= e(url('usuarios/' . $pessoa['id'] . '/foto')) ?>" alt="">
        <?php endif; ?>
        <input type="file" name="foto" accept="image/jpeg,image/png,image/webp">
        <small class="dica">JPG, PNG ou WebP, até 5 MB. Sem arquivo novo, a foto atual permanece.</small>
    </div>
    <?php if (!$novo && !empty($pessoa['foto'])): ?>
        <label class="check">
            <input type="checkbox" name="remover_foto" value="1">
            <span>Remover foto</span>
        </label>
    <?php endif; ?>
    <?php if ($admin): ?>
        <p class="meta">Acesso administrativo, definido no servidor.</p>
    <?php else: ?>
        <fieldset class="maquinas">
            <legend>Função</legend>
            <label class="escolha">
                <input type="radio" name="funcao" value="motorista" required <?= $funcao === 'motorista' ? 'checked' : '' ?>>
                <span>Motorista<small>Caminhão</small></span>
            </label>
            <label class="escolha">
                <input type="radio" name="funcao" value="operador" required <?= $funcao === 'operador' ? 'checked' : '' ?>>
                <span>Operador<small>Pá carregadeira</small></span>
            </label>
            <label class="escolha">
                <input type="radio" name="funcao" value="ambos" required <?= $funcao === 'ambos' ? 'checked' : '' ?>>
                <span>Ambos<small>Caminhão e pá carregadeira</small></span>
            </label>
        </fieldset>
    <?php endif; ?>
    <label class="campo">
        <span>Senha</span>
        <input type="password" name="senha" maxlength="72" autocomplete="new-password" <?= $novo ? 'required' : '' ?>>
        <small class="dica"><?= $novo ? 'Obrigatória neste cadastro.' : 'Em branco, a senha atual permanece.' ?></small>
    </label>
    <?php if (!$fixo): ?>
        <label class="check">
            <input type="checkbox" name="ativo" value="1" <?= $pessoa === null || (int) ($pessoa['ativo'] ?? 1) === 1 ? 'checked' : '' ?>>
            <span>Ativo</span>
        </label>
    <?php endif; ?>
    <div class="botoes">
        <button type="submit" class="btn primario">Salvar</button>
        <a class="btn secundario" href="<?= e(url('usuarios')) ?>">Voltar</a>
    </div>
</form>
