# Pátio — fila de carregamento

O administrativo lança a ordem, confere cliente, pedido, nota, material e quantidade, e libera. No tablet, o operador de pá carregadeira assume a pá, inicia e finaliza para a conferência.

O ciclo de lavra conta as viagens do caminhão: fila, carga, ida até o britador e retorno vazio. Na lavra, o operador de escavadeira hidráulica assume a escavadeira e faz a carga. O motorista marca a fila, a ida e o retorno. Quem opera as duas máquinas pode estar no pátio ou na lavra, um lugar por vez. A tela fica em `/lavra`.

## Documentação

Este README é o guia de uso. Para quem desenvolve (pessoas ou agentes de IA):

- [Produto e escopo](PRODUCT.md)
- [Preparação local](docs/onboarding.md)
- [Código e arquitetura](docs/architecture.md)
- [Telas e acessibilidade](docs/design.md)
- [Qualidade e testes](docs/quality.md)
- [Instruções para agentes](AGENTS.md)
- [Ferramentas, decisões e pendências](docs/setup.md)
- [O que mudou](CHANGELOG.md)

## Lançar o carregamento pela nota fiscal

Em **Fila › Carregamento › Nova ordem**, o quadro **Ler a nota fiscal** recebe o **XML** ou o **PDF (DANFE)** da nota. Toque em **Ler nota**: o sistema preenche o cliente, o pedido, o número da nota, o material, a quantidade, a unidade e a hora em que a nota foi emitida. Confira, marque **Liberar agora para o pátio** se quiser e toque em **Lançar ordem**.

- **XML**: leitura exata, campo por campo. A hora é a da emissão da nota.
- **PDF**: o sistema lê o texto do DANFE. O número e a série vêm da chave de acesso (conferida pelo dígito verificador); a hora vem do protocolo de autorização (segundos depois da emissão) ou da hora de saída. Alguns DANFEs, como o da Próspera, só mostram a data da emissão, sem a hora: a tela avisa, o sistema guarda a data (a nota conta no vendido desse dia) e, para gravar a hora, use o XML. PDF escaneado (foto) não tem texto: use o XML.
- **Pedido**: o do campo de pedido da nota (xPed) ou, se não tiver, o "Pedido: ..." das informações complementares.
- **Material**: o do cadastro com o mesmo nome do material da nota (sem diferença de acento ou maiúscula; "BRITA 1" acha "Brita 1"). Se não achar, a tela avisa e você escolhe. Se a nota tiver mais de um material, o primeiro vai em **Material** e os outros (até 4) vão em **Outros materiais**, cada um com a quantidade dele. A tela avisa para conferir. Itens em outra unidade (t e m³ na mesma nota) não entram: a carga usa uma unidade só, e a tela avisa.

### Carga com mais de um material

Quando o mesmo caminhão leva mais de um material na mesma nota (por exemplo, 10 t de Brita 1 e 5 t de Pó de pedra), toque em **+ Outro material nesta carga** no formulário da ordem e preencha o material e a quantidade de cada um. São até 5 materiais por carga, todos na unidade da ordem. Para mais que isso, faça uma nota por vez.

- A carga sai **inteira, numa viagem só**: não pode ser marcada como **Carregamento fracionado**.
- O cartão do pátio mostra os materiais um embaixo do outro. O operador da pá inicia e finaliza uma vez só, sem digitar nada a mais.
- A ficha, a fila, o Controle (com a soma no vendido, no carregado e no carregado do dia), o Monitor da TV e "Meus trabalhos" mostram todos os materiais.
- A nota vai para o Fin Pro com os materiais juntos, por exemplo "Brita 1 (10,00 t) + Pó de pedra (5,00 t)".
- **Corrigir a quantidade carregada** vale só para a carga de um material. Antes de a pá começar, os materiais da carga se corrigem em **Corrigir dados**.
- **Unidade**: t, m³ ou un. Se a nota trouxer outra unidade (por exemplo SC), o campo fica em **Escolha** e a tela avisa.
- **Nota repetida**: a tela avisa se a nota já está em outro carregamento, e o sistema não deixa lançar a mesma nota (mesma chave de acesso) duas vezes.

A hora em que o caminhão foi carregado continua vindo do pátio (início e fim do carregamento). A ficha da ordem mostra a hora da nota e quanto tempo depois dela o carregamento começou.

Ler o PDF precisa do programa `pdftotext` (pacote `poppler-utils`), que já vem no Docker. Num servidor sem ele (por exemplo a hospedagem compartilhada da Hostinger), use o XML.

## Carregamento fracionado

Quando o cliente leva o material da nota em várias viagens (por exemplo, uma nota de 1,5 m³ buscada em 3 viagens de 0,5 m³), marque **Carregamento fracionado** ao lançar a ordem. A nota é lançada uma vez só, com a quantidade total.

