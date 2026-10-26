# Código e arquitetura

## Stack real

- PHP 8.3 (`declare(strict_types=1)` em todo arquivo), sem framework e sem Composer. Extensões usadas: `pdo_mysql`, `gd`, `exif`; o programa `pdftotext` (poppler-utils) lê o PDF da nota fiscal.
- MySQL 8.4 em produção (imagem `mysql:8.4` no `docker-compose.yml`); MariaDB 10.11 nos testes. Acesso por PDO com prepares nativos.
- Servidor: Apache com `mod_rewrite` na imagem `php:8.3-apache` (`Dockerfile`, `docker/`). Para testes e desenvolvimento rápido, `php -S ... -t public public/router.php`.
- Navegador: HTML renderizado no servidor, CSS próprio e JavaScript puro, sem build. Service worker (`public/sw.js`) só para o aviso de "Sem conexão".
- App Android: uma `Activity` com WebView em `android/src/br/com/prospera/operacoes/Principal.java`, montada sem Android Studio por `android/construir.sh`.

## Mapa do código

```text
public/            raiz da web (DocumentRoot)
  index.php        controlador único: despachar() lê a rota e chama as funções tela_*()
  router.php       roteador para o servidor embutido do PHP (php -S)
  assets/          app.css, dashboard.css e o JS das telas (fluxo, avisos, envio, tempo, tablet, dashboard)
  baixar/          APK do app Android
src/               classes de domínio, uma por assunto
  bootstrap.php    carrega .env, fuso, sessão (storage/sessoes) e as classes
  helpers.php      funções comuns: escape, CSRF, flash, redirect, render, horímetro ao soltar, toque repetido
  Db.php           conexão PDO, trava (GET_LOCK) e atualização automática do banco
  Auth.php         entrada, papéis, bloqueio por senhas erradas
  Operacoes.php    fila de carregamento do pátio, fracionado, vendido e Controle de carga
  Lavra.php        ciclo do Pusch, médias, limpeza da lavra, resumo
  Horimetro.php    leituras, turnos, manutenção preventiva (troca registrada pelo operador em /troca e avisos de 100, 50, 25 e 8 h)
  Posse.php        quem está com cada máquina
  Problemas.php    problemas das máquinas
  Pecas.php        peças das máquinas (até 5 fotos em peca_fotos) e pedidos ao Compras (mensagem do WhatsApp, página pública /compras/<código>)
  Oficina.php      máquina na oficina (bloqueia assumir e começar trabalho), ordem de serviço e disponibilidade do mês
  Estoque.php      estoque das pilhas (conchas lançadas pela pá)
  Britador.php     britador secundário: britagens e problemas
  NotaFiscal.php   leitura da NF-e (XML e PDF) e o PDF guardado com a ordem (storage/notas/)
  FinPro.php       envio das notas Pix e Transferência ao Fin Pro (fila finpro_envios, cURL com prazo de 8 s)
  Dashboard.php    dados do Monitor da TV
  Cadastro.php, Pessoas.php, Fotos.php, Tablets.php
views/             templates PHP; trecho_*.php são pedaços reaproveitados
database/schema.sql  tabelas (CREATE TABLE IF NOT EXISTS)
docker/            configuração do Apache, php.ini e entrada do contêiner
android/           código e script de montagem do app
tests/             testes automáticos e preparação da sessão (ver docs/quality.md)
deploy.ps1         publicação na Hostinger (caminho secundário)
```

Tamanho em linhas (19/10/2026): `public/index.php` 2.177, `src/Lavra.php` 1.244, `src/Operacoes.php` 1.217, `src/helpers.php` 1.008, `public/assets/app.css` 897. Os demais arquivos têm menos de 700. A referência do toolkit é 350 linhas; dividir os maiores é uma proposta registrada em [setup.md](setup.md), não uma regra imposta.

## Fronteiras e fluxo

