<section class="cabeca">
    <div>
        <p class="olho">Administrativo</p>
        <h1>Painel do dia</h1>
    </div>
    <p class="data"><?= e(data_por_extenso()) ?></p>
</section>

<div id="fluxo" data-url="<?= e($fluxoUrl) ?>" data-marca="<?= e($fluxoMarca) ?>">
<section class="vendas-dia">
    <a class="venda" href="<?= e(url('controle')) ?>">
        <span>Vendido hoje</span>
        <strong>R$ <?= e(formatar_quantidade($vendas['valor'])) ?></strong>
        <small><?= (int) $vendas['notas'] ?> <?= (int) $vendas['notas'] === 1 ? 'nota' : 'notas' ?><?= $vendas['sem_valor'] > 0 ? ' · ' . (int) $vendas['sem_valor'] . ' sem valor' : '' ?></small>
    </a>
    <a class="venda" href="<?= e(url('fila?situacao=saldo')) ?>">
        <span>Saldo a buscar</span>
        <strong><?= (int) $saldos ?></strong>
        <small><?= (int) $saldos === 1 ? 'nota fracionada esperando o cliente' : 'notas fracionadas esperando o cliente' ?></small>
    </a>
</section>

<?php if ($autorizar > 0 || $conferir > 0): ?>
    <nav class="parado" aria-label="Esperando o administrativo">
        <?php if ($autorizar > 0): ?>
            <a href="<?= e(url('fila?situacao=aguardando_autorizacao')) ?>">Autorizar <strong><?= (int) $autorizar ?></strong></a>
        <?php endif; ?>
        <?php if ($conferir > 0): ?>
            <a href="<?= e(url('fila?situacao=aguardando_conferencia')) ?>">Conferir <strong><?= (int) $conferir ?></strong></a>
        <?php endif; ?>
    </nav>
<?php endif; ?>

<div class="painel-agora">
    <section class="painel-bloco">
        <h2><a href="<?= e(url('fila')) ?>">Carregamento</a></h2>
        <div class="tempos">
            <div>
                <span>Liberadas</span>
                <strong><?= (int) $liberadas ?></strong>
            </div>
            <div>
                <span>Na pá</span>
                <strong><?= count($carregando) ?></strong>
            </div>
        </div>
        <?php if ($carregando === []): ?>
            <p class="vazio">Nenhuma carga em andamento.</p>
        <?php else: ?>
            <div class="lista">
                <?php foreach ($carregando as $ordem): ?>
                    <article class="cartao">
                        <header>
                            <?php
                            $fotos = [url_foto_pa($ordem), url_foto_pessoa($ordem)];
                            $legenda = (string) $ordem['ordem'];
                            $destaque = true;
                            require __DIR__ . '/trecho_quem.php';
                            ?>
                            <span class="selo status-carregando">Carregando</span>
                        </header>
                        <p class="cliente"><?= e($ordem['cliente']) ?></p>
                        <?php if ($ordem['inicio_carregamento']): ?>
                            <p class="ciclo"><time data-desde="<?= (int) strtotime((string) $ordem['inicio_carregamento']) ?>"><?= e(texto_duracao(segundos_entre((string) $ordem['inicio_carregamento'], agora_sql()))) ?></time></p>
                        <?php endif; ?>
                        <p class="meta"><?php
                            $fotos = [];
                            $legenda = ($ordem['pa_nome'] ?: 'Sem pá') . ' · ' . ($ordem['operador'] ?: 'Sem operador');
                            $destaque = false;
                            require __DIR__ . '/trecho_quem.php';
                        ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="painel-bloco">
        <h2><a href="<?= e(url('lavra/fila')) ?>">Lavra</a></h2>
        <div class="tempos">
            <div>
                <span>Em ciclo</span>
                <strong><?= count($ciclos) ?></strong>
            </div>
            <div>
                <span>Livres</span>
                <strong><?= (int) $livres ?></strong>
            </div>
        </div>
        <?php if ($ciclos === []): ?>
            <p class="vazio">Nenhum caminhão em ciclo.</p>
        <?php else: ?>
            <div class="lista">
                <?php foreach ($ciclos as $ciclo): ?>
                    <?php
                    $etapa = Lavra::etapas()[$ciclo['status']] ?? null;
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
                            <span class="selo status-<?= e($ciclo['status']) ?>"><?= e($etapa['rotulo'] ?? '') ?></span>
                        </header>
                        <p class="cliente"><?= e($ciclo['operador'] ?: 'Sem motorista') ?></p>
                        <?php if ($ciclo['status'] === 'na_fila'): ?>
                            <p class="meta">Aguardando a escavadeira</p>
                        <?php elseif (trim((string) ($ciclo['retro_nome'] ?? '')) !== ''): ?>
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
                            <p class="ciclo"><time data-desde="<?= (int) strtotime($desde) ?>"><?= e(texto_duracao(segundos_entre($desde, agora_sql()))) ?></time></p>
                        <?php endif; ?>
                        <p class="meta">Desde <?= e(hora_curta($desde)) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<h2>Hoje</h2>
