---
name: deploy-hostinger
description: Ordem de envio do deploy.ps1 e o cuidado com funções novas nas telas
metadata:
  type: architecture
source: deploy.ps1 (comentário da ordem de envio), views/patio.php
evidence: revisão de 17/10/2026 achou tela nova chamando função que só existiria no index.php novo
last_verified: 2026-10-19
invalidate_when: deploy.ps1 mudar a ordem de envio ou a Hostinger deixar de ser usada
---

O caminho principal é o Easypanel (imagem Docker inteira, sem janela). Na Hostinger, `deploy.ps1` envia arquivo por arquivo, com o site no ar: schema, classes novas, telas, resto do código e por último `helpers.php` e `index.php`. Durante alguns segundos, a tela nova roda com o `index.php` antigo. Tela que chama função nova do `index.php` ou do `helpers.php` deve conferir com `function_exists` antes. Classe nova entra na lista `$classesNovas` do `deploy.ps1`.