- **No pátio**: ao finalizar, o operador informa **quanto foi carregado nesta viagem**. O sistema não aceita mais do que o saldo da nota.
- **O que sobra**: vira a próxima viagem (CAR-012-2, CAR-012-3...), com a situação **Saldo a buscar**. Ela não entra na fila do pátio sozinha.
- **Quando o cliente volta**: toque em **O cliente voltou: liberar viagem** na ficha da ordem, ou em **Liberar viagem** no **Controle de carga**. A viagem vai para a fila do pátio com o saldo que falta.
- **Cliente não vai buscar o resto**: **Encerrar saldo**, na viagem com saldo, na viagem 1 ou no **Controle de carga** (cancela só o que faltava; o que já foi carregado continua). A viagem 1 não se cancela enquanto há saldo aberto: primeiro encerre o saldo.
- **Viagem liberada e o cliente não veio**: na ficha da viagem, **O cliente não veio: voltar para Saldo a buscar** (sai da fila do pátio sem cancelar).
- **Quantidade errada ou fracionado esquecido**: na ficha de uma viagem finalizada, **Corrigir a quantidade carregada**. O saldo da nota é refeito: se faltar material, a próxima viagem fica em **Saldo a buscar** (a ordem vira fracionada); se a nota ficar toda carregada, a viagem que esperava é encerrada. Saldo encerrado pelo administrativo continua encerrado.
- A ficha da ordem mostra todas as viagens da nota, com quanto cada uma levou e o saldo.

## Controle de carga e vendido do dia

**Fila › Controle** (só administrativo) mostra, para o dia escolhido:

- **Vendido no dia**: a soma do valor das notas emitidas no dia (o dia da emissão da nota, também quando o PDF só traz a data; sem nenhuma, o dia do lançamento). Cada nota conta uma vez, mesmo fracionada. Notas canceladas não contam. Se alguma nota estiver sem valor, a tela avisa quantas.
- **Carregado no dia**: o que saiu do pátio, em t e m³, contando cada viagem do fracionado pelo que foi carregado de verdade.
- **Saldo a buscar**: as notas fracionadas esperando o cliente voltar, com o botão **Liberar viagem**.
- **Notas do dia**: cada nota com o vendido, o carregado, o saldo, o valor e a situação.

O **valor da nota** vem sozinho ao ler o XML (valor total da nota) ou o PDF (VALOR TOTAL DA NOTA). Também dá para digitar no campo **Valor da nota (R$)** da ordem. O Painel mostra o **Vendido hoje** e quantas notas têm saldo a buscar.

**Nota sem valor** (lançada antes desta função, ou digitada sem valor): não entra no vendido do dia, e o Controle avisa quantas são. Digite o valor direto na coluna **Valor** de **Notas do dia** e toque em **Salvar**, ou na ficha da ordem (**Salvar valor**). Vale também para carregamento já concluído. Na ficha dá para **Corrigir valor** depois de concluído.

## Monitor da TV

A aba **Monitor** foi feita para ser lida de longe, numa TV ligada o dia todo. Toque em **Tela cheia** na TV.

- **No alto, três números**: o **entregue aos clientes hoje** (t, cargas e quantos na fila), as **viagens ao britador primário** (t levadas e o tempo da viagem completa) e o **vendido hoje** em R$, dividido por forma de pagamento (Pix, Dinheiro, Cartão, Cheque, Boleto, A prazo; "Sem forma" nas notas antigas). O vendido aparece sempre: quem estiver na frente da TV vê o valor.
- **Clientes · Pátio**: cada pá com o que está fazendo, em palavras: **carregando** (verde, com o cliente, o tempo e quem opera), **parada** (vermelho, com o problema aberto da máquina), **na oficina** (vermelho, desde quando e o motivo) ou **livre**. Embaixo, a fila numerada com "espera N min": 30 minutos ou mais fica **amarelo**, 1 hora ou mais fica **vermelho**. O que não cabe aparece como "+ N na fila".
- **Lavra → Britador Primário**: os problemas abertos das escavadeiras e dos caminhões no topo (até 2; o resto como "+ N"), a faixa amarela da limpeza da lavra e a pista com os caminhões: amarelo na lavra, azul carregado indo ao britador primário, cinza voltando vazio, contorno vermelho se o caminhão tem problema aberto. Os caminhões deslizam pela pista entre uma atualização e outra. O motorista só marca a chegada no britador: 3 minutos depois o caminhão passa a aparecer voltando vazio. Embaixo, **Máquinas em uso**: cada escavadeira e caminhão que está com alguém, a pessoa e o que está fazendo; fica **verde** quando está sendo usada (carregando, limpando a lavra ou em viagem). As pás aparecem só na coluna do pátio. Escavadeira ou caminhão na oficina aparece num selo vermelho ao lado de **Máquinas em uso**.
- **Britador Secundário**: a situação agora (Britando, Parado por problema, Terminou às, Sem britagem hoje), a hora em que **começou** e a que **terminou** a britagem e os **problemas do dia** (abertos em vermelho, resolvidos em cinza com o horário).
- **Rodapé**: a hora da última atualização e a melhor hora do dia no pátio e na lavra.
- Os relógios contam sozinhos e os números se atualizam a cada 8 segundos. Se a internet da TV cair, aparece **"Sem atualização desde ..."** em amarelo; depois de 3 minutos os números ficam apagados, para ninguém ler número velho como se fosse de agora.
- As letras são menores na página do Monitor (no computador do escritório) e um pouco maiores na **Tela cheia** da TV.
- A imagem anda 2 pixels de tempos em tempos para não marcar a tela, e a página recarrega sozinha às 3h da manhã.

## Britador secundário

