# O que mudou

## Outubro de 2026: revisão do pátio e do fracionado

- **Corrigir a quantidade carregada** na ficha de uma viagem finalizada (operador digitou errado ou esqueceram de marcar fracionado). O saldo da nota é refeito sozinho.
- **Encerrar saldo** também na viagem 1 e no Controle de carga. A viagem 1 não se cancela mais com saldo aberto (a nota sumia do vendido e o saldo ficava solto).
- **Cliente não veio**: a viagem liberada volta para Saldo a buscar sem ser cancelada.
- **Nota do PDF sem hora**: conta no vendido do dia da emissão (antes contava no dia do lançamento).
- **Tablet do pátio**: o cartão mostra a observação do administrativo; o aviso de carga alterada diz o que era antes; o **Ciente** fica sempre à vista; só quem iniciou a carga finaliza; **Enviadas hoje** e **Meus trabalhos** mostram o que saiu de verdade.
- **Correção perdida**: se a pá começa a carregar com o formulário de correção aberto, a tela avisa que nada foi alterado (antes dizia "Ordem atualizada").
- **Leitura da nota**: linhas do mesmo material em unidades diferentes não se somam; unidade desconhecida fica em branco para escolher.
- A viagem do fracionado liberada entra no fim da fila do pátio, não no começo.

### Ao publicar esta versão
1. Pode publicar com o turno rodando. O banco ganha sozinho a coluna `nf_emitida_data` em `fila_carregamentos` (preenchida nas notas que já têm a hora).
2. No Easypanel, toque em **Implantar** se não começar sozinho.

## Outubro de 2026: revisão das máquinas

- **Encerrar viagem vira aviso**: o motivo Oficina já registra o problema do caminhão; nos outros motivos, a opção **É problema no caminhão** registra o que foi escrito (ex.: pneu furou). Aparece na hora no Painel, na Manutenção e na TV.
- **Problema já avisado**: ao soltar, "Teve problema" com o texto em branco vale quando o problema já está aberto (não precisa escrever de novo nem marcar "Tudo certo").
- **Entrada errada**: o operador corrige a própria entrada na tela de soltar e não fica mais preso no tablet.
- **Horímetro com ponto de milhar**: "12.350" passa a ser lido como 12.350 h (antes virava 12,4 h).

## Outubro de 2026: revisão do tempo médio do Pusch e da limpeza

- **Limpeza intercalada com cargas**: a limpeza não termina mais ao carregar um caminhão. A escavadeira carrega e volta a limpar; os minutos de carga não contam como limpeza. Termina no botão, ao soltar a escavadeira ou, se esquecida, no fim do dia. O administrativo pode terminar pelo Painel.
- **Tempo médio do Pusch**: viagem encerrada sem voltar, viagem que foi para a garagem e etapa que atravessa a noite ficam fora das médias de estrada, volta e ciclo (continuam na produção).
- **Monitor**: o caminhão que chegou no britador aparece voltando vazio (cinza) depois de 3 minutos; caminhões no mesmo ponto ficam lado a lado.
- Relatórios de dias antigos mantêm o desconto da limpeza.

### Ao publicar esta versão
1. Pode publicar com o turno rodando. O banco ganha sozinho a coluna `garagem` em `ciclos_lavra`.
2. No Easypanel, toque em **Implantar** se não começar sozinho.

## Outubro de 2026: problemas e limpeza no Monitor da TV

- **Problemas nas máquinas no Monitor**: faixa vermelha com a máquina, o problema, quem avisou e quando (ex.: "ABC1D23 Pneu furou"). Some sozinha quando o problema é resolvido na ficha da máquina.
- **Limpeza no Monitor**: o quadro "Caminhões na lavra" fica amarelo com "Retro 1 em limpeza", além da faixa na área da lavra.
- Tempos abaixo de 1 minuto aparecem em segundos (antes aparecia "0 min").

