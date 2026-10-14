<?php
$novo = $pessoa === null || empty($pessoa['id']);
$fixo = !$novo && Auth::administradorFixo((string) ($pessoa['nome'] ?? ''));
$admin = !$novo && (int) ($pessoa['administrador'] ?? 0) === 1;
$funcao = is_array($pessoa) ? (string) ($pessoa['funcao'] ?? Pessoas::funcao($pessoa)) : '';
?>
<section class="cabeca">
    <div>
        <p class="olho">Pessoas</p>
        <h1><?= $novo ? 'Nova pessoa' : e((string) (($pessoa['nome_completo'] ?? '') !== '' ? $pessoa['nome_completo'] : $pessoa['nome'])) ?></h1>
    </div>
</section>
<form method="post" class="formulario" enctype="multipart/form-data" action="<?= e(url('usuarios' . ($novo ? '/novo' : '/' . $pessoa['id']))) ?>">
    <?= csrf_field() ?>
    <label class="campo">
        <span>Usuário</span>
        <input type="text" name="nome" maxlength="40" required value="<?= e((string) ($pessoa['nome'] ?? '')) ?>" <?= $fixo ? 'readonly' : '' ?> autofocus>
        <small class="dica">O primeiro nome. É com ele que a pessoa entra e que o trabalho fica registrado.</small>
    </label>
    <label class="campo">
        <span>Nome completo</span>
        <input type="text" name="nome_completo" maxlength="120" required value="<?= e((string) ($pessoa['nome_completo'] ?? $pessoa['nome'] ?? '')) ?>">
    </label>
    <div class="campo">
        <span>Foto</span>
        <?php if (!$novo && !empty($pessoa['foto'])): ?>
            <img class="foto-pessoa" src="<?= e(url('usuarios/' . $pessoa['id'] . '/foto') . '?v=' . Fotos::versao((string) $pessoa['foto'])) ?>" alt="">
        <?php endif; ?>
        <input type="file" name="foto" accept="image/jpeg,image/png,image/webp">
        <div class="enquadro" hidden>
            <div class="enquadro-moldura">
                <img alt="">
            </div>
            <label class="enquadro-zoom">
                <span>Aproximar</span>
                <input type="range" min="1" max="3" step="0.01" value="1">
            </label>
            <small class="dica">Arraste a foto para deixar o rosto inteiro dentro do quadrado.</small>
        </div>
        <small class="dica">JPG, PNG ou WebP, até 10 MB. Sem arquivo novo, a foto atual permanece.</small>
    </div>
    <?php if (!$novo && !empty($pessoa['foto'])): ?>
        <label class="check">
            <input type="checkbox" name="remover_foto" value="1">
            <span>Remover foto</span>
        </label>
    <?php endif; ?>
    <?php if ($admin): ?>
        <p class="meta">Acesso administrativo, definido no servidor.</p>
    <?php else: ?>
        <fieldset class="maquinas">
            <legend>Função</legend>
            <label class="escolha">
                <input type="radio" name="funcao" value="motorista" required <?= $funcao === 'motorista' ? 'checked' : '' ?>>
                <span>Motorista<small>Caminhão</small></span>
            </label>
            <label class="escolha">
                <input type="radio" name="funcao" value="operador" required <?= $funcao === 'operador' ? 'checked' : '' ?>>
                <span>Operador<small>Pá carregadeira</small></span>
            </label>
            <label class="escolha">
                <input type="radio" name="funcao" value="ambos" required <?= $funcao === 'ambos' ? 'checked' : '' ?>>
                <span>Ambos<small>Caminhão e pá carregadeira</small></span>
            </label>
        </fieldset>
    <?php endif; ?>
    <label class="campo">
        <span>Senha</span>
        <input type="password" name="senha" maxlength="72" autocomplete="new-password" <?= $novo ? 'required' : '' ?>>
        <small class="dica"><?= $novo ? 'Obrigatória neste cadastro.' : 'Em branco, a senha atual permanece.' ?></small>
    </label>
    <?php if (!$fixo): ?>
        <label class="check">
            <input type="checkbox" name="ativo" value="1" <?= $pessoa === null || (int) ($pessoa['ativo'] ?? 1) === 1 ? 'checked' : '' ?>>
            <span>Ativo</span>
        </label>
    <?php endif; ?>
    <div class="botoes">
        <button type="submit" class="btn primario">Salvar</button>
        <a class="btn secundario" href="<?= e(url('usuarios')) ?>">Voltar</a>
    </div>
