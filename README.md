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

O motorista abre a lavra. O operador abre a lavra e o pátio. Quem está nas duas funções abre as duas operações. O administrativo visualiza e faz tudo, inclusive os cadastros de caminhões, pás e retroescavadeiras, a fila de carregamento, os materiais e as pessoas.

## Docker

```bash
docker compose up --build
```

A tela fica em `http://localhost:8081`. O MySQL do compose usa a porta `3308`.
