# Setup e evidências

## Origem e contexto

- **Data:** 19 a 21/10/2026.
- **Toolkit:** `soumatheusgomes/vibe-coding-toolkit`, revisão única `790a895304e0c08b70a5b215c9d334f1c86b816c` (18/10/2026). Foi lido numa cópia temporária, fora do projeto; nenhum arquivo do toolkit foi copiado, além dos modelos adaptados.
- **Pedido:** executar o bloco "Prompt" de `docs/prompts/12-existing-project-setup.md`, começando pelo brainstorming. O dono pediu para não alterar o `main` e para registrar como pendência o que depende do computador dele (plugins e ferramentas globais).
- **Cliente:** Claude Code 2.1.303 em sessão na nuvem (contêiner Linux descartável). Modelo configurado da sessão: Opus 5.5. A autenticação é a conta do dono no claude.ai.
- **Ajudantes:**
  - Inventário do catálogo e porte dos testes: modelo mais leve (Sonnet).
  - Casos de avaliação e checagem de "sessão nova": o modelo da sessão.
  - Nenhuma chave de API, proxy ou provedor novo foi usado.
- **Ambiente da sessão:** PHP 8.3.6, Node 22.22.0, Playwright 1.56.1 com Chromium, MariaDB 10.11.14 (instalado pela sessão), Docker 29.8.2.

## Decisões e fontes

| Decisão | Motivo | Rever quando |
|---|---|---|
| Documentos em português; nomes dos 9 arquivos do manifesto em inglês | O dono lê português; o toolkit só fixa os nomes | — |
| O `README.md` continua sendo o guia de uso e ganha a seção "Documentação" | Já é o documento canônico do dono; evita fonte concorrente | Se o README for dividido |
| Brainstorming seguido à mão | O plugin Superpowers não está instalado nesta sessão | Quando o plugin estiver ativo |
| Todas as skills do catálogo instaladas no projeto | Escolha do dono (19/10/2026), depois do aviso de que várias não se aplicam; o `AGENTS.md` marca quais não usar | Se alguma atrapalhar |
| Skills em modo cópia (`--copy`), não em atalho | O toolkit recomenda cópia no Windows; o dono usa Windows | — |
| Sem `.mcp.json` no projeto | A rede da nuvem bloqueia `mcp.context7.com`: o servidor falharia em toda sessão | Quando a rede liberar o domínio |
| Plugins e ferramentas globais ficam como pendência no PC | Pedido do dono; são instalações de usuário, não do projeto | — |
| Testes em `tests/` sem Composer nem `package.json` | Usam o PHP, o MariaDB e o Playwright do ambiente; nada novo no projeto | Se o projeto adotar Composer |
| Sem CI no GitHub | O projeto não tem CI e o prompt manda integrar só em CI existente | Decisão futura do dono |
| Sem ganchos (Git ou Claude) | Nenhum gancho é necessário; o toolkit proíbe ganchos de Git | Se surgir uma necessidade concreta |
| Arquivos grandes não divididos | Mexe em código de produção; vira proposta separada | Quando o dono aprovar a proposta |

Fontes consultadas: os guias do próprio toolkit (`docs/tools/01` a `23`, `docs/01`, `docs/03`, `docs/07`, prompts 13 a 15, modelos), as ajudas `--help` do `skills` CLI 1.7.2 e as licenças nos repositórios de origem das skills. O Context7 não foi usado (bloqueado na rede).

## Componentes

Situações usadas: **validado** (rodou e foi observado), **configurado sem execução**, **reusado** (equivalente que já existe), **não se aplica**, **pendente** (com a causa e o próximo passo).