O operador do britador secundário tem uma função só para ele: em **Pessoas**, marque **Operador do britador secundário** (sem outra função). Ele não assume máquina: entra direto na tela **Britador**.

- **Iniciar britagem** quando começa e **Terminar britagem** quando para. Pode haver mais de uma britagem no dia; o Monitor mostra o primeiro início e o último fim.
- **Avisar problema**: o que aconteceu e se o britador parou. O problema fica aberto (no tablet e no Monitor) até ele tocar em **Resolvido**.
- O administrativo também abre a tela pelo menu **Britador** e pode marcar no lugar do operador.

## Forma de pagamento da nota

Ao lançar a ordem, escolha a **Forma de pagamento** (Pix, Transferência (TED/DOC), Dinheiro, Cartão, Cheque, Boleto ou A prazo). O PDF da nota da Próspera não traz essa informação; quando a nota é lida pelo XML, o campo já vem preenchido. A ordem só é liberada para o pátio com a forma escolhida. Para corrigir depois, use o quadro do valor na ficha da ordem. O Monitor soma o vendido do dia por forma.

**Nota paga em mais de uma forma** (por exemplo, Pix R$ 1.000,00 e Cartão R$ 905,60): escolha a primeira forma em **Forma de pagamento** e toque em **+ Outra forma de pagamento**. Informe quanto foi pago na primeira forma e, em cada linha, a outra forma e o valor (até 4 formas). A soma precisa bater com o **Valor da nota**; se não bater, o sistema avisa e não grava. Lendo o **XML**, as formas e os valores já vêm preenchidos quando a nota traz essa informação (o PDF não traz). A ficha mostra todas as formas, o Monitor conta cada parte na sua forma, e para corrigir use o quadro do valor na ficha (o valor sozinho, no Controle, não muda uma nota com várias formas).

**Venda paga depois** (a prefeitura paga por transferência em 15 ou 30 dias): escolha **Transferência (TED/DOC)** e preencha **Pagamento previsto para**, ou toque em **+15 dias** ou **+30 dias** (contados do dia da nota). Deixe vazio quando o pagamento é na hora. Lendo o **XML**, "Depósito bancário" vira Transferência e o vencimento da nota já preenche o dia. A ficha mostra o dia previsto e o corrige no quadro do valor. O Monitor conta a venda no dia da nota, como as outras.

**Venda com empenho** (a prefeitura manda o empenho, a carga sai e a nota é emitida depois): na Nova ordem, marque **Nota fiscal sai depois (empenho)**. O número da nota fica opcional e a forma vem como Transferência. No campo **Pedido**, ponha o número do empenho e, se quiser, anexe o **PDF ou a foto do empenho** (abre na ficha, em "Ver o empenho"). Libere a ordem normalmente. Enquanto a nota não chega, a fila mostra "NF a emitir (empenho)" e o **Painel** lista a venda em **Aguardando nota fiscal**. Quando a nota for emitida, abra a ficha da ordem: no quadro **Aguardando nota fiscal**, leia o PDF ou o XML (ou preencha número, data e valor à mão), toque em **+15 dias** ou **+30 dias** e em **Lançar a nota fiscal**. Só aí o vendido conta (no dia da nota) e a nota vai para o Fin Pro.

## Integração com o Fin Pro

As notas pagas por **Pix** ou **Transferência (TED/DOC)**, ou com alguma parte em Pix ou Transferência (nota paga em mais de uma forma), vão sozinhas para o Fin Pro, na aba "Transferências de clientes", para conferir com o extrato do banco. Na nota de várias formas, o Fin Pro espera o comprovante só da parte em Pix ou Transferência, e dá para guardar lá a foto do comprovante do cartão. Vai a nota quando é lançada, quando a forma é corrigida na ficha e quando você toca em **Reenviar ao Fin Pro** na ficha da ordem. As outras formas e as notas canceladas não vão.

- O PDF lido em **Ler a nota fiscal** fica guardado com a ordem e vai junto. Na ficha, o link **PDF da nota** abre o arquivo. Ordem lançada à mão fica sem PDF.
- O **CNPJ/CPF do cliente** é lido da nota (PDF ou XML) e vai junto para o Fin Pro. Ele aparece no formulário da ordem para conferir ou corrigir (pode digitar com ou sem pontos, e o CNPJ novo com letras também vale; o sistema confere os dígitos) e na ficha. Se a nota não trouxer, a leitura avisa e o campo pode ficar em branco.
- A ficha mostra "Enviada ao Fin Pro em ...", "Esperando envio ao Fin Pro: <motivo>" ou "Fin Pro não configurado".
- Se o Fin Pro estiver fora do ar, a nota espera e o sistema tenta de novo sozinho enquanto o Monitor da TV ou o Painel estiverem abertos (a cada 5 minutos, depois com mais intervalo).

Para ligar, no Easypanel, em **Ambiente**, acrescente as duas variáveis e toque em **Implantar**:

```
FINPRO_URL=
FINPRO_TOKEN=
```

`FINPRO_URL` é o endereço do Fin Pro (por exemplo `https://finpro.seudominio.com.br`). `FINPRO_TOKEN` é uma senha longa, a mesma que vai na `OPERACOES_TOKEN` do Fin Pro. Não mande essa senha por mensagem nem a coloque no GitHub.

## Local no Windows

