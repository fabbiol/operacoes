# Pátio — fila de carregamento

O administrativo lança a ordem, confere cliente, pedido, nota, material e quantidade, e libera. No tablet, o operador escolhe a pá carregadeira, inicia e finaliza para a conferência.

O ciclo de lavra conta as viagens do caminhão: fila, carga, ida até o britador e retorno vazio. Na lavra, o operador escolhe a retroescavadeira e faz a carga. O motorista marca a fila, a ida e o retorno. O mesmo operador pode estar no pátio ou na lavra, um lugar por vez. A tela fica em `/lavra`.

## Local no Windows

O MariaDB da máquina precisa estar no ar (o mesmo da porta `3307`). Crie o banco uma vez:

```powershell
& "C:\Program Files\MariaDB 11.5\bin\mysql.exe" -h 127.0.0.1 -P 3307 -u root -p -e "CREATE DATABASE IF NOT EXISTS operacoes_prospera CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS 'operacoes'@'127.0.0.1' IDENTIFIED BY 'operacoes_local'; GRANT ALL PRIVILEGES ON operacoes_prospera.* TO 'operacoes'@'127.0.0.1'; FLUSH PRIVILEGES;"
```

As tabelas são criadas na primeira abertura. Suba a tela com:

```powershell
php -S 127.0.0.1:8081 -t public public/router.php
```

Abra `http://127.0.0.1:8081`. O primeiro acesso fica em `APP_USUARIOS`, no formato `nome:senha:funcao` (`admin`, `motorista` ou `operador`). Quem está em `APP_ADMIN` entra como administrativo. As outras pessoas são cadastradas em Pessoas, com usuário, nome completo, foto e função. O usuário é o primeiro nome.

O usuário pode ser trocado em Pessoas: o histórico (viagens, cargas, horímetro, turnos, manutenções) passa para o nome novo, e quem está com a sessão aberta continua trabalhando. Quem vem de `APP_USUARIOS` tem o nome trocado no servidor, não na tela.

O motorista abre a lavra. O operador abre a lavra e o pátio. Quem está nas duas funções abre as duas operações. O administrativo visualiza e faz tudo, inclusive os cadastros de caminhões, pás e retroescavadeiras, a fila de carregamento, os materiais e as pessoas.

## Tablets autorizados

A entrada pela foto, sem senha, só aparece nos tablets que o administrativo autorizou. Em qualquer outro aparelho, a tela de entrada mostra só usuário e senha.

Para autorizar um tablet, entre nele com o usuário e a senha do administrativo, abra Pessoas › Tablets, dê um nome ao aparelho e toque em Autorizar este aparelho. Depois toque em Sair: a entrada passa a mostrar as fotos da equipe. Na mesma tela dá para revogar um tablet perdido ou trocado.

A autorização fica num cookie do navegador. Se alguém limpar os dados do Chrome no tablet, autorize de novo.

Cinco senhas erradas seguidas para o mesmo usuário bloqueiam a entrada por 15 minutos naquele endereço. A senha do administrativo precisa ter pelo menos 8 caracteres.

## Sessão

As sessões ficam em `storage/sessoes` e duram o turno (14 horas). Ao publicar esta versão pela primeira vez, todos precisam entrar de novo, e quem estiver com máquina assumida perde o horímetro de entrada daquele turno. Publique entre turnos.

## Máquinas

Quem está com cada máquina fica gravado no banco (tabela `maquina_posse`), com o horímetro de entrada. A máquina continua com a pessoa se a sessão cair ou se ela entrar por outro tablet, e ninguém mais consegue assumir a mesma máquina até ela soltar.

Se alguém foi embora sem soltar, o administrativo abre a ficha da máquina e usa **Liberar máquina**. Com a leitura do painel, o turno da pessoa é gravado; sem leitura, a máquina só fica livre. Enquanto a máquina não for solta ou liberada, ela não pode ser desativada, e a pessoa que está com ela não pode ser desativada nem trocar para uma função que não usa essa máquina.

O administrativo não consegue iniciar uma carga com uma máquina que está com outra pessoa, nem para um operador que está com outra máquina.

## Docker

```bash
docker compose up --build
```

A tela fica em `http://localhost:8081`. O MySQL do compose usa a porta `3308`.

## Publicação

`.\deploy.ps1` envia os arquivos para a Hostinger numa ordem que mantém o site no ar: primeiro o schema, depois as classes novas, as telas, o código e os arquivos públicos, e por último `helpers.php` e `index.php`, um logo depois do outro. No fim, confere se o site responde, se o `fluxo.js` publicado é o deste computador e se o `.env` e a pasta `storage/sessoes` estão fechados para a web (HTTP 403). Se alguma conferência falhar, o script termina com erro.

Para publicar de um computador que não tem o projeto: baixe o ZIP do GitHub (botão **Code** › **Download ZIP**), extraia em qualquer pasta e rode, no PowerShell, dentro da pasta extraída:

```powershell
powershell -ExecutionPolicy Bypass -File .\deploy.ps1
```

Sem o token guardado, o script pede o token da API Hostinger na hora; o que é colado não aparece na tela. Funciona também com acento no nome do usuário do Windows (os arquivos temporários do envio ficam em `C:\Users\Public\operacoes-deploy`).

## Mudanças no banco

O banco é atualizado sozinho no primeiro acesso depois de uma publicação: o `database/schema.sql` cria as tabelas, e as funções `garantir*()` de `src/Db.php` acrescentam colunas em tabelas que já existem. Ao criar ou mudar uma função `garantir*()`, troque também o valor de `Db::MIGRACOES`; sem isso, a atualização não roda de novo nos servidores que já estão no ar.
