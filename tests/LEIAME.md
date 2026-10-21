# Testes automáticos

Tudo roda com um comando, em qualquer sessão da nuvem (ou no seu computador), sem tocar no banco de verdade nem em produção.

```sh
sh tests/preparar.sh        # uma vez por contêiner novo: instala o que falta, liga o MariaDB e cria o usuário de teste
sh tests/rodar.sh rapido    # sintaxe + testes PHP sem banco (segundos; não precisa de MariaDB nem de navegador)
sh tests/rodar.sh           # tudo: rápido + integração (banco temporário) + telas (navegador)
```

Para rodar só uma parte: `sh tests/rodar.sh integracao`, `sh tests/rodar.sh telas`, ou o nome (ou pedaço do nome) de um teste,
com ou sem o grupo na frente: `sh tests/rodar.sh telas patio`, `sh tests/rodar.sh fracionado valor`.
`sh tests/rodar.sh limpar` apaga restos de uma rodada interrompida à força (bancos `op_t_*`, servidores nas portas 8170–8199, pastas temporárias). Ele apaga **todos** esses recursos, inclusive os de outra rodada que esteja rodando ao mesmo tempo, e sempre olha a faixa 8170–8199, mesmo com `TESTE_PORTA_INICIO`/`TESTE_PORTA_FIM`: só use quando nenhuma rodada estiver em andamento.

## O que o `rodar.sh` mostra

Cada teste sai como **OK**, **FALHA** ou **PULADO**; no PULADO vem o motivo (por exemplo, "MariaDB não responde em 127.0.0.1:3307 — rode sh tests/preparar.sh").
No fim há um resumo com as falhas e os pulados. Teste pulado **não conta como passou**: aparece no resumo e o fecho diz "passou, mas com suites PULADAS". Uma parte pulada dentro de um teste que passou (por exemplo, o caso da fila que atravessou a noite, que só roda depois das 07h) aparece em "PARTES PULADAS" e o fecho diz "passou, mas com partes PULADAS dentro de testes".
O código de saída é 1 se algo FALHOU. Com `TESTE_ESTRITO=1`, teste ou parte pulada também vira erro (para usar em automação).
Falhou? As linhas `FALHA` e o fim da saída aparecem na hora. Para ver a saída inteira dos que passaram: `TESTE_VERBOSO=1 sh tests/rodar.sh telas patio`.

## Pré-requisitos

| Preciso de | Para | Como resolver |
| --- | --- | --- |
| PHP 8.3, git | tudo | já vêm na nuvem |
| Node 18+ | sintaxe de JS, testes HTTP e de tela | já vem na nuvem |
| MariaDB em 127.0.0.1:3307 + usuário `operacoes_teste` | integração e telas | `sh tests/preparar.sh` |
| curl | integração e telas | `sh tests/preparar.sh` |
| `pdftotext` (poppler-utils) | leitura do PDF da nota | `sh tests/preparar.sh` |
| extensão `gd` do PHP | teste de fotos (sem ela, fica PULADO) | `apt-get install php-gd` |
| Playwright + Chromium | telas | na nuvem: `/opt/node-tools/node_modules/playwright` e `/opt/pw-browsers`. Fora dela: `npm i -g playwright && npx playwright install chromium` |

O Playwright é procurado como `require('playwright')` (respeita `NODE_PATH`), depois em `/opt/node-tools/node_modules/playwright`; `TESTE_PLAYWRIGHT` aponta outro lugar.
O `preparar.sh` pode rodar várias vezes: o que já está pronto não muda. Só instala pacotes (apt-get) se for root; sem root, diz o que instalar. Ele **não** instala navegador.

## Variáveis de ambiente

