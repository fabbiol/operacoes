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
    foto VARCHAR(80) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_usuario_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS materiais (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    material VARCHAR(120) NOT NULL,
    observacoes TEXT NULL,
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
    foto VARCHAR(80) NULL,
    observacoes TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_pa_maquina (maquina)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fila_carregamentos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ordem VARCHAR(20) NOT NULL,
    data DATETIME NOT NULL,
    cliente VARCHAR(160) NOT NULL,
    pedido VARCHAR(60) NOT NULL,
    nf VARCHAR(60) NOT NULL,
    material_id INT UNSIGNED NOT NULL,
    quantidade_autorizada DECIMAL(12,2) NOT NULL,
    unidade ENUM('t', 'm3', 'un') NOT NULL,
    pa_carregadeira_id INT UNSIGNED NULL,
    retroescavadeira_id INT UNSIGNED NULL,
    operador VARCHAR(120) NULL,
    status ENUM(
        'aguardando_autorizacao',
        'liberado',
        'carregando',
        'aguardando_conferencia',
        'concluido',
        'cancelado'
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
    CONSTRAINT fk_fila_material FOREIGN KEY (material_id) REFERENCES materiais (id),
    CONSTRAINT fk_fila_pa FOREIGN KEY (pa_carregadeira_id) REFERENCES pas_carregadeiras (id)
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
    encerramento VARCHAR(160) NULL,
    encerramento_ciente_em DATETIME NULL,
    encerramento_ciente_por VARCHAR(120) NULL,
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
