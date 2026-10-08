<?php if (($faltando ?? []) !== []): ?>
    <?php
    $trechos = [];
    foreach ($faltando as $item) {
        $viagens = (int) $item['viagens'] === 1 ? '1 viagem' : (int) $item['viagens'] . ' viagens';
        $nome = e((string) $item['placa']);
        if (Auth::admin() && (int) $item['id'] > 0) {
            $nome = '<a href="' . e(url('cadastros/caminhoes/' . (int) $item['id'])) . '">' . $nome . '</a>';
        }
        $trechos[] = $nome . ' fez ' . $viagens;
    }
    ?>
    <p class="nota"><?= implode(' e ', $trechos) ?>, sem capacidade no cadastro.</p>
<?php endif; ?>
