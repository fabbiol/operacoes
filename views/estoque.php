<section class="cabeca">
    <div>
        <p class="olho">Pilhas do estoque</p>
        <h1>Estoque</h1>
    </div>
    <?php if (!Auth::admin()): ?>
        <a class="btn secundario" href="<?= e(url('patio')) ?>">Voltar ao pátio</a>
    <?php endif; ?>
</section>
<p class="nota ajuda">Conta só o que entra na pilha, pelas conchas da pá. Densidade de <?= e(formatar_quantidade(densidade_brita())) ?> t/m³.</p>

<?php if ($saldos === []): ?>
    <p class="vazio">Nenhum material marcado com estoque. O administrativo marca em Cadastros › Materiais.</p>
<?php else: ?>
    <div class="lista">
        <?php foreach ($saldos as $saldo): ?>
            <article class="cartao destaque">
                <header>
                    <strong><?= e($saldo['nome']) ?></strong>
                    <span class="selo"><?= (int) $saldo['conchas'] ?> conchas</span>
                </header>
                <div class="tempos tempos-ciclo">
                    <div><span>Na pilha</span><strong><?= e(formatar_quantidade($saldo['m3'])) ?> m³</strong></div>
                    <div><span>Toneladas</span><strong><?= e(formatar_quantidade($saldo['t'])) ?> t</strong></div>
                    <div><span>Hoje</span><strong><?= e(formatar_quantidade($saldo['m3_hoje'])) ?> m³</strong></div>
                </div>
                <p class="meta">Hoje: <?= (int) $saldo['conchas_hoje'] ?> conchas · <?= e(formatar_quantidade($saldo['t_hoje'])) ?> t</p>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<section class="bloco">
    <h2>Lançar na pilha</h2>
    <?php if ($materiais === []): ?>
        <p class="vazio">Nenhum material com estoque para lançar.</p>
    <?php elseif (!Auth::admin() && $paDoTurno === null): ?>
        <p class="aviso atencao">Para lançar, escolha a pá carregadeira do turno.</p>
        <p><a class="btn secundario" href="<?= e(url('maquina')) ?>">Escolher a pá</a></p>
    <?php else: ?>
        <form method="post" action="<?= e(url('estoque')) ?>" class="formulario">
            <?= csrf_field() ?>
            <fieldset class="maquinas<?= count($materiais) > 1 ? ' maquinas-duas' : '' ?>">
                <legend>Material</legend>
                <?php foreach ($materiais as $material): ?>
                    <label class="escolha">
                        <input type="radio" name="material_id" value="<?= (int) $material['id'] ?>" required <?= (int) ($form['material_id'] ?? 0) === (int) $material['id'] || count($materiais) === 1 ? 'checked' : '' ?>>
                        <span><?= e($material['nome']) ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <label class="campo">
                <span>Conchas colocadas</span>
                <input type="number" name="conchas" inputmode="numeric" min="1" max="999" step="1" required value="<?= e((string) ($form['conchas'] ?? '')) ?>">
                <?php if ($paDoTurno !== null): ?>
                    <small class="dica">
                        <?= e($paDoTurno['nome']) ?>
                        <?= $paDoTurno['concha_m3'] !== null ? ' · concha de ' . e(formatar_quantidade($paDoTurno['concha_m3'])) . ' m³' : ' · concha não cadastrada: avise o administrativo' ?>
                    </small>
                <?php endif; ?>
            </label>
            <?php if (Auth::admin()): ?>
                <div class="grade">
                    <label class="campo">
                        <span>Pá carregadeira</span>
                        <select name="pa_carregadeira_id" required>
                            <option value="">Escolha</option>
                            <?php foreach ($pas as $pa): ?>
                                <option value="<?= (int) $pa['id'] ?>" <?= (int) ($form['pa_carregadeira_id'] ?? 0) === (int) $pa['id'] ? 'selected' : '' ?>>
                                    <?= e($pa['nome']) ?><?= $pa['concha_m3'] !== null ? ' · concha ' . e(formatar_quantidade($pa['concha_m3'])) . ' m³' : ' · sem concha' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="campo">
                        <span>Quando</span>
                        <input type="datetime-local" name="colocado_em" required value="<?= e(data_para_input($form['colocado_em'] ?? null)) ?>">
                    </label>
                </div>
            <?php endif; ?>
            <label class="campo">
                <span>Observação</span>
                <input type="text" name="observacoes" maxlength="200" value="<?= e((string) ($form['observacoes'] ?? '')) ?>">
            </label>
            <div class="botoes">
                <button type="submit" class="btn primario">Lançar no estoque</button>
            </div>
            <?php if (!Auth::admin()): ?>
                <p class="nota">Lançou errado? Avise o administrativo, que corrige ou apaga.</p>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</section>

<section class="bloco">
    <h2>Últimos lançamentos</h2>
    <?php if ($lancamentos === []): ?>
        <p class="vazio">Nenhum lançamento ainda.</p>
    <?php else: ?>
        <div class="tabela-rolagem">
            <table class="tabela">
                <thead><tr><th>Quando</th><th>Material</th><th>Conchas</th><th>m³</th><th>t</th><th>Quem</th><?= Auth::admin() ? '<th></th>' : '' ?></tr></thead>
                <tbody>
                    <?php foreach ($lancamentos as $l): ?>
                        <tr>
                            <td><?= e(date('d/m H:i', (int) strtotime((string) $l['colocado_em']))) ?></td>
                            <td><?= e($l['material_nome']) ?><?php if (!empty($l['observacoes'])): ?><small><?= e($l['observacoes']) ?></small><?php endif; ?></td>
                            <td class="numero"><?= (int) $l['conchas'] ?><small><?= e($l['pa_nome']) ?> · <?= e(formatar_quantidade($l['concha_m3'])) ?> m³</small></td>
                            <td class="numero"><?= e(formatar_quantidade($l['m3'])) ?></td>
                            <td class="numero"><?= e(formatar_quantidade($l['t'])) ?></td>
                            <td><?= e($l['usuario']) ?></td>
                            <?php if (Auth::admin()): ?>
                                <td><a href="<?= e(url('estoque/' . $l['id'])) ?>">Corrigir</a></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