### Ao publicar esta versão
1. Pode publicar com o turno rodando. Nada muda no banco.
2. No Easypanel, toque em **Implantar** se não começar sozinho. A TV pega a versão nova sozinha às 3h, ou na hora se recarregar o Monitor.

## Outubro de 2026: aviso no tablet da pá e limpeza da lavra

**Aviso no tablet do pátio**: quando o administrativo libera uma carga nova, altera ou cancela uma carga, o tablet do operador toca, vibra e mostra um quadro grande com o cliente e a carga, que só some no **Ciente**. O som repete até 3 vezes enquanto ninguém confirma.

- **App 1.1**: o som e a vibração passam a vir do Android, sem precisar de toque na tela. Atualize o app nos tablets do pátio: abra `/app`, **Baixar o app** e instale por cima (não precisa desinstalar nem autorizar de novo).

**Limpeza da lavra**: o operador da escavadeira toca em **Iniciar limpeza da lavra** quando para de carregar para limpar. O Painel, o Monitor e a tela dos caminhões mostram a limpeza. A espera dos caminhões na fila durante a limpeza sai do tempo médio de fila e de ciclo e aparece à parte como **Limpeza**. A limpeza termina no botão, ao iniciar uma carga ou ao soltar a escavadeira.

### Ao publicar esta versão
1. Pode publicar com o turno rodando. O banco ganha sozinho, no primeiro acesso, a tabela `lavra_limpezas`.
2. No Easypanel, toque em **Implantar** se não começar sozinho.
3. Atualize o app nos tablets do pátio para a versão 1.1 (em `/app`).

## Outubro de 2026: problemas das máquinas ao soltar

Ao soltar qualquer máquina (pá carregadeira, escavadeira ou caminhão), o operador responde **Como ficou a máquina?**: **Tudo certo** ou **Teve problema**, e aí escreve qual foi (por exemplo, "quebrou o ar-condicionado").

- O administrativo vê os problemas abertos no **Painel** (quadro **Problemas nas máquinas**), na **Manutenção** e na **ficha da máquina**, onde marca como resolvido ou registra um problema avisado por telefone.
- Quem vai pegar a máquina vê o problema aberto na hora de escolher. Quem vai soltar vê o que já foi avisado.
- Os turnos mostram se a pessoa entregou a máquina tudo certo ou avisou problema.
- **Liberar máquina** também aceita o problema.

### Ao publicar esta versão
1. Pode publicar com o turno rodando. O banco ganha sozinho, no primeiro acesso, a tabela `maquina_problemas` e a coluna `maquina_ok` em `horimetro_turnos`. Os turnos antigos ficam sem essa marca.
2. No Easypanel, toque em **Implantar** se não começar sozinho.
3. Quem estiver com uma máquina na hora da publicação vai responder a pergunta nova ao soltar.

## Outubro de 2026: valor da nota já concluída e Monitor menor

- **Nota sem valor**: no **Controle de carga**, a nota sem valor tem o campo para digitar o valor e **Salvar**, mesmo com o carregamento concluído. Na ficha da ordem também (**Salvar valor**, e **Corrigir valor** se estiver errado). O valor entra no vendido do dia na hora.
- **Monitor**: o quadro "Vendido hoje" avisa quantas notas estão sem valor. Letras menores na página e um pouco menores na tela cheia, para caber mais da fila.

### Ao publicar esta versão
1. Pode publicar com o turno rodando. Nada muda no banco.
2. No Easypanel, toque em **Implantar** se não começar sozinho.
3. Depois, em **Fila › Controle**, digite o valor das notas de hoje que estão sem valor.

## Outubro de 2026: carregamento fracionado, controle de carga e Monitor novo

**Carregamento fracionado**: para o cliente que leva a nota em várias viagens (3 viagens de 0,5 m³, por exemplo). Marque **Carregamento fracionado** ao lançar a ordem. A nota é lançada uma vez, com o total.

