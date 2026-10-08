<p class="monitor-nota">O caminhão na estrada acompanha a etapa do ciclo, o tempo desde o último toque e a distância de cada trecho.</p>
<form class="estrada-medidas" method="post" action="<?= e(url('dashboard/estrada')) ?>">
    <?= csrf_field() ?>
    <label>Ida até o britador
        <input name="ida_km" inputmode="decimal" maxlength="8" placeholder="km" value="<?= $idaKm !== null ? e(number_format($idaKm, 2, ',', '')) : '' ?>">
    </label>
    <label>Retorno até a lavra
        <input name="retorno_km" inputmode="decimal" maxlength="8" placeholder="km" value="<?= $retornoKm !== null ? e(number_format($retornoKm, 2, ',', '')) : '' ?>">
    </label>
    <button type="submit">Salvar</button>
    <p>Informe os quilômetros medidos. A ida e o retorno podem ter comprimentos diferentes. O caminhão para no fim do trecho até o próximo toque.</p>
</form>
<div id="monitor" class="monitor"></div>
<script id="monitor-dados" type="application/json" data-url="<?= e(url('dashboard/dados')) ?>"><?= json_encode($instantaneo, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= e(asset('assets/dashboard.js')) ?>"></script>
