---
name: testes-de-tela
description: Duas proteções do app que os testes de tela precisam respeitar
metadata:
  type: architecture
source: src/helpers.php (TOQUE_REPETIDO_MS), src/Horimetro.php (leitura impossível)
evidence: envios rápidos voltavam sem gravar; leituras próximas eram recusadas
last_verified: 2026-10-19
invalidate_when: mudar TOQUE_REPETIDO_MS ou a regra de leitura impossível do horímetro
---

- Toque repetido: um envio logo depois de outra ação (menos de 1,2 s) é tratado como toque duplo e não grava. Nos testes de tela, espere 1,3 s antes de cada envio de formulário.
- Horímetro: a leitura não pode subir mais horas do que o tempo passado desde a anterior. Para simular um turno, os testes "envelhecem" as leituras e a posse (`lido_em` e `iniciado_em` menos 12 horas) antes de soltar a máquina.
