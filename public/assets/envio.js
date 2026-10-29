(function () {
    // Um toque por envio: o botão vira "Enviando…" e nenhum outro formulário da tela sai enquanto isso.
    // Num toque duplo, o segundo toque cai no botão da tela nova, que surge no mesmo lugar: por isso um envio
    // logo depois de outro é ignorado, e também um toque logo depois de os cartões mudarem de lugar.
    // Sem sinal, a ação não sai do tablet.
    var PRAZO = 15000;
    var CALMA = 1000;
    // O servidor diz quanto falta da janela quando esta tela veio logo depois de uma ação.
    var calmaAte = performance.now() + (Number(document.body.getAttribute('data-calma')) || 0);
    // Depois que os cartões mudam de lugar, só os formulários dos cartões esperam.
    var calmaCartoesAte = 0;
    var saindo = null;

    document.addEventListener('fluxo:trocou', function () {
        calmaCartoesAte = performance.now() + CALMA;
    });

    function apressado(form) {
        var agora = performance.now();
        if (agora < calmaAte) return 'Toque repetido ignorado. Confira a tela e toque de novo se precisar.';
        if (agora < calmaCartoesAte && form.closest('#fluxo')) return 'A tela acabou de mudar. Confira e toque de novo.';
        return '';
    }

    function avisar(form, texto, tipo, classe) {
        // O formulário do cabeçalho (Sair) recebe o aviso no topo da página, fora do menu.
        var noTopo = !!form.closest('header');
        var pai = noTopo ? (document.querySelector('main') || document.body) : form.parentNode;
        var antes = noTopo ? pai.firstChild : form;
        var anterior = noTopo ? pai.firstElementChild : form.previousElementSibling;
        var aviso = anterior && anterior.classList.contains('envio-aviso') ? anterior : null;
        if (!aviso) {
            aviso = document.createElement('p');
            aviso.setAttribute('role', 'alert');
            pai.insertBefore(aviso, antes);
        }
        aviso.className = 'aviso ' + (classe || 'erro') + ' envio-aviso';
        aviso.setAttribute('data-tipo', tipo);
        aviso.textContent = texto;
        if (aviso.scrollIntoView) aviso.scrollIntoView({ block: 'nearest' });
    }

    function bloqueio() {
        if (navigator.onLine === false) {
            return 'Sem sinal. A ação NÃO foi enviada. Tente de novo quando o sinal voltar.';
        }
        var conexao = window.operacoesConexao;
        return conexao && conexao.bloqueio ? conexao.bloqueio : '';
    }

    function liberar(form, semResposta) {
        clearTimeout(form._envioPrazo);
        form.removeAttribute('data-enviando');
        if (saindo === form) saindo = null;
        form.querySelectorAll('button, input[type=submit]').forEach(function (botao) {
            botao.disabled = false;
            botao.classList.remove('enviando');
            if (botao.hasAttribute('data-texto')) {
                botao.textContent = botao.getAttribute('data-texto');
                botao.removeAttribute('data-texto');
            }
        });
        if (semResposta) {
            avisar(form, 'Sem resposta do servidor. Confira a tela antes de tocar de novo.', 'sem-resposta');
            document.dispatchEvent(new CustomEvent('envio:liberado'));
        }
    }

    document.addEventListener('submit', function (evento) {
        var form = evento.target;
        if (evento.defaultPrevented || !form || form.nodeName !== 'FORM') return;
        if ((form.getAttribute('method') || '').toLowerCase() !== 'post') return;
        if (saindo !== null) {
            evento.preventDefault();
            return;
        }
        var cedo = apressado(form);
        if (cedo) {
            evento.preventDefault();
            avisar(form, cedo, 'repetido', 'atencao');
            return;
        }
        var motivo = bloqueio();
        if (motivo) {
            evento.preventDefault();
            avisar(form, motivo, 'sem-rede');
            document.dispatchEvent(new CustomEvent('envio:barrado'));
            return;
        }

        saindo = form;
        form.setAttribute('data-enviando', '1');
        var botao = evento.submitter || form.querySelector('button[type=submit], button:not([type])');
        if (botao && botao.nodeName === 'BUTTON') {
            botao.classList.add('enviando');
            // Botões com foto ou partes (o rosto da entrada) mantêm o conteúdo.
            if (botao.children.length === 0) {
                botao.setAttribute('data-texto', botao.textContent);
                botao.textContent = 'Enviando…';
            }
        }
        // Desliga os botões depois que o navegador já montou os dados do envio.
        setTimeout(function () {
            form.querySelectorAll('button, input[type=submit]').forEach(function (b) { b.disabled = true; });
        }, 0);
        form._envioPrazo = setTimeout(function () { liberar(form, true); }, PRAZO);
        document.dispatchEvent(new CustomEvent('envio:saindo'));
    });

    // O sinal voltou: o aviso de "sem sinal" deixa de valer.
    document.addEventListener('conexao:ok', function () {
        document.querySelectorAll('.envio-aviso[data-tipo="sem-rede"]').forEach(function (aviso) {
            aviso.remove();
        });
    });

    window.addEventListener('pageshow', function (evento) {
        if (evento.persisted) {
            document.querySelectorAll('form[data-enviando]').forEach(function (form) { liberar(form, false); });
        }
    });
})();

(function () {
    // Pagamento previsto: +15 ou +30 dias a partir do dia da nota (ou de hoje, sem ele).
    document.addEventListener('click', function (evento) {
        var botao = evento.target.closest('[data-prazo]');
        if (!botao) return;
        var form = botao.form;
        var bloco = botao.closest('.previsto-pagamento');
        var campo = (form && form.querySelector('[name=nf_emitida_em]')) || null;
        var base = (campo && campo.value.slice(0, 10))
            || (form && form.querySelector('[name=nf_emitida_data]') && form.querySelector('[name=nf_emitida_data]').value)
            || (bloco && bloco.getAttribute('data-base'))
            || (form && form.querySelector('[name=data]') && form.querySelector('[name=data]').value.slice(0, 10))
            || '';
        var dia = /^\d{4}-\d{2}-\d{2}$/.test(base) ? new Date(base + 'T12:00:00') : new Date();
        dia.setDate(dia.getDate() + Number(botao.getAttribute('data-prazo')));
        var mes = String(dia.getMonth() + 1).padStart(2, '0');
        bloco.querySelector('[name=nf_previsto]').value = dia.getFullYear() + '-' + mes + '-' + String(dia.getDate()).padStart(2, '0');
    });
})();
