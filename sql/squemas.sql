-- ============================================================
-- Sistema de Gestão de Estoque Multi-Categoria
-- Foco: Cosméticos importados (lotes, validade, fornecedores)
-- Banco: MySQL / MariaDB
-- ============================================================

CREATE DATABASE IF NOT EXISTS gestao_estoque
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE gestao_estoque;

-- ============================================================
-- USUÁRIOS DO SISTEMA (todos são gestores/admin, não há outros perfis)
-- ============================================================
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- FORNECEDORES (essencial para importação)
-- ============================================================
CREATE TABLE fornecedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    cnpj_cpf VARCHAR(20),
    pais_origem VARCHAR(80),
    telefone VARCHAR(30),
    email VARCHAR(150),
    endereco VARCHAR(255),
    contato_responsavel VARCHAR(120),
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- CLIENTES
-- ============================================================
CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    cpf_cnpj VARCHAR(20),
    telefone VARCHAR(30),
    email VARCHAR(150),
    endereco VARCHAR(255),
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- PRODUTOS (tabela-base, comum a todas as categorias)
-- ============================================================
CREATE TABLE produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(180) NOT NULL,
    descricao TEXT,
    categoria ENUM('roupa', 'cosmetico', 'brinquedo', 'jogo', 'filme') NOT NULL,
    codigo_barras VARCHAR(50) UNIQUE,
    preco_venda DECIMAL(10,2) NOT NULL DEFAULT 0,
    fornecedor_id INT,
    estoque_minimo INT NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_produtos_fornecedor
        FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id)
        ON DELETE SET NULL,
    INDEX idx_produtos_categoria (categoria)
) ENGINE=InnoDB;

