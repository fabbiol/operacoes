<section class="cabeca">
    <div>
        <p class="olho">Antes de sair</p>
        <h1>Encerre a viagem</h1>
    </div>
</section>

<?php if (($trabalho['tipo'] ?? '') === 'caminhao' && !empty($ciclo)): ?>
    <p class="nota">A viagem da <?= e((string) ($ciclo['placa'] ?? 'máquina')) ?> ainda está aberta. O horímetro final entra no Sair, depois que ela fechar.</p>
    <p><a class="btn primario" href="<?= e(url('lavra/ciclos/' . (int) $ciclo['id'] . '/encerrar')) ?>">Encerrar viagem</a></p>
<?php else: ?>
    <p class="nota">Conclua o trabalho desta máquina antes de sair. O horímetro final entra nessa saída.</p>
    <p><a class="btn primario" href="<?= e(url($destino ?? 'lavra')) ?>">Voltar ao trabalho</a></p>
<?php endif; ?>
