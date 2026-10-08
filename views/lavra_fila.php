<?php
$abertas = (int) ($contagens['na_fila'] ?? 0)
    + (int) ($contagens['carregando'] ?? 0)
    + (int) ($contagens['em_viagem'] ?? 0)
    + (int) ($contagens['retornando'] ?? 0);
$filtros = [
    'abertas' => ['Abertas', $abertas],
    'na_fila' => ['Na fila', $contagens['na_fila'] ?? 0],
    'carregando' => ['Carga', $contagens['carregando'] ?? 0],
    'em_viagem' => ['Ida', $contagens['em_viagem'] ?? 0],
    'retornando' => ['Retorno', $contagens['retornando'] ?? 0],
    'concluido' => ['Concluídas', $contagens['concluido'] ?? 0],
    'cancelado' => ['Canceladas', $contagens['cancelado'] ?? 0],
    'todas' => ['Todas', array_sum($contagens)],
];
$etapas = Lavra::etapas();
?>
<section class="cabeca">
    <div>
        <p class="olho">Administrativo</p>
        <h1>Fila da lavra</h1>
    </div>
    <div class="acoes">
        <a class="btn primario" href="<?= e(url('lavra')) ?>">Abrir a lavra</a>
    </div>
</section>

<form class="busca" method="get" action="<?= e(url('lavra/fila')) ?>">
    <input type="hidden" name="situacao" value="<?= e($situacao) ?>">
    <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Placa ou motorista" aria-label="Buscar">
    <button type="submit" class="btn secundario">Buscar</button>
</form>

<nav class="filtros" aria-label="Situação">
    <?php foreach ($filtros as $chave => [$rotulo, $total]): ?>
        <a class="<?= $situacao === $chave ? 'ativo' : '' ?>" href="<?= e(url('lavra/fila?situacao=' . $chave . ($busca !== '' ? '&q=' . rawurlencode($busca) : ''))) ?>">
            <?= e($rotulo) ?> <strong><?= (int) $total ?></strong>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($ciclos === []): ?>
    <p class="vazio">Nenhum ciclo nessa visão.</p>
<?php else: ?>
    <div class="lista">
        <?php foreach ($ciclos as $ciclo): ?>
            <?php
            $etapa = $etapas[$ciclo['status']] ?? null;
            $rotulo = $etapa['rotulo'] ?? ($ciclo['status'] === 'concluido' ? 'Concluído' : 'Cancelado');
            $tempos = Lavra::tempos($ciclo);
            $desde = Lavra::desde($ciclo);
            ?>
            <article class="cartao">
                <header>
                    <?php
                    $fotos = [url_foto_caminhao($ciclo), url_foto_pessoa($ciclo)];
                    $legenda = (string) $ciclo['placa'];
                    $destaque = true;
                    require __DIR__ . '/trecho_quem.php';
                    ?>
                    <span class="selo status-<?= e($ciclo['status']) ?>"><?= e($rotulo) ?></span>
                </header>
                <p class="cliente"><?= e($ciclo['operador'] ?: 'Sem motorista') ?></p>
                <?php if (trim((string) ($ciclo['retro_nome'] ?? '')) !== ''): ?>
                    <p class="meta"><?php
                        $fotos = [url_foto_retro($ciclo)];
                        $legenda = (string) $ciclo['retro_nome'];
                        $quemCarga = trim((string) ($ciclo['operador_carga'] ?? ''));
                        if ($quemCarga !== '') {
                            $legenda .= ' · ' . $quemCarga;
                        }
                        $destaque = false;
                        require __DIR__ . '/trecho_quem.php';
                    ?></p>
                <?php endif; ?>
                <?php if ($desde): ?>
                    <p class="carga"><?= e($rotulo) ?> desde <?= e(hora_curta($desde)) ?></p>
                <?php elseif ($ciclo['status'] === 'concluido'): ?>
                    <p class="carga">Ciclo <?= e(texto_duracao($tempos['ciclo'])) ?></p>
                <?php endif; ?>
                <p class="meta">Entrou na fila <?= e(quando($ciclo['inicio_fila'])) ?><?php if ($ciclo['fim_ciclo']): ?> · Chegou <?= e(hora_curta($ciclo['fim_ciclo'])) ?><?php endif; ?></p>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