| Variável | Para que serve | Padrão |
| --- | --- | --- |
| `NOTA_REAL_DIR` | pasta com a nota real de um cliente (`nota.pdf` e `real.xml`) para o teste `telas/nota_real`. **Nunca** coloque essas notas no repositório. Sem ela, esse teste fica PULADO | — |
| `TESTE_DB_HOST`, `TESTE_DB_PORTA` | onde está o MariaDB de teste | `127.0.0.1`, `3307` |
| `TESTE_DB_USUARIO`, `TESTE_DB_SENHA` | usuário de teste (senha falsa, só deste banco local) | `operacoes_teste`, `teste_local` |
| `TESTE_DB_ADMIN` | (só `preparar.sh`) comando do cliente com acesso de administrador | `mysql -uroot` |
| `TESTE_SEM_APT=1` | (só `preparar.sh`) não instala nada, só mostra o que faria | — |
| `TESTE_ESTRITO=1` | teste ou parte PULADA também faz o `rodar.sh` sair com erro | — |
| `TESTE_VERBOSO=1` | mostra a saída completa dos testes que passaram | — |
| `TESTE_TEMPO_MAXIMO` | segundos que cada teste pode levar | `420` |
| `TESTE_CAPTURAS` | pasta onde os testes de tela salvam capturas de tela (PNG). Sem ela, não salvam | — |
| `TESTE_PORTA_INICIO`, `TESTE_PORTA_FIM` | faixa de portas dos servidores temporários | `8170`, `8199` |

Para ver o que o `rodar.sh` faz quando o banco não está no ar, aponte para uma porta sem ninguém: `TESTE_DB_PORTA=3999 sh tests/rodar.sh` (os testes de banco saem PULADOS com o motivo; os rápidos passam).

## O que cada grupo cobre

**sintaxe** — `php -l` em todo `.php` versionado, `node --check` em todo `.js`, `sh -n` em todo `.sh` (a lista vem de `git ls-files`, mais os arquivos novos de `tests/`).

**unitarios** (`tests/unitarios/`, PHP puro, sem banco)
- `leitura_nota_xml` — regra de unidades e datas na leitura do XML da nota (soma só toneladas, unidade desconhecida, leiaute antigo só com a data).

**integracao** (`tests/integracao/`, banco temporário; PHP direto no código ou HTTP puro)
- `lavra` — relatório da lavra: médias de fila, estrada, volta e ciclo; garagem, oficina, fila que atravessa a noite, desconto da limpeza da escavadeira.
- `finalizar_corrigir_horimetro` — quem pode finalizar a carga, correção do carregado (soma das viagens do fracionado), correção da entrada do horímetro.
- `posse` — posse das máquinas (pá, retro, caminhão): quem está com quê, liberação pelo administrativo, dois toques ao mesmo tempo, sessão antiga que vira posse no banco.
- `varrer_telas` — abre as telas principais com cada papel (administrativo, operadora, motorista, "ambos") e procura erro do PHP ou resposta 5xx.
- `trocar_nome` — trocar o nome de uma pessoa leva o histórico junto; pessoa de `APP_USUARIOS` não troca.
- `entrada_por_foto` — entrada pela foto no tablet autorizado, senha, limite de tentativas, tablet revogado, aparelho não autorizado.
- `fotos` — envio de fotos: redução, giro pelo EXIF, PNG com fundo branco, arquivo quebrado, cache 304 (as imagens são geradas na hora).

**telas** (`tests/telas/`, Chromium pelo Playwright contra o servidor temporário)
- `patio` — pátio de ponta a ponta: nota só com data, correção do carregado (fracionado e saldo), observações, avisos no tablet, viagem que volta para Saldo a buscar.
- `fracionado` — nota em várias viagens: quanto levou em cada uma, saldo, vendido do dia, liberar viagem, encerrar saldo.
- `aviso_patio` — quadro de aviso no tablet da pá (nova, alterada, cancelada) e som/vibração no app.
- `limpeza_lavra` — limpeza da lavra: tempo fora da fila e do ciclo, Painel, Monitor, quem pode terminar.
- `problema_maquina` — "Como ficou a máquina?" ao soltar, Painel, Manutenção, ficha, resolver, liberar com problema.
- `lavra_encerrar_horimetro` — encerrar viagem como problema, Oficina, horímetro com ponto de milhar, entrada digitada errada.
- `valor_nota` — nota sem valor no Monitor e no Controle; preencher e corrigir o valor.
- `nota_leitura`, `nota_m3`, `nota_unidade_desconhecida` — leitura de XML e PDF (notas **sintéticas** de `tests/notas/`), nota repetida, material fora do cadastro, m³, unidades desconhecidas.
- `nota_real` — a mesma leitura com a nota real do cliente (só com `NOTA_REAL_DIR`; nada dela está escrito nos testes).
- `tv_tela_cheia`, `tv_toque`, `tv_tamanhos` — Monitor da TV em tela cheia, com mouse e com toque, e em vários tamanhos de televisão.
- `som_aviso` — som do aviso sem AudioContext (não precisa de servidor).
- `app_android` — página /app, faixa "Baixar o app" no Chrome do tablet autorizado, e o pedido de tela acesa ao Android dentro do app.
- `app_tablet` — serviço do app no Chrome (manifesto), tela sempre acesa onde há máquina, tela "Sem conexão" sem sinal ou com o servidor fora, volta sozinha. Derruba e religa o servidor do ambiente.
- `app_atualizacao` — atualização automática da lavra direto no servidor, ação normal com o app e faixa de conexão ao cair o sinal.

