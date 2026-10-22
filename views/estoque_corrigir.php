<section class="cabeca">
    <div>
        <p class="olho">Estoque</p>
        <h1>Corrigir lançamento</h1>
    </div>
</section>
<p class="nota">Lançado por <?= e($lancamento['usuario']) ?> com a <?= e($lancamento['pa_nome']) ?> (concha de <?= e(formatar_quantidade($lancamento['concha_m3'])) ?> m³).</p>

<form method="post" action="<?= e(url('estoque/' . $lancamento['id'])) ?>" class="formulario">
    <?= csrf_field() ?>
    <label class="campo">
        <span>Material</span>
        <select name="material_id" required>
            <?php foreach ($materiais as $material): ?>
                <option value="<?= (int) $material['id'] ?>" <?= (int) $lancamento['material_id'] === (int) $material['id'] ? 'selected' : '' ?>><?= e($material['nome']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <div class="grade">
        <label class="campo">
            <span>Conchas</span>
            <input type="number" name="conchas" inputmode="numeric" min="1" max="999" step="1" required value="<?= e((string) $lancamento['conchas']) ?>">
        </label>
        <label class="campo">
            <span>Quando</span>
            <input type="datetime-local" name="colocado_em" required value="<?= e(data_para_input((string) $lancamento['colocado_em'])) ?>">
        </label>
    </div>
    <label class="campo">
        <span>Observação</span>
        <input type="text" name="observacoes" maxlength="200" value="<?= e((string) ($lancamento['observacoes'] ?? '')) ?>">
    </label>
    <div class="botoes">
        <button type="submit" class="btn primario">Salvar</button>
        <a class="btn secundario" href="<?= e(url('estoque')) ?>">Voltar</a>
    </div>
</form>

<form method="post" action="<?= e(url('estoque/' . $lancamento['id'] . '/apagar')) ?>" class="formulario">
    <?= csrf_field() ?>
    <button type="submit" class="btn perigo">Apagar lançamento</button>
</form>
