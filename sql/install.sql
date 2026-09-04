-- ============================================================
-- Proxcel - Script de Instalação do Banco de Dados
-- Versão: 1.0.0
-- ============================================================

CREATE DATABASE IF NOT EXISTS `proxcel_db` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `proxcel_db`;

-- ============================================================
-- TABELA: CLIENTES
-- ============================================================
CREATE TABLE IF NOT EXISTS `ctempresa_proxcel_clientes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `razao_social` VARCHAR(255) NOT NULL,
    `nome_fantasia` VARCHAR(255) DEFAULT NULL,
    `cnpj` VARCHAR(18) UNIQUE DEFAULT NULL,
    `cpf` VARCHAR(14) UNIQUE DEFAULT NULL,
    `email` VARCHAR(255) NOT NULL,
    `telefone` VARCHAR(20) DEFAULT NULL,
    `celular` VARCHAR(20) DEFAULT NULL,
    `cep` VARCHAR(10) DEFAULT NULL,
    `endereco` VARCHAR(255) DEFAULT NULL,
    `numero` VARCHAR(20) DEFAULT NULL,
    `complemento` VARCHAR(100) DEFAULT NULL,
    `bairro` VARCHAR(100) DEFAULT NULL,
    `cidade` VARCHAR(100) DEFAULT NULL,
    `estado` CHAR(2) DEFAULT NULL,
    `observacoes` TEXT DEFAULT NULL,
    `status` ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `criado_por` INT UNSIGNED DEFAULT NULL,
    `atualizado_por` INT UNSIGNED DEFAULT NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_razao_social` (`razao_social`),
    INDEX `idx_cnpj` (`cnpj`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: USUÁRIOS DO SISTEMA
-- ============================================================
CREATE TABLE IF NOT EXISTS `ctempresa_proxcel_usuarios` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nome` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `senha` VARCHAR(255) NOT NULL,
    `tipo` ENUM('admin','operador') NOT NULL DEFAULT 'operador',
    `avatar` VARCHAR(255) DEFAULT NULL,
    `telefone` VARCHAR(20) DEFAULT NULL,
    `status` ENUM('ativo','inativo','bloqueado') NOT NULL DEFAULT 'ativo',
    `ultimo_acesso` DATETIME DEFAULT NULL,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tipo` (`tipo`),
    INDEX `idx_status` (`status`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: USUÁRIOS DE CLIENTES
-- ============================================================
CREATE TABLE IF NOT EXISTS `ctempresa_proxcel_usuarios_clientes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cliente_id` INT UNSIGNED NOT NULL,
    `nome` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `senha` VARCHAR(255) NOT NULL,
    `telefone` VARCHAR(20) DEFAULT NULL,
    `status` ENUM('ativo','inativo','bloqueado') NOT NULL DEFAULT 'ativo',
    `ultimo_acesso` DATETIME DEFAULT NULL,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_cliente` (`cliente_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_email` (`email`),
    FOREIGN KEY (`cliente_id`) REFERENCES `ctempresa_proxcel_clientes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: CATEGORIAS DE DOCUMENTOS
-- ============================================================
CREATE TABLE IF NOT EXISTS `ctempresa_proxcel_categorias_documentos` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nome` VARCHAR(100) NOT NULL,
    `descricao` VARCHAR(255) DEFAULT NULL,
    `icone` VARCHAR(50) DEFAULT 'fa-file',
    `cor` VARCHAR(7) DEFAULT '#3EAF5C',
    `status` ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: DOCUMENTOS
-- ============================================================
CREATE TABLE IF NOT EXISTS `ctempresa_proxcel_documentos` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cliente_id` INT UNSIGNED NOT NULL,
    `categoria_id` INT UNSIGNED DEFAULT NULL,
    `nome` VARCHAR(255) NOT NULL,
    `descricao` TEXT DEFAULT NULL,
    `nome_original` VARCHAR(255) NOT NULL,
    `nome_arquivo` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `tamanho` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `extensao` VARCHAR(10) NOT NULL,
    `data_documento` DATE DEFAULT NULL,
    `status` ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `criado_por` INT UNSIGNED DEFAULT NULL,
    `criado_por_tipo` ENUM('sistema','cliente') DEFAULT 'sistema',
    INDEX `idx_cliente` (`cliente_id`),
    INDEX `idx_categoria` (`categoria_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_data` (`data_documento`),
    FOREIGN KEY (`cliente_id`) REFERENCES `ctempresa_proxcel_clientes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`categoria_id`) REFERENCES `ctempresa_proxcel_categorias_documentos`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: PERMISSÕES
-- ============================================================
CREATE TABLE IF NOT EXISTS `ctempresa_proxcel_permissoes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `modulo` VARCHAR(50) NOT NULL,
    `acao` VARCHAR(50) NOT NULL,
    `descricao` VARCHAR(255) NOT NULL,
    `chave` VARCHAR(100) NOT NULL UNIQUE,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_modulo` (`modulo`),
    INDEX `idx_chave` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: PERMISSÕES POR TIPO DE USUÁRIO
-- ============================================================
CREATE TABLE IF NOT EXISTS `ctempresa_proxcel_tipo_usuario_permissoes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tipo_usuario` ENUM('admin','operador','cliente') NOT NULL,
    `permissao_id` INT UNSIGNED NOT NULL,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_tipo_permissao` (`tipo_usuario`, `permissao_id`),
    FOREIGN KEY (`permissao_id`) REFERENCES `ctempresa_proxcel_permissoes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: PERMISSÕES POR USUÁRIO (OVERRIDE)
-- ============================================================
CREATE TABLE IF NOT EXISTS `ctempresa_proxcel_usuario_permissoes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT UNSIGNED NOT NULL,
    `usuario_tipo` ENUM('sistema','cliente') NOT NULL DEFAULT 'sistema',
    `permissao_id` INT UNSIGNED NOT NULL,
    `concedida` TINYINT(1) NOT NULL DEFAULT 1,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_usuario_permissao` (`usuario_id`, `usuario_tipo`, `permissao_id`),
    FOREIGN KEY (`permissao_id`) REFERENCES `ctempresa_proxcel_permissoes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: LOGS DE ACESSO (LGPD)
-- ============================================================
CREATE TABLE IF NOT EXISTS `ctempresa_proxcel_logs_acesso` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT UNSIGNED DEFAULT NULL,
    `usuario_tipo` ENUM('sistema','cliente') DEFAULT NULL,
    `usuario_nome` VARCHAR(255) DEFAULT NULL,
    `acao` VARCHAR(100) NOT NULL,
    `descricao` TEXT DEFAULT NULL,
    `ip` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `pagina` VARCHAR(500) DEFAULT NULL,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_usuario` (`usuario_id`, `usuario_tipo`),
    INDEX `idx_acao` (`acao`),
    INDEX `idx_ip` (`ip`),
    INDEX `idx_criado` (`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: LOGS DE ALTERAÇÃO (LGPD)
-- ============================================================
CREATE TABLE IF NOT EXISTS `ctempresa_proxcel_logs_alteracao` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT UNSIGNED DEFAULT NULL,
    `usuario_tipo` ENUM('sistema','cliente') DEFAULT NULL,
    `usuario_nome` VARCHAR(255) DEFAULT NULL,
    `tabela` VARCHAR(100) NOT NULL,
    `registro_id` INT UNSIGNED NOT NULL,
    `acao` ENUM('criar','editar','excluir') NOT NULL,
    `dados_anteriores` JSON DEFAULT NULL,
    `dados_novos` JSON DEFAULT NULL,
    `ip` VARCHAR(45) NOT NULL,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_usuario` (`usuario_id`, `usuario_tipo`),
    INDEX `idx_tabela` (`tabela`),
    INDEX `idx_registro` (`registro_id`),
    INDEX `idx_acao` (`acao`),
    INDEX `idx_criado` (`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: TENTATIVAS DE LOGIN
-- ============================================================
CREATE TABLE IF NOT EXISTS `ctempresa_proxcel_tentativas_login` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip` VARCHAR(45) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `sucesso` TINYINT(1) NOT NULL DEFAULT 0,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ip` (`ip`),
    INDEX `idx_criado` (`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABELA: TOKENS DE RECUPERAÇÃO DE SENHA
-- ============================================================
CREATE TABLE IF NOT EXISTS `ctempresa_proxcel_tokens_recuperacao` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL,
    `token` VARCHAR(255) NOT NULL,
    `usuario_tipo` ENUM('sistema','cliente') NOT NULL DEFAULT 'sistema',
    `usado` TINYINT(1) NOT NULL DEFAULT 0,
    `expira_em` DATETIME NOT NULL,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_token` (`token`),
    INDEX `idx_email` (`email`),
    INDEX `idx_expira` (`expira_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DADOS INICIAIS: CATEGORIAS DE DOCUMENTOS
-- ============================================================
INSERT INTO `ctempresa_proxcel_categorias_documentos` (`nome`, `descricao`, `icone`, `cor`) VALUES
('Demonstrativos Financeiros', 'Demonstrativos e relatórios financeiros periódicos', 'fa-file-invoice-dollar', '#10B981'),
('Contratos', 'Contratos e aditivos', 'fa-file-contract', '#3EAF5C'),
('Notas Fiscais', 'Notas fiscais e recibos', 'fa-file-invoice', '#3B82F6'),
('Relatórios', 'Relatórios diversos', 'fa-chart-bar', '#8B5CF6'),
('Certidões', 'Certidões e certificados', 'fa-certificate', '#F59E0B'),
('Documentos Pessoais', 'RG, CPF, CNH e outros', 'fa-id-card', '#EF4444'),
('Balanços', 'Balanços e demonstrativos', 'fa-balance-scale', '#06B6D4'),
('Impostos', 'Guias e comprovantes de impostos', 'fa-receipt', '#EC4899'),
('Outros', 'Documentos diversos', 'fa-folder', '#6B7280');

-- ============================================================
-- DADOS INICIAIS: PERMISSÕES
-- ============================================================
INSERT INTO `ctempresa_proxcel_permissoes` (`modulo`, `acao`, `descricao`, `chave`) VALUES
('clientes', 'visualizar', 'Visualizar clientes', 'clientes.visualizar'),
('clientes', 'criar', 'Criar clientes', 'clientes.criar'),
('clientes', 'editar', 'Editar clientes', 'clientes.editar'),
('clientes', 'excluir', 'Excluir clientes', 'clientes.excluir'),
('usuarios', 'visualizar', 'Visualizar usuários', 'usuarios.visualizar'),
('usuarios', 'criar', 'Criar usuários', 'usuarios.criar'),
('usuarios', 'editar', 'Editar usuários', 'usuarios.editar'),
('usuarios', 'excluir', 'Excluir usuários', 'usuarios.excluir'),
('usuarios_clientes', 'visualizar', 'Visualizar usuários de clientes', 'usuarios_clientes.visualizar'),
('usuarios_clientes', 'criar', 'Criar usuários de clientes', 'usuarios_clientes.criar'),
('usuarios_clientes', 'editar', 'Editar usuários de clientes', 'usuarios_clientes.editar'),
('usuarios_clientes', 'excluir', 'Excluir usuários de clientes', 'usuarios_clientes.excluir'),
('documentos', 'visualizar', 'Visualizar documentos', 'documentos.visualizar'),
('documentos', 'criar', 'Criar/importar documentos', 'documentos.criar'),
('documentos', 'editar', 'Editar documentos', 'documentos.editar'),
('documentos', 'excluir', 'Excluir documentos', 'documentos.excluir'),
('documentos', 'download', 'Download de documentos', 'documentos.download'),
('relatorios', 'visualizar', 'Visualizar relatórios', 'relatorios.visualizar'),
('relatorios', 'gerar', 'Gerar relatórios', 'relatorios.gerar'),
('configuracoes', 'visualizar', 'Visualizar configurações', 'configuracoes.visualizar'),
('configuracoes', 'editar', 'Editar configurações', 'configuracoes.editar'),
('logs', 'visualizar', 'Visualizar logs', 'logs.visualizar');

-- ============================================================
-- DADOS INICIAIS: PERMISSÕES POR TIPO (ADMIN = TUDO)
-- ============================================================
INSERT INTO `ctempresa_proxcel_tipo_usuario_permissoes` (`tipo_usuario`, `permissao_id`)
SELECT 'admin', `id` FROM `ctempresa_proxcel_permissoes`;

-- OPERADOR: tudo menos configurações e logs
INSERT INTO `ctempresa_proxcel_tipo_usuario_permissoes` (`tipo_usuario`, `permissao_id`)
SELECT 'operador', `id` FROM `ctempresa_proxcel_permissoes` 
WHERE `modulo` NOT IN ('configuracoes', 'logs');

-- CLIENTE: apenas visualizar documentos e download
INSERT INTO `ctempresa_proxcel_tipo_usuario_permissoes` (`tipo_usuario`, `permissao_id`)
SELECT 'cliente', `id` FROM `ctempresa_proxcel_permissoes` 
WHERE `chave` IN ('documentos.visualizar', 'documentos.download');

-- ============================================================
-- DADOS INICIAIS: USUÁRIO ADMINISTRADOR
-- Senha padrão: Proxcel@2024
-- ============================================================
INSERT INTO `ctempresa_proxcel_usuarios` (`nome`, `email`, `senha`, `tipo`, `status`) VALUES
('Administrador', 'admin@proxcel.com.br', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'ativo');

-- ============================================================
-- CLIENTE DE DEMONSTRAÇÃO
-- ============================================================
INSERT INTO `ctempresa_proxcel_clientes` (`razao_social`, `nome_fantasia`, `cnpj`, `email`, `telefone`, `cidade`, `estado`, `status`, `criado_por`) VALUES
('Empresa Demonstração LTDA', 'Demo Empresa', '00.000.000/0001-00', 'demo@empresa.com.br', '(11) 99999-9999', 'São Paulo', 'SP', 'ativo', 1);

-- Usuário do cliente demo (senha: Proxcel@2024)
INSERT INTO `ctempresa_proxcel_usuarios_clientes` (`cliente_id`, `nome`, `email`, `senha`, `status`) VALUES
(1, 'Usuário Demo', 'demo@proxcel.com.br', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ativo');
