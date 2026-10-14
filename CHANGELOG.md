# O que mudou

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
