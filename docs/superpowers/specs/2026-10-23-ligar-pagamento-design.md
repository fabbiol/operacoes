# Ligar o pagamento à nota e CNPJ/CPF do cliente

Data: 23/10/2026. Aprovado pelo dono em conversa. Continua o design `2026-10-23-integracao-finpro-design.md`.

## Por quê

Quem paga por transferência muitas vezes não é a pessoa da nota, e muda a cada compra. O dono precisa ligar na mão um
comprovante (Pix/TED, PDF ou foto, recebido pelo WhatsApp) a uma ou mais notas da aba **Transferências de clientes** do
Fin Pro. E quando o próprio cliente paga, o CNPJ/CPF da nota ajuda a conferir (e, no próximo passo, a leitura do
Bradesco a marcar sozinha).

## Operações

- Ler o CNPJ/CPF do destinatário da nota: XML (`dest/CNPJ` ou `dest/CPF`) e PDF (DANFE, campo "CNPJ/CPF" do bloco
  DESTINATÁRIO/REMETENTE). Guardar só os dígitos (11 ou 14) numa coluna nova `fila_carregamentos.nf_documento`
  (`garantir*()` + `schema.sql`, trocar `Db::MIGRACOES`); campo editável no formulário da ordem (com máscara de exibição
  000.000.000-00 / 00.000.000/0000-00 na ficha); validação dos dígitos verificadores; vazio é aceito.
- Contrato com o Fin Pro: campo novo opcional `documento` (só dígitos, 11 ou 14) no `POST /api/integracao/operacoes/notas`.

## Fin Pro (aba Transferências de clientes)

- Guardar e mostrar o `documento` (CNPJ/CPF formatado) junto do cliente.
- **Número da nota clicável**: abre o PDF da nota (quando houver).
- **Ligar pagamento**: selecionar uma ou mais notas "Aguardando pagamento" do mesmo jeito que se marca linhas em outras
  telas do Fin Pro; enviar o comprovante (PDF ou foto); o Fin Pro lê o comprovante com a leitura de comprovantes que já
  existe (valor, data, pagador, documento do pagador, ID da transação) e mostra para conferir, com a soma das notas
  marcadas e a diferença; o dono confirma (pode corrigir valor e data). As notas passam a **Pago**, guardando o
  pagamento (valor, data, pagador, documento do pagador, ID da transação, comprovante, quem ligou e quando). Um
  pagamento pode cobrir várias notas. Não deixa ligar o mesmo comprovante (mesmo ID da transação) duas vezes.
- Abrir o comprovante de uma nota paga com um toque. **Desfazer** o pagamento: todas as notas dele voltam a "Aguardando
  pagamento" e o comprovante fica guardado no histórico.
- O reenvio da mesma nota pela Operações não muda a situação nem o pagamento.

## Fora do escopo

- Leitura do extrato do Bradesco, lista "Pagamentos sem nota" e marcação automática (próximo passo).
- Lista de pagadores conhecidos.

## Critérios de aceite

- Operações: leitura do CNPJ/CPF testada com os PDFs e XMLs sintéticos de `tests/notas/` e envio do campo `documento`
  testado com o Fin Pro falso; testes filtrados passando.
- Fin Pro: `pytest` do ligar (uma nota, várias notas, comprovante repetido, nota já paga, desfazer), do `documento` e
  da tela; testes existentes de acesso e permissões passando.
