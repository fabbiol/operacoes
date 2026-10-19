---
name: vocabulario
description: Termos da pedreira usados pelo dono e como aparecem no código
metadata:
  type: business-rule
source: conversa com o dono (outubro de 2026) e src/Lavra.php, src/Operacoes.php
evidence: o dono explicou "Pusch" em 17/10/2026; nomes conferidos no código
last_verified: 2026-10-19
invalidate_when: o dono mudar os nomes ou o código renomear tabelas e classes
---

- **Pusch**: o ciclo do caminhão levando material da **Lavra** até o **Britador** e voltando (fila → carga → ida → britador → volta). É o `ciclos_lavra` em `src/Lavra.php`. "Tempo médio do Pusch" são as médias desse ciclo.
- **Escavadeira hidráulica**: no código e no banco é **retro** (`retroescavadeiras`, `retroescavadeira_id`). Carrega os caminhões na lavra e faz a **limpeza da lavra**.
- **Pá carregadeira** (`pas_carregadeiras`): carrega os clientes no **pátio** (`fila_carregamentos`).
- **Fracionado**: o cliente busca a nota em várias viagens; a viagem 1 guarda a nota e o valor; as seguintes ficam em **Saldo a buscar**.
- **Horímetro**: leitura de horas de motor ao assumir e ao soltar a máquina; **posse** é quem está com a máquina agora.
- **Implantar**: o botão do Easypanel que publica o `main` em produção.
