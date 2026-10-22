# Monitor novo, Britador Secundário e forma de pagamento

Data: 22/10/2026. Aprovado pelo dono em conversa, a partir da página de teste
"Monitor da TV — teste" (desenho com dados de exemplo).

## Por quê

O Monitor da TV ficou confuso para quem olha de fora: números repetidos, tudo com o mesmo peso, a pista sem
explicação e os gráficos por hora quase vazios. O dono quer uma TV que se entende de longe e que mostre também
o Britador Secundário, que hoje o sistema não acompanha, e o vendido do dia por forma de pagamento.

## Objetivos

1. **Monitor novo**, como o desenho aprovado:
   - Cabeçalho: Próspera, data por extenso e relógio grande. Sem as faixas de notificação no topo.
   - Três números grandes:
     - "Entregue aos clientes hoje" (t, cargas, quantos na fila);
     - "Viagens ao britador primário hoje" (viagens, t levadas, tempo da viagem completa);
     - "Vendido hoje" (total em R$, divisão por forma de pagamento, número de notas).
   - Três colunas lado a lado:
     - **Clientes · Pátio**: cada pá com o que está fazendo, em palavras (carregando quem, há quanto tempo, quem
       opera; parada com o problema), e a fila numerada com "espera N min" (a maior espera em amarelo).
     - **Lavra → Britador Primário**: problemas abertos de máquinas da lavra (escavadeiras e caminhões) no topo; a
       pista oval de antes, com os caminhões no lugar onde estão (amarelo na lavra, azul carregado/no britador,
       cinza voltando, borda vermelha se a máquina tem problema aberto) e os textos "indo carregado →" e
       "← voltando vazio"; legenda; "Máquinas em uso": cada máquina com alguém (pás, escavadeiras e caminhões),
       com a pessoa e o que está fazendo. Quando não cabe, os últimos viram "+ N caminhões".
     - **Britador Secundário**: situação agora (Britando / Parado por problema / Sem britagem hoje / Terminou),
       hora em que começou, hora em que terminou ("ainda britando" se não terminou) e os problemas do dia (abertos
       em vermelho, resolvidos em cinza, com horário).
   - Rodapé: "Melhor hora do pátio: 9h, com 5 cargas · Melhor hora da lavra: 10h, com 8 viagens" e "Atualizado às".
   - Sai do Monitor: o estoque (fica só na tela Estoque), os gráficos por hora e as faixas de notificação.
2. **Função "Operador do britador secundário"** no cadastro de Pessoas. A pessoa só tem essa função.
   - Tela única no tablet, com três ações: **Iniciar britagem**, **Avisar problema** (descrição e "o britador parou?"
     sim/não) com **Resolvido** em cada problema aberto, e **Terminar britagem**.
   - Pode haver mais de uma britagem no dia. O Monitor mostra o primeiro início e o último fim do dia.
3. **Forma de pagamento da nota**: Pix, Dinheiro, Cartão, Cheque, Boleto, A prazo.
   - Nota lida por PDF: na conferência aparece o campo "Forma de pagamento", obrigatório para liberar. O PDF da
     Próspera não traz a forma (conferido numa nota real em 22/10/2026).
   - Nota lida por XML: o sistema preenche pelo `pag/detPag/tPag` e o administrativo confere. Códigos: 01 →
     Dinheiro, 02 → Cheque, 03 e 04 → Cartão, 15 → Boleto, 17 → Pix, 05 e 14 → A prazo; outros códigos ficam em
     branco para escolher.
   - A forma pode ser corrigida na ficha da ordem, como o valor.
   - O "Vendido hoje" soma o valor das notas emitidas no dia por forma, com as mesmas regras do vendido de hoje
     (cada nota uma vez, mesmo fracionada; canceladas fora). Nota sem forma (as antigas) entra em "Sem forma".

## Fora do escopo

- Alternar telas no Monitor.
- Horímetro, posse ou manutenção do Britador Secundário (ele não é cadastrado como máquina).
- Ler o arquivo ZIP do XML.
- Relatórios de britagem além do Monitor.

## Restrições

- Tudo o que vale para o resto do sistema: PHP sem framework, CSRF, toque repetido, textos em português simples,
  banco atualizado sozinho por `garantir*()` com `Db::MIGRACOES` trocado.
- O Monitor precisa caber sem rolagem de 960×540 a 1920×1080 (teste `tv_tamanhos`). Na TV pequena, o que não cabe
  é resumido ("+ N"), nunca cortado.
- A pista continua mostrando o caminhão pela etapa e pelo tempo da viagem, como hoje.
- O Painel do administrativo continua como está.

## Decisões do dono

- O estoque sai do Monitor e fica só na tela Estoque.
- A lavra mantém a pista "do jeito antigo, mais fluida", com o nome "Britador Primário".
- As notificações de cima saem; os avisos ficam dentro da coluna de cada parte.
- O Britador Secundário tem um funcionário só para ele.
- A forma de pagamento vem da nota: escolhida na mão quando a nota é PDF (o XML vem em ZIP e não será usado no
  dia a dia).

## Decisões de quem constrói (conferir)

- O Britador Secundário guarda britagens (início, fim, quem) e problemas (início, descrição, parou sim/não,
  resolvido quando e por quem) em tabelas próprias, separadas dos problemas de máquinas.
- O administrativo também pode abrir a tela do britador para marcar no lugar do operador.
- A forma de pagamento fica na ordem principal da nota (as viagens do fracionado herdam).
- Cartão de crédito e débito ficam juntos em "Cartão".
- Quem entra pela foto no tablet autorizado inclui o operador do britador.

## Critérios de aceite

- O Monitor mostra as três colunas e os três números com os dados reais e passa nos testes de TV existentes,
  atualizados para o desenho novo.
- O operador do britador inicia, avisa problema, resolve e termina pelo tablet, e o Monitor mostra os horários e
  os problemas.
- Uma nota lida por PDF não libera sem forma de pagamento; uma lida por XML vem com a forma certa; o "Vendido
  hoje" divide o total por forma e bate com o Controle de carga.
- `sh tests/rodar.sh` passa, com testes novos para o britador e a forma de pagamento.