- O operador informa, ao finalizar, quanto foi carregado naquela viagem (nunca mais que o saldo da nota).
- O que falta vira a próxima viagem (CAR-012-2...), em **Saldo a buscar**. Quando o cliente volta, o administrativo toca em **Liberar viagem** e ela vai para a fila do pátio.
- **Encerrar saldo** cancela só o que faltava buscar.

**Controle de carga** (Fila › Controle, só administrativo): o vendido do dia em R$, o carregado do dia (t e m³), as notas com saldo a buscar e todas as notas do dia com a situação de cada uma. Dá para ver dias anteriores.

- **Valor da nota**: lido sozinho do XML e do PDF, ou digitado na ordem.
- **Vendido do dia**: soma das notas emitidas no dia, cada nota uma vez (fracionada também). Aparece também no Painel.

**Monitor da TV** refeito para ler de longe: poucos números e grandes, tempo de espera de cada cliente da fila (amarelo a partir de 30 min, vermelho a partir de 1 h), relógios que contam sozinhos, aviso quando a TV fica sem internet, e o vendido do dia em R$ só se marcar a opção naquele aparelho.

### Ao publicar esta versão
1. Pode publicar com o turno rodando. O banco ganha sozinho, no primeiro acesso, as colunas do fracionado e do valor da nota em `fila_carregamentos` (`nf_valor`, `fracionado`, `ordem_principal_id`, `viagem`, `quantidade_carregada`) e a situação **Saldo a buscar**. As ordens que já existem continuam iguais.
2. No Easypanel, se a publicação não começar sozinha, toque em **Implantar**.
3. Na TV, abra o Monitor de novo e toque em **Tela cheia**.
4. As notas lançadas antes desta versão não têm valor: o Controle avisa quantas estão "sem valor". Se quiser, digite o valor na ordem.

## Outubro de 2026: lançar o carregamento pela nota fiscal

Na **Nova ordem** tem o quadro **Ler a nota fiscal**: escolha o **XML** ou o **PDF** da nota e toque em **Ler nota**. O sistema preenche cliente, pedido, número da nota, material, quantidade, unidade e a **hora em que a nota foi emitida**. É só conferir e lançar.

- A tela diz o que leu e avisa o que precisa de atenção: pedido que não está na nota, material que não está no cadastro, nota com mais de um material, unidade diferente de t, m³ e un.
- A mesma nota não é lançada duas vezes: a tela avisa ao ler, e o sistema não deixa salvar.
- O campo **Nota emitida em** também aparece para lançar ou corrigir à mão.
- A ficha da ordem mostra a hora da nota e quanto tempo depois dela o carregamento começou. A hora do carregamento continua vindo do pátio.
- Lançar sem nota, digitando, continua igual.

### Ao publicar esta versão
1. Pode publicar com o turno rodando. O banco ganha duas colunas sozinho no primeiro acesso (`nf_emitida_em` e `nf_chave` em `fila_carregamentos`).
2. No Easypanel, a imagem nova instala o leitor de PDF (`poppler-utils`): a publicação demora um pouco mais desta vez.
3. Na Hostinger (hospedagem compartilhada) só o XML pode ser lido.

## Outubro de 2026: app Android do tablet

Os tablets Positivo não oferecem "Instalar app" no Chrome, então o sistema ganhou um **app Android** de verdade: ícone da Próspera na tela inicial, tela cheia, sem a barra do Chrome. As telas continuam vindo do sistema, então as melhorias chegam sozinhas, sem instalar o app de novo.

- **Baixar**: a página `/app` do sistema (por exemplo `https://operacoes.fabbiol.tech/app`) tem o botão **Baixar o app** e o passo a passo. Ela abre sem entrar no sistema.
- **Faixa nos tablets autorizados**: "Instale o app da Próspera neste tablet" agora leva para essa página (**Baixar o app**). Dentro do app a faixa não aparece.
- **Tela acesa**: dentro do app, quem mantém a tela acesa com máquina assumida é o próprio app.
- **Sem conexão**: o app mostra o aviso e tenta de novo a cada 10 segundos, também na primeira vez que abre.
- **Senhas**: o Android não oferece para guardar senhas digitadas no app.
- O código do app fica em `android/`, e o README explica como montar uma versão nova.

