---
name: pdo-tipos
description: Tipos devolvidos pelo PDO com prepares nativos
metadata:
  type: architecture
source: src/Db.php (PDO::ATTR_EMULATE_PREPARES => false)
evidence: em 17/10/2026 um teste comparou COUNT(*) com '1' e falhou porque veio o inteiro 1
last_verified: 2026-10-19
invalidate_when: mudar a opção ATTR_EMULATE_PREPARES ou a versão do PHP
---

Com `ATTR_EMULATE_PREPARES` desligado (PHP 8.1+), colunas inteiras (`COUNT(*)`, `id`, `TINYINT`) voltam como `int`; `DECIMAL` volta como `string`. O código usa `strict_types`: converta antes de comparar (`(int)`, `(string)`) em vez de comparar com `===` contra texto.
