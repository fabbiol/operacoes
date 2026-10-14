# O que mudou

## Outubro de 2026: segunda leva de melhorias

Os itens que tinham ficado para a próxima leva. Cada item tem um commit próprio no Git com os detalhes.

### 1. Horímetro com conferência e correção
Uma leitura maior do que a máquina conseguiria rodar desde a última (horas passadas no relógio + 1 h de folga) é recusada, com a última leitura e a hora dela na mensagem. Assim um 12345 digitado no lugar de 1234,5 não trava a máquina. Na ficha da máquina, o administrativo anula uma leitura errada com um motivo; a leitura fica no histórico, riscada, e deixa de valer.

### 2. Quem está com cada máquina fica gravado no banco
A máquina assumida e o horímetro de entrada do turno ficam no banco, não mais só na sessão do tablet. A pessoa continua com a máquina se a sessão cair ou se entrar por outro tablet, e duas pessoas não pegam a mesma máquina. A tela de escolher máquina mostra quais estão com outras pessoas. Na ficha da máquina, o administrativo vê com quem ela está e pode **Liberar máquina** de quem foi embora sem soltar (com a leitura do painel, o turno da pessoa é gravado). O administrativo não inicia carga com máquina que está com outra pessoa, nem para um operador que está com outra máquina. Máquina com alguém não pode ser desativada.

### 3. Toneladas gravadas na viagem
A viagem guarda a capacidade do caminhão na hora em que sai carregado. Corrigir a capacidade no cadastro não muda mais a produção dos dias anteriores. A atualização do banco também ficou protegida na publicação: o código novo sempre cria as próprias colunas, mesmo que um pedido chegue no meio do envio dos arquivos.

### 4. Fotos leves
A foto enviada é reduzida no servidor (máquinas até 960 px, pessoas até 640 px, em JPG) e sai de pé mesmo tirada com o aparelho deitado. Fotos antigas grandes são reduzidas na primeira vez que alguém as abre. O tablet guarda as fotos e só confere se mudaram. O limite de envio passou para 10 MB.

### 5. Trocar o nome de uma pessoa
Ao trocar o usuário em Pessoas, o histórico vai junto (viagens, cargas, horímetro, turnos, manutenções, a máquina que ela está usando), e quem estiver com o tablet aberto continua trabalhando, já com o nome novo. Quem vem de `APP_USUARIOS` tem o nome trocado no servidor.

### Ao publicar esta versão
1. Pode publicar com o turno rodando: ninguém precisa entrar de novo, e quem estiver com máquina continua com ela e com o horímetro de entrada.
2. O banco é atualizado sozinho no primeiro acesso depois da publicação (tabela `maquina_posse` e colunas novas em `horimetros` e `ciclos_lavra`).

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