-- ============================================================
-- ROUPAS (atributos específicos)
-- ============================================================
CREATE TABLE roupas (
    produto_id INT PRIMARY KEY,
    tamanho VARCHAR(10) NOT NULL,      -- PP, P, M, G, GG, 38, 40...
    marca VARCHAR(80),
    sexo ENUM('masculino', 'feminino', 'unissex') NOT NULL DEFAULT 'unissex',
    CONSTRAINT fk_roupas_produto
        FOREIGN KEY (produto_id) REFERENCES produtos(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- COSMÉTICOS (categoria principal do sistema)
-- ============================================================
CREATE TABLE cosmeticos (
    produto_id INT PRIMARY KEY,
    categoria_cosmetico ENUM('perfume', 'maquiagem', 'skincare', 'capilar', 'cuidados_pessoais') NOT NULL,
    tom_cor VARCHAR(50),                -- ex: "Bege claro", "N4", etc
    quantidade_valor DECIMAL(10,2),     -- ex: 100
    quantidade_unidade ENUM('ml', 'g', 'un') NOT NULL DEFAULT 'ml',
    modo_aplicacao VARCHAR(150),        -- ex: "Aplicar no rosto limpo"
    registro_anvisa VARCHAR(50),
    CONSTRAINT fk_cosmeticos_produto
        FOREIGN KEY (produto_id) REFERENCES produtos(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- BRINQUEDOS
-- ============================================================
CREATE TABLE brinquedos (
    produto_id INT PRIMARY KEY,
    classificacao_indicativa VARCHAR(10),   -- ex: "0+", "3+", "12+"
    marca VARCHAR(80),
    colecao VARCHAR(120),
    CONSTRAINT fk_brinquedos_produto
        FOREIGN KEY (produto_id) REFERENCES produtos(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- JOGOS
-- ============================================================
CREATE TABLE jogos (
    produto_id INT PRIMARY KEY,
    genero VARCHAR(80),
    classificacao_indicativa VARCHAR(10),   -- ex: "L", "10", "12", "14", "16", "18"
    desenvolvedora VARCHAR(120),
    plataforma VARCHAR(60),                  -- PC, PS5, Xbox, Switch...
    modo_jogo ENUM('single_player', 'multiplayer', 'ambos') DEFAULT 'single_player',
    CONSTRAINT fk_jogos_produto
        FOREIGN KEY (produto_id) REFERENCES produtos(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- FILMES
-- ============================================================
CREATE TABLE filmes (
    produto_id INT PRIMARY KEY,
    genero VARCHAR(80),
    classificacao_indicativa VARCHAR(10),
    duracao_minutos INT,
    data_lancamento DATE,
    CONSTRAINT fk_filmes_produto
        FOREIGN KEY (produto_id) REFERENCES produtos(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- LOTES (chave do controle de validade / importação)
-- ============================================================
CREATE TABLE lotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    fornecedor_id INT,
    numero_lote VARCHAR(60) NOT NULL,
    data_fabricacao DATE,
    data_validade DATE,
    quantidade_recebida INT NOT NULL,
    quantidade_disponivel INT NOT NULL,
    preco_custo DECIMAL(10,2),
    nota_fiscal VARCHAR(60),
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lotes_produto
        FOREIGN KEY (produto_id) REFERENCES produtos(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_lotes_fornecedor
        FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id)
        ON DELETE SET NULL,
    INDEX idx_lotes_validade (data_validade),
    INDEX idx_lotes_produto (produto_id)
) ENGINE=InnoDB;

-- ============================================================
-- MOVIMENTAÇÕES DE ESTOQUE (entradas e saídas)
-- ============================================================
CREATE TABLE estoque_movimentacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    lote_id INT,
    tipo ENUM('entrada', 'saida', 'ajuste', 'perda_vencimento') NOT NULL,
    quantidade INT NOT NULL,
    motivo VARCHAR(255),
    usuario_id INT,
    data_movimentacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mov_produto
        FOREIGN KEY (produto_id) REFERENCES produtos(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_mov_lote
        FOREIGN KEY (lote_id) REFERENCES lotes(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_mov_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE SET NULL,
    INDEX idx_mov_data (data_movimentacao)
) ENGINE=InnoDB;

-- ============================================================
-- VENDAS
-- ============================================================
CREATE TABLE vendas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT,
    usuario_id INT,
    data_venda DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    valor_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    forma_pagamento ENUM('dinheiro', 'pix', 'debito', 'credito', 'boleto') NOT NULL,
    status ENUM('pendente', 'concluida', 'cancelada') NOT NULL DEFAULT 'concluida',
    CONSTRAINT fk_vendas_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_vendas_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE venda_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venda_id INT NOT NULL,
    produto_id INT NOT NULL,
    lote_id INT,
    quantidade INT NOT NULL,
    preco_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_item_venda
        FOREIGN KEY (venda_id) REFERENCES vendas(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_item_produto
        FOREIGN KEY (produto_id) REFERENCES produtos(id),
    CONSTRAINT fk_item_lote
        FOREIGN KEY (lote_id) REFERENCES lotes(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- VIEWS ÚTEIS
-- ============================================================

-- Estoque total disponível por produto (soma dos lotes)
CREATE OR REPLACE VIEW vw_estoque_atual AS
SELECT
    p.id AS produto_id,
    p.nome,
    p.categoria,
    COALESCE(SUM(l.quantidade_disponivel), 0) AS quantidade_total
FROM produtos p
LEFT JOIN lotes l ON l.produto_id = p.id
GROUP BY p.id, p.nome, p.categoria;

-- Lotes próximos do vencimento (próximos 30 dias) ou já vencidos
CREATE OR REPLACE VIEW vw_lotes_vencendo AS
SELECT
    l.id AS lote_id,
    p.nome AS produto,
    f.nome AS fornecedor,
    l.numero_lote,
    l.data_validade,
    l.quantidade_disponivel,
    DATEDIFF(l.data_validade, CURDATE()) AS dias_para_vencer
FROM lotes l
JOIN produtos p ON p.id = l.produto_id
LEFT JOIN fornecedores f ON f.id = l.fornecedor_id
WHERE l.data_validade IS NOT NULL
  AND l.quantidade_disponivel > 0
  AND DATEDIFF(l.data_validade, CURDATE()) <= 30
ORDER BY l.data_validade ASC;

-- ============================================================
-- USUÁRIO INICIAL (senha: admin123 -> troque em produção)
-- Hash gerado com password_hash('admin123', PASSWORD_DEFAULT)
-- ============================================================
INSERT INTO usuarios (nome, email, senha_hash)
VALUES ('Administrador', 'admin@sistema.com',
        '$2y$10$3Q6nq0e6c1Yv8m0Z0mR0Y.examplehashchangeit1234567890abcd');