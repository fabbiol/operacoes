# Qualidade

## Comandos

| Comando | O que prova | Quando rodar |
|---|---|---|
| `sh tests/preparar.sh` | Deixa a sessão pronta: MariaDB em `127.0.0.1:3307`, usuário de teste e `pdftotext`; avisa se faltar o Playwright | Uma vez por sessão nova na nuvem, ou quando o banco cair |
| `sh tests/rodar.sh rapido` | Sintaxe de todo PHP (`php -l`), JS (`node --check`) e shell (`sh -n`), mais os testes que não precisam de banco | A cada mudança, em segundos |
| `sh tests/rodar.sh` | Tudo acima, mais integração com banco temporário e telas no navegador (Playwright) | Antes de subir o branch e antes de pedir para juntar no main |

O resumo final mostra OK, FALHA ou PULADO para cada parte, com o motivo do PULADO; uma parte pulada não conta como aprovada. A lista dos testes, as variáveis (`NOTA_REAL_DIR` etc.) e o modo de rodar só uma parte estão em `tests/LEIAME.md`.

Não há lint, verificação de tipos nem build: o PHP e o JS vão para produção como estão. A imagem Docker é montada pelo Easypanel a partir do `Dockerfile`.

## Regras de base

- `declare(strict_types=1)` em todo PHP; consultas sempre preparadas pelo PDO; todo texto do banco escapado com `e()` nas views e com `esc()`/`textContent` no JS.
- Todo POST com `csrf_check()`; rota do escritório depois de `exigir_admin();` em `public/index.php`.
- Mensagens de erro para o usuário em `InvalidArgumentException`, sem expor detalhes internos nem segredos.
- Referência de tamanho: 350 linhas por arquivo (toolkit). Hoje quatro arquivos passam bastante disso (ver [architecture.md](architecture.md#mapa-do-código)). Não divida arquivo sem um benefício que dê para verificar; a proposta está em [setup.md](setup.md#pendências-e-próxima-entrega).
- Mudança no banco segue [architecture.md](architecture.md#dados-e-migrações): `garantir*()` que pode rodar mais de uma vez e troca de `Db::MIGRACOES`.

## Estratégia de testes

- Os testes ficam em `tests/` e não vão para o servidor: ficam fora da imagem Docker pelo `.dockerignore`, e o `deploy.ps1` só envia `src`, `views`, `public` e o schema.
- Cada teste com banco ou tela cria o próprio ambiente: cópia do código numa pasta temporária, banco `op_t_*`, servidor `php -S` numa porta livre entre 8170 e 8199 e dados de exemplo inventados. Tudo é apagado no fim.
- Notas fiscais: só as notas de mentira de `tests/notas/` (CNPJ e clientes inventados). Uma nota real de cliente nunca vai para o Git; os trechos que precisam dela só rodam com `NOTA_REAL_DIR` apontando para uma pasta fora do repositório.
- Correção de erro: reproduza a falha com um teste antes e mostre o mesmo teste passando depois.
- Não há teste de carga, de concorrência real entre aparelhos nem do app Android dentro de um tablet. Isso é verificado à mão pelo dono depois do **Implantar**.

### Avaliação da IA (harness)

- Modo adotado: **adaptação nativa do método inspirado no Harness2**, pelo próprio agente da sessão. Não roda o framework Harness2 nem reproduz os resultados dele.
- Fonte do procedimento: toolkit `soumatheusgomes/vibe-coding-toolkit`, revisão `790a895304e0c08b70a5b215c9d334f1c86b816c`, arquivos `docs/prompts/15-harness-improvement.md` (melhoria) e `docs/prompts/14-harness-evaluation.md` com `docs/07-harness-evaluation.md` (casos).
- Como chamar numa sessão: "Leia https://raw.githubusercontent.com/soumatheusgomes/vibe-coding-toolkit/790a895304e0c08b70a5b215c9d334f1c86b816c/docs/prompts/14-harness-evaluation.md e execute o bloco Prompt neste projeto". Para a melhoria, troque por `15-harness-improvement.md`.
- Quando rodar: depois de mudar `AGENTS.md`, `CLAUDE.md`, a memória, as skills ou o jeito de delegar. Os casos rodam em cópias descartáveis, fora do projeto.
- Resultados e data da última rodada: [setup.md](setup.md#memória-e-evidências-do-harness). Uma rodada que passou é um teste rápido, não garantia de que vai funcionar sempre.

## Revisão depois de implementar ou corrigir

Escolha o escopo da revisão pelo risco e pelo diff real. Para mudança que não é trivial, use uma revisão independente (outro agente, só leitura) quando houver. Registre o alvo exato, quem chama o código mudado, os contratos e testes afetados, os achados confirmados e a cobertura. Só faça outra rodada se houver evidência nova ou risco não resolvido; respeite uma contagem pedida pelo dono.

Termine quando os achados relevantes estiverem resolvidos ou decididos, as checagens afetadas passarem e os riscos que sobraram estiverem registrados. Revisão que não pôde ser feita fica escrita como limitação.

## Revisão de segurança

- Procedimento: o bloco "Prompt" de `docs/prompts/13-security-review.md` do toolkit, revisão `790a895304e0c08b70a5b215c9d334f1c86b816c`. Ele não foi copiado para cá: o projeto não tem pasta de prompts, e a versão fixada evita que o texto mude sem aviso.
- Como chamar:
  - Revisão do estado atual (baseline): "Leia https://raw.githubusercontent.com/soumatheusgomes/vibe-coding-toolkit/790a895304e0c08b70a5b215c9d334f1c86b816c/docs/prompts/13-security-review.md e execute o bloco Prompt neste projeto".
  - Revisão de uma mudança: acrescente "no intervalo `<base>..<head>`".
- Mudança que mexe em login, permissões, sessão, upload, nota fiscal ou banco: use também a skill `differential-review` (instalada no projeto) sobre o diff.
- A revisão só lê: não muda arquivos, não instala nada, não acessa produção nem manda código para fora.
- O relatório fica fora do repositório. Na nuvem, a pasta temporária some no fim da sessão; entregue o relatório resumido e sem dados sensíveis na conversa, ou num lugar privado escolhido pelo dono. Nunca grave o relatório dentro do projeto.
- Classifique cada achado por gravidade (crítica, alta, média, baixa), confiança (alta, média, baixa) e situação (confirmado ou precisa validar), e separe as hipóteses. "Nenhum achado" não quer dizer que o sistema é seguro.
- Revisões feitas: em 17/10/2026, uma revisão de segurança e uma simulação de um dia de trabalho sobre o PR #15. Os achados foram corrigidos no mesmo PR.

## Dependências e entrega

- O projeto não tem gerenciador de pacotes, então não há auditoria de pacotes PHP ou JS. As dependências reais são as imagens `php:8.3-apache` e `mysql:8.4`, as bibliotecas do sistema instaladas no `Dockerfile` e as ferramentas de montagem do app Android. Atualize as imagens de propósito, testando com `sh tests/rodar.sh`.
- As skills ficam em `.agents/skills/`, travadas por `skills-lock.json`. Atualize com `npx --yes skills@latest update --project --yes` e revise o diff.
- As checagens são comandos explícitos. Não há ganchos de Git, bloqueio de push nem exigência de branch. Atualize este documento junto com os scripts que mudarem.