O MariaDB da máquina precisa estar no ar (o mesmo da porta `3307`). Crie o banco uma vez:

```powershell
& "C:\Program Files\MariaDB 11.5\bin\mysql.exe" -h 127.0.0.1 -P 3307 -u root -p -e "CREATE DATABASE IF NOT EXISTS operacoes_prospera CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS 'operacoes'@'127.0.0.1' IDENTIFIED BY 'operacoes_local'; GRANT ALL PRIVILEGES ON operacoes_prospera.* TO 'operacoes'@'127.0.0.1'; FLUSH PRIVILEGES;"
```

As tabelas são criadas na primeira abertura. Suba a tela com:

```powershell
php -S 127.0.0.1:8081 -t public public/router.php
```

Abra `http://127.0.0.1:8081`. O primeiro acesso fica em `APP_USUARIOS`, no formato `nome:senha:funcao` (`admin`, `motorista`, `operador`, `escavadeira` ou `ambos`; `operador` vale para a pá e para a escavadeira). Quem está em `APP_ADMIN` entra como administrativo. As outras pessoas são cadastradas em Pessoas, com usuário, nome completo, foto e função. O usuário é o primeiro nome.

O usuário pode ser trocado em Pessoas: o histórico (viagens, cargas, horímetro, turnos, manutenções) passa para o nome novo, e quem está com a sessão aberta continua trabalhando. Quem vem de `APP_USUARIOS` tem o nome trocado no servidor, não na tela.

Em Pessoas, cada pessoa tem uma ou mais funções marcadas:

- **Motorista**: assume caminhão e abre a lavra (fila, ida e retorno).
- **Operador de pá carregadeira**: assume pá e abre o pátio.
- **Operador de escavadeira hidráulica**: assume escavadeira e abre a lavra (carga dos caminhões). Se não houver nenhuma pá cadastrada, também carrega no pátio com a escavadeira.

Cada pessoa só vê e assume as máquinas das funções marcadas. O administrativo visualiza e faz tudo, inclusive os cadastros de caminhões, pás e escavadeiras hidráulicas, a fila de carregamento, os materiais e as pessoas.

## Tablets autorizados

A entrada pela foto só aparece nos tablets que o administrativo autorizou: a pessoa toca na foto dela e digita a senha dela (a mesma cadastrada em Pessoas). Em qualquer outro aparelho, a tela de entrada mostra só usuário e senha. O administrativo não aparece nas fotos e entra sempre com usuário e senha.

Para ficar rápido no campo, use senhas de 4 números para a equipe. Cinco senhas erradas seguidas bloqueiam aquela pessoa por 15 minutos.

Em cada tablet, desligue no Chrome a opção de salvar senhas, para o tablet não guardar a senha de ninguém: menu do Chrome (⋮) › **Configurações** › **Gerenciador de senhas** › **Configurações** › desligue **Oferecer para salvar senhas** e **Login automático**. Se o Chrome perguntar "Salvar senha?", toque em **Nunca**.

Para autorizar um tablet, entre nele com o usuário e a senha do administrativo, abra Pessoas › Tablets, dê um nome ao aparelho e toque em Autorizar este aparelho. Depois toque em Sair: a entrada passa a mostrar as fotos da equipe. Na mesma tela dá para revogar um tablet perdido ou trocado.

A autorização fica num cookie do navegador. Se alguém limpar os dados do Chrome no tablet, autorize de novo.

Cinco senhas erradas seguidas para o mesmo usuário bloqueiam a entrada por 15 minutos naquele endereço. A senha do administrativo precisa ter pelo menos 8 caracteres.

## App do tablet

O sistema tem um app para Android: fica um ícone da Próspera na tela inicial e ele abre em tela cheia, sem a barra do Chrome. As telas são as do sistema: o que muda no Git chega a todos os tablets sozinho, sem instalar o app de novo.

**Instalar** (uma vez por tablet):

1. No tablet, abra o Chrome em `/app` no endereço do sistema (por exemplo `https://operacoes.fabbiol.tech/app`). Nos tablets autorizados, a faixa "Instale o app da Próspera neste tablet" tem o botão **Baixar o app**, que leva para lá.
2. Toque em **Baixar o app** e siga os passos da página: abrir o arquivo, permitir a instalação desta fonte e tocar em **Instalar** (se o Play Protect avisar, **Mais detalhes › Instalar mesmo assim**).
3. Abra o app **Próspera** pelo ícone. Na primeira vez, entre como administrativo e **autorize o tablet de novo** em Pessoas › Tablets: o app tem a memória dele, separada do Chrome.

Os tablets Positivo (Android 14 Go, Chrome 155) não oferecem "Instalar app" nem "Adicionar à tela inicial" no Chrome; por isso o app Android. Onde o Chrome oferece, o sistema também pode ser instalado direto pelo menu do Chrome, com o mesmo resultado.

No app não tem barra de endereço nem "puxar para atualizar": as telas da lavra e do pátio já se atualizam sozinhas, e o botão Voltar do Android funciona normalmente.

**O que o app faz a mais:**

