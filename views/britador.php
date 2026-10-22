<?php
$abertos = array_values(array_filter($britador['problemas'], static fn (array $p): bool => $p['resolvido_em'] === null));
$resolvidos = array_values(array_filter($britador['problemas'], static fn (array $p): bool => $p['resolvido_em'] !== null));
?>
<section class="cabeca">
    <div>
        <p class="olho">Britador secundário</p>
        <h1><?= $britador['aberta'] ? ($britador['parado'] ? 'Parado por problema' : 'Britando') : 'Sem britagem agora' ?></h1>
    </div>
</section>

<div class="tempos">
    <div>
        <span>Começou hoje</span>
        <strong><?= $britador['inicio'] !== null ? e(hora_curta((string) $britador['inicio'])) : '—' ?></strong>
    </div>
    <div>
        <span>Terminou</span>
        <strong><?= $britador['aberta'] ? 'ainda britando' : ($britador['fim'] !== null ? e(hora_curta((string) $britador['fim'])) : '—') ?></strong>
    </div>
    <div>
        <span>Problemas abertos</span>
        <strong><?= count($abertos) ?></strong>
    </div>
</div>

<?php if ($britador['aberta']): ?>
    <?php if ($britador['aberta_desde'] !== null && substr((string) $britador['aberta_desde'], 0, 10) !== date('Y-m-d')): ?>
        <p class="aviso atencao">A britagem começou em <?= e(date('d/m H:i', (int) strtotime((string) $britador['aberta_desde']))) ?> e não foi terminada. Termine e comece a de hoje.</p>
    <?php endif; ?>
    <form method="post" action="<?= e(url('britador/terminar')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn concluir">Terminar britagem</button>
    </form>
<?php else: ?>
    <form method="post" action="<?= e(url('britador/iniciar')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn primario">Iniciar britagem</button>
    </form>
<?php endif; ?>

<?php if ($abertos !== []): ?>
    <section class="bloco">
        <h2>Problemas abertos</h2>
        <div class="lista">
            <?php foreach ($abertos as $problema): ?>
                <article class="cartao">
                    <header>
                        <strong><?= e((string) $problema['descricao']) ?></strong>
                        <span class="selo<?= (int) $problema['parou'] === 1 ? ' status-cancelado' : '' ?>"><?= (int) $problema['parou'] === 1 ? 'Britador parado' : 'Britando assim' ?></span>
                    </header>
                    <p class="meta">Desde <?= e(date('d/m H:i', (int) strtotime((string) $problema['inicio']))) ?> · <?= e((string) $problema['usuario']) ?></p>
                    <form method="post" action="<?= e(url('britador/problemas/' . $problema['id'] . '/resolver')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn secundario">Resolvido</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="bloco">
    <h2>Avisar problema</h2>
    <form method="post" action="<?= e(url('britador/problemas')) ?>" class="formulario">
        <?= csrf_field() ?>
        <label class="campo">
            <span>O que aconteceu</span>
            <textarea name="descricao" rows="3" maxlength="500" required></textarea>
        </label>
        <fieldset class="maquinas maquinas-duas">
            <legend>O britador parou?</legend>
            <label class="escolha escolha-problema">
                <input type="radio" name="parou" value="1" required>
                <span>Sim, parou</span>
            </label>
            <label class="escolha">
                <input type="radio" name="parou" value="0" required>
                <span>Não, continua britando</span>
            </label>
        </fieldset>
        <div class="botoes">
            <button type="submit" class="btn primario">Avisar problema</button>
        </div>
    </form>
</section>

<?php if ($resolvidos !== []): ?>
    <section class="bloco">
        <h2>Resolvidos hoje</h2>
        <ul class="enviadas">
            <?php foreach ($resolvidos as $problema): ?>
                <li><div class="enviada"><strong><?= e((string) $problema['descricao']) ?></strong>
                    <p class="meta"><?= e(hora_curta((string) $problema['inicio'])) ?> às <?= e(hora_curta((string) $problema['resolvido_em'])) ?><?= (int) $problema['parou'] === 1 ? ' · britador parado' : '' ?> · <?= e((string) $problema['resolvido_por']) ?></p></div></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
