# Operações Próspera

Sistema interno da pedreira Próspera para controlar a expedição de material aos clientes (pátio), o transporte da lavra até o britador (Pusch), as máquinas e o horímetro, e para mostrar a operação na TV do escritório.

## Problema e objetivo

O escritório precisa saber, em tempo real, quanto material saiu para cada cliente, quanto foi vendido no dia e quanto tempo os caminhões levam no ciclo da lavra, sem depender de anotação em papel. Os operadores e motoristas registram o trabalho em tablets no campo; o escritório lança as notas fiscais, libera as cargas e acompanha tudo pelo Painel e pela TV.

## Público e papéis

- **Administrativo** (escritório): lança e libera ordens a partir da nota fiscal, confere, corrige, cadastra pessoas, máquinas e materiais, autoriza tablets, vê Painel, Controle de carga, manutenção e o Monitor da TV.
- **Operador**: assume a pá carregadeira (pátio) ou a escavadeira hidráulica (lavra), inicia e finaliza cargas, informa o horímetro e como ficou a máquina.
- **Motorista**: assume o caminhão, marca fila, ida ao britador e volta na lavra.
- **Ambos**: a mesma pessoa pode operar e dirigir, um lugar por vez.
- **Operador do britador secundário**: só essa função; marca o início e o fim da britagem e avisa os problemas do britador.
- Uso no campo em tablets Android (Chrome ou o app da Próspera); a entrada pela foto só aparece em tablets autorizados.

## Fluxos principais

1. **Carga no pátio**: o administrativo lê o PDF ou o XML da nota fiscal, confere e libera a ordem. O tablet da pá avisa com som. O operador inicia e finaliza a carga, e a ordem vai para conferência. Uma nota pode ter até 5 materiais no mesmo caminhão: a carga sai inteira, numa viagem só.
2. **Fracionado**: o cliente leva a nota em várias viagens. O que sobra fica em **Saldo a buscar** até o administrativo liberar a próxima viagem ou encerrar o saldo.
3. **Pusch (lavra → britador)**: o caminhão entra na fila, a escavadeira carrega, o motorista marca a ida, a chegada ao britador e a volta. O sistema calcula as médias de fila, carga, ida, volta e ciclo, fora das viagens encerradas, das idas à garagem e das etapas que atravessam a meia-noite.
4. **Limpeza da lavra**: a escavadeira marca o início e o fim da limpeza; a espera dos caminhões nesse tempo não entra nas médias.
5. **Máquinas**: assumir e soltar com o horímetro, responder "Como ficou a máquina?" e avisar problemas, que aparecem no Painel, na manutenção e na TV. Cada serviço de manutenção preventiva mostra o dia previsto em que vence, pela média de horas por dia dos turnos dos últimos 14 dias. O operador registra no tablet a troca (óleo, filtros...) da máquina que está com ele; na primeira vez de cada serviço, diz de quantas em quantas horas é a troca. Quando faltam 100, 50, 25 e 8 h, o aviso aparece no tablet de quem está com a máquina e no Painel do administrativo (não na TV).
6. **Estoque das pilhas**: o operador da pá lança as conchas que colocou na pilha (Brita 01, Pedrisco). O sistema mostra o total e o que entrou no dia, em m³ e toneladas. Só entra material: os clientes carregam direto da bica.
7. **Britador secundário**: o operador marca no tablet quando a britagem começa e termina e avisa os problemas (com ou sem parada), que ficam abertos até ele marcar resolvido.
8. **Peças e pedido ao Compras**: o administrativo cadastra as peças de cada máquina (com até 5 fotos), marca as que vão ser trocadas, escolhe com qual operador confirmou e envia a mensagem pronta pelo WhatsApp, com um link público das fotos. O pedido passa de enviado a trocado (com o horímetro) ou cancelado, e cada peça guarda a última troca.
9. **Máquina na oficina e ordem de serviço**: o administrativo manda a máquina para a oficina (com o motivo e, se houver, o problema aberto); ninguém assume a máquina até ela ser liberada. Ao liberar, fica a ordem de serviço (o que foi feito, quem fez, pedido de peças e custo) e o problema ligado é resolvido. Ficha e Manutenção mostram a disponibilidade do mês; o Monitor mostra a máquina na oficina.
10. **Notas por transferência no Fin Pro**: a nota principal paga por Pix ou Transferência (TED/DOC), não cancelada, vai sozinha para o Fin Pro (outro sistema do dono) com o PDF lido, ao lançar, ao corrigir a forma e pelo botão **Reenviar**. Se o Fin Pro não responder, fica na fila e é tentada de novo; a ficha mostra a situação.
11. **Acompanhamento**: Painel do administrativo, Controle de carga (vendido no dia, carregado, saldos) e Monitor da TV (pátio, lavra até o britador primário e britador secundário; vendido do dia por forma de pagamento, escolhida na nota).

