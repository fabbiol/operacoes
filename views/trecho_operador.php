<?php if (Auth::admin()): ?>
    <label class="campo">
        <span>Operador</span>
        <?php if (($operadores ?? []) !== []): ?>
            <select name="operador" required>
                <option value="">Escolha quem vai carregar</option>
                <?php foreach ($operadores as $nomeOperador): ?>
                    <option value="<?= e($nomeOperador) ?>"<?= $nomeOperador === $operador ? ' selected' : '' ?>><?= e($nomeOperador) ?></option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <input type="text" name="operador" maxlength="120" required value="<?= e($operador) ?>" autocomplete="off" placeholder="Quem vai carregar">
        <?php endif; ?>
    </label>
<?php else: ?>
    <p class="meta">Operador: <strong><?= e(Auth::usuario()) ?></strong></p>
<?php endif; ?>
