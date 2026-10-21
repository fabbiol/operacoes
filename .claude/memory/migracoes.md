---
name: migracoes
description: Como o banco se atualiza sozinho e os cuidados ao mudar
metadata:
  type: architecture
source: src/Db.php (migrar, garantir*, MIGRACOES, schema_versao), README.md "Mudanças no banco"
evidence: atualização de um banco igual ao do main conferida em 17/10/2026; backfill alterou atualizado_em na primeira versão
last_verified: 2026-10-19
invalidate_when: trocar o mecanismo de Db::migrar ou adotar uma ferramenta de migração
---

No primeiro acesso depois de publicar, `Db::migrar` roda `database/schema.sql` e as funções `garantir*()`, que acrescentam colunas em tabelas existentes. A marca fica na tabela `schema_versao` (hash do schema + `Db::MIGRACOES`). Ao criar ou mudar uma `garantir*()`, troque `Db::MIGRACOES`.

Cuidados: as `garantir*()` precisam rodar várias vezes sem erro; colunas novas aceitam NULL ou têm valor padrão, porque o código novo lê linhas antigas; várias tabelas têm `atualizado_em ... ON UPDATE CURRENT_TIMESTAMP`, então um UPDATE de preenchimento deve incluir `atualizado_em = atualizado_em` para não parecer mudança recente.
