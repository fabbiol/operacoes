# Preparação local

## Pré-requisitos

- **Sessão do Claude Code na nuvem** (onde o desenvolvimento acontece hoje): o ambiente já traz PHP 8.3, Node 22, Playwright com Chromium e Docker. O MariaDB e o `pdftotext` são instalados pelo `sh tests/preparar.sh` quando faltam.
- **Computador do dono (Windows)**: Docker Desktop para rodar o sistema completo, ou PHP 8.3 com o MariaDB da máquina na porta 3307 (passo a passo em [README.md](../README.md#local-no-windows)).
- Para montar o app Android: JDK, `aapt`, `dalvik-exchange`, `zipalign`, `apksigner`, `android-sdk-platform-23` e a chave de assinatura, que fica com o dono. Veja o README, seção "App Android: como montar uma versão nova".

## Primeira execução

1. Copie `.env.example` para `.env` e troque as senhas. O `.env` nunca vai para o Git. As variáveis principais são:
   - `APP_USUARIOS`: primeiros acessos, no formato `nome:senha:funcao`;
   - `APP_ADMIN`, `APP_TZ`;
   - `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`.
2. Com Docker: `docker compose -f docker-compose.yml -f docker-compose.local.yml up --build` e abra `http://127.0.0.1:8081`.
3. Sem Docker: `php -S 127.0.0.1:8081 -t public public/router.php`, com o banco já criado.

As tabelas são criadas sozinhas no primeiro acesso.

## Banco de dados

- Produção: MySQL 8.4 no Easypanel. Nunca use o banco de produção para testar nem rode teste contra o endereço de produção.
- Local: o serviço `db` do `docker-compose.yml` (porta 3308 com o arquivo local) ou o MariaDB da máquina.
- Testes: `tests/` cria um banco temporário `op_t_*` para cada teste, num MariaDB local em `127.0.0.1:3307`, e apaga o banco no fim.
- Mudanças de esquema: edite `database/schema.sql` para tabelas novas e uma função `garantir*()` em `src/Db.php` para colunas em tabelas existentes; troque `Db::MIGRACOES`. Veja [architecture.md](architecture.md#dados-e-migrações).

## Verificar

- `sh tests/rodar.sh rapido`: sintaxe de todos os arquivos PHP, JS e shell, mais os testes que não precisam de banco. Leva segundos.
- `sh tests/rodar.sh`: tudo, incluindo os testes com banco temporário e os de tela no navegador. Precisa do `sh tests/preparar.sh` antes, numa sessão nova.
- O resumo no fim mostra OK, FALHA ou PULADO para cada parte, com o motivo do PULADO. Detalhes em [quality.md](quality.md) e em `tests/LEIAME.md`.

## Agentes

Leia o [AGENTS.md](../AGENTS.md). O [CLAUDE.md](../CLAUDE.md) importa as mesmas instruções e a memória do projeto. Consulte o [setup.md](setup.md) para as skills, as integrações e o que está pendente.

## Problemas conhecidos

- **MariaDB parou no meio da sessão na nuvem.** O contêiner às vezes reinicia e o banco cai (visto várias vezes em outubro de 2026). Rode `sh tests/preparar.sh` de novo.
- **Horário 4 horas adiantado em teste.** O cliente `mysql` usa UTC e o app usa UTC-4. Use `SET time_zone='-04:00'` no SQL de teste.
- **PDF da nota não é lido na Hostinger.** A hospedagem compartilhada não tem `pdftotext`; lá, use o XML da nota.