</form>
<script>
(function () {
    var form = document.querySelector('main form');
    var input = form.querySelector('input[type="file"][name="foto"]');
    var bloco = form.querySelector('.enquadro');
    var moldura = bloco.querySelector('.enquadro-moldura');
    var imagem = moldura.querySelector('img');
    var zoomInput = bloco.querySelector('input[type="range"]');
    var previa = form.querySelector('.foto-pessoa');
    var base = 1;
    var zoom = 1;
    var offsetX = 0;
    var offsetY = 0;
    var arrastando = false;
    var origemX = 0;
    var origemY = 0;
    var ponteiroX = 0;
    var ponteiroY = 0;
    var pronto = false;

    function lado() {
        return moldura.clientWidth;
    }

    function limitar() {
        var medida = lado();
        var fator = base * zoom;
        var largura = imagem.naturalWidth * fator;
        var altura = imagem.naturalHeight * fator;
        offsetX = Math.min(0, Math.max(medida - largura, offsetX));
        offsetY = Math.min(0, Math.max(medida - altura, offsetY));
    }

    function desenhar() {
        var fator = base * zoom;
        imagem.style.width = (imagem.naturalWidth * fator) + 'px';
        imagem.style.height = (imagem.naturalHeight * fator) + 'px';
        imagem.style.left = offsetX + 'px';
        imagem.style.top = offsetY + 'px';
    }

    function cobrir() {
        var medida = lado();
        base = Math.max(medida / imagem.naturalWidth, medida / imagem.naturalHeight);
        zoom = 1;
        zoomInput.value = '1';
        var fator = base * zoom;
        offsetX = (medida - imagem.naturalWidth * fator) / 2;
        offsetY = (medida - imagem.naturalHeight * fator) / 2;
        limitar();
        desenhar();
    }

    input.addEventListener('change', function () {
        var arquivo = input.files && input.files[0];
        pronto = false;
        if (!arquivo) {
            bloco.hidden = true;
            return;
        }
        var leitor = new FileReader();
        leitor.onload = function () {
            imagem.onload = function () {
                bloco.hidden = false;
                if (previa) previa.hidden = true;
                cobrir();
            };
            imagem.src = leitor.result;
        };
        leitor.readAsDataURL(arquivo);
    });

    zoomInput.addEventListener('input', function () {
        var medida = lado();
        var antes = base * zoom;
        var centroX = (medida / 2 - offsetX) / antes;
        var centroY = (medida / 2 - offsetY) / antes;
        zoom = Number(zoomInput.value);
        var fator = base * zoom;
        offsetX = medida / 2 - centroX * fator;
        offsetY = medida / 2 - centroY * fator;
        limitar();
        desenhar();
    });

    moldura.addEventListener('pointerdown', function (evento) {
        arrastando = true;
        ponteiroX = evento.clientX;
        ponteiroY = evento.clientY;
        origemX = offsetX;
        origemY = offsetY;
        moldura.setPointerCapture(evento.pointerId);
    });
    moldura.addEventListener('pointermove', function (evento) {
        if (!arrastando) return;
        offsetX = origemX + (evento.clientX - ponteiroX);
        offsetY = origemY + (evento.clientY - ponteiroY);
        limitar();
        desenhar();
    });
    moldura.addEventListener('pointerup', function () { arrastando = false; });
    moldura.addEventListener('pointercancel', function () { arrastando = false; });

    form.addEventListener('submit', function (evento) {
        if (pronto || bloco.hidden || !imagem.naturalWidth) return;
        evento.preventDefault();
        var destino = 640;
        var tela = document.createElement('canvas');
        tela.width = destino;
        tela.height = destino;
        var contexto = tela.getContext('2d');
        var medida = lado();
        var fator = base * zoom;
        var origem = -offsetX / fator;
        var origemTopo = -offsetY / fator;
        var trecho = medida / fator;
        contexto.drawImage(imagem, origem, origemTopo, trecho, trecho, 0, 0, destino, destino);
        tela.toBlob(function (blob) {
            if (blob) {
                var recorte = new File([blob], 'foto.jpg', { type: 'image/jpeg' });
                var dados = new DataTransfer();
                dados.items.add(recorte);
                input.files = dados.files;
            }
            pronto = true;
            form.requestSubmit();
        }, 'image/jpeg', 0.92);
    });
})();
</script>
