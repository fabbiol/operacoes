# Avisos de terceiros

As skills em `.agents/skills/` e `.claude/skills/` são cópias sem alteração de projetos de terceiros, instaladas com o `skills` CLI. A origem exata e o hash de cada uma estão em `skills-lock.json`. Elas não fazem parte do sistema da Próspera e não vão para o servidor.

| Skill | Origem | Autor | Licença |
|---|---|---|---|
| `vercel-react-best-practices`, `vercel-composition-patterns`, `web-design-guidelines`, `writing-guidelines`, `vercel-react-view-transitions`, `vercel-optimize`, `deploy-to-vercel`, `vercel-cli-with-tokens`, `vercel-react-native-skills` | https://github.com/vercel-labs/agent-skills | Vercel, Inc. | MIT |
| `find-skills` | https://github.com/vercel-labs/skills | Vercel, Inc. (Copyright (c) 2026) | MIT |
| `next-dev-loop`, `next-bundle-optimizer` | https://github.com/vercel/next.js (pasta `skills/`) | Vercel, Inc. (Copyright (c) 2025) | MIT |
| `supabase-postgres-best-practices` | https://github.com/supabase/agent-skills | Supabase (Copyright (c) 2026) | MIT |
| `shadcn` | https://github.com/shadcn/ui | shadcn (Copyright (c) 2023) | MIT |
| `gh-fix-ci` | https://github.com/openai/skills (`skills/.curated/gh-fix-ci`) | OpenAI | Apache 2.0; o texto completo vai junto, em `.agents/skills/gh-fix-ci/LICENSE.txt` |
| `differential-review` | https://github.com/trailofbits/skills (`plugins/differential-review`) | Trail of Bits | CC BY-SA 4.0 |

## differential-review (CC BY-SA 4.0)

"differential-review", de Trail of Bits (https://github.com/trailofbits/skills), sob a licença Creative Commons Atribuição-CompartilhaIgual 4.0 Internacional (https://creativecommons.org/licenses/by-sa/4.0/). Copiada sem alterações. As cópias neste repositório continuam sob a mesma licença.

## Licença MIT

Vale para as skills marcadas como MIT na tabela, com o aviso de copyright do autor indicado em cada linha.

```text
Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

Ao atualizar as skills (`npx --yes skills@latest update --project --yes`), confira se a origem ou a licença mudou e atualize este arquivo.
