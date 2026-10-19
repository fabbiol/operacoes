---
name: fuso-horario
description: Fuso do app e do MySQL nos testes
metadata:
  type: architecture
source: src/Db.php (SET time_zone), docker/php.ini, testes de outubro de 2026
evidence: horários inseridos com NOW() pelo cliente mysql apareceram 4 h adiantados na TV
last_verified: 2026-10-19
invalidate_when: mudar APP_TZ, o time_zone da conexão em Db::conectar ou o fuso do servidor
---

O app roda em America/Cuiaba (UTC-4) e a conexão PDO faz `SET time_zone = '-04:00'`. O cliente `mysql` usado nos testes fica em UTC, então `NOW()` digitado nele dá 4 horas a mais. Em SQL de teste, comece com `SET time_zone='-04:00'`, e rode testes PHP de linha de comando com `APP_TZ=America/Cuiaba`.