- **Tela acesa**: enquanto a pessoa está com uma máquina, a tela do tablet não apaga. Ao soltar a máquina, o tablet volta a apagar a tela normalmente. Deixe o tablet carregando na máquina. O monitor da TV também fica com a tela acesa.
- **Sem conexão**: se o Wi-Fi cai ou o sistema não responde, aparece a tela "Sem conexão" da Próspera, no lugar da página de erro do Chrome. Ela tenta de novo sozinha a cada 10 segundos e volta para a tela da pessoa quando o sinal volta. Nada fica guardado no tablet: as telas sempre vêm do sistema, com a situação de agora.
- **Som e vibração nos avisos do pátio** (versão 1.1): o app toca o som de notificação do tablet e vibra quando chega uma carga nova, alterada ou cancelada, mesmo sem ninguém ter tocado na tela. No Chrome, o som só toca depois do primeiro toque na tela.

**Atualizar o app**: abra `/app` no tablet, toque em **Baixar o app** e instale por cima. Não precisa desinstalar nem autorizar o tablet de novo (a versão nova é assinada com a mesma chave).

**Travar o tablet no app** (opcional, para o operador não sair do sistema). O Android tem a opção **Fixar app**; não precisa instalar nada:

1. Abra **Configurações › Segurança e privacidade › Mais configurações de segurança › Fixar app** e ative. Ative também **Pedir PIN antes de liberar** (ou "Bloquear dispositivo ao liberar"). Os nomes podem mudar um pouco de um tablet para outro: se não achar, digite **fixar** na busca das Configurações.
2. Abra o app da Próspera.
3. Abra a lista de apps recentes (o botão quadrado, ou deslize de baixo para cima e segure no meio da tela).
4. Toque no ícone da Próspera, no alto do cartão do app, e em **Fixar**.
5. Para soltar: toque e segure **Voltar** e o **quadrado** ao mesmo tempo (ou, com gestos, deslize de baixo para cima e segure). O tablet pede o PIN.

Com o app fixado, o tablet não sai do sistema, não mostra notificações por cima e o botão de início não funciona até alguém com o PIN soltar.

## App Android: como montar uma versão nova

O código do app fica em `android/` (uma tela só, que abre o sistema). O arquivo pronto fica em `public/baixar/prospera.apk` (endereço `/baixar/prospera.apk`) e é publicado junto com o sistema. Não crie uma pasta `public/app`: o servidor confundiria com a página `/app`. Só precisa montar de novo se mudar o endereço do sistema ou o próprio app; mudanças nas telas do sistema não precisam.

1. Aumente `android:versionCode` e `android:versionName` em `android/AndroidManifest.xml`, `VERSAO` em `android/src/.../Principal.java` e `$versaoApp` em `views/app_android.php`.
2. Monte com a mesma chave de assinatura de sempre (o arquivo `prospera-app.jks`, guardado fora do Git):

```sh
apt-get install default-jdk android-sdk-platform-23 aapt dalvik-exchange zipalign apksigner
CHAVE=/caminho/prospera-app.jks CHAVE_SENHA='senha da chave' ENDERECO=https://operacoes.fabbiol.tech/ sh android/construir.sh
```

O Android só atualiza o app se a versão nova vier com a mesma chave. Sem a chave, é preciso desinstalar o app antigo em cada tablet antes de instalar o novo (e autorizar o tablet de novo). Nunca coloque a chave no Git: o repositório é público.

O que o app faz: abre só o endereço do sistema (outros endereços vão para o Chrome); mantém a tela acesa quando o sistema pede (com máquina assumida); mostra "Sem conexão" e tenta de novo sozinho; escolhe foto da galeria nos cadastros e o PDF ou XML da nota fiscal (versão 1.2); o botão Voltar volta uma tela e, no começo, deixa o app em segundo plano; o Android não oferece para guardar senhas digitadas no app.

## Sessão

As sessões ficam em `storage/sessoes` e duram o turno (14 horas). Ao publicar esta versão pela primeira vez, todos precisam entrar de novo, e quem estiver com máquina assumida perde o horímetro de entrada daquele turno. Publique entre turnos.

## Máquinas

Quem está com cada máquina fica gravado no banco (tabela `maquina_posse`), com o horímetro de entrada. A máquina continua com a pessoa se a sessão cair ou se ela entrar por outro tablet, e ninguém mais consegue assumir a mesma máquina até ela soltar.

Se alguém foi embora sem soltar, o administrativo abre a ficha da máquina e usa **Liberar máquina**. Com a leitura do painel, o turno da pessoa é gravado; sem leitura, a máquina só fica livre. Enquanto a máquina não for solta ou liberada, ela não pode ser desativada, e a pessoa que está com ela não pode ser desativada nem trocar para uma função que não usa essa máquina.

O administrativo não consegue iniciar uma carga com uma máquina que está com outra pessoa, nem para um operador que está com outra máquina.

## Aviso no tablet do pátio

No pátio, o tablet do operador avisa com som, vibração e um quadro grande na frente da tela quando o administrativo:

- **libera uma carga nova** (três bipes subindo);
- **altera uma carga** que está na fila ou carregando: cliente, pedido, nota, material, quantidade, unidade ou observações (dois bipes);
- **cancela uma carga** que estava liberada (dois bipes descendo);
- **tira da fila** a viagem do fracionado em que o cliente não veio (volta para Saldo a buscar; dois bipes descendo).