<div class="painel-agora">
    <section class="painel-bloco">
        <h2><a href="<?= e(url('patio/resumo')) ?>">Carregamento</a></h2>
        <div class="tempos">
            <div>
                <span>Concluídos</span>
                <strong><?= (int) $cargaDia['maquina']['total'] ?></strong>
            </div>
            <div>
                <span>Ciclo médio</span>
                <strong><?= e(texto_duracao($cargaDia['maquina']['medias']['ciclo'])) ?></strong>
            </div>
            <div>
                <span>Fila</span>
                <strong><?= e(texto_duracao($cargaDia['maquina']['medias']['fila'])) ?></strong>
            </div>
            <div>
                <span>Carga</span>
                <strong><?= e(texto_duracao($cargaDia['maquina']['medias']['carga'])) ?></strong>
            </div>
        </div>
        <?php if ($cargaDia['maquina']['grupos'] !== []): ?>
            <p class="recortes">
                <?php foreach ($cargaDia['maquina']['grupos'] as $grupo): ?>
                    <span><?php
                        $amostra = $grupo['linhas'][0] ?? [];
                        $fotos = [url_foto_pa($amostra)];
                        $legenda = $grupo['titulo'] . ' · ' . (int) $grupo['total'];
                        $destaque = false;
                        require __DIR__ . '/trecho_quem.php';
                    ?></span>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
        <?php if ($cargaDia['operador']['grupos'] !== []): ?>
            <p class="recortes">
                <?php foreach ($cargaDia['operador']['grupos'] as $grupo): ?>
                    <span><?php
                        $amostra = $grupo['linhas'][0] ?? [];
                        $fotos = [url_foto_pessoa($amostra)];
                        $legenda = $grupo['titulo'] . ' · ' . (int) $grupo['total'];
                        $destaque = false;
                        require __DIR__ . '/trecho_quem.php';
                    ?></span>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
    </section>

    <section class="painel-bloco">
        <h2><a href="<?= e(url('lavra/resumo')) ?>">Lavra</a></h2>
        <div class="tempos">
            <div>
                <span>Viagens</span>
                <strong><?= (int) $lavraDia['caminhao']['viagens'] ?></strong>
            </div>
            <div>
                <span>Produção</span>
                <strong><?= e(texto_capacidade($lavraDia['caminhao']['producao']['toneladas'] ?? null) ?: '—') ?></strong>
            </div>
            <div>
                <span>Ciclo médio</span>
                <strong><?= e(texto_duracao($lavraDia['caminhao']['medias']['ciclo'])) ?></strong>
            </div>
            <div>
                <span>Fila</span>
                <strong><?= e(texto_duracao($lavraDia['caminhao']['medias']['fila'])) ?></strong>
            </div>
            <div>
                <span>Carga</span>
                <strong><?= e(texto_duracao($lavraDia['caminhao']['medias']['carga'])) ?></strong>
            </div>
            <div>
                <span>Estrada</span>
                <strong><?= e(texto_duracao($lavraDia['caminhao']['medias']['ida'])) ?></strong>
            </div>
            <div>
                <span>Volta</span>
                <strong><?= e(texto_duracao($lavraDia['caminhao']['medias']['retorno'])) ?></strong>
            </div>
        </div>
        <?php if ($lavraDia['caminhao']['grupos'] !== []): ?>
            <p class="recortes">
                <?php foreach ($lavraDia['caminhao']['grupos'] as $grupo): ?>
                    <span><?php
                        $amostra = $grupo['linhas'][0] ?? [];
                        $fotos = [url_foto_caminhao($amostra)];
                        $legenda = $grupo['titulo'] . ' · ' . (int) $grupo['viagens'];
                        $producaoGrupo = texto_capacidade($grupo['producao']['toneladas'] ?? null);
                        if ($producaoGrupo !== '') {
                            $legenda .= ' · ' . $producaoGrupo;
                        }
                        $destaque = false;
                        require __DIR__ . '/trecho_quem.php';
                    ?></span>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
        <?php $faltando = $lavraDia['caminhao']['producao']['faltando'] ?? []; require __DIR__ . '/trecho_sem_capacidade.php'; ?>
        <?php if ($lavraDia['motorista']['grupos'] !== []): ?>
            <p class="recortes">
                <?php foreach ($lavraDia['motorista']['grupos'] as $grupo): ?>
                    <span><?php
                        $amostra = $grupo['linhas'][0] ?? [];
                        $fotos = [url_foto_pessoa($amostra)];
                        $legenda = $grupo['titulo'] . ' · ' . (int) $grupo['viagens'];
                        $producaoGrupo = texto_capacidade($grupo['producao']['toneladas'] ?? null);
                        if ($producaoGrupo !== '') {
                            $legenda .= ' · ' . $producaoGrupo;
                        }
                        $destaque = false;
                        require __DIR__ . '/trecho_quem.php';
                    ?></span>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
    </section>
</div>
</div>