### Ao publicar esta versão
1. Pode publicar com o turno rodando. Nada muda no banco.
2. Em cada tablet: abra `/app` no Chrome, baixe e instale o app, abra pelo ícone e **autorize o tablet de novo dentro do app** (Pessoas › Tablets).
3. Guarde o arquivo da chave do app (`prospera-app.jks`) e a senha dela num lugar seguro, fora do Git. Sem ela não dá para atualizar o app depois sem desinstalar.

## Outubro de 2026: entrada pela foto pede a senha

No tablet autorizado, tocar na foto não entra mais direto. A pessoa toca na foto dela, aparece a foto com o nome e o campo **Senha de (nome)**, e ela só entra com a senha dela (a mesma cadastrada em Pessoas). Assim ninguém entra no nome de outra pessoa.

- **Voltar · não sou (nome)** volta para as fotos, se tocou na foto errada.
- Senha errada avisa e continua na mesma pessoa. Cinco senhas erradas seguidas bloqueiam aquela pessoa por 15 minutos (o mesmo limite do usuário e senha).
- Quem entrou pela foto continua saindo sozinho se o tablet for revogado.
- O administrativo continua entrando com usuário e senha.

### Ao publicar esta versão
1. Pode publicar com o turno rodando. Nada muda no banco. Quem já está dentro continua.
2. Combine a senha com cada pessoa da equipe (em Pessoas). Senhas de 4 números deixam a entrada rápida.
3. Em cada tablet, desligue no Chrome "Oferecer para salvar senhas" (o README mostra onde), para o tablet não guardar a senha de ninguém.

## Outubro de 2026: app do tablet

O sistema pode ser instalado no tablet como um app, com ícone da Próspera na tela inicial e tela cheia, sem a barra do Chrome. É o mesmo sistema: as mudanças chegam sozinhas, sem reinstalar. O passo a passo está no README, em "App do tablet".

- **Ícone e tela cheia**: nos tablets autorizados aparece a faixa "Instale o app da Próspera neste tablet", com o botão Instalar. "Agora não" esconde a faixa por 7 dias.
- **Tela acesa**: enquanto a pessoa está com uma máquina, a tela do tablet não apaga. Ao soltar a máquina, volta ao normal. O monitor da TV também fica aceso.
- **Tela "Sem conexão"**: se o Wi-Fi cai ou o sistema não responde, aparece um aviso da Próspera no lugar da página de erro do Chrome, e a tela volta sozinha quando o sinal volta. Nada fica guardado no tablet: as telas sempre vêm do sistema, com a situação de agora. Envio de formulário e atualização automática continuam indo direto ao sistema, como antes.
- **Travar o tablet no app**: guia da opção "Fixar app" do Android, no README.

### Ao publicar esta versão
1. Pode publicar com o turno rodando. Nada muda no banco.
2. O app só instala pelo endereço com https (o do Easypanel e o da Hostinger já são).
3. Depois de publicar, instale o app em cada tablet (uma vez) e, se quiser, fixe o app.

## Outubro de 2026: operador de pá e operador de escavadeira separados

Em Pessoas, a função deixou de ser "Motorista, Operador ou Ambos". Agora são três caixas para marcar uma ou mais: **Motorista**, **Operador de pá carregadeira** e **Operador de escavadeira hidráulica**.

- O operador de pá só vê e assume pá carregadeira, e abre o pátio.
- O operador de escavadeira só vê e assume escavadeira hidráulica, e abre a lavra para carregar os caminhões. Se não houver nenhuma pá cadastrada, ele também carrega no pátio com a escavadeira (como já era).
- No pátio, a lista de operadores do administrativo mostra só quem opera pá (ou só quem opera escavadeira, quando não há pá).
- "Retroescavadeira" passou a se chamar **Escavadeira hidráulica** em todas as telas, no cadastro de máquinas, no horímetro e no monitor da TV.