O quadro mostra o cliente, a carga e o número (CAR-...) e só some no **Ciente**. Na carga alterada, mostra também o que era antes ("Antes: 4,00 t · Brita 1") e a observação nova. Com muitos avisos, os quadros rolam e o **Ciente** fica sempre à vista. Enquanto ninguém tocar em Ciente, o som repete a cada 20 segundos, até 3 vezes. Se o operador estava em outra tela (iniciando ou finalizando uma carga), o aviso aparece quando ele volta ao pátio. Ao abrir o pátio pela primeira vez, as cargas que já estavam lá não avisam. O administrativo não recebe esses avisos.

O cartão da carga no pátio e as telas de iniciar e finalizar mostram a observação do administrativo. Com duas pás, só quem iniciou a carga (ou o administrativo) finaliza: no tablet do outro operador aparece "Carregando com ...". Se o administrativo corrigir a carga no mesmo momento em que a pá começa a carregar, a tela dele avisa que nada foi alterado.

No app da Próspera (versão 1.1) o som e a vibração são do próprio Android. No Chrome, sem o app, quem decide é o navegador: o som toca se o operador tocou em alguma tela do sistema antes de voltar ao pátio (por exemplo, no **Finalizar**) ou já tocou no pátio. Logo depois de abrir o navegador ou recarregar a página, só toca a partir do primeiro toque. O quadro aparece sempre. Para o som tocar sempre, use o app.

## Estoque das pilhas

O estoque de Brita 01, Pedrisco e outros materiais da pilha é contado pelas conchas da pá. Ele só soma o que entra: os caminhões dos clientes carregam na bica.

- **Preparar** (administrativo, uma vez): em **Cadastros › Pás carregadeiras**, preencha a **Concha** de cada pá em m³ (a 656 pega 3,3 m³). Em **Cadastros › Materiais**, marque **Tem estoque** nos materiais da pilha.
- **Lançar** (operador da pá): no pátio, toque em **Estoque**. Escolha o material, digite quantas conchas colocou e toque em **Lançar no estoque**. Vale a pá do turno e a hora do lançamento.
- **O que aparece**: para cada material, o total na pilha em m³, toneladas (1,45 t/m³) e conchas, o que entrou hoje e os últimos lançamentos com quem lançou.
- **Corrigir** (só o administrativo): no menu **Estoque**, toque em **Corrigir** no lançamento errado para mudar o material, as conchas ou a data, ou apagar. O administrativo também lança com data passada, escolhendo a pá. O operador que errou avisa o administrativo.
- A planilha antiga (de "até 05/10" a 21/10) entrou sozinha no estoque, dia por dia, com o usuário "Planilha".
- Cada lançamento guarda a concha da pá naquela hora. Mudar a concha no cadastro depois não altera o que já entrou.

## Limpeza da lavra

Quando a escavadeira para de carregar para limpar a lavra, o operador toca em **Iniciar limpeza da lavra** na tela de carga. Enquanto isso:

- a tela da escavadeira mostra **Limpeza da lavra** com o tempo e o botão **Terminar limpeza**;
- os motoristas veem "Retro 1 em limpeza da lavra" na tela dos caminhões;
- o **Painel** mostra a limpeza em andamento, com quem e desde quando, e o **Monitor da TV** mostra uma faixa amarela na área da lavra.

Durante a limpeza a escavadeira pode carregar os caminhões que chegam: a limpeza continua marcada, e os minutos de carga não contam como limpeza. A limpeza termina no **Terminar limpeza**, ao carregar no pátio com a escavadeira ou ao **soltar** a escavadeira. Limpeza esquecida aberta fecha sozinha no fim do dia em que começou, e o administrativo pode terminá-la pelo botão **Terminar** no Painel. Não dá para começar a limpeza no meio de uma carga.

**Tempo médio do Pusch** (lavra → britador → lavra): a espera do caminhão na fila enquanto a escavadeira que carregou ele estava limpando (e não carregando outro caminhão) sai da fila e do ciclo, e aparece à parte como **Limpeza** em cada viagem. O Painel e os Tempos do dia mostram o total de limpeza do dia. Também ficam fora da média:

- a viagem **encerrada sem voltar** (pneu, oficina, fim de turno): fora da volta e do ciclo, e da estrada se não chegou no britador;
- a viagem que foi para a **garagem** no britador: fora da volta e do ciclo;
- a etapa que **atravessa a meia-noite** (caminhão deixado na fila ou no britador de um dia para o outro).

Essas viagens continuam contando na produção e no número de viagens.

## Problemas das máquinas

Ao soltar qualquer máquina (pá carregadeira, escavadeira ou caminhão), pelo **Soltar** ou pelo **Sair**, o operador informa o horímetro e responde **Como ficou a máquina?**:

- **Tudo certo**: solta normalmente. O turno fica marcado como "entregou tudo certo".
- **Teve problema**: aparece **Qual foi o problema?** (por exemplo, "quebrou o ar-condicionado"). Sem escrever o problema, a máquina não é solta. O problema fica gravado com a máquina, quem avisou, quando e o horímetro.

O operador precisa responder uma das duas para soltar. Se o problema já foi avisado e ainda não foi resolvido, a tela de soltar mostra: basta marcar **Teve problema** e deixar o texto em branco (escreve só se for um problema novo).