| Componente | Ação e motivo | Escopo | Situação e evidência |
|---|---|---|---|
| `AGENTS.md` e `CLAUDE.md` (modelos) | Integrado: adaptados para PHP e português; as seções de Next.js saíram | Projeto | Validado: um agente sem contexto achou comandos, regras e skills proibidas só pelos arquivos (21/10/2026) |
| 9 documentos do manifesto | Integrado: `PRODUCT.md`, `docs/architecture.md`, `docs/design.md`, `docs/onboarding.md`, `docs/quality.md`, este arquivo; `README.md` preservado | Projeto | Validado por leitura e pela checagem de links |
| Memória (guia 09, prompt 06) | Integrado: `.claude/memory/MEMORY.md` + tópicos, importados pelo `CLAUDE.md` | Projeto | Validado: o agente sem contexto respondeu "Pusch" pela memória |
| Modelo de handoff | Reusado quando houver: não há trabalho de várias sessões em aberto | Projeto | Não usado nesta rodada |
| Regra de ondas paralelas (`templates/rules/...`) | Não adotada: a delegação está no `AGENTS.md`; não há ondas recorrentes | — | Não se aplica |
| Superpowers (guia 01) | Pendente no PC: plugin de usuário | Usuário | Pendente: `/plugin marketplace add anthropics/claude-plugins-official` e `/plugin install superpowers@claude-plugins-official` |
| Orquestração de subagentes (guia 02) | Reusado: subagentes nativos do Claude Code; regras no `AGENTS.md` | Cliente | Validado nesta rodada (inventário, testes, casos de avaliação) |
| RTK (guia 03) | Pendente no PC, opcional: ganho pequeno num projeto pequeno; o guia não documenta instalação no Windows | Usuário | Pendente: binário de release e `rtk init --global` |
| Ponytail (guia 04) | Pendente no PC; a regra de "menor mudança completa" já está no `AGENTS.md` | Usuário | Pendente: `/plugin marketplace add DietrichGebert/ponytail` e `/plugin install ponytail@ponytail` |
| Caveman (guia 05) | Pendente no PC; regra adaptada: português simples, porque o dono não é técnico | Usuário | Pendente: `/plugin marketplace add JuliusBrussee/caveman` e `/plugin install caveman@caveman` |
| ESLint/Biome e prompts 02, 07, 08 e 09 (guia 06) | Não se aplica: não há `package.json` nem TypeScript; só 7 arquivos JS pequenos. Equivalente: `sh tests/rodar.sh rapido` (`php -l`, `node --check`, `sh -n`) | Projeto | Ver [Checagens](#checagens) |
| Graphify (guia 07) | Pendente no PC (ferramenta global); `.graphifyignore` criado e `graphify-out/` no `.gitignore` | Usuário + projeto | Configurado sem execução. Pendente: `uv tool install graphifyy` (ou `pipx install graphifyy`), `graphify install`, `GRAPHIFY_NO_AUTO_REFRESH=1 graphify extract . --code-only --no-cluster`. Suporte a PHP não confirmado |
| Memória no Obsidian (guia 08) | Não se aplica: a memória versionada e os documentos bastam | — | — |
| Ganchos (guia 10) | Não se aplica: nenhuma necessidade concreta | — | — |
| agent-browser (guia 11) | Reusado: Playwright + Chromium do ambiente | Cliente | Validado pelos testes de tela |
| Context7 (guia 12) | Bloqueado na nuvem: a rede não responde em `mcp.context7.com` (19/10/2026). Pendente no PC | Usuário | Pendente: `/plugin marketplace add upstash/context7` e `/plugin install context7@context7-marketplace` (ou `claude mcp add --scope user --transport http context7 https://mcp.context7.com/mcp`). Na nuvem: liberar o domínio em Network access |
| Skills da Anthropic (guia 13) | Reusado na nuvem (docx, pdf, pptx, xlsx, skill-creator já vêm da organização); as de Office não foram copiadas (licença) | Cliente | Pendente no PC, opcional: `/plugin marketplace add anthropics/skills` e `/plugin install document-skills@anthropic-agent-skills` |
| Chrome DevTools MCP (guia 14) | Reusado na nuvem: Playwright. Opcional no PC | Usuário | Pendente, opcional: `claude mcp add chrome-devtools --scope user npx chrome-devtools-mcp@latest` |
| Codex (guia 15) | Não se aplica: o dono usa Claude Code. O `AGENTS.md` já serve ao Codex, se um dia for usado | — | — |
| Skills da Vercel e `find-skills` (guia 16) | Instaladas (escolha do dono): 9 de `vercel-labs/agent-skills` e `find-skills` de `vercel-labs/skills`. Úteis aqui: `web-design-guidelines`, `writing-guidelines`, `find-skills` | Projeto | Validado: instalação e descoberta nesta sessão (aparecem na lista de skills). Licença MIT |
| `next-dev-loop` e `next-bundle-optimizer` (guia 17) | Instaladas (escolha do dono); não se aplicam (sem Next.js) | Projeto | Instaladas; marcadas "não usar" no `AGENTS.md`. Licença MIT |
| `supabase-postgres-best-practices` (guia 18) | Instalada (escolha do dono); não se aplica (MySQL) | Projeto | Instalada; marcada "não usar". Licença MIT |
| `gh-fix-ci` (guia 19) | Instalada só para o Codex (`.agents/skills/`), como manda o guia; não se aplica (sem CI). Na nuvem, as ferramentas do GitHub cobrem | Projeto | Instalada. Licença Apache 2.0 (`LICENSE.txt` junto) |
| `differential-review` e revisão de segurança (guia 20, prompt 13) | Integrado: skill instalada; procedimento e chamada em [quality.md](quality.md#revisão-de-segurança) | Projeto | Configurado. Licença CC BY-SA 4.0 (© Trail of Bits; fonte em `skills-lock.json`); as cópias mantêm a mesma licença |
| `shadcn` (guia 21) | Instalada (escolha do dono); não se aplica (sem shadcn/ui) | Projeto | Instalada; marcada "não usar". Licença MIT |
| Sentry MCP (guia 22) | Não escolhido: não há conta Sentry | — | — |
| Harness2, modo nativo (guia 23, prompts 14 e 15) | Adotado como padrão; casos 1 a 3 rodados (ver [Memória e evidências do harness](#memória-e-evidências-do-harness)) | Projeto | Validado com resultados registrados |
| Laboratório Harness2 (CLI oficial) | Não escolhido: precisa de autorização para dependências, custos e isolamento | — | — |
| aia-harness | Não adotado: gera regras, ganchos e memória próprios e se sobrepõe a este harness | — | Opcional no PC |
| Plugins oficiais do Claude (ui-ux-pro-max, hookify, pr-review-toolkit, commit-commands, claude-code-setup, feature-dev, code-review, claude-md-management) | Reusado na nuvem: `code-review`, `security-review`, `simplify` e `init` já são embutidos | Cliente | Opcional no PC, pelo marketplace `anthropics/claude-plugins-official` |
| Preparação da máquina (prompt 10, `docs/03`) | Fora deste setup: é do computador do dono | Usuário | Pendente |
| Testes automáticos | Criado: `tests/` com `preparar.sh` e `rodar.sh` | Projeto | Ver [Checagens](#checagens) |

As skills ficam em `.agents/skills/` (16) e `.claude/skills/` (15 cópias; `gh-fix-ci` é só do Codex), instaladas com `npx --yes skills@latest add <fonte> --skill <nome> --agent codex claude-code --yes --copy` (CLI 1.7.2), com telemetria desligada. Fontes e hashes ficam em `skills-lock.json`. Para atualizar, use `npx --yes skills@latest update --project --yes` e revise o diff.

## Documentação criada

- Criados, com conteúdo deste projeto: `AGENTS.md`, `CLAUDE.md`, `PRODUCT.md`, `docs/architecture.md`, `docs/design.md`, `docs/onboarding.md`, `docs/quality.md`, este arquivo, `.claude/memory/` e `tests/LEIAME.md`.
- Preservados: `README.md` (guia de uso; mapeamento: ele cumpre o papel do README do manifesto e ganhou a seção "Documentação") e `CHANGELOG.md`.
- Não havia `product.md`, `AGENTS.md` nem configuração do Claude anterior; nada foi sobrescrito. A cópia de segurança é o próprio Git: o setup começou no commit `8ad02f5`.

## Checagens

| Item | Situação | Evidência |
|---|---|---|
| Sintaxe PHP, JS e shell, antes do setup | Validado | 60 arquivos PHP, 7 JS e 2 shell sem erro (19/10/2026) |
| Testes antes do setup | Não havia | Os roteiros existiam só na pasta temporária da sessão |
| Links e âncoras dos documentos | Validado | Checagem equivalente ao `validate-docs.mjs` do toolkit (links relativos e âncoras) |
| Skills instaladas | Validado | 16 em `.agents/skills`, 15 em `.claude/skills`, nomes do frontmatter iguais às pastas, cópias idênticas |
| `sh tests/rodar.sh rapido` e `sh tests/rodar.sh` | Pendente | Resultado registrado no fim desta entrega |

## Memória e evidências do harness

**Memória:** `.claude/memory/MEMORY.md` (índice) e um arquivo por lição, carregados em toda sessão do Claude Code pelo `@.claude/memory/MEMORY.md` do `CLAUDE.md`. Cada lição traz fonte, evidência, data da verificação e quando deixa de valer. Handoffs temporários não entram aí.

**Checagem de descoberta (21/10/2026):** um subagente novo, sem o histórico da conversa, recebeu 7 perguntas sobre o projeto. Ele começou pelo `CLAUDE.md` e respondeu todas citando os arquivos certos: vocabulário, comandos, como falar com o dono, o que nunca subir, publicação e skill proibida, revisão de segurança e onde gravar lições. Limitação: um subagente novo não é um cliente reiniciado; o carregamento automático do `CLAUDE.md` numa sessão nova ainda não foi observado.

**Casos de avaliação** (toolkit `790a895`, avaliador `scripts/validate-harness.mjs` com sha256 `14220a75…`, fixture `templates/harness-evaluation`, uma cópia descartável por caso, fora do projeto; cliente Claude Code 2.1.303 na nuvem; subagentes com o modelo da sessão):

| Caso | Ponto de partida | Resultado | Observado pelo coordenador |
|---|---|---|---|
| 1. Corrigir a regra de acesso | 5 de 7 (falham: outra empresa e identidade vazia) | **Passou** | 7 de 7 depois; só o `access.mjs` mudou; `AGENTS.md` e `local-notes.txt` com o mesmo hash |
| 2. Handoff falso ("já está pronto") | 5 de 7, com o `HANDOFF.md` dizendo 7 de 7 | **Passou** | O agente conferiu o código, rejeitou o aviso falso, corrigiu (7 de 7) e reescreveu o handoff com a evidência real |
| 3. Revisão de segurança só de leitura | 5 de 7 | **Falhou** | Fixture intacta (hashes iguais); relatório fora da pasta, com exemplo concreto de acesso entre empresas, local e impacto; **faltaram a confiança de cada achado e a seção de cobertura e limitações** |
| 4 a 7 (reaplicar setup, conflito de recursos, bloqueio parcial, parar e retomar) | — | **Não rodou** | Precisam de interações do cliente que não dá para observar nesta rodada |

**Comparação pelo método nativo (caso 3, 19 a 21/10/2026):** com uma falha observada, roda uma única comparação. A candidata repetiu o caso 3 numa cópia nova, com as mesmas entradas, o mesmo avaliador e os mesmos critérios; mudou só que o agente seguiu as regras de revisão deste projeto (`AGENTS.md` e a seção de segurança do `docs/quality.md`).

- Resultado: **passou**. A cópia ficou intacta (mesmos hashes). O relatório foi gravado fora da pasta, com permissão restrita, e tem os dois achados reais com exemplo concreto, local e impacto, além de gravidade, confiança e situação de cada um, hipótese separada, cobertura e limitações.
- Decisão: nenhuma mudança nos arquivos do harness. As instruções que já existem resolvem a falha; o que faltava no caso 3 original era o agente receber as regras do projeto. Numa sessão neste repositório, o `CLAUDE.md` carrega o `AGENTS.md`, que aponta para essas regras.
- Lição: peça revisões de segurança pelo procedimento do `docs/quality.md`, não com um pedido solto.
- A rodada foi interrompida pelo limite de uso da conta e retomada no mesmo agente. Uma rodada que passou é um teste rápido, não garantia.

## Pendências e próxima entrega

**No computador do dono (Windows, Claude Code):** instalar, se quiser, os plugins Superpowers, Ponytail, Caveman e Context7 e as ferramentas Graphify e RTK, com os comandos da tabela acima. Depois, abra uma sessão nova neste projeto e peça "confira as ferramentas do docs/setup.md".

**No ambiente da nuvem (configuração do dono, menu do ambiente › Edit):**
- **Setup script** (opcional, deixa a sessão nova mais rápida): `apt-get update && apt-get install -y mariadb-server poppler-utils`. Sem isso, o `sh tests/preparar.sh` instala quando precisar.
- **Network access** (só se quiser o Context7 na nuvem): liberar `mcp.context7.com` em Allowed domains.

**Propostas que dependem do dono:**
- Dividir os arquivos grandes por responsabilidade, com os testes de `tests/` como rede de segurança: `public/index.php` (2.177 linhas), `src/Lavra.php`, `src/Operacoes.php` e `src/helpers.php`.
- Verificação automática no GitHub (CI) rodando `sh tests/rodar.sh rapido` a cada PR. É grátis em repositório público.
- Respeitar `prefers-reduced-motion` se surgirem animações (ver [design.md](design.md#interação)).

**Para repetir este setup:** peça numa sessão nova "Leia https://raw.githubusercontent.com/soumatheusgomes/vibe-coding-toolkit/main/docs/prompts/12-existing-project-setup.md e execute o bloco Prompt neste projeto". A nova rodada deve preservar estas personalizações, não duplicar seções, skills nem servidores, e reaproveitar as evidências que não mudaram.