### Ao publicar esta versão
1. Quem hoje é "Operador" fica com as duas funções (pá e escavadeira), para ninguém perder acesso. Depois, em Pessoas, desmarque a função que a pessoa não faz.
2. O banco é atualizado sozinho no primeiro acesso (coluna nova `escavadeira` em `usuarios`). Pode publicar com o turno rodando.
3. Antes de tirar uma função de alguém, a pessoa precisa soltar a máquina daquela função (ou o administrativo liberar na ficha da máquina).

## Outubro de 2026: segunda leva de melhorias

Os itens que tinham ficado para a próxima leva. Cada item tem um commit próprio no Git com os detalhes.

### 1. Horímetro com conferência e correção
Uma leitura maior do que a máquina conseguiria rodar desde a última (horas passadas no relógio + 1 h de folga) é recusada, com a última leitura e a hora dela na mensagem. Assim um 12345 digitado no lugar de 1234,5 não trava a máquina. Na ficha da máquina, o administrativo anula uma leitura errada com um motivo; a leitura fica no histórico, riscada, e deixa de valer. Se a leitura anulada é a entrada de quem está com a máquina, dá para informar a leitura certa, e o turno da pessoa continua sendo somado. Um serviço de manutenção registrado sobre uma leitura anulada não trava o plano: o vencimento é recalculado e o serviço pode ser registrado de novo com o horímetro certo.

### 2. Quem está com cada máquina fica gravado no banco
A máquina assumida e o horímetro de entrada do turno ficam no banco, não mais só na sessão do tablet. A pessoa continua com a máquina se a sessão cair ou se entrar por outro tablet, e duas pessoas não pegam a mesma máquina. A tela de escolher máquina mostra quais estão com outras pessoas. Na ficha da máquina, o administrativo vê com quem ela está e pode **Liberar máquina** de quem foi embora sem soltar (com a leitura do painel, o turno da pessoa é gravado). O administrativo não inicia carga com máquina que está com outra pessoa, nem para um operador que está com outra máquina. Enquanto a máquina não for solta ou liberada, nem ela nem a pessoa que está com ela podem ser desativadas, e a pessoa não troca para uma função que não usa essa máquina.

### 3. Toneladas gravadas na viagem
A viagem guarda a capacidade do caminhão na hora em que sai carregado. Corrigir a capacidade no cadastro não muda mais a produção dos dias anteriores. Viagens de caminhão que estava sem capacidade passam a contar com a primeira capacidade preenchida e ficam com ela. A atualização do banco também ficou protegida na publicação: o código novo sempre cria as próprias colunas, mesmo que um pedido chegue no meio do envio dos arquivos.

### 4. Fotos leves
A foto enviada é reduzida no servidor (máquinas até 960 px, pessoas até 640 px, em JPG) e sai de pé mesmo tirada com o aparelho deitado. Fotos antigas grandes são reduzidas na primeira vez que alguém as abre. O tablet guarda as fotos por uma hora sem pedir de novo, e depois só confere se mudaram. O limite de envio passou para 10 MB; foto acima de 50 megapixels é recusada.

### 5. Trocar o nome de uma pessoa
Ao trocar o usuário em Pessoas, o histórico vai junto (viagens, cargas, horímetro, turnos, manutenções, a máquina que ela está usando), e quem estiver com o tablet aberto continua trabalhando, já com o nome novo. Quem vem de `APP_USUARIOS` tem o nome trocado no servidor.

