# Integração Operações → Fin Pro: notas pagas por transferência

Data: 23/10/2026. Aprovado pelo dono em conversa.

## Por quê

O dono lança a nota fiscal (PDF) na Operações. No Fin Pro (outro sistema dele, `fabbiol/fin-pro`, Python/FastAPI, no VPS
pelo Easypanel) vai entrar a leitura do extrato do Bradesco para conferir os clientes que pagaram por transferência. Para
isso, as notas pagas por Pix ou TED/DOC precisam chegar sozinhas ao Fin Pro, numa aba própria, sem digitar de novo.

## Operações

- Forma de pagamento nova: `transferencia`, rótulo **"Transferência (TED/DOC)"** (o DOC foi extinto em 2024; uma opção só).
  No XML, `tPag` 18 → `transferencia`.
- O PDF lido em "Ler a nota fiscal" fica guardado (`storage/notas/`) e ligado à ordem quando ela é lançada. Ordem lançada
  à mão fica sem PDF.
- Vai para o Fin Pro a ordem **principal** (não as viagens seguintes do fracionado) com forma `pix` ou `transferencia`,
  não cancelada: ao lançar, ao corrigir a forma para uma dessas (formulário ou quadro do valor na ficha) e no botão
  **Reenviar** da ficha.
- Fila de envio: cada ordem tem um registro de envio (pendente, enviado, erro, tentativas, último erro, quando). O envio é
  tentado na hora; se falhar, fica pendente e é tentado de novo de tempos em tempos (no máximo a cada 5 minutos, poucos
  por vez), aproveitando as consultas que o Monitor da TV e o Painel fazem a cada poucos segundos. A ficha mostra
  "Enviada ao Fin Pro em ..." ou "Esperando envio ao Fin Pro (motivo)".
- Configuração no servidor (`.env` / Easypanel): `FINPRO_URL` (ex.: `https://finpro.exemplo.com.br`) e `FINPRO_TOKEN`.
  Sem as duas, nada é enviado e a ficha diz "Fin Pro não configurado".
- A nota que deixa de ser Pix/Transferência depois de enviada continua no Fin Pro (fora do escopo).

## Fin Pro

- Aba nova **"Transferências de clientes"**, separada de Empresas, no menu lateral: tabela com **Nota | Pedido | Empresa |
  Valor | Material**, data de emissão, forma (Pix ou TED/DOC), situação **Aguardando pagamento** e o botão para abrir o PDF.
  Filtros simples (empresa, situação) e busca pela nota.
- Dados próprios (não usa `empresas.db`).
- Entrada da integração, autenticada só por token (sem login de usuário), descrita no contrato abaixo.
- A leitura do Bradesco e a baixa automática ficam para o próximo passo: a tabela já guarda a situação para isso.

## Contrato

`POST {FINPRO_URL}/api/integracao/operacoes/notas`

- Cabeçalho `Authorization: Bearer <FINPRO_TOKEN>`. No Fin Pro o token vem da variável de ambiente `OPERACOES_TOKEN`
  (comparação em tempo constante). Sem a variável, a rota responde 503; token errado, 401.
- Corpo `multipart/form-data`:
  - `origem_id`: id da ordem na Operações (inteiro, obrigatório);
  - `nota`: número da NF (obrigatório);
  - `pedido`: número do pedido;
  - `empresa`: cliente (obrigatório);
  - `valor`: valor da nota com ponto decimal, ex. `1234.56` (pode vir vazio quando a nota está sem valor);
  - `material`: nome do material;
  - `emitida_em`: `AAAA-MM-DD` ou `AAAA-MM-DD HH:MM:SS`;
  - `forma`: `pix` ou `transferencia`;
  - `chave`: chave de acesso de 44 dígitos (pode vir vazia);
  - `arquivo`: o PDF da nota (opcional; só `application/pdf`, até 10 MB).
- Respostas JSON: `201 {"ok": true, "id": N, "situacao": "criada"}`; reenvio da mesma `origem_id`:
  `200 {"ok": true, "id": N, "situacao": "atualizada"}` (atualiza os dados e o PDF, sem duplicar); `401`, `422` (com
  `{"ok": false, "erro": "..."}`), `503`.
- A Operações considera enviado só com 200 ou 201; qualquer outra resposta ou falha de rede deixa pendente com o motivo.

## Fora do escopo

- Leitura do extrato do Bradesco e baixa automática.
- Enviar outras formas de pagamento.
- Apagar do Fin Pro a nota cancelada na Operações.

## Critérios de aceite

- Operações: teste de tela do envio com um servidor falso que responde como o Fin Pro (sucesso, falha e reenvio); a fila
  tenta de novo depois de uma falha; `sh tests/rodar.sh` passa.
- Fin Pro: testes `pytest` da rota (token certo/errado/ausente, campos obrigatórios, PDF, reenvio sem duplicar) e da
  listagem; a aba aparece no menu.
