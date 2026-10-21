# Operações Próspera — instruções para agentes

Sistema da pedreira Próspera: fila de carregamento do pátio, ciclo de lavra (Pusch), máquinas e horímetro, Monitor da TV e app Android dos tablets. O escopo está em [PRODUCT.md](PRODUCT.md); o mapa do código em [docs/architecture.md](docs/architecture.md); checagens em [docs/quality.md](docs/quality.md); ferramentas, decisões e pendências em [docs/setup.md](docs/setup.md). As lições do projeto estão em [.claude/memory/MEMORY.md](.claude/memory/MEMORY.md).

## Produto e stack

- PHP 8.3 sem framework e sem gerenciador de pacotes; MySQL 8.4 em produção (MariaDB nos testes); views PHP renderizadas no servidor e JavaScript puro em `public/assets/`.
- App Android (WebView) em `android/`, montado por `android/construir.sh`.
- Produção: Docker no Easypanel, que publica o branch `main` quando o dono toca em **Implantar**. `deploy.ps1` publica na Hostinger (caminho secundário).

## Comandos

- Preparar a sessão na nuvem (banco de teste e ferramentas): `sh tests/preparar.sh`
- Checagem rápida (sintaxe de PHP, JS e shell e testes sem banco): `sh tests/rodar.sh rapido`
- Todos os testes (integração com banco temporário e telas com Playwright): `sh tests/rodar.sh`
- Rodar o sistema no computador: `docker compose -f docker-compose.yml -f docker-compose.local.yml up --build` (porta 8081)
- Não há lint, tipos nem build: PHP e JS são publicados como estão. Detalhes em [docs/quality.md](docs/quality.md).

## Acordos de trabalho

- O dono não é técnico: converse em português simples, sem jargão, e explique o que muda para quem usa o sistema. Código, comentários e commits também em português, no estilo do código vizinho.
- Leia o código e as instruções relevantes antes de editar. Separe o que foi observado do que é suposição.
- Faça a menor mudança completa que resolve o pedido; reuse padrões e funções existentes. Preserve validação, CSRF, autorização, tratamento de erro e acessibilidade.
- Não crie abstrações, dependências nem recursos que não foram pedidos. Não transforme suposição em fato nem pendência em resultado.
- Antes de dar algo por pronto, rode os testes relevantes (`sh tests/rodar.sh`) e, para correções, reproduza a falha antes e mostre a regressão passando depois.
- Continue o trabalho autorizado; pergunte só o essencial. Publicar, mandar mensagem a terceiros, custos e ações destrutivas pedem autorização específica.
- Respostas a perguntas de andamento não interrompem o trabalho. Um bloqueio para só o que depende dele.

## Git e publicação

- Trabalhe no branch da sessão, faça commits em português com descrição detalhada e suba só esse branch. Depois pergunte: "Quer que eu junte no main?". Só com o "sim" crie o PR e faça o merge (squash).
- Depois de juntar no main, lembre o dono de tocar em **Implantar** no Easypanel e, se o app mudou, de atualizar o app nos tablets.
- Nunca suba: a chave de assinatura do app (`*.jks` e a senha), notas fiscais reais de clientes, `.env`, `.hostinger/token`. O repositório é público.
- Testes nunca acessam produção (operacoes.fabbiol.tech). Use só o banco e o servidor temporários de `tests/`.
- Não adicione ganchos de Git, bloqueios de push nem exigência de branch.

## Documentos e memória

- `PRODUCT.md` é dono do escopo; `docs/architecture.md`, da estrutura e das decisões; `docs/design.md`, das telas; `docs/onboarding.md`, da preparação; `docs/quality.md`, das checagens. O [README.md](README.md) é o guia de uso do dono. Atualize o documento responsável junto com a mudança, sem repetir regras.
- Mudanças que o dono vê entram no [CHANGELOG.md](CHANGELOG.md), com a seção "Ao publicar esta versão".
- Lição durável (algo que uma sessão futura gostaria de saber antes de começar) vai para `.claude/memory/`, com fonte, data da verificação e quando deixa de valer. Progresso temporário não vira memória.

## Skills e ferramentas

- Úteis aqui: `differential-review` (revisão de segurança de uma mudança), `web-design-guidelines` (telas e acessibilidade), `writing-guidelines` (textos e documentos), `find-skills` (achar uma skill que falta).
- Instaladas por escolha do dono, mas **não se aplicam** a este sistema (PHP sem framework, MySQL, Easypanel): as de React, Next.js, React Native, Vercel (inclusive `deploy-to-vercel` e `vercel-cli-with-tokens`), PostgreSQL/Supabase, shadcn e `gh-fix-ci` (só para o Codex, e o projeto não tem CI). Não use. Licenças e origens: [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md). "Publicar" aqui é juntar no `main` e o dono tocar em **Implantar** no Easypanel.
- Plugins (Superpowers, Ponytail, Caveman, Context7) e ferramentas globais (RTK, Graphify) ainda não estão instalados: veja as pendências em [docs/setup.md](docs/setup.md). Confira se a ferramenta existe antes de contar com ela.
- Revisão de segurança reutilizável: veja [docs/quality.md](docs/quality.md#revisão-de-segurança). Relatórios ficam fora do repositório.

## Delegação e revisão

- Delegue só quando isolar contexto, especialização ou trabalho paralelo independente justificarem. Para busca e inventário, prefira um modelo mais leve; para segurança, dados, concorrência e revisão crítica, capacidade maior.
- Cada tarefa delegada recebe objetivo, arquivos permitidos, recursos (porta, banco), critérios de aceite e checagens. Um escritor por arquivo; quem coordena cuida de Git, configuração compartilhada e integração. Revisores só leem.
- Confira o diff e rode as checagens antes de aceitar o trabalho de um ajudante. Revisão proporcional ao risco; termine quando os achados relevantes estiverem resolvidos ou decididos e as checagens passarem.