## Garantias de isolamento

- Cada teste de integração ou de tela roda no **próprio ambiente**: pasta temporária (`op-teste-*`) com cópia de `public/`, `src/`, `views/` e `database/` e um `storage/` vazio; banco novo `op_t_<nome>_<sorteio>` com os dados de `tests/lib/seed.sql`; servidor `php -S` numa porta livre de 8170 a 8199.
- Os testes usam só o usuário `operacoes_teste`, que existe só em `127.0.0.1` e só enxerga bancos que começam com `op_t_`. Eles não conseguem tocar o banco real nem outro `op_*`. Nenhum teste fala com produção.
- Ao terminar (bem, mal ou com Ctrl+C), o runner mata o servidor, apaga o banco e remove a pasta. Rodar duas vezes seguidas não deixa `op_t_*`, servidor nas portas 8170–8199 nem pastas temporárias.
- As pessoas e senhas dos testes (`Fabio`, `Maria`, `Pedro`, `Joao`, `Ana`) são falsas e só existem nesses servidores temporários. As notas de `tests/notas/` são sintéticas (CNPJ fictício; `gerar.py` as recria com `brazilfiscalreport`). Nota real de cliente nunca entra aqui.

## Detalhes que pegam desprevenido

- O app recusa envio de formulário rápido demais: os testes esperam 1,3 s antes de enviar. Não tire essa pausa.
- O app grava horas em Cuiaba (UTC-4) e o cliente `mysql` usa UTC. O runner abre toda conexão de teste com `SET time_zone='-04:00'`; no SQL do teste, `NOW()` já vem no horário do app.
- O horímetro recusa horas que não cabem no tempo passado: os testes "envelhecem" a leitura de entrada em 12 h (`envelhecer` em `lib/teste.js`).
- Teste novo: um arquivo em `tests/integracao/` (`.php` ou `.js`) ou `tests/telas/` (`.js`) já entra no runner. Use `lib/cli.php` (PHP) ou `lib/teste.js` e `lib/http.js` (Node). Saída `OK ...`/`FALHA ...` e código de saída diferente de 0 se falhar; saia com 77 (`T.pular('motivo')`) para pular. Se o teste não precisa de servidor nem banco, ponha `@sem-ambiente` nas primeiras linhas.

## Não portados

- `escavadeira` (fases A, B e C, das sessões antigas): testa a migração das funções (pá, escavadeira, motorista) entre duas versões do código: roda o código antigo, guarda as sessões e depois roda o novo sobre o mesmo banco. Não cabe no modelo de um ambiente novo por teste; só faria sentido numa próxima migração, com a versão antiga tirada do Git.
- `janela` e as capturas de tela soltas (`foto`, `rostos`, `instalar`, `semconexao`): só tiravam fotos de tela para olhar, sem conferência automática. As capturas dos testes de tela agora saem com `TESTE_CAPTURAS`.
- `real_apache` (a mesma conferência da nota real contra o Apache do Docker): este runner só usa o servidor embutido do PHP.
- Em `tv_tamanhos` (antes `foto.js`) a conferência "tudo cabe na tela, sem rolagem" é nova: o script antigo só imprimia as medidas e guardava capturas.
