-- Fila de carregamento do pátio.
-- Quem carrega no pátio é a pá. Na lavra, a retroescavadeira carrega o caminhão que segue até o britador.

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(40) NOT NULL,
    nome_completo VARCHAR(120) NULL,
    senha_hash VARCHAR(255) NOT NULL,
    administrador TINYINT(1) NOT NULL DEFAULT 0,
    papel VARCHAR(20) NOT NULL DEFAULT 'operador',
    motorista TINYINT(1) NOT NULL DEFAULT 0,
    operador TINYINT(1) NOT NULL DEFAULT 0,
    escavadeira TINYINT(1) NOT NULL DEFAULT 0,
    britador TINYINT(1) NOT NULL DEFAULT 0,
    foto VARCHAR(80) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_usuario_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS materiais (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    material VARCHAR(120) NOT NULL,
    observacoes TEXT NULL,
    estoque TINYINT(1) NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_material (material)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pas_carregadeiras (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    maquina VARCHAR(120) NOT NULL,
    marca VARCHAR(80) NULL,
    modelo VARCHAR(80) NULL,
    ano SMALLINT UNSIGNED NULL,
    concha_m3 DECIMAL(5,2) NULL,
    foto VARCHAR(80) NULL,
    observacoes TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_pa_maquina (maquina)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Material que a pá colocou na pilha do estoque, contado em conchas.
-- A concha fica gravada como era no lançamento: mudar a concha da pá depois não altera o que já entrou.
CREATE TABLE IF NOT EXISTS estoque_entradas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    material_id INT UNSIGNED NOT NULL,
    pa_carregadeira_id INT UNSIGNED NOT NULL,
    conchas SMALLINT UNSIGNED NOT NULL,
    concha_m3 DECIMAL(5,2) NOT NULL,
    colocado_em DATETIME NOT NULL,
    usuario VARCHAR(40) NOT NULL,
    observacoes VARCHAR(200) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_estoque_material (material_id, colocado_em),
    KEY idx_estoque_quando (colocado_em),
    CONSTRAINT fk_estoque_material FOREIGN KEY (material_id) REFERENCES materiais (id),
    CONSTRAINT fk_estoque_pa FOREIGN KEY (pa_carregadeira_id) REFERENCES pas_carregadeiras (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fila_carregamentos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ordem VARCHAR(20) NOT NULL,
    data DATETIME NOT NULL,
    cliente VARCHAR(160) NOT NULL,
    pedido VARCHAR(60) NOT NULL,
    nf VARCHAR(60) NOT NULL,
    nf_emitida_em DATETIME NULL,
    nf_emitida_data DATE NULL,
    nf_chave CHAR(44) NULL,
    nf_valor DECIMAL(12,2) NULL,
    nf_pagamento VARCHAR(16) NULL,
    nf_arquivo VARCHAR(80) NULL,
    nf_documento VARCHAR(14) NULL,
    -- Venda paga depois (prefeitura: transferência em 15 ou 30 dias): o dia em que o pagamento é esperado.
    nf_previsto DATE NULL,
    material_id INT UNSIGNED NOT NULL,
    quantidade_autorizada DECIMAL(12,2) NOT NULL,
    unidade ENUM('t', 'm3', 'un') NOT NULL,
    fracionado TINYINT(1) NOT NULL DEFAULT 0,
    ordem_principal_id INT UNSIGNED NULL,
    viagem SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    quantidade_carregada DECIMAL(12,2) NULL,
    pa_carregadeira_id INT UNSIGNED NULL,
    retroescavadeira_id INT UNSIGNED NULL,
    operador VARCHAR(120) NULL,
    status ENUM(
        'aguardando_autorizacao',
        'liberado',
        'carregando',
        'aguardando_conferencia',
        'concluido',
        'cancelado',
        'saldo'
    ) NOT NULL DEFAULT 'aguardando_autorizacao',
    autorizado_por VARCHAR(120) NULL,
    autorizado_em DATETIME NULL,
    inicio_carregamento DATETIME NULL,
    fim_carregamento DATETIME NULL,
    observacoes TEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_fila_ordem (ordem),
    KEY idx_fila_status (status),
    KEY idx_fila_data (data),
    KEY idx_fila_nf_chave (nf_chave),
    KEY idx_fila_principal (ordem_principal_id),
    CONSTRAINT fk_fila_material FOREIGN KEY (material_id) REFERENCES materiais (id),
    CONSTRAINT fk_fila_pa FOREIGN KEY (pa_carregadeira_id) REFERENCES pas_carregadeiras (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nota paga em mais de uma forma (Pix + cartão, dinheiro + Pix...): uma linha por forma, com o valor de cada uma;
-- a soma é o valor da nota. fila_carregamentos.nf_pagamento fica com a primeira forma. Nota de uma forma só não tem linha aqui.
CREATE TABLE IF NOT EXISTS nota_pagamentos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ordem_id INT UNSIGNED NOT NULL,
    posicao TINYINT UNSIGNED NOT NULL,
    forma VARCHAR(16) NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_nota_pagamentos (ordem_id, posicao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Carga com mais de um material na mesma nota (sai inteira, nunca fracionada): o primeiro material fica em
-- fila_carregamentos.material_id/quantidade_autorizada; os outros (até 4), aqui, na mesma unidade da ordem.
CREATE TABLE IF NOT EXISTS ordem_materiais (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ordem_id INT UNSIGNED NOT NULL,
    posicao TINYINT UNSIGNED NOT NULL,
    material_id INT UNSIGNED NOT NULL,
    quantidade DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_ordem_materiais (ordem_id, posicao),
    CONSTRAINT fk_ordem_materiais_material FOREIGN KEY (material_id) REFERENCES materiais (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS retroescavadeiras (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    maquina VARCHAR(120) NOT NULL,
    marca VARCHAR(80) NULL,
    modelo VARCHAR(80) NULL,
    ano SMALLINT UNSIGNED NULL,
    foto VARCHAR(80) NULL,
    observacoes TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_retro_maquina (maquina)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS caminhoes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    placa VARCHAR(20) NOT NULL,
    marca VARCHAR(80) NULL,
    modelo VARCHAR(80) NULL,
    ano SMALLINT UNSIGNED NULL,
    capacidade_t DECIMAL(8,2) NULL,
    foto VARCHAR(80) NULL,
    observacoes TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_caminhao_placa (placa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estrada (
    id TINYINT UNSIGNED NOT NULL,
    ida_km DECIMAL(6,2) NULL,
    retorno_km DECIMAL(6,2) NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ciclos_lavra (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    caminhao_id INT UNSIGNED NOT NULL,
    status ENUM('na_fila', 'carregando', 'em_viagem', 'no_britador', 'retornando', 'concluido', 'cancelado') NOT NULL DEFAULT 'na_fila',
    inicio_fila DATETIME NOT NULL,
    inicio_carga DATETIME NULL,
    fim_carga DATETIME NULL,
    chegada_britador DATETIME NULL,
    inicio_retorno DATETIME NULL,
    fim_ciclo DATETIME NULL,
    operador VARCHAR(120) NULL,
    retroescavadeira_id INT UNSIGNED NULL,
    operador_carga VARCHAR(120) NULL,
    capacidade_t DECIMAL(8,2) NULL,
    encerramento VARCHAR(160) NULL,
    encerramento_ciente_em DATETIME NULL,
    encerramento_ciente_por VARCHAR(120) NULL,
    garagem TINYINT(1) NOT NULL DEFAULT 0,
    observacoes TEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ciclo_status (status),
    KEY idx_ciclo_caminhao (caminhao_id, status),
    KEY idx_ciclo_fim (fim_ciclo),
    CONSTRAINT fk_ciclo_caminhao FOREIGN KEY (caminhao_id) REFERENCES caminhoes (id),
    CONSTRAINT fk_ciclo_retro FOREIGN KEY (retroescavadeira_id) REFERENCES retroescavadeiras (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS horimetros (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo ENUM('pa', 'retro', 'caminhao') NOT NULL,
    maquina_id INT UNSIGNED NOT NULL,
    leitura DECIMAL(10,1) NOT NULL,
    lido_em DATETIME NOT NULL,
    usuario VARCHAR(40) NOT NULL,
    anulado_em DATETIME NULL,
    anulado_por VARCHAR(40) NULL,
    anulado_motivo VARCHAR(200) NULL,
    PRIMARY KEY (id),
    KEY idx_horimetro_maquina (tipo, maquina_id, leitura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS manutencao_planos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo ENUM('pa', 'retro', 'caminhao') NOT NULL,
    maquina_id INT UNSIGNED NOT NULL,
    nome VARCHAR(120) NOT NULL,
    intervalo_h DECIMAL(10,1) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_plano_maquina (tipo, maquina_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS manutencoes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    plano_id INT UNSIGNED NOT NULL,
    horimetro DECIMAL(10,1) NOT NULL,
    feito_em DATETIME NOT NULL,
    usuario VARCHAR(40) NOT NULL,
    observacoes TEXT NULL,
    PRIMARY KEY (id),
    KEY idx_manutencao_plano (plano_id, feito_em),
    CONSTRAINT fk_manutencao_plano FOREIGN KEY (plano_id) REFERENCES manutencao_planos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS horimetro_turnos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo ENUM('pa', 'retro', 'caminhao') NOT NULL,
    maquina_id INT UNSIGNED NOT NULL,
    usuario VARCHAR(40) NOT NULL,
    entrada DECIMAL(10,1) NOT NULL,
    saida DECIMAL(10,1) NOT NULL,
    horas DECIMAL(10,1) NOT NULL,
    maquina_ok TINYINT(1) NULL,
    iniciado_em DATETIME NULL,
    encerrado_em DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_turno_usuario (usuario, encerrado_em),
    KEY idx_turno_maquina (tipo, maquina_id, encerrado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limpeza da lavra: a escavadeira para de carregar para limpar a lavra. O tempo que o caminhão
-- espera na fila por causa dela não entra nas médias de fila e de ciclo.
CREATE TABLE IF NOT EXISTS lavra_limpezas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    retroescavadeira_id INT UNSIGNED NOT NULL,
    operador VARCHAR(120) NOT NULL,
    inicio DATETIME NOT NULL,
    fim DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_limpeza_retro (retroescavadeira_id, inicio),
    KEY idx_limpeza_aberta (fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Problemas das máquinas: o operador conta ao soltar a máquina, ou o administrativo registra na ficha.
-- Ficam abertos até alguém marcar como resolvido.
CREATE TABLE IF NOT EXISTS maquina_problemas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo ENUM('pa', 'retro', 'caminhao') NOT NULL,
    maquina_id INT UNSIGNED NOT NULL,
    usuario VARCHAR(40) NOT NULL,
    descricao VARCHAR(500) NOT NULL,
    horimetro DECIMAL(10,1) NULL,
    relatado_em DATETIME NOT NULL,
    resolvido_em DATETIME NULL,
    resolvido_por VARCHAR(40) NULL,
    resolucao VARCHAR(500) NULL,
    PRIMARY KEY (id),
    KEY idx_problema_maquina (tipo, maquina_id, resolvido_em),
    KEY idx_problema_aberto (resolvido_em, relatado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tablets que mostram as fotos da equipe na entrada. O aparelho guarda o código e aqui fica só o hash.
CREATE TABLE IF NOT EXISTS tablets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(60) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    autorizado_por VARCHAR(40) NOT NULL,
    criado_em DATETIME NOT NULL,
    ultimo_uso DATETIME NULL,
    revogado_em DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_tablet_token (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Senhas erradas por usuário e endereço. Ficam no banco para o bloqueio valer mesmo sem o cookie.
CREATE TABLE IF NOT EXISTS login_falhas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario VARCHAR(40) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    criado_em DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_falha_usuario (usuario, ip, criado_em),
    KEY idx_falha_ip (ip, criado_em),
    KEY idx_falha_quando (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quem está com cada máquina agora. Uma linha por máquina e por pessoa; some quando a máquina é solta.
CREATE TABLE IF NOT EXISTS maquina_posse (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo ENUM('pa', 'retro', 'caminhao') NOT NULL,
    maquina_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    usuario VARCHAR(40) NOT NULL,
    entrada DECIMAL(10,1) NULL,
    entrada_id INT UNSIGNED NULL,
    iniciado_em DATETIME NOT NULL,
    precisa_soltar TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_posse_maquina (tipo, maquina_id),
    UNIQUE KEY uniq_posse_usuario (usuario_id),
    KEY idx_posse_nome (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Versão do banco: a marca da última atualização (src/Db.php). Banco sem ela é atualizado no próximo acesso.
CREATE TABLE IF NOT EXISTS schema_versao (
    id TINYINT UNSIGNED NOT NULL,
    hash CHAR(64) NOT NULL,
    atualizado_em DATETIME NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Britador secundário: cada britagem (início e fim) e os problemas avisados pelo operador dele.
CREATE TABLE IF NOT EXISTS britagens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario VARCHAR(40) NOT NULL,
    inicio DATETIME NOT NULL,
    fim DATETIME NULL,
    terminado_por VARCHAR(40) NULL,
    PRIMARY KEY (id),
    KEY idx_britagem_inicio (inicio),
    KEY idx_britagem_aberta (fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS britagem_problemas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario VARCHAR(40) NOT NULL,
    descricao VARCHAR(500) NOT NULL,
    parou TINYINT(1) NOT NULL DEFAULT 0,
    inicio DATETIME NOT NULL,
    resolvido_em DATETIME NULL,
    resolvido_por VARCHAR(40) NULL,
    PRIMARY KEY (id),
    KEY idx_britagem_problema_inicio (inicio),
    KEY idx_britagem_problema_aberto (resolvido_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Peças de cada máquina (filtros etc.) e os pedidos ao Compras. O item do pedido é uma cópia da peça
-- na hora do pedido: editar a peça depois não muda o histórico.
CREATE TABLE IF NOT EXISTS maquina_pecas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo ENUM('pa', 'retro', 'caminhao') NOT NULL,
    maquina_id INT UNSIGNED NOT NULL,
    funcao VARCHAR(120) NOT NULL,
    marca VARCHAR(80) NULL,
    codigo VARCHAR(80) NOT NULL,
    quantidade SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    foto VARCHAR(80) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_peca_maquina (tipo, maquina_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fotos de cada peça (até 5). A coluna maquina_pecas.foto é de antes e fica vazia: a foto vem para cá (Db::garantirFotosDasPecas).
CREATE TABLE IF NOT EXISTS peca_fotos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    peca_id INT UNSIGNED NOT NULL,
    arquivo VARCHAR(80) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_foto_peca (peca_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pecas_pedidos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo ENUM('pa', 'retro', 'caminhao') NOT NULL,
    maquina_id INT UNSIGNED NOT NULL,
    codigo_publico CHAR(32) NOT NULL,
    horimetro DECIMAL(10,1) NULL,
    pedido_por VARCHAR(40) NOT NULL,
    confirmado_com VARCHAR(40) NULL,
    observacoes VARCHAR(500) NULL,
    criado_em DATETIME NOT NULL,
    situacao ENUM('enviado', 'trocado', 'cancelado') NOT NULL DEFAULT 'enviado',
    trocado_em DATETIME NULL,
    trocado_horimetro DECIMAL(10,1) NULL,
    trocado_por VARCHAR(40) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_pedido_codigo (codigo_publico),
    KEY idx_pedido_maquina (tipo, maquina_id, criado_em),
    KEY idx_pedido_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pecas_pedido_itens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id INT UNSIGNED NOT NULL,
    peca_id INT UNSIGNED NULL,
    funcao VARCHAR(120) NOT NULL,
    marca VARCHAR(80) NULL,
    codigo VARCHAR(80) NOT NULL,
    quantidade SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY idx_item_peca (peca_id),
    CONSTRAINT fk_item_pedido FOREIGN KEY (pedido_id) REFERENCES pecas_pedidos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Máquina na oficina: cada estadia vira a ordem de serviço ao liberar (o que foi feito, quem fez, peças e custo).
-- Sem saída, a máquina está na oficina e ninguém pode assumi-la.
CREATE TABLE IF NOT EXISTS maquina_oficina (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo ENUM('pa', 'retro', 'caminhao') NOT NULL,
    maquina_id INT UNSIGNED NOT NULL,
    motivo VARCHAR(500) NOT NULL,
    problema_id INT UNSIGNED NULL,
    entrada DATETIME NOT NULL,
    entrada_por VARCHAR(40) NOT NULL,
    saida DATETIME NULL,
    saida_por VARCHAR(40) NULL,
    servico TEXT NULL,
    executor VARCHAR(120) NULL,
    pedido_id INT UNSIGNED NULL,
    custo DECIMAL(12,2) NULL,
    PRIMARY KEY (id),
    KEY idx_oficina_maquina (tipo, maquina_id, entrada),
    KEY idx_oficina_saida (saida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Envio das notas Pix e Transferência ao Fin Pro (src/FinPro.php): um registro por ordem, tentado de novo se falhar.
CREATE TABLE IF NOT EXISTS finpro_envios (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ordem_id INT UNSIGNED NOT NULL,
    situacao ENUM('pendente', 'enviado', 'erro') NOT NULL DEFAULT 'pendente',
    tentativas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ultimo_erro VARCHAR(300) NULL,
    enviado_em DATETIME NULL,
    proxima_tentativa DATETIME NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_finpro_ordem (ordem_id),
    KEY idx_finpro_pendente (situacao, proxima_tentativa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A hora da última rodada de reenvio ao Fin Pro (uma linha só): no máximo uma rodada a cada 5 minutos.
CREATE TABLE IF NOT EXISTS finpro_rodada (
    id TINYINT UNSIGNED NOT NULL,
    rodada_em DATETIME NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
