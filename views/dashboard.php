<div class="monitor-topo">
    <p class="monitor-nota">Para a TV: toque em Tela cheia. Os números se atualizam sozinhos; se a conexão cair, a TV avisa desde quando está sem atualizar.</p>
    <button type="button" id="ampliar" class="ampliar">Tela cheia</button>
</div>
<div id="monitor" class="tv"></div>
<script id="monitor-dados" type="application/json" data-url="<?= e(url('dashboard/dados')) ?>"><?= json_encode($instantaneo, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= e(asset('assets/dashboard.js')) ?>"></script>
