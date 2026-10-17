<?php
// Mude junto com android:versionName do android/AndroidManifest.xml quando montar uma versão nova do app.
$versaoApp = '1.2';
?>
<section class="cabeca">
    <div>
        <p class="olho">Tablet</p>
        <h1>App da Próspera para Android</h1>
    </div>
</section>

<?php if (!empty($noApp)): ?>
    <p class="aviso">Você já está usando o app da Próspera.</p>
<?php endif; ?>

<p>O app abre o sistema em tela cheia, com o ícone da Próspera na tela do tablet. As telas são as mesmas do sistema: as mudanças chegam sozinhas, sem instalar de novo.</p>

<div class="botoes">
    <a class="btn primario" href="<?= e(url('baixar/prospera.apk') . '?v=' . rawurlencode($versaoApp)) ?>">Baixar o app (versão <?= e($versaoApp) ?>)</a>
</div>

<h2>Como instalar no tablet</h2>
<ol class="passos">
    <li>Toque em <strong>Baixar o app</strong>. Se o Chrome avisar que o arquivo pode ser perigoso, toque em <strong>Baixar mesmo assim</strong>.</li>
    <li>Quando terminar, toque em <strong>Abrir</strong>. Se o Android pedir permissão, toque em <strong>Configurações</strong>, ligue <strong>Permitir desta fonte</strong> e volte.</li>
    <li>Toque em <strong>Instalar</strong>. Se aparecer um aviso do Play Protect, toque em <strong>Mais detalhes</strong> e em <strong>Instalar mesmo assim</strong>.</li>
    <li>Abra o app <strong>Próspera</strong> pelo ícone.</li>
    <li>Na primeira vez, entre com o usuário do administrativo e autorize o tablet em <strong>Pessoas › Tablets</strong>. O app tem a memória dele, separada do Chrome: mesmo que o tablet já estivesse autorizado no Chrome, autorize de novo dentro do app.</li>
</ol>

<h2>Atualizar o app</h2>
<p>Já tem o app no tablet? Toque em <strong>Baixar o app</strong> e instale por cima, do mesmo jeito. Não precisa desinstalar nem autorizar o tablet de novo. A versão 1.1 toca o som de notificação e vibra nos avisos do pátio (carga nova, alterada ou cancelada), mesmo sem ninguém ter tocado na tela. A versão 1.2 deixa escolher o PDF ou o XML da nota fiscal no app (antes só aparecia foto).</p>

<h2>Travar o tablet no app (opcional)</h2>
<p>Ative <strong>Configurações › Segurança e privacidade › Mais configurações de segurança › Fixar app</strong>. Depois abra o app, vá na lista de apps recentes (o botão quadrado), toque no ícone da Próspera e em <strong>Fixar</strong>.</p>

<p class="nota"><a href="<?= e(url('login')) ?>">Voltar para a entrada</a></p>