### Ao publicar esta versão
1. Pode publicar com o turno rodando: ninguém precisa entrar de novo, e quem estiver com máquina continua com ela e com o horímetro de entrada. A publicação foi simulada arquivo por arquivo sobre uma cópia da versão anterior, com tablets e TV usando o sistema, sem erro.
2. O banco é atualizado sozinho no primeiro acesso depois da publicação (tabela `maquina_posse` e colunas novas em `horimetros` e `ciclos_lavra`).
3. Nas primeiras horas depois de publicar, evite trocar o nome de quem está com o tablet aberto: a sessão aberta antes desta versão ainda guarda só o nome e cai (como já acontecia antes). Depois do primeiro acesso de cada tablet, a troca de nome não derruba mais ninguém.
4. Para quem mexer no código: ao criar ou mudar uma função `garantir*()` em `src/Db.php`, troque o valor de `Db::MIGRACOES` (o README explica).

## Outubro de 2026: primeira leva de melhorias

Melhorias pensadas para o tablet de campo (Positivo Vision Tab 7: Android 14 Go, tela de 7", só Wi-Fi 2,4 GHz, sem 4G). Cada item tem um commit próprio no Git com os detalhes e a lista de arquivos.

### 1. Sessão dura o turno
As sessões ficam em `storage/sessoes` e valem 14 horas. Antes o servidor apagava a sessão depois de 24 minutos parada, e o tablet deslogava sozinho no meio do turno. Formulário enviado com a sessão vencida volta para a tela com aviso, em vez da página em branco 419.

### 2. Entrada pela foto só nos tablets autorizados
A tela de entrada só mostra as fotos e só aceita entrar com um toque nos tablets autorizados em Pessoas › Tablets. Em qualquer outro aparelho, só usuário e senha. Revogar um tablet derruba quem entrou nele. Senhas erradas ficam registradas no banco: 5 erros do mesmo usuário bloqueiam por 15 minutos. A senha do administrativo precisa ter pelo menos 8 caracteres.

### 3. Toque duplo não registra duas vezes
O botão vira "Enviando…" depois do toque. Um toque repetido logo depois de uma ação é ignorado, com o aviso "Toque repetido ignorado". Se a tela estava desatualizada, nada é gravado e aparece a etapa atual do caminhão. Duas ordens não são mais lançadas com clique duplo.

### 4. Aviso de sem conexão
Uma faixa vermelha no topo avisa "SEM CONEXÃO", "O servidor não responde", "A sessão terminou" ou "A tela mudou". Sem sinal, a ação não sai do tablet. Os cronômetros contam pelo relógio do servidor.

### 5. Regras do pátio e da lavra
No pátio, o operador carrega sempre no próprio nome; o administrativo escolhe numa lista. A mesma pá não inicia duas ordens, e o mesmo operador não carrega duas ao mesmo tempo. Máquina em trabalho não pode ser desativada no cadastro.

### 6. Telas cabem no tablet de 7"
A ação do momento aparece sem rolar, com o tablet em pé ou deitado. "Encerrar viagem" foi para o fim do cartão, em contorno, e "Garagem" ficou afastada de "Chegou na lavra".

### 7. Publicação mais segura
O `deploy.ps1` envia os arquivos numa ordem que mantém o site no ar e confere que a pasta de sessões está fechada para a web.

### Ao publicar esta versão
1. Publique entre turnos: todos precisam entrar de novo, e quem estiver com máquina assumida perde o horímetro de entrada daquele turno.
2. Autorize cada tablet uma vez: entre nele com o usuário e a senha do administrativo, abra Pessoas › Tablets, toque em Autorizar este aparelho e depois em Sair.
3. Confira no fim do `deploy.ps1` a linha "Pasta de sessões no domínio principal: HTTP 403".
4. Se a senha do administrativo tiver menos de 8 caracteres, troque em Pessoas.

### Ficou para a próxima leva
- Gravar no banco quem está com cada máquina e o horímetro de entrada do turno (hoje ficam só na sessão).
- Recusar leitura de horímetro absurda e deixar o administrativo anular uma leitura errada.
- Gravar as toneladas na viagem, para a produção de dias anteriores não mudar quando a capacidade do caminhão é editada.
- Reduzir as fotos no envio, para não pesar no Wi-Fi do tablet.
- Ao renomear uma pessoa, levar o nome novo para o histórico.
