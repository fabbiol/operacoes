# Pátio — fila de carregamento

O administrativo lança a ordem, confere cliente, pedido, nota, material e quantidade, e libera. No tablet, o operador de pá carregadeira assume a pá, inicia e finaliza para a conferência.

O ciclo de lavra conta as viagens do caminhão: fila, carga, ida até o britador e retorno vazio. Na lavra, o operador de escavadeira hidráulica assume a escavadeira e faz a carga. O motorista marca a fila, a ida e o retorno. Quem opera as duas máquinas pode estar no pátio ou na lavra, um lugar por vez. A tela fica em `/lavra`.

## Lançar o carregamento pela nota fiscal

Em **Fila › Carregamento › Nova ordem**, o quadro **Ler a nota fiscal** recebe o **XML** ou o **PDF (DANFE)** da nota. Toque em **Ler nota**: o sistema preenche o cliente, o pedido, o número da nota, o material, a quantidade, a unidade e a hora em que a nota foi emitida. Confira, marque **Liberar agora para o pátio** se quiser e toque em **Lançar ordem**.

- **XML**: leitura exata, campo por campo. A hora é a da emissão da nota.
- **PDF**: o sistema lê o texto do DANFE. O número e a série vêm da chave de acesso (conferida pelo dígito verificador); a hora vem do protocolo de autorização (segundos depois da emissão). PDF escaneado (foto) não tem texto: use o XML.
- **Pedido**: o do campo de pedido da nota (xPed) ou, se não tiver, o "Pedido: ..." das informações complementares.
- **Material**: o do cadastro com o mesmo nome do material da nota (sem diferença de acento ou maiúscula; "BRITA 1" acha "Brita 1"). Se não achar, a tela avisa e você escolhe. Se a nota tiver mais de um material, o carregamento usa o primeiro e a tela avisa.
- **Nota repetida**: a tela avisa se a nota já está em outro carregamento, e o sistema não deixa lançar a mesma nota (mesma chave de acesso) duas vezes.

A hora em que o caminhão foi carregado continua vindo do pátio (início e fim do carregamento). A ficha da ordem mostra a hora da nota e quanto tempo depois dela o carregamento começou.

Ler o PDF precisa do programa `pdftotext` (pacote `poppler-utils`), que já vem no Docker. Num servidor sem ele (por exemplo a hospedagem compartilhada da Hostinger), use o XML.

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

**Travar o tablet no app** (opcional, para o operador não sair do sistema). O Android tem a opção **Fixar app**; não precisa instalar nada:

1. Abra **Configurações › Segurança e privacidade › Mais configurações de segurança › Fixar app** e ative. Ative também **Pedir PIN antes de liberar** (ou "Bloquear dispositivo ao liberar"). Os nomes podem mudar um pouco de um tablet para outro: se não achar, digite **fixar** na busca das Configurações.
2. Abra o app da Próspera.
3. Abra a lista de apps recentes (o botão quadrado, ou deslize de baixo para cima e segure no meio da tela).
4. Toque no ícone da Próspera, no alto do cartão do app, e em **Fixar**.
5. Para soltar: toque e segure **Voltar** e o **quadrado** ao mesmo tempo (ou, com gestos, deslize de baixo para cima e segure). O tablet pede o PIN.

Com o app fixado, o tablet não sai do sistema, não mostra notificações por cima e o botão de início não funciona até alguém com o PIN soltar.

## App Android: como montar uma versão nova

O código do app fica em `android/` (uma tela só, que abre o sistema). O arquivo pronto fica em `public/app/prospera.apk` e é publicado junto com o sistema. Só precisa montar de novo se mudar o endereço do sistema ou o próprio app; mudanças nas telas do sistema não precisam.

1. Aumente `android:versionCode` e `android:versionName` em `android/AndroidManifest.xml`, `VERSAO` em `android/src/.../Principal.java` e `$versaoApp` em `views/app_android.php`.
2. Monte com a mesma chave de assinatura de sempre (o arquivo `prospera-app.jks`, guardado fora do Git):

```sh
apt-get install default-jdk android-sdk-platform-23 aapt dalvik-exchange zipalign apksigner
CHAVE=/caminho/prospera-app.jks CHAVE_SENHA='senha da chave' ENDERECO=https://operacoes.fabbiol.tech/ sh android/construir.sh
```

O Android só atualiza o app se a versão nova vier com a mesma chave. Sem a chave, é preciso desinstalar o app antigo em cada tablet antes de instalar o novo (e autorizar o tablet de novo). Nunca coloque a chave no Git: o repositório é público.

O que o app faz: abre só o endereço do sistema (outros endereços vão para o Chrome); mantém a tela acesa quando o sistema pede (com máquina assumida); mostra "Sem conexão" e tenta de novo sozinho; escolhe foto da galeria nos cadastros; o botão Voltar volta uma tela e, no começo, deixa o app em segundo plano; o Android não oferece para guardar senhas digitadas no app.

## Sessão

As sessões ficam em `storage/sessoes` e duram o turno (14 horas). Ao publicar esta versão pela primeira vez, todos precisam entrar de novo, e quem estiver com máquina assumida perde o horímetro de entrada daquele turno. Publique entre turnos.

## Máquinas

Quem está com cada máquina fica gravado no banco (tabela `maquina_posse`), com o horímetro de entrada. A máquina continua com a pessoa se a sessão cair ou se ela entrar por outro tablet, e ninguém mais consegue assumir a mesma máquina até ela soltar.

Se alguém foi embora sem soltar, o administrativo abre a ficha da máquina e usa **Liberar máquina**. Com a leitura do painel, o turno da pessoa é gravado; sem leitura, a máquina só fica livre. Enquanto a máquina não for solta ou liberada, ela não pode ser desativada, e a pessoa que está com ela não pode ser desativada nem trocar para uma função que não usa essa máquina.

O administrativo não consegue iniciar uma carga com uma máquina que está com outra pessoa, nem para um operador que está com outra máquina.

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

O banco é atualizado sozinho no primeiro acesso depois de uma publicação: o `database/schema.sql` cria as tabelas, e as funções `garantir*()` de `src/Db.php` acrescentam colunas em tabelas que já existem. Ao criar ou mudar uma função `garantir*()`, troque também o valor de `Db::MIGRACOES`; sem isso, a atualização não roda de novo nos servidores que já estão no ar.
