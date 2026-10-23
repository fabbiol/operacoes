# Manutenção: peças e pedido ao Compras, oficina e ordem de serviço, previsão de vencimento

Data: 23/10/2026. Aprovado pelo dono em conversa.

## Por quê

Na troca de filtros, alguém tira foto de cada peça e manda pelo WhatsApp ao Compras, toda vez. O dono quer as peças de
cada máquina gravadas uma vez (função, marca, código, foto), marcar as que vão ser trocadas, confirmar com o operador e
mandar o pedido pronto pelo WhatsApp, com histórico. Também quer saber quanto tempo cada máquina fica parada na oficina,
o que foi feito e quanto custou, e em que dia cada serviço preventivo vai vencer.

## Parte A — Peças e pedido ao Compras

- Cada máquina (pá, escavadeira, caminhão) tem a lista **Peças**, na ficha: função (ex.: "Filtro de óleo do motor"),
  marca, código/modelo, quantidade usada na troca (padrão 1) e foto opcional. Editar e desativar; a lista guarda as
  desativadas fora da vista.
- **Novo pedido de peças**, na ficha: marcar as peças (com a quantidade), escolher o operador com quem confirmou (lista
  de pessoas ativas que operam aquele tipo de máquina, ou "Ninguém"), observação opcional. O pedido guarda o horímetro
  atual, quem pediu e uma cópia de cada item (função, marca, código, quantidade), para o histórico não mudar se a peça
  for editada depois.
- **Enviar para o Compras**: botão que abre o WhatsApp (`https://wa.me/?text=...`) com a mensagem pronta; o dono escolhe
  o contato ou grupo. A mensagem:
  `*Pedido de peças — <máquina>*` / `Horímetro: <h>` / `Confirmado com: <operador>` / linha em branco / uma linha por
  item `1. <função> — <marca> <código> (<qtd> un)` / `Obs.: ...` / `Fotos: <link>`.
- **Link das fotos**: página pública `/compras/<código secreto de 32 caracteres>`, sem senha, só leitura, com a máquina,
  os itens e as fotos. Código inválido: "não encontrado". O link não expira.
- Situações do pedido: **Enviado** → **Trocado** (data, horímetro, quem marcou) ou **Cancelado**.
- **Histórico**: na ficha, os pedidos da máquina; em cada peça, a última troca (data e horímetro).
- Na tela Manutenção, o link "Pedidos de peças" com os pedidos recentes de todas as máquinas.
- Só o administrativo cadastra peças e faz pedidos.

## Parte B — Máquina na oficina e ordem de serviço

- **Mandar para a oficina** (ficha, administrativo): motivo, opcionalmente ligado a um problema aberto. Quem estiver com a
  máquina precisa ser liberado antes (como já é para desativar).
- Na oficina: ninguém assume a máquina (some da escolha de máquina com o aviso "na oficina"); o Monitor mostra
  "na oficina" (pá na coluna do pátio, escavadeira e caminhão na lavra); a Manutenção mostra o selo "Na oficina".
- **Liberar da oficina**: o que foi feito, quem fez (texto), peças (opcional: um pedido de peças da máquina), custo em R$
  (opcional). Fica gravada a **ordem de serviço** com entrada, saída e duração. O problema ligado é marcado resolvido.
- **Disponibilidade do mês** na ficha e na Manutenção: 100% menos o tempo na oficina no mês, sobre o tempo do mês até agora.
- Histórico das ordens de serviço na ficha.

## Parte C — Previsão de vencimento

- Média de horas de uso por dia da máquina: horas de horímetro dos turnos dos últimos 14 dias divididas pelos dias com
  turno nesse período (sem turno: sem previsão).
- Em cada serviço preventivo com prazo: "vence por volta de <dia da semana>, <dd/mm>" (ou "vencido"). Na Manutenção e
  na ficha.

## Fora do escopo

- Guardar o número do Compras ou mandar mensagem sem o dono tocar em Enviar.
- Estoque de peças, preços de peças, fornecedores.
- Diesel, checklist, vencimentos por data (ideias 3, 4, 5, 7 e 8).

## Decisões do dono

- Opções escolhidas: 1 (oficina), 2 (ordem de serviço), 6 (previsão) e o histórico de peças com pedido ao Compras.
- O pedido vai pelo WhatsApp, como hoje.

## Decisões de quem constrói (aprovadas com o resumo em conversa)

- A confirmação com o operador é o administrativo escolhendo o nome no pedido (o operador não confirma no tablet).
- O WhatsApp abre sem número; o dono escolhe o destino.
- O link das fotos não expira.

## Critérios de aceite

- Cada parte com teste de tela próprio; `sh tests/rodar.sh` passa.
- O link público mostra só o pedido do código e nada mais; sem código válido, 404.
