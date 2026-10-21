# Telas e acessibilidade

## Direção

- Quem usa: operadores e motoristas em tablets Android no campo (sol, luva, pressa), o administrativo no computador do escritório e a TV da sala, lida de 3 a 6 metros.
- Idioma: português do Brasil, frases curtas, sem siglas técnicas.
- Tablet: botões grandes, uma ação principal por tela, cronômetros visíveis, aviso com som e quadro grande no pátio ("Ciente" sempre à vista).
- TV: fundo escuro, poucos números grandes, cor só para o que pede atenção (espera longa, problema, conexão caída).

## Sistema existente

- Cores e sombra do sistema: variáveis em `:root` no início de `public/assets/app.css` (`--ink`, `--paper`, `--gold`, `--muted`, `--line`, `--saida`, `--entrada`, `--info`). Fonte: "Segoe UI", Arial.
- TV: variáveis `--tv-*` e a escala `--k` em `public/assets/dashboard.css`. A escala muda com a tela cheia.
- Componentes reaproveitados nas views: `trecho_*.php` (máquina, quem está com ela, situação, carga da lavra, horímetro), `.cartao`, `.selo status-*`, `.aviso`, `.botoes`, `.btn primario|secundario|perigo|concluir`.
- O valor de cada cor e medida fica nos arquivos CSS; este documento não repete valores.

## Interação

- HTML semântico, navegação por teclado, foco visível e contraste adequado.
- Todo campo tem rótulo. O erro aparece ao lado do campo ou num aviso no topo do formulário.
- Envio mostra "Enviando…", e o toque duplo é barrado (`public/assets/envio.js`).
- Telas que se atualizam sozinhas trocam o bloco `#fluxo` quando o estado muda (`public/assets/fluxo.js`). A região viva da página anuncia só a queda e a volta da conexão e o fim da sessão; a troca do bloco não é anunciada.
- Sem sinal, o tablet mostra "Sem conexão" (`public/offline.html`, `public/sw.js`) e volta sozinho.
- Movimento: não existe regra `prefers-reduced-motion` nos CSS (19/10/2026). Hoje só há uma transição. Se entrar animação nova, ela deve respeitar essa preferência.

## Layout e revisão

- Cortes do `app.css`: largura 980 px e 720 px, altura 560 px e paisagem com altura de até 520 px.
- Cortes do `dashboard.css`: largura 900 px e 600 px, além do modo tela cheia.
- Estados que existem: lista vazia (`.vazio`), erro de formulário (`.aviso.erro`), sem conexão, carregando ("Enviando…"), aviso pendente no pátio.
- Revisões feitas:
  - Monitor da TV fotografado em 960×540, 1280×720, 1366×768 e 1920×1080 (17/10/2026).
  - Tela do motorista no tamanho do tablet em pé, 600×850 (outubro de 2026).
  - Aviso do pátio com 5 avisos numa tela de 480 px de altura (17/10/2026).
- Para revisar uma tela, use a skill `web-design-guidelines` e fotografe com o Playwright nos tamanhos acima.

## Manutenção

Mudanças visuais ou de interação atualizam o CSS de origem e esta página quando uma regra muda.
