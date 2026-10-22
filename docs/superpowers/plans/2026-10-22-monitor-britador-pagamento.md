# Monitor novo, Britador Secundário e forma de pagamento — plano

> Execução: nativa (na própria sessão), uma tarefa por vez, testes no contêiner Linux (`sh tests/rodar.sh`).

**Objetivo:** construir o que o design aprovado descreve.
**Design:** [docs/superpowers/specs/2026-10-22-monitor-britador-pagamento-design.md](../specs/2026-10-22-monitor-britador-pagamento-design.md)
**Arquitetura:** os dados do Monitor saem de `Dashboard::instantaneo()` (JSON em `/dashboard/dados`) e são desenhados por
`public/assets/dashboard.js`; o britador é uma classe nova `src/Britador.php` com tela própria; a forma de pagamento é uma
coluna da ordem, lida do XML por `NotaFiscal` e escolhida na conferência da nota.

## Regras de todas as tarefas

- Banco: colunas novas por `garantir*()` com `Db::MIGRACOES` trocado; tabelas novas em `database/schema.sql`.
- Todo POST com `csrf_check()`; textos em português simples; erros por `InvalidArgumentException`.
- O Monitor cabe sem rolagem de 960×540 a 1920×1080 (`tests/telas/tv_tamanhos.js`).

## Pontos de atenção na revisão

1. Nota antiga sem forma de pagamento: entra em "Sem forma", não quebra o Monitor nem o Controle.
2. Ordem fracionada: a forma é da nota principal; viagens filhas não contam de novo no vendido.
3. Britagem esquecida aberta de um dia para o outro: o Monitor do dia seguinte mostra "Sem britagem hoje", e a tela do
   operador deixa terminar a antiga.
4. Dois toques em "Iniciar britagem": uma britagem só (trava `Db::comTrava('britagem')` e checagem de aberta).
5. TV pequena com muitas máquinas em uso e muitos problemas: vira "+ N", nada cortado.

---

### Tarefa 1: forma de pagamento

**Arquivos:** `src/Db.php` (`garantirPagamento`: `fila_carregamentos.nf_pagamento VARCHAR(12) NULL`), `src/NotaFiscal.php`
(`lerXml` devolve `pagamento` a partir de `pag/detPag/tPag`), `src/Operacoes.php` (gravar/ler a forma; `vendas()` com
`por_forma`), `public/index.php` e as views da conferência e da ficha (`views/ordem_campos.php`, `views/ordem.php`),
`src/helpers.php` (`formas_pagamento(): array` = `['pix'=>'Pix','dinheiro'=>'Dinheiro','cartao'=>'Cartão','cheque'=>'Cheque','boleto'=>'Boleto','prazo'=>'A prazo']`).
**Teste:** `tests/telas/forma_pagamento.js` — PDF: liberar sem forma é recusado; com "Pix" grava `pix`. XML (tPag 15) vem
com "Boleto" marcado. Ficha corrige para "Dinheiro". `Operacoes::vendas()` devolve `por_forma` com `sem_forma`.
Unitário: `tests/unitarios/leitura_nota_xml.php` confere `pagamento === 'boleto'` na nota1.

### Tarefa 2: Britador Secundário

**Arquivos:** `database/schema.sql` (`britagens`: id, usuario, inicio, fim NULL; `britagem_problemas`: id, usuario,
descricao, parou TINYINT, inicio, resolvido_em NULL, resolvido_por NULL), `src/Db.php` (`usuarios.britador`),
`src/Pessoas.php` + `views/pessoa_form.php` (função "Operador do britador secundário"; `FUNCOES` ganha `britador`),
`src/Auth.php` (`britador()`, `inicio()` → `/britador`, `rotuloPapel`), `src/Britador.php` (`iniciar(string $usuario)`,
`terminar(string $usuario)`, `avisar(string $usuario, string $descricao, bool $parou)`, `resolver(int $id, string $usuario)`,
`aberta(): ?array`, `doDia(string $dia): array{inicio:?string, fim:?string, aberta:bool, parado:bool, problemas:list}`),
`public/index.php` (rotas `britador`, `britador/iniciar`, `britador/terminar`, `britador/problemas`,
`britador/problemas/{id}/resolver`, com `exigir_papel('britador')`), `views/britador.php`, `views/layout.php` (menu).
**Teste:** `tests/telas/britador.js` — pessoa nova com a função entra e cai em `/britador`; inicia; avisa problema que
parou; resolve; termina; `Britador::doDia` bate; motorista recebe 403; toque duplo em iniciar não cria duas.

### Tarefa 3: dados do Monitor

**Arquivos:** `src/Dashboard.php` — `instantaneo()` ganha `vendas.por_forma`, `britador` (= `Britador::doDia`),
`em_uso` (de `maquina_posse`: tipo, nome da máquina, pessoa, o que faz), `problemas` com `area` (`patio` para pá, `lavra`
para escavadeira e caminhão) e `melhor_hora` (`patio` e `lavra`: hora e quantidade, a partir de `horasPatio/horasLavra`).

### Tarefa 4: Monitor novo

**Arquivos:** `public/assets/dashboard.js`, `public/assets/dashboard.css`, `views/dashboard.php` (sai a opção "Mostrar o
vendido": o vendido agora é um dos três números).
Três números, três colunas (`tv-zona` pátio, lavra com pista e "Máquinas em uso", britador), rodapé com a melhor hora;
saem a faixa de problemas, os gráficos por hora e o estoque. Problemas da pá aparecem no cartão da pá ("Parada").
**Testes:** atualizar `tests/telas/tv_*.js` e `tests/telas/valor_nota.js` (vendido sempre visível); `tv_tamanhos` continua
sem rolagem nos quatro tamanhos.

### Tarefa 5: documentos

`README.md` (Monitor, britador, forma de pagamento), `CHANGELOG.md` com "Ao publicar esta versão", `PRODUCT.md`
(papéis e fluxos), `docs/architecture.md` (mapa: `Britador.php`).
