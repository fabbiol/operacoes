# Memória do projeto

Lições que uma sessão nova gostaria de saber antes de começar e que não ficam óbvias lendo o código. Este índice é carregado em toda sessão do Claude Code pelo `CLAUDE.md`; o detalhe de cada lição fica no arquivo do tópico.

Regras: uma linha por lição; cada tópico tem `name`, `description`, `metadata.type` (feedback, architecture, business-rule ou reference), `source`, `evidence`, `last_verified` e `invalidate_when`. Não guarde o que o código ou o Git já mostram, nem progresso temporário, nem segredos. Lição que deixou de valer é atualizada ou removida. Limite: cerca de 130 linhas neste índice.

- [Vocabulário da pedreira](vocabulario.md) — "Pusch" é o caminhão levando material da Lavra ao Britador; "retro" no código é a escavadeira hidráulica.
- [Como falar com o dono](dono.md) — dono não técnico: português simples, perguntar antes de juntar no main, lembrar do Implantar.
- [Fuso horário nos testes](fuso-horario.md) — app em UTC-4 (America/Cuiaba); o cliente `mysql` usa UTC: use `SET time_zone='-04:00'` no SQL de teste.
- [Toque repetido e horímetro nos testes](testes-de-tela.md) — esperar 1,3 s antes de enviar formulário; envelhecer leituras do horímetro em 12 h.
- [Tipos do PDO](pdo-tipos.md) — com prepares nativos, colunas inteiras voltam como `int`: compare com conversão.
- [Atualização do banco](migracoes.md) — `schema.sql` + `garantir*()` no primeiro acesso; trocar `Db::MIGRACOES`; preencher coluna sem mexer em `atualizado_em`.
- [Publicação pela Hostinger](deploy-hostinger.md) — as telas sobem antes do `index.php`: tela que usa função nova confere `function_exists`.
- [Chave do app Android](app-android.md) — a chave de assinatura fica só com o dono; sem ela não dá para atualizar o app por cima.
