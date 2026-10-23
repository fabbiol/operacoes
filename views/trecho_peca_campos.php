<label class="campo">
    <span>Função</span>
    <input type="text" name="funcao" maxlength="120" required placeholder="Ex.: Filtro de óleo do motor" value="<?= e((string) ($valores['funcao'] ?? '')) ?>">
</label>
<div class="grade">
    <label class="campo">
        <span>Marca</span>
        <input type="text" name="marca" maxlength="80" value="<?= e((string) ($valores['marca'] ?? '')) ?>">
    </label>
    <label class="campo">
        <span>Código / modelo</span>
        <input type="text" name="codigo" maxlength="80" required value="<?= e((string) ($valores['codigo'] ?? '')) ?>">
    </label>
</div>
<label class="campo">
    <span>Quantidade usada na troca</span>
    <input type="number" name="quantidade" min="1" max="99" step="1" required value="<?= (int) ($valores['quantidade'] ?? 1) ?>">
</label>
<div class="campo">
    <span>Foto (opcional)</span>
    <input type="file" name="foto" accept="image/jpeg,image/png,image/webp">
    <small class="dica">JPG, PNG ou WebP, até 10 MB.</small>
</div>
