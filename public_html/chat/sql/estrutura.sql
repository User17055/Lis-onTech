SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    nivel ENUM('admin', 'atendente') NOT NULL DEFAULT 'atendente',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contatos (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NULL,
    telefone VARCHAR(30) NOT NULL UNIQUE,
    wa_id VARCHAR(30) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS atendimentos (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    contato_id BIGINT NOT NULL,
    usuario_id INT NULL,
    protocolo VARCHAR(40) NOT NULL UNIQUE,
    status ENUM('aberto', 'em_atendimento', 'aguardando_cliente', 'fechado') NOT NULL DEFAULT 'aberto',
    assunto VARCHAR(150) NULL,
    janela_24h_ate DATETIME NULL,
    aberto_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fechado_em DATETIME NULL,
    ultima_interacao_em DATETIME NULL,
    FOREIGN KEY (contato_id) REFERENCES contatos(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_status (status),
    INDEX idx_contato (contato_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mensagens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    atendimento_id BIGINT NOT NULL,
    wa_message_id VARCHAR(150) NULL UNIQUE,
    meta_status ENUM('recebida', 'enviada', 'entregue', 'lida', 'erro') NULL,
    direcao ENUM('entrada', 'saida') NOT NULL,
    remetente_tipo ENUM('cliente', 'atendente', 'sistema') NOT NULL,
    usuario_id INT NULL,
    tipo ENUM('texto', 'template', 'imagem', 'audio', 'video', 'documento', 'desconhecido') NOT NULL DEFAULT 'texto',
    conteudo TEXT NULL,
    erro_meta TEXT NULL,
    payload_json LONGTEXT NULL,
    criada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (atendimento_id) REFERENCES atendimentos(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_atendimento (atendimento_id),
    INDEX idx_criada_em (criada_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS templates_whatsapp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL UNIQUE,
    idioma VARCHAR(10) NOT NULL DEFAULT 'pt_BR',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO usuarios (nome, email, senha_hash, nivel)
SELECT 'Administrador', 'admin@admin.com', '$2y$12$CjQsvIYVCl.ONoqi.CUFqe5xuQuchbiMJpjcA9AgEGkVwwj1xIw8O', 'admin'
WHERE NOT EXISTS (
    SELECT 1 FROM usuarios WHERE email = 'admin@admin.com'
);

INSERT INTO templates_whatsapp (nome, idioma, ativo)
SELECT 'hello_world', 'en_US', 1
WHERE NOT EXISTS (
    SELECT 1 FROM templates_whatsapp WHERE nome = 'hello_world'
);

SET FOREIGN_KEY_CHECKS = 1;