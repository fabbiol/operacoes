<?php
// Manutenção da máquina que está com o operador: registrar a troca (óleo, filtros...) com o horímetro atual.
// Serviço novo: o operador diz de quantas em quantas horas é a troca, e isso fica salvo para as próximas vezes.
$escolhido = (string) ($_POST['servico'] ?? '');
?>
<section class="cabeca">
    <div>
        <p class="olho"><?= e((string) $maquina['nome']) ?></p>
        <h1>Manutenção da máquina</h1>
    </div>
</section>
<p class="meta">Horímetro atual <?= e(Horimetro::texto($atual)) ?></p>

<?php if ($avisos !== []): ?>
    <?php $avisosManutencao = $avisos; $verTodos = false; require __DIR__ . '/trecho_avisos_manutencao.php'; ?>
<?php endif; ?>

<form method="post" action="<?= e(url('troca')) ?>" class="formulario">
    <?= csrf_field() ?>
    <fieldset class="servicos-troca">
        <legend>Qual troca foi feita?</legend>
        <?php foreach ($planos as $plano): ?>
            <label class="opcao-troca">
                <input type="radio" name="servico" value="<?= (int) $plano['id'] ?>" required <?= $escolhido === (string) $plano['id'] ? 'checked' : '' ?>>
                <span class="opcao-texto">
                    <strong><?= e($plano['nome']) ?></strong>
                    <small>A cada <?= e(Horimetro::texto($plano['intervalo_h'])) ?><?php if ($plano['ultima'] !== null): ?> · última com <?= e(Horimetro::texto($plano['ultima'])) ?><?php endif; ?></small>
                    <?php $situacao = $plano['situacao']; require __DIR__ . '/trecho_situacao.php'; ?>
                </span>
            </label>
        <?php endforeach; ?>
        <label class="opcao-troca">
            <input type="radio" name="servico" value="novo" required <?= $escolhido === 'novo' || $planos === [] ? 'checked' : '' ?>>
            <span class="opcao-texto"><strong>Outro serviço (primeira vez nesta máquina)</strong></span>
        </label>
    </fieldset>
    <fieldset class="servico-novo">
        <legend>Só para outro serviço</legend>
        <label class="campo">
            <span>Nome do serviço</span>
            <input type="text" name="nome" maxlength="120" placeholder="Ex.: Troca de óleo do motor" value="<?= e((string) ($_POST['nome'] ?? '')) ?>">
        </label>
        <label class="campo">
            <span>De quantas em quantas horas?</span>
            <input type="text" name="intervalo" inputmode="decimal" maxlength="8" placeholder="Ex.: 250" value="<?= e((string) ($_POST['intervalo'] ?? '')) ?>">
        </label>
        <small class="dica">Fica salvo: nas próximas trocas desse serviço é só escolher na lista.</small>
    </fieldset>
    <button type="submit" class="btn primario">Registrar troca com <?= e(Horimetro::texto($atual)) ?></button>
</form>