## O que já existe

Tudo o que está acima está em produção desde outubro de 2026, além de:

- cadastro de pessoas com foto, papéis e senhas; tablets autorizados;
- manutenção preventiva pelo horímetro;
- app Android 1.2 (WebView do sistema, com aviso sonoro e escolha de arquivo da nota);
- relatórios de tempos do pátio e da lavra e "Meus trabalhos" de cada pessoa.

## Fora do escopo

- Emitir nota fiscal (o sistema só lê a nota já emitida).
- Financeiro e cobrança (a conferência do pagamento por transferência fica no Fin Pro).
- Saída de material do estoque das pilhas.
- Publicar o app em loja; ele é instalado pelo endereço `/app`.

## Requisitos

- Interface em português, pensada para tablet no campo: botões grandes, poucos passos, aviso sonoro no pátio.
- Fuso de Mato Grosso (America/Cuiaba).
- Uma pessoa por máquina e uma máquina por pessoa; só quem iniciou a carga finaliza (ou o administrativo).
- Valores e quantidades de nota fiscal contam no dia da emissão da nota.
- Segurança: login com bloqueio após 5 senhas erradas, CSRF em todo formulário, rotas do escritório só para o administrativo, nenhum segredo no repositório (que é público).

## Restrições e decisões técnicas

- PHP 8.3 sem framework e sem gerenciador de pacotes; MySQL 8.4 em produção; Docker no Easypanel. Detalhes em [docs/architecture.md](docs/architecture.md).
- O banco se atualiza sozinho no primeiro acesso depois de publicar, sem ferramenta de migração.
- Publicação: o `main` é publicado quando o dono toca em **Implantar**; nenhuma mudança vai ao `main` sem o "sim" do dono.

## Suposições e decisões pendentes

- Os arquivos maiores (`public/index.php`, `src/Lavra.php`, `src/Operacoes.php`, `src/helpers.php`) passam bastante da referência de 350 linhas. Dividi-los é uma proposta separada, que depende do dono porque mexe no código em produção (ver [docs/setup.md](docs/setup.md)).
- Não há verificação automática no GitHub (CI). Criar uma é uma decisão futura.

## Critérios de aceite

- Cada fluxo acima funciona no tablet e aparece no Painel e na TV, sem erro de PHP.
- O vendido do dia bate com as notas emitidas no dia; cada nota conta uma vez, mesmo fracionada.
- As médias do Pusch não incluem viagens encerradas, idas à garagem, etapas que passam da meia-noite nem a espera durante a limpeza.
- `sh tests/rodar.sh` passa antes de cada entrega (ver [docs/quality.md](docs/quality.md)).

## Próxima entrega

Definida pelo dono a cada conversa. Pendências técnicas conhecidas estão em [docs/setup.md](docs/setup.md#pendências-e-próxima-entrega).

## Manutenção

Atualize este documento quando o objetivo, o escopo, as restrições ou os critérios de aceite mudarem. Decisões técnicas e evidências ficam em `docs/architecture.md` e `docs/setup.md`.