**Caminhão que encerra a viagem** (Encerrar viagem): o motivo **Oficina** já vira problema do caminhão na hora, com o detalhe escrito em "O que aconteceu". Nos outros motivos, marque **É problema no caminhão** e escreva o que aconteceu (ex.: "pneu furou"): o escritório e a TV veem na hora, sem esperar o motorista soltar o caminhão. Ao soltar depois, "Teve problema" já vem marcado.

**Entrada digitada errada**: se o operador digitou o horímetro errado ao assumir e a saída é recusada, a tela de soltar tem **A entrada foi digitada errada?**: ele informa o horímetro certo da entrada e solta normalmente. A entrada errada fica anulada, com registro na ficha da máquina. Se já houve outra leitura depois da entrada, quem corrige é o administrativo.

O horímetro aceita ponto de milhar, igual ao que a tela mostra: "12.350" é 12.350 h; a casa decimal é a vírgula ("12.350,5").

Onde aparece:

- **Painel**: quadro **Problemas nas máquinas**, com os problemas abertos. Tocar num problema abre a ficha da máquina.
- **Monitor da TV**: faixa vermelha **Problemas nas máquinas**, enquanto houver algum aberto.
- **Manutenção**: os problemas abertos de cada máquina.
- **Ficha da máquina** (Cadastros): os problemas abertos, **Marcar resolvido** (com o que foi feito, se quiser), **Registrar problema** (quando o aviso chega por telefone) e os últimos resolvidos. Os turnos mostram se a pessoa entregou tudo certo ou avisou problema.
- **Escolher máquina**: quem vai pegar a máquina vê o problema aberto embaixo do nome dela.
- **Liberar máquina** (quando alguém foi embora sem soltar) também tem o campo **Problema na máquina**.

## Máquina na oficina e ordem de serviço

Quando uma máquina vai para a oficina, abra a ficha dela (Cadastros → a máquina) e, em **Oficina**, toque em **Mandar para a oficina**. Escreva o motivo e, se a máquina tem um problema avisado, escolha o problema na lista. Se alguém estiver com a máquina, ela precisa ser solta antes (ou liberada na ficha, como para desativar).

Enquanto a máquina está na oficina:

- ninguém consegue assumir a máquina nem começar carga ou viagem com ela; no tablet, ela aparece embaixo, em **Com outras pessoas ou na oficina**;
- a ficha e a **Manutenção** mostram o selo **Na oficina**;
- o **Monitor da TV** mostra a pá no quadro dela no pátio, em vermelho, "na oficina desde" a hora; a escavadeira e o caminhão aparecem na lavra, num selo vermelho **Na oficina** ao lado de "Máquinas em uso".

Quando a máquina voltar, toque em **Liberar da oficina** e informe o que foi feito (obrigatório), quem fez, o pedido de peças usado (se houver) e o custo em reais. Fica gravada a **ordem de serviço**, com a entrada, a saída e quanto tempo a máquina ficou parada. O problema escolhido ao mandar para a oficina é marcado como resolvido. As ordens de serviço aparecem na ficha, embaixo.

A **disponibilidade no mês**, na ficha e na Manutenção, é 100% menos o tempo que a máquina passou na oficina desde o dia 1 até agora. Uma máquina que não foi para a oficina no mês fica com 100%. Só o administrativo manda para a oficina e libera.

## Peças e pedido ao Compras

Cada máquina (pá, escavadeira e caminhão) tem a lista **Peças** na ficha (Cadastros → a máquina). Cadastre uma vez cada peça que costuma ser trocada: a função ("Filtro de óleo do motor"), a marca, o código e quantas vão na troca. As fotos são opcionais: até 5 por peça (por exemplo, a peça e a etiqueta com o código), escolhidas juntas ou aos poucos. Em **Editar**, marque **Apagar** embaixo de uma foto para tirá-la. A primeira foto aparece na lista; o link do pedido para o Compras mostra todas. **Editar** muda a peça e **Desativar peça** tira da lista (as desativadas ficam guardadas embaixo e podem voltar).

Para pedir peças:

1. Na ficha da máquina, toque em **Novo pedido de peças**.
2. Marque as peças que vão ser trocadas e, se precisar, mude a quantidade.
3. Em **Confirmado com**, escolha o operador com quem você conferiu (ou "Ninguém"). Escreva uma observação se quiser e toque em **Gravar pedido**.
4. Na página do pedido, toque em **Enviar para o Compras**. O WhatsApp abre com a mensagem pronta; escolha o contato ou o grupo do Compras e envie.

A mensagem leva a máquina, o horímetro, com quem foi confirmado, as peças e um link com as fotos. Esse link abre sem senha, só mostra aquele pedido e não expira: não encaminhe para quem não é da empresa.

Quando as peças forem colocadas, abra o pedido e toque em **Peças trocadas**: fica gravado o dia, o horímetro e quem marcou, e cada peça mostra a última troca na ficha. Se o pedido não for adiante, toque em **Cancelar pedido**. Os pedidos de cada máquina aparecem na ficha, e todos juntos em **Manutenção → Pedidos de peças**. Só o administrativo cadastra peças e faz pedidos.

## Previsão de vencimento da manutenção

Na **Manutenção** e na ficha da máquina, cada serviço que ainda não venceu mostra "vence por volta de quinta, 30/10": é o dia em que as horas que faltam acabam, se a máquina continuar trabalhando a média de horas por dia dos turnos dos últimos 14 dias (só contam os dias com turno). Sem turno nesse período, não aparece previsão; serviço vencido continua com o selo **Vencida**.

