<?php
$acao = $ordem === null ? url('ordens/nova') : url('ordens/' . $ordem['id']);
$valor = $valor ?? [];
$travado = !empty($travado);
?>
<section class="cabeca">
    <div>
        <p class="olho"><?= $ordem === null ? 'Administrativo' : e($ordem['ordem']) ?></p>
        <?php if (!empty($secao)): ?>
            <h2>Corrigir dados</h2>
        <?php else: ?>
            <h1><?= $ordem === null ? 'Nova ordem' : 'Corrigir dados' ?></h1>
        <?php endif; ?>
    </div>
</section>

<?php if ($ordem === null): ?>
    <form method="post" action="<?= e(url('ordens/nova/nota')) ?>" class="formulario ler-nota" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <label class="campo">
            <span>Ler a nota fiscal</span>
            <input type="file" name="nota" accept=".pdf,.xml,application/pdf,application/xml,text/xml" required>
            <small class="dica">Escolha o PDF (DANFE) ou o XML da nota: os campos abaixo são preenchidos para você conferir.</small>
        </label>
        <button type="submit" class="btn secundario">Ler nota</button>
    </form>
<?php endif; ?>

<?php if (!empty($leitura)): ?>
    <div class="leitura-nota">
        <p class="aviso"><?= e($leitura['mensagem']) ?></p>
        <?php if ($leitura['avisos'] !== []): ?>
            <ul class="aviso atencao">
                <?php foreach ($leitura['avisos'] as $aviso): ?>
                    <li><?= e($aviso) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>

<form method="post" action="<?= e($acao) ?>" class="formulario">
    <?= csrf_field() ?>
    <input type="hidden" name="nf_chave" value="<?= e($valor['nf_chave'] ?? '') ?>">
    <div class="grade">
        <label class="campo">
            <span>Data</span>
            <input type="datetime-local" name="data" required value="<?= e($valor['data'] ?? '') ?>" <?= $travado ? 'disabled' : '' ?>>
        </label>
        <label class="campo">
            <span>Cliente</span>
            <input type="text" name="cliente" maxlength="160" required value="<?= e($valor['cliente'] ?? '') ?>" <?= $travado ? 'disabled' : '' ?>>
        </label>
        <label class="campo">
            <span>Pedido</span>
            <input type="text" name="pedido" maxlength="60" required value="<?= e($valor['pedido'] ?? '') ?>" <?= $travado ? 'disabled' : '' ?>>
        </label>
        <label class="campo">
            <span>Nota fiscal</span>
            <input type="text" name="nf" maxlength="60" required value="<?= e($valor['nf'] ?? '') ?>" <?= $travado ? 'disabled' : '' ?>>
        </label>
        <label class="campo">
            <span>Nota emitida em</span>
            <input type="datetime-local" name="nf_emitida_em" step="1" value="<?= e($valor['nf_emitida_em'] ?? '') ?>" <?= $travado ? 'disabled' : '' ?>>
        </label>
        <label class="campo">
            <span>Material</span>
            <select name="material_id" required <?= $travado ? 'disabled' : '' ?>>
                <option value="">Selecione</option>
                <?php foreach ($materiais as $item): ?>
                    <option value="<?= (int) $item['id'] ?>" <?= (int) ($valor['material_id'] ?? 0) === (int) $item['id'] ? 'selected' : '' ?>>
                        <?= e($item['nome']) ?><?= (int) $item['ativo'] === 1 ? '' : ' (inativo)' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="campo">
            <span>Quantidade autorizada</span>
            <input type="text" name="quantidade_autorizada" inputmode="decimal" required value="<?= e($valor['quantidade_autorizada'] ?? '') ?>" placeholder="0,00" <?= $travado ? 'disabled' : '' ?>>
        </label>
        <label class="campo">
            <span>Unidade</span>
            <select name="unidade" required <?= $travado ? 'disabled' : '' ?>>
                <?php foreach (unidades() as $codigo => $rotulo): ?>
                    <option value="<?= e($codigo) ?>" <?= ($valor['unidade'] ?? '') === $codigo ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <label class="campo">
        <span>Observações</span>
        <textarea name="observacoes" rows="4" maxlength="4000"><?= e($valor['observacoes'] ?? '') ?></textarea>
    </label>
    <?php if ($travado): ?>
        <p class="nota">O carregamento já começou. Dá para completar as observações; o restante fica como foi liberado.</p>
    <?php endif; ?>
    <?php if ($ordem === null): ?>
        <label class="check">
            <input type="checkbox" name="liberar" value="1">
            <span>Liberar agora para o pátio</span>
        </label>
    <?php endif; ?>
    <div class="botoes">
        <button type="submit" class="btn primario"><?= $ordem === null ? 'Lançar ordem' : 'Salvar' ?></button>
        <a class="btn secundario" href="<?= e($ordem === null ? url('fila') : url('ordens/' . $ordem['id'])) ?>">Voltar</a>
    </div>
</form>
<?php if ($materiais === []): ?>
    <p class="nota">Antes de lançar, cadastre o <a href="<?= e(url('cadastros/materiais')) ?>">material</a>. A pá é escolhida no pátio, ao iniciar o carregamento.</p>
<?php elseif ($ordem === null): ?>
    <p class="nota">Quem carrega é a pá. O operador escolhe a máquina ao iniciar.</p>
<?php endif; ?>