1. A requisição chega em `public/index.php`, que chama `despachar($path, $method)`.
2. Algumas rotas funcionam sem login (`login`, `health`, `app`, foto da entrada e `compras/<código>`, a página do pedido de peças que só abre com o código secreto de 32 caracteres). As outras exigem login. Antes da linha `exigir_admin();` (por volta da linha 458), cada rota confere o próprio acesso: `exigir_papel(...)` para operador, motorista ou escavadeira, `exigir_cadastro(...)` nas fichas das máquinas e `exigir_admin()` em rotas pontuais. Quem está no campo e não assumiu máquina é mandado para `/maquina` antes (`exigir_maquina()`). Depois dessa linha, tudo é só do administrativo. Rota nova precisa da sua checagem ou ficar depois da linha.
3. Todo POST chama `csrf_check()`. Envios repetidos (toque duplo) são barrados por `TOQUE_REPETIDO_MS` em `src/helpers.php` e por `public/assets/envio.js`.
4. As funções `tela_*()` validam a entrada e chamam as classes de `src/`. As classes lançam `InvalidArgumentException` com a mensagem para o usuário; a tela mostra o erro ao lado do formulário ou num aviso (`flash_set`).
5. As classes falam com o banco por `Db::pdo()`, sempre com consultas preparadas. Checagens do tipo "a máquina está livre?" seguidas de gravação usam `Db::comTrava()` ou condições no próprio `UPDATE`.
6. `render()` monta a view dentro de `views/layout.php`. Todo texto vindo do banco passa por `e()`.
7. Telas que se atualizam sozinhas (pátio, lavra, Painel) usam `public/assets/fluxo.js`: a página consulta uma "marca" do estado e troca o bloco `#fluxo` quando ela muda. A TV usa o JSON de `/dashboard/dados`.

## Dados e migrações

- O esquema está em `database/schema.sql`. As colunas acrescentadas depois que a tabela já existia são criadas pelas funções `garantir*()` de `src/Db.php`.
- No primeiro acesso depois de uma publicação, `Db::migrar()` roda o schema e as `garantir*()`, protegido por `GET_LOCK`. A marca da versão fica na tabela `schema_versao` (linha 1: hash do schema + `Db::MIGRACOES`; linha 2: a planilha do estoque já foi tratada e não volta). Ao criar ou mudar uma `garantir*()`, troque `Db::MIGRACOES`.
- As `garantir*()` precisam poder rodar mais de uma vez, e o código precisa aceitar linhas antigas (colunas novas com NULL ou valor padrão).
- Arquivos fora do banco: fotos em `storage/fotos/`, PDFs das notas em `storage/notas/` (os lidos e ainda não lançados em `storage/notas/pendentes/`, apagados depois de um dia) e sessões em `storage/sessoes/`. Em produção ficam num volume do Docker.
- Fuso: PHP em `America/Cuiaba` e conexão MySQL com `time_zone = '-04:00'`.

## Decisões

| Decisão | Motivo | Alternativa | Rever quando |
|---|---|---|---|
| Sem framework e sem Composer | Sistema pequeno, publicado também em hospedagem compartilhada; menos peças para o dono manter | Laravel/Slim com Composer | Se a hospedagem compartilhada sair e o código crescer muito |
| Migração automática no primeiro acesso | O dono só toca em **Implantar**; não há etapa manual | Ferramenta de migração rodada no deploy | Se houver mudança de dados destrutiva ou demorada |
| Marca da migração na tabela `schema_versao` (out/2026) | Banco novo ou zerado é montado de novo mesmo com a pasta `storage` antiga | Arquivo em `storage/` (usado até out/2026) | — |
| Fila do Fin Pro tentada nas consultas do Monitor e do Painel (out/2026) | Sem cron nem processo a mais no contêiner; o envio na hora resolve quase tudo | Cron no contêiner ou fila externa | Se as notas passarem a esperar demais sem ninguém com o Monitor ou o Painel aberto |
| App Android como WebView | As telas vêm do sistema; o app só cuida de som, tela acesa e arquivos | App nativo | Se precisar funcionar sem sinal |
| Testes em `tests/` sem dependência no projeto | Rodam na nuvem com o PHP, o MariaDB e o Playwright do ambiente | PHPUnit via Composer | Se o projeto adotar Composer |

## Manutenção

Atualize este mapa quando mudarem diretórios, dependências relevantes, contratos ou fronteiras. Registre o comportamento verificado e remova decisões que deixaram de valer.