## Troca registrada pelo operador e avisos

Quem faz a troca (óleo, filtros e outros serviços) é o operador, e ele mesmo registra no tablet:

1. Com a máquina assumida, toque em **Manutenção**, no menu.
2. Escolha o serviço feito. Se ele ainda não está na lista daquela máquina, marque **Outro serviço**, escreva o nome (ex.: "Troca de óleo do motor") e de quantas em quantas horas é a troca (ex.: 250). O prazo fica salvo: nas próximas vezes é só escolher na lista.
3. Toque em **Registrar troca**. A troca fica gravada com o horímetro atual da máquina e o nome de quem registrou.

O operador só registra troca na máquina que está com ele. O administrativo continua podendo tudo na ficha da máquina: incluir serviço, desativar e registrar o serviço feito.

**Avisos.** Quando faltam **100 h** para um serviço, aparece o quadro **Manutenção chegando**: no tablet de quem está com a máquina (em todas as telas) e no **Painel** do administrativo, com a máquina. O aviso fica mais forte conforme as horas acabam: amarelo com 100 e 50 h, laranja com 25 h e vermelho com 8 h e depois de vencido. Ele some quando a troca é registrada. O aviso não aparece na TV. O pedido das peças continua com o escritório (veja **Peças e pedido ao Compras**).

## Docker

No computador, com as portas abertas:

```bash
docker compose -f docker-compose.yml -f docker-compose.local.yml up --build
```

A tela fica em `http://localhost:8081`. O MySQL do compose usa a porta `3308`. As variáveis vêm de um `.env` (modelo em `.env.example`).

O `docker-compose.yml` sozinho é o de servidor: não abre nenhuma porta (o MySQL não fica exposto na internet), guarda banco, fotos e sessões em volumes que sobrevivem a cada nova versão, e entende o proxy do painel (HTTPS e endereço de quem acessa, usados no cookie seguro e no bloqueio de senha errada). A imagem já traz o que a redução de fotos precisa (GD e EXIF) e aceita envio de até 12 MB.

## Easypanel

1. Crie um serviço **Compose** e, em **Fonte › Git**, preencha:
   - **URL do Repositório:** `https://github.com/fabbiol/operacoes.git` (repositório público: não precisa de chave SSH);
   - **Ramo:** `main`;
   - **Caminho de Build:** `/`;
   - **Arquivo Docker Compose:** `docker-compose.yml`.
2. Em **Ambiente**, defina as variáveis (troque as senhas):

   ```
   APP_USUARIOS=Fabio:SENHA-FORTE-DO-ADMIN:admin
   APP_ADMIN=Fabio
   APP_DEBUG=0
   APP_TZ=America/Cuiaba
   DB_NAME=operacoes_prospera
   DB_USER=operacoes
   DB_PASSWORD=SENHA-DO-BANCO
   DB_ROOT_PASSWORD=OUTRA-SENHA-DO-BANCO
   ```

   A senha do administrativo precisa ter pelo menos 8 caracteres. As outras pessoas são cadastradas depois, em Pessoas.
3. Clique em **Implantar**. O banco e as tabelas são criados sozinhos no primeiro acesso.
4. Em **Domínios**, adicione o domínio apontando para o serviço `app`, porta `80`. O painel cuida do HTTPS.
5. Para publicar a cada alteração na `main`, use o webhook de implantação do serviço no GitHub (**Settings › Webhooks**).

Banco, fotos e sessões ficam nos volumes `operacoes_db` e `operacoes_storage`. Não apague esses volumes: é onde estão os dados.

## Publicação

`.\deploy.ps1` envia os arquivos para a Hostinger numa ordem que mantém o site no ar: primeiro o schema, depois as classes novas, as telas, o código e os arquivos públicos, e por último `helpers.php` e `index.php`, um logo depois do outro. No fim, confere se o site responde, se o `fluxo.js` publicado é o deste computador e se o `.env` e a pasta `storage/sessoes` estão fechados para a web (HTTP 403). Se alguma conferência falhar, o script termina com erro.

Para publicar de um computador que não tem o projeto: baixe o ZIP do GitHub (botão **Code** › **Download ZIP**), extraia em qualquer pasta e rode, no PowerShell, dentro da pasta extraída:

```powershell
powershell -ExecutionPolicy Bypass -File .\deploy.ps1
```

Sem o token guardado, o script pede o token da API Hostinger na hora; o que é colado não aparece na tela. Funciona também com acento no nome do usuário do Windows (os arquivos temporários do envio ficam em `C:\Users\Public\operacoes-deploy`).

## Mudanças no banco

O banco é atualizado sozinho no primeiro acesso depois de uma publicação (a marca da versão fica no próprio banco, na tabela `schema_versao`: banco novo ou zerado é montado de novo, mesmo que a pasta `storage` tenha sobrado): o `database/schema.sql` cria as tabelas, e as funções `garantir*()` de `src/Db.php` acrescentam colunas em tabelas que já existem. Ao criar ou mudar uma função `garantir*()`, troque também o valor de `Db::MIGRACOES`; sem isso, a atualização não roda de novo nos servidores que já estão no ar.
