-- Atividade atribuída a várias pessoas: quem assumir primeiro fica com ela
CREATE TABLE IF NOT EXISTS tarefa_candidatos (
  tarefa_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (tarefa_id, usuario_id),
  KEY ix_tc_usuario (usuario_id),
  CONSTRAINT fk_tc_tarefa FOREIGN KEY (tarefa_id) REFERENCES tarefas(id) ON DELETE CASCADE,
  CONSTRAINT fk_tc_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO configuracoes (chave, valor) VALUES
  ('expediente_inicio', '08:00'),
  ('expediente_fim', '18:00'),
  ('feriados', '');
