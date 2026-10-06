-- BPMS — estrutura do banco (MySQL 8 / MariaDB 10.6+)
-- Rodar com: php database/instalar.php  (ou importar este arquivo no phpMyAdmin)

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  senha_hash VARCHAR(255) NOT NULL,
  perfil ENUM('admin','usuario') NOT NULL DEFAULT 'usuario',
  cargo VARCHAR(100) NULL,
  gestor_id INT UNSIGNED NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  sessao_versao INT UNSIGNED NOT NULL DEFAULT 1,
  ultimo_acesso DATETIME NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_usuarios_gestor FOREIGN KEY (gestor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS grupos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(80) NOT NULL UNIQUE,
  descricao VARCHAR(255) NULL,
  cor VARCHAR(7) NOT NULL DEFAULT '#00995d',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuario_grupos (
  usuario_id INT UNSIGNED NOT NULL,
  grupo_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (usuario_id, grupo_id),
  CONSTRAINT fk_ug_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_ug_grupo FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Definição de processo (o "fluxo"); as versões guardam o desenho BPMN
CREATE TABLE IF NOT EXISTS processos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  sigla VARCHAR(10) NOT NULL,
  descricao VARCHAR(500) NULL,
  categoria VARCHAR(80) NULL,
  cor VARCHAR(7) NOT NULL DEFAULT '#00995d',
  icone VARCHAR(30) NOT NULL DEFAULT 'fluxo',
  titulo_modelo VARCHAR(200) NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  versao_publicada_id INT UNSIGNED NULL,
  criado_por INT UNSIGNED NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_proc_criador FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS processo_versoes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  processo_id INT UNSIGNED NOT NULL,
  numero INT UNSIGNED NOT NULL,
  xml MEDIUMTEXT NOT NULL,
  config LONGTEXT NOT NULL,
  status ENUM('rascunho','publicado','arquivado') NOT NULL DEFAULT 'rascunho',
  criado_por INT UNSIGNED NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  publicado_em DATETIME NULL,
  publicado_por INT UNSIGNED NULL,
  UNIQUE KEY uq_versao (processo_id, numero),
  CONSTRAINT fk_versao_proc FOREIGN KEY (processo_id) REFERENCES processos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quais grupos podem iniciar o processo (nenhuma linha = todos)
CREATE TABLE IF NOT EXISTS processo_iniciadores (
  processo_id INT UNSIGNED NOT NULL,
  grupo_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (processo_id, grupo_id),
  CONSTRAINT fk_pi_proc FOREIGN KEY (processo_id) REFERENCES processos(id) ON DELETE CASCADE,
  CONSTRAINT fk_pi_grupo FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Execução
CREATE TABLE IF NOT EXISTS instancias (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  processo_id INT UNSIGNED NOT NULL,
  versao_id INT UNSIGNED NOT NULL,
  codigo VARCHAR(30) NOT NULL UNIQUE,
  titulo VARCHAR(200) NOT NULL,
  status ENUM('andamento','concluida','cancelada','erro') NOT NULL DEFAULT 'andamento',
  variaveis LONGTEXT NOT NULL,
  iniciado_por INT UNSIGNED NULL,
  iniciado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  concluido_em DATETIME NULL,
  KEY ix_inst_status (status),
  KEY ix_inst_iniciador (iniciado_por),
  CONSTRAINT fk_inst_proc FOREIGN KEY (processo_id) REFERENCES processos(id),
  CONSTRAINT fk_inst_versao FOREIGN KEY (versao_id) REFERENCES processo_versoes(id),
  CONSTRAINT fk_inst_usuario FOREIGN KEY (iniciado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tokens: onde a execução está no diagrama
CREATE TABLE IF NOT EXISTS tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  instancia_id INT UNSIGNED NOT NULL,
  elemento_id VARCHAR(100) NOT NULL,
  status ENUM('ativo','aguardando','concluido','cancelado','erro') NOT NULL DEFAULT 'ativo',
  vence_em DATETIME NULL,
  erro TEXT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  concluido_em DATETIME NULL,
  KEY ix_tok_inst (instancia_id, status),
  KEY ix_tok_timer (status, vence_em),
  CONSTRAINT fk_tok_inst FOREIGN KEY (instancia_id) REFERENCES instancias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tarefas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  instancia_id INT UNSIGNED NOT NULL,
  token_id INT UNSIGNED NOT NULL,
  elemento_id VARCHAR(100) NOT NULL,
  nome VARCHAR(200) NOT NULL,
  status ENUM('aberta','concluida','cancelada') NOT NULL DEFAULT 'aberta',
  usuario_id INT UNSIGNED NULL,
  grupo_id INT UNSIGNED NULL,
  prioridade ENUM('baixa','normal','alta') NOT NULL DEFAULT 'normal',
  prazo DATETIME NULL,
  dados LONGTEXT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  assumida_em DATETIME NULL,
  concluida_em DATETIME NULL,
  concluida_por INT UNSIGNED NULL,
  KEY ix_tar_usuario (usuario_id, status),
  KEY ix_tar_grupo (grupo_id, status),
  KEY ix_tar_inst (instancia_id),
  CONSTRAINT fk_tar_inst FOREIGN KEY (instancia_id) REFERENCES instancias(id) ON DELETE CASCADE,
  CONSTRAINT fk_tar_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  CONSTRAINT fk_tar_grupo FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE SET NULL,
  CONSTRAINT fk_tar_concl FOREIGN KEY (concluida_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Atividade atribuída a várias pessoas: quem assumir primeiro fica com ela
CREATE TABLE IF NOT EXISTS tarefa_candidatos (
  tarefa_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (tarefa_id, usuario_id),
  KEY ix_tc_usuario (usuario_id),
  CONSTRAINT fk_tc_tarefa FOREIGN KEY (tarefa_id) REFERENCES tarefas(id) ON DELETE CASCADE,
  CONSTRAINT fk_tc_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS historico (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  instancia_id INT UNSIGNED NOT NULL,
  tipo VARCHAR(30) NOT NULL,
  elemento_id VARCHAR(100) NULL,
  descricao VARCHAR(500) NOT NULL,
  usuario_id INT UNSIGNED NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_hist_inst (instancia_id, id),
  CONSTRAINT fk_hist_inst FOREIGN KEY (instancia_id) REFERENCES instancias(id) ON DELETE CASCADE,
  CONSTRAINT fk_hist_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comentarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  instancia_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NULL,
  texto TEXT NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_com_inst (instancia_id),
  CONSTRAINT fk_com_inst FOREIGN KEY (instancia_id) REFERENCES instancias(id) ON DELETE CASCADE,
  CONSTRAINT fk_com_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS anexos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  instancia_id INT UNSIGNED NULL,
  tarefa_id INT UNSIGNED NULL,
  campo VARCHAR(60) NULL,
  nome_original VARCHAR(255) NOT NULL,
  arquivo VARCHAR(80) NOT NULL,
  mime VARCHAR(100) NOT NULL,
  tamanho INT UNSIGNED NOT NULL,
  sha256 CHAR(64) NOT NULL,
  enviado_por INT UNSIGNED NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_anx_inst (instancia_id),
  CONSTRAINT fk_anx_inst FOREIGN KEY (instancia_id) REFERENCES instancias(id) ON DELETE CASCADE,
  CONSTRAINT fk_anx_usuario FOREIGN KEY (enviado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notificacoes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  titulo VARCHAR(160) NOT NULL,
  texto VARCHAR(500) NULL,
  link VARCHAR(255) NULL,
  lida TINYINT(1) NOT NULL DEFAULT 0,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_not_usuario (usuario_id, lida),
  CONSTRAINT fk_not_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_tentativas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  email VARCHAR(160) NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_lt (ip, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuracoes (
  chave VARCHAR(60) PRIMARY KEY,
  valor TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO configuracoes (chave, valor) VALUES
  ('empresa', 'Minha Empresa'),
  ('email_remetente', ''),
  ('formatos_anexo', 'pdf,png,jpg,jpeg,docx,xlsx,xml,txt,csv'),
  ('tamanho_max_mb', '10'),
  ('expediente_inicio', '08:00'),
  ('expediente_fim', '18:00'),
  ('feriados', '');
