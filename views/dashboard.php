<p class="monitor-nota">A posição na estrada segue a etapa e o tempo desde o toque. Não é GPS.</p>
<div id="monitor" class="monitor"></div>
<script id="monitor-dados" type="application/json" data-url="<?= e(url('dashboard/dados')) ?>"><?= json_encode($instantaneo, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= e(asset('assets/dashboard.js')) ?>"></script>
